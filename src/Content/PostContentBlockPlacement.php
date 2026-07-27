<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/** Shared placement engine for shortcode-backed article blocks. */
abstract class PostContentBlockPlacement {
    public const MANUAL = "manual";
    public const ABOVE_CONTENT = "above_content";
    public const BELOW_CONTENT = "below_content";
    public const BELOW_AUTHOR = "below_author";

    protected Shortcodes $shortcodes;

    public function __construct( ?Shortcodes $shortcodes = null ) {
        $this->shortcodes = $shortcodes ?? new Shortcodes();
    }

    public function register(): void {
        add_filter( "the_content", [ $this, "place_in_article_content" ], 42 );
        add_action( "wp_footer", [ $this, "print_placement_script" ], 49 );
    }

    public function place_in_article_content( string $content ): string {
        $placement = $this->current_placement();
        if ( ! in_array( $placement, [ self::ABOVE_CONTENT, self::BELOW_CONTENT ], true ) || ! $this->is_supported_article() ) {
            return $content;
        }

        $post_id = $this->queried_post_id();
        if ( $post_id <= 0 || ( function_exists( "get_the_ID" ) && (int) get_the_ID() !== $post_id ) || $this->contains_manual_shortcode( $content ) ) {
            return $content;
        }

        static $placed = [];
        $placement_key = static::class . ":" . $post_id;
        if ( isset( $placed[ $placement_key ] ) ) {
            return $content;
        }

        $html = $this->render_block( $post_id );
        if ( "" === $html ) {
            return $content;
        }

        $placed[ $placement_key ] = true;
        return self::ABOVE_CONTENT === $placement ? $html . $content : $content . $html;
    }

    public function print_placement_script(): void {
        $placement = $this->current_placement();
        if ( self::MANUAL === $placement || ! $this->is_supported_article() ) {
            return;
        }

        $post_id = $this->queried_post_id();
        $html = $this->render_block( $post_id );
        if ( $post_id <= 0 || "" === $html ) {
            return;
        }

        $post = get_post( $post_id );
        $payload = wp_json_encode(
            [
                "placement" => $placement,
                "html" => $html,
                "selector" => $this->block_selector(),
                "attribute" => $this->placement_attribute(),
                "classPrefix" => $this->placement_class_prefix(),
                "authorName" => $post instanceof \WP_Post ? (string) get_the_author_meta( "display_name", (int) $post->post_author ) : "",
            ]
        );
        if ( ! is_string( $payload ) || "" === $payload ) {
            return;
        }

        echo "<script id=\"" . esc_attr( $this->script_id() ) . "\">" . self::placement_script() . $payload . ");</script>";
    }

    protected static function normalize_placement( string $placement, array $allowed ): string {
        return in_array( $placement, $allowed, true ) ? $placement : self::MANUAL;
    }

    abstract protected function setting_key(): string;

    abstract protected function enabled_key(): string;

    abstract protected function shortcode_tag(): string;

    abstract protected function acf_field(): string;

    abstract protected function block_selector(): string;

    abstract protected function placement_attribute(): string;

    abstract protected function placement_class_prefix(): string;

    abstract protected function script_id(): string;

    abstract protected function allowed_placements(): array;

    abstract protected function render_block( int $post_id ): string;

    private function current_placement(): string {
        return self::normalize_placement( (string) Settings::get( $this->setting_key(), self::MANUAL ), $this->allowed_placements() );
    }

    private function contains_manual_shortcode( string $content ): bool {
        if ( function_exists( "has_shortcode" ) && has_shortcode( $content, $this->shortcode_tag() ) ) {
            return true;
        }
        if ( false === stripos( $content, "smp_post_acf" ) ) {
            return false;
        }
        return 1 === preg_match( "/field\\s*=\\s*[\"']?" . preg_quote( $this->acf_field(), "/" ) . "[\"']?/i", $content );
    }

    private function is_supported_article(): bool {
        return RuntimeContext::is_public_dom_context()
            && Settings::bool( $this->enabled_key() )
            && is_singular( PublicationContentTypes::active_article_post_types() );
    }

    private function queried_post_id(): int {
        return function_exists( "get_queried_object_id" ) ? (int) get_queried_object_id() : 0;
    }

