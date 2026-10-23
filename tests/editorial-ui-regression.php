<?php

declare(strict_types=1);
require_once __DIR__ . '/fixtures/dashboard-source.php';

$root       = dirname( __DIR__ );
$dashboard  = smpi_test_dashboard_source( $root );
$muckrack   = (string) file_get_contents( $root . '/src/Content/MuckRackVerification.php' );
$dashboard_css = (string) file_get_contents( $root . '/assets/admin/dashboard.css' );
$core_scoped_css = (string) file_get_contents( $root . '/lib/hexa-wordpress-plugin-core/src/WpAdminComponents/ScopedCssOverride.php' );
$core_ui_source = (string) file_get_contents( $root . "/lib/hexa-wordpress-plugin-core/src/WpAdminComponents/CoreUi.php" );
$core_version = trim( (string) file_get_contents( $root . '/lib/hexa-wordpress-plugin-core/VERSION' ) );
$breadcrumb_start = strpos( $dashboard, 'private function breadcrumb_controls_html' );
$breadcrumb_end = false !== $breadcrumb_start ? strpos( $dashboard, 'private function context_select_html', $breadcrumb_start ) : false;
$breadcrumb_flow = false !== $breadcrumb_start && false !== $breadcrumb_end ? substr( $dashboard, $breadcrumb_start, $breadcrumb_end - $breadcrumb_start ) : '';
$feature_card_start = strpos( $dashboard, 'private function feature_card(' );
$feature_card_end = false !== $feature_card_start ? strpos( $dashboard, 'private function feature_card_from_snippet', $feature_card_start ) : false;
$feature_card_source = false !== $feature_card_start && false !== $feature_card_end ? substr( $dashboard, $feature_card_start, $feature_card_end - $feature_card_start ) : '';
$elementor  = (string) file_get_contents( $root . '/src/Authorship/ElementorAuthorRenderer.php' );
$loop       = (string) file_get_contents( $root . '/src/Authorship/LoopBylineRenderer.php' );
$article_styles = (string) file_get_contents( $root . '/src/Content/ArticleStyles.php' );

$ajax = (string) file_get_contents( $root . '/src/Admin/Ajax/AjaxController.php' );
$settings_repository = (string) file_get_contents( $root . '/src/Settings/SettingsRepository.php' );

