<?php

declare(strict_types=1);

$root       = dirname( __DIR__ );
$settings   = (string) file_get_contents( $root . '/src/Settings/SettingsRepository.php' );
$ajax       = (string) file_get_contents( $root . '/src/Admin/Ajax/AjaxController.php' );
$quick      = (string) file_get_contents( $root . '/src/Support/QuickStartFeatures.php' );
$muckrack   = (string) file_get_contents( $root . '/src/Content/MuckRackVerification.php' );
$dashboard  = (string) file_get_contents( $root . '/src/Admin/Dashboard/DashboardController.php' );
$bootstrap  = (string) file_get_contents( $root . '/src/Bootstrap/Plugin.php' );

$checks = [
    'MuckRack defaults to a 16px checkmark.' => str_contains( $settings, '"muckrack_icon_size" => 16' )
        && str_contains( $muckrack, '"muckrack_icon_size", 16, 8, 64' )
        && str_contains( $ajax, '"muckrack_icon_size" => [ 8, 64, 16 ]' ),
    'Quick Start inherits the 16px MuckRack default in every context.' => str_contains( $quick, '"muckrack_icon_size" => 16' )
        && str_contains( $quick, '"muckrack_icon_size_loop_cards" => 0' )
        && str_contains( $quick, '"Default icon size", "value" => "16px"' ),
    'Elementor primary category is enabled and excludes the default category.' => str_contains( $settings, "'elementor_primary_category_enabled' => true" )
        && str_contains( $settings, "'elementor_primary_category_exclude_default' => true" )
        && str_contains( $ajax, '"elementor_primary_category_enabled"' )
        && str_contains( $bootstrap, 'new Content\\ElementorPrimaryCategory()' ),
    'Feature activity logs use the collapsed Hexa Core renderer.' => str_contains( $dashboard, 'new ActivityLogRenderer( $config )' )
        && str_contains( $dashboard, "'collapsed'   => true" )
        && ! str_contains( $dashboard, '$html = "<ul>"' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: Editorial defaults, Elementor category output, and Core activity logs use the required contracts.\n";
