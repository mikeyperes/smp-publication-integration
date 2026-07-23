<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Single source of truth for article design CSS.
 *
 * The exact same CSS strings are used by the front end (wp_head) and by the
 * admin "Features" design previews. Front-end output and the admin samples
 * therefore always match. Configurable values (accent, text color, font size,
 * font style) are driven by CSS custom properties so they can update live in
 * the admin and apply on the front end.
 */
final class ArticleStyles {
    public function register(): void {
        add_filter( "the_content", [ $this, "decorate_article_content" ], 9 );
        add_action( "wp_head", [ $this, "print_styles" ], 34 );
        add_action( "wp_footer", [ $this, "print_markup_fallback_script" ], 48 );
    }

    public function print_styles(): void {
        if ( ! RuntimeContext::is_public_dom_context() ) {
            return;
        }
        $needs = Settings::bool( "breadcrumbs_enabled" ) || Settings::bool( "article_heading_styles_enabled" ) || Settings::bool( "article_drop_cap_enabled" ) || Settings::bool( "inline_photo_treatments_enabled" ) || Settings::bool( "featured_image_caption_templates_enabled" ) || Settings::bool( "post_summary_acf_enabled" ) || Settings::bool( "post_faqs_acf_enabled" ) || Settings::bool( "table_of_contents_enabled" );
        if ( ! $needs ) {
            return;
        }
        $heading = self::normalize_article_heading_style( (string) Settings::get( "article_heading_style", "h2-tick" ) );
        $photo = self::normalize_inline_photo_style( (string) Settings::get( "inline_photo_treatment", "none" ) );
        $featured = self::normalize_featured_image_caption_style( (string) Settings::get( "featured_image_caption_template", "fig2" ) );
        $breadcrumb_override = Settings::bool( "breadcrumbs_enabled" ) ? Breadcrumbs::custom_css() : "";
        if ( Settings::bool( "article_drop_cap_enabled" ) && self::article_drop_cap_style_uses_script_font( self::normalize_article_drop_cap_style( (string) Settings::get( "article_drop_cap_style", "dropcap-classic" ) ) ) ) {
            echo self::script_font_link_html();
        }
        echo "<style id=smpi-article-style-controls>" . self::frontend_vars_css() . self::breadcrumbs_css() . self::toc_css() . self::article_heading_css( $heading ) . self::article_drop_cap_css() . self::post_acf_css() . self::inline_photo_css( $photo ) . self::featured_image_caption_css( $featured ) . ( "" !== $breadcrumb_override ? PHP_EOL . $breadcrumb_override : "" ) . "</style>";
    }

    public function decorate_article_content( string $content ): string {
        if ( ! RuntimeContext::is_public_dom_context() || ! is_singular( [ "post", "press-release" ] ) ) {
            return $content;
        }
        if ( ! $this->article_markup_enabled() ) {
            return $content;
        }
        return TemplateMarkup::decorate_article_content( $content );
    }

    public function print_markup_fallback_script(): void {
        if ( ! RuntimeContext::is_public_dom_context() || ! is_singular( [ "post", "press-release" ] ) || ! $this->article_markup_enabled() ) {
            return;
        }
        $payload = wp_json_encode(
            [
                "headings" => Settings::bool( "article_heading_styles_enabled" ),
                "dropcap"  => Settings::bool( "article_drop_cap_enabled" ),
            ]
        );
        ?>
        <script id="smpi-article-markup-normalizer">
        (function(cfg){if(!cfg)return;var selectors=[".elementor-widget-theme-post-content .elementor-widget-container",".elementor-widget-theme-post-content",".elementor-widget-post-content .elementor-widget-container",".elementor-widget-post-content","article .entry-content",".entry-content",".post-content"];var root=null;for(var i=0;i<selectors.length;i++){root=document.querySelector(selectors[i]);if(root)break;}if(!root)return;root.classList.add("smpi-template","smpi-template--article-content","smpi-template-content","smpi-article-content");function owned(el){return !el.closest(".smpi-post-summary,.smpi-post-faqs,.smpi-table-of-contents,.smpi-breadcrumbs");}root.querySelectorAll("a").forEach(function(el){if(owned(el))el.classList.add("smpi-template-link","smpi-article-link");});root.querySelectorAll("ol,ul").forEach(function(el){if(owned(el))el.classList.add("smpi-template-list","smpi-article-list");});root.querySelectorAll("li").forEach(function(el){if(owned(el))el.classList.add("smpi-template-item","smpi-article-list-item");});root.querySelectorAll("p").forEach(function(el){if(owned(el))el.classList.add("smpi-template-text","smpi-article-paragraph");});root.querySelectorAll("img").forEach(function(el){if(owned(el))el.classList.add("smpi-template-image","smpi-article-image");});if(cfg.headings){root.querySelectorAll("h2,h3,h4").forEach(function(el){if(owned(el))el.classList.add("smpi-template-title","smpi-article-heading","smpi-article-heading--"+el.tagName.toLowerCase());});}if(cfg.dropcap){var lead=Array.from(root.querySelectorAll("p")).find(function(el){return owned(el)&&!el.closest("aside,blockquote,figure,nav");});if(lead)lead.classList.add("smpi-article-lead");}})(<?php echo $payload ? $payload : "{}"; ?>);
        </script>
        <?php
    }

    private function article_markup_enabled(): bool {
        return Settings::bool( "article_heading_styles_enabled" )
            || Settings::bool( "article_drop_cap_enabled" )
            || Settings::bool( "inline_photo_treatments_enabled" )
            || Settings::bool( "table_of_contents_enabled" );
    }

    public static function normalize_toc_style( string $style = "" ): string {
        $style = sanitize_key( "" !== $style ? $style : (string) Settings::get( "table_of_contents_style", "toc02" ) );
        return in_array( $style, [ "none", "toc00", "toc01", "toc02", "toc03", "toc04" ], true ) ? $style : "toc02";
    }

    public static function normalize_breadcrumb_style( string $style = "" ): string {
        $style = sanitize_key( "" !== $style ? $style : (string) Settings::get( "breadcrumbs_style", "bc-b2" ) );
        return in_array( $style, [ "bc-b1", "bc-b2", "bc-b3", "bc-b4", "bc-b5", "bc-b6" ], true ) ? $style : "bc-b2";
    }

    public static function normalize_article_heading_style( string $style = "" ): string {
        $style = sanitize_key( "" !== $style ? $style : (string) Settings::get( "article_heading_style", "h2-tick" ) );
        return in_array( $style, self::article_heading_style_keys(), true ) ? $style : "h2-tick";
    }

    public static function article_heading_style_keys(): array {
        return [ "none", "h2-tick", "h2-leftrule", "h2-underline", "h2-topline", "h2-dot", "h2-trailingrule", "h2-serif", "h2-uppercase", "h2-gradient", "h2-bracket", "h2-number", "h2-square", "h2-highlight", "h2-double", "h2-corner_tick" ];
    }

    public static function normalize_article_drop_cap_style( string $style = "" ): string {
        $style = sanitize_key( "" !== $style ? $style : (string) Settings::get( "article_drop_cap_style", "dropcap-classic" ) );
        return in_array( $style, self::article_drop_cap_style_keys(), true ) ? $style : "dropcap-classic";
    }

