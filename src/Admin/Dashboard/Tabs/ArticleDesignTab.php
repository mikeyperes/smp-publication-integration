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

/** Article Design tab: design choices with live samples, and their reports. */
trait ArticleDesignTab {

    private function features(): void {
        $settings = Settings::all();
        echo "<div class=\"smpi-section-intro\"><h2>Features</h2><p>Enable publication features and configure their output.</p></div>";
        echo \smp_publication_integration\Content\ArticleStyles::script_font_preview_link_html();
        echo "<style id=smpi-design-preview-css>" . \smp_publication_integration\Content\ArticleStyles::preview_bundle_css() . TemplateDesignRegistry::preview_typography_state_css() . "</style>";
        echo $this->feature_layout_styles_html();
        echo CoreUi::collection_filter(
            [
                "id" => "smpi-feature-search",
                "target_id" => "smpi-feature-collection",
                "item_selector" => ".smpi-feature-filter-item",
                "text_selector" => ":scope > summary, .smpi-feature-overview, .smpi-feature-settings",
                "group_selector" => ".smpi-feature-group",
                "label" => "Search features",
                "placeholder" => "Search features...",
                "item_label_singular" => "feature",
                "item_label_plural" => "features",
                "empty_message" => "No matching features.",
            ]
        );
        echo "<div id=\"smpi-feature-collection\">";
        echo CoreUi::collapsible( [ "title" => "HWS Base Tools primary color", "body_html" => $this->feature_brand_color_tools_html( $settings ), "open" => false, "meta_html" => CoreUi::pill( "Color source", "dark" ), "class" => "smpi-feature-filter-item" ] );
        $design_classes = [ "smpi-design-host", "smpi-feature-groups" ];
        foreach ( Settings::typography_preservation_surfaces() as $prefix => $default ) {
            $mode = Settings::typography_mode( $prefix );
            $design_classes[] = TemplateTypography::mode_state_class( $prefix, $mode );
            foreach ( TemplateTypography::preservation_values( $settings, $prefix, $default ) as $property => $preserve ) {
                if ( $preserve ) {
                    $design_classes[] = TypographyPreservation::state_class( $prefix, $property );
                }
            }
        }
        echo "<div class=\"" . esc_attr( implode( " ", $design_classes ) ) . "\" data-hpc-typography-scope data-hpc-template-color-scope style=\"" . esc_attr( $this->design_preview_variables() ) . "\">";
        $this->render_article_design_feature_group( $settings );
        $this->render_author_feature_group( $settings );
        $this->render_content_feature_group( $settings );
        $this->render_system_feature_group( $settings );
        echo "</div>";
        echo "</div>";
    }

    private function render_article_design_feature_group( array $settings ): void {
        $this->feature_group_open( "article-design", "Article design", "Article navigation, headings, images, and structured content blocks." );

        $breadcrumb_controls = $this->breadcrumb_controls_html( $settings );
        $this->feature_card(
            "Breadcrumb navigation",
            "breadcrumbs_enabled",
            "Registers a Hexa core-generated ACF multi post selector on Publication Theme Options: <code>smpi_breadcrumb_disabled_objects</code>. No repeater.",
            "Adds breadcrumbs below the site header on posts, pages, custom post types, and category or tag archives. Rank Math markup is used when available; SMP provides the fallback.",
            "[smp_breadcrumbs]\n[smp_breadcrumbs style=\"bc-b2\"]\nACF option: smpi_breadcrumb_disabled_objects\nRank Math source: rank-math-options-general",
            $this->breadcrumbs_report_html(),
            $this->activity_log_html(),
            $breadcrumb_controls,
            true,
            $this->breadcrumb_css_override_html()
        );

        $toc_controls = $this->inline_toggle_setting_html( "table_of_contents_auto_single", "Automatically place above article content" )
            . $this->inline_toggle_setting_html( "table_of_contents_include_summary", "Include the Article Summary link" )
            . $this->select_setting_html( "table_of_contents_style", $this->toc_style_options(), $settings, "Table of contents design" )
            . $this->template_color_setting_html( "table_of_contents", "Table of contents design color", $settings )
            . $this->typography_surface_control_html( "table_of_contents", $settings )
            . $this->shortcode_usage_html(
                "Use the default shortcode in an Elementor Shortcode widget or in post content. Turn off automatic placement when positioning it manually.",
                [
                    [ "Default", "[smp_table_of_contents]" ],
                    [ "Choose a design", "[smp_table_of_contents style=\"toc02\"]" ],
                    [ "Elementor h2 contents", "[smp_table_of_contents style=\"unstyled\" title=\"Contents\" levels=\"h2\" open=\"desktop\" markers=\"leading-zero\"]" ],
                    [ "Another post", "[smp_table_of_contents post_id=\"123\" title=\"In this article\"]" ],
                ]
            );
        $this->feature_card(
            "Article table of contents",
            "table_of_contents_enabled",
            "No ACF changes. Parses post headings from post_content.",
            "Builds a table of contents from post headings. Place it by shortcode or automatically above single-post content.",
            "[smp_table_of_contents]\n[smp_table_of_contents style=\"toc02\"]\n[smp_table_of_contents style=\"unstyled\" title=\"Contents\" levels=\"h2\" open=\"desktop\" markers=\"leading-zero\"]\n[smp_table_of_contents post_id=\"123\" title=\"In this article\"]",
            $this->table_of_contents_report_html(),
            $this->activity_log_html(),
            $toc_controls
        );

        $article_heading_controls = $this->select_setting_html( "article_heading_style", $this->article_heading_style_options(), $settings, "Article H2/H3 template" )
            . $this->template_color_setting_html( "article_heading", "Heading design color", $settings )
            . $this->typography_surface_control_html( "article_heading", $settings );
        $this->feature_card(
            "Article H2/H3 heading styles",
            "article_heading_styles_enabled",
            "No ACF changes. Applies selected CSS to H2 and H3 tags inside single post content only.",
            "Styles H2 and H3 headings inside single-post content with one template, one accent color, and separate font sizes.",
            "No shortcode needed. Enable the feature, choose a heading template, then tune accent color and H2/H3 sizes.",
            $this->article_heading_report_html(),
            $this->activity_log_html(),
            $article_heading_controls
        );

        $numbered_list_controls = $this->select_setting_html( "article_numbered_list_style", $this->article_numbered_list_style_options(), $settings, "Numbered list design" )
            . $this->template_color_setting_html( "article_numbered_list", "Numbered list design color", $settings )
            . $this->typography_surface_control_html( "article_numbered_list", $settings );
        $this->feature_card(
            "Numbered article list styles",
            "article_numbered_lists_enabled",
            "No ACF changes. Styles top-level ordered lists inside single post content and leaves nested lists unchanged.",
            "Turns standard numbered article lists into structured editorial rows with selectable number, divider, rail, card, and index treatments.",
            "Use a normal ordered list (<ol>) in post content. Each top-level list item becomes one numbered row; optional H3/H4 headings and paragraphs receive semantic SMP classes.",
            $this->article_numbered_list_report_html(),
            $this->activity_log_html(),
            $numbered_list_controls
        );

        $drop_cap_controls = $this->select_setting_html( "article_drop_cap_style", $this->article_drop_cap_style_options(), $settings, "First-letter template" )
            . $this->template_color_setting_html( "article_drop_cap", "Drop cap design color", $settings )
            . $this->typography_surface_control_html( "article_drop_cap", $settings );
        $this->feature_card(
            "First-letter drop cap",
            "article_drop_cap_enabled",
            "No ACF changes. Applies CSS to the first letter of the first paragraph inside single post content.",
            "Styles the first letter of the first paragraph in single-post content with one of ten selectable templates.",
            "No shortcode needed. Enable the feature, choose a template, then tune accent color and size.",
            $this->article_drop_cap_report_html(),
            $this->activity_log_html(),
            $drop_cap_controls
        );

        $inline_photo_controls = $this->select_setting_html( "inline_photo_treatment", $this->inline_photo_treatment_options(), $settings, "Inline photo treatment" )
            . $this->template_color_setting_html( "inline_photo_caption", "Inline photo design color", $settings )
            . $this->typography_surface_control_html( "inline_photo_caption", $settings );
        $this->feature_card(
            "Inline article image styles",
            "inline_photo_treatments_enabled",
            "No ACF changes. Applies selected treatment to inline figures in posts and press-release articles.",
            "Styles inline figures and captions in posts and press releases.",
            "No shortcode needed. Enable the feature and select a treatment.",
            $this->simple_status_html( Settings::bool( "inline_photo_treatments_enabled" ), "Current treatment: " . (string) Settings::get( "inline_photo_treatment", "none" ) . ". Font: " . Settings::font_family_label( "inline_photo_caption_font_family" ) . "." ),
            $this->activity_log_html(),
            $inline_photo_controls
        );

        $featured_image_caption_controls = $this->select_setting_html( "featured_image_caption_template", $this->featured_image_caption_template_options(), $settings, "Featured image caption template" )
            . $this->template_color_setting_html( "featured_image_caption", "Featured caption design color", $settings )
            . $this->typography_surface_control_html( "featured_image_caption", $settings );
        $this->feature_card(
            "Featured image caption styles",
            "featured_image_caption_templates_enabled",
            "No ACF changes. Reads the caption from the media attachment used as the post featured image.",
            "Styles captions attached to featured images on posts and press releases.",
            "No shortcode needed. Add a media caption to the featured image, enable this feature, and select a template.",
            $this->featured_image_caption_report_html(),
            $this->activity_log_html(),
            $featured_image_caption_controls
        );

        $post_summary_controls = $this->post_content_block_required_field_html( "summary" )
            . $this->select_setting_html( "post_summary_style", $this->post_summary_style_options(), $settings, "Summary output style" )
            . $this->select_setting_html( "post_summary_placement", $this->post_summary_placement_options(), $settings, "Summary placement" )
            . $this->template_color_setting_html( "post_summary", "Summary design color", $settings )
            . $this->summary_background_setting_html( $settings )
            . $this->typography_surface_control_html( "post_summary", $settings )
            . $this->shortcode_usage_html(
                "Both shortcodes use the selected Summary design. Choose Manual placement when positioning the block in Elementor or post content.",
                [
                    [ "Recommended", "[smp_post_summary]" ],
                    [ "ACF alias", "[smp_post_acf field=\"post_summary\"]" ],
                    [ "Another post", "[smp_post_summary post_id=\"123\"]" ],
                ]
            );
        $this->feature_card(
            "Article summary block",
            "post_summary_acf_enabled",
            "Registers and reads the existing <code>post_summary</code> editor field. The feature toggle and Custom Fields tab update the same setting.",
            "Displays the article summary with a selectable design, color, typography, and manual or automatic placement.",
            "[smp_post_summary]\n[smp_post_acf field=\"post_summary\"]",
            $this->post_summary_report_html(),
            $this->activity_log_html(),
            $post_summary_controls
        );

        $post_faq_controls = $this->post_content_block_required_field_html( "faqs" )
            . $this->select_setting_html( "post_faqs_style", $this->post_faq_style_options(), $settings, "FAQ output style" )
            . $this->select_setting_html( "post_faqs_placement", $this->post_faq_placement_options(), $settings, "FAQ placement" )
            . $this->template_color_setting_html( "post_faqs", "FAQ design color", $settings )
            . $this->typography_surface_control_html( "post_faqs", $settings )
            . $this->shortcode_usage_html(
                "Use the shortcode for manual placement. Automatic modes insert the same canonical FAQ output once per article.",
                [
                    [ "Recommended", "[smp_post_faqs]" ],
                    [ "Native accordion", "[smp_post_faqs format=\"accordion\" style=\"unstyled\"]" ],
                    [ "ACF alias", "[smp_post_acf field=\"post_faq_items\"]" ],
                    [ "Another post", "[smp_post_faqs post_id=\"123\"]" ],
                ]
            );
        $this->feature_card(
            "Article FAQ block",
            "post_faqs_acf_enabled",
            "Registers and reads the existing <code>post_faq_items</code> editor fields. FAQPage schema continues to use the same rows.",
            "Displays structured article FAQs with a selectable design, color, typography, and manual or automatic placement.",
            "[smp_post_faqs]\n[smp_post_acf field=\"post_faq_items\"]",
            $this->post_faq_report_html(),
            $this->activity_log_html(),
            $post_faq_controls
        );

        $this->feature_group_close();
    }

