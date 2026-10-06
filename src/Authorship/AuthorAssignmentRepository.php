<?php
namespace smp_publication_integration\Authorship;

use smp_publication_integration\Content\PublicationContentTypes;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Single source of truth for who wrote a post.
 *
 * The native WordPress author is always the primary author. A post becomes
 * multi-author only when the site feature is on AND the post's own switch is
 * on; its co-authors then follow the primary author in their saved order.
 *
 * Storage:
 * - ENABLED_META_KEY   "1" when the post's "Multiple authors" switch is on.
 * - CO_AUTHORS_META_KEY ordered co-author user IDs. Kept when the switch is
 *   turned off so turning it back on restores the list.
 * - TAXONOMY           query index of the active co-authors, used only by
 *   author archives and author listings. Empty whenever the switch is off.
 */
final class AuthorAssignmentRepository {
    public const TAXONOMY = "smpi_author";
    public const USER_ID_META_KEY = "_smpi_user_id";
    public const ENABLED_META_KEY = "_smpi_multi_authors";
    public const CO_AUTHORS_META_KEY = "_smpi_co_authors";
    public const REST_FIELD = "smpi_post_authors";
    /** Pre-2.2.5 ACF field value; read only by the migration. */
    public const LEGACY_META_KEY = "smpi_post_authors";

    private array $cache = [];

    /** @return array<string,mixed> */
    public static function taxonomy_args(): array {
        return [
            "hierarchical" => false,
            "public" => false,
            "show_ui" => false,
            "show_in_rest" => false,
            "query_var" => false,
            "rewrite" => false,
            "labels" => [ "name" => "SMP Co-authors" ],
        ];
    }

    public static function site_enabled(): bool {
        return Settings::bool( "multi_authors_enabled" );
    }

    public static function supported_post_types(): array {
        $types = apply_filters( "smpi_multi_author_post_types", PublicationContentTypes::active_article_post_types() );
        if ( ! is_array( $types ) ) {
            return [ "post" ];
        }
        return array_values( array_unique( array_filter( array_map( "sanitize_key", $types ) ) ) );
    }

    public function supports( $post ): bool {
        $post = get_post( $post );
        return $post instanceof \WP_Post && in_array( (string) $post->post_type, self::supported_post_types(), true );
    }

    /** The post's own switch, independent of the site feature. */
    public function switch_on( int $post_id ): bool {
        return $post_id > 0 && "1" === (string) get_post_meta( $post_id, self::ENABLED_META_KEY, true );
    }

    /** True when this post currently renders as multi-author. */
    public function is_multi_author( int $post_id ): bool {
        return count( $this->ids_for_post( $post_id ) ) > 1;
    }

