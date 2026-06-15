<?php
namespace smp_publication_integration\Admin;

use smp_publication_integration\Support\PluginRegistry;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ajax {
    public const NONCE = 'smpi_admin';

    public function register(): void {
        add_action( 'wp_ajax_smpi_save_settings', [ $this, 'save_settings' ] );
        add_action( 'wp_ajax_smpi_save_page_assignment', [ $this, 'save_page_assignment' ] );
        add_action( 'wp_ajax_smpi_refresh_optimization', [ $this, 'refresh_optimization' ] );
        add_action( 'wp_ajax_smpi_plugin_action', [ $this, 'plugin_action' ] );
    }

    public static function nonce(): string {
        return wp_create_nonce( self::NONCE );
    }

    private function guard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
        }
        check_ajax_referer( self::NONCE, 'nonce' );
    }

    public function save_settings(): void {
        $this->guard();
        $changes = [];
        foreach ( [ 'founders_enabled', 'shadow_press_releases', 'author_social_cleanup', 'public_debug_enabled', 'elementor_css_cache_busting', 'publication_social_cleanup', 'muckrack_verified_enabled', 'press_release_include_enabled' ] as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                $changes[ $key ] = (bool) absint( $_POST[ $key ] );
            }
        }
        if ( isset( $_POST['post_time_mode'] ) ) {
            $changes['post_time_mode'] = sanitize_key( wp_unslash( $_POST['post_time_mode'] ) );
        }
        if ( isset( $_POST['muckrack_verified_style'] ) ) {
            $changes['muckrack_verified_style'] = sanitize_key( wp_unslash( $_POST['muckrack_verified_style'] ) );
        }
        foreach ( [ 'muckrack_verified_contexts', 'press_release_include_contexts' ] as $array_key ) {
            if ( isset( $_POST[ $array_key ] ) ) {
                $raw = wp_unslash( $_POST[ $array_key ] );
                $changes[ $array_key ] = is_array( $raw ) ? array_map( 'sanitize_key', $raw ) : [];
            }
        }
        wp_send_json_success( [ 'settings' => Settings::update( $changes ) ] );
    }

    public function save_page_assignment(): void {
        $this->guard();
        $type = isset( $_POST['page_type'] ) ? sanitize_key( wp_unslash( $_POST['page_type'] ) ) : '';
        $page_id = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
        $template = isset( $_POST['template'] ) ? wp_kses_post( wp_unslash( $_POST['template'] ) ) : '';
        wp_send_json_success( [ 'settings' => Settings::update_page( $type, $page_id, $template ) ] );
    }

    public function refresh_optimization(): void {
        $this->guard();
        wp_send_json_success( [ 'html' => Dashboard::render_optimization_report_html() ] );
    }

    public function plugin_action(): void {
        $this->guard();
        $plugin_file = isset( $_POST['plugin_file'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) : '';
        $operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
        $result = PluginRegistry::perform_action( $plugin_file, $operation );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        wp_send_json_success( [ 'plugin' => PluginRegistry::info( $plugin_file ) ] );
    }
}