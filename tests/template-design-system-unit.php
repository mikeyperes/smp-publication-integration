<?php

declare(strict_types=1);

define( "ABSPATH", dirname( __DIR__ ) . "/" );

$GLOBALS["template_design_options"] = [
    "hws_brand_primary_color" => "#123456",
    "hws_brand_secondary_color" => "#654321",
    "elementor_active_kit" => 77,
];
$GLOBALS["template_design_elementor"] = [
    "system_typography" => [
        [ "_id" => "primary", "title" => "Primary", "typography_font_family" => "Roboto" ],
        [ "_id" => "secondary", "title" => "Secondary", "typography_font_family" => "Playfair Display" ],
    ],
];

function get_option( string $key, mixed $default = false ): mixed {
    return array_key_exists( $key, $GLOBALS["template_design_options"] )
        ? $GLOBALS["template_design_options"][ $key ]
        : $default;
}

function update_option( string $key, mixed $value, bool $autoload = true ): bool {
    $GLOBALS["template_design_options"][ $key ] = $value;
    return true;
}

function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
    return 77 === $post_id && "_elementor_page_settings" === $key
        ? $GLOBALS["template_design_elementor"]
        : ( $single ? "" : [] );
}

function sanitize_key( mixed $value ): string {
    return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( (string) $value ) ) ?: "";
}

function sanitize_html_class( mixed $value ): string {
    return preg_replace( "/[^a-zA-Z0-9_\-]/", "", (string) $value ) ?: "";
}

function sanitize_text_field( mixed $value ): string {
    return trim( strip_tags( (string) $value ) );
}

function sanitize_hex_color( mixed $value ): ?string {
    $value = strtolower( trim( (string) $value ) );
    return preg_match( "/^#[0-9a-f]{6}$/", $value ) ? $value : null;
}

function wp_parse_args( mixed $args, array $defaults = [] ): array {
    return array_merge( $defaults, is_array( $args ) ? $args : [] );
}

function absint( mixed $value ): int {
    return abs( (int) $value );
}

function is_user_logged_in(): bool {
    return false;
}

function post_type_exists( string $post_type ): bool {
    return in_array( $post_type, [ "post", "page", "press-release", "profile" ], true );
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, "UTF-8" );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, "UTF-8" );
}

function esc_url( mixed $value ): string {
    return filter_var( (string) $value, FILTER_SANITIZE_URL ) ?: "";
}

function esc_url_raw( mixed $value ): string {
    return esc_url( $value );
}

function get_bloginfo( string $show = "" ): string {
    return "Template Test Publication";
}

$root = dirname( __DIR__ );
$core = $root . "/lib/hexa-wordpress-plugin-core";
require $core . "/src/BrandColors/BrandColorProvider.php";
require $core . "/src/BrandColors/FontFamilyProvider.php";
require $core . "/src/BrandColors/FontWeightProvider.php";
require $core . "/src/BrandColors/TemplateColorResolver.php";
require $core . "/src/Typography/TypographyPreservation.php";
require $core . "/src/Typography/TemplateTypography.php";
require $core . "/src/GettingStartedChecklist/ChecklistReportBuilder.php";
require $core . "/src/SchemaTools/SchemaGraph.php";
require $root . "/src/Design/TemplateDesignRegistry.php";
require $root . "/src/Design/TemplateBackground.php";
require $root . "/src/Settings/SettingsRepository.php";
require $root . "/src/Settings/SettingsMigrations.php";
require $root . "/src/Support/Settings.php";
require $root . "/src/Support/Fields.php";
require $root . "/src/Support/QuickStartFeatures.php";
require $root . "/src/Content/ArticleStyles.php";
require $root . "/src/Content/MuckRackVerification.php";

use Hexa\PluginCore\BrandColors\TemplateColorResolver;
use Hexa\PluginCore\Typography\TemplateTypography;
use Hexa\PluginCore\Typography\TypographyPreservation;
use smp_publication_integration\Content\ArticleStyles;
use smp_publication_integration\Content\MuckRackVerification;
use smp_publication_integration\Design\TemplateDesignRegistry;
use smp_publication_integration\Settings\SettingsMigrations;
use smp_publication_integration\Settings\SettingsRepository;
use smp_publication_integration\Support\QuickStartFeatures;

