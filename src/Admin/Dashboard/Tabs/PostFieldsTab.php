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

/** Post Fields tab: post field groups (summary, FAQs) and their reports. */
trait PostFieldsTab {

    private function post_content_block_required_field_html( string $block ): string {
        $definitions = [
            "summary" => [
                "setting" => "post_summary_acf_enabled",
                "field_key" => "field_65ab7ba0e849b",
                "label" => "Article Summary",
            ],
            "faqs" => [
                "setting" => "post_faqs_acf_enabled",
                "field_key" => "field_smpi_post_faq_items",
                "label" => "Structured FAQ",
            ],
        ];
        $definition = $definitions[ $block ] ?? $definitions["summary"];
        $enabled = Settings::bool( $definition["setting"] );
        $registered = \Hexa\PluginCore\Fields\Field::available() && (bool) \Hexa\PluginCore\Fields\FieldGroups::get_field( $definition["field_key"] );
        $ready = $enabled && $registered;
        $custom_fields_url = admin_url( "options-general.php?page=smp-publication-integration&tab=custom_fields" );

        $html = "<div class=\"smpi-control-group\"><h3>Required editor field</h3>";
        if ( $ready ) {
            $html .= $this->simple_status_html( true, $definition["label"] . " is ready on supported article editors." );
        } else {
            $html .= "<div class=\"smpi-alert smpi-alert-warning\"><strong>Editor field not ready.</strong><p>Enable this feature, then reload the page. Open Custom Fields to review the shared editor-field registration.</p><p><a class=\"button button-secondary\" href=\"" . esc_url( $custom_fields_url ) . "\">Open Custom Fields</a></p></div>";
        }

        return $html . "<div class=\"smpi-status-rows\"><div class=\"smpi-status-row\">" . self::ico( $ready, true ) . "<span>" . esc_html( $definition["label"] ) . " editor field: " . esc_html( $ready ? "Ready" : "Not ready" ) . "</span></div></div></div>";
    }

