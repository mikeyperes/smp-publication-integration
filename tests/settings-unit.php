<?php

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$GLOBALS['smpi_test_options'] = [];

function get_option( string $key, mixed $default = false ): mixed { return $GLOBALS['smpi_test_options'][ $key ] ?? $default; }
function update_option( string $key, mixed $value, bool $autoload = true ): bool { $GLOBALS['smpi_test_options'][ $key ] = $value; return true; }
function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ?: '' ); }
function sanitize_text_field( mixed $value ): string { return trim( strip_tags( (string) $value ) ); }
function sanitize_hex_color( mixed $value ): ?string {
    $value = strtolower( trim( (string) $value ) );
    return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : null;
}
function wp_parse_args( mixed $args, array $defaults = [] ): array { return array_merge( $defaults, is_array( $args ) ? $args : [] ); }
function is_user_logged_in(): bool { return false; }

require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/ActivityLog/ActivityLogConfig.php';
require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/ActivityLog/ActivityLogEntry.php';
require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/ActivityLog/ActivityLogger.php';
require dirname( __DIR__ ) . '/src/Settings/SettingsRepository.php';
require dirname( __DIR__ ) . '/src/Settings/SettingsMigrations.php';
require dirname( __DIR__ ) . '/src/Support/Settings.php';
require dirname( __DIR__ ) . '/src/Content/Breadcrumbs.php';

use smp_publication_integration\Content\Breadcrumbs;
use smp_publication_integration\Settings\SettingsMigrations;
use smp_publication_integration\Support\Settings;

set_error_handler(
    static function( int $severity, string $message, string $file, int $line ): never {
        throw new ErrorException( $message, 0, $severity, $file, $line );
    }
);

$settings = Settings::update( [ 'article_heading_accent_color' => 'invalid' ] );
if ( '#2d5277' !== $settings['article_heading_accent_color'] ) {
    fwrite( STDERR, "FAIL: Invalid feature color did not fall back to the canonical default.\n" );
    exit( 1 );
}

$settings = Settings::update( [ 'article_heading_accent_color' => '#A1B2C3' ] );
if ( '#a1b2c3' !== $settings['article_heading_accent_color'] ) {
    fwrite( STDERR, "FAIL: Valid feature color was not normalized and saved.\n" );
    exit( 1 );
}

$settings = Settings::update( [ 'breadcrumbs_background_color' => 'invalid' ] );
if ( '#ffffff' !== $settings[ 'breadcrumbs_background_color' ] ) {
    fwrite( STDERR, "FAIL: Invalid breadcrumb background did not fall back to white.\n" );
    exit( 1 );
}

$settings = Settings::update( [ 'breadcrumbs_background_color' => '#0A0B0C' ] );
if ( '#0a0b0c' !== $settings[ 'breadcrumbs_background_color' ] ) {
    fwrite( STDERR, "FAIL: Valid breadcrumb background was not normalized and saved.\n" );
    exit( 1 );
}

$settings = Settings::update( [ 'article_drop_cap_style' => 'dropcap-highlight' ] );
if ( 'dropcap-highlight' !== $settings['article_drop_cap_style'] ) {
    fwrite( STDERR, "FAIL: Valid drop-cap template was not saved.\n" );
    exit( 1 );
}

$settings = Settings::update( [ 'article_drop_cap_style' => 'not-a-template' ] );
if ( 'dropcap-classic' !== $settings['article_drop_cap_style'] ) {
    fwrite( STDERR, "FAIL: Invalid drop-cap template did not fall back to the classic template.\n" );
    exit( 1 );
}

$GLOBALS['smpi_test_options']['smpi_settings'] = [
    'article_heading_styles_enabled'   => false,
    'article_heading_style'            => 'h2-tick',
    'article_heading_accent_color'     => '#000033',
    'article_heading_h2_font_size'     => 23,
    'article_heading_h3_font_size'     => 20,
    'table_of_contents_enabled'        => true,
    'table_of_contents_style'          => 'toc03',
    'inline_photo_treatments_enabled'  => true,
    'inline_photo_treatment'           => 'fig2',
];
( new SettingsMigrations() )->repair_defective_heading_quick_start_preset();
if ( true !== $GLOBALS['smpi_test_options']['smpi_settings']['article_heading_styles_enabled'] ) {
    fwrite( STDERR, "FAIL: Defective Quick Start heading preset was not repaired.\n" );
    exit( 1 );
}