function template_design_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
        exit( 1 );
    }
}

$expected_templates = [
    "breadcrumbs" => [ "bc-b1", "bc-b2", "bc-b3", "bc-b4", "bc-b5", "bc-b6" ],
    "table_of_contents" => [ "none", "toc00", "toc01", "toc02", "toc03", "toc04" ],
    "article_heading" => [ "none", "h2-tick", "h2-leftrule", "h2-underline", "h2-topline", "h2-dot", "h2-trailingrule", "h2-serif", "h2-uppercase", "h2-gradient", "h2-bracket", "h2-number", "h2-square", "h2-highlight", "h2-double", "h2-corner_tick" ],
    "article_numbered_list" => [ "none", "nlist01", "nlist02", "nlist03", "nlist04", "nlist05" ],
    "article_drop_cap" => [ "dropcap-classic", "dropcap-highlight", "dropcap-outline", "dropcap-side-rule", "dropcap-soft-tile", "dropcap-script-classic", "dropcap-script-tile", "dropcap-script-round", "dropcap-script-underline", "dropcap-script-shadow" ],
    "inline_photo_caption" => [ "none", "fig1", "fig2", "fig4", "fig5" ],
    "featured_image_caption" => [ "none", "fig1", "fig2", "fig4", "fig5" ],
    "post_summary" => [ "none", "sum00", "sum01", "sum02", "sum03", "sum04", "sum05" ],
    "post_faqs" => [ "none", "faq00", "faq01", "faq02", "faq03", "faq04" ],
    "muckrack_verified" => [ "tooltip", "text", "compact_block" ],
    "publication_muckrack" => [ "block", "mini_block", "compact", "minimalist" ],
];
$expected_native_accents = [
    "breadcrumbs" => array_fill_keys( $expected_templates["breadcrumbs"], "#d63428" ),
    "table_of_contents" => [ "none" => "#9ca3af", "toc00" => "#2563eb", "toc01" => "#2563eb", "toc02" => "#2563eb", "toc03" => "#2563eb", "toc04" => "#2563eb" ],
    "article_heading" => array_replace( array_fill_keys( $expected_templates["article_heading"], "#d63428" ), [ "h2-topline" => "#e5e7eb", "h2-trailingrule" => "#e5e7eb" ] ),
    "article_numbered_list" => [ "none" => "#9ca3af", "nlist01" => "#00ff41", "nlist02" => "#2563eb", "nlist03" => "#d63428", "nlist04" => "#111827", "nlist05" => "#a16207" ],
    "article_drop_cap" => array_replace( array_fill_keys( $expected_templates["article_drop_cap"], "#111111" ), [ "dropcap-highlight" => "#facc15" ] ),
    "inline_photo_caption" => [ "none" => "#d63428", "fig1" => "#d63428", "fig2" => "#d63428", "fig4" => "#0a0a0c", "fig5" => "#e9e9e9" ],
    "featured_image_caption" => [ "none" => "#d63428", "fig1" => "#d63428", "fig2" => "#d63428", "fig4" => "#0a0a0c", "fig5" => "#e9e9e9" ],
    "post_summary" => [ "none" => "#2563eb", "sum00" => "#1f2937", "sum01" => "#2563eb", "sum02" => "#0a0a0a", "sum03" => "#0a0a0a", "sum04" => "#2563eb", "sum05" => "#00ff41" ],
    "post_faqs" => [ "none" => "#2563eb", "faq00" => "#e5e7eb", "faq01" => "#e5e7eb", "faq02" => "#e5e7eb", "faq03" => "#2563eb", "faq04" => "#2563eb" ],
    "muckrack_verified" => array_fill_keys( $expected_templates["muckrack_verified"], "#2d5277" ),
    "publication_muckrack" => array_fill_keys( $expected_templates["publication_muckrack"], "#2d5277" ),
];

