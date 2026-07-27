<?php

namespace smp_publication_integration\Design;

use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\BrandColors\FontFamilyProvider;
use Hexa\PluginCore\BrandColors\FontWeightProvider;
use Hexa\PluginCore\BrandColors\TemplateColorResolver;
use Hexa\PluginCore\Typography\TemplateTypography;
use Hexa\PluginCore\Typography\TypographyPreservation;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * SMP-owned template metadata consumed by the generic Core controls.
 */
final class TemplateDesignRegistry {
    public static function definitions(): array {
        $red = "#d63428";
        $blue = "#2563eb";
        $muckrack = "#2d5277";

        return [
            "breadcrumbs" => [
                "source_key" => "breadcrumbs_color_source",
                "custom_key" => "breadcrumbs_accent_color",
                "template_key" => "breadcrumbs_style",
                "default_template" => "bc-b2",
                "fallback" => $red,
                "palettes" => array_fill_keys( [ "bc-b1", "bc-b2", "bc-b3", "bc-b4", "bc-b5", "bc-b6" ], [ "accent" => $red ] ),
                "variables" => [ "--smpi-bc-accent" => "color", "--smpi-bc-tint" => "rgba:0.07" ],
            ],
            "table_of_contents" => [
                "source_key" => "table_of_contents_color_source",
                "custom_key" => "table_of_contents_accent_color",
                "template_key" => "table_of_contents_style",
                "default_template" => "toc02",
                "fallback" => $blue,
                "palettes" => [
                    "none" => [ "accent" => "#9ca3af" ],
                    "toc00" => [ "accent" => $blue ],
                    "toc01" => [ "accent" => $blue ],
                    "toc02" => [ "accent" => $blue ],
                    "toc03" => [ "accent" => $blue ],
                    "toc04" => [ "accent" => $blue ],
                ],
                "variables" => [ "--smpi-toc-accent" => "color" ],
            ],
            "article_heading" => [
                "source_key" => "article_heading_color_source",
                "custom_key" => "article_heading_accent_color",
                "template_key" => "article_heading_style",
                "default_template" => "h2-tick",
                "fallback" => $red,
                "palettes" => array_replace(
                    array_fill_keys( [ "none", "h2-tick", "h2-leftrule", "h2-underline", "h2-topline", "h2-dot", "h2-trailingrule", "h2-serif", "h2-uppercase", "h2-gradient", "h2-bracket", "h2-number", "h2-square", "h2-highlight", "h2-double", "h2-corner_tick" ], [ "accent" => $red ] ),
                    [
                        "h2-topline" => [ "accent" => "#e5e7eb" ],
                        "h2-trailingrule" => [ "accent" => "#e5e7eb" ],
                    ]
                ),
                "variables" => [ "--smpi-heading-accent" => "color", "--smpi-heading-accent-fade" => "rgba:0", "--smpi-heading-highlight" => "rgba:0.16" ],
            ],
            "article_drop_cap" => [
                "source_key" => "article_drop_cap_color_source",
                "custom_key" => "article_drop_cap_color",
                "template_key" => "article_drop_cap_style",
                "default_template" => "dropcap-classic",
                "fallback" => "#111111",
                "palettes" => [
                    "dropcap-classic" => [ "accent" => "#111111" ],
                    "dropcap-highlight" => [ "accent" => "#facc15" ],
                    "dropcap-outline" => [ "accent" => "#111111" ],
                    "dropcap-side-rule" => [ "accent" => "#111111" ],
                    "dropcap-soft-tile" => [ "accent" => "#111111" ],
                    "dropcap-script-classic" => [ "accent" => "#111111" ],
                    "dropcap-script-tile" => [ "accent" => "#111111" ],
                    "dropcap-script-round" => [ "accent" => "#111111" ],
                    "dropcap-script-underline" => [ "accent" => "#111111" ],
                    "dropcap-script-shadow" => [ "accent" => "#111111" ],
                ],
                "variables" => [ "--smpi-dropcap-color" => "color", "--smpi-dropcap-soft" => "rgba:0.14", "--smpi-dropcap-ink" => "contrast" ],
            ],
            "inline_photo_caption" => [
                "source_key" => "inline_photo_color_source",
                "custom_key" => "inline_photo_accent_color",
                "template_key" => "inline_photo_treatment",
                "default_template" => "none",
                "fallback" => $red,
                "palettes" => [
                    "none" => [ "accent" => $red ],
                    "fig1" => [ "accent" => $red ],
                    "fig2" => [ "accent" => $red ],
                    "fig4" => [ "accent" => "#0a0a0c" ],
                    "fig5" => [ "accent" => "#e9e9e9" ],
                ],
                "variables" => [
                    "--smpi-photo-accent" => "color",
                    "--smpi-photo-overlay" => "rgba:0.85",
                    "--smpi-photo-overlay-clear" => "rgba:0",
                ],
            ],
            "featured_image_caption" => [
                "source_key" => "featured_image_caption_color_source",
                "custom_key" => "featured_image_caption_accent_color",
                "template_key" => "featured_image_caption_template",
                "default_template" => "fig2",
                "fallback" => $red,
                "palettes" => [
                    "none" => [ "accent" => $red ],
                    "fig1" => [ "accent" => $red ],
                    "fig2" => [ "accent" => $red ],
                    "fig4" => [ "accent" => "#0a0a0c" ],
                    "fig5" => [ "accent" => "#e9e9e9" ],
                ],
                "variables" => [
                    "--smpi-fi-accent" => "color",
                    "--smpi-fi-overlay" => "rgba:0.85",
                    "--smpi-fi-overlay-clear" => "rgba:0",
                ],
            ],
            "post_summary" => [
                "source_key" => "post_summary_color_source",
                "custom_key" => "post_summary_accent_color",
                "template_key" => "post_summary_style",
                "default_template" => "none",
                "fallback" => $blue,
                "palettes" => [
                    "none" => [ "accent" => $blue ],
                    "sum00" => [ "accent" => "#1f2937" ],
                    "sum01" => [ "accent" => $blue ],
                    "sum02" => [ "accent" => "#0a0a0a" ],
                    "sum03" => [ "accent" => "#0a0a0a" ],
                    "sum04" => [ "accent" => $blue ],
                ],
                "variables" => [ "--smpi-summary-accent" => "color", "--smpi-summary-accent-soft" => "rgba:0.10", "--smpi-summary-accent-ink" => "contrast" ],
            ],
            "post_faqs" => [
                "source_key" => "post_faqs_color_source",
                "custom_key" => "post_faqs_accent_color",
                "template_key" => "post_faqs_style",
                "default_template" => "none",
                "fallback" => $blue,
                "palettes" => [
                    "none" => [ "accent" => $blue ],
                    "faq00" => [ "accent" => "#e5e7eb" ],
                    "faq01" => [ "accent" => "#e5e7eb" ],
                    "faq02" => [ "accent" => "#e5e7eb" ],
                    "faq03" => [ "accent" => $blue ],
                    "faq04" => [ "accent" => $blue ],
                ],
                "variables" => [
                    "--smpi-faq-accent" => "color",
                    "--smpi-faq-accent-soft" => "rgba:0.22",
                    "--smpi-faq-accent-tint" => "rgba:0.07",
                ],
            ],
            "muckrack_verified" => [
                "source_key" => "muckrack_verified_color_source",
                "custom_key" => "muckrack_icon_color",
                "template_key" => "muckrack_verified_style",
                "default_template" => "tooltip",
                "fallback" => $muckrack,
                "palettes" => array_fill_keys( [ "tooltip", "text", "compact_block" ], [ "accent" => $muckrack ] ),
                "variables" => [ "--smpi-muckrack-author-accent" => "color" ],
            ],
            "publication_muckrack" => [
                "source_key" => "publication_muckrack_color_source",
                "custom_key" => "publication_muckrack_color",
                "template_key" => "publication_muckrack_style",
                "default_template" => "block",
                "fallback" => $muckrack,
                "palettes" => array_fill_keys( [ "block", "mini_block", "compact", "minimalist" ], [ "accent" => $muckrack ] ),
                "variables" => [ "--smpi-muckrack-publication-accent" => "color" ],
            ],
        ];
    }