    public static function article_drop_cap_style_uses_script_font( string $style ): bool {
        return 0 === strpos( $style, "dropcap-script-" );
    }

    public static function script_font_link_html(): string {
        return "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\"><link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin><link rel=\"stylesheet\" id=\"smpi-script-font\" href=\"https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&amp;display=swap\">";
    }

    public static function article_drop_cap_style_keys(): array {
        return [ "dropcap-classic", "dropcap-highlight", "dropcap-outline", "dropcap-side-rule", "dropcap-soft-tile", "dropcap-script-classic", "dropcap-script-tile", "dropcap-script-round", "dropcap-script-underline", "dropcap-script-shadow" ];
    }

    public static function normalize_inline_photo_style( string $style = "" ): string {
        $style = sanitize_key( "" !== $style ? $style : (string) Settings::get( "inline_photo_treatment", "none" ) );
        return in_array( $style, [ "none", "fig1", "fig2", "fig4", "fig5" ], true ) ? $style : "none";
    }

    public static function normalize_featured_image_caption_style( string $style = "" ): string {
        $style = sanitize_key( "" !== $style ? $style : (string) Settings::get( "featured_image_caption_template", "fig2" ) );
        return in_array( $style, [ "none", "fig1", "fig2", "fig4", "fig5" ], true ) ? $style : "fig2";
    }

    public static function normalize_summary_style( string $style = "" ): string {
        $style = sanitize_key( "" !== $style ? $style : (string) Settings::get( "post_summary_style", "none" ) );
        return in_array( $style, [ "none", "sum00", "sum01", "sum02", "sum03", "sum04" ], true ) ? $style : "none";
    }

    public static function normalize_faq_style( string $style = "" ): string {
        $style = sanitize_key( "" !== $style ? $style : (string) Settings::get( "post_faqs_style", "none" ) );
        return in_array( $style, [ "none", "faq00", "faq01", "faq02", "faq03", "faq04" ], true ) ? $style : "none";
    }

    public static function wrap_post_summary( string $html, string $style = "" ): string {
        if ( "" === trim( $html ) ) {
            return "";
        }
        $style = self::normalize_summary_style( $style );
        if ( "none" === $style ) {
            return $html;
        }
        $html = TemplateMarkup::decorate_rich_text( $html, "post-summary" );
        $classes = TemplateMarkup::root_classes( "article-summary", [ "smpi-post-summary", "smpi-" . $style ] );
        return "<aside class=\"" . esc_attr( $classes ) . "\"><h2 class=\"smpi-template-title smpi-post-summary-title\">" . esc_html( self::summary_title( $style ) ) . "</h2><div class=\"smpi-template-content smpi-post-summary-content\">" . wp_kses_post( $html ) . "</div></aside>";
    }

    public static function wrap_post_faqs( string $html, string $style = "" ): string {
        if ( "" === trim( $html ) ) {
            return "";
        }
        $style = self::normalize_faq_style( $style );
        if ( "none" === $style ) {
            return $html;
        }
        $html = TemplateMarkup::decorate_rich_text( $html, "post-faq" );
        $classes = TemplateMarkup::root_classes( "article-faqs", [ "smpi-post-faqs", "smpi-" . $style ] );
        return "<section class=\"" . esc_attr( $classes ) . "\"><h2 class=\"smpi-template-title smpi-post-faqs-title\">" . esc_html( self::faq_title( $style ) ) . "</h2><div class=\"smpi-template-content smpi-post-faqs-content\">" . wp_kses_post( $html ) . "</div></section>";
    }

    public static function summary_title( string $style ): string {
        return "sum03" === $style ? "The Brief" : ( in_array( $style, [ "sum01", "sum02", "sum04" ], true ) ? "What to know" : "Summary" );
    }

    public static function faq_title( string $style ): string {
        return "faq04" === $style ? "People also ask" : "Frequently asked questions";
    }

    /* ---------------------------------------------------------------------
     * Front-end CSS-variable values (read from settings). The admin previews
     * set the same variables inline so both render identically.
     * ------------------------------------------------------------------- */
    private static function frontend_vars_css(): string {
        $css = "";
        $bc = self::breadcrumb_var_values();
        $css .= ".smpi-breadcrumbs-band,.smpi-breadcrumbs{--smpi-bc-accent:" . $bc["accent"] . ";--smpi-bc-tint:" . $bc["tint"] . ";--smpi-bc-background:" . $bc["background"] . ";--smpi-bc-font-size:" . $bc["size"] . "}";
        $toc = self::toc_var_values();
        $css .= ".smpi-table-of-contents{--smpi-toc-accent:" . $toc["accent"] . ";--smpi-toc-text:" . $toc["text"] . ";--smpi-toc-size:" . $toc["size"] . ";--smpi-toc-fstyle:" . $toc["fstyle"] . "}";
        $faq = self::faq_var_values();
        $css .= ".smpi-post-faqs{--smpi-faq-accent:" . $faq["accent"] . ";--smpi-faq-text:" . $faq["text"] . ";--smpi-faq-size:" . $faq["size"] . ";--smpi-faq-fstyle:" . $faq["fstyle"] . "}";
        if ( Settings::bool( "article_heading_styles_enabled" ) ) {
            $h = self::article_heading_var_values();
            $css .= "body.single-post{--smpi-heading-accent:" . $h["accent"] . ";--smpi-heading-accent-fade:" . $h["accent_fade"] . ";--smpi-heading-highlight:" . $h["highlight"] . ";--smpi-heading-line:" . $h["line"] . ";--smpi-heading-ink:" . $h["ink"] . ";--smpi-heading-h2-size:" . $h["h2_size"] . ";--smpi-heading-h3-size:" . $h["h3_size"] . "}";
        }
        if ( Settings::bool( "article_drop_cap_enabled" ) ) {
            $d = self::article_drop_cap_var_values();
            $css .= "body.single-post{--smpi-dropcap-color:" . $d["color"] . ";--smpi-dropcap-soft:" . $d["soft"] . ";--smpi-dropcap-ink:" . $d["ink"] . ";--smpi-dropcap-size:" . $d["size"] . "}";
        }
        if ( Settings::bool( "inline_photo_treatments_enabled" ) ) {
            $p = self::photo_var_values();
            $css .= "body.single-post,body.single-press-release{--smpi-photo-accent:" . $p["accent"] . ";--smpi-photo-cap-color:" . $p["color"] . ";--smpi-photo-cap-size:" . $p["size"] . ";--smpi-photo-cap-fstyle:" . $p["fstyle"] . "}";
        }
        if ( Settings::bool( "featured_image_caption_templates_enabled" ) ) {
            $f = self::featured_image_var_values();
            $css .= "body.single-post,body.single-press-release{--smpi-fi-accent:" . $f["accent"] . ";--smpi-fi-cap-color:" . $f["color"] . ";--smpi-fi-cap-size:" . $f["size"] . ";--smpi-fi-cap-fstyle:" . $f["fstyle"] . "}";
        }
        return $css;
    }