$definitions = TemplateDesignRegistry::definitions();
template_design_assert( array_keys( $expected_templates ) === array_keys( $definitions ), "The eleven design surfaces changed without updating the canonical matrix." );
template_design_assert(
    TemplateDesignRegistry::custom_color_setting_keys() === SettingsRepository::brand_primary_color_keys(),
    "The legacy brand-primary color key API does not return the registered custom template color keys."
);
$dashboard_source = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );
template_design_assert( 11 === substr_count( $dashboard_source, 'template_color_setting_html( "' ), "Every design surface must render through the shared Core template color control." );
$core_template_color_source = (string) file_get_contents( $core . "/src/WpAdminComponents/TemplateColorControl.php" );
template_design_assert(
    str_contains( $core_template_color_source, 'var explicit=hex(color);if(!explicit)syncCustomDisplay(control);color=explicit||base(control,mode)' ),
    "The bundled Core can replace a newly selected custom color with stale nested storage."
);
template_design_assert(
    ! str_contains( $core_template_color_source, 'syncCustomDisplay(control);color=hex(color)||base(control,mode)' ),
    "The bundled Core still uses the stale picker event order."
);

$template_count = 0;
$color_case_count = 0;
$typography_case_count = 0;
$production_sources = (string) file_get_contents( $root . "/src/Content/ArticleStyles.php" )
    . (string) file_get_contents( $root . "/src/Content/MuckRackVerification.php" );

