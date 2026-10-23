<?php
namespace smp_publication_integration\Admin\Dashboard\Tabs;

use smp_publication_integration\Admin\CodeViews;
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\ActivityLog\ActivityLogConfig;
use Hexa\PluginCore\ActivityLog\ActivityLogRenderer;
use Hexa\PluginCore\PluginChecks\PluginInventoryRenderer;
use Hexa\PluginCore\SmartSearch\SmartSearchRenderer;
use Hexa\PluginCore\FieldStructures\FieldStructureRenderer;
use Hexa\PluginCore\FieldStructures\AcfFieldGroupRenderer;
use Hexa\PluginCore\ContentTypes\ContentTypeRenderer;
use Hexa\PluginCore\Taxonomies\TaxonomyRenderer;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;
use Hexa\PluginCore\SnippetRegistry\SnippetRegistry;
use Hexa\PluginCore\SnippetRegistry\SnippetsTableRenderer;
use Hexa\PluginCore\WpAdminTabs\HostTabsRenderer;
use Hexa\PluginCore\PluginUpdates\PluginUpdateStatus;
use Hexa\PluginCore\CorePackageUpdates\CorePackageStatus;
use Hexa\PluginCore\WpAdminTabs\TabDefinition;
use Hexa\PluginCore\WpAdminTabs\TabRegistry;
use Hexa\PluginCore\WpAdminComponents\ColorControl;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\TypographyControl;
use Hexa\PluginCore\WpAdminComponents\TemplateColorControl;
use Hexa\PluginCore\WpAdminComponents\ScopedCssOverride;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;
use Hexa\PluginCore\Typography\TypographyPreservation;
use Hexa\PluginCore\Typography\TemplateTypography;
use Hexa\PluginCore\SiteStructure\SiteStructureRenderer;
use Hexa\PluginCore\SchemaDetection\SchemaPageScanner;
use Hexa\PluginCore\SchemaDetection\SchemaScanRenderer;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Admin\QuickStartCleanupWorkflow;

use smp_publication_integration\Admin\Navigation\AdminNavigation;
use smp_publication_integration\Config;
use smp_publication_integration\Content\AcfFields;
use smp_publication_integration\Content\AuthorSocialIcons;
use smp_publication_integration\Content\AuthorArchiveLoading;
use smp_publication_integration\Content\MuckRackVerification;
use smp_publication_integration\Content\MultiAuthors;
use smp_publication_integration\Content\PublicationContentTypes;
use smp_publication_integration\Content\Schema;
use smp_publication_integration\Design\TemplateBackground;
use smp_publication_integration\Design\TemplateDesignRegistry;
use smp_publication_integration\Support\Dependencies;
use smp_publication_integration\Support\ArticleCleanup;
use smp_publication_integration\Support\Fields;
use smp_publication_integration\Support\PageStructure;
use smp_publication_integration\Support\PluginInventory;
use smp_publication_integration\Support\PluginRegistry;
use smp_publication_integration\Support\QuickStartFeatures;
use smp_publication_integration\Support\Settings;
use smp_publication_integration\Support\SnippetDefinitions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Small building blocks shared by several tabs (cards, toggles, status icons, activity log). */
trait DashboardHelpers {

    private function shortcode_usage_html( string $instructions, array $examples ): string {
        $html = "<div class=\"smpi-control-group smpi-shortcode-reference\"><h3>Shortcode and use</h3><p>" . esc_html( $instructions ) . "</p><div class=smpi-shortcode-list>";
        foreach ( $examples as $example ) {
            $label = (string) ( $example[0] ?? "Example" );
            $code = (string) ( $example[1] ?? "" );
            $html .= CodeViews::code( $code, "shortcode", $label );
        }
        return $html . "</div></div>";
    }


