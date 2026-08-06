<?php

declare( strict_types=1 );

namespace SMP\PublicationIntegration\Infrastructure;

use Hexa\PluginCore\CorePackageUpdates\CorePackageConfig;
use Hexa\PluginCore\PluginUpdates\UpdaterConfig;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Config;

defined( 'ABSPATH' ) || exit;

final class Updates {
    private static ?UpdaterConfig $plugin_config = null;
    private static ?CorePackageConfig $core_config = null;

    public static function plugin_config(): UpdaterConfig {
        if ( self::$plugin_config instanceof UpdaterConfig ) {
            return self::$plugin_config;
        }

        $plugin_file = dirname( __DIR__, 2 ) . '/smp-publication-integration.php';

        self::$plugin_config = UpdaterConfig::from_plugin_file(
            $plugin_file,
            Config::$github_repo,
            [
                'plugin_slug'               => Config::$plugin_folder_name,
                'proper_folder_name'        => Config::$plugin_folder_name,
                'runtime_folder_name'       => Config::$plugin_folder_name,
                'plugin_basename'           => Config::plugin_basename(),
                'canonical_plugin_basename' => Config::$plugin_folder_name . '/' . Config::$plugin_file,
                'plugin_starter_file'       => Config::$plugin_file,
                'github_branch'             => Config::$github_branch,
                'requires'                  => '5.0',
                'tested'                    => '7.0',
                'requires_php'              => '8.1',
                'nonce_action'              => Ajax::NONCE,
                'nonce_param'               => 'nonce',
                'ajax_action_prefix'        => 'smpi_core_updater',
                'progress_key'              => 'smpi_core_update_progress',
            ]
        );

        return self::$plugin_config;
    }

    public static function core_config(): CorePackageConfig {
        if ( self::$core_config instanceof CorePackageConfig ) {
            return self::$core_config;
        }

        self::$core_config = CorePackageConfig::from_core_root(
            dirname( __DIR__, 2 ) . '/lib/hexa-wordpress-plugin-core',
            [
                'github_repo'        => 'mikeyperes/hexa-wordpress-plugin-core',
                'github_branch'      => 'main',
                'nonce_action'       => Ajax::NONCE,
                'nonce_param'        => 'nonce',
                'ajax_action_prefix' => 'smpi_core_package',
                'cache_key'          => 'smpi_hexa_plugin_core_package',
            ]
        );

        return self::$core_config;
    }
}
