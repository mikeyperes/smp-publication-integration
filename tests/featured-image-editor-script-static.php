<?php

declare( strict_types=1 );

$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Content/FeaturedImageRequirements.php' );

$checks = [
    'The editor observer ignores attribute writes made by the publishing guard.' => str_contains(
        $source,
        "new MutationObserver(queueRefresh).observe(document.documentElement, { childList: true, subtree: true });"
    ) && ! str_contains( $source, 'attributes: true' ),
    'Repeated editor events are coalesced into one animation-frame refresh.' => str_contains( $source, 'var refreshQueued = false;' )
        && str_contains( $source, 'window.requestAnimationFrame(function(){' )
        && str_contains( $source, 'wp.data.subscribe(onEditorStateChange);' )
        && str_contains( $source, 'if (blockId === lastBlockId) return;' ),
    'Publishing controls only receive lock attributes when their values change.' => str_contains(
        $source,
        "if (button.getAttribute('data-smpi-featured-image-lock') !== '1')"
    ) && str_contains( $source, "if (button.getAttribute('title') !== lockMessage)" ),
    'The editor notice only mutates its visibility class when the state changes.' => str_contains(
        $source,
        "if (item && item.classList.contains('is-hidden') !== shouldHide)"
    ),
    'A pre-existing publish-button title is restored after the featured-image lock clears.' => str_contains(
        $source,
        "button.setAttribute('data-smpi-featured-image-original-title', button.getAttribute('title'))"
    ) && str_contains(
        $source,
        "button.setAttribute('title', button.getAttribute('data-smpi-featured-image-original-title'))"
    ),
    'Server-side REST and classic editor enforcement remain registered.' => str_contains(
        $source,
        'add_filter( "rest_pre_insert_post", [ $this, "validate_rest_featured_image" ], 10, 2 );'
    ) && str_contains(
        $source,
        'add_filter( "wp_insert_post_data", [ $this, "prevent_classic_publish_without_featured_image" ], 10, 2 );'
    ),
];

foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

echo "PASS: featured-image editor guard avoids recursive DOM mutation loops.\n";
