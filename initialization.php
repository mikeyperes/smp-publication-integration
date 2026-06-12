<?php
/**
 * Plugin Name: SMP Publication Integration
 * Description: Publication profile integration for Scale My Publication systems.
 * Author: Michael Peres
 * Plugin URI: https://github.com/mikeyperes/smp-publication-integration
 * Version: 0.2.0
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

final class Config {
    public const VERSION = '0.2.0';

    public static string $plugin_name        = 'SMP Publication Integration';
    public static string $plugin_slug        = 'smp-publication-integration';
    public static string $plugin_folder_name = 'smp-publication-integration';
    public static string $plugin_file        = 'initialization.php';

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

    ( new Content\PublicationPostType() )->register();
    ( new Content\AcfFields() )->register();
    ( new Content\Shortcodes() )->register();
    ( new Content\Schema() )->register();
    ( new Content\Visibility() )->register();
    ( new Content\PostTime() )->register();
    ( new Content\AuthorSocialCleanup() )->register();
    ( new Content\DebugEndpoint() )->register();

    if ( is_admin() || wp_doing_ajax() ) {
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