    public static function breadcrumb_var_values(): array {
        $accent = self::hex( Settings::get( "breadcrumbs_accent_color", "#d63428" ), "#d63428" );
        return [
            "accent" => $accent,
            "background" => self::hex( Settings::get( "breadcrumbs_background_color", "#ffffff" ), "#ffffff" ),
            "tint"   => self::rgba( $accent, 0.07 ),
            "size"   => self::px( Settings::get( "breadcrumbs_font_size", 13 ), 13 ),
        ];
    }

    public static function toc_var_values(): array {
        return [
            "accent" => self::hex( Settings::get( "table_of_contents_accent_color", "#2563eb" ), "#2563eb" ),
            "text"   => self::hex( Settings::get( "table_of_contents_text_color", "#111827" ), "#111827" ),
            "size"   => self::px( Settings::get( "table_of_contents_text_font_size", 15 ), 15 ),
            "fstyle" => self::fstyle( Settings::get( "table_of_contents_text_font_style", "normal" ) ),
        ];
    }

    public static function article_heading_var_values(): array {
        $accent = self::hex( Settings::get( "article_heading_accent_color", Settings::color_default( "article_heading_accent_color" ) ), Settings::color_default( "article_heading_accent_color" ) );
        return [
            "accent"  => $accent,
            "accent_fade" => self::rgba( $accent, 0 ),
            "highlight" => self::rgba( $accent, 0.16 ),
            "line"    => "#e5e7eb",
            "ink"     => "#111827",
            "h2_size" => self::px( Settings::get( "article_heading_h2_font_size", 23 ), 23 ),
            "h3_size" => self::px( Settings::get( "article_heading_h3_font_size", 20 ), 20 ),
        ];
    }

    public static function article_drop_cap_var_values(): array {
        $default = Settings::color_default( "article_drop_cap_color" );
        $color = self::hex( Settings::get( "article_drop_cap_color", $default ), $default );
        return [
            "color" => $color,
            "soft"  => self::rgba( $color, 0.14 ),
            "ink"   => self::contrast_ink( $color ),
            "size"  => self::px( Settings::get( "article_drop_cap_font_size", 96 ), 96, 48, 180 ),
        ];
    }

    public static function faq_var_values(): array {
        return [
            "accent" => self::hex( Settings::get( "post_faqs_accent_color", "#2563eb" ), "#2563eb" ),
            "text"   => self::hex( Settings::get( "post_faqs_text_color", "#1f2937" ), "#1f2937" ),
            "size"   => self::px( Settings::get( "post_faqs_text_font_size", 16 ), 16 ),
            "fstyle" => self::fstyle( Settings::get( "post_faqs_text_font_style", "normal" ) ),
        ];
    }

    public static function photo_var_values(): array {
        return [
            "accent" => self::hex( Settings::get( "inline_photo_accent_color", "#d63428" ), "#d63428" ),
            "color"  => self::hex( Settings::get( "inline_photo_caption_text_color", "#272727" ), "#272727" ),
            "size"   => self::px( Settings::get( "inline_photo_caption_font_size", 16 ), 16 ),
            "fstyle" => self::fstyle( Settings::get( "inline_photo_caption_font_style", "italic" ) ),
        ];
    }

    public static function featured_image_var_values(): array {
        return [
            "accent" => self::hex( Settings::get( "featured_image_caption_accent_color", "#d63428" ), "#d63428" ),
            "color"  => self::hex( Settings::get( "featured_image_caption_text_color", "#272727" ), "#272727" ),
            "size"   => self::px( Settings::get( "featured_image_caption_font_size", 16 ), 16 ),
            "fstyle" => self::fstyle( Settings::get( "featured_image_caption_font_style", "italic" ) ),
        ];
    }

    private static function hex( $value, string $fallback ): string {
        $value = sanitize_hex_color( (string) $value );
        return $value ?: $fallback;
    }

    private static function px( $value, int $fallback, int $min = 8, int $max = 96 ): string {
        $n = absint( $value );
        return ( $n >= $min && $n <= $max ? $n : $fallback ) . "px";
    }

    private static function rgba( string $hex, float $alpha ): string {
        $hex = ltrim( $hex, "#" );
        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
            $hex = "d63428";
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        return "rgba(" . $r . "," . $g . "," . $b . "," . max( 0, min( 1, $alpha ) ) . ")";
    }

    private static function fstyle( $value ): string {
        return "italic" === (string) $value ? "italic" : "normal";
    }

    private static function contrast_ink( string $hex ): string {
        $hex = ltrim( $hex, "#" );
        if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
            return "#111111";
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        return ( ( $r * 299 + $g * 587 + $b * 114 ) / 1000 ) >= 150 ? "#111111" : "#ffffff";
    }

