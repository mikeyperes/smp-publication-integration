<?php
namespace smp_publication_integration\Authorship;

use smp_publication_integration\Content\MuckRackVerification;
use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class LoopBylineRenderer {
    private AuthorAssignmentRepository $repository;

    public function __construct( AuthorAssignmentRepository $repository ) {
        $this->repository = $repository;
    }

    public function filter( string $html ): string {
        if (
            "" === trim( $html )
            || false !== strpos( $html, "smpi-multi-author-item" )
            || ! Settings::bool( "multi_authors_enabled" )
            || Settings::bool( "multi_authors_disable_loop_cards" )
            || ! RuntimeContext::has_article_loop_context()
            || false === stripos( $html, "/author/" )
        ) {
            return $html;
        }
        if ( is_author() && ! in_the_loop() ) {
            return $html;
        }

        $post = get_post();
        if ( ! $post instanceof \WP_Post || ! in_array( (string) $post->post_type, $this->repository->supported_post_types(), true ) ) {
            return $html;
        }
        if ( is_singular( $this->repository->supported_post_types() ) && (int) get_queried_object_id() === (int) $post->ID ) {
            return $html;
        }

        $selected = $this->repository->selected_ids_for_post( (int) $post->ID );
        if ( empty( $selected ) || ( 1 === count( $selected ) && (int) $selected[0] === (int) $post->post_author ) ) {
            return $html;
        }

        $records = $this->repository->records_for_post( (int) $post->ID, false );
        $source = ( new AuthorFieldResolver() )->record( (int) $post->post_author );
        if ( empty( $records ) || ! $source instanceof AuthorRecord ) {
            return $html;
        }

        $format = sanitize_key( (string) Settings::get( "multi_authors_loop_output", "comma" ) );
        if ( "primary" === $format ) {
            $records = [ $records[0] ];
        }

        return $this->replace_source_link( $html, $source, $records, $format );
    }

    private function replace_source_link( string $html, AuthorRecord $source, array $authors, string $format ): string {
        $doc = $this->document( $html );
        if ( ! $doc ) {
            return $html;
        }

        $source_data = $source->to_array();
        $links = iterator_to_array( $doc->getElementsByTagName( "a" ) );
        foreach ( $links as $link ) {
            if ( ! $link instanceof \DOMElement || ! $this->href_matches( $link->getAttribute( "href" ), $source_data ) ) {
                continue;
            }
            if ( $this->parent_contains_all_authors( $link, $authors ) ) {
                continue;
            }
            $fragment = $doc->createDocumentFragment();
            foreach ( $authors as $index => $author ) {
                if ( ! $author instanceof AuthorRecord ) {
                    continue;
                }
                if ( $index > 0 ) {
                    $fragment->appendChild(
                        "lines" === $format
                            ? $doc->createElement( "br" )
                            : $doc->createTextNode( ", " )
                    );
                }
                $data = $author->to_array();
                $author_link = $link->cloneNode( false );
                if ( ! $author_link instanceof \DOMElement ) {
                    continue;
                }
                $author_link->removeAttribute( "id" );
                $author_link->setAttribute( "href", (string) $data["url"] );
                $author_link->setAttribute( "data-smpi-author-id", (string) $data["id"] );
                $author_link->setAttribute( "data-smpi-author-slug", (string) $data["slug"] );
                $author_link->appendChild( $doc->createTextNode( (string) $data["name"] ) );
                $fragment->appendChild( $author_link );

                $badge = $this->badge_html( (int) $data["id"] );
                if ( "" !== $badge ) {
                    $badge_fragment = $this->fragment( $doc, " " . $badge );
                    if ( $badge_fragment ) {
                        $fragment->appendChild( $badge_fragment );
                    }
                }
            }
            if ( $link->parentNode ) {
                $link->parentNode->replaceChild( $fragment, $link );
            }
            break;
        }
        return $this->body_html( $doc, $html );
    }

    private function parent_contains_all_authors( \DOMElement $link, array $authors ): bool {
        $parent = $link->parentNode instanceof \DOMElement ? $link->parentNode : null;
        if ( ! $parent || ! $parent->ownerDocument ) {
            return false;
        }
        $html = $parent->ownerDocument->saveHTML( $parent ) ?: "";
        foreach ( $authors as $author ) {
            if ( ! $author instanceof AuthorRecord || ! $this->href_matches( $html, $author->to_array() ) ) {
                return false;
            }
        }
        return true;
    }

    private function href_matches( string $href, array $author ): bool {
        $href = html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, "UTF-8" );
        $slug = (string) ( $author["slug"] ?? "" );
        $url = (string) ( $author["url"] ?? "" );
        return ( "" !== $slug && false !== strpos( $href, "/author/" . $slug ) )
            || ( "" !== $url && untrailingslashit( $href ) === untrailingslashit( $url ) );
    }

    private function badge_html( int $user_id ): string {
        if (
            ! class_exists( MuckRackVerification::class )
            || ! Settings::bool( "muckrack_verified_enabled" )
            || ! in_array( "loop_cards", Settings::array( "muckrack_verified_contexts" ), true )
        ) {
            return "";
        }
        return MuckRackVerification::verification_icon(
            $user_id,
            (string) Settings::get( "muckrack_verified_style", "tooltip" ),
            "loop_cards"
        );
    }

    private function document( string $html ): ?\DOMDocument {
        $doc = new \DOMDocument( "1.0", "UTF-8" );
        $previous = libxml_use_internal_errors( true );
        $loaded = $doc->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        return $loaded ? $doc : null;
    }

    private function fragment( \DOMDocument $doc, string $html ): ?\DOMDocumentFragment {
        $tmp = $this->document( $html );
        if ( ! $tmp ) {
            return null;
        }
        $body = $tmp->getElementsByTagName( "body" )->item( 0 );
        if ( ! $body ) {
            return null;
        }
        $fragment = $doc->createDocumentFragment();
        foreach ( iterator_to_array( $body->childNodes ) as $child ) {
            $fragment->appendChild( $doc->importNode( $child, true ) );
        }
        return $fragment;
    }

    private function body_html( \DOMDocument $doc, string $fallback ): string {
        $body = $doc->getElementsByTagName( "body" )->item( 0 );
        if ( ! $body ) {
            return $fallback;
        }
        $html = "";
        foreach ( $body->childNodes as $child ) {
            $html .= $doc->saveHTML( $child );
        }
        return "" !== $html ? $html : $fallback;
    }
}
