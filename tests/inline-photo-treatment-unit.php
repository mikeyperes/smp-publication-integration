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

    require_once __DIR__ . "/../src/Content/TemplateMarkup.php";
    require_once __DIR__ . "/../src/Content/ArticleStyles.php";
    require_once __DIR__ . "/../src/Content/InlinePhotoTreatments.php";

    use smp_publication_integration\Content\InlinePhotoTreatments;

    function assert_true( bool $condition, string $message ): void {
        if ( ! $condition ) {
            fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
            exit( 1 );
        }
    }

    function assert_contains( string $html, string $needle, string $message ): void {
        assert_true( false !== strpos( $html, $needle ), $message );
    }

    function assert_inline_photo_contract( string $html, string $message ): void {
        assert_contains( $html, "smpi-template", $message . " has the shared template class" );
        assert_contains( $html, "smpi-template--inline-photo", $message . " has the inline-photo component class" );
        assert_contains( $html, "smpi-inline-photo", $message . " keeps the legacy component class" );
        assert_contains( $html, "smpi-inline-photo--fig2", $message . " has the selected template modifier" );
        assert_contains( $html, "smpi-template-caption smpi-inline-photo-caption", $message . " has the caption class contract" );
    }

    $brooke_markup = '<p><b><img class="alignnone wp-image-560651 size-large" src="https://example.com/photo.jpg" alt="" width="800" height="1000" /><br>
</b><i><span style="font-weight: 400;">Photo credit: Brooke Triplett, used with permission.</span></i></p>';
    $normalized = InlinePhotoTreatments::normalize_legacy_caption_markup( $brooke_markup, "fig2" );

    assert_inline_photo_contract( $normalized, "legacy image/caption pair" );
    assert_contains( $normalized, 'data-smpi-inline-photo="legacy-caption"', "wrapper records legacy-caption source" );
    assert_contains( $normalized, "Photo credit: Brooke Triplett, used with permission.", "caption text moves into figcaption" );
    assert_true( false === strpos( $normalized, "<p><b><img" ), "legacy paragraph wrapper is removed" );

    $same_paragraph_italic = '<p><i><span style="font-weight: 400;"><img data-lazyloaded="1" class="alignnone wp-image-560653 size-full" src="https://example.com/photo-2.jpg" alt="" width="400" height="600" /><br>
Photo credit: Brooke Triplett, used with permission.</span></i></p>';
    $normalized_two = InlinePhotoTreatments::normalize_legacy_caption_markup( $same_paragraph_italic, "fig2" );

    assert_inline_photo_contract( $normalized_two, "same-italic-node image/caption pair" );
    assert_contains( $normalized_two, "Photo credit: Brooke Triplett, used with permission.", "same-node caption text moves into figcaption" );

    $sanitized_bold = "<p><b><img src=\"https://example.com/photo-3.jpg\" alt=\"\" width=\"800\" height=\"1000\" />\n</b><i>Photo credit: Brooke Triplett, used with permission.</i></p>";
    $normalized_sanitized_bold = InlinePhotoTreatments::normalize_legacy_caption_markup( $sanitized_bold, "fig2" );

    assert_inline_photo_contract( $normalized_sanitized_bold, "sanitized bold image/caption pair" );
    assert_contains( $normalized_sanitized_bold, "Photo credit: Brooke Triplett, used with permission.", "sanitized bold caption text moves into figcaption" );

    $sanitized_italic = "<p><i><img src=\"https://example.com/photo-4.jpg\" alt=\"\" width=\"400\" height=\"600\" /><br>\nPhoto credit: Brooke Triplett, used with permission.</i></p>";
    $normalized_sanitized_italic = InlinePhotoTreatments::normalize_legacy_caption_markup( $sanitized_italic, "fig2" );

    assert_inline_photo_contract( $normalized_sanitized_italic, "sanitized italic image/caption pair" );
    assert_contains( $normalized_sanitized_italic, "Photo credit: Brooke Triplett, used with permission.", "sanitized italic caption text moves into figcaption" );

    echo "PASS: inline photo treatment normalization tests" . PHP_EOL;
}
