<?php
if ( ! defined( "ABSPATH" ) ) {
    exit;
}

use smp_publication_integration\Authorship\AuthorListFormatter;
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
    /** Author view models for a post: the native author, then active co-authors. `mode` = all|primary. */
    function smpi_get_post_journalists( $post_id = 0, array $args = [] ): array {
        $post_id = smpi_resolve_journalist_post_id( $post_id );
        if ( $post_id <= 0 || ! class_exists( MultiAuthors::class ) ) {
            return [];
        }
        $authors = MultiAuthors::author_view_models_for_post( $post_id );
        if ( "primary" === sanitize_key( (string) ( $args["mode"] ?? "all" ) ) ) {
            return array_slice( $authors, 0, 1 );
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
        return $post_id > 0 && class_exists( MultiAuthors::class ) && MultiAuthors::has_multiple_authors( $post_id );
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
        $authors = smpi_get_post_journalists( $post_id, [ "mode" => $args["mode"] ] );
        $html = AuthorListFormatter::render( $authors, $args + [ "link_class" => "smpi-post-journalist-link" ] );
        if ( "" === $html || in_array( sanitize_key( (string) $args["format"] ), [ "list", "ul", "plain" ], true ) ) {
            return $html;
        }
        return '<span class="' . esc_attr( sanitize_html_class( (string) $args["class"] ) ) . '">' . $html . "</span>";
    }
}
