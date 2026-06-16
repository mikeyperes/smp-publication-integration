<?php
namespace smp_publication_integration\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PluginRegistry {
    public static function catalog(): array {
        return [
            'hws-base-tools/hws-base-tools.php' => [ 'label' => 'HWS Base Tools', 'type' => 'required', 'repo' => 'mikeyperes/hws-base-tools', 'starter' => 'hws-base-tools.php' ],
            'smp-publication-integration/smp-publication-integration.php' => [ 'label' => 'SMP Publication Integration', 'type' => 'current', 'repo' => 'mikeyperes/smp-publication-integration', 'starter' => 'smp-publication-integration.php' ],
            'advanced-custom-fields-pro/acf.php' => [ 'label' => 'Advanced Custom Fields Pro', 'type' => 'recommended', 'repo' => '', 'starter' => 'acf.php' ],
            'hexa-pr-wire-distributor/hexa-pr-wire-distributor.php' => [ 'label' => 'Hexa PR Wire Distributor', 'type' => 'recommended', 'repo' => 'mikeyperes/hexa-pr-wire-distributor', 'starter' => 'hexa-pr-wire-distributor.php' ],
            'smp-core-podcast-integration/initialization.php' => [ 'label' => 'SMP Core Podcast Integration', 'type' => 'recommended', 'repo' => 'mikeyperes/smp-core-podcast-integration', 'starter' => 'initialization.php' ],
            'smp-verified-profiles/initialization.php' => [ 'label' => 'SMP Verified Profiles', 'type' => 'recommended', 'repo' => 'mikeyperes/smp-verified-profiles', 'starter' => 'initialization.php' ],
            'smp-contributor-network/initialization.php' => [ 'label' => 'SMP Contributor Network', 'type' => 'recommended', 'repo' => 'mikeyperes/smp-contributor-network', 'starter' => 'initialization.php' ],
            'sfpf-person-profile-integration/initialization.php' => [ 'label' => 'SFPF Person Profile Integration', 'type' => 'recommended', 'repo' => 'mikeyperes/sfpf-person-profile-integration', 'starter' => 'initialization.php' ],
            'seo-by-rank-math/seo-by-rank-math.php' => [ 'label' => 'Rank Math SEO', 'type' => 'recommended', 'repo' => '', 'starter' => 'seo-by-rank-math.php' ],
            'seo-by-rank-math-pro/rank-math-pro.php' => [ 'label' => 'Rank Math SEO PRO', 'type' => 'recommended', 'repo' => '', 'starter' => 'rank-math-pro.php' ],
            'litespeed-cache/litespeed-cache.php' => [ 'label' => 'LiteSpeed Cache', 'type' => 'recommended', 'repo' => '', 'starter' => 'litespeed-cache.php' ],
        ];
    }

    public static function info( string $plugin_file ): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $catalog = self::catalog();
        $installed = get_plugins();
        $updates = get_site_transient( 'update_plugins' );
        $data = $installed[ $plugin_file ] ?? [];
        $config = $catalog[ $plugin_file ] ?? [ 'label' => $plugin_file, 'type' => 'external', 'repo' => '', 'starter' => basename( $plugin_file ) ];
        $response = is_object( $updates ) && isset( $updates->response[ $plugin_file ] ) ? $updates->response[ $plugin_file ] : null;

        return [
            'plugin_file' => $plugin_file,
            'label' => $config['label'],
            'type' => $config['type'],
            'github_repo' => $config['repo'],
            'installed' => isset( $installed[ $plugin_file ] ),
            'active' => Dependencies::plugin_active( $plugin_file ),
            'version' => $data['Version'] ?? '',
            'name' => $data['Name'] ?? $config['label'],
            'author' => $data['Author'] ?? '',
            'update_available' => (bool) $response,
            'update_version' => $response->new_version ?? '',
            'github_version' => $config['repo'] ? self::github_latest_version( $config['repo'], $config['starter'] ) : '',
        ];
    }

    public static function all(): array {
        $items = [];
        foreach ( array_keys( self::catalog() ) as $plugin_file ) {
            $items[ $plugin_file ] = self::info( $plugin_file );
        }
        return $items;
    }

    public static function github_latest_version( string $repo, string $starter ): string {
        $cache_key = 'smpi_github_version_' . md5( $repo . '|' . $starter );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return (string) $cached;
        }
        $response = wp_remote_get( 'https://raw.githubusercontent.com/' . $repo . '/main/' . ltrim( $starter, '/' ), [ 'timeout' => 8 ] );
        if ( is_wp_error( $response ) ) {
            set_transient( $cache_key, '', 15 * MINUTE_IN_SECONDS );
            return '';
        }
        $version = '';
        if ( preg_match( '/^\s*\*?\s*Version:\s*([^\r\n]+)/mi', wp_remote_retrieve_body( $response ), $matches ) ) {
            $version = trim( $matches[1] );
        }
        set_transient( $cache_key, $version, HOUR_IN_SECONDS );
        return $version;
    }

    public static function perform_action( string $plugin_file, string $operation ) {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return new \WP_Error( 'smpi_forbidden', 'Permission denied.' );
        }
        $catalog = self::catalog();
        if ( ! isset( $catalog[ $plugin_file ] ) ) {
            return new \WP_Error( 'smpi_unknown_plugin', 'Unknown plugin.' );
        }
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        if ( 'activate' === $operation ) {
            return activate_plugin( $plugin_file );
        }
        if ( 'deactivate' === $operation ) {
            deactivate_plugins( $plugin_file );
            return true;
        }
        if ( 'delete' === $operation ) {
            if ( is_plugin_active( $plugin_file ) ) {
                return new \WP_Error( 'smpi_active_delete', 'Deactivate the plugin before deleting it.' );
            }
            return delete_plugins( [ $plugin_file ] );
        }
        if ( 'update' === $operation ) {
            $repo = $catalog[ $plugin_file ]['repo'];
            if ( ! $repo ) {
                return new \WP_Error( 'smpi_no_repo', 'No GitHub repository is configured for this plugin.' );
            }
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            $skin = new \Automatic_Upgrader_Skin();
            $upgrader = new \Plugin_Upgrader( $skin );
            return $upgrader->install( 'https://github.com/' . $repo . '/archive/refs/heads/main.zip', [ 'overwrite_package' => true ] );
        }
        return new \WP_Error( 'smpi_bad_action', 'Unsupported action.' );
    }
}