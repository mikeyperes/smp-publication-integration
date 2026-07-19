<?php

declare(strict_types=1);

function sanitize_key( string $value ): string {
    return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ?: '' );
}

function apply_filters( string $hook, mixed $value, ...$arguments ): mixed {
    if ( 'smpi_dashboard_tabs' === $hook && is_array( $value ) ) {
        $value['extension_diagnostics'] = 'Extension Diagnostics';
    }

    return $value;
}

require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/WpAdminTabs/TabDefinition.php';
require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/WpAdminTabs/TabRegistry.php';
require dirname( __DIR__ ) . '/src/Admin/Navigation/AdminRoute.php';
require dirname( __DIR__ ) . '/src/Admin/Navigation/AdminNavigation.php';

use Hexa\PluginCore\WpAdminTabs\TabDefinition;
use Hexa\PluginCore\WpAdminTabs\TabRegistry;
use smp_publication_integration\Admin\Navigation\AdminNavigation;

$navigation = new AdminNavigation();
$expected_tabs = [
    'overview'            => 'Dashboard',
    'quick_run'           => 'Quick Start',
    'publication_options' => 'Publication Settings',
    'profiles'            => 'Publication Fields',
    'brand'               => 'Brand Settings',
    'pages'               => 'Pages',
    'menu'                => 'Menus',
    'features'            => 'Article Design',
    'custom_fields'       => 'Post Fields',
    'multiple_authors'    => 'Authors',
    'content_generation'  => 'Content Generation',
    'snippets'            => 'Publishing Rules',
    'post_hygiene'        => 'Post HTML Cleanup',
    'schema'              => 'Schema Settings',
    'reports'             => 'Schema Tests',
    'verified_profiles'   => 'Verified Profiles',
    'article_cleanup'     => 'Article & Media Cleanup',
    'optimization'        => 'Database Optimization',
    'plugins'             => 'Plugins',
    'integrations'        => 'Integrations',
    'ui_cleanup'          => 'WordPress Admin',
    'shortcodes'          => 'Shortcodes',
    'hexa_core'           => 'Hexa WP Core',
];

$tabs = $navigation->tabs();
$base_tabs = array_slice( $tabs, 0, count( $expected_tabs ), true );

if ( $expected_tabs !== $base_tabs ) {
    fwrite( STDERR, "FAIL: Admin navigation does not expose the exact ordered flat tab list.\n" );
    exit( 1 );
}

if (
    count( $expected_tabs ) + 1 !== count( $tabs )
    || 'Extension Diagnostics' !== ( $tabs['extension_diagnostics'] ?? null )
    || 'extension_diagnostics' !== array_key_last( $tabs )
) {
    fwrite( STDERR, "FAIL: Extension tabs must remain appended after the base tabs.\n" );
    exit( 1 );
}

$rendered = [];
$registry = $navigation->registry(
    static function ( string $id ) use ( &$rendered ): void {
        $rendered[] = $id;
    },
    'manage_options'
);
$definitions = $registry->all();

if ( ! $registry instanceof TabRegistry || array_keys( $tabs ) !== array_keys( $definitions ) ) {
    fwrite( STDERR, "FAIL: Every navigation tab must be registered in the Hexa WP Core TabRegistry.\n" );
    exit( 1 );
}

foreach ( $tabs as $id => $label ) {
    $definition = $definitions[ $id ] ?? null;
    if (
        ! $definition instanceof TabDefinition
        || $definition->id !== $id
        || $definition->label !== $label
        || 'manage_options' !== $definition->capability
        || ! is_callable( $definition->renderer )
    ) {
        fwrite( STDERR, "FAIL: {$id} is not a complete Hexa WP Core TabDefinition.\n" );
        exit( 1 );
    }
}

call_user_func( $definitions['features']->renderer );
if ( [ 'features' ] !== $rendered ) {
    fwrite( STDERR, "FAIL: Core tab definitions must invoke their registered SMP renderer.\n" );
    exit( 1 );
}

$legacy_areas = [
    'overview'            => 'overview',
    'quick_run'           => 'operations',
    'publication_options' => 'publication',
    'profiles'            => 'publication',
    'brand'               => 'publication',
    'pages'               => 'publication',
    'menu'                => 'publication',
    'features'            => 'editorial',
    'custom_fields'       => 'editorial',
    'multiple_authors'    => 'editorial',
    'content_generation'  => 'editorial',
    'snippets'            => 'advanced',
    'post_hygiene'        => 'editorial',
    'schema'              => 'structured_data',
    'reports'             => 'structured_data',
    'verified_profiles'   => 'structured_data',
    'article_cleanup'     => 'operations',
    'optimization'        => 'operations',
    'plugins'             => 'operations',
    'integrations'        => 'operations',
    'ui_cleanup'          => 'advanced',
    'shortcodes'          => 'advanced',
    'hexa_core'           => 'advanced',
];

foreach ( $legacy_areas as $section => $area ) {
    $flat_route = $navigation->resolve( $section );
    $legacy_route = $navigation->resolve( $area, $section );

    if (
        $section !== $flat_route->section()
        || $area !== $flat_route->area()
        || $flat_route->section() !== $legacy_route->section()
        || $flat_route->area() !== $legacy_route->area()
    ) {
        fwrite( STDERR, "FAIL: Flat and legacy routes differ for {$section}.\n" );
        exit( 1 );
    }
}

$legacy_core = $navigation->resolve( 'hexa-core' );
if ( 'advanced' !== $legacy_core->area() || 'hexa_core' !== $legacy_core->section() ) {
    fwrite( STDERR, "FAIL: hexa-core must continue to resolve to hexa_core.\n" );
    exit( 1 );
}

$extension = $navigation->resolve( 'extension_diagnostics' );
if ( 'advanced' !== $extension->area() || 'extension_diagnostics' !== $extension->section() ) {
    fwrite( STDERR, "FAIL: Extension tabs must remain routable.\n" );
    exit( 1 );
}

echo "PASS: 23 ordered tabs use the Core registry and preserve legacy routes.\n";