    public static function typography_definitions(): array {
        return [
            "breadcrumbs" => [
                "title" => "Breadcrumb typography",
                "font_family" => [ "key" => "breadcrumbs_font_family", "label" => "Breadcrumb font" ],
                "font_weight" => [ "key" => "breadcrumbs_font_weight", "label" => "Breadcrumb weight" ],
                "font_color" => [ "key" => "breadcrumbs_text_color", "label" => "Breadcrumb text color" ],
                "font_size" => [ "key" => "breadcrumbs_font_size", "label" => "Breadcrumb font size", "min" => 8, "max" => 64, "suffix" => "px" ],
            ],
            "table_of_contents" => [
                "title" => "Table of contents typography",
                "font_family" => [ "key" => "table_of_contents_font_family", "label" => "Table of contents font" ],
                "font_weight" => [ "key" => "table_of_contents_font_weight", "label" => "Table of contents weight" ],
                "font_color" => [ "key" => "table_of_contents_text_color", "label" => "Table of contents text color" ],
                "font_size" => [ "key" => "table_of_contents_text_font_size", "label" => "Table of contents text size", "min" => 8, "max" => 64, "suffix" => "px" ],
                "font_style" => [ "key" => "table_of_contents_text_font_style", "label" => "Table of contents text style" ],
            ],
            "article_heading" => [
                "title" => "Heading typography",
                "font_family" => [ "key" => "article_heading_font_family", "label" => "Heading font" ],
                "font_weight" => [ "key" => "article_heading_font_weight", "label" => "Heading weight" ],
                "font_color" => [ "key" => "article_heading_text_color", "label" => "Heading text color" ],
                "font_size" => [
                    [ "key" => "article_heading_h2_font_size", "label" => "H2 font size", "min" => 8, "max" => 64, "suffix" => "px" ],
                    [ "key" => "article_heading_h3_font_size", "label" => "H3 font size", "min" => 8, "max" => 64, "suffix" => "px" ],
                ],
            ],
            "article_drop_cap" => [
                "title" => "Drop cap typography",
                "font_family" => [ "key" => "article_drop_cap_font_family", "label" => "Drop cap font", "description" => "Non-script templates use this font. Script templates retain their template typeface." ],
                "font_weight" => [ "key" => "article_drop_cap_font_weight", "label" => "Drop cap weight" ],
                "font_size" => [ "key" => "article_drop_cap_font_size", "label" => "Drop cap size", "min" => 48, "max" => 180, "suffix" => "px" ],
            ],
            "inline_photo_caption" => [
                "title" => "Inline caption typography",
                "font_family" => [ "key" => "inline_photo_caption_font_family", "label" => "Caption font" ],
                "font_weight" => [ "key" => "inline_photo_caption_font_weight", "label" => "Caption weight" ],
                "font_color" => [ "key" => "inline_photo_caption_text_color", "label" => "Caption text color" ],
                "font_size" => [ "key" => "inline_photo_caption_font_size", "label" => "Caption text size", "min" => 8, "max" => 64, "suffix" => "px" ],
                "font_style" => [ "key" => "inline_photo_caption_font_style", "label" => "Caption text style" ],
            ],
            "featured_image_caption" => [
                "title" => "Featured caption typography",
                "font_family" => [ "key" => "featured_image_caption_font_family", "label" => "Caption font" ],
                "font_weight" => [ "key" => "featured_image_caption_font_weight", "label" => "Caption weight" ],
                "font_color" => [ "key" => "featured_image_caption_text_color", "label" => "Caption text color" ],
                "font_size" => [ "key" => "featured_image_caption_font_size", "label" => "Caption text size", "min" => 8, "max" => 64, "suffix" => "px" ],
                "font_style" => [ "key" => "featured_image_caption_font_style", "label" => "Caption text style" ],
            ],
            "post_summary" => [
                "title" => "Summary typography",
                "font_family" => [ "key" => "post_summary_font_family", "label" => "Summary font" ],
                "font_weight" => [ "key" => "post_summary_font_weight", "label" => "Summary weight" ],
                "font_color" => [ "key" => "post_summary_text_color", "label" => "Summary text color" ],
                "font_size" => [ "key" => "post_summary_font_size", "label" => "Summary text size", "min" => 8, "max" => 64, "suffix" => "px" ],
            ],
            "post_faqs" => [
                "title" => "FAQ typography",
                "font_family" => [ "key" => "post_faqs_font_family", "label" => "FAQ font" ],
                "font_weight" => [ "key" => "post_faqs_font_weight", "label" => "FAQ weight" ],
                "font_color" => [ "key" => "post_faqs_text_color", "label" => "FAQ text color" ],
                "font_size" => [ "key" => "post_faqs_text_font_size", "label" => "FAQ text size", "min" => 8, "max" => 64, "suffix" => "px" ],
                "font_style" => [ "key" => "post_faqs_text_font_style", "label" => "FAQ text style" ],
            ],
            "muckrack_verified" => [
                "title" => "Author verification typography",
                "font_family" => [ "key" => "muckrack_verified_font_family", "label" => "Verification font" ],
                "font_weight" => [ "key" => "muckrack_verified_font_weight", "label" => "Verification weight" ],
                "font_color" => [ "key" => "muckrack_verified_text_color", "label" => "Verification text color" ],
                "font_size" => [ "key" => "muckrack_verified_font_size", "label" => "Verification text size", "min" => 8, "max" => 64, "suffix" => "px" ],
            ],
            "publication_muckrack" => [
                "title" => "Publication verification typography",
                "font_family" => [ "key" => "publication_muckrack_font_family", "label" => "Verification font" ],
                "font_weight" => [ "key" => "publication_muckrack_font_weight", "label" => "Verification weight" ],
                "font_color" => [ "key" => "publication_muckrack_text_color", "label" => "Verification text color" ],
                "font_size" => [ "key" => "publication_muckrack_font_size", "label" => "Verification text size", "min" => 8, "max" => 64, "suffix" => "px" ],
            ],
        ];
    }

