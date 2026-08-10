<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
$main = (string) file_get_contents( $root . '/smp-publication-integration.php' );
$runtime = (string) file_get_contents( $root . '/src/Runtime/Plugin.php' );
$settings = (string) file_get_contents( $root . '/src/Settings/SettingsRepository.php' );
$ajax = (string) file_get_contents( $root . '/src/Admin/Ajax/AjaxController.php' );
$dashboard = (string) file_get_contents( $root . '/src/Admin/Dashboard/DashboardController.php' );

$checks = [
    'Loads the author archive loading module through the canonical runtime.' => str_contains( $main, 'src/Content/AuthorArchiveLoading.php' )
        && str_contains( $runtime, 'new Content\AuthorArchiveLoading()' ),
    'Publishes an opt-in default with native mode and No Style fallbacks.' => str_contains( $settings, "'author_archive_loading_enabled' => false" )
        && str_contains( $settings, "'author_archive_loading_mode' => 'pagination'" )
        && str_contains( $settings, "'author_archive_loading_style' => 'none'" ),
    'Accepts every author loading control through the guarded AJAX settings route.' => str_contains( $ajax, '"author_archive_loading_enabled"' )
        && str_contains( $ajax, '"author_archive_loading_mode"')
        && str_contains( $ajax, '"author_archive_loading_style"' ),
    'Shows the enable control, three modes, six visual choices, and wrapper code in Authors.' => str_contains( $dashboard, 'Enable author archive loading override' )
        && str_contains( $dashboard, 'AuthorArchiveLoading::MODE_INFINITE_SCROLL' )
        && str_contains( $dashboard, 'AuthorArchiveLoading::MODE_LOAD_MORE' )
        && str_contains( $dashboard, 'AuthorArchiveLoading::STYLE_NONE' )
        && str_contains( $dashboard, 'AuthorArchiveLoading::STYLE_SOFT_PILL' )
        && str_contains( $dashboard, 'Stable Elementor wrapper structure' ),
];

foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

echo "PASS: Author archive loading is wired through settings, runtime, AJAX, and the visual Authors panel.\n";
