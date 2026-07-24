<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class PostFaqPlacement {
    public const SETTING = "post_faqs_placement";
    public const MANUAL = "manual";
    public const BELOW_CONTENT = "below_content";
    public const BELOW_AUTHOR = "below_author";

    private Shortcodes $shortcodes;

    public function __construct( ?Shortcodes $shortcodes = null ) {
        $this->shortcodes = $shortcodes ?? new Shortcodes();
    }

    public function register(): void {
        add_filter( "the_content", [ $this, "append_to_article_content" ], 42 );
        add_action( "wp_footer", [ $this, "print_placement_script" ], 49 );
    }

    public static function normalize( string $placement ): string {
        return in_array( $placement, [ self::MANUAL, self::BELOW_CONTENT, self::BELOW_AUTHOR ], true )
            ? $placement
            : self::MANUAL;
    }

    public function append_to_article_content( string $content ): string {
        if ( self::BELOW_CONTENT !== $this->current_placement() || ! $this->is_supported_article() ) {
            return $content;
        }

        $post_id = $this->queried_post_id();
        if ( $post_id <= 0 || ( function_exists( "get_the_ID" ) && (int) get_the_ID() !== $post_id ) ) {
            return $content;
        }
        if ( function_exists( "has_shortcode" ) && has_shortcode( $content, "smp_post_faqs" ) ) {
            return $content;
        }

        static $appended = [];
        if ( isset( $appended[ $post_id ] ) ) {
            return $content;
        }

        $html = $this->render_faq( $post_id );
        if ( "" === $html ) {
            return $content;
        }

        $appended[ $post_id ] = true;
        return $content . $html;
    }

    public function print_placement_script(): void {
        $placement = $this->current_placement();
        if ( self::MANUAL === $placement || ! $this->is_supported_article() ) {
            return;
        }

        $post_id = $this->queried_post_id();
        $html = $this->render_faq( $post_id );
        if ( $post_id <= 0 || "" === $html ) {
            return;
        }

        $post = get_post( $post_id );
        $author_name = $post instanceof \WP_Post ? (string) get_the_author_meta( "display_name", (int) $post->post_author ) : "";
        $payload = wp_json_encode(
            [
                "placement" => $placement,
                "html" => $html,
                "authorName" => $author_name,
            ]
        );
        if ( ! is_string( $payload ) || "" === $payload ) {
            return;
        }

        $script = <<<'SMPI_JS'
(function(data){
if(!data||!data.html||!data.placement)return;
function q(selector,root){try{return Array.prototype.slice.call((root||document).querySelectorAll(selector));}catch(e){return[];}}
function clean(value){return String(value||"").replace(/\s+/g," ").trim();}
function norm(value){return clean(value).toLowerCase().replace(/[^a-z0-9]+/g,"");}
function visible(element){if(!element)return false;var rect=element.getBoundingClientRect(),style=window.getComputedStyle(element);return rect.width>1&&rect.height>1&&style.display!=="none"&&style.visibility!=="hidden";}
function y(element){var rect=element.getBoundingClientRect();return rect.top+window.scrollY;}
function isLoop(element){return !!(element&&element.closest(".e-loop-item,.elementor-loop-item,.elementor-post,.elementor-grid-item,.elementor-widget-loop-grid article,.elementor-posts-container article"));}
function contentWidget(){var selectors=[".elementor-widget-theme-post-content",".elementor-widget-post-content","article .entry-content",".entry-content",".post-content"];for(var i=0;i<selectors.length;i++){var element=document.querySelector(selectors[i]);if(element&&visible(element))return element;}return null;}
function contentPlacementRoot(){var content=contentWidget();if(!content)return document.querySelector("article")||null;return content.closest(".elementor-widget-theme-post-content,.elementor-widget-post-content")||content;}
function authorCardContainers(){var content=contentPlacementRoot(),floor=content?y(content)+content.getBoundingClientRect().height-2:0,want=norm(data.authorName),found=[];q(".elementor-author-box,.elementor-widget-theme-post-author,.elementor-widget-author-box,[class*='about-author'],[class*='author-box']").forEach(function(element){var root=element.closest(".elementor-widget-theme-post-author,.elementor-widget-author-box,.elementor-element")||element;if(visible(root)&&!isLoop(root)&&y(root)>=floor&&found.indexOf(root)<0)found.push(root);});q(".e-con,.elementor-section,.elementor-container,.elementor-element").forEach(function(element){if(!visible(element)||isLoop(element)||y(element)<floor)return;var rect=element.getBoundingClientRect();if(rect.height<40||rect.height>900||rect.width<120)return;var text=clean(element.textContent),normalized=norm(text),lower=text.toLowerCase();if(want&&normalized.indexOf(want)===-1)return;var hasAuthorLink=!!element.querySelector("a[href*='/author/'],a[rel='author']"),hasAbout=lower.indexOf("about the author")!==-1,hasSocial=/twitter\s*\/\s*x|linkedin|instagram|email/.test(lower),hasImage=!!element.querySelector("img,.elementor-widget-image");if((hasAuthorLink||hasAbout)&&(hasImage||hasSocial||hasAbout)&&found.indexOf(element)<0)found.push(element);});return found.sort(function(a,b){var ah=a.getBoundingClientRect().height,bh=b.getBoundingClientRect().height;return ah===bh?y(a)-y(b):ah-bh;});}
function placementTarget(){if(data.placement==="below_content")return contentPlacementRoot();var authors=authorCardContainers();return authors.length?authors[0]:null;}
function nodeFromHtml(){var template=document.createElement("template");template.innerHTML=String(data.html).trim();return template.content.querySelector(".smpi-post-faqs");}
function run(){var target=placementTarget();if(!target)return false;var nodes=q(".smpi-post-faqs"),node=nodes.shift()||nodeFromHtml();if(!node)return false;nodes.forEach(function(extra){extra.remove();});node.setAttribute("data-smpi-faq-placement",data.placement);node.classList.remove("smpi-faq-placement--manual","smpi-faq-placement--below-content","smpi-faq-placement--below-author");node.classList.add("smpi-faq-placement--"+data.placement.replace(/_/g,"-"));if(target.nextElementSibling!==node)target.insertAdjacentElement("afterend",node);return true;}
if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",run,{once:true});}else{run();}
setTimeout(run,400);setTimeout(run,1100);setTimeout(run,2400);
})(
SMPI_JS;
        echo "<script id=\"smpi-post-faq-placement\">" . $script . $payload . ");</script>";
    }

    private function current_placement(): string {
        return self::normalize( (string) Settings::get( self::SETTING, self::MANUAL ) );
    }

    private function is_supported_article(): bool {
        return RuntimeContext::is_public_dom_context()
            && Settings::bool( "post_faqs_acf_enabled" )
            && is_singular( [ "post", "press-release", "imported-news" ] );
    }

    private function queried_post_id(): int {
        return function_exists( "get_queried_object_id" ) ? (int) get_queried_object_id() : 0;
    }

    private function render_faq( int $post_id ): string {
        if ( $post_id <= 0 ) {
            return "";
        }
        return trim( $this->shortcodes->render_post_faqs( [ "post_id" => $post_id ] ) );
    }
}