    public static function typography_variable_definitions(): array {
        return [
            "breadcrumbs" => [ "font_color" => [ "--smpi-bc-text", "breadcrumbs_text_color", "color", "#374151" ], "font_size" => [ "--smpi-bc-font-size", "breadcrumbs_font_size", "size", 13, 8, 64 ] ],
            "table_of_contents" => [ "font_color" => [ "--smpi-toc-text", "table_of_contents_text_color", "color", "#1f2937" ], "font_size" => [ "--smpi-toc-size", "table_of_contents_text_font_size", "size", 15, 8, 64 ], "font_style" => [ "--smpi-toc-fstyle", "table_of_contents_text_font_style", "style", "normal" ] ],
            "article_heading" => [ "font_color" => [ "--smpi-heading-ink", "article_heading_text_color", "color", "#111827" ], "font_size_h2" => [ "--smpi-heading-h2-size", "article_heading_h2_font_size", "size", 23, 8, 64 ], "font_size_h3" => [ "--smpi-heading-h3-size", "article_heading_h3_font_size", "size", 20, 8, 64 ] ],
            "article_drop_cap" => [ "font_size" => [ "--smpi-dropcap-size", "article_drop_cap_font_size", "size", 96, 48, 180 ] ],
            "inline_photo_caption" => [ "font_color" => [ "--smpi-photo-cap-color", "inline_photo_caption_text_color", "color", "#272727" ], "font_size" => [ "--smpi-photo-cap-size", "inline_photo_caption_font_size", "size", 16, 8, 64 ], "font_style" => [ "--smpi-photo-cap-fstyle", "inline_photo_caption_font_style", "style", "italic" ] ],
            "featured_image_caption" => [ "font_color" => [ "--smpi-fi-cap-color", "featured_image_caption_text_color", "color", "#272727" ], "font_size" => [ "--smpi-fi-cap-size", "featured_image_caption_font_size", "size", 16, 8, 64 ], "font_style" => [ "--smpi-fi-cap-fstyle", "featured_image_caption_font_style", "style", "italic" ] ],
            "post_summary" => [ "font_color" => [ "--smpi-summary-text", "post_summary_text_color", "color", "#1f2937" ], "font_size" => [ "--smpi-summary-size", "post_summary_font_size", "size", 16, 8, 64 ] ],
            "post_faqs" => [ "font_color" => [ "--smpi-faq-text", "post_faqs_text_color", "color", "#1f2937" ], "font_size" => [ "--smpi-faq-size", "post_faqs_text_font_size", "size", 16, 8, 64 ], "font_style" => [ "--smpi-faq-fstyle", "post_faqs_text_font_style", "style", "normal" ] ],
            "muckrack_verified" => [ "font_color" => [ "--smpi-muckrack-author-text", "muckrack_verified_text_color", "color", "#64748b" ], "font_size" => [ "--smpi-muckrack-author-size", "muckrack_verified_font_size", "size", 14, 8, 64 ] ],
            "publication_muckrack" => [ "font_color" => [ "--smpi-muckrack-publication-text", "publication_muckrack_text_color", "color", "#334155" ], "font_size" => [ "--smpi-muckrack-publication-size", "publication_muckrack_font_size", "size", 14, 8, 64 ] ],
        ];
    }

