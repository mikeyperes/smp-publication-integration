<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Fields;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class MuckRackVerification {
    private const FIELD_VERIFIED = "muckrack_verified";
    private const FIELD_URL = "muckrack_url";
    private const FIELD_DESCRIPTION = "what_best_describe_you";

    public function register(): void {
        add_action( "init", [ $this, "register_shortcodes" ], 100 );
        add_filter( "the_author", [ $this, "filter_author_name" ], 20, 1 );
        add_filter( "the_content", [ $this, "filter_content" ], 30 );
        add_action( "wp_head", [ $this, "print_styles" ], 30 );
    }

    public function register_shortcodes(): void {
        add_shortcode( "acf_author_field", [ $this, "render_author_field_shortcode" ] );
        add_shortcode( "muckrack_verified", [ $this, "render_muckrack_shortcode" ] );
        add_shortcode( "smp_publication_muckrack_verified", [ $this, "render_publication_shortcode" ] );
    }

    public function print_styles(): void {
        if ( ! Settings::bool( "muckrack_verified_enabled" ) && ! Settings::bool( "publication_muckrack_verified_enabled" ) ) {
            return;
        }

        echo "<style id=\"smpi-muckrack-styles\">.smpi-muckrack-icon{display:inline-flex;align-items:center;justify-content:center;margin-left:.28em;color:var(--e-global-color-primary,#2d5277);vertical-align:middle}.smpi-muckrack-link{text-decoration:none}.smpi-muckrack-brand{color:#2d5277;font-weight:700}.smpi-muckrack-footer-note{margin:24px 0 0;padding:12px 14px;border-left:3px solid #2d5277;background:#f5f8fb;font-size:.95em}.smpi-muckrack-publication-note{display:block;margin:.35em 0 0;font-size:.92em;line-height:1.35;color:#334155}.smpi-muckrack-publication-footer{margin:24px 0 0;padding:12px 14px;border-left:3px solid #2d5277;background:#f5f8fb;font-size:.95em}</style>";
    }

    public function render_author_field_shortcode( array $atts = [] ): string {
        $atts = shortcode_atts( [ "field" => "", "user_id" => 0 ], $atts, "acf_author_field" );
        $field = sanitize_key( (string) $atts["field"] );
        if ( "" === $field ) {
            return "";
        }
        $author_id = $this->resolve_author_id( (int) $atts["user_id"] );
        if ( ! $author_id ) {
            return "";
        }
        $value = self::author_field( $author_id, $field );
        return is_array( $value ) || is_object( $value ) ? esc_html( wp_json_encode( $value ) ) : wp_kses_post( (string) $value );
    }

    public function render_muckrack_shortcode( array $atts = [] ): string {
        $atts = shortcode_atts( [ "type" => "icon", "user_id" => 0, "style" => "" ], $atts, "muckrack_verified" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"] );
        if ( ! $author_id || ! self::author_verified( $author_id ) ) {
            return "";
        }
        return "text" === sanitize_key( (string) $atts["type"] ) ? self::verification_text( $author_id ) : self::verification_icon( $author_id, sanitize_key( (string) $atts["style"] ) );
    }

    public function render_publication_shortcode( array $atts = [] ): string {
        $atts = shortcode_atts( [ "class" => "" ], $atts, "smp_publication_muckrack_verified" );
        return self::publication_verification_text( sanitize_html_class( (string) $atts["class"] ) );
    }

    public function filter_author_name( string $display_name ): string {
        if ( is_admin() ) {
            return $display_name;
        }

        $output = $display_name;
        if ( Settings::bool( "muckrack_verified_enabled" ) ) {
            $context = $this->author_context();
            if ( "" !== $context && in_array( $context, Settings::array( "muckrack_verified_contexts" ), true ) ) {
                $author_id = $this->resolve_author_id( 0 );
                if ( $author_id && self::author_verified( $author_id ) ) {
                    $output .= " " . self::verification_icon( $author_id, (string) Settings::get( "muckrack_verified_style", "tooltip" ) );
                }
            }
        }

        if ( is_singular( "post" ) && in_array( "below_author", Settings::array( "publication_muckrack_placements" ), true ) ) {
            $publication_note = self::publication_verification_text( "smpi-muckrack-publication-note" );
            if ( "" !== $publication_note ) {
                $output .= $publication_note;
            }
        }

        return $output;
    }

    public function filter_content( string $content ): string {
        if ( is_admin() || ! is_singular( "post" ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $append = "";
        if ( Settings::bool( "muckrack_verified_enabled" ) && in_array( "single_footer", Settings::array( "muckrack_verified_contexts" ), true ) ) {
            $author_id = $this->resolve_author_id( 0 );
            if ( $author_id && self::author_verified( $author_id ) ) {
                $append .= "<div class=\"smpi-muckrack-footer-note\">" . self::verification_text( $author_id ) . "</div>";
            }
        }

        if ( in_array( "bottom_article", Settings::array( "publication_muckrack_placements" ), true ) ) {
            $publication_note = self::publication_verification_text( "smpi-muckrack-publication-footer" );
            if ( "" !== $publication_note ) {
                $append .= "<div class=\"smpi-muckrack-publication-footer-wrap\">" . $publication_note . "</div>";
            }
        }

        return $content . $append;
    }

    private function author_context(): string {
        if ( is_singular( "post" ) ) {
            return "single_author";
        }
        if ( is_author() ) {
            return "author";
        }
        if ( is_home() || is_front_page() ) {
            return "home";
        }
        return "";
    }

    private function resolve_author_id( int $explicit_id = 0 ): int {
        if ( $explicit_id > 0 ) {
            return $explicit_id;
        }
        if ( is_author() ) {
            return (int) get_queried_object_id();
        }
        $post = get_post();
        return $post ? (int) $post->post_author : 0;
    }

    public static function author_field( int $author_id, string $field ) {
        if ( function_exists( "get_field" ) ) {
            $value = get_field( $field, "user_" . $author_id );
            if ( null !== $value && false !== $value && "" !== $value ) {
                return $value;
            }
        }
        return get_user_meta( $author_id, $field, true );
    }

    public static function author_acf_verified( int $author_id ): bool {
        return self::truthy( self::author_field( $author_id, self::FIELD_VERIFIED ) );
    }

    public static function author_verified( int $author_id ): bool {
        return self::author_acf_verified( $author_id ) || Settings::bool( "muckrack_author_always_show" );
    }

    public static function verification_icon( int $author_id, string $style = "tooltip" ): string {
        if ( ! self::author_verified( $author_id ) ) {
            return "";
        }
        $url = esc_url( (string) self::author_field( $author_id, self::FIELD_URL ) );
        $label = esc_attr( "Verified by MuckRack editorial team" );
        $icon = "<span class=\"smpi-muckrack-icon\" title=\"" . $label . "\" aria-label=\"" . $label . "\"><i aria-hidden=\"true\" class=\"fas fa-check-circle\"></i></span>";
        if ( "text" === $style ) {
            return self::verification_text( $author_id );
        }
        return $url ? "<a class=\"smpi-muckrack-link\" href=\"" . $url . "\" target=\"_blank\" rel=\"noopener noreferrer\">" . $icon . "</a>" : $icon;
    }

    public static function verification_text( int $author_id ): string {
        if ( ! self::author_verified( $author_id ) ) {
            return "";
        }
        $url = (string) self::author_field( $author_id, self::FIELD_URL );
        $description = trim( (string) self::author_field( $author_id, self::FIELD_DESCRIPTION ) );
        $description = "" !== $description ? $description : "Author";
        $target = "" !== $url ? $url : "https://muckrack.com/";
        return esc_html( $description ) . " verified by <span class=\"smpi-muckrack-brand\">MuckRack</span> editorial team <a href=\"" . esc_url( $target ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">(learn more)</a>";
    }

    public static function publication_verified(): bool {
        return self::truthy( Fields::option( "publication_muckrack_verified" ) );
    }

    public static function publication_enabled(): bool {
        return Settings::bool( "publication_muckrack_verified_enabled" ) && self::publication_verified();
    }

    public static function publication_verification_text( string $class = "" ): string {
        if ( ! self::publication_enabled() ) {
            return "";
        }

        $mode = (string) Settings::get( "publication_muckrack_text_mode", "news_outlet" );
        $label = "publication_name" === $mode ? get_bloginfo( "name" ) : "News outlet";
        $url = trim( (string) Fields::option( "publication_muckrack_url" ) );
        $target = "" !== $url ? $url : "https://muckrack.com/";
        $classes = trim( "smpi-muckrack-publication-text " . $class );

        return "<span class=\"" . esc_attr( $classes ) . "\">" . esc_html( $label ) . " verified by <span class=\"smpi-muckrack-brand\">MuckRack</span> editorial team <a href=\"" . esc_url( $target ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">(learn more)</a></span>";
    }

    public static function publication_report(): array {
        return [
            "enabled" => Settings::bool( "publication_muckrack_verified_enabled" ),
            "acf_verified" => self::publication_verified(),
            "effective" => self::publication_enabled(),
            "text_mode" => (string) Settings::get( "publication_muckrack_text_mode", "news_outlet" ),
            "placements" => Settings::array( "publication_muckrack_placements" ),
            "url" => trim( (string) Fields::option( "publication_muckrack_url" ) ),
            "shortcode" => "[smp_publication_muckrack_verified]",
            "preview" => wp_strip_all_tags( self::publication_verification_text() ),
        ];
    }

    public static function integrity_report( int $limit = 10 ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT u.ID, u.display_name, COUNT(p.ID) AS posts FROM {$wpdb->users} u LEFT JOIN {$wpdb->posts} p ON p.post_author = u.ID AND p.post_type = %s AND p.post_status = %s GROUP BY u.ID ORDER BY posts DESC, u.ID ASC LIMIT %d", "post", "publish", $limit ) );
        $out = [];
        foreach ( $rows as $row ) {
            $author_id = (int) $row->ID;
            $out[] = [
                "user_id" => $author_id,
                "display_name" => $row->display_name,
                "posts" => (int) $row->posts,
                "acf_verified" => self::author_acf_verified( $author_id ),
                "verified" => self::author_verified( $author_id ),
                "forced" => Settings::bool( "muckrack_author_always_show" ),
                "has_url" => "" !== trim( (string) self::author_field( $author_id, self::FIELD_URL ) ),
                "has_description" => "" !== trim( (string) self::author_field( $author_id, self::FIELD_DESCRIPTION ) ),
                "shortcode_icon" => "" !== self::verification_icon( $author_id ),
            ];
        }
        return $out;
    }

    private static function truthy( $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }
        $value = strtolower( trim( (string) $value ) );
        return in_array( $value, [ "1", "true", "yes", "on", "verified" ], true );
    }
}
