<?php

declare( strict_types=1 );

$root       = dirname( __DIR__ );
$main       = (string) file_get_contents( $root . '/smp-publication-integration.php' );
$runtime    = (string) file_get_contents( $root . '/src/Runtime/Plugin.php' );
$legacy     = (string) file_get_contents( $root . '/src/Bootstrap/Plugin.php' );
$autoloader = (string) file_get_contents( $root . '/src/Support/Autoloader.php' );
$generation = (string) file_get_contents( $root . '/src/Content/ContentGeneration.php' );
$hygiene    = (string) file_get_contents( $root . '/src/Content/PostHygiene.php' );
$checklist  = (string) file_get_contents( $root . '/src/Content/GoingLiveChecklist.php' );

$checks = [
    'The host runtime uses a properly cased plugin namespace.' => str_contains( $runtime, 'namespace SMP\\PublicationIntegration\\Runtime;' ),
    'The host creates one PluginContext and one CoreBootstrap.' => 1 === substr_count( $runtime, 'new PluginContext(' )
        && 1 === substr_count( $runtime, 'new CoreBootstrap(' ),
    'The updater participates in the CoreBootstrap module lifecycle.' => str_contains( $runtime, 'add_module( new GitHubPluginUpdater' )
        && ! str_contains( $main, 'boot_github_updater' ),
    'Content types and ACF groups remain Core modules.' => str_contains( $runtime, 'PublicationContentTypes::content_types()' )
        && str_contains( $runtime, 'PublicationContentTypes::acf_groups()' ),
    'Legacy register-only modules use the shared Core adapter.' => str_contains( $runtime, 'CoreContracts\\RegisterMethodModule' )
        && ! is_file( $root . '/src/Bootstrap/ModuleAdapter.php' ),
    'The old Bootstrap class is only a compatibility facade.' => str_contains( $legacy, 'Legacy class name retained' )
        && str_contains( $legacy, '$this->runtime->boot();' )
        && ! str_contains( $legacy, 'new CoreBootstrap(' ),
    'Both the proper and legacy host namespaces autoload from src.' => str_contains( $autoloader, "'SMP\\\\PublicationIntegration\\\\'" )
        && str_contains( $autoloader, "'smp_publication_integration\\\\'" ),
    'The obsolete snippets renderer facade was removed.' => ! is_file( $root . '/src/Admin/SnippetsTableRenderer.php' ),
    'Domain admin AJAX uses the shared action registry.' => str_contains( $generation, 'new AjaxActionRegistry(' )
        && str_contains( $hygiene, 'new AjaxActionRegistry(' )
        && str_contains( $checklist, 'new AjaxActionRegistry(' )
        && ! str_contains( $generation . $hygiene . $checklist, 'add_action( "wp_ajax_' )
        && ! str_contains( $generation . $hygiene . $checklist, 'check_ajax_referer(' ),
];

foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

echo "PASS: SMP Publication uses one namespaced CoreBootstrap lifecycle with thin legacy facades.\n";
