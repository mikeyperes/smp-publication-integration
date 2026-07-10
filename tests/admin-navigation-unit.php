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

require dirname( __DIR__ ) . '/src/Admin/Navigation/AdminRoute.php';
require dirname( __DIR__ ) . '/src/Admin/Navigation/AdminNavigation.php';

use smp_publication_integration\Admin\Navigation\AdminNavigation;

$navigation = new AdminNavigation();
$areas      = $navigation->areas();

if ( 6 !== count( $areas ) || array_keys( $areas ) !== [ 'overview', 'publication', 'editorial', 'structured_data', 'operations', 'advanced' ] ) {
    fwrite( STDERR, "FAIL: Admin navigation must expose exactly six ordered areas.\n" );
    exit( 1 );
}

$legacy = $navigation->resolve( 'features' );
if ( 'editorial' !== $legacy->area() || 'features' !== $legacy->section() ) {
    fwrite( STDERR, "FAIL: Legacy Features route did not resolve to Editorial.\n" );
    exit( 1 );
}

$modern = $navigation->resolve( 'publication', 'pages' );
if ( 'publication' !== $modern->area() || 'pages' !== $modern->section() ) {
    fwrite( STDERR, "FAIL: Publication Pages area route did not resolve.\n" );
    exit( 1 );
}

$legacy_core = $navigation->resolve( 'hexa-core' );
if ( 'advanced' !== $legacy_core->area() || 'hexa_core' !== $legacy_core->section() ) {
    fwrite( STDERR, "FAIL: Legacy Hexa Core route did not resolve to Advanced.\n" );
    exit( 1 );
}

$extension = $navigation->resolve( 'extension_diagnostics' );
if ( 'advanced' !== $extension->area() || 'extension_diagnostics' !== $extension->section() ) {
    fwrite( STDERR, "FAIL: Legacy extension tabs must remain reachable under Advanced.\n" );
    exit( 1 );
}

echo "PASS: Six-area navigation preserves legacy and extension routes.\n";
