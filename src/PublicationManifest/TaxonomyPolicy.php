<?php

declare( strict_types=1 );

namespace SMP\PublicationIntegration\PublicationManifest;

defined( 'ABSPATH' ) || exit;

final class TaxonomyPolicy {
    /** @return array{status:string,reason:string} */
    public function category_policy( object $term ): array {
        $term_id = isset( $term->term_id ) ? (int) $term->term_id : 0;
        $slug    = isset( $term->slug ) ? strtolower( trim( (string) $term->slug ) ) : '';
        $name    = isset( $term->name ) ? strtolower( trim( (string) $term->name ) ) : '';

        if ( $term_id === (int) get_option( 'default_category', 0 ) || 'uncategorized' === $slug || 'uncategorized' === $name ) {
            return [ 'status' => 'excluded', 'reason' => 'default_category' ];
        }

        if ( 'digital-magazine' === $slug || 'digital magazine' === $name ) {
            return [ 'status' => 'reserved', 'reason' => 'magazine_only' ];
        }

        $policy = [ 'status' => 'eligible', 'reason' => '' ];
        if ( function_exists( 'apply_filters' ) ) {
            $filtered = apply_filters( 'smpi_publication_manifest_category_policy', $policy, $term );
            if ( is_array( $filtered ) ) {
                $status = isset( $filtered['status'] ) ? sanitize_key( (string) $filtered['status'] ) : '';
                if ( in_array( $status, [ 'eligible', 'reserved', 'excluded' ], true ) ) {
                    $policy['status'] = $status;
                    $policy['reason'] = isset( $filtered['reason'] ) ? sanitize_key( (string) $filtered['reason'] ) : '';
                }
            }
        }

        return $policy;
    }

    /** @return array<string,mixed> */
    public function category_record( object $term ): array {
        $link = get_term_link( $term );
        if ( is_wp_error( $link ) ) {
            $link = '';
        }

        return [
            'id'                   => (int) ( $term->term_id ?? 0 ),
            'name'                 => (string) ( $term->name ?? '' ),
            'slug'                 => (string) ( $term->slug ?? '' ),
            'parent_id'            => (int) ( $term->parent ?? 0 ),
            'url'                  => (string) $link,
            'published_post_count' => (int) ( $term->count ?? 0 ),
            'campaign_policy'      => $this->category_policy( $term ),
        ];
    }
}
