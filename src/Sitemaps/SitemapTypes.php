<?php
namespace smp_publication_integration\Sitemaps;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\Sitemaps\SitemapProviders;
use Hexa\PluginCore\Sitemaps\SitemapsPanel;
use smp_publication_integration\Authorship\AuthorAssignmentRepository;
use smp_publication_integration\Content\ArticleTypes;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * The sitemaps this plugin owns: the Google News sitemap and the publication
 * taxonomies. HWS Base Tools shows them read-only and links to the Sitemaps tab.
 */
final class SitemapTypes implements ModuleInterface {
    public const SLUG = "smp-publication-integration";

    public static function definitions(): array {
        return [
            [ "id" => "news", "recommended" => true, "why" => "On for every news site; Google reads it whether or not Google News has approved the outlet." ],
            [ "id" => "taxonomy_" . ArticleTypes::TAXONOMY, "recommended" => static fn( array $row ) => ! empty( $row["recommended"] ), "why" => "Article type archives follow the usual rule: on when there are more than a few." ],
            [ "id" => "taxonomy_" . AuthorAssignmentRepository::TAXONOMY, "recommended" => false, "why" => "Internal author assignments; the Authors sitemap covers author pages." ],
        ];
    }

    public function register(): void {
        if ( ! class_exists( SitemapProviders::class ) ) {
            return;
        }
        SitemapProviders::register( self::SLUG, "SMP Publication Integration", admin_url( "options-general.php?page=smp-publication-integration&tab=sitemaps" ), self::definitions() );
        add_filter( "smpi_dashboard_tabs", static function ( array $tabs ): array { $tabs["sitemaps"] = "Sitemaps"; return $tabs; } );
        add_filter( "smpi_render_dashboard_tab", static function ( $rendered, string $id ) {
            if ( "sitemaps" !== $id ) {
                return $rendered;
            }
            SitemapsPanel::render( self::SLUG );
            return true;
        }, 10, 2 );
    }
}
