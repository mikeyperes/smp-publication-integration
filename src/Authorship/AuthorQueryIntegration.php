<?php
namespace smp_publication_integration\Authorship;

use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class AuthorQueryIntegration {
    private const QUERY_VAR = "smpi_author_user_id";

    private AuthorAssignmentRepository $repository;

    public function __construct( AuthorAssignmentRepository $repository ) {
        $this->repository = $repository;
    }

    public function register(): void {
        add_action( "pre_get_posts", [ $this, "prepare_author_query" ], 20 );
        add_filter( "posts_clauses", [ $this, "filter_author_clauses" ], 20, 2 );
        add_filter( "elementor/query/get_query_args/current_query", [ $this, "filter_elementor_query_args" ], 20 );
        add_filter( "elementor/query/query_args", [ $this, "filter_elementor_query_args" ], 20 );
    }

    public function prepare_author_query( \WP_Query $query ): void {
        if ( is_admin() || ! Settings::bool( "multi_authors_enabled" ) || ! $query->is_author() ) {
            return;
        }
        $author_id = absint( $query->get( "author" ) );
        if ( $author_id <= 0 ) {
            $slug = sanitize_title( (string) $query->get( "author_name" ) );
            $user = "" !== $slug ? get_user_by( "slug", $slug ) : null;
            $author_id = $user instanceof \WP_User ? (int) $user->ID : 0;
        }
        if ( $author_id <= 0 ) {
            return;
        }

        $query->set( self::QUERY_VAR, $author_id );
        $query->set( "author", "" );
        $query->set( "author_name", "" );
        $current = $query->get( "post_type" );
        if ( empty( $current ) || "post" === $current ) {
            $query->set( "post_type", $this->repository->supported_post_types() );
        }
    }

    public function filter_author_clauses( array $clauses, \WP_Query $query ): array {
        $author_id = absint( $query->get( self::QUERY_VAR ) );
        if ( $author_id <= 0 ) {
            return $clauses;
        }

        global $wpdb;
        $clauses["where"] .= $wpdb->prepare(
            " AND ( {$wpdb->posts}.post_author = %d OR EXISTS (
                SELECT 1
                FROM {$wpdb->term_relationships} smpi_tr
                INNER JOIN {$wpdb->term_taxonomy} smpi_tt
                    ON smpi_tt.term_taxonomy_id = smpi_tr.term_taxonomy_id
                    AND smpi_tt.taxonomy = %s
                INNER JOIN {$wpdb->termmeta} smpi_tm
                    ON smpi_tm.term_id = smpi_tt.term_id
                    AND smpi_tm.meta_key = %s
                    AND CAST(smpi_tm.meta_value AS UNSIGNED) = %d
                WHERE smpi_tr.object_id = {$wpdb->posts}.ID
            ) )",
            $author_id,
            AuthorAssignmentRepository::TAXONOMY,
            AuthorAssignmentRepository::USER_ID_META_KEY,
            $author_id
        );
        return $clauses;
    }

    public function filter_elementor_query_args( array $query_args ): array {
        if ( is_admin() || ! Settings::bool( "multi_authors_enabled" ) || ! is_author() ) {
            return $query_args;
        }
        $author_id = (int) get_queried_object_id();
        if ( $author_id <= 0 ) {
            return $query_args;
        }
        $ids = $this->repository->post_ids_for_user( $author_id );
        unset( $query_args["author"], $query_args["author_name"], $query_args["author__in"], $query_args["author__not_in"] );
        $query_args["post__in"] = ! empty( $ids ) ? $ids : [ 0 ];
        $query_args["post_type"] = $this->repository->supported_post_types();
        return $query_args;
    }
}
