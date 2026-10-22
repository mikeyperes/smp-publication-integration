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

/** Authors tab: multiple authors, listings and archive loading. */
trait AuthorsTab {

    private function multi_author_feature_card( array $settings, bool $collapsible = true ): void {
        $this->feature_card(
            "Multiple authors",
            "multi_authors_enabled",
            "Adds an <strong>Authors</strong> box to article editors. Each post has its own <strong>Multiple authors</strong> switch, off by default: while off, the post uses only its WordPress author. Switching it on reveals an ordered co-author list shown after the WordPress author.",
            "When on for this site, editors can give any article co-authors. Bylines, Elementor author units, author archives, schema, REST and shortcodes all read the same ordered list. When off for this site, every post uses only its WordPress author and saved co-author lists are kept.",
            "[smp_post_authors]\n[smp_post_authors format=\"links\"]\n[smp_post_authors format=\"lines\"]\n[smp_post_authors format=\"list\"]\n[smp_post_authors context=\"card\"]\n[smp_post_author_ids]\n[author_name author_index=\"1\"]\n[acf_author_field field=\"job_title\" author_index=\"1\"]\n[author_bio author_index=\"1\"]\n[author_image author_index=\"1\" output=\"url\"]\n[author_muckrack_verified author_index=\"1\"]",
            $this->multi_authors_report_html(),
            $this->activity_log_html(),
            $this->multi_author_settings_html( $settings ),
            $collapsible,
            "",
            $this->multi_author_reference_html()
        );
    }

    private function multi_authors_report_html(): string {
        $report = MultiAuthors::field_report( 10 );
        $html = $this->simple_status_html( ! empty( $report["enabled"] ), "Authors box on: " . implode( ", ", (array) $report["supported_post_types"] ) . "." );
        if ( empty( $report["rows"] ) ) {
            return $html . "<p class=smpi-muted>No post has Multiple authors switched on.</p>";
        }
        $html .= "<table class=\"widefat striped\"><thead><tr><th>Multi-author post</th><th>Type</th><th>Status</th><th>Authors (in order)</th></tr></thead><tbody>";
        foreach ( (array) $report["rows"] as $row ) {
            $names = array_map( static fn( int $id ): string => ( get_user_by( "id", $id ) instanceof \WP_User ? get_user_by( "id", $id )->display_name : "#" . $id ), array_map( "absint", (array) $row["authors"] ) );
            $html .= "<tr><td>#" . esc_html( (string) $row["post_id"] ) . " " . esc_html( (string) $row["title"] ) . "</td><td><code>" . esc_html( (string) $row["type"] ) . "</code></td><td><code>" . esc_html( (string) $row["status"] ) . "</code></td><td>" . esc_html( implode( ", ", $names ) ) . "</td></tr>";
        }
        $html .= "</tbody></table>";
        $html .= "<p class=smpi-muted>Shortcodes accept <code>author_index</code>: 0 is the WordPress author, 1 the first co-author.</p>";
        return $html;
    }

    private function multiple_authors(): void {
        $settings = Settings::all();
        echo "<div class=\"smpi-panel\"><h2>Authors</h2><p>Manage author listing pages, author archives, and multiple-author article output.</p></div>";
        echo $this->author_listing_settings_html();
        echo $this->author_archive_loading_settings_html();
        echo '<section class="smpi-panel smpi-archive-search-settings"><h2>Archive search</h2>'
            . '<p>Category, tag, author and search pages show a search box with category, tag and author filters above the stories; the list updates as readers type or tick. A template that already places [hws_search] keeps its own box.</p>'
            . $this->inline_toggle_setting_html( 'archive_search_enabled', 'Show the search box on archive pages' )
            . '</section>';
        echo $this->multi_author_debug_module_html();
        $this->multi_author_feature_card( $settings, false );
    }

    private function author_listing_settings_html(): string {
        return '<section class="smpi-panel smpi-author-listing-settings">'
            . '<h2>In the author listing page</h2>'
            . '<p>Choose which members and articles appear in author listings and author archives.</p>'
            . '<div class="smpi-author-listing-toggle-list">'
            . $this->inline_toggle_setting_html( "author_listing_hide_without_articles", "Hide members with no articles" )
            . $this->inline_toggle_setting_html( "author_listing_hide_without_featured_image", "Hide members without a featured image" )
            . ( \smp_publication_integration\Content\Visibility::distributor_owns()
                ? '<p class="smpi-muted">Press releases on author pages are set in Hexa PR Wire Distributor, General Settings, "Show press releases on".</p>'
                : $this->inline_toggle_setting_html( "author_listing_show_press_releases", "Show press releases attached to the author" ) )
            . '</div></section>';
    }

