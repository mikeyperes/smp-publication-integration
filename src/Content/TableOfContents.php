<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Fields;
use smp_publication_integration\Support\RuntimeContext;
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
        add_action( "wp_footer", [ $this, "print_scroll_tracking_script" ], 33 );
    }

    public function render_shortcode( array $atts = [] ): string {
        if ( ! Settings::bool( "table_of_contents_enabled" ) ) {
            return "";
        }
        $title_supplied = array_key_exists( "title", $atts );
        $atts = shortcode_atts(
            [
                "post_id" => 0,
                "title" => "Table of Contents",
                "style" => "",
                "levels" => "h2,h3,h4",
                "open" => "",
                "markers" => "",
            ],
            $atts,
            self::SHORTCODE
        );
        $post = $this->resolve_post( (int) $atts["post_id"] );
        if ( ! $post ) {
            return "";
        }
        $items = self::filter_items_by_levels( self::items_for_post( $post ), (string) $atts["levels"] );
        if ( empty( $items ) ) {
            return "";
        }
        $open_mode = self::normalize_open_mode( (string) $atts["open"] );
        $html = self::build_toc_from_items(
            $items,
            (string) $atts["title"],
            sanitize_key( (string) $atts["style"] ),
            $title_supplied,
            $open_mode,
            sanitize_key( (string) $atts["markers"] )
        );
        return $html . self::responsive_open_script( $open_mode ) . self::shortcode_anchor_script( $items );
    }

    public function filter_content( string $content ): string {
        if ( ! RuntimeContext::is_public_dom_context() || ! Settings::bool( "table_of_contents_enabled" ) || ! Settings::bool( "table_of_contents_auto_single" ) ) {
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
        if ( ! RuntimeContext::is_public_dom_context() ) {
            return;
        }
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

    private static function items_for_post( \WP_Post $post ): array {
        $items = self::items( (string) $post->post_content );
        $summary = self::summary_item( (int) $post->ID );
        if ( $summary ) {
            array_unshift( $items, $summary );
        }
        return $items;
    }

    private static function summary_item( int $post_id ): array {
        if ( $post_id <= 0 || ! Settings::bool( "table_of_contents_include_summary" ) || ! Settings::bool( "post_summary_acf_enabled" ) ) {
            return [];
        }
        $summary = Fields::get( $post_id, "post_summary", "" );
        if ( ! Fields::has_value( $summary ) ) {
            return [];
        }
        $style = ArticleStyles::normalize_summary_style( (string) Settings::get( "post_summary_style", "none" ) );
        return [ "level" => 2, "text" => ArticleStyles::summary_title( $style ), "id" => "smpi-summary-heading", "source" => "summary" ];
    }

    private static function build_toc_from_items( array $items, string $title, string $style = "", bool $title_supplied = false, string $open_mode = "", string $markers = "" ): string {
        if ( empty( $items ) ) {
            return "";
        }
        $style = ArticleStyles::normalize_toc_style( $style );
        $class = TemplateMarkup::root_classes( "toc", [ "smpi-table-of-contents", "smpi-" . $style, "smpi-toc-collapsible" ] );
        $skin = "unstyled" === $style ? "unstyled" : "styled";
        $skin_attribute = " data-smpi-skin=\"" . esc_attr( $skin ) . "\"";
        $open_attribute = in_array( $open_mode, [ "always", "desktop" ], true ) ? " open" : "";
        $responsive_attribute = "desktop" === $open_mode ? " data-smpi-open=\"desktop\"" : "";
        $markers_attribute = "leading-zero" === $markers ? " data-smpi-markers=\"leading-zero\"" : "";
        $caret = "<span class=\"smpi-toc-caret\" aria-hidden=\"true\"></span>";
        if ( "toc04" === $style ) {
            $label = $title_supplied ? $title : "Jump to";
            $html = "<details class=\"" . esc_attr( $class ) . "\"" . $skin_attribute . $responsive_attribute . $markers_attribute . $open_attribute . "><summary class=\"smpi-template-title smpi-toc-label\"><span class=\"smpi-template-text smpi-toc-label-text\">" . esc_html( $label ) . "</span>" . $caret . "</summary><div class=\"smpi-template-content smpi-template-list smpi-toc-panel smpi-toc-list\">";
            foreach ( $items as $index => $item ) {
                $ordinal = "leading-zero" === $markers ? "<span class=\"smpi-toc-ordinal\" aria-hidden=\"true\">" . esc_html( sprintf( "%02d", $index + 1 ) ) . "</span>" : "";
                $html .= "<a class=\"smpi-template-item smpi-template-link smpi-toc-item smpi-toc-link\" href=#" . esc_attr( $item["id"] ) . ">" . $ordinal . esc_html( $item["text"] ) . "</a>";
            }
            return $html . "</div></details>";
        }
        $label = $title_supplied ? $title : ( "toc00" === $style ? "In this article" : ( in_array( $style, [ "unstyled", "toc02" ], true ) ? "On this page" : $title ) );
        $html = "<details class=\"" . esc_attr( $class ) . "\"" . $skin_attribute . $responsive_attribute . $markers_attribute . $open_attribute . "><summary class=\"smpi-template-title smpi-toc-label\"><span class=\"smpi-template-text smpi-toc-label-text\">" . esc_html( $label ) . "</span>" . $caret . "</summary><ol class=\"smpi-template-content smpi-template-list smpi-toc-list\">";
        foreach ( $items as $index => $item ) {
            $ordinal = "leading-zero" === $markers ? "<span class=\"smpi-toc-ordinal\" aria-hidden=\"true\">" . esc_html( sprintf( "%02d", $index + 1 ) ) . "</span>" : "";
            $html .= "<li class=\"smpi-template-item smpi-toc-item smpi-toc-level-" . esc_attr( (string) $item["level"] ) . "\">" . $ordinal . "<a class=\"smpi-template-link smpi-toc-link\" href=#" . esc_attr( $item["id"] ) . ">" . esc_html( $item["text"] ) . "</a></li>";
        }
        return $html . "</ol></details>";
    }

    private static function filter_items_by_levels( array $items, string $levels ): array {
        $requested = preg_split( "/[\\s,|]+/", strtolower( $levels ), -1, PREG_SPLIT_NO_EMPTY );
        $requested = is_array( $requested ) ? array_values( array_intersect( [ "h2", "h3", "h4" ], $requested ) ) : [];
        if ( empty( $requested ) ) {
            $requested = [ "h2", "h3", "h4" ];
        }
        $levels_by_number = array_map(
            static fn( string $level ): int => (int) substr( $level, 1 ),
            $requested
        );
        return array_values(
            array_filter(
                $items,
                static fn( array $item ): bool => in_array( (int) ( $item["level"] ?? 0 ), $levels_by_number, true )
            )
        );
    }

    private static function normalize_open_mode( string $open ): string {
        $open = sanitize_key( $open );
        return in_array( $open, [ "always", "desktop" ], true ) ? $open : "";
    }

    private static function responsive_open_script( string $open_mode ): string {
        if ( "desktop" !== $open_mode ) {
            return "";
        }
        return '<script class="smpi-toc-responsive-open-script" data-no-optimize="1">(function(script){var details=script&&script.previousElementSibling;if(!details||!details.matches("details[data-smpi-open=desktop]"))return;var media=window.matchMedia("(min-width:1025px)");function sync(){if(media.matches){details.setAttribute("open","");}else{details.removeAttribute("open");}}sync();if(media.addEventListener){media.addEventListener("change",sync);}else if(media.addListener){media.addListener(sync);}})(document.currentScript);</script>';
    }

    private static function items( string $content ): array {
        if ( ! preg_match_all( "/<h([2-4])([^>]*)>(.*?)<\\/h\\1>/is", $content, $matches, PREG_SET_ORDER ) ) {
            return [];
        }
        $items = [];
        foreach ( $matches as $index => $match ) {
            $text = trim( wp_strip_all_tags( $match[3] ) );
            if ( "" === $text ) {
                continue;
            }
            $id = "";
            if ( preg_match( "/\\sid=\"?([^\"\\s>]+)/i", (string) $match[2], $id_match ) ) {
                $id = sanitize_title( (string) $id_match[1] );
            }
            if ( "" === $id ) {
                $id = "smpi-toc-" . sanitize_title( $text ) . "-" . ( $index + 1 );
            }
            $items[] = [ "level" => (int) $match[1], "text" => $text, "id" => $id, "source" => "heading" ];
        }
        return $items;
    }

    private static function add_heading_ids( string $content, array $items ): string {
        $ids = array_map(
            static function ( array $item ): string {
                return (string) ( $item["id"] ?? "" );
            },
            $items
        );
        return TemplateMarkup::decorate_article_content(
            $content,
            $ids,
            Settings::bool( "article_heading_styles_enabled" ),
            Settings::bool( "article_drop_cap_enabled" )
        );
    }

    private static function shortcode_anchor_script( array $items ): string {
        $headings = [];
        $summary_id = "";
        foreach ( $items as $item ) {
            if ( "summary" === (string) ( $item["source"] ?? "heading" ) ) {
                $summary_id = (string) $item["id"];
                continue;
            }
            $headings[] = [ "id" => (string) $item["id"], "text" => (string) $item["text"] ];
        }
        $payload = wp_json_encode( [ "headings" => $headings, "summary_id" => $summary_id ] );
        if ( ! is_string( $payload ) || "" === $payload ) {
            return "";
        }
        return "<script class=\"smpi-toc-shortcode-anchor-script\" data-no-optimize=\"1\">(function(data){if(!data)return;function ready(fn){if(document.readyState!==\"loading\"){fn();return;}document.addEventListener(\"DOMContentLoaded\",fn,{once:true});}function visible(el){var r=el.getBoundingClientRect();return r.width>1&&r.height>1&&window.getComputedStyle(el).display!==\"none\";}function norm(text){return String(text||\"\").replace(/\\s+/g,\" \").trim().toLowerCase();}ready(function(){if(data.summary_id){var sum=document.querySelector(\".smpi-post-summary-title\");if(sum&&visible(sum)){sum.id=data.summary_id;}}var selectors=[\".elementor-widget-theme-post-content .elementor-widget-container\",\".elementor-widget-theme-post-content\",\".elementor-widget-post-content\",\"article .entry-content\",\".entry-content\",\".post-content\"];var target=null;for(var i=0;i<selectors.length;i++){target=document.querySelector(selectors[i]);if(target)break;}if(!target||!data.headings||!data.headings.length)return;var nodes=Array.from(target.querySelectorAll(\"h2,h3,h4\")).filter(function(h){return visible(h)&&norm(h.textContent);});var cursor=0;data.headings.forEach(function(heading){if(!heading||!heading.id||document.getElementById(heading.id))return;var wanted=norm(heading.text);for(var k=cursor;k<nodes.length;k++){if(norm(nodes[k].textContent)===wanted){nodes[k].id=heading.id;cursor=k+1;return;}}});});})(" . $payload . ");</script>";
    }

    public function print_auto_inject_script(): void {
        if ( ! RuntimeContext::is_public_dom_context() || ! Settings::bool( "table_of_contents_enabled" ) || ! Settings::bool( "table_of_contents_auto_single" ) || ! is_singular( "post" ) ) {
            return;
        }
        $style = ArticleStyles::normalize_toc_style( (string) Settings::get( "table_of_contents_style", "toc02" ) );
        if ( "none" === $style ) {
            return;
        }
        $payload = wp_json_encode( [ "style" => $style, "include_summary" => Settings::bool( "table_of_contents_include_summary" ) ] );
        $script = <<<SMPI_JS
(function(data){if(!data||document.querySelector(".smpi-table-of-contents"))return;function visible(el){var r=el.getBoundingClientRect();return r.width>1&&r.height>1&&window.getComputedStyle(el).display!=="none";}function slug(text,index){return "smpi-toc-"+text.toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-|-$/g,"")+"-"+index;}var selectors=[".elementor-widget-theme-post-content .elementor-widget-container",".elementor-widget-theme-post-content",".elementor-widget-post-content","article .entry-content",".entry-content",".post-content"];var target=null;for(var i=0;i<selectors.length;i++){target=document.querySelector(selectors[i]);if(target)break;}if(!target)return;var headings=Array.from(target.querySelectorAll("h2,h3,h4")).filter(function(h){return visible(h)&&(h.textContent||"").trim();});if(data.include_summary){var sumH=document.querySelector(".smpi-post-summary-title");if(sumH&&visible(sumH)&&(sumH.textContent||"").trim()){if(!sumH.id)sumH.id="smpi-summary-heading";headings.unshift(sumH);}}if(!headings.length)return;var details=document.createElement("details");details.className="smpi-template smpi-template--toc smpi-table-of-contents smpi-"+(data.style||"toc02")+" smpi-toc-collapsible";details.setAttribute("aria-label","Table of contents");details.setAttribute("data-smpi-skin",data.style==="unstyled"?"unstyled":"styled");var summary=document.createElement("summary");summary.className="smpi-template-title smpi-toc-label";var labelText=data.style==="toc04"?"Jump to":(data.style==="toc00"?"In this article":((data.style==="unstyled"||data.style==="toc02")?"On this page":"Table of Contents"));var lspan=document.createElement("span");lspan.className="smpi-template-text smpi-toc-label-text";lspan.textContent=labelText;summary.appendChild(lspan);var caret=document.createElement("span");caret.className="smpi-toc-caret";caret.setAttribute("aria-hidden","true");summary.appendChild(caret);details.appendChild(summary);if(data.style==="toc04"){var panel=document.createElement("div");panel.className="smpi-template-content smpi-template-list smpi-toc-panel smpi-toc-list";headings.forEach(function(h,idx){if(!h.id)h.id=slug(h.textContent,idx+1);var a=document.createElement("a");a.className="smpi-template-item smpi-template-link smpi-toc-item smpi-toc-link";a.href="#"+h.id;a.textContent=h.textContent.trim();panel.appendChild(a);});details.appendChild(panel);}else{var ol=document.createElement("ol");ol.className="smpi-template-content smpi-template-list smpi-toc-list";headings.forEach(function(h,idx){if(!h.id)h.id=slug(h.textContent,idx+1);var li=document.createElement("li");li.className="smpi-template-item smpi-toc-item smpi-toc-level-"+h.tagName.slice(1);var a=document.createElement("a");a.className="smpi-template-link smpi-toc-link";a.href="#"+h.id;a.textContent=h.textContent.trim();li.appendChild(a);ol.appendChild(li);});details.appendChild(ol);}target.parentNode.insertBefore(details,target);})(
SMPI_JS;
        echo "<script id=\"smpi-toc-auto-inject\" data-no-optimize=\"1\">" . $script . $payload . ");</script>";
    }
    /**
     * Marks the contents item for the section being read and moves the mark as
     * the reader scrolls: the link gets aria-current="location" and
     * smpi-toc-link--active, its item smpi-toc-item--active, and the list
     * smpi-toc-has-active. Works for the shortcode and the automatic list.
     */
    public function print_scroll_tracking_script(): void {
        if ( ! RuntimeContext::is_public_dom_context() || ! Settings::bool( "table_of_contents_enabled" ) || ! is_singular() ) {
            return;
        }
        $script = <<<SMPI_JS
(function(){function init(){if(!document.querySelector(".smpi-table-of-contents"))return;var queued=false;function target(link){var id=(link.getAttribute("href")||"").slice(1);if(!id)return null;try{id=decodeURIComponent(id);}catch(e){}return document.getElementById(id);}function update(){queued=false;var line=Math.min(window.innerHeight*0.3,200);var atEnd=window.innerHeight+window.pageYOffset>=document.documentElement.scrollHeight-2;document.querySelectorAll(".smpi-table-of-contents").forEach(function(toc){var links=Array.from(toc.querySelectorAll(".smpi-toc-link[href^='#']"));var current=null;links.forEach(function(link){var t=target(link);if(!t)return;var top=t.getBoundingClientRect().top;if(top-line<=0||(atEnd&&top<window.innerHeight)){current=link;}});links.forEach(function(link){var on=link===current;link.classList.toggle("smpi-toc-link--active",on);var item=link.closest(".smpi-toc-item");if(item&&item!==link){item.classList.toggle("smpi-toc-item--active",on);}if(on){link.setAttribute("aria-current","location");}else{link.removeAttribute("aria-current");}});toc.classList.toggle("smpi-toc-has-active",!!current);});}function queue(){if(!queued){queued=true;window.requestAnimationFrame(update);}}window.addEventListener("scroll",queue,{passive:true});window.addEventListener("resize",queue);window.addEventListener("hashchange",queue);window.addEventListener("load",queue);queue();}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",init,{once:true});}else{init();}})();
SMPI_JS;
        echo "<script id=\"smpi-toc-scroll-tracking\" data-no-optimize=\"1\">" . $script . "</script>";
    }
    public static function integrity_report(): array {
        $post = get_posts( [ "post_type" => "post", "post_status" => "publish", "posts_per_page" => 1 ] );
        $post = $post[0] ?? null;
        $items = $post instanceof \WP_Post ? self::items( (string) $post->post_content ) : [];
        return [ "enabled" => Settings::bool( "table_of_contents_enabled" ), "auto_single" => Settings::bool( "table_of_contents_auto_single" ), "style" => (string) Settings::get( "table_of_contents_style", "toc02" ), "shortcode" => "[smp_table_of_contents]", "sample_post_id" => $post instanceof \WP_Post ? (int) $post->ID : 0, "heading_count" => count( $items ) ];
    }
}
