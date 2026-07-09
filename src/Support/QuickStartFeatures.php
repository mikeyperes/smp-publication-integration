<?php
namespace smp_publication_integration\Support;

use Hexa\PluginCore\ContentCleanup\ArticleMediaCleanupScanner;
use Hexa\PluginCore\GettingStartedChecklist\ChecklistReportBuilder;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class QuickStartFeatures {
    private const NONCE_ACTION = 'smpi_admin';
    private const NONCE_FIELD  = 'nonce';
    private const RUN_ACTION   = 'smpi_quick_start_checklist_run_item';
    private const QUICK_CLEANUP_SCAN_ACTION = 'smpi_quick_start_article_cleanup_scan';
    private const QUICK_CLEANUP_BATCH_ACTION = 'smpi_quick_start_article_cleanup_batch_delete';
    private const QUICK_CLEANUP_STEP_ID = 'delete_old_posts_keep_latest_10';
    private const DELETE_POSTS_AND_MEDIA_CONFIRMATION = 'DELETE POSTS AND MEDIA';

    public static function checklist_config(): GettingStartedChecklistConfig {
        return new GettingStartedChecklistConfig(
            [
                'root_id'       => 'smpi-quick-start-checklist',
                'title'         => 'Quick Start',
                'description'   => 'Apply standard publication settings one item at a time.',
                'capability'    => 'manage_options',
                'nonce_action'  => self::NONCE_ACTION,
                'nonce_field'   => self::NONCE_FIELD,
                'run_action'    => self::RUN_ACTION,
                'quick_cleanup_step_id' => self::QUICK_CLEANUP_STEP_ID,
                'quick_cleanup_scan_action' => self::QUICK_CLEANUP_SCAN_ACTION,
                'quick_cleanup_batch_action' => self::QUICK_CLEANUP_BATCH_ACTION,
                'empty_message' => 'No SMP Quick Start checklist items are registered.',
                'show_type_badges' => false,
                'steps'         => self::checklist_steps(),
            ]
        );
    }

    public static function register_checklist_ajax(): void {
        static $registered = false;

        if ( $registered ) {
            return;
        }

        ( new GettingStartedChecklistAjaxController( self::checklist_config() ) )->register();
        AjaxActionRegistry::create(
            [
                'capability'   => 'manage_options',
                'nonce_action' => self::NONCE_ACTION,
                'nonce_field'  => self::NONCE_FIELD,
            ]
        )->register(
            [
                self::QUICK_CLEANUP_SCAN_ACTION => [
                    'callback' => [ self::class, 'quick_cleanup_scan' ],
                ],
                self::QUICK_CLEANUP_BATCH_ACTION => [
                    'callback' => [ self::class, 'quick_cleanup_batch_delete' ],
                ],
            ]
        );

        $registered = true;
    }

    public static function run_checklist_item( array $payload ): array {
        $context = is_array( $payload['context'] ?? null ) ? $payload['context'] : [];
        $step    = is_array( $payload['step'] ?? null ) ? $payload['step'] : [];
        $item_id = sanitize_key( (string) ( $context['quick_start_item'] ?? $step['id'] ?? '' ) );
        $item    = self::item( $item_id );

        if ( ! $item ) {
            return self::checklist_result( false, 'Unknown SMP Quick Start item.', 'error', [], [ 'item_id' => $item_id ] );
        }

        if ( self::QUICK_CLEANUP_STEP_ID === $item_id ) {
            return self::delete_old_posts_keep_latest_10( $payload );
        }

        $targets = is_array( $item['settings'] ?? null ) ? $item['settings'] : [];
        if ( [] === $targets ) {
            return self::checklist_result( false, 'SMP Quick Start item has no target settings.', 'error', [], [ 'item_id' => $item_id ] );
        }

        $before_settings = Settings::all();
        $before_matches  = self::count_matching_settings( $targets, $before_settings );

        Settings::update( $targets );
        self::purge_frontend_cache();

        $after_settings = Settings::all();
        $after_matches  = self::count_matching_settings( $targets, $after_settings );
        $rows           = [];

        foreach ( $targets as $key => $target ) {
            $before = $before_settings[ $key ] ?? null;
            $after  = $after_settings[ $key ] ?? null;
            $matched_before = self::setting_matches( $target, $before );
            $matched_after  = self::setting_matches( $target, $after );

            $rows[] = [
                'item'    => self::setting_label( (string) $key, $item ),
                'before'  => self::format_value( $before ),
                'action'  => 'Saved target value: ' . self::format_value( $target ) . '.',
                'after'   => self::format_value( $after ),
                'meaning' => $matched_after
                    ? ( $matched_before ? 'Already matched before this run.' : 'Updated and verified after saving.' )
                    : 'Saved value did not match the target after saving.',
            ];
        }

        $total   = count( $targets );
        $success = $after_matches === $total;
        $title   = (string) ( $item['title'] ?? $item_id );

        $report = ChecklistReportBuilder::before_after(
            $title . ' Settings',
            $rows,
            [
                'type'    => 'smpi_quick_start_settings',
                'summary' => $after_matches . ' of ' . $total . ' setting' . ( 1 === $total ? '' : 's' ) . ' verified.',
            ]
        );

        return self::checklist_result(
            $success,
            $success ? 'Applied and verified.' : 'Applied but needs review.',
            $success ? 'success' : 'warning',
            [ $report ],
            [
                'item_id'        => $item_id,
                'target_count'   => $total,
                'before_matches' => $before_matches,
                'after_matches'  => $after_matches,
            ]
        );
    }

    public static function checklist_steps(): array {
        $steps = [];

        foreach ( self::items() as $item_id => $item ) {
            $steps[] = [
                'id'           => (string) $item_id,
                'label'        => (string) ( $item['title'] ?? $item_id ),
                'type'         => (string) ( $item['type'] ?? 'feature_toggle' ),
                'description'  => self::checklist_description( $item ),
                'callback'     => [ self::class, 'run_checklist_item' ],
                'context'      => [
                    'quick_start_item' => (string) $item_id,
                ],
                'required_inputs' => is_array( $item['required_inputs'] ?? null ) ? $item['required_inputs'] : [],
            ];
        }

        return $steps;
    }

    public static function items(): array {
        return [
            "delete_old_posts_keep_latest_10" => [
                "title" => "Delete Old Posts, Keep Latest 10",
                "description" => "Deletes regular posts and their associated media. Default keeps the newest 10 posts; set Posts to keep to 0 to delete all regular posts.",
                "type" => "setup_action",
                "required_inputs" => [
                    [
                        "id" => "delete_old_posts_keep_recent",
                        "label" => "Posts to keep",
                        "type" => "number",
                        "required" => true,
                        "value" => "10",
                        "min" => 0,
                        "max" => 5000,
                        "step" => 1,
                        "description" => "Default is 10. Use 0 only when deleting all regular posts. Associated featured, inline, and gallery media are deleted for every deleted post.",
                    ],
                    ChecklistReportBuilder::confirmation_input(
                        "delete_old_posts_confirmation",
                        self::DELETE_POSTS_AND_MEDIA_CONFIRMATION,
                        "Delete posts and media confirmation",
                        [
                            "description" => "Type exactly: " . self::DELETE_POSTS_AND_MEDIA_CONFIRMATION . ". This permanently deletes matching regular posts and their associated featured, inline, and gallery media.",
                        ]
                    ),
                ],
                "details" => [
                    [ "label" => "Post type", "value" => "post" ],
                    [ "label" => "Default", "value" => "Keep newest 10 posts" ],
                    [ "label" => "Delete all option", "value" => "Set Posts to keep to 0" ],
                    [ "label" => "Deletes media", "value" => "Associated featured, inline, and gallery media" ],
                ],
            ],
            "elementor_css_cache_busting" => [
                "title" => "Elementor CSS cache busting",
                "description" => "Keeps Elementor upload CSS cache-safe on the frontend.",
                "settings" => [
                    "elementor_css_cache_busting" => true,
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Scope", "value" => "/wp-content/uploads/elementor/css/ only" ],
                ],
            ],
            "muckrack_verified_authors" => [
                "title" => "MuckRack verified authors",
                "description" => "Shows MuckRack verification badges for authors.",
                "settings" => [
                    "muckrack_verified_enabled" => true,
                    "muckrack_author_always_show" => true,
                    "muckrack_verified_contexts" => [ "single_author", "single_footer", "author", "home", "loop_cards" ],
                    "muckrack_verified_style" => "tooltip",
                    "muckrack_icon_color" => "#000033",
                    "muckrack_icon_style" => "circle_check",
                    "muckrack_icon_size" => 24,
                    "muckrack_icon_margin_left" => 2,
                    "muckrack_icon_margin_top" => 0,
                    "muckrack_icon_color_single_author" => "#2f55ff",
                    "muckrack_icon_size_single_author" => 22,
                    "muckrack_icon_margin_left_single_author" => "",
                    "muckrack_icon_margin_top_single_author" => "",
                    "muckrack_icon_color_single_footer" => "#2f55ff",
                    "muckrack_icon_size_single_footer" => 28,
                    "muckrack_icon_margin_left_single_footer" => "",
                    "muckrack_icon_margin_top_single_footer" => "",
                    "muckrack_icon_color_loop_cards" => "#2f55ff",
                    "muckrack_icon_size_loop_cards" => 24,
                    "muckrack_icon_margin_left_loop_cards" => "",
                    "muckrack_icon_margin_top_loop_cards" => "",
                    "muckrack_icon_color_home" => "",
                    "muckrack_icon_size_home" => 0,
                    "muckrack_icon_margin_left_home" => "",
                    "muckrack_icon_margin_top_home" => "",
                    "muckrack_icon_color_author" => "",
                    "muckrack_icon_size_author" => 0,
                    "muckrack_icon_margin_left_author" => "",
                    "muckrack_icon_margin_top_author" => "",
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Always show", "value" => "Yes" ],
                    [ "label" => "Display style", "value" => "Tooltip" ],
                    [ "label" => "Icon style", "value" => "Circle check" ],
                    [ "label" => "Default icon color", "value" => "#000033", "color" => "#000033" ],
                    [ "label" => "Default icon size", "value" => "24px" ],
                    [ "label" => "Author/header/footer/loop color", "value" => "#2f55ff", "color" => "#2f55ff" ],
                    [ "label" => "Contexts", "value" => "single_author, single_footer, author, home, loop_cards" ],
                ],
            ],
            "multiple_post_authors" => [
                "title" => "Multiple post authors",
                "description" => "Allows posts to show more than one author.",
                "settings" => [
                    "multi_authors_enabled" => true,
                    "multi_authors_disable_loop_cards" => false,
                    "multi_authors_loop_output" => "lines",
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Loop card output", "value" => "Lines" ],
                    [ "label" => "Loop cards disabled", "value" => "No" ],
                ],
            ],
            "muckrack_verified_publication" => [
                "title" => "MuckRack verified publication",
                "description" => "Shows the publication MuckRack verification block.",
                "settings" => [
                    "publication_muckrack_verified_enabled" => true,
                    "publication_muckrack_text_mode" => "news_outlet",
                    "publication_muckrack_style" => "mini_block",
                    "publication_muckrack_color" => "#000033",
                    "publication_muckrack_font_size" => 13,
                    "publication_muckrack_placements" => [ "bottom_article" ],
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Text mode", "value" => "News outlet" ],
                    [ "label" => "Display style", "value" => "Mini editorial block" ],
                    [ "label" => "Accent color", "value" => "#000033", "color" => "#000033" ],
                    [ "label" => "Font size", "value" => "13px" ],
                    [ "label" => "Placement", "value" => "Bottom of article" ],
                ],
            ],
            "press_release_inclusion_controls" => [
                "title" => "Press-release inclusion controls",
                "description" => "Controls whether press releases appear in article lists.",
                "settings" => [
                    "press_release_include_enabled" => false,
                    "press_release_include_contexts" => [],
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "No" ],
                    [ "label" => "Contexts", "value" => "None" ],
                ],
            ],
            "article_type_schema_selector" => [
                "title" => "Article type schema selector",
                "description" => "Adds the article type selector used for schema.",
                "settings" => [
                    "article_types_enabled" => true,
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Types", "value" => "editorial-news, analysis, opinion, reportage, press-release, sponsored" ],
                ],
            ],
            "breadcrumbs" => [
                "title" => "Breadcrumbs",
                "description" => "Shows breadcrumb navigation on article and archive pages.",
                "settings" => [
                    "breadcrumbs_enabled" => true,
                    "breadcrumbs_style" => "bc-b6",
                    "breadcrumbs_accent_color" => "#000033",
                    "breadcrumbs_font_size" => 11,
                    "breadcrumbs_hide_home" => true,
                    "breadcrumbs_hide_term_archives" => false,
                    "breadcrumbs_disabled_post_types" => [],
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Template", "value" => "bc-b6" ],
                    [ "label" => "Accent color", "value" => "#000033", "color" => "#000033" ],
                    [ "label" => "Font size", "value" => "11px" ],
                    [ "label" => "Hide home", "value" => "Yes" ],
                    [ "label" => "Hide term archives", "value" => "No" ],
                ],
            ],
            "table_of_contents" => [
                "title" => "Table of contents",
                "description" => "Adds a table of contents for article headings.",
                "settings" => [
                    "table_of_contents_enabled" => true,
                    "table_of_contents_auto_single" => false,
                    "table_of_contents_style" => "toc03",
                    "table_of_contents_include_summary" => true,
                    "table_of_contents_accent_color" => "#000033",
                    "table_of_contents_text_font_style" => "normal",
                    "table_of_contents_text_font_size" => 12,
                    "table_of_contents_text_color" => "#000000",
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Auto single placement", "value" => "No" ],
                    [ "label" => "Template", "value" => "toc03" ],
                    [ "label" => "Include summary", "value" => "Yes" ],
                    [ "label" => "Accent color", "value" => "#000033", "color" => "#000033" ],
                    [ "label" => "Text color", "value" => "#000000", "color" => "#000000" ],
                    [ "label" => "Text size/style", "value" => "12px normal" ],
                ],
            ],
            "article_h2_h3_styles" => [
                "title" => "Article H2/H3 styles",
                "description" => "Styles H2 and H3 headings inside article content.",
                "settings" => [
                    "article_heading_styles_enabled" => false,
                    "article_heading_style" => "h2-tick",
                    "article_heading_accent_color" => "#000033",
                    "article_heading_h2_font_size" => 23,
                    "article_heading_h3_font_size" => 20,
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "No" ],
                    [ "label" => "Template", "value" => "h2-tick" ],
                    [ "label" => "Accent color", "value" => "#000033", "color" => "#000033" ],
                    [ "label" => "H2 size", "value" => "23px" ],
                    [ "label" => "H3 size", "value" => "20px" ],
                ],
            ],
            "article_first_letter_drop_cap" => [
                "title" => "Article first-letter drop cap",
                "description" => "Adds a large first-letter treatment to article intros.",
                "settings" => [
                    "article_drop_cap_enabled" => true,
                    "article_drop_cap_color" => "#111111",
                    "article_drop_cap_font_size" => 96,
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Drop cap color", "value" => "#111111", "color" => "#111111" ],
                    [ "label" => "Drop cap size", "value" => "96px" ],
                ],
            ],
            "inline_photo_treatments" => [
                "title" => "Inline photo treatments",
                "description" => "Styles inline article images and captions.",
                "settings" => [
                    "inline_photo_treatments_enabled" => true,
                    "inline_photo_treatment" => "fig2",
                    "inline_photo_accent_color" => "#000033",
                    "inline_photo_caption_font_style" => "italic",
                    "inline_photo_caption_font_size" => 12,
                    "inline_photo_caption_text_color" => "#000000",
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Template", "value" => "fig2" ],
                    [ "label" => "Accent color", "value" => "#000033", "color" => "#000033" ],
                    [ "label" => "Caption color", "value" => "#000000", "color" => "#000000" ],
                    [ "label" => "Caption size/style", "value" => "12px italic" ],
                ],
            ],
            "featured_image_caption_templates" => [
                "title" => "Featured image caption templates",
                "description" => "Styles captions for featured images.",
                "settings" => [
                    "featured_image_caption_templates_enabled" => true,
                    "featured_image_caption_template" => "fig2",
                    "featured_image_caption_accent_color" => "#000033",
                    "featured_image_caption_font_style" => "italic",
                    "featured_image_caption_font_size" => 10,
                    "featured_image_caption_text_color" => "#272727",
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Template", "value" => "fig2" ],
                    [ "label" => "Accent color", "value" => "#000033", "color" => "#000033" ],
                    [ "label" => "Caption color", "value" => "#272727", "color" => "#272727" ],
                    [ "label" => "Caption size/style", "value" => "10px italic" ],
                ],
            ],
            "hide_home_posts_without_featured_image" => [
                "title" => "Hide home posts without featured images",
                "description" => "Keeps posts without featured images off the home page.",
                "settings" => [
                    "hide_home_posts_without_featured_image" => true,
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Post type", "value" => "post" ],
                    [ "label" => "Scope", "value" => "Home/front-page post queries" ],
                ],
            ],
            "post_featured_image_required" => [
                "title" => "Featured image required for posts",
                "description" => "Requires a featured image before posts can publish.",
                "settings" => [
                    "post_featured_image_required" => true,
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Post type", "value" => "post" ],
                    [ "label" => "Guarded states", "value" => "publish, future, pending" ],
                ],
            ],
            "article_summary_faq_blocks" => [
                "title" => "Article Summary & FAQ Blocks",
                "description" => "Enables article summary and FAQ blocks.",
                "settings" => [
                    "post_summary_acf_enabled" => true,
                    "post_faqs_acf_enabled" => true,
                    "post_summary_style" => "sum01",
                    "post_faqs_style" => "faq03",
                    "post_faqs_accent_color" => "#000033",
                    "post_faqs_text_font_style" => "normal",
                    "post_faqs_text_font_size" => 16,
                    "post_faqs_text_color" => "#1f2937",
                ],
                "details" => [
                    [ "label" => "Summary field", "value" => "Enabled" ],
                    [ "label" => "FAQ field", "value" => "Enabled" ],
                    [ "label" => "Summary style", "value" => "sum01" ],
                    [ "label" => "FAQ style", "value" => "faq03" ],
                    [ "label" => "FAQ accent color", "value" => "#000033", "color" => "#000033" ],
                    [ "label" => "FAQ text color", "value" => "#1f2937", "color" => "#1f2937" ],
                    [ "label" => "FAQ size/style", "value" => "16px normal" ],
                ],
            ],
            "publication_social_link_cleanup" => [
                "title" => "Publication social link cleanup",
                "description" => "Hides empty publication social links.",
                "settings" => [
                    "publication_social_cleanup" => true,
                ],
                "details" => [
                    [ "label" => "Enabled", "value" => "Yes" ],
                    [ "label" => "Scope", "value" => "Publication header/footer/global social widgets" ],
                ],
            ],
        ];
    }

    public static function item( string $id ): ?array {
        $items = self::items();
        return $items[ $id ] ?? null;
    }

    public static function quick_cleanup_scan( AjaxRequest $request ): array|\WP_Error {
        $keep_recent = self::quick_cleanup_keep_recent_from_request( $request );
        if ( null === $keep_recent ) {
            return new \WP_Error( 'smpi_quick_cleanup_invalid_keep_recent', 'Posts to keep must be a whole number from 0 to 5000.' );
        }

        $confirmation = trim( $request->text( 'confirmation' ) );
        if ( ! hash_equals( self::DELETE_POSTS_AND_MEDIA_CONFIRMATION, $confirmation ) ) {
            return new \WP_Error( 'smpi_quick_cleanup_invalid_confirmation', 'Delete posts and media confirmation is invalid.' );
        }

        $config  = ArticleCleanup::config();
        $scanner = new ArticleMediaCleanupScanner( $config );

        return $scanner->scan_deletion_plan(
            self::quick_cleanup_criteria( $keep_recent, $config->max_limit() ),
            $keep_recent,
            $config->max_limit()
        );
    }

    public static function quick_cleanup_batch_delete( AjaxRequest $request ): array|\WP_Error {
        $keep_recent = self::quick_cleanup_keep_recent_from_request( $request );
        if ( null === $keep_recent ) {
            return new \WP_Error( 'smpi_quick_cleanup_invalid_keep_recent', 'Posts to keep must be a whole number from 0 to 5000.' );
        }

        $confirmation = trim( $request->text( 'confirmation' ) );
        if ( ! hash_equals( self::DELETE_POSTS_AND_MEDIA_CONFIRMATION, $confirmation ) ) {
            return new \WP_Error( 'smpi_quick_cleanup_invalid_confirmation', 'Delete posts and media confirmation is invalid.' );
        }

        if ( function_exists( 'current_user_can' ) && ! current_user_can( 'delete_posts' ) ) {
            return new \WP_Error( 'smpi_quick_cleanup_delete_permission_denied', 'Current user cannot delete posts.' );
        }

        $config  = ArticleCleanup::config();
        $scanner = new ArticleMediaCleanupScanner( $config );

        return $scanner->delete_batch(
            self::quick_cleanup_criteria( $keep_recent, $config->max_limit() ),
            true,
            'all_except_keep_recent',
            1,
            array_map( 'absint', $request->items( 'exclude_ids' ) )
        );
    }

    private static function quick_cleanup_keep_recent_from_request( AjaxRequest $request ): ?int {
        return self::delete_posts_keep_recent_from_inputs(
            [
                'delete_old_posts_keep_recent' => $request->text( 'keep_recent', '10' ),
            ]
        );
    }

    private static function quick_cleanup_criteria( int $keep_recent, int $limit ): array {
        return [
            'post_type'   => 'post',
            'status'      => 'any',
            'keep_recent' => $keep_recent,
            'search'      => '',
            'limit'       => $limit,
        ];
    }

    private static function purge_frontend_cache(): void {
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }

        foreach ( [ 'litespeed_purge_all', 'litespeed_purge_all_object' ] as $action ) {
            if ( has_action( $action ) ) {
                do_action( $action );
            }
        }

        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
        }

        if ( function_exists( 'w3tc_flush_all' ) ) {
            w3tc_flush_all();
        }
    }

    private static function checklist_description( array $item ): string {
        return trim( (string) ( $item['description'] ?? '' ) );
    }

    private static function delete_old_posts_keep_latest_10( array $payload ): array {
        $inputs       = is_array( $payload['inputs'] ?? null ) ? $payload['inputs'] : [];
        $confirmation = isset( $inputs['delete_old_posts_confirmation'] ) ? trim( (string) $inputs['delete_old_posts_confirmation'] ) : '';
        $keep_recent  = self::delete_posts_keep_recent_from_inputs( $inputs );

        if ( ! hash_equals( self::DELETE_POSTS_AND_MEDIA_CONFIRMATION, $confirmation ) ) {
            return self::checklist_result( false, 'Delete posts and media confirmation is invalid.', 'error' );
        }

        if ( null === $keep_recent ) {
            return self::checklist_result( false, 'Posts to keep must be a whole number from 0 to 5000.', 'error' );
        }

        if ( function_exists( 'current_user_can' ) && ! current_user_can( 'delete_posts' ) ) {
            return self::checklist_result( false, 'Current user cannot delete posts.', 'error' );
        }

        $config   = ArticleCleanup::config();
        $scanner  = new ArticleMediaCleanupScanner( $config );
        $criteria = [
            'post_type'   => 'post',
            'status'      => 'any',
            'keep_recent' => $keep_recent,
            'search'      => '',
            'limit'       => $config->max_limit(),
        ];

        $batch_size          = $config->max_batch_size();
        $exclude_ids         = [];
        $batches             = [];
        $deleted_total       = 0;
        $failed_total        = 0;
        $deleted_media_total = 0;
        $preserved_ids       = [];
        $last_has_more       = false;

        for ( $batch = 1; $batch <= 200; $batch++ ) {
            $result = $scanner->delete_batch( $criteria, true, 'all_except_keep_recent', $batch_size, $exclude_ids );
            if ( is_wp_error( $result ) ) {
                return self::checklist_result(
                    false,
                    'Article cleanup failed: ' . $result->get_error_message(),
                    'error',
                    [],
                    [
                        'item_id'     => 'delete_old_posts_keep_latest_10',
                        'post_type'   => 'post',
                        'keep_recent' => $keep_recent,
                        'batch'       => $batch,
                    ]
                );
            }

            $deleted_count       = (int) ( $result['deleted_count'] ?? 0 );
            $failed_count        = (int) ( $result['failed_count'] ?? 0 );
            $deleted_media_count = (int) ( $result['deleted_media_count'] ?? 0 );
            $preserved_ids       = array_values( array_unique( array_merge( $preserved_ids, (array) ( $result['preserved_ids'] ?? [] ) ) ) );
            $exclude_ids         = array_values( array_unique( (array) ( $result['exclude_ids'] ?? $exclude_ids ) ) );
            $last_has_more       = ! empty( $result['has_more'] );

            $deleted_total       += $deleted_count;
            $failed_total        += $failed_count;
            $deleted_media_total += $deleted_media_count;

            $batches[] = [
                'batch'         => $batch,
                'post_type'     => 'post',
                'deleted_posts' => $deleted_count,
                'failed_posts'  => $failed_count,
                'deleted_media' => $deleted_media_count,
                'preserved_ids' => implode( ', ', array_map( 'strval', (array) ( $result['preserved_ids'] ?? [] ) ) ),
                'failed_ids'    => implode( ', ', array_map( 'strval', (array) ( $result['failed_ids'] ?? [] ) ) ),
            ];

            if ( ! $last_has_more ) {
                break;
            }
        }

        $success = ! $last_has_more && 0 === $failed_total;
        $preserved_label = $keep_recent > 0 ? 'preserved the newest ' . $keep_recent . ' posts' : 'no posts were preserved';
        $message = $deleted_total > 0
            ? sprintf( 'Deleted %d regular posts and %d associated media item(s); %s.', $deleted_total, $deleted_media_total, $preserved_label )
            : ( $keep_recent > 0 ? 'No old posts needed deletion; the newest ' . $keep_recent . ' posts remain protected.' : 'No regular posts needed deletion.' );

        if ( $last_has_more ) {
            $message = 'Article cleanup stopped before all batches completed. Re-run the task to continue.';
        } elseif ( $failed_total > 0 ) {
            $message = sprintf( 'Article cleanup completed with %d failed post deletion(s).', $failed_total );
        }

        return self::checklist_result(
            $success,
            $message,
            $success ? 'success' : 'warning',
            [
                ChecklistReportBuilder::table(
                    'news_outlet_article_cleanup',
                    'News Outlet Article Cleanup',
                    $batches,
                    [
                        'batch'         => 'Batch',
                        'post_type'     => 'Post Type',
                        'deleted_posts' => 'Deleted Posts',
                        'failed_posts'  => 'Failed Posts',
                        'deleted_media' => 'Deleted Media',
                        'preserved_ids' => 'Preserved IDs',
                        'failed_ids'    => 'Failed IDs',
                    ],
                    [
                        'summary' => $keep_recent > 0
                            ? 'Post type post only. The newest ' . $keep_recent . ' matching posts were preserved; older matching posts were deleted with associated media.'
                            : 'Post type post only. No posts were preserved; all matching regular posts were deleted with associated media.',
                        'meta'    => [
                            'documentation' => 'This destructive report scans only WordPress posts, uses the selected Posts to keep value, deletes matching posts in batches, and reports deleted posts, failed posts, deleted media, and preserved IDs.',
                            'summary_items' => [
                                [ 'label' => 'Before', 'value' => $keep_recent > 0 ? 'The scanner selected regular posts with any status and protected the newest ' . $keep_recent . '.' : 'The scanner selected regular posts with any status and did not preserve any posts.' ],
                                [ 'label' => 'Action Taken', 'value' => $deleted_total . ' old post' . ( 1 === $deleted_total ? '' : 's' ) . ' and ' . $deleted_media_total . ' associated media item' . ( 1 === $deleted_media_total ? '' : 's' ) . ' were deleted across ' . count( $batches ) . ' batch' . ( 1 === count( $batches ) ? '' : 'es' ) . '.' ],
                                [ 'label' => 'Verified After', 'value' => $failed_total . ' failed post deletion' . ( 1 === $failed_total ? '' : 's' ) . '; batch limit reached: ' . ( $last_has_more ? 'yes' : 'no' ) . '.' ],
                            ],
                        ],
                    ]
                ),
            ],
            [
                'item_id'             => 'delete_old_posts_keep_latest_10',
                'post_type'           => 'post',
                'status'              => 'any',
                'keep_recent'         => $keep_recent,
                'deleted_posts'       => $deleted_total,
                'failed_posts'        => $failed_total,
                'deleted_media'       => $deleted_media_total,
                'preserved_ids'       => $preserved_ids,
                'batch_limit_reached' => $last_has_more,
            ]
        );
    }

    private static function delete_posts_keep_recent_from_inputs( array $inputs ): ?int {
        $raw = isset( $inputs['delete_old_posts_keep_recent'] ) ? trim( (string) $inputs['delete_old_posts_keep_recent'] ) : '10';

        if ( '' === $raw || ! preg_match( '/^\d+$/', $raw ) ) {
            return null;
        }

        $keep_recent = (int) $raw;
        if ( $keep_recent < 0 || $keep_recent > 5000 ) {
            return null;
        }

        return $keep_recent;
    }

    private static function setting_label( string $setting_key, array $item ): string {
        foreach ( (array) ( $item['details'] ?? [] ) as $detail ) {
            if ( ! is_array( $detail ) ) {
                continue;
            }

            $label = trim( (string) ( $detail['label'] ?? '' ) );
            if ( '' === $label ) {
                continue;
            }

            $normalized_label = sanitize_key( str_replace( ' ', '_', $label ) );
            if ( '' !== $normalized_label && false !== stripos( $setting_key, $normalized_label ) ) {
                return $label . ' (' . $setting_key . ')';
            }
        }

        return ucwords( str_replace( '_', ' ', $setting_key ) ) . ' (' . $setting_key . ')';
    }

    private static function count_matching_settings( array $targets, array $settings ): int {
        $matches = 0;

        foreach ( $targets as $key => $target ) {
            if ( self::setting_matches( $target, $settings[ $key ] ?? null ) ) {
                $matches++;
            }
        }

        return $matches;
    }

    private static function setting_matches( mixed $target, mixed $actual ): bool {
        if ( is_array( $target ) ) {
            $target_values = array_values( array_map( 'strval', $target ) );
            $actual_values = is_array( $actual ) ? array_values( array_map( 'strval', $actual ) ) : [];
            sort( $target_values );
            sort( $actual_values );
            return $target_values === $actual_values;
        }

        if ( is_bool( $target ) ) {
            return (bool) $actual === $target;
        }

        if ( is_int( $target ) ) {
            return (int) $actual === $target;
        }

        return (string) $actual === (string) $target;
    }

    private static function format_value( mixed $value ): string {
        if ( is_bool( $value ) ) {
            return $value ? 'Enabled' : 'Disabled';
        }

        if ( is_array( $value ) ) {
            $values = array_map(
                static fn( mixed $item ): string => is_scalar( $item ) || null === $item ? (string) $item : (string) wp_json_encode( $item ),
                $value
            );

            return [] === $values ? 'None' : implode( ', ', $values );
        }

        if ( null === $value || '' === $value ) {
            return 'Empty';
        }

        return is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
    }

    private static function checklist_result( bool $success, string $message, string $level, array $reports = [], array $context = [] ): array {
        $data = $context;
        if ( [] !== $reports ) {
            $data['reports'] = array_values( array_filter( $reports ) );
        }

        return [
            'success' => $success,
            'message' => $message,
            'logs'    => [
                [
                    'level'   => $level,
                    'message' => $message,
                    'context' => $context,
                ],
            ],
            'data'    => $data,
        ];
    }
}