foreach ( $definitions as $surface => $definition ) {
    $templates = array_keys( $definition["palettes"] );
    template_design_assert( $expected_templates[ $surface ] === $templates, $surface . " template choices do not match the canonical registry." );
    template_design_assert( $expected_native_accents[ $surface ] === array_map( static fn( array $palette ): string => (string) ( $palette["accent"] ?? "" ), $definition["palettes"] ), $surface . " native template accents do not match their original designs." );
    $template_count += count( $templates );

    foreach ( array_keys( $definition["variables"] ) as $variable ) {
        template_design_assert( str_contains( $production_sources, $variable ), $surface . " registers an accent variable that no frontend output consumes: " . $variable );
    }

    foreach ( $templates as $template ) {
        template_design_assert( str_contains( $production_sources, $template ), $surface . " template is not represented in frontend design code: " . $template );
        $native_palette = TemplateColorResolver::template_palette( $template, $definition["palettes"], $definition["fallback"] );
        $native = (string) reset( $native_palette );

        foreach ( [ TemplateColorResolver::TEMPLATE_DEFAULT, TemplateColorResolver::SITE_PRIMARY, TemplateColorResolver::SITE_SECONDARY, TemplateColorResolver::CUSTOM ] as $mode ) {
            $settings = [
                $definition["source_key"] => $mode,
                $definition["template_key"] => $template,
                $definition["custom_key"] => "",
            ];
            $variables = TemplateDesignRegistry::css_variables( $surface, $settings );
            ++$color_case_count;

            if ( TemplateColorResolver::TEMPLATE_DEFAULT === $mode ) {
                template_design_assert( [] === $variables, $surface . "/" . $template . " Template Default emitted color overrides." );
                template_design_assert( $native === TemplateDesignRegistry::effective_color( $surface, $settings ), $surface . "/" . $template . " did not retain its native accent." );
                continue;
            }

            template_design_assert( array_keys( $definition["variables"] ) === array_keys( $variables ), $surface . "/" . $template . " changed variables outside its mapped accent set." );
            $expected_base = TemplateColorResolver::SITE_PRIMARY === $mode
                ? "#123456"
                : ( TemplateColorResolver::SITE_SECONDARY === $mode ? "#654321" : $native );
            template_design_assert( $expected_base === TemplateDesignRegistry::effective_color( $surface, $settings ), $surface . "/" . $template . " resolved the wrong color source." );
            foreach ( $definition["variables"] as $variable => $transform ) {
                template_design_assert( TemplateColorResolver::transform( $expected_base, $transform ) === $variables[ $variable ], $surface . "/" . $template . " transformed " . $variable . " incorrectly." );
            }

            if ( TemplateColorResolver::CUSTOM === $mode ) {
                $settings[ $definition["custom_key"] ] = "#a1b2c3";
                $custom_variables = TemplateDesignRegistry::css_variables( $surface, $settings );
                template_design_assert( "#a1b2c3" === TemplateDesignRegistry::effective_color( $surface, $settings ), $surface . "/" . $template . " ignored an explicit custom color." );
                foreach ( $definition["variables"] as $variable => $transform ) {
                    template_design_assert( TemplateColorResolver::transform( "#a1b2c3", $transform ) === $custom_variables[ $variable ], $surface . "/" . $template . " did not apply the explicit custom color to " . $variable . "." );
                }
            }
        }

        foreach ( [ TemplateTypography::TEMPLATE_DEFAULT, TemplateTypography::SITE_INHERIT, TemplateTypography::CUSTOM ] as $mode ) {
            $settings = array_merge(
                TypographyPreservation::defaults( $surface, false ),
                [
                    TemplateTypography::setting_key( $surface ) => $mode,
                    $definition["template_key"] => $template,
                ]
            );
            $typography = TemplateDesignRegistry::typography_definition( $surface );
            $settings[ $typography["font_family"]["key"] ] = "native_primary";
            $settings[ $typography["font_weight"]["key"] ] = "700";
            foreach ( TemplateDesignRegistry::typography_variable_definitions()[ $surface ] ?? [] as $variable_definition ) {
                [ , $setting_key, $type ] = $variable_definition;
                $settings[ $setting_key ] = "color" === $type ? "#abcdef" : ( "size" === $type ? 22 : "oblique" );
            }

            $variables = TemplateDesignRegistry::typography_css_variables( $surface, $settings );
            $preservation = TemplateTypography::preservation_values( $settings, $surface, false );
            ++$typography_case_count;

            if ( TemplateTypography::TEMPLATE_DEFAULT === $mode ) {
                template_design_assert( [] === $variables, $surface . "/" . $template . " Template Default emitted typography overrides." );
                template_design_assert( ! in_array( true, $preservation, true ), $surface . "/" . $template . " Template Default did not retain template typography." );
            } elseif ( TemplateTypography::SITE_INHERIT === $mode ) {
                template_design_assert( [] === $variables, $surface . "/" . $template . " Site Typography emitted custom values." );
                template_design_assert( ! in_array( false, $preservation, true ), $surface . "/" . $template . " Site Typography did not inherit every typography property." );
            } else {
                template_design_assert( count( $variables ) >= 2, $surface . "/" . $template . " Custom Typography did not emit the Core font and weight selections." );
                template_design_assert( ! in_array( true, $preservation, true ), $surface . "/" . $template . " Custom Typography unexpectedly preserved a site value." );
            }
        }
    }
}

template_design_assert( 74 === $template_count, "Expected exactly 74 registered template choices; found " . $template_count . "." );
template_design_assert( 296 === $color_case_count, "Expected exactly 296 color mode/template cases; ran " . $color_case_count . "." );
template_design_assert( 222 === $typography_case_count, "Expected exactly 222 typography mode/template cases; ran " . $typography_case_count . "." );

foreach ( $definitions as $surface => $definition ) {
    foreach ( array_keys( $definition["variables"] ) as $variable ) {
        template_design_assert( ! str_contains( $production_sources, "var(" . $variable . ")" ), $surface . " uses " . $variable . " without a native Template Default fallback." );
        template_design_assert( str_contains( $production_sources, "var(" . $variable . "," ), $surface . " does not consume " . $variable . " in frontend CSS." );
    }
}

