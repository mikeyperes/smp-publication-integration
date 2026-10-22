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

/** Plugins tab: required, optional and forbidden plugins, plus Verified Profiles and other integrations. */
trait PluginsTab {

    private function plugins(): void {
        $renderer = new PluginInventoryRenderer();

        // Integrations: Verified Profiles (was its own tab) drives founder records on the Founders tab.
        echo '<div class="smpi-panel"><h2>Integrations</h2><p>Verified Profiles supplies founder profile records for the Founders tab. SMP still runs without it.</p>';
        $this->plugin_table( [ 'smp-verified-profiles/initialization.php' => PluginRegistry::info( 'smp-verified-profiles/initialization.php' ) ] );
        echo '</div>';

        echo $renderer->render_group(
            [
                [
                    'definitions' => PluginInventory::recommended_definitions(),
                    'args'        => PluginInventory::recommended_renderer_args(),
                ],
                [
                    'definitions' => PluginInventory::optional_definitions(),
                    'args'        => PluginInventory::optional_renderer_args(),
                ],
                [
                    'definitions' => PluginInventory::forbidden_definitions(),
                    'args'        => PluginInventory::forbidden_renderer_args(),
                ],
            ],
            [
                'title'       => 'Plugins',
                'description' => 'SMP required, optional and explicitly forbidden plugins.',
                'persist_key' => 'smpi-plugin-inventory',
                'open'        => true,
            ]
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function plugin_table( array $plugins ): void {
        echo "<table class=\"widefat striped smpi-plugin-registry\"><thead><tr><th>Plugin</th><th>Requirement</th><th>Status</th><th>Version</th><th>GitHub</th><th>Actions</th></tr></thead><tbody>";
        foreach ( $plugins as $file => $info ) {
            echo $this->plugin_row_html( (string) $file, $info );
        }
        echo "</tbody></table>";
    }

    private function plugin_row_html( string $file, array $info ): string {
        $status = ! empty( $info["active"] ) ? "<span class=\"smpi-ok\">Active</span>" : ( ! empty( $info["installed"] ) ? "<span class=\"smpi-warn\">Installed inactive</span>" : "<span class=\"smpi-bad\">Missing</span>" );
        $version = esc_html( (string) ( $info["version"] ?: "n/a" ) );
        if ( ! empty( $info["github_version"] ) ) {
            $version .= "<br><small>GitHub: " . esc_html( (string) $info["github_version"] ) . "</small>";
        }
        $repo = ! empty( $info["github_repo"] ) ? "<code>" . esc_html( "https://github.com/" . $info["github_repo"] ) . "</code>" : "n/a";
        return "<tr data-plugin-file=\"" . esc_attr( $file ) . "\"><td><strong>" . esc_html( (string) $info["label"] ) . "</strong><br><code>" . esc_html( $file ) . "</code></td><td>" . esc_html( (string) $info["type"] ) . "</td><td>" . $status . "</td><td>" . $version . "</td><td>" . $repo . "</td><td>" . $this->plugin_actions_html( $info ) . "<span class=\"spinner\"></span><span class=\"smpi-save-state\"></span></td></tr>";
    }

    private function plugin_actions_html( array $info ): string {
        $url = admin_url( "options-general.php?page=smp-publication-integration&tab=plugins" );
        return "<a class=\"button button-secondary\" href=\"" . esc_url( $url ) . "\">Manage in Plugins tab</a>";
    }
}
