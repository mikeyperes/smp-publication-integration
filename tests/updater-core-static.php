<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
$main = (string) file_get_contents( $root . '/smp-publication-integration.php' );

$checks = [
    'Targets the canonical main GitHub branch.' => str_contains( $main, 'GitHub Branch: main' )
        && str_contains( $main, "public static string \$github_branch = 'main';" ),
    'Registers the Hexa WP Core updater directly.' => str_contains( $main, 'new \\Hexa\\PluginCore\\PluginUpdates\\GitHubPluginUpdater' )
        && str_contains( $main, 'hexa_plugin_core_updater_config()' ),
    'Does not load a legacy updater wrapper.' => ! str_contains( $main, "require_once __DIR__ . '/GitHub_Updater.php'" )
        && ! is_file( $root . '/GitHub_Updater.php' ),
];

foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

echo "PASS: SMP Publication uses the canonical main branch and Hexa WP Core updater.\n";
