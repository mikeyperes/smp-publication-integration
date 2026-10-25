<?php
namespace smp_publication_integration\Pages;

use Hexa\PluginCore\SiteStructure\PageRedirects;
use Hexa\PluginCore\SiteStructure\PageStructureManager;
use smp_publication_integration\Support\PageStructure;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Where SMP's pages sit: each under its parent (PageTypes 'parent'), which may
 * be an HWS Base Tools page (About, Parent Network). Hexa WP Core moves pages
 * and keeps their old addresses redirecting.
 *
 * - Answers Hexa WP Core's `hexa_site_structure_page_id` for SMP page keys, so
 *   another plugin's page can sit under an SMP page.
 * - Hands HWS Base Tools the pages SMP linked before they moved there
 *   (About was `about_publication`), so no second page is created.
 * - ensure_pages() sets every SMP page up in one call: wp smpi-pages ensure.
 */
final class PageParents {
    public function register(): void {
        add_filter( 'hexa_site_structure_page_id', [ self::class, 'page_id' ], 20, 2 );
        if ( class_exists( PageRedirects::class ) ) {
            PageRedirects::register();
        }
        if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
            \WP_CLI::add_command( 'smpi-pages ensure', static function (): void {
                foreach ( self::ensure_pages() as $key => $result ) {
                    if ( is_wp_error( $result ) ) {
                        \WP_CLI::warning( $key . ': ' . $result->get_error_message() );
                        continue;
                    }
                    \WP_CLI::log( sprintf( '%s: page %d%s%s %s', $key, $result['page_id'], $result['created'] ? ' created' : '', $result['moved'] ? ' moved' : '', (string) get_permalink( $result['page_id'] ) ) );
                }
            } );
        }
    }

    /** @param int $id */
    public static function page_id( $id, string $key ): int {
        $id = (int) $id;
        if ( $id > 0 ) {
            return $id;
        }
        if ( PageTypes::exists( $key ) ) {
            return PageLinker::linked_id( $key );
        }
        if ( isset( PageTypes::BASE_TOOLS_OWNED[ $key ] ) ) {
            $id = PageTypes::base_tools_linked_id( $key );
            if ( $id > 0 ) {
                return $id;
            }
            $old = array_search( $key, PageTypes::BASE_TOOLS_ALIASES, true );
            $old = false !== $old ? (string) $old : $key;
            $id  = Settings::page_assignment_id( $old );
            return PageLinker::valid( $id ) ? $id : 0;
        }
        return 0;
    }

    /** The page an SMP page sits under (0 = top level). */
    public static function parent_id( string $key ): int {
        $parent = (string) ( PageTypes::get( $key )['parent'] ?? '' );
        return '' !== $parent ? PageLinker::linked_id( $parent ) : 0;
    }

    /** Moves an SMP page under its parent; true when it moved. */
    public static function place( string $key, int $page_id ): bool {
        $parent_id = self::parent_id( $key );
        return $parent_id > 0 && PageStructureManager::place_under_parent( $page_id, $parent_id );
    }

    /** @return array<string,array<string,mixed>|\WP_Error> */
    public static function ensure_pages(): array {
        // Base Tools' pages first, so SMP pages find their Base Tools parents (About, Parent Network).
        if ( class_exists( '\\HWS\\BaseTools\\CompanyPages\\CompanyPages' ) ) {
            \HWS\BaseTools\CompanyPages\CompanyPages::manager();
        }
        return PageStructure::manager()->ensure_pages();
    }
}
