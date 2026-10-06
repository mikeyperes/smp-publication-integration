<?php
namespace smp_publication_integration\Authorship;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Keeps author assignments consistent outside the editor: REST, user
 * deletion, and the one-time move from the pre-2.2.5 data model.
 */
final class AuthorLifecycle {
    private const MIGRATION_OPTION = "smpi_multi_author_migration_2_2_5";

    private AuthorAssignmentRepository $repository;

    public function __construct( AuthorAssignmentRepository $repository ) {
        $this->repository = $repository;
    }

    public function register(): void {
        add_action( "rest_api_init", [ $this, "register_rest_fields" ] );
        add_action( "delete_user", [ $this, "delete_user" ], 10, 1 );
        add_action( "admin_init", [ $this, "maybe_migrate" ], 30 );
    }

    public function register_rest_fields(): void {
        foreach ( AuthorAssignmentRepository::supported_post_types() as $post_type ) {
            if ( ! post_type_exists( $post_type ) ) {
                continue;
            }
            register_rest_field(
                $post_type,
                AuthorAssignmentRepository::REST_FIELD,
                [
                    "get_callback" => [ $this, "rest_get_authors" ],
                    "update_callback" => [ $this, "rest_update_authors" ],
                    "schema" => [
                        "description" => "Ordered WordPress user IDs: the native author first, then active co-authors. Writing two or more IDs turns the post's Multiple authors switch on; writing one turns it off.",
                        "type" => "array",
                        "items" => [ "type" => "integer" ],
                        "context" => [ "view", "edit" ],
                    ],
                ]
            );
        }
    }

    public function rest_get_authors( array $object ): array {
        return $this->repository->ids_for_post( (int) ( $object["id"] ?? 0 ) );
    }

    public function rest_update_authors( $value, \WP_Post $post ) {
        if ( ! current_user_can( "edit_post", $post->ID ) ) {
            return new \WP_Error( "smpi_multi_authors_forbidden", "You cannot edit authors for this post.", [ "status" => 403 ] );
        }
        if ( count( (array) $value ) > 1 && ! AuthorAssignmentRepository::site_enabled() ) {
            return new \WP_Error( "smpi_multi_authors_disabled", "Multiple authors is turned off for this site.", [ "status" => 400 ] );
        }
        $this->repository->assign( (int) $post->ID, $value );
        return true;
    }

    public function delete_user( int $user_id ): void {
        $this->repository->remove_user( $user_id );
    }

    /** Batched, admin-only conversion of legacy data; stops once complete. */
    public function maybe_migrate(): void {
        if ( ! current_user_can( "manage_options" ) || get_option( self::MIGRATION_OPTION ) ) {
            return;
        }
        $result = $this->repository->migrate_batch( 100 );
        if ( ! empty( $result["complete"] ) && 0 === $this->repository->prune_stale_index( 200 ) ) {
            update_option( self::MIGRATION_OPTION, "2.2.5", false );
        }
    }
}
