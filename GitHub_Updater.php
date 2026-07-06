<?php
namespace smp_publication_integration;

use Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater;
use Hexa\PluginCore\PluginUpdates\GitHubVersionClient;
use Hexa\PluginCore\PluginUpdates\UpdaterConfig;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

function init_github_updater( array $config ) {
    if ( empty( $config["plugin_file"] ) || empty( $config["github_repo"] ) ) {
        return false;
    }

    $updater_config = smp_github_updater_config_from_legacy_args( $config );
    ( new GitHubPluginUpdater( $updater_config ) )->register();

    return $updater_config;
}

function smp_github_updater_config_from_legacy_args( array $config ): UpdaterConfig {
    $plugin_file = (string) $config["plugin_file"];
    $basename    = function_exists( "plugin_basename" ) ? plugin_basename( $plugin_file ) : basename( dirname( $plugin_file ) ) . "/" . basename( $plugin_file );
    $folder      = ! empty( $config["proper_folder_name"] ) ? trim( (string) $config["proper_folder_name"], "/" ) : dirname( $basename );

    return UpdaterConfig::from_plugin_file(
        $plugin_file,
        (string) $config["github_repo"],
        [
            "plugin_slug"               => $folder,
            "proper_folder_name"        => $folder,
            "runtime_folder_name"       => dirname( $basename ),
            "plugin_basename"           => $basename,
            "canonical_plugin_basename" => $folder . "/" . basename( $plugin_file ),
            "plugin_starter_file"       => basename( $plugin_file ),
            "github_branch"             => $config["github_branch"] ?? "main",
            "requires"                  => $config["requires"] ?? "5.0",
            "tested"                    => $config["tested"] ?? "7.0",
            "requires_php"              => $config["requires_php"] ?? "",
            "timeout"                   => $config["timeout"] ?? 15,
            "ajax_action_prefix"        => $config["ajax_action_prefix"] ?? "smpi_core_updater",
            "progress_key"              => $config["progress_key"] ?? "smpi_core_update_progress",
            "nonce_action"              => $config["nonce_action"] ?? \smp_publication_integration\Admin\Ajax::NONCE,
            "nonce_param"               => $config["nonce_param"] ?? "nonce",
        ]
    );
}

function smp_register_api_update_filter( array $config ): void {
    init_github_updater( $config );
}

function smp_github_remote_version( array $config ): string {
    $updater_config = smp_github_updater_config_from_legacy_args( $config );
    $version        = ( new GitHubVersionClient( $updater_config ) )->remote_version( true );

    return is_string( $version ) ? $version : "";
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
