<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class EstimatedReadTime {
    public const SHORTCODE = "smp_estimated_read_time";
    private const DEFAULT_WORDS_PER_MINUTE = 200;

    public function register(): void {
        add_action( "init", [ $this, "register_shortcode" ] );
    }

    public function register_shortcode(): void {
        add_shortcode( self::SHORTCODE, [ $this, "render_shortcode" ] );
    }

    public function render_shortcode( array $atts = [] ): string {
        if ( ! Settings::bool( "estimated_read_time_enabled" ) ) {
            return "";
        }

        $atts = shortcode_atts(
            [
                "post_id" => 0,
                "unit" => "minutes",
                "wpm" => self::DEFAULT_WORDS_PER_MINUTE,
            ],
            $atts,
            self::SHORTCODE
        );

        $post_id = absint( $atts["post_id"] );
        if ( ! $post_id ) {
            $post_id = (int) get_the_ID();
        }

        if ( ! $post_id ) {
            return "";
        }

        $content = self::content_for_post( $post_id );
        if ( "" === $content ) {
            return "";
        }

        $seconds = self::seconds_for_content( $content, absint( $atts["wpm"] ) );
        if ( $seconds <= 0 ) {
            return "";
        }

        return esc_html( (string) self::value_for_unit( $seconds, sanitize_key( (string) $atts["unit"] ) ) );
    }

    public static function content_for_post( int $post_id ): string {
        $post = get_post( $post_id );
        if ( ! $post || ! in_array( $post->post_type, [ "post", "page", PublicationPostType::POST_TYPE, "press-release" ], true ) ) {
            return "";
        }

        return (string) $post->post_content;
    }

    public static function seconds_for_content( string $content, int $words_per_minute = self::DEFAULT_WORDS_PER_MINUTE ): int {
        $words = self::word_count( $content );
        if ( $words <= 0 ) {
            return 0;
        }

        $words_per_minute = max( 1, min( 1000, $words_per_minute ?: self::DEFAULT_WORDS_PER_MINUTE ) );
        return max( 1, (int) ceil( ( $words / $words_per_minute ) * MINUTE_IN_SECONDS ) );
    }

    public static function value_for_unit( int $seconds, string $unit = "minutes" ): int {
        if ( in_array( $unit, [ "second", "seconds", "sec", "secs" ], true ) ) {
            return max( 1, $seconds );
        }

        return max( 1, (int) ceil( $seconds / MINUTE_IN_SECONDS ) );
    }

    public static function word_count( string $content ): int {
        $content = strip_shortcodes( $content );
        $content = wp_strip_all_tags( $content, true );
        $content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, get_bloginfo( "charset" ) ?: "UTF-8" );
        $content = trim( preg_replace( "/\s+/u", " ", $content ) ?: "" );
        if ( "" === $content ) {
            return 0;
        }

        preg_match_all( "/[\p{L}\p{N}]+/u", $content, $matches );
        return count( $matches[0] ?? [] );
    }

    public static function integrity_report( int $limit = 5 ): array {
        $posts = get_posts(
            [
                "post_type" => "post",
                "post_status" => "publish",
                "posts_per_page" => $limit,
                "orderby" => "date",
                "order" => "DESC",
                "no_found_rows" => true,
            ]
        );

        $rows = [];
        foreach ( $posts as $post ) {
            $seconds = self::seconds_for_content( (string) $post->post_content );
            $rows[] = [
                "post_id" => (int) $post->ID,
                "title" => get_the_title( $post ),
                "words" => self::word_count( (string) $post->post_content ),
                "seconds" => $seconds,
                "minutes" => self::value_for_unit( $seconds, "minutes" ),
            ];
        }

        return [
            "enabled" => Settings::bool( "estimated_read_time_enabled" ),
            "shortcode" => "[" . self::SHORTCODE . "]",
            "posts" => $rows,
        ];
    }
}
