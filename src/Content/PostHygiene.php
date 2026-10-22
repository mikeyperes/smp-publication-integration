<?php
namespace smp_publication_integration\Content;

use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class PostHygiene {

    public function register(): void {
        add_filter( "wp_insert_post_data", [ $this, "sanitize_post_data" ], 20, 2 );
        ( new AjaxActionRegistry(
            [
                'capability'   => 'manage_options',
                'nonce_action' => Ajax::NONCE,
                'nonce_field'  => 'nonce',
            ]
        ) )->register(
            [
                'smpi_post_hygiene_preview' => [ 'callback' => [ $this, 'preview' ] ],
            ]
        );
    }



    public function sanitize_post_data( array $data, array $postarr ): array {
        if ( empty( $data["post_content"] ) || ! Settings::bool( "post_hygiene_enabled" ) ) {
            return $data;
        }

        $post_type = isset( $data["post_type"] ) ? sanitize_key( (string) $data["post_type"] ) : "";
        if ( ! $this->should_clean_post_type( $post_type ) ) {
            return $data;
        }

        $post_id = isset( $postarr["ID"] ) ? absint( $postarr["ID"] ) : 0;
        if ( $this->has_elementor_data( $post_id, $postarr ) ) {
            return $data;
        }

        $data["post_content"] = self::sanitize_html( (string) $data["post_content"] );
        return $data;
    }

    public static function sanitize_html( string $html, ?array $settings = null ): string {
        $settings = $settings ?: Settings::all();
        $comments = [];
        $html = preg_replace_callback(
            "/<!--.*?-->/s",
            static function( array $match ) use ( &$comments ): string {
                $token = "%%SMPI_COMMENT_" . count( $comments ) . "%%";
                $comments[ $token ] = $match[0];
                return $token;
            },
            $html
        );

        if ( ! empty( $settings["post_hygiene_remove_font_tags"] ) ) {
            $html = preg_replace( "/<\/?font\b[^>]*>/i", "", $html );
        }

        if ( ! empty( $settings["post_hygiene_clean_heading_children"] ) ) {
            $html = preg_replace_callback( "/<(h[1-6])\b([^>]*)>(.*?)<\/\\1>/is", static function( array $match ): string {
                $inner = preg_replace( "/<\/?(?:span|font)\b[^>]*>/i", "", $match[3] );
                return "<" . $match[1] . $match[2] . ">" . $inner . "</" . $match[1] . ">";
            }, $html );
        }

        if ( ! empty( $settings["post_hygiene_unwrap_spans"] ) ) {
            $html = preg_replace( "/<span\b[^>]*>/i", "", $html );
            $html = preg_replace( "/<\/span>/i", "", $html );
        }

        $html = preg_replace( "/\sdata-[a-z0-9_:-]+\s*=\s*(\"[^\"]*\"|[^\s>]+)/i", "", $html );

        if ( ! empty( $settings["post_hygiene_strip_inline_styles"] ) ) {
            $html = preg_replace( "/\sstyle\s*=\s*(\"[^\"]*\"|[^\s>]+)/i", "", $html );
        }

        if ( ! empty( $settings["post_hygiene_strip_classes_ids"] ) ) {
            $html = preg_replace( "/\s(?:class|id)\s*=\s*(\"[^\"]*\"|[^\s>]+)/i", "", $html );
        }

        $html = wp_kses( $html, self::allowed_html( $settings ) );

        if ( ! empty( $settings["post_hygiene_strip_empty_tags"] ) ) {
            $html = self::strip_empty_tags( $html );
        }

        foreach ( $comments as $token => $comment ) {
            $html = str_replace( $token, $comment, $html );
        }

        return trim( $html );
    }

    public function preview( AjaxRequest $request ): array {
        $html = $request->raw( 'html', '', 'post' );

        return [ 'html' => self::sanitize_html( is_scalar( $html ) ? (string) $html : '' ) ];
    }




    private function should_clean_post_type( string $post_type ): bool {
        if ( in_array( $post_type, [ "elementor_library", "revision", "nav_menu_item", "acf-field", "acf-field-group" ], true ) ) {
            return false;
        }
        return in_array( $post_type, Settings::array( "post_hygiene_allowed_post_types" ), true );
    }

    private function has_elementor_data( int $post_id, array $postarr ): bool {
        if ( isset( $postarr["meta_input"] ) && is_array( $postarr["meta_input"] ) && ! empty( $postarr["meta_input"]["_elementor_data"] ) ) {
            return true;
        }
        return $post_id > 0 && "" !== (string) get_post_meta( $post_id, "_elementor_data", true );
    }

    private static function allowed_html( array $settings ): array {
        $common = ! empty( $settings["post_hygiene_strip_classes_ids"] ) ? [] : [ "class" => true, "id" => true ];
        return [
            "p" => $common, "h1" => $common, "h2" => $common, "h3" => $common, "h4" => $common, "h5" => $common, "h6" => $common,
            "ul" => $common, "ol" => $common, "li" => $common, "blockquote" => $common, "strong" => $common, "b" => $common, "em" => $common, "i" => $common, "br" => [],
            "a" => array_merge( $common, [ "href" => true, "target" => true, "rel" => true, "title" => true ] ),
            "img" => array_merge( $common, [ "src" => true, "alt" => true, "width" => true, "height" => true, "loading" => true, "decoding" => true, "srcset" => true, "sizes" => true ] ),
            "figure" => $common, "figcaption" => $common,
            "table" => $common, "thead" => $common, "tbody" => $common, "tr" => $common, "th" => array_merge( $common, [ "scope" => true, "colspan" => true, "rowspan" => true ] ), "td" => array_merge( $common, [ "colspan" => true, "rowspan" => true ] ),
        ];
    }

    private static function strip_empty_tags( string $html ): string {
        $pattern = "/<(p|h[1-6]|li|blockquote|figcaption|th|td)>\s*(?:&nbsp;|\s)*<\/\\1>/i";
        do {
            $before = $html;
            $html = preg_replace( $pattern, "", $html );
        } while ( $before !== $html );
        return $html;
    }
}
