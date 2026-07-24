<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$article = (string) file_get_contents( $root . "/src/Content/ArticleStyles.php" );
$dashboard = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );
$table = (string) file_get_contents( $root . "/src/Content/TableOfContents.php" );

$start = strpos( $article, "* Table of contents" );
$end = strpos( $article, "* Post summary + FAQ", (int) $start );
$toc_css = false !== $start && false !== $end ? substr( $article, $start, $end - $start ) : "";

$checks = [
    "TOC accent defaults through the brand-derived settings contract." => str_contains( $article, '$default = Settings::color_default( "table_of_contents_accent_color" );' )
        && str_contains( $article, 'Settings::get( "table_of_contents_accent_color", $default ), $default' ),
    "TOC CSS contains no hardcoded blue accent fallback." => "" !== $toc_css
        && ! str_contains( $toc_css, "#2563eb" )
        && ! str_contains( $toc_css, "37,99,235" ),
    "Every TOC decorative accent uses the shared accent variable." => str_contains( $toc_css, ".smpi-toc-caret" )
        && str_contains( $toc_css, ".smpi-toc01" )
        && str_contains( $toc_css, ".smpi-toc02 .smpi-toc-item:before" )
        && str_contains( $toc_css, ".smpi-toc03 .smpi-toc-link:before" )
        && str_contains( $toc_css, ".smpi-toc04 .smpi-toc-link:hover" )
        && substr_count( $toc_css, "var(--smpi-toc-accent)" ) >= 12,
    "All TOC templates fill their available content width." => str_contains( $toc_css, "box-sizing:border-box;margin:0;max-width:none;width:100%" )
        && ! str_contains( $toc_css, "max-width:560px" )
        && ! str_contains( $toc_css, "max-width:var(--content-width" ),
    "The admin has no stale TOC preview stylesheet." => ! str_contains( $dashboard, ".smpi-design-toc" )
        && ! str_contains( $dashboard, ".smpi-design-preview a{color:#2563eb" ),
    "The TOC shortcode and use instructions are visible in its feature settings." => str_contains( $dashboard, '"Default", "[smp_table_of_contents]"' )
        && str_contains( $dashboard, "Turn off automatic placement when positioning it manually." ),
    "TOC template CSS has one frontend owner." => str_contains( $table, 'ArticleStyles::toc_css()' )
        && ! str_contains( substr( $article, 0, (int) strpos( $article, "public function decorate_article_content" ) ), 'self::toc_css()' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: TOC colors, previews, shortcode guidance, and full-width output share one stylesheet.\n";
