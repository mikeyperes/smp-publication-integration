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

/** Schema Settings tab and its detection report. */
trait SchemaTab {


    private function article_type_selector_options_html(): string {
        $html = "<div class=smpi-control-group><h3>Allowed article type schema objects</h3><table class=widefat><thead><tr><th>Editor choice</th><th>Term slug</th><th>Schema object</th><th>Use case</th></tr></thead><tbody>";
        foreach ( \smp_publication_integration\Content\ArticleTypes::terms() as $slug => $config ) {
            $html .= "<tr><td>" . esc_html( $config["label"] ) . "</td><td><code>" . esc_html( $slug ) . "</code></td><td><code>" . esc_html( $config["schema_type"] ) . "</code></td><td>" . esc_html( $config["description"] ) . "</td></tr>";
        }
        return $html . "</tbody></table></div>";
    }

    private function article_type_selector_report_html(): string {
        $enabled = \smp_publication_integration\Content\ArticleTypes::is_enabled();
        $registered = taxonomy_exists( \smp_publication_integration\Content\ArticleTypes::TAXONOMY );
        $post_types = \smp_publication_integration\Content\ArticleTypes::supported_post_types();
        $html = $this->simple_status_html( ! $enabled || $registered, $enabled ? "Enabled: the taxonomy is registered and uses the radio-only metabox." : "Disabled: the Article Types metabox is hidden and schema falls back to post type defaults." );
        $html .= "<table class=widefat><tbody>";
        $html .= "<tr><th>Setting key</th><td><code>article_types_enabled</code></td></tr>";
        $html .= "<tr><th>Taxonomy</th><td><code>" . esc_html( \smp_publication_integration\Content\ArticleTypes::TAXONOMY ) . "</code></td></tr>";
        $html .= "<tr><th>Registered now</th><td>" . esc_html( $registered ? "yes" : "no" ) . "</td></tr>";
        $html .= "<tr><th>Post types</th><td><code>" . esc_html( implode( ", ", $post_types ) ) . "</code></td></tr>";
        $html .= "<tr><th>Editor behavior</th><td>One radio selection only. No Add field. No free-text term creation.</td></tr>";
        return $html . "</tbody></table>";
    }



    private function article_type_selector_report_text(): string {
        $enabled = \smp_publication_integration\Content\ArticleTypes::is_enabled();
        $registered = taxonomy_exists( \smp_publication_integration\Content\ArticleTypes::TAXONOMY );
        $post_types = \smp_publication_integration\Content\ArticleTypes::supported_post_types();
        return "Article type selector is " . ( $enabled ? "enabled" : "disabled" ) . "; taxonomy registered: " . ( $registered ? "yes" : "no" ) . "; post types: " . implode( ", ", $post_types ) . ".";
    }