    private function author_archive_loading_settings_html(): string {
        $settings = Settings::all();
        $mode_options = [
            AuthorArchiveLoading::MODE_PAGINATION => [
                'label' => 'Pagination',
                'description' => 'Reloads the archive with numbered pages plus Previous and Next links.',
                'preview' => '<span class="smpi-author-mode-preview"><span>Previous</span><b>1</b><span>2</span><span>3</span><span>Next</span></span>',
            ],
            AuthorArchiveLoading::MODE_INFINITE_SCROLL => [
                'label' => 'Infinite scroll',
                'description' => 'Uses Elementor Pro to fetch the next page automatically near the end of the loop.',
                'preview' => '<span class="smpi-author-mode-preview"><span class="smpi-author-mode-dots">● ● ●</span><span>Loads automatically</span></span>',
            ],
            AuthorArchiveLoading::MODE_LOAD_MORE => [
                'label' => 'Load more button',
                'description' => 'Keeps visitors in control and uses Elementor Pro AJAX to append the next page.',
                'preview' => '<span class="smpi-author-mode-preview"><b>Load more articles</b></span>',
            ],
        ];
        $style_options = [
            AuthorArchiveLoading::STYLE_NONE => [
                'label' => 'No style',
                'description' => 'Elementor and element-level Custom CSS remain the only presentation owners.',
                'preview' => AuthorArchiveLoading::preview_html( AuthorArchiveLoading::STYLE_NONE ),
            ],
            AuthorArchiveLoading::STYLE_EDITORIAL_TEXT => [
                'label' => 'Editorial Text',
                'description' => 'Transparent text controls with a restrained underline for hover and current states.',
                'preview' => AuthorArchiveLoading::preview_html( AuthorArchiveLoading::STYLE_EDITORIAL_TEXT ),
            ],
            AuthorArchiveLoading::STYLE_ACCENT_UNDERLINE => [
                'label' => 'Accent Underline',
                'description' => 'Minimal text controls with a two-pixel publication-accent rule.',
                'preview' => AuthorArchiveLoading::preview_html( AuthorArchiveLoading::STYLE_ACCENT_UNDERLINE ),
            ],
            AuthorArchiveLoading::STYLE_HAIRLINE_OUTLINE => [
                'label' => 'Hairline Outline',
                'description' => 'Quiet one-pixel borders, two-pixel corners, and no inherited button shadow.',
                'preview' => AuthorArchiveLoading::preview_html( AuthorArchiveLoading::STYLE_HAIRLINE_OUTLINE ),
            ],
            AuthorArchiveLoading::STYLE_SOLID_ACCENT => [
                'label' => 'Solid Accent',
                'description' => 'A compact publication-color action; numbered pagination fills only the current page.',
                'preview' => AuthorArchiveLoading::preview_html( AuthorArchiveLoading::STYLE_SOLID_ACCENT ),
            ],
            AuthorArchiveLoading::STYLE_SOFT_PILL => [
                'label' => 'Soft Pill',
                'description' => 'A subtle soft-surface treatment with rounded controls and a defined current state.',
                'preview' => AuthorArchiveLoading::preview_html( AuthorArchiveLoading::STYLE_SOFT_PILL ),
            ],
        ];
        $structure = '<div class="smpi-author-loading-structure"><h3>Stable Elementor wrapper structure</h3>'
            . '<p>The plugin changes Elementor Pro control values and adds scoped classes to the real Loop Grid. Elementor continues to own its query, AJAX anchor, live message, and accessible pagination markup.</p>'
            . CodeViews::code( '<div class="elementor-widget-loop-grid smpi-author-loading' . "\n"
                . '  smpi-author-loading--mode-{pagination|infinite_scroll|load_more}' . "\n"
                . '  smpi-author-loading--style-{selected-style}">' . "\n"
                . '  <div class="elementor-widget-container">...Elementor native controls...</div>' . "\n"
                . '</div>', 'html' ) . '</div>';

        return '<section class="smpi-panel smpi-author-loading-settings"><h2>Author archive post loading</h2>'
            . '<p>Choose one native Elementor Pro loading method for current-query post loops on author archives. Disable this feature to leave every Loop Grid setting untouched.</p>'
            . $this->inline_toggle_setting_html( 'author_archive_loading_enabled', 'Enable author archive loading override' )
            . $this->select_setting_html( 'author_archive_loading_mode', $mode_options, $settings, 'Loading method' )
            . $this->select_setting_html( 'author_archive_loading_style', $style_options, $settings, 'Minimalist design' )
            . $structure
            . '<style id="smpi-author-loading-preview-css">' . AuthorArchiveLoading::preview_css()
            . '.smpi-author-mode-preview{align-items:center;display:flex;flex-wrap:wrap;gap:8px;min-height:42px}.smpi-author-mode-preview span,.smpi-author-mode-preview b{font-size:12px}.smpi-author-mode-preview b{border:1px solid #e5e1dd;border-radius:2px;padding:8px 10px}.smpi-author-mode-dots{color:#923131;letter-spacing:3px}.smpi-author-loading-structure{border-top:1px solid #e6eaf0;margin-top:16px;padding-top:16px}.smpi-author-loading-structure h3{margin-top:0}'
            . '</style></section>';
    }

