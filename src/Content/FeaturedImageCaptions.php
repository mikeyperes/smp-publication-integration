<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class FeaturedImageCaptions {
    private const POST_TYPES = [ "post", "press-release" ];

    public function register(): void {
        add_filter( "post_thumbnail_html", [ $this, "filter_post_thumbnail_html" ], 20, 5 );
        add_action( "wp_footer", [ $this, "print_fallback_script" ], 45 );
    }

    public function filter_post_thumbnail_html( string $html, int $post_id, int $post_thumbnail_id, $size, $attr ): string {
        if ( "" === trim( $html ) || ! $this->should_apply( $post_id ) || $post_thumbnail_id <= 0 ) {
            return $html;
        }
        if ( false !== strpos( $html, "smpi-featured-image-caption" ) ) {
            return $html;
        }
        $caption = $this->caption_for_attachment( $post_thumbnail_id );
        if ( "" === $caption ) {
            return $html;
        }
        $style = ArticleStyles::normalize_featured_image_caption_style( (string) Settings::get( "featured_image_caption_template", "fig2" ) );
        if ( "none" === $style ) {
            return $html;
        }
        $classes = TemplateMarkup::root_classes( "featured-image-caption", [ "smpi-featured-image-caption", "smpi-featured-image-caption--" . $style ] );
        $media = TemplateMarkup::decorate_featured_media( $html );
        $caption = TemplateMarkup::decorate_rich_text( wp_kses_post( $caption ), "featured-image-caption" );
        return "<figure class=\"" . esc_attr( $classes ) . "\" data-smpi-featured-image-caption=\"server\">" . $media . "<figcaption class=\"smpi-template-caption smpi-featured-image-caption-text\">" . $caption . "</figcaption></figure>";
    }

    public function print_fallback_script(): void {
        $post_id = (int) get_queried_object_id();
        if ( ! $this->should_apply( $post_id ) ) {
            return;
        }
        $thumb_id = (int) get_post_thumbnail_id( $post_id );
        if ( $thumb_id <= 0 ) {
            return;
        }
        $caption = $this->caption_for_attachment( $thumb_id );
        if ( "" === $caption ) {
            return;
        }
        $style = ArticleStyles::normalize_featured_image_caption_style( (string) Settings::get( "featured_image_caption_template", "fig2" ) );
        if ( "none" === $style ) {
            return;
        }
        $payload = [ "caption" => wp_strip_all_tags( $caption ), "style" => $style ];
        ?>
        <script id="smpi-featured-image-caption-fallback">
        (function(){var cfg=<?php echo wp_json_encode( $payload ); ?>;if(!cfg||!cfg.caption){return;}function ready(fn){if(document.readyState!=="loading"){fn();return;}document.addEventListener("DOMContentLoaded",fn,{once:true});}ready(function(){if(document.querySelector(".smpi-featured-image-caption")){return;}var roots=[].slice.call(document.querySelectorAll(".elementor-widget-theme-post-featured-image,.post-thumbnail,.wp-post-image"));for(var i=0;i<roots.length;i++){var img=roots[i].matches&&roots[i].matches("img")?roots[i]:roots[i].querySelector("img");if(!img||img.closest(".elementor-widget-theme-post-content,.entry-content,.smpi-featured-image-caption")){continue;}var host=img.closest(".elementor-widget-theme-post-featured-image")||img.closest("figure")||img.parentElement;if(!host||host.querySelector("figcaption,.smpi-featured-image-caption-text")){continue;}host.classList.add("smpi-template","smpi-template--featured-image-caption","smpi-featured-image-caption","smpi-featured-image-caption--"+cfg.style);host.setAttribute("data-smpi-featured-image-caption","fallback");img.classList.add("smpi-template-image","smpi-featured-image-caption-image");var link=img.closest("a");if(link){link.classList.add("smpi-template-link","smpi-featured-image-caption-link");}var cap=document.createElement(host.tagName&&host.tagName.toLowerCase()==="figure"?"figcaption":"div");cap.className="smpi-template-caption smpi-featured-image-caption-text";cap.textContent=cfg.caption;host.appendChild(cap);break;}});})();
        </script>
        <?php
    }

    private function should_apply( int $post_id ): bool {
        if ( ! RuntimeContext::is_public_dom_context() || ! Settings::bool( "featured_image_caption_templates_enabled" ) || $post_id <= 0 ) {
            return false;
        }
        $post_type = get_post_type( $post_id );
        if ( ! in_array( $post_type, self::POST_TYPES, true ) ) {
            return false;
        }
        return is_singular( self::POST_TYPES );
    }

    private function caption_for_attachment( int $attachment_id ): string {
        $caption = wp_get_attachment_caption( $attachment_id );
        return is_string( $caption ) ? trim( $caption ) : "";
    }
}
