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
        add_action( "wp_enqueue_scripts", [ $this, "enqueue_icon_styles" ], 20 );
        add_action( "wp_footer", [ $this, "print_elementor_injection_script" ], 30 );
    }

    public function register_shortcodes(): void {
        add_shortcode( "acf_author_field", [ $this, "render_author_field_shortcode" ] );
        add_shortcode( "muckrack_verified", [ $this, "render_muckrack_shortcode" ] );
        add_shortcode( "smp_publication_muckrack_verified", [ $this, "render_publication_shortcode" ] );
    }

    public function enqueue_icon_styles(): void {
        if ( is_admin() || ( ! Settings::bool( "muckrack_verified_enabled" ) && ! Settings::bool( "publication_muckrack_verified_enabled" ) ) ) {
            return;
        }

        foreach ( [ "elementor-icons-fa-solid", "font-awesome-5-all", "font-awesome", "elementor-icons" ] as $handle ) {
            if ( wp_style_is( $handle, "registered" ) ) {
                wp_enqueue_style( $handle );
            }
        }
    }

    public function print_styles(): void {
        if ( ! Settings::bool( "muckrack_verified_enabled" ) && ! Settings::bool( "publication_muckrack_verified_enabled" ) ) {
            return;
        }

        echo "<style id=smpi-muckrack-styles>.smpi-muckrack-icon-circle,.smpi-muckrack-icon-check{display:inline-flex;align-items:center;justify-content:center;margin-left:.28em;vertical-align:middle;line-height:1;--smpi-muckrack-color:#2d5277}.smpi-muckrack-icon-circle{width:1.35em;height:1.35em;border-radius:999px;background:var(--smpi-muckrack-color,#2d5277);color:#fff;font-size:.86em}.smpi-muckrack-icon-check{color:var(--smpi-muckrack-color,#2d5277);font-size:1em;background:transparent}.smpi-muckrack-icon-circle i,.smpi-muckrack-icon-check i{display:block;line-height:1}.smpi-muckrack-icon-circle i{font-size:.78em}.smpi-muckrack-link{text-decoration:none}.smpi-muckrack-brand{color:var(--smpi-muckrack-color,#2d5277);font-weight:700}.smpi-muckrack-footer-note,.smpi-muckrack-js-below-author,.smpi-muckrack-js-bottom-article{margin:24px 0 0}.smpi-muckrack-author-note{display:inline-flex;align-items:center;gap:.28em;margin:.18em 0 .18em .38em;padding:.34em .55em;border-left:2px solid var(--smpi-muckrack-color,#2d5277);background:#f5f8fb;color:#64748b;font-size:.72em;line-height:1.28;vertical-align:middle}.smpi-muckrack-author-note .smpi-muckrack-brand{color:var(--smpi-muckrack-color,#2d5277)}.smpi-muckrack-author-note a{color:inherit}.smpi-muckrack-footer-note{padding:12px 14px;border-left:3px solid var(--smpi-muckrack-color,#2d5277);background:#f5f8fb;font-size:.95em}.smpi-muckrack-publication-text{--smpi-muckrack-color:#2d5277}.smpi-muckrack-publication-note{margin:.35em 0 0;font-size:.92em;line-height:1.35;color:#334155}.smpi-muckrack-publication-footer{font-size:.95em}.smpi-muckrack-publication-block{display:block;padding:12px 14px;border-left:3px solid var(--smpi-muckrack-color,#2d5277);background:#f5f8fb}.smpi-muckrack-publication-compact{display:inline-flex;align-items:center;gap:.35em;padding:.28em .7em;border:1px solid var(--smpi-muckrack-color,#2d5277);border-radius:999px;background:#fff;font-size:.92em}.smpi-muckrack-publication-minimalist{display:inline;color:inherit;font-size:.95em}.smpi-muckrack-publication-compact a,.smpi-muckrack-publication-minimalist a,.smpi-muckrack-publication-block a{color:inherit}</style>";
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

    public function print_elementor_injection_script(): void {
        if ( is_admin() ) {
            return;
        }

        $author_badge = "";
        if ( Settings::bool( "muckrack_verified_enabled" ) ) {
            $context = $this->author_context();
            if ( "" !== $context && in_array( $context, Settings::array( "muckrack_verified_contexts" ), true ) ) {
                $author_id = $this->resolve_author_id( 0 );
                if ( $author_id && self::author_verified( $author_id ) ) {
                    $author_badge = self::verification_icon( $author_id, (string) Settings::get( "muckrack_verified_style", "tooltip" ) );
                }
            }
        }

        $publication_below = "";
        $publication_bottom = "";
        if ( is_singular( "post" ) && self::publication_enabled() ) {
            if ( in_array( "below_author", Settings::array( "publication_muckrack_placements" ), true ) ) {
                $publication_below = self::publication_verification_text( "smpi-muckrack-publication-note" );
            }
            if ( in_array( "bottom_article", Settings::array( "publication_muckrack_placements" ), true ) ) {
                $publication_bottom = self::publication_verification_text( "smpi-muckrack-publication-footer" );
            }
        }

        if ( "" === $author_badge && "" === $publication_below && "" === $publication_bottom ) {
            return;
        }

        $payload = wp_json_encode(
            [
                "authorBadge" => $author_badge,
                "publicationBelow" => $publication_below,
                "publicationBottom" => $publication_bottom,
            ]
        );

        echo "<script id=\"smpi-muckrack-elementor-placement\">(function(data){if(!data)return;function htmlNode(html){var t=document.createElement(\"template\");t.innerHTML=(html||\"\").trim();return t.content.firstElementChild;}function visible(el){return !!(el&&el.offsetParent!==null&&(el.textContent||\"\").trim());}function unique(nodes){var out=[];nodes.forEach(function(n){if(n&&out.indexOf(n)<0)out.push(n);});return out;}function authorTargets(){var selectors=[\".elementor-post-info__item--type-author\",\".elementor-author-box__name\",\".elementor-widget-theme-post-author .elementor-author-box__name\",\".elementor-widget-author-box .elementor-author-box__name\",\".elementor-icon-list-item a[href*=\\\"/author/\\\"] .elementor-icon-list-text\",\".elementor-icon-list-item a[href*=\\\"/author/\\\"]\",\"a[rel=\\\"author\\\"]\",\".byline .author\"];var found=[];selectors.forEach(function(sel){document.querySelectorAll(sel).forEach(function(el){if(visible(el))found.push(el);});});return unique(found);}function hasBadgeNear(el){var root=el.closest(\".elementor-widget,.elementor-icon-list-item,.elementor-author-box,.byline,article\")||el.parentElement;return !!(root&&root.querySelector(\".smpi-muckrack-icon-circle,.smpi-muckrack-icon-check\"));}function injectAuthor(){if(!data.authorBadge)return;authorTargets().forEach(function(el){if(hasBadgeNear(el))return;var node=htmlNode(data.authorBadge);if(node)el.insertAdjacentElement(\"afterend\",node);});}function authorContainer(){var targets=authorTargets();if(!targets.length)return null;var el=targets[0];return el.closest(\".elementor-widget-author-box,.elementor-widget-theme-post-author,.elementor-widget-post-info,.elementor-author-box,.elementor-icon-list-item,.elementor-widget,.byline\")||el;}function insertAfter(target,html,marker){if(!target||!html||document.querySelector(\".\"+marker))return;var wrap=document.createElement(\"div\");wrap.className=marker;wrap.innerHTML=html;target.insertAdjacentElement(\"afterend\",wrap);}function injectBelowAuthor(){insertAfter(authorContainer(),data.publicationBelow,\"smpi-muckrack-js-below-author\");}function injectBottom(){if(!data.publicationBottom||document.querySelector(\".smpi-muckrack-publication-footer-wrap,.smpi-muckrack-js-bottom-article\"))return;var selectors=[\".elementor-widget-theme-post-content\",\".elementor-widget-post-content\",\"article .entry-content\",\".entry-content\",\".post-content\"];for(var i=0;i<selectors.length;i++){var target=document.querySelector(selectors[i]);if(target){insertAfter(target,data.publicationBottom,\"smpi-muckrack-js-bottom-article\");return;}}}function run(){injectAuthor();injectBelowAuthor();injectBottom();}if(document.readyState===\"loading\"){document.addEventListener(\"DOMContentLoaded\",run);}else{run();}setTimeout(run,800);})(" . $payload . ");</script>";
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
        if ( "text" === $style ) {
            return self::verification_text( $author_id );
        }
        if ( "compact_block" === $style ) {
            return self::verification_author_note( $author_id );
        }
        $url = esc_url( (string) self::author_field( $author_id, self::FIELD_URL ) );
        $label = esc_attr( "Verified by MuckRack editorial team" );
        $color = sanitize_hex_color( (string) Settings::get( "muckrack_icon_color", "#2d5277" ) ) ?: "#2d5277";
        $icon_style = "check" === (string) Settings::get( "muckrack_icon_style", "circle_check" ) ? "smpi-muckrack-icon-check" : "smpi-muckrack-icon-circle";
        $quote = chr( 34 );
        $icon = "<span class=" . $quote . esc_attr( $icon_style ) . $quote . " title=" . $quote . $label . $quote . " aria-label=" . $quote . $label . $quote . " style=" . $quote . "--smpi-muckrack-color:" . esc_attr( $color ) . $quote . "><i class=" . $quote . "fas fa-solid fa-check" . $quote . " aria-hidden=" . $quote . "true" . $quote . "></i></span>";
        return $url ? "<a class=smpi-muckrack-link href=" . $quote . $url . $quote . " target=_blank rel=noopener>" . $icon . "</a>" : $icon;
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

    public static function verification_author_note( int $author_id ): string {
        if ( ! self::author_verified( $author_id ) ) {
            return "";
        }
        $url = (string) self::author_field( $author_id, self::FIELD_URL );
        $target = "" !== $url ? $url : "https://muckrack.com/";
        $color = sanitize_hex_color( (string) Settings::get( "muckrack_icon_color", "#2d5277" ) ) ?: "#2d5277";
        return "<span class=smpi-muckrack-author-note style=--smpi-muckrack-color:" . esc_attr( $color ) . ">Author verified by <span class=smpi-muckrack-brand>MuckRack</span> editorial team <a href=" . esc_url( $target ) . " target=_blank rel=noopener>(learn more)</a></span>";
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

        return self::publication_verification_markup( $class );
    }

    public static function publication_verification_markup( string $class = "", string $style_override = "", string $color_override = "" ): string {
        $mode = (string) Settings::get( "publication_muckrack_text_mode", "news_outlet" );
        $label = "publication_name" === $mode ? get_bloginfo( "name" ) : "News outlet";
        $url = trim( (string) Fields::option( "publication_muckrack_url" ) );
        $target = "" !== $url ? $url : "https://muckrack.com/";
        $style = sanitize_key( "" !== $style_override ? $style_override : (string) Settings::get( "publication_muckrack_style", "block" ) );
        if ( ! in_array( $style, [ "block", "compact", "minimalist" ], true ) ) {
            $style = "block";
        }
        $color = sanitize_hex_color( "" !== $color_override ? $color_override : (string) Settings::get( "publication_muckrack_color", "#2d5277" ) ) ?: "#2d5277";
        $classes = trim( "smpi-muckrack-publication-text smpi-muckrack-publication-" . $style . " " . $class );

        return "<span class=\"" . esc_attr( $classes ) . "\" style=\"--smpi-muckrack-color:" . esc_attr( $color ) . "\">" . esc_html( $label ) . " verified by <span class=\"smpi-muckrack-brand\">MuckRack</span> editorial team <a href=\"" . esc_url( $target ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">(learn more)</a></span>";
    }

    public static function publication_report(): array {
        return [
            "enabled" => Settings::bool( "publication_muckrack_verified_enabled" ),
            "acf_verified" => self::publication_verified(),
            "effective" => self::publication_enabled(),
            "text_mode" => (string) Settings::get( "publication_muckrack_text_mode", "news_outlet" ),
            "style" => (string) Settings::get( "publication_muckrack_style", "block" ),
            "color" => sanitize_hex_color( (string) Settings::get( "publication_muckrack_color", "#2d5277" ) ) ?: "#2d5277",
            "placements" => Settings::array( "publication_muckrack_placements" ),
            "url" => trim( (string) Fields::option( "publication_muckrack_url" ) ),
            "shortcode" => "[smp_publication_muckrack_verified]",
            "preview" => wp_strip_all_tags( self::publication_verification_text() ),
            "preview_html" => self::publication_verification_text(),
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
