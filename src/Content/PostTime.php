<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PostTime {
    public const SHORTCODE = 'smp_post_date';

    public function register(): void {
        add_filter( 'get_the_date', [ $this, 'filter_date' ], 10, 3 );
        add_filter( 'get_the_time', [ $this, 'filter_time' ], 10, 3 );
        add_shortcode( self::SHORTCODE, [ $this, 'render_date_shortcode' ] );
    }

    public function render_date_shortcode( array $atts = [] ): string {
        $atts = shortcode_atts(
            [
                'post_id' => 0,
                'format'  => 'M j, Y · g:i A',
            ],
            $atts,
            self::SHORTCODE
        );
        $post = get_post( (int) $atts['post_id'] ?: null );
        if ( ! $post instanceof \WP_Post ) {
            return '';
        }

        $format = (string) $atts['format'];
        if ( '' === $format ) {
            $format = (string) get_option( 'date_format', 'F j, Y' );
        }
        $timestamp = function_exists( 'get_post_timestamp' )
            ? get_post_timestamp( $post, 'date' )
            : get_post_time( 'U', true, $post );
        if ( ! is_int( $timestamp ) || $timestamp <= 0 ) {
            return '';
        }

        $date = function_exists( 'wp_date' )
            ? wp_date( $format, $timestamp, wp_timezone() )
            : date_i18n( $format, $timestamp );
        return esc_html( (string) $date );
    }

    /**
     * WordPress may pass a null format (Elementor Post Info does) and a
     * non-string value (for example the integer from format 'U'); only
     * display strings are reformatted, everything else passes through.
     */
    public function filter_date( $date, $format = '', $post = null ) {
        return is_string( $date ) && ! $this->is_machine_format( $format ) ? $this->format_post_time( $date, $post ) : $date;
    }

    public function filter_time( $time, $format = '', $post = null ) {
        return is_string( $time ) && ! $this->is_machine_format( $format ) ? $this->format_post_time( $time, $post ) : $time;
    }

    private function is_machine_format( $format ): bool {
        return in_array( $format, [ 'U', 'G', 'c', 'r', DATE_ATOM, DATE_RFC2822 ], true );
    }

    private function format_post_time( string $fallback, $post ): string {
        if ( ! RuntimeContext::is_public_frontend() ) {
            return $fallback;
        }
        $mode = (string) Settings::get( 'post_time_mode', 'native' );
        if ( 'native' === $mode ) {
            return $fallback;
        }
        $post = get_post( $post );
        if ( ! $post ) {
            return $fallback;
        }
        $timestamp = get_post_time( 'U', true, $post );
        if ( 'relative_then_date' === $mode && ( current_time( 'timestamp', true ) - $timestamp ) < DAY_IN_SECONDS ) {
            return human_time_diff( $timestamp, current_time( 'timestamp', true ) ) . ' ago';
        }
        return date_i18n( get_option( 'date_format', 'F j, Y' ), $timestamp );
    }
}