    private function custom_fields(): void {
        $settings = Settings::all();
        $acf_active = Dependencies::fields_available();
        $post_header_group_registered = function(): bool {
            return \Hexa\PluginCore\Fields\Field::available() && ( (bool) \Hexa\PluginCore\Fields\FieldGroups::get_group( "group_64a7290b61191" ) || (bool) \Hexa\PluginCore\Fields\FieldGroups::get_group( "group_smpi_post_faqs" ) );
        };
        $visibility_group_registered = function(): bool {
            return \Hexa\PluginCore\Fields\Field::available() && (bool) \Hexa\PluginCore\Fields\FieldGroups::get_group( "group_smpi_visibility_controls" );
        };

        $definitions = [
            [
                "id" => "publication_options_acf",
                "label" => "Publication Options ACF",
                "type" => "acf",
                "enabled" => true,
                "registered" => function() use ( $acf_active ): bool { return $acf_active && (bool) \Hexa\PluginCore\Fields\FieldGroups::get_group( "group_smpi_publication_profile" ); },
                "acf_group_key" => "group_smpi_publication_profile",
                "object_name" => "smp-publication-integration options page",
                "location" => "ACF options page: smp-publication-integration",
                "description" => "Main publication profile fields, schema policy references, contact points, and fallback publication identity fields.",
                "instructions" => "Edit these fields from the Publication Options tab. They stay always on because SMP schema and shortcodes depend on them.",
                "fields" => [ "smpi_publication_user", "smpi_publication_summary", "smpi_publication_logo", "schema policy pages", "contactPoint", "postalAddress" ],
                "dependencies" => [ "ACF Pro", "SMP publication options page" ],
                "code_example" => "[smp_publication_field field=legal_name format=text]",
                "test_report" => "Custom fields run on " . \Hexa\PluginCore\Fields\Acf::mode() . "; the publication options group should be registered on the options page.",
            ],
            [
                "id" => "post_summary_acf",
                "label" => "Article Summary Editor Field",
                "type" => "acf",
                "setting_key" => "post_summary_acf_enabled",
                "enabled" => Settings::bool( "post_summary_acf_enabled" ),
                "registered" => function(): bool { return Settings::bool( "post_summary_acf_enabled" ) && \Hexa\PluginCore\Fields\Field::available() && (bool) \Hexa\PluginCore\Fields\FieldGroups::get_field( "field_65ab7ba0e849b" ); },
                "acf_group_key" => "group_64a7290b61191",
                "object_name" => "post_summary",
                "location" => "post, press-release, knowledge-base, and resources editors",
                "description" => "Optional article summary field used by shortcodes and single article treatments.",
                "instructions" => "Enable this when editors need a reusable post summary separate from the article body. Styling controls remain in Features.",
                "fields" => [ "post_summary" ],
                "dependencies" => [ "ACF Pro", "What to Know local field group" ],
                "code_example" => "[smp_post_summary style=\"sum00\"]",
                "test_report" => $this->post_acf_addons_report_text(),
            ],
            [
                "id" => "post_faq_acf",
                "label" => "Structured FAQ Editor Fields",
                "type" => "acf",
                "setting_key" => "post_faqs_acf_enabled",
                "enabled" => Settings::bool( "post_faqs_acf_enabled" ),
                "registered" => function(): bool { return Settings::bool( "post_faqs_acf_enabled" ) && \Hexa\PluginCore\Fields\Field::available() && (bool) \Hexa\PluginCore\Fields\FieldGroups::get_field( "field_smpi_post_faq_items" ); },
                "acf_group_key" => "group_smpi_post_faqs",
                "object_name" => "post_faq_items",
                "location" => "post, press-release, knowledge-base, and resources editors",
                "description" => "Structured FAQ rows that power FAQPage schema and display shortcodes.",
                "instructions" => "Enable this when article editors need repeatable question and answer rows. Schema rows can be disabled one by one in the editor.",
                "fields" => [ "question", "answer", "enabled_for_schema" ],
                "dependencies" => [ "ACF Pro", "FAQPage schema output", "SMP post FAQ shortcode" ],
                "code_example" => "[smp_post_faqs style=\"faq02\"]",
                "test_report" => $this->post_acf_addons_report_text(),
            ],
            [
                "id" => "shadow_visibility_acf",
                "label" => "Post Shadow Visibility ACF",
                "type" => "acf",
                "setting_key" => "shadow_posts_enabled",
                "enabled" => Settings::bool( "shadow_posts_enabled" ),
                "registered" => function() use ( $visibility_group_registered ): bool { return Settings::bool( "shadow_posts_enabled" ) && $visibility_group_registered(); },
                "acf_group_key" => "group_smpi_visibility_controls",
                "object_name" => "_smpi_shadow_complete, _smpi_shadow_home",
                "location" => "post and press-release side metabox",
                "description" => "Editor controls that hide posts from archive queries while keeping direct URLs accessible.",
                "instructions" => "Enable this for editorial shadowing controls. The front end still allows direct single URLs.",
                "fields" => [ "_smpi_shadow_complete", "_smpi_shadow_home" ],
                "dependencies" => [ "ACF Pro", "pre_get_posts archive filters" ],
                "code_example" => 'get_field("_smpi_shadow_complete", $post_id)',
                "test_report" => "Shadow posts setting is " . ( Settings::bool( "shadow_posts_enabled" ) ? "enabled" : "disabled" ) . ".",
            ],
            [
                "id" => "press_release_visibility_acf",
                "label" => "Press Release Visibility ACF",
                "type" => "acf",
                "setting_key" => "press_release_include_enabled",
                "enabled" => Settings::bool( "press_release_include_enabled" ) || Settings::bool( "shadow_press_releases" ),
                "registered" => function() use ( $visibility_group_registered ): bool { return ( Settings::bool( "press_release_include_enabled" ) || Settings::bool( "shadow_press_releases" ) ) && $visibility_group_registered(); },
                "acf_group_key" => "group_smpi_visibility_controls",
                "object_name" => "_smpi_pr_shadow_override",
                "location" => "press-release side metabox",
                "description" => "Per-post override for global press-release inclusion or hiding rules.",
                "instructions" => "Use this when press releases need force-show or force-hide control independent of global defaults.",
                "fields" => [ "_smpi_pr_shadow_override" ],
                "dependencies" => [ "ACF Pro", "press-release CPT" ],
                "code_example" => 'get_field("_smpi_pr_shadow_override", $post_id)',
                "test_report" => "Press-release inclusion setting is " . ( Settings::bool( "press_release_include_enabled" ) ? "enabled" : "disabled" ) . ".",
            ],
            [
                "id" => "article_type_taxonomy",
                "label" => "Article Type Taxonomy",
                "type" => "taxonomy",
                "setting_key" => "article_types_enabled",
                "enabled" => Settings::bool( "article_types_enabled" ),
                "registered" => function(): bool { return Settings::bool( "article_types_enabled" ) && taxonomy_exists( \smp_publication_integration\Content\ArticleTypes::TAXONOMY ); },
                "object_name" => \smp_publication_integration\Content\ArticleTypes::TAXONOMY,
                "location" => "post, press-release, knowledge-base, and resources editors",
                "description" => "Controlled taxonomy that maps editorial selections to schema article types.",
                "instructions" => "Enable this to show the radio-only Article Type selector. Terms are managed by code so editors cannot create arbitrary schema labels.",
                "fields" => array_keys( \smp_publication_integration\Content\ArticleTypes::terms() ),
                "dependencies" => [ "WordPress taxonomy API", "SMP article schema mapper" ],
                "code_example" => "PublicationTaxonomies::registry(); // Hexa WP Core owns registration",
                "test_report" => $this->article_type_selector_report_text(),
            ],
        ];

        echo ( new FieldStructureRenderer() )->render(
            $definitions,
            [
                "title" => "Custom Fields and Content Structures",
                "description" => "SMP field groups, editor structures, taxonomies, dependencies, and live registration status rendered through Hexa WordPress Plugin Core.",
                "save_action" => "smpi_save_settings",
                "nonce" => Ajax::nonce(),
                "nonce_field" => "nonce",
            ]
        );
    }