    private function render_author_feature_group( array $settings ): void {
        $this->feature_group_open( "authors-verification", "Authors and verification", "Author assignment and MuckRack verification output." );

        $author_social_contexts = isset( $settings["author_social_auto_contexts"] ) && is_array( $settings["author_social_auto_contexts"] )
            ? $settings["author_social_auto_contexts"]
            : [];
        $author_archive_position_controls = $this->select_setting_html(
            "author_social_archive_position",
            [
                "below_name" => [ "label" => "Below name", "description" => "Place the icon group immediately after the author name." ],
                "below_title" => [ "label" => "Below title", "description" => "Place the icon group immediately after the author role or title." ],
                "below_image" => [ "label" => "Below image", "description" => "Place the icon group inside the author image widget, below the portrait." ],
                "below_bio" => [ "label" => "Below bio", "description" => "Place the icon group immediately after the author biography." ],
            ],
            $settings,
            "Author archive position"
        );
        $author_archive_position_controls = "<div class=\"smpi-author-social-archive-position\" data-smpi-author-social-archive-position" . ( in_array( "author_archive", $author_social_contexts, true ) ? "" : " hidden" ) . ">" . $author_archive_position_controls . "</div>";

        $author_social_controls = $this->context_select_html(
            "author_social_networks",
            AuthorSocialIcons::networks(),
            $settings,
            "Visible social networks"
        )
            . $this->select_setting_html( "author_social_style", $this->author_social_style_options( $settings ), $settings, "Visual template" )
            . $this->template_color_setting_html( "author_social", "Author social icon color", $settings )
            . $this->number_setting_html( "author_social_size", "Icon size", $settings, 12, 64, "px" )
            . $this->context_select_html(
                "author_social_auto_contexts",
                [
                    "single_post" => "Single post (post.php / single.php)",
                    "author_archive" => "Author archive (author.php)",
                ],
                $settings,
                "Automatic placement (optional)"
            )
            . $author_archive_position_controls
            . $this->shortcode_usage_html(
                "Use the shortcode in an Elementor Shortcode widget or enable an automatic context below. Single-post placement appends after the content; author.php placement uses the selected author name, title, image, or bio target.",
                [
                    [ "Recommended", "[smp_author_social_icons]" ],
                    [ "No Style", "[smp_author_social_icons style=\"unstyled\"]" ],
                    [ "Specific author", "[smp_author_social_icons user_id=\"54\"]" ],
                    [ "Override networks", "[smp_author_social_icons networks=\"linkedin,x,website\"]" ],
                ]
            )
            . $this->author_social_no_style_html();
        $this->feature_card(
            "Author social icons",
            "author_social_icons_enabled",
            "Reads the existing author user/ACF social URL aliases for X, LinkedIn, Facebook, Instagram, YouTube, website, Crunchbase, and Muck Rack. Missing or invalid values never produce a list item.",
            "Builds one accessible social-icon group for manual shortcode placement or optional automatic insertion on single posts and author archives.",
            "[smp_author_social_icons]\n[smp_author_social_icons style=\"unstyled\"]\n[smp_author_social_icons user_id=\"54\" networks=\"linkedin,x,website\"]",
            $this->author_social_report_html( $settings ),
            $this->activity_log_html(),
            $author_social_controls,
            true,
            "",
            $this->author_social_structure_html( $settings )
        );

        $author_muckrack_controls = $this->inline_toggle_setting_html( "muckrack_author_always_show", "Always show for every author" )
            . $this->context_select_html( "muckrack_verified_contexts", [ "single_author" => "single.php header author mention", "single_footer" => "single.php footer/about-author mention", "loop_cards" => "Loop card authors: show checkmark", "home" => "Home page author mention", "author" => "author.php author mention" ], $settings, "Placement contexts" )
            . $this->select_setting_html( "muckrack_verified_style", [ "tooltip" => [ "label" => "Tooltip icon", "description" => "Small badge beside the author name. Hover or focus explains the verification without adding sentence-length text.", "preview" => $this->author_tooltip_preview_html( $settings ) ], "text" => [ "label" => "Inline text", "description" => "Writes the full verification sentence directly into the author area.", "preview" => $this->author_inline_preview_html( $settings ) ], "compact_block" => [ "label" => "Small editorial block", "description" => "Smaller author version of the editorial verification block with left accent and compact text.", "preview" => $this->author_compact_block_preview_html( $settings ) ] ], $settings, "Display style" )
            . $this->typography_surface_control_html( "muckrack_verified", $settings )
            . $this->icon_style_setting_html( $settings )
            . $this->template_color_setting_html( "muckrack_verified", "Author verification design color", $settings )
            . $this->number_setting_html( "muckrack_icon_size", "Default checkmark size", $settings, 8, 64, "px" )
            . $this->number_setting_html( "muckrack_icon_margin_left", "Default checkmark margin-left", $settings, -32, 64, "px" )
            . $this->number_setting_html( "muckrack_icon_margin_top", "Default checkmark margin-top", $settings, -32, 64, "px" )
            . $this->author_context_overrides_html( $settings );
        $author_reference = $this->author_muckrack_mode_help_html( $settings ) . $this->author_muckrack_shortcodes_html();
        $this->feature_card(
            "MuckRack verified authors",
            "muckrack_verified_enabled",
            "Uses the author user fields owned by hws-base-tools: muckrack_verified and muckrack_url.",
            "Shows author-level MuckRack verification automatically or by shortcode. Placement, style, color, and context overrides are configured here.",
            "[author_muckrack_verified]\n[author_muckrack_verified type=\"text\"]\n[author_muckrack_verified style=\"compact_block\"]\n[author_muckrack]\n[muckrack_verified type=\"icon\" user_id=\"54\"]",
            $this->muckrack_report_html(),
            $this->activity_log_html(),
            $author_muckrack_controls,
            true,
            "",
            $author_reference
        );

        $this->multi_author_feature_card( $settings );

        $publication_muckrack_controls = $this->publication_muckrack_source_html()
            . $this->select_setting_html( "publication_muckrack_text_mode", [ "news_outlet" => [ "label" => "News outlet verified by MuckRack editorial team", "description" => "Generic wording when you do not want the site name in the sentence." ], "publication_name" => [ "label" => get_bloginfo( "name" ) . " verified by MuckRack editorial team", "description" => "Uses the current publication name in the verification sentence." ], "publication_sentence" => [ "label" => get_bloginfo( "name" ) . " is verified by MuckRack's editorial team.", "description" => "A full sentence with the publication name, as publication footers use it." ] ], $settings, "Text option" )
            . $this->context_select_html( "publication_muckrack_placements", [ "below_author" => "Below author", "bottom_article" => "Bottom of article" ], $settings )
            . $this->select_setting_html( "publication_muckrack_style", [ "block" => [ "label" => "Editorial block", "description" => "Small article footer block with a left accent bar.", "preview" => $this->publication_preview_sample_html( "block", $settings ) ], "mini_block" => [ "label" => "Mini editorial block", "description" => "Same left-accent editorial concept with smaller text and a quieter footprint.", "preview" => $this->publication_preview_sample_html( "mini_block", $settings ) ], "compact" => [ "label" => "Compact pill", "description" => "Small inline badge for tight author or header layouts.", "preview" => $this->publication_preview_sample_html( "compact", $settings ) ], "minimalist" => [ "label" => "Minimalist text", "description" => "Plain text treatment that blends into existing article copy.", "preview" => $this->publication_preview_sample_html( "minimalist", $settings ) ] ], $settings, "Display style" )
            . $this->template_color_setting_html( "publication_muckrack", "Publication verification design color", $settings )
            . $this->typography_surface_control_html( "publication_muckrack", $settings );
        $this->feature_card(
            "MuckRack verified publication",
            "publication_muckrack_verified_enabled",
            "Registers site option ACF fields on Publication Theme Options: smpi_publication_muckrack_verified and smpi_publication_muckrack_url.",
            "Shows publication-level MuckRack verification separately from author verification.",
            "[smp_publication_muckrack_verified]",
            $this->publication_muckrack_report_html(),
            $this->activity_log_html(),
            $publication_muckrack_controls
        );

        $this->feature_group_close();
    }

    private function render_content_feature_group( array $settings ): void {
        $this->feature_group_open( "content-distribution", "Content and distribution", "Category output, press releases, schema types, and social-link cleanup." );

        $elementor_category_controls = $this->inline_toggle_setting_html( "elementor_primary_category_exclude_default", "Exclude the configured WordPress default category" );
        $this->feature_card(
            "Elementor primary category",
            "elementor_primary_category_enabled",
            "No ACF changes. Registers one SMP-owned Elementor text dynamic tag.",
            "Outputs one category in Elementor, honors Rank Math or Yoast primary-category metadata, and can exclude the WordPress default category.",
            "Elementor dynamic tag: SMP Publication > Primary Category",
            $this->simple_status_html( Settings::bool( "elementor_primary_category_enabled" ), "Primary Category dynamic tag is registered for Elementor text fields." ),
            $this->activity_log_html(),
            $elementor_category_controls
        );

        $this->feature_card(
            "Press-release inclusion controls",
            "press_release_include_enabled",
            "Uses the press-release CPT and <code>_smpi_pr_shadow_override</code> meta. The editor provides force-include and force-exclude choices.",
            "Includes press releases in selected post loops. Author archives use the separate switch on the Authors tab.",
            "add_action(\"pre_get_posts\", function (WP_Query \$q) { /* SMP uses the same main-query guard pattern and selected contexts. */ });",
            $this->press_release_report_html(),
            $this->activity_log_html(),
            ( \smp_publication_integration\Content\Visibility::distributor_owns()
                ? "<p class=\"smpi-muted\">Hexa PR Wire Distributor is active, so its General Settings switches (\"Show press releases on\") decide the home, category, tag and author pages. The choice below applies only to single.php recent article queries.</p>"
                : "<p class=\"smpi-muted\">Author archives are controlled on the Authors tab.</p>" ) . $this->context_select_html( "press_release_include_contexts", [ "home" => "Home page", "category_tag" => "Category and tag pages", "single_recent" => "single.php recent article queries" ], $settings )
        );

        $article_type_reference = "<p class=\"smpi-muted\">The registration toggle is in Custom Fields.</p>" . $this->article_type_selector_options_html();
        $this->feature_card(
            "Article type schema selector",
            "",
            "Registers the <code>smpi_article_type</code> taxonomy only when the Custom Fields setting is enabled.",
            "The editor field and toggle live in Custom Fields. This reference shows the allowed values and schema mappings.",
            "editorial-news => NewsArticle\nanalysis => AnalysisNewsArticle\nopinion => OpinionNewsArticle\nreportage => ReportageNewsArticle\npress-release => NewsArticle\nsponsored => AdvertiserContentArticle",
            $this->article_type_selector_report_html(),
            $this->activity_log_html(),
            "",
            true,
            "",
            $article_type_reference
        );

        $this->feature_card_from_snippet( "publication_social_link_cleanup", $this->simple_status_html( Settings::bool( "publication_social_cleanup" ), "Publication social link cleanup script active on frontend pages." ) );

        $this->feature_group_close();
    }

    private function render_system_feature_group( array $settings ): void {
        $this->feature_group_open( "system-integrations", "System integrations", "Elementor asset behavior and HWS admin access reporting." );

        $this->feature_card_from_snippet( "elementor_css_cache_busting", $this->elementor_css_report_html() );
        $this->feature_card(
            "HWS masked admin URL",
            "hws_masked_admin_report_enabled",
            "HWS Base Tools owns this feature. SMP only reports its status and URL.",
            "Reports the masked admin URL managed by HWS Base Tools.",
            "HWS option: hws_login_mask_options with slug hexa-admin.",
            $this->hws_masked_login_report_html(),
            $this->activity_log_html()
        );

        $this->feature_group_close();
    }

    private function feature_group_open( string $slug, string $title, string $description ): void {
        $slug = sanitize_html_class( $slug );
        $title_id = "smpi-feature-group-" . $slug;
        echo "<section class=\"smpi-feature-group smpi-feature-group--" . esc_attr( $slug ) . "\" data-smpi-feature-group=\"" . esc_attr( $slug ) . "\" aria-labelledby=\"" . esc_attr( $title_id ) . "\"><header class=smpi-feature-group-head><h2 id=\"" . esc_attr( $title_id ) . "\">" . esc_html( $title ) . "</h2><p>" . esc_html( $description ) . "</p></header><div class=smpi-feature-group-list>";
    }

    private function feature_group_close(): void {
        echo "</div></section>";
    }

