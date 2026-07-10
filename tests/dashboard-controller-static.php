<?php

declare(strict_types=1);

$controller_file = dirname( __DIR__ ) . '/src/Admin/Dashboard/DashboardController.php';
$controller      = file_get_contents( $controller_file );

if ( ! is_string( $controller ) ) {
    fwrite( STDERR, "FAIL: Dashboard controller source could not be read.\n" );
    exit( 1 );
}

if ( ! str_contains( $controller, 'use Hexa\\PluginCore\\SnippetRegistry\\SnippetsTableRenderer;' ) ) {
    fwrite( STDERR, "FAIL: Dashboard controller must import the shared SnippetsTableRenderer.\n" );
    exit( 1 );
}

if ( str_contains( $controller, 'use Hexa\\PluginCore\\SnippetRegistry\\SnippetRenderer;' ) ) {
    fwrite( STDERR, "FAIL: Dashboard controller retains the obsolete SnippetRenderer import.\n" );
    exit( 1 );
}

$bootstrap = file_get_contents( dirname( __DIR__ ) . '/src/Bootstrap/Plugin.php' );
if ( ! is_string( $bootstrap ) || ! str_contains( $bootstrap, "'tab_id'        => 'hexa_core'" ) ) {
    fwrite( STDERR, "FAIL: Shared Core tab module must use SMP's canonical hexa_core section ID.\n" );
    exit( 1 );
}

if ( ! str_contains( $controller, "plugins_url( 'assets/admin/dashboard.css', dirname( __DIR__, 3 )" ) ) {
    fwrite( STDERR, "FAIL: Dashboard stylesheet URL must resolve from the plugin root.\n" );
    exit( 1 );
}

$dashboard_css = dirname( __DIR__ ) . '/assets/admin/dashboard.css';
if ( ! is_readable( $dashboard_css ) || ! str_contains( (string) file_get_contents( $dashboard_css ), '.smpi-table-scroll' ) ) {
    fwrite( STDERR, "FAIL: Responsive dashboard stylesheet is missing table containment rules.\n" );
    exit( 1 );
}

foreach ( [ '_smpi_shadow_complete', '_smpi_pr_shadow_override' ] as $field_name ) {
    $expected = '\'get_field("' . $field_name . '", $post_id)\'';
    if ( ! str_contains( $controller, $expected ) ) {
        fwrite( STDERR, "FAIL: Literal post ID code example is not safely quoted for {$field_name}.\n" );
        exit( 1 );
    }
}

echo "PASS: Dashboard renderer resolution and literal code examples are stable.\n";
