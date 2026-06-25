<?php
namespace smp_publication_integration\Authorship;

use smp_publication_integration\Content\MuckRackVerification;
use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class ElementorAuthorRenderer {
    private const PRIMARY_MARKER_CLASS = "smp-author";
    private const LEGACY_MARKER_CLASS = "smpi-author-module";
    private const ITEM_MARKER_CLASS = "smpi-multi-author-item";

    private AuthorAssignmentRepository $repository;
    private ?array $marked_element_ids = null;
    private ?array $marked_templates = null;
    private ?array $binding_map = null;

    public function __construct( AuthorAssignmentRepository $repository ) {
        $this->repository = $repository;
    }

    public function filter_widget( string $content, $widget ): string {
        if (
            "" === trim( $content )
            || false !== strpos( $content, self::ITEM_MARKER_CLASS )
            || ! Settings::bool( "multi_authors_enabled" )
            || ! RuntimeContext::is_public_dom_context()
            || ! $this->is_supported_render_context()
            || ! $this->widget_is_marked( $widget, $content )
        ) {
            return $content;
        }
        return $this->render_for_current_post( $content );
    }

    public function filter_content( string $content ): string {
        if (
            "" === trim( $content )
            || false !== strpos( $content, self::ITEM_MARKER_CLASS )
            || ! Settings::bool( "multi_authors_enabled" )
            || ! RuntimeContext::is_public_dom_context()
            || ! is_singular( $this->repository->supported_post_types() )
            || ! $this->contains_marker( $content )
        ) {
            return $content;
        }

        $doc = $this->document( $content );
        if ( ! $doc ) {
            return $content;
        }
        $xpath = new \DOMXPath( $doc );
        $nodes = $xpath->query( $this->marker_xpath() );
        if ( ! $nodes || 0 === $nodes->length ) {
            return $content;
        }

        foreach ( iterator_to_array( $nodes ) as $node ) {
            if ( ! $node instanceof \DOMElement || ! $node->parentNode ) {
                continue;
            }
            $replacement = $this->render_for_current_post( $doc->saveHTML( $node ) ?: "" );
            if ( "" === $replacement ) {
                continue;
            }
            $fragment = $this->fragment( $doc, $replacement );
            if ( $fragment ) {
                $node->parentNode->replaceChild( $fragment, $node );
            }
        }
        return $this->body_html( $doc, $content );
    }

    private function render_for_current_post( string $template_html ): string {
        $post = get_post();
        if ( ! $post instanceof \WP_Post ) {
            return $template_html;
        }
        $selected = $this->repository->selected_ids_for_post( (int) $post->ID );
        if ( empty( $selected ) || ( 1 === count( $selected ) && (int) $selected[0] === (int) $post->post_author ) ) {
            return $template_html;
        }

        $authors = $this->repository->records_for_post( (int) $post->ID, false );
        $source = ( new AuthorFieldResolver() )->record( (int) $post->post_author );
        if ( empty( $authors ) || ! $source instanceof AuthorRecord || ! $this->is_exact_marked_root( $template_html ) ) {
            return $template_html;
        }

        $context = $this->verification_context( $template_html );
        [ $author_template, $preserved_html ] = $this->author_template_and_preserved_children( $template_html );
        $items = [];
        foreach ( $authors as $index => $author ) {
            if ( ! $author instanceof AuthorRecord ) {
                continue;
            }
            $html = $this->rebind( $author_template, $source, $author, $context );
            $items[] = $this->mark_item( $html, $author, $index, count( $authors ) );
        }
        return empty( $items ) ? $template_html : implode( "", $items ) . $preserved_html;
    }

    private function is_supported_render_context(): bool {
        return is_singular( $this->repository->supported_post_types() )
            || RuntimeContext::has_article_loop_context();
    }

    private function author_template_and_preserved_children( string $template_html ): array {
        $doc = $this->document( $template_html );
        if ( ! $doc ) {
            return [ $template_html, "" ];
        }

        $body = $doc->getElementsByTagName( "body" )->item( 0 );
        if ( ! $body ) {
            return [ $template_html, "" ];
        }

        $preserved = [];
        foreach ( iterator_to_array( $body->childNodes ) as $root ) {
            if ( ! $root instanceof \DOMElement || ! $this->element_is_marked( $root ) ) {
                continue;
            }

            foreach ( iterator_to_array( $root->childNodes ) as $child ) {
                if ( ! $child instanceof \DOMElement || ! $this->is_non_author_direct_child( $child ) ) {
                    continue;
                }
                $preserved[] = $doc->saveHTML( $child ) ?: "";
                $root->removeChild( $child );
            }
        }

        return [ $this->body_html( $doc, $template_html ), implode( "", array_filter( $preserved ) ) ];
    }

    private function is_non_author_direct_child( \DOMElement $element ): bool {
        $html = $element->ownerDocument ? ( $element->ownerDocument->saveHTML( $element ) ?: "" ) : "";
        $classes = strtolower(
            implode(
                " ",
                [
                    $element->getAttribute( "class" ),
                    $element->getAttribute( "id" ),
                    $element->getAttribute( "data-widget_type" ),
                    $element->getAttribute( "data-element_type" ),
                ]
            )
        );
        $text = strtolower( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) ?: "" ) );

        if ( false !== strpos( $classes, "smpi-author-keep" ) ) {
            return false;
        }
        if (
            false !== strpos( $classes, "share" )
            || false !== strpos( $classes, "social-share" )
            || false !== strpos( $classes, "addtoany" )
            || false !== strpos( $classes, "elementor-share" )
            || false !== strpos( $classes, "elementor-widget-share" )
        ) {
            return true;
        }
        if (
            preg_match( '/(^|\s)share(\s|$)/', $text )
            && false === strpos( $classes, "author" )
            && false === strpos( $html, "/author/" )
        ) {
            return true;
        }

        return false;
    }

    private function rebind( string $html, AuthorRecord $source, AuthorRecord $author, string $context ): string {
        $doc = $this->document( $html );
        if ( ! $doc ) {
            return $html;
        }
        $source_data = $source->to_array();
        $author_data = $author->to_array();
        $xpath = new \DOMXPath( $doc );

        $this->remove_badges( $xpath );
        $this->replace_author_links( $doc, $source_data, $author_data );
        $this->replace_author_images( $doc, $source_data, $author_data );
        $this->replace_exact_author_text( $doc, (string) $source_data["name"], (string) $author_data["name"] );
        $this->replace_bound_fields( $doc, $xpath, $author );
        $this->replace_social_links( $doc, $author );
        $this->insert_badge( $doc, $xpath, $author, $context );

        return $this->body_html( $doc, $html );
    }

    private function replace_author_links( \DOMDocument $doc, array $source, array $author ): void {
        foreach ( iterator_to_array( $doc->getElementsByTagName( "a" ) ) as $link ) {
            if ( ! $link instanceof \DOMElement ) {
                continue;
            }
            $href = html_entity_decode( $link->getAttribute( "href" ), ENT_QUOTES | ENT_HTML5, "UTF-8" );
            $matches = untrailingslashit( $href ) === untrailingslashit( (string) $source["url"] )
                || false !== strpos( $href, "/author/" . (string) $source["slug"] );
            if ( $matches ) {
                $link->setAttribute( "href", (string) $author["url"] );
            }
        }
    }

    private function replace_author_images( \DOMDocument $doc, array $source, array $author ): void {
        $replacements = [];
        foreach ( (array) ( $source["avatars"] ?? [] ) as $size => $url ) {
            $target = (string) ( $author["avatars"][ $size ] ?? "" );
            if ( "" !== (string) $url && "" !== $target ) {
                $replacements[ (string) $url ] = $target;
            }
        }
        if ( "" !== (string) $source["avatar"] && "" !== (string) $author["avatar"] ) {
            $replacements[ (string) $source["avatar"] ] = (string) $author["avatar"];
        }
        foreach ( iterator_to_array( $doc->getElementsByTagName( "img" ) ) as $image ) {
            if ( ! $image instanceof \DOMElement ) {
                continue;
            }
            foreach ( [ "src", "srcset", "data-src", "data-srcset" ] as $attribute ) {
                $value = $image->getAttribute( $attribute );
                if ( "" !== $value && ! empty( $replacements ) ) {
                    $image->setAttribute( $attribute, str_replace( array_keys( $replacements ), array_values( $replacements ), $value ) );
                }
            }
            if ( $this->normalize_text( $image->getAttribute( "alt" ) ) === $this->normalize_text( (string) $source["name"] ) ) {
                $image->setAttribute( "alt", (string) $author["name"] );
            }
        }
    }

    private function replace_exact_author_text( \DOMNode $node, string $source, string $target ): void {
        foreach ( iterator_to_array( $node->childNodes ) as $child ) {
            if ( $child instanceof \DOMText && $this->normalize_text( $child->nodeValue ) === $this->normalize_text( $source ) ) {
                $child->nodeValue = $this->preserve_edge_space( $child->nodeValue, $target );
            } elseif ( $child instanceof \DOMElement && ! in_array( strtolower( $child->tagName ), [ "script", "style", "svg" ], true ) ) {
                $this->replace_exact_author_text( $child, $source, $target );
            }
        }
    }

    private function replace_bound_fields( \DOMDocument $doc, \DOMXPath $xpath, AuthorRecord $author ): void {
        foreach ( $this->binding_map() as $element_id => $field ) {
            $nodes = $xpath->query( $this->element_xpath( $element_id ) );
            if ( ! $nodes ) {
                continue;
            }
            foreach ( $nodes as $node ) {
                if ( ! $node instanceof \DOMElement ) {
                    continue;
                }
                $this->replace_bound_node( $doc, $node, $field, $author );
            }
        }
    }

    private function replace_bound_node( \DOMDocument $doc, \DOMElement $node, string $field, AuthorRecord $author ): void {
        $data = $author->to_array();
        if ( "image" === $field ) {
            foreach ( $node->getElementsByTagName( "img" ) as $image ) {
                $image->setAttribute( "src", (string) $data["avatar"] );
                $image->removeAttribute( "srcset" );
                $image->setAttribute( "alt", (string) $data["name"] );
            }
            return;
        }
        if ( "url" === $field ) {
            foreach ( $node->getElementsByTagName( "a" ) as $link ) {
                $link->setAttribute( "href", (string) $data["url"] );
            }
            return;
        }
        if ( "verification" === $field ) {
            $value = $this->badge_html( $author->id(), $this->verification_context( $doc->saveHTML( $node ) ?: "" ) );
        } elseif ( "name" === $field ) {
            $value = esc_html( (string) $data["name"] );
        } elseif ( "bio" === $field || "description" === $field ) {
            $value = wp_kses_post( wpautop( $author->field( "bio", $author->field( "description" ) ) ) );
        } else {
            $value = esc_html( wp_strip_all_tags( $author->field( $field ) ) );
        }
        $container = $this->descendant_with_class( $node, "elementor-widget-container" ) ?: $node;
        while ( $container->firstChild ) {
            $container->removeChild( $container->firstChild );
        }
        if ( "" !== $value ) {
            $fragment = $this->fragment( $doc, $value );
            if ( $fragment ) {
                $container->appendChild( $fragment );
            }
        }
    }

    private function replace_social_links( \DOMDocument $doc, AuthorRecord $author ): void {
        $resolver = new AuthorFieldResolver();
        foreach ( iterator_to_array( $doc->getElementsByTagName( "a" ) ) as $link ) {
            if ( ! $link instanceof \DOMElement ) {
                continue;
            }
            $kind = $this->social_kind( $link );
            if ( "" === $kind ) {
                continue;
            }
            $target = $resolver->social_url( $author->id(), $kind );
            if ( "" === $target ) {
                $removal = $this->social_widget_root( $link );
                if ( $removal->parentNode ) {
                    $removal->parentNode->removeChild( $removal );
                }
            } else {
                $link->setAttribute( "href", $target );
            }
        }
    }

    private function insert_badge( \DOMDocument $doc, \DOMXPath $xpath, AuthorRecord $author, string $context ): void {
        $badge = $this->badge_html( $author->id(), $context );
        if ( "" === $badge ) {
            return;
        }
        $target_name = $this->normalize_text( (string) $author->to_array()["name"] );
        $nodes = $xpath->query( '//*[not(self::script) and not(self::style) and not(self::svg)]' );
        if ( ! $nodes ) {
            return;
        }
        foreach ( $nodes as $node ) {
            if ( ! $node instanceof \DOMElement || $this->normalize_text( (string) $node->textContent ) !== $target_name ) {
                continue;
            }
            $has_exact_child = false;
            foreach ( $node->childNodes as $child ) {
                if ( $child instanceof \DOMElement && $this->normalize_text( (string) $child->textContent ) === $target_name ) {
                    $has_exact_child = true;
                    break;
                }
            }
            if ( $has_exact_child ) {
                continue;
            }
            $fragment = $this->fragment( $doc, " " . $badge );
            if ( ! $fragment ) {
                return;
            }
            if ( "a" === strtolower( $node->tagName ) && $node->parentNode ) {
                $node->parentNode->insertBefore( $fragment, $node->nextSibling );
            } else {
                $node->appendChild( $fragment );
            }
            return;
        }
    }

    private function remove_badges( \DOMXPath $xpath ): void {
        $nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " smpi-muckrack-icon ") or contains(concat(" ", normalize-space(@class), " "), " smpi-muckrack-author-note ") or contains(concat(" ", normalize-space(@class), " "), " smpi-muckrack-link ")]' );
        if ( ! $nodes ) {
            return;
        }
        foreach ( iterator_to_array( $nodes ) as $node ) {
            if ( $node instanceof \DOMNode && $node->parentNode ) {
                $node->parentNode->removeChild( $node );
            }
        }
    }

    private function badge_html( int $user_id, string $context ): string {
        if (
            ! class_exists( MuckRackVerification::class )
            || ! Settings::bool( "muckrack_verified_enabled" )
            || ! in_array( $context, Settings::array( "muckrack_verified_contexts" ), true )
        ) {
            return "";
        }
        $badge = MuckRackVerification::verification_icon(
            $user_id,
            (string) Settings::get( "muckrack_verified_style", "tooltip" ),
            $context
        );
        $doc = $this->document( $badge );
        if ( ! $doc ) {
            return $badge;
        }
        $xpath = new \DOMXPath( $doc );
        $icons = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " smpi-muckrack-icon ")]' );
        $icon = $icons && $icons->length ? $icons->item( 0 ) : null;
        return $icon instanceof \DOMElement ? ( $doc->saveHTML( $icon ) ?: $badge ) : $badge;
    }

    private function verification_context( string $html ): string {
        if ( RuntimeContext::has_article_loop_context() && ! is_singular( $this->repository->supported_post_types() ) ) {
            return is_author() ? "author" : "loop_cards";
        }

        $lower = strtolower( wp_strip_all_tags( $html ) );
        return preg_match( '/about the author|twitter|linkedin|email/', $lower ) || false !== strpos( $html, "author_bio" )
            ? "single_footer"
            : "single_author";
    }

    private function mark_item( string $html, AuthorRecord $author, int $index, int $count ): string {
        $doc = $this->document( $html );
        if ( ! $doc ) {
            return $html;
        }
        $body = $doc->getElementsByTagName( "body" )->item( 0 );
        if ( ! $body ) {
            return $html;
        }
        foreach ( $body->childNodes as $child ) {
            if ( ! $child instanceof \DOMElement ) {
                continue;
            }
            $classes = trim( $child->getAttribute( "class" ) . " " . self::ITEM_MARKER_CLASS );
            $child->setAttribute( "class", $classes );
            $child->setAttribute( "data-smpi-author-index", (string) $index );
            $child->setAttribute( "data-smpi-author-id", (string) $author->id() );
            $child->setAttribute( "data-smpi-multi-author-count", (string) $count );
            break;
        }
        return $this->body_html( $doc, $html );
    }

    private function is_exact_marked_root( string $html ): bool {
        $doc = $this->document( $html );
        if ( ! $doc ) {
            return false;
        }
        $body = $doc->getElementsByTagName( "body" )->item( 0 );
        if ( ! $body ) {
            return false;
        }
        $roots = [];
        foreach ( $body->childNodes as $child ) {
            if ( $child instanceof \DOMElement ) {
                $roots[] = $child;
            }
        }
        return 1 === count( $roots ) && $this->element_is_marked( $roots[0] );
    }

    private function widget_is_marked( $widget, string $content ): bool {
        if ( $this->contains_marker_class( $content ) ) {
            return true;
        }
        if ( ! is_object( $widget ) || ! method_exists( $widget, "get_id" ) ) {
            return false;
        }
        return in_array( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $widget->get_id() ), $this->marked_element_ids(), true );
    }

    private function contains_marker( string $content ): bool {
        if ( $this->contains_marker_class( $content ) ) {
            return true;
        }
        foreach ( $this->marked_element_ids() as $id ) {
            if ( false !== strpos( $content, "elementor-element-" . $id ) ) {
                return true;
            }
        }
        return false;
    }

    private function marker_xpath(): string {
        $tests = [
            'contains(concat(" ", normalize-space(@class), " "), " ' . self::PRIMARY_MARKER_CLASS . ' ")',
            'contains(concat(" ", normalize-space(@class), " "), " ' . self::LEGACY_MARKER_CLASS . ' ")',
        ];
        foreach ( $this->marked_element_ids() as $id ) {
            $tests[] = 'contains(concat(" ", normalize-space(@class), " "), " elementor-element-' . $id . ' ")';
        }
        return '//*[(' . implode( " or ", $tests ) . ') and not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " smpi-multi-author-item ")])]';
    }

    private function marked_element_ids(): array {
        if ( null !== $this->marked_element_ids ) {
            return $this->marked_element_ids;
        }
        $ids = [];
        foreach ( $this->marked_templates() as $template_id ) {
            $data = get_post_meta( (int) $template_id, "_elementor_data", true );
            $nodes = is_string( $data ) ? json_decode( $data, true ) : null;
            if ( is_array( $nodes ) ) {
                $this->collect_marked_ids( $nodes, $ids );
            }
        }
        $this->marked_element_ids = array_values( array_unique( array_filter( $ids ) ) );
        return $this->marked_element_ids;
    }

    private function binding_map(): array {
        if ( null !== $this->binding_map ) {
            return $this->binding_map;
        }

        $map = [];
        foreach ( $this->marked_templates() as $template_id ) {
            $data = get_post_meta( (int) $template_id, "_elementor_data", true );
            $nodes = is_string( $data ) ? json_decode( $data, true ) : null;
            if ( is_array( $nodes ) ) {
                $this->collect_bindings( $nodes, $map, false );
            }
        }
        $this->binding_map = $map;
        return $this->binding_map;
    }

    private function marked_templates(): array {
        if ( null !== $this->marked_templates ) {
            return $this->marked_templates;
        }

        $this->marked_templates = array_map(
            "absint",
            get_posts(
                [
                    "post_type" => "elementor_library",
                    "post_status" => [ "publish", "draft", "private" ],
                    "posts_per_page" => 100,
                    "fields" => "ids",
                    "no_found_rows" => true,
                    "meta_query" => [
                        "relation" => "OR",
                        [
                            "key" => "_elementor_data",
                            "value" => self::PRIMARY_MARKER_CLASS,
                            "compare" => "LIKE",
                        ],
                        [
                            "key" => "_elementor_data",
                            "value" => self::LEGACY_MARKER_CLASS,
                            "compare" => "LIKE",
                        ],
                    ],
                ]
            )
        );

        return $this->marked_templates;
    }

    private function collect_marked_ids( array $nodes, array &$ids ): void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            if ( $this->node_is_marked( $node ) && ! empty( $node["id"] ) ) {
                $ids[] = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $node["id"] );
            }
            if ( ! empty( $node["elements"] ) && is_array( $node["elements"] ) ) {
                $this->collect_marked_ids( $node["elements"], $ids );
            }
        }
    }

    private function collect_bindings( array $nodes, array &$map, bool $inside_marker ): void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $marked = $inside_marker || $this->node_is_marked( $node );
            if ( $marked && ! empty( $node["id"] ) ) {
                $field = $this->binding_for_node( $node );
                if ( "" !== $field ) {
                    $map[ preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $node["id"] ) ] = $field;
                }
            }
            if ( ! empty( $node["elements"] ) && is_array( $node["elements"] ) ) {
                $this->collect_bindings( $node["elements"], $map, $marked );
            }
        }
    }

    private function binding_for_node( array $node ): string {
        $settings = isset( $node["settings"] ) && is_array( $node["settings"] ) ? $node["settings"] : [];
        $value = strtolower( stripcslashes( html_entity_decode( wp_json_encode( $settings ) ?: "", ENT_QUOTES | ENT_HTML5, "UTF-8" ) ) );
        $patterns = [
            "verification" => '/author_muckrack_verified|muckrack_verified/',
            "image" => '/author_image|author-profile-picture/',
            "name" => '/author_name|author-name/',
            "bio" => '/author_bio|author-info[^\\]]*description|acf_author_field[^\\]]*field=["\']?(?:bio|description)/',
            "job_title" => '/author_title|acf_author_field[^\\]]*field=["\']?(?:job_title|title)/',
            "subtitle" => '/author_subtitle|acf_author_field[^\\]]*field=["\']?subtitle/',
            "url" => '/author-url/',
        ];
        foreach ( $patterns as $field => $pattern ) {
            if ( preg_match( $pattern, $value ) ) {
                return $field;
            }
        }
        return "";
    }

    private function node_is_marked( array $node ): bool {
        $settings = isset( $node["settings"] ) && is_array( $node["settings"] ) ? $node["settings"] : [];
        $classes = "";
        foreach ( [ "_css_classes", "css_classes" ] as $key ) {
            $raw = $settings[ $key ] ?? "";
            $classes .= " " . ( is_array( $raw ) ? implode( " ", array_map( "strval", $raw ) ) : (string) $raw );
        }
        return (bool) preg_match( '/(^|\s)(?:' . preg_quote( self::PRIMARY_MARKER_CLASS, "/" ) . '|' . preg_quote( self::LEGACY_MARKER_CLASS, "/" ) . ')(\s|$)/', $classes );
    }

    private function element_is_marked( \DOMElement $element ): bool {
        if ( preg_match( '/(^|\s)(?:' . preg_quote( self::PRIMARY_MARKER_CLASS, "/" ) . '|' . preg_quote( self::LEGACY_MARKER_CLASS, "/" ) . ')(\s|$)/', $element->getAttribute( "class" ) ) ) {
            return true;
        }
        foreach ( $this->marked_element_ids() as $id ) {
            if ( preg_match( '/(^|\s)elementor-element-' . preg_quote( $id, "/" ) . '(\s|$)/', $element->getAttribute( "class" ) ) ) {
                return true;
            }
        }
        return false;
    }

    private function contains_marker_class( string $content ): bool {
        return false !== strpos( $content, self::PRIMARY_MARKER_CLASS )
            || false !== strpos( $content, self::LEGACY_MARKER_CLASS );
    }

    private function social_kind( \DOMElement $link ): string {
        $label = strtolower(
            implode(
                " ",
                [
                    (string) $link->textContent,
                    $link->getAttribute( "href" ),
                    $link->getAttribute( "class" ),
                    $link->getAttribute( "title" ),
                    $link->getAttribute( "aria-label" ),
                ]
            )
        );
        if ( false !== strpos( $label, "twitter" ) || false !== strpos( $label, "x.com" ) || false !== strpos( $label, "x-twitter" ) ) {
            return "x";
        }
        foreach ( [ "linkedin", "email", "website", "facebook", "instagram", "youtube" ] as $kind ) {
            if ( false !== strpos( $label, $kind ) || ( "email" === $kind && false !== strpos( $label, "mailto:" ) ) ) {
                return $kind;
            }
        }
        return "";
    }

    private function social_widget_root( \DOMElement $link ): \DOMElement {
        for ( $node = $link; $node instanceof \DOMElement; $node = $node->parentNode instanceof \DOMElement ? $node->parentNode : null ) {
            $classes = " " . $node->getAttribute( "class" ) . " ";
            if (
                false !== strpos( $classes, " elementor-icon-list-item " )
                || false !== strpos( $classes, " elementor-widget-button " )
                || false !== strpos( $classes, " elementor-social-icon " )
            ) {
                return $node;
            }
        }
        return $link;
    }

    private function descendant_with_class( \DOMElement $node, string $class ): ?\DOMElement {
        foreach ( $node->getElementsByTagName( "*" ) as $child ) {
            if ( $child instanceof \DOMElement && preg_match( '/(^|\s)' . preg_quote( $class, "/" ) . '(\s|$)/', $child->getAttribute( "class" ) ) ) {
                return $child;
            }
        }
        return null;
    }

    private function element_xpath( string $id ): string {
        return '//*[contains(concat(" ", normalize-space(@class), " "), " elementor-element-' . preg_replace( '/[^A-Za-z0-9_-]/', '', $id ) . ' ")]';
    }

    private function normalize_text( string $value ): string {
        $value = strtolower( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, "UTF-8" ) ) );
        return preg_replace( '/[^a-z0-9]+/', '', $value ) ?: "";
    }

    private function preserve_edge_space( string $original, string $replacement ): string {
        return ( preg_match( '/^\s/', $original ) ? " " : "" )
            . $replacement
            . ( preg_match( '/\s$/', $original ) ? " " : "" );
    }

    private function document( string $html ): ?\DOMDocument {
        if ( "" === trim( $html ) ) {
            return null;
        }
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
