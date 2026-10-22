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
    'overview' => 'Dashboard', 'quick_run' => 'Quick Start',
    'publication_options' => 'Publication Settings', 'pages' => 'Publication Pages', 'menu' => 'Menus',
    'founders_list' => 'Founders', 'executive_leadership' => 'Executive Leadership', 'staff' => 'Staff', 'contributors' => 'Contributors', 'multiple_authors' => 'Authors',
    'custom_post_types' => 'Custom Post Types', 'features' => 'Article Design', 'custom_fields' => 'Post Fields', 'content_generation' => 'Content Generation', 'snippets' => 'Publishing Rules', 'post_hygiene' => 'Post HTML Cleanup',
    'schema' => 'Schema Settings', 'reports' => 'Schema Tests',
    'article_cleanup' => 'Article & Media Cleanup', 'plugins' => 'Plugins', 'shortcodes' => 'Shortcodes', 'update-center' => 'Update Center', 'hexa_core' => 'Hexa WP Core',
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

$areas = [
    'overview' => 'overview', 'quick_run' => 'overview',
    'publication_options' => 'publication', 'pages' => 'publication', 'menu' => 'publication',
    'founders_list' => 'people', 'executive_leadership' => 'people', 'staff' => 'people', 'contributors' => 'people', 'multiple_authors' => 'people',
    'custom_post_types' => 'articles', 'features' => 'articles', 'custom_fields' => 'articles', 'content_generation' => 'articles', 'snippets' => 'articles', 'post_hygiene' => 'articles',
    'schema' => 'search_schema', 'reports' => 'search_schema',
    'article_cleanup' => 'maintenance', 'plugins' => 'maintenance', 'shortcodes' => 'maintenance', 'update-center' => 'maintenance', 'hexa_core' => 'maintenance',
];
foreach ( $areas as $section => $area ) {
    $route = $navigation->resolve( $section );
    if ( $section !== $route->section() || $area !== $route->area() ) {
        fwrite( STDERR, "FAIL: {$section} is not in the {$area} group.\n" );
        exit( 1 );
    }
}

$moved = [ 'founder_profiles' => 'founders_list', 'founding_date' => 'pages', 'integrations' => 'plugins', 'verified_profiles' => 'plugins', 'profiles' => 'publication_options', 'brand' => 'publication_options', 'optimization' => 'plugins', 'ui_cleanup' => 'overview' ];
foreach ( $moved as $old => $new ) {
    if ( $new !== $navigation->resolve( $old )->section() ) {
        fwrite( STDERR, "FAIL: old tab {$old} must open {$new}.\n" );
        exit( 1 );
    }
}

$groups = $navigation->groups();
if ( [ 'Overview', 'Publication', 'People', 'Articles', 'Search & Schema', 'Maintenance' ] !== array_slice( array_column( $groups, 'label' ), 0, 6 ) ) {
    fwrite( STDERR, "FAIL: the six sidebar groups are wrong.\n" );
    exit( 1 );
}

if ( 'hexa_core' !== $navigation->resolve( 'hexa-core' )->section() || 'extension_diagnostics' !== $navigation->resolve( 'extension_diagnostics' )->section() ) {
    fwrite( STDERR, "FAIL: hexa-core and extension tabs must stay routable.\n" );
    exit( 1 );
}

echo "PASS: " . count( $expected_tabs ) . " tabs in six groups use the Core registry; removed tabs redirect.\n";
