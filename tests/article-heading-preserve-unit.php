<?php

namespace {
    define( "ABSPATH", __DIR__ );

    function sanitize_key( $key ): string {
        return strtolower( preg_replace( "/[^a-zA-Z0-9_\\-]/", "", (string) $key ) );
    }

    require_once __DIR__ . "/../src/Content/ArticleStyles.php";

    use smp_publication_integration\Content\ArticleStyles;

    function assert_heading_preserve( bool $condition, string $message ): void {
        if ( ! $condition ) {
            fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
            exit( 1 );
        }
    }

    $scope = ".article";
    $h2 = $scope . " h2";
    $h3 = $scope . " h3";
    $preserve_all = [
        "font_family" => true,
        "font_size" => true,
        "font_color" => true,
        "font_weight" => true,
    ];
    $preserved = ArticleStyles::article_heading_rules( "h2-serif", $scope, $h2, $h3, $preserve_all );

    assert_heading_preserve( false === strpos( $preserved, "font-family:" ), "Preserved headings must not override the theme font family." );
    assert_heading_preserve( false === strpos( $preserved, "font-size:" ), "Preserved headings must not override H2 or H3 font sizes." );
    assert_heading_preserve( false === strpos( $preserved, "font-weight:" ), "Preserved headings must not override the theme font weight." );
    assert_heading_preserve( false === strpos( $preserved, "color:" ), "Preserved headings must not override the theme text color." );
    assert_heading_preserve( false !== strpos( $preserved, "background:var(--smpi-heading-accent" ), "Preserved headings must retain the selected decorative design." );

    $customized = ArticleStyles::article_heading_rules( "h2-serif", $scope, $h2, $h3 );
    foreach ( [ "font-family:", "font-size:", "font-weight:", "color:" ] as $declaration ) {
        assert_heading_preserve( false !== strpos( $customized, $declaration ), "Typography customization must emit " . $declaration );
    }

    $root = dirname( __DIR__ );
    $settings = (string) file_get_contents( $root . "/src/Settings/SettingsRepository.php" );
    $ajax = (string) file_get_contents( $root . "/src/Admin/Ajax/AjaxController.php" );
    $dashboard = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );
    $quick_start = (string) file_get_contents( $root . "/src/Support/QuickStartFeatures.php" );
    assert_heading_preserve( false !== strpos( $settings, 'TypographyPreservation::defaults( "article_heading", true )' ), "Heading preservation defaults must come from Core." );
    assert_heading_preserve( false !== strpos( $settings, 'TypographyPreservation::setting_keys( "article_heading" )' ), "Heading preservation keys must come from Core." );
    assert_heading_preserve( false !== strpos( $ajax, "Settings::typography_preservation_setting_keys()" ), "Core-generated preservation keys must persist through AJAX." );
    assert_heading_preserve( false !== strpos( $dashboard, 'typography_preservation_control_html(' ) && false !== strpos( $dashboard, '"article_heading"' ), "The Features UI must render the reusable Core preservation control." );
    assert_heading_preserve( false === strpos( $dashboard, "smpiSyncHeadingPreserveControls" ), "SMP must not duplicate Core preservation synchronization." );
    assert_heading_preserve( false !== strpos( $quick_start, 'TypographyPreservation::defaults( "article_heading", true )' ), "Quick Start must use Core-generated heading defaults." );

    echo "PASS: Heading templates can preserve theme font family, size, color, and weight." . PHP_EOL;
}
