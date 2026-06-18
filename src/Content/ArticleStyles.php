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

    public static function toc_css(): string {
        return ".smpi-table-of-contents{max-width:var(--content-width,720px);margin:0}.smpi-table-of-contents .smpi-toc-label{margin:0 0 12px}.smpi-table-of-contents ol{margin:0;padding:0}.smpi-table-of-contents a{text-decoration:none}.smpi-toc-none ol{padding-left:20px}.smpi-toc00{max-width:520px}.smpi-toc00 .smpi-toc-label{font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af}.smpi-toc00 ol{list-style:none;display:grid;gap:10px}.smpi-toc00 a{color:#111827;border-bottom:1px solid transparent}.smpi-toc00 a:hover{color:#2563eb;border-color:#2563eb}.smpi-toc01{max-width:520px;border-left:2px solid #e5e7eb;padding:2px 0 2px 20px}.smpi-toc01 .smpi-toc-label{font-size:.82rem;font-weight:800;color:#0a0a0a}.smpi-toc01 ol{list-style:none;display:grid;gap:11px}.smpi-toc01 a{color:#52525b}.smpi-toc01 a:hover{color:#2563eb}.smpi-toc02{background:#f7f8f9;border-radius:12px;padding:20px 24px;max-width:560px}.smpi-toc02 .smpi-toc-label{font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#6b7280}.smpi-toc02 ol{list-style:none;display:grid;gap:12px;counter-reset:t}.smpi-toc02 li{counter-increment:t;display:flex;gap:12px;align-items:baseline}.smpi-toc02 li:before{content:counter(t,decimal-leading-zero);color:#c0c6cf;font-weight:700;font-size:.85rem}.smpi-toc02 a{color:#1f2937}.smpi-toc02 a:hover{color:#2563eb}.smpi-toc03{max-width:var(--content-width,720px);padding:18px 0;border-top:1px solid #ececec;border-bottom:1px solid #ececec}.smpi-toc03 .smpi-toc-label{font-size:.82rem;font-weight:800;color:#0a0a0a;margin-bottom:4px}.smpi-toc03 ol{list-style:none;counter-reset:t}.smpi-toc03 li{counter-increment:t;border-top:1px solid #eceef1}.smpi-toc03 a{display:flex;gap:12px;align-items:baseline;padding:8px 2px;color:#111827}.smpi-toc03 a:before{content:counter(t,decimal-leading-zero);color:#2563eb;font-weight:800;font-size:.82rem}.smpi-toc03 a:hover{color:#2563eb}.smpi-toc04{display:flex;flex-wrap:wrap;align-items:center;gap:8px}.smpi-toc04 .smpi-toc-label{font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af;margin-right:4px}.smpi-toc04 a{color:#374151;font-size:.9rem;border:1px solid #e5e7eb;border-radius:999px;padding:6px 14px;background:#fff;line-height:1.3}.smpi-toc04 a:hover{border-color:#2563eb;color:#2563eb}";
    }

    public static function post_acf_css(): string {
        return ".smpi-post-summary{max-width:var(--content-width,720px);margin:0}.smpi-post-faqs{max-width:var(--content-width,720px);margin:2rem auto}.smpi-sum00{background:#f5f6f7;padding:26px 32px}.smpi-sum00 h2{margin:0;font-size:1.3rem;font-weight:800;color:#1f2937;display:inline-block;padding-bottom:8px;border-bottom:3px solid #111827}.smpi-sum00 .smpi-post-summary-content{margin-top:18px}.smpi-sum01{border-left:4px solid #2563eb;padding:2px 0 2px 22px}.smpi-sum01 .smpi-post-summary-content{font-size:15px}.smpi-sum01 li{margin-bottom:9px;line-height:1.45}.smpi-sum01 li:last-child{margin-bottom:0}.smpi-sum01 h2{margin:0 0 10px;font-size:.78rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#2563eb}.smpi-sum02{padding:18px 0;border-top:2px solid #0a0a0a;border-bottom:1px solid #e5e7eb}.smpi-sum02 h2{margin:0 0 12px;font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#0a0a0a}.smpi-sum03{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.smpi-sum03 h2{margin:0;background:#0a0a0a;color:#fff;padding:12px 22px;font-size:.85rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.smpi-sum03 .smpi-post-summary-content{padding:18px 22px}.smpi-sum04{background:#eff4ff;border-radius:14px;padding:24px 28px}.smpi-sum04 h2{margin:0 0 14px;font-size:1.05rem;font-weight:800;color:#1e3a8a;display:flex;align-items:center;gap:9px}.smpi-sum04 h2:before{content:\"\";width:18px;height:18px;border-radius:5px;background:#2563eb}.smpi-post-summary ul,.smpi-post-summary ol{margin:0;padding-left:1.2rem}.smpi-faq00 h2,.smpi-faq01 h2,.smpi-faq02 h2,.smpi-faq03 h2,.smpi-faq04 h2{font-size:1.4rem;font-weight:800;color:#0a0a0a;margin:0 0 14px}.smpi-post-faqs-content>ul,.smpi-post-faqs-content>ol{list-style:none;margin:0;padding:0}.smpi-faq00 .smpi-post-faqs-content,.smpi-faq01 .smpi-post-faqs-content{border-top:1px solid #e5e7eb}.smpi-faq00 .smpi-post-faqs-content>ul>li,.smpi-faq00 .smpi-post-faqs-content>ol>li,.smpi-faq00 .smpi-post-faqs-content>:not(ul):not(ol),.smpi-faq01 .smpi-post-faqs-content>ul>li,.smpi-faq01 .smpi-post-faqs-content>ol>li,.smpi-faq01 .smpi-post-faqs-content>:not(ul):not(ol){border-bottom:1px solid #e5e7eb;padding:16px 0;margin:0}.smpi-faq02 .smpi-post-faqs-content>ul>li,.smpi-faq02 .smpi-post-faqs-content>ol>li,.smpi-faq02 .smpi-post-faqs-content>:not(ul):not(ol){border:1px solid #e5e7eb;border-radius:12px;padding:18px 22px;margin:0 0 14px;box-shadow:0 1px 2px rgba(0,0,0,.04)}.smpi-faq03 .smpi-post-faqs-content{counter-reset:f}.smpi-faq03 .smpi-post-faqs-content>ul>li,.smpi-faq03 .smpi-post-faqs-content>ol>li,.smpi-faq03 .smpi-post-faqs-content>:not(ul):not(ol){counter-increment:f;position:relative;padding:18px 0 18px 52px;border-bottom:1px solid #e5e7eb}.smpi-faq03 .smpi-post-faqs-content>ul>li:before,.smpi-faq03 .smpi-post-faqs-content>ol>li:before,.smpi-faq03 .smpi-post-faqs-content>:not(ul):not(ol):before{content:counter(f,decimal-leading-zero);position:absolute;left:0;top:18px;font-size:1.7rem;font-weight:800;color:#c7d6ff;line-height:1}.smpi-faq04 .smpi-post-faqs-content>ul>li,.smpi-faq04 .smpi-post-faqs-content>ol>li,.smpi-faq04 .smpi-post-faqs-content>:not(ul):not(ol){background:#f8fafc;border:1px solid #eef2f7;border-radius:12px;margin-bottom:10px;padding:16px 20px}";
    }

    public static function inline_photo_css( string $style ): string {
        if ( "none" === $style || ! Settings::bool( "inline_photo_treatments_enabled" ) ) {
            return "";
        }
        $scope = "body.single-post .elementor-widget-theme-post-content figure,body.single-post .entry-content figure,body.single-post .wp-caption,body.single-press-release .elementor-widget-theme-post-content figure,body.single-press-release .entry-content figure,body.single-press-release .wp-caption";
        if ( "fig1" === $style ) {
            return $scope . "{margin:2.5rem auto}.elementor-widget-theme-post-content figure img,.entry-content figure img,.wp-caption img{width:100%;height:auto;display:block}.elementor-widget-theme-post-content figure figcaption,.entry-content figure figcaption,.wp-caption .wp-caption-text{margin-top:16px;padding-left:16px;border-left:3px solid #d63428;font-family:Georgia,serif;font-style:italic;font-size:17px;color:#1f1f1f;line-height:1.5}";
        }
        if ( "fig2" === $style ) {
            return $scope . "{margin:2.5rem auto;border-radius:6px;overflow:hidden}.elementor-widget-theme-post-content figure img,.entry-content figure img,.wp-caption img{width:100%;display:block;margin:0}.elementor-widget-theme-post-content figure figcaption,.entry-content figure figcaption,.wp-caption .wp-caption-text{margin:0;background:#fafafa;border-top:3px solid #d63428;padding:16px 20px;font-family:Georgia,serif;font-style:italic;font-size:13px;color:#272727}";
        }
        if ( "fig4" === $style ) {
            return $scope . "{margin:2.5rem auto;position:relative;border-radius:16px;overflow:hidden}.elementor-widget-theme-post-content figure img,.entry-content figure img,.wp-caption img{width:100%;display:block}.elementor-widget-theme-post-content figure figcaption,.entry-content figure figcaption,.wp-caption .wp-caption-text{position:absolute;left:0;right:0;bottom:0;padding:54px 22px 18px;color:#fff;font-family:Georgia,serif;font-style:italic;font-size:17px;background:linear-gradient(to top,rgba(10,10,12,.85),rgba(10,10,12,0))}";
        }
        return $scope . "{margin:2.5rem auto;border:1px solid #e9e9e9;border-radius:18px;padding:14px;background:#fff;box-shadow:0 20px 44px -30px rgba(15,15,15,.4)}.elementor-widget-theme-post-content figure img,.entry-content figure img,.wp-caption img{width:100%;display:block;border-radius:10px}.elementor-widget-theme-post-content figure figcaption,.entry-content figure figcaption,.wp-caption .wp-caption-text{display:block;padding:16px 6px 4px;font-family:Georgia,serif;font-style:italic;font-size:16.5px;color:#272727}";
    }
}