    /** Saved co-authors (excluding the primary), whether or not the switch is on. */
    public function stored_co_author_ids( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post ) {
            return [];
        }
        $ids = $this->normalize_ids( get_post_meta( $post_id, self::CO_AUTHORS_META_KEY, true ) );
        return array_values( array_diff( $ids, [ (int) $post->post_author ] ) );
    }

    /**
     * Ordered author IDs: the native author first, then active co-authors.
     *
     * @return int[]
     */
    public function ids_for_post( int $post_id ): array {
        if ( $post_id <= 0 ) {
            return [];
        }
        if ( isset( $this->cache[ $post_id ] ) ) {
            return $this->cache[ $post_id ];
        }

        $post = get_post( $post_id );
        $primary = $post instanceof \WP_Post ? (int) $post->post_author : 0;
        $ids = $primary > 0 ? [ $primary ] : [];
        if ( $primary > 0 && self::site_enabled() && $this->supports( $post ) && $this->switch_on( $post_id ) ) {
            $ids = array_merge( $ids, $this->stored_co_author_ids( $post_id ) );
        }

        $this->cache[ $post_id ] = $this->normalize_ids( $ids );
        return $this->cache[ $post_id ];
    }

    /** @return AuthorRecord[] */
    public function records_for_post( int $post_id ): array {
        $resolver = new AuthorFieldResolver();
        $records = [];
        foreach ( $this->ids_for_post( $post_id ) as $user_id ) {
            $record = $resolver->record( $user_id );
            if ( $record instanceof AuthorRecord ) {
                $records[] = $record;
            }
        }
        return $records;
    }

    /**
     * Persist the post's switch and co-author list, then rebuild its index.
     * Never touches the native author.
     */
    public function save( int $post_id, bool $enabled, $co_author_ids ): void {
        $post = get_post( $post_id );
        if ( ! $this->supports( $post ) ) {
            return;
        }
        $co_authors = array_values( array_diff( $this->normalize_ids( $co_author_ids ), [ (int) $post->post_author ] ) );
        $enabled = $enabled && ! empty( $co_authors );

        if ( $enabled ) {
            update_post_meta( $post_id, self::ENABLED_META_KEY, "1" );
        } else {
            delete_post_meta( $post_id, self::ENABLED_META_KEY );
        }
        if ( empty( $co_authors ) ) {
            delete_post_meta( $post_id, self::CO_AUTHORS_META_KEY );
        } else {
            update_post_meta( $post_id, self::CO_AUTHORS_META_KEY, $co_authors );
        }

        $this->sync_index( $post_id );
    }

    /**
     * Assign a complete ordered author list (REST/programmatic writes):
     * the first ID becomes the native author, the rest become co-authors.
     *
     * @return int[] the resulting ordered IDs
     */
    public function assign( int $post_id, $ordered_ids ): array {
        $post = get_post( $post_id );
        if ( ! $this->supports( $post ) ) {
            return [];
        }
        $ids = $this->normalize_ids( $ordered_ids );
        if ( ! empty( $ids ) && (int) $post->post_author !== $ids[0] ) {
            wp_update_post( [ "ID" => $post_id, "post_author" => $ids[0] ] );
        }
        $this->save( $post_id, count( $ids ) > 1, array_slice( $ids, 1 ) );
        return $this->ids_for_post( $post_id );
    }

    /** Index the active co-authors for archive queries; empty when the switch is off. */
    public function sync_index( int $post_id ): void {
        $term_ids = [];
        if ( $this->switch_on( $post_id ) ) {
            foreach ( $this->stored_co_author_ids( $post_id ) as $user_id ) {
                $term_id = $this->term_id_for_user( $user_id, true );
                if ( $term_id > 0 ) {
                    $term_ids[] = $term_id;
                }
            }
        }
        wp_set_object_terms( $post_id, $term_ids, self::TAXONOMY, false );
        $this->clear_cache( $post_id );
        do_action( "smpi_multi_authors_updated", $post_id, $this->ids_for_post( $post_id ) );
    }

    /** Posts where the user is the native author or an active co-author. */
    public function post_ids_for_user( int $user_id, array $post_status = [ "publish" ], ?array $post_types = null ): array {
        $args = $this->user_query_args( $user_id, $post_status, $post_types, -1 );
        if ( null === $args ) {
            return [];
        }
        $post_ids = array_map( "absint", ( new \WP_Query( $args + [ "author" => $user_id ] ) )->posts );
        $term_id = $this->term_id_for_user( $user_id, false );
        if ( $term_id > 0 ) {
            $post_ids = array_merge( $post_ids, array_map( "absint", ( new \WP_Query( $args + [ "tax_query" => $this->term_query( $term_id ) ] ) )->posts ) );
        }
        return array_values( array_unique( $post_ids ) );
    }

    public function has_posts_for_user( int $user_id, array $post_status = [ "publish" ], ?array $post_types = null ): bool {
        $args = $this->user_query_args( $user_id, $post_status, $post_types, 1 );
        if ( null === $args ) {
            return false;
        }
        $args += [ "ignore_sticky_posts" => true, "update_post_meta_cache" => false, "update_post_term_cache" => false ];
        if ( ! empty( ( new \WP_Query( $args + [ "author" => $user_id ] ) )->posts ) ) {
            return true;
        }
        $term_id = $this->term_id_for_user( $user_id, false );
        return $term_id > 0 && ! empty( ( new \WP_Query( $args + [ "tax_query" => $this->term_query( $term_id ) ] ) )->posts );
    }

    /** Drop a deleted user from every co-author list and remove their index term. */
    public function remove_user( int $user_id ): void {
        $term_id = $this->term_id_for_user( $user_id, false );
        global $wpdb;
        $post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::CO_AUTHORS_META_KEY ) );
        foreach ( array_map( "absint", (array) $post_ids ) as $post_id ) {
            $stored = array_map( "absint", (array) get_post_meta( $post_id, self::CO_AUTHORS_META_KEY, true ) );
            if ( in_array( $user_id, $stored, true ) ) {
                $this->save( $post_id, $this->switch_on( $post_id ), array_diff( $stored, [ $user_id ] ) );
            }
        }
        if ( $term_id > 0 ) {
            wp_delete_term( $term_id, self::TAXONOMY );
        }
    }

    /**
     * Convert pre-2.2.5 data (ACF list + ordered taxonomy) into the switch model.
     * The old list stored the primary first and kept post_author in sync with it.
     */
    public function migrate_batch( int $limit = 100 ): array {
        global $wpdb;
        $limit = max( 1, min( 500, $limit ) );
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s) LIMIT %d",
                self::LEGACY_META_KEY,
                "_" . self::LEGACY_META_KEY,
                $limit
            )
        );
        $migrated = 0;
        foreach ( array_map( "absint", (array) $post_ids ) as $post_id ) {
            if ( $this->migrate_post( $post_id ) ) {
                $migrated++;
            }
        }
        return [
            "processed" => count( (array) $post_ids ),
            "multi_author" => $migrated,
            "complete" => count( (array) $post_ids ) < $limit,
        ];
    }

    /** Index terms left from the old ordered-list model that no post switch backs. */
    public function prune_stale_index( int $limit = 200 ): int {
        global $wpdb;
        $stale = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT tr.object_id
                FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
                LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = tr.object_id AND pm.meta_key = %s
                WHERE pm.meta_id IS NULL
                LIMIT %d",
                self::TAXONOMY,
                self::ENABLED_META_KEY,
                max( 1, $limit )
            )
        );
        foreach ( array_map( "absint", (array) $stale ) as $post_id ) {
            $this->migrate_post( $post_id );
        }
        return count( (array) $stale );
    }

    public function normalize_ids( $value ): array {
        if ( is_object( $value ) && isset( $value->ID ) ) {
            $value = [ $value ];
        } elseif ( is_string( $value ) && str_contains( $value, "," ) ) {
            $value = explode( ",", $value );
        } elseif ( ! is_array( $value ) ) {
            $value = "" === trim( (string) $value ) ? [] : [ $value ];
        }

        $ids = [];
        foreach ( $value as $item ) {
            if ( is_object( $item ) && isset( $item->ID ) ) {
                $id = (int) $item->ID;
            } elseif ( is_array( $item ) && isset( $item["ID"] ) ) {
                $id = (int) $item["ID"];
            } else {
                $id = is_scalar( $item ) ? (int) $item : 0;
            }
            if ( $id > 0 && ! isset( $ids[ $id ] ) && get_user_by( "id", $id ) ) {
                $ids[ $id ] = $id;
            }
        }
        return array_values( $ids );
    }

    public function clear_cache( int $post_id ): void {
        unset( $this->cache[ $post_id ] );
    }

    private function migrate_post( int $post_id ): bool {
        $post = get_post( $post_id );
        $ordered = $this->normalize_ids( get_post_meta( $post_id, self::LEGACY_META_KEY, true ) );
        if ( empty( $ordered ) ) {
            $ordered = $this->legacy_index_ids( $post_id );
        }
        // delete_metadata(), unlike delete_post_meta(), does not redirect revisions to their parent.
        delete_metadata( "post", $post_id, self::LEGACY_META_KEY );
        delete_metadata( "post", $post_id, "_" . self::LEGACY_META_KEY );

        if ( ! $post instanceof \WP_Post || ! $this->supports( $post ) ) {
            wp_set_object_terms( $post_id, [], self::TAXONOMY, false );
            return false;
        }
        $co_authors = array_values( array_diff( $ordered, [ (int) $post->post_author ] ) );
        $this->save( $post_id, ! empty( $co_authors ), $co_authors );
        return ! empty( $co_authors );
    }

    /** Ordered user IDs from the old taxonomy, which indexed the full list. */
    private function legacy_index_ids( int $post_id ): array {
        $terms = wp_get_object_terms( $post_id, self::TAXONOMY, [ "orderby" => "term_order", "order" => "ASC" ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [];
        }
        $ids = [];
        foreach ( $terms as $term ) {
            $ids[] = (int) get_term_meta( (int) $term->term_id, self::USER_ID_META_KEY, true );
        }
        return $this->normalize_ids( $ids );
    }

    private function user_query_args( int $user_id, array $post_status, ?array $post_types, int $limit ): ?array {
        $post_types = null === $post_types ? self::supported_post_types() : array_values( array_unique( array_filter( array_map( "sanitize_key", $post_types ) ) ) );
        if ( $user_id <= 0 || empty( $post_types ) ) {
            return null;
        }
        return [
            "post_type" => $post_types,
            "post_status" => $post_status,
            "posts_per_page" => $limit,
            "fields" => "ids",
            "no_found_rows" => true,
        ];
    }

    private function term_query( int $term_id ): array {
        return [ [ "taxonomy" => self::TAXONOMY, "field" => "term_id", "terms" => [ $term_id ] ] ];
    }

    private function term_id_for_user( int $user_id, bool $create ): int {
        $slug = "smpi-user-" . $user_id;
        $term = get_term_by( "slug", $slug, self::TAXONOMY );
        if ( $term instanceof \WP_Term ) {
            return (int) $term->term_id;
        }
        if ( ! $create ) {
            return 0;
        }
        $user = get_user_by( "id", $user_id );
        if ( ! $user instanceof \WP_User ) {
            return 0;
        }
        $result = wp_insert_term( (string) $user->display_name, self::TAXONOMY, [ "slug" => $slug ] );
        if ( is_wp_error( $result ) ) {
            return 0;
        }
        $term_id = (int) $result["term_id"];
        update_term_meta( $term_id, self::USER_ID_META_KEY, $user_id );
        return $term_id;
    }
}
