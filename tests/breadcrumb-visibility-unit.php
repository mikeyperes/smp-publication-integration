<?php

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['smpi_test_options'] = [];
$GLOBALS['smpi_test_route'] = [
    'front'    => false,
    'home'     => false,
    'category' => false,
    'tag'      => false,
    'singular' => true,
];
$GLOBALS['smpi_test_post'] = new WP_Post( 42, 'post' );

class WP_Post {
    public function __construct( public int $ID, public string $post_type ) {}
}

class WP_Term {}

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
function is_admin(): bool { return false; }
function wp_doing_ajax(): bool { return false; }
function wp_doing_cron(): bool { return false; }
function is_feed(): bool { return false; }
function is_embed(): bool { return false; }
function is_front_page(): bool { return (bool) $GLOBALS['smpi_test_route']['front']; }
function is_home(): bool { return (bool) $GLOBALS['smpi_test_route']['home']; }
function is_category(): bool { return (bool) $GLOBALS['smpi_test_route']['category']; }
function is_tag(): bool { return (bool) $GLOBALS['smpi_test_route']['tag']; }
function is_singular( mixed $post_type = '' ): bool {
    if ( ! $GLOBALS['smpi_test_route']['singular'] ) {
        return false;
    }
    return '' === $post_type || $post_type === $GLOBALS['smpi_test_post']->post_type;
}
function get_post(): ?WP_Post { return $GLOBALS['smpi_test_post']; }
function get_post_type( mixed $post = null ): string { return $post instanceof WP_Post ? $post->post_type : $GLOBALS['smpi_test_post']->post_type; }

require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/ActivityLog/ActivityLogConfig.php';
require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/ActivityLog/ActivityLogEntry.php';
require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/ActivityLog/ActivityLogger.php';
require dirname( __DIR__ ) . '/src/Settings/SettingsRepository.php';
require dirname( __DIR__ ) . '/src/Support/Settings.php';
require dirname( __DIR__ ) . '/src/Support/RuntimeContext.php';
require dirname( __DIR__ ) . '/src/Support/Fields.php';
require dirname( __DIR__ ) . '/src/Content/Breadcrumbs.php';

use smp_publication_integration\Content\Breadcrumbs;
use smp_publication_integration\Support\Settings;

$checks = [];

$checks['The single-post visibility option defaults off.'] =
    false === Settings::defaults()['breadcrumbs_hide_single_posts'];

$settings = Settings::update( [ 'breadcrumbs_hide_single_posts' => true ] );
$checks['The single-post visibility option is sanitized and persisted as a boolean.'] =
    true === $settings['breadcrumbs_hide_single_posts']
    && true === Settings::bool( 'breadcrumbs_hide_single_posts' );

$checks['Enabled single-post hiding suppresses standard post breadcrumbs.'] =
    false === Breadcrumbs::should_render();

$GLOBALS['smpi_test_post'] = new WP_Post( 43, 'page' );
$checks['Single-post hiding does not suppress pages.'] =
    true === Breadcrumbs::should_render();

Settings::update( [ 'breadcrumbs_hide_single_posts' => false ] );
$GLOBALS['smpi_test_post'] = new WP_Post( 44, 'post' );
$checks['Disabling single-post hiding restores standard post breadcrumbs.'] =
    true === Breadcrumbs::should_render();

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: Breadcrumb single-post visibility is persisted, scoped, and reversible.\n";