    /* ---------------------------------------------------------------------
     * Breadcrumbs
     * ------------------------------------------------------------------- */
    public static function breadcrumbs_css(): string {
        return ".smpi-breadcrumbs-band{background:var(--smpi-bc-background,#fff);box-sizing:border-box;clear:both;margin:0;max-width:none;width:100%}.smpi-breadcrumbs{--smpi-bc-line:#e5e7eb;--smpi-bc-muted:#6b7280;--smpi-bc-ink:#111827;--smpi-bc-body:#374151;--smpi-bc-soft:#f7f8f9;background:var(--smpi-bc-background,#fff);box-sizing:border-box;max-width:var(--content-width,1120px);margin:0 auto;font-family:inherit;font-size:var(--smpi-bc-font-size,13px);clear:both}.smpi-breadcrumbs *{box-sizing:border-box}.smpi-breadcrumbs .smpi-breadcrumb-list{margin:0}.smpi-breadcrumbs .smpi-breadcrumb-link{text-decoration:none}.smpi-breadcrumbs .smpi-breadcrumb-title{font-family:Georgia,serif;font-weight:700;letter-spacing:-.01em}.smpi-bc-b1{background:var(--smpi-bc-tint,rgba(214,52,40,.07));padding:20px 24px}.smpi-bc-b1 .smpi-breadcrumb-title{font-size:25px;line-height:1.15;color:var(--smpi-bc-ink,#111827);margin:0 0 8px}.smpi-bc-b1 .smpi-breadcrumb-list{font-size:var(--smpi-bc-font-size,13px);color:var(--smpi-bc-muted,#6b7280);line-height:1.5}.smpi-bc-b1 .smpi-breadcrumb-link{color:var(--smpi-bc-accent,#d63428)}.smpi-bc-b1 .smpi-breadcrumb-link:hover{text-decoration:underline}.smpi-bc-b1 .smpi-breadcrumb-separator{color:#b9b9b9}.smpi-bc-b1 .smpi-breadcrumb-current{color:var(--smpi-bc-muted,#6b7280)}.smpi-bc-b2{padding:14px 24px;border-bottom:1px solid var(--smpi-bc-line,#e5e7eb)}.smpi-bc-b2 .smpi-breadcrumb-list{display:flex;flex-wrap:wrap;align-items:center;font-size:var(--smpi-bc-font-size,13px);color:var(--smpi-bc-muted,#6b7280)}.smpi-bc-b2 .smpi-breadcrumb-link{color:var(--smpi-bc-muted,#6b7280)}.smpi-bc-b2 .smpi-breadcrumb-link:hover{color:var(--smpi-bc-accent,#d63428)}.smpi-bc-b2 .smpi-breadcrumb-separator{font-size:0;margin:0 9px}.smpi-bc-b2 .smpi-breadcrumb-separator::after{content:\"\\203A\";font-size:14px;color:#c3c3c3}.smpi-bc-b2 .smpi-breadcrumb-current{color:var(--smpi-bc-ink,#111827);font-weight:600}.smpi-bc-b3{padding:16px 24px;border-bottom:1px solid var(--smpi-bc-line,#e5e7eb);position:relative}.smpi-bc-b3::after{content:\"\";position:absolute;left:24px;bottom:-1px;width:46px;height:2px;background:var(--smpi-bc-accent,#d63428)}.smpi-bc-b3 .smpi-breadcrumb-list{font-size:var(--smpi-bc-font-size,11px);letter-spacing:.18em;text-transform:uppercase;color:var(--smpi-bc-muted,#6b7280)}.smpi-bc-b3 .smpi-breadcrumb-link{color:var(--smpi-bc-accent,#d63428);font-weight:600}.smpi-bc-b3 .smpi-breadcrumb-separator{font-size:0;margin:0 8px}.smpi-bc-b3 .smpi-breadcrumb-separator::after{content:\"/\";font-size:11px;letter-spacing:0;color:#ccc}.smpi-bc-b3 .smpi-breadcrumb-current{color:var(--smpi-bc-ink,#111827)}.smpi-bc-b4{padding:14px 22px;background:var(--smpi-bc-soft,#f7f8f9);border-bottom:1px solid var(--smpi-bc-line,#e5e7eb)}.smpi-bc-b4 .smpi-breadcrumb-list{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.smpi-bc-b4 .smpi-breadcrumb-link,.smpi-bc-b4 .smpi-breadcrumb-current{display:inline-block;padding:5px 13px;border-radius:999px;font-size:var(--smpi-bc-font-size,12px);line-height:1.4}.smpi-bc-b4 .smpi-breadcrumb-link{background:#fff;border:1px solid var(--smpi-bc-line,#e5e7eb);color:var(--smpi-bc-body,#374151)}.smpi-bc-b4 .smpi-breadcrumb-link:hover{border-color:var(--smpi-bc-accent,#d63428);color:var(--smpi-bc-accent,#d63428)}.smpi-bc-b4 .smpi-breadcrumb-current{background:var(--smpi-bc-accent,#d63428);color:#fff;max-width:100%}.smpi-bc-b4 .smpi-breadcrumb-separator{display:none}.smpi-bc-b5{padding:24px;background:linear-gradient(180deg,var(--smpi-bc-tint,rgba(214,52,40,.07)),#fff)}.smpi-bc-b5 .smpi-breadcrumb-list{font-size:var(--smpi-bc-font-size,12px);letter-spacing:.03em;color:var(--smpi-bc-muted,#6b7280);margin:0 0 11px}.smpi-bc-b5 .smpi-breadcrumb-link{color:var(--smpi-bc-accent,#d63428)}.smpi-bc-b5 .smpi-breadcrumb-separator{font-size:0;margin:0 8px}.smpi-bc-b5 .smpi-breadcrumb-separator::after{content:\"\\2014\";font-size:12px;color:#cdcdcd}.smpi-bc-b5 .smpi-breadcrumb-current{color:var(--smpi-bc-muted,#6b7280)}.smpi-bc-b5 .smpi-breadcrumb-title{font-size:30px;line-height:1.12;color:var(--smpi-bc-ink,#111827);margin:0}.smpi-bc-b6{max-width:var(--content-width,1140px);padding:14px 0;border-bottom:0}.smpi-bc-b6 .smpi-breadcrumb-list{display:flex;flex-wrap:wrap;align-items:center;font-size:var(--smpi-bc-font-size,13px);letter-spacing:.01em;text-transform:capitalize;color:var(--smpi-bc-muted,#6b7280)}.smpi-bc-b6 .smpi-breadcrumb-link{color:var(--smpi-bc-accent,#d63428);font-weight:600}.smpi-bc-b6 .smpi-breadcrumb-link:hover{text-decoration:underline}.smpi-bc-b6 .smpi-breadcrumb-separator{font-size:0;margin:0 8px}.smpi-bc-b6 .smpi-breadcrumb-separator::after{content:\"/\";font-size:13px;letter-spacing:0;color:#cfcfcf}.smpi-bc-b6 .smpi-breadcrumb-current{color:var(--smpi-bc-ink,#111827);font-weight:500}.smpi-breadcrumbs.smpi-bc-b1,.smpi-breadcrumbs.smpi-bc-b4,.smpi-breadcrumbs.smpi-bc-b5{background:var(--smpi-bc-background,#fff)}@media(max-width:680px){.smpi-breadcrumbs{max-width:100%}.smpi-bc-b1,.smpi-bc-b2,.smpi-bc-b3,.smpi-bc-b4,.smpi-bc-b5,.smpi-bc-b6{padding-left:16px;padding-right:16px}.smpi-bc-b1 .smpi-breadcrumb-title{font-size:21px}.smpi-bc-b5 .smpi-breadcrumb-title{font-size:24px}}";
    }

