<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class MultiAuthors {
    public const FIELD_NAME = "smpi_post_authors";
    public const FIELD_KEY = "field_smpi_post_authors";

    private static array $author_stack = [];

    public function register(): void {
        add_shortcode( "smp_post_author_ids", [ $this, "render_author_ids_shortcode" ] );
    }

    public static function enabled(): bool {
        return Settings::bool( "multi_authors_enabled" );
    }

    public static function supported_post_types(): array {
        return [ "post", "press-release", "imported-news" ];
    }

    public static function author_ids_for_post( int $post_id, bool $fallback = true ): array {
        $post = $post_id ? get_post( $post_id ) : null;
        if ( ! $post ) {
            return [];
        }

        $ids = [];
        if ( self::enabled() ) {
            $ids = self::normalize_user_ids( self::raw_author_value( $post_id ) );
        }

        if ( $fallback && empty( $ids ) && (int) $post->post_author > 0 ) {
            $ids[] = (int) $post->post_author;
        }

        return self::valid_unique_user_ids( $ids );
    }

    public static function primary_author_id_for_post( int $post_id ): int {
        $ids = self::author_ids_for_post( $post_id, true );
        return ! empty( $ids ) ? (int) $ids[0] : 0;
    }

    public static function resolve_author_id( int $explicit_user_id = 0, int $explicit_post_id = 0, int $author_index = 0 ): int {
        if ( $explicit_user_id > 0 && get_user_by( "id", $explicit_user_id ) ) {
            return $explicit_user_id;
        }

        $context_id = self::current_author_id();
        if ( $context_id > 0 ) {
            return $context_id;
        }

        if ( is_author() && $explicit_post_id <= 0 ) {
            return (int) get_queried_object_id();
        }

        $post_id = $explicit_post_id;
        if ( $post_id <= 0 ) {
            $post = get_post();
            $post_id = $post ? (int) $post->ID : 0;
        }

        if ( $post_id > 0 ) {
            $ids = self::author_ids_for_post( $post_id, true );
            if ( isset( $ids[ $author_index ] ) ) {
                return (int) $ids[ $author_index ];
            }
            return ! empty( $ids ) ? (int) $ids[0] : 0;
        }

        return 0;
    }

    public static function current_author_id(): int {
        return ! empty( self::$author_stack ) ? (int) end( self::$author_stack ) : 0;
    }

    public static function with_author_context( int $author_id, callable $callback ) {
        self::$author_stack[] = $author_id;
        try {
            return $callback();
        } finally {
            array_pop( self::$author_stack );
        }
    }

    public function render_author_ids_shortcode( array $atts = [] ): string {
        $atts = shortcode_atts( [ "post_id" => 0, "separator" => "," ], $atts, "smp_post_author_ids" );
        $post_id = (int) $atts["post_id"];
        if ( $post_id <= 0 ) {
            $post = get_post();
            $post_id = $post ? (int) $post->ID : 0;
        }
        $ids = self::author_ids_for_post( $post_id, true );
        return esc_html( implode( (string) $atts["separator"], $ids ) );
    }

    public static function field_report( int $limit = 10 ): array {
        $enabled = self::enabled();
        $query = new \WP_Query(
            [
                "post_type" => self::supported_post_types(),
                "post_status" => [ "publish", "draft", "pending", "future" ],
                "posts_per_page" => $limit,
                "orderby" => "modified",
                "order" => "DESC",
                "no_found_rows" => true,
            ]
        );
        $rows = [];
        foreach ( $query->posts as $post ) {
            $ids = self::author_ids_for_post( (int) $post->ID, true );
            $rows[] = [
                "post_id" => (int) $post->ID,
                "title" => get_the_title( $post ),
                "type" => (string) $post->post_type,
                "status" => (string) $post->post_status,
                "native_author" => (int) $post->post_author,
                "authors" => $ids,
                "count" => count( $ids ),
            ];
        }
        wp_reset_postdata();

        return [
            "enabled" => $enabled,
            "field" => self::FIELD_NAME,
            "field_key" => self::FIELD_KEY,
            "supported_post_types" => self::supported_post_types(),
            "rows" => $rows,
        ];
    }

    private static function raw_author_value( int $post_id ) {
        if ( function_exists( "get_field" ) ) {
            $value = get_field( self::FIELD_NAME, $post_id, false );
            if ( null !== $value && false !== $value && "" !== $value && [] !== $value ) {
                return $value;
            }
        }
        return get_post_meta( $post_id, self::FIELD_NAME, true );
    }

    private static function normalize_user_ids( $value ): array {
        if ( is_object( $value ) && isset( $value->ID ) ) {
            $value = [ (int) $value->ID ];
        }
        if ( ! is_array( $value ) ) {
            $value = "" === (string) $value ? [] : [ $value ];
        }

        $ids = [];
        foreach ( $value as $item ) {
            if ( is_object( $item ) && isset( $item->ID ) ) {
                $ids[] = (int) $item->ID;
            } elseif ( is_array( $item ) && isset( $item["ID"] ) ) {
                $ids[] = (int) $item["ID"];
            } elseif ( is_scalar( $item ) ) {
                $ids[] = (int) $item;
            }
        }
        return $ids;
    }

    private static function valid_unique_user_ids( array $ids ): array {
        $out = [];
        foreach ( $ids as $id ) {
            $id = absint( $id );
            if ( $id <= 0 || isset( $out[ $id ] ) || ! get_user_by( "id", $id ) ) {
                continue;
            }
            $out[ $id ] = $id;
        }
        return array_values( $out );
    }
}
