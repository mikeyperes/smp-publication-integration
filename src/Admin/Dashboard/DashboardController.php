<?php
namespace smp_publication_integration\Admin\Dashboard;

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
use Hexa\PluginCore\SchemaDetection\SchemaPageScanner;
use Hexa\PluginCore\SchemaDetection\SchemaScanRenderer;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Admin\QuickStartCleanupWorkflow;
use smp_publication_integration\Admin\Dashboard\Tabs\PagesTab;
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

class DashboardController {
    use Tabs\ArticleCleanupTab;
    use Tabs\ArticleDesignTab;
    use Tabs\AuthorsTab;
    use Tabs\CustomPostTypesTab;
    use Tabs\DashboardAssets;
    use Tabs\DashboardHelpers;
    use Tabs\MenuTab;
    use Tabs\OverviewTab;
    use Tabs\PluginsTab;
    use Tabs\PostFieldsTab;
    use Tabs\PublicationSettingsTab;
    use Tabs\PublishingRulesTab;
    use Tabs\QuickStartTab;
    use Tabs\SchemaTab;
    use Tabs\SchemaTestsTab;
    use Tabs\ShortcodesTab;

    private const SCHEMA_DETECTION_CACHE_KEY = 'smpi_schema_detection_report';
    private const SCHEMA_DETECTION_REFRESH_LOCK_KEY = 'smpi_schema_detection_refresh_lock';

    public function register(): void {
        ArticleCleanup::register_ajax();
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function add_settings_page(): void {
        add_options_page( Config::$settings_page_name, Config::$settings_page_name, Config::$settings_page_capability, Config::$settings_page_slug, [ $this, 'render' ] );
    }

    public function enqueue_admin_assets(): void {
        $page = isset( $_GET["page"] ) ? sanitize_key( wp_unslash( $_GET["page"] ) ) : "";
        if ( Config::$settings_page_slug !== $page ) {
            return;
        }

        wp_enqueue_editor();
        wp_enqueue_media();
        wp_enqueue_style(
            'smpi-admin-navigation',
            plugins_url( 'assets/admin/dashboard.css', dirname( __DIR__, 3 ) . '/smp-publication-integration.php' ),
            [],
            Config::VERSION
        );
        wp_enqueue_style(
            'smpi-author-social-icons',
            plugins_url( 'assets/frontend/author-social-icons.css', dirname( __DIR__, 3 ) . '/smp-publication-integration.php' ),
            [ 'smpi-admin-navigation' ],
            Config::VERSION
        );

        if ( Dependencies::fields_available() ) {
            \Hexa\PluginCore\Fields\Form::enqueue();
        }
    }

    public function render(): void {
        if ( ! current_user_can( Config::$settings_page_capability ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'smp-publication-integration' ) );
        }
        $navigation = $this->navigation();
        $registry   = $this->tab_registry( $navigation );
        $tabs       = $registry->all();
        $requested  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
        $section    = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
        $route      = $navigation->resolve( $requested, $section );
        $active     = $route->section();
        $sidebar_identity = $this->sidebar_identity();
        if ( Dependencies::fields_available() ) {
            \Hexa\PluginCore\Fields\Form::head();
        }
        ?>
        <div class="wrap smpi-dashboard">
            <h1><?php echo esc_html( Config::$settings_page_display_title ); ?></h1>
            <?php $this->styles(); ?>
            <?php CoreUi::render_assets(); ?>
            <?php DynamicButton::render_assets(); ?>
            <?php
            ( new HostTabsRenderer() )->render(
                [
                    "tabs"            => $tabs,
                    "active"          => $active,
                    "page_url"        => admin_url( "options-general.php?page=" . Config::$settings_page_slug ),
                    "ajax_action"     => "smpi_load_tab",
                    "nonce"           => Ajax::nonce(),
                    "nonce_field"     => "nonce",
                    "root_id"         => "smpi-core-tabs",
                    "panel_id"        => "smpi-tab-panel",
                    "label"           => "SMP Publication Integration sections",
                    "layout"          => "sidebar",
                    "groups"          => $navigation->groups(),
                    "sidebar_identity" => $sidebar_identity,
                    "sidebar_collapsible" => true,
                    "sidebar_collapsed"   => false,
                    "sidebar_persist"     => true,
                    "render_callback" => function( string $tab ) use ( $registry ): void {
                        $this->render_registered_tab( $registry, $tab );
                    },
                ]
            );
            ?>
        </div>
        <?php $this->scripts(); ?>
        <?php
    }