    private function multi_author_controls_html( array $settings ): string {
        return $this->multi_author_settings_html( $settings ) . $this->multi_author_reference_html();
    }

    private function multi_author_settings_html( array $settings ): string {
        return $this->multi_author_loop_output_controls_html( $settings );
    }

    private function multi_author_reference_html(): string {
        return $this->multi_author_examples_html()
            . "<div class=\"smpi-control-group\"><h3>Elementor protocol</h3><p>Add <code>smp-author</code> to the exact author identity unit that should repeat for every author of a multi-author post. Do not put it on a row that also contains Share, read-time, ads, or unrelated controls. Legacy <code>smpi-author-module</code> is still supported for older templates, and the loop-card fallback can rewrite author-name links when no class exists.</p></div>";
    }
    private function multi_author_debug_module_html(): string {
        return "<div class=\"smpi-panel smpi-control-group smpi-multi-author-debug-panel smpi-multi-author-test\"><h2>Frontend hook and class detection test</h2><p>Enter a post ID or URL, or leave blank to test the most recent published article. The test reports assigned authors, schema authors, visible frontend authors, detected <code>smp-author</code> units, legacy fallback units, loop-card mode, author links, and whether share/read-time content is being captured by the author boundary.</p><div class=\"smpi-inline-test-row\"><input type=\"text\" class=\"regular-text\" data-smpi-multi-author-test-target placeholder=\"Post ID or URL\"><button type=\"button\" class=\"button button-primary\" data-smpi-test-multi-authors>Run frontend author test</button><span class=\"spinner\"></span><span class=\"smpi-save-state\" aria-live=\"polite\"></span></div><div class=\"smpi-multi-author-test-result\" data-smpi-multi-author-test-result aria-live=\"polite\"></div></div>";
    }

    private function multi_author_loop_output_controls_html( array $settings ): string {
        return $this->select_setting_html(
            "multi_authors_loop_output",
            [
                "comma" => [
                    "label" => "Comma separated",
                    "description" => "Each loop item gets one inner author group; names stay separate links in a compact byline.",
                    "preview" => "<span class=\"smpi-loop-author-preview smpi-loop-author-preview--comma\">Michael Peres, Mash Viral, Anne Lise Sylta</span>",
                ],
                "lines" => [
                    "label" => "Stacked names",
                    "description" => "Each loop item gets one inner author group; every author renders on its own row.",
                    "preview" => "<span class=\"smpi-loop-author-preview smpi-loop-author-preview--lines\">Michael Peres<br>Mash Viral<br>Anne Lise Sylta</span>",
                ],
                "primary" => [
                    "label" => "Primary only",
                    "description" => "Loop cards show only the WordPress author, even on multi-author posts.",
                    "preview" => "<span class=\"smpi-loop-author-preview\">Michael Peres</span>",
                ],
            ],
            $settings,
            "Loop/card author output"
        );
    }

    private function multi_author_examples_html(): string {
        return "<div class=\"smpi-control-group\"><h3>Multiple author shortcode examples</h3><div class=\"smpi-shortcode-list\"><div class=\"smpi-shortcode-row\"><strong>Plain text, comma separated</strong><code>[smp_post_authors format=\"plain\"]</code><small>Example output: Sam Harris, John Smith</small></div><div class=\"smpi-shortcode-row\"><strong>Plain text, one per line</strong><code>[smp_post_authors format=\"lines\"]</code><small>Example output: Sam Harris<br>John Smith</small></div><div class=\"smpi-shortcode-row\"><strong>Loop/card auto option</strong><code>[smp_post_authors context=\"card\"]</code><small>Uses the loop/card option selected above.</small></div><div class=\"smpi-shortcode-row\"><strong>Loop/card comma output</strong><code>[smp_post_authors context=\"card\" format=\"plain\"]</code><small>Example output: Sam Harris, John Smith</small></div><div class=\"smpi-shortcode-row\"><strong>Loop/card one per line</strong><code>[smp_post_authors context=\"card\" format=\"lines\"]</code><small>Example output: Sam Harris<br>John Smith</small></div><div class=\"smpi-shortcode-row\"><strong>Linked author names</strong><code>[smp_post_authors format=\"links\"]</code><small>Outputs author archive links for every author of the post.</small></div><div class=\"smpi-shortcode-row\"><strong>HTML list</strong><code>[smp_post_authors format=\"list\"]</code><small>Outputs a simple list when the template needs stacked names.</small></div><div class=\"smpi-shortcode-row\"><strong>Specific field</strong><code>[smp_post_authors field=\"job_title\" format=\"lines\"]</code><small>Supports name, id, url, email, job_title, title, subtitle, bio, bio_short, description, and mapped social fields.</small></div></div></div>";
    }
}
