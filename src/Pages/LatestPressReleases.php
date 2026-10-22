<?php
namespace smp_publication_integration\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** [smp_latest_press_releases count="10"]: the newest press releases as a simple list. */
final class LatestPressReleases {
    public const SHORTCODE = 'smp_latest_press_releases';

    public function register(): void {
        add_action( 'init', static function (): void {
            add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
        } );
    }

    public static function shortcode( $atts ): string {
        $atts      = shortcode_atts( [ 'count' => 10, 'post_type' => 'press-release' ], (array) $atts, self::SHORTCODE );
        $post_type = sanitize_key( (string) $atts['post_type'] );
        if ( ! post_type_exists( $post_type ) ) {
            return '';
        }
        $posts = get_posts( [ 'post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => max( 1, min( 50, absint( $atts['count'] ) ) ), 'no_found_rows' => true ] );
        if ( ! $posts ) {
            return '<p class="smpi-press-releases-empty">No press releases yet.</p>';
        }
        $html = '<ul class="smpi-press-releases">';
        foreach ( $posts as $post ) {
            $html .= '<li class="smpi-press-release"><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a> <time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time></li>';
        }
        return $html . '</ul>';
    }
}
