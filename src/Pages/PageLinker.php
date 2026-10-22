<?php
namespace smp_publication_integration\Pages;

use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Which WordPress page each page type is linked to: the saved choice, or the
 * page found automatically at one of the type's usual addresses.
 */
final class PageLinker {
    private const STATUSES = [ 'publish', 'draft', 'private', 'pending' ];

    public static function saved_id( string $key ): int {
        $key = PageTypes::canonical( $key );
        $id  = Settings::page_assignment_id( $key );
        if ( $id <= 0 ) {
            foreach ( PageTypes::MERGED as $old => $kept ) {
                if ( $kept === $key ) {
                    $id = Settings::page_assignment_id( $old );
                    if ( $id > 0 ) {
                        break;
                    }
                }
            }
        }
        return self::valid( $id ) ? $id : 0;
    }

    /** @return array{id:int,source:string} source: saved | found | none */
    public static function link( string $key ): array {
        $saved = self::saved_id( $key );
        if ( $saved ) {
            return [ 'id' => $saved, 'source' => 'saved' ];
        }
        $found = self::find( $key );
        return [ 'id' => $found, 'source' => $found ? 'found' : 'none' ];
    }

    public static function linked_id( string $key ): int {
        return self::link( $key )['id'];
    }

    public static function find( string $key ): int {
        foreach ( (array) ( PageTypes::get( $key )['slugs'] ?? [] ) as $slug ) {
            // A plain slug matches the page's own address at any level (/about/headquarters/ too); * is a wildcard.
            $id = self::find_pattern( (string) $slug );
            if ( $id ) {
                return $id;
            }
        }
        return 0;
    }

    private static function find_pattern( string $pattern ): int {
        global $wpdb;
        $like = str_replace( '*', '%', $wpdb->esc_like( str_replace( '*', "\0", $pattern ) ) );
        $like = str_replace( "\0", '%', $like );
        $in   = implode( ',', array_fill( 0, count( self::STATUSES ), '%s' ) );
        $sql  = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_name LIKE %s AND post_status IN ($in) ORDER BY post_status = 'publish' DESC, ID ASC LIMIT 1", array_merge( [ $like ], self::STATUSES ) ); // phpcs:ignore WordPress.DB.PreparedSQL
        return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    public static function valid( int $id ): bool {
        $post = $id > 0 ? get_post( $id ) : null;
        return $post && 'page' === $post->post_type && 'trash' !== $post->post_status;
    }

    public static function assign( string $key, int $page_id ): void {
        $key = PageTypes::canonical( $key );
        if ( ! PageTypes::exists( $key ) || ( $page_id && ! self::valid( $page_id ) ) ) {
            return;
        }
        $settings = Settings::all();
        $settings['page_assignments'][ $key ] = max( 0, $page_id );
        update_option( Settings::OPTION, $settings, false );
        Settings::invalidate_cache();
        Settings::log( 'Page linked: ' . $key . ' -> ' . $page_id );
    }

    public static function is_elementor( int $page_id ): bool {
        return 'builder' === get_post_meta( $page_id, '_elementor_edit_mode', true );
    }

    /**
     * Creates the page from its template, or puts the template into the linked
     * page. The page's current content is saved as a revision first.
     *
     * @return array{page_id:int,created:bool,revision_id:int}|\WP_Error
     */
    public static function create_or_apply( string $key ) {
        $key  = PageTypes::canonical( $key );
        $type = PageTypes::get( $key );
        if ( ! $type ) {
            return new \WP_Error( 'smpi_page_type', 'Unknown page type.' );
        }
        $content = PageTemplates::get( $key );
        $page_id = self::linked_id( $key );

        if ( ! $page_id ) {
            $page_id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => (string) $type['label'], 'post_name' => PageTypes::slug( $key ), 'post_content' => $content ], true );
            if ( is_wp_error( $page_id ) ) {
                return $page_id;
            }
            update_post_meta( (int) $page_id, '_smpi_page_key', $key );
            self::assign( $key, (int) $page_id );
            return [ 'page_id' => (int) $page_id, 'created' => true, 'revision_id' => 0 ];
        }

        // Keep what is there now: store it as a revision before replacing it.
        $revision = wp_save_post_revision( $page_id );
        $result   = wp_update_post( [ 'ID' => $page_id, 'post_content' => $content ], true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        self::assign( $key, $page_id );
        return [ 'page_id' => $page_id, 'created' => false, 'revision_id' => (int) $revision ];
    }
}
