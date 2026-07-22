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
    false !== strpos( $source, "isLoop(el)||!isCurrentAuthorTarget(el)" ),
    "Top placement must only accept the current author outside loops."
);
assert_muckrack_placement(
    false !== strpos( $source, "if(!targets.length)return;removeTopAuthorBadges();targets=topAuthorTargets();" )
        && false !== strpos( $source, "var target=targets[0]" ),
    "Top placement must normalize duplicates before choosing one author target."
);
assert_muckrack_placement(
    false !== strpos( $source, "cleanupInvalidBadges();normalizeTopBadge();" )
        && false === strpos( $source, "function injectTop()" ),
    "Runtime placement must use the single normalized top badge path."
);
assert_muckrack_placement(
    false !== strpos( $source, 'pair.querySelector(":scope > .smpi-muckrack-author-label")' ),
    "Badge cleanup must unwrap generated author labels before reinjection."
);

echo "PASS: MuckRack badges are constrained to one author target and excluded from pagination." . PHP_EOL;