$mapped_contract_count = 0;
$native_typography = array_fill_keys( TypographyPreservation::PROPERTIES, false );
$heading_variables = array_keys( $definitions["article_heading"]["variables"] );
foreach ( array_diff( $expected_templates["article_heading"], [ "none" ] ) as $template ) {
    $css = ArticleStyles::article_heading_rules( $template, ".article", ".article h2", ".article h3", $native_typography );
    template_design_assert(
        array_filter( $heading_variables, static fn( string $variable ): bool => str_contains( $css, "var(" . $variable . "," ) ) !== [],
        "Heading " . $template . " has no mapped decorative color surface."
    );
    ++$mapped_contract_count;
}
template_design_assert( "" === ArticleStyles::article_heading_rules( "none", ".article", ".article h2", ".article h3", $native_typography ), "Heading None unexpectedly emits template CSS." );

$numbered_list_contracts = [
    "nlist01" => "color:var(--smpi-numbered-list-accent,#00ff41)",
    "nlist02" => "background:var(--smpi-numbered-list-accent,#2563eb)",
    "nlist03" => "border-left:3px solid var(--smpi-numbered-list-accent,#d63428)",
    "nlist04" => "border-top:3px solid var(--smpi-numbered-list-accent,#111827)",
    "nlist05" => "border:1px solid var(--smpi-numbered-list-accent,#a16207)",
];
foreach ( $numbered_list_contracts as $template => $needle ) {
    $css = ArticleStyles::article_numbered_list_rules( $template, ".article-list", false );
    template_design_assert( str_contains( $css, $needle ), "Numbered list " . $template . " is not wired to its original decorative color surface." );
    ++$mapped_contract_count;
}
template_design_assert( "" === ArticleStyles::article_numbered_list_rules( "none", ".article-list", false ), "Numbered list None unexpectedly emits template CSS." );

$drop_cap_variables = array_keys( $definitions["article_drop_cap"]["variables"] );
foreach ( $expected_templates["article_drop_cap"] as $template ) {
    $css = ArticleStyles::article_drop_cap_rules( $template, ".article .lead", $native_typography, TemplateTypography::TEMPLATE_DEFAULT, "template" );
    template_design_assert(
        array_filter( $drop_cap_variables, static fn( string $variable ): bool => str_contains( $css, "var(" . $variable . "," ) ) !== [],
        "Drop cap " . $template . " has no mapped decorative color surface."
    );
    ++$mapped_contract_count;
}

$inline_contracts = [
    "fig1" => "border-left:3px solid var(--smpi-photo-accent,#d63428)",
    "fig2" => "border-top:3px solid var(--smpi-photo-accent,#d63428)",
    "fig4" => "linear-gradient(to top,var(--smpi-photo-overlay,rgba(10,10,12,.85)),var(--smpi-photo-overlay-clear,rgba(10,10,12,0)))",
    "fig5" => "border:1px solid var(--smpi-photo-accent,#e9e9e9)",
];
foreach ( $inline_contracts as $template => $needle ) {
    $css = ArticleStyles::inline_photo_rules( $template, ".figure", ".image", ".caption", false );
    template_design_assert( str_contains( $css, $needle ), "Inline caption " . $template . " is not wired to its original decorative color surface." );
    ++$mapped_contract_count;
}

$featured_contracts = [
    "fig1" => "border-left:3px solid var(--smpi-fi-accent,#d63428)",
    "fig2" => "border-top:3px solid var(--smpi-fi-accent,#d63428)",
    "fig4" => "linear-gradient(to top,var(--smpi-fi-overlay,rgba(10,10,12,.85)),var(--smpi-fi-overlay-clear,rgba(10,10,12,0)))",
    "fig5" => "border:1px solid var(--smpi-fi-accent,#e9e9e9)",
];
foreach ( $featured_contracts as $template => $needle ) {
    $css = ArticleStyles::featured_image_caption_rules( $template, ".figure", ".image", ".caption", false );
    template_design_assert( str_contains( $css, $needle ), "Featured caption " . $template . " is not wired to its original decorative color surface." );
    ++$mapped_contract_count;
}

