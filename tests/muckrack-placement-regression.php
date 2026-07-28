<?php
$source = file_get_contents( __DIR__ . "/../src/Content/MuckRackVerification.php" );

function assert_muckrack_placement( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
        exit( 1 );
    }
}

assert_muckrack_placement(
    false !== strpos( $source, "function isInvalidPlacement(el)" )
        && false !== strpos( $source, ".elementor-pagination,.pagination,.nav-links,[class*='pagination']" ),
    "MuckRack placement must reject pagination containers."
);
assert_muckrack_placement(
    false !== strpos( $source, "function injectTop()" )
        && false !== strpos( $source, "function normalizeTopBadges()" ),
    "The existing author-page placement flow must remain intact."
);
assert_muckrack_placement(
    false === strpos( $source, "function removeTopAuthorBadges()" )
        && false === strpos( $source, "function normalizeTopBadge()" ),
    "Pagination cleanup must not rebuild the author archive header."
);
assert_muckrack_placement(
    false !== strpos( $source, "cleanupInvalidBadges();injectTop();injectFooter();injectLoops();normalizeTopBadges();cleanupInvalidBadges();" ),
    "Runtime placement must preserve author rendering and isolate pagination cleanup."
);
assert_muckrack_placement(
    false !== strpos( $source, 'pair.querySelector(":scope > .smpi-muckrack-author-label")' ),
    "Badge cleanup must unwrap generated author labels before reinjection."
);
assert_muckrack_placement(
    false !== strpos( $source, "function isProseNameTarget(el)" )
        && false !== strpos( $source, ".smpi-author-bio,.elementor-author-box__bio,.elementor-author-box__text" )
        && false === strpos( $source, '.elementor-icon-list-text,.elementor-author-box__name,*' ),
    "Author-name detection must reject biography prose instead of scanning every descendant."
);
assert_muckrack_placement(
    false !== strpos( $source, "function containsLoop(el)" )
        && false !== strpos( $source, "isLoop(el)||containsLoop(el)" )
        && false !== strpos( $source, 'footerInLoop=icon.classList.contains("smpi-muckrack-context-single_footer")&&isLoop(icon)' ),
    "Footer author detection must reject related-post loops and remove misplaced footer badges."
);
assert_muckrack_placement(
    false !== strpos( $source, "function bestNameTarget(root,name)" )
        && false !== strpos( $source, "if(target)return [{el:target,root:cards[i]}]" )
        && false !== strpos( $source, "fallback=exactTextTargets(document,data.authorName).filter" )
        && false !== strpos( $source, "!isLoop(el)&&(floor===null||y(el)>=floor-2)" ),
    "Footer author detection must select one highest-ranked semantic name target."
);

echo "PASS: MuckRack pagination cleanup preserves the existing author-page placement flow." . PHP_EOL;
