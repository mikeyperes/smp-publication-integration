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

/** Publishing Rules tab (snippets). */
trait PublishingRulesTab {

    private function snippets(): void {
        $registry = new SnippetRegistry();
        foreach ( SnippetDefinitions::all() as $definition ) {
            $definition["default_enabled"] = Settings::bool( (string) ( $definition["option_key"] ?? $definition["id"] ) );
            $registry->add( $definition );
        }
        echo "<div class=\"smpi-section-intro\"><h2>Snippet implementation catalog</h2><p>Feature controls live in Editorial &gt; Features. This view contains implementation metadata and test status.</p></div>";
        echo ( new SnippetsTableRenderer() )->render( $registry, [
            "title"         => "Implementations",
            "description"   => "Hooks, code references, tests, and operational notes.",
            "ajax_url"      => admin_url( "admin-ajax.php" ),
            "test_action"   => "smpi_snippet_test",
            "nonce"         => Ajax::nonce(),
            "nonce_field"   => "nonce",
            "show_toggle"   => false,
            "categories"    => [
                "frontend-assets"    => [ "label" => "Front-end assets", "description" => "Asset URL and cache handling." ],
                "frontend-cleanup"   => [ "label" => "Front-end cleanup", "description" => "Removes empty or invalid rendered output." ],
                "admin-experience"   => [ "label" => "Admin experience", "description" => "Admin-only screen behaviors." ],
                "query-visibility"   => [ "label" => "Query & visibility", "description" => "Main-query inclusion and shadowing." ],
                "content-shortcodes" => [ "label" => "Content shortcodes", "description" => "Shortcode-based content helpers." ],
            ],
        ] );
    }
    private function feature_card_from_snippet( string $snippet_id, string $extra_report_html = "" ): void {
        $definition = SnippetDefinitions::definition( $snippet_id );
        if ( ! $definition ) {
            return;
        }

        $this->feature_card(
            $definition->name,
            $definition->option_key,
            "Snippet-backed feature. No custom ACF fields needed.",
            $definition->description,
            $this->snippet_code_text( $definition ),
            $this->snippet_report_html( $snippet_id ) . $extra_report_html,
            $this->activity_log_html(),
            "",
            true,
        );
    }

    private function snippet_code_text( $definition ): string {
        $lines = [];
        foreach ( $definition->snippets as $snippet ) {
            $label = isset( $snippet["label"] ) ? (string) $snippet["label"] : "Snippet";
            $value = isset( $snippet["value"] ) ? (string) $snippet["value"] : "";
            if ( "" !== $value ) {
                $lines[] = $label . ": " . $value;
            }
        }

        if ( "" !== $definition->readme ) {
            $lines[] = "";
            $lines[] = $definition->readme;
        }

        return implode( "\n", $lines );
    }

    private function snippet_report_html( string $snippet_id ): string {
        $test = SnippetDefinitions::registry()->test( $snippet_id );
        $status = (string) ( $test["status"] ?? "fail" );
        $message = (string) ( $test["message"] ?? "Snippet test did not return a message." );
        $html = $this->simple_status_html( "fail" !== $status, "Snippet registry: " . $message );
        $html .= "<ul class=smpi-status-rows>";
        foreach ( (array) ( $test["rules"] ?? [] ) as $rule ) {
            $passed = ! empty( $rule["passed"] );
            $html .= "<li class=smpi-status-row>" . self::ico( $passed ) . "<span>" . esc_html( (string) ( $rule["label"] ?? "Rule" ) ) . " - " . esc_html( (string) ( $rule["message"] ?? "" ) ) . "</span></li>";
        }
        return $html . "</ul>";
    }
}