    private function schema(): void {
        $schema = new Schema();
        $post_id = $this->latest_post_id();
        $home_schema = $schema->generate_home_schema_array();
        $single_schema = $post_id ? $schema->generate_single_schema_array( $post_id ) : [];
        $home_report = Schema::integrity_report( 0 );
        $single_report = $post_id ? Schema::integrity_report( $post_id ) : [ "types" => [], "checks" => [] ];
        $debug_url = rest_url( "smpi/v1/schema" );
        $single_debug_url = $post_id ? add_query_arg( "post_id", $post_id, $debug_url ) : $debug_url;
        $article_types_enabled = \smp_publication_integration\Content\ArticleTypes::is_enabled();
        $article_types_taxonomy_ready = taxonomy_exists( \smp_publication_integration\Content\ArticleTypes::TAXONOMY );

        $run_panel = "<h3>Run Schema Integrity Test</h3><p><button id=\"smpi-reprocess-schema\" type=\"button\" class=\"button button-primary\">Run schema refresh and sample test</button></p><p><a class=\"button\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( $debug_url ) . "\">Open home schema JSON</a> " . ( $post_id ? "<a class=\"button\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( $single_debug_url ) . "\">Open latest post schema JSON</a>" : "" ) . "</p><div id=\"smpi-schema-report\" class=\"smpi-code-panel\"></div>";

        $article_type_map = "<h3>Article Type Taxonomy Mapping</h3><table class=\"widefat striped\"><thead><tr><th>Term</th><th>Schema Type</th><th>Use Case</th></tr></thead><tbody>";
        foreach ( \smp_publication_integration\Content\ArticleTypes::terms() as $slug => $config ) {
            $article_type_map .= "<tr><td><code>" . esc_html( $slug ) . "</code></td><td><code>" . esc_html( $config["schema_type"] ) . "</code></td><td>" . esc_html( $config["description"] ) . "</td></tr>";
        }
        $article_type_map .= "</tbody></table>";

        $ideal_home = [ "@context" => "https://schema.org", "@graph" => [ [ "@type" => "NewsMediaOrganization" ], [ "@type" => "WebSite" ], [ "@type" => "CollectionPage" ], [ "@type" => "ItemList" ] ] ];
        $ideal_single = [ "@context" => "https://schema.org", "@graph" => [ [ "@type" => "NewsMediaOrganization" ], [ "@type" => "WebSite" ], [ "@type" => "WebPage" ], [ "@type" => "NewsArticle" ], [ "@type" => "Person" ], [ "@type" => "ImageObject" ], [ "@type" => "BreadcrumbList" ], [ "@type" => "FAQPage" ] ] ];

        if ( class_exists( "\\Hexa\\PluginCore\\SchemaTools\\SchemaDashboardRenderer" ) ) {
            $actions = [
                [ "label" => "Open home schema JSON", "url" => $debug_url ],
            ];
            if ( $post_id ) {
                $actions[] = [ "label" => "Open latest post schema JSON", "url" => $single_debug_url ];
                if ( class_exists( "\\Hexa\\PluginCore\\SchemaTools\\SchemaGraph" ) ) {
                    $actions[] = [ "label" => "Open Schema Validator", "url" => \Hexa\PluginCore\SchemaTools\SchemaGraph::validator_url( (string) get_permalink( $post_id ) ) ];
                }
            }

            $graphs = [
                [ "title" => "Ideal Home Graph", "schema" => $ideal_home ],
                [ "title" => "Actual Home Graph", "schema" => $home_schema ],
            ];
            if ( $post_id ) {
                $graphs[] = [ "title" => "Ideal Single Graph", "schema" => $ideal_single ];
                $graphs[] = [ "title" => "Actual Latest Single Graph", "schema" => $single_schema ];
            }

            echo ( new \Hexa\PluginCore\SchemaTools\SchemaDashboardRenderer() )->render(
                [
                    "kicker" => "Schema",
                    "title" => "Publication and article schema integrity",
                    "description" => "Home uses NewsMediaOrganization, WebSite, CollectionPage, and ItemList. Singles use WebPage, article schema, publisher, author, image, breadcrumbs, and FAQPage when structured FAQ rows exist.",
                    "status_cards" => [
                        [ "title" => "Home schema graph", "ok" => in_array( "NewsMediaOrganization", $home_report["types"], true ) && in_array( "CollectionPage", $home_report["types"], true ), "body" => "Types: " . implode( ", ", $home_report["types"] ) ],
                        [ "title" => "Single schema graph", "ok" => $post_id && in_array( "WebPage", $single_report["types"], true ), "body" => $post_id ? "Latest post #" . $post_id . " types: " . implode( ", ", $single_report["types"] ) : "No published post found." ],
                        [ "title" => "Article type selector", "ok" => ! $article_types_enabled || $article_types_taxonomy_ready, "body" => $article_types_enabled ? "Enabled and taxonomy registered." : "Feature disabled; schema falls back by post type." ],
                        [ "title" => "Debug JSON URL", "ok" => true, "body" => $debug_url ],
                    ],
                    "actions" => $actions,
                    "html_sections" => [ $run_panel, $article_type_map ],
                    "integrity_sections" => [
                        [ "title" => "Home Page Integrity", "checks" => $home_report["checks"] ],
                        [ "title" => "Single Page Integrity", "checks" => $single_report["checks"] ],
                    ],
                    "graphs" => $graphs,
                    "scan_report_html" => $this->schema_detection_report_html(),
                ]
            );
            return;
        }

        echo "<div class=\"smpi-hero\"><p class=\"smpi-kicker\">Schema</p><h2>Publication and article schema integrity</h2><p>HexaWP Core schema dashboard renderer is unavailable.</p></div>";
        echo $run_panel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function schema_detection_report_html(): string {
        $cached = get_transient( self::SCHEMA_DETECTION_CACHE_KEY );
        if ( is_array( $cached ) && isset( $cached["scans"] ) && is_array( $cached["scans"] ) ) {
            $html = $this->render_schema_detection_report( $cached["scans"] );
            $generated_at = isset( $cached["generated_at"] ) ? (int) $cached["generated_at"] : 0;
            if ( $generated_at > 0 ) {
                $html .= "<p class=\"smpi-muted\">Schema detection cache generated " . esc_html( human_time_diff( $generated_at, time() ) ) . " ago. The report refreshes in the background and no longer blocks this tab.</p>";
            }

            self::schedule_schema_detection_refresh();
            return $html;
        }

        self::schedule_schema_detection_refresh();
        return "<div class=\"smpi-panel\"><h2>Hexa Core Schema Detection</h2><p>Schema detection is queued for background refresh. This tab no longer fetches the homepage and recent posts during page render.</p><p class=\"smpi-muted\">Refresh this tab after a minute to view the cached scan.</p></div>";
    }

    public static function refresh_schema_detection_report(): void {
        $scanner = new SchemaPageScanner();
        $scans = [];

        $scans[] = $scanner->scanUrl( home_url( "/" ), [
            "title"      => "Homepage",
            "cache_bust" => true,
            "timeout"    => 5,
        ] );

        foreach ( self::recent_schema_post_ids( 10 ) as $post_id ) {
            $title = get_the_title( $post_id );
            $scans[] = $scanner->scanUrl( get_permalink( $post_id ), [
                "title"      => "Post #" . $post_id . ( "" !== $title ? ": " . $title : "" ),
                "cache_bust" => true,
                "timeout"    => 5,
            ] );
        }

        set_transient(
            self::SCHEMA_DETECTION_CACHE_KEY,
            [
                "generated_at" => time(),
                "scans"        => $scans,
            ],
            6 * HOUR_IN_SECONDS
        );
        delete_transient( self::SCHEMA_DETECTION_REFRESH_LOCK_KEY );
    }

    private function render_schema_detection_report( array $scans ): string {
        return ( new SchemaScanRenderer() )->renderReport( $scans, [
            "title"    => "Hexa Core Schema Detection",
            "subtitle" => "Scans the homepage and the 10 most recent published posts through Hexa WordPress Plugin Core.",
            "expected" => [
                "Homepage: NewsMediaOrganization, WebSite, CollectionPage, and ItemList.",
                "Posts: NewsMediaOrganization, WebSite, WebPage, article schema, author, image, BreadcrumbList, and FAQPage when article FAQ rows are enabled.",
            ],
            "debug"    => false,
        ] );
    }

    private static function schedule_schema_detection_refresh(): void {
        if ( false !== get_transient( self::SCHEMA_DETECTION_REFRESH_LOCK_KEY ) ) {
            return;
        }

        if ( ! wp_next_scheduled( "smpi_refresh_schema_detection_report" ) ) {
            wp_schedule_single_event( time() + MINUTE_IN_SECONDS, "smpi_refresh_schema_detection_report" );
        }

        set_transient( self::SCHEMA_DETECTION_REFRESH_LOCK_KEY, "1", 15 * MINUTE_IN_SECONDS );
    }

    private static function recent_schema_post_ids( int $limit = 10 ): array {
        $query = new \WP_Query( [
            "post_type"      => "post",
            "post_status"    => "publish",
            "posts_per_page" => max( 1, $limit ),
            "fields"         => "ids",
            "no_found_rows"  => true,
        ] );

        return array_map( "intval", (array) $query->posts );
    }
}
