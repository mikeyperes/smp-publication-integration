<?php

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$GLOBALS['smpi_test_options'] = [];
$GLOBALS['smpi_test_actions'] = [];
$GLOBALS['smpi_test_option_reads'] = [];
$GLOBALS['smpi_test_blog_id'] = 1;

function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
    $GLOBALS['smpi_test_actions'][ $hook ][ $priority ][] = [ $callback, $accepted_args ];
}
function do_action( string $hook, ...$args ): void {
    $callbacks = $GLOBALS['smpi_test_actions'][ $hook ] ?? [];
    ksort( $callbacks );
    foreach ( $callbacks as $priority_callbacks ) {
        foreach ( $priority_callbacks as [ $callback, $accepted_args ] ) {
            $callback( ...array_slice( $args, 0, $accepted_args ) );
        }
    }
}
function get_option( string $key, mixed $default = false ): mixed {
    $GLOBALS['smpi_test_option_reads'][ $key ] = (int) ( $GLOBALS['smpi_test_option_reads'][ $key ] ?? 0 ) + 1;
    return $GLOBALS['smpi_test_options'][ $key ] ?? $default;
}
function get_current_blog_id(): int { return (int) $GLOBALS['smpi_test_blog_id']; }
function update_option( string $key, mixed $value, bool $autoload = true ): bool {
    $exists = array_key_exists( $key, $GLOBALS['smpi_test_options'] );
    $old = $GLOBALS['smpi_test_options'][ $key ] ?? null;
    $GLOBALS['smpi_test_options'][ $key ] = $value;
    if ( $exists ) {
        do_action( 'updated_option', $key, $old, $value );
    } else {
        do_action( 'added_option', $key, $value );
    }
    return true;
}
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
require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/BrandColors/BrandColorProvider.php';
require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/BrandColors/FontFamilyProvider.php';
require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/BrandColors/FontWeightProvider.php';
require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/BrandColors/TemplateColorResolver.php';
require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/Typography/TypographyPreservation.php';
require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/Typography/TemplateTypography.php';
require dirname( __DIR__ ) . '/src/Design/TemplateDesignRegistry.php';
require dirname( __DIR__ ) . '/src/Design/TemplateBackground.php';
require dirname( __DIR__ ) . '/src/Settings/SettingsRepository.php';
require dirname( __DIR__ ) . '/src/Settings/SettingsMigrations.php';
require dirname( __DIR__ ) . '/src/Support/Settings.php';
require dirname( __DIR__ ) . '/src/Content/Breadcrumbs.php';

use smp_publication_integration\Content\Breadcrumbs;
use smp_publication_integration\Settings\SettingsMigrations;
use smp_publication_integration\Settings\SettingsRepository;
use smp_publication_integration\Support\Settings;

set_error_handler(
    static function( int $severity, string $message, string $file, int $line ): never {
        throw new ErrorException( $message, 0, $severity, $file, $line );
    }
);

SettingsRepository::invalidate_cache();
$GLOBALS['smpi_test_option_reads'][ SettingsRepository::OPTION ] = 0;
SettingsRepository::all();
$first_settings_read_count = $GLOBALS['smpi_test_option_reads'][ SettingsRepository::OPTION ];
SettingsRepository::all();
if ( 1 !== $first_settings_read_count || 1 !== $GLOBALS['smpi_test_option_reads'][ SettingsRepository::OPTION ] ) {
    fwrite( STDERR, "FAIL: Repeated settings reads were not served from the request cache.\n" );
    exit( 1 );
}
if ( 1 !== count( $GLOBALS['smpi_test_actions']['updated_option'][10] ?? [] ) ) {
    fwrite( STDERR, "FAIL: Settings cache invalidation hooks were not registered exactly once.\n" );
    exit( 1 );
}
if ( 1 !== count( $GLOBALS['smpi_test_actions']['switch_blog'][10] ?? [] ) ) {
    fwrite( STDERR, "FAIL: Multisite settings cache invalidation was not registered exactly once.\n" );
    exit( 1 );
}

