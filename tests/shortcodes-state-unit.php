<?php

declare(strict_types=1);
require_once __DIR__ . '/fixtures/dashboard-source.php';

$root = dirname( __DIR__ );
$dashboard = smpi_test_dashboard_source( $root );
$core = (string) file_get_contents( $root . "/lib/hexa-wordpress-plugin-core/src/WpAdminComponents/CoreUi.php" );

$checks = [
    "Shortcode parent cards default to collapsed" => false !== strpos( $dashboard, 'class=\"smpi-sc-card\" data-ctx=' )
        && false === strpos( $dashboard, 'class=\"smpi-sc-card\" data-ctx=\"" . esc_attr( (string) $ctx["key"] ) . "\" open' ),
    "Shortcode parent cards expose Core query keys" => false !== strpos( $dashboard, 'data-hpc-query-key=\"" . esc_attr( $query_key )' ),
    "AJAX-refreshed shortcode cards reinitialize Core state" => false !== strpos( $dashboard, "hexaPluginCoreInitPersistentDetails(box.get(0))" ),
    "Core owns hpc_open URL state" => false !== strpos( $core, "var detailsQueryParam = 'hpc_open'" )
        && false !== strpos( $core, "history.replaceState" ),
];

foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$label}.\n" );
        exit( 1 );
    }
}

echo "PASS: Shortcode parent cards default closed and preserve open state through Hexa WP Core URL state.\n";
