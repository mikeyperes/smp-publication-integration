<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class InlinePhotoTreatments {
    private const POST_TYPES = [ "post", "press-release" ];

    public function register(): void {
        add_filter( "the_content", [ $this, "filter_content" ], 18 );
    }

    public function filter_content( string $html ): string {
        if ( ! $this->should_apply() || "" === trim( $html ) ) {
            return $html;
        }
        $style = ArticleStyles::normalize_inline_photo_style( (string) Settings::get( "inline_photo_treatment", "none" ) );
        if ( "none" === $style ) {
            return $html;
        }
        return self::normalize_legacy_caption_markup( $html, $style );
    }

    public static function normalize_legacy_caption_markup( string $html, string $style ): string {
        if ( "" === trim( $html ) || false === stripos( $html, "<img" ) ) {
            return $html;
        }
        $style = ArticleStyles::normalize_inline_photo_style( $style );
        if ( "none" === $style ) {
            return $html;
        }

        $caption_prefix = "(?:Photo(?:\\s+credit)?|Image(?:\\s+credit)?|Credit)";
        $image_pattern = "<img\\b(?=[^>]*\\bsrc=)[^>]*>";
        $patterns = [
            "~<p>\\s*(?:<b\\b[^>]*>\\s*)?(" . $image_pattern . ")\\s*(?:<br\\s*/?>\\s*)?(?:</b>\\s*)?(?:<br\\s*/?>\\s*)?<i\\b[^>]*>\\s*(?:<span\\b[^>]*>\\s*)?(" . $caption_prefix . "[^<]{3,500})\\s*(?:</span>\\s*)?</i>\\s*</p>~i",
            "~<p>\\s*<i\\b[^>]*>\\s*(?:<span\\b[^>]*>\\s*)?(" . $image_pattern . ")\\s*(?:<br\\s*/?>\\s*)?(" . $caption_prefix . "[^<]{3,500})\\s*(?:</span>\\s*)?</i>\\s*</p>~i",
        ];

        foreach ( $patterns as $pattern ) {
            $html = preg_replace_callback(
                $pattern,
                static function ( array $matches ) use ( $style ): string {
                    $image = trim( $matches[1] );
                    $caption = trim( wp_strip_all_tags( html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML5, "UTF-8" ) ) );
                    if ( "" === $image || "" === $caption ) {
                        return $matches[0];
                    }
                    return "<figure class=\"smpi-inline-photo smpi-inline-photo--" . esc_attr( $style ) . "\" data-smpi-inline-photo=\"legacy-caption\">" . $image . "<figcaption>" . esc_html( $caption ) . "</figcaption></figure>";
                },
                $html
            );
        }

        return is_string( $html ) ? $html : "";
    }

    private function should_apply(): bool {
        if ( ! RuntimeContext::is_public_dom_context() || ! Settings::bool( "inline_photo_treatments_enabled" ) ) {
            return false;
        }
        return is_singular( self::POST_TYPES );
    }
}
