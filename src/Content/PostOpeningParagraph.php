<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Elementor\PostOpeningParagraphTag;

defined( 'ABSPATH' ) || exit;

/** Read-only post field, independent of site type and article policy. */
final class PostOpeningParagraph {
    public const NAME = 'post_opening_paragraph';

    public function register(): void {
        add_action( 'init', [ $this, 'register_shortcode' ] );
        add_action( 'elementor/dynamic_tags/register', [ $this, 'register_dynamic_tag' ] );
    }

    public function register_shortcode(): void {
        add_shortcode( self::NAME, [ $this, 'shortcode' ] );
    }

    public function register_dynamic_tag( object $manager ): void {
        if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) || ! method_exists( $manager, 'register' ) ) {
            return;
        }
        $manager->register_group( ElementorPrimaryCategory::TAG_GROUP, [ 'title' => 'SMP Publication' ] );
        $manager->register( new PostOpeningParagraphTag() );
    }

    public function shortcode( $atts = [] ): string {
        $atts = shortcode_atts( [ 'post_id' => 0, 'word_limit' => 0 ], (array) $atts, self::NAME );
        return esc_html( self::text( max( 0, (int) $atts['post_id'] ), max( 0, (int) $atts['word_limit'] ) ) );
    }

    public static function text( int $post_id = 0, int $word_limit = 0 ): string {
        $post = get_post( $post_id > 0 ? $post_id : get_the_ID() );
        if ( ! $post || post_password_required( $post ) ) {
            return '';
        }
        $text = self::extract( (string) $post->post_content );
        return $word_limit > 0 ? wp_trim_words( $text, $word_limit, '…' ) : $text;
    }

    /** Never executes shortcodes, blocks, or the_content filters. */
    public static function extract( string $content ): string {
        $content = strip_shortcodes( $content );
        if ( preg_match_all( '@\[/?([a-zA-Z_][a-zA-Z0-9_-]*)\b@', $content, $tags ) ) {
            $content = preg_replace_callback( '/' . get_shortcode_regex( array_unique( $tags[1] ) ) . '/s', 'strip_shortcode_tag', $content );
        }
        $content = preg_replace( '/<!--.*?-->/s', '', $content );
        $content = preg_replace( '~<(script|style|figure|figcaption|h[1-6]|ul|ol|table|pre)\b[^>]*>.*?</\1\s*>~is', '', $content );
        preg_match_all( '~<p\b[^>]*>(.*?)</p\s*>~is', wpautop( $content ), $paragraphs );
        foreach ( $paragraphs[1] as $paragraph ) {
            $text = html_entity_decode( wp_strip_all_tags( $paragraph ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            $text = trim( (string) preg_replace( '/[\s\x{00a0}]+/u', ' ', $text ) );
            if ( '' !== $text ) {
                return $text;
            }
        }
        return '';
    }
}
