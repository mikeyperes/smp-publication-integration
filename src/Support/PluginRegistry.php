<?php
namespace smp_publication_integration\Support;

use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;
use Hexa\PluginCore\PluginUpdates\DirectPluginInstaller;
use Hexa\PluginCore\PluginUpdates\GitHubVersionClient;
use Hexa\PluginCore\PluginUpdates\UpdaterConfig;

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
        $core_status = PluginProvisioner::plugin_status_by_file( $plugin_file );

        return [
            'plugin_file' => $plugin_file,
            'label' => $config['label'],
            'type' => $config['type'],
            'github_repo' => $config['repo'],
            'installed' => (bool) $core_status['installed'],
            'active' => (bool) $core_status['active'],
            'version' => $core_status['version'] ?? ( $data['Version'] ?? '' ),
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
        $cache_key = self::github_version_cache_key( $repo, $starter );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return (string) $cached;
        }

        self::schedule_github_version_refresh( $repo, $starter );
        return '';
    }

    public static function refresh_github_version( string $repo, string $starter ): void {
        $cache_key = self::github_version_cache_key( $repo, $starter );
        $version = self::fetch_github_latest_version( $repo, $starter );
        set_transient( $cache_key, $version, HOUR_IN_SECONDS );
        delete_transient( self::github_version_lock_key( $repo, $starter ) );
    }

    private static function fetch_github_latest_version( string $repo, string $starter ): string {
        $slug = basename( UpdaterConfig::normalize_github_repo( $repo ) );
        $updater_config = UpdaterConfig::from_slug_and_github_url(
            $slug,
            $repo,
            [
                'plugin_starter_file' => basename( $starter ),
                'plugin_name'         => $slug,
                'version'             => '0.0.0',
                'github_branch'       => 'main',
                'timeout'             => 5,
            ]
        );
        $version = ( new GitHubVersionClient( $updater_config ) )->remote_version( true );

        return is_string( $version ) ? $version : '';
    }

    private static function schedule_github_version_refresh( string $repo, string $starter ): void {
        $lock_key = self::github_version_lock_key( $repo, $starter );
        if ( false !== get_transient( $lock_key ) ) {
            return;
        }

        if ( ! wp_next_scheduled( 'smpi_refresh_github_version', [ $repo, $starter ] ) ) {
            wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'smpi_refresh_github_version', [ $repo, $starter ] );
        }

        set_transient( $lock_key, '1', 15 * MINUTE_IN_SECONDS );
    }

    private static function github_version_cache_key( string $repo, string $starter ): string {
        return 'smpi_github_version_' . md5( $repo . '|' . $starter );
    }

    private static function github_version_lock_key( string $repo, string $starter ): string {
        return 'smpi_github_version_refresh_' . md5( $repo . '|' . $starter );
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
            return PluginProvisioner::activate_plugin_file( $plugin_file );
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
        if ( 'install' === $operation || 'update' === $operation ) {
            $repo = $catalog[ $plugin_file ]['repo'];
            if ( ! $repo ) {
                return new \WP_Error( 'smpi_no_repo', 'No GitHub repository is configured for this plugin.' );
            }

            $slug = dirname( $plugin_file );
            if ( 'install' === $operation ) {
                return PluginProvisioner::ensure_github_plugin_active(
                    $slug,
                    $repo,
                    [
                        'branch'      => 'main',
                        'work_prefix' => 'smpi-github-plugin',
                    ]
                );
            }

            $installed = get_plugins();
            $data = $installed[ $plugin_file ] ?? [];
            $updater_config = UpdaterConfig::from_slug_and_github_url(
                $slug,
                $repo,
                [
                    'plugin_starter_file'       => $catalog[ $plugin_file ]['starter'],
                    'plugin_basename'           => $plugin_file,
                    'canonical_plugin_basename' => $plugin_file,
                    'proper_folder_name'        => $slug,
                    'runtime_folder_name'       => $slug,
                    'plugin_name'               => $catalog[ $plugin_file ]['label'],
                    'version'                   => $data['Version'] ?? '0.0.0',
                    'github_branch'             => 'main',
                    'progress_key'              => 'smpi_plugin_update_progress_' . md5( $plugin_file ),
                ]
            );

            return ( new DirectPluginInstaller( $updater_config ) )->run();
        }
        return new \WP_Error( 'smpi_bad_action', 'Unsupported action.' );
    }
}