$breadcrumb_css = ArticleStyles::breadcrumbs_css( false );
$breadcrumb_contracts = [
    "bc-b1" => ".smpi-bc-b1 .smpi-breadcrumb-link{color:var(--smpi-bc-accent,#d63428)",
    "bc-b2" => ".smpi-bc-b2 .smpi-breadcrumb-link:hover{color:var(--smpi-bc-accent,#d63428)",
    "bc-b3" => ".smpi-bc-b3::after{content:\"\";position:absolute;left:24px;bottom:-1px;width:46px;height:2px;background:var(--smpi-bc-accent,#d63428)",
    "bc-b4" => ".smpi-bc-b4 .smpi-breadcrumb-current{background:var(--smpi-bc-accent,#d63428)",
    "bc-b5" => ".smpi-bc-b5 .smpi-breadcrumb-link{color:var(--smpi-bc-accent,#d63428)",
    "bc-b6" => ".smpi-bc-b6 .smpi-breadcrumb-link,.smpi-bc-b6 .smpi-breadcrumb-current{color:var(--smpi-bc-accent,#d63428)",
];
foreach ( $breadcrumb_contracts as $template => $needle ) {
    template_design_assert( str_contains( $breadcrumb_css, $needle ), "Breadcrumb " . $template . " is not wired to its original decorative color surface." );
    ++$mapped_contract_count;
}

$toc_css = ArticleStyles::toc_css( false );
template_design_assert( str_contains( $toc_css, ".smpi-table-of-contents .smpi-toc-caret{border-color:var(--smpi-toc-accent,#9ca3af)}" ), "The table of contents caret is not wired to the selected design color." );
$toc_contracts = [
    "none" => ".smpi-toc-caret{flex:0 0 auto;width:8px;height:8px;border-right:2px solid var(--smpi-toc-accent,#2563eb)",
    "toc00" => ".smpi-toc00 .smpi-toc-link:hover{color:var(--smpi-toc-accent,#2563eb)",
    "toc01" => ".smpi-toc01{background:#fafbfc;border-radius:12px;border-left:3px solid var(--smpi-toc-accent,#2563eb)",
    "toc02" => ".smpi-toc02 .smpi-toc-item:before{content:counter(t,decimal-leading-zero);color:var(--smpi-toc-accent,#2563eb)",
    "toc03" => ".smpi-toc03 .smpi-toc-link:before{content:counter(t,decimal-leading-zero);color:var(--smpi-toc-accent,#2563eb)",
    "toc04" => ".smpi-toc04 .smpi-toc-link:hover{border-color:var(--smpi-toc-accent,#2563eb);color:var(--smpi-toc-accent,#2563eb)",
];
foreach ( $toc_contracts as $template => $needle ) {
    template_design_assert( str_contains( $toc_css, $needle ), "Table of contents " . $template . " is not wired to its original decorative color surface." );
    ++$mapped_contract_count;
}

$content_block_css = ArticleStyles::post_acf_css( false );
$content_block_contracts = [
    "sum00" => ".smpi-sum00 .smpi-post-summary-title{margin:0;font-size:1.3rem;font-weight:800;color:var(--smpi-summary-accent,#1f2937)",
    "sum01" => ".smpi-sum01{background:var(--smpi-summary-background,#fff);border:1px solid #e5e7eb;border-left:4px solid var(--smpi-summary-accent,#2563eb)",
    "sum02" => ".smpi-sum02{background:var(--smpi-summary-background,transparent);border:1px solid var(--smpi-summary-accent-soft,rgba(10,10,10,.12));border-top:3px solid var(--smpi-summary-accent,#0a0a0a)",
    "sum03" => ".smpi-sum03 .smpi-post-summary-title{margin:0;background:var(--smpi-summary-accent,#0a0a0a);color:var(--smpi-summary-accent-ink,#fff)",
    "sum04" => ".smpi-sum04{background:var(--smpi-summary-background,var(--smpi-summary-accent-soft,#eff4ff))",
    "sum05" => ".smpi-sum05 .smpi-post-summary-title{align-items:center;color:var(--smpi-summary-accent,#00ff41)",
    "faq00" => ".smpi-faq00 .smpi-post-faqs-content,.smpi-faq01 .smpi-post-faqs-content{border-top:1px solid var(--smpi-faq-accent,#e5e7eb)",
    "faq01" => ".smpi-faq00 .smpi-post-faq-item,.smpi-faq01 .smpi-post-faq-item{border-bottom:1px solid var(--smpi-faq-accent,#e5e7eb)",
    "faq02" => ".smpi-faq02 .smpi-post-faq-item{border:1px solid var(--smpi-faq-accent,#e5e7eb)",
    "faq03" => ".smpi-faq03 .smpi-post-faq-item:before{content:counter(f,decimal-leading-zero);position:absolute;left:0;top:7px;font-size:2rem;font-weight:800;line-height:1;color:var(--smpi-faq-accent-soft,rgba(37,99,235,.22))",
    "faq04" => ".smpi-faq04 .smpi-post-faq-item{background:var(--smpi-faq-accent-tint,#f8fafc);border:1px solid var(--smpi-faq-accent-soft,#eef2f7)",
];
foreach ( $content_block_contracts as $template => $needle ) {
    template_design_assert( str_contains( $content_block_css, $needle ), "Content block " . $template . " is not wired to its original decorative color surface." );
    ++$mapped_contract_count;
}