    /* ---------------------------------------------------------------------
     * Table of contents
     * ------------------------------------------------------------------- */
    public static function toc_css(): string {
        return ".smpi-table-of-contents{max-width:var(--content-width,720px);margin:0}.smpi-table-of-contents .smpi-toc-label{display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;list-style:none;user-select:none;margin:0}.smpi-table-of-contents .smpi-toc-label::-webkit-details-marker{display:none}.smpi-table-of-contents .smpi-toc-link{text-decoration:none;font-size:var(--smpi-toc-size,15px);font-style:var(--smpi-toc-fstyle,normal)}.smpi-table-of-contents .smpi-toc-list{margin:0;padding:0}.smpi-toc-caret{flex:0 0 auto;width:8px;height:8px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);transition:transform .2s ease;opacity:.5}.smpi-table-of-contents[open] .smpi-toc-caret{transform:rotate(-135deg)}.smpi-table-of-contents[open] .smpi-toc-list,.smpi-table-of-contents[open] .smpi-toc-panel{margin-top:14px}.smpi-toc-none{border:1px solid #ececec;border-radius:12px;padding:14px 16px}.smpi-toc-none .smpi-toc-list{padding-left:20px}.smpi-toc00{max-width:560px;background:#fafbfc;border-radius:12px;padding:16px 18px}.smpi-toc00 .smpi-toc-label{font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af}.smpi-toc00 .smpi-toc-list{list-style:none;display:grid;gap:10px}.smpi-toc00 .smpi-toc-link{color:var(--smpi-toc-text,#111827);border-bottom:1px solid transparent}.smpi-toc00 .smpi-toc-link:hover{color:var(--smpi-toc-accent,#2563eb);border-color:var(--smpi-toc-accent,#2563eb)}.smpi-toc01{max-width:560px;background:#fafbfc;border-radius:12px;border-left:3px solid var(--smpi-toc-accent,#2563eb);padding:14px 18px}.smpi-toc01 .smpi-toc-label{font-size:.82rem;font-weight:800;color:#0a0a0a}.smpi-toc01 .smpi-toc-list{list-style:none;display:grid;gap:11px}.smpi-toc01 .smpi-toc-link{color:var(--smpi-toc-text,#52525b)}.smpi-toc01 .smpi-toc-link:hover{color:var(--smpi-toc-accent,#2563eb)}.smpi-toc02{background:#f7f8f9;border-radius:12px;padding:18px 24px;max-width:560px}.smpi-toc02 .smpi-toc-label{font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#6b7280}.smpi-toc02 .smpi-toc-list{list-style:none;display:grid;gap:12px;counter-reset:t}.smpi-toc02 .smpi-toc-item{counter-increment:t;display:flex;gap:12px;align-items:baseline}.smpi-toc02 .smpi-toc-item:before{content:counter(t,decimal-leading-zero);color:var(--smpi-toc-accent,#2563eb);font-weight:700;font-size:.85rem}.smpi-toc02 .smpi-toc-link{color:var(--smpi-toc-text,#1f2937)}.smpi-toc02 .smpi-toc-link:hover{color:var(--smpi-toc-accent,#2563eb)}.smpi-toc03{background:#f8f9fb;border-radius:14px;padding:18px 22px}.smpi-toc03 .smpi-toc-label{font-size:.6rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#8a92a0}.smpi-toc03 .smpi-toc-list{list-style:none;counter-reset:t}.smpi-toc03 .smpi-toc-item{counter-increment:t}.smpi-toc03 .smpi-toc-link{display:flex;gap:13px;align-items:baseline;padding:9px 10px;margin:0 -10px;border-radius:9px;color:var(--smpi-toc-text,#1f2937);line-height:1.4;transition:background .12s ease,color .12s ease}.smpi-toc03 .smpi-toc-link:before{content:counter(t,decimal-leading-zero);color:var(--smpi-toc-accent,#2563eb);font-weight:800;font-size:.8rem;min-width:1.6em;flex:0 0 auto}.smpi-toc03 .smpi-toc-link:hover{background:#eef1f6;color:var(--smpi-toc-accent,#2563eb)}.smpi-toc04{background:#fafbfc;border-radius:12px;padding:14px 18px}.smpi-toc04 .smpi-toc-label{font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af}.smpi-toc04 .smpi-toc-panel{display:flex;flex-wrap:wrap;align-items:center;gap:8px}.smpi-toc04 .smpi-toc-link{color:var(--smpi-toc-text,#374151);border:1px solid #e5e7eb;border-radius:999px;padding:6px 14px;background:#fff;line-height:1.3}.smpi-toc04 .smpi-toc-link:hover{border-color:var(--smpi-toc-accent,#2563eb);color:var(--smpi-toc-accent,#2563eb)}";
    }

    /* ---------------------------------------------------------------------
     * Post summary + FAQ
     * ------------------------------------------------------------------- */
    public static function post_acf_css(): string {
        return ".smpi-post-summary{max-width:var(--content-width,720px);margin:0}.smpi-post-faqs{max-width:var(--content-width,720px);margin:2rem auto}.smpi-sum00{background:#f5f6f7;padding:26px 32px}.smpi-sum00 .smpi-post-summary-title{margin:0;font-size:1.3rem;font-weight:800;color:#1f2937;display:inline-block;padding-bottom:8px;border-bottom:3px solid #111827}.smpi-sum00 .smpi-post-summary-content{margin-top:18px}.smpi-sum01{border-left:4px solid #2563eb;padding:2px 0 2px 22px}.smpi-sum01 .smpi-post-summary-content{font-size:15px}.smpi-sum01 .smpi-post-summary-item{margin-bottom:9px;line-height:1.45}.smpi-sum01 .smpi-post-summary-item:last-child{margin-bottom:0}.smpi-sum01 .smpi-post-summary-title{margin:0 0 10px;font-size:.78rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#2563eb}.smpi-sum02{padding:18px 0;border-top:2px solid #0a0a0a;border-bottom:1px solid #e5e7eb}.smpi-sum02 .smpi-post-summary-title{margin:0 0 12px;font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#0a0a0a}.smpi-sum03{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.smpi-sum03 .smpi-post-summary-title{margin:0;background:#0a0a0a;color:#fff;padding:12px 22px;font-size:.85rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.smpi-sum03 .smpi-post-summary-content{padding:18px 22px}.smpi-sum04{background:#eff4ff;border-radius:14px;padding:24px 28px}.smpi-sum04 .smpi-post-summary-title{margin:0 0 14px;font-size:1.05rem;font-weight:800;color:#1e3a8a;display:flex;align-items:center;gap:9px}.smpi-sum04 .smpi-post-summary-title:before{content:\"\";width:18px;height:18px;border-radius:5px;background:#2563eb}.smpi-post-summary-list{margin:0;padding-left:1.2rem}.smpi-post-faqs-content{color:var(--smpi-faq-text,#1f2937);font-size:var(--smpi-faq-size,16px);font-style:var(--smpi-faq-fstyle,normal)}.smpi-post-faqs-title{font-size:1.05rem;font-weight:800;color:#0a0a0a;margin:0 0 10px}.smpi-post-faq-question{font-size:.95rem;font-weight:700;line-height:1.3;margin:0 0 4px;color:#0a0a0a}.smpi-post-faq-answer{font-size:.86em;line-height:1.5}.smpi-post-faq-text{margin:0 0 .5em}.smpi-post-faq-text:last-child{margin-bottom:0}.smpi-post-faq-list{list-style:none;margin:0;padding:0}.smpi-faq00 .smpi-post-faqs-content,.smpi-faq01 .smpi-post-faqs-content{border-top:1px solid #e5e7eb}.smpi-faq00 .smpi-post-faq-item,.smpi-faq01 .smpi-post-faq-item{border-bottom:1px solid #e5e7eb;padding:16px 0;margin:0}.smpi-faq02 .smpi-post-faq-item{border:1px solid #e5e7eb;border-radius:12px;padding:18px 22px;margin:0 0 14px;box-shadow:0 1px 2px rgba(0,0,0,.04)}.smpi-faq03 .smpi-post-faqs-content{counter-reset:f}.smpi-faq03 .smpi-post-faq-item{counter-increment:f;position:relative;padding:12px 0 12px 34px;border-bottom:1px solid #e5e7eb}.smpi-faq03 .smpi-post-faq-item:before{content:counter(f,decimal-leading-zero);position:absolute;left:0;top:14px;font-size:1rem;font-weight:800;color:var(--smpi-faq-accent,#2563eb);line-height:1}.smpi-faq04 .smpi-post-faq-item{background:#f8fafc;border:1px solid #eef2f7;border-radius:12px;margin-bottom:10px;padding:16px 20px}";
    }

    /* ---------------------------------------------------------------------
     * Article H2/H3 heading treatments — imported from HerForward article-h2s.
     * ------------------------------------------------------------------- */
    public static function article_heading_rules( string $style, string $scope, string $h2, string $h3 ): string {
        $style = self::normalize_article_heading_style( $style );
        if ( "none" === $style ) {
            return "";
        }
        $base = $h2 . "," . $h3 . "{font-family:var(--smpi-heading-sans,Arial,sans-serif);font-weight:700;line-height:1.3;color:var(--smpi-heading-ink,#111827);margin:1.75em 0 .75em;clear:both;text-transform:none;letter-spacing:0}" . $h2 . "{font-size:var(--smpi-heading-h2-size,23px)}" . $h3 . "{font-size:var(--smpi-heading-h3-size,20px)}";
        return $base . self::article_heading_template( $style, $scope, $h2, $h3 );
    }

