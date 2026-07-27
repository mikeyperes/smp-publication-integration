<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$article = (string) file_get_contents( $root . "/src/Content/ArticleStyles.php" );
$dashboard = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );
$table = (string) file_get_contents( $root . "/src/Content/TableOfContents.php" );
$compatibility = (string) file_get_contents( $root . "/src/Content/ElementorFrontendCompatibility.php" );
$registry = (string) file_get_contents( $root . "/src/Design/TemplateDesignRegistry.php" );

$start = strpos( $article, "* Table of contents" );
$end = strpos( $article, "* Post summary + FAQ", (int) $start );
$toc_css = false !== $start && false !== $end ? substr( $article, $start, $end - $start ) : "";

$checks = [
    "TOC accent source is resolved by the shared template registry." => str_contains( $article, 'TemplateDesignRegistry::css_variables( $surface, $settings )' )
        && str_contains( $registry, '"source_key" => "table_of_contents_color_source"' )
        && str_contains( $registry, '"custom_key" => "table_of_contents_accent_color"' ),
    "TOC native CSS retains the original blue template accent fallback." => "" !== $toc_css
        && str_contains( $toc_css, "#2563eb" ),
    "Every TOC decorative accent uses the shared accent variable." => str_contains( $toc_css, ".smpi-toc-caret" )
        && str_contains( $toc_css, ".smpi-toc01" )
        && str_contains( $toc_css, ".smpi-toc02 .smpi-toc-item:before" )
        && str_contains( $toc_css, ".smpi-toc03 .smpi-toc-link:before" )
        && str_contains( $toc_css, ".smpi-toc04 .smpi-toc-link:hover" )
        && substr_count( $toc_css, "var(--smpi-toc-accent,#2563eb)" ) >= 12
        && ! str_contains( $toc_css, "var(--smpi-toc-accent)" ),
    "All TOC templates fill their available content width." => str_contains( $toc_css, "box-sizing:border-box;margin:0;max-width:none;width:100%" )
        && ! str_contains( $toc_css, "max-width:560px" )
        && ! str_contains( $toc_css, "max-width:var(--content-width" ),
    "The admin has no stale TOC preview stylesheet." => ! str_contains( $dashboard, ".smpi-design-toc" )
        && ! str_contains( $dashboard, ".smpi-design-preview a{color:#2563eb" ),
    "The TOC shortcode and use instructions are visible in its feature settings." => str_contains( $dashboard, '"Default", "[smp_table_of_contents]"' )
        && str_contains( $dashboard, "Turn off automatic placement when positioning it manually." ),
    "TOC template CSS has one frontend owner." => str_contains( $table, 'ArticleStyles::toc_css()' )
        && ! str_contains( substr( $article, 0, (int) strpos( $article, "public function decorate_article_content" ) ), 'self::toc_css()' ),
    "Native Elementor TOC markup and anchors remain owned by Elementor." => ! str_contains( $table, '.elementor-widget-table-of-contents' )
        && ! str_contains( $table, 'print_elementor_compatibility_script' )
        && ! str_contains( $table, 'smpi-elementor-toc-bridge' )
        && ! str_contains( $table, 'smpi-elementor-toc-fallback' )
        && ! str_contains( $table, 'smpi-elementor-toc-' ),
    "Native Elementor TOC loading is compact and cannot create a nested scroll area." => str_contains( $compatibility, '.elementor-widget-table-of-contents .elementor-toc__body{max-height:none!important;overflow:visible!important}' )
        && str_contains( $compatibility, '.elementor-widget-table-of-contents .elementor-toc__spinner{display:none!important}' )
        && str_contains( $compatibility, 'box-shadow:0 14px 0 currentColor,0 28px 0 currentColor' ),
    "Elementor compatibility remains CSS-only and does not replace native TOC output." => ! str_contains( $compatibility, 'wp_footer' )
        && ! str_contains( $compatibility, 'MutationObserver' )
        && ! str_contains( $compatibility, 'replaceChildren' )
        && ! str_contains( $compatibility, 'smpi-elementor-toc-' ),
    "Elementor sticky menu clones cannot expose duplicate submenu arrows." => str_contains( $compatibility, '.elementor-sticky__spacer{pointer-events:none!important;visibility:hidden!important}' )
        && str_contains( $compatibility, '.elementor-widget-nav-menu a>.sub-arrow~.sub-arrow{display:none!important}' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: TOC output, native Elementor loading, and menu compatibility remain isolated.\n";
