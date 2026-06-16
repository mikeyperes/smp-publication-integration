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
        add_action( "wp_ajax_smpi_create_page_assignment", [ $this, "create_page_assignment" ] );
        add_action( "wp_ajax_smpi_search_users", [ $this, "search_users" ] );
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
        foreach ( [ 'founders_enabled', 'shadow_press_releases', 'author_social_cleanup', 'public_debug_enabled', 'estimated_read_time_enabled', 'elementor_css_cache_busting', 'publication_social_cleanup', 'muckrack_verified_enabled', 'press_release_include_enabled' ] as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                $changes[ $key ] = (bool) absint( $_POST[ $key ] );
            }
        }
        foreach ( [ "system_publication_user_id" ] as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                $changes[ $key ] = absint( $_POST[ $key ] );
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
        $settings = Settings::update( $changes );
        $this->sync_publication_mapping( $settings );
        wp_send_json_success( [ "settings" => $settings ] );
    }


    public function search_users(): void {
        $this->guard();
        $term = isset( $_POST["term"] ) ? sanitize_text_field( wp_unslash( $_POST["term"] ) ) : "";
        $args = [
            "number" => 20,
            "orderby" => "display_name",
            "order" => "ASC",
            "fields" => "all",
        ];
        if ( "" !== $term ) {
            $args["search"] = "*" . $term . "*";
            $args["search_columns"] = [ "user_login", "user_nicename", "user_email", "display_name" ];
        }

        $users = get_users( $args );
        $results = [];
        foreach ( $users as $user ) {
            $results[] = $this->user_result( $user );
        }

        wp_send_json_success( [ "users" => $results ] );
    }

    private function user_result( \WP_User $user ): array {
        return [
            "id" => (int) $user->ID,
            "label" => $user->display_name . " (#" . $user->ID . ")",
            "name" => $user->display_name,
            "email" => $user->user_email,
            "login" => $user->user_login,
            "avatar" => get_avatar_url( $user->ID, [ "size" => 96 ] ),
            "edit_url" => get_edit_user_link( $user->ID ),
            "view_url" => get_author_posts_url( $user->ID ),
        ];
    }

    private function sync_publication_mapping( array $settings ): void {
        $user_id = isset( $settings["system_publication_user_id"] ) ? absint( $settings["system_publication_user_id"] ) : 0;

        if ( ! $user_id ) {
            delete_option( "smpi_publication_user_id" );
            if ( function_exists( "update_field" ) ) {
                update_field( "smpi_publication_user", 0, "option" );
            }
            Settings::log( "Publication author selection cleared." );
            return;
        }

        if ( ! get_user_by( "id", $user_id ) ) {
            return;
        }

        update_option( "smpi_publication_user_id", $user_id, false );
        if ( function_exists( "update_field" ) ) {
            update_field( "smpi_publication_user", $user_id, "option" );
        }
        Settings::log( "Publication author selected: user #" . $user_id );
    }

    public function save_page_assignment(): void {
        $this->guard();
        $type = isset( $_POST['page_type'] ) ? sanitize_key( wp_unslash( $_POST['page_type'] ) ) : '';
        $page_id = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
        $template = isset( $_POST['template'] ) ? wp_kses_post( wp_unslash( $_POST['template'] ) ) : '';
        wp_send_json_success( [ 'settings' => Settings::update_page( $type, $page_id, $template ) ] );
    }


    public function create_page_assignment(): void {
        $this->guard();
        $type = isset( $_POST["page_type"] ) ? sanitize_key( wp_unslash( $_POST["page_type"] ) ) : "";
        $page_types = Settings::page_types();
        if ( ! isset( $page_types[ $type ] ) ) {
            wp_send_json_error( [ "message" => "Unknown page type." ], 400 );
        }

        $settings = Settings::all();
        $current = isset( $settings["page_assignments"][ $type ] ) ? absint( $settings["page_assignments"][ $type ] ) : 0;
        if ( $current && get_post( $current ) ) {
            wp_send_json_success( [ "page" => $this->page_result( $current ), "settings" => $settings ] );
        }

        $title = (string) $page_types[ $type ]["label"];
        $slug = sanitize_title( $title );
        $existing = get_page_by_path( $slug );
        if ( $existing instanceof \WP_Post ) {
            $page_id = (int) $existing->ID;
        } else {
            $templates = Settings::default_page_templates();
            $content = (string) ( $settings["page_templates"][ $type ] ?? $templates[ $type ] ?? "" );
            $page_id = wp_insert_post(
                [
                    "post_type" => "page",
                    "post_status" => "draft",
                    "post_title" => $title,
                    "post_name" => $slug,
                    "post_content" => $content,
                ],
                true
            );
            if ( is_wp_error( $page_id ) ) {
                wp_send_json_error( [ "message" => $page_id->get_error_message() ], 500 );
            }
        }

        $settings = Settings::update_page( $type, (int) $page_id, (string) ( $settings["page_templates"][ $type ] ?? "" ) );
        wp_send_json_success( [ "page" => $this->page_result( (int) $page_id ), "settings" => $settings ] );
    }

    private function page_result( int $page_id ): array {
        return [
            "id" => $page_id,
            "title" => get_the_title( $page_id ),
            "edit_url" => get_edit_post_link( $page_id, "raw" ),
            "view_url" => get_permalink( $page_id ),
        ];
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
