<?php
namespace smp_publication_integration\Admin;

use Hexa\PluginCore\SiteStructure\SiteStructureAjaxController;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxGuard;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use smp_publication_integration\Support\Dependencies;
use smp_publication_integration\Support\PageStructure;
use smp_publication_integration\Support\PluginRegistry;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ajax {
    public const NONCE = 'smpi_admin';

    public function register(): void {
        ( new AjaxActionRegistry(
            [
                'capability'   => 'manage_options',
                'nonce_action' => self::NONCE,
                'nonce_field'  => 'nonce',
                'logger'       => static function ( \Throwable $throwable ): void {
                    error_log( '[SMP Publication Integration] AJAX error: ' . $throwable->getMessage() );
                },
            ]
        ) )->register(
            [
                'smpi_load_tab'                => [ 'callback' => [ $this, 'load_tab' ] ],
                'smpi_save_settings'           => [ 'callback' => [ $this, 'save_settings' ] ],
                'smpi_save_page_assignment'    => [ 'callback' => [ $this, 'save_page_assignment' ] ],
                'smpi_create_page_assignment'  => [ 'callback' => [ $this, 'create_page_assignment' ] ],
                'smpi_page_details'            => [ 'callback' => [ $this, 'page_details' ] ],
                'smpi_update_page_slug'        => [ 'callback' => [ $this, 'update_page_slug' ] ],
                'smpi_search_users'            => [ 'callback' => [ $this, 'search_users' ] ],
                'smpi_shortcode_user_preview'  => [ 'callback' => [ $this, 'shortcode_user_preview' ] ],
                'smpi_search_profiles'         => [ 'callback' => [ $this, 'search_profiles' ] ],
                'smpi_save_founder_profiles'   => [ 'callback' => [ $this, 'save_founder_profiles' ] ],
                'smpi_refresh_optimization'    => [ 'callback' => [ $this, 'refresh_optimization' ] ],
                'smpi_plugin_action'           => [ 'callback' => [ $this, 'plugin_action' ] ],
            ]
        );

        ( new SiteStructureAjaxController(
            PageStructure::manager(),
            [
                'capability'   => 'manage_options',
                'nonce_action' => self::NONCE,
                'nonce_field'  => 'nonce',
                'actions'      => PageStructure::ajax_actions(),
                'logger'       => static function ( \Throwable $throwable ): void {
                    error_log( '[SMP Publication Integration] SiteStructure AJAX error: ' . $throwable->getMessage() );
                },
            ]
        ) )->register();

        add_filter( "hexa_plugin_core_smart_search_results", [ $this, "filter_smart_search_results" ], 10, 4 );

        add_action( 'admin_post_smpi_enable_verified_profile_snippet', [ $this, 'enable_verified_profile_snippet' ] );
    }

    public static function nonce(): string {
        return AjaxGuard::create_nonce( self::NONCE );
    }

    public function load_tab( AjaxRequest $request ): array {
        $tab = $request->key( 'tab', 'overview', 'post' );
        $dashboard = new Dashboard();
        return $dashboard->tab_fragment( $tab );
    }

    public function save_settings( AjaxRequest $request ): array {
        $changes = [];
        foreach ( [ "founders_enabled", "shadow_posts_enabled", "shadow_press_releases", "author_social_cleanup", "public_debug_enabled", "estimated_read_time_enabled", "elementor_css_cache_busting", "publication_social_cleanup", "muckrack_verified_enabled", "muckrack_author_always_show", "publication_muckrack_verified_enabled", "press_release_include_enabled", "post_summary_acf_enabled", "post_faqs_acf_enabled", "article_types_enabled", "table_of_contents_enabled", "table_of_contents_auto_single", "inline_photo_treatments_enabled", "featured_image_caption_templates_enabled", "rank_math_breadcrumb_check_enabled", "hws_masked_admin_report_enabled" ] as $key ) {
            if ( $request->has( $key, 'post' ) ) {
                $changes[ $key ] = $request->bool( $key, false, 'post' );
            }
        }
        foreach ( [ "system_publication_user_id" ] as $key ) {
            if ( $request->has( $key, 'post' ) ) {
                $changes[ $key ] = $request->int( $key, 0, 'post' );
            }
        }
        foreach ( [ "muckrack_icon_size" => [ 8, 64, 18 ], "publication_muckrack_font_size" => [ 8, 64, 14 ], "table_of_contents_text_font_size" => [ 8, 64, 15 ], "inline_photo_caption_font_size" => [ 8, 64, 16 ], "featured_image_caption_font_size" => [ 8, 64, 16 ], "post_faqs_text_font_size" => [ 8, 64, 16 ], "muckrack_icon_size_single_author" => [ 0, 64, 0 ], "muckrack_icon_size_single_footer" => [ 0, 64, 0 ], "muckrack_icon_size_loop_cards" => [ 0, 64, 0 ], "muckrack_icon_size_home" => [ 0, 64, 0 ], "muckrack_icon_size_author" => [ 0, 64, 0 ] ] as $key => $limits ) {
            if ( $request->has( $key, 'post' ) ) {
                $value = $request->int( $key, 0, 'post' );
                $changes[ $key ] = 0 === strpos( $key, "muckrack_icon_size_" ) && 0 === $value ? 0 : max( $limits[0], min( $limits[1], $value ?: $limits[2] ) );
            }
        }
        foreach ( [ "table_of_contents_style", "inline_photo_treatment", "featured_image_caption_template", "post_summary_style", "post_faqs_style", "table_of_contents_text_font_style", "inline_photo_caption_font_style", "featured_image_caption_font_style", "post_faqs_text_font_style", 'post_time_mode', 'muckrack_verified_style', 'muckrack_icon_style', 'publication_muckrack_text_mode', 'publication_muckrack_style' ] as $key ) {
            if ( $request->has( $key, 'post' ) ) {
                $changes[ $key ] = $request->key( $key, '', 'post' );
            }
        }
        foreach ( [ 'muckrack_icon_color', 'muckrack_icon_color_single_author', 'muckrack_icon_color_single_footer', 'muckrack_icon_color_loop_cards', 'muckrack_icon_color_home', 'muckrack_icon_color_author', 'table_of_contents_accent_color', 'table_of_contents_text_color', 'inline_photo_accent_color', 'inline_photo_caption_text_color', 'featured_image_caption_accent_color', 'featured_image_caption_text_color', 'post_faqs_accent_color', 'post_faqs_text_color', 'publication_muckrack_color' ] as $color_key ) {
            if ( $request->has( $color_key, 'post' ) ) {
                $raw = trim( (string) $request->raw( $color_key, '', 'post' ) );
                $changes[ $color_key ] = '' === $raw ? '' : sanitize_hex_color( $raw );
            }
        }
        foreach ( [ 'muckrack_verified_contexts', 'publication_muckrack_placements', 'press_release_include_contexts' ] as $array_key ) {
            if ( $request->has( $array_key, 'post' ) || $request->has( $array_key . '_present', 'post' ) ) {
                $changes[ $array_key ] = $request->key_array( $array_key, 'post' );
            }
        }
        $settings = Settings::update( $changes );
        $this->sync_publication_mapping( $settings );
        $response = [ "settings" => $settings ];
        if ( "features" === $request->key( 'tab', '', 'post' ) ) {
            $response["fragment"] = ( new Dashboard() )->tab_fragment( "features" );
        }
        return $response;
    }

    public function search_users( AjaxRequest $request ): array {
        $term = $request->text( 'term', '', 'post' );
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

        return [ "users" => $results ];
    }

    public function shortcode_user_preview( AjaxRequest $request ): array {
        $user_id = $request->int( 'user_id', 0, 'post' );
        if ( $user_id <= 0 || ! get_user_by( "id", $user_id ) ) {
            throw AjaxFailure::not_found( "Selected user was not found." );
        }
        return [ "user" => Dashboard::shortcode_selected_user_html( $user_id ), "html" => Dashboard::shortcode_user_values_html( $user_id ) ];
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

    public function search_profiles( AjaxRequest $request ): array {
        $this->require_verified_profiles();
        $term = $request->text( 'term', '', 'post' );
        $query = new \WP_Query(
            [
                "post_type" => "profile",
                "post_status" => [ "publish", "draft", "pending", "private" ],
                "posts_per_page" => 20,
                "orderby" => "title",
                "order" => "ASC",
                "s" => $term,
                "no_found_rows" => true,
            ]
        );

        $profiles = [];
        foreach ( $query->posts as $post ) {
            $profiles[] = $this->profile_result( $post );
        }

        return [ "profiles" => $profiles ];
    }

    public function filter_smart_search_results( array $results, string $source, string $query, int $limit ): array {
        if ( "smpi_profiles" !== $source ) {
            return $results;
        }

        if ( ! post_type_exists( "profile" ) ) {
            return [];
        }

        global $wpdb;

        $like = "%" . $wpdb->esc_like( $query ) . "%";
        $statuses = [ "publish", "draft", "pending", "private" ];
        $placeholders = implode( ",", array_fill( 0, count( $statuses ), "%s" ) );
        $prepared = $wpdb->prepare(
            "SELECT DISTINCT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = %s AND p.post_status IN (" . $placeholders . ") AND (p.post_title LIKE %s OR p.post_content LIKE %s OR pm.meta_value LIKE %s) ORDER BY p.post_title ASC LIMIT %d",
            array_merge( [ "profile" ], $statuses, [ $like, $like, $like, max( 1, min( 50, $limit ) ) ] )
        );

        if ( ! is_string( $prepared ) || "" === $prepared ) {
            return [];
        }

        $ids = $wpdb->get_col( $prepared );
        $profiles = [];

        foreach ( $ids as $id ) {
            $post = get_post( absint( $id ) );
            if ( $post && "profile" === get_post_type( $post ) ) {
                $profiles[] = $this->profile_result( $post );
            }
        }

        return $profiles;
    }

    public function save_founder_profiles( AjaxRequest $request ): array {
        $this->require_verified_profiles();
        $ids = [];
        foreach ( $request->items( 'founder_profile_ids', 'post' ) as $id ) {
            $id = absint( $id );
            if ( $id && "profile" === get_post_type( $id ) ) {
                $ids[] = $id;
            }
        }
        $ids = array_values( array_unique( $ids ) );
        $this->update_founder_profiles( $ids );

        $profiles = [];
        foreach ( $ids as $id ) {
            $post = get_post( $id );
            if ( $post ) {
                $profiles[] = $this->profile_result( $post );
            }
        }

        return [ "ids" => $ids, "profiles" => $profiles ];
    }

    private function require_verified_profiles(): void {
        if ( ! Dependencies::sfpf_active() ) {
            throw AjaxFailure::bad_request( "Verified Profiles integration is required to add founders." );
        }
        if ( ! post_type_exists( "profile" ) ) {
            throw AjaxFailure::bad_request( "Verified Profiles is active, but the profile post type is not registered. Enable register_profile_custom_post_type." );
        }
    }

    private function profile_result( \WP_Post $post ): array {
        $title = get_the_title( $post ) ?: "Profile #" . (string) $post->ID;
        $status = get_post_status( $post ) ?: "unknown";

        return [
            "id"        => (int) $post->ID,
            "value"     => (int) $post->ID,
            "label"     => $title,
            "name"      => $title,
            "subtitle"  => "Verified profile - " . $status,
            "type"      => "profile",
            "status"    => $status,
            "edit_url"  => get_edit_post_link( $post->ID, "raw" ),
            "view_url"  => get_permalink( $post ),
            "thumbnail" => get_the_post_thumbnail_url( $post, "thumbnail" ) ?: "",
        ];
    }

    private function update_founder_profiles( array $ids ): void {
        update_option( "smpi_founder_profile_ids", $ids, false );
        if ( function_exists( "update_field" ) ) {
            $rows = array_map(
                static fn( int $id ): array => [ "profile" => $id ],
                $ids
            );
            update_field( "smpi_founder_profiles", $rows, "option" );
        }
        Settings::log( "Founder profiles updated: " . implode( ", ", $ids ) );
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

    public function save_page_assignment( AjaxRequest $request ): array {
        $type = $request->key( 'page_type', '', 'post' );
        $page_id = $request->int( 'page_id', 0, 'post' );
        $template = $request->html( 'template', '', 'post' );
        $settings = Settings::update_page( $type, $page_id, $template );
        $response = [ "settings" => $settings, "page" => null ];
        if ( $page_id > 0 ) {
            $response["page"] = $this->page_result( $page_id );
        }
        return $response;
    }

    public function create_page_assignment( AjaxRequest $request ): array {
        $type = $request->key( 'page_type', '', 'post' );
        $page_types = Settings::page_types();
        if ( ! isset( $page_types[ $type ] ) ) {
            throw AjaxFailure::bad_request( "Unknown page type." );
        }

        $settings = Settings::all();
        $current = isset( $settings["page_assignments"][ $type ] ) ? absint( $settings["page_assignments"][ $type ] ) : 0;
        if ( $current && get_post( $current ) ) {
            return [ "mode" => "already_assigned", "message" => "Already assigned.", "page" => $this->page_result( $current ), "settings" => $settings ];
        }

        $title = (string) $page_types[ $type ]["label"];
        $slug = sanitize_title( $title );
        $existing = get_page_by_path( $slug, OBJECT, "page" );
        $mode = "reused_existing";
        if ( is_a( $existing, "WP_Post" ) ) {
            $page_id = (int) $existing->ID;
        } else {
            $templates = Settings::default_page_templates();
            $stored_template = isset( $settings["page_templates"][ $type ] ) ? trim( (string) $settings["page_templates"][ $type ] ) : "";
            $content = "" !== $stored_template ? (string) $settings["page_templates"][ $type ] : (string) ( $templates[ $type ] ?? "" );
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
                throw AjaxFailure::server_error( $page_id->get_error_message() );
            }
            $mode = "created";
        }

        $settings = Settings::update_page( $type, (int) $page_id, (string) ( $settings["page_templates"][ $type ] ?? "" ) );
        $message = "created" === $mode ? "Created draft page and assigned it." : "Reused existing page and assigned it.";
        return [ "mode" => $mode, "message" => $message, "page" => $this->page_result( (int) $page_id ), "settings" => $settings ];
    }

    public function page_details( AjaxRequest $request ): array {
        $page_id = $request->int( 'page_id', 0, 'post' );
        if ( $page_id <= 0 ) {
            return [ "page" => null ];
        }
        $post = get_post( $page_id );
        if ( ! $post || "page" !== $post->post_type ) {
            throw AjaxFailure::not_found( "Selected page was not found." );
        }
        return [ "page" => $this->page_result( $page_id ) ];
    }

    public function update_page_slug( AjaxRequest $request ): array {
        $type = $request->key( 'page_type', '', 'post' );
        if ( ! isset( Settings::page_types()[ $type ] ) ) {
            throw AjaxFailure::bad_request( "Unknown page type." );
        }
        $page_id = $request->int( 'page_id', 0, 'post' );
        $post = get_post( $page_id );
        if ( ! $post || "page" !== $post->post_type ) {
            throw AjaxFailure::not_found( "Selected page was not found." );
        }
        $requested = $request->title_slug( 'slug', '', 'post' );
        if ( "" === $requested ) {
            throw AjaxFailure::bad_request( "Slug cannot be empty." );
        }
        $unique = wp_unique_post_slug( $requested, $page_id, $post->post_status, "page", (int) $post->post_parent );
        $updated = wp_update_post( [ "ID" => $page_id, "post_name" => $unique ], true );
        if ( is_wp_error( $updated ) ) {
            throw AjaxFailure::server_error( $updated->get_error_message() );
        }
        clean_post_cache( $page_id );
        Settings::log( "Page slug updated: " . $type . " -> " . $unique );
        $message = $unique === $requested ? "Slug updated." : "Slug updated with a unique suffix because the requested slug was unavailable.";
        return [ "message" => $message, "requested_slug" => $requested, "slug_adjusted" => $unique !== $requested, "page" => $this->page_result( $page_id ) ];
    }

    private function page_result( int $page_id ): array {
        $post = get_post( $page_id );
        if ( ! $post ) {
            return [];
        }
        $status = (string) $post->post_status;
        $status_obj = get_post_status_object( $status );
        $permalink = Settings::page_slug_url( $page_id );
        $edit_url = get_edit_post_link( $page_id, "raw" );
        return [
            "id" => $page_id,
            "title" => get_the_title( $page_id ),
            "status" => $status,
            "status_label" => $status_obj ? (string) $status_obj->label : ucfirst( $status ),
            "slug" => (string) $post->post_name,
            "post_type" => (string) $post->post_type,
            "permalink" => $permalink,
            "view_url" => $permalink,
            "edit_url" => $edit_url,
            "date" => get_the_date( "M j, Y g:i a", $page_id ),
            "modified" => get_the_modified_date( "M j, Y g:i a", $page_id ),
            "author" => get_the_author_meta( "display_name", (int) $post->post_author ),
            "detail_html" => Dashboard::page_detail_html( $page_id ),
        ];
    }

    public function refresh_optimization(): array {
        return [ 'html' => Dashboard::render_optimization_report_html() ];
    }

    public function enable_verified_profile_snippet(): void {
        if ( ! current_user_can( "manage_options" ) ) {
            wp_die( esc_html__( "Permission denied.", "smp-publication-integration" ) );
        }
        check_admin_referer( "smpi_enable_verified_profile_snippet" );
        $snippet = isset( $_GET["snippet"] ) ? sanitize_key( wp_unslash( $_GET["snippet"] ) ) : "";
        $allowed = [ "register_profile_custom_post_type", "register_profile_general_acf_fields" ];
        if ( in_array( $snippet, $allowed, true ) ) {
            update_option( $snippet, true, false );
            Settings::log( "Verified Profiles snippet enabled: " . $snippet );
        }
        wp_safe_redirect( admin_url( "options-general.php?page=smp-publication-integration&tab=publication_options" ) );
        exit;
    }

    public function plugin_action( AjaxRequest $request ): array {
        $plugin_file = $request->text( 'plugin_file', '', 'post' );
        $operation = $request->key( 'operation', '', 'post' );
        $result = PluginRegistry::perform_action( $plugin_file, $operation );
        if ( is_wp_error( $result ) ) {
            throw AjaxFailure::bad_request( $result->get_error_message(), $result->get_error_code() ?: 'plugin_action_failed' );
        }
        $dashboard = new Dashboard();
        return [ 'plugin' => PluginRegistry::info( $plugin_file ), 'row_html' => $dashboard->plugin_row_fragment( $plugin_file ) ];
    }
}
