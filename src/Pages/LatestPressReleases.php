<?php
namespace smp_publication_integration\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * [smp_latest_press_releases count="10"]: the newest press releases as a simple list.
 * [smp_press_release_form]: the site's press release submission form, a form shortcode
 * saved on the Publication Pages tab (option smpi_press_release_form); empty prints nothing.
 * Press releases are syndicated by the parent wire, Hexa PR Wire (HEXA_PR_WIRE_URL).
 */
final class LatestPressReleases {
    public const SHORTCODE      = 'smp_latest_press_releases';
    public const FORM_SHORTCODE = 'smp_press_release_form';
    public const FORM_OPTION    = 'smpi_press_release_form';
    public const HEXA_PR_WIRE_URL = 'https://hexaprwire.com';

    public function register(): void {
        add_action( 'init', static function (): void {
            add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
            add_shortcode( self::FORM_SHORTCODE, [ self::class, 'form' ] );
        } );
    }

    public static function form_shortcode(): string {
        return (string) get_option( self::FORM_OPTION, '' );
    }

    /** Only a single shortcode, e.g. [gravityform id="3"], is kept. */
    public static function save_form_shortcode( string $value ): void {
        $value = trim( wp_strip_all_tags( $value ) );
        update_option( self::FORM_OPTION, preg_match( '/^\[[a-z0-9_-]+[^\[\]]*\]$/i', $value ) ? $value : '', false );
    }

    public static function form(): string {
        $code = self::form_shortcode();
        return '' === $code ? '' : '<div class="smpi-press-release-form">' . do_shortcode( $code ) . '</div>';
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
