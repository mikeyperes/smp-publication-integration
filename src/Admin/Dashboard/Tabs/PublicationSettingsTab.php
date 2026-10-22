<?php
namespace smp_publication_integration\Admin\Dashboard\Tabs;

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
use smp_publication_integration\Admin\FounderProfiles\FounderProfilePresenter;
use smp_publication_integration\Admin\QuickStartCleanupWorkflow;

use smp_publication_integration\Admin\Navigation\AdminNavigation;
use smp_publication_integration\Config;
use smp_publication_integration\Content\AcfFields;
use smp_publication_integration\Content\AuthorSocialIcons;
use smp_publication_integration\Content\AuthorArchiveLoading;
use smp_publication_integration\Content\Breadcrumbs;
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

/** Publication Settings tab: newsroom-only fields (Google News, MuckRack, RSS, podcast, coverage topics, area served). */
trait PublicationSettingsTab {


    /** Newsroom-only fields edited here; company facts are owned by HWS Base Tools. */
    private const NEWSROOM_FIELDS = [
        'field_smpi_google_news_url',
        AcfFields::PUBLICATION_MUCKRACK_VERIFIED_FIELD_KEY,
        AcfFields::PUBLICATION_MUCKRACK_URL_FIELD_KEY,
        'field_smpi_rss_feed',
        'field_smpi_has_podcast',
        'field_smpi_knows_about',
        'field_smpi_area_served',
    ];

    private function publication_options(): void {
        echo "<div class=smpi-hero><p class=smpi-kicker>Publication</p><h2>Publication Settings</h2><p>Newsroom details only: Google News, MuckRack, RSS, podcast, coverage topics and area served. Company facts come from HWS Base Tools.</p></div>";
        echo $this->company_facts_panel_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if ( ! Dependencies::fields_available() ) {
            echo "<div class=smpi-panel><p>The field layer is not available, so newsroom fields cannot be edited right now.</p></div>";
            return;
        }
        echo "<div class=smpi-panel><h2>Newsroom</h2>";
        \Hexa\PluginCore\Fields\Form::render( [ "post_id" => "option", "field_groups" => [ "group_smpi_publication_profile" ], "fields" => self::NEWSROOM_FIELDS, "form" => true, "submit_value" => "Save Publication Settings", "updated_message" => "Publication settings saved.", "return" => admin_url( "options-general.php?page=smp-publication-integration&tab=publication_options&updated=1" ) ] );
        echo "</div>";
    }

    /** Read-only company facts from HWS Base Tools, under Core's source banner with an edit link. */
    private function company_facts_panel_html(): string {
        $rows = [ 'name' => 'Name', 'legal_name' => 'Legal name', 'description' => 'Short description', 'founding_date' => 'Founding date', 'founding_location' => 'Founding city', 'founders' => 'Founders', 'headquarters_city' => 'Headquarters', 'parent_name' => 'Parent company', 'email' => 'Contact email', 'phone' => 'Phone', 'mission' => 'Mission' ];
        $html = "<div class=smpi-panel><h2>Company</h2>";
        $html .= \smp_publication_integration\Pages\SourceNotice::html();
        $html .= "<table class=\"widefat striped\"><tbody>";
        foreach ( $rows as $field => $label ) {
            $value = \smp_publication_integration\Pages\CompanyFacts::shortcode( [ 'field' => $field, 'format' => 'text' ] );
            $html .= "<tr><th style=\"width:200px\">" . esc_html( $label ) . "</th><td>" . ( '' !== trim( wp_strip_all_tags( $value ) ) ? $value : "<span class=smpi-muted>Not set</span>" ) . " <code>[smp_company field=" . esc_html( $field ) . "]</code></td></tr>";
        }
        return $html . "</tbody></table></div>";
    }

    private function publication_acf_shortcode_reference_html(): string {
        $fields = [];
        if ( \Hexa\PluginCore\Fields\Field::available() ) {
            $acf_fields = \Hexa\PluginCore\Fields\FieldGroups::fields( "group_smpi_publication_profile" );
            $fields = is_array( $acf_fields ) ? $acf_fields : [];
        }
        if ( empty( $fields ) && \Hexa\PluginCore\Fields\Field::available() ) {
            $group = \Hexa\PluginCore\Fields\FieldGroups::get_group( "group_smpi_publication_profile" );
            $fields = is_array( $group ) && isset( $group["fields"] ) && is_array( $group["fields"] ) ? $group["fields"] : [];
        }
        $html = "<div class=smpi-panel><h2>Publication Options ACF shortcode examples</h2><p>Every publication option field can be rendered with <code>[smp_publication_field]</code>. Repeater rows use <code>row=1</code> and <code>sub_field</code>.</p><table class=\"widefat smpi-publication-shortcode-table\"><thead><tr><th>Field</th><th>ACF name</th><th>Primary shortcode</th><th>Variations / parameters</th></tr></thead><tbody>";
        foreach ( $fields as $field ) {
            if ( empty( $field["name"] ) ) {
                continue;
            }
            $name = (string) $field["name"];
            $label = ! empty( $field["label"] ) ? (string) $field["label"] : $name;
            $variation = "[smp_publication_field field=" . $name . " format=text]";
            if ( isset( $field["type"] ) && "repeater" === $field["type"] && ! empty( $field["sub_fields"] ) && is_array( $field["sub_fields"] ) ) {
                $examples = [];
                foreach ( $field["sub_fields"] as $sub_field ) {
                    if ( ! empty( $sub_field["name"] ) ) {
                        $examples[] = "[smp_publication_field field=" . $name . " row=1 sub_field=" . $sub_field["name"] . "]";
                    }
                }
                $variation = implode( " ", $examples );
            } elseif ( "smpi_publication_user" === $name ) {
                $variation = "[smp_publication_user]";
            } elseif ( "smpi_publication_muckrack_verified" === $name ) {
                $variation = "[smp_publication_muckrack_verified] [smp_publication_field field=publication_muckrack_verified]";
            } elseif ( in_array( $name, [ "smpi_founder_users", "smpi_publication_logo" ], true ) ) {
                $variation = "[smp_publication_field field=" . $name . " format=json]";
            }
            $html .= "<tr><td>" . esc_html( $label ) . "</td><td><code>" . esc_html( $name ) . "</code></td><td><code>" . esc_html( "[smp_publication_field field=" . $name . "]" ) . "</code></td><td><code>" . esc_html( $variation ) . "</code></td></tr>";
        }
        return $html . "</tbody></table></div>";
    }
}
