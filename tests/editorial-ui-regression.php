<?php

declare(strict_types=1);

$root       = dirname( __DIR__ );
$dashboard  = (string) file_get_contents( $root . '/src/Admin/Dashboard/DashboardController.php' );
$muckrack   = (string) file_get_contents( $root . '/src/Content/MuckRackVerification.php' );
$elementor  = (string) file_get_contents( $root . '/src/Authorship/ElementorAuthorRenderer.php' );
$loop       = (string) file_get_contents( $root . '/src/Authorship/LoopBylineRenderer.php' );

$checks = [
    'Elementor CSS cache busting selects the Core collapsible renderer.' => str_contains( $dashboard, '"elementor_css_cache_busting" === $snippet_id' )
        && str_contains( $dashboard, 'CoreUi::collapsible' ),
    'The cache-busting feature is closed by default.' => str_contains( $dashboard, '"open" => false' ),
    'Frontend injection creates an exact author and badge pair.' => str_contains( $muckrack, 'function pairBadge(el,node)' )
        && str_contains( $muckrack, '.smpi-muckrack-inline-pair{display:inline-flex;align-items:center' ),
    'Frontend injection does not promote author text to a card-wide link.' => str_contains( $muckrack, 'norm(link.textContent)===norm(el.textContent)' )
        && ! str_contains( $muckrack, 'el.closest("a[href]")||el' ),
    'Repeated placement passes do not append a second badge to a pair.' => str_contains( $muckrack, 'if(existing){if(hasBadge(existing))return false;' ),
    'Badge pairs override Elementor full-width links without collapsing author names.' => str_contains( $muckrack, '.smpi-muckrack-inline-pair>.smpi-muckrack-author-label{min-width:min-content;word-break:normal;overflow-wrap:normal}' )
        && str_contains( $muckrack, '.smpi-muckrack-inline-pair>.smpi-muckrack-link{width:auto!important;max-width:none}' ),
    'Elementor-cloned authors use the same pair contract.' => str_contains( $elementor, '$pair->setAttribute( "class", "smpi-muckrack-inline-pair" )' ),
    'Loop bylines use the same pair contract.' => str_contains( $loop, 'self::ITEM_CLASS . " smpi-muckrack-inline-pair"' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: Editorial badges and cache-busting UI use shared structural contracts.\n";
