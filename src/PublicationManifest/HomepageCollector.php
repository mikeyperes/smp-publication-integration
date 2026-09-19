<?php

declare( strict_types=1 );

namespace SMP\PublicationIntegration\PublicationManifest;

defined( 'ABSPATH' ) || exit;

final class HomepageCollector {
    private const MAX_TEMPLATE_DEPTH = 8;

    private TaxonomyPolicy $policy;
    private ElementorQueryInspector $query_inspector;
    private NativeWidgetQueryCollector $native_query_collector;
    private int $current_document_id = 0;

    public function __construct(
        ?TaxonomyPolicy $policy = null,
        ?ElementorQueryInspector $query_inspector = null,
        ?NativeWidgetQueryCollector $native_query_collector = null
    ) {
        $this->policy                 = $policy ?? new TaxonomyPolicy();
        $this->query_inspector        = $query_inspector ?? new ElementorQueryInspector();
        $this->native_query_collector = $native_query_collector ?? new NativeWidgetQueryCollector();
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
        $this->current_document_id = $front_page_id;
        $this->native_query_collector->begin_collection();
        $sections       = [];
        $query_widgets  = [];
        $category_index = [];
        $warnings       = [];

        foreach ( array_values( $elements ) as $position => $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            if ( $this->excluded_node( $node ) ) {
                $this->walk_private_query_history( $node );
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
            $nested_sections = [];

            $this->walk_content_node( $node, $section, $query_widgets, $category_index, $warnings, $nested_sections );
            $section['widget_types']     = array_values( array_unique( $section['widget_types'] ) );
            $section['query_widget_ids'] = array_values( array_unique( $section['query_widget_ids'] ) );
            $sections[]                  = $section;
            foreach ( $nested_sections as $nested_section ) {
                $nested_section['order']            = count( $sections ) + 1;
                $nested_section['widget_types']     = array_values( array_unique( $nested_section['widget_types'] ) );
                $nested_section['query_widget_ids'] = array_values( array_unique( $nested_section['query_widget_ids'] ) );
                $sections[]                         = $nested_section;
            }
        }
        foreach ( $sections as $index => &$ordered_section ) {
            $ordered_section['order'] = $index + 1;
        }
        unset( $ordered_section );

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
            'collection_status'   => empty( $warnings ) ? 'complete' : 'partial',
            'collection_warnings' => $this->unique_warnings( $warnings ),
        ];
    }