$checks = [
    'Every Features card defaults to the Hexa Core collapsible renderer.' => str_contains( $dashboard, 'bool $collapsible = true' )
        && str_contains( $dashboard, 'CoreUi::collapsible' )
        && str_contains( $dashboard, 'feature_brand_color_tools_html( $settings )' ),
    "Features expose the reusable Core search and group filter." => version_compare( $core_version, "0.19.54", ">=" )
        && str_contains( $dashboard, "CoreUi::collection_filter(" )
        && str_contains( $dashboard, "\"target_id\" => \"smpi-feature-collection\"" )
        && str_contains( $dashboard, "\"item_selector\" => \".smpi-feature-filter-item\"" )
        && str_contains( $dashboard, "\"group_selector\" => \".smpi-feature-group\"" )
        && str_contains( $dashboard, "\"text_selector\" => \":scope > summary, .smpi-feature-overview, .smpi-feature-settings\"" )
        && 2 === substr_count( $dashboard, "\"class\" => \"smpi-feature-filter-item\"" )
        && str_contains( $core_ui_source, "function initCollectionFilters(scope)" )
        && str_contains( $core_ui_source, "DOMContentLoaded" )
        && str_contains( $core_ui_source, "hexa-core-host-tab-loaded" ),
    'Every Features card is closed by default.' => str_contains( $dashboard, '"open" => false' )
        && ! str_contains( $dashboard, '"elementor_css_cache_busting" === $snippet_id' ),
    'Features are organized into four clear groups.' => str_contains( $dashboard, 'data-smpi-feature-group=' )
        && str_contains( $dashboard, '"Article design"' )
        && str_contains( $dashboard, '"Authors and verification"' )
        && str_contains( $dashboard, '"Content and distribution"' )
        && str_contains( $dashboard, '"System integrations"' )
        && strpos( $dashboard, 'render_article_design_feature_group' ) < strpos( $dashboard, 'render_author_feature_group' )
        && strpos( $dashboard, 'render_author_feature_group' ) < strpos( $dashboard, 'render_content_feature_group' )
        && strpos( $dashboard, 'render_content_feature_group' ) < strpos( $dashboard, 'render_system_feature_group' ),
    'Expanded feature cards put settings before collapsed reference and diagnostics.' => str_contains( $feature_card_source, 'smpi-feature-overview' )
        && str_contains( $feature_card_source, 'smpi-feature-settings' )
        && str_contains( $feature_card_source, 'CoreUi::detail_card(' )
        && str_contains( $feature_card_source, '"Implementation reference"' )
        && str_contains( $feature_card_source, '"Status and diagnostics"' )
        && strpos( $feature_card_source, '$overview_html' ) < strpos( $feature_card_source, '$settings_html' )
        && strpos( $feature_card_source, '$settings_html' ) < strpos( $feature_card_source, '$reference_html' )
        && strpos( $feature_card_source, '$reference_html' ) < strpos( $feature_card_source, '$diagnostics_html' ),
    'Complex feature guidance is retained outside primary settings.' => str_contains( $dashboard, 'multi_author_settings_html( $settings )' )
        && str_contains( $dashboard, 'multi_author_reference_html()' )
        && str_contains( $dashboard, '$this->author_muckrack_mode_help_html( $settings ) . $this->author_muckrack_shortcodes_html()' )
        && str_contains( $dashboard, '$this->shortcode_usage_html(' ),
    'Breadcrumbs left SMP for HWS Base Tools.' => ! is_readable( $root . '/src/Content/Breadcrumbs.php' ) && str_contains( $dashboard, 'Breadcrumbs moved to HWS Base Tools.' ),
    'Frontend injection creates an exact author and badge pair.' => str_contains( $muckrack, 'function pairBadge(el,node)' )
        && str_contains( $muckrack, '.smpi-muckrack-inline-pair{display:inline-flex;align-items:center' ),
    'Frontend injection does not promote author text to a card-wide link.' => str_contains( $muckrack, 'norm(link.textContent)===norm(el.textContent)' )
        && ! str_contains( $muckrack, 'el.closest("a[href]")||el' ),
    'Repeated placement passes do not append a second badge to a pair.' => str_contains( $muckrack, 'if(existing){if(hasBadge(existing))return false;' ),
    'Single-post placement ranks semantic author-name targets without requiring a link.' => str_contains( $muckrack, 'function nameTargetScore(el)' )
        && str_contains( $muckrack, 'function bestNameTarget(root,name)' )
        && str_contains( $muckrack, 'exactTextTargets(document,data.authorName).forEach' ),
    'Biography prose is excluded from author badge targeting.' => str_contains( $muckrack, 'function isProseNameTarget(el)' )
        && str_contains( $muckrack, '.smpi-author-bio,.elementor-author-box__bio,.elementor-author-box__text' )
        && ! str_contains( $muckrack, '.elementor-icon-list-text,.elementor-author-box__name,*' ),
    'Badge pairs override Elementor full-width links without collapsing author names.' => str_contains( $muckrack, '.smpi-muckrack-inline-pair>.smpi-muckrack-author-label{min-width:min-content;word-break:normal;overflow-wrap:normal}' )
        && str_contains( $muckrack, '.smpi-muckrack-inline-pair>.smpi-muckrack-link{width:auto!important;max-width:none}' ),
    'Elementor-cloned authors use the same pair contract.' => str_contains( $elementor, '$pair->setAttribute( "class", "smpi-muckrack-inline-pair" )' ),
    'Loop bylines use the same pair contract.' => str_contains( $loop, 'self::ITEM_CLASS . " smpi-muckrack-inline-pair"' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: Feature cards, breadcrumb styling, and editorial badges use shared structural contracts.\n";
