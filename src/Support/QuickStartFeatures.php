<?php
namespace smp_publication_integration\Support;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class QuickStartFeatures {
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
}
