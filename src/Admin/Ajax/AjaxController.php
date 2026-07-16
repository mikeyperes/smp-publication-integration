<?php
namespace smp_publication_integration\Admin\Ajax;

use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\PluginChecks\PluginInventoryAjaxController;
use Hexa\PluginCore\SiteStructure\SiteStructureAjaxController;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxGuard;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use smp_publication_integration\Admin\Dashboard;
use smp_publication_integration\Content\MultiAuthors;
use smp_publication_integration\Content\Schema;
use smp_publication_integration\Support\Dependencies;
use smp_publication_integration\Support\PageStructure;
use smp_publication_integration\Support\PluginInventory;
use smp_publication_integration\Support\QuickStartFeatures;
use smp_publication_integration\Support\Settings;
use smp_publication_integration\Support\SnippetDefinitions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AjaxController {
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
                'smpi_import_brand_primary_color' => [ 'callback' => [ $this, 'import_brand_primary_color' ] ],
                'smpi_search_users'            => [ 'callback' => [ $this, 'search_users' ] ],
                'smpi_shortcode_user_preview'  => [ 'callback' => [ $this, 'shortcode_user_preview' ] ],
                'smpi_search_profiles'         => [ 'callback' => [ $this, 'search_profiles' ] ],
                'smpi_save_founder_profiles'   => [ 'callback' => [ $this, 'save_founder_profiles' ] ],
                'smpi_test_multi_authors'       => [ 'callback' => [ $this, 'test_multi_authors' ] ],
                'smpi_refresh_optimization'    => [ 'callback' => [ $this, 'refresh_optimization' ] ],
                'smpi_snippet_toggle'          => [ 'callback' => [ $this, 'toggle_snippet' ] ],
                'smpi_snippet_test'            => [ 'callback' => [ $this, 'test_snippet' ] ],
            ]
        );

        ( new SiteStructureAjaxController(
            PageStructure::menu_manager(),
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

        ( new PluginInventoryAjaxController(
            PluginInventory::recommended_definitions(),
            [
                'nonce_action'  => self::NONCE,
                'nonce_field'   => 'nonce',
                'action_prefix' => PluginInventory::recommended_action_prefix(),
                'renderer_args' => PluginInventory::recommended_renderer_args(),
            ]
        ) )->register();

        ( new PluginInventoryAjaxController(
            PluginInventory::forbidden_definitions(),
            [
                'nonce_action'  => self::NONCE,
                'nonce_field'   => 'nonce',
                'action_prefix' => PluginInventory::forbidden_action_prefix(),
                'renderer_args' => PluginInventory::forbidden_renderer_args(),
            ]
        ) )->register();

        QuickStartFeatures::register_checklist_ajax();

        add_filter( "hexa_plugin_core_smart_search_results", [ $this, "filter_smart_search_results" ], 10, 4 );

        add_action( 'admin_post_smpi_enable_verified_profile_snippet', [ $this, 'enable_verified_profile_snippet' ] );
    }

    public static function nonce(): string {
        return AjaxGuard::create_nonce( self::NONCE );
    }

    public function toggle_snippet( AjaxRequest $request ): array {
        $id = sanitize_key( $request->text( 'snippet_id', '', 'post' ) );
        $definition = SnippetDefinitions::definition( $id );
        if ( ! $definition ) {
            throw AjaxFailure::not_found( 'Unknown snippet.' );
        }
        $key = '' !== $definition->option_key ? $definition->option_key : $id;
        Settings::update( [ $key => $request->bool( 'enable', false, 'post' ) ] );
        $enabled = Settings::bool( $key );
        return [
            'snippet_id' => $id,
            'enabled'    => $enabled,
            'message'    => $enabled ? 'Snippet enabled.' : 'Snippet disabled.',
            'test'       => SnippetDefinitions::registry()->test( $id ),
        ];
    }

    public function test_snippet( AjaxRequest $request ): array {
        $id = sanitize_key( $request->text( 'snippet_id', '', 'post' ) );
        if ( ! SnippetDefinitions::definition( $id ) ) {
            throw AjaxFailure::not_found( 'Unknown snippet.' );
        }
        return SnippetDefinitions::registry()->test( $id );
    }

    public function load_tab( AjaxRequest $request ): array {
        $tab = $request->key( 'tab', 'overview', 'post' );
        $dashboard = new Dashboard();
        return $dashboard->tab_fragment( $tab );
    }

    public function save_settings( AjaxRequest $request ): array {
        $changes = [];
        foreach ( [ "founders_enabled", "shadow_posts_enabled", "shadow_press_releases", "post_list_defaults_enabled", "author_social_cleanup", "public_debug_enabled", "estimated_read_time_enabled", "elementor_css_cache_busting", "elementor_primary_category_enabled", "elementor_primary_category_exclude_default", "publication_social_cleanup", "muckrack_verified_enabled", "muckrack_author_always_show", "publication_muckrack_verified_enabled", "multi_authors_enabled", "multi_authors_disable_loop_cards", "press_release_include_enabled", "post_summary_acf_enabled", "post_faqs_acf_enabled", "article_types_enabled", "breadcrumbs_enabled", "breadcrumbs_hide_home", "breadcrumbs_hide_term_archives", "table_of_contents_enabled", "table_of_contents_auto_single", "table_of_contents_include_summary", "article_heading_styles_enabled", "article_drop_cap_enabled", "inline_photo_treatments_enabled", "featured_image_caption_templates_enabled", "rank_math_breadcrumb_check_enabled", "hws_masked_admin_report_enabled", "content_generation_enabled", "post_hygiene_enabled", "post_hygiene_strip_inline_styles", "post_hygiene_unwrap_spans", "post_hygiene_remove_font_tags", "post_hygiene_strip_classes_ids", "post_hygiene_strip_empty_tags", "post_hygiene_clean_heading_children" ] as $key ) {
            if ( $request->has( $key, "post" ) ) {
                $changes[ $key ] = $request->bool( $key, false, "post" );
            }
        }
        foreach ( [ "system_publication_user_id", "content_generation_timeout" ] as $key ) {
            if ( $request->has( $key, 'post' ) ) {
                $changes[ $key ] = $request->int( $key, 0, 'post' );
            }
        }
        if ( $request->has( "content_generation_api_base", "post" ) ) {
            $changes["content_generation_api_base"] = esc_url_raw( (string) $request->raw( "content_generation_api_base", "", "post" ) );
        }
        foreach ( [ "muckrack_icon_size" => [ 8, 64, 16 ], "publication_muckrack_font_size" => [ 8, 64, 14 ], "breadcrumbs_font_size" => [ 8, 64, 13 ], "table_of_contents_text_font_size" => [ 8, 64, 15 ], "article_heading_h2_font_size" => [ 8, 64, 23 ], "article_heading_h3_font_size" => [ 8, 64, 20 ], "article_drop_cap_font_size" => [ 48, 180, 96 ], "inline_photo_caption_font_size" => [ 8, 64, 16 ], "featured_image_caption_font_size" => [ 8, 64, 16 ], "post_faqs_text_font_size" => [ 8, 64, 16 ], "muckrack_icon_size_single_author" => [ 0, 64, 0 ], "muckrack_icon_size_single_footer" => [ 0, 64, 0 ], "muckrack_icon_size_loop_cards" => [ 0, 64, 0 ], "muckrack_icon_size_home" => [ 0, 64, 0 ], "muckrack_icon_size_author" => [ 0, 64, 0 ] ] as $key => $limits ) {
            if ( $request->has( $key, 'post' ) ) {
                $value = $request->int( $key, 0, 'post' );
                $changes[ $key ] = 0 === strpos( $key, "muckrack_icon_size_" ) && 0 === $value ? 0 : max( $limits[0], min( $limits[1], $value ?: $limits[2] ) );
            }
        }
        foreach ( [ "muckrack_icon_margin_left" => [ -32, 64, 2 ], "muckrack_icon_margin_top" => [ -32, 64, 0 ] ] as $key => $limits ) {
            if ( $request->has( $key, 'post' ) ) {
                $raw = trim( (string) $request->raw( $key, '', 'post' ) );
                $value = "" === $raw ? $limits[2] : (int) $raw;
                $changes[ $key ] = max( $limits[0], min( $limits[1], $value ) );
            }
        }
        foreach ( [ "single_author", "single_footer", "loop_cards", "home", "author" ] as $context ) {
            foreach ( [ "muckrack_icon_margin_left_", "muckrack_icon_margin_top_" ] as $prefix ) {
                $key = $prefix . $context;
                if ( $request->has( $key, 'post' ) ) {
                    $raw = trim( (string) $request->raw( $key, '', 'post' ) );
                    $changes[ $key ] = "" === $raw ? "" : max( -32, min( 64, (int) $raw ) );
                }
            }
        }
        foreach ( [ "breadcrumbs_style", "table_of_contents_style", "article_heading_style", "inline_photo_treatment", "featured_image_caption_template", "post_summary_style", "post_faqs_style", "multi_authors_loop_output", "table_of_contents_text_font_style", "inline_photo_caption_font_style", "featured_image_caption_font_style", "post_faqs_text_font_style", 'post_time_mode', 'muckrack_verified_style', 'muckrack_icon_style', 'publication_muckrack_text_mode', 'publication_muckrack_style' ] as $key ) {
            if ( $request->has( $key, 'post' ) ) {
                $changes[ $key ] = $request->key( $key, '', 'post' );
            }
        }
        foreach ( [ 'muckrack_icon_color', 'muckrack_icon_color_single_author', 'muckrack_icon_color_single_footer', 'muckrack_icon_color_loop_cards', 'muckrack_icon_color_home', 'muckrack_icon_color_author', 'breadcrumbs_accent_color', 'table_of_contents_accent_color', 'table_of_contents_text_color', 'article_heading_accent_color', 'article_drop_cap_color', 'inline_photo_accent_color', 'inline_photo_caption_text_color', 'featured_image_caption_accent_color', 'featured_image_caption_text_color', 'post_faqs_accent_color', 'post_faqs_text_color', 'publication_muckrack_color' ] as $color_key ) {
            if ( $request->has( $color_key, 'post' ) ) {
                $raw = trim( (string) $request->raw( $color_key, '', 'post' ) );
                $changes[ $color_key ] = '' === $raw ? '' : sanitize_hex_color( $raw );
            }
        }
        foreach ( [ "muckrack_verified_contexts", "publication_muckrack_placements", "press_release_include_contexts", "breadcrumbs_disabled_post_types", "post_hygiene_allowed_post_types" ] as $array_key ) {
            if ( $request->has( $array_key, "post" ) || $request->has( $array_key . "_present", "post" ) ) {
                $changes[ $array_key ] = $request->key_array( $array_key, "post" );
            }
        }
        $settings = Settings::update( $changes );
        $this->sync_publication_mapping( $settings );
        if ( ! empty( $changes ) ) {
            $this->purge_frontend_cache();
        }
        $response = [ "settings" => $settings, "message" => empty( $changes ) ? "No setting changed." : "Saved " . implode( ", ", array_keys( $changes ) ) . "." ];
        return $response;
    }

    public function import_brand_primary_color( AjaxRequest $request ): array {
        $key = $request->key( "key", "", "post" );
        $brand = Settings::brand_primary_color( "#2d5277" );

        if ( "_all_feature_primary_colors" === $key ) {
            $keys = Settings::brand_primary_color_keys();
        } elseif ( in_array( $key, Settings::color_setting_keys(), true ) ) {
            $keys = [ $key ];
        } else {
            throw AjaxFailure::bad_request( "Invalid color setting." );
        }

        $changes = [];
        foreach ( $keys as $setting_key ) {
            $changes[ $setting_key ] = $brand;
        }

        $settings = Settings::update( $changes );
        $this->sync_publication_mapping( $settings );
        $this->purge_frontend_cache();

        $colors = [];
        foreach ( $keys as $setting_key ) {
            $colors[ $setting_key ] = $brand;
        }

        return [
            "settings" => $settings,
            "color" => $brand,
            "rgb" => BrandColorProvider::rgb_string( $brand ),
            "colors" => $colors,
            "message" => "_all_feature_primary_colors" === $key ? "Imported HWS primary color into feature accent colors." : "Imported HWS primary color into " . $key . ".",
        ];
    }

    private function purge_frontend_cache(): void {
        if ( function_exists( "wp_cache_flush" ) ) {
            wp_cache_flush();
        }

        foreach ( [ "litespeed_purge_all", "litespeed_purge_all_object" ] as $action ) {
            if ( has_action( $action ) ) {
                do_action( $action );
            }
        }

        if ( function_exists( "rocket_clean_domain" ) ) {
            rocket_clean_domain();
        }
        if ( function_exists( "w3tc_flush_all" ) ) {
            w3tc_flush_all();
        }
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
        $post_id = $request->int( "post_id", 0, "post" );
        return [ "user" => Dashboard::shortcode_selected_user_html( $user_id ), "html" => Dashboard::shortcode_user_values_html( $user_id, $post_id ) ];
    }

    public function test_multi_authors( AjaxRequest $request ): array {
        $target = trim( (string) $request->text( "target", "", "post" ) );
        $post_id = $this->resolve_multi_author_test_post_id( $target );
        if ( $post_id <= 0 ) {
            throw AjaxFailure::not_found( "No published post, press release, or imported-news item was found for the test." );
        }

        $post = get_post( $post_id );
        if ( ! $post || ! in_array( (string) $post->post_type, MultiAuthors::supported_post_types(), true ) ) {
            throw AjaxFailure::bad_request( "The selected item is not a supported article post type." );
        }
        if ( "publish" !== (string) $post->post_status ) {
            throw AjaxFailure::bad_request( "The selected item must be published for a frontend hook test." );
        }

        $authors = MultiAuthors::author_view_models_for_post( $post_id );
        $schema = ( new Schema() )->generate_single_schema_array( $post_id );
        $generated_schema_author_ids = $this->schema_author_ids( $schema );
        $frontend = $this->fetch_frontend_author_state( $post_id );
        $resolved_ids = array_map( static fn( array $author ): string => (string) $author["id"], $authors );
        $expected_schema_ids = array_map(
            static fn( array $author ): string => trailingslashit( (string) $author["url"] ) . "#person",
            $authors
        );
        $schema_matches = $this->same_values( $expected_schema_ids, $generated_schema_author_ids );
        $frontend_schema_matches = $this->same_values( $expected_schema_ids, $frontend["schema_author_ids"] );
        $hook_rendered = (int) $frontend["stack_count"] >= count( $authors ) && count( $authors ) > 1;
        $hook_ready = (int) $frontend["marker_count"] > 0 || $hook_rendered;

        $report = [
            "post_id" => $post_id,
            "title" => get_the_title( $post_id ),
            "permalink" => get_permalink( $post_id ),
            "enabled" => MultiAuthors::enabled(),
            "authors" => $authors,
            "resolved_ids" => $resolved_ids,
            "generated_schema_author_ids" => $generated_schema_author_ids,
            "frontend_schema_author_ids" => $frontend["schema_author_ids"],
            "frontend_http_code" => $frontend["http_code"],
            "frontend_error" => $frontend["error"],
            "marker_count" => (int) $frontend["marker_count"],
            "primary_marker_count" => (int) $frontend["primary_marker_count"],
            "legacy_marker_count" => (int) $frontend["legacy_marker_count"],
            "item_count" => (int) $frontend["item_count"],
            "author_link_counts" => $frontend["author_link_counts"],
            "share_count" => (int) $frontend["share_count"],
            "stack_count" => (int) $frontend["stack_count"],
            "visible_name_counts" => $frontend["visible_name_counts"],
            "detected_units" => $frontend["detected_units"],
            "loop_output" => (string) Settings::get( "multi_authors_loop_output", "comma" ),
            "loop_cards_disabled" => Settings::bool( "multi_authors_disable_loop_cards" ),
            "schema_matches" => $schema_matches,
            "frontend_schema_matches" => $frontend_schema_matches,
            "hook_ready" => $hook_ready,
            "hook_rendered" => $hook_rendered,
        ];

        Settings::log( "Multiple author frontend test ran for post #" . $post_id . "." );

        return [
            "message" => $hook_rendered ? "Multiple author frontend hook rendered for post #" . $post_id . "." : "Multiple author test completed for post #" . $post_id . ".",
            "report" => $report,
            "html" => $this->multi_author_test_html( $report ),
        ];
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

    private function resolve_multi_author_test_post_id( string $target ): int {
        if ( "" !== $target ) {
            if ( ctype_digit( $target ) ) {
                return absint( $target );
            }
            $post_id = url_to_postid( $target );
            if ( $post_id > 0 ) {
                return (int) $post_id;
            }
            $parts = wp_parse_url( $target );
            if ( is_array( $parts ) && ! empty( $parts["query"] ) ) {
                parse_str( (string) $parts["query"], $query );
                if ( ! empty( $query["post"] ) ) {
                    return absint( $query["post"] );
                }
                if ( ! empty( $query["p"] ) ) {
                    return absint( $query["p"] );
                }
            }
        }

        $post_types = array_values( array_filter( MultiAuthors::supported_post_types(), "post_type_exists" ) );
        $latest = get_posts(
            [
                "post_type" => $post_types ?: [ "post" ],
                "post_status" => "publish",
                "posts_per_page" => 1,
                "orderby" => "date",
                "order" => "DESC",
                "fields" => "ids",
                "no_found_rows" => true,
            ]
        );
        return ! empty( $latest ) ? (int) $latest[0] : 0;
    }

    private function fetch_frontend_author_state( int $post_id ): array {
        $url = add_query_arg( "smpi_multi_author_test", (string) time(), get_permalink( $post_id ) );
        $headers = [
            "Cache-Control" => "no-cache",
            "Pragma" => "no-cache",
        ];
        $cookie_header = $this->current_request_cookie_header();
        if ( "" !== $cookie_header ) {
            $headers["Cookie"] = $cookie_header;
        }
        $response = wp_remote_get(
            $url,
            [
                "timeout" => 15,
                "redirection" => 5,
                "headers" => $headers,
            ]
        );

        $state = [
            "http_code" => 0,
            "error" => "",
            "marker_count" => 0,
            "primary_marker_count" => 0,
            "legacy_marker_count" => 0,
            "item_count" => 0,
            "stack_count" => 0,
            "share_count" => 0,
            "schema_author_ids" => [],
            "visible_name_counts" => [],
            "author_link_counts" => [],
            "detected_units" => [],
        ];

        if ( is_wp_error( $response ) ) {
            $state["error"] = $response->get_error_message();
            return $state;
        }

        $state["http_code"] = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );
        if ( "" === $body ) {
            $state["error"] = "Empty frontend response.";
            return $state;
        }

        $state["primary_marker_count"] = substr_count( $body, "smp-author" );
        $state["legacy_marker_count"] = substr_count( $body, "smpi-author-module" );
        $state["marker_count"] = (int) $state["primary_marker_count"] + (int) $state["legacy_marker_count"];
        $state["item_count"] = substr_count( $body, "smpi-multi-author-item" );
        $state["share_count"] = substr_count( strtolower( $body ), ">share<" ) + substr_count( strtolower( $body ), " share " );
        if ( preg_match_all( '/data-smpi-multi-author-count=["\'](\d+)["\']/i', $body, $matches ) ) {
            $state["stack_count"] = array_sum( array_map( "absint", $matches[1] ) );
        }
        $state["schema_author_ids"] = $this->frontend_schema_author_ids( $body );
        foreach ( MultiAuthors::author_view_models_for_post( $post_id ) as $author ) {
            $name = (string) $author["name"];
            $state["visible_name_counts"][ $name ] = substr_count( wp_strip_all_tags( $body ), $name );
            $url = untrailingslashit( (string) $author["url"] );
            $state["author_link_counts"][ $name ] = "" !== $url ? substr_count( $body, $url ) : 0;
        }
        $state["detected_units"] = $this->frontend_detected_author_units( $body );

        return $state;
    }

    private function current_request_cookie_header(): string {
        if ( empty( $_COOKIE ) || ! is_array( $_COOKIE ) ) {
            return "";
        }

        $pairs = [];
        foreach ( $_COOKIE as $name => $value ) {
            if ( ! is_scalar( $value ) ) {
                continue;
            }
            $cookie_name = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $name );
            if ( "" === $cookie_name ) {
                continue;
            }
            $cookie_value = str_replace( [ "\r", "\n", ";" ], "", (string) wp_unslash( $value ) );
            $pairs[] = $cookie_name . "=" . $cookie_value;
        }

        return implode( "; ", $pairs );
    }

    private function frontend_detected_author_units( string $body ): array {
        if ( ! class_exists( "\\DOMDocument" ) || "" === trim( $body ) ) {
            return [];
        }

        $previous = libxml_use_internal_errors( true );
        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML( "<?xml encoding=\"utf-8\" ?>" . $body, LIBXML_NOWARNING | LIBXML_NOERROR );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( ! $loaded ) {
            return [];
        }

        $xpath = new \DOMXPath( $dom );
        $nodes = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' smp-author ') or contains(concat(' ', normalize-space(@class), ' '), ' smpi-author-module ') or contains(concat(' ', normalize-space(@class), ' '), ' smpi-multi-author-item ')]" );
        if ( ! $nodes ) {
            return [];
        }

        $units = [];
        foreach ( $nodes as $node ) {
            if ( ! $node instanceof \DOMElement ) {
                continue;
            }
            $text = trim( preg_replace( "/\s+/", " ", (string) $node->textContent ) );
            $links = [];
            $link_nodes = $xpath->query( ".//a[contains(@href, '/author/')]", $node );
            if ( $link_nodes ) {
                foreach ( $link_nodes as $link_node ) {
                    if ( $link_node instanceof \DOMElement ) {
                        $href = trim( (string) $link_node->getAttribute( "href" ) );
                        if ( "" !== $href ) {
                            $links[] = $href;
                        }
                    }
                }
            }

            $units[] = [
                "classes" => trim( (string) $node->getAttribute( "class" ) ),
                "author_id" => (string) $node->getAttribute( "data-smpi-author-id" ),
                "author_index" => (string) $node->getAttribute( "data-smpi-author-index" ),
                "loop_output" => (string) $node->getAttribute( "data-smpi-loop-output" ),
                "text" => wp_trim_words( $text, 18, "..." ),
                "author_links" => array_values( array_unique( $links ) ),
                "has_share_text" => (bool) preg_match( "/(^|\s)share($|\s)/i", $text ),
                "has_read_time_text" => (bool) preg_match( "/\bmin read\b/i", $text ),
            ];

            if ( count( $units ) >= 30 ) {
                break;
            }
        }

        return $units;
    }

    private function frontend_schema_author_ids( string $body ): array {
        if ( ! preg_match( '/<script[^>]+id=["\']smpi-schema-jsonld["\'][^>]*>(.*?)<\/script>/is', $body, $match ) ) {
            return [];
        }
        $json = html_entity_decode( trim( (string) $match[1] ), ENT_QUOTES | ENT_HTML5, "UTF-8" );
        $schema = json_decode( $json, true );
        return is_array( $schema ) ? $this->schema_author_ids( $schema ) : [];
    }

    private function schema_author_ids( array $schema ): array {
        $graph = isset( $schema["@graph"] ) && is_array( $schema["@graph"] ) ? $schema["@graph"] : [ $schema ];
        foreach ( $graph as $node ) {
            if ( ! is_array( $node ) || empty( $node["@type"] ) || empty( $node["author"] ) ) {
                continue;
            }
            $types = is_array( $node["@type"] ) ? $node["@type"] : [ $node["@type"] ];
            $is_article = false;
            foreach ( $types as $type ) {
                if ( false !== strpos( (string) $type, "Article" ) ) {
                    $is_article = true;
                    break;
                }
            }
            if ( ! $is_article ) {
                continue;
            }
            $authors = isset( $node["author"][0] ) ? $node["author"] : [ $node["author"] ];
            $ids = [];
            foreach ( $authors as $author ) {
                if ( is_array( $author ) && ! empty( $author["@id"] ) ) {
                    $ids[] = (string) $author["@id"];
                }
            }
            return array_values( array_unique( $ids ) );
        }
        return [];
    }

    private function same_values( array $expected, array $actual ): bool {
        sort( $expected );
        sort( $actual );
        return $expected === $actual;
    }

    private function multi_author_test_html( array $report ): string {
        $ok_hook = ! empty( $report["hook_rendered"] );
        $ok_schema = ! empty( $report["schema_matches"] ) && ! empty( $report["frontend_schema_matches"] );
        $boundary_issues = array_filter(
            (array) ( $report["detected_units"] ?? [] ),
            static fn( array $unit ): bool => ! empty( $unit["has_share_text"] ) || ! empty( $unit["has_read_time_text"] )
        );
        $html = "<div class=\"smpi-test-proof\">";
        $html .= "<p>" . ( $ok_hook ? "<span class=\"smpi-ico smpi-ico--ok\">✓</span>" : "<span class=\"smpi-ico smpi-ico--warn\">!</span>" ) . " <strong>" . esc_html( (string) $report["title"] ) . "</strong> <a target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( (string) $report["permalink"] ) . "\">Open post</a></p>";
        $html .= "<div class=\"smpi-test-proof-grid\">";
        $html .= "<div><strong>Feature enabled</strong><br>" . ( ! empty( $report["enabled"] ) ? "Yes" : "No" ) . "</div>";
        $html .= "<div><strong>Loop/card mode</strong><br><code>" . esc_html( (string) ( $report["loop_output"] ?? "comma" ) ) . "</code>" . ( ! empty( $report["loop_cards_disabled"] ) ? "<br>Loop cards forced to primary only." : "" ) . "</div>";
        $html .= "<div><strong>Resolved authors</strong><br><code>" . esc_html( implode( ", ", (array) $report["resolved_ids"] ) ) . "</code></div>";
        $html .= "<div><strong>Schema match</strong><br>" . ( $ok_schema ? "Yes" : "No" ) . "</div>";
        $html .= "<div><strong>Elementor hook</strong><br>" . ( ! empty( $report["hook_ready"] ) ? "Target matched" : "Missing target" ) . "</div>";
        $html .= "<div><strong>Class detection</strong><br><code>smp-author</code>: " . esc_html( (string) $report["primary_marker_count"] ) . "<br><code>smpi-author-module</code>: " . esc_html( (string) $report["legacy_marker_count"] ) . "</div>";
        $html .= "<div><strong>Rendered stack</strong><br>" . esc_html( (string) $report["item_count"] ) . " marked item(s); score " . esc_html( (string) $report["stack_count"] ) . "</div>";
        $html .= "<div><strong>Share text hits</strong><br>" . esc_html( (string) $report["share_count"] ) . "</div>";
        $html .= "<div><strong>Frontend HTTP</strong><br>" . esc_html( (string) $report["frontend_http_code"] ) . ( "" !== (string) $report["frontend_error"] ? " - " . esc_html( (string) $report["frontend_error"] ) : "" ) . "</div>";
        $html .= "</div>";
        $html .= "<table class=\"widefat striped\"><thead><tr><th>Index</th><th>User</th><th>Email</th><th>Author URL</th><th>Visible text count</th><th>Author link count</th></tr></thead><tbody>";
        foreach ( (array) $report["authors"] as $index => $author ) {
            $name = (string) $author["name"];
            $count = isset( $report["visible_name_counts"][ $name ] ) ? (int) $report["visible_name_counts"][ $name ] : 0;
            $link_count = isset( $report["author_link_counts"][ $name ] ) ? (int) $report["author_link_counts"][ $name ] : 0;
            $html .= "<tr><td>" . esc_html( (string) $index ) . "</td><td>" . esc_html( $name ) . " (#" . esc_html( (string) $author["id"] ) . ")</td><td>" . esc_html( (string) $author["email"] ) . "</td><td><code>" . esc_html( (string) $author["url"] ) . "</code></td><td>" . esc_html( (string) $count ) . "</td><td>" . esc_html( (string) $link_count ) . "</td></tr>";
        }
        $html .= "</tbody></table>";
        $html .= "<h3>Detected author units</h3>";
        if ( empty( $report["detected_units"] ) ) {
            $html .= "<p class=\"smpi-alert smpi-alert-warning\">No marked author unit was found. Add <code>smp-author</code> to the exact author identity container, or rely on the loop-card fallback where Elementor exposes a native author link.</p>";
        } else {
            $html .= "<table class=\"widefat striped\"><thead><tr><th>#</th><th>Class</th><th>Author ID</th><th>Loop mode</th><th>Author links</th><th>Boundary flags</th><th>Detected text</th></tr></thead><tbody>";
            foreach ( (array) $report["detected_units"] as $index => $unit ) {
                $links = [];
                foreach ( (array) ( $unit["author_links"] ?? [] ) as $href ) {
                    $links[] = "<code>" . esc_html( (string) $href ) . "</code>";
                }
                $flags = [];
                if ( ! empty( $unit["has_share_text"] ) ) {
                    $flags[] = "Share inside unit";
                }
                if ( ! empty( $unit["has_read_time_text"] ) ) {
                    $flags[] = "Read-time inside unit";
                }
                $loop_output = "" !== (string) ( $unit["loop_output"] ?? "" ) ? (string) $unit["loop_output"] : "n/a";
                $html .= "<tr><td>" . esc_html( (string) $index ) . "</td><td><code>" . esc_html( (string) ( $unit["classes"] ?? "" ) ) . "</code></td><td><code>" . esc_html( (string) ( $unit["author_id"] ?? "" ) ) . "</code></td><td><code>" . esc_html( $loop_output ) . "</code></td><td>" . ( $links ? implode( "<br>", $links ) : "<span class=\"smpi-muted\">none</span>" ) . "</td><td>" . ( $flags ? esc_html( implode( ", ", $flags ) ) : "OK" ) . "</td><td>" . esc_html( (string) ( $unit["text"] ?? "" ) ) . "</td></tr>";
            }
            $html .= "</tbody></table>";
        }
        if ( ! empty( $boundary_issues ) ) {
            $html .= "<p class=\"smpi-alert smpi-alert-warning smpi-detected-unit-warning\">At least one detected author unit includes Share or read-time text. Move <code>smp-author</code> down to the smallest repeated author identity unit so the plugin does not duplicate unrelated controls.</p>";
        }
        $html .= "<p class=\"smpi-muted\">Generated schema authors: <code>" . esc_html( implode( ", ", (array) $report["generated_schema_author_ids"] ) ) . "</code></p>";
        $html .= "<p class=\"smpi-muted\">Fetched frontend schema authors: <code>" . esc_html( implode( ", ", (array) $report["frontend_schema_author_ids"] ) ) . "</code></p>";
        if ( ! $ok_hook ) {
            $html .= "<p class=\"smpi-alert smpi-alert-warning\">The author resolver and schema can pass while the visual Elementor hook is inactive. Add <code>smp-author</code> to the exact Elementor author unit that should repeat. Legacy <code>smpi-author-module</code> still works for older templates.</p>";
        }
        return $html . "</div>";
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
        if ( "smpi_shortcodes" === $source ) {
            $needle = strtolower( $query );
            $hits = [];
            foreach ( Dashboard::shortcode_catalog() as $ctx ) {
                foreach ( $ctx["items"] as $item ) {
                    $tag = (string) $item["tag"];
                    $code = isset( $item["code"] ) ? (string) $item["code"] : "[" . $tag . "]";
                    $idesc = isset( $item["desc"] ) ? (string) $item["desc"] : "";
                    $depk = ! empty( $item["deprecated"] ) ? " deprecated legacy" : "";
                    $hay = strtolower( $tag . " " . $idesc . " " . (string) $ctx["title"] . $depk );
                    if ( "" === $needle || false !== strpos( $hay, $needle ) ) {
                        $hits[] = [ "id" => "", "value" => $tag, "name" => $code, "subtitle" => wp_trim_words( $idesc, 12 ) ];
                    }
                    if ( count( $hits ) >= $limit ) { return $hits; }
                }
            }
            return $hits;
        }
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

}