$muckrack_source = (string) file_get_contents( $root . "/src/Content/MuckRackVerification.php" );
$muckrack_contracts = [
    "tooltip" => ".smpi-muckrack-icon{display:inline-flex",
    "text" => ".smpi-muckrack-brand{color:var(--smpi-muckrack-author-accent,#2d5277)",
    "compact_block" => ".smpi-muckrack-author-note{display:inline-flex",
    "block" => ".smpi-muckrack-publication-block{display:block;padding:10px 12px;border-left:3px solid var(--smpi-muckrack-publication-accent,#2d5277)",
    "mini_block" => ".smpi-muckrack-publication-mini_block{display:block;padding:7px 10px;border-left:2px solid var(--smpi-muckrack-publication-accent,#2d5277)",
    "compact" => ".smpi-muckrack-publication-compact{display:inline-flex;align-items:center;gap:.35em;padding:.28em .7em;border:1px solid var(--smpi-muckrack-publication-accent,#2d5277)",
    "minimalist" => ".smpi-muckrack-publication-text .smpi-muckrack-brand{color:var(--smpi-muckrack-publication-accent,#2d5277)",
];
foreach ( $muckrack_contracts as $template => $needle ) {
    template_design_assert( str_contains( $muckrack_source, $needle ), "MuckRack " . $template . " is not wired to its original decorative color surface." );
    ++$mapped_contract_count;
}

template_design_assert( 68 === $mapped_contract_count, "Expected 68 color-bearing templates; verified " . $mapped_contract_count . "." );
template_design_assert( 6 === $template_count - $mapped_contract_count, "Only the six explicit None templates may have no decorative color mapping." );

foreach ( ArticleStyles::article_drop_cap_script_template_fonts() as $style => $font_key ) {
    $font = ArticleStyles::article_drop_cap_script_fonts()[ $font_key ];
    $template_css = ArticleStyles::article_drop_cap_rules( $style, ".article .lead", [], TemplateTypography::TEMPLATE_DEFAULT );
    $custom_template_css = ArticleStyles::article_drop_cap_rules( $style, ".article .lead", TypographyPreservation::values( [], "article_drop_cap", false ), TemplateTypography::CUSTOM, "template" );
    $site_css = ArticleStyles::article_drop_cap_rules( $style, ".article .lead", TypographyPreservation::values( [], "article_drop_cap", true ), TemplateTypography::SITE_INHERIT );
    template_design_assert( str_contains( $template_css, 'font-family:"' . $font["family"] . '"' ), $style . " lost its template-owned script font in Template Default." );
    template_design_assert( str_contains( $custom_template_css, 'font-family:"' . $font["family"] . '"' ), $style . " lost its template-owned script font in Custom Typography." );
    template_design_assert( ! str_contains( $site_css, "font-family:" ) && ! str_contains( $site_css, "font-style:" ), $style . " forced a font or style in Site Typography mode." );
}

