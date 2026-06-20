<?php
/**
 * Plugin Name: SMP Publication Integration
 * Description: Publication profile integration for Scale My Publication systems.
 * Author: Michael Peres
 * Plugin URI: https://github.com/mikeyperes/smp-publication-integration
 * Version: 0.6.35
 * Text Domain: smp-publication-integration
 * Domain Path: /languages
 * Author URI: https://michaelperes.com
 * GitHub Plugin URI: https://github.com/mikeyperes/smp-publication-integration/
 * GitHub Branch: main
 */

namespace smp_publication_integration;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/src/Support/Autoloader.php';

Support\Autoloader::register( __DIR__ . '/src' );

function register_hexa_plugin_core_autoloader(): void {
    static $registered = false;

    if ( $registered ) {
        return;
    }

    $base_dir = __DIR__ . '/lib/hexa-wordpress-plugin-core/src/';
    $prefix   = 'Hexa\\PluginCore\\';

    spl_autoload_register(
        static function( string $class_name ) use ( $base_dir, $prefix ): void {
            if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 ) {
                return;
            }

            $relative_class = substr( $class_name, strlen( $prefix ) );
            $file           = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

            if ( is_readable( $file ) ) {
                require_once $file;
            }
        },
        true,
        true
    );

    $registered = true;
}

register_hexa_plugin_core_autoloader();

require_once __DIR__ . "/src/Content/AcfFields.php";
require_once __DIR__ . "/src/Content/Shortcodes.php";
require_once __DIR__ . "/src/Content/AuthorShortcodes.php";
require_once __DIR__ . "/src/Content/Schema.php";
require_once __DIR__ . "/src/Content/ArticleTypes.php";
require_once __DIR__ . "/src/Content/Visibility.php";
require_once __DIR__ . "/src/Content/PostTime.php";
require_once __DIR__ . "/src/Content/EstimatedReadTime.php";
require_once __DIR__ . "/src/Content/ElementorCssCacheBusting.php";
require_once __DIR__ . "/src/Content/MuckRackVerification.php";
require_once __DIR__ . "/src/Content/AuthorSocialCleanup.php";
require_once __DIR__ . "/src/Content/TableOfContents.php";
require_once __DIR__ . "/src/Content/ArticleStyles.php";
require_once __DIR__ . "/src/Content/DebugEndpoint.php";
require_once __DIR__ . "/src/Admin/Ajax.php";
require_once __DIR__ . "/src/Admin/Dashboard.php";

final class Config {
    public const VERSION = "0.6.35";

    public static string $plugin_name        = 'SMP Publication Integration';
    public static string $plugin_slug        = 'smp-publication-integration';
    public static string $plugin_folder_name = 'smp-publication-integration';
    public static string $plugin_file        = "smp-publication-integration.php";

    public static string $settings_page_name          = 'SMP Publication Integration';
    public static string $settings_page_capability    = 'manage_options';
    public static string $settings_page_slug          = 'smp-publication-integration';
    public static string $settings_page_display_title = 'SMP Publication Integration';

    public static string $github_repo   = 'mikeyperes/smp-publication-integration';
    public static string $github_branch = 'main';

    public static function plugin_basename(): string {
        return plugin_basename( __FILE__ );
    }
}

Support\BootstrapMigration::register( Config::$plugin_folder_name, Config::$plugin_file );

function hexa_plugin_core_updater_config(): \Hexa\PluginCore\PluginUpdates\UpdaterConfig {
    static $config = null;

    if ( $config instanceof \Hexa\PluginCore\PluginUpdates\UpdaterConfig ) {
        return $config;
    }

    $config = \Hexa\PluginCore\PluginUpdates\UpdaterConfig::from_plugin_file(
        __FILE__,
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
            'nonce_action'              => Admin\Ajax::NONCE,
            'nonce_param'               => 'nonce',
            'ajax_action_prefix'        => 'smpi_core_updater',
            'progress_key'              => 'smpi_core_update_progress',
        ]
    );

    return $config;
}

