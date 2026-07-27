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

echo "PASS: MuckRack pagination cleanup preserves the existing author-page placement flow." . PHP_EOL;
