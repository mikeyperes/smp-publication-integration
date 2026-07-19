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

restore_error_handler();
echo "PASS: Settings color updates and targeted heading preset migration.\n";
