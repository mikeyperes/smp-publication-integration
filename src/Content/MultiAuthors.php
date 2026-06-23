<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class MultiAuthors {
    public const FIELD_NAME = "smpi_post_authors";
    public const FIELD_KEY = "field_smpi_post_authors";

    private static array $author_stack = [];
    private static ?array $marked_elementor_ids = null;
    private static ?array $marked_author_field_widgets = null;

    public function register(): void {
        add_shortcode( "smp_post_author_ids", [ $this, "render_author_ids_shortcode" ] );
        add_shortcode( "smp_post_authors", [ $this, "render_authors_shortcode" ] );
        add_shortcode( "smp_post_author_names", [ $this, "render_authors_shortcode" ] );
        add_filter( "elementor/widget/render_content", [ $this, "filter_elementor_author_module" ], 20, 2 );
        add_filter( "elementor/frontend/the_content", [ $this, "filter_elementor_rendered_content" ], 20, 1 );
    }

    public static function enabled(): bool {
        return Settings::bool( "multi_authors_enabled" );
    }

    public static function loop_cards_disabled(): bool {
        return Settings::bool( "multi_authors_disable_loop_cards" );
    }

    public static function supported_post_types(): array {
        return [ "post", "press-release", "imported-news" ];
    }

    public static function author_ids_for_post( int $post_id, bool $fallback = true ): array {
        $post = $post_id ? get_post( $post_id ) : null;
        if ( ! $post ) {
            return [];
        }

        $ids = [];
        if ( self::enabled() ) {
            $ids = self::normalize_user_ids( self::raw_author_value( $post_id ) );
        }

        if ( $fallback && empty( $ids ) && (int) $post->post_author > 0 ) {
            $ids[] = (int) $post->post_author;
        }

        return self::valid_unique_user_ids( $ids );
    }

    public static function primary_author_id_for_post( int $post_id ): int {
        $ids = self::author_ids_for_post( $post_id, true );
        return ! empty( $ids ) ? (int) $ids[0] : 0;
    }

    public static function resolve_author_id( int $explicit_user_id = 0, int $explicit_post_id = 0, int $author_index = 0 ): int {
        if ( $explicit_user_id > 0 && get_user_by( "id", $explicit_user_id ) ) {
            return $explicit_user_id;
        }

        $context_id = self::current_author_id();
        if ( $context_id > 0 ) {
            return $context_id;
        }

        if ( is_author() && $explicit_post_id <= 0 ) {
            return (int) get_queried_object_id();
        }

        $post_id = $explicit_post_id;
        if ( $post_id <= 0 ) {
            $post = get_post();
            $post_id = $post ? (int) $post->ID : 0;
        }

        if ( $post_id > 0 ) {
            $ids = self::author_ids_for_post( $post_id, true );
            if ( isset( $ids[ $author_index ] ) ) {
                return (int) $ids[ $author_index ];
            }
            return ! empty( $ids ) ? (int) $ids[0] : 0;
        }

        return 0;
    }

    public static function current_author_id(): int {
        return ! empty( self::$author_stack ) ? (int) end( self::$author_stack ) : 0;
    }

    public static function with_author_context( int $author_id, callable $callback ) {
        self::$author_stack[] = $author_id;
        try {
            return $callback();
        } finally {
            array_pop( self::$author_stack );
        }
    }

    public function render_author_ids_shortcode( array $atts = [] ): string {
        $atts = shortcode_atts( [ "post_id" => 0, "separator" => "," ], $atts, "smp_post_author_ids" );
        $post_id = (int) $atts["post_id"];
        if ( $post_id <= 0 ) {
            $post = get_post();
            $post_id = $post ? (int) $post->ID : 0;
        }
        $ids = self::author_ids_for_post( $post_id, true );
        return esc_html( implode( (string) $atts["separator"], $ids ) );
    }

    public function render_authors_shortcode( array $atts = [], ?string $content = null, string $tag = "smp_post_authors" ): string {
        $atts = shortcode_atts(
            [
                "post_id" => 0,
                "field" => "name",
                "format" => "plain",
                "separator" => ", ",
                "context" => "",
                "class" => "smpi-post-authors",
            ],
            $atts,
            $tag
        );

        $post_id = (int) $atts["post_id"];
        if ( $post_id <= 0 ) {
            $post = get_post();
            $post_id = $post ? (int) $post->ID : 0;
        }
        if ( $post_id <= 0 ) {
            return "";
        }

        $authors = self::author_view_models_for_post( $post_id );
        if ( empty( $authors ) ) {
            return "";
        }
        if ( "card" === sanitize_key( (string) $atts["context"] ) && self::loop_cards_disabled() ) {
            $authors = [ $authors[0] ];
        }

        $field = sanitize_key( (string) $atts["field"] );
        $format = sanitize_key( (string) $atts["format"] );
        $values = [];
        foreach ( $authors as $author ) {
            $values[] = self::author_shortcode_value( $author, $field );
        }
        $values = array_values( array_filter( $values, static fn( string $value ): bool => "" !== $value ) );
        if ( empty( $values ) ) {
            return "";
        }

        if ( "ids" === $format ) {
            return esc_html( implode( (string) $atts["separator"], $values ) );
        }

        if ( "links" === $format ) {
            $links = [];
            foreach ( $authors as $author ) {
                $label = self::author_shortcode_value( $author, $field );
                if ( "" === $label ) {
                    continue;
                }
                $links[] = '<a href="' . esc_url( (string) $author["url"] ) . '">' . esc_html( $label ) . '</a>';
            }
            return implode( esc_html( (string) $atts["separator"] ), $links );
        }

        if ( "lines" === $format || "line" === $format ) {
            return implode( "<br>\n", array_map( "esc_html", $values ) );
        }

        if ( "list" === $format || "ul" === $format ) {
            $items = array_map( static fn( string $value ): string => "<li>" . esc_html( $value ) . "</li>", $values );
            return '<ul class="' . esc_attr( sanitize_html_class( (string) $atts["class"] ) ) . '">' . implode( "", $items ) . '</ul>';
        }

        return esc_html( implode( (string) $atts["separator"], $values ) );
    }

    public function filter_elementor_author_module( string $content, $widget ): string {
        if ( "" === trim( $content ) || false !== strpos( $content, "smpi-multi-author-item" ) ) {
            return $content;
        }
        if ( ! self::enabled() || ! RuntimeContext::is_public_dom_context() || ! is_singular( self::supported_post_types() ) ) {
            return $content;
        }
        if ( ! $this->widget_has_author_module_marker( $widget, $content ) ) {
            return $content;
        }

        return $this->author_stack_html_for_current_post( $content );
    }

    public function filter_elementor_rendered_content( string $content ): string {
        if ( ! $this->content_has_author_module_marker( $content ) || false !== strpos( $content, "smpi-multi-author-item" ) ) {
            return $content;
        }
        if ( ! self::enabled() || ! RuntimeContext::is_public_dom_context() || ! is_singular( self::supported_post_types() ) ) {
            return $content;
        }

        $doc = new \DOMDocument( "1.0", "UTF-8" );
        $previous = libxml_use_internal_errors( true );
        $loaded = $doc->loadHTML( '<?xml encoding="UTF-8"><body>' . $content . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $loaded ) {
            return $content;
        }

        $xpath = new \DOMXPath( $doc );
        $nodes = $xpath->query( $this->author_module_xpath_expression() );
        if ( ! $nodes || 0 === $nodes->length ) {
            return $content;
        }

        $targets = [];
        foreach ( $nodes as $node ) {
            $targets[] = $node;
        }

        foreach ( $targets as $node ) {
            if ( ! $node instanceof \DOMElement || ! $node->parentNode ) {
                continue;
            }
            if ( self::loop_cards_disabled() && $this->is_loop_card_node( $node ) ) {
                continue;
            }
            $replacement_html = $this->author_stack_html_for_current_post( $doc->saveHTML( $node ) ?: "" );
            if ( "" === $replacement_html ) {
                continue;
            }
            $fragment = $this->html_fragment( $doc, $replacement_html );
            if ( $fragment ) {
                $node->parentNode->replaceChild( $fragment, $node );
            }
        }

        $body = $doc->getElementsByTagName( "body" )->item( 0 );
        if ( ! $body ) {
            return $content;
        }
        $html = "";
        foreach ( $body->childNodes as $child ) {
            $html .= $doc->saveHTML( $child );
        }

        return "" !== $html ? $html : $content;
    }

    private function content_has_author_module_marker( string $content ): bool {
        if ( false !== strpos( $content, "smpi-author-module" ) ) {
            return true;
        }
        foreach ( self::marked_elementor_element_ids() as $element_id ) {
            if ( false !== strpos( $content, "elementor-element-" . $element_id ) ) {
                return true;
            }
        }
        return false;
    }

    private function author_module_xpath_expression(): string {
        $tests = [ 'contains(concat(" ", normalize-space(@class), " "), " smpi-author-module ")' ];
        foreach ( self::marked_elementor_element_ids() as $element_id ) {
            $tests[] = 'contains(concat(" ", normalize-space(@class), " "), " elementor-element-' . $element_id . ' ")';
        }
        return '//*[(' . implode( " or ", $tests ) . ') and not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " smpi-multi-author-item ")])]';
    }

    private function author_stack_html_for_current_post( string $content ): string {
        if ( "" === trim( $content ) ) {
            return $content;
        }

        $post = get_post();
        $post_id = $post ? (int) $post->ID : 0;
        $authors = self::author_view_models_for_post( $post_id );
        if ( count( $authors ) < 2 ) {
            return $content;
        }

        $badge_context = false !== strpos( $content, 'id="share-button"' ) || false !== strpos( $content, "id='share-button'" ) ? "single_author" : "single_footer";

        $primary = $authors[0];
        $template = self::author_only_template_html( $content, $primary );
        if ( "" === $template ) {
            return $content;
        }

        $items = [ self::mark_original_author_scope_html( $content, count( $authors ) ) ];
        foreach ( $authors as $index => $author ) {
            if ( 0 === $index ) {
                continue;
            }
            $html = self::rebind_author_html( $template, $primary, $author, $badge_context );
            $items[] = self::mark_repeated_author_html( $html, $author, $index, count( $authors ) );
        }

        return implode( "", $items );
    }

    private static function author_only_template_html( string $html, array $primary ): string {
        $doc = new \DOMDocument( "1.0", "UTF-8" );
        $previous = libxml_use_internal_errors( true );
        $loaded = $doc->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( ! $loaded ) {
            return "";
        }
        $body = $doc->getElementsByTagName( "body" )->item( 0 );
        if ( ! $body ) {
            return "";
        }

        $roots = [];
        foreach ( $body->childNodes as $child ) {
            if ( $child instanceof \DOMElement ) {
                $roots[] = $child;
            }
        }
        if ( count( $roots ) !== 1 ) {
            return "";
        }

        $root = $roots[0];
        $children = self::direct_element_children( $root );
        if ( empty( $children ) ) {
            return self::node_contains_author_data( $doc, $root, $primary ) ? $html : "";
        }

        $author_child_ids = [];
        foreach ( $children as $index => $child ) {
            if ( self::node_contains_author_data( $doc, $child, $primary ) ) {
                $author_child_ids[ $index ] = true;
            }
        }
        if ( empty( $author_child_ids ) ) {
            return "";
        }

        $clone = $root->cloneNode( true );
        if ( ! $clone instanceof \DOMElement ) {
            return "";
        }

        $clone_children = self::direct_element_children( $clone );
        foreach ( $clone_children as $index => $child ) {
            if ( isset( $author_child_ids[ $index ] ) ) {
                continue;
            }
            if ( $child->parentNode ) {
                $child->parentNode->removeChild( $child );
            }
        }

        foreach ( iterator_to_array( $clone->childNodes ) as $child ) {
            if ( $child instanceof \DOMText && "" === trim( $child->nodeValue ) ) {
                $clone->removeChild( $child );
            }
        }

        return $doc->saveHTML( $clone ) ?: "";
    }

    private static function mark_original_author_scope_html( string $html, int $count ): string {
        $doc = new \DOMDocument( "1.0", "UTF-8" );
        $previous = libxml_use_internal_errors( true );
        $loaded = $doc->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( ! $loaded ) {
            return $html;
        }

        $body = $doc->getElementsByTagName( "body" )->item( 0 );
        if ( ! $body ) {
            return $html;
        }
        foreach ( $body->childNodes as $child ) {
            if ( $child instanceof \DOMElement ) {
                $child->setAttribute( "data-smpi-multi-author-count", (string) $count );
                break;
            }
        }

        $out = "";
        foreach ( $body->childNodes as $child ) {
            $out .= $doc->saveHTML( $child );
        }
        return "" !== $out ? $out : $html;
    }

    private static function direct_element_children( \DOMElement $root ): array {
        $children = [];
        foreach ( $root->childNodes as $child ) {
            if ( $child instanceof \DOMElement ) {
                $children[] = $child;
            }
        }
        return $children;
    }

    private static function node_contains_author_data( \DOMDocument $doc, \DOMElement $node, array $primary ): bool {
        $html = $doc->saveHTML( $node ) ?: "";
        $text = self::normalize_match_text( (string) $node->textContent );
        $name = self::normalize_match_text( (string) $primary["name"] );
        if ( "" !== $name && false !== strpos( $text, $name ) ) {
            return true;
        }

        foreach ( [ "url", "slug", "avatar" ] as $key ) {
            $value = (string) ( $primary[ $key ] ?? "" );
            if ( "" !== $value && false !== strpos( $html, $value ) ) {
                return true;
            }
        }
        foreach ( (array) ( $primary["avatars"] ?? [] ) as $avatar ) {
            $avatar = (string) $avatar;
            if ( "" !== $avatar && false !== strpos( $html, $avatar ) ) {
                return true;
            }
        }
        foreach ( self::marked_elementor_author_field_widgets() as $element_id => $field ) {
            if ( false !== strpos( $html, "elementor-element-" . $element_id ) ) {
                return true;
            }
        }

        return false;
    }

    private function html_fragment( \DOMDocument $doc, string $html ): ?\DOMDocumentFragment {
        $tmp = new \DOMDocument( "1.0", "UTF-8" );
        $previous = libxml_use_internal_errors( true );
        $loaded = $tmp->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( ! $loaded ) {
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

    public static function author_view_models_for_post( int $post_id ): array {
        $models = [];
        foreach ( self::author_ids_for_post( $post_id, true ) as $user_id ) {
            $user = get_user_by( "id", $user_id );
            if ( ! $user ) {
                continue;
            }
            $models[] = self::author_view_model( $user );
        }
        return $models;
    }

    private function widget_has_author_module_marker( $widget, string $content ): bool {
        if ( false !== strpos( $content, "smpi-author-module" ) ) {
            return true;
        }
        foreach ( [ "_css_classes", "css_classes" ] as $setting ) {
            $value = "";
            try {
                if ( is_object( $widget ) && method_exists( $widget, "get_settings_for_display" ) ) {
                    $raw = $widget->get_settings_for_display( $setting );
                    $value = is_array( $raw ) ? implode( " ", array_map( "strval", $raw ) ) : (string) $raw;
                }
                if ( "" === $value && is_object( $widget ) && method_exists( $widget, "get_settings" ) ) {
                    $raw = $widget->get_settings( $setting );
                    $value = is_array( $raw ) ? implode( " ", array_map( "strval", $raw ) ) : (string) $raw;
                }
            } catch ( \Throwable $e ) {
                $value = "";
            }
            if ( preg_match( '/(^|\s)smpi-author-module(\s|$)/', $value ) ) {
                return true;
            }
        }
        return false;
    }

    private function is_loop_card_node( \DOMElement $node ): bool {
        for ( $current = $node; $current instanceof \DOMElement; $current = $current->parentNode instanceof \DOMElement ? $current->parentNode : null ) {
            $classes = " " . $current->getAttribute( "class" ) . " ";
            foreach ( [ " e-loop-item ", " elementor-loop-item ", " elementor-post ", " elementor-grid-item " ] as $needle ) {
                if ( false !== strpos( $classes, $needle ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function marked_elementor_element_ids(): array {
        if ( null !== self::$marked_elementor_ids ) {
            return self::$marked_elementor_ids;
        }

        self::$marked_elementor_ids = [];
        foreach ( self::elementor_templates_with_author_markers() as $template_id ) {
            $data = get_post_meta( (int) $template_id, "_elementor_data", true );
            if ( ! is_string( $data ) || "" === $data ) {
                continue;
            }
            $decoded = json_decode( $data, true );
            if ( is_array( $decoded ) ) {
                self::collect_marked_elementor_ids( $decoded, self::$marked_elementor_ids );
            }
        }

        self::$marked_elementor_ids = array_values( array_unique( array_filter( self::$marked_elementor_ids ) ) );
        return self::$marked_elementor_ids;
    }

    private static function elementor_templates_with_author_markers(): array {
        if ( ! function_exists( "get_posts" ) ) {
            return [];
        }

        return get_posts(
            [
                "post_type" => "elementor_library",
                "post_status" => [ "publish", "draft", "private" ],
                "posts_per_page" => 50,
                "fields" => "ids",
                "no_found_rows" => true,
                "meta_query" => [
                    [
                        "key" => "_elementor_data",
                        "value" => "smpi-author-module",
                        "compare" => "LIKE",
                    ],
                ],
            ]
        );
    }

    private static function collect_marked_elementor_ids( array $nodes, array &$ids ): void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            if ( self::node_has_author_module_marker( $node ) && ! empty( $node["id"] ) && is_string( $node["id"] ) ) {
                $ids[] = preg_replace( '/[^A-Za-z0-9_-]/', '', $node["id"] );
            }
            if ( ! empty( $node["elements"] ) && is_array( $node["elements"] ) ) {
                self::collect_marked_elementor_ids( $node["elements"], $ids );
            }
        }
    }

    private static function node_has_author_module_marker( array $node ): bool {
        $settings = isset( $node["settings"] ) && is_array( $node["settings"] ) ? $node["settings"] : [];
        $classes = "";
        foreach ( [ "_css_classes", "css_classes" ] as $key ) {
            if ( isset( $settings[ $key ] ) ) {
                $classes .= " " . ( is_array( $settings[ $key ] ) ? implode( " ", array_map( "strval", $settings[ $key ] ) ) : (string) $settings[ $key ] );
            }
        }
        return (bool) preg_match( '/(^|\s)smpi-author-module(\s|$)/', $classes );
    }

    public static function field_report( int $limit = 10 ): array {
        $enabled = self::enabled();
        $query = new \WP_Query(
            [
                "post_type" => self::supported_post_types(),
                "post_status" => [ "publish", "draft", "pending", "future" ],
                "posts_per_page" => $limit,
                "orderby" => "modified",
                "order" => "DESC",
                "no_found_rows" => true,
            ]
        );
        $rows = [];
        foreach ( $query->posts as $post ) {
            $ids = self::author_ids_for_post( (int) $post->ID, true );
            $rows[] = [
                "post_id" => (int) $post->ID,
                "title" => get_the_title( $post ),
                "type" => (string) $post->post_type,
                "status" => (string) $post->post_status,
                "native_author" => (int) $post->post_author,
                "authors" => $ids,
                "count" => count( $ids ),
            ];
        }
        wp_reset_postdata();

        return [
            "enabled" => $enabled,
            "field" => self::FIELD_NAME,
            "field_key" => self::FIELD_KEY,
            "supported_post_types" => self::supported_post_types(),
            "rows" => $rows,
        ];
    }

    private static function raw_author_value( int $post_id ) {
        if ( function_exists( "get_field" ) ) {
            $value = get_field( self::FIELD_NAME, $post_id, false );
            if ( null !== $value && false !== $value && "" !== $value && [] !== $value ) {
                return $value;
            }
        }
        return get_post_meta( $post_id, self::FIELD_NAME, true );
    }

    private static function author_view_model( \WP_User $user ): array {
        $id = (int) $user->ID;
        $model = [
            "id" => $id,
            "name" => (string) $user->display_name,
            "slug" => (string) $user->user_nicename,
            "url" => get_author_posts_url( $id ),
            "email" => (string) $user->user_email,
            "avatar" => self::author_avatar_url( $id ),
            "avatars" => self::author_avatar_urls( $id ),
            "fields" => [],
        ];

        $aliases = class_exists( AuthorShortcodes::class ) ? AuthorShortcodes::field_aliases() : [];
        $model["fields"]["bio"] = self::first_author_field( $id, $aliases["bio"] ?? [ "bio", "description" ] );
        $model["fields"]["bio_short"] = self::first_author_field( $id, $aliases["bio_short"] ?? [ "bio_short" ] );
        $model["fields"]["description"] = "" !== $model["fields"]["bio"] ? $model["fields"]["bio"] : (string) get_the_author_meta( "description", $id );
        $model["fields"]["title"] = self::first_author_field( $id, $aliases["title"] ?? [ "title", "job_title" ] );
        $model["fields"]["job_title"] = self::first_author_field( $id, [ "job_title", "author_job_title", "author_title", "title", "role", "position" ] );
        $model["fields"]["subtitle"] = self::first_author_field( $id, $aliases["subtitle"] ?? [ "subtitle", "tagline" ] );
        foreach ( [ "x", "linkedin", "facebook", "instagram", "youtube", "website", "crunchbase", "muckrack" ] as $field ) {
            $model["fields"][ $field ] = self::first_author_field( $id, $aliases[ $field ] ?? [ $field ] );
        }
        $model["fields"]["email"] = "" !== $model["email"] ? "mailto:" . $model["email"] : "";

        return $model;
    }

    private static function author_avatar_url( int $user_id ): string {
        try {
            $avatar = get_avatar_url( $user_id, [ "size" => 300 ] );
        } catch ( \Throwable $e ) {
            $avatar = "";
        }
        return is_string( $avatar ) ? $avatar : "";
    }

    private static function author_avatar_urls( int $user_id ): array {
        $urls = [];
        foreach ( [ 40, 80, 96, 100, 150, 300, 450 ] as $size ) {
            try {
                $url = get_avatar_url( $user_id, [ "size" => $size ] );
            } catch ( \Throwable $e ) {
                $url = "";
            }
            if ( is_string( $url ) && "" !== $url ) {
                $urls[ $size ] = $url;
            }
        }
        return $urls;
    }

    private static function first_author_field( int $author_id, array $fields ): string {
        foreach ( $fields as $field ) {
            $value = class_exists( MuckRackVerification::class ) ? MuckRackVerification::author_field( $author_id, (string) $field ) : get_user_meta( $author_id, (string) $field, true );
            if ( is_array( $value ) || is_object( $value ) ) {
                continue;
            }
            $value = trim( (string) $value );
            if ( "" !== $value ) {
                return $value;
            }
        }
        return "";
    }

    private static function rebind_author_html( string $html, array $primary, array $author, string $badge_context = "" ): string {
        $search_replace = [
            (string) $primary["name"] => (string) $author["name"],
            esc_html( (string) $primary["name"] ) => esc_html( (string) $author["name"] ),
            esc_attr( (string) $primary["name"] ) => esc_attr( (string) $author["name"] ),
            (string) $primary["url"] => (string) $author["url"],
            esc_url( (string) $primary["url"] ) => esc_url( (string) $author["url"] ),
            "/author/" . (string) $primary["slug"] . "/" => "/author/" . (string) $author["slug"] . "/",
        ];

        if ( "" !== (string) $primary["avatar"] && "" !== (string) $author["avatar"] ) {
            $search_replace[ (string) $primary["avatar"] ] = (string) $author["avatar"];
            $search_replace[ esc_url( (string) $primary["avatar"] ) ] = esc_url( (string) $author["avatar"] );
        }
        foreach ( (array) ( $primary["avatars"] ?? [] ) as $size => $primary_avatar ) {
            $author_avatar = (string) ( $author["avatars"][ $size ] ?? "" );
            if ( "" === (string) $primary_avatar || "" === $author_avatar ) {
                continue;
            }
            $search_replace[ (string) $primary_avatar ] = $author_avatar;
            $search_replace[ esc_url( (string) $primary_avatar ) ] = esc_url( $author_avatar );
        }

        $html = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $html );
        $html = self::rebind_dynamic_author_blocks( $html, $author );
        $html = self::rebind_social_links( $html, $author );
        return self::inject_author_verification_badge( $html, $author, $badge_context );
    }

    private static function mark_repeated_author_html( string $html, array $author, int $index, int $count ): string {
        $doc = new \DOMDocument( "1.0", "UTF-8" );
        $previous = libxml_use_internal_errors( true );
        $loaded = $doc->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( ! $loaded ) {
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
            $child->setAttribute( "data-smpi-author-index", (string) $index );
            $child->setAttribute( "data-smpi-author-id", (string) $author["id"] );
            $child->setAttribute( "data-smpi-author-name", (string) $author["name"] );
            if ( 0 === $index ) {
                $child->setAttribute( "data-smpi-multi-author-count", (string) $count );
            }
            $existing = trim( $child->getAttribute( "class" ) );
            if ( ! preg_match( '/(^|\s)smpi-multi-author-item(\s|$)/', $existing ) ) {
                $child->setAttribute( "class", trim( $existing . " smpi-multi-author-item" ) );
            }
        }

        $out = "";
        foreach ( $body->childNodes as $child ) {
            $out .= $doc->saveHTML( $child );
        }
        return "" !== $out ? $out : $html;
    }

    private static function author_shortcode_value( array $author, string $field ): string {
        $fields = isset( $author["fields"] ) && is_array( $author["fields"] ) ? $author["fields"] : [];
        if ( in_array( $field, [ "", "name", "display_name" ], true ) ) {
            return (string) $author["name"];
        }
        if ( in_array( $field, [ "id", "ids", "user_id" ], true ) ) {
            return (string) $author["id"];
        }
        if ( in_array( $field, [ "url", "author_url" ], true ) ) {
            return (string) $author["url"];
        }
        if ( "email" === $field ) {
            return (string) $author["email"];
        }
        if ( in_array( $field, [ "job_title", "title", "subtitle", "bio", "bio_short", "description" ], true ) ) {
            return wp_strip_all_tags( (string) ( $fields[ $field ] ?? "" ) );
        }
        return wp_strip_all_tags( (string) ( $fields[ $field ] ?? "" ) );
    }

    private static function inject_author_verification_badge( string $html, array $author, string $context ): string {
        if ( "" === $context || ! class_exists( MuckRackVerification::class ) || ! Settings::bool( "muckrack_verified_enabled" ) ) {
            return $html;
        }
        if ( ! in_array( $context, Settings::array( "muckrack_verified_contexts" ), true ) ) {
            return $html;
        }

        $badge = MuckRackVerification::verification_icon( (int) $author["id"], (string) Settings::get( "muckrack_verified_style", "tooltip" ), $context );
        if ( "" === $badge ) {
            return $html;
        }

        $doc = new \DOMDocument( "1.0", "UTF-8" );
        $previous = libxml_use_internal_errors( true );
        $loaded = $doc->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( ! $loaded ) {
            return $html;
        }

        $xpath = new \DOMXPath( $doc );
        self::remove_existing_verification_badges( $xpath );
        $target = self::find_author_name_node( $xpath, (string) $author["name"] );
        if ( ! $target ) {
            return self::document_body_html( $doc, $html );
        }

        $fragment = self::fragment_from_html( $doc, " " . $badge );
        if ( ! $fragment ) {
            return self::document_body_html( $doc, $html );
        }

        if ( "a" === strtolower( $target->tagName ) && $target->parentNode ) {
            $target->parentNode->insertBefore( $fragment, $target->nextSibling );
        } else {
            $target->appendChild( $fragment );
        }

        return self::document_body_html( $doc, $html );
    }

    private static function remove_existing_verification_badges( \DOMXPath $xpath ): void {
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

    private static function find_author_name_node( \DOMXPath $xpath, string $name ): ?\DOMElement {
        $target = self::normalize_match_text( $name );
        if ( "" === $target ) {
            return null;
        }

        $nodes = $xpath->query( '//*[not(self::script) and not(self::style) and not(self::svg)]' );
        if ( ! $nodes ) {
            return null;
        }

        $best = null;
        $best_score = PHP_INT_MAX;
        foreach ( $nodes as $node ) {
            if ( ! $node instanceof \DOMElement ) {
                continue;
            }
            if ( self::normalize_match_text( (string) $node->textContent ) !== $target ) {
                continue;
            }
            $child_exact = false;
            foreach ( $node->childNodes as $child ) {
                if ( $child instanceof \DOMElement && self::normalize_match_text( (string) $child->textContent ) === $target ) {
                    $child_exact = true;
                    break;
                }
            }
            if ( $child_exact ) {
                continue;
            }
            $score = strlen( (string) $node->textContent );
            if ( $score < $best_score ) {
                $best = $node;
                $best_score = $score;
            }
        }

        return $best;
    }

    private static function normalize_match_text( string $value ): string {
        $value = strtolower( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, "UTF-8" ) ) );
        return preg_replace( '/[^a-z0-9]+/', '', $value ) ?: "";
    }

    private static function document_body_html( \DOMDocument $doc, string $fallback ): string {
        $body = $doc->getElementsByTagName( "body" )->item( 0 );
        if ( ! $body ) {
            return $fallback;
        }
        $out = "";
        foreach ( $body->childNodes as $child ) {
            $out .= $doc->saveHTML( $child );
        }
        return "" !== $out ? $out : $fallback;
    }

    private static function rebind_dynamic_author_blocks( string $html, array $author ): string {
        $field_widgets = self::marked_elementor_author_field_widgets();
        if ( empty( $field_widgets ) || false === stripos( $html, "elementor-element-" ) ) {
            return $html;
        }

        $doc = new \DOMDocument( "1.0", "UTF-8" );
        $previous = libxml_use_internal_errors( true );
        $loaded = $doc->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( ! $loaded ) {
            return $html;
        }

        $xpath = new \DOMXPath( $doc );
        foreach ( $field_widgets as $element_id => $field ) {
            $nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " elementor-element-' . $element_id . ' ")]' );
            if ( ! $nodes || 0 === $nodes->length ) {
                continue;
            }
            $value = self::author_display_field_html( $author, (string) $field );
            foreach ( $nodes as $node ) {
                if ( ! $node instanceof \DOMElement ) {
                    continue;
                }
                $target = self::first_descendant_by_class( $node, "elementor-widget-container" ) ?: $node;
                while ( $target->firstChild ) {
                    $target->removeChild( $target->firstChild );
                }
                if ( "" === $value ) {
                    continue;
                }
                $fragment = self::fragment_from_html( $doc, $value );
                if ( $fragment ) {
                    $target->appendChild( $fragment );
                }
            }
        }
        $body = $doc->getElementsByTagName( "body" )->item( 0 );
        if ( ! $body ) {
            return $html;
        }
        $out = "";
        foreach ( $body->childNodes as $child ) {
            $out .= $doc->saveHTML( $child );
        }
        return "" !== $out ? $out : $html;
    }

    private static function author_display_field_html( array $author, string $field ): string {
        $field = sanitize_key( $field );
        $fields = isset( $author["fields"] ) && is_array( $author["fields"] ) ? $author["fields"] : [];
        if ( in_array( $field, [ "bio", "description", "author_bio" ], true ) ) {
            $value = (string) ( $fields["bio"] ?? $fields["description"] ?? "" );
            return "" !== $value ? wp_kses_post( wpautop( $value ) ) : "";
        }
        if ( "bio_short" === $field ) {
            $value = (string) ( $fields["bio_short"] ?? "" );
            return "" !== $value ? esc_html( wp_strip_all_tags( $value ) ) : "";
        }
        if ( in_array( $field, [ "job_title", "title", "author_title" ], true ) ) {
            $value = (string) ( $fields["job_title"] ?? $fields["title"] ?? $fields["subtitle"] ?? "" );
            return "" !== $value ? esc_html( wp_strip_all_tags( $value ) ) : "";
        }
        if ( in_array( $field, [ "subtitle", "author_subtitle" ], true ) ) {
            $value = (string) ( $fields["subtitle"] ?? $fields["title"] ?? "" );
            return "" !== $value ? esc_html( wp_strip_all_tags( $value ) ) : "";
        }
        $value = (string) ( $fields[ $field ] ?? "" );
        return "" !== $value ? esc_html( wp_strip_all_tags( $value ) ) : "";
    }

    private static function first_descendant_by_class( \DOMElement $node, string $class ): ?\DOMElement {
        foreach ( $node->getElementsByTagName( "*" ) as $child ) {
            if ( $child instanceof \DOMElement && preg_match( '/(^|\s)' . preg_quote( $class, "/" ) . '(\s|$)/', $child->getAttribute( "class" ) ) ) {
                return $child;
            }
        }
        return null;
    }

    private static function fragment_from_html( \DOMDocument $doc, string $html ): ?\DOMDocumentFragment {
        $tmp = new \DOMDocument( "1.0", "UTF-8" );
        $previous = libxml_use_internal_errors( true );
        $loaded = $tmp->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( ! $loaded ) {
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

    private static function rebind_social_links( string $html, array $author ): string {
        if ( false === stripos( $html, "<a" ) ) {
            return $html;
        }

        return preg_replace_callback(
            '/<a\b([^>]*)>(.*?)<\/a>/is',
            static function ( array $matches ) use ( $author ): string {
                $attrs = $matches[1];
                $inner = $matches[2];
                $label = strtolower( wp_strip_all_tags( $inner ) );
                $target = "";

                if ( false !== strpos( $label, "twitter" ) || false !== strpos( $label, "/ x" ) || preg_match( '/(^|\s)x(\s|$)/', $label ) ) {
                    $target = (string) ( $author["fields"]["x"] ?? "" );
                } elseif ( false !== strpos( $label, "linkedin" ) ) {
                    $target = (string) ( $author["fields"]["linkedin"] ?? "" );
                } elseif ( false !== strpos( $label, "email" ) ) {
                    $target = (string) ( $author["fields"]["email"] ?? "" );
                } elseif ( false !== strpos( $label, "website" ) ) {
                    $target = (string) ( $author["fields"]["website"] ?? "" );
                } else {
                    return $matches[0];
                }

                if ( "" === $target ) {
                    $attrs = preg_replace( '/\s href=(["\']).*?\1/i', '', $attrs );
                    $attrs .= ' data-smpi-empty-author-social="1" style="display:none"';
                    return '<a href="#"' . $attrs . '>' . $inner . '</a>';
                }

                if ( ! preg_match( '/^[a-z][a-z0-9+.-]*:/i', $target ) && false === strpos( $target, "@" ) ) {
                    $target = "https://" . ltrim( $target, "/" );
                }
                $attrs = preg_replace( '/\s href=(["\']).*?\1/i', '', $attrs );
                return '<a href="' . esc_url( $target ) . '"' . $attrs . '>' . $inner . '</a>';
            },
            $html
        ) ?: $html;
    }

    private static function marked_elementor_author_field_widgets(): array {
        if ( null !== self::$marked_author_field_widgets ) {
            return self::$marked_author_field_widgets;
        }

        self::$marked_author_field_widgets = [];
        foreach ( self::elementor_templates_with_author_markers() as $template_id ) {
            $data = get_post_meta( (int) $template_id, "_elementor_data", true );
            if ( ! is_string( $data ) || "" === $data ) {
                continue;
            }
            $decoded = json_decode( $data, true );
            if ( is_array( $decoded ) ) {
                self::collect_marked_author_field_widgets( $decoded, self::$marked_author_field_widgets );
            }
        }

        return self::$marked_author_field_widgets;
    }

    private static function collect_marked_author_field_widgets( array $nodes, array &$map ): void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            if ( self::node_has_author_module_marker( $node ) ) {
                self::collect_author_field_widgets( $node["elements"] ?? [], $map );
                continue;
            }
            if ( ! empty( $node["elements"] ) && is_array( $node["elements"] ) ) {
                self::collect_marked_author_field_widgets( $node["elements"], $map );
            }
        }
    }

    private static function collect_author_field_widgets( array $nodes, array &$map ): void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $field = self::elementor_author_field_for_node( $node );
            if ( "" !== $field && ! empty( $node["id"] ) && is_string( $node["id"] ) ) {
                $map[ preg_replace( '/[^A-Za-z0-9_-]/', '', $node["id"] ) ] = $field;
            }
            if ( ! empty( $node["elements"] ) && is_array( $node["elements"] ) ) {
                self::collect_author_field_widgets( $node["elements"], $map );
            }
        }
    }

    private static function elementor_author_field_for_node( array $node ): string {
        $settings = isset( $node["settings"] ) && is_array( $node["settings"] ) ? $node["settings"] : [];
        $haystack = wp_json_encode( $settings ) ?: "";
        $haystack = stripcslashes( html_entity_decode( $haystack, ENT_QUOTES | ENT_HTML5, "UTF-8" ) );
        if ( preg_match( '/acf_author_field[^\]]*field=(?:\\\\?"|["\'])([A-Za-z0-9_-]+)(?:\\\\?"|["\'])/i', $haystack, $match ) ) {
            return sanitize_key( (string) $match[1] );
        }
        if ( false !== strpos( $haystack, "author-info" ) && false !== strpos( $haystack, "description" ) ) {
            return "bio";
        }
        return "";
    }

    private static function normalize_user_ids( $value ): array {
        if ( is_object( $value ) && isset( $value->ID ) ) {
            $value = [ (int) $value->ID ];
        }
        if ( ! is_array( $value ) ) {
            $value = "" === (string) $value ? [] : [ $value ];
        }

        $ids = [];
        foreach ( $value as $item ) {
            if ( is_object( $item ) && isset( $item->ID ) ) {
                $ids[] = (int) $item->ID;
            } elseif ( is_array( $item ) && isset( $item["ID"] ) ) {
                $ids[] = (int) $item["ID"];
            } elseif ( is_scalar( $item ) ) {
                $ids[] = (int) $item;
            }
        }
        return $ids;
    }

    private static function valid_unique_user_ids( array $ids ): array {
        $out = [];
        foreach ( $ids as $id ) {
            $id = absint( $id );
            if ( $id <= 0 || isset( $out[ $id ] ) || ! get_user_by( "id", $id ) ) {
                continue;
            }
            $out[ $id ] = $id;
        }
        return array_values( $out );
    }
}