    public static function typography_preservation_surfaces(): array {
        return [
            "breadcrumbs" => [ "font_family" => false, "font_size" => false, "font_color" => true, "font_weight" => false ],
            "table_of_contents" => false,
            "article_heading" => true,
            "article_drop_cap" => false,
            "inline_photo_caption" => false,
            "featured_image_caption" => false,
            "post_summary" => [ "font_family" => false, "font_size" => true, "font_color" => true, "font_weight" => false ],
            "post_faqs" => false,
            "muckrack_verified" => [ "font_family" => false, "font_size" => true, "font_color" => true, "font_weight" => false ],
            "publication_muckrack" => [ "font_family" => false, "font_size" => false, "font_color" => true, "font_weight" => false ],
        ];
    }

    public static function typography_preservation_defaults( string $surface ) {
        $surfaces = self::typography_preservation_surfaces();
        return $surfaces[ sanitize_key( $surface ) ] ?? true;
    }

    public static function font_family_css_variables(): array {
        return [
            "breadcrumbs_font_family" => "--smpi-bc-font",
            "table_of_contents_font_family" => "--smpi-toc-font",
            "article_heading_font_family" => "--smpi-heading-font",
            "article_drop_cap_font_family" => "--smpi-dropcap-font",
            "inline_photo_caption_font_family" => "--smpi-photo-cap-font",
            "featured_image_caption_font_family" => "--smpi-fi-cap-font",
            "post_summary_font_family" => "--smpi-summary-font",
            "post_faqs_font_family" => "--smpi-faq-font",
            "muckrack_verified_font_family" => "--smpi-muckrack-author-font",
            "publication_muckrack_font_family" => "--smpi-muckrack-publication-font",
        ];
    }