    private static function article_heading_template( string $style, string $scope, string $h2, string $h3 ): string {
        $heading = $h2 . "," . $h3;
        $before = $h2 . "::before," . $h3 . "::before";
        $after = $h2 . "::after," . $h3 . "::after";
        switch ( $style ) {
            case "h2-leftrule":
                return $heading . "{padding-left:18px;border-left:3px solid var(--smpi-heading-accent,#d63428)}";
            case "h2-underline":
                return $heading . "{position:relative;padding-bottom:12px}" . $after . "{content:\"\";position:absolute;left:0;bottom:0;width:52px;height:3px;border-radius:2px;background:var(--smpi-heading-accent,#d63428)}";
            case "h2-topline":
                return $heading . "{border-top:1px solid var(--smpi-heading-line,#e5e7eb);padding-top:18px}";
            case "h2-dot":
                return $heading . "{display:flex;align-items:center;gap:14px}" . $before . "{content:\"\";width:10px;height:10px;border-radius:50%;background:var(--smpi-heading-accent,#d63428);flex:0 0 auto}";
            case "h2-trailingrule":
                return $heading . "{display:flex;align-items:center;gap:20px;white-space:nowrap}" . $after . "{content:\"\";flex:1;height:1px;background:var(--smpi-heading-line,#e5e7eb)}";
            case "h2-serif":
                return $heading . "{font-family:var(--smpi-heading-serif,Georgia,serif)!important;font-weight:700}" . $before . "{content:\"\";display:inline-block;width:22px;height:2px;background:var(--smpi-heading-accent,#d63428);vertical-align:middle;margin-right:15px}";
            case "h2-uppercase":
                return $heading . "{display:inline-block;text-transform:uppercase;letter-spacing:.12em;font-weight:700;padding-top:13px;border-top:2px solid var(--smpi-heading-accent,#d63428)}";
            case "h2-gradient":
                return $heading . "{position:relative;padding-bottom:12px}" . $after . "{content:\"\";position:absolute;left:0;bottom:0;width:92px;height:3px;background:linear-gradient(90deg,var(--smpi-heading-accent,#d63428),var(--smpi-heading-accent-fade,rgba(214,52,40,0)))}";
            case "h2-bracket":
                return $heading . "{position:relative;padding-left:18px}" . $before . "{content:\"\";position:absolute;left:0;top:3px;width:9px;height:16px;border-left:3px solid var(--smpi-heading-accent,#d63428);border-top:3px solid var(--smpi-heading-accent,#d63428)}";
            case "h2-number":
                return $scope . "{counter-reset:hx}" . $heading . "{counter-increment:hx}" . $before . "{content:counter(hx,decimal-leading-zero);color:var(--smpi-heading-accent,#d63428);font-weight:800;margin-right:16px;font-variant-numeric:tabular-nums}";
            case "h2-square":
                return $heading . "{display:flex;align-items:center;gap:14px}" . $before . "{content:\"\";width:15px;height:15px;border-radius:4px;background:var(--smpi-heading-accent,#d63428);flex:0 0 auto}";
            case "h2-highlight":
                return $heading . "{display:inline-block;background:linear-gradient(transparent 62%,var(--smpi-heading-highlight,rgba(214,52,40,.16)) 0);padding:0 3px}";
            case "h2-double":
                return $heading . "{padding:14px 0;border-top:2px solid var(--smpi-heading-accent,#d63428);border-bottom:1px solid var(--smpi-heading-line,#e5e7eb)}";
            case "h2-corner_tick":
                return $heading . "{position:relative;padding-top:16px}" . $before . "{content:\"\";position:absolute;left:0;top:0;width:3px;height:11px;background:var(--smpi-heading-accent,#d63428);border-radius:2px}";
            case "h2-tick":
            default:
                return $before . "{content:\"\";display:inline-block;width:26px;height:3px;border-radius:2px;background:var(--smpi-heading-accent,#d63428);vertical-align:middle;margin-right:16px}";
        }
    }

    public static function article_heading_css( string $style ): string {
        if ( "none" === $style || ! Settings::bool( "article_heading_styles_enabled" ) ) {
            return "";
        }
        $scope = "body.single-post";
        return self::article_heading_rules( $style, $scope, $scope . " .smpi-article-heading--h2", $scope . " .smpi-article-heading--h3" );
    }

    public static function article_drop_cap_rules( string $style, string $paragraph ): string {
        $style = self::normalize_article_drop_cap_style( $style );
        $letter = $paragraph . "::first-letter";
        $base = $paragraph . "{overflow:visible}" . $letter . "{box-sizing:border-box;float:left;font-family:Arial,Helvetica,sans-serif;font-size:var(--smpi-dropcap-size,96px);font-weight:900;line-height:.78;margin:.08em 24px 0 0;text-transform:uppercase;letter-spacing:0}";
        $cursive = "font-family:\"Dancing Script\",\"Snell Roundhand\",\"Apple Chancery\",\"Segoe Script\",\"Brush Script MT\",cursive;font-weight:600;line-height:.9;";
        switch ( $style ) {
            case "dropcap-highlight":
                return $base . $letter . "{background:var(--smpi-dropcap-color,#facc15);color:var(--smpi-dropcap-ink,#111111);line-height:.8;margin:.06em 18px 0 0;padding:.08em .13em .11em}";
            case "dropcap-outline":
                return $base . $letter . "{background:transparent;border:2px solid var(--smpi-dropcap-color,#111111);color:var(--smpi-dropcap-color,#111111);line-height:.8;margin:.06em 18px 0 0;padding:.06em .12em .09em}";
            case "dropcap-side-rule":
                return $base . $letter . "{border-left:6px solid var(--smpi-dropcap-color,#111111);color:var(--smpi-dropcap-color,#111111);line-height:.82;margin:.06em 20px 0 0;padding-left:.12em}";
            case "dropcap-soft-tile":
                return $base . $letter . "{background:var(--smpi-dropcap-soft,rgba(17,17,17,.14));border-radius:6px;color:var(--smpi-dropcap-color,#111111);line-height:.8;margin:.06em 18px 0 0;padding:.08em .13em .11em}";
            case "dropcap-script-classic":
                return $base . $letter . "{" . $cursive . "color:var(--smpi-dropcap-color,#111111);margin:.02em 22px 0 0}";
            case "dropcap-script-tile":
                return $base . $letter . "{" . $cursive . "background:var(--smpi-dropcap-soft,rgba(17,17,17,.12));border-radius:10px;color:var(--smpi-dropcap-color,#111111);margin:.04em 20px 0 0;padding:.1em .17em .14em}";
            case "dropcap-script-round":
                return $base . $letter . "{" . $cursive . "background:var(--smpi-dropcap-soft,rgba(17,17,17,.12));border-radius:999px;color:var(--smpi-dropcap-color,#111111);margin:.04em 20px 0 0;padding:.12em .22em .16em}";
            case "dropcap-script-underline":
                return $base . $letter . "{" . $cursive . "border-bottom:4px solid var(--smpi-dropcap-color,#111111);color:var(--smpi-dropcap-color,#111111);margin:.02em 22px 0 0;padding-bottom:.05em}";
            case "dropcap-script-shadow":
                return $base . $letter . "{" . $cursive . "color:var(--smpi-dropcap-color,#111111);text-shadow:.05em .05em 0 var(--smpi-dropcap-soft,rgba(17,17,17,.22))}";
            case "dropcap-classic":
            default:
                return $base . $letter . "{color:var(--smpi-dropcap-color,#111111)}";
        }
    }

