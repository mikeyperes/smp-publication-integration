<?php
namespace smp_publication_integration\Support;

use Hexa\PluginCore\SnippetRegistry\SnippetDefinition;
use Hexa\PluginCore\SnippetRegistry\SnippetRegistry;
use smp_publication_integration\Content\AuthorSocialCleanup;
use smp_publication_integration\Content\ElementorCssCacheBusting;

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
                "description" => "Hides empty publication-level Elementor social anchors across frontend pages while preserving valid social links.",
                "info" => "<p>Renamed from Publication social icons to clarify behavior: this cleans invalid rendered links in publication header/footer social areas, not author profile links.</p>",
                "snippets" => [
                    [
                        "label" => "Runtime class",
                        "value" => AuthorSocialCleanup::class,
                        "description" => "Prints the frontend cleanup script when author or publication social cleanup is enabled.",
                    ],
                    [
                        "label" => "Frontend selector coverage",
                        "value" => "a.elementor-social-icon, .elementor-social-icons-wrapper a, a[class*=\"social-icon\"]",
                        "description" => "Targets rendered empty social anchors without requiring Elementor template edits.",
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
                "readme" => "Publication social link cleanup\n\nPurpose: hide empty publication-level social anchors generated by Elementor templates.\n\nScope: frontend pages, publication header/footer/global social widgets.\n\nSetting: smpi_settings[publication_social_cleanup].",
            ],
        ];
    }
}
