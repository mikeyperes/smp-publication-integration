<?php

declare( strict_types=1 );

namespace SMP\PublicationIntegration\PublicationManifest;

defined( 'ABSPATH' ) || exit;

final class HomepageCollector {
    private TaxonomyPolicy $policy;

    public function __construct( ?TaxonomyPolicy $policy = null ) {
        $this->policy = $policy ?? new TaxonomyPolicy();
    }

    /** @return array<string,mixed> */
    public function collect(): array {
        $show_on_front = (string) get_option( 'show_on_front', 'posts' );
        $front_page_id = 'page' === $show_on_front ? (int) get_option( 'page_on_front', 0 ) : 0;
        $elements      = [];
        $builder       = 'wordpress';

        if ( $front_page_id > 0 ) {
            $raw = get_post_meta( $front_page_id, '_elementor_data', true );
            if ( is_string( $raw ) && '' !== trim( $raw ) ) {
                $decoded = json_decode( $raw, true );
                if ( is_array( $decoded ) ) {
                    $elements = $decoded;
                    $builder  = 'elementor';
                }
            } elseif ( is_array( $raw ) ) {
                $elements = $raw;
                $builder  = 'elementor';
            }
        }

        return $this->collect_from_elements( $front_page_id, $elements, $builder, $show_on_front );
    }

    /**
     * Kept public so the same deterministic extractor can be fixture-tested.
     *
     * @param array<int,mixed> $elements
     * @return array<string,mixed>
     */
    public function collect_from_elements( int $front_page_id, array $elements, string $builder = 'elementor', string $show_on_front = 'page' ): array {
        $sections       = [];
        $query_widgets  = [];
        $category_index = [];

        foreach ( array_values( $elements ) as $position => $node ) {
            if ( ! is_array( $node ) || $this->excluded_node( $node ) ) {
                continue;
            }

            $section = [
                'order'            => $position + 1,
                'elementor_id'     => sanitize_key( (string) ( $node['id'] ?? '' ) ),
                'label'            => $this->section_label( $node ),
                'heading'          => $this->section_heading( $node ),
                'widget_types'     => [],
                'query_widget_ids' => [],
            ];

            $this->walk_content_node( $node, $section, $query_widgets, $category_index );
            $section['widget_types']     = array_values( array_unique( $section['widget_types'] ) );
            $section['query_widget_ids'] = array_values( array_unique( $section['query_widget_ids'] ) );
            $sections[]                  = $section;
        }

        $categories          = array_values( $category_index );
        $campaign_categories = array_values(
            array_filter(
                $categories,
                static fn( array $category ): bool => 'eligible' === (string) ( $category['campaign_policy']['status'] ?? '' )
            )
        );

        $front_url   = $front_page_id > 0 ? get_permalink( $front_page_id ) : home_url( '/' );
        $front_title = $front_page_id > 0 ? get_the_title( $front_page_id ) : get_bloginfo( 'name' );

        return [
            'mode'                => $show_on_front,
            'page_id'             => $front_page_id,
            'title'               => (string) $front_title,
            'url'                 => (string) $front_url,
            'builder'             => $builder,
            'content_source'      => $front_page_id > 0 ? '_elementor_data' : 'posts_index',
            'sections'            => $sections,
            'query_widgets'       => $query_widgets,
            'categories'          => $categories,
            'campaign_categories' => $campaign_categories,
        ];
    }

    /**
     * @param array<string,mixed> $node
     * @param array<string,mixed> $section
     * @param array<int,array<string,mixed>> $query_widgets
     * @param array<int,array<string,mixed>> $category_index
     */
    private function walk_content_node( array $node, array &$section, array &$query_widgets, array &$category_index ): void {
        if ( $this->excluded_node( $node ) ) {
            return;
        }

        $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
        if ( '' !== $widget_type ) {
            $section['widget_types'][] = $widget_type;
        }

        if ( $this->is_query_widget( $widget_type ) ) {
            $widget = $this->query_widget_record( $node, (string) $section['label'], (string) $section['heading'] );
            if ( ! empty( $widget['categories'] ) ) {
                $query_widgets[]                    = $widget;
                $section['query_widget_ids'][]      = $widget['elementor_id'];
                foreach ( $widget['categories'] as $category ) {
                    $category_id = (int) ( $category['id'] ?? 0 );
                    if ( $category_id < 1 ) {
                        continue;
                    }
                    if ( ! isset( $category_index[ $category_id ] ) ) {
                        $category_index[ $category_id ] = $category;
                        $category_index[ $category_id ]['sources'] = [];
                    }
                    $category_index[ $category_id ]['sources'][] = [
                        'elementor_id' => $widget['elementor_id'],
                        'widget_type'  => $widget['widget_type'],
                        'section'      => $widget['section'],
                    ];
                }
            }
        }

        $children = isset( $node['elements'] ) && is_array( $node['elements'] ) ? $node['elements'] : [];
        foreach ( $children as $child ) {
            if ( is_array( $child ) ) {
                $this->walk_content_node( $child, $section, $query_widgets, $category_index );
            }
        }
    }

