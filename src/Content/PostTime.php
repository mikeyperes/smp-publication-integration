<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PostTime {
    public function register(): void {
        add_filter( 'get_the_date', [ $this, 'filter_date' ], 10, 3 );
        add_filter( 'get_the_time', [ $this, 'filter_time' ], 10, 3 );
    }

    public function filter_date( string $date, string $format, $post ): string {
        return $this->format_post_time( $date, $post );
    }

    public function filter_time( string $time, string $format, $post ): string {
        return $this->format_post_time( $time, $post );
    }

    private function format_post_time( string $fallback, $post ): string {
        if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
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