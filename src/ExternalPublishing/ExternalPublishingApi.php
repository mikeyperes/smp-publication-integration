<?php

declare( strict_types=1 );

namespace SMP\PublicationIntegration\ExternalPublishing;

use smp_publication_integration\Config;
use smp_publication_integration\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class ExternalPublishingApi {
    private const NAMESPACE = 'smpi/v1';
    private const OPTION_PREFIX = 'smpi_ep_';
    private const OPERATION_TTL = DAY_IN_SECONDS;

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/external-publishing', [
            'methods' => 'GET', 'callback' => [ $this, 'status' ],
            'permission_callback' => [ $this, 'can_use_collection' ],
        ] );
        register_rest_route( self::NAMESPACE, '/external-publishing/posts', [
            'methods' => 'POST', 'callback' => [ $this, 'create_post' ],
            'permission_callback' => [ $this, 'can_use_collection' ],
        ] );
        register_rest_route( self::NAMESPACE, '/external-publishing/posts/(?P<id>\d+)', [
            [ 'methods' => 'GET', 'callback' => [ $this, 'read_post' ], 'permission_callback' => [ $this, 'can_use_post' ] ],
            [ 'methods' => 'POST', 'callback' => [ $this, 'update_post' ], 'permission_callback' => [ $this, 'can_use_post' ] ],
            [ 'methods' => 'DELETE', 'callback' => [ $this, 'delete_post' ], 'permission_callback' => [ $this, 'can_use_post' ] ],
        ] );
    }

    public function can_use_collection(): bool|\WP_Error {
        if ( ! Settings::bool( 'external_publishing_enabled' ) ) {
            return new \WP_Error( 'smpi_external_publishing_disabled', 'External publishing is disabled in SMP Publication Integration.', [ 'status' => 403 ] );
        }
        return current_user_can( 'edit_posts' ) ? true : new \WP_Error( 'smpi_external_publishing_forbidden', 'This WordPress user cannot create posts.', [ 'status' => 403 ] );
    }

    public function can_use_post( \WP_REST_Request $request ): bool|\WP_Error {
        if ( ! Settings::bool( 'external_publishing_enabled' ) ) {
            return new \WP_Error( 'smpi_external_publishing_disabled', 'External publishing is disabled in SMP Publication Integration.', [ 'status' => 403 ] );
        }
        $post_id = absint( $request['id'] );
        $capability = 'DELETE' === strtoupper( $request->get_method() ) ? 'delete_post' : 'edit_post';
        return $post_id > 0 && current_user_can( $capability, $post_id ) ? true : new \WP_Error( 'smpi_external_publishing_forbidden', 'This WordPress user cannot access the requested post.', [ 'status' => 403 ] );
    }

    public function status(): \WP_REST_Response {
        $user = wp_get_current_user();
        return new \WP_REST_Response( [
            'enabled' => true,
            'connector' => 'smp_publication_integration',
            'version' => Config::VERSION,
            'user' => [ 'id' => (int) $user->ID, 'name' => (string) $user->display_name, 'roles' => array_values( (array) $user->roles ) ],
            'capabilities' => [ 'create_posts' => current_user_can( 'edit_posts' ), 'publish_posts' => current_user_can( 'publish_posts' ) ],
        ], 200 );
    }

    public function create_post( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return $this->mutate( $request, 'POST', '/wp/v2/posts', $this->post_payload( $request ) );
    }

    public function read_post( \WP_REST_Request $request ): \WP_REST_Response {
        return $this->proxy( 'GET', '/wp/v2/posts/' . absint( $request['id'] ), [], [ 'context' => 'edit' ] );
    }

    public function update_post( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return $this->mutate( $request, 'POST', '/wp/v2/posts/' . absint( $request['id'] ), $this->post_payload( $request ) );
    }

    public function delete_post( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return $this->mutate( $request, 'DELETE', '/wp/v2/posts/' . absint( $request['id'] ), [ 'force' => rest_sanitize_boolean( $request->get_param( 'force' ) ) ] );
    }

    private function mutate( \WP_REST_Request $request, string $method, string $route, array $payload ): \WP_REST_Response|\WP_Error {
        $operation_id = trim( (string) ( $request->get_header( 'x-hexa-operation-id' ) ?: $request->get_param( 'operation_id' ) ) );
        if ( 1 !== preg_match( '/^[A-Za-z0-9._:-]{16,128}$/', $operation_id ) ) {
            return new \WP_Error( 'smpi_operation_id_required', 'A valid X-Hexa-Operation-ID is required for every mutation.', [ 'status' => 400 ] );
        }
        $key = self::OPTION_PREFIX . hash( 'sha256', $operation_id );
        $fingerprint = hash( 'sha256', wp_json_encode( [ $method, $route, $payload ] ) );
        $existing = get_transient( $key );
        if ( is_array( $existing ) ) {
            return $this->replay_operation( $existing, $fingerprint );
        }
        $lock_key = $key . '_lock';
        $old_lock = get_option( $lock_key, null );
        if ( is_array( $old_lock ) && (int) ( $old_lock['created_at'] ?? 0 ) < time() - 300 ) {
            delete_option( $lock_key );
        }
        if ( ! add_option( $lock_key, [ 'fingerprint' => $fingerprint, 'created_at' => time() ], '', false ) ) {
            $raced = get_transient( $key );
            return is_array( $raced ) ? $this->replay_operation( $raced, $fingerprint ) : new \WP_Error( 'smpi_operation_in_progress', 'The matching operation is still in progress.', [ 'status' => 409 ] );
        }
        try {
            $response = $this->proxy( $method, $route, $payload );
            set_transient( $key, [
                'state' => 'complete', 'fingerprint' => $fingerprint,
                'status' => $response->get_status(), 'data' => $response->get_data(),
            ], self::OPERATION_TTL );
            return $response;
        } finally {
            delete_option( $lock_key );
        }
    }

    private function replay_operation( array $record, string $fingerprint ): \WP_REST_Response|\WP_Error {
        if ( ! hash_equals( (string) ( $record['fingerprint'] ?? '' ), $fingerprint ) ) {
            return new \WP_Error( 'smpi_operation_id_reused', 'The operation ID was already used for a different mutation.', [ 'status' => 409 ] );
        }
        if ( 'complete' !== (string) ( $record['state'] ?? '' ) ) {
            return new \WP_Error( 'smpi_operation_in_progress', 'The matching operation is still in progress.', [ 'status' => 409 ] );
        }
        $data = is_array( $record['data'] ?? null ) ? $record['data'] : [];
        $data['hexa_idempotent_replay'] = true;
        return new \WP_REST_Response( $data, (int) ( $record['status'] ?? 200 ) );
    }

    private function proxy( string $method, string $route, array $body = [], array $query = [] ): \WP_REST_Response {
        $subrequest = new \WP_REST_Request( $method, $route );
        $subrequest->set_body_params( $body );
        $subrequest->set_query_params( $query );
        $response = rest_do_request( $subrequest );
        $data = $response->get_data();
        if ( is_array( $data ) ) {
            $data['hexa_connector'] = 'smp_publication_integration';
            $response->set_data( $data );
        }
        return $response;
    }

    private function post_payload( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) || [] === $params ) {
            $params = $request->get_body_params();
        }
        $payload = [];
        foreach ( [ 'title', 'content', 'excerpt', 'status', 'slug', 'date' ] as $field ) {
            if ( array_key_exists( $field, $params ) && is_scalar( $params[ $field ] ) ) {
                $payload[ $field ] = (string) $params[ $field ];
            }
        }
        foreach ( [ 'author', 'featured_media' ] as $field ) {
            if ( array_key_exists( $field, $params ) ) {
                $payload[ $field ] = absint( $params[ $field ] );
            }
        }
        $array_fields = [ 'categories', 'tags' ];
        foreach ( get_object_taxonomies( 'post', 'objects' ) as $taxonomy ) {
            if ( ! empty( $taxonomy->show_in_rest ) ) {
                $array_fields[] = (string) ( $taxonomy->rest_base ?: $taxonomy->name );
            }
        }
        foreach ( array_unique( $array_fields ) as $field ) {
            if ( array_key_exists( $field, $params ) && is_array( $params[ $field ] ) ) {
                $payload[ $field ] = array_values( array_unique( array_filter( array_map( 'absint', $params[ $field ] ) ) ) );
            }
        }
        return $payload;
    }
}
