<?php
namespace smp_publication_integration\Authorship;

use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class AuthorLifecycle {
    private const MIGRATION_OPTION = "smpi_multi_author_migration_1";

    private AuthorAssignmentRepository $repository;
    private bool $syncing_meta = false;

    public function __construct( AuthorAssignmentRepository $repository ) {
        $this->repository = $repository;
    }

    public function register(): void {
        add_action( "init", [ $this->repository, "register_taxonomy" ], 9 );
        add_filter( "acf/update_value/name=" . AuthorAssignmentRepository::LEGACY_META_KEY, [ $this, "sync_acf_value" ], 20, 3 );
        add_action( "updated_post_meta", [ $this, "sync_meta_change" ], 20, 4 );
        add_action( "added_post_meta", [ $this, "sync_meta_change" ], 20, 4 );
        add_action( "deleted_post_meta", [ $this, "sync_deleted_meta" ], 20, 4 );
        add_action( "rest_api_init", [ $this, "register_rest_fields" ] );
        add_action( "delete_user", [ $this, "delete_user" ], 10, 3 );
        add_action( "set_object_terms", [ $this, "clear_term_cache" ], 10, 6 );
        add_action( "admin_init", [ $this, "maybe_migrate_batch" ], 30 );
    }

    public function sync_acf_value( $value, $post_id, array $field ) {
        $post_id = is_numeric( $post_id ) ? (int) $post_id : 0;
        if ( $post_id > 0 && Settings::bool( "multi_authors_enabled" ) ) {
            $this->repository->set_ids( $post_id, $value, true );
        }
        return $value;
    }

    public function sync_meta_change( int $meta_id, int $post_id, string $meta_key, $meta_value ): void {
        if ( $this->syncing_meta || AuthorAssignmentRepository::LEGACY_META_KEY !== $meta_key || ! Settings::bool( "multi_authors_enabled" ) ) {
            return;
        }
        $this->syncing_meta = true;
        try {
            $this->repository->set_ids( $post_id, $meta_value, true );
        } finally {
            $this->syncing_meta = false;
        }
    }

    public function sync_deleted_meta( $meta_ids, int $post_id, string $meta_key, $meta_value ): void {
        if ( AuthorAssignmentRepository::LEGACY_META_KEY === $meta_key ) {
            $this->repository->clear( $post_id );
        }
    }

    public function register_rest_fields(): void {
        foreach ( $this->repository->supported_post_types() as $post_type ) {
            if ( ! post_type_exists( $post_type ) ) {
                continue;
            }
            register_rest_field(
                $post_type,
                AuthorAssignmentRepository::LEGACY_META_KEY,
                [
                    "get_callback" => [ $this, "rest_get_authors" ],
                    "update_callback" => [ $this, "rest_update_authors" ],
                    "schema" => [
                        "description" => "Ordered WordPress user IDs assigned as post authors.",
                        "type" => "array",
                        "items" => [ "type" => "integer" ],
                        "context" => [ "view", "edit" ],
                    ],
                ]
            );
        }
    }

    public function rest_get_authors( array $object ): array {
        return $this->repository->ids_for_post( (int) ( $object["id"] ?? 0 ), true );
    }

    public function rest_update_authors( $value, \WP_Post $post ) {
        if ( ! current_user_can( "edit_post", $post->ID ) ) {
            return new \WP_Error( "smpi_multi_authors_forbidden", "You cannot edit authors for this post.", [ "status" => 403 ] );
        }
        $ids = $this->repository->set_ids( (int) $post->ID, $value, true );
        update_post_meta( (int) $post->ID, AuthorAssignmentRepository::LEGACY_META_KEY, $ids );
        return true;
    }

    public function delete_user( int $user_id, $reassign_id = null, $user = null ): void {
        $this->repository->remove_user( $user_id, (int) $reassign_id );
    }

    public function clear_term_cache( int $object_id, array $terms, array $term_taxonomy_ids, string $taxonomy, bool $append, array $old_term_taxonomy_ids ): void {
        if ( AuthorAssignmentRepository::TAXONOMY === $taxonomy ) {
            $this->repository->clear_cache( $object_id );
        }
    }

    public function maybe_migrate_batch(): void {
        if ( ! Settings::bool( "multi_authors_enabled" ) || ! current_user_can( "manage_options" ) ) {
            return;
        }
        $state = get_option( self::MIGRATION_OPTION, [] );
        if ( is_array( $state ) && ! empty( $state["complete"] ) ) {
            return;
        }
        $offset = is_array( $state ) ? absint( $state["next_offset"] ?? 0 ) : 0;
        update_option( self::MIGRATION_OPTION, $this->repository->migrate_batch( 100, $offset ), false );
    }
}
