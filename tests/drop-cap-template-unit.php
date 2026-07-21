<?php
namespace {
    define( "ABSPATH", __DIR__ );

    function sanitize_key( $key ): string {
        return strtolower( preg_replace( "/[^a-zA-Z0-9_\\-]/", "", (string) $key ) );
    }

    require_once __DIR__ . "/../src/Content/ArticleStyles.php";

    use smp_publication_integration\Content\ArticleStyles;

    function assert_drop_cap( bool $condition, string $message ): void {
        if ( ! $condition ) {
            fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
            exit( 1 );
        }
    }

    $styles = ArticleStyles::article_drop_cap_style_keys();
    assert_drop_cap( 5 === count( $styles ), "Drop-cap feature must expose exactly five templates." );
    assert_drop_cap( "dropcap-classic" === ArticleStyles::normalize_article_drop_cap_style( "invalid-style" ), "Invalid template must use the classic fallback." );

    $selector = ".test-article .smpi-article-lead";
    $rules = [];
    foreach ( $styles as $style ) {
        $css = ArticleStyles::article_drop_cap_rules( $style, $selector );
        assert_drop_cap( false !== strpos( $css, $selector . "::first-letter" ), $style . " must use the shared first-letter selector." );
        assert_drop_cap( false !== strpos( $css, "var(--smpi-dropcap-size,96px)" ), $style . " must use the shared size variable." );
        assert_drop_cap( false !== strpos( $css, "var(--smpi-dropcap-color" ), $style . " must use the shared accent variable." );
        $rules[] = $css;
    }

    assert_drop_cap( 5 === count( array_unique( $rules ) ), "Every drop-cap template must generate distinct CSS." );
    assert_drop_cap( false !== strpos( $rules[1], "background:var(--smpi-dropcap-color" ), "Highlight template must use a solid accent background." );
    assert_drop_cap( false !== strpos( $rules[1], "color:var(--smpi-dropcap-ink" ), "Highlight template must use the shared contrast variable." );
    assert_drop_cap( false !== strpos( $rules[2], "border:2px solid var(--smpi-dropcap-color" ), "Outline template must use an accent frame." );
    assert_drop_cap( false !== strpos( $rules[3], "border-left:6px solid var(--smpi-dropcap-color" ), "Side-rule template must use an accent rule." );
    assert_drop_cap( false !== strpos( $rules[4], "background:var(--smpi-dropcap-soft" ), "Soft-tile template must use the shared tint variable." );

    echo "PASS: five drop-cap templates share one selector and variable contract." . PHP_EOL;
}
