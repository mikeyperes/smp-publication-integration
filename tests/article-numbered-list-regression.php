<?php

declare(strict_types=1);

define( "ABSPATH", dirname( __DIR__ ) . "/" );

function sanitize_key( mixed $value ): string {
    return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( (string) $value ) ) ?: "";
}

$root = dirname( __DIR__ );
require $root . "/src/Content/TemplateMarkup.php";
require $root . "/src/Content/ArticleStyles.php";

use smp_publication_integration\Content\ArticleStyles;

function numbered_list_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
        exit( 1 );
    }
}

$styles = ArticleStyles::article_numbered_list_style_keys();
numbered_list_assert(
    [ "none", "nlist01", "nlist02", "nlist03", "nlist04", "nlist05" ] === $styles,
    "The canonical numbered-list style set changed unexpectedly."
);
numbered_list_assert( "nlist01" === ArticleStyles::normalize_article_numbered_list_style( "invalid" ), "Invalid list styles do not fall back safely." );
numbered_list_assert( "" === ArticleStyles::article_numbered_list_rules( "none", ".article-list", false ), "The site-default option emits SMP template CSS." );

$contracts = [
    "nlist01" => "color:var(--smpi-numbered-list-accent,#00ff41)",
    "nlist02" => "background:var(--smpi-numbered-list-accent,#2563eb)",
    "nlist03" => "border-left:3px solid var(--smpi-numbered-list-accent,#d63428)",
    "nlist04" => "border-top:3px solid var(--smpi-numbered-list-accent,#111827)",
    "nlist05" => "border:1px solid var(--smpi-numbered-list-accent,#a16207)",
];
foreach ( $contracts as $style => $accent_contract ) {
    $selector = ".article-list--" . $style;
    $css = ArticleStyles::article_numbered_list_rules( $style, $selector, false );
    numbered_list_assert( str_contains( $css, "counter-reset:smpi-numbered-list" ), $style . " does not initialize its own ordered-list counter." );
    numbered_list_assert( str_contains( $css, $selector . " > .smpi-numbered-list-item" ), $style . " does not target direct list items." );
    numbered_list_assert( str_contains( $css, $accent_contract ), $style . " does not map its intended accent area." );
    numbered_list_assert( ! str_contains( $css, $selector . "{grid-template-columns:repeat" ), $style . " renders list items in multiple columns." );
    numbered_list_assert( ! str_contains( $css, "span 20" ), $style . " creates excessive implicit grid rows." );
    if ( in_array( $style, [ "nlist01", "nlist02", "nlist05" ], true ) ) {
        numbered_list_assert( str_contains( $css, $selector . " > .smpi-numbered-list-item > *{grid-column:2;min-width:0}" ), $style . " does not place arbitrary editor markup in the content column." );
    }
}

$template = (string) file_get_contents( $root . "/src/Content/TemplateMarkup.php" );
$article = (string) file_get_contents( $root . "/src/Content/ArticleStyles.php" );
$public_dom = (string) file_get_contents( $root . "/assets/frontend/public-dom.js" );
$dashboard = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );
$settings = (string) file_get_contents( $root . "/src/Settings/SettingsRepository.php" );
$ajax = (string) file_get_contents( $root . "/src/Admin/Ajax/AjaxController.php" );
$quick_start = (string) file_get_contents( $root . "/src/Support/QuickStartFeatures.php" );

numbered_list_assert(
    str_contains( $template, '$depth > 0 && $depth <= $root_depth' )
        && str_contains( $template, 'count( array_keys( $breadcrumbs, "OL", true ) ) !== 1' )
        && str_contains( $template, 'in_array( "UL", $breadcrumbs, true )' )
        && str_contains( $template, '$depth !== $root_depth + 1' )
        && str_contains( $template, 'count( array_keys( $breadcrumbs, "LI", true ) ) !== 1' )
        && str_contains( $template, 'self::has_blocked_article_ancestor( $processor )' )
        && str_contains( $template, '"smpi-post-summary-list", "smpi-post-faq-list", "smpi-numbered-list", "wp-block-footnotes"' ),
    "Sibling roots, nested lists, or plugin-owned ordered lists are not handled safely."
);
numbered_list_assert(
    str_contains( $public_dom, "(list.parentElement && list.parentElement.closest('ol'))" )
        && str_contains( $public_dom, '.smpi-post-summary-list,.smpi-post-faq-list,.smpi-toc-list,.wp-block-footnotes' ),
    "The dynamic-render fallback does not preserve nested and plugin-owned lists."
);
foreach (
    [
        "Site default ordered list",
        "Editorial rows with dividers",
        "Bordered rows with number tiles",
        "Left accent rail with index",
        "Oversized background numbers",
        "Circular number markers",
    ] as $label
) {
    numbered_list_assert( str_contains( $dashboard, '"label" => "' . $label . '"' ), "Missing descriptive option label: " . $label . "." );
}
numbered_list_assert(
    str_contains( $settings, '"article_numbered_lists_enabled" => false' )
        && str_contains( $settings, '"article_numbered_list_style" => "nlist01"' )
        && str_contains( $ajax, '"article_numbered_list_style"' )
        && str_contains( $ajax, "'article_numbered_list_accent_color'" )
        && str_contains( $quick_start, '"article_numbered_list_styles"' ),
    "Settings, AJAX persistence, or Quick Start registration is incomplete."
);

echo "PASS: Numbered article lists have five labeled one-row templates with shared settings and guarded decoration." . PHP_EOL;
