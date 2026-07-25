<?php

declare(strict_types=1);

define( "ABSPATH", dirname( __DIR__ ) . "/" );

$root = dirname( __DIR__ );
require $root . "/src/Content/PostContentBlockPlacement.php";
require $root . "/src/Content/PostSummaryPlacement.php";
require $root . "/src/Content/PostFaqPlacement.php";

use smp_publication_integration\Content\PostFaqPlacement;
use smp_publication_integration\Content\PostSummaryPlacement;

$article = (string) file_get_contents( $root . "/src/Content/ArticleStyles.php" );
$dashboard = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );
$settings = (string) file_get_contents( $root . "/src/Settings/SettingsRepository.php" );
$ajax = (string) file_get_contents( $root . "/src/Admin/Ajax/AjaxController.php" );
$bootstrap = (string) file_get_contents( $root . "/src/Bootstrap/Plugin.php" );
$main = (string) file_get_contents( $root . "/smp-publication-integration.php" );
$placement = (string) file_get_contents( $root . "/src/Content/PostContentBlockPlacement.php" );
$summary_placement = (string) file_get_contents( $root . "/src/Content/PostSummaryPlacement.php" );
$faq_placement = (string) file_get_contents( $root . "/src/Content/PostFaqPlacement.php" );
$shortcodes = (string) file_get_contents( $root . "/src/Content/Shortcodes.php" );
$registry = (string) file_get_contents( $root . "/src/Design/TemplateDesignRegistry.php" );

