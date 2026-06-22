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
        add_action( "wp_footer", [ $this, "print_auto_inject_script" ], 32 );
    }

    public function render_shortcode( array $atts = [] ): string {
        if ( ! Settings::bool( "table_of_contents_enabled" ) ) {
            return "";
        }
        $atts = shortcode_atts( [ "post_id" => 0, "title" => "Table of Contents", "style" => "" ], $atts, self::SHORTCODE );
        $post = $this->resolve_post( (int) $atts["post_id"] );
        if ( ! $post ) {
            return "";
        }
        return self::build_toc( (string) $post->post_content, (string) $atts["title"], sanitize_key( (string) $atts["style"] ) );
    }

    public function filter_content( string $content ): string {
        if ( is_admin() || ! Settings::bool( "table_of_contents_enabled" ) || ! Settings::bool( "table_of_contents_auto_single" ) ) {
            return $content;
        }
        static $inserted = false;
        if ( $inserted || ! is_singular( "post" ) ) {
            return $content;
        }
        if ( has_shortcode( $content, self::SHORTCODE ) ) {
            return $content;
        }
        $items = self::items( $content );
        if ( empty( $items ) ) {
            return $content;
        }
        $inserted = true;
        return self::build_toc_from_items( $items, "Table of Contents" ) . self::add_heading_ids( $content, $items );
    }

    public function print_styles(): void {
        if ( ! Settings::bool( "table_of_contents_enabled" ) ) {
            return;
        }
        echo "<style id=smpi-table-of-contents-styles>" . ArticleStyles::toc_css() . "</style>";
    }

    private function resolve_post( int $post_id ): ?\WP_Post {
        if ( $post_id > 0 ) {
            $post = get_post( $post_id );
            return $post instanceof \WP_Post ? $post : null;
        }
        $post = get_post();
        return $post instanceof \WP_Post ? $post : null;
    }

    public static function build_toc( string $content, string $title = "Table of Contents", string $style = "" ): string {
        return self::build_toc_from_items( self::items( $content ), $title, $style );
    }

    private static function build_toc_from_items( array $items, string $title, string $style = "" ): string {
        if ( empty( $items ) ) {
            return "";
        }
        $style = ArticleStyles::normalize_toc_style( $style );
        $class = "smpi-table-of-contents smpi-" . $style . " smpi-toc-collapsible";
        $caret = "<span class=\"smpi-toc-caret\" aria-hidden=\"true\"></span>";
        if ( "toc04" === $style ) {
            $html = "<details class=\"" . esc_attr( $class ) . "\"><summary class=\"smpi-toc-label\"><span>" . esc_html( "Jump to" ) . "</span>" . $caret . "</summary><div class=\"smpi-toc-panel\">";
            foreach ( $items as $item ) {
                $html .= "<a href=#" . esc_attr( $item["id"] ) . ">" . esc_html( $item["text"] ) . "</a>";
            }
            return $html . "</div></details>";
        }
        $label = "toc00" === $style ? "In this article" : ( "toc02" === $style ? "On this page" : $title );
        $html = "<details class=\"" . esc_attr( $class ) . "\"><summary class=\"smpi-toc-label\"><span>" . esc_html( $label ) . "</span>" . $caret . "</summary><ol>";
        foreach ( $items as $item ) {
            $html .= "<li class=smpi-toc-level-" . esc_attr( (string) $item["level"] ) . "><a href=#" . esc_attr( $item["id"] ) . ">" . esc_html( $item["text"] ) . "</a></li>";
        }
        return $html . "</ol></details>";
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
            $items[] = [ "level" => (int) $match[1], "text" => $text, "id" => "smpi-toc-" . sanitize_title( $text ) . "-" . ( $index + 1 ) ];
        }
        return $items;
    }

    private static function add_heading_ids( string $content, array $items ): string {
        $index = 0;
        return (string) preg_replace_callback( "/<h([2-4])([^>]*)>(.*?)<\/h\1>/is", static function ( array $match ) use ( &$index, $items ): string {
            if ( ! isset( $items[ $index ] ) ) {
                return $match[0];
            }
            $id = $items[ $index ]["id"];
            $index++;
            if ( preg_match( "/\sid=/i", $match[2] ) ) {
                return $match[0];
            }
            return "<h" . $match[1] . $match[2] . " id=" . esc_attr( $id ) . ">" . $match[3] . "</h" . $match[1] . ">";
        }, $content );
    }

    public function print_auto_inject_script(): void {
        if ( is_admin() || ! Settings::bool( "table_of_contents_enabled" ) || ! Settings::bool( "table_of_contents_auto_single" ) || ! is_singular( "post" ) ) {
            return;
        }
        $style = ArticleStyles::normalize_toc_style( (string) Settings::get( "table_of_contents_style", "toc02" ) );
        if ( "none" === $style ) {
            return;
        }
        $payload = wp_json_encode( [ "style" => $style, "include_summary" => Settings::bool( "table_of_contents_include_summary" ) ] );
        $script = <<<SMPI_JS
(function(data){if(!data||document.querySelector(".smpi-table-of-contents"))return;function visible(el){var r=el.getBoundingClientRect();return r.width>1&&r.height>1&&window.getComputedStyle(el).display!=="none";}function slug(text,index){return "smpi-toc-"+text.toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-|-$/g,"")+"-"+index;}var selectors=[".elementor-widget-theme-post-content .elementor-widget-container",".elementor-widget-theme-post-content",".elementor-widget-post-content","article .entry-content",".entry-content",".post-content"];var target=null;for(var i=0;i<selectors.length;i++){target=document.querySelector(selectors[i]);if(target)break;}if(!target)return;var headings=Array.from(target.querySelectorAll("h2,h3,h4")).filter(function(h){return visible(h)&&(h.textContent||"").trim();});if(data.include_summary){var sumH=document.querySelector(".smpi-post-summary h2");if(sumH&&visible(sumH)&&(sumH.textContent||"").trim()){if(!sumH.id)sumH.id="smpi-summary-heading";headings.unshift(sumH);}}if(!headings.length)return;var details=document.createElement("details");details.className="smpi-table-of-contents smpi-"+(data.style||"toc02")+" smpi-toc-collapsible";details.setAttribute("aria-label","Table of contents");var summary=document.createElement("summary");summary.className="smpi-toc-label";var labelText=data.style==="toc04"?"Jump to":(data.style==="toc00"?"In this article":(data.style==="toc02"?"On this page":"Table of Contents"));var lspan=document.createElement("span");lspan.textContent=labelText;summary.appendChild(lspan);var caret=document.createElement("span");caret.className="smpi-toc-caret";caret.setAttribute("aria-hidden","true");summary.appendChild(caret);details.appendChild(summary);if(data.style==="toc04"){var panel=document.createElement("div");panel.className="smpi-toc-panel";headings.forEach(function(h,idx){if(!h.id)h.id=slug(h.textContent,idx+1);var a=document.createElement("a");a.href="#"+h.id;a.textContent=h.textContent.trim();panel.appendChild(a);});details.appendChild(panel);}else{var ol=document.createElement("ol");headings.forEach(function(h,idx){if(!h.id)h.id=slug(h.textContent,idx+1);var li=document.createElement("li");li.className="smpi-toc-level-"+h.tagName.slice(1);var a=document.createElement("a");a.href="#"+h.id;a.textContent=h.textContent.trim();li.appendChild(a);ol.appendChild(li);});details.appendChild(ol);}target.parentNode.insertBefore(details,target);})(
SMPI_JS;
        echo "<script id=\"smpi-toc-auto-inject\">" . $script . $payload . ");</script>";
    }
    public static function integrity_report(): array {
        $post = get_posts( [ "post_type" => "post", "post_status" => "publish", "posts_per_page" => 1 ] );
        $post = $post[0] ?? null;
        $items = $post instanceof \WP_Post ? self::items( (string) $post->post_content ) : [];
        return [ "enabled" => Settings::bool( "table_of_contents_enabled" ), "auto_single" => Settings::bool( "table_of_contents_auto_single" ), "style" => (string) Settings::get( "table_of_contents_style", "toc02" ), "shortcode" => "[smp_table_of_contents]", "sample_post_id" => $post instanceof \WP_Post ? (int) $post->ID : 0, "heading_count" => count( $items ) ];
    }
}