    /** @param array<string,mixed> $node */
    private function query_widget_record( array $node, string $section_label, string $section_heading ): array {
        $settings   = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
        $references = $this->taxonomy_references( $settings );
        $categories = [];

        foreach ( $references as $reference ) {
            if ( 'category' !== $reference['taxonomy'] ) {
                continue;
            }
            foreach ( $this->resolve_terms( $reference['field'], $reference['terms'] ) as $term ) {
                $categories[ (int) $term->term_id ] = $this->policy->category_record( $term );
            }
        }

        return [
            'elementor_id' => sanitize_key( (string) ( $node['id'] ?? '' ) ),
            'widget_type'  => sanitize_key( (string) ( $node['widgetType'] ?? '' ) ),
            'section'      => '' !== $section_label ? $section_label : $section_heading,
            'listing_id'   => absint( $settings['listing_id'] ?? $settings['lisitng_id'] ?? 0 ),
            'post_count'   => absint( $settings['posts_num'] ?? $settings['posts_per_page'] ?? 0 ),
            'categories'   => array_values( $categories ),
        ];
    }

    /**
     * @param array<string,mixed> $settings
     * @return array<int,array{taxonomy:string,field:string,terms:array<int,string|int>}>
     */
    private function taxonomy_references( array $settings ): array {
        $references = [];
        $this->find_taxonomy_clauses( $settings, $references );
        $this->find_elementor_term_tokens( $settings, $references );
        $this->find_elementor_query_include_terms( $settings, $references );

        $unique = [];
        foreach ( $references as $reference ) {
            $key = $reference['taxonomy'] . '|' . $reference['field'] . '|' . implode( ',', array_map( 'strval', $reference['terms'] ) );
            $unique[ $key ] = $reference;
        }

        return array_values( $unique );
    }

    /**
     * @param array<mixed> $value
     * @param array<int,array{taxonomy:string,field:string,terms:array<int,string|int>}> $references
     */
    private function find_taxonomy_clauses( array $value, array &$references ): void {
        $taxonomy = sanitize_key( (string) ( $value['tax_query_taxonomy'] ?? $value['taxonomy'] ?? '' ) );
        if ( '' !== $taxonomy ) {
            $field = sanitize_key( (string) ( $value['tax_query_field'] ?? $value['field'] ?? 'term_id' ) );
            $terms = $value['tax_query_terms'] ?? $value['terms'] ?? [];
            $terms = $this->normalize_terms( $terms );
            if ( ! empty( $terms ) ) {
                $references[] = [
                    'taxonomy' => $taxonomy,
                    'field'    => in_array( $field, [ 'term_id', 'id', 'slug', 'name' ], true ) ? $field : 'term_id',
                    'terms'    => $terms,
                ];
            }
        }

        foreach ( $value as $child ) {
            if ( is_array( $child ) ) {
                $this->find_taxonomy_clauses( $child, $references );
            }
        }
    }

    /**
     * @param array<mixed> $value
     * @param array<int,array{taxonomy:string,field:string,terms:array<int,string|int>}> $references
     */
    private function find_elementor_term_tokens( array $value, array &$references, string $context_key = '' ): void {
        foreach ( $value as $key => $child ) {
            $key_name      = is_string( $key ) ? strtolower( $key ) : '';
            $child_context = '' !== $key_name ? $key_name : $context_key;
            if ( is_array( $child ) ) {
                $this->find_elementor_term_tokens( $child, $references, $child_context );
                continue;
            }
            if ( ! is_scalar( $child ) || ! str_contains( $child_context, 'term' ) ) {
                continue;
            }
            if ( preg_match_all( '/(?:^|[,\s])category:(\d+)(?:$|[,\s])/', (string) $child, $matches ) ) {
                $references[] = [
                    'taxonomy' => 'category',
                    'field'    => 'term_id',
                    'terms'    => array_map( 'intval', $matches[1] ),
                ];
            }
        }
    }

