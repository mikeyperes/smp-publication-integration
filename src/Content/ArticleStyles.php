<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class ArticleStyles {
    public function register(): void {
        add_action( "wp_head", [ $this, "print_styles" ], 34 );
    }

    public function print_styles(): void {
        $needs = Settings::bool( "inline_photo_treatments_enabled" ) || Settings::bool( "post_summary_acf_enabled" ) || Settings::bool( "post_faqs_acf_enabled" ) || Settings::bool( "table_of_contents_enabled" );
        if ( ! $needs ) {
            return;
        }
        $photo = self::normalize_inline_photo_style( (string) Settings::get( "inline_photo_treatment", "none" ) );
        echo "<style id=smpi-article-style-controls>" . self::toc_css() . self::post_acf_css() . self::inline_photo_css( $photo ) . "</style>";
    }

    public static function normalize_toc_style( string $style = "" ): string {
        $style = sanitize_key( "" !== $style ? $style : (string) Settings::get( "table_of_contents_style", "toc02" ) );
        return in_array( $style, [ "none", "toc00", "toc01", "toc02", "toc03", "toc04" ], true ) ? $style : "toc02";
    }

    public static function normalize_inline_photo_style( string $style = "" ): string {
        $style = sanitize_key( "" !== $style ? $style : (string) Settings::get( "inline_photo_treatment", "none" ) );
        return in_array( $style, [ "none", "fig1", "fig2", "fig4", "fig5" ], true ) ? $style : "none";
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
        $title = "sum03" === $style ? "The Brief" : ( in_array( $style, [ "sum01", "sum02", "sum04" ], true ) ? "What to know" : "Summary" );
        return "<aside class=\"smpi-post-summary " . esc_attr( "smpi-" . $style ) . "\"><h2>" . esc_html( $title ) . "</h2><div class=\"smpi-post-summary-content\">" . wp_kses_post( $html ) . "</div></aside>";
    }

    public static function wrap_post_faqs( string $html, string $style = "" ): string {
        if ( "" === trim( $html ) ) {
            return "";
        }
        $style = self::normalize_faq_style( $style );
        if ( "none" === $style ) {
            return $html;
        }
        $title = "faq04" === $style ? "People also ask" : "Frequently asked questions";
        return "<section class=\"smpi-post-faqs " . esc_attr( "smpi-" . $style ) . "\"><h2>" . esc_html( $title ) . "</h2><div class=\"smpi-post-faqs-content\">" . wp_kses_post( $html ) . "</div></section>";
    }


    private static function design_color( string $key, string $default ): string {
        $color = sanitize_hex_color( (string) Settings::get( $key, $default ) );
        return $color ?: $default;
    }

    private static function design_font_size( string $key, int $default ): int {
        $value = absint( Settings::get( $key, $default ) );
        return max( 8, min( 64, $value ?: $default ) );
    }

    private static function design_font_style( string $key, string $default = "normal" ): string {
        $value = (string) Settings::get( $key, $default );
        return "italic" === $value ? "italic" : "normal";
    }

    public static function toc_css(): string {
        $accent = self::design_color( "table_of_contents_accent_color", "#2563eb" );
        $text_color = self::design_color( "table_of_contents_text_color", "#1f2937" );
        $font_size = self::design_font_size( "table_of_contents_text_font_size", 15 );
        $font_style = self::design_font_style( "table_of_contents_text_font_style", "normal" );
        return ".smpi-table-of-contents{max-width:var(--content-width,720px);margin:0}.smpi-table-of-contents .smpi-toc-label{margin:0 0 12px}.smpi-table-of-contents ol{margin:0;padding:0}.smpi-table-of-contents a{text-decoration:none}.smpi-toc-none ol{padding-left:20px}.smpi-toc00{max-width:520px}.smpi-toc00 .smpi-toc-label{font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af}.smpi-toc00 ol{list-style:none;display:grid;gap:10px}.smpi-toc00 a{color:#111827;border-bottom:1px solid transparent}.smpi-toc00 a:hover{color:#2563eb;border-color:#2563eb}.smpi-toc01{max-width:520px;border-left:2px solid #e5e7eb;padding:2px 0 2px 20px}.smpi-toc01 .smpi-toc-label{font-size:.82rem;font-weight:800;color:#0a0a0a}.smpi-toc01 ol{list-style:none;display:grid;gap:11px}.smpi-toc01 a{color:#52525b}.smpi-toc01 a:hover{color:#2563eb}.smpi-toc02{background:#f7f8f9;border-radius:12px;padding:20px 24px;max-width:560px}.smpi-toc02 .smpi-toc-label{font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#6b7280}.smpi-toc02 ol{list-style:none;display:grid;gap:12px;counter-reset:t}.smpi-toc02 li{counter-increment:t;display:flex;gap:12px;align-items:baseline}.smpi-toc02 li:before{content:counter(t,decimal-leading-zero);color:#c0c6cf;font-weight:700;font-size:.85rem}.smpi-toc02 a{color:#1f2937}.smpi-toc02 a:hover{color:#2563eb}.smpi-toc03{max-width:var(--content-width,720px);padding:18px 0;border-top:1px solid #ececec;border-bottom:1px solid #ececec}.smpi-toc03 .smpi-toc-label{font-size:.82rem;font-weight:800;color:#0a0a0a;margin-bottom:4px}.smpi-toc03 ol{list-style:none;counter-reset:t}.smpi-toc03 li{counter-increment:t;border-top:1px solid #eceef1}.smpi-toc03 a{display:flex;gap:12px;align-items:baseline;padding:8px 2px;color:#111827}.smpi-toc03 a:before{content:counter(t,decimal-leading-zero);color:#2563eb;font-weight:800;font-size:.82rem}.smpi-toc03 a:hover{color:#2563eb}.smpi-toc04{display:flex;flex-wrap:wrap;align-items:center;gap:8px}.smpi-toc04 .smpi-toc-label{font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af;margin-right:4px}.smpi-toc04 a{color:#374151;font-size:.9rem;border:1px solid #e5e7eb;border-radius:999px;padding:6px 14px;background:#fff;line-height:1.3}.smpi-toc04 a:hover{border-color:#2563eb;color:#2563eb}" . ".smpi-table-of-contents{color:" . $text_color . ";font-size:" . $font_size . "px;font-style:" . $font_style . "}.smpi-table-of-contents a,.smpi-table-of-contents .smpi-toc-label{color:" . $accent . "}.smpi-toc01{border-left-color:" . $accent . "}.smpi-toc02 li:before,.smpi-toc03 a:before{color:" . $accent . "}.smpi-toc04 a{border-color:" . $accent . ";color:" . $text_color . "}.smpi-toc04 a:hover{color:" . $accent . "}";
    }

    public static function post_acf_css(): string {
        $faq_accent = self::design_color( "post_faqs_accent_color", "#2563eb" );
        $faq_text_color = self::design_color( "post_faqs_text_color", "#1f2937" );
        $faq_font_size = self::design_font_size( "post_faqs_text_font_size", 16 );
        $faq_font_style = self::design_font_style( "post_faqs_text_font_style", "normal" );
        return ".smpi-post-summary{max-width:var(--content-width,720px);margin:0}.smpi-post-faqs{max-width:var(--content-width,720px);margin:2rem auto}.smpi-sum00{background:#f5f6f7;padding:26px 32px}.smpi-sum00 h2{margin:0;font-size:1.3rem;font-weight:800;color:#1f2937;display:inline-block;padding-bottom:8px;border-bottom:3px solid #111827}.smpi-sum00 .smpi-post-summary-content{margin-top:18px}.smpi-sum01{border-left:4px solid #2563eb;padding:2px 0 2px 22px}.smpi-sum01 .smpi-post-summary-content{font-size:15px}.smpi-sum01 li{margin-bottom:9px;line-height:1.45}.smpi-sum01 li:last-child{margin-bottom:0}.smpi-sum01 h2{margin:0 0 10px;font-size:.78rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#2563eb}.smpi-sum02{padding:18px 0;border-top:2px solid #0a0a0a;border-bottom:1px solid #e5e7eb}.smpi-sum02 h2{margin:0 0 12px;font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#0a0a0a}.smpi-sum03{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.smpi-sum03 h2{margin:0;background:#0a0a0a;color:#fff;padding:12px 22px;font-size:.85rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.smpi-sum03 .smpi-post-summary-content{padding:18px 22px}.smpi-sum04{background:#eff4ff;border-radius:14px;padding:24px 28px}.smpi-sum04 h2{margin:0 0 14px;font-size:1.05rem;font-weight:800;color:#1e3a8a;display:flex;align-items:center;gap:9px}.smpi-sum04 h2:before{content:\"\";width:18px;height:18px;border-radius:5px;background:#2563eb}.smpi-post-summary ul,.smpi-post-summary ol{margin:0;padding-left:1.2rem}.smpi-faq00 h2,.smpi-faq01 h2,.smpi-faq02 h2,.smpi-faq03 h2,.smpi-faq04 h2{font-size:1.4rem;font-weight:800;color:#0a0a0a;margin:0 0 14px}.smpi-post-faqs-content>ul,.smpi-post-faqs-content>ol{list-style:none;margin:0;padding:0}.smpi-faq00 .smpi-post-faqs-content,.smpi-faq01 .smpi-post-faqs-content{border-top:1px solid #e5e7eb}.smpi-faq00 .smpi-post-faqs-content>ul>li,.smpi-faq00 .smpi-post-faqs-content>ol>li,.smpi-faq00 .smpi-post-faqs-content>:not(ul):not(ol),.smpi-faq01 .smpi-post-faqs-content>ul>li,.smpi-faq01 .smpi-post-faqs-content>ol>li,.smpi-faq01 .smpi-post-faqs-content>:not(ul):not(ol){border-bottom:1px solid #e5e7eb;padding:16px 0;margin:0}.smpi-faq02 .smpi-post-faqs-content>ul>li,.smpi-faq02 .smpi-post-faqs-content>ol>li,.smpi-faq02 .smpi-post-faqs-content>:not(ul):not(ol){border:1px solid #e5e7eb;border-radius:12px;padding:18px 22px;margin:0 0 14px;box-shadow:0 1px 2px rgba(0,0,0,.04)}.smpi-faq03 .smpi-post-faqs-content{counter-reset:f}.smpi-faq03 .smpi-post-faqs-content>ul>li,.smpi-faq03 .smpi-post-faqs-content>ol>li,.smpi-faq03 .smpi-post-faqs-content>:not(ul):not(ol){counter-increment:f;position:relative;padding:18px 0 18px 52px;border-bottom:1px solid #e5e7eb}.smpi-faq03 .smpi-post-faqs-content>ul>li:before,.smpi-faq03 .smpi-post-faqs-content>ol>li:before,.smpi-faq03 .smpi-post-faqs-content>:not(ul):not(ol):before{content:counter(f,decimal-leading-zero);position:absolute;left:0;top:18px;font-size:1.7rem;font-weight:800;color:#c7d6ff;line-height:1}.smpi-faq04 .smpi-post-faqs-content>ul>li,.smpi-faq04 .smpi-post-faqs-content>ol>li,.smpi-faq04 .smpi-post-faqs-content>:not(ul):not(ol){background:#f8fafc;border:1px solid #eef2f7;border-radius:12px;margin-bottom:10px;padding:16px 20px}" . ".smpi-post-faqs,.smpi-post-faqs .smpi-post-faqs-content,.smpi-post-faqs .smpi-post-faqs-content p,.smpi-post-faqs .smpi-post-faqs-content li{color:" . $faq_text_color . ";font-size:" . $faq_font_size . "px;font-style:" . $faq_font_style . "}.smpi-post-faqs h2,.smpi-post-faqs .smpi-post-faqs-content strong{color:" . $faq_accent . "!important}.smpi-faq00 .smpi-post-faqs-content,.smpi-faq01 .smpi-post-faqs-content{border-top-color:" . $faq_accent . "}.smpi-faq02 .smpi-post-faqs-content>ul>li,.smpi-faq02 .smpi-post-faqs-content>ol>li,.smpi-faq02 .smpi-post-faqs-content>:not(ul):not(ol),.smpi-faq04 .smpi-post-faqs-content>ul>li,.smpi-faq04 .smpi-post-faqs-content>ol>li,.smpi-faq04 .smpi-post-faqs-content>:not(ul):not(ol){border-left:3px solid " . $faq_accent . "}.smpi-faq03 .smpi-post-faqs-content>ul>li:before,.smpi-faq03 .smpi-post-faqs-content>ol>li:before,.smpi-faq03 .smpi-post-faqs-content>:not(ul):not(ol):before{color:" . $faq_accent . "}";
    }

    public static function inline_photo_css( string $style ): string {
        if ( "none" === $style || ! Settings::bool( "inline_photo_treatments_enabled" ) ) {
            return "";
        }

        $accent = self::design_color( "inline_photo_accent_color", "#d63428" );
        $caption_color = self::design_color( "inline_photo_caption_text_color", "#272727" );
        $caption_size = self::design_font_size( "inline_photo_caption_font_size", 16 );
        $caption_style = self::design_font_style( "inline_photo_caption_font_style", "italic" );
        $scope = "body.single-post .elementor-widget-theme-post-content figure,body.single-post .entry-content figure,body.single-post .wp-caption,body.single-press-release .elementor-widget-theme-post-content figure,body.single-press-release .entry-content figure,body.single-press-release .wp-caption";
        $image = "body.single-post .elementor-widget-theme-post-content figure img,body.single-post .entry-content figure img,body.single-post .wp-caption img,body.single-press-release .elementor-widget-theme-post-content figure img,body.single-press-release .entry-content figure img,body.single-press-release .wp-caption img";
        $caption = "body.single-post .elementor-widget-theme-post-content figure figcaption,body.single-post .entry-content figure figcaption,body.single-post .wp-caption .wp-caption-text,body.single-press-release .elementor-widget-theme-post-content figure figcaption,body.single-press-release .entry-content figure figcaption,body.single-press-release .wp-caption .wp-caption-text";
        $caption_base = $caption . "{font-family:Georgia,serif;font-style:" . $caption_style . ";font-size:" . $caption_size . "px;color:" . $caption_color . ";line-height:1.5}";

        if ( "fig1" === $style ) {
            return $scope . "{margin:2.5rem auto}" . $image . "{width:100%;height:auto;display:block}" . $caption_base . $caption . "{margin-top:16px;padding-left:16px;border-left:3px solid " . $accent . "}";
        }
        if ( "fig2" === $style ) {
            return $scope . "{margin:2.5rem auto;border-radius:6px;overflow:hidden}" . $image . "{width:100%;display:block;margin:0}" . $caption_base . $caption . "{margin:0;background:#fafafa;border-top:3px solid " . $accent . ";padding:16px 20px}";
        }
        if ( "fig4" === $style ) {
            return $scope . "{margin:2.5rem auto;position:relative;border-radius:16px;overflow:hidden}" . $image . "{width:100%;display:block}" . $caption_base . $caption . "{position:absolute;left:0;right:0;bottom:0;padding:54px 22px 18px;background:linear-gradient(to top,rgba(10,10,12,.85),rgba(10,10,12,0))}";
        }
        return $scope . "{margin:2.5rem auto;border:1px solid " . $accent . ";border-radius:18px;padding:14px;background:#fff;box-shadow:0 20px 44px -30px rgba(15,15,15,.4)}" . $image . "{width:100%;display:block;border-radius:10px}" . $caption_base . $caption . "{display:block;padding:16px 6px 4px}";
    }

}