    private function post_content_block_report_html( string $block ): string {
        $definitions = [
            "summary" => [
                "label" => "Article Summary",
                "enabled" => "post_summary_acf_enabled",
                "field_key" => "field_65ab7ba0e849b",
                "field" => "post_summary",
                "style" => "post_summary_style",
                "placement" => "post_summary_placement",
                "font" => "post_summary_font_family",
                "shortcode" => "[smp_post_summary]",
            ],
            "faqs" => [
                "label" => "Article FAQs",
                "enabled" => "post_faqs_acf_enabled",
                "field_key" => "field_smpi_post_faq_items",
                "field" => "post_faq_items",
                "style" => "post_faqs_style",
                "placement" => "post_faqs_placement",
                "font" => "post_faqs_font_family",
                "shortcode" => "[smp_post_faqs]",
            ],
        ];
        $definition = $definitions[ $block ] ?? $definitions["summary"];
        $enabled = Settings::bool( $definition["enabled"] );
        $registered = \Hexa\PluginCore\Fields\Field::available() && (bool) \Hexa\PluginCore\Fields\FieldGroups::get_field( $definition["field_key"] );
        $ready = $enabled && $registered;
        $html = $this->simple_status_html( $ready, $definition["label"] . " editor field: " . ( $ready ? "ready" : "not ready" ) . "." );
        $html .= "<table class=\"widefat striped\"><tbody>";
        $html .= "<tr><th>Content source</th><td><code>" . esc_html( $definition["field"] ) . "</code></td></tr>";
        $html .= "<tr><th>Selected design</th><td><code>" . esc_html( (string) Settings::get( $definition["style"], "none" ) ) . "</code></td></tr>";
        $html .= "<tr><th>Placement</th><td>" . esc_html( str_replace( "_", " ", (string) Settings::get( $definition["placement"], "manual" ) ) ) . "</td></tr>";
        $html .= "<tr><th>Shortcode</th><td><code>" . esc_html( $definition["shortcode"] ) . "</code></td></tr>";
        $html .= "<tr><th>Font</th><td>" . esc_html( Settings::font_family_label( $definition["font"] ) ) . "</td></tr>";
        return $html . "</tbody></table>";
    }



    private function post_acf_addons_report_text(): string {
        $summary_enabled = Settings::bool( "post_summary_acf_enabled" );
        $faqs_enabled = Settings::bool( "post_faqs_acf_enabled" );
        $summary_registered = \Hexa\PluginCore\Fields\Field::available() && (bool) \Hexa\PluginCore\Fields\FieldGroups::get_field( "field_65ab7ba0e849b" );
        $faqs_registered = \Hexa\PluginCore\Fields\Field::available() && (bool) \Hexa\PluginCore\Fields\FieldGroups::get_field( "field_smpi_post_faq_items" );
        return "Article Summary editor field: " . ( $summary_enabled && $summary_registered ? "ready" : "not ready" ) . ". Structured FAQ editor fields: " . ( $faqs_enabled && $faqs_registered ? "ready" : "not ready" ) . ".";
    }

