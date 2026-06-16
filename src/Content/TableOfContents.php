<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class TableOfContents {
    public const SHORTCODE = "smp_table_of_contents";

    public function register(): void {
        add_shortcode( self::SHORTCODE, [ $this, "render_shortcode" ] );
        add_filter( "the_content", [ $this, "filter_content" ], 11 );
        add_action( "wp_head", [ $this, "print_styles" ], 32 );
    }

    public function render_shortcode( array $atts = [] ): string {
        if ( ! Settings::bool( "table_of_contents_enabled" ) ) {
            return "";
        }
        $atts = shortcode_atts( [ "post_id" => 0, "title" => "Table of Contents" ], $atts, self::SHORTCODE );
        $post = $this->resolve_post( (int) $atts["post_id"] );
        if ( ! $post ) {
            return "";
        }
        return self::build_toc( (string) $post->post_content, (string) $atts["title"] );
    }

    public function filter_content( string $content ): string {
        if ( is_admin() || ! Settings::bool( "table_of_contents_enabled" ) || ! Settings::bool( "table_of_contents_auto_single" ) ) {
            return $content;
        }
        if ( ! is_singular( "post" ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }
        if ( has_shortcode( $content, self::SHORTCODE ) ) {
            return $content;
        }
        $items = self::items( $content );
        if ( empty( $items ) ) {
            return $content;
        }
        return self::build_toc_from_items( $items, "Table of Contents" ) . self::add_heading_ids( $content, $items );
    }

    public function print_styles(): void {
        if ( ! Settings::bool( "table_of_contents_enabled" ) ) {
            return;
        }
        echo "<style id=smpi-table-of-contents-styles>.smpi-table-of-contents{max-width:var(--content-width,1140px);margin:0 auto 24px;padding:18px 20px;border:1px solid #d8dee8;border-radius:14px;background:#f8fafc}.smpi-table-of-contents h2{margin:0 0 10px;font-size:18px}.smpi-table-of-contents ol{margin:0;padding-left:20px}.smpi-table-of-contents li{margin:5px 0}.smpi-table-of-contents a{text-decoration:none}</style>";
    }

    private function resolve_post( int $post_id ): ?\WP_Post {
        if ( $post_id > 0 ) {
            $post = get_post( $post_id );
            return $post instanceof \WP_Post ? $post : null;
        }
        $post = get_post();
        return $post instanceof \WP_Post ? $post : null;
    }

    public static function build_toc( string $content, string $title = "Table of Contents" ): string {
        return self::build_toc_from_items( self::items( $content ), $title );
    }

    private static function build_toc_from_items( array $items, string $title ): string {
        if ( empty( $items ) ) {
            return "";
        }
        $html = "<nav class=smpi-table-of-contents><h2>" . esc_html( $title ) . "</h2><ol>";
        foreach ( $items as $item ) {
            $html .= "<li class=smpi-toc-level-" . esc_attr( (string) $item["level"] ) . "><a href=#" . esc_attr( $item["id"] ) . ">" . esc_html( $item["text"] ) . "</a></li>";
        }
        return $html . "</ol></nav>";
    }

    private static function items( string $content ): array {
        if ( ! preg_match_all( "/<h([2-4])([^>]*)>(.*?)<\/h\1>/is", $content, $matches, PREG_SET_ORDER ) ) {
            return [];
        }
        $items = [];
        foreach ( $matches as $index => $match ) {
            $text = trim( wp_strip_all_tags( $match[3] ) );
            if ( "" === $text ) {
                continue;
            }
            $items[] = [
                "level" => (int) $match[1],
                "text" => $text,
                "id" => "smpi-toc-" . sanitize_title( $text ) . "-" . ( $index + 1 ),
            ];
        }
        return $items;
    }

    private static function add_heading_ids( string $content, array $items ): string {
        $index = 0;
        return (string) preg_replace_callback(
            "/<h([2-4])([^>]*)>(.*?)<\/h\1>/is",
            static function ( array $match ) use ( &$index, $items ): string {
                if ( ! isset( $items[ $index ] ) ) {
                    return $match[0];
                }
                $id = $items[ $index ]["id"];
                $index++;
                if ( preg_match( "/\sid=/i", $match[2] ) ) {
                    return $match[0];
                }
                return "<h" . $match[1] . $match[2] . " id=" . esc_attr( $id ) . ">" . $match[3] . "</h" . $match[1] . ">";
            },
            $content
        );
    }

    public static function integrity_report(): array {
        $post = get_posts( [ "post_type" => "post", "post_status" => "publish", "posts_per_page" => 1 ] );
        $post = $post[0] ?? null;
        $items = $post instanceof \WP_Post ? self::items( (string) $post->post_content ) : [];
        return [
            "enabled" => Settings::bool( "table_of_contents_enabled" ),
            "auto_single" => Settings::bool( "table_of_contents_auto_single" ),
            "shortcode" => "[smp_table_of_contents]",
            "sample_post_id" => $post instanceof \WP_Post ? (int) $post->ID : 0,
            "heading_count" => count( $items ),
        ];
    }
}
