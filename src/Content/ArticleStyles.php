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
        add_action( "wp_head", [ $this, "print_styles" ], 34 );
    }

    public function print_styles(): void {
        if ( ! RuntimeContext::is_public_dom_context() ) {
            return;
        }
        $needs = Settings::bool( "breadcrumbs_enabled" ) || Settings::bool( "inline_photo_treatments_enabled" ) || Settings::bool( "featured_image_caption_templates_enabled" ) || Settings::bool( "post_summary_acf_enabled" ) || Settings::bool( "post_faqs_acf_enabled" ) || Settings::bool( "table_of_contents_enabled" );
        if ( ! $needs ) {
            return;
        }
        $photo = self::normalize_inline_photo_style( (string) Settings::get( "inline_photo_treatment", "none" ) );
        $featured = self::normalize_featured_image_caption_style( (string) Settings::get( "featured_image_caption_template", "fig2" ) );
        echo "<style id=smpi-article-style-controls>" . self::frontend_vars_css() . self::breadcrumbs_css() . self::toc_css() . self::post_acf_css() . self::inline_photo_css( $photo ) . self::featured_image_caption_css( $featured ) . "</style>";
    }

    public static function normalize_toc_style( string $style = "" ): string {
        $style = sanitize_key( "" !== $style ? $style : (string) Settings::get( "table_of_contents_style", "toc02" ) );
        return in_array( $style, [ "none", "toc00", "toc01", "toc02", "toc03", "toc04" ], true ) ? $style : "toc02";
    }

    public static function normalize_breadcrumb_style( string $style = "" ): string {
        $style = sanitize_key( "" !== $style ? $style : (string) Settings::get( "breadcrumbs_style", "bc-b2" ) );
        return in_array( $style, [ "bc-b1", "bc-b2", "bc-b3", "bc-b4", "bc-b5", "bc-b6" ], true ) ? $style : "bc-b2";
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
        return "<aside class=\"smpi-post-summary " . esc_attr( "smpi-" . $style ) . "\"><h2>" . esc_html( self::summary_title( $style ) ) . "</h2><div class=\"smpi-post-summary-content\">" . wp_kses_post( $html ) . "</div></aside>";
    }

    public static function wrap_post_faqs( string $html, string $style = "" ): string {
        if ( "" === trim( $html ) ) {
            return "";
        }
        $style = self::normalize_faq_style( $style );
        if ( "none" === $style ) {
            return $html;
        }
        return "<section class=\"smpi-post-faqs " . esc_attr( "smpi-" . $style ) . "\"><h2>" . esc_html( self::faq_title( $style ) ) . "</h2><div class=\"smpi-post-faqs-content\">" . wp_kses_post( $html ) . "</div></section>";
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
        $css .= ".smpi-breadcrumbs{--smpi-bc-accent:" . $bc["accent"] . ";--smpi-bc-tint:" . $bc["tint"] . ";--smpi-bc-font-size:" . $bc["size"] . "}";
        $toc = self::toc_var_values();
        $css .= ".smpi-table-of-contents{--smpi-toc-accent:" . $toc["accent"] . ";--smpi-toc-text:" . $toc["text"] . ";--smpi-toc-size:" . $toc["size"] . ";--smpi-toc-fstyle:" . $toc["fstyle"] . "}";
        $faq = self::faq_var_values();
        $css .= ".smpi-post-faqs{--smpi-faq-accent:" . $faq["accent"] . ";--smpi-faq-text:" . $faq["text"] . ";--smpi-faq-size:" . $faq["size"] . ";--smpi-faq-fstyle:" . $faq["fstyle"] . "}";
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

    private static function px( $value, int $fallback ): string {
        $n = absint( $value );
        return ( $n >= 8 && $n <= 96 ? $n : $fallback ) . "px";
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

    /* ---------------------------------------------------------------------
     * Breadcrumbs
     * ------------------------------------------------------------------- */
    public static function breadcrumbs_css(): string {
        return ".smpi-breadcrumbs{--smpi-bc-line:#e5e7eb;--smpi-bc-muted:#6b7280;--smpi-bc-ink:#111827;--smpi-bc-body:#374151;--smpi-bc-soft:#f7f8f9;box-sizing:border-box;max-width:var(--content-width,1120px);margin:0 auto;font-family:inherit;font-size:var(--smpi-bc-font-size,13px);clear:both}.smpi-breadcrumbs *{box-sizing:border-box}.smpi-breadcrumbs .rank-math-breadcrumb p{margin:0}.smpi-breadcrumbs .rank-math-breadcrumb a{text-decoration:none}.smpi-breadcrumbs .pt{font-family:Georgia,serif;font-weight:700;letter-spacing:-.01em}.smpi-bc-b1{background:var(--smpi-bc-tint,rgba(214,52,40,.07));padding:20px 24px}.smpi-bc-b1 .pt{font-size:25px;line-height:1.15;color:var(--smpi-bc-ink,#111827);margin:0 0 8px}.smpi-bc-b1 .rank-math-breadcrumb p{font-size:var(--smpi-bc-font-size,13px);color:var(--smpi-bc-muted,#6b7280);line-height:1.5}.smpi-bc-b1 .rank-math-breadcrumb a{color:var(--smpi-bc-accent,#d63428)}.smpi-bc-b1 .rank-math-breadcrumb a:hover{text-decoration:underline}.smpi-bc-b1 .rank-math-breadcrumb .separator{color:#b9b9b9}.smpi-bc-b1 .rank-math-breadcrumb .last{color:var(--smpi-bc-muted,#6b7280)}.smpi-bc-b2{padding:14px 24px;border-bottom:1px solid var(--smpi-bc-line,#e5e7eb)}.smpi-bc-b2 .rank-math-breadcrumb p{display:flex;flex-wrap:wrap;align-items:center;font-size:var(--smpi-bc-font-size,13px);color:var(--smpi-bc-muted,#6b7280)}.smpi-bc-b2 .rank-math-breadcrumb a{color:var(--smpi-bc-muted,#6b7280)}.smpi-bc-b2 .rank-math-breadcrumb a:hover{color:var(--smpi-bc-accent,#d63428)}.smpi-bc-b2 .rank-math-breadcrumb .separator{font-size:0;margin:0 9px}.smpi-bc-b2 .rank-math-breadcrumb .separator::after{content:\"\\203A\";font-size:14px;color:#c3c3c3}.smpi-bc-b2 .rank-math-breadcrumb .last{color:var(--smpi-bc-ink,#111827);font-weight:600}.smpi-bc-b3{padding:16px 24px;border-bottom:1px solid var(--smpi-bc-line,#e5e7eb);position:relative}.smpi-bc-b3::after{content:\"\";position:absolute;left:24px;bottom:-1px;width:46px;height:2px;background:var(--smpi-bc-accent,#d63428)}.smpi-bc-b3 .rank-math-breadcrumb p{font-size:var(--smpi-bc-font-size,11px);letter-spacing:.18em;text-transform:uppercase;color:var(--smpi-bc-muted,#6b7280)}.smpi-bc-b3 .rank-math-breadcrumb a{color:var(--smpi-bc-accent,#d63428);font-weight:600}.smpi-bc-b3 .rank-math-breadcrumb .separator{font-size:0;margin:0 8px}.smpi-bc-b3 .rank-math-breadcrumb .separator::after{content:\"/\";font-size:11px;letter-spacing:0;color:#ccc}.smpi-bc-b3 .rank-math-breadcrumb .last{color:var(--smpi-bc-ink,#111827)}.smpi-bc-b4{padding:14px 22px;background:var(--smpi-bc-soft,#f7f8f9);border-bottom:1px solid var(--smpi-bc-line,#e5e7eb)}.smpi-bc-b4 .rank-math-breadcrumb p{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.smpi-bc-b4 .rank-math-breadcrumb a,.smpi-bc-b4 .rank-math-breadcrumb .last{display:inline-block;padding:5px 13px;border-radius:999px;font-size:var(--smpi-bc-font-size,12px);line-height:1.4}.smpi-bc-b4 .rank-math-breadcrumb a{background:#fff;border:1px solid var(--smpi-bc-line,#e5e7eb);color:var(--smpi-bc-body,#374151)}.smpi-bc-b4 .rank-math-breadcrumb a:hover{border-color:var(--smpi-bc-accent,#d63428);color:var(--smpi-bc-accent,#d63428)}.smpi-bc-b4 .rank-math-breadcrumb .last{background:var(--smpi-bc-accent,#d63428);color:#fff;max-width:100%}.smpi-bc-b4 .rank-math-breadcrumb .separator{display:none}.smpi-bc-b5{padding:24px;background:linear-gradient(180deg,var(--smpi-bc-tint,rgba(214,52,40,.07)),#fff)}.smpi-bc-b5 .rank-math-breadcrumb p{font-size:var(--smpi-bc-font-size,12px);letter-spacing:.03em;color:var(--smpi-bc-muted,#6b7280);margin:0 0 11px}.smpi-bc-b5 .rank-math-breadcrumb a{color:var(--smpi-bc-accent,#d63428)}.smpi-bc-b5 .rank-math-breadcrumb .separator{font-size:0;margin:0 8px}.smpi-bc-b5 .rank-math-breadcrumb .separator::after{content:\"\\2014\";font-size:12px;color:#cdcdcd}.smpi-bc-b5 .rank-math-breadcrumb .last{color:var(--smpi-bc-muted,#6b7280)}.smpi-bc-b5 .pt{font-size:30px;line-height:1.12;color:var(--smpi-bc-ink,#111827);margin:0}.smpi-bc-b6{max-width:var(--content-width,1140px);padding:14px 0;border-bottom:1px solid var(--smpi-bc-line,#e5e7eb)}.smpi-bc-b6 .rank-math-breadcrumb p{display:flex;flex-wrap:wrap;align-items:center;font-size:var(--smpi-bc-font-size,13px);letter-spacing:.01em;text-transform:capitalize;color:var(--smpi-bc-muted,#6b7280)}.smpi-bc-b6 .rank-math-breadcrumb a{color:var(--smpi-bc-accent,#d63428);font-weight:600}.smpi-bc-b6 .rank-math-breadcrumb a:hover{text-decoration:underline}.smpi-bc-b6 .rank-math-breadcrumb .separator{font-size:0;margin:0 8px}.smpi-bc-b6 .rank-math-breadcrumb .separator::after{content:\"/\";font-size:13px;letter-spacing:0;color:#cfcfcf}.smpi-bc-b6 .rank-math-breadcrumb .last{color:var(--smpi-bc-ink,#111827);font-weight:500}@media(max-width:680px){.smpi-breadcrumbs{max-width:100%}.smpi-bc-b1,.smpi-bc-b2,.smpi-bc-b3,.smpi-bc-b4,.smpi-bc-b5,.smpi-bc-b6{padding-left:16px;padding-right:16px}.smpi-bc-b1 .pt{font-size:21px}.smpi-bc-b5 .pt{font-size:24px}}";
    }

    /* ---------------------------------------------------------------------
     * Table of contents
     * ------------------------------------------------------------------- */
    public static function toc_css(): string {
        return ".smpi-table-of-contents{max-width:var(--content-width,720px);margin:0}.smpi-table-of-contents .smpi-toc-label{display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;list-style:none;user-select:none;margin:0}.smpi-table-of-contents .smpi-toc-label::-webkit-details-marker{display:none}.smpi-table-of-contents a{text-decoration:none;font-size:var(--smpi-toc-size,15px);font-style:var(--smpi-toc-fstyle,normal)}.smpi-table-of-contents ol{margin:0;padding:0}.smpi-toc-caret{flex:0 0 auto;width:8px;height:8px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);transition:transform .2s ease;opacity:.5}.smpi-table-of-contents[open] .smpi-toc-caret{transform:rotate(-135deg)}.smpi-table-of-contents[open] ol,.smpi-table-of-contents[open] .smpi-toc-panel{margin-top:14px}.smpi-toc-none{border:1px solid #ececec;border-radius:12px;padding:14px 16px}.smpi-toc-none ol{padding-left:20px}.smpi-toc00{max-width:560px;background:#fafbfc;border-radius:12px;padding:16px 18px}.smpi-toc00 .smpi-toc-label{font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af}.smpi-toc00 ol{list-style:none;display:grid;gap:10px}.smpi-toc00 a{color:var(--smpi-toc-text,#111827);border-bottom:1px solid transparent}.smpi-toc00 a:hover{color:var(--smpi-toc-accent,#2563eb);border-color:var(--smpi-toc-accent,#2563eb)}.smpi-toc01{max-width:560px;background:#fafbfc;border-radius:12px;border-left:3px solid var(--smpi-toc-accent,#2563eb);padding:14px 18px}.smpi-toc01 .smpi-toc-label{font-size:.82rem;font-weight:800;color:#0a0a0a}.smpi-toc01 ol{list-style:none;display:grid;gap:11px}.smpi-toc01 a{color:var(--smpi-toc-text,#52525b)}.smpi-toc01 a:hover{color:var(--smpi-toc-accent,#2563eb)}.smpi-toc02{background:#f7f8f9;border-radius:12px;padding:18px 24px;max-width:560px}.smpi-toc02 .smpi-toc-label{font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#6b7280}.smpi-toc02 ol{list-style:none;display:grid;gap:12px;counter-reset:t}.smpi-toc02 li{counter-increment:t;display:flex;gap:12px;align-items:baseline}.smpi-toc02 li:before{content:counter(t,decimal-leading-zero);color:var(--smpi-toc-accent,#2563eb);font-weight:700;font-size:.85rem}.smpi-toc02 a{color:var(--smpi-toc-text,#1f2937)}.smpi-toc02 a:hover{color:var(--smpi-toc-accent,#2563eb)}.smpi-toc03{background:#f8f9fb;border-radius:14px;padding:18px 22px}.smpi-toc03 .smpi-toc-label{font-size:.66rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#8a92a0}.smpi-toc03 ol{list-style:none;counter-reset:t}.smpi-toc03 li{counter-increment:t}.smpi-toc03 a{display:flex;gap:13px;align-items:baseline;padding:9px 10px;margin:0 -10px;border-radius:9px;color:var(--smpi-toc-text,#1f2937);line-height:1.4;transition:background .12s ease,color .12s ease}.smpi-toc03 a:before{content:counter(t,decimal-leading-zero);color:var(--smpi-toc-accent,#2563eb);font-weight:800;font-size:.8rem;min-width:1.6em;flex:0 0 auto}.smpi-toc03 a:hover{background:#eef1f6;color:var(--smpi-toc-accent,#2563eb)}.smpi-toc04{background:#fafbfc;border-radius:12px;padding:14px 18px}.smpi-toc04 .smpi-toc-label{font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af}.smpi-toc04 .smpi-toc-panel{display:flex;flex-wrap:wrap;align-items:center;gap:8px}.smpi-toc04 a{color:var(--smpi-toc-text,#374151);border:1px solid #e5e7eb;border-radius:999px;padding:6px 14px;background:#fff;line-height:1.3}.smpi-toc04 a:hover{border-color:var(--smpi-toc-accent,#2563eb);color:var(--smpi-toc-accent,#2563eb)}";
    }

    /* ---------------------------------------------------------------------
     * Post summary + FAQ
     * ------------------------------------------------------------------- */
    public static function post_acf_css(): string {
        return ".smpi-post-summary{max-width:var(--content-width,720px);margin:0}.smpi-post-faqs{max-width:var(--content-width,720px);margin:2rem auto}.smpi-sum00{background:#f5f6f7;padding:26px 32px}.smpi-sum00 h2{margin:0;font-size:1.3rem;font-weight:800;color:#1f2937;display:inline-block;padding-bottom:8px;border-bottom:3px solid #111827}.smpi-sum00 .smpi-post-summary-content{margin-top:18px}.smpi-sum01{border-left:4px solid #2563eb;padding:2px 0 2px 22px}.smpi-sum01 .smpi-post-summary-content{font-size:15px}.smpi-sum01 li{margin-bottom:9px;line-height:1.45}.smpi-sum01 li:last-child{margin-bottom:0}.smpi-sum01 h2{margin:0 0 10px;font-size:.78rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#2563eb}.smpi-sum02{padding:18px 0;border-top:2px solid #0a0a0a;border-bottom:1px solid #e5e7eb}.smpi-sum02 h2{margin:0 0 12px;font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#0a0a0a}.smpi-sum03{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.smpi-sum03 h2{margin:0;background:#0a0a0a;color:#fff;padding:12px 22px;font-size:.85rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.smpi-sum03 .smpi-post-summary-content{padding:18px 22px}.smpi-sum04{background:#eff4ff;border-radius:14px;padding:24px 28px}.smpi-sum04 h2{margin:0 0 14px;font-size:1.05rem;font-weight:800;color:#1e3a8a;display:flex;align-items:center;gap:9px}.smpi-sum04 h2:before{content:\"\";width:18px;height:18px;border-radius:5px;background:#2563eb}.smpi-post-summary ul,.smpi-post-summary ol{margin:0;padding-left:1.2rem}.smpi-post-faqs-content{color:var(--smpi-faq-text,#1f2937);font-size:var(--smpi-faq-size,16px);font-style:var(--smpi-faq-fstyle,normal)}.smpi-faq00 h2,.smpi-faq01 h2,.smpi-faq02 h2,.smpi-faq03 h2,.smpi-faq04 h2{font-size:1.05rem;font-weight:800;color:#0a0a0a;margin:0 0 10px}.smpi-post-faqs .smpi-post-faq-question{font-size:.95rem;font-weight:700;line-height:1.3;margin:0 0 4px;color:#0a0a0a}.smpi-post-faqs .smpi-post-faq-answer{font-size:.86em;line-height:1.5}.smpi-post-faqs .smpi-post-faq-answer p{margin:0 0 .5em}.smpi-post-faqs .smpi-post-faq-answer p:last-child{margin-bottom:0}.smpi-post-faqs-content>ul,.smpi-post-faqs-content>ol{list-style:none;margin:0;padding:0}.smpi-faq00 .smpi-post-faqs-content,.smpi-faq01 .smpi-post-faqs-content{border-top:1px solid #e5e7eb}.smpi-faq00 .smpi-post-faqs-content>ul>li,.smpi-faq00 .smpi-post-faqs-content>ol>li,.smpi-faq00 .smpi-post-faqs-content>:not(ul):not(ol),.smpi-faq01 .smpi-post-faqs-content>ul>li,.smpi-faq01 .smpi-post-faqs-content>ol>li,.smpi-faq01 .smpi-post-faqs-content>:not(ul):not(ol){border-bottom:1px solid #e5e7eb;padding:16px 0;margin:0}.smpi-faq02 .smpi-post-faqs-content>ul>li,.smpi-faq02 .smpi-post-faqs-content>ol>li,.smpi-faq02 .smpi-post-faqs-content>:not(ul):not(ol){border:1px solid #e5e7eb;border-radius:12px;padding:18px 22px;margin:0 0 14px;box-shadow:0 1px 2px rgba(0,0,0,.04)}.smpi-faq03 .smpi-post-faqs-content{counter-reset:f}.smpi-faq03 .smpi-post-faqs-content>ul>li,.smpi-faq03 .smpi-post-faqs-content>ol>li,.smpi-faq03 .smpi-post-faqs-content>:not(ul):not(ol){counter-increment:f;position:relative;padding:12px 0 12px 34px;border-bottom:1px solid #e5e7eb}.smpi-faq03 .smpi-post-faqs-content>ul>li:before,.smpi-faq03 .smpi-post-faqs-content>ol>li:before,.smpi-faq03 .smpi-post-faqs-content>:not(ul):not(ol):before{content:counter(f,decimal-leading-zero);position:absolute;left:0;top:14px;font-size:1rem;font-weight:800;color:var(--smpi-faq-accent,#2563eb);line-height:1}.smpi-faq04 .smpi-post-faqs-content>ul>li,.smpi-faq04 .smpi-post-faqs-content>ol>li,.smpi-faq04 .smpi-post-faqs-content>:not(ul):not(ol){background:#f8fafc;border:1px solid #eef2f7;border-radius:12px;margin-bottom:10px;padding:16px 20px}";
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
        $fig = "body.single-post .elementor-widget-theme-post-content figure,body.single-post .entry-content figure,body.single-post .wp-caption,body.single-press-release .elementor-widget-theme-post-content figure,body.single-press-release .entry-content figure,body.single-press-release .wp-caption";
        $img = ".elementor-widget-theme-post-content figure img,.entry-content figure img,.wp-caption img";
        $cap = ".elementor-widget-theme-post-content figure figcaption,.entry-content figure figcaption,.wp-caption .wp-caption-text";
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
        $img = ".smpi-featured-image-caption img";
        $cap = ".smpi-featured-image-caption figcaption,.smpi-featured-image-caption .smpi-featured-image-caption-text";
        return self::featured_image_caption_rules( $style, $host, $img, $cap );
    }

    /* ---------------------------------------------------------------------
     * Admin design previews use the SAME css strings, scoped to the preview
     * containers. This is the one-source-of-truth bundle.
     * ------------------------------------------------------------------- */
    public static function preview_bundle_css(): string {
        $css = self::breadcrumbs_css() . self::toc_css() . self::post_acf_css();
        foreach ( [ "fig1", "fig2", "fig4", "fig5" ] as $style ) {
            $sel = ".smpi-pp.smpi-pp-" . $style;
            $css .= self::inline_photo_rules( $style, $sel, $sel . " img", $sel . " figcaption" );
        }
        foreach ( [ "fig1", "fig2", "fig4", "fig5" ] as $style ) {
            $sel = ".smpi-fi-preview.smpi-fi-preview-" . $style;
            $css .= self::featured_image_caption_rules( $style, $sel, $sel . " img", $sel . " .smpi-featured-image-caption-text" );
        }
        // Current setting values become CSS variables on the host so every
        // preview reflects the live controls; JS updates these for real time.
        $b = self::breadcrumb_var_values();
        $t = self::toc_var_values();
        $f = self::faq_var_values();
        $p = self::photo_var_values();
        $fp = self::featured_image_var_values();
        $css .= ".smpi-design-host{--smpi-bc-accent:" . $b["accent"] . ";--smpi-bc-tint:" . $b["tint"] . ";--smpi-bc-font-size:" . $b["size"] . ";--smpi-toc-accent:" . $t["accent"] . ";--smpi-toc-text:" . $t["text"] . ";--smpi-toc-size:" . $t["size"] . ";--smpi-toc-fstyle:" . $t["fstyle"] . ";--smpi-faq-accent:" . $f["accent"] . ";--smpi-faq-text:" . $f["text"] . ";--smpi-faq-size:" . $f["size"] . ";--smpi-faq-fstyle:" . $f["fstyle"] . ";--smpi-photo-accent:" . $p["accent"] . ";--smpi-photo-cap-color:" . $p["color"] . ";--smpi-photo-cap-size:" . $p["size"] . ";--smpi-photo-cap-fstyle:" . $p["fstyle"] . "}";
        // Keep previews contained inside the small sample cards.
        $css .= ".smpi-choice-preview .smpi-breadcrumbs,.smpi-choice-preview .smpi-table-of-contents,.smpi-choice-preview .smpi-post-summary,.smpi-choice-preview .smpi-post-faqs,.smpi-choice-preview .smpi-pp,.smpi-choice-preview .smpi-fi-preview{max-width:100%!important;margin:0!important}.smpi-choice-preview .smpi-pp,.smpi-choice-preview .smpi-fi-preview{display:block}.smpi-choice-preview .smpi-pp img,.smpi-choice-preview .smpi-fi-preview img{height:120px;width:100%;object-fit:cover}.smpi-choice-preview .smpi-table-of-contents a,.smpi-choice-preview .smpi-post-faqs-content>ul>li,.smpi-choice-preview .smpi-post-faqs-content>ol>li{font-size:13px}";
        return $css;
    }
}
