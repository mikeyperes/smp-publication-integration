<?php
namespace smp_publication_integration\Authorship;

use Hexa\PluginCore\QuerySafety\QueryEligibility;
use smp_publication_integration\Content\Visibility;
use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class AuthorQueryIntegration {
    private const QUERY_VAR = "smpi_author_user_id";
    private const ELEMENTOR_CURRENT_QUERY_HOOKS = [
        "elementor/query/get_query_args/current_query",
        "elementor_pro/query_control/get_query_args/current_query",
    ];

    private AuthorAssignmentRepository $repository;

    public function __construct( AuthorAssignmentRepository $repository ) {
        $this->repository = $repository;
    }

    public function register(): void {
        add_action( "pre_get_posts", [ $this, "prepare_author_query" ], 20 );
        add_action( "wp", [ $this, "restore_queried_author_object" ], 1 );
        add_filter( "posts_clauses", [ $this, "filter_author_clauses" ], 20, 2 );
        $elementor_priority = PHP_INT_MAX - 5;
        add_filter( "elementor/query/get_query_args/current_query", [ $this, "filter_elementor_query_args" ], $elementor_priority );
        add_filter( "elementor_pro/query_control/get_query_args/current_query", [ $this, "filter_elementor_query_args" ], $elementor_priority );
        add_filter( "elementor/query/query_args", [ $this, "filter_elementor_query_args" ], $elementor_priority );
        add_filter( "elementor/query/fallback_query_args", [ $this, "filter_elementor_query_args" ], $elementor_priority );
    }

    public function prepare_author_query( \WP_Query $query ): void {
        if ( ! QueryEligibility::allows_main_filtered_frontend_query( $query )
            || ! $query->is_author()
            || $query->is_feed()
            || ! Settings::bool( "multi_authors_enabled" )
        ) {
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

        $author = get_user_by( "id", $author_id );
        if ( ! $author instanceof \WP_User ) {
            return;
        }

        // Elementor resolves archive conditions before the later wp action runs.
        $query->queried_object = $author;
        $query->queried_object_id = $author_id;

        $query->set( self::QUERY_VAR, $author_id );
        $query->set( "author", "" );
        $query->set( "author_name", "" );
        $allow_press_releases = Visibility::author_press_releases_enabled();
        $query->set( Visibility::HPR_ALLOW_QUERY_VAR, $allow_press_releases );
        if ( $allow_press_releases ) {
            $query->set( Visibility::HPR_FORCE_HIDE_QUERY_VAR, false );
        }
        $current = $query->get( "post_type" );
        if ( empty( $current ) || "post" === $current ) {
            $query->set( "post_type", $this->author_archive_post_types() );
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
        if ( ! QueryEligibility::allows_main_or_explicit_filtered_frontend_query(
                $query,
                Visibility::MANAGED_CONTEXT_QUERY_VAR,
                [ "author" ]
            )
        ) {
            return $clauses;
        }

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
        if ( RuntimeContext::is_background_request()
            || ! $this->is_elementor_author_archive_query( $query_args )
            || ! empty( $query_args["suppress_filters"] )
            || ! is_author()
            || ! Settings::bool( "multi_authors_enabled" )
        ) {
            return $query_args;
        }
        $author_id = self::current_archive_author_id();
        if ( $author_id <= 0 ) {
            return $query_args;
        }
        $post_types = $this->author_archive_post_types();
        unset( $query_args["author"], $query_args["author_name"], $query_args["author__in"], $query_args["author__not_in"] );
        $query_args["post_type"] = $post_types;
        $query_args["suppress_filters"] = false;
        $query_args[ self::QUERY_VAR ] = $author_id;
        $query_args[ Visibility::HPR_ALLOW_QUERY_VAR ] = Visibility::author_press_releases_enabled();
        $query_args[ Visibility::MANAGED_CONTEXT_QUERY_VAR ] = "author";
        if ( $query_args[ Visibility::HPR_ALLOW_QUERY_VAR ] ) {
            $query_args[ Visibility::HPR_FORCE_HIDE_QUERY_VAR ] = false;
        }
        return $query_args;
    }

    private function is_elementor_author_archive_query( array $query_args ): bool {
        if ( "author" === (string) ( $query_args[ Visibility::MANAGED_CONTEXT_QUERY_VAR ] ?? "" ) ) {
            return true;
        }

        $hook = function_exists( "current_filter" ) ? (string) current_filter() : "";

        return in_array( $hook, self::ELEMENTOR_CURRENT_QUERY_HOOKS, true );
    }

    public function author_archive_post_types(): array {
        $types = $this->repository->supported_post_types();
        if ( ! Visibility::author_press_releases_enabled() ) {
            $types = array_values( array_diff( $types, [ "press-release" ] ) );
        } elseif ( ! in_array( "press-release", $types, true ) ) {
            $types[] = "press-release";
        }
        return ! empty( $types ) ? array_values( array_unique( $types ) ) : [ "post" ];
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
