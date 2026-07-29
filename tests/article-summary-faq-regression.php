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
$background = (string) file_get_contents( $root . "/src/Design/TemplateBackground.php" );

$checks = [
    "Summary design color uses the shared template color source contract." => str_contains( $registry, '"source_key" => "post_summary_color_source"' )
        && str_contains( $registry, '"custom_key" => "post_summary_accent_color"' )
        && str_contains( $dashboard, 'template_color_setting_html( "post_summary", "Summary design color"' )
        && str_contains( $ajax, 'TemplateDesignRegistry::source_setting_keys()' ),
    "Summary text color remains an independent shared typography control." => str_contains( $registry, '"font_color" => [ "key" => "post_summary_text_color"' )
        && str_contains( $registry, '"--smpi-summary-text"' )
        && str_contains( $article, "--smpi-summary-text" ),
    "Original Summary templates own canonical body typography on every surface." => str_contains( $article, '.smpi-post-summary{max-width:var(--content-width,720px);margin:0;color:var(--smpi-summary-text,#1f2937);font-family:var(--smpi-summary-font,Arial,Helvetica,sans-serif);font-size:var(--smpi-summary-size,16px);font-weight:var(--smpi-summary-weight,400);line-height:1.55}' )
        && str_contains( $article, '.smpi-post-summary .smpi-post-summary-title{font-family:inherit;line-height:inherit}' )
        && str_contains( $article, '.smpi-post-summary .smpi-post-summary-content,.smpi-post-summary .smpi-post-summary-content *{color:inherit;font-family:inherit;font-size:inherit;font-weight:inherit}' ),
    "Site text color inheritance excludes Summary title accents." => str_contains( $article, 'false !== strpos( $selector, "smpi-post-summary-content" )' )
        && str_contains( $dashboard, "Only mapped accents and tints change; typography stays unchanged." )
        && str_contains( $dashboard, "Original Template matches the preview." ),
    "Summary sum00 maps its title and underline colors." => str_contains( $article, ".smpi-sum00 .smpi-post-summary-title{margin:0;font-size:1.3rem;font-weight:800;color:var(--smpi-summary-accent,#1f2937)" )
        && str_contains( $article, "border-bottom:3px solid var(--smpi-summary-accent,#111827)" ),
    "Summary sum01 maps its original blue rule and title." => str_contains( $article, ".smpi-sum01{background:var(--smpi-summary-background,#fff);border:1px solid #e5e7eb;border-left:4px solid var(--smpi-summary-accent,#2563eb)" )
        && str_contains( $article, ".smpi-sum01 .smpi-post-summary-title{margin:0 0 10px;font-size:.78rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--smpi-summary-accent,#2563eb)" ),
    "Summary sum02 uses compact typography and a restrained accent border." => str_contains( $article, ".smpi-sum02{background:var(--smpi-summary-background,transparent);border:1px solid var(--smpi-summary-accent-soft,rgba(10,10,10,.12));border-top:3px solid var(--smpi-summary-accent,#0a0a0a);border-radius:4px" )
        && str_contains( $article, 'font-family:var(--smpi-summary-font,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,' )
        && str_contains( $article, 'sans-serif);font-size:14px' )
        && str_contains( $article, ".smpi-sum02 .smpi-post-summary-title{margin:0 0 10px;font-size:11px" ),
    "Summary sum03 maps its header and contrast text." => str_contains( $article, ".smpi-sum03 .smpi-post-summary-title{margin:0;background:var(--smpi-summary-accent,#0a0a0a);color:var(--smpi-summary-accent-ink,#fff)" ),
    "Summary sum04 uses a compact secondary-content treatment with mapped accents." => str_contains( $article, ".smpi-sum04{background:var(--smpi-summary-background,var(--smpi-summary-accent-soft,#eff4ff));border:1px solid rgba(15,23,42,.08);border-radius:6px;box-shadow:none;font-size:14px;line-height:1.45;padding:18px 20px}" )
        && str_contains( $article, ".smpi-sum04 .smpi-post-summary-title{align-items:center;color:var(--smpi-summary-accent,#1e3a8a);display:flex;font-size:12px;font-weight:700" )
        && str_contains( $article, '.smpi-sum04 .smpi-post-summary-title:before{background:var(--smpi-summary-accent,#2563eb);border-radius:2px;content:\"\";height:10px;width:10px}' )
        && str_contains( $article, ".smpi-sum04 .smpi-post-summary-item{line-height:1.45;margin:0 0 4px}" ),
    "Summary sum05 maps only its title and diamond bullet accents." => str_contains( $article, ".smpi-sum05{background:var(--smpi-summary-background,#fff);border:1px solid #d8dee8" )
        && str_contains( $article, ".smpi-sum05 .smpi-post-summary-title{align-items:center;color:var(--smpi-summary-accent,#00ff41)" )
        && str_contains( $article, ".smpi-sum05 .smpi-post-summary-item::before{border:1px solid var(--smpi-summary-accent,#00ff41)" )
        && ! str_contains( $article, ".smpi-sum05{background:var(--smpi-summary-accent" ),
    "Every Summary choice label states its visible treatment." => str_contains( $dashboard, '"label" => "Site content with no SMP styling"' )
        && str_contains( $dashboard, '"label" => "Gray panel with underlined heading"' )
        && str_contains( $dashboard, '"label" => "White card with left accent"' )
        && str_contains( $dashboard, '"label" => "Compact bullets with top rule"' )
        && str_contains( $dashboard, '"label" => "Solid-color header card"' )
        && str_contains( $dashboard, '"label" => "Soft-tint icon callout"' )
        && str_contains( $dashboard, '"label" => "Bordered block with diamond bullets"' ),
    "The Summary exposes a genuine Elementor stripped mode." => str_contains( $dashboard, '"unstyled" => [ "label" => "Strip all design (Elementor)"' )
        && str_contains( $article, 'data-smpi-skin' )
        && str_contains( $article, '.smpi-post-summary:not(.smpi-unstyled)' ),
    "Summary background reuses the Core picker and supports template, none, and custom modes." => str_contains( $dashboard, 'ColorControl::render( [' )
        && str_contains( $dashboard, '"key" => "post_summary_background_color"' )
        && str_contains( $dashboard, 'summary_background_setting_html( $settings )' )
        && str_contains( $background, 'self::TEMPLATE' )
        && str_contains( $background, 'self::NONE' )
        && str_contains( $background, 'self::CUSTOM' )
        && str_contains( $ajax, '"post_summary_background_mode"' )
        && str_contains( $ajax, "'post_summary_background_color'" ),
    "Summary and FAQ are independent feature cards." => str_contains( $dashboard, '"Article summary block",' )
        && str_contains( $dashboard, '"post_summary_acf_enabled",' )
        && str_contains( $dashboard, '"Article FAQ block",' )
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
