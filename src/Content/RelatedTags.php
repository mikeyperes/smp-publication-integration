<?php
namespace smp_publication_integration\Content;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * [smp_related_tags]: on a tag or category archive, the tags most often used together with it,
 * as plain links. Prints nothing (label included) when there are none, so the row disappears.
 */
final class RelatedTags {
    public const SHORTCODE = "smp_related_tags";
    private const SAMPLE_POSTS = 200;
    private const CACHE_SECONDS = 12 * HOUR_IN_SECONDS;

    public function register(): void {
        add_action( "init", [ $this, "register_shortcode" ] );
    }

    public function register_shortcode(): void {
        add_shortcode( self::SHORTCODE, [ $this, "render_shortcode" ] );
    }

    public function render_shortcode( $atts = [] ): string {
        $atts = shortcode_atts(
            [
                "limit" => 8,
                "label" => "",
                "class" => "",
            ],
            is_array( $atts ) ? $atts : [],
            self::SHORTCODE
        );

        $term = function_exists( "get_queried_object" ) ? get_queried_object() : null;
        if ( ! $term instanceof \WP_Term || ! in_array( $term->taxonomy, [ "post_tag", "category" ], true ) ) {
            return "";
        }

        $tags = self::related( $term, max( 1, min( 24, absint( $atts["limit"] ) ) ) );
        if ( empty( $tags ) ) {
            return "";
        }

        $links = "";
        foreach ( $tags as $tag ) {
            $url = get_term_link( $tag );
            if ( is_wp_error( $url ) ) {
                continue;
            }
            $links .= '<a class="smpi-related-tag" href="' . esc_url( $url ) . '">' . esc_html( $tag->name ) . "</a>";
        }
        if ( "" === $links ) {
            return "";
        }

        $label = "" !== (string) $atts["label"] ? '<span class="smpi-related-tags-label">' . esc_html( (string) $atts["label"] ) . "</span>" : "";
        $class = trim( "smpi-related-tags " . sanitize_html_class( (string) $atts["class"] ) );
        $aria = "" !== (string) $atts["label"] ? (string) $atts["label"] : __( "Related tags", "smp-publication-integration" );
        return '<nav class="' . esc_attr( $class ) . '" aria-label="' . esc_attr( $aria ) . '">' . $label . $links . "</nav>";
    }

    /** @return array<int,\WP_Term> the tags most used on the archive's latest stories, the archive's own tag left out */
    public static function related( \WP_Term $term, int $limit ): array {
        // WordPress moves the terms "last changed" marker whenever a story's tags change, so a new key replaces the old list.
        $key = "smpi_related_tags_" . md5( $term->term_id . "|" . $limit . "|" . wp_cache_get_last_changed( "terms" ) );
        $cached = get_transient( $key );
        if ( is_array( $cached ) ) {
            return array_values( array_filter( array_map( "get_tag", $cached ), static fn( $t ) => $t instanceof \WP_Term ) );
        }

        $field = "category" === $term->taxonomy ? "cat" : "tag_id";
        $ids = get_posts(
            [
                "post_type" => "post",
                "post_status" => "publish",
                $field => (int) $term->term_id,
                "posts_per_page" => self::SAMPLE_POSTS,
                "fields" => "ids",
                "no_found_rows" => true,
                "suppress_filters" => false,
            ]
        );

        $counts = [];
        $terms = empty( $ids ) ? [] : wp_get_object_terms( $ids, "post_tag", [ "fields" => "all_with_object_id" ] );
        foreach ( is_array( $terms ) ? $terms : [] as $tag ) {
            if ( (int) $tag->term_id === (int) $term->term_id ) {
                continue;
            }
            $counts[ (int) $tag->term_id ] = ( $counts[ (int) $tag->term_id ] ?? 0 ) + 1;
        }
        // Most shared first; ties in the tag's own overall use, then by name, so the order is stable.
        uksort(
            $counts,
            static function ( $a, $b ) use ( $counts ) {
                if ( $counts[ $a ] !== $counts[ $b ] ) {
                    return $counts[ $b ] <=> $counts[ $a ];
                }
                $ta = get_tag( $a );
                $tb = get_tag( $b );
                return [ $tb->count ?? 0, $ta->name ?? "" ] <=> [ $ta->count ?? 0, $tb->name ?? "" ];
            }
        );
        $top = array_slice( array_keys( $counts ), 0, $limit );
        set_transient( $key, $top, self::CACHE_SECONDS );

        return array_values( array_filter( array_map( "get_tag", $top ), static fn( $t ) => $t instanceof \WP_Term ) );
    }
}