update_option( 'unrelated_option', 'value', false );
SettingsRepository::all();
if ( 1 !== $GLOBALS['smpi_test_option_reads'][ SettingsRepository::OPTION ] ) {
    fwrite( STDERR, "FAIL: An unrelated option update invalidated the SMP settings cache.\n" );
    exit( 1 );
}

update_option( SettingsRepository::OPTION, [ 'shadow_posts_enabled' => false ], false );
if ( SettingsRepository::bool( 'shadow_posts_enabled' ) || 2 !== $GLOBALS['smpi_test_option_reads'][ SettingsRepository::OPTION ] ) {
    fwrite( STDERR, "FAIL: updated_option did not invalidate the request settings cache.\n" );
    exit( 1 );
}

$before_repository_save_read = $GLOBALS['smpi_test_option_reads'][ SettingsRepository::OPTION ];
SettingsRepository::update( [ 'shadow_posts_enabled' => true ] );
if ( ! SettingsRepository::bool( 'shadow_posts_enabled' ) || $before_repository_save_read + 1 !== $GLOBALS['smpi_test_option_reads'][ SettingsRepository::OPTION ] ) {
    fwrite( STDERR, "FAIL: Repository saves did not invalidate and refresh the request settings cache.\n" );
    exit( 1 );
}

$before_blog_switch_reads = $GLOBALS['smpi_test_option_reads'][ SettingsRepository::OPTION ];
$GLOBALS['smpi_test_options'][ SettingsRepository::OPTION ] = [ 'shadow_posts_enabled' => false ];
$GLOBALS['smpi_test_blog_id'] = 2;
do_action( 'switch_blog', 2, 1, 'switch' );
if ( SettingsRepository::bool( 'shadow_posts_enabled' ) || $before_blog_switch_reads + 1 !== $GLOBALS['smpi_test_option_reads'][ SettingsRepository::OPTION ] ) {
    fwrite( STDERR, "FAIL: A switched blog reused another site's request settings cache.\n" );
    exit( 1 );
}

$GLOBALS['smpi_test_blog_id'] = 1;
do_action( 'switch_blog', 1, 2, 'restore' );
if ( SettingsRepository::bool( 'shadow_posts_enabled' ) || $before_blog_switch_reads + 2 !== $GLOBALS['smpi_test_option_reads'][ SettingsRepository::OPTION ] ) {
    fwrite( STDERR, "FAIL: Restoring a blog reused its pre-switch request settings cache.\n" );
    exit( 1 );
}

update_option( SettingsRepository::OPTION, [], false );
SettingsRepository::invalidate_cache();

$GLOBALS['smpi_test_options']['hws_brand_primary_color'] = '#bd00ff';
if ( '#111111' !== SettingsRepository::color_default( 'article_drop_cap_color' ) ) {
    fwrite( STDERR, "FAIL: Drop-cap custom fallback did not retain its template-native color.\n" );
    exit( 1 );
}
if ( '#2563eb' !== SettingsRepository::color_default( 'post_summary_accent_color' ) ) {
    fwrite( STDERR, "FAIL: Summary custom fallback did not retain its template-native color.\n" );
    exit( 1 );
}
if ( '#bd00ff' !== \smp_publication_integration\Design\TemplateDesignRegistry::effective_color( 'table_of_contents', [ 'table_of_contents_color_source' => 'site_primary', 'table_of_contents_style' => 'toc03' ] ) ) {
    fwrite( STDERR, "FAIL: Site Primary did not resolve through Hexa brand assets.\n" );
    exit( 1 );
}
unset( $GLOBALS['smpi_test_options']['hws_brand_primary_color'] );

$settings = Settings::update( [ 'article_heading_accent_color' => 'invalid' ] );
if ( '' !== $settings['article_heading_accent_color'] ) {
    fwrite( STDERR, "FAIL: Invalid custom color did not fall back to the selected template palette.\n" );
    exit( 1 );
}