    public static function font_weight_css_variables(): array {
        return [
            "breadcrumbs_font_weight" => "--smpi-bc-weight",
            "table_of_contents_font_weight" => "--smpi-toc-weight",
            "article_heading_font_weight" => "--smpi-heading-weight",
            "article_drop_cap_font_weight" => "--smpi-dropcap-weight",
            "inline_photo_caption_font_weight" => "--smpi-photo-cap-weight",
            "featured_image_caption_font_weight" => "--smpi-fi-cap-weight",
            "post_summary_font_weight" => "--smpi-summary-weight",
            "post_faqs_font_weight" => "--smpi-faq-weight",
            "muckrack_verified_font_weight" => "--smpi-muckrack-author-weight",
            "publication_muckrack_font_weight" => "--smpi-muckrack-publication-weight",
        ];
    }

    public static function typography_preview_variables( string $surface ): array {
        $surface = sanitize_key( $surface );
        $definition = self::typography_definition( $surface );
        if ( [] === $definition ) {
            return [];
        }

        $variables = [];
        foreach ( [ "font_family" => self::font_family_css_variables(), "font_weight" => self::font_weight_css_variables() ] as $property => $map ) {
            $key = (string) ( $definition[ $property ]["key"] ?? "" );
            if ( "" !== $key && isset( $map[ $key ] ) ) {
                $variables[ $key ] = [ "variable" => $map[ $key ], "property" => $property, "type" => "option_css" ];
            }
        }
        foreach ( self::typography_variable_definitions()[ $surface ] ?? [] as $property => $variable_definition ) {
            [ $variable, $setting_key, $type ] = $variable_definition;
            $preservation_property = 0 === strpos( $property, "font_size" ) ? "font_size" : $property;
            $variables[ $setting_key ] = [
                "variable" => $variable,
                "property" => $preservation_property,
                "preserve_property" => "font_style" === $property ? "font_family" : $preservation_property,
                "type" => $type,
                "suffix" => "size" === $type ? "px" : "",
            ];
        }

        $selector_map = self::preview_typography_selectors()[ $surface ] ?? [];
        $css_properties = [ "font_family" => "font-family", "font_weight" => "font-weight", "font_color" => "color", "font_size" => "font-size", "font_style" => "font-style" ];
        foreach ( $variables as $setting_key => &$config ) {
            $property = (string) ( $config["property"] ?? "" );
            $selector = (string) ( $selector_map[ $property ] ?? ( "font_style" === $property ? ( $selector_map["font_family"] ?? "" ) : "" ) );
            if ( "article_heading_h2_font_size" === $setting_key ) {
                $selector = ".smpi-ah-preview .smpi-article-heading--h2";
            } elseif ( "article_heading_h3_font_size" === $setting_key ) {
                $selector = ".smpi-ah-preview .smpi-article-heading--h3";
            }
            $config["selector"] = $selector;
            $config["css_property"] = (string) ( $css_properties[ $property ] ?? "" );
        }
        unset( $config );

        return $variables;
    }