    private function feature_card( string $title, string $toggle_key, string $acf, string $description, string $code, string $test_report, string $activity_log, string $extra_controls = "", bool $collapsible = true, string $before_activity_html = "", string $reference_extra_html = "" ): void {
        $log_key = $toggle_key ?: sanitize_key( $title );
        $toggle_html = $toggle_key ? $this->inline_toggle_html( $toggle_key ) : "<span class=smpi-feature-managed>Managed in Custom Fields</span>";
        $meta_html = "" !== $toggle_key
            ? ( Settings::bool( $toggle_key ) ? CoreUi::pill( "Enabled", "success" ) : CoreUi::pill( "Disabled", "warning" ) )
            : CoreUi::pill( "Linked settings", "dark" );

        $overview_html = "<section class=smpi-feature-overview><div><h3>What it does</h3><p>" . esc_html( $description ) . "</p></div><div class=smpi-feature-overview-side>" . $toggle_html . "</div></section>";
        $settings_html = "";
        if ( "" !== $extra_controls ) {
            $settings_html = "<section class=smpi-feature-settings><header class=smpi-feature-settings-head><h3>Settings</h3></header><div class=smpi-feature-settings-body>" . $extra_controls . "</div></section>";
        }

        $reference_body = "<div class=smpi-feature-reference-grid><section><h4>Data and dependencies</h4><p>" . wp_kses_post( $acf ) . "</p></section><section><h4>Implementation</h4>" . CodeViews::code( $code, "text" ) . "</section></div>";
        if ( "" !== $reference_extra_html ) {
            $reference_body .= "<div class=smpi-feature-reference-extra>" . $reference_extra_html . "</div>";
        }
        $reference_html = CoreUi::detail_card(
            [
                "title" => "Implementation reference",
                "body_html" => $reference_body,
                "meta_html" => CoreUi::pill( "Reference", "dark" ),
                "class" => "smpi-feature-reference-panel",
                "open" => false,
            ]
        );
        $diagnostics_html = CoreUi::detail_card(
            [
                "title" => "Status and diagnostics",
                "body_html" => "<div class=smpi-feature-diagnostics>" . $test_report . "</div>",
                "meta_html" => CoreUi::pill( "Live status", "dark" ),
                "class" => "smpi-feature-diagnostics-panel",
                "open" => false,
            ]
        );
        $activity_log = $this->activity_log_html( $log_key );

        $html = "<article class=\"smpi-feature-card" . ( $collapsible ? " smpi-feature-card--core-collapsible" : "" ) . "\"><div class=\"smpi-feature-save-banner\" aria-live=\"polite\"></div>"
            . $overview_html
            . $settings_html
            . $reference_html
            . $diagnostics_html;
        if ( "" !== $before_activity_html ) {
            $html .= "<div class=smpi-feature-before-activity>" . $before_activity_html . "</div>";
        }
        $html .= "<div class=smpi-feature-activity>" . $activity_log . "</div></article>";

        if ( $collapsible ) {
            echo CoreUi::collapsible(
                [
                    "title" => $title,
                    "body_html" => $html,
                    "open" => false,
                    "meta_html" => $meta_html,
                    "class" => "smpi-feature-filter-item",
                ]
            );
            return;
        }

        echo $html;
    }


    private function hws_masked_login_status(): array {
        $opts = get_option( "hws_login_mask_options", [] );
        $slug = is_array( $opts ) && ! empty( $opts["slug"] ) ? sanitize_title( (string) $opts["slug"] ) : "hexa-admin";
        $enabled = is_array( $opts ) && ! empty( $opts["enabled"] ) && Dependencies::hws_base_tools_active();
        return [ "enabled" => $enabled, "slug" => $slug, "url" => home_url( "/" . $slug . "/" ), "hws_active" => Dependencies::hws_base_tools_active() ];
    }

    private function hws_masked_login_report_html(): string {
        $status = $this->hws_masked_login_status();
        return $this->simple_status_html( ! empty( $status["enabled"] ), "Masked login URL: " . $status["url"] ) . "<p><a class=\"button\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( $status["url"] ) . "\">Open masked admin URL</a></p><p><small>Owned by HWS Base Tools. SMP only reports this.</small></p>";
    }

    private function activity_log_html( string $feature_key = "" ): string {
        $entries = Settings::activity_entries();
        if ( "" !== $feature_key ) {
            $needle = sanitize_key( $feature_key );
            $entries = array_values( array_filter( $entries, static function ( object $entry ) use ( $needle ): bool {
                $data = method_exists( $entry, 'to_array' ) ? $entry->to_array() : [];
                return false !== strpos( sanitize_key( (string) ( $data["message"] ?? "" ) ), $needle );
            } ) );
        }

        $entries = array_slice( $entries, -5 );
        $id      = 'smpi-activity-log-' . ( $feature_key ? sanitize_key( $feature_key ) : 'general' );
        $config  = new ActivityLogConfig(
            [
                'id'          => $id,
                'title'       => 'Activity Log',
                'storage'     => ActivityLogConfig::STORAGE_PERMANENT,
                'storage_key' => 'smpi_activity_log',
                'max_entries' => 5,
                'collapsed'   => true,
                'dark'        => true,
            ]
        );

        ob_start();
        ( new ActivityLogRenderer( $config ) )->render( $entries );
        return (string) ob_get_clean();
    }

    private function counts(): array {
        $out = [];
        foreach ( [ 'post', 'page', 'profile', 'press-release' ] as $type ) {
            $count = wp_count_posts( $type );
            $out[ $type ] = $count && isset( $count->publish ) ? (int) $count->publish : 0;
        }
        return $out;
    }

    private function latest_post_id(): int {
        $query = new \WP_Query( [ "post_type" => "post", "post_status" => "publish", "posts_per_page" => 1, "fields" => "ids", "no_found_rows" => true ] );
        return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
    }
    private function toggle( string $key, string $label, array $settings ): void {
        echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input class="smpi-setting" type="checkbox" data-key="' . esc_attr( $key ) . '" value="1" ' . checked( ! empty( $settings[ $key ] ), true, false ) . '> Enabled</label><span class="spinner"></span><span class="smpi-save-state"></span></td></tr>';
    }

    private function card( string $title, string $content ): void {
        echo '<div class="smpi-card"><h3>' . esc_html( $title ) . '</h3><p>' . wp_kses_post( $content ) . '</p></div>';
    }
}
