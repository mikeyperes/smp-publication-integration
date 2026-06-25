<?php
namespace {
    define( "ABSPATH", __DIR__ );

    function sanitize_key( $key ): string {
        return strtolower( preg_replace( "/[^a-zA-Z0-9_\\-]/", "", (string) $key ) );
    }
    function esc_attr( $value ): string {
        return htmlspecialchars( (string) $value, ENT_QUOTES, "UTF-8" );
    }
    function esc_html( $value ): string {
        return htmlspecialchars( (string) $value, ENT_QUOTES, "UTF-8" );
    }
    function wp_strip_all_tags( $value ): string {
        return strip_tags( (string) $value );
    }

    require_once __DIR__ . "/../src/Content/ArticleStyles.php";
    require_once __DIR__ . "/../src/Content/InlinePhotoTreatments.php";

    use smp_publication_integration\Content\InlinePhotoTreatments;

    function assert_true( bool $condition, string $message ): void {
        if ( ! $condition ) {
            fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
            exit( 1 );
        }
    }

    $brooke_markup = '<p><b><img class="alignnone wp-image-560651 size-large" src="https://example.com/photo.jpg" alt="" width="800" height="1000" /><br>
</b><i><span style="font-weight: 400;">Photo credit: Brooke Triplett, used with permission.</span></i></p>';
    $normalized = InlinePhotoTreatments::normalize_legacy_caption_markup( $brooke_markup, "fig2" );

    assert_true( false !== strpos( $normalized, 'class="smpi-inline-photo smpi-inline-photo--fig2"' ), "legacy image/caption pair is wrapped in inline photo figure" );
    assert_true( false !== strpos( $normalized, 'data-smpi-inline-photo="legacy-caption"' ), "wrapper records legacy-caption source" );
    assert_true( false !== strpos( $normalized, "<figcaption>Photo credit: Brooke Triplett, used with permission.</figcaption>" ), "caption text moves into figcaption" );
    assert_true( false === strpos( $normalized, "<p><b><img" ), "legacy paragraph wrapper is removed" );

    $same_paragraph_italic = '<p><i><span style="font-weight: 400;"><img data-lazyloaded="1" class="alignnone wp-image-560653 size-full" src="https://example.com/photo-2.jpg" alt="" width="400" height="600" /><br>
Photo credit: Brooke Triplett, used with permission.</span></i></p>';
    $normalized_two = InlinePhotoTreatments::normalize_legacy_caption_markup( $same_paragraph_italic, "fig2" );

    assert_true( false !== strpos( $normalized_two, 'class="smpi-inline-photo smpi-inline-photo--fig2"' ), "same-italic-node image/caption pair is wrapped" );
    assert_true( false !== strpos( $normalized_two, "<figcaption>Photo credit: Brooke Triplett, used with permission.</figcaption>" ), "same-node caption text moves into figcaption" );

    echo "PASS: inline photo treatment normalization tests" . PHP_EOL;
}
