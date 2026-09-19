<?php

declare( strict_types=1 );

namespace SMP\PublicationIntegration\PublicationManifest;

defined( 'ABSPATH' ) || exit;

/**
 * Interprets the static parts of Elementor query controls without executing
 * the query or treating exclusion controls as positive homepage evidence.
 */
final class ElementorQueryInspector {
    /**
     * @param array<string,mixed> $settings
     * @return array{references:array<int,array{taxonomy:string,field:string,terms:array<int,string|int>}>,warnings:array<int,array<string,mixed>>}
     */
    public function inspect( array $settings ): array {
        $references = [];
        $warnings   = [];

        $this->find_taxonomy_clauses( $settings, $references, $warnings );
        $this->find_elementor_term_tokens( $settings, $references );
        $this->find_elementor_query_include_terms( $settings, $references );

        if ( $this->has_custom_query_hook( $settings ) ) {
            $warnings[] = $this->warning(
                'custom_query_hook_not_inspected',
                'A custom Elementor query hook can change the rendered posts and cannot be resolved from saved widget settings.'
            );
        }

        $unique = [];
        foreach ( $references as $reference ) {
            $key = $reference['taxonomy'] . '|' . $reference['field'] . '|' . implode( ',', array_map( 'strval', $reference['terms'] ) );
            $unique[ $key ] = $reference;
        }

        return [
            'references' => array_values( $unique ),
            'warnings'   => $this->unique_warnings( $warnings ),
        ];
    }

    /**
     * @param array<mixed> $value
     * @param array<int,array{taxonomy:string,field:string,terms:array<int,string|int>}> $references
     * @param array<int,array<string,mixed>> $warnings
     */
    private function find_taxonomy_clauses( array $value, array &$references, array &$warnings ): void {
        $taxonomy = sanitize_key( (string) ( $value['tax_query_taxonomy'] ?? $value['taxonomy'] ?? '' ) );
        if ( '' !== $taxonomy ) {
            $operator = strtoupper( trim( (string) ( $value['tax_query_operator'] ?? $value['operator'] ?? 'IN' ) ) );
            $field    = sanitize_key( (string) ( $value['tax_query_field'] ?? $value['field'] ?? 'term_id' ) );
            $terms    = $this->normalize_terms( $value['tax_query_terms'] ?? $value['terms'] ?? [] );

            if ( in_array( $operator, [ 'NOT IN', 'NOT_IN', 'NOTIN', 'NOT EXISTS', 'NOT_EXISTS' ], true ) ) {
                // Negative clauses intentionally contribute no positive category evidence.
            } elseif ( ! in_array( $operator, [ 'IN', 'AND', 'EXISTS' ], true ) ) {
                $warnings[] = $this->warning(
                    'unsupported_taxonomy_operator',
                    'A taxonomy clause uses an operator the manifest collector cannot interpret.',
                    [ 'taxonomy' => $taxonomy, 'operator' => $operator ]
                );
            } elseif ( ! in_array( $field, [ 'term_id', 'id', 'slug', 'name' ], true ) ) {
                $warnings[] = $this->warning(
                    'unsupported_taxonomy_field',
                    'A taxonomy clause uses a term field the manifest collector cannot resolve.',
                    [ 'taxonomy' => $taxonomy, 'field' => $field ]
                );
            } elseif ( 'EXISTS' !== $operator && ! empty( $terms ) ) {
                $references[] = [
                    'taxonomy' => $taxonomy,
                    'field'    => $field,
                    'terms'    => $terms,
                ];
            }
        }

        foreach ( $value as $child ) {
            if ( is_array( $child ) ) {
                $this->find_taxonomy_clauses( $child, $references, $warnings );
            }
        }
    }

    /**
     * @param array<mixed> $value
     * @param array<int,array{taxonomy:string,field:string,terms:array<int,string|int>}> $references
     * @param array<int,string> $context
     */
    private function find_elementor_term_tokens( array $value, array &$references, array $context = [] ): void {
        $taxonomy = sanitize_key( (string) ( $value['tax_query_taxonomy'] ?? $value['taxonomy'] ?? '' ) );
        $operator = strtoupper( trim( (string) ( $value['tax_query_operator'] ?? $value['operator'] ?? 'IN' ) ) );
        if ( '' !== $taxonomy && in_array( $operator, [ 'NOT IN', 'NOT_IN', 'NOTIN', 'NOT EXISTS', 'NOT_EXISTS' ], true ) ) {
            return;
        }

        foreach ( $value as $key => $child ) {
            $key_name      = is_string( $key ) ? strtolower( $key ) : '';
            $child_context = '' !== $key_name ? array_merge( $context, [ $key_name ] ) : $context;
            if ( is_array( $child ) ) {
                $this->find_elementor_term_tokens( $child, $references, $child_context );
                continue;
            }
            if ( ! is_scalar( $child ) || ! $this->term_context( $child_context ) || $this->negative_context( $child_context ) ) {
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
     * Elementor Pro Loop Grid and Loop Carousel store plain term IDs in
     * `<prefix>_include_term_ids`, enabled by `<prefix>_include = terms`.
     *
     * @param array<string,mixed> $settings
     * @param array<int,array{taxonomy:string,field:string,terms:array<int,string|int>}> $references
     */
    private function find_elementor_query_include_terms( array $settings, array &$references ): void {
        foreach ( $settings as $key => $value ) {
            if ( is_string( $key ) && preg_match( '/^(.+)_include_term_ids$/', $key, $match ) ) {
                $include = $settings[ $match[1] . '_include' ] ?? null;
                if ( null === $include || in_array( 'terms', (array) $include, true ) ) {
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
            if ( is_array( $value ) ) {
                $this->find_elementor_query_include_terms( $value, $references );
            }
        }
    }

    /** @param array<int,string> $context */
    private function term_context( array $context ): bool {
        foreach ( $context as $key ) {
            if ( str_contains( $key, 'term' ) ) {
                return true;
            }
        }
        return false;
    }

    /** @param array<int,string> $context */
    private function negative_context( array $context ): bool {
        foreach ( $context as $key ) {
            if ( str_contains( $key, 'exclude' ) || str_contains( $key, 'not_in' ) || str_contains( $key, 'notin' ) || str_contains( $key, 'negative' ) ) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $settings */
    private function has_custom_query_hook( array $settings ): bool {
        foreach ( $settings as $key => $value ) {
            if ( is_string( $key ) && in_array( $key, [ 'query_id', 'post_query_query_id', 'posts_query_query_id' ], true ) && is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                return true;
            }
            if ( is_array( $value ) && $this->has_custom_query_hook( $value ) ) {
                return true;
            }
        }
        return false;
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

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function warning( string $code, string $message, array $context = [] ): array {
        return [
            'code'    => $code,
            'message' => $message,
            'context' => $context,
        ];
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
}
