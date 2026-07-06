<?php
namespace smp_publication_integration\Support;

use Hexa\PluginCore\SnippetRegistry\SnippetDefinition;
use Hexa\PluginCore\SnippetRegistry\SnippetRegistry;
use smp_publication_integration\Content\AuthorSocialCleanup;
use smp_publication_integration\Content\ElementorCssCacheBusting;
use smp_publication_integration\Content\PostListDefaults;
use smp_publication_integration\Content\Visibility;
use smp_publication_integration\Content\EstimatedReadTime;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class SnippetDefinitions {
    public static function registry(): SnippetRegistry {
        return ( new SnippetRegistry() )->add_many( self::all() );
    }

    public static function definition( string $id ): ?SnippetDefinition {
        return self::registry()->get( $id );
    }

    public static function all(): array {
        return [
            [
                "id" => "elementor_css_cache_busting",
                "name" => "Elementor CSS cache busting",
                "category" => "frontend-assets",
                "option_key" => "elementor_css_cache_busting",
                "default_enabled" => true,
                "description" => "Adds an Elementor-upload-CSS-only filemtime query argument so rebuilt Elementor CSS is not served stale.",
                "info" => "<p>This snippet only touches URLs under <code>/wp-content/uploads/elementor/css/</code>. It does not mutate global theme, plugin, or WordPress core assets.</p>",
                "snippets" => [
                    [
                        "label" => "Runtime class",
                        "value" => ElementorCssCacheBusting::class,
                        "description" => "Registers the style_loader_src filter.",
                    ],
                    [
                        "label" => "Hook",
                        "value" => "style_loader_src @ 9999",
                        "description" => "Appends mv_css=filemtime(file) when the Elementor CSS file is readable.",
                    ],
                ],
                "test_rules" => [
                    [
                        "id" => "smp_setting_enabled",
                        "label" => "SMP feature setting is enabled",
                        "description" => "Reads the nested smpi_settings option instead of a standalone WordPress option.",
                        "type" => "callback",
                        "callback" => static fn( ...$args ): bool => Settings::bool( "elementor_css_cache_busting" ),
                        "required" => true,
                    ],
                    [
                        "id" => "runtime_class_loaded",
                        "label" => "Runtime class is loaded",
                        "description" => "Confirms the snippet runtime class is available.",
                        "type" => "callback",
                        "callback" => static fn( ...$args ): bool => class_exists( ElementorCssCacheBusting::class ),
                        "required" => true,
                    ],
                    [
                        "id" => "elementor_css_samples",
                        "label" => "Elementor CSS sample scan can run",
                        "description" => "Confirms the report callback can inspect Elementor upload CSS files.",
                        "type" => "callback",
                        "callback" => static function( ...$args ): bool {
                            return is_array( ElementorCssCacheBusting::test_report() );
                        },
                        "required" => false,
                    ],
                ],
                "readme" => "Elementor CSS cache busting\n\nPurpose: prevent stale Elementor upload CSS after Elementor rebuilds.\n\nScope: frontend style URLs containing /wp-content/uploads/elementor/css/ only.\n\nSetting: smpi_settings[elementor_css_cache_busting].",
            ],
            [
                "id" => "publication_social_link_cleanup",
                "name" => "Publication social link cleanup",
                "category" => "frontend-cleanup",
                "option_key" => "publication_social_cleanup",
                "default_enabled" => true,
                "description" => "Hides empty publication-level Elementor social anchors and icon-list social rows across frontend pages while preserving valid social links.",
                "info" => "<p>Renamed from Publication social icons to clarify behavior: this cleans invalid rendered links in publication header/footer social areas, not author profile links.</p>",
                "snippets" => [
                    [
                        "label" => "Runtime class",
                        "value" => AuthorSocialCleanup::class,
                        "description" => "Prints the frontend cleanup script when author or publication social cleanup is enabled.",
                    ],
                    [
                        "label" => "Frontend selector coverage",
                        "value" => "Elementor social-icons, icon-list social rows, and social button links",
                        "description" => "Targets rendered empty or hash social controls without requiring Elementor template edits.",
                    ],
                ],
                "test_rules" => [
                    [
                        "id" => "smp_setting_enabled",
                        "label" => "SMP feature setting is enabled",
                        "description" => "Reads smpi_settings[publication_social_cleanup].",
                        "type" => "callback",
                        "callback" => static fn( ...$args ): bool => Settings::bool( "publication_social_cleanup" ),
                        "required" => true,
                    ],
                    [
                        "id" => "runtime_class_loaded",
                        "label" => "Runtime class is loaded",
                        "description" => "Confirms the shared social cleanup runtime class is available.",
                        "type" => "callback",
                        "callback" => static fn( ...$args ): bool => class_exists( AuthorSocialCleanup::class ),
                        "required" => true,
                    ],
                ],
                "readme" => "Publication social link cleanup\n\nPurpose: hide empty publication-level social anchors and icon-list social rows generated by Elementor templates.\n\nScope: frontend pages, publication header/footer/global social widgets.\n\nSetting: smpi_settings[publication_social_cleanup].",
            ],
            [
                "id" => "post_list_defaults",
                "name" => "Default post list view",
                "category" => "admin-experience",
                "option_key" => "post_list_defaults_enabled",
                "default_enabled" => true,
                "scope_admin_only" => true,
                "description" => "Applies the preferred Posts list Screen Options (20 items, compact view, Author, Tags, Article Types, Date, SEO Details) for users who have not customized that screen.",
                "info" => "<p>Adjusts only default and hidden columns plus the per-page count for users who have not set their own Screen Options. It never overrides an explicit user choice.</p>",
                "snippets" => [
                    [ "label" => "Runtime class", "value" => PostListDefaults::class, "description" => "Filters default_hidden_columns, hidden_columns, edit_post_per_page and applies defaults on current_screen." ],
                ],
                "test_rules" => [
                    [ "id" => "smp_setting_enabled", "label" => "SMP feature setting is enabled", "description" => "Reads smpi_settings[post_list_defaults_enabled].", "type" => "callback", "callback" => static fn( ...$args ): bool => Settings::bool( "post_list_defaults_enabled" ), "required" => true ],
                    [ "id" => "runtime_class_loaded", "label" => "Runtime class is loaded", "description" => "Confirms the runtime class is available.", "type" => "callback", "callback" => static fn( ...$args ): bool => class_exists( PostListDefaults::class ), "required" => true ],
                ],
                "readme" => "Default post list view\n\nApplies preferred Posts list Screen Options for users who have not customized that screen.\n\nSetting: smpi_settings[post_list_defaults_enabled].",
            ],
            [
                "id" => "shadow_posts",
                "name" => "Shadow posts",
                "category" => "query-visibility",
                "option_key" => "shadow_posts_enabled",
                "default_enabled" => true,
                "description" => "Adds post-editor visibility toggles. Completely shadowed posts stay link-accessible only and are excluded from home, category, and tag main queries; home-only shadowed posts are excluded from the home query.",
                "info" => "<p>Registers the post meta controls <code>_smpi_shadow_complete</code> and <code>_smpi_shadow_home</code> and guards only the front-end main query. Single URLs remain accessible.</p>",
                "snippets" => [
                    [ "label" => "Runtime class", "value" => Visibility::class, "description" => "pre_get_posts main-query guard (priority 1000) plus add_meta_boxes / save_post for the visibility toggles." ],
                ],
                "test_rules" => [
                    [ "id" => "smp_setting_enabled", "label" => "SMP feature setting is enabled", "description" => "Reads smpi_settings[shadow_posts_enabled].", "type" => "callback", "callback" => static fn( ...$args ): bool => Settings::bool( "shadow_posts_enabled" ), "required" => true ],
                    [ "id" => "runtime_class_loaded", "label" => "Runtime class is loaded", "description" => "Confirms the runtime class is available.", "type" => "callback", "callback" => static fn( ...$args ): bool => class_exists( Visibility::class ), "required" => true ],
                ],
                "readme" => "Shadow posts\n\nExcludes flagged posts from front-end main queries while keeping single URLs accessible.\n\nSetting: smpi_settings[shadow_posts_enabled].",
            ],
            [
                "id" => "estimated_read_time",
                "name" => "Estimated read time",
                "category" => "content-shortcodes",
                "option_key" => "estimated_read_time_enabled",
                "default_enabled" => true,
                "description" => "Calculates reading time from post_content (HTML and shortcodes stripped) and exposes it via the [smp_estimated_read_time] shortcode.",
                "info" => "<p>Returns friendly text by default (for example <code>4 min read</code>). Use <code>format=\"number\"</code> for a bare numeric value in Elementor widgets.</p>",
                "snippets" => [
                    [ "label" => "Runtime class", "value" => EstimatedReadTime::class, "description" => "Registers the shortcode on init." ],
                ],
                "shortcodes" => [
                    [ "label" => "Estimated read time", "tag" => "smp_estimated_read_time", "description" => "Friendly text by default; supports format, unit, suffix, and post_id attributes." ],
                ],
                "test_rules" => [
                    [ "id" => "smp_setting_enabled", "label" => "SMP feature setting is enabled", "description" => "Reads smpi_settings[estimated_read_time_enabled].", "type" => "callback", "callback" => static fn( ...$args ): bool => Settings::bool( "estimated_read_time_enabled" ), "required" => true ],
                    [ "id" => "runtime_class_loaded", "label" => "Runtime class is loaded", "description" => "Confirms the runtime class is available.", "type" => "callback", "callback" => static fn( ...$args ): bool => class_exists( EstimatedReadTime::class ), "required" => true ],
                ],
                "readme" => "Estimated read time\n\nShortcode-based reading time from post_content.\n\nSetting: smpi_settings[estimated_read_time_enabled].\nShortcode: [smp_estimated_read_time].",
            ],
            [
                "id" => "author_social_cleanup",
                "name" => "Author social icons",
                "category" => "frontend-cleanup",
                "option_key" => "author_social_cleanup",
                "default_enabled" => true,
                "description" => "Hides empty author social controls on single posts and author archives, including social-icons widgets, icon-list rows, and button widgets with missing, blank, hash, or javascript links.",
                "info" => "<p>Shares the <code>AuthorSocialCleanup</code> runtime class with the Publication social link cleanup snippet. Author scope runs only on single posts and author archives.</p>",
                "snippets" => [
                    [ "label" => "Runtime class", "value" => AuthorSocialCleanup::class, "description" => "Prints the frontend cleanup script when author or publication social cleanup is enabled." ],
                ],
                "test_rules" => [
                    [ "id" => "smp_setting_enabled", "label" => "SMP feature setting is enabled", "description" => "Reads smpi_settings[author_social_cleanup].", "type" => "callback", "callback" => static fn( ...$args ): bool => Settings::bool( "author_social_cleanup" ), "required" => true ],
                    [ "id" => "runtime_class_loaded", "label" => "Runtime class is loaded", "description" => "Confirms the shared social cleanup runtime class is available.", "type" => "callback", "callback" => static fn( ...$args ): bool => class_exists( AuthorSocialCleanup::class ), "required" => true ],
                ],
                "readme" => "Author social icons\n\nHides empty author social controls on single posts and author archives, including Elementor icon-list rows such as Twitter / X and LinkedIn when their links are blank or hash-only.\n\nSetting: smpi_settings[author_social_cleanup].",
            ],
        ];
    }
}