unset( $GLOBALS['smpi_test_options']['smpi_migration_heading_quick_start_0_6_191'] );
$GLOBALS['smpi_test_options']['smpi_settings']['article_heading_styles_enabled'] = false;
$GLOBALS['smpi_test_options']['smpi_settings']['article_heading_accent_color'] = '#123456';
( new SettingsMigrations() )->repair_defective_heading_quick_start_preset();
if ( false !== $GLOBALS['smpi_test_options']['smpi_settings']['article_heading_styles_enabled'] ) {
    fwrite( STDERR, "FAIL: Heading migration changed a non-preset disabled setting.\n" );
    exit( 1 );
}

$GLOBALS['smpi_test_options']['smpi_settings'] = [
    'breadcrumbs_hide_single_posts' => true,
    'breadcrumbs_disabled_post_types' => [ 'profile' ],
];
( new SettingsMigrations() )->migrate_breadcrumb_single_post_setting();
$migrated_breadcrumbs = $GLOBALS['smpi_test_options']['smpi_settings'];
if (
    isset( $migrated_breadcrumbs['breadcrumbs_hide_single_posts'] )
    || [ 'profile', 'post' ] !== $migrated_breadcrumbs['breadcrumbs_disabled_post_types']
) {
    fwrite( STDERR, "FAIL: Legacy single-post breadcrumb visibility was not migrated to the Posts type.\n" );
    exit( 1 );
}

unset( $GLOBALS['smpi_test_options']['smpi_migration_breadcrumb_post_types_0_6_218'] );
$GLOBALS['smpi_test_options']['smpi_settings'] = [
    'breadcrumbs_hide_single_posts' => false,
    'breadcrumbs_disabled_post_types' => [ 'page' ],
];
( new SettingsMigrations() )->migrate_breadcrumb_single_post_setting();
$migrated_breadcrumbs = $GLOBALS['smpi_test_options']['smpi_settings'];
if (
    isset( $migrated_breadcrumbs['breadcrumbs_hide_single_posts'] )
    || [ 'page' ] !== $migrated_breadcrumbs['breadcrumbs_disabled_post_types']
) {
    fwrite( STDERR, "FAIL: Disabled legacy single-post visibility changed another post type.\n" );
    exit( 1 );
}

$valid_css = 'body .smpi-breadcrumbs[class*="smpi-bc-"] { color: #123456; }';
$valid_result = Breadcrumbs::validate_custom_css( $valid_css );
if ( empty( $valid_result["valid"] ) || $valid_css !== $valid_result["css"] ) {
    fwrite( STDERR, "FAIL: Valid scoped breadcrumb CSS was rejected.
" );
    exit( 1 );
}
$band_css = 'body .smpi-breadcrumbs-band { background: #123456; }';
$band_result = Breadcrumbs::validate_custom_css( $band_css );
if ( empty( $band_result["valid"] ) || $band_css !== $band_result["css"] ) {
    fwrite( STDERR, "FAIL: Valid breadcrumb band CSS was rejected.\n" );
    exit( 1 );
}
$invalid_result = Breadcrumbs::validate_custom_css( 'body .unscoped-component { color: red; }' );
if ( ! empty( $invalid_result["valid"] ) ) {
    fwrite( STDERR, "FAIL: Unscoped breadcrumb CSS was accepted.
" );
    exit( 1 );
}
$settings = Settings::update( [ "breadcrumbs_css_override" => $valid_css ] );
if ( $valid_css !== $settings["breadcrumbs_css_override"] ) {
    fwrite( STDERR, "FAIL: Valid breadcrumb CSS was not saved intact.
" );
    exit( 1 );
}

restore_error_handler();
echo "PASS: Settings colors, template sanitization, and targeted settings migrations.\n";
