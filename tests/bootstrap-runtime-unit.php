<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );

define( 'ABSPATH', '/tmp/smpi-wordpress/' );
define( 'WP_PLUGIN_DIR', dirname( $root ) );

$GLOBALS['smpi_test_hooks'] = [];
$GLOBALS['smpi_test_actions_ran'] = [];
$GLOBALS['smpi_test_shortcodes'] = [];

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
    $GLOBALS['smpi_test_hooks'][ $hook ][ $priority ][] = [ $callback, $accepted_args ];
}
function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
    add_action( $hook, $callback, $priority, $accepted_args );
}
function do_action( string $hook, mixed ...$args ): void {
    $GLOBALS['smpi_test_actions_ran'][ $hook ] = ( $GLOBALS['smpi_test_actions_ran'][ $hook ] ?? 0 ) + 1;
    $priorities = $GLOBALS['smpi_test_hooks'][ $hook ] ?? [];
    ksort( $priorities, SORT_NUMERIC );
    foreach ( $priorities as $callbacks ) {
        foreach ( $callbacks as [ $callback, $accepted_args ] ) {
            call_user_func_array( $callback, array_slice( $args, 0, $accepted_args ) );
        }
    }
}
function did_action( string $hook ): int { return (int) ( $GLOBALS['smpi_test_actions_ran'][ $hook ] ?? 0 ); }
function add_shortcode( string $tag, callable $callback ): void { $GLOBALS['smpi_test_shortcodes'][ $tag ] = $callback; }
function register_activation_hook( string $file, callable $callback ): void { unset( $file, $callback ); }
function is_admin(): bool { return true; }
function wp_doing_ajax(): bool { return false; }
function wp_doing_cron(): bool { return false; }
function is_plugin_active( string $plugin_file ): bool { return true; }
function plugin_basename( string $file ): string { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function plugin_dir_url( string $file ): string { return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/'; }
function admin_url( string $path = '' ): string { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function get_plugin_data( string $file, bool $markup = true, bool $translate = true ): array {
    unset( $file, $markup, $translate );
    return [
        'Name'        => 'SMP Publication Integration',
        'Version'     => '2.0.0',
        'Author'      => 'Michael Peres',
        'PluginURI'   => 'https://github.com/mikeyperes/smp-publication-integration',
        'Description' => 'Publication Integration',
    ];
}
function sanitize_key( mixed $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: ''; }
function sanitize_text_field( mixed $value ): string { return trim( strip_tags( (string) $value ) ); }
function wp_unslash( mixed $value ): mixed { return $value; }
function get_option( string $key, mixed $default = false ): mixed { unset( $key ); return $default; }
function post_type_exists( string $post_type ): bool { return in_array( $post_type, [ 'post', 'page', 'press-release' ], true ); }
function is_multisite(): bool { return false; }

require $root . '/smp-publication-integration.php';
do_action( 'plugins_loaded' );

$checks = [
    'Core lifecycle hook ran.' => did_action( 'smpi_core_booted' ) === 1,
    'Updater hooks were registered through CoreBootstrap.' => ! empty( $GLOBALS['smpi_test_hooks']['pre_set_site_transient_update_plugins'] ),
    'Core content and ACF modules scheduled their registrations.' => ! empty( $GLOBALS['smpi_test_hooks']['init'] )
        && ! empty( $GLOBALS['smpi_test_hooks']['acf/init'] ),
    'Core-backed domain AJAX actions were registered.' => ! empty( $GLOBALS['smpi_test_hooks']['wp_ajax_smpi_post_hygiene_preview'] )
        && ! empty( $GLOBALS['smpi_test_hooks']['wp_ajax_smpi_going_live_checklist_status'] )
        && ! empty( $GLOBALS['smpi_test_hooks']['wp_ajax_smpi_generate_content'] ),
];

foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

echo "PASS: SMP Publication boots one real Core lifecycle and Core-backed domain AJAX modules.\n";
