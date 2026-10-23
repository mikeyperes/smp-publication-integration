<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$main = (string) file_get_contents( $root . '/smp-publication-integration.php' );
$bootstrap = (string) file_get_contents( $root . '/src/Runtime/Plugin.php' );
$article = (string) file_get_contents( $root . '/src/Content/ArticleStyles.php' );
$runtime = (string) file_get_contents( $root . '/src/Content/PublicDomRuntime.php' );
$javascript = (string) file_get_contents( $root . '/assets/frontend/public-dom.js' );

$checks = [
    'The public DOM runtime is loaded and bootstrapped as an owned module.' =>
        str_contains( $main, 'src/Content/PublicDomRuntime.php' )
        && str_contains( $bootstrap, 'new Content\\PublicDomRuntime()' ),
    'The runtime uses one external deferred frontend asset without inline localization.' =>
        str_contains( $runtime, "public const HANDLE = 'smpi-public-dom'" )
        && str_contains( $runtime, "assets/frontend/public-dom.js" )
        && str_contains( $runtime, "wp_script_add_data( self::HANDLE, 'strategy', 'defer' )" )
        && ! str_contains( $runtime, 'wp_add_inline_script' )
        && ! str_contains( $runtime, 'wp_localize_script' ),
    'SMP no longer prints or places breadcrumbs (HWS Base Tools owns them).' =>
        ! is_readable( $root . '/src/Content/Breadcrumbs.php' )
        && ! str_contains( $javascript, 'initializeBreadcrumbs' )
        && ! str_contains( $runtime, 'breadcrumbs_enabled' ),
    'Article server decoration runs after WordPress paragraph generation while its footer executable block stays retired.' =>
        str_contains( $article, 'add_filter( "the_content", [ $this, "decorate_article_content" ], 12 )' )
        && ! str_contains( $article, 'smpi-article-markup-normalizer' )
        && ! str_contains( $article, 'print_markup_fallback_script' ),
    'Target article configuration is represented by SSR body classes.' =>
        str_contains( $runtime, "smpi-runtime-article-markup" )
        && str_contains( $runtime, "smpi-runtime-article-headings" )
        && str_contains( $runtime, "smpi-runtime-article-dropcap" )
        && str_contains( $runtime, "smpi-runtime-article-numbered-lists" ),
    'The asset initializes on direct load and the persistent-player lifecycle.' =>
        str_contains( $javascript, "document.addEventListener('DOMContentLoaded'" )
        && str_contains( $javascript, "document.addEventListener('smp:content-ready'" )
        && str_contains( $javascript, 'event.detail && event.detail.root' ),
    'Repeated initialization is class- and marker-idempotent.' =>
        str_contains( $javascript, 'if (window.SmpiPublicDomRuntime) return;' )
        && str_contains( $javascript, 'classList.add' ),
    'The fetched-content runtime contains no dynamic code execution primitive.' =>
        ! preg_match( '/\b(?:eval|Function)\s*\(|document\.write\s*\(|innerHTML\s*=|insertAdjacentHTML\s*\(/', $javascript ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: SMPI public DOM behavior is external, idempotent, SSR-preserving, and persistent-navigation aware.\n";
