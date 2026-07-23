<?php

declare(strict_types=1);

define( "ABSPATH", dirname( __DIR__ ) . "/" );

$GLOBALS["smpi_font_test_options"] = [
    "elementor_active_kit" => 77,
];
$GLOBALS["smpi_font_test_kit"] = [
    "system_typography" => [
        [ "_id" => "primary", "title" => "Primary", "typography_font_family" => "Roboto" ],
        [ "_id" => "secondary", "title" => "Secondary", "typography_font_family" => "Playfair Display" ],
    ],
    "custom_typography" => [
        [ "_id" => "brand_body", "title" => "Brand Body", "typography_font_family" => "Inter" ],
    ],
];

function get_option( string $key, mixed $default = false ): mixed {
    return $GLOBALS["smpi_font_test_options"][ $key ] ?? $default;
}

function update_option( string $key, mixed $value, bool $autoload = true ): bool {
    $GLOBALS["smpi_font_test_options"][ $key ] = $value;
    return true;
}

function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
    return 77 === $post_id && "_elementor_page_settings" === $key
        ? $GLOBALS["smpi_font_test_kit"]
        : ( $single ? "" : [] );
}

function sanitize_key( string $value ): string {
    return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "";
}

function sanitize_text_field( mixed $value ): string {
    return trim( strip_tags( (string) $value ) );
}

function sanitize_hex_color( mixed $value ): ?string {
    $value = strtolower( trim( (string) $value ) );
    return preg_match( "/^#[0-9a-f]{6}$/", $value ) ? $value : null;
}

function absint( mixed $value ): int {
    return abs( (int) $value );
}

function wp_parse_args( mixed $args, array $defaults = [] ): array {
    return array_merge( $defaults, is_array( $args ) ? $args : [] );
}

function is_user_logged_in(): bool {
    return false;
}

$root = dirname( __DIR__ );
require $root . "/lib/hexa-wordpress-plugin-core/src/ActivityLog/ActivityLogConfig.php";
require $root . "/lib/hexa-wordpress-plugin-core/src/ActivityLog/ActivityLogEntry.php";
require $root . "/lib/hexa-wordpress-plugin-core/src/ActivityLog/ActivityLogger.php";
require $root . "/lib/hexa-wordpress-plugin-core/src/BrandColors/BrandColorProvider.php";
require $root . "/lib/hexa-wordpress-plugin-core/src/BrandColors/FontFamilyProvider.php";
require $root . "/lib/hexa-wordpress-plugin-core/src/BrandColors/FontWeightProvider.php";
require $root . "/src/Settings/SettingsRepository.php";
require $root . "/src/Support/Settings.php";
require $root . "/src/Content/ArticleStyles.php";
require $root . "/src/Content/MuckRackVerification.php";

use smp_publication_integration\Content\ArticleStyles;
use smp_publication_integration\Content\MuckRackVerification;
use smp_publication_integration\Support\Settings;

$font_keys = Settings::font_family_setting_keys();
if ( 10 !== count( $font_keys ) ) {
    fwrite( STDERR, "FAIL: SMP must expose exactly ten shared font-family settings.\n" );
    exit( 1 );
}

$weight_keys = Settings::font_weight_setting_keys();
if ( 10 !== count( $weight_keys ) ) {
    fwrite( STDERR, "FAIL: SMP must expose exactly ten shared font-weight settings.\n" );
    exit( 1 );
}

$changes = array_fill_keys( $font_keys, "elementor_brand_body" );
$saved = Settings::update( $changes );
foreach ( $font_keys as $key ) {
    if ( "elementor_brand_body" !== $saved[ $key ] ) {
        fwrite( STDERR, "FAIL: Valid Elementor source was not saved for {$key}.\n" );
        exit( 1 );
    }
}
$saved = Settings::update( array_fill_keys( $weight_keys, "700" ) );
foreach ( $weight_keys as $key ) {
    if ( "700" !== $saved[ $key ] ) {
        fwrite( STDERR, "FAIL: Valid Core font weight was not saved for {$key}.\n" );
        exit( 1 );
    }
}
Settings::update( [
    "article_heading_preserve_font_family" => false,
    "article_heading_preserve_font_weight" => false,
] );