    public static function article_drop_cap_css(): string {
        if ( ! Settings::bool( "article_drop_cap_enabled" ) ) {
            return "";
        }
        $scope = "body.single-post";
        $style = self::normalize_article_drop_cap_style( (string) Settings::get( "article_drop_cap_style", "dropcap-classic" ) );
        return self::article_drop_cap_rules( $style, $scope . " .smpi-article-lead" );
    }

    /* ---------------------------------------------------------------------
     * Inline photo treatments — rules are generated by a single template so
     * the front end (theme figures) and the admin preview (figure markup)
     * are byte-identical.
     * ------------------------------------------------------------------- */
    public static function inline_photo_rules( string $style, string $fig, string $img, string $cap ): string {
        $tpl = self::inline_photo_template( $style );
        return strtr( $tpl, [ "%FIG%" => $fig, "%IMG%" => $img, "%CAP%" => $cap ] );
    }

    private static function inline_photo_template( string $style ): string {
        if ( "fig1" === $style ) {
            return "%FIG%{margin:2.5rem auto}%IMG%{width:100%;height:auto;display:block}%CAP%{margin-top:16px;padding-left:16px;border-left:3px solid var(--smpi-photo-accent,#d63428);font-family:Georgia,serif;font-style:var(--smpi-photo-cap-fstyle,italic);font-size:var(--smpi-photo-cap-size,17px);color:var(--smpi-photo-cap-color,#1f1f1f);line-height:1.5}";
        }
        if ( "fig2" === $style ) {
            return "%FIG%{margin:2.5rem auto;border-radius:6px;overflow:hidden}%IMG%{width:100%;display:block;margin:0}%CAP%{margin:0;background:#fafafa;border-top:3px solid var(--smpi-photo-accent,#d63428);padding:16px 20px;font-family:Georgia,serif;font-style:var(--smpi-photo-cap-fstyle,italic);font-size:var(--smpi-photo-cap-size,13px);color:var(--smpi-photo-cap-color,#272727)}";
        }
        if ( "fig4" === $style ) {
            return "%FIG%{margin:2.5rem auto;position:relative;border-radius:16px;overflow:hidden}%IMG%{width:100%;display:block}%CAP%{position:absolute;left:0;right:0;bottom:0;padding:54px 22px 18px;color:var(--smpi-photo-cap-color-overlay,#fff);font-family:Georgia,serif;font-style:var(--smpi-photo-cap-fstyle,italic);font-size:var(--smpi-photo-cap-size,17px);background:linear-gradient(to top,rgba(10,10,12,.85),rgba(10,10,12,0))}";
        }
        return "%FIG%{margin:2.5rem auto;border:1px solid #e9e9e9;border-radius:18px;padding:14px;background:#fff;box-shadow:0 20px 44px -30px rgba(15,15,15,.4)}%IMG%{width:100%;display:block;border-radius:10px}%CAP%{display:block;padding:16px 6px 4px;font-family:Georgia,serif;font-style:var(--smpi-photo-cap-fstyle,italic);font-size:var(--smpi-photo-cap-size,16.5px);color:var(--smpi-photo-cap-color,#272727)}";
    }

    public static function inline_photo_css( string $style ): string {
        if ( "none" === $style || ! Settings::bool( "inline_photo_treatments_enabled" ) ) {
            return "";
        }
        $fig = "body.single-post .smpi-inline-photo,body.single-press-release .smpi-inline-photo";
        $img = ".smpi-inline-photo .smpi-inline-photo-image";
        $cap = ".smpi-inline-photo .smpi-inline-photo-caption";
        return self::inline_photo_rules( $style, $fig, $img, $cap );
    }



    /* ---------------------------------------------------------------------
     * Featured image caption templates. These duplicate the inline photo
     * treatment designs with independent selectors and CSS variables so this
     * feature can evolve without changing inline figure behavior.
     * ------------------------------------------------------------------- */
    public static function featured_image_caption_rules( string $style, string $host, string $img, string $cap ): string {
        $tpl = self::featured_image_caption_template( $style );
        return strtr( $tpl, [ "%HOST%" => $host, "%IMG%" => $img, "%CAP%" => $cap ] );
    }

    private static function featured_image_caption_template( string $style ): string {
        if ( "fig1" === $style ) {
            return "%HOST%{display:block;margin:2.5rem auto}%IMG%{width:100%;height:auto;display:block}%CAP%{display:block;margin-top:16px;padding-left:16px;border-left:3px solid var(--smpi-fi-accent,#d63428);font-family:Georgia,serif;font-style:var(--smpi-fi-cap-fstyle,italic);font-size:var(--smpi-fi-cap-size,17px);color:var(--smpi-fi-cap-color,#1f1f1f);line-height:1.5}";
        }
        if ( "fig2" === $style ) {
            return "%HOST%{display:block;margin:2.5rem auto;border-radius:6px;overflow:hidden}%IMG%{width:100%;display:block;margin:0}%CAP%{display:block;margin:0;background:#fafafa;border-top:3px solid var(--smpi-fi-accent,#d63428);padding:16px 20px;font-family:Georgia,serif;font-style:var(--smpi-fi-cap-fstyle,italic);font-size:var(--smpi-fi-cap-size,13px);color:var(--smpi-fi-cap-color,#272727);line-height:1.45}";
        }
        if ( "fig4" === $style ) {
            return "%HOST%{display:block;margin:2.5rem auto;position:relative;border-radius:16px;overflow:hidden}%IMG%{width:100%;display:block}%CAP%{display:block;position:absolute;left:0;right:0;bottom:0;padding:54px 22px 18px;color:var(--smpi-fi-cap-color-overlay,#fff);font-family:Georgia,serif;font-style:var(--smpi-fi-cap-fstyle,italic);font-size:var(--smpi-fi-cap-size,17px);line-height:1.45;background:linear-gradient(to top,rgba(10,10,12,.85),rgba(10,10,12,0))}";
        }
        return "%HOST%{display:block;margin:2.5rem auto;border:1px solid #e9e9e9;border-radius:18px;padding:14px;background:#fff;box-shadow:0 20px 44px -30px rgba(15,15,15,.4)}%IMG%{width:100%;display:block;border-radius:10px}%CAP%{display:block;padding:16px 6px 4px;font-family:Georgia,serif;font-style:var(--smpi-fi-cap-fstyle,italic);font-size:var(--smpi-fi-cap-size,16.5px);color:var(--smpi-fi-cap-color,#272727);line-height:1.45}";
    }

    public static function featured_image_caption_css( string $style ): string {
        if ( "none" === $style || ! Settings::bool( "featured_image_caption_templates_enabled" ) ) {
            return "";
        }
        $host = "body.single-post .smpi-featured-image-caption,body.single-press-release .smpi-featured-image-caption";
        $img = ".smpi-featured-image-caption .smpi-featured-image-caption-image";
        $cap = ".smpi-featured-image-caption .smpi-featured-image-caption-text";
        return self::featured_image_caption_rules( $style, $host, $img, $cap );
    }