$checks = [
    "Summary design color uses the shared template color source contract." => str_contains( $registry, '"source_key" => "post_summary_color_source"' )
        && str_contains( $registry, '"custom_key" => "post_summary_accent_color"' )
        && str_contains( $dashboard, 'template_color_setting_html( "post_summary", "Summary design color"' )
        && str_contains( $ajax, 'TemplateDesignRegistry::source_setting_keys()' ),
    "Summary text color remains an independent shared typography control." => str_contains( $registry, '"font_color" => [ "key" => "post_summary_text_color"' )
        && str_contains( $registry, '"--smpi-summary-text"' )
        && str_contains( $article, "--smpi-summary-text" ),
    "Original Summary templates own canonical body typography on every surface." => str_contains( $article, '.smpi-post-summary{max-width:var(--content-width,720px);margin:0;color:var(--smpi-summary-text,#1f2937);font-family:var(--smpi-summary-font,Arial,Helvetica,sans-serif);font-size:var(--smpi-summary-size,16px);font-weight:var(--smpi-summary-weight,400);line-height:1.55}' )
        && str_contains( $article, '.smpi-post-summary .smpi-post-summary-content,.smpi-post-summary .smpi-post-summary-content *{color:inherit;font-family:inherit;font-size:inherit;font-weight:inherit}' ),
    "Site text color inheritance excludes Summary title accents." => str_contains( $article, 'false !== strpos( $selector, "smpi-post-summary-content" )' )
        && str_contains( $dashboard, "Only mapped accents and tints change; typography stays unchanged." )
        && str_contains( $dashboard, "Original Template matches the preview." ),
    "Summary sum00 maps its title and underline colors." => str_contains( $article, ".smpi-sum00 .smpi-post-summary-title{margin:0;font-size:1.3rem;font-weight:800;color:var(--smpi-summary-accent,#1f2937)" )
        && str_contains( $article, "border-bottom:3px solid var(--smpi-summary-accent,#111827)" ),
    "Summary sum01 maps its original blue rule and title." => str_contains( $article, ".smpi-sum01{border:1px solid #e5e7eb;border-left:4px solid var(--smpi-summary-accent,#2563eb)" )
        && str_contains( $article, ".smpi-sum01 .smpi-post-summary-title{margin:0 0 10px;font-size:.78rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--smpi-summary-accent,#2563eb)" ),
    "Summary sum02 maps its original top rule and title." => str_contains( $article, ".smpi-sum02{padding:18px 0;border-top:2px solid var(--smpi-summary-accent,#0a0a0a);border-bottom:1px solid #e5e7eb" )
        && str_contains( $article, ".smpi-sum02 .smpi-post-summary-title{margin:0 0 12px;font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--smpi-summary-accent,#0a0a0a)" ),
    "Summary sum03 maps its header and contrast text." => str_contains( $article, ".smpi-sum03 .smpi-post-summary-title{margin:0;background:var(--smpi-summary-accent,#0a0a0a);color:var(--smpi-summary-accent-ink,#fff)" ),
    "Summary sum04 maps its panel tint, title, and icon." => str_contains( $article, ".smpi-sum04{background:var(--smpi-summary-accent-soft,#eff4ff)" )
        && str_contains( $article, ".smpi-sum04 .smpi-post-summary-title{margin:0 0 14px;font-size:1.05rem;font-weight:800;color:var(--smpi-summary-accent,#1e3a8a)" )
        && str_contains( $article, '.smpi-sum04 .smpi-post-summary-title:before{content:\"\";width:18px;height:18px;border-radius:5px;background:var(--smpi-summary-accent,#2563eb)' ),
    "Summary and FAQ are independent feature cards." => str_contains( $dashboard, '"Article Summary",' )
        && str_contains( $dashboard, '"post_summary_acf_enabled",' )
        && str_contains( $dashboard, '"Article FAQs",' )
        && str_contains( $dashboard, '"post_faqs_acf_enabled",' )
        && ! str_contains( $dashboard, '"Article Summary & FAQ Blocks"' ),
    "Both cards expose visible use instructions and canonical shortcodes." => str_contains( $dashboard, 'shortcode_usage_html(' )
        && str_contains( $dashboard, '[smp_post_summary]' )
        && str_contains( $dashboard, '[smp_post_acf field=\"post_summary\"]' )
        && str_contains( $dashboard, '[smp_post_faqs]' )
        && str_contains( $dashboard, '[smp_post_acf field=\"post_faq_items\"]' ),
    "The generic ACF shortcode uses the selected Summary and FAQ wrappers." => str_contains( $shortcodes, 'return $this->render_post_summary( [ "post_id" => $post_id, "style" => (string) $atts["style"] ] );' )
        && str_contains( $shortcodes, 'return $this->render_post_faqs( [ "post_id" => $post_id, "style" => (string) $atts["style"] ] );' )
        && str_contains( $shortcodes, 'private function render_post_field_value(' ),
    "The numbered FAQ template renders one counter per item." => str_contains( $article, '.smpi-faq03 .smpi-post-faq-item:after{content:none}' )
        && ! str_contains( $article, '.smpi-faq03 .smpi-post-faq-item:before,.smpi-faq03 .smpi-post-faq-item:after{content:counter(f,decimal-leading-zero)' ),
    "Summary placement exposes Manual, Above content, and Below content." => PostSummaryPlacement::MANUAL === PostSummaryPlacement::normalize( "manual" )
        && PostSummaryPlacement::ABOVE_CONTENT === PostSummaryPlacement::normalize( "above_content" )
        && PostSummaryPlacement::BELOW_CONTENT === PostSummaryPlacement::normalize( "below_content" )
        && PostSummaryPlacement::MANUAL === PostSummaryPlacement::normalize( "invalid" )
        && str_contains( $settings, '"post_summary_placement" => "manual"' )
        && str_contains( $ajax, '"post_summary_placement"' ),
    "FAQ placement exposes Manual, Below content, and Below author." => PostFaqPlacement::MANUAL === PostFaqPlacement::normalize( "manual" )
        && PostFaqPlacement::BELOW_CONTENT === PostFaqPlacement::normalize( "below_content" )
        && PostFaqPlacement::BELOW_AUTHOR === PostFaqPlacement::normalize( "below_author" )
        && PostFaqPlacement::MANUAL === PostFaqPlacement::normalize( "invalid" )
        && str_contains( $ajax, '"post_faqs_placement"' ),
    "Automatic placement reuses one canonical generic engine." => str_contains( $placement, 'abstract class PostContentBlockPlacement' )
        && str_contains( $placement, 'contentPlacementRoot()' )
        && str_contains( $placement, 'authorCardContainers()' )
        && str_contains( $summary_placement, '$this->shortcodes->render_post_summary' )
        && str_contains( $faq_placement, '$this->shortcodes->render_post_faqs' ),
    "Both isolated placement modules are registered." => str_contains( $main, 'require_once __DIR__ . "/src/Content/PostContentBlockPlacement.php"' )
        && str_contains( $main, 'require_once __DIR__ . "/src/Content/PostSummaryPlacement.php"' )
        && str_contains( $main, 'require_once __DIR__ . "/src/Content/PostFaqPlacement.php"' )
        && str_contains( $bootstrap, 'new Content\PostSummaryPlacement()' )
        && str_contains( $bootstrap, 'new Content\PostFaqPlacement()' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: Summary and FAQ cards, shortcodes, colors, and placements share canonical rendering contracts.\n";