    /**
     * @param array<string,mixed> $node
     * @param array<string,mixed> $section
     * @param array<int,array<string,mixed>> $query_widgets
     * @param array<int,array<string,mixed>> $category_index
     * @param array<int,array<string,mixed>> $warnings
     * @param array<int,array<string,mixed>> $nested_sections
     * @param array<int,int> $template_chain
     * @param array<int,string> $template_scope
     */
    private function walk_content_node(
        array $node,
        array &$section,
        array &$query_widgets,
        array &$category_index,
        array &$warnings,
        array &$nested_sections,
        int $template_depth = 0,
        array $template_chain = [],
        array $template_scope = []
    ): void {
        if ( $this->excluded_node( $node ) ) {
            $this->walk_private_query_history( $node, $template_depth, $template_chain );
            return;
        }

        $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
        if ( '' !== $widget_type ) {
            $section['widget_types'][] = $widget_type;
        }

        if ( $this->is_query_widget( $widget_type ) ) {
            $widget = $this->query_widget_record( $node, (string) $section['label'], (string) $section['heading'], $template_chain, $template_scope );
            foreach ( $widget['warnings'] as $warning ) {
                $warnings[] = $warning;
            }
            if ( ! empty( $widget['categories'] ) || ! empty( $widget['warnings'] ) || ! empty( $widget['native_query']['resolved'] ) ) {
                $query_widgets[]               = $widget;
                $section['query_widget_ids'][] = $widget['elementor_id'];
            }
            if ( ! empty( $widget['categories'] ) ) {
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
                        'template_id'  => $widget['template_id'],
                        'template_chain' => $widget['template_chain'],
                        'category_source' => $widget['category_source'],
                        'native_provider' => (string) ( $widget['native_query']['provider'] ?? '' ),
                    ];
                }
            }
        }

        if ( 'template' === $widget_type ) {
            $this->walk_template_widget(
                $node,
                $section,
                $query_widgets,
                $category_index,
                $warnings,
                $nested_sections,
                $template_depth,
                $template_chain,
                $template_scope
            );
        }

        $children = isset( $node['elements'] ) && is_array( $node['elements'] ) ? $node['elements'] : [];
        foreach ( $children as $child ) {
            if ( is_array( $child ) ) {
                $this->walk_content_node( $child, $section, $query_widgets, $category_index, $warnings, $nested_sections, $template_depth, $template_chain, $template_scope );
            }
        }
    }

    /** @param array<string,mixed> $node */
    private function query_widget_record( array $node, string $section_label, string $section_heading, array $template_chain, array $template_scope ): array {
        $settings   = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
        $inspection = $this->query_inspector->inspect( $settings );
        $references = $inspection['references'];
        $categories = [];
        $category_source = 'saved_query';
        $native_query = [
            'attempted'    => false,
            'resolved'     => false,
            'provider'     => '',
            'post_count'   => 0,
            'result_limit' => 0,
        ];

        foreach ( $references as $reference ) {
            if ( 'category' !== $reference['taxonomy'] ) {
                continue;
            }
            foreach ( $this->resolve_terms( $reference['field'], $reference['terms'] ) as $term ) {
                $categories[ (int) $term->term_id ] = $this->policy->category_record( $term );
            }
        }

        $needs_native_evidence = empty( $categories ) || ! empty( $inspection['warnings'] );
        $native_result = $this->native_query_collector->collect( $node, $this->current_document_id );
        if ( $needs_native_evidence ) {
            $native_query  = [
                'attempted'    => true,
                'resolved'     => (bool) $native_result['resolved'],
                'provider'     => (string) $native_result['provider'],
                'post_count'   => (int) $native_result['post_count'],
                'result_limit' => (int) $native_result['result_limit'],
            ];

            if ( $native_result['resolved'] ) {
                $categories      = [];
                $category_source = 'native_query_results';
                $inspection['warnings'] = [];
                foreach ( $this->resolve_terms( 'term_id', $native_result['category_ids'] ) as $term ) {
                    $categories[ (int) $term->term_id ] = $this->policy->category_record( $term );
                }
            }
            if ( is_array( $native_result['warning'] ) ) {
                $inspection['warnings'][] = $native_result['warning'];
            }
        }

        $source_elementor_id = sanitize_key( (string) ( $node['id'] ?? '' ) );
        $elementor_id = $this->evidence_elementor_id( $source_elementor_id, $template_scope );
        $widget_type  = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
        $warnings     = [];
        foreach ( $inspection['warnings'] as $warning ) {
            $warnings[] = $this->widget_warning( $warning, $elementor_id, $widget_type, $template_chain );
        }
        if ( empty( $categories ) && ! $native_query['resolved'] ) {
            $warnings[] = $this->widget_warning(
                [
                    'code'    => 'query_scope_not_statically_resolved',
                    'message' => 'No positive category restriction could be resolved from this query widget; runtime results may contain additional categories.',
                    'context' => [],
                ],
                $elementor_id,
                $widget_type,
                $template_chain
            );
        }

        return [
            'elementor_id' => $elementor_id,
            'source_elementor_id' => $source_elementor_id,
            'widget_type'  => $widget_type,
            'section'      => '' !== $section_label ? $section_label : $section_heading,
            'listing_id'   => absint( $settings['listing_id'] ?? $settings['lisitng_id'] ?? 0 ),
            'post_count'   => absint( $settings['posts_num'] ?? $settings['posts_per_page'] ?? 0 ),
            'categories'   => array_values( $categories ),
            'template_id'  => empty( $template_chain ) ? 0 : (int) end( $template_chain ),
            'template_chain' => array_values( $template_chain ),
            'category_source' => $category_source,
            'native_query' => $native_query,
            'warnings'     => $warnings,
        ];
    }

    /**
     * Responsive-hidden and other excluded Elementor nodes can still execute
     * server-side queries. Reproduce only their ordered query history; never
     * expose them as public homepage category evidence.
     *
     * @param array<string,mixed> $node
     * @param array<int,int> $template_chain
     */
    private function walk_private_query_history( array $node, int $template_depth = 0, array $template_chain = [] ): void {
        $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
        if ( $this->is_query_widget( $widget_type ) ) {
            $this->native_query_collector->collect( $node, $this->current_document_id );
        }

        if ( 'template' === $widget_type ) {
            $settings    = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
            $template_id = absint( $settings['template_id'] ?? $node['template_id'] ?? 0 );
            if ( $template_id < 1 || $template_depth >= self::MAX_TEMPLATE_DEPTH || in_array( $template_id, $template_chain, true ) || ! function_exists( 'get_post_meta' ) ) {
                $this->native_query_collector->invalidate_previous_results();
                return;
            }

            $template_elements = $this->decode_elementor_data( get_post_meta( $template_id, '_elementor_data', true ) );
            if ( null === $template_elements ) {
                $this->native_query_collector->invalidate_previous_results();
                return;
            }

            $resolved_chain = array_merge( $template_chain, [ $template_id ] );
            foreach ( $template_elements as $template_node ) {
                if ( is_array( $template_node ) ) {
                    $this->walk_private_query_history( $template_node, $template_depth + 1, $resolved_chain );
                }
            }
        }

        $children = isset( $node['elements'] ) && is_array( $node['elements'] ) ? $node['elements'] : [];
        foreach ( $children as $child ) {
            if ( is_array( $child ) ) {
                $this->walk_private_query_history( $child, $template_depth, $template_chain );
            }
        }
    }

    /**
     * @param array<string,mixed> $node
     * @param array<string,mixed> $section
     * @param array<int,array<string,mixed>> $query_widgets
     * @param array<int,array<string,mixed>> $category_index
     * @param array<int,array<string,mixed>> $warnings
     * @param array<int,array<string,mixed>> $nested_sections
     * @param array<int,int> $template_chain
     * @param array<int,string> $template_scope
     */
    private function walk_template_widget(
        array $node,
        array &$section,
        array &$query_widgets,
        array &$category_index,
        array &$warnings,
        array &$nested_sections,
        int $template_depth,
        array $template_chain,
        array $template_scope
    ): void {
        $settings    = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
        $template_id = absint( $settings['template_id'] ?? $node['template_id'] ?? 0 );

        if ( $template_id < 1 ) {
            $this->native_query_collector->invalidate_previous_results();
            $warnings[] = $this->template_warning(
                'template_id_missing',
                'An Elementor Template widget has no resolvable template ID.',
                $node,
                $template_chain,
                $template_scope
            );
            return;
        }

        if ( in_array( $template_id, $template_chain, true ) ) {
            $this->native_query_collector->invalidate_previous_results();
            $warnings[] = $this->template_warning(
                'template_cycle_detected',
                'Nested Elementor template traversal stopped because the template chain contains a cycle.',
                $node,
                $template_chain,
                $template_scope,
                [ 'template_id' => $template_id ]
            );
            return;
        }

        if ( $template_depth >= self::MAX_TEMPLATE_DEPTH ) {
            $this->native_query_collector->invalidate_previous_results();
            $warnings[] = $this->template_warning(
                'template_depth_limit_reached',
                'Nested Elementor template traversal stopped at the supported depth limit.',
                $node,
                $template_chain,
                $template_scope,
                [ 'template_id' => $template_id, 'maximum_depth' => self::MAX_TEMPLATE_DEPTH ]
            );
            return;
        }

        if ( ! function_exists( 'get_post_meta' ) ) {
            $this->native_query_collector->invalidate_previous_results();
            $warnings[] = $this->template_warning(
                'template_storage_unavailable',
                'Elementor template content could not be loaded from WordPress post metadata.',
                $node,
                $template_chain,
                $template_scope,
                [ 'template_id' => $template_id ]
            );
            return;
        }

        $template_elements = $this->decode_elementor_data( get_post_meta( $template_id, '_elementor_data', true ) );
        if ( null === $template_elements ) {
            $this->native_query_collector->invalidate_previous_results();
            $warnings[] = $this->template_warning(
                'template_data_unavailable',
                'The referenced Elementor template has no readable element data.',
                $node,
                $template_chain,
                $template_scope,
                [ 'template_id' => $template_id ]
            );
            return;
        }

        $resolved_chain = array_merge( $template_chain, [ $template_id ] );
        $template_widget_id = sanitize_key( (string) ( $node['id'] ?? 'template' ) );
        $resolved_scope = array_merge( $template_scope, [ $template_widget_id . '-' . $template_id ] );
        foreach ( $template_elements as $template_node ) {
            if ( is_array( $template_node ) ) {
                $template_section = [
                    'order'            => 0,
                    'elementor_id'     => $this->evidence_elementor_id( sanitize_key( (string) ( $template_node['id'] ?? '' ) ), $resolved_scope ),
                    'source_elementor_id' => sanitize_key( (string) ( $template_node['id'] ?? '' ) ),
                    'label'            => $this->section_label( $template_node ),
                    'heading'          => $this->section_heading( $template_node ),
                    'widget_types'     => [],
                    'query_widget_ids' => [],
                    'template_id'      => $template_id,
                    'template_chain'   => $resolved_chain,
                ];
                $descendant_sections = [];
                $this->walk_content_node(
                    $template_node,
                    $template_section,
                    $query_widgets,
                    $category_index,
                    $warnings,
                    $descendant_sections,
                    $template_depth + 1,
                    $resolved_chain,
                    $resolved_scope
                );
                $section['widget_types'] = array_merge( $section['widget_types'], $template_section['widget_types'] );
                $section['query_widget_ids'] = array_merge( $section['query_widget_ids'], $template_section['query_widget_ids'] );
                if ( '' !== $template_section['label'] || '' !== $template_section['heading'] ) {
                    $nested_sections[] = $template_section;
                }
                foreach ( $descendant_sections as $descendant_section ) {
                    $nested_sections[] = $descendant_section;
                }
            }
        }
    }

    /** @return array<int,mixed>|null */
    private function decode_elementor_data( $raw ): ?array {
        if ( is_array( $raw ) ) {
            return array_values( $raw );
        }
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
            return null;
        }
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? array_values( $decoded ) : null;
    }

    /**
     * @param array<string,mixed> $warning
     * @param array<int,int> $template_chain
     * @return array<string,mixed>
     */
    private function widget_warning( array $warning, string $elementor_id, string $widget_type, array $template_chain ): array {
        return [
            'code'           => sanitize_key( (string) ( $warning['code'] ?? 'query_collection_warning' ) ),
            'message'        => sanitize_text_field( (string) ( $warning['message'] ?? 'The query widget could not be fully interpreted.' ) ),
            'elementor_id'   => $elementor_id,
            'widget_type'    => $widget_type,
            'template_id'    => empty( $template_chain ) ? 0 : (int) end( $template_chain ),
            'template_chain' => array_values( $template_chain ),
            'context'        => isset( $warning['context'] ) && is_array( $warning['context'] ) ? $warning['context'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $node
     * @param array<int,int> $template_chain
     * @param array<int,string> $template_scope
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function template_warning( string $code, string $message, array $node, array $template_chain, array $template_scope, array $context = [] ): array {
        return $this->widget_warning(
            [ 'code' => $code, 'message' => $message, 'context' => $context ],
            $this->evidence_elementor_id( sanitize_key( (string) ( $node['id'] ?? '' ) ), $template_scope ),
            'template',
            $template_chain
        );
    }

    /** @param array<int,string> $template_scope */
    private function evidence_elementor_id( string $elementor_id, array $template_scope ): string {
        if ( empty( $template_scope ) ) {
            return $elementor_id;
        }
        return sanitize_key( 'template-' . implode( '-', $template_scope ) . '-' . $elementor_id );
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
        if ( in_array( $widget_type, [ 'nav-menu', 'wp-widget-nav_menu', 'theme-site-logo', 'theme-site-title' ], true ) ) {
            return true;
        }

        $settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
        if ( $this->all_devices_hidden( $settings ) ) {
            return true;
        }
        $html_tag = sanitize_key( (string) ( $settings['html_tag'] ?? '' ) );
        if ( in_array( $html_tag, [ 'header', 'footer', 'nav' ], true ) ) {
            return true;
        }

        $label = strtolower( trim( (string) ( $settings['_title'] ?? '' ) ) );
        return in_array( $label, [ 'header', 'site header', 'footer', 'site footer', 'menu', 'main menu', 'navigation' ], true );
    }

    /** @param array<string,mixed> $settings */
    private function all_devices_hidden( array $settings ): bool {
        foreach ( [ 'hide_desktop', 'hide_tablet', 'hide_mobile' ] as $key ) {
            $value = $settings[ $key ] ?? null;
            if ( ! in_array( $value, [ true, 1, '1', 'true', 'yes' ], true ) ) {
                return false;
            }
        }
        return true;
    }

    /** @param array<int,array<string,mixed>> $warnings @return array<int,array<string,mixed>> */
    private function unique_warnings( array $warnings ): array {
        $unique = [];
        foreach ( $warnings as $warning ) {
            $encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $warning ) : json_encode( $warning );
            $unique[ is_string( $encoded ) ? $encoded : serialize( $warning ) ] = $warning;
        }
        return array_values( $unique );
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