$settings = Settings::update( [ 'article_heading_accent_color' => '#A1B2C3' ] );
if ( '#a1b2c3' !== $settings['article_heading_accent_color'] ) {
    fwrite( STDERR, "FAIL: Valid feature color was not normalized and saved.\n" );
    exit( 1 );
}

$settings = Settings::update( [ 'post_summary_accent_color' => '#A1B2C3' ] );
if ( '#a1b2c3' !== $settings['post_summary_accent_color'] ) {
    fwrite( STDERR, "FAIL: Summary design color was not normalized and saved.\n" );
    exit( 1 );
}

$settings = Settings::update( [
    'table_of_contents_style' => 'unstyled',
    'post_summary_style' => 'unstyled',
] );
if ( 'unstyled' !== $settings['table_of_contents_style'] || 'unstyled' !== $settings['post_summary_style'] ) {
    fwrite( STDERR, "FAIL: Elementor stripped component modes were not normalized and saved.\n" );
    exit( 1 );
}

$settings = Settings::update( [
    'post_summary_background_mode' => 'custom',
    'post_summary_background_color' => '#F0E1D2',
] );
if ( 'custom' !== $settings['post_summary_background_mode'] || '#f0e1d2' !== $settings['post_summary_background_color'] ) {
    fwrite( STDERR, "FAIL: Custom Summary background mode and color were not normalized and saved.\n" );
    exit( 1 );
}

$settings = Settings::update( [ 'post_summary_background_mode' => 'none' ] );
if ( 'none' !== $settings['post_summary_background_mode'] ) {
    fwrite( STDERR, "FAIL: Transparent Summary background mode was not saved.\n" );
    exit( 1 );
}

$settings = Settings::update( [
    'post_summary_background_mode' => 'invalid',
    'post_summary_background_color' => 'invalid',
] );
if ( 'template' !== $settings['post_summary_background_mode'] || '#ffffff' !== $settings['post_summary_background_color'] ) {
    fwrite( STDERR, "FAIL: Invalid Summary background settings did not restore safe defaults.\n" );
    exit( 1 );
}

$settings = Settings::update( [ 'post_summary_placement' => 'above_content' ] );
if ( 'above_content' !== $settings['post_summary_placement'] ) {
    fwrite( STDERR, "FAIL: Valid Summary placement was not saved.\n" );
    exit( 1 );
}
$settings = Settings::update( [ 'post_summary_placement' => 'somewhere_else' ] );
if ( 'manual' !== $settings['post_summary_placement'] ) {
    fwrite( STDERR, "FAIL: Invalid Summary placement did not fall back to manual.\n" );
    exit( 1 );
}

$settings = Settings::update( [ 'post_faqs_placement' => 'below_author' ] );
if ( 'below_author' !== $settings['post_faqs_placement'] ) {
    fwrite( STDERR, "FAIL: Valid FAQ placement was not saved.\n" );
    exit( 1 );
}
$settings = Settings::update( [ 'post_faqs_placement' => 'somewhere_else' ] );
if ( 'manual' !== $settings['post_faqs_placement'] ) {
    fwrite( STDERR, "FAIL: Invalid FAQ placement did not fall back to manual.\n" );
    exit( 1 );
}