function hexa_plugin_core_package_config(): \Hexa\PluginCore\CorePackageUpdates\CorePackageConfig {
    static $config = null;

    if ( $config instanceof \Hexa\PluginCore\CorePackageUpdates\CorePackageConfig ) {
        return $config;
    }

    $config = \Hexa\PluginCore\CorePackageUpdates\CorePackageConfig::from_core_root(
        __DIR__ . '/lib/hexa-wordpress-plugin-core',
        [
            'github_repo'        => 'mikeyperes/hexa-wordpress-plugin-core',
            'github_branch'      => 'main',
            'nonce_action'       => Admin\Ajax::NONCE,
            'nonce_param'        => 'nonce',
            'ajax_action_prefix' => 'smpi_core_package',
            'cache_key'          => 'smpi_hexa_plugin_core_package',
        ]
    );

    return $config;
}

function boot_github_updater(): void {
    if ( ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return;
    }

    require_once __DIR__ . '/GitHub_Updater.php';

    init_github_updater(
        [
            'plugin_file'        => __FILE__,
            'github_repo'        => Config::$github_repo,
            'github_branch'      => Config::$github_branch,
            'proper_folder_name' => Config::$plugin_folder_name,
            'requires'           => '5.0',
            'tested'             => '7.0',
        ]
    );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot_github_updater', 20 );

function boot_plugin(): void {
    $missing = Support\Dependencies::missing_required_dependencies();
    if ( ! empty( $missing ) ) {
        add_action( 'admin_notices', [ Support\Dependencies::class, 'render_missing_required_notice' ] );
        return;
    }

    ( new Content\AcfFields() )->register();
    ( new Content\Shortcodes() )->register();
    ( new Content\AuthorShortcodes() )->register();
    ( new Content\Schema() )->register();
    ( new Content\ArticleTypes() )->register();
    ( new Content\Visibility() )->register();
    ( new Content\PostTime() )->register();
    ( new Content\EstimatedReadTime() )->register();
    ( new Content\ElementorCssCacheBusting() )->register();
    ( new Content\MuckRackVerification() )->register();
    ( new Content\AuthorSocialCleanup() )->register();
    ( new Content\TableOfContents() )->register();
    ( new Content\ArticleStyles() )->register();
    ( new Content\DebugEndpoint() )->register();

    if ( is_admin() || wp_doing_ajax() ) {
        ( new \Hexa\PluginCore\PluginUpdates\UpdaterAjaxController( hexa_plugin_core_updater_config() ) )->register();
        ( new \Hexa\PluginCore\CorePackageUpdates\CorePackageAjaxController( hexa_plugin_core_package_config() ) )->register();
        ( new \Hexa\PluginCore\WpAdminTabs\CoreTabModule(
            new \Hexa\PluginCore\WpAdminTabs\CoreTabConfig(
                [
                    'tabs_filter'   => 'smpi_dashboard_tabs',
                    'render_filter' => 'smpi_render_dashboard_tab',
                    'capability'    => Config::$settings_page_capability,
                    'core_root'     => __DIR__ . '/lib/hexa-wordpress-plugin-core',
                    'readme_path'   => __DIR__ . '/lib/hexa-wordpress-plugin-core/README.md',
                    'library_path'  => __DIR__ . '/HEXA_PLUGIN_CORE_LIBRARY.md',
                ]
            )
        ) )->register();

        ( new Admin\Ajax() )->register();
        ( new Admin\Dashboard() )->register();
    }
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot_plugin', 30 );

function activate_plugin(): void {
    $missing = Support\Dependencies::missing_required_dependencies();
    if ( empty( $missing ) ) {
        return;
    }

    deactivate_plugins( plugin_basename( __FILE__ ) );
    wp_die(
        wp_kses_post( Support\Dependencies::required_notice_message( $missing ) ),
        esc_html__( 'Plugin dependency missing', 'smp-publication-integration' ),
        [ 'back_link' => true ]
    );
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate_plugin' );
