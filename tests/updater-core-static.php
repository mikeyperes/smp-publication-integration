<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
$main = (string) file_get_contents( $root . '/smp-publication-integration.php' );
$legacy = (string) file_get_contents( $root . '/initialization.php' );
$readme = (string) file_get_contents( $root . '/README.md' );
$core_version = trim( (string) file_get_contents( $root . '/lib/hexa-wordpress-plugin-core/VERSION' ) );

$checks = [
    'Keeps every plugin version surface on 1.0.24.' => str_contains( $main, 'Version: 1.0.24' )
        && str_contains( $main, 'public const VERSION = "1.0.24";' )
        && str_contains( $legacy, 'Version: 1.0.24' )
        && str_contains( $readme, '- Version: `1.0.24`' ),
    'Publishes the PHP 8.1 requirement through every release surface.' => str_contains( $main, 'Requires PHP: 8.1' )
        && str_contains( $main, "'requires_php'              => '8.1'" )
        && str_contains( $legacy, 'Requires PHP: 8.1' )
        && str_contains( $readme, '| PHP | 8.1 |' ),
    'Ships the documented Hexa WP Core 1.2.0 bundle.' => '1.2.0' === $core_version
        && str_contains( $readme, '| Hexa WP Core bundle | 1.2.0 |' )
        && str_contains( $main, "'minimum_version' => trim( (string) file_get_contents( \$hexa_plugin_core_root . '/VERSION' ) )" ),
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
