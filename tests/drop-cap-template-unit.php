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
    assert_drop_cap( 10 === count( $styles ), "Drop-cap feature must expose exactly ten templates." );
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

    assert_drop_cap( 10 === count( array_unique( $rules ) ), "Every drop-cap template must generate distinct CSS." );
    assert_drop_cap( false !== strpos( $rules[1], "background:var(--smpi-dropcap-color" ), "Highlight template must use a solid accent background." );
    assert_drop_cap( false !== strpos( $rules[1], "color:var(--smpi-dropcap-ink" ), "Highlight template must use the shared contrast variable." );
    assert_drop_cap( false !== strpos( $rules[2], "border:2px solid var(--smpi-dropcap-color" ), "Outline template must use an accent frame." );
    assert_drop_cap( false !== strpos( $rules[3], "border-left:6px solid var(--smpi-dropcap-color" ), "Side-rule template must use an accent rule." );
    assert_drop_cap( false !== strpos( $rules[4], "background:var(--smpi-dropcap-soft" ), "Soft-tile template must use the shared tint variable." );

    $template_fonts = ArticleStyles::article_drop_cap_script_template_fonts();
    $script_fonts = ArticleStyles::article_drop_cap_script_fonts();
    foreach ( [ 5, 6, 7, 8, 9 ] as $i ) {
        $font_key = $template_fonts[ $styles[ $i ] ] ?? "";
        $font = $script_fonts[ $font_key ] ?? [];
        assert_drop_cap( false !== strpos( $rules[ $i ], "cursive" ), $styles[ $i ] . " must use the cursive font stack." );
        assert_drop_cap( [] !== $font && false !== strpos( $rules[ $i ], 'font-family:"' . $font["family"] . '"' ), $styles[ $i ] . " must use its own script typeface." );
        assert_drop_cap( false !== strpos( $rules[ $i ], "font-weight:" . $font["default_weight"] ), $styles[ $i ] . " must use its script typeface's weight." );
        assert_drop_cap( ArticleStyles::article_drop_cap_style_uses_script_font( $styles[ $i ] ), $styles[ $i ] . " must be detected as a script-font template." );
        assert_drop_cap( ! ArticleStyles::article_drop_cap_style_uses_script_font( $styles[ $i - 5 ] ), $styles[ $i - 5 ] . " must not be detected as a script-font template." );
    }
    assert_drop_cap( [ "dancing-script", "great-vibes", "parisienne", "pinyon-script", "allura" ] === array_keys( $script_fonts ), "Drop-cap must expose the five template-owned script fonts in order." );
    assert_drop_cap( 5 === count( array_unique( array_values( $template_fonts ) ) ), "Each script template must own a distinct typeface." );
    assert_drop_cap( "dancing-script" === ArticleStyles::normalize_article_drop_cap_script_font( "invalid-font" ), "Invalid script fonts must use Dancing Script." );
    assert_drop_cap( false !== strpos( ArticleStyles::script_font_link_html(), "family=Dancing+Script" ), "The default frontend font link must load Dancing Script." );
    $great_vibes_link = ArticleStyles::script_font_link_html_for_style( "dropcap-script-tile" );
    assert_drop_cap( false !== strpos( $great_vibes_link, "family=Great+Vibes" ) && false === strpos( $great_vibes_link, "family=Dancing+Script" ), "The frontend must load only the selected script font." );
    $preview_link = ArticleStyles::script_font_preview_link_html();
    foreach ( [ "Dancing+Script", "Great+Vibes", "Parisienne", "Pinyon+Script", "Allura" ] as $family ) {
        assert_drop_cap( false !== strpos( $preview_link, "family=" . $family ), "Admin previews must load " . $family . "." );
    }
    assert_drop_cap( 5 === substr_count( $preview_link, 'rel="stylesheet"' ), "Admin previews must load each script family through an independent stylesheet." );
    assert_drop_cap( false !== strpos( $rules[6], "background:var(--smpi-dropcap-soft" ), "Script-tile template must use the shared tint variable." );
    assert_drop_cap( false !== strpos( $rules[7], "border-radius:999px" ), "Script-round template must use a rounded badge." );
    assert_drop_cap( false !== strpos( $rules[8], "border-bottom:4px solid var(--smpi-dropcap-color" ), "Script-underline template must use an accent underline." );
    assert_drop_cap( false !== strpos( $rules[9], "text-shadow" ), "Script-shadow template must use an offset shadow." );

    $preserved = ArticleStyles::article_drop_cap_rules(
        "dropcap-script-tile",
        $selector,
        [ "font_family" => true, "font_size" => true, "font_color" => true, "font_weight" => true ]
    );
    foreach ( [ "font-family:", "font-size:", "font-weight:", "color:" ] as $declaration ) {
        assert_drop_cap( false === strpos( $preserved, $declaration ), "Preserved drop caps must not emit " . $declaration );
    }
    assert_drop_cap( false !== strpos( $preserved, "background:var(--smpi-dropcap-soft" ), "Typography preservation must retain the selected drop-cap design." );

    $dashboard = (string) file_get_contents( dirname( __DIR__ ) . "/src/Admin/Dashboard/DashboardController.php" );
    assert_drop_cap( false === strpos( $dashboard, "Script letter font" ), "The obsolete full-sentence script-font gallery must be removed." );
    assert_drop_cap( false === strpos( $dashboard, 'select_setting_html( "article_drop_cap_script_font"' ), "The obsolete standalone script-font setting must not remain in the Features UI." );
    assert_drop_cap( false !== strpos( $dashboard, 'TypographyControl::render(' ) && false !== strpos( $dashboard, '"title" => "Drop cap typography"' ), "Drop-cap typography fields and preservation toggles must share the combined Core control." );
    assert_drop_cap( false === strpos( $dashboard, '"title" => "Keep current typography"' ), "The detached preservation panel must not remain in SMP." );
    foreach ( array_keys( $template_fonts ) as $style ) {
        assert_drop_cap( false !== strpos( $dashboard, '"' . $style . '" => [' ), $style . " must remain a selectable template row." );
    }

    echo "PASS: five distinct script drop caps share one selector and Core preservation contract." . PHP_EOL;
}
