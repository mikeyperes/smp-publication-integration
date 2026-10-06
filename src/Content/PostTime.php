<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PostTime {
    public function register(): void {
        add_filter( 'get_the_date', [ $this, 'filter_date' ], 10, 3 );
        add_filter( 'get_the_time', [ $this, 'filter_time' ], 10, 3 );
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
