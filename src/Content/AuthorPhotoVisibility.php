<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Authorship\AuthorPhoto;
use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

/** Remove the portrait and its empty layout wrappers, preserving all author text. */
final class AuthorPhotoVisibility {
    private bool $buffering = false;
    public function register(): void {
        add_filter( 'get_avatar', [ $this, 'mark_avatar' ], 100, 6 );
        add_action( 'template_redirect', [ $this, 'start_buffer' ], 0 );
        add_filter( 'elementor/widget/render_content', [ $this, 'filter_html' ], 100 );
        add_filter( 'elementor/frontend/the_content', [ $this, 'filter_html' ], 100 );
    }

    public function mark_avatar( $html, $id_or_email, $size, $default, $alt, $args ): string {
        $user = is_numeric( $id_or_email ) ? get_user_by( 'id', (int) $id_or_email ) : null;
        if ( ! $user && $id_or_email instanceof \WP_User ) {
            $user = $id_or_email;
        }
        if ( ! $user && $id_or_email instanceof \WP_Comment ) {
            $user = get_user_by( 'id', (int) $id_or_email->user_id );
        }
        if ( ! $user && is_string( $id_or_email ) && strpos( $id_or_email, '@' ) !== false ) {
            $user = get_user_by( 'email', $id_or_email );
        }
        if ( $user && Settings::bool( 'hide_author_photo_when_missing' ) ) {
            return preg_replace( '/<img\b/', '<img data-smpi-author-id="' . (int) $user->ID . '"', (string) $html, 1 ) ?? (string) $html;
        }
        return (string) $html;
    }

    public function start_buffer(): void {
        if ( Settings::bool( 'hide_author_photo_when_missing' ) && RuntimeContext::is_public_dom_context() && ! is_feed() && ! is_trackback() && ! is_robots() ) {
            $this->buffering = true;
            ob_start( [ $this, 'render_html' ] );
        }
    }

    public function filter_html( string $html ): string {
        return $this->buffering ? $html : $this->render_html( $html );
    }

    public function render_html( string $html ): string {
        if ( ! Settings::bool( 'hide_author_photo_when_missing' ) || ! RuntimeContext::is_public_dom_context() || stripos( $html, '<img' ) === false || ! class_exists( '\DOMDocument' ) ) {
            return $html;
        }
        $full = stripos( $html, '<html' ) !== false;
        $doc = new \DOMDocument( '1.0', 'UTF-8' );
        $previous = libxml_use_internal_errors( true );
        $loaded = $doc->loadHTML( '<?xml encoding="UTF-8">' . ( $full ? $html : '<html><body>' . $html . '</body></html>' ), LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( ! $loaded ) {
            return $html;
        }
        $changed = false;
        foreach ( iterator_to_array( $doc->getElementsByTagName( 'img' ) ) as $image ) {
            $id = (int) $image->getAttribute( 'data-smpi-author-id' );
            $is_author = (bool) preg_match( '/(?:^|\s)(?:avatar|elementor-avatar|smpi-author-image|smpi-author-listing-image)(?:\s|$)/', $image->getAttribute( 'class' ) );
            for ( $node = $image->parentNode; ! $id && $node instanceof \DOMElement && $node->tagName !== 'body'; $node = $node->parentNode ) {
                $id = (int) ( $node->getAttribute( 'data-smpi-author-id' ) ?: $node->getAttribute( 'data-author-id' ) );
                if ( $node->tagName === 'a' ) {
                    $id = $id ?: $this->author_from_url( $node->getAttribute( 'href' ) );
                }
                if ( ! $id && preg_match( '/(?:author|byline)/i', $node->getAttribute( 'class' ) ) ) {
                    $is_author = true;
                    foreach ( $node->getElementsByTagName( 'a' ) as $link ) {
                        $id = $this->author_from_url( $link->getAttribute( 'href' ) );
                        if ( $id ) { break; }
                    }
                }
            }
            if ( ! $id && $is_author ) {
                $post = get_post();
                $id = $post instanceof \WP_Post ? (int) $post->post_author : 0;
            }
            if ( ! $id || AuthorPhoto::has_photo( $id ) ) {
                continue;
            }
            $changed = true;
            $node = $image;
            // Empty portrait links, fixed-width widgets and image-only columns disappear too.
            while ( $node->parentNode instanceof \DOMElement ) {
                $parent = $node->parentNode;
                $parent->removeChild( $node );
                if ( in_array( $parent->tagName, [ 'body', 'html' ], true ) || trim( $parent->textContent ) !== '' || $parent->getElementsByTagName( 'img' )->length || $parent->getElementsByTagName( 'svg' )->length || $parent->getElementsByTagName( 'input' )->length ) {
                    break;
                }
                // Only climb wrappers that became empty, never a neighboring visual widget.
                if ( $parent->getElementsByTagName( 'video' )->length || $parent->getElementsByTagName( 'iframe' )->length ) { break; }
                $node = $parent;
            }
        }
        if ( ! $changed ) { return $html; }
        if ( $full ) {
            foreach ( iterator_to_array( $doc->childNodes ) as $child ) {
                if ( $child instanceof \DOMProcessingInstruction ) { $doc->removeChild( $child ); }
            }
            return $doc->saveHTML() ?: $html;
        }
        $body = $doc->getElementsByTagName( 'body' )->item( 0 );
        $result = '';
        foreach ( $body->childNodes as $child ) { $result .= $doc->saveHTML( $child ); }
        return $result;
    }

    private function author_from_url( string $url ): int {
        if ( ! preg_match( '~/author/([^/?#]+)~', $url, $match ) ) { return 0; }
        $user = get_user_by( 'slug', rawurldecode( $match[1] ) );
        return $user ? (int) $user->ID : 0;
    }
}
