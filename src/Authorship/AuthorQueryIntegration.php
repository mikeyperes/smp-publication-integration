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
        add_action( "wp", [ $this, "restore_queried_author_object" ], 1 );
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

    public function restore_queried_author_object(): void {
        if ( is_admin() || ! is_author() ) {
            return;
        }

        global $wp_query;
        if ( ! $wp_query instanceof \WP_Query ) {
            return;
        }

        $author_id = absint( $wp_query->get( self::QUERY_VAR ) );
        if ( $author_id <= 0 ) {
            return;
        }

        $author = get_user_by( "id", $author_id );
        if ( ! $author instanceof \WP_User ) {
            return;
        }

        $wp_query->queried_object = $author;
        $wp_query->queried_object_id = $author_id;
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
        $author_id = self::current_archive_author_id();
        if ( $author_id <= 0 ) {
            return $query_args;
        }
        $ids = $this->repository->post_ids_for_user( $author_id );
        unset( $query_args["author"], $query_args["author_name"], $query_args["author__in"], $query_args["author__not_in"] );
        $query_args["post__in"] = ! empty( $ids ) ? $ids : [ 0 ];
        $query_args["post_type"] = $this->repository->supported_post_types();
        return $query_args;
    }

    public static function current_archive_author_id(): int {
        if ( ! function_exists( "is_author" ) || ! is_author() ) {
            return 0;
        }

        global $wp_query;
        if ( $wp_query instanceof \WP_Query ) {
            $stored = absint( $wp_query->get( self::QUERY_VAR ) );
            if ( $stored > 0 && get_user_by( "id", $stored ) instanceof \WP_User ) {
                return $stored;
            }
        }

        $queried = function_exists( "get_queried_object" ) ? get_queried_object() : null;
        if ( $queried instanceof \WP_User ) {
            return (int) $queried->ID;
        }

        $queried_id = function_exists( "get_queried_object_id" ) ? absint( get_queried_object_id() ) : 0;
        if ( $queried_id > 0 && get_user_by( "id", $queried_id ) instanceof \WP_User ) {
            return $queried_id;
        }

        if ( $wp_query instanceof \WP_Query ) {
            $native_id = absint( $wp_query->get( "author" ) );
            if ( $native_id > 0 && get_user_by( "id", $native_id ) instanceof \WP_User ) {
                return $native_id;
            }

            $slug = sanitize_title( (string) $wp_query->get( "author_name" ) );
            $author = "" !== $slug ? get_user_by( "slug", $slug ) : null;
            if ( $author instanceof \WP_User ) {
                return (int) $author->ID;
            }
        }

        return 0;
    }

    public static function current_archive_author(): ?\WP_User {
        $author_id = self::current_archive_author_id();
        $author = $author_id > 0 ? get_user_by( "id", $author_id ) : null;
        return $author instanceof \WP_User ? $author : null;
    }
}
