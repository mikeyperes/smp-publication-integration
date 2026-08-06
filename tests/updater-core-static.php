<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
$main = (string) file_get_contents( $root . '/smp-publication-integration.php' );
$legacy = (string) file_get_contents( $root . '/initialization.php' );
$readme = (string) file_get_contents( $root . '/README.md' );
$runtime = (string) file_get_contents( $root . '/src/Runtime/Plugin.php' );
$updates = (string) file_get_contents( $root . '/src/Infrastructure/Updates.php' );
$core_version = trim( (string) file_get_contents( $root . '/lib/hexa-wordpress-plugin-core/VERSION' ) );

$checks = [
    'Keeps every plugin version surface on 2.0.3.' => str_contains( $main, 'Version: 2.0.3' )
        && str_contains( $main, 'public const VERSION = "2.0.3";' )
        && str_contains( $legacy, 'Version: 2.0.3' )
        && str_contains( $readme, '- Version: `2.0.3`' ),
    'Publishes the PHP 8.1 requirement through every release surface.' => str_contains( $main, 'Requires PHP: 8.1' )
        && str_contains( $updates, "'requires_php'              => '8.1'" )
        && str_contains( $legacy, 'Requires PHP: 8.1' )
        && str_contains( $readme, '| PHP | 8.1 |' ),
    'Ships the documented Hexa WP Core 3.0.1 bundle.' => '3.0.1' === $core_version
        && str_contains( $readme, '| Hexa WP Core bundle | 3.0.1 |' )
        && str_contains( $main, "'minimum_version' => trim( (string) file_get_contents( \$hexa_plugin_core_root . '/VERSION' ) )" ),
    'Targets the canonical main GitHub branch.' => str_contains( $main, 'GitHub Branch: main' )
        && str_contains( $main, "public static string \$github_branch = 'main';" ),
    'Registers the Hexa WP Core updater through the single CoreBootstrap lifecycle.' => str_contains( $runtime, 'new GitHubPluginUpdater( Updates::plugin_config() )' )
        && ! str_contains( $main, 'boot_github_updater' ),
    'Keeps legacy updater access as a thin compatibility facade.' => str_contains( $main, 'Updates::plugin_config()' )
        && str_contains( $updates, 'UpdaterConfig::from_plugin_file' ),
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
