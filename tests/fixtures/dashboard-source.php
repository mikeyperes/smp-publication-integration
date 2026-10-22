<?php
/** The admin page's source as one string: the controller plus its per-tab files. */
function smpi_test_dashboard_source( string $root ): string {
    $source = (string) file_get_contents( $root . '/src/Admin/Dashboard/DashboardController.php' );
    foreach ( glob( $root . '/src/Admin/Dashboard/Tabs/*.php' ) ?: [] as $file ) {
        $source .= "\n" . (string) file_get_contents( $file );
    }
    return $source;
}
