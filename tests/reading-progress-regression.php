<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
$main = (string) file_get_contents( $root . '/smp-publication-integration.php' );
$bootstrap = (string) file_get_contents( $root . '/src/Bootstrap/Plugin.php' );
$settings = (string) file_get_contents( $root . '/src/Settings/SettingsRepository.php' );
$ajax = (string) file_get_contents( $root . '/src/Admin/Ajax/AjaxController.php' );
$dashboard = (string) file_get_contents( $root . '/src/Admin/Dashboard/DashboardController.php' );

$checks = [
    'SMP no longer loads or boots a reading-progress renderer.' => ! file_exists( $root . '/src/Content/ReadingProgress.php' )
        && ! str_contains( $main, 'Content/ReadingProgress.php' )
        && ! str_contains( $bootstrap, 'Content\\ReadingProgress' ),
    'Article Design no longer exposes duplicate reading-progress controls or previews.' => ! str_contains( $dashboard, 'Reading progress bar' )
        && ! str_contains( $dashboard, 'reading progress' )
        && ! str_contains( $dashboard, 'reading_progress_enabled' )
        && ! str_contains( $dashboard, 'smpi-reading-progress' ),
    'SMP no longer owns reading-progress defaults or validation.' => ! str_contains( $settings, 'reading_progress_' ),
    'SMP AJAX no longer accepts reading-progress settings.' => ! str_contains( $ajax, 'reading_progress_' ),
    'The current release keeps reading-progress ownership removed.' => str_contains( $main, 'Version: 1.0.26' )
        && str_contains( $main, 'public const VERSION = "1.0.26"' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: SMP Publication Integration no longer owns or renders reading progress.\n";
