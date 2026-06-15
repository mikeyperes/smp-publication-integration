<?php
namespace smp_publication_integration;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

function init_github_updater( array $config ) {
    if ( empty( $config["plugin_file"] ) || empty( $config["github_repo"] ) ) {
        return false;
    }

    $hws_updater = trailingslashit( WP_PLUGIN_DIR ) . "hws-base-tools/GitHub_Updater.php";
    if ( ! function_exists( "\hws_base_tools\hws_init_github_updater" ) && is_readable( $hws_updater ) ) {
        require_once $hws_updater;
    }

    $hws_instance = false;
    if ( function_exists( "\hws_base_tools\hws_init_github_updater" ) ) {
        $hws_instance = \hws_base_tools\hws_init_github_updater( $config );
    }

    smp_register_api_update_filter( $config );

    return $hws_instance;
}

function smp_register_api_update_filter( array $config ): void {
    static $registered = [];

    $plugin_file = (string) $config["plugin_file"];
    $plugin_basename = plugin_basename( $plugin_file );

    if ( isset( $registered[ $plugin_basename ] ) ) {
        return;
    }

    $registered[ $plugin_basename ] = true;

    add_filter(
        "pre_set_site_transient_update_plugins",
        static function ( $transient ) use ( $config, $plugin_basename ) {
            if ( empty( $transient->checked ) ) {
                return $transient;
            }

            if ( ! function_exists( "get_plugin_data" ) ) {
                require_once ABSPATH . "wp-admin/includes/plugin.php";
            }

            $plugin_data = get_plugin_data( $config["plugin_file"], false, false );
            $current_version = $plugin_data["Version"] ?? "0.0.0";
            $remote_version = smp_github_remote_version( $config );

            if ( "" === $remote_version || ! version_compare( $remote_version, $current_version, ">" ) ) {
                return $transient;
            }

            $repo = trim( (string) $config["github_repo"], "/" );
            $branch = $config["github_branch"] ?? "main";
            $folder = ! empty( $config["proper_folder_name"] ) ? trim( (string) $config["proper_folder_name"], "/" ) : dirname( $plugin_basename );
            $github_url = "https://github.com/" . $repo;

            $transient->response[ $plugin_basename ] = (object) [
                "id"          => $github_url,
                "slug"        => $folder,
                "plugin"      => $plugin_basename,
                "new_version" => $remote_version,
                "url"         => $github_url,
                "package"     => $github_url . "/archive/" . rawurlencode( $branch ) . ".zip",
                "tested"      => $config["tested"] ?? "7.0",
                "requires"    => $config["requires"] ?? "5.0",
            ];

            return $transient;
        }
    );
}

function smp_github_remote_version( array $config ): string {
    $repo = trim( (string) $config["github_repo"], "/" );
    $branch = $config["github_branch"] ?? "main";
    $starter_file = basename( (string) $config["plugin_file"] );
    $url = "https://api.github.com/repos/" . $repo . "/contents/" . rawurlencode( $starter_file ) . "?ref=" . rawurlencode( $branch );

    $response = wp_remote_get(
        $url,
        [
            "timeout" => $config["timeout"] ?? 15,
            "headers" => [
                "Accept"     => "application/vnd.github+json",
                "User-Agent" => "SMPPublicationIntegrationUpdater/0.3",
            ],
        ]
    );

    if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        return "";
    }

    $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( empty( $data["content"] ) || ! is_string( $data["content"] ) ) {
        return "";
    }

    $contents = base64_decode( $data["content"], true );
    if ( false === $contents ) {
        return "";
    }

    return smp_parse_plugin_header( $contents, "Version" );
}

function smp_parse_plugin_header( string $contents, string $header ): string {
    foreach ( preg_split( "/\r\n|\r|\n/", $contents ) as $line ) {
        $line = ltrim( $line, " \t/*#@" );
        if ( 0 === stripos( $line, $header . ":" ) ) {
            return trim( substr( $line, strlen( $header ) + 1 ) );
        }
    }

    return "";
}
