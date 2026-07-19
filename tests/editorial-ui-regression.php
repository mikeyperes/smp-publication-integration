<?php

declare(strict_types=1);

$root       = dirname( __DIR__ );
$dashboard  = (string) file_get_contents( $root . '/src/Admin/Dashboard/DashboardController.php' );
$muckrack   = (string) file_get_contents( $root . '/src/Content/MuckRackVerification.php' );
$dashboard_css = (string) file_get_contents( $root . '/assets/admin/dashboard.css' );
$core_scoped_css = (string) file_get_contents( $root . '/lib/hexa-wordpress-plugin-core/src/WpAdminComponents/ScopedCssOverride.php' );
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
$breadcrumbs = (string) file_get_contents( $root . '/src/Content/Breadcrumbs.php' );
$ajax = (string) file_get_contents( $root . '/src/Admin/Ajax/AjaxController.php' );
$settings_repository = (string) file_get_contents( $root . '/src/Settings/SettingsRepository.php' );

$checks = [
    'Every Features card defaults to the Hexa Core collapsible renderer.' => str_contains( $dashboard, 'bool $collapsible = true' )
        && str_contains( $dashboard, 'CoreUi::collapsible' )
        && str_contains( $dashboard, 'feature_brand_color_tools_html( $settings )' ),
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
        && str_contains( $dashboard, '$this->post_content_blocks_shortcode_reference_html()' ),    'Breadcrumb background is saved and applied through one scoped CSS variable.' => str_contains( $dashboard, 'breadcrumbs_background_color' )
        && str_contains( $article_styles, '--smpi-bc-background' )
        && str_contains( $article_styles, 'background:var(--smpi-bc-background,#fff)' ),
    'Breadcrumb background owns a full-width band and bc-b6 has no divider.' => str_contains( $breadcrumbs, 'smpi-breadcrumbs-band' )
        && str_contains( $article_styles, '.smpi-breadcrumbs-band{background:var(--smpi-bc-background,#fff);box-sizing:border-box;clear:both;margin:0;max-width:none;width:100%}' )
        && str_contains( $article_styles, '.smpi-bc-b6{max-width:var(--content-width,1140px);padding:14px 0;border-bottom:0}' )
        && ! str_contains( $article_styles, '.smpi-bc-b6{max-width:var(--content-width,1140px);padding:14px 0;border-bottom:1px' )
        && str_contains( $dashboard, '.smpi-breadcrumbs-band,.smpi-breadcrumbs' ),
    'Breadcrumb controls render in Template, Appearance, Visibility order.' => str_contains( $dashboard, '$this->breadcrumb_controls_html( $settings )' )
        && str_contains( $breadcrumb_flow, 'smpi-breadcrumb-flow' )
        && strpos( $breadcrumb_flow, '"Template"' ) < strpos( $breadcrumb_flow, '"Appearance"' )
        && strpos( $breadcrumb_flow, '"Appearance"' ) < strpos( $breadcrumb_flow, '"Visibility"' ),
    'All breadcrumb visibility settings share the Visibility section.' => str_contains( $breadcrumb_flow, 'breadcrumbs_hide_home' )
        && str_contains( $breadcrumb_flow, 'breadcrumbs_hide_term_archives' )
        && str_contains( $breadcrumb_flow, 'breadcrumbs_disabled_post_types' )
        && str_contains( $breadcrumb_flow, '$this->breadcrumb_section_html( "visibility", "03", "Visibility", $visibility )' ),
    'Breadcrumb templates and exclusions use compact responsive grids.' => str_contains( $dashboard_css, '.smpi-breadcrumb-section--template' )
        && str_contains( $dashboard_css, '.smpi-breadcrumb-section--template .smpi-control-group:has(input[data-key="breadcrumbs_style"]) .smpi-choice-grid{grid-template-columns:minmax(0,1fr)}' )
        && str_contains( $dashboard_css, '.smpi-breadcrumb-section--visibility .smpi-choice-list' )
        && str_contains( $dashboard_css, '@media(max-width:782px)' ),
    'Breadcrumb CSS override imports the generic Hexa Core component.' => version_compare( $core_version, '0.19.48', '>=' )
        && str_contains( $dashboard, 'use Hexa\PluginCore\WpAdminComponents\ScopedCssOverride;' )
        && str_contains( $dashboard, 'return ScopedCssOverride::render(' )
        && str_contains( $core_scoped_css, 'final class ScopedCssOverride' )
        && str_contains( $core_scoped_css, "CoreUi::detail_card(" ),
    'Breadcrumb CSS override is scoped, formatted, and closed by default.' => str_contains( $dashboard, 'body .smpi-breadcrumbs-band' )
        && str_contains( $dashboard, '"html_example" => $html_example' )
        && str_contains( $dashboard, '"css_example"  => $css_example' )
        && str_contains( $dashboard, '"open"         => false' ),
    'Breadcrumb CSS override is editable, validated, saved, and emitted after template CSS.' => str_contains( $core_scoped_css, 'data-hpc-scoped-css-input' )
        && str_contains( $dashboard, '"setting_key"  => Breadcrumbs::CSS_SETTING' )
        && str_contains( $dashboard, '"input_class"  => "smpi-setting"' )
        && str_contains( $breadcrumbs, 'public static function validate_custom_css' )
        && str_contains( $breadcrumbs, 'CSS_SCOPE_MARKER' )
        && str_contains( $breadcrumbs, 'LEGACY_CSS_SCOPE_MARKER' )
        && str_contains( $ajax, 'Breadcrumbs::validate_custom_css' )
        && str_contains( $settings_repository, '"breadcrumbs_css_override"' )
        && str_contains( $article_styles, 'Breadcrumbs::custom_css()' ),
    'Breadcrumb CSS override renders immediately before Activity Log.' => str_contains( $feature_card_source, '$before_activity_html' )
        && str_contains( $feature_card_source, 'smpi-feature-before-activity' )
        && strpos( $feature_card_source, 'smpi-feature-before-activity' ) < strpos( $feature_card_source, 'smpi-feature-activity' )
        && str_contains( $dashboard_css, '.smpi-feature-before-activity{grid-column:1/-1;min-width:0}' ),
    'Frontend injection creates an exact author and badge pair.' => str_contains( $muckrack, 'function pairBadge(el,node)' )
        && str_contains( $muckrack, '.smpi-muckrack-inline-pair{display:inline-flex;align-items:center' ),
    'Frontend injection does not promote author text to a card-wide link.' => str_contains( $muckrack, 'norm(link.textContent)===norm(el.textContent)' )
        && ! str_contains( $muckrack, 'el.closest("a[href]")||el' ),
    'Repeated placement passes do not append a second badge to a pair.' => str_contains( $muckrack, 'if(existing){if(hasBadge(existing))return false;' ),
    'Single-post header placement supports exact author text without requiring a link.' => str_contains( $muckrack, 'exactTextTargets(document,data.authorName).forEach' )
        && str_contains( $muckrack, 'link===el||link.contains(el)||el.contains(link)' ),
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