    /* ---------------------------------------------------------------------
     * Admin design previews use the SAME css strings, scoped to the preview
     * containers. This is the one-source-of-truth bundle.
     * ------------------------------------------------------------------- */
    public static function preview_bundle_css(): string {
        $css = self::breadcrumbs_css() . self::toc_css() . self::post_acf_css();
        $css .= ".smpi-choice-preview .smpi-ah-preview-stack{display:grid;gap:14px;max-width:760px}.smpi-choice-preview .smpi-ah-preview{background:#fff;border:1px solid var(--smpi-heading-line,#e5e7eb);border-radius:12px;box-sizing:border-box;counter-reset:hx;display:block;margin:0!important;max-width:100%;padding:26px 28px}.smpi-choice-preview .smpi-ah-preview .smpi-article-paragraph{font-family:var(--smpi-heading-sans,Arial,sans-serif);font-size:15px;line-height:1.7;color:var(--smpi-heading-body,#475569);margin:14px 0 0;max-width:680px}.smpi-choice-preview .smpi-ah-preview .smpi-article-heading{font-family:var(--smpi-heading-sans,Arial,sans-serif);font-weight:700;line-height:1.3;color:var(--smpi-heading-ink,#111827);margin:0;clear:none;text-transform:none;letter-spacing:0}.smpi-choice-preview .smpi-ah-preview .smpi-article-heading--h2{font-size:var(--smpi-heading-h2-size,23px)}.smpi-choice-preview .smpi-ah-preview .smpi-article-heading--h3{font-size:var(--smpi-heading-h3-size,20px)}";
        foreach ( array_diff( self::article_heading_style_keys(), [ "none" ] ) as $style ) {
            $sel = ".smpi-choice-preview .smpi-ah-preview.smpi-ah-preview-" . $style;
            $css .= self::article_heading_rules( $style, $sel, $sel . " .smpi-article-heading--h2", $sel . " .smpi-article-heading--h3" );
        }
        $css .= ".smpi-choice-preview .smpi-ah-preview-stack{box-sizing:border-box;width:100%;max-width:820px;overflow:hidden}.smpi-choice-preview .smpi-ah-preview{box-sizing:border-box;width:100%;min-width:0;overflow:hidden;position:relative}.smpi-choice-preview .smpi-ah-preview *{box-sizing:border-box}.smpi-choice-preview .smpi-ah-preview .smpi-article-heading{clear:none!important;margin:0!important;max-width:100%;overflow-wrap:anywhere;white-space:normal!important}.smpi-choice-preview .smpi-ah-preview .smpi-article-heading::before,.smpi-choice-preview .smpi-ah-preview .smpi-article-heading::after{box-sizing:border-box}.smpi-choice-preview .smpi-ah-preview .smpi-article-paragraph{margin:14px 0 0!important;max-width:100%;overflow-wrap:anywhere}";
        $css .= ".smpi-choice-preview .smpi-dropcap-preview{background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-sizing:border-box;display:flow-root;max-width:760px;overflow:hidden;padding:24px}.smpi-choice-preview .smpi-dropcap-preview .smpi-article-lead{color:#333;font-family:Arial,Helvetica,sans-serif;font-size:18px;line-height:1.65;margin:0}";
        foreach ( self::article_drop_cap_style_keys() as $style ) {
            $sel = ".smpi-choice-preview .smpi-dropcap-preview--" . $style . " .smpi-article-lead";
            $css .= self::article_drop_cap_rules( $style, $sel );
        }
        foreach ( [ "fig1", "fig2", "fig4", "fig5" ] as $style ) {
            $sel = ".smpi-pp.smpi-pp-" . $style;
            $css .= self::inline_photo_rules( $style, $sel, $sel . " .smpi-inline-photo-image", $sel . " .smpi-inline-photo-caption" );
        }
        foreach ( [ "fig1", "fig2", "fig4", "fig5" ] as $style ) {
            $sel = ".smpi-fi-preview.smpi-fi-preview-" . $style;
            $css .= self::featured_image_caption_rules( $style, $sel, $sel . " .smpi-featured-image-caption-image", $sel . " .smpi-featured-image-caption-text" );
        }
        $b = self::breadcrumb_var_values();
        $t = self::toc_var_values();
        $h = self::article_heading_var_values();
        $d = self::article_drop_cap_var_values();
        $f = self::faq_var_values();
        $p = self::photo_var_values();
        $fp = self::featured_image_var_values();
        $css .= ".smpi-design-host{--smpi-bc-accent:" . $b["accent"] . ";--smpi-bc-tint:" . $b["tint"] . ";--smpi-bc-background:" . $b["background"] . ";--smpi-bc-font-size:" . $b["size"] . ";--smpi-toc-accent:" . $t["accent"] . ";--smpi-toc-text:" . $t["text"] . ";--smpi-toc-size:" . $t["size"] . ";--smpi-toc-fstyle:" . $t["fstyle"] . ";--smpi-heading-accent:" . $h["accent"] . ";--smpi-heading-accent-fade:" . $h["accent_fade"] . ";--smpi-heading-highlight:" . $h["highlight"] . ";--smpi-heading-line:" . $h["line"] . ";--smpi-heading-ink:" . $h["ink"] . ";--smpi-heading-h2-size:" . $h["h2_size"] . ";--smpi-heading-h3-size:" . $h["h3_size"] . ";--smpi-dropcap-color:" . $d["color"] . ";--smpi-dropcap-soft:" . $d["soft"] . ";--smpi-dropcap-ink:" . $d["ink"] . ";--smpi-dropcap-size:" . $d["size"] . ";--smpi-faq-accent:" . $f["accent"] . ";--smpi-faq-text:" . $f["text"] . ";--smpi-faq-size:" . $f["size"] . ";--smpi-faq-fstyle:" . $f["fstyle"] . ";--smpi-photo-accent:" . $p["accent"] . ";--smpi-photo-cap-color:" . $p["color"] . ";--smpi-photo-cap-size:" . $p["size"] . ";--smpi-photo-cap-fstyle:" . $p["fstyle"] . ";--smpi-fi-accent:" . $fp["accent"] . ";--smpi-fi-cap-color:" . $fp["color"] . ";--smpi-fi-cap-size:" . $fp["size"] . ";--smpi-fi-cap-fstyle:" . $fp["fstyle"] . "}";
        $css .= ".smpi-choice-preview .smpi-breadcrumbs,.smpi-choice-preview .smpi-table-of-contents,.smpi-choice-preview .smpi-post-summary,.smpi-choice-preview .smpi-post-faqs,.smpi-choice-preview .smpi-pp,.smpi-choice-preview .smpi-fi-preview{max-width:100%!important;margin:0!important}.smpi-choice-preview .smpi-pp,.smpi-choice-preview .smpi-fi-preview{display:block}.smpi-choice-preview .smpi-inline-photo-image,.smpi-choice-preview .smpi-featured-image-caption-image{height:120px;width:100%;object-fit:cover}.smpi-choice-preview .smpi-toc-link,.smpi-choice-preview .smpi-post-faq-item{font-size:13px}";
        return $css;
    }

}