    /**
     * @return array<string,string>
     */
    private function sidebar_identity(): array {
        $plugin_status = ( new PluginUpdateStatus( \smp_publication_integration\hexa_plugin_core_updater_config() ) )->get();
        $core_status   = ( new CorePackageStatus( \smp_publication_integration\hexa_plugin_core_package_config() ) )->get();

        return [
            "plugin_name"     => (string) ( $plugin_status["plugin_name"] ?? Config::$plugin_name ),
            "current_version" => (string) ( $plugin_status["current_version"] ?? Config::VERSION ),
            "github_version"  => (string) ( $plugin_status["latest_version"] ?? "Unknown" ),
            "github_url"      => (string) ( $plugin_status["github_url"] ?? "https://github.com/" . Config::$github_repo ),
            "core_name"       => "Hexa WP Core",
            "core_version"    => (string) ( $core_status["current_version"] ?? "Unknown" ),
            "core_github_url" => (string) ( $core_status["github_url"] ?? "https://github.com/mikeyperes/hexa-wordpress-plugin-core" ),
        ];
    }

    public function tab_fragment( string $id ): array {
        $navigation = $this->navigation();
        $registry   = $this->tab_registry( $navigation );
        $route      = $navigation->resolve( $id );
        $tab_id     = $route->section();
        $definition = $registry->get( $tab_id ) ?? $registry->get( 'overview' );

        if ( ! $definition instanceof TabDefinition ) {
            return [
                'tab'   => 'overview',
                'label' => 'Dashboard',
                'html'  => '',
            ];
        }

        $tab_id = $definition->id;

        ob_start();
        if ( 'publication_options' === $tab_id && Dependencies::fields_available() ) {
            \Hexa\PluginCore\Fields\Form::head();
        }
        $this->render_registered_tab( $registry, $tab_id );
        $html = ob_get_clean();

        return [
            'tab'   => $tab_id,
            'label' => $this->tab_label( $definition ),
            'html'  => is_string( $html ) ? $html : '',
        ];
    }

    private function navigation(): AdminNavigation {
        return new AdminNavigation();
    }

    private function tab_registry( ?AdminNavigation $navigation = null ): TabRegistry {
        $navigation = $navigation ?? $this->navigation();

        return $navigation->registry(
            function ( string $id ): void {
                $this->tab( $id );
            },
            Config::$settings_page_capability
        );
    }

    private function render_registered_tab( TabRegistry $registry, string $id ): void {
        $definition = $registry->get( $id ) ?? $registry->get( 'overview' );
        if ( ! $definition instanceof TabDefinition ) {
            return;
        }

        if (
            null !== $definition->capability
            && '' !== $definition->capability
            && ! current_user_can( $definition->capability )
        ) {
            echo '<div class="notice notice-error"><p>You do not have permission to view this section.</p></div>';
            return;
        }

        if ( is_callable( $definition->renderer ) ) {
            call_user_func( $definition->renderer );
        }
    }

    private function tab_label( TabDefinition $definition ): string {
        return $definition->label . ( $definition->deprecated ? ' (Deprecated)' : '' );
    }

    private function tab( string $id ): void {
        if ( 'pages' === $id ) { ( new PagesTab() )->render(); return; }
        if ( apply_filters( 'smpi_render_dashboard_tab', false, $id ) ) {
            return;
        }

        $tabs = [
            'publication_options' => 'publication_options',
            'shortcodes'          => 'shortcodes',
            'schema'              => 'schema',
            'reports'             => 'reports',
            'custom_fields'       => 'custom_fields',
            'custom_post_types'   => 'custom_post_types',
            'features'            => 'features',
            'multiple_authors'    => 'multiple_authors',
            'snippets'            => 'snippets',
            'menu'                => 'menu',
            'plugins'             => 'plugins',
            'article_cleanup'     => 'article_cleanup',
            'quick_run'           => 'quick_run',
        ];
        $method = $tabs[ $id ] ?? 'overview';
        $this->{$method}();
    }
}
