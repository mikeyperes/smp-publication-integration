<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$main = (string) file_get_contents( $root . '/smp-publication-integration.php' );
$bootstrap = (string) file_get_contents( $root . '/src/Bootstrap/Plugin.php' );
$breadcrumbs = (string) file_get_contents( $root . '/src/Content/Breadcrumbs.php' );
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
    'Breadcrumb SSR uses the exact inert ScaleMyPodcast companion contract.' =>
        str_contains( $breadcrumbs, '<template data-smp-ajax-companion="smpi-breadcrumbs">' )
        && str_contains( $breadcrumbs, 'data-smpi-header-selectors' )
        && str_contains( $breadcrumbs, 'echo $markup' ),
    'The retired breadcrumb executable block cannot reappear from PHP.' =>
        ! str_contains( $breadcrumbs, 'smpi-breadcrumbs-inject' )
        && ! str_contains( $breadcrumbs, '<script' ),
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
        && str_contains( $javascript, 'source.content.firstElementChild.cloneNode(true)' )
        && str_contains( $javascript, "bar.setAttribute('data-smp-ajax-companion-rendered', 'smpi-breadcrumbs')" )
        && str_contains( $javascript, '.smpi-breadcrumbs-band[data-smpi-breadcrumbs-injected]' )
        && str_contains( $javascript, "bar.setAttribute('data-smpi-breadcrumbs-injected', '1')" )
        && str_contains( $javascript, 'classList.add' ),
    'Breadcrumb cleanup only removes SMP-owned bands and cannot remove a canonical Elementor title wrapper.' =>
        str_contains( $javascript, '.smpi-breadcrumbs-band[data-smp-ajax-companion-rendered="smpi-breadcrumbs"]' )
        && ! str_contains( $javascript, "],[data-smpi-breadcrumbs-injected]" ),
    'Runtime breadcrumb content uses the canonical 1140px header grid and responsive gutters.' =>
        str_contains( $article, 'max-width:1140px;padding-left:50px;padding-right:50px' )
        && str_contains( $article, 'max-width:767px' )
        && str_contains( $article, 'padding-left:20px;padding-right:20px' ),
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
