<?php
if ( ! defined( "ABSPATH" ) ) {
    exit;
}

use smp_publication_integration\Content\MultiAuthors;

if ( ! function_exists( "smpi_resolve_journalist_post_id" ) ) {
    function smpi_resolve_journalist_post_id( $post_id = 0 ): int {
        $post_id = absint( $post_id );
        if ( $post_id > 0 ) {
            return $post_id;
        }
        $post = function_exists( "get_post" ) ? get_post() : null;
        return $post instanceof WP_Post ? (int) $post->ID : 0;
    }
}

if ( ! function_exists( "smpi_get_post_journalists" ) ) {
    function smpi_get_post_journalists( $post_id = 0, array $args = [] ): array {
        $post_id = smpi_resolve_journalist_post_id( $post_id );
        if ( $post_id <= 0 || ! class_exists( MultiAuthors::class ) ) {
            return [];
        }

        $args = array_merge(
            [
                "fallback" => true,
                "mode" => "all",
            ],
            $args
        );

        $authors = MultiAuthors::author_view_models_for_post( $post_id, (bool) $args["fallback"] );
        if ( "primary" === sanitize_key( (string) $args["mode"] ) ) {
            return empty( $authors ) ? [] : [ $authors[0] ];
        }
        return $authors;
    }
}

if ( ! function_exists( "smpi_get_primary_journalist" ) ) {
    function smpi_get_primary_journalist( $post_id = 0 ): ?array {
        $authors = smpi_get_post_journalists( $post_id, [ "mode" => "primary" ] );
        return $authors[0] ?? null;
    }
}

if ( ! function_exists( "smpi_post_has_multiple_journalists" ) ) {
    function smpi_post_has_multiple_journalists( $post_id = 0 ): bool {
        $post_id = smpi_resolve_journalist_post_id( $post_id );
        return $post_id > 0 && class_exists( MultiAuthors::class ) && MultiAuthors::has_multiple_authors( $post_id, true );
    }
}

if ( ! function_exists( "smpi_render_post_journalists" ) ) {
    function smpi_render_post_journalists( $post_id = 0, array $args = [] ): string {
        $args = array_merge(
            [
                "mode" => "all",
                "field" => "name",
                "format" => "links",
                "separator" => ", ",
                "class" => "smpi-post-journalists",
            ],
            $args
        );

        $authors = smpi_get_post_journalists( $post_id, [ "mode" => sanitize_key( (string) $args["mode"] ) ] );
        if ( empty( $authors ) ) {
            return "";
        }

        $field = sanitize_key( (string) $args["field"] );
        $format = sanitize_key( (string) $args["format"] );
        $values = [];
        foreach ( $authors as $author ) {
            $fields = isset( $author["fields"] ) && is_array( $author["fields"] ) ? $author["fields"] : [];
            if ( in_array( $field, [ "", "name", "display_name" ], true ) ) {
                $value = (string) ( $author["name"] ?? "" );
            } elseif ( in_array( $field, [ "id", "ids", "user_id" ], true ) ) {
                $value = (string) ( $author["id"] ?? "" );
            } elseif ( in_array( $field, [ "url", "author_url" ], true ) ) {
                $value = (string) ( $author["url"] ?? "" );
            } elseif ( "email" === $field ) {
                $value = (string) ( $author["email"] ?? "" );
            } else {
                $value = wp_strip_all_tags( (string) ( $fields[ $field ] ?? "" ) );
            }
            if ( "" !== trim( $value ) ) {
                $values[] = [ "value" => trim( $value ), "author" => $author ];
            }
        }
        if ( empty( $values ) ) {
            return "";
        }

        if ( "links" === $format ) {
            $links = [];
            foreach ( $values as $row ) {
                $author = $row["author"];
                $links[] = '<a class="smpi-post-journalist-link" href="' . esc_url( (string) ( $author["url"] ?? "" ) ) . '">' . esc_html( $row["value"] ) . '</a>';
            }
            return '<span class="' . esc_attr( sanitize_html_class( (string) $args["class"] ) ) . '">' . implode( esc_html( (string) $args["separator"] ), $links ) . '</span>';
        }

        $plain = array_map( static fn( array $row ): string => (string) $row["value"], $values );
        if ( in_array( $format, [ "lines", "line" ], true ) ) {
            return '<span class="' . esc_attr( sanitize_html_class( (string) $args["class"] ) ) . '">' . implode( "<br>\n", array_map( "esc_html", $plain ) ) . '</span>';
        }
        if ( in_array( $format, [ "list", "ul" ], true ) ) {
            $items = array_map( static fn( string $value ): string => "<li>" . esc_html( $value ) . "</li>", $plain );
            return '<ul class="' . esc_attr( sanitize_html_class( (string) $args["class"] ) ) . '">' . implode( "", $items ) . '</ul>';
        }
        return esc_html( implode( (string) $args["separator"], $plain ) );
    }
}
