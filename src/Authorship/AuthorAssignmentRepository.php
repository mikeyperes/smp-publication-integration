<?php
namespace smp_publication_integration\Authorship;

use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class AuthorAssignmentRepository {
    public const TAXONOMY = "smpi_author";
    public const LEGACY_META_KEY = "smpi_post_authors";
    public const USER_ID_META_KEY = "_smpi_user_id";

    private array $cache = [];

    public function register_taxonomy(): void {
        register_taxonomy(
            self::TAXONOMY,
            $this->supported_post_types(),
            [
                "hierarchical" => false,
                "public" => false,
                "show_ui" => false,
                "show_in_rest" => false,
                "query_var" => false,
                "rewrite" => false,
                "sort" => true,
                "args" => [ "orderby" => "term_order" ],
                "labels" => [ "name" => "SMP Authors" ],
            ]
        );
    }

    public function supported_post_types(): array {
        $types = apply_filters( "smpi_multi_author_post_types", [ "post", "press-release", "imported-news" ] );
        if ( ! is_array( $types ) ) {
            return [ "post" ];
        }
        return array_values( array_unique( array_filter( array_map( "sanitize_key", $types ) ) ) );
    }

    public function ids_for_post( int $post_id, bool $fallback = true ): array {
        if ( $post_id <= 0 ) {
            return [];
        }
        $key = $post_id . ":" . ( $fallback ? "1" : "0" );
        if ( isset( $this->cache[ $key ] ) ) {
            return $this->cache[ $key ];
        }

        $ids = Settings::bool( "multi_authors_enabled" ) ? $this->taxonomy_ids( $post_id ) : [];
        if ( empty( $ids ) && Settings::bool( "multi_authors_enabled" ) ) {
            $ids = $this->legacy_ids( $post_id );
        }

        $post = get_post( $post_id );
        if ( $fallback && empty( $ids ) && $post instanceof \WP_Post && (int) $post->post_author > 0 ) {
            $ids = [ (int) $post->post_author ];
        }

        $this->cache[ $key ] = $this->normalize_ids( $ids );
        return $this->cache[ $key ];
    }

    public function selected_ids_for_post( int $post_id ): array {
        return $this->ids_for_post( $post_id, false );
    }

    public function records_for_post( int $post_id, bool $fallback = true ): array {
        $resolver = new AuthorFieldResolver();
        $records = [];
        foreach ( $this->ids_for_post( $post_id, $fallback ) as $user_id ) {
            $record = $resolver->record( $user_id );
            if ( $record instanceof AuthorRecord ) {
                $records[] = $record;
            }
        }
        return $records;
    }

    public function set_ids( int $post_id, $raw_ids, bool $sync_native = true ): array {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || ! in_array( (string) $post->post_type, $this->supported_post_types(), true ) ) {
            return [];
        }

        $ids = $this->normalize_ids( $raw_ids );
        $term_ids = [];
        foreach ( $ids as $user_id ) {
            $term_id = $this->term_id_for_user( $user_id );
            if ( $term_id > 0 ) {
                $term_ids[] = $term_id;
            }
        }

        wp_set_object_terms( $post_id, $term_ids, self::TAXONOMY, false );
        $this->clear_cache( $post_id );

        if ( $sync_native && ! empty( $ids ) && (int) $post->post_author !== (int) $ids[0] ) {
            global $wpdb;
            $wpdb->update( $wpdb->posts, [ "post_author" => (int) $ids[0] ], [ "ID" => $post_id ], [ "%d" ], [ "%d" ] );
            clean_post_cache( $post_id );
        }

        do_action( "smpi_multi_authors_updated", $post_id, $ids );
        return $ids;
    }

    public function clear( int $post_id ): void {
        wp_set_object_terms( $post_id, [], self::TAXONOMY, false );
        $this->clear_cache( $post_id );
        do_action( "smpi_multi_authors_updated", $post_id, [] );
    }

    public function migrate_post( int $post_id ): array {
        $existing = $this->taxonomy_ids( $post_id );
        if ( ! empty( $existing ) ) {
            return $existing;
        }
        $legacy = $this->legacy_ids( $post_id );
        if ( empty( $legacy ) ) {
            return [];
        }
        return $this->set_ids( $post_id, $legacy, true );
    }

    public function migrate_batch( int $limit = 100, int $offset = 0 ): array {
        $query = new \WP_Query(
            [
                "post_type" => $this->supported_post_types(),
                "post_status" => [ "publish", "draft", "pending", "future", "private" ],
                "posts_per_page" => max( 1, min( 500, $limit ) ),
                "offset" => max( 0, $offset ),
                "fields" => "ids",
                "orderby" => "ID",
                "order" => "ASC",
                "no_found_rows" => false,
                "meta_query" => [
                    [
                        "key" => self::LEGACY_META_KEY,
                        "compare" => "EXISTS",
                    ],
                ],
            ]
        );
        $migrated = 0;
        foreach ( $query->posts as $post_id ) {
            if ( ! empty( $this->migrate_post( (int) $post_id ) ) ) {
                $migrated++;
            }
        }
        return [
            "processed" => count( $query->posts ),
            "migrated" => $migrated,
            "total" => (int) $query->found_posts,
            "next_offset" => $offset + count( $query->posts ),
            "complete" => $offset + count( $query->posts ) >= (int) $query->found_posts,
        ];
    }

    public function post_ids_for_user( int $user_id, array $post_status = [ "publish" ] ): array {
        $base_args = [
            "post_type" => $this->supported_post_types(),
            "post_status" => $post_status,
            "posts_per_page" => -1,
            "fields" => "ids",
            "no_found_rows" => true,
        ];
        $native_query = new \WP_Query( $base_args + [ "author" => $user_id ] );
        $post_ids = array_map( "absint", $native_query->posts );

        $term_id = $this->existing_term_id_for_user( $user_id );
        if ( $term_id <= 0 ) {
            return array_values( array_unique( $post_ids ) );
        }
        $term_query = new \WP_Query(
            $base_args + [
                "tax_query" => [
                    [
                        "taxonomy" => self::TAXONOMY,
                        "field" => "term_id",
                        "terms" => [ $term_id ],
                    ],
                ],
            ]
        );
        return array_values( array_unique( array_merge( $post_ids, array_map( "absint", $term_query->posts ) ) ) );
    }

    public function remove_user( int $user_id, int $reassign_id = 0 ): void {
        $post_ids = $this->post_ids_for_user( $user_id, [ "publish", "draft", "pending", "future", "private", "trash" ] );
        foreach ( $post_ids as $post_id ) {
            $ids = array_values( array_diff( $this->ids_for_post( $post_id, false ), [ $user_id ] ) );
            if ( empty( $ids ) && $reassign_id > 0 && get_user_by( "id", $reassign_id ) ) {
                $ids = [ $reassign_id ];
            }
            $this->set_ids( $post_id, $ids, true );
        }
        $term_id = $this->existing_term_id_for_user( $user_id );
        if ( $term_id > 0 ) {
            wp_delete_term( $term_id, self::TAXONOMY );
        }
    }

    public function normalize_ids( $value ): array {
        if ( is_object( $value ) && isset( $value->ID ) ) {
            $value = [ $value ];
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
        unset( $this->cache[ $post_id . ":0" ], $this->cache[ $post_id . ":1" ] );
        wp_cache_delete( "post_" . $post_id, "smpi_multi_authors" );
    }

    private function taxonomy_ids( int $post_id ): array {
        $cached = wp_cache_get( "post_" . $post_id, "smpi_multi_authors" );
        if ( is_array( $cached ) ) {
            return $this->normalize_ids( $cached );
        }

        $terms = wp_get_object_terms(
            $post_id,
            self::TAXONOMY,
            [ "orderby" => "term_order", "order" => "ASC" ]
        );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [];
        }

        $ids = [];
        foreach ( $terms as $term ) {
            $user_id = (int) get_term_meta( (int) $term->term_id, self::USER_ID_META_KEY, true );
            if ( $user_id > 0 ) {
                $ids[] = $user_id;
            }
        }
        $ids = $this->normalize_ids( $ids );
        wp_cache_set( "post_" . $post_id, $ids, "smpi_multi_authors" );
        return $ids;
    }

    private function legacy_ids( int $post_id ): array {
        $value = get_post_meta( $post_id, self::LEGACY_META_KEY, true );
        return $this->normalize_ids( $value );
    }

    private function term_id_for_user( int $user_id ): int {
        $existing = $this->existing_term_id_for_user( $user_id );
        if ( $existing > 0 ) {
            return $existing;
        }

        $user = get_user_by( "id", $user_id );
        if ( ! $user instanceof \WP_User ) {
            return 0;
        }
        $slug = "smpi-user-" . $user_id;
        $result = wp_insert_term( (string) $user->display_name, self::TAXONOMY, [ "slug" => $slug ] );
        if ( is_wp_error( $result ) ) {
            return 0;
        }
        $term_id = (int) $result["term_id"];
        update_term_meta( $term_id, self::USER_ID_META_KEY, $user_id );
        return $term_id;
    }

    private function existing_term_id_for_user( int $user_id ): int {
        $terms = get_terms(
            [
                "taxonomy" => self::TAXONOMY,
                "hide_empty" => false,
                "number" => 1,
                "fields" => "ids",
                "meta_query" => [
                    [
                        "key" => self::USER_ID_META_KEY,
                        "value" => $user_id,
                        "compare" => "=",
                        "type" => "NUMERIC",
                    ],
                ],
            ]
        );
        return is_wp_error( $terms ) || empty( $terms ) ? 0 : (int) $terms[0];
    }
}