    private function table_of_contents_report_html(): string {
        $report = \smp_publication_integration\Content\TableOfContents::integrity_report();
        $html = $this->simple_status_html( ! empty( $report["enabled"] ), "Shortcode active: " . (string) $report["shortcode"] . ". Auto single.php placement: " . ( ! empty( $report["auto_single"] ) ? "on" : "off" ) . "." );
        $html .= "<table class=widefat><tbody><tr><th>Font</th><td>" . esc_html( Settings::font_family_label( "table_of_contents_font_family" ) ) . "</td></tr><tr><th>Sample post</th><td>#" . esc_html( (string) $report["sample_post_id"] ) . "</td></tr><tr><th>Headings found</th><td>" . esc_html( (string) $report["heading_count"] ) . "</td></tr></tbody></table>";
        return $html;
    }


    private function elementor_css_report_html(): string {
        $report = \smp_publication_integration\Content\ElementorCssCacheBusting::test_report();
        $html = $this->simple_status_html( ! empty( $report["enabled"] ) && (int) $report["css_files"] > 0, "Elementor CSS files found: " . (int) $report["css_files"] );
        $html .= "<table class=\"widefat striped\"><thead><tr><th>File</th><th>Readable</th><th>Cache query</th></tr></thead><tbody>";
        foreach ( $report["sample_files"] as $file ) {
            $html .= "<tr><td><code>" . esc_html( $file["file"] ) . "</code></td><td>" . self::ico( (bool) $file["readable"], true ) . "</td><td><code>" . esc_html( $file["query"] ) . "</code></td></tr>";
        }
        return $html . "</tbody></table>";
    }

    private function muckrack_report_html(): string {
        $rows = \smp_publication_integration\Content\MuckRackVerification::integrity_report( 10 );
        $forced = Settings::bool( "muckrack_author_always_show" );
        $html = $this->simple_status_html( Settings::bool( "muckrack_verified_enabled" ), "Top 10 authors by published posts checked for MuckRack ACF/user fields. Always-show override: " . ( $forced ? "on" : "off" ) . ". Font: " . Settings::font_family_label( "muckrack_verified_font_family" ) . "." );
        $text_color = sanitize_hex_color( (string) Settings::get( "muckrack_verified_text_color", "#64748b" ) ) ?: "#64748b";
        $html .= "<table class=\"widefat striped\"><tbody>";
        $typography_mode = Settings::typography_mode( "muckrack_verified" );
        $typography_options = TemplateTypography::options();
        $html .= "<tr><th>Typography mode</th><td>" . esc_html( (string) ( $typography_options[ $typography_mode ]["label"] ?? "Template Default" ) ) . "</td></tr>";
        $html .= "<tr><th>Font</th><td>" . esc_html( Settings::font_family_label( "muckrack_verified_font_family" ) ) . "</td></tr>";
        $html .= "<tr><th>Font weight</th><td>" . esc_html( Settings::font_weight_label( "muckrack_verified_font_weight" ) ) . "</td></tr>";
        $html .= "<tr><th>Text color</th><td><span class=smpi-color-swatch style=\"background:" . esc_attr( $text_color ) . "\"></span> <code>" . esc_html( $text_color ) . "</code></td></tr>";
        $html .= "<tr><th>Text size</th><td>" . esc_html( (string) max( 8, min( 64, (int) Settings::get( "muckrack_verified_font_size", 14 ) ) ) ) . "px</td></tr>";
        $html .= "<tr><th>Leave as is</th><td>" . esc_html( $this->typography_preserved_label( "muckrack_verified" ) ) . "</td></tr>";
        $html .= "</tbody></table>";
        $html .= "<table class=\"widefat striped\"><thead><tr><th>User</th><th>Posts</th><th>ACF verified</th><th>Effective</th><th>Forced</th><th>URL</th><th>Description</th></tr></thead><tbody>";
        foreach ( $rows as $row ) {
            $html .= "<tr><td>" . esc_html( $row["display_name"] ) . " (#" . esc_html( (string) $row["user_id"] ) . ")</td><td>" . esc_html( (string) $row["posts"] ) . "</td><td>" . self::ico( (bool) $row["acf_verified"] ) . "</td><td>" . self::ico( (bool) $row["verified"] ) . "</td><td>" . ( $row["forced"] ? "YES" : "NO" ) . "</td><td>" . self::ico( (bool) $row["has_url"] ) . "</td><td>" . self::ico( (bool) $row["has_description"] ) . "</td></tr>";
        }
        return $html . "</tbody></table>";
    }
}
