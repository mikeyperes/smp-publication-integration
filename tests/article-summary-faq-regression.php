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

$checks = [
    "Summary uses a dedicated brand-derived design color." => str_contains( $settings, '"post_summary_accent_color" => $brand' )
        && str_contains( $settings, '"post_summary_accent_color" => $colors["post_summary_accent_color"]' )
        && str_contains( $dashboard, 'color_setting_html( "post_summary_accent_color", "Summary design color"' )
        && str_contains( $ajax, "'post_summary_accent_color'" ),
    "Legacy Summary text color is retained but no longer rendered or applied." => str_contains( $settings, '"post_summary_text_color"' )
        && ! str_contains( $dashboard, '"font_color" => [ "key" => "post_summary_text_color"' )
        && ! str_contains( $article, "--smpi-summary-text" ),
    "Every Summary template consumes the shared design color variables." => str_contains( $article, ".smpi-sum00" )
        && str_contains( $article, ".smpi-sum01" )
        && str_contains( $article, ".smpi-sum02" )
        && str_contains( $article, ".smpi-sum03" )
        && str_contains( $article, ".smpi-sum04" )
        && substr_count( $article, "--smpi-summary-accent" ) >= 12
        && str_contains( $article, "--smpi-summary-accent-soft" )
        && str_contains( $article, "--smpi-summary-accent-ink" ),
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
