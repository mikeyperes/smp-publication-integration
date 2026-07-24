<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$bootstrap = (string) file_get_contents( $root . "/smp-publication-integration.php" );
$template = (string) file_get_contents( $root . "/src/Content/TemplateMarkup.php" );
$breadcrumbs = (string) file_get_contents( $root . "/src/Content/Breadcrumbs.php" );
$toc = (string) file_get_contents( $root . "/src/Content/TableOfContents.php" );
$article = (string) file_get_contents( $root . "/src/Content/ArticleStyles.php" );
$inline = (string) file_get_contents( $root . "/src/Content/InlinePhotoTreatments.php" );
$featured = (string) file_get_contents( $root . "/src/Content/FeaturedImageCaptions.php" );
$shortcodes = (string) file_get_contents( $root . "/src/Content/Shortcodes.php" );
$dashboard = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );

$css_start = strpos( $article, "public static function breadcrumbs_css" );
$css_end = strpos( $article, "public static function preview_bundle_css" );
$css_source = false !== $css_start && false !== $css_end
    ? substr( $article, $css_start, $css_end - $css_start )
    : "";

$checks = [
    "Template markup is loaded before every renderer that consumes it." =>
        str_contains( $bootstrap, 'require_once __DIR__ . "/src/Content/TemplateMarkup.php";' )
        && strpos( $bootstrap, "/src/Content/TemplateMarkup.php" ) < strpos( $bootstrap, "/src/Content/Breadcrumbs.php" ),

    "Shared semantic classes are defined in one helper." =>
        str_contains( $template, 'public const ROOT = "smpi-template";' )
        && str_contains( $template, 'public const TITLE = "smpi-template-title";' )
        && str_contains( $template, 'public const CONTENT = "smpi-template-content";' )
        && str_contains( $template, 'public const LIST = "smpi-template-list";' )
        && str_contains( $template, 'public const ITEM = "smpi-template-item";' )
        && str_contains( $template, 'public const LINK = "smpi-template-link";' )
        && str_contains( $template, 'public const IMAGE = "smpi-template-image";' )
        && str_contains( $template, 'public const CAPTION = "smpi-template-caption";' )
        && str_contains( $template, 'public const TEXT = "smpi-template-text";' ),

    "Structured WordPress HTML processing owns server-side decoration." =>
        str_contains( $template, '\\WP_HTML_Processor::create_fragment( $html )' )
        && str_contains( $template, '$processor->next_token()' )
        && str_contains( $template, '$processor->add_class( $class_name )' )
        && str_contains( $template, 'return $processor->get_updated_html();' ),

    "Breadcrumb PHP and fallback paths expose the full class contract." =>
        str_contains( $breadcrumbs, 'TemplateMarkup::decorate_breadcrumbs( $crumbs )' )
        && str_contains( $breadcrumbs, 'TemplateMarkup::root_classes( "breadcrumbs"' )
        && str_contains( $breadcrumbs, "smpi-breadcrumb-title" )
        && str_contains( $breadcrumbs, "smpi-breadcrumb-link" )
        && str_contains( $breadcrumbs, "smpi-breadcrumb-separator" )
        && str_contains( $breadcrumbs, "smpi-breadcrumb-current" )
        && str_contains( $breadcrumbs, "smpi-breadcrumbs-band" ),

    "TOC PHP and JavaScript paths use component classes for every structural element." =>
        str_contains( $toc, 'TemplateMarkup::root_classes( "toc"' )
        && str_contains( $toc, 'smpi-template-title smpi-toc-label' )
        && str_contains( $toc, 'smpi-template-content smpi-template-list smpi-toc-list' )
        && str_contains( $toc, 'smpi-template-item smpi-toc-item' )
        && str_contains( $toc, 'smpi-template-link smpi-toc-link' )
        && str_contains( $toc, 'details.className="smpi-template smpi-template--toc' )
        && str_contains( $toc, 'a.className="smpi-template-link smpi-toc-link"' ),

    "Article content is decorated on PHP output with a dynamic-render fallback." =>
        str_contains( $article, 'add_filter( "the_content", [ $this, "decorate_article_content" ], 9 )' )
        && str_contains( $article, 'TemplateMarkup::decorate_article_content( $content )' )
        && str_contains( $article, 'root.classList.add("smpi-template","smpi-template--article-content","smpi-template-content","smpi-article-content")' )
        && str_contains( $article, 'smpi-template-link","smpi-article-link' )
        && str_contains( $article, 'smpi-template-list","smpi-article-list' )
        && str_contains( $article, 'smpi-template-item","smpi-article-list-item' )
        && str_contains( $article, 'smpi-template-text","smpi-article-paragraph' )
        && str_contains( $article, 'smpi-template-image","smpi-article-image' )
        && str_contains( $article, 'smpi-template-title","smpi-article-heading' ),

    "Article fallback preserves the one server-selected drop cap." =>
        str_contains( $article, 'if(cfg.dropcap&&!root.querySelector(".smpi-article-lead"))' )
        && ! str_contains( $article, 'if(cfg.dropcap){var lead=Array.from(root.querySelectorAll("p"))' ),

    "Inline image PHP paths expose root, link, image, and caption classes." =>
        str_contains( $inline, 'TemplateMarkup::decorate_inline_photos( $html, $style )' )
        && str_contains( $inline, 'TemplateMarkup::root_classes( "inline-photo"' )
        && str_contains( $inline, 'smpi-template-caption smpi-inline-photo-caption' )
        && str_contains( $template, '"smpi-inline-photo-link"' )
        && str_contains( $template, '"smpi-inline-photo-image"' ),

    "Featured image PHP and JavaScript paths expose root, link, image, and caption classes." =>
        str_contains( $featured, 'TemplateMarkup::root_classes( "featured-image-caption"' )
        && str_contains( $featured, 'TemplateMarkup::decorate_featured_media( $html )' )
        && str_contains( $featured, 'smpi-template-caption smpi-featured-image-caption-text' )
        && str_contains( $featured, 'host.classList.add("smpi-template","smpi-template--featured-image-caption"' )
        && str_contains( $featured, 'smpi-template-image","smpi-featured-image-caption-image' )
        && str_contains( $featured, 'smpi-template-link","smpi-featured-image-caption-link' ),

    "Summary and FAQ output use semantic roots, titles, content, lists, items, and rich text." =>
        str_contains( $article, 'TemplateMarkup::root_classes( "article-summary"' )
        && str_contains( $article, 'TemplateMarkup::root_classes( "article-faqs"' )
        && str_contains( $article, 'smpi-template-title smpi-post-summary-title' )
        && str_contains( $article, 'smpi-template-content smpi-post-summary-content' )
        && str_contains( $article, 'smpi-template-title smpi-post-faqs-title' )
        && str_contains( $article, 'smpi-template-content smpi-post-faqs-content' )
        && str_contains( $shortcodes, 'smpi-template-list smpi-post-faq-list' )
        && str_contains( $shortcodes, 'smpi-template-item smpi-post-faq-item' )
        && str_contains( $shortcodes, 'smpi-template-title smpi-post-faq-question' )
        && str_contains( $shortcodes, 'smpi-template-content smpi-post-faq-answer' ),

    "Admin previews render the same class contract as front-end output." =>
        str_contains( $dashboard, '\\Content\\TableOfContents::build_toc(' )
        && str_contains( $dashboard, 'smpi-template-title smpi-article-heading smpi-article-heading--h2' )
        && str_contains( $dashboard, 'TemplateMarkup::root_classes( "inline-photo"' )
        && str_contains( $dashboard, 'TemplateMarkup::root_classes( "featured-image-caption"' )
        && str_contains( $dashboard, 'TemplateMarkup::root_classes( "article-summary"' )
        && str_contains( $dashboard, 'TemplateMarkup::root_classes( "article-faqs"' )
        && str_contains( $dashboard, 'smpi-breadcrumbs-band' ),

    "SMP front-end CSS no longer depends on Rank Math or Elementor selectors." =>
        "" !== $css_source
        && ! str_contains( $css_source, "rank-math-breadcrumb" )
        && ! str_contains( $css_source, "elementor-widget" ),

    "SMP front-end CSS targets semantic classes instead of bare template elements." =>
        str_contains( $article, '$scope . " .smpi-article-heading--h2"' )
        && str_contains( $article, '$scope . " .smpi-article-heading--h3"' )
        && str_contains( $article, '$scope . " .smpi-article-lead"' )
        && str_contains( $article, '$img = ".smpi-inline-photo .smpi-inline-photo-image";' )
        && str_contains( $article, '$cap = ".smpi-inline-photo .smpi-inline-photo-caption";' )
        && str_contains( $article, '$img = ".smpi-featured-image-caption .smpi-featured-image-caption-image";' )
        && ! str_contains( $article, ".smpi-table-of-contents a{" )
        && ! str_contains( $article, ".smpi-inline-photo img" )
        && ! str_contains( $article, ".smpi-featured-image-caption img" ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: Feature template PHP, JavaScript, preview, and CSS paths share one semantic class contract.\n";
