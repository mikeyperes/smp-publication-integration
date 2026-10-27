<?php
namespace smp_publication_integration\Settings;

use smp_publication_integration\Pages\PageTypes;
use Hexa\PluginCore\ActivityLog\ActivityLogConfig;
use Hexa\PluginCore\ActivityLog\ActivityLogEntry;
use Hexa\PluginCore\ActivityLog\ActivityLogger;
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\BrandColors\FontFamilyProvider;
use Hexa\PluginCore\BrandColors\FontWeightProvider;
use Hexa\PluginCore\BrandColors\TemplateColorResolver;
use Hexa\PluginCore\Typography\TemplateTypography;
use Hexa\PluginCore\Typography\TypographyPreservation;
use smp_publication_integration\Design\TemplateBackground;
use smp_publication_integration\Design\TemplateDesignRegistry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettingsRepository {
    public const OPTION = 'smpi_settings';

    private static array $request_cache = [];
    private static bool $cache_invalidation_hooks_registered = false;

    public static function defaults(): array {
        $colors = self::color_defaults();
        $defaults = [
            'shadow_posts_enabled' => true,
            'shadow_press_releases' => false,
            'post_list_defaults_enabled' => true,
            'hide_home_posts_without_featured_image' => true,
            'post_featured_image_required' => true,
            'post_time_mode'        => 'native',
            'author_social_cleanup' => true,
            'author_social_icons_enabled' => false,
            'author_social_style' => 'social-solid',
            'author_social_size' => 24,
            'author_social_color' => '',
            'author_social_networks' => [ 'website', 'linkedin', 'x', 'instagram', 'facebook', 'youtube', 'crunchbase', 'muckrack' ],
            'author_social_auto_contexts' => [],
            'author_social_archive_position' => 'below_bio',
            'public_debug_enabled'  => false,
            'estimated_read_time_enabled' => true,
            'elementor_css_cache_busting' => true,
            'elementor_primary_category_enabled' => true,
            'elementor_primary_category_exclude_default' => true,
            'publication_social_cleanup' => true,
            'muckrack_verified_enabled' => true,
            'muckrack_author_always_show' => false,
            'muckrack_verified_contexts' => [ 'single_author', 'single_footer', 'author', 'home', 'loop_cards' ],
            'muckrack_verified_style' => 'tooltip',
            'muckrack_verified_font_family' => 'template',
            'muckrack_verified_font_weight' => 'inherit',
            'muckrack_verified_text_color' => $colors['muckrack_verified_text_color'],
            'muckrack_verified_font_size' => 14,
            'muckrack_icon_color' => '',
            'muckrack_icon_style' => 'circle_check',
            "muckrack_icon_size" => 16,
            "muckrack_icon_margin_left" => 2,
            "muckrack_icon_margin_top" => 0,
            "muckrack_icon_color_single_author" => "",
            "muckrack_icon_size_single_author" => 0,
            "muckrack_icon_margin_left_single_author" => "",
            "muckrack_icon_margin_top_single_author" => "",
            "muckrack_icon_color_single_footer" => "",
            "muckrack_icon_size_single_footer" => 0,
            "muckrack_icon_margin_left_single_footer" => "",
            "muckrack_icon_margin_top_single_footer" => "",
            "muckrack_icon_color_loop_cards" => "",
            "muckrack_icon_size_loop_cards" => 0,
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
            'publication_muckrack_verified_enabled' => false,
            'publication_muckrack_text_mode' => 'news_outlet',
            'publication_muckrack_style' => 'block',
            'publication_muckrack_font_family' => 'template',
            'publication_muckrack_font_weight' => 'inherit',
            'publication_muckrack_color' => '',
            'publication_muckrack_text_color' => $colors['publication_muckrack_text_color'],
            "publication_muckrack_font_size" => 14,
            'publication_muckrack_placements' => [ 'bottom_article' ],
            'multi_authors_enabled' => true,
            'multi_authors_loop_output' => 'comma',
            'author_listing_hide_without_articles' => false,
            'author_listing_hide_without_featured_image' => false,
            'author_listing_show_press_releases' => true,
            'author_archive_loading_enabled' => false,
            'archive_search_enabled' => true,
            'author_archive_loading_mode' => 'pagination',
            'author_archive_loading_style' => 'none',
            'press_release_include_enabled' => true,
            'press_release_include_contexts' => [ 'home', 'category_tag', 'single_recent' ],
            'post_summary_acf_enabled' => false,
            'post_faqs_acf_enabled' => false,
            'article_types_enabled' => false,
            // Breadcrumbs moved to HWS Base Tools (off by default). These keys stay
            // only so Base Tools can carry over a site that had them switched on.
            "breadcrumbs_enabled" => false,
            "breadcrumbs_style" => "bc-b2",
            "breadcrumbs_accent_color" => '',
            "breadcrumbs_background_color" => '',
            "breadcrumbs_text_color" => $colors["breadcrumbs_text_color"],
            "breadcrumbs_font_size" => 13,
            "breadcrumbs_font_family" => "template",
            "breadcrumbs_font_weight" => "inherit",
            "breadcrumbs_hide_home" => true,
            "breadcrumbs_hide_term_archives" => false,
            "breadcrumbs_disabled_post_types" => [],
            "breadcrumbs_css_override" => "",
            'table_of_contents_enabled' => false,
            'table_of_contents_auto_single' => false,
            "table_of_contents_style" => "toc02",
            "table_of_contents_include_summary" => true,
            "table_of_contents_accent_color" => '',
            "table_of_contents_text_font_style" => "normal",
            "table_of_contents_text_font_size" => 15,
            "table_of_contents_text_color" => $colors["table_of_contents_text_color"],
            "table_of_contents_font_family" => "template",
            "table_of_contents_font_weight" => "inherit",
            "article_heading_styles_enabled" => false,
            "article_heading_style" => "h2-tick",
            "article_heading_accent_color" => '',
            "article_heading_text_color" => $colors["article_heading_text_color"],
            "article_heading_h2_font_size" => 23,
            "article_heading_h3_font_size" => 20,
            "article_heading_font_family" => "template",
            "article_heading_font_weight" => "inherit",
            "article_numbered_lists_enabled" => false,
            "article_numbered_list_style" => "nlist01",
            "article_numbered_list_accent_color" => '',
            "article_numbered_list_text_color" => $colors["article_numbered_list_text_color"],
            "article_numbered_list_font_size" => 16,
            "article_numbered_list_font_family" => "template",
            "article_numbered_list_font_weight" => "inherit",
            "article_drop_cap_enabled" => false,
            "article_drop_cap_style" => "dropcap-classic",
            "article_drop_cap_color" => '',
            "article_drop_cap_font_size" => 96,
            "article_drop_cap_font_family" => "template",
            "article_drop_cap_font_weight" => "inherit",
            "article_drop_cap_script_font" => "dancing-script",
            "inline_photo_treatments_enabled" => false,
            "inline_photo_treatment" => "none",
            "inline_photo_accent_color" => '',
            "inline_photo_caption_font_style" => "italic",
            "inline_photo_caption_font_size" => 16,
            "inline_photo_caption_text_color" => $colors["inline_photo_caption_text_color"],
            "inline_photo_caption_font_family" => "template",
            "inline_photo_caption_font_weight" => "inherit",
            "featured_image_caption_templates_enabled" => false,
            "featured_image_caption_template" => "fig2",
            "featured_image_caption_accent_color" => '',
            "featured_image_caption_font_style" => "italic",
            "featured_image_caption_font_size" => 16,
            "featured_image_caption_text_color" => $colors["featured_image_caption_text_color"],
            "featured_image_caption_font_family" => "template",
            "featured_image_caption_font_weight" => "inherit",
            "post_summary_style" => "none",
            "post_summary_font_family" => "template",
            "post_summary_font_weight" => "inherit",
            "post_summary_accent_color" => '',
            "post_summary_background_mode" => TemplateBackground::TEMPLATE,
            "post_summary_background_color" => "#ffffff",
            "post_summary_text_color" => $colors["post_summary_text_color"],
            "post_summary_font_size" => 16,
            "post_summary_placement" => "manual",
            "post_faqs_style" => "none",
            "post_faqs_placement" => "manual",
            "post_faqs_accent_color" => '',
            "post_faqs_text_font_style" => "normal",
            "post_faqs_text_font_size" => 16,
            "post_faqs_text_color" => $colors["post_faqs_text_color"],
            "post_faqs_font_family" => "template",
            "post_faqs_font_weight" => "inherit",
            'rank_math_breadcrumb_check_enabled' => true,
            'hws_masked_admin_report_enabled' => true,
            "content_generation_enabled" => true,
            "content_generation_api_base" => "https://publish.scalemypublication.com/api/smp-content-generation/v1",
            "post_hygiene_enabled" => true,
            "post_hygiene_strip_inline_styles" => true,
            "post_hygiene_unwrap_spans" => true,
            "post_hygiene_remove_font_tags" => true,
            "post_hygiene_strip_classes_ids" => false,
            "post_hygiene_strip_empty_tags" => true,
            "post_hygiene_clean_heading_children" => true,
            "post_hygiene_allowed_post_types" => [ "post" ],
            'system_publication_user_id' => 0,
            'page_assignments'      => [],
            'page_templates'        => self::default_page_templates(),
        ];
        $defaults = array_merge( $defaults, TemplateDesignRegistry::source_defaults() );
        foreach ( self::typography_preservation_surfaces() as $prefix => $preservation_defaults ) {
            $defaults[ TemplateTypography::setting_key( $prefix ) ] = TemplateTypography::TEMPLATE_DEFAULT;
            $defaults = array_merge( $defaults, TypographyPreservation::defaults( $prefix, $preservation_defaults ) );
        }

        return $defaults;
    }

    public static function all(): array {
        self::register_cache_invalidation_hooks();
        $cache_key = self::request_cache_key();

        if ( isset( self::$request_cache[ $cache_key ] ) ) {
            return self::$request_cache[ $cache_key ];
        }

        $raw = get_option( self::OPTION, [] );
        $settings = wp_parse_args( is_array( $raw ) ? $raw : [], self::defaults() );
        $defaults = self::default_page_templates();

        if ( ! isset( $settings['page_templates'] ) || ! is_array( $settings['page_templates'] ) ) {
            $settings['page_templates'] = [];
        }

        foreach ( self::page_types() as $type => $config ) {
            if ( empty( $config['template'] ) ) {
                continue;
            }
            $stored = isset( $settings['page_templates'][ $type ] ) ? trim( (string) $settings['page_templates'][ $type ] ) : '';
            if ( '' === $stored || self::should_refresh_default_page_template( $stored ) ) {
                $settings['page_templates'][ $type ] = (string) ( $defaults[ $type ] ?? '' );
            }
        }

        self::$request_cache[ $cache_key ] = $settings;

        return self::$request_cache[ $cache_key ];
    }

    public static function invalidate_cache(): void {
        unset( self::$request_cache[ self::request_cache_key() ] );
    }

    public static function invalidate_cache_for_option( string $option ): void {
        if ( self::OPTION === $option ) {
            self::invalidate_cache();
        }
    }

    private static function register_cache_invalidation_hooks(): void {
        if ( self::$cache_invalidation_hooks_registered || ! function_exists( 'add_action' ) ) {
            return;
        }

        foreach ( [ 'added_option', 'updated_option', 'deleted_option' ] as $hook ) {
            add_action( $hook, [ self::class, 'invalidate_cache_for_option' ], 10, 1 );
        }
        add_action( 'switch_blog', [ self::class, 'invalidate_cache' ], 10, 0 );

        self::$cache_invalidation_hooks_registered = true;
    }

    private static function request_cache_key(): int {
        return function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
    }

    private static function should_refresh_default_page_template( string $template ): bool {
        if ( false === strpos( $template, "[smp_publication_page_template type=" ) ) {
            return true;
        }

        foreach ( [ '<strong>Purpose:</strong>', '<h3>What this page should contain</h3>', 'At [smp_publication_field field=legal_name format=text]' ] as $marker ) {
            if ( false !== strpos( $template, $marker ) ) {
                return true;
            }
        }
        return false;
    }

    public static function get( string $key, $default = null ) {
        $settings = self::all();
        return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
    }

    public static function bool( string $key ): bool {
        return (bool) self::get( $key, false );
    }

    public static function array( string $key ): array {
        $value = self::get( $key, [] );
        return is_array( $value ) ? array_values( array_filter( array_map( 'sanitize_key', $value ) ) ) : [];
    }

    public static function font_family_setting_keys(): array {
        return array_keys( self::font_family_css_variables() );
    }

    public static function font_family_css_variables(): array {
        return TemplateDesignRegistry::font_family_css_variables();
    }

    public static function font_family_css( string $key ): string {
        if ( ! in_array( $key, self::font_family_setting_keys(), true ) || ! class_exists( FontFamilyProvider::class ) ) {
            return "";
        }

        return FontFamilyProvider::css_value( (string) self::get( $key, FontFamilyProvider::TEMPLATE ) );
    }

    public static function font_family_label( string $key ): string {
        if ( ! in_array( $key, self::font_family_setting_keys(), true ) || ! class_exists( FontFamilyProvider::class ) ) {
            return "Template font";
        }

        $font = FontFamilyProvider::resolve( (string) self::get( $key, FontFamilyProvider::TEMPLATE ) );
        $label = (string) ( $font["label"] ?? "Template font" );
        $family = (string) ( $font["family"] ?? "" );
        return "" !== $family && false === stripos( $label, $family ) ? $label . " - " . $family : $label;
    }

    public static function font_weight_setting_keys(): array {
        return array_keys( self::font_weight_css_variables() );
    }

    public static function typography_preservation_setting_keys(): array {
        $keys = [];
        foreach ( array_keys( self::typography_preservation_surfaces() ) as $prefix ) {
            $keys = array_merge( $keys, TypographyPreservation::setting_keys( $prefix ) );
        }
        return $keys;
    }

    public static function typography_mode_setting_keys(): array {
        return array_map( [ TemplateTypography::class, "setting_key" ], array_keys( self::typography_preservation_surfaces() ) );
    }

    /**
     * Typography preservation is registered once so defaults, AJAX allowlists,
     * previews, and frontend output cannot drift between feature cards.
     */
    public static function typography_preservation_surfaces(): array {
        return TemplateDesignRegistry::typography_preservation_surfaces();
    }

    public static function typography_preservation_defaults( string $prefix ) {
        return TemplateDesignRegistry::typography_preservation_defaults( $prefix );
    }

    public static function typography_mode( string $prefix ): string {
        $key = TemplateTypography::setting_key( $prefix );
        return TemplateTypography::normalize_mode( (string) self::get( $key, TemplateTypography::TEMPLATE_DEFAULT ) );
    }

    public static function typography_values( string $prefix ): array {
        return TemplateTypography::preservation_values(
            self::all(),
            $prefix,
            self::typography_preservation_defaults( $prefix )
        );
    }

    public static function font_weight_css_variables(): array {
        return TemplateDesignRegistry::font_weight_css_variables();
    }

    public static function font_weight_css( string $key ): string {
        if ( ! in_array( $key, self::font_weight_setting_keys(), true ) || ! class_exists( FontWeightProvider::class ) ) {
            return "";
        }

        return FontWeightProvider::css_value( self::get( $key, FontWeightProvider::FONT_DEFAULT ) );
    }

    public static function font_weight_label( string $key ): string {
        if ( ! in_array( $key, self::font_weight_setting_keys(), true ) || ! class_exists( FontWeightProvider::class ) ) {
            return "Font default";
        }

        return FontWeightProvider::label( self::get( $key, FontWeightProvider::FONT_DEFAULT ) );
    }

    public static function brand_primary_color( string $fallback = "#2d5277" ): string {
        if ( class_exists( BrandColorProvider::class ) ) {
            return BrandColorProvider::primary_color( $fallback );
        }

        if ( function_exists( "get_option" ) ) {
            $stored = get_option( "hws_brand_primary_color", "" );
            if ( is_scalar( $stored ) && "" !== trim( (string) $stored ) ) {
                $color = sanitize_hex_color( (string) $stored );
                if ( is_string( $color ) && "" !== $color ) {
                    return strtolower( $color );
                }
            }
        }

        $color = sanitize_hex_color( $fallback );
        return is_string( $color ) && "" !== $color ? strtolower( $color ) : "#2d5277";
    }

    public static function color_defaults(): array {
        $brand = self::brand_primary_color( "#2d5277" );

        return [
            "muckrack_icon_color" => $brand,
            "author_social_color" => $brand,
            "muckrack_verified_text_color" => "#64748b",
            "publication_muckrack_color" => $brand,
            "publication_muckrack_text_color" => "#334155",
            "breadcrumbs_accent_color" => $brand,
            "breadcrumbs_background_color" => "#ffffff",
            "breadcrumbs_text_color" => "#374151",
            "table_of_contents_accent_color" => $brand,
            "table_of_contents_text_color" => "#1f2937",
            "article_heading_accent_color" => $brand,
            "article_heading_text_color" => "#111827",
            "article_numbered_list_accent_color" => $brand,
            "article_numbered_list_text_color" => "#1f2937",
            "article_drop_cap_color" => $brand,
            "inline_photo_accent_color" => $brand,
            "inline_photo_caption_text_color" => "#272727",
            "featured_image_caption_accent_color" => $brand,
            "featured_image_caption_text_color" => "#272727",
            "post_summary_accent_color" => $brand,
            "post_summary_background_color" => "#ffffff",
            "post_summary_text_color" => "#1f2937",
            "post_faqs_accent_color" => $brand,
            "post_faqs_text_color" => "#1f2937",
        ];
    }

    public static function color_default( string $key ): string {
        foreach ( TemplateDesignRegistry::definitions() as $definition ) {
            if ( $definition["custom_key"] === $key ) {
                return (string) $definition["fallback"];
            }
        }
        $defaults = self::color_defaults();
        return $defaults[ $key ] ?? self::brand_primary_color( "#2d5277" );
    }

    public static function color_setting_keys(): array {
        return [
            "muckrack_icon_color",
            "author_social_color",
            "muckrack_icon_color_single_author",
            "muckrack_icon_color_single_footer",
            "muckrack_icon_color_loop_cards",
            "muckrack_icon_color_home",
            "muckrack_icon_color_author",
            "breadcrumbs_accent_color",
            "breadcrumbs_background_color",
            "breadcrumbs_text_color",
            "table_of_contents_accent_color",
            "table_of_contents_text_color",
            "article_heading_accent_color",
            "article_heading_text_color",
            "article_numbered_list_accent_color",
            "article_numbered_list_text_color",
            "article_drop_cap_color",
            "inline_photo_accent_color",
            "inline_photo_caption_text_color",
            "featured_image_caption_accent_color",
            "featured_image_caption_text_color",
            "post_summary_accent_color",
            "post_summary_background_color",
            "post_summary_text_color",
            "post_faqs_accent_color",
            "post_faqs_text_color",
            "publication_muckrack_color",
            "publication_muckrack_text_color",
            "muckrack_verified_text_color",
        ];
    }

    public static function brand_primary_color_keys(): array {
        return TemplateDesignRegistry::custom_color_setting_keys();
    }

    public static function update( array $changes ): array {
        $settings = self::all();
        foreach ( $changes as $key => $value ) {
            if ( 'post_time_mode' === $key ) {
                $allowed = [ 'native', 'relative_then_date', 'friendly_date' ];
                $settings[ $key ] = in_array( $value, $allowed, true ) ? $value : 'native';
                continue;
            }

            if ( "system_publication_user_id" === $key ) {
                $settings[ $key ] = absint( $value );
                continue;
            }

            if ( in_array( $key, TemplateDesignRegistry::source_setting_keys(), true ) ) {
                $settings[ $key ] = TemplateColorResolver::normalize_source( (string) $value );
                continue;
            }

            if ( in_array( $key, self::typography_mode_setting_keys(), true ) ) {
                $settings[ $key ] = TemplateTypography::normalize_mode( (string) $value );
                continue;
            }

            if ( in_array( $key, self::font_family_setting_keys(), true ) ) {
                $settings[ $key ] = class_exists( FontFamilyProvider::class )
                    ? FontFamilyProvider::normalize_selection( (string) $value )
                    : "template";
                continue;
            }

            if ( in_array( $key, self::font_weight_setting_keys(), true ) ) {
                $settings[ $key ] = class_exists( FontWeightProvider::class )
                    ? FontWeightProvider::normalize_selection( $value )
                    : "inherit";
                continue;
            }

            if ( "article_drop_cap_font_size" === $key ) {
                $value = absint( $value );
                $settings[ $key ] = max( 48, min( 180, $value ?: 96 ) );
                continue;
            }

            if ( in_array( $key, [ "author_social_size", "muckrack_icon_size", "muckrack_verified_font_size", "publication_muckrack_font_size", "breadcrumbs_font_size", "table_of_contents_text_font_size", "article_heading_h2_font_size", "article_heading_h3_font_size", "article_numbered_list_font_size", "inline_photo_caption_font_size", "featured_image_caption_font_size", "post_summary_font_size", "post_faqs_text_font_size", "muckrack_icon_size_single_author", "muckrack_icon_size_single_footer", "muckrack_icon_size_loop_cards", "muckrack_icon_size_home", "muckrack_icon_size_author" ], true ) ) {
                $value = absint( $value );
                if ( "author_social_size" === $key ) {
                    $settings[ $key ] = max( 12, min( 64, $value ?: 24 ) );
                    continue;
                }
                if ( 0 === strpos( $key, "muckrack_icon_size_" ) ) {
                    $settings[ $key ] = 0 === $value ? 0 : max( 8, min( 64, $value ) );
                    continue;
                }
                $font_size_defaults = [
                    "publication_muckrack_font_size" => 14,
                    "muckrack_verified_font_size" => 14,
                    "breadcrumbs_font_size" => 13,
                    "table_of_contents_text_font_size" => 15,
                    "article_heading_h2_font_size" => 23,
                    "article_heading_h3_font_size" => 20,
                    "article_numbered_list_font_size" => 16,
                    "inline_photo_caption_font_size" => 16,
                    "featured_image_caption_font_size" => 16,
                    "post_summary_font_size" => 16,
                    "post_faqs_text_font_size" => 16,
                ];
                $default = "muckrack_icon_size" === $key ? 16 : ( $font_size_defaults[ $key ] ?? 18 );
                $settings[ $key ] = max( 8, min( 64, $value ?: $default ) );
                continue;
            }

            if ( in_array( $key, [ "muckrack_icon_margin_left", "muckrack_icon_margin_top" ], true ) ) {
                $settings[ $key ] = max( -32, min( 64, (int) $value ) );
                continue;
            }

            if ( 0 === strpos( $key, "muckrack_icon_margin_left_" ) || 0 === strpos( $key, "muckrack_icon_margin_top_" ) ) {
                $raw = trim( (string) $value );
                if ( "" === $raw ) {
                    $settings[ $key ] = "";
                    continue;
                }
                $settings[ $key ] = max( -32, min( 64, (int) $raw ) );
                continue;
            }

            $style_options = [
                "breadcrumbs_style" => [ "bc-b1", "bc-b2", "bc-b3", "bc-b4", "bc-b5", "bc-b6" ],
                "table_of_contents_style" => [ "unstyled", "none", "toc00", "toc01", "toc02", "toc03", "toc04" ],
                "article_heading_style" => [ "none", "h2-tick", "h2-leftrule", "h2-underline", "h2-topline", "h2-dot", "h2-trailingrule", "h2-serif", "h2-uppercase", "h2-gradient", "h2-bracket", "h2-number", "h2-square", "h2-highlight", "h2-double", "h2-corner_tick" ],
                "article_numbered_list_style" => [ "none", "nlist01", "nlist02", "nlist03", "nlist04", "nlist05" ],
                "article_drop_cap_style" => [ "dropcap-classic", "dropcap-highlight", "dropcap-outline", "dropcap-side-rule", "dropcap-soft-tile", "dropcap-script-classic", "dropcap-script-tile", "dropcap-script-round", "dropcap-script-underline", "dropcap-script-shadow" ],
                "article_drop_cap_script_font" => [ "dancing-script", "great-vibes", "parisienne", "pinyon-script", "allura" ],
                "author_social_style" => [ "social-solid", "social-outline", "social-soft", "social-pills", "social-minimal", "unstyled" ],
                "author_social_archive_position" => [ "below_name", "below_title", "below_image", "below_bio" ],
                "author_archive_loading_mode" => [ "pagination", "infinite_scroll", "load_more" ],
                "author_archive_loading_style" => [ "none", "editorial_text", "accent_underline", "hairline_outline", "solid_accent", "soft_pill" ],
                "inline_photo_treatment" => [ "none", "fig1", "fig2", "fig4", "fig5" ],
                "featured_image_caption_template" => [ "none", "fig1", "fig2", "fig4", "fig5" ],
                "post_summary_style" => [ "unstyled", "none", "sum00", "sum01", "sum02", "sum03", "sum04", "sum05" ],
                "post_summary_background_mode" => array_keys( TemplateBackground::options() ),
                "post_summary_placement" => [ "manual", "above_content", "below_content" ],
                "post_faqs_style" => [ "unstyled", "none", "faq00", "faq01", "faq02", "faq03", "faq04" ],
                "post_faqs_placement" => [ "manual", "below_content", "below_author" ],
                "multi_authors_loop_output" => [ "primary", "comma", "lines" ],
                "table_of_contents_text_font_style" => [ "normal", "italic" ],
                "inline_photo_caption_font_style" => [ "normal", "italic" ],
                "featured_image_caption_font_style" => [ "normal", "italic" ],
                "post_faqs_text_font_style" => [ "normal", "italic" ],
            ];
            if ( isset( $style_options[ $key ] ) ) {
                $value = sanitize_key( (string) $value );
                $settings[ $key ] = in_array( $value, $style_options[ $key ], true ) ? $value : $style_options[ $key ][0];
                continue;
            }

            if ( "article_heading_styles_enabled" === $key || "article_numbered_lists_enabled" === $key || "inline_photo_treatments_enabled" === $key || "featured_image_caption_templates_enabled" === $key ) {
                $settings[ $key ] = (bool) $value;
                continue;
            }

            if ( in_array( $key, self::typography_preservation_setting_keys(), true ) ) {
                $settings[ $key ] = (bool) $value;
                continue;
            }

            if ( in_array( $key, [ 'shadow_posts_enabled', 'shadow_press_releases', 'post_list_defaults_enabled', 'hide_home_posts_without_featured_image', 'post_featured_image_required', 'author_social_cleanup', 'author_social_icons_enabled', 'public_debug_enabled', 'estimated_read_time_enabled', 'elementor_css_cache_busting', 'elementor_primary_category_enabled', 'elementor_primary_category_exclude_default', 'publication_social_cleanup', 'muckrack_verified_enabled', 'muckrack_author_always_show', 'publication_muckrack_verified_enabled', 'multi_authors_enabled', 'author_listing_hide_without_articles', 'author_listing_hide_without_featured_image', 'author_listing_show_press_releases', 'author_archive_loading_enabled', 'archive_search_enabled', 'press_release_include_enabled', 'post_summary_acf_enabled', 'post_faqs_acf_enabled', 'article_types_enabled', 'breadcrumbs_enabled', 'breadcrumbs_hide_home', 'breadcrumbs_hide_term_archives', 'table_of_contents_enabled', 'table_of_contents_auto_single', 'table_of_contents_include_summary', 'article_numbered_lists_enabled', 'article_drop_cap_enabled', 'rank_math_breadcrumb_check_enabled', 'hws_masked_admin_report_enabled', "content_generation_enabled", "post_hygiene_enabled", "post_hygiene_strip_inline_styles", "post_hygiene_unwrap_spans", "post_hygiene_remove_font_tags", "post_hygiene_strip_classes_ids", "post_hygiene_strip_empty_tags", "post_hygiene_clean_heading_children" ], true ) ) {
                $settings[ $key ] = (bool) $value;
                continue;
            }

            if ( 'muckrack_verified_style' === $key ) {
                $allowed = [ 'tooltip', 'text', 'compact_block' ];
                $settings[ $key ] = in_array( $value, $allowed, true ) ? $value : 'tooltip';
                continue;
            }

            if ( 'muckrack_icon_style' === $key ) {
                $allowed = [ "circle_check", "circle_outline_check", "check" ];
                $settings[ $key ] = in_array( $value, $allowed, true ) ? $value : 'circle_check';
                continue;
            }

            if ( in_array( $key, TemplateDesignRegistry::custom_color_setting_keys(), true ) ) {
                $raw = trim( (string) $value );
                $settings[ $key ] = "" === $raw ? "" : ( sanitize_hex_color( $raw ) ?: "" );
                continue;
            }

            if ( 0 === strpos( $key, 'muckrack_icon_color_' ) ) {
                $raw = trim( (string) $value );
                if ( '' === $raw ) {
                    $settings[ $key ] = '';
                    continue;
                }
                $color = sanitize_hex_color( $raw );
                $settings[ $key ] = $color ?: '';
                continue;
            }

            if ( 'publication_muckrack_text_mode' === $key ) {
                $allowed = [ 'news_outlet', 'publication_name', 'publication_sentence' ];
                $settings[ $key ] = in_array( $value, $allowed, true ) ? $value : 'news_outlet';
                continue;
            }

            if ( 'publication_muckrack_style' === $key ) {
                $allowed = [ 'block', 'mini_block', 'compact', 'minimalist' ];
                $settings[ $key ] = in_array( $value, $allowed, true ) ? $value : 'block';
                continue;
            }

            if ( "breadcrumbs_background_color" === $key ) {
                $raw = trim( (string) $value );
                $settings[ $key ] = "" === $raw ? "" : ( sanitize_hex_color( $raw ) ?: "" );
                continue;
            }

            if ( in_array( $key, [ "breadcrumbs_text_color", "table_of_contents_text_color", "article_heading_text_color", "article_numbered_list_text_color", "inline_photo_caption_text_color", "featured_image_caption_text_color", "post_summary_background_color", "post_summary_text_color", "post_faqs_text_color", "muckrack_verified_text_color", "publication_muckrack_text_color" ], true ) ) {
                $color = sanitize_hex_color( (string) $value );
                $settings[ $key ] = $color ?: self::color_default( $key );
                continue;
            }

            if ( 'muckrack_verified_contexts' === $key ) {
                $allowed = [ 'single_author', 'single_footer', 'author', 'home', 'loop_cards' ];
                $items = is_array( $value ) ? array_map( 'sanitize_key', $value ) : [];
                $settings[ $key ] = array_values( array_intersect( $allowed, $items ) );
                continue;
            }

            if ( 'author_social_networks' === $key ) {
                $settings[ $key ] = \smp_publication_integration\Content\AuthorSocialIcons::normalize_networks( is_array( $value ) ? $value : [] );
                continue;
            }

            if ( 'author_social_auto_contexts' === $key ) {
                $allowed = [ 'single_post', 'author_archive' ];
                $items = is_array( $value ) ? array_map( 'sanitize_key', $value ) : [];
                $settings[ $key ] = array_values( array_intersect( $allowed, array_unique( $items ) ) );
                continue;
            }

            if ( 'publication_muckrack_placements' === $key ) {
                $allowed = [ 'below_author', 'bottom_article' ];
                $items = is_array( $value ) ? array_map( 'sanitize_key', $value ) : [];
                $settings[ $key ] = array_values( array_intersect( $allowed, $items ) );
                continue;
            }

            if ( 'press_release_include_contexts' === $key ) {
                $allowed = [ 'home', 'category_tag', 'single_recent' ];
                $items = is_array( $value ) ? array_map( 'sanitize_key', $value ) : [];
                $settings[ $key ] = array_values( array_intersect( $allowed, $items ) );
                continue;
            }

            if ( "breadcrumbs_disabled_post_types" === $key ) {
                $items = is_array( $value ) ? array_map( "sanitize_key", $value ) : [];
                $settings[ $key ] = array_values( array_filter( $items, "post_type_exists" ) );
                continue;
            }

            if ( "post_hygiene_allowed_post_types" === $key ) {
                $items = is_array( $value ) ? array_map( "sanitize_key", $value ) : [];
                $items = array_values( array_filter( $items, "post_type_exists" ) );
                $settings[ $key ] = $items ?: [ "post" ];
                continue;
            }

            if ( "breadcrumbs_css_override" === $key ) {
                $settings[ $key ] = trim( str_replace( [ chr( 13 ), chr( 0 ) ], "", (string) $value ) );
                continue;
            }

            if ( "content_generation_api_base" === $key ) {
                $url = esc_url_raw( (string) $value );
                $settings[ $key ] = $url ?: "https://publish.scalemypublication.com/api/smp-content-generation/v1";
                continue;
            }
        }

        update_option( self::OPTION, $settings, false );
        self::invalidate_cache();
        self::log( 'Settings updated: ' . implode( ', ', array_keys( $changes ) ) );
        return $settings;
    }

    public static function update_page( string $type, int $page_id, string $template = '' ): array {
        $page_types = self::page_types();
        if ( ! isset( $page_types[ $type ] ) ) {
            return self::all();
        }

        $settings = self::all();
        $settings['page_assignments'][ $type ] = $page_id > 0 ? $page_id : 0;
        if ( '' !== $template || ! empty( $page_types[ $type ]['template'] ) ) {
            $settings['page_templates'][ $type ] = wp_kses_post( $template );
        }

        update_option( self::OPTION, $settings, false );
        self::invalidate_cache();
        self::log( 'Page assignment updated: ' . $type );
        return $settings;
    }

    /** Generic pages HWS Base Tools owns (any website). Every publication standard page is SMP's. */
    public static function hws_owned_page_keys(): array {
        return array_keys( \smp_publication_integration\Pages\PageTypes::BASE_TOOLS_OWNED );
    }

    /** Pages HWS Base Tools owned before HWS 13.19.0; their old assignments are still read until reassigned here. */
    public static function hws_legacy_page_keys(): array {
        return [ "terms", "privacy", "brand_assets", "headquarters", "faqs" ];
    }

    public static function page_assignment_id( string $type ): int {
        $type = sanitize_key( $type );
        $settings = self::all();
        $page_id = isset( $settings["page_assignments"][ $type ] ) ? absint( $settings["page_assignments"][ $type ] ) : 0;

        if ( $page_id <= 0 && in_array( $type, array_merge( self::hws_owned_page_keys(), self::hws_legacy_page_keys() ), true ) && function_exists( "get_option" ) ) {
            $page_id = absint( get_option( "hws_site_page_assignment_" . $type, 0 ) );
        }

        return $page_id;
    }

    public static function activity_log(): array {
        $entries = array_reverse( self::activity_logger()->all() );
        $log     = [];

        foreach ( array_slice( $entries, 0, 25 ) as $entry ) {
            $data      = $entry->to_array();
            $timestamp = strtotime( (string) ( $data['timestamp'] ?? '' ) );
            $log[]     = [
                'time'    => $timestamp ? date_i18n( 'Y-m-d H:i:s', $timestamp ) : current_time( 'mysql' ),
                'message' => sanitize_text_field( (string) ( $data['message'] ?? '' ) ),
                'level'   => sanitize_key( (string) ( $data['level'] ?? 'info' ) ),
                'source'  => sanitize_text_field( (string) ( $data['source'] ?? '' ) ),
            ];
        }

        return $log;
    }

    /**
     * @return ActivityLogEntry[]
     */
    public static function activity_entries(): array {
        return self::activity_logger()->all();
    }

    public static function log( string $message ): void {
        self::activity_logger()->add(
            new ActivityLogEntry(
                sanitize_text_field( $message ),
                [],
                is_user_logged_in() ? wp_get_current_user()->user_login : 'system',
                'smp-publication-integration',
                null,
                'info'
            )
        );
    }

    private static function activity_logger(): ActivityLogger {
        return new ActivityLogger(
            new ActivityLogConfig(
                [
                    'id'          => 'smpi-activity-log',
                    'title'       => 'SMP Publication Activity',
                    'storage'     => ActivityLogConfig::STORAGE_PERMANENT,
                    'storage_key' => 'smpi_activity_log',
                    'max_entries' => 50,
                    'collapsed'   => true,
                    'dark'        => true,
                ]
            )
        );
    }

    public static function page_slug_url( int $page_id ): string {
        $post = get_post( $page_id );
        if ( ! $post || "page" !== $post->post_type ) {
            return "";
        }

        $uri = get_page_uri( $page_id );
        if ( $uri ) {
            return home_url( user_trailingslashit( $uri ) );
        }

        $permalink = get_permalink( $page_id );
        return is_string( $permalink ) ? $permalink : "";
    }

    public static function page_slug( string $type ): string {
        $type = sanitize_key( $type );
        $slugs = self::default_page_slugs();
        if ( isset( $slugs[ $type ] ) ) {
            return $slugs[ $type ];
        }

        $page_types = self::page_types();
        $label = isset( $page_types[ $type ]['label'] ) ? (string) $page_types[ $type ]['label'] : str_replace( '_', ' ', $type );
        return sanitize_title( $label );
    }

    /** Page types now live in src/Pages/PageTypes.php; these keep the older Settings API working. */
    public static function default_page_slugs(): array {
        $slugs = [];
        foreach ( array_keys( PageTypes::all() ) as $type ) {
            $slugs[ $type ] = PageTypes::slug( $type );
        }
        return $slugs;
    }

    public static function page_types(): array {
        $types = [];
        foreach ( PageTypes::all() as $type => $config ) {
            $types[ $type ] = [ 'label' => $config['label'], 'description' => $config['description'], 'template' => true, 'group' => $config['group'] ];
        }
        return $types;
    }

    public static function default_page_templates(): array {
        $templates = [];
        foreach ( self::page_types() as $type => $config ) {
            if ( empty( $config['template'] ) ) {
                continue;
            }
            $templates[ $type ] = '[smp_publication_page_template type=' . sanitize_key( (string) $type ) . ']';
        }
        return $templates;
    }


}
