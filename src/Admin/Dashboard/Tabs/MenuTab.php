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

/** Menus tab. */
trait MenuTab {

    private function menu(): void {
        $manager = PageStructure::menu_manager();
        echo ( new SiteStructureRenderer(
            $manager,
            [
                'instance_id'                   => 'smpi-publication-menu',
                'nonce'                         => Ajax::nonce(),
                'card_class'                    => 'smpi-panel hpc-card',
                'table_class'                   => 'widefat striped hpc-table',
                'show_pages'                    => false,
                'show_menus'                    => true,
                'menu_sections'                 => [
                    'create_menus'       => true,
                    'custom_items'       => false,
                    'menu_structures'    => true,
                    'single_page_attach' => false,
                    'inventory'          => false,
                ],
                'menu_structure_layout'         => 'rows',
                'menu_structure_page_display'   => 'checkboxes',
                'menu_structure_show_parent'    => false,
                'actions'                       => PageStructure::ajax_actions(),
                'labels'                        => [
                    'menus_title'                 => 'Menu',
                    'menus_heading'               => 'Publication menu setup',
                    'menus_description'           => 'Create WordPress menus, then attach reusable publication page lists to existing menus.',
                    'create_menus_title'          => 'Create menus',
                    'create_menus_description'    => 'Create a WordPress menu for Legal, Editorial pages, Footer, or another site location.',
                    'menu_structures_title'       => 'Assign page lists to existing menus',
                    'menu_structures_description' => 'Attach the Legal or Editorial pages list to a selected WordPress menu. Unassigned pages stay visible but are skipped.',
                ],
            ]
        ) )->render();
    }
}