    public static function preview_typography_selectors(): array {
        return [
            "breadcrumbs" => [
                "font_family" => ".smpi-breadcrumbs,.smpi-breadcrumb-title,.smpi-breadcrumb-list,.smpi-breadcrumb-link,.smpi-breadcrumb-current",
                "font_weight" => ".smpi-breadcrumbs,.smpi-breadcrumb-title,.smpi-breadcrumb-list,.smpi-breadcrumb-link,.smpi-breadcrumb-current",
                "font_color" => ".smpi-breadcrumb-title,.smpi-breadcrumb-list,.smpi-breadcrumb-current",
                "font_size" => ".smpi-breadcrumb-title,.smpi-breadcrumb-list,.smpi-breadcrumb-link,.smpi-breadcrumb-current",
            ],
            "table_of_contents" => [
                "font_family" => ".smpi-table-of-contents,.smpi-toc-label,.smpi-toc-link",
                "font_weight" => ".smpi-table-of-contents,.smpi-toc-label,.smpi-toc-link",
                "font_color" => ".smpi-toc-label,.smpi-toc-link:not(:hover)",
                "font_size" => ".smpi-toc-label,.smpi-toc-link",
                "font_style" => ".smpi-toc-label,.smpi-toc-link",
            ],
            "article_heading" => [
                "font_family" => ".smpi-ah-preview .smpi-article-heading",
                "font_weight" => ".smpi-ah-preview .smpi-article-heading",
                "font_color" => ".smpi-ah-preview .smpi-article-heading",
                "font_size" => ".smpi-ah-preview .smpi-article-heading",
            ],
            "article_drop_cap" => [
                "font_family" => ".smpi-dropcap-preview .smpi-article-lead::first-letter",
                "font_weight" => ".smpi-dropcap-preview .smpi-article-lead::first-letter",
                "font_color" => ".smpi-dropcap-preview .smpi-article-lead::first-letter",
                "font_size" => ".smpi-dropcap-preview .smpi-article-lead::first-letter",
            ],
            "inline_photo_caption" => [
                "font_family" => ".smpi-pp .smpi-inline-photo-caption",
                "font_weight" => ".smpi-pp .smpi-inline-photo-caption",
                "font_color" => ".smpi-pp .smpi-inline-photo-caption",
                "font_size" => ".smpi-pp .smpi-inline-photo-caption",
                "font_style" => ".smpi-pp .smpi-inline-photo-caption",
            ],
            "featured_image_caption" => [
                "font_family" => ".smpi-fi-preview .smpi-featured-image-caption-text",
                "font_weight" => ".smpi-fi-preview .smpi-featured-image-caption-text",
                "font_color" => ".smpi-fi-preview .smpi-featured-image-caption-text",
                "font_size" => ".smpi-fi-preview .smpi-featured-image-caption-text",
                "font_style" => ".smpi-fi-preview .smpi-featured-image-caption-text",
            ],
            "post_summary" => [
                "font_family" => ".smpi-post-summary,.smpi-post-summary .smpi-template-title,.smpi-post-summary .smpi-template-content,.smpi-post-summary .smpi-template-content *",
                "font_weight" => ".smpi-post-summary,.smpi-post-summary .smpi-template-title,.smpi-post-summary .smpi-template-content,.smpi-post-summary .smpi-template-content *",
                "font_color" => ".smpi-post-summary .smpi-template-content,.smpi-post-summary .smpi-template-content *",
                "font_size" => ".smpi-post-summary,.smpi-post-summary .smpi-template-title,.smpi-post-summary .smpi-template-content,.smpi-post-summary .smpi-template-content *",
            ],
            "post_faqs" => [
                "font_family" => ".smpi-post-faqs,.smpi-post-faqs .smpi-template-title,.smpi-post-faqs .smpi-template-content,.smpi-post-faqs .smpi-template-content *",
                "font_weight" => ".smpi-post-faqs,.smpi-post-faqs .smpi-template-title,.smpi-post-faqs .smpi-template-content,.smpi-post-faqs .smpi-template-content *",
                "font_color" => ".smpi-post-faqs,.smpi-post-faqs .smpi-template-title,.smpi-post-faqs .smpi-template-content,.smpi-post-faqs .smpi-template-content *",
                "font_size" => ".smpi-post-faqs,.smpi-post-faqs .smpi-template-title,.smpi-post-faqs .smpi-template-content,.smpi-post-faqs .smpi-template-content *",
                "font_style" => ".smpi-post-faqs .smpi-template-content,.smpi-post-faqs .smpi-template-content *",
            ],
            "muckrack_verified" => [
                "font_family" => ".smpi-muckrack-author-text,.smpi-muckrack-author-note,.smpi-muckrack-footer-note,.smpi-author-inline-demo,.smpi-author-block-demo,.smpi-tooltip-demo",
                "font_weight" => ".smpi-muckrack-author-text,.smpi-muckrack-author-note,.smpi-muckrack-footer-note,.smpi-author-inline-demo,.smpi-author-block-demo,.smpi-tooltip-demo",
                "font_color" => ".smpi-muckrack-author-text,.smpi-muckrack-author-note,.smpi-muckrack-footer-note,.smpi-author-inline-demo,.smpi-author-block-demo,.smpi-tooltip-demo",
                "font_size" => ".smpi-muckrack-author-text,.smpi-muckrack-author-note,.smpi-muckrack-footer-note,.smpi-author-inline-demo,.smpi-author-block-demo,.smpi-tooltip-demo",
            ],
            "publication_muckrack" => [
                "font_family" => ".smpi-muckrack-publication-text,.smpi-publication-preview-block,.smpi-publication-preview-mini_block,.smpi-publication-preview-compact,.smpi-publication-preview-minimalist",
                "font_weight" => ".smpi-muckrack-publication-text,.smpi-publication-preview-block,.smpi-publication-preview-mini_block,.smpi-publication-preview-compact,.smpi-publication-preview-minimalist",
                "font_color" => ".smpi-muckrack-publication-text,.smpi-publication-preview-block,.smpi-publication-preview-mini_block,.smpi-publication-preview-compact,.smpi-publication-preview-minimalist",
                "font_size" => ".smpi-muckrack-publication-text,.smpi-publication-preview-block,.smpi-publication-preview-mini_block,.smpi-publication-preview-compact,.smpi-publication-preview-minimalist",
            ],
        ];
    }