    private function feature_layout_styles_html(): string {
        return <<<'HTML'
<style id="smpi-feature-layout-css">
.smpi-feature-groups{display:grid;gap:32px;margin-top:28px;min-width:0}
.smpi-feature-group{display:grid;gap:12px;min-width:0}
.smpi-feature-group-head{border-bottom:1px solid #cfd6df;padding:0 0 10px}
.smpi-feature-group-head h2{font-size:18px;letter-spacing:0;line-height:1.3;margin:0}
.smpi-feature-group-head p{color:#596579;font-size:13px;line-height:1.45;margin:4px 0 0}
.smpi-feature-group-list{display:grid;gap:10px;min-width:0}
.smpi-feature-group-list>.hpc-section{margin:0;min-width:0}
.smpi-feature-card.smpi-feature-card--core-collapsible{display:grid;gap:16px;min-width:0}
.smpi-feature-card .smpi-feature-save-banner{margin:0}
.smpi-feature-overview{align-items:start;border-bottom:1px solid #e1e6ed;display:grid;gap:18px;grid-template-columns:minmax(0,1fr) auto;padding:0 0 16px}
.smpi-feature-overview h3,.smpi-feature-settings-head h3,.smpi-feature-reference-grid h4{color:#1d2735;font-size:14px;letter-spacing:0;line-height:1.35;margin:0;text-transform:none}
.smpi-feature-overview p,.smpi-feature-settings-head p{color:#536176;line-height:1.55;margin:5px 0 0;max-width:78ch}
.smpi-feature-overview-side{align-items:center;display:flex;justify-content:flex-end;min-width:180px}
.smpi-feature-managed{color:#596579;font-size:12px;font-weight:700}
.smpi-feature-settings{display:grid;gap:4px;min-width:0}
.smpi-feature-settings-head{padding-bottom:4px}
.smpi-feature-settings-body{display:grid;min-width:0}
.smpi-feature-settings-body>.smpi-control-group,.smpi-feature-settings-body>.smpi-control-row,.smpi-feature-settings-body>.smpi-breadcrumb-flow,.smpi-feature-settings-body>.smpi-drop-cap-preview{border-top:1px solid #e6eaf0;margin:0;padding:16px 0}
.smpi-feature-settings-body>:first-child{border-top:0;padding-top:8px}
.smpi-feature-card .smpi-control-group h3,.smpi-feature-card .smpi-breadcrumb-section-head h3{letter-spacing:0;text-transform:none}
.smpi-feature-card .smpi-choice-card{border-radius:8px}
.smpi-feature-card .smpi-selected-pill{letter-spacing:0}
.smpi-feature-card>.hpc-detail-card,.smpi-feature-before-activity>.hpc-detail-card{margin:0;min-width:0}
.smpi-feature-reference-grid{display:grid;gap:20px;grid-template-columns:minmax(0,1fr) minmax(0,1fr);min-width:0}
.smpi-feature-reference-grid section{min-width:0}
.smpi-feature-reference-grid p{line-height:1.55;margin:6px 0 0}
.smpi-feature-reference-grid .smpi-code{border-radius:6px;margin:6px 0 0;max-width:100%;min-height:72px}
.smpi-feature-reference-extra{border-top:1px solid #e1e6ed;margin-top:18px;padding-top:4px}
.smpi-feature-reference-extra>.smpi-control-group{border-top:1px solid #e6eaf0;margin:0;padding:16px 0}
.smpi-feature-reference-extra>.smpi-control-group:first-child{border-top:0}
.smpi-feature-reference-extra .smpi-mode-grid>div,.smpi-feature-reference-extra .smpi-shortcode-row{background:transparent;border:0;border-radius:0}
.smpi-feature-reference-extra .smpi-shortcode-row{border-top:1px solid #e6eaf0;padding:12px 0}
.smpi-feature-card .smpi-shortcode-code{align-items:center;display:flex;flex-wrap:wrap;gap:8px;min-width:0}
.smpi-feature-card .smpi-shortcode-code code{overflow-wrap:anywhere;white-space:normal}
.smpi-feature-before-activity,.smpi-feature-activity{min-width:0}
.smpi-feature-diagnostics .widefat{max-width:100%}
.smpi-feature-card pre,.smpi-feature-card code{max-width:100%}
.smpi-control-group:has(input[data-key="article_numbered_list_style"]) .smpi-choice-grid{grid-template-columns:minmax(0,1fr)}
.smpi-control-group:has(input[data-key="article_numbered_list_style"]) .smpi-choice-preview{max-width:760px}
@media(max-width:782px){
.smpi-feature-groups{gap:26px;margin-top:22px}
.smpi-feature-group-head h2{font-size:17px}
.smpi-feature-overview{grid-template-columns:minmax(0,1fr)}
.smpi-feature-overview-side{justify-content:flex-start;min-width:0}
.smpi-feature-reference-grid{grid-template-columns:minmax(0,1fr)}
.smpi-feature-card .smpi-shortcode-row{grid-template-columns:minmax(0,1fr)}
.smpi-feature-card .smpi-shortcode-row small{grid-column:1}
.smpi-feature-card .hpc-detail-card-title{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
}
</style>
HTML;
    }

    private function feature_brand_color_tools_html( array $settings ): string {
        $payload = BrandColorProvider::payload( Settings::brand_primary_color( "#2d5277" ) );
        $primary = (string) $payload["primary_color"];
        $rgb = (string) $payload["primary_rgb"];
        $link = "" !== (string) $payload["admin_url"] ? CoreUi::external_link( (string) $payload["admin_url"], "Open HWS Brand Assets", "button button-secondary" ) : "";

        return "<section class=\"smpi-feature-card smpi-feature-card--core-collapsible smpi-brand-color-tools\"><div class=smpi-feature-head><div><p class=smpi-kicker>Brand color source</p><h2>HWS Base Tools primary color</h2><p class=smpi-muted>Each design can use its original palette, the site palette, or a custom accent.</p></div></div><div class=smpi-feature-grid><section><h3>Primary color</h3><p><code>" . esc_html( $primary ) . "</code> <code>" . esc_html( $rgb ) . "</code></p><span class=smpi-color-swatch style=\"background:" . esc_attr( $primary ) . "\"></span></section><section><h3>Apply to all designs</h3><p>Set every template design card to Site Primary without changing neutral text, borders, or backgrounds.</p><button type=button class=\"button button-primary\" data-smpi-import-brand-color data-key=\"_all_feature_primary_colors\" data-brand-color=\"" . esc_attr( $primary ) . "\">Use Site Primary for all designs</button> " . $link . "<span class=spinner></span><span class=\"smpi-save-state\" aria-live=\"polite\"></span></section></div></section>";
    }

    private function inline_toggle_html( string $key ): string {
        $enabled = Settings::bool( $key );
        return "<label class=smpi-switch><input class=\"smpi-setting smpi-feature-primary-toggle\" type=checkbox data-key=" . esc_attr( $key ) . " value=1 " . checked( $enabled, true, false ) . "><span></span><strong>" . ( $enabled ? "Enabled" : "Disabled" ) . "</strong></label><span class=spinner></span><span class=smpi-save-state></span>";
    }

    private function inline_toggle_setting_html( string $key, string $label ): string {
        $enabled = Settings::bool( $key );
        return "<div class=smpi-control-row>"
            . CoreUi::toggle(
                $key,
                $enabled,
                $label,
                [
                    "id"          => "smpi-" . $key,
                    "input_class" => "smpi-setting",
                    "data"        => [ "key" => $key ],
                ]
            )
            . "<span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function breadcrumb_controls_html( array $settings ): string {
        $template = $this->select_setting_html( "breadcrumbs_style", $this->breadcrumb_style_options(), $settings, "Breadcrumb template" );
        $appearance = "<div class=\"smpi-breadcrumb-appearance-grid\">"
            . $this->template_color_setting_html( "breadcrumbs", "Breadcrumb design color", $settings )
            . $this->optional_color_setting_html( "breadcrumbs_background_color", "Background override", $settings, "Use template background" )
            . $this->typography_surface_control_html( "breadcrumbs", $settings )
            . "</div>";
        $visibility = "<div class=\"smpi-breadcrumb-visibility-grid\">"
            . $this->inline_toggle_setting_html( "breadcrumbs_hide_home", "Hide on front page and posts page" )
            . $this->inline_toggle_setting_html( "breadcrumbs_hide_term_archives", "Hide on category and tag pages" )
            . "</div>"
            . $this->breadcrumb_post_type_toggles_html( $settings );

        return "<div class=\"smpi-breadcrumb-flow\">"
            . $this->breadcrumb_section_html( "template", "01", "Template", $template )
            . $this->breadcrumb_section_html( "appearance", "02", "Appearance", $appearance )
            . $this->breadcrumb_section_html( "visibility", "03", "Visibility", $visibility )
            . "</div>";
    }

    private function breadcrumb_css_override_html(): string {
        $selector = Breadcrumbs::CSS_SELECTOR;
        $html_example = <<<'HTML'
<div class="smpi-breadcrumbs-band">
  <div class="smpi-template smpi-template--breadcrumbs smpi-breadcrumbs smpi-bc-b6">
    <nav class="smpi-template-content smpi-breadcrumb-nav" aria-label="Breadcrumbs">
      <p class="smpi-template-list smpi-breadcrumb-list">
        <a class="smpi-template-item smpi-template-link smpi-breadcrumb-item smpi-breadcrumb-link" href="/">Home</a>
        <span class="smpi-breadcrumb-separator"> / </span>
        <span class="smpi-template-item smpi-breadcrumb-item smpi-breadcrumb-current">Current page</span>
      </p>
    </nav>
  </div>
</div>
HTML;
        $css_example = <<<'CSS'
body .smpi-breadcrumbs-band {
  --smpi-bc-background: #111827;
  --smpi-bc-accent: #60a5fa;
  background: var(--smpi-bc-background);
}

body .smpi-breadcrumbs-band .smpi-breadcrumbs[class*="smpi-bc-"] .smpi-breadcrumb-link,
body .smpi-breadcrumbs-band .smpi-breadcrumbs[class*="smpi-bc-"] .smpi-breadcrumb-current {
  color: #f8fafc;
}

body.page-id-123 .smpi-breadcrumbs-band {
  --smpi-bc-background: #ffffff;
}

CSS;

        return ScopedCssOverride::render(
            [
                "title"        => "Breadcrumb CSS override",
                "selector"     => $selector,
                "instructions" => [
                    "Enter the CSS you want to apply in the editor below.",
                    "Use the band selector for the full background and its breadcrumb child for text. Add body.page-id-123 for one page only.",
                    "Changes save when you leave the editor and load after the Breadcrumb template CSS.",
                ],
                "html_example" => $html_example,
                "css_example"  => $css_example,
                "setting_key"  => Breadcrumbs::CSS_SETTING,
                "value"        => (string) Settings::get( Breadcrumbs::CSS_SETTING, "" ),
                "input_class"  => "smpi-setting",
                "editor_label" => "Your CSS override",
                "editor_description" => "Edit scoped CSS here. Changes save automatically when you leave the field.",
                "placeholder"  => $css_example,
                "status_html" => '<span class="spinner"></span><span class="smpi-save-state"></span>',
                "open"         => false,
            ]
        );
    }

    private function breadcrumb_section_html( string $slug, string $step, string $title, string $body_html ): string {
        $slug = sanitize_html_class( $slug );
        $title_id = "smpi-breadcrumb-" . $slug . "-title";
        return "<section class=\"smpi-breadcrumb-section smpi-breadcrumb-section--" . esc_attr( $slug ) . "\" aria-labelledby=\"" . esc_attr( $title_id ) . "\"><header class=smpi-breadcrumb-section-head><span class=smpi-breadcrumb-step aria-hidden=true>" . esc_html( $step ) . "</span><h3 id=\"" . esc_attr( $title_id ) . "\">" . esc_html( $title ) . "</h3></header>" . $body_html . "</section>";
    }

    private function breadcrumb_post_type_toggles_html( array $settings ): string {
        $key = "breadcrumbs_disabled_post_types";
        $selected = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? $settings[ $key ] : [];
        $options = $this->breadcrumb_post_type_options();
        $html = "<div class=\"smpi-control-group smpi-context-control smpi-breadcrumb-post-type-control\"><h3>Hide breadcrumbs by post type</h3><p class=smpi-muted>Choose each individual post type where breadcrumbs should be hidden.</p>";

        if ( empty( $options ) ) {
            return $html . "<p class=smpi-muted>No public post types are registered.</p></div>";
        }

        $html .= "<div class=smpi-breadcrumb-toggle-grid>";
        foreach ( $options as $value => $label ) {
            $html .= "<div class=smpi-breadcrumb-toggle-item>"
                . CoreUi::toggle(
                    $key . "[]",
                    in_array( $value, $selected, true ),
                    (string) $label,
                    [
                        "id"          => "smpi-breadcrumb-hide-" . $value,
                        "value"       => $value,
                        "class"       => "smpi-breadcrumb-post-type-toggle",
                        "input_class" => "smpi-setting-array",
                        "data"        => [ "key" => $key ],
                    ]
                )
                . "</div>";
        }

        return $html . "</div><span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function context_select_html( string $key, array $options, array $settings, string $label = "Placement contexts" ): string {
        $selected = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? $settings[ $key ] : [];
        $html = "<div class=\"smpi-control-group smpi-context-control\"><h3>" . esc_html( $label ) . "</h3><div class=smpi-choice-list>";
        foreach ( $options as $value => $option_label ) {
            $choice = $this->choice_data( $option_label, $this->context_description( $key, (string) $value ) );
            $is_selected = in_array( $value, $selected, true );
            $html .= "<label class=\"smpi-choice-card smpi-context-choice" . ( $is_selected ? " is-selected" : "" ) . "\"><input class=smpi-setting-array type=checkbox data-key=" . esc_attr( $key ) . " value=" . esc_attr( $value ) . " " . checked( $is_selected, true, false ) . "><span class=smpi-choice-body><strong>" . esc_html( $choice["label"] ) . "</strong><small>" . esc_html( $choice["description"] ) . "</small></span>" . ( $is_selected ? "<span class=smpi-selected-pill>Selected</span>" : "" ) . "</label>";
        }
        return $html . "</div><span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function select_setting_html( string $key, array $options, array $settings, string $label = "Style" ): string {
        $current = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : "";
        $html = "<div class=smpi-control-group><h3>" . esc_html( $label ) . "</h3><div class=smpi-choice-grid>";
        foreach ( $options as $value => $option_label ) {
            $choice = $this->choice_data( $option_label );
            $is_selected = $current === (string) $value;
            $html .= "<label class=\"smpi-choice-card" . ( $is_selected ? " is-selected" : "" ) . "\"><input class=smpi-setting type=radio name=" . esc_attr( "smpi_" . $key ) . " data-key=" . esc_attr( $key ) . " value=" . esc_attr( $value ) . " " . checked( $is_selected, true, false ) . "><span class=smpi-choice-body><strong>" . esc_html( $choice["label"] ) . "</strong>" . ( "" !== $choice["description"] ? "<small>" . esc_html( $choice["description"] ) . "</small>" : "" ) . ( "" !== $choice["preview"] ? "<span class=smpi-choice-preview>" . $this->preview_html( $choice["preview"] ) . "</span>" : "" ) . "</span>" . ( $is_selected ? "<span class=smpi-selected-pill>Selected</span>" : "" ) . "</label>";
        }
        return $html . "</div><span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function template_color_setting_html( string $surface, string $label, array $settings ): string {
        $definition = TemplateDesignRegistry::definition( $surface );
        if ( [] === $definition ) {
            return "";
        }

        return "<div class=\"smpi-control-group smpi-template-color-control\">" . TemplateColorControl::render( [
            "source_key" => $definition["source_key"],
            "custom_key" => $definition["custom_key"],
            "template_key" => $definition["template_key"],
            "template" => TemplateDesignRegistry::template( $surface, $settings ),
            "source" => TemplateDesignRegistry::source( $surface, $settings ),
            "custom" => (string) ( $settings[ $definition["custom_key"] ] ?? "" ),
            "palettes" => $definition["palettes"],
            "variables" => $definition["variables"],
            "fallback" => $definition["fallback"],
            "title" => $label,
            "description" => "Choose the original template color, a site color, or a custom design color. Only mapped accents and tints change; typography stays unchanged.",
            "input_class" => "smpi-setting",
            "control_class" => "smpi-template-color-core-control",
            "preview_scope" => ".smpi-design-host",
            "status_html" => "<span class=spinner></span><span class=\"smpi-save-state\" aria-live=\"polite\"></span>",
        ] ) . "</div>";
    }

    private function optional_color_setting_html( string $key, string $label, array $settings, string $inherit_label ): string {
        $default = Settings::color_default( $key );
        $color = sanitize_hex_color( (string) ( $settings[ $key ] ?? "" ) ) ?: "";
        return "<div class=smpi-control-group>" . ColorControl::render( [
            "key" => $key,
            "label" => $label,
            "value" => $color,
            "default" => $default,
            "allow_inherit" => true,
            "inherited_value" => $default,
            "inherit_label" => $inherit_label,
            "inherited_status_label" => "Template background",
            "control_class" => "smpi-color-core-control",
            "hex_input_class" => "smpi-color-setting",
            "value_input_class" => "smpi-setting smpi-color-hidden",
            "picker_class" => "smpi-color-picker-control",
            "preview_scope" => ".smpi-design-host",
            "preview_variables" => [ "--smpi-bc-background" => "color" ],
            "status_html" => "<span class=spinner></span><span class=\"smpi-save-state\" aria-live=\"polite\"></span>",
        ] ) . "</div>";
    }

    private function summary_background_setting_html( array $settings ): string {
        $mode = TemplateBackground::normalize_mode( (string) ( $settings["post_summary_background_mode"] ?? TemplateBackground::TEMPLATE ) );
        $color = sanitize_hex_color( (string) ( $settings["post_summary_background_color"] ?? "#ffffff" ) ) ?: "#ffffff";
        $options = "";
        foreach ( TemplateBackground::options() as $value => $option ) {
            $selected = $mode === $value;
            $options .= '<label class="smpi-summary-background-mode-option' . ( $selected ? ' is-selected' : '' ) . '">'
                . '<input class="smpi-setting" type="radio" name="smpi_post_summary_background_mode" data-key="post_summary_background_mode" value="' . esc_attr( $value ) . '" ' . checked( $selected, true, false ) . '>'
                . '<span><strong>' . esc_html( (string) $option["label"] ) . '</strong><small>' . esc_html( (string) $option["description"] ) . '</small></span>'
                . '</label>';
        }
        $picker = ColorControl::render( [
            "key" => "post_summary_background_color",
            "label" => "Background color",
            "description" => "Used only when Custom background is selected.",
            "value" => $color,
            "default" => "#ffffff",
            "allow_inherit" => false,
            "import_brand" => false,
            "control_class" => "smpi-color-core-control",
            "hex_input_class" => "smpi-color-setting",
            "value_input_class" => "smpi-setting smpi-color-hidden",
            "picker_class" => "smpi-color-picker-control",
            "preview_scope" => ".smpi-design-host",
            "preview_variables" => [ "--smpi-summary-background" => "color" ],
            "status_html" => "<span class=spinner></span><span class=\"smpi-save-state\" aria-live=\"polite\"></span>",
        ] );

        return '<div class="smpi-control-group smpi-summary-background-control" data-smpi-summary-background-control>'
            . '<h3>Summary background</h3><div class="smpi-summary-background-mode-options">' . $options . '</div>'
            . '<div class="smpi-summary-background-picker" data-smpi-summary-background-picker>' . $picker . '</div>'
            . self::summary_background_assets()
            . '</div>';
    }

    private static function summary_background_assets(): string {
        static $rendered = false;
        if ( $rendered ) {
            return "";
        }
        $rendered = true;
        return <<<'HTML'
<style id="smpi-summary-background-control-css">.smpi-summary-background-mode-options{display:grid;gap:8px;grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:14px}.smpi-summary-background-mode-option{align-items:flex-start;background:#fff;border:1px solid #d8dee8;border-radius:6px;cursor:pointer;display:flex;gap:9px;padding:11px 12px}.smpi-summary-background-mode-option input{margin-top:2px}.smpi-summary-background-mode-option strong,.smpi-summary-background-mode-option small{display:block}.smpi-summary-background-mode-option small{color:#64748b;line-height:1.35;margin-top:2px}.smpi-summary-background-mode-option:has(input:checked){background:#f1f5fb;border-color:#3157d5;box-shadow:inset 0 0 0 1px #3157d5}.smpi-summary-background-picker{transition:opacity .15s ease}.smpi-summary-background-control:not(.is-custom) .smpi-summary-background-picker{opacity:.48}@media(max-width:900px){.smpi-summary-background-mode-options{grid-template-columns:1fr}}</style>
<script id="smpi-summary-background-control-js">(function(){if(window.smpiSummaryBackgroundReady)return;window.smpiSummaryBackgroundReady=true;function mode(root){var input=root?root.querySelector('input[data-key="post_summary_background_mode"]:checked'):null;return input?input.value:"template"}function color(root){var control=root?root.querySelector('[data-hpc-color-control][data-key="post_summary_background_color"]'):null;return control?String(control.getAttribute("data-hpc-color-effective")||"#ffffff"):"#ffffff"}function sync(root){if(!root)return;var current=mode(root),picker=root.querySelector("[data-smpi-summary-background-picker]"),host=root.closest(".smpi-design-host");root.classList.toggle("is-custom",current==="custom");if(picker)picker.querySelectorAll("input,button").forEach(function(input){input.disabled=current!=="custom";input.setAttribute("aria-disabled",current==="custom"?"false":"true")});if(host){if(current==="none")host.style.setProperty("--smpi-summary-background","transparent");else if(current==="custom")host.style.setProperty("--smpi-summary-background",color(root));else host.style.removeProperty("--smpi-summary-background")}}function init(root){(root||document).querySelectorAll("[data-smpi-summary-background-control]").forEach(sync)}document.addEventListener("change",function(event){var root=event.target.closest("[data-smpi-summary-background-control]");if(root)sync(root)});document.addEventListener("hexa-color-change",function(event){var control=event.detail&&event.detail.control,root=control?control.closest("[data-smpi-summary-background-control]"):null;if(root)sync(root)});document.addEventListener("hexa-core-host-tab-loaded",function(event){init(event.detail&&event.detail.panel?event.detail.panel:document)});if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",function(){init(document)});else init(document)})();</script>
HTML;
    }

    private function typography_surface_control_html( string $prefix, array $settings ): string {
        $args = TemplateDesignRegistry::typography_definition( $prefix );
        if ( [] === $args ) {
            return "";
        }

        $args["description"] = "Original Template matches the preview. Use Site Typography inherits every site value. Custom Typography changes only the values you select.";
        $args["mode_control"] = true;
        foreach ( [ "font_family", "font_weight", "font_color" ] as $property ) {
            if ( empty( $args[ $property ]["key"] ) ) {
                continue;
            }
            if ( "font_color" === $property ) {
                $args[ $property ] = array_merge(
                    [
                        "default" => Settings::color_default( $args[ $property ]["key"] ),
                        "import_brand" => true,
                        "import_button_class" => "button button-secondary",
                    ],
                    $args[ $property ]
                );
            }
        }

        return $this->typography_control_html( $prefix, $settings, Settings::typography_preservation_defaults( $prefix ), $args );
    }

    private function typography_control_html( string $prefix, array $settings, $default, array $args ): string {
        $args["prefix"] = $prefix;
        $args["settings"] = $settings;
        $args["defaults"] = $default;
        $args["preview_variables"] = TemplateDesignRegistry::typography_preview_variables( $prefix );
        $args["input_class"] = "smpi-setting";
        $args["control_class"] = trim( "smpi-typography-core-control " . (string) ( $args["control_class"] ?? "" ) );

        return "<div class=\"smpi-control-group smpi-typography-control\">" . TypographyControl::render( $args ) . "<span class=spinner></span><span class=\"smpi-save-state\" aria-live=\"polite\"></span></div>";
    }

    private function design_preview_variables(): string {
        $variables = [];
        $all_settings = Settings::all();
        foreach ( array_keys( TemplateDesignRegistry::definitions() ) as $surface ) {
            $variables = array_merge(
                $variables,
                TemplateDesignRegistry::css_variables( $surface, $all_settings ),
                TemplateDesignRegistry::typography_css_variables( $surface, $all_settings )
            );
        }
        $breadcrumb_background = sanitize_hex_color( (string) ( $all_settings["breadcrumbs_background_color"] ?? "" ) );
        if ( $breadcrumb_background ) {
            $variables["--smpi-bc-background"] = $breadcrumb_background;
        }
        $summary_background = TemplateBackground::css_value(
            (string) ( $all_settings["post_summary_background_mode"] ?? TemplateBackground::TEMPLATE ),
            (string) ( $all_settings["post_summary_background_color"] ?? "#ffffff" )
        );
        if ( "" !== $summary_background ) {
            $variables["--smpi-summary-background"] = $summary_background;
        }
        $declarations = [];
        foreach ( $variables as $variable => $value ) {
            $declarations[] = $variable . ":" . $value;
        }
        return implode( ";", $declarations );
    }

    private function number_setting_html( string $key, string $label, array $settings, int $min = 8, int $max = 64, string $suffix = "" ): string {
        $value = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : $min;
        $value = max( $min, min( $max, $value ) );
        return "<div class=smpi-control-group><h3>" . esc_html( $label ) . "</h3><label class=smpi-number-control><input class=smpi-setting type=number min=" . esc_attr( (string) $min ) . " max=" . esc_attr( (string) $max ) . " data-key=" . esc_attr( $key ) . " value=" . esc_attr( (string) $value ) . "><span>" . esc_html( $suffix ) . "</span></label><span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function author_context_overrides_html( array $settings ): string {
        $contexts = [
            "single_author" => [ "label" => "Single header author", "description" => "Overrides the badge beside the top byline on single.php." ],
            "single_footer" => [ "label" => "Footer/about-author", "description" => "Overrides the badge beside the bottom author profile name." ],
            "loop_cards" => [ "label" => "Loop card authors", "description" => "Overrides author badges in Elementor loop/recent-article cards." ],
            "home" => [ "label" => "Home page authors", "description" => "Overrides author badges in home page article lists." ],
            "author" => [ "label" => "Author archive/profile", "description" => "Overrides badges on author.php archive/profile pages." ],
        ];
        $default_color = TemplateDesignRegistry::effective_color( "muckrack_verified", $settings );
        $html = "<div class=\"smpi-control-group smpi-context-overrides\"><h3>Context overrides</h3><p class=smpi-muted>Choose a custom color or use the default color above. Every context uses the same Hexa WP Core color control.</p><div class=smpi-context-override-list>";
        foreach ( $contexts as $context => $meta ) {
            $color_key = "muckrack_icon_color_" . $context;
            $size_key = "muckrack_icon_size_" . $context;
            $margin_left_key = "muckrack_icon_margin_left_" . $context;
            $margin_top_key = "muckrack_icon_margin_top_" . $context;
            $color = sanitize_hex_color( (string) ( $settings[ $color_key ] ?? "" ) ) ?: "";
            $size = isset( $settings[ $size_key ] ) ? absint( $settings[ $size_key ] ) : 0;
            $size = $size > 0 ? max( 8, min( 64, $size ) ) : 0;
            $margin_left = isset( $settings[ $margin_left_key ] ) && "" !== (string) $settings[ $margin_left_key ] ? max( -32, min( 64, (int) $settings[ $margin_left_key ] ) ) : "";
            $margin_top = isset( $settings[ $margin_top_key ] ) && "" !== (string) $settings[ $margin_top_key ] ? max( -32, min( 64, (int) $settings[ $margin_top_key ] ) ) : "";
            $color_control = ColorControl::render( [
                "key" => $color_key,
                "id" => "smpi-" . $color_key,
                "label" => "Color override",
                "value" => $color,
                "default" => $default_color,
                "allow_inherit" => true,
                "inherited_value" => $default_color,
                "control_class" => "smpi-color-core-control smpi-context-color-control",
                "hex_input_class" => "smpi-color-setting",
                "picker_class" => "smpi-color-picker-control",
                "value_input_class" => "smpi-setting smpi-color-hidden",
                "import_brand" => true,
                "import_button_class" => "button button-secondary",
                "inherit_button_class" => "button button-secondary",
            ] );
            $html .= "<div class=smpi-context-override-row>"
                . "<div class=smpi-context-override-copy><strong>" . esc_html( $meta["label"] ) . "</strong><small>" . esc_html( $meta["description"] ) . "</small></div>"
                . $color_control
                . "<label>Size override <input class=smpi-setting type=number min=0 max=64 data-key=" . esc_attr( $size_key ) . " value=\"" . esc_attr( (string) $size ) . "\"><span>px</span></label>"
                . "<label>Margin left <input class=smpi-setting type=number min=-32 max=64 placeholder=inherit data-key=" . esc_attr( $margin_left_key ) . " value=\"" . esc_attr( (string) $margin_left ) . "\"><span>px</span></label>"
                . "<label>Margin top <input class=smpi-setting type=number min=-32 max=64 placeholder=inherit data-key=" . esc_attr( $margin_top_key ) . " value=\"" . esc_attr( (string) $margin_top ) . "\"><span>px</span></label>"
                . "</div>";
        }
        return $html . "</div><span class=spinner></span><span class=\"smpi-save-state\" aria-live=\"polite\"></span></div>";
    }

    private function breadcrumb_style_options(): array {
        return [
            "bc-b1" => [ "label" => "Option 1: Tinted Band + Title", "description" => "Page title above a soft brand-tinted band; breadcrumb beneath in muted text.", "preview" => $this->breadcrumb_design_preview_html( "bc-b1" ) ],
            "bc-b2" => [ "label" => "Option 2: Minimal Hairline", "description" => "Thin row over a hairline divider, chevron separators, current page in bold ink.", "preview" => $this->breadcrumb_design_preview_html( "bc-b2" ) ],
            "bc-b3" => [ "label" => "Option 3: Uppercase Eyebrow", "description" => "Letter-spaced uppercase crumbs over a short accent rule.", "preview" => $this->breadcrumb_design_preview_html( "bc-b3" ) ],
            "bc-b4" => [ "label" => "Option 4: Soft Chips", "description" => "Each crumb is a rounded chip; current page becomes a filled accent pill.", "preview" => $this->breadcrumb_design_preview_html( "bc-b4" ) ],
            "bc-b5" => [ "label" => "Option 5: Gradient Lead-in", "description" => "Breadcrumb above a large serif headline on a soft top-down gradient.", "preview" => $this->breadcrumb_design_preview_html( "bc-b5" ) ],
            "bc-b6" => [ "label" => "Option 6: Flush Minimal", "description" => "Capital-case crumbs aligned to the header grid on a full-width background band, with no forced divider.", "preview" => $this->breadcrumb_design_preview_html( "bc-b6" ) ],
        ];
    }

    private function breadcrumb_post_type_options(): array {
        $options = [];
        foreach ( get_post_types( [ "public" => true ], "objects" ) as $type => $object ) {
            if ( "attachment" === $type ) {
                continue;
            }
            $options[ $type ] = isset( $object->labels->name ) ? (string) $object->labels->name : $type;
        }
        return $options;
    }

    private function toc_style_options(): array {
        return [
            "unstyled" => [ "label" => "Strip all design (Elementor)", "description" => "Keep semantic TOC markup and anchor behavior while loading no SMP presentation on this component.", "preview" => $this->toc_design_preview_html( "unstyled" ) ],
            "none" => [ "label" => "No style", "description" => "Bare list markup only.", "preview" => $this->toc_design_preview_html( "none" ) ],
            "toc00" => [ "label" => "Minimal List", "description" => "Quiet label and plain links.", "preview" => $this->toc_design_preview_html( "toc00" ) ],
            "toc01" => [ "label" => "Side Rule", "description" => "Left margin hairline treatment.", "preview" => $this->toc_design_preview_html( "toc01" ) ],
            "toc02" => [ "label" => "Soft Box", "description" => "Contained gray card with numbered items.", "preview" => $this->toc_design_preview_html( "toc02" ) ],
            "toc03" => [ "label" => "Numbered Rows", "description" => "Divided rows with blue indexes.", "preview" => $this->toc_design_preview_html( "toc03" ) ],
            "toc04" => [ "label" => "Jump Pills", "description" => "Horizontal pill links.", "preview" => $this->toc_design_preview_html( "toc04" ) ],
        ];
    }

    private function article_drop_cap_style_options(): array {
        return [
            "dropcap-classic" => [ "label" => "Classic editorial", "description" => "A large unframed letter with traditional editorial spacing.", "preview" => $this->article_drop_cap_preview_html( "dropcap-classic" ) ],
            "dropcap-highlight" => [ "label" => "Highlight block", "description" => "A solid accent tile behind the first letter.", "preview" => $this->article_drop_cap_preview_html( "dropcap-highlight" ) ],
            "dropcap-outline" => [ "label" => "Outline frame", "description" => "A thin accent frame around the first letter.", "preview" => $this->article_drop_cap_preview_html( "dropcap-outline" ) ],
            "dropcap-side-rule" => [ "label" => "Side rule", "description" => "A compact first letter anchored by a strong vertical rule.", "preview" => $this->article_drop_cap_preview_html( "dropcap-side-rule" ) ],
            "dropcap-soft-tile" => [ "label" => "Soft tile", "description" => "A restrained tinted tile with a compact corner radius.", "preview" => $this->article_drop_cap_preview_html( "dropcap-soft-tile" ) ],
            "dropcap-script-classic" => [ "label" => "Script classic", "description" => "Dancing Script with clean editorial spacing.", "preview" => $this->article_drop_cap_preview_html( "dropcap-script-classic" ) ],
            "dropcap-script-tile" => [ "label" => "Script tile", "description" => "Great Vibes on a soft tinted tile.", "preview" => $this->article_drop_cap_preview_html( "dropcap-script-tile" ) ],
            "dropcap-script-round" => [ "label" => "Script round", "description" => "Parisienne inside a rounded tinted badge.", "preview" => $this->article_drop_cap_preview_html( "dropcap-script-round" ) ],
            "dropcap-script-underline" => [ "label" => "Script underline", "description" => "Pinyon Script with an accent underline.", "preview" => $this->article_drop_cap_preview_html( "dropcap-script-underline" ) ],
            "dropcap-script-shadow" => [ "label" => "Script shadow", "description" => "Allura with a soft offset tint shadow.", "preview" => $this->article_drop_cap_preview_html( "dropcap-script-shadow" ) ],
        ];
    }

    private function article_heading_style_options(): array {
        return [
            "none" => [ "label" => "No style", "description" => "Leave theme H2 and H3 styles untouched.", "preview" => $this->article_heading_preview_html( "none" ) ],
            "h2-tick" => [ "label" => "H2 01: Accent Tick", "description" => "Short accent bar before the heading.", "preview" => $this->article_heading_preview_html( "h2-tick" ) ],
            "h2-leftrule" => [ "label" => "H2 02: Left Rule", "description" => "Vertical accent bar, echoing the What to Know block.", "preview" => $this->article_heading_preview_html( "h2-leftrule" ) ],
            "h2-underline" => [ "label" => "H2 03: Short Underline", "description" => "Compact accent underline anchored left.", "preview" => $this->article_heading_preview_html( "h2-underline" ) ],
            "h2-topline" => [ "label" => "H2 04: Top Hairline", "description" => "Full hairline above with generous breathing room.", "preview" => $this->article_heading_preview_html( "h2-topline" ) ],
            "h2-dot" => [ "label" => "H2 05: Lead Dot", "description" => "Small accent dot leads the heading.", "preview" => $this->article_heading_preview_html( "h2-dot" ) ],
            "h2-trailingrule" => [ "label" => "H2 06: Trailing Rule", "description" => "Heading followed by a line that fills the row.", "preview" => $this->article_heading_preview_html( "h2-trailingrule" ) ],
            "h2-serif" => [ "label" => "H2 07: Serif + Tick", "description" => "Serif heading with a thin accent tick.", "preview" => $this->article_heading_preview_html( "h2-serif" ) ],
            "h2-uppercase" => [ "label" => "H2 08: Uppercase Eyebrow", "description" => "Letter-spaced caps over a short accent rule.", "preview" => $this->article_heading_preview_html( "h2-uppercase" ) ],
            "h2-gradient" => [ "label" => "H2 09: Gradient Underline", "description" => "Accent-to-transparent underline.", "preview" => $this->article_heading_preview_html( "h2-gradient" ) ],
            "h2-bracket" => [ "label" => "H2 10: Corner Bracket", "description" => "Small accent bracket frames the start.", "preview" => $this->article_heading_preview_html( "h2-bracket" ) ],
            "h2-number" => [ "label" => "H2 11: Indexed", "description" => "Auto-numbered accent index before the heading.", "preview" => $this->article_heading_preview_html( "h2-number" ) ],
            "h2-square" => [ "label" => "H2 12: Accent Square", "description" => "Rounded accent square leads in.", "preview" => $this->article_heading_preview_html( "h2-square" ) ],
            "h2-highlight" => [ "label" => "H2 13: Marker Highlight", "description" => "Subtle accent wash under the text.", "preview" => $this->article_heading_preview_html( "h2-highlight" ) ],
            "h2-double" => [ "label" => "H2 14: Framed Rules", "description" => "Heading set between an accent rule and a hairline.", "preview" => $this->article_heading_preview_html( "h2-double" ) ],
            "h2-corner_tick" => [ "label" => "H2 15: Standing Tick", "description" => "Short vertical accent tick above-left.", "preview" => $this->article_heading_preview_html( "h2-corner_tick" ) ],
        ];
    }

    private function article_heading_preview_sample( string $style ): array {
        $samples = [
            "h2-tick" => [ "Where the method began", "This section walks through how the idea first took shape and why it connected with early customers." ],
            "h2-leftrule" => [ "Building the framework", "A repeatable structure replaced improvisation, giving the team something concrete to lean on." ],
            "h2-underline" => [ "A practical foundation", "The early groundwork mattered more than the origin story, and it shows in the results." ],
            "h2-topline" => [ "Scaling the approach", "What worked for one seller had to be translated into a system that worked for many." ],
            "h2-dot" => [ "What changed for sellers", "Confidence followed competence once the fundamentals were taught in the right order." ],
            "h2-trailingrule" => [ "Inside the training", "Each module built on the last, moving from listening skills to live objection handling." ],
            "h2-serif" => [ "From classroom to call floor", "The instincts that make a good teacher turn out to make a good closer too." ],
            "h2-uppercase" => [ "The core principle", "Sell by understanding the person in front of you, not by performing enthusiasm." ],
            "h2-gradient" => [ "Reading the room", "Timing and attention do more for a conversation than any single script ever could." ],
            "h2-bracket" => [ "Handling objections", "Resistance is information; the method treats it as a step rather than a setback." ],
            "h2-number" => [ "Structure over hype", "Process, not personality, is what keeps results steady across very different sellers." ],
            "h2-square" => [ "Coaching the team", "Feedback loops turned individual wins into habits the whole group could repeat." ],
            "h2-highlight" => [ "Why it resonated", "People wanted a method they could trust, and a method is exactly what they got." ],
            "h2-double" => [ "The bigger shift", "The change was less about tactics and more about how sales itself was being taught." ],
            "h2-corner_tick" => [ "What comes next", "The next chapter is about reach - bringing the same clarity to a much wider audience." ],
        ];
        return $samples[ $style ] ?? [ "Where the method began", "This section walks through how the idea first took shape and why it connected with early customers." ];
    }

    private function article_numbered_list_style_options(): array {
        return [
            "none" => [ "label" => "Site default ordered list", "description" => "Use the active theme's existing ordered-list styling without an SMP design.", "preview" => $this->article_numbered_list_preview_html( "none" ) ],
            "nlist01" => [ "label" => "Editorial rows with dividers", "description" => "Large left-aligned numbers and fine horizontal dividers, matching the supplied reference design.", "preview" => $this->article_numbered_list_preview_html( "nlist01" ) ],
            "nlist02" => [ "label" => "Bordered rows with number tiles", "description" => "Each item sits in a bordered row with a solid accent number tile.", "preview" => $this->article_numbered_list_preview_html( "nlist02" ) ],
            "nlist03" => [ "label" => "Left accent rail with index", "description" => "A vertical accent rail groups each item with a compact number above the text.", "preview" => $this->article_numbered_list_preview_html( "nlist03" ) ],
            "nlist04" => [ "label" => "Oversized background numbers", "description" => "Large low-contrast numbers sit behind spacious editorial rows.", "preview" => $this->article_numbered_list_preview_html( "nlist04" ) ],
            "nlist05" => [ "label" => "Circular number markers", "description" => "Small outlined number circles lead compact rows separated by restrained dividers.", "preview" => $this->article_numbered_list_preview_html( "nlist05" ) ],
        ];
    }

    private function inline_photo_treatment_options(): array {
        return [
            "none" => [ "label" => "No style", "description" => "Leave theme figure styles untouched.", "preview" => $this->inline_photo_preview_html( "none" ) ],
            "fig1" => [ "label" => "Treatment 1: Clean rounded", "description" => "Rounded image, soft shadow, serif caption rule.", "preview" => $this->inline_photo_preview_html( "fig1" ) ],
            "fig2" => [ "label" => "Treatment 2: Caption plate", "description" => "Image with flush caption plate.", "preview" => $this->inline_photo_preview_html( "fig2" ) ],
            "fig4" => [ "label" => "Treatment 4: Gradient overlay", "description" => "Caption overlays the photo bottom.", "preview" => $this->inline_photo_preview_html( "fig4" ) ],
            "fig5" => [ "label" => "Treatment 5: Framed plate", "description" => "Thin framed card and editorial caption.", "preview" => $this->inline_photo_preview_html( "fig5" ) ],
        ];
    }

    private function featured_image_caption_template_options(): array {
        return [
            "none" => [ "label" => "No style", "description" => "Detect the featured image but leave caption output disabled.", "preview" => $this->featured_image_caption_preview_html( "none" ) ],
            "fig1" => [ "label" => "Treatment 1: Clean rounded", "description" => "Duplicate of inline treatment 1 for featured images only.", "preview" => $this->featured_image_caption_preview_html( "fig1" ) ],
            "fig2" => [ "label" => "Treatment 2: Caption plate", "description" => "Duplicate of inline treatment 2 for featured images only.", "preview" => $this->featured_image_caption_preview_html( "fig2" ) ],
            "fig4" => [ "label" => "Treatment 4: Gradient overlay", "description" => "Duplicate of inline treatment 4 for featured images only.", "preview" => $this->featured_image_caption_preview_html( "fig4" ) ],
            "fig5" => [ "label" => "Treatment 5: Framed plate", "description" => "Duplicate of inline treatment 5 for featured images only.", "preview" => $this->featured_image_caption_preview_html( "fig5" ) ],
        ];
    }

    private function post_summary_style_options(): array {
        return [
            "unstyled" => [ "label" => "Strip all design (Elementor)", "description" => "Keep the What to know heading and semantic summary markup while loading no SMP presentation on this component.", "preview" => $this->summary_design_preview_html( "unstyled" ) ],
            "none" => [ "label" => "Site content with no SMP styling", "description" => "Render the Summary exactly as entered using the active site's content styles.", "preview" => $this->summary_design_preview_html( "none" ) ],
            "sum00" => [ "label" => "Gray panel with underlined heading", "description" => "Flat gray panel with a strong selected-color heading underline.", "preview" => $this->summary_design_preview_html( "sum00" ) ],
            "sum01" => [ "label" => "White card with left accent", "description" => "White bordered card with a selected-color rule along the left edge.", "preview" => $this->summary_design_preview_html( "sum01" ) ],
            "sum02" => [ "label" => "Compact bullets with top rule", "description" => "Compact bullet list inside a thin border with a selected-color top rule.", "preview" => $this->summary_design_preview_html( "sum02" ) ],
            "sum03" => [ "label" => "Solid-color header card", "description" => "Bordered white card topped by a solid selected-color header bar.", "preview" => $this->summary_design_preview_html( "sum03" ) ],
            "sum04" => [ "label" => "Soft-tint icon callout", "description" => "Soft selected-color panel with a small matching title icon.", "preview" => $this->summary_design_preview_html( "sum04" ) ],
            "sum05" => [ "label" => "Bordered block with diamond bullets", "description" => "Thin bordered block with a compact What to know label and outlined diamond bullets.", "preview" => $this->summary_design_preview_html( "sum05" ) ],
        ];
    }

    private function post_faq_style_options(): array {
        return [
            "none" => [ "label" => "No style", "description" => "Render the FAQ field exactly as entered.", "preview" => $this->faq_design_preview_html( "none" ) ],
            "unstyled" => [ "label" => "Strip all design (Elementor)", "description" => "Keep the FAQ heading and semantic FAQ markup while loading no SMP presentation on this component.", "preview" => $this->faq_design_preview_html( "unstyled" ) ],
            "faq00" => [ "label" => "Accordion", "description" => "Accessible details style when markup supports it.", "preview" => $this->faq_design_preview_html( "faq00" ) ],
            "faq01" => [ "label" => "Stacked Q and A", "description" => "Divided editorial Q and A rows.", "preview" => $this->faq_design_preview_html( "faq01" ) ],
            "faq02" => [ "label" => "Card List", "description" => "Each question in its own card.", "preview" => $this->faq_design_preview_html( "faq02" ) ],
            "faq03" => [ "label" => "Numbered", "description" => "Oversized ghost numbers.", "preview" => $this->faq_design_preview_html( "faq03" ) ],
            "faq04" => [ "label" => "People Also Ask", "description" => "Soft Google-like Q and A cards.", "preview" => $this->faq_design_preview_html( "faq04" ) ],
        ];
    }

    private function post_summary_placement_options(): array {
        return [
            "manual" => [
                "label" => "Manual shortcode",
                "description" => "Do not insert the Summary automatically. Place its shortcode in Elementor or post content.",
                "preview" => "<code>[smp_post_summary]</code>",
            ],
            "above_content" => [
                "label" => "Above content",
                "description" => "Insert the Summary immediately before the article content.",
            ],
            "below_content" => [
                "label" => "Below content",
                "description" => "Insert the Summary immediately after the article content.",
            ],
        ];
    }

    private function post_faq_placement_options(): array {
        return [
            "manual" => [
                "label" => "Manual shortcode",
                "description" => "Do not insert FAQs automatically. Place the shortcode where the block should appear.",
                "preview" => "<code>[smp_post_faqs]</code>",
            ],
            "below_content" => [
                "label" => "Below content",
                "description" => "Insert the FAQ block immediately after the article content.",
            ],
            "below_author" => [
                "label" => "Below author",
                "description" => "Insert the FAQ block immediately after the article author section.",
            ],
        ];
    }

    private function preview_image_url(): string {
        return "https://picsum.photos/seed/smpi-inline/720/420";
    }

    private function toc_preview_label( string $style ): string {
        if ( "toc00" === $style ) { return "In this article"; }
        if ( in_array( $style, [ "unstyled", "toc02" ], true ) ) { return "On this page"; }
        if ( "toc04" === $style ) { return "Jump to"; }
        return "Table of Contents";
    }

    private function breadcrumb_design_preview_html( string $style ): string {
        $style = \smp_publication_integration\Content\ArticleStyles::normalize_breadcrumb_style( $style );
        $title = "Amazon Shelves Guadagnino Film";
        $crumbs = "<nav aria-label=\"breadcrumbs\" class=\"rank-math-breadcrumb smpi-template-content smpi-breadcrumb-nav\"><p class=\"smpi-template-list smpi-breadcrumb-list\"><a class=\"smpi-template-item smpi-template-link smpi-breadcrumb-item smpi-breadcrumb-link\" href=\"#\">Home</a><span class=\"separator smpi-breadcrumb-separator\"> - </span><a class=\"smpi-template-item smpi-template-link smpi-breadcrumb-item smpi-breadcrumb-link\" href=\"#\">Entertainment</a><span class=\"separator smpi-breadcrumb-separator\"> - </span><span class=\"last smpi-template-item smpi-breadcrumb-item smpi-breadcrumb-current\">" . esc_html( $title ) . "</span></p></nav>";
        $title_html = in_array( $style, [ "bc-b1", "bc-b5" ], true ) ? "<div class=\"smpi-template-title smpi-breadcrumb-title\">" . esc_html( $title ) . "</div>" : "";
        $inner = "bc-b5" === $style ? $crumbs . $title_html : $title_html . $crumbs;
        $classes = \smp_publication_integration\Content\TemplateMarkup::root_classes( "breadcrumbs", [ "smpi-breadcrumbs", "smpi-" . $style ] );
        return "<div class=\"smpi-breadcrumbs-band\"><div class=\"" . esc_attr( $classes ) . "\">" . $inner . "</div></div>";
    }

    private function toc_design_preview_html( string $style ): string {
        $content = "<h2>The rise of new technology</h2><h2>What the data reveals</h2>";
        return \smp_publication_integration\Content\TableOfContents::build_toc( $content, $this->toc_preview_label( $style ), $style );
    }

    private function article_heading_preview_html( string $style ): string {
        $style = \smp_publication_integration\Content\ArticleStyles::normalize_article_heading_style( $style );
        $class = "none" === $style ? "smpi-ah-preview h2-stage smpi-ah-preview-none" : "smpi-ah-preview h2-stage smpi-ah-preview-" . $style;
        $sample = $this->article_heading_preview_sample( $style );
        return "<div class=\"smpi-ah-preview-stack\"><div class=\"" . esc_attr( $class ) . "\"><h2 class=\"smpi-template-title smpi-article-heading smpi-article-heading--h2\">" . esc_html( $sample[0] ) . "</h2><p class=\"smpi-template-text smpi-article-paragraph\">" . esc_html( $sample[1] ) . "</p></div><div class=\"" . esc_attr( $class ) . "\"><h3 class=\"smpi-template-title smpi-article-heading smpi-article-heading--h3\">What changed next</h3><p class=\"smpi-template-text smpi-article-paragraph\">The same selected treatment applies to article H3 subheadings inside post content.</p></div></div>";
    }

    private function article_numbered_list_preview_html( string $style ): string {
        $style = \smp_publication_integration\Content\ArticleStyles::normalize_article_numbered_list_style( $style );
        $classes = \smp_publication_integration\Content\TemplateMarkup::root_classes(
            "article-numbered-list",
            [ "smpi-article-list", "smpi-numbered-list", "smpi-numbered-list--" . $style ]
        );
        $items = [
            [ "CSS and JavaScript minification", "Reduce transfer size while keeping the article's critical rendering path predictable." ],
            [ "Image optimization", "Serve efficient formats and dimensions without changing the editorial source image." ],
        ];
        $html = "";
        foreach ( $items as $item ) {
            $html .= "<li class=\"smpi-template-item smpi-article-list-item smpi-numbered-list-item\"><h3 class=\"smpi-template-title smpi-numbered-list-title\">" . esc_html( $item[0] ) . "</h3><p class=\"smpi-template-text smpi-numbered-list-text\">" . esc_html( $item[1] ) . "</p></li>";
        }
        return "<ol class=\"" . esc_attr( $classes ) . "\">" . $html . "</ol>";
    }

    private function article_drop_cap_preview_html( string $style ): string {
        $style = \smp_publication_integration\Content\ArticleStyles::normalize_article_drop_cap_style( $style );
        return "<div class=\"smpi-template smpi-template--article-content smpi-dropcap-preview smpi-dropcap-preview--" . esc_attr( $style ) . "\"><p class=\"smpi-template-text smpi-article-paragraph smpi-article-lead\">Female founders are reshaping how modern publications tell important stories.</p></div>";
    }

    private function article_heading_report_html(): string {
        $enabled = Settings::bool( "article_heading_styles_enabled" );
        $style = (string) Settings::get( "article_heading_style", "h2-tick" );
        $h2 = (int) Settings::get( "article_heading_h2_font_size", 23 );
        $h3 = (int) Settings::get( "article_heading_h3_font_size", 20 );
        $preserved = [];
        foreach ( TypographyPreservation::values( Settings::all(), "article_heading", true ) as $key => $preserve ) {
            if ( $preserve ) {
                $label = [ "font_family" => "font", "font_size" => "size", "font_color" => "color", "font_weight" => "weight" ][ $key ] ?? $key;
                $preserved[] = $label;
            }
        }
        $preserve_text = empty( $preserved ) ? "No theme typography is preserved." : "Preserving theme " . implode( ", ", $preserved ) . ".";
        return $this->simple_status_html( $enabled, "Current template: " . $style . ". " . $preserve_text . " Font: " . Settings::font_family_label( "article_heading_font_family" ) . ". H2: " . $h2 . "px. H3: " . $h3 . "px. Applies only to single post content." );
    }

    private function article_numbered_list_report_html(): string {
        $enabled = Settings::bool( "article_numbered_lists_enabled" );
        $style = \smp_publication_integration\Content\ArticleStyles::normalize_article_numbered_list_style( (string) Settings::get( "article_numbered_list_style", "nlist01" ) );
        $size = (int) Settings::get( "article_numbered_list_font_size", 16 );
        return $this->simple_status_html(
            $enabled,
            "Current design: " . $style . ". Font: " . Settings::font_family_label( "article_numbered_list_font_family" ) . ". Text size: " . $size . "px. Applies to top-level ordered lists on single posts."
        );
    }

    private function article_drop_cap_report_html(): string {
        $enabled = Settings::bool( "article_drop_cap_enabled" );
        $style = \smp_publication_integration\Content\ArticleStyles::normalize_article_drop_cap_style( (string) Settings::get( "article_drop_cap_style", "dropcap-classic" ) );
        $script_font = \smp_publication_integration\Content\ArticleStyles::article_drop_cap_script_font_for_style( $style );
        $preserved = array_keys( array_filter( TypographyPreservation::values( Settings::all(), "article_drop_cap", false ) ) );
        $size = (int) Settings::get( "article_drop_cap_font_size", 96 );
        $default_color = Settings::color_default( "article_drop_cap_color" );
        $color = sanitize_hex_color( (string) Settings::get( "article_drop_cap_color", $default_color ) ) ?: $default_color;
        $html = $this->simple_status_html( $enabled, $enabled ? "Drop cap CSS is injected on single post content." : "Drop cap CSS is disabled." );
        $font_source = [] !== $script_font ? (string) $script_font["label"] . " (template)" : Settings::font_family_label( "article_drop_cap_font_family" );
        $html .= "<table class=widefat><tbody><tr><th>Template</th><td><code>" . esc_html( $style ) . "</code></td></tr><tr><th>Font source</th><td>" . esc_html( $font_source ) . "</td></tr><tr><th>Font weight</th><td>" . esc_html( [] !== $script_font ? (string) $script_font["default_weight"] : Settings::font_weight_label( "article_drop_cap_font_weight" ) ) . "</td></tr><tr><th>Preserved</th><td>" . esc_html( [] === $preserved ? "None" : implode( ", ", array_map( static fn( string $property ): string => str_replace( "_", " ", $property ), $preserved ) ) ) . "</td></tr><tr><th>Accent color</th><td>" . esc_html( $color ) . "</td></tr><tr><th>Size</th><td>" . esc_html( (string) $size ) . "px</td></tr><tr><th>Selector</th><td><code>body.single-post .smpi-article-lead::first-letter</code></td></tr><tr><th>CSS source</th><td><code>ArticleStyles::article_drop_cap_rules()</code></td></tr></tbody></table>";
        return $html;
    }

    private function inline_photo_preview_html( string $style ): string {
        $img = "<img class=\"smpi-template-image smpi-inline-photo-image\" src=\"" . esc_url( $this->preview_image_url() ) . "\" alt=\"\">";
        $cap = "<figcaption class=\"smpi-template-caption smpi-inline-photo-caption\">Collegiate summer leagues provide a platform for emerging talent.</figcaption>";
        $classes = \smp_publication_integration\Content\TemplateMarkup::root_classes( "inline-photo", [ "smpi-pp", "smpi-pp-" . $style, "smpi-inline-photo", "smpi-inline-photo--" . $style ] );
        return "<figure class=\"" . esc_attr( $classes ) . "\">" . $img . $cap . "</figure>";
    }

    private function featured_image_caption_preview_html( string $style ): string {
        $img = "<img class=\"smpi-template-image smpi-featured-image-caption-image\" src=\"" . esc_url( $this->preview_image_url() ) . "\" alt=\"\">";
        $cap = "<span class=\"smpi-template-caption smpi-featured-image-caption-text\">Featured image caption pulled from the media attachment.</span>";
        $classes = \smp_publication_integration\Content\TemplateMarkup::root_classes( "featured-image-caption", [ "smpi-fi-preview", "smpi-fi-preview-" . $style, "smpi-featured-image-caption", "smpi-featured-image-caption--" . $style ] );
        return "<figure class=\"" . esc_attr( $classes ) . "\">" . $img . $cap . "</figure>";
    }

    private function featured_image_caption_report_html(): string {
        $enabled = Settings::bool( "featured_image_caption_templates_enabled" );
        $style = (string) Settings::get( "featured_image_caption_template", "fig2" );
        $html = $this->simple_status_html( $enabled, "Current template: " . $style . ". Font: " . Settings::font_family_label( "featured_image_caption_font_family" ) . ". Auto-detects single post and press-release featured images with media captions." );
        $items = get_posts( [
            "post_type"      => [ "post", "press-release" ],
            "post_status"    => [ "publish", "pending", "draft" ],
            "posts_per_page" => 8,
            "orderby"        => "date",
            "order"          => "DESC",
            "meta_query"     => [ [ "key" => "_thumbnail_id", "compare" => "EXISTS" ] ],
        ] );
        if ( empty( $items ) ) {
            return $html . "<p class=smpi-muted>No recent posts with featured images were found.</p>";
        }
        $html .= "<table class=\"widefat striped\"><thead><tr><th>Post</th><th>Type</th><th>Featured image caption</th><th>Proof URL</th></tr></thead><tbody>";
        foreach ( $items as $item ) {
            $thumb_id = (int) get_post_thumbnail_id( $item->ID );
            $caption = $thumb_id ? trim( (string) wp_get_attachment_caption( $thumb_id ) ) : "";
            $url = get_permalink( $item );
            $html .= "<tr><td>" . esc_html( get_the_title( $item ) ) . "</td><td><code>" . esc_html( get_post_type( $item ) ) . "</code></td><td>" . ( "" !== $caption ? esc_html( $caption ) : "<span class=smpi-warn>No media caption</span>" ) . "</td><td>" . ( $url ? "<a href=\"" . esc_url( $url ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">Open</a>" : "" ) . "</td></tr>";
        }
        return $html . "</tbody></table>";
    }

    private function summary_design_preview_html( string $style ): string {
        $content = "<ul class=\"smpi-template-list smpi-post-summary-list\"><li class=\"smpi-template-item smpi-post-summary-item\">Commitments exceed \$150 billion across five regions.</li><li class=\"smpi-template-item smpi-post-summary-item\">The fund becomes operational after final approval.</li></ul>";
        $classes = \smp_publication_integration\Content\TemplateMarkup::root_classes( "article-summary", [ "smpi-post-summary", "smpi-" . $style ] );
        if ( "none" === $style ) {
            return "<aside class=\"" . esc_attr( $classes ) . "\"><div class=\"smpi-template-content smpi-post-summary-content\">" . $content . "</div></aside>";
        }
        $title = esc_html( \smp_publication_integration\Content\ArticleStyles::summary_title( $style ) );
        return "<aside class=\"" . esc_attr( $classes ) . "\"><h2 class=\"smpi-template-title smpi-post-summary-title\">" . $title . "</h2><div class=\"smpi-template-content smpi-post-summary-content\">" . $content . "</div></aside>";
    }

    private function faq_design_preview_html( string $style ): string {
        $content = "<ul class=\"smpi-template-list smpi-post-faq-list\"><li class=\"smpi-template-item smpi-post-faq-item\"><h3 class=\"smpi-template-title smpi-post-faq-question\">What record did Messi tie?</h3><div class=\"smpi-template-content smpi-post-faq-answer\"><p class=\"smpi-template-text smpi-post-faq-text\">He tied Klose's World Cup goals record with a hat trick against Algeria.</p></div></li><li class=\"smpi-template-item smpi-post-faq-item\"><h3 class=\"smpi-template-title smpi-post-faq-question\">What injury did Jose Ramirez sustain?</h3><div class=\"smpi-template-content smpi-post-faq-answer\"><p class=\"smpi-template-text smpi-post-faq-text\">Surgery on a broken hamate bone, about five to seven weeks of recovery.</p></div></li></ul>";
        $classes = \smp_publication_integration\Content\TemplateMarkup::root_classes( "article-faqs", [ "smpi-post-faqs", "smpi-" . $style ] );
        if ( "none" === $style ) {
            return "<section class=\"" . esc_attr( $classes ) . "\"><div class=\"smpi-template-content smpi-post-faqs-content\">" . $content . "</div></section>";
        }
        $title = esc_html( \smp_publication_integration\Content\ArticleStyles::faq_title( $style ) );
        return "<section class=\"" . esc_attr( $classes ) . "\"><h2 class=\"smpi-template-title smpi-post-faqs-title\">" . $title . "</h2><div class=\"smpi-template-content smpi-post-faqs-content\">" . $content . "</div></section>";
    }



    private function icon_style_setting_html( array $settings ): string {
        return $this->select_setting_html( "muckrack_icon_style", [
            "circle_check" => [ "label" => "Solid circle check", "description" => "Uses the supplied filled-circle SVG. The selected icon color controls the SVG fill.", "preview" => $this->author_icon_preview_html( $settings, "circle_check" ) ],
            "circle_outline_check" => [ "label" => "Outline circle check", "description" => "Uses the supplied outline-circle SVG. The selected icon color controls the SVG stroke.", "preview" => $this->author_icon_preview_html( $settings, "circle_outline_check" ) ],
            "check" => [ "label" => "Plain check", "description" => "Uses the supplied check path only, with no circle.", "preview" => $this->author_icon_preview_html( $settings, "check" ) ],
        ], $settings, "Icon style" );
    }

    private function preview_html( string $html ): string {
        $allowed = wp_kses_allowed_html( "post" );
        $allowed["svg"] = [ "class" => true, "xmlns" => true, "width" => true, "height" => true, "viewBox" => true, "viewbox" => true, "fill" => true, "aria-hidden" => true, "focusable" => true ];
        $allowed["path"] = [ "fill-rule" => true, "clip-rule" => true, "d" => true, "fill" => true, "stroke" => true, "stroke-width" => true, "stroke-linecap" => true, "stroke-linejoin" => true ];
        return wp_kses( $html, $allowed );
    }

    private function choice_data( $choice, string $fallback_description = "" ): array {
        if ( is_array( $choice ) ) {
            return [
                "label" => (string) ( $choice["label"] ?? "" ),
                "description" => (string) ( $choice["description"] ?? $fallback_description ),
                "preview" => (string) ( $choice["preview"] ?? "" ),
            ];
        }
        return [ "label" => (string) $choice, "description" => $fallback_description, "preview" => "" ];
    }

    private function context_description( string $key, string $value ): string {
        $descriptions = [
            "author_social_networks" => [
                "x" => "Shows X only when the resolved author has a usable X or Twitter value.",
                "linkedin" => "Shows LinkedIn only when the resolved author has a usable LinkedIn value.",
                "facebook" => "Shows Facebook only when the resolved author has a usable Facebook value.",
                "instagram" => "Shows Instagram only when the resolved author has a usable Instagram value.",
                "youtube" => "Shows YouTube only when the resolved author has a usable YouTube value.",
                "website" => "Shows the author's personal website only when a usable URL exists.",
                "crunchbase" => "Shows Crunchbase only when the resolved author has a usable Crunchbase value.",
                "muckrack" => "Shows Muck Rack only when the resolved author has a usable Muck Rack value.",
            ],
            "author_social_auto_contexts" => [
                "single_post" => "Appends the canonical icon group once after the main post content. Leave off when the shortcode is already placed in the post template.",
                "author_archive" => "Appends the canonical icon group once after the author archive description rendered by author.php.",
            ],
            "muckrack_verified_contexts" => [
                "single_author" => "Adds the author verification badge beside the byline on single article templates.",
                "single_footer" => "Adds the selected author verification badge to the footer/about-author profile name.",
                "loop_cards" => "Adds author checkmarks to Elementor loop and recent-article cards using each card author link or author text.",
                "home" => "Applies the author badge when author names render in home page article lists.",
                "author" => "Applies the author badge on author archive/profile pages.",
            ],
            "publication_muckrack_placements" => [
                "below_author" => "Places publication verification after the footer/about-author card, never inside the header byline.",
                "bottom_article" => "Places publication verification immediately after the article content widget and before the footer/profile area.",
            ],
            "press_release_include_contexts" => [
                "home" => "Allows press releases to appear in the main home page feed.",
                "category_tag" => "Allows press releases in category and tag archive queries.",
                "author" => "Allows press releases to appear on author archive pages.",
                "single_recent" => "Allows press releases in recent-article modules shown on single posts.",
            ],
            "breadcrumbs_disabled_post_types" => $this->breadcrumb_post_type_descriptions(),
        ];
        return $descriptions[ $key ][ $value ] ?? "Controls where this feature is allowed to run on the front end.";
    }

    private function author_social_style_options( array $settings ): array {
        $size = max( 12, min( 64, (int) ( $settings["author_social_size"] ?? 24 ) ) );
        $descriptions = [
            "social-solid" => "High-contrast filled circles for compact author cards and bylines.",
            "social-outline" => "Lightweight circular outlines that sit cleanly on white or transparent backgrounds.",
            "social-soft" => "Rounded tiles with a quiet color tint and a larger touch target.",
            "social-pills" => "Full network names beside each icon for maximum clarity and accessibility.",
            "social-minimal" => "Bare monochrome icons with no container shape.",
            "unstyled" => "Outputs semantic HTML only. The plugin adds no design variables or template styling, so Elementor or theme CSS owns the appearance.",
        ];
        $options = [];
        foreach ( AuthorSocialIcons::styles() as $style => $label ) {
            $options[ $style ] = [
                "label" => $label,
                "description" => $descriptions[ $style ] ?? "Author social icon presentation.",
                "preview" => AuthorSocialIcons::preview_html( $style, $size ),
            ];
        }
        return $options;
    }

    private function author_social_report_html( array $settings ): string {
        $enabled = Settings::bool( "author_social_icons_enabled" );
        $style = AuthorSocialIcons::normalize_style( (string) ( $settings["author_social_style"] ?? "social-solid" ) );
        $style_labels = AuthorSocialIcons::styles();
        $network_labels = AuthorSocialIcons::networks();
        $networks = AuthorSocialIcons::normalize_networks( $settings["author_social_networks"] ?? [] );
        $contexts = isset( $settings["author_social_auto_contexts"] ) && is_array( $settings["author_social_auto_contexts"] )
            ? array_values( array_intersect( [ "single_post", "author_archive" ], array_map( "sanitize_key", $settings["author_social_auto_contexts"] ) ) )
            : [];
        $archive_position = AuthorSocialIcons::normalize_archive_position( (string) ( $settings["author_social_archive_position"] ?? "below_bio" ) );
        $network_names = array_map( static fn( string $network ): string => (string) ( $network_labels[ $network ] ?? $network ), $networks );
        $context_names = array_map(
            static fn( string $context ): string => "single_post" === $context ? "single post content" : "author archive template",
            $contexts
        );
        $html = $this->simple_status_html( $enabled, $enabled ? "Author social icon shortcode is enabled." : "Feature is disabled; shortcode and automatic output return nothing." );
        $html .= "<table class=\"widefat striped\"><tbody>";
        $html .= "<tr><th>Shortcode</th><td><code>[" . esc_html( AuthorSocialIcons::SHORTCODE ) . "]</code></td></tr>";
        $html .= "<tr><th>Template</th><td>" . esc_html( (string) ( $style_labels[ $style ] ?? $style ) ) . "</td></tr>";
        $html .= "<tr><th>Icon size</th><td>" . esc_html( (string) max( 12, min( 64, (int) ( $settings["author_social_size"] ?? 24 ) ) ) ) . "px</td></tr>";
        $html .= "<tr><th>Eligible networks</th><td>" . esc_html( [] !== $network_names ? implode( ", ", $network_names ) : "None selected" ) . "</td></tr>";
        $html .= "<tr><th>Automatic placement</th><td>" . esc_html( [] !== $context_names ? implode( ", ", $context_names ) : "Off (manual shortcode only)" ) . "</td></tr>";
        $html .= "<tr><th>Author archive position</th><td>" . esc_html( (string) ( AuthorSocialIcons::archive_positions()[ $archive_position ] ?? $archive_position ) ) . "</td></tr>";
        $html .= "<tr><th>Empty values</th><td>Skipped before the HTML list is built; an author with no eligible URLs produces no wrapper.</td></tr>";
        return $html . "</tbody></table>";
    }

    private function author_social_no_style_html(): string {
        $css = "selector .smpi-author-social-list {\n  display: flex;\n  gap: 12px;\n  list-style: none;\n  margin: 0;\n  padding: 0;\n}\n\nselector .smpi-author-social-link {\n  align-items: center;\n  display: inline-flex;\n  gap: 8px;\n  color: #111827;\n}\n\nselector .smpi-author-social-icon svg {\n  display: block;\n  height: 24px;\n  width: 24px;\n}";
        return "<div class=\"smpi-control-group smpi-author-social-no-style\"><h3>No Style + Elementor</h3><ol><li>Choose <strong>No Style (Elementor/custom CSS)</strong> above.</li><li>Add <code>[smp_author_social_icons]</code> to an Elementor <strong>Shortcode</strong> widget.</li><li>Open that widget's <strong>Advanced &gt; Custom CSS</strong> panel.</li><li>Paste your CSS there and start every rule with <code>selector</code> so it stays scoped to that shortcode widget.</li></ol><pre class=smpi-code>" . esc_html( $css ) . "</pre></div>";
    }

    private function author_social_structure_html( array $settings ): string {
        $size = max( 12, min( 64, (int) ( $settings["author_social_size"] ?? 24 ) ) );
        $markup = '<nav class="smpi-template smpi-template--author-socials smpi-author-socials smpi-author-socials--unstyled smpi-unstyled" data-smpi-skin="unstyled" aria-label="Author social links">' . "\n"
            . '  <ul class="smpi-template-list smpi-author-social-list smpi-author-socials__list" role="list">' . "\n"
            . '    <li class="smpi-template-item smpi-author-social-item smpi-author-socials__item smpi-author-social-item--linkedin" data-network="linkedin">' . "\n"
            . '      <a class="smpi-template-link smpi-author-social-link smpi-author-socials__link" href="https://linkedin.com/in/example" target="_blank" rel="me noopener noreferrer" aria-label="Author on LinkedIn">' . "\n"
            . '        <span class="smpi-author-social-icon smpi-author-socials__icon" aria-hidden="true"><svg><!-- icon --></svg></span>' . "\n"
            . '        <span class="smpi-author-social-label smpi-author-socials__label">LinkedIn</span>' . "\n"
            . '      </a>' . "\n"
            . '    </li>' . "\n"
            . '  </ul>' . "\n"
            . '</nav>';
        return "<div class=\"smpi-author-social-structure\"><h3>Visual HTML output</h3><p>The same stable class structure is used by every template. Disabled networks and empty author values do not create list items.</p><div class=\"smpi-author-social-structure-preview\">" . AuthorSocialIcons::preview_html( "unstyled", $size ) . "</div><pre class=smpi-code>" . esc_html( $markup ) . "</pre></div>";
    }

    private function breadcrumb_post_type_descriptions(): array {
        $descriptions = [];
        foreach ( get_post_types( [ "public" => true ], "objects" ) as $type => $object ) {
            if ( "attachment" === $type ) {
                continue;
            }
            $label = isset( $object->labels->singular_name ) ? (string) $object->labels->singular_name : $type;
            $descriptions[ $type ] = "Do not inject SMP breadcrumbs on single " . $label . " templates.";
        }
        return $descriptions;
    }

    private function author_icon_preview_html( array $settings, string $style = "" ): string {
        $style = "" !== $style ? $style : (string) ( $settings["muckrack_icon_style"] ?? "circle_check" );
        if ( ! in_array( $style, [ "circle_check", "circle_outline_check", "check" ], true ) ) {
            $style = "circle_check";
        }
        $preview_class = "check" === $style ? "smpi-muckrack-preview-check" : ( "circle_outline_check" === $style ? "smpi-muckrack-preview-outline" : "smpi-muckrack-preview-circle" );
        $icon_class = "check" === $style ? "smpi-muckrack-icon-check" : ( "circle_outline_check" === $style ? "smpi-muckrack-icon-outline" : "smpi-muckrack-icon-circle" );
        $class = "smpi-muckrack-icon " . $icon_class . " " . $preview_class;
        $size = isset( $settings['muckrack_icon_size'] ) ? absint( $settings['muckrack_icon_size'] ) : 16;
        $size = max( 8, min( 64, $size ?: 22 ) );
        $margin_left = isset( $settings['muckrack_icon_margin_left'] ) ? (int) $settings['muckrack_icon_margin_left'] : 2;
        $margin_top = isset( $settings['muckrack_icon_margin_top'] ) ? (int) $settings['muckrack_icon_margin_top'] : 0;
        $margin_left = max( -32, min( 64, $margin_left ) );
        $margin_top = max( -32, min( 64, $margin_top ) );
        return '<span class="' . esc_attr( $class ) . '" style="font-size:' . esc_attr( (string) $size ) . 'px;margin-left:' . esc_attr( (string) $margin_left ) . 'px;margin-top:' . esc_attr( (string) $margin_top ) . 'px" aria-label="Verified by MuckRack editorial team">' . $this->muckrack_icon_svg_html( $style ) . '</span>';
    }

    private function muckrack_icon_svg_html( string $style ): string {
        if ( "check" === $style ) {
            return "<svg class=\"smpi-muckrack-svg\" xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 -0.5 25 25\" fill=\"none\" aria-hidden=\"true\" focusable=\"false\"><path d=\"M9 12.0002L11.333 14.3332L16 9.66724\" stroke=\"currentColor\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"></path></svg>";
        }
        if ( "circle_outline_check" === $style ) {
            return "<svg class=\"smpi-muckrack-svg\" xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 -0.5 25 25\" fill=\"none\" aria-hidden=\"true\" focusable=\"false\"><path fill-rule=\"evenodd\" clip-rule=\"evenodd\" d=\"M5.5 12.0002C5.50024 8.66068 7.85944 5.78639 11.1348 5.1351C14.4102 4.48382 17.6895 6.23693 18.9673 9.32231C20.2451 12.4077 19.1655 15.966 16.3887 17.8212C13.6119 19.6764 9.91127 19.3117 7.55 16.9502C6.23728 15.6373 5.49987 13.8568 5.5 12.0002Z\" stroke=\"currentColor\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"></path><path d=\"M9 12.0002L11.333 14.3332L16 9.66724\" stroke=\"currentColor\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"></path></svg>";
        }
        return "<svg class=\"smpi-muckrack-svg\" xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 0 24 24\" fill=\"none\" aria-hidden=\"true\" focusable=\"false\"><path fill-rule=\"evenodd\" clip-rule=\"evenodd\" d=\"M2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12ZM15.7071 9.29289C16.0976 9.68342 16.0976 10.3166 15.7071 10.7071L12.0243 14.3899C11.4586 14.9556 10.5414 14.9556 9.97568 14.3899L8.29289 12.7071C7.90237 12.3166 7.90237 11.6834 8.29289 11.2929C8.68342 10.9024 9.31658 10.9024 9.70711 11.2929L11 12.5858L14.2929 9.29289C14.6834 8.90237 15.3166 8.90237 15.7071 9.29289Z\" fill=\"currentColor\"></path></svg>";
    }

    private function author_tooltip_preview_html( array $settings ): string {
        return "<span class=smpi-tooltip-demo><strong>Jane Reporter</strong> " . $this->author_icon_preview_html( $settings ) . "<span class=smpi-tooltip-bubble>Verified by MuckRack editorial team</span></span>";
    }

    private function author_inline_preview_html( array $settings ): string {
        return "<span class=\"smpi-author-inline-demo smpi-muckrack-author-text\">Journalist verified by <span class=\"smpi-muckrack-brand smpi-muckrack-author-brand\">MuckRack</span> editorial team <a href=#>(learn more)</a></span>";
    }

    private function author_compact_block_preview_html( array $settings ): string {
        return "<span class=\"smpi-author-block-demo smpi-muckrack-author-note\">Author verified by <span class=smpi-muckrack-brand>MuckRack</span> editorial team <a href=#>(learn more)</a></span>";
    }

    private function author_muckrack_mode_help_html( array $settings ): string {
        $forced = Settings::bool( "muckrack_author_always_show" );
        $style_key = (string) ( $settings["muckrack_icon_style"] ?? "circle_check" );
        $style = "check" === $style_key ? "plain supplied SVG check" : ( "circle_outline_check" === $style_key ? "supplied outline circle SVG" : "supplied solid circle SVG" );
        return "<div class=\"smpi-control-group smpi-feature-mode\"><h3>How this works</h3><div class=smpi-mode-grid><div><strong>Automatic placement</strong><p>SMP detects common Elementor author/byline widgets and adds the selected MuckRack badge beside the visible author when the context is enabled.</p></div><div><strong>Shortcode placement</strong><p>Use a shortcode widget when you need exact manual placement inside an Elementor template.</p></div><div><strong>Current badge style</strong><p>" . esc_html( $style ) . ". Always show for every author is " . ( $forced ? "enabled" : "disabled" ) . ".</p></div></div></div>";
    }

    private function author_muckrack_shortcodes_html(): string {
        $rows = [
            [ "Current post author badge", "[author_muckrack_verified]", "Use inside a single article template or Elementor shortcode widget." ],
            [ "Current post author text", "[author_muckrack_verified type=\"text\"]", "Full verification sentence for the current post author." ],
            [ "Current post author small block", "[author_muckrack_verified style=\"compact_block\"]", "Smaller editorial-block verification treatment for author areas." ],
            [ "Current post author MuckRack URL", "[author_muckrack]", "Returns the current author MuckRack URL." ],
            [ "Specific user badge", "[muckrack_verified type=\"icon\" user_id=\"54\"]", "Use when you know the WordPress user ID." ],
            [ "Specific user text", "[muckrack_verified type=\"text\" user_id=\"54\"]", "Specific user verification sentence." ],
            [ "Specific user raw field", "[acf_author_field field=\"muckrack_url\" user_id=\"54\"]", "Reads a raw author ACF/user field." ],
        ];
        $html = "<div class=\"smpi-control-group smpi-shortcode-reference\"><h3>Copy-ready shortcodes</h3><div class=smpi-shortcode-list>";
        foreach ( $rows as $row ) {
            $html .= "<div class=smpi-shortcode-row><strong>" . esc_html( $row[0] ) . "</strong><code>" . esc_html( $row[1] ) . "</code><small>" . esc_html( $row[2] ) . "</small></div>";
        }
        return $html . "</div></div>";
    }


    private function publication_preview_sample_html( string $style, array $settings ): string {
        $label = "publication_name" === (string) ( $settings["publication_muckrack_text_mode"] ?? "news_outlet" ) ? get_bloginfo( "name" ) : "News outlet";
        $class = "smpi-publication-preview-" . sanitize_html_class( $style );
        return '<span class="smpi-muckrack-publication-text smpi-muckrack-publication-' . esc_attr( $style ) . ' ' . esc_attr( $class ) . '">' . esc_html( $label ) . ' verified by <span class="smpi-muckrack-brand smpi-publication-preview-brand">MuckRack</span> editorial team <a href="#">(learn more)</a></span>';
    }


    private static function ico( bool $ok, bool $strict = false ): string {
        if ( $ok ) {
            return "<span class=\"smpi-ico smpi-ico--ok\" aria-hidden=\"true\">&#10003;</span>";
        }
        $class = $strict ? "smpi-ico--bad" : "smpi-ico--warn";
        $glyph = $strict ? "&#10007;" : "&#9888;";
        return "<span class=\"smpi-ico " . $class . "\" aria-hidden=\"true\">" . $glyph . "</span>";
    }

    private function simple_status_html( bool $ok, string $message ): string {
        return "<p class=\"smpi-status-line\">" . self::ico( $ok ) . "<span>" . esc_html( $message ) . "</span></p>";
    }

    private function typography_preserved_label( string $prefix ): string {
        $mode = Settings::typography_mode( $prefix );
        if ( TemplateTypography::TEMPLATE_DEFAULT === $mode ) {
            return "Not applicable in Template Default";
        }
        if ( TemplateTypography::SITE_INHERIT === $mode ) {
            return "All typography uses the site";
        }
        $values = TemplateTypography::preservation_values( Settings::all(), $prefix, Settings::typography_preservation_defaults( $prefix ) );
        $properties = array_keys( array_filter( $values ) );
        return [] === $properties ? "None" : implode( ", ", array_map( static fn( string $property ): string => str_replace( "_", " ", $property ), $properties ) );
    }




    private function post_summary_report_html(): string {
        return $this->post_content_block_report_html( "summary" );
    }

    private function post_faq_report_html(): string {
        return $this->post_content_block_report_html( "faqs" );
    }

    private function publication_muckrack_report_html(): string {
        $report = MuckRackVerification::publication_report();
        $placements = ! empty( $report["placements"] ) ? implode( ", ", array_map( "sanitize_key", (array) $report["placements"] ) ) : "none selected";
        $html = $this->simple_status_html( ! empty( $report["effective"] ), "Feature toggle: " . ( ! empty( $report["enabled"] ) ? "on" : "off" ) . ". Publication ACF verified: " . ( ! empty( $report["acf_verified"] ) ? "yes" : "no" ) . "." );
        $html .= "<table class=\"widefat striped\"><tbody>";
        $html .= "<tr><th>Text option</th><td><code>" . esc_html( (string) $report["text_mode"] ) . "</code></td></tr>";
        $html .= "<tr><th>Display style</th><td><code>" . esc_html( (string) $report["style"] ) . "</code></td></tr>";
        $html .= "<tr><th>Color mode</th><td><code>" . esc_html( (string) $report["color_source"] ) . "</code></td></tr>";
        $html .= "<tr><th>Typography mode</th><td><code>" . esc_html( (string) $report["typography_mode"] ) . "</code></td></tr>";
        $html .= "<tr><th>Font</th><td>" . esc_html( (string) $report["font_family"] ) . "</td></tr>";
        $html .= "<tr><th>Font weight</th><td>" . esc_html( (string) $report["font_weight"] ) . "</td></tr>";
        $html .= "<tr><th>Text color</th><td><span class=smpi-color-swatch style=\"background:" . esc_attr( (string) $report["text_color"] ) . "\"></span> <code>" . esc_html( (string) $report["text_color"] ) . "</code></td></tr>";
        $html .= "<tr><th>Text size</th><td>" . esc_html( (string) $report["font_size"] ) . "px</td></tr>";
        $html .= "<tr><th>Leave as is</th><td>" . esc_html( empty( $report["preserved"] ) ? "None" : implode( ", ", array_map( static fn( string $property ): string => str_replace( "_", " ", $property ), (array) $report["preserved"] ) ) ) . "</td></tr>";
        $html .= "<tr><th>Accent color</th><td><span class=smpi-color-swatch style=\"background:" . esc_attr( (string) $report["color"] ) . "\"></span> <code>" . esc_html( (string) $report["color"] ) . "</code></td></tr>";
        $html .= "<tr><th>Placement</th><td>" . esc_html( $placements ) . "</td></tr>";
        $html .= "<tr><th>MuckRack URL</th><td>" . ( "" !== $report["url"] ? esc_html( (string) $report["url"] ) : self::ico( false ) . "Missing optional URL." ) . "</td></tr>";
        $html .= "<tr><th>Shortcode</th><td><code>" . esc_html( (string) $report["shortcode"] ) . "</code></td></tr>";
        $html .= "<tr><th>Actual preview</th><td>" . ( "" !== $report["preview_html"] ? wp_kses_post( (string) $report["preview_html"] ) : self::ico( false ) . "Enable feature and verify publication ACF to render the live shortcode preview. Visual examples are shown above." ) . "</td></tr>";
        return $html . "</tbody></table>";
    }

    private function publication_muckrack_source_html(): string {
        $report = MuckRackVerification::publication_report();
        $verified = \Hexa\PluginCore\Fields\Field::available()
            ? (bool) \Hexa\PluginCore\Fields\Field::get( AcfFields::PUBLICATION_MUCKRACK_VERIFIED_FIELD_KEY, 'option' )
            : (bool) get_option( 'options_' . AcfFields::PUBLICATION_MUCKRACK_VERIFIED_FIELD_NAME, false );
        $source_url = \Hexa\PluginCore\Fields\Field::available()
            ? trim( (string) \Hexa\PluginCore\Fields\Field::get( AcfFields::PUBLICATION_MUCKRACK_URL_FIELD_KEY, 'option' ) )
            : trim( (string) get_option( 'options_' . AcfFields::PUBLICATION_MUCKRACK_URL_FIELD_NAME, '' ) );
        $input_url = '' !== $source_url ? $source_url : trim( (string) ( $report['url'] ?? '' ) );
        $effective = MuckRackVerification::publication_enabled();
        $options_url = add_query_arg(
            [
                'page' => Config::$settings_page_slug,
                'tab'  => 'publication_options',
            ],
            admin_url( 'options-general.php' )
        );

        $status = '<div class="smpi-status-rows smpi-publication-muckrack-source-status">'
            . $this->publication_muckrack_source_status_html( 'verified', $verified, 'Publication verification source', $verified ? 'Enabled' : 'Disabled' )
            . $this->publication_muckrack_source_status_html( 'url', '' !== $source_url, 'Publication MuckRack URL', '' !== $source_url ? 'Saved' : 'Missing' )
            . $this->publication_muckrack_source_status_html( 'effective', $effective, 'Frontend output', $effective ? 'Active' : 'Inactive' )
            . '</div>';
        $toggle = CoreUi::toggle(
            'smpi_publication_muckrack_source_verified',
            $verified,
            'Publication verified by MuckRack',
            [
                'id'          => 'smpi-publication-muckrack-source-verified',
                'input_class' => 'smpi-publication-muckrack-source-verified',
            ]
        );
        $edit_link = '<a class="smpi-publication-muckrack-source-link" href="' . esc_url( $options_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $options_url ) . '</a>';

        return '<div class="smpi-control-group smpi-publication-muckrack-source" data-smpi-publication-muckrack-source>'
            . '<h3>Publication verification source</h3>'
            . '<p class="smpi-muted">Edit the existing Publication Options ACF fields used by the shortcode and frontend output.</p>'
            . $status
            . '<div class="smpi-publication-muckrack-source-fields"><div class="smpi-control-row">' . $toggle . '</div>'
            . '<label class="smpi-publication-muckrack-source-url-field" for="smpi-publication-muckrack-source-url"><strong>Publication MuckRack URL</strong>'
            . '<input id="smpi-publication-muckrack-source-url" class="regular-text smpi-publication-muckrack-source-url" type="url" value="' . esc_attr( $input_url ) . '" placeholder="https://muckrack.com/media-outlet/example"></label></div>'
            . '<div class="smpi-publication-muckrack-source-actions"><button type="button" class="button button-primary" data-smpi-save-publication-muckrack-source>Save publication source</button><span class="spinner"></span><span class="smpi-save-state" aria-live="polite"></span></div>'
            . '<p class="smpi-publication-muckrack-source-edit"><strong>Edit all Publication Options:</strong> ' . $edit_link . '</p>'
            . '</div>';
    }

    private function publication_muckrack_source_status_html( string $key, bool $ok, string $label, string $state ): string {
        return '<div class="smpi-status-row" data-smpi-publication-muckrack-status="' . esc_attr( $key ) . '">'
            . self::ico( $ok, true )
            . '<span><strong>' . esc_html( $label ) . ':</strong> <span data-smpi-publication-muckrack-status-text>' . esc_html( $state ) . '</span></span>'
            . '</div>';
    }

    private function press_release_report_html(): string {
        $rows = \smp_publication_integration\Content\Visibility::author_report( 10 );
        $hpr = Dependencies::hpr_active();
        $global = Settings::bool( "press_release_include_enabled" );
        $author = \smp_publication_integration\Content\Visibility::author_press_releases_enabled();
        $html = $this->simple_status_html( $hpr && ( $global || $author ), "Hexa PR Wire active: " . ( $hpr ? "yes" : "no" ) . ". General loops: " . ( $global ? "enabled" : "disabled" ) . ". Author archives: " . ( $author ? "enabled" : "disabled" ) . "." );
        $html .= "<table class=\"widefat striped\"><thead><tr><th>Recent author</th><th>Posts</th><th>Press releases</th><th>Expected on author.php</th><th>Consistent</th></tr></thead><tbody>";
        foreach ( $rows as $row ) {
            $html .= "<tr><td>" . esc_html( $row["display_name"] ) . " (#" . esc_html( (string) $row["user_id"] ) . ")</td><td>" . esc_html( (string) $row["posts"] ) . "</td><td>" . esc_html( (string) $row["press_releases"] ) . "</td><td>" . ( $row["expected"] ? "YES" : "NO" ) . "</td><td>" . self::ico( (bool) $row["consistent"], true ) . "</td></tr>";
        }
        return $html . "</tbody></table>";
    }
}
