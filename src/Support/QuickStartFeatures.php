<?php
namespace smp_publication_integration\Support;

use Hexa\PluginCore\GettingStartedChecklist\ChecklistReportBuilder;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class QuickStartFeatures {
    private const NONCE_ACTION = 'smpi_admin';
    private const NONCE_FIELD  = 'nonce';
    private const RUN_ACTION   = 'smpi_quick_start_checklist_run_item';

    public static function checklist_config(): GettingStartedChecklistConfig {
        return new GettingStartedChecklistConfig(
            [
                'root_id'       => 'smpi-quick-start-checklist',
                'title'         => 'Quick Start',
                'description'   => 'Applies the SMP publication baseline through the reusable Hexa WP Core checklist. Each item reports what the SMP setting value was before, what was saved, and what was verified afterward.',
                'capability'    => 'manage_options',
                'nonce_action'  => self::NONCE_ACTION,
                'nonce_field'   => self::NONCE_FIELD,
                'run_action'    => self::RUN_ACTION,
                'empty_message' => 'No SMP Quick Start checklist items are registered.',
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

        $targets = is_array( $item['settings'] ?? null ) ? $item['settings'] : [];
        if ( [] === $targets ) {
            return self::checklist_result( false, 'SMP Quick Start item has no target settings.', 'error', [], [ 'item_id' => $item_id ] );
        }

        $before_settings = Settings::all();
        $before_matches  = self::count_matching_settings( $targets, $before_settings );

        Settings::update( $targets );

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
                'action'  => 'Saved smpi_settings[' . (string) $key . '] as ' . self::format_value( $target ) . '.',
                'after'   => self::format_value( $after ),
                'meaning' => $matched_after
                    ? ( $matched_before ? 'Already matched the baseline before this run; verified unchanged afterward.' : 'Updated to the baseline value and verified afterward.' )
                    : 'The saved value did not verify against the baseline target.',
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
                'summary' => $after_matches . ' of ' . $total . ' SMP setting' . ( 1 === $total ? '' : 's' ) . ' verified against the Quick Start baseline.',
                'meta'    => [
                    'documentation' => 'This report reads SMP settings before the action, writes the exact Quick Start baseline values, then reads the same settings again to prove the verified after state.',
                    'summary_items' => [
                        [
                            'label' => 'Before',
                            'value' => $before_matches . ' of ' . $total . ' target setting' . ( 1 === $total ? '' : 's' ) . ' already matched before applying.',
                        ],
                        [
                            'label' => 'Action Taken',
                            'value' => 'Saved the ' . $title . ' baseline into the smpi_settings option.',
                        ],
                        [
                            'label' => 'Verified After',
                            'value' => $after_matches . ' of ' . $total . ' target setting' . ( 1 === $total ? '' : 's' ) . ' matched after saving.',
                        ],
                    ],
                ],
            ]
        );

        return self::checklist_result(
            $success,
            $success ? $title . ' baseline applied and verified.' : $title . ' baseline was saved but did not fully verify.',
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
                'type'         => 'feature_toggle',
                'description'  => self::checklist_description( $item ),
                'action_label' => 'Apply Settings',
                'callback'     => [ self::class, 'run_checklist_item' ],
                'context'      => [
                    'quick_start_item' => (string) $item_id,
                ],
            ];
        }

        return $steps;
    }

    public static function items(): array {
        return [
            "elementor_css_cache_busting" => [
                "title" => "Elementor CSS cache busting",
                "description" => "Apply Mash Viral's frontend asset safety setting for Elementor upload CSS.",
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
                "description" => "Match Mash Viral's author badge behavior, style, contexts, icon colors, icon sizes, and fallback mode.",
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
                "description" => "Enable Mash Viral's multiple-author handling and loop-card output mode.",
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
                "description" => "Enable Mash Viral's publication-level MuckRack block styling and article placement.",
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
                "description" => "Match Mash Viral's press-release inclusion state. The feature stays available but disabled by default.",
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
                "description" => "Enable Mash Viral's article-type taxonomy selector for schema-backed article classification.",
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
                "description" => "Apply Mash Viral's breadcrumb display, template, accent color, font size, and archive/home behavior.",
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
                "description" => "Apply Mash Viral's table-of-contents state, design, colors, font style, font size, and summary behavior.",
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
                "description" => "Match Mash Viral's article-heading configuration. The saved template/color/size values are copied, but the feature remains disabled to match Mash Viral.",
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
                "description" => "Apply the editorial drop-cap treatment used for Block Editorial's current feature setup. Mash Viral's source version does not yet carry this new setting.",
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
                "description" => "Apply Mash Viral's inline figure treatment, accent color, caption text color, font style, and font size.",
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
                "description" => "Apply Mash Viral's featured-image caption template, accent color, caption color, font style, and font size.",
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
            "article_summary_faq_blocks" => [
                "title" => "Article Summary & FAQ Blocks",
                "description" => "Apply Mash Viral's editor-field enablement and frontend output styles for article summaries and FAQs.",
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
                "description" => "Enable Mash Viral's cleanup for empty publication-level Elementor social links.",
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

    public static function apply( string $id ): array {
        $items = self::items();
        if ( "all" === $id ) {
            $changes = [];
            foreach ( $items as $item ) {
                $changes = array_merge( $changes, $item["settings"] );
            }
            Settings::update( $changes );
            return [
                "applied" => array_keys( $items ),
                "settings" => $changes,
            ];
        }

        if ( ! isset( $items[ $id ] ) ) {
            return [
                "applied" => [],
                "settings" => [],
            ];
        }

        Settings::update( $items[ $id ]["settings"] );
        return [
            "applied" => [ $id ],
            "settings" => $items[ $id ]["settings"],
        ];
    }

    public static function is_complete( string $id, ?array $current = null ): bool {
        $item = self::item( $id );
        if ( ! $item ) {
            return false;
        }

        $current = $current ?? Settings::all();
        foreach ( $item["settings"] as $key => $target ) {
            $actual = $current[ $key ] ?? null;
            if ( is_array( $target ) ) {
                $target_values = array_values( array_map( "strval", $target ) );
                $actual_values = is_array( $actual ) ? array_values( array_map( "strval", $actual ) ) : [];
                sort( $target_values );
                sort( $actual_values );
                if ( $target_values !== $actual_values ) {
                    return false;
                }
                continue;
            }

            if ( is_bool( $target ) ) {
                if ( (bool) $actual !== $target ) {
                    return false;
                }
                continue;
            }

            if ( is_int( $target ) ) {
                if ( (int) $actual !== $target ) {
                    return false;
                }
                continue;
            }

            if ( (string) $actual !== (string) $target ) {
                return false;
            }
        }

        return true;
    }

    private static function checklist_description( array $item ): string {
        $description = trim( (string) ( $item['description'] ?? '' ) );
        $details     = [];

        foreach ( (array) ( $item['details'] ?? [] ) as $detail ) {
            if ( ! is_array( $detail ) ) {
                continue;
            }

            $label = trim( (string) ( $detail['label'] ?? '' ) );
            $value = trim( (string) ( $detail['value'] ?? '' ) );
            if ( '' === $label && '' === $value ) {
                continue;
            }

            $details[] = ( '' !== $label ? $label . ': ' : '' ) . $value;
        }

        if ( [] !== $details ) {
            $description .= ( '' !== $description ? ' ' : '' ) . 'Targets: ' . implode( '; ', $details ) . '.';
        }

        return $description;
    }

    private static function setting_label( string $setting_key, array $item ): string {
        foreach ( (array) ( $item['details'] ?? [] ) as $detail ) {
            if ( ! is_array( $detail ) ) {
                continue;
            }

            $label = trim( (string) ( $detail['label'] ?? '' ) );
            $value = trim( (string) ( $detail['value'] ?? '' ) );
            if ( '' !== $label && false !== stripos( str_replace( '_', ' ', $setting_key ), strtolower( str_replace( ' ', '_', $label ) ) ) ) {
                return $label . ' (' . $setting_key . ')';
            }
            if ( '' !== $value && false !== stripos( $setting_key, sanitize_key( $label ) ) ) {
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
            $values = array_map( static fn( mixed $item ): string => is_scalar( $item ) || null === $item ? (string) $item : wp_json_encode( $item ), $value );
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
