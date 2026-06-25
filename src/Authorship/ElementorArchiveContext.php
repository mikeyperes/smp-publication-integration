<?php
namespace smp_publication_integration\Authorship;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class ElementorArchiveContext {
    private array $authordata_stack = [];
    private int $depth = 0;
    private ?array $element_ids = null;

    public function before_render( $element ): void {
        if ( ! is_author() || ! is_object( $element ) || ! method_exists( $element, "get_id" ) ) {
            return;
        }
        $element_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $element->get_id() );
        if ( "" === $element_id || ! in_array( $element_id, $this->element_ids(), true ) ) {
            return;
        }
        $author = AuthorQueryIntegration::current_archive_author();
        if ( ! $author instanceof \WP_User ) {
            return;
        }

        global $authordata;
        $key = spl_object_id( $element );
        $this->authordata_stack[ $key ][] = $authordata ?? null;
        $this->depth++;
        $authordata = $author;
    }

    public function after_render( $element ): void {
        if ( ! is_object( $element ) ) {
            return;
        }
        $key = spl_object_id( $element );
        if ( empty( $this->authordata_stack[ $key ] ) ) {
            return;
        }

        global $authordata;
        $authordata = array_pop( $this->authordata_stack[ $key ] );
        if ( empty( $this->authordata_stack[ $key ] ) ) {
            unset( $this->authordata_stack[ $key ] );
        }
        $this->depth = max( 0, $this->depth - 1 );
    }

    public function active(): bool {
        return $this->depth > 0;
    }

    private function element_ids(): array {
        if ( null !== $this->element_ids ) {
            return $this->element_ids;
        }
        $this->element_ids = [];
        $templates = get_posts(
            [
                "post_type" => "elementor_library",
                "post_status" => [ "publish", "draft", "private" ],
                "posts_per_page" => 100,
                "fields" => "ids",
                "no_found_rows" => true,
                "meta_query" => [
                    [
                        "key" => "_elementor_template_type",
                        "value" => "archive",
                    ],
                ],
            ]
        );
        foreach ( $templates as $template_id ) {
            $data = get_post_meta( (int) $template_id, "_elementor_data", true );
            $nodes = is_string( $data ) ? json_decode( $data, true ) : null;
            if ( is_array( $nodes ) ) {
                $this->collect_ids( $nodes );
            }
        }
        $this->element_ids = array_values( array_unique( $this->element_ids ) );
        return $this->element_ids;
    }

    private function collect_ids( array $nodes ): void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = isset( $node["settings"] ) && is_array( $node["settings"] ) ? $node["settings"] : [];
            $dynamic = wp_json_encode( $settings["__dynamic__"] ?? [] ) ?: "";
            if (
                preg_match( '/author-(?:name|info|profile-picture|url)|acf_author_field|author_(?:name|bio|title|subtitle|image)/i', $dynamic )
                && ! empty( $node["id"] )
            ) {
                $this->element_ids[] = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $node["id"] );
            }
            if ( ! empty( $node["elements"] ) && is_array( $node["elements"] ) ) {
                $this->collect_ids( $node["elements"] );
            }
        }
    }
}