$settings = Settings::update( [ 'breadcrumbs_background_color' => 'invalid' ] );
if ( '' !== $settings[ 'breadcrumbs_background_color' ] ) {
    fwrite( STDERR, "FAIL: Invalid breadcrumb background did not restore the template background.\n" );
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

unset( $GLOBALS['smpi_test_options']['smpi_migration_author_listing_1_0_14'] );
$GLOBALS['smpi_test_options']['smpi_settings'] = [
    'press_release_include_enabled' => false,
    'press_release_include_contexts' => [],
];
( new SettingsMigrations() )->migrate_author_listing_settings();
if ( false !== $GLOBALS['smpi_test_options']['smpi_settings']['author_listing_show_press_releases'] ) {
    fwrite( STDERR, "FAIL: Author listing migration did not preserve an existing press-release exclusion.\n" );
    exit( 1 );
}

unset( $GLOBALS['smpi_test_options']['smpi_migration_author_listing_1_0_14'] );
$GLOBALS['smpi_test_options']['smpi_settings'] = [
    'press_release_include_enabled' => true,
    'press_release_include_contexts' => [ 'home', 'author' ],
];
( new SettingsMigrations() )->migrate_author_listing_settings();
if (
    true !== $GLOBALS['smpi_test_options']['smpi_settings']['author_listing_show_press_releases']
    || [ 'home' ] !== $GLOBALS['smpi_test_options']['smpi_settings']['press_release_include_contexts']
) {
    fwrite( STDERR, "FAIL: Author listing migration did not preserve an existing press-release inclusion.\n" );
    exit( 1 );
}

unset( $GLOBALS['smpi_test_options']['smpi_migration_author_listing_1_0_14'] );
$GLOBALS['smpi_test_options']['smpi_settings'] = [
    'press_release_include_enabled' => true,
    'press_release_include_contexts' => [ 'home', 'author' ],
    'author_listing_show_press_releases' => false,
];
( new SettingsMigrations() )->migrate_author_listing_settings();
if (
    false !== $GLOBALS['smpi_test_options']['smpi_settings']['author_listing_show_press_releases']
    || [ 'home' ] !== $GLOBALS['smpi_test_options']['smpi_settings']['press_release_include_contexts']
) {
    fwrite( STDERR, "FAIL: Author listing migration overwrote the explicit author setting or retained the retired context.\n" );
    exit( 1 );
}

$defaults = SettingsRepository::defaults();
if (
    false !== $defaults['author_listing_hide_without_articles']
    || false !== $defaults['author_listing_hide_without_featured_image']
    || true !== $defaults['author_listing_show_press_releases']
) {
    fwrite( STDERR, "FAIL: Author listing defaults do not preserve existing site output.\n" );
    exit( 1 );
}
$settings = Settings::update( [
    'author_listing_hide_without_articles' => true,
    'author_listing_hide_without_featured_image' => true,
    'author_listing_show_press_releases' => false,
] );
if (
    true !== $settings['author_listing_hide_without_articles']
    || true !== $settings['author_listing_hide_without_featured_image']
    || false !== $settings['author_listing_show_press_releases']
) {
    fwrite( STDERR, "FAIL: Author listing booleans were not saved.\n" );
    exit( 1 );
}

$defaults = SettingsRepository::defaults();
foreach ( \smp_publication_integration\Design\TemplateDesignRegistry::definitions() as $surface => $definition ) {
    if ( 'template_default' !== $defaults[ $definition['source_key'] ] ) {
        fwrite( STDERR, "FAIL: New installations must default every design color surface to Template Default.\n" );
        exit( 1 );
    }
}
foreach ( array_keys( \smp_publication_integration\Design\TemplateDesignRegistry::typography_preservation_surfaces() ) as $surface ) {
    if ( 'template_default' !== $defaults[ $surface . '_typography_mode' ] ) {
        fwrite( STDERR, "FAIL: New installations must default every typography surface to Template Default.\n" );
        exit( 1 );
    }
}
foreach ( [ 'font_family', 'font_size', 'font_color', 'font_weight' ] as $property ) {
    if ( true !== $defaults[ 'article_heading_preserve_' . $property ] || false !== $defaults[ 'article_drop_cap_preserve_' . $property ] ) {
        fwrite( STDERR, "FAIL: Core-generated typography preservation defaults are incorrect.\n" );
        exit( 1 );
    }
}
$settings = Settings::update( [
    'article_drop_cap_preserve_font_family' => true,
    'article_drop_cap_preserve_font_size' => true,
] );
if ( true !== $settings['article_drop_cap_preserve_font_family'] || true !== $settings['article_drop_cap_preserve_font_size'] ) {
    fwrite( STDERR, "FAIL: Drop-cap preservation settings were not saved.\n" );
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