$article_css = ArticleStyles::font_overrides_css();
$muckrack_css = MuckRackVerification::font_overrides_css();
if ( 8 !== substr_count( $article_css, "--e-global-typography-brand_body-font-family" ) ) {
    fwrite( STDERR, "FAIL: All eight article design outputs did not resolve the selected Elementor source.\n" );
    exit( 1 );
}
if ( 2 !== substr_count( $muckrack_css, "--e-global-typography-brand_body-font-family" ) ) {
    fwrite( STDERR, "FAIL: Both MuckRack outputs did not resolve the selected Elementor source.\n" );
    exit( 1 );
}
if ( 8 !== substr_count( $article_css, "font-weight:700" ) || 2 !== substr_count( $muckrack_css, "font-weight:700" ) ) {
    fwrite( STDERR, "FAIL: All ten design outputs did not apply the selected Core font weight.\n" );
    exit( 1 );
}

$saved = Settings::update( [ "article_heading_font_family" => "native_primary" ] );
if ( "native_primary" !== $saved["article_heading_font_family"] || "Native primary - Roboto" !== Settings::font_family_label( "article_heading_font_family" ) ) {
    fwrite( STDERR, "FAIL: Native primary was not preserved and reported through Core.\n" );
    exit( 1 );
}

$saved = Settings::update( [ "article_heading_font_family" => "font-family:red" ] );
if ( "template" !== $saved["article_heading_font_family"] || "" !== Settings::font_family_css( "article_heading_font_family" ) ) {
    fwrite( STDERR, "FAIL: An arbitrary font value bypassed Core source validation.\n" );
    exit( 1 );
}

$saved = Settings::update( [ "article_heading_font_weight" => "heavy" ] );
if ( "inherit" !== $saved["article_heading_font_weight"] || "" !== Settings::font_weight_css( "article_heading_font_weight" ) ) {
    fwrite( STDERR, "FAIL: An invalid font weight bypassed Core validation.\n" );
    exit( 1 );
}

$dashboard = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );
$ajax = (string) file_get_contents( $root . "/src/Admin/Ajax/AjaxController.php" );
$quick_start = (string) file_get_contents( $root . "/src/Support/QuickStartFeatures.php" );
$core_version = trim( (string) file_get_contents( $root . "/lib/hexa-wordpress-plugin-core/VERSION" ) );

if ( version_compare( $core_version, "0.19.68", "<" ) || ! str_contains( $dashboard, "FontFamilyControl::render(" ) || ! str_contains( $dashboard, '"weight_key" => $weight_key' ) ) {
    fwrite( STDERR, "FAIL: SMP is not using the reusable Hexa WP Core font control.\n" );
    exit( 1 );
}
foreach ( $font_keys as $key ) {
    if ( ! str_contains( $dashboard, 'font_family_setting_html( "' . $key . '"' ) || ! str_contains( $quick_start, '"' . $key . '" => "template"' ) ) {
        fwrite( STDERR, "FAIL: {$key} is missing from the Features UI or Quick Start.\n" );
        exit( 1 );
    }
}
foreach ( $weight_keys as $key ) {
    if ( ! str_contains( $quick_start, '"' . $key . '" => "inherit"' ) ) {
        fwrite( STDERR, "FAIL: {$key} is missing from Quick Start.\n" );
        exit( 1 );
    }
}
if ( ! str_contains( $ajax, "Settings::font_family_setting_keys()" ) || ! str_contains( $ajax, "Settings::font_weight_setting_keys()" ) || ! str_contains( $dashboard, "smpiFV" ) || ! str_contains( $dashboard, "smpiWV" ) ) {
    fwrite( STDERR, "FAIL: Shared AJAX persistence or live preview synchronization is missing.\n" );
    exit( 1 );
}

echo "PASS: All ten SMP design outputs use the reusable Core font family and weight contracts.\n";