    public static function preview_typography_state_css(): string {
        $selectors = self::preview_typography_selectors();
        $properties = [ "font_family" => "font-family", "font_weight" => "font-weight", "font_color" => "color", "font_size" => "font-size", "font_style" => "font-style" ];
        $css = "";
        foreach ( $selectors as $surface => $surface_selectors ) {
            foreach ( $properties as $property => $css_property ) {
                if ( empty( $surface_selectors[ $property ] ) ) {
                    continue;
                }
                $targets = array_filter( array_map( "trim", explode( ",", $surface_selectors[ $property ] ) ) );
                $site_class = "." . TemplateTypography::mode_state_class( $surface, TemplateTypography::SITE_INHERIT );
                $preserve_property = "font_style" === $property ? "font_family" : $property;
                $preserve_class = "." . TypographyPreservation::state_class( $surface, $preserve_property );
                $site_targets = array_map( static fn( string $target ): string => $site_class . " " . $target, $targets );
                $preserve_targets = array_map( static fn( string $target ): string => $preserve_class . " " . $target, $targets );
                $css .= implode( ",", array_merge( $site_targets, $preserve_targets ) ) . "{" . $css_property . ":inherit!important}";
            }

            foreach ( self::typography_preview_variables( $surface ) as $config ) {
                $property = (string) ( $config["property"] ?? "" );
                $variable = (string) ( $config["variable"] ?? "" );
                $selector = (string) ( $config["selector"] ?? "" );
                $css_property = (string) ( $config["css_property"] ?? "" );
                if ( "" === $property || ! preg_match( "/^--[a-z0-9_-]+$/", $variable ) || "" === $selector || "" === $css_property ) {
                    continue;
                }
                $custom_class = "." . TemplateTypography::custom_property_state_class( $surface, $property );
                $targets = array_filter( array_map( "trim", explode( ",", $selector ) ) );
                $custom_targets = array_map( static fn( string $target ): string => $custom_class . " " . $target, $targets );
                $css .= implode( ",", $custom_targets ) . "{" . $css_property . ":var(" . $variable . ")!important}";
            }
        }
        return $css;
    }

    public static function definition( string $surface ): array {
        $surface = sanitize_key( $surface );
        return self::definitions()[ $surface ] ?? [];
    }

    public static function typography_definition( string $surface ): array {
        $surface = sanitize_key( $surface );
        return self::typography_definitions()[ $surface ] ?? [];
    }

    public static function source_setting_keys(): array {
        return array_values( array_column( self::definitions(), "source_key" ) );
    }

    public static function custom_color_setting_keys(): array {
        return array_values( array_column( self::definitions(), "custom_key" ) );
    }

    public static function surface_for_source_key( string $key ): string {
        foreach ( self::definitions() as $surface => $definition ) {
            if ( $definition["source_key"] === $key ) {
                return $surface;
            }
        }
        return "";
    }

    public static function source_defaults(): array {
        $defaults = [];
        foreach ( self::definitions() as $definition ) {
            $defaults[ $definition["source_key"] ] = TemplateColorResolver::TEMPLATE_DEFAULT;
        }
        return $defaults;
    }

    public static function source( string $surface, array $settings ): string {
        $definition = self::definition( $surface );
        return [] === $definition
            ? TemplateColorResolver::TEMPLATE_DEFAULT
            : TemplateColorResolver::normalize_source( (string) ( $settings[ $definition["source_key"] ] ?? TemplateColorResolver::TEMPLATE_DEFAULT ) );
    }

    public static function template( string $surface, array $settings ): string {
        $definition = self::definition( $surface );
        if ( [] === $definition ) {
            return "";
        }
        $template = sanitize_key( (string) ( $settings[ $definition["template_key"] ] ?? $definition["default_template"] ) );
        return isset( $definition["palettes"][ $template ] ) ? $template : $definition["default_template"];
    }

