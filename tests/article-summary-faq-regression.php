<?php

declare(strict_types=1);

define( "ABSPATH", dirname( __DIR__ ) . "/" );

$root = dirname( __DIR__ );
require $root . "/src/Content/PostFaqPlacement.php";

use smp_publication_integration\Content\PostFaqPlacement;

$article = (string) file_get_contents( $root . "/src/Content/ArticleStyles.php" );
$dashboard = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );
$settings = (string) file_get_contents( $root . "/src/Settings/SettingsRepository.php" );
$ajax = (string) file_get_contents( $root . "/src/Admin/Ajax/AjaxController.php" );
$bootstrap = (string) file_get_contents( $root . "/src/Bootstrap/Plugin.php" );
$main = (string) file_get_contents( $root . "/smp-publication-integration.php" );
$placement = (string) file_get_contents( $root . "/src/Content/PostFaqPlacement.php" );

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
    "Key Points Card has square left corners in shared preview and frontend CSS." => str_contains( $article, ".smpi-sum01{border:1px solid #e5e7eb;border-left:4px solid var(--smpi-summary-accent,#2563eb);border-radius:0 12px 12px 0" ),
    "Numbered FAQs derive both solid and ghost numbers from the FAQ accent." => str_contains( $article, "--smpi-faq-accent-soft" )
        && str_contains( $article, ".smpi-post-faq-item:before,.smpi-faq03 .smpi-post-faq-item:after" )
        && str_contains( $article, "color:var(--smpi-faq-accent-soft" )
        && str_contains( $article, "color:var(--smpi-faq-accent,#2563eb)" )
        && str_contains( $article, ".smpi-choice-preview .smpi-faq03>.smpi-post-faqs-content:before{content:none!important}" )
        && ! str_contains( $dashboard, "#c7d6ff" )
        && ! str_contains( $dashboard, ".smpi-design-faq" ),
    "FAQ placement exposes Manual, Below content, and Below author." => PostFaqPlacement::MANUAL === PostFaqPlacement::normalize( "manual" )
        && PostFaqPlacement::BELOW_CONTENT === PostFaqPlacement::normalize( "below_content" )
        && PostFaqPlacement::BELOW_AUTHOR === PostFaqPlacement::normalize( "below_author" )
        && PostFaqPlacement::MANUAL === PostFaqPlacement::normalize( "invalid" )
        && str_contains( $dashboard, '"post_faqs_placement"' )
        && str_contains( $dashboard, '"preview" => "<code>[smp_post_faqs]</code>"' )
        && str_contains( $ajax, '"post_faqs_placement"' ),
    "Automatic FAQ placement reuses the canonical shortcode renderer." => str_contains( $placement, '$this->shortcodes->render_post_faqs' )
        && str_contains( $placement, 'data-smpi-faq-placement' )
        && str_contains( $placement, 'contentPlacementRoot()' )
        && str_contains( $placement, 'authorCardContainers()' ),
    "FAQ placement is registered as a normal isolated content module." => str_contains( $main, 'require_once __DIR__ . "/src/Content/PostFaqPlacement.php"' )
        && str_contains( $bootstrap, 'new Content\PostFaqPlacement()' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: Summary design colors and FAQ placement use one shared rendering contract.\n";