$GLOBALS["template_design_options"][ SettingsRepository::OPTION ] = [
    "article_heading_accent_color" => "#13579b",
    "breadcrumbs_background_color" => "#ffffff",
];
unset( $GLOBALS["template_design_options"]["smpi_migration_template_design_modes_0_6_243"] );
( new SettingsMigrations() )->migrate_template_design_modes();
$migrated = $GLOBALS["template_design_options"][ SettingsRepository::OPTION ];
foreach ( $definitions as $surface => $definition ) {
    template_design_assert( TemplateColorResolver::CUSTOM === $migrated[ $definition["source_key"] ], "Existing " . $surface . " color mode was not migrated to Custom." );
    template_design_assert( "" !== (string) $migrated[ $definition["custom_key"] ], "Existing " . $surface . " color value was not preserved or populated." );
    template_design_assert( TemplateTypography::CUSTOM === $migrated[ TemplateTypography::setting_key( $surface ) ], "Existing " . $surface . " typography mode was not migrated to Custom." );
}
template_design_assert( "#13579b" === $migrated["article_heading_accent_color"], "Migration replaced an existing custom accent." );
template_design_assert( "" === $migrated["breadcrumbs_background_color"], "Migration retained the old forced white breadcrumb background." );

unset( $GLOBALS["template_design_options"][ SettingsRepository::OPTION ], $GLOBALS["template_design_options"]["smpi_migration_template_design_modes_0_6_243"] );
( new SettingsMigrations() )->migrate_template_design_modes();
$new_install = $GLOBALS["template_design_options"][ SettingsRepository::OPTION ];
foreach ( $definitions as $surface => $definition ) {
    template_design_assert( TemplateColorResolver::TEMPLATE_DEFAULT === $new_install[ $definition["source_key"] ], "New " . $surface . " color mode did not default to Template Default." );
    template_design_assert( TemplateTypography::TEMPLATE_DEFAULT === $new_install[ TemplateTypography::setting_key( $surface ) ], "New " . $surface . " typography mode did not default to Template Default." );
}

$quick_start_items = QuickStartFeatures::items();
foreach ( $definitions as $surface => $definition ) {
    $mode_pair_found = false;
    foreach ( $quick_start_items as $item ) {
        $settings = is_array( $item["settings"] ?? null ) ? $item["settings"] : [];
        if ( TemplateColorResolver::CUSTOM === ( $settings[ $definition["source_key"] ] ?? null ) && TemplateTypography::CUSTOM === ( $settings[ TemplateTypography::setting_key( $surface ) ] ?? null ) ) {
            $mode_pair_found = true;
            break;
        }
    }
    template_design_assert( $mode_pair_found, "Quick Start does not explicitly select Custom color and typography for " . $surface . "." );
}

$GLOBALS["template_design_options"][ SettingsRepository::OPTION ] = SettingsRepository::defaults();
$context_color = new ReflectionMethod( MuckRackVerification::class, "author_context_color_override" );
template_design_assert( "" === $context_color->invoke( null, "single_author" ), "MuckRack emitted a default inline author color." );
$GLOBALS["template_design_options"][ SettingsRepository::OPTION ]["muckrack_icon_color_single_author"] = "#fedcba";
template_design_assert( "#fedcba" === $context_color->invoke( null, "single_author" ), "MuckRack ignored an explicit context color override." );
$publication_default = MuckRackVerification::publication_verification_markup();
$publication_custom = MuckRackVerification::publication_verification_markup( "", "", "#abcdef" );
template_design_assert( ! str_contains( $publication_default, "style=" ) && ! str_contains( $publication_default, "--smpi-muckrack-author-accent" ), "MuckRack emitted a default inline publication color." );
template_design_assert( str_contains( $publication_custom, 'style="--smpi-muckrack-publication-accent:#abcdef"' ) && ! str_contains( $publication_custom, "--smpi-muckrack-author-accent" ), "MuckRack did not isolate an explicit shortcode color to the publication accent." );

echo "PASS: 74 templates, 296 color cases, 222 typography cases, 68 frontend color contracts, migration, Quick Start, script fonts, and MuckRack inline-color isolation.\n";
