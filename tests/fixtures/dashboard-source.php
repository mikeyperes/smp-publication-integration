<?php
/** The admin page's source as one string: the controller plus its per-tab files. */
function smpi_test_dashboard_source( string $root ): string {
    $source = (string) file_get_contents( $root . '/src/Admin/Dashboard/DashboardController.php' );
    foreach ( glob( $root . '/src/Admin/Dashboard/Tabs/*.php' ) ?: [] as $file ) {
        $source .= "\n" . (string) file_get_contents( $file );
    }
    return $source;
}

/*
 * Tests load the plugin's classes the way the plugin does (src/ by namespace),
 * so a test that needs a class it did not require by hand still finds it.
 * Registered last, after any hand-written test doubles.
 */
spl_autoload_register(
    static function ( string $class_name ): void {
        foreach ( [ 'smp_publication_integration\\', 'SMP\\PublicationIntegration\\' ] as $prefix ) {
            if ( 0 === strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
                $path = dirname( __DIR__, 2 ) . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
                if ( is_readable( $path ) ) {
                    require_once $path;
                }
                return;
            }
        }
    }
);