    public static function css_variables( string $surface, array $settings ): array {
        $definition = self::definition( $surface );
        if ( [] === $definition ) {
            return [];
        }
        return TemplateColorResolver::css_variables(
            self::source( $surface, $settings ),
            self::template( $surface, $settings ),
            $definition["palettes"],
            (string) ( $settings[ $definition["custom_key"] ] ?? "" ),
            $definition["variables"],
            $definition["fallback"],
            BrandColorProvider::primary_color( $definition["fallback"] ),
            BrandColorProvider::secondary_color( "#111827" )
        );
    }

    public static function effective_color( string $surface, array $settings ): string {
        $definition = self::definition( $surface );
        if ( [] === $definition ) {
            return "#2d5277";
        }
        return TemplateColorResolver::effective_base(
            self::source( $surface, $settings ),
            self::template( $surface, $settings ),
            $definition["palettes"],
            (string) ( $settings[ $definition["custom_key"] ] ?? "" ),
            $definition["fallback"],
            BrandColorProvider::primary_color( $definition["fallback"] ),
            BrandColorProvider::secondary_color( "#111827" )
        );
    }

    public static function css_declarations( string $surface, array $settings ): string {
        $declarations = [];
        foreach ( self::css_variables( $surface, $settings ) as $variable => $value ) {
            $declarations[] = $variable . ":" . $value;
        }
        return implode( ";", $declarations );
    }

    public static function typography_css_variables( string $surface, array $settings ): array {
        $surface = sanitize_key( $surface );
        if ( TemplateTypography::CUSTOM !== TemplateTypography::normalize_mode( (string) ( $settings[ TemplateTypography::setting_key( $surface ) ] ?? TemplateTypography::TEMPLATE_DEFAULT ) ) ) {
            return [];
        }

        $preservation = TemplateTypography::preservation_values(
            $settings,
            $surface,
            self::typography_preservation_defaults( $surface )
        );
        $variables = [];
        $typography = self::typography_definition( $surface );
        $family_key = (string) ( $typography["font_family"]["key"] ?? "" );
        if ( "" !== $family_key && empty( $preservation["font_family"] ) && isset( self::font_family_css_variables()[ $family_key ] ) && class_exists( FontFamilyProvider::class ) ) {
            $family = FontFamilyProvider::css_value( (string) ( $settings[ $family_key ] ?? FontFamilyProvider::TEMPLATE ) );
            if ( "" !== $family ) {
                $variables[ self::font_family_css_variables()[ $family_key ] ] = $family;
            }
        }
        $weight_key = (string) ( $typography["font_weight"]["key"] ?? "" );
        if ( "" !== $weight_key && empty( $preservation["font_weight"] ) && isset( self::font_weight_css_variables()[ $weight_key ] ) && class_exists( FontWeightProvider::class ) ) {
            $weight = FontWeightProvider::css_value( $settings[ $weight_key ] ?? FontWeightProvider::FONT_DEFAULT );
            if ( "" !== $weight ) {
                $variables[ self::font_weight_css_variables()[ $weight_key ] ] = $weight;
            }
        }
        foreach ( self::typography_variable_definitions()[ $surface ] ?? [] as $property => $definition ) {
            $preservation_property = 0 === strpos( $property, "font_size" ) ? "font_size" : $property;
            $preservation_property = "font_style" === $property ? "font_family" : $preservation_property;
            if ( ! empty( $preservation[ $preservation_property ] ) ) {
                continue;
            }
            [ $variable, $setting_key, $type, $fallback ] = $definition;
            if ( "color" === $type ) {
                $variables[ $variable ] = sanitize_hex_color( (string) ( $settings[ $setting_key ] ?? $fallback ) ) ?: $fallback;
            } elseif ( "size" === $type ) {
                $min = (int) ( $definition[4] ?? 8 );
                $max = (int) ( $definition[5] ?? 64 );
                $variables[ $variable ] = max( $min, min( $max, (int) ( $settings[ $setting_key ] ?? $fallback ) ) ) . "px";
            } else {
                $variables[ $variable ] = "italic" === (string) ( $settings[ $setting_key ] ?? $fallback ) ? "italic" : "normal";
            }
        }
        return $variables;
    }

    public static function typography_css_declarations( string $surface, array $settings ): string {
        $declarations = [];
        foreach ( self::typography_css_variables( $surface, $settings ) as $variable => $value ) {
            $declarations[] = $variable . ":" . $value;
        }
        return implode( ";", $declarations );
    }
}