    /**
     * CRITICAL — see laravel-hexa-app-publish BUGLOG.md CAMPAIGN-BUG-008.
     * Elementor Pro Loop Grid and Loop Carousel query controls store plain term
     * IDs in `<prefix>_include_term_ids`, applied only when `<prefix>_include`
     * selects "terms". Without this, those homepages report no categories.
     * Exclusion lists (`_exclude_term_ids`) are intentionally ignored.
     *
     * @param array<string,mixed> $settings
     * @param array<int,array{taxonomy:string,field:string,terms:array<int,string|int>}> $references
     */
    private function find_elementor_query_include_terms( array $settings, array &$references ): void {
        foreach ( $settings as $key => $value ) {
            if ( ! is_string( $key ) || ! preg_match( '/^(.+)_include_term_ids$/', $key, $match ) ) {
                continue;
            }
            $include = $settings[ $match[1] . '_include' ] ?? null;
            if ( null !== $include && ! in_array( 'terms', (array) $include, true ) ) {
                continue;
            }
            // `category:ID` tokens are handled by find_elementor_term_tokens(); absint() drops them here.
            $ids = array_values( array_filter( array_map( 'absint', (array) $value ) ) );
            if ( ! empty( $ids ) ) {
                $references[] = [
                    'taxonomy' => 'category',
                    'field'    => 'term_id',
                    'terms'    => $ids,
                ];
            }
        }
    }

    /** @return array<int,string|int> */
    private function normalize_terms( $terms ): array {
        if ( is_string( $terms ) ) {
            $terms = preg_split( '/\s*,\s*/', trim( $terms ) ) ?: [];
        } elseif ( is_int( $terms ) ) {
            $terms = [ $terms ];
        }
        if ( ! is_array( $terms ) ) {
            return [];
        }

        $normalized = [];
        foreach ( $terms as $term ) {
            if ( is_int( $term ) || ( is_string( $term ) && '' !== trim( $term ) ) ) {
                $normalized[] = is_string( $term ) ? trim( $term ) : $term;
            }
        }
        return $normalized;
    }

    /**
     * @param array<int,string|int> $terms
     * @return array<int,object>
     */
    private function resolve_terms( string $field, array $terms ): array {
        $resolved = [];
        foreach ( $terms as $value ) {
            $term = in_array( $field, [ 'term_id', 'id' ], true )
                ? get_term( absint( $value ), 'category' )
                : get_term_by( $field, (string) $value, 'category' );
            if ( is_object( $term ) && ! is_wp_error( $term ) && isset( $term->term_id ) ) {
                $resolved[ (int) $term->term_id ] = $term;
            }
        }
        return array_values( $resolved );
    }

    private function is_query_widget( string $widget_type ): bool {
        if ( '' === $widget_type ) {
            return false;
        }
        return in_array( $widget_type, [ 'posts', 'loop-grid', 'archive-posts', 'jet-listing-grid', 'jet-smart-listing', 'dynamic-posts-base', 'loop-carousel' ], true )
            || str_contains( $widget_type, 'post-list' );
    }

    /** @param array<string,mixed> $node */
    private function excluded_node( array $node ): bool {
        $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
        if ( in_array( $widget_type, [ 'nav-menu', 'wp-widget-nav_menu', 'theme-site-logo', 'theme-site-title', 'template' ], true ) ) {
            return true;
        }

        $settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
        $html_tag = sanitize_key( (string) ( $settings['html_tag'] ?? '' ) );
        if ( in_array( $html_tag, [ 'header', 'footer', 'nav' ], true ) ) {
            return true;
        }

        $label = strtolower( trim( (string) ( $settings['_title'] ?? '' ) ) );
        return in_array( $label, [ 'header', 'site header', 'footer', 'site footer', 'menu', 'main menu', 'navigation' ], true );
    }

    /** @param array<string,mixed> $node */
    private function section_label( array $node ): string {
        $settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
        return sanitize_text_field( (string) ( $settings['_title'] ?? $settings['title'] ?? '' ) );
    }

    /** @param array<string,mixed> $node */
    private function section_heading( array $node ): string {
        $children = isset( $node['elements'] ) && is_array( $node['elements'] ) ? $node['elements'] : [];
        foreach ( $children as $child ) {
            if ( ! is_array( $child ) || $this->excluded_node( $child ) ) {
                continue;
            }
            $settings = isset( $child['settings'] ) && is_array( $child['settings'] ) ? $child['settings'] : [];
            foreach ( [ 'title', 'title_text', 'heading', 'text' ] as $key ) {
                if ( isset( $settings[ $key ] ) && is_scalar( $settings[ $key ] ) && '' !== trim( (string) $settings[ $key ] ) ) {
                    return sanitize_text_field( wp_strip_all_tags( (string) $settings[ $key ] ) );
                }
            }
            $nested = $this->section_heading( $child );
            if ( '' !== $nested ) {
                return $nested;
            }
        }
        return '';
    }
}