    private static function placement_script(): string {
        return <<<'SMPI_JS'
(function(data){
if(!data||!data.html||!data.placement||!data.selector)return;
function q(selector,root){try{return Array.prototype.slice.call((root||document).querySelectorAll(selector));}catch(e){return[];}}
function clean(value){return String(value||"").replace(/\s+/g," ").trim();}
function norm(value){return clean(value).toLowerCase().replace(/[^a-z0-9]+/g,"");}
function visible(element){if(!element)return false;var rect=element.getBoundingClientRect(),style=window.getComputedStyle(element);return rect.width>1&&rect.height>1&&style.display!=="none"&&style.visibility!=="hidden";}
function y(element){var rect=element.getBoundingClientRect();return rect.top+window.scrollY;}
function isLoop(element){return !!(element&&element.closest(".e-loop-item,.elementor-loop-item,.elementor-post,.elementor-grid-item,.elementor-widget-loop-grid article,.elementor-posts-container article"));}
function contentWidget(){var selectors=[".elementor-widget-theme-post-content",".elementor-widget-post-content","article .entry-content",".entry-content",".post-content"];for(var i=0;i<selectors.length;i++){var element=document.querySelector(selectors[i]);if(element&&visible(element))return element;}return null;}
function contentPlacementRoot(){var content=contentWidget();if(!content)return document.querySelector("article")||null;return content.closest(".elementor-widget-theme-post-content,.elementor-widget-post-content")||content;}
function authorCardContainers(){var content=contentPlacementRoot(),floor=content?y(content)+content.getBoundingClientRect().height-2:0,want=norm(data.authorName),found=[];q(".elementor-author-box,.elementor-widget-theme-post-author,.elementor-widget-author-box,[class*='about-author'],[class*='author-box']").forEach(function(element){var root=element.closest(".elementor-widget-theme-post-author,.elementor-widget-author-box,.elementor-element")||element;if(visible(root)&&!isLoop(root)&&y(root)>=floor&&found.indexOf(root)<0)found.push(root);});q(".e-con,.elementor-section,.elementor-container,.elementor-element").forEach(function(element){if(!visible(element)||isLoop(element)||y(element)<floor)return;var rect=element.getBoundingClientRect();if(rect.height<40||rect.height>900||rect.width<120)return;var text=clean(element.textContent),normalized=norm(text),lower=text.toLowerCase();if(want&&normalized.indexOf(want)===-1)return;var hasAuthorLink=!!element.querySelector("a[href*='/author/'],a[rel='author']"),hasAbout=lower.indexOf("about the author")!==-1,hasSocial=/twitter\s*\/\s*x|linkedin|instagram|email/.test(lower),hasImage=!!element.querySelector("img,.elementor-widget-image");if((hasAuthorLink||hasAbout)&&(hasImage||hasSocial||hasAbout)&&found.indexOf(element)<0)found.push(element);});return found.sort(function(a,b){var ah=a.getBoundingClientRect().height,bh=b.getBoundingClientRect().height;return ah===bh?y(a)-y(b):ah-bh;});}
function placementTarget(){var content=contentPlacementRoot();if(data.placement==="above_content")return content?{element:content,position:"beforebegin"}:null;if(data.placement==="below_content")return content?{element:content,position:"afterend"}:null;var authors=authorCardContainers();return authors.length?{element:authors[0],position:"afterend"}:null;}
function nodeFromHtml(){var template=document.createElement("template");template.innerHTML=String(data.html).trim();return template.content.querySelector(data.selector);}
function run(){var destination=placementTarget();if(!destination||!destination.element)return false;var nodes=q(data.selector),node=nodes.shift()||nodeFromHtml();if(!node)return false;nodes.forEach(function(extra){extra.remove();});if(data.attribute)node.setAttribute(data.attribute,data.placement);Array.from(node.classList).forEach(function(name){if(data.classPrefix&&name.indexOf(data.classPrefix)===0)node.classList.remove(name);});if(data.classPrefix)node.classList.add(data.classPrefix+data.placement.replace(/_/g,"-"));var sibling=destination.position==="beforebegin"?destination.element.previousElementSibling:destination.element.nextElementSibling;if(sibling!==node)destination.element.insertAdjacentElement(destination.position,node);return true;}
if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",run,{once:true});}else{run();}
setTimeout(run,400);setTimeout(run,1100);setTimeout(run,2400);
})(
SMPI_JS;
    }
}
