<?php

declare( strict_types=1 );

namespace SMP\PublicationIntegration\PublicationManifest;

use smp_publication_integration\Config;

defined( 'ABSPATH' ) || exit;

final class ManifestEndpoint {
    private ManifestBuilder $builder;

    public function __construct( ?ManifestBuilder $builder = null ) {
        $this->builder = $builder ?? new ManifestBuilder();
    }

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_route' ] );

        foreach ( [ 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post', 'profile_update', 'user_register', 'deleted_user', 'switch_theme', 'customize_save_after', 'activated_plugin', 'deactivated_plugin' ] as $hook ) {
            add_action( $hook, [ $this, 'invalidate' ], 10, 4 );
        }

        foreach ( [ 'created_term', 'edited_term', 'delete_term' ] as $hook ) {
            add_action( $hook, [ $this, 'invalidate_term' ], 10, 4 );
        }

        foreach ( [
            'update_option_blogname',
            'update_option_blogdescription',
            'update_option_blog_public',
            'update_option_default_category',
            'update_option_page_on_front',
            'update_option_permalink_structure',
            'update_option_show_on_front',
            'update_option_site_icon',
            'update_option_smpi_settings',
            'update_option_theme_mods_' . get_option( 'stylesheet', '' ),
        ] as $hook ) {
            add_action( $hook, [ $this, 'invalidate' ], 10, 4 );
        }

        add_action( 'elementor/document/after_save', [ $this, 'invalidate' ], 10, 2 );
    }

    public function register_route(): void {
        register_rest_route(
            'smpi/v1',
            '/publication-manifest',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'render' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function render( \WP_REST_Request $request ): \WP_REST_Response {
        $manifest = get_transient( self::cache_key() );
        if ( ! is_array( $manifest ) || empty( $manifest['meta']['fingerprint'] ) ) {
            $manifest = $this->builder->build();
            set_transient( self::cache_key(), $manifest, self::cache_ttl() );
        }

        $etag    = '"' . (string) $manifest['meta']['fingerprint'] . '"';
        $headers = [
            'ETag'          => $etag,
            'Cache-Control' => 'public, max-age=' . self::cache_ttl() . ', stale-while-revalidate=' . self::cache_ttl(),
            'Vary'          => 'Accept-Encoding',
        ];

        $requested_etag = trim( (string) $request->get_header( 'if-none-match' ) );
        if ( '' !== $requested_etag && hash_equals( $etag, $requested_etag ) ) {
            return new \WP_REST_Response( null, 304, $headers );
        }

        return new \WP_REST_Response( $manifest, 200, $headers );
    }

    public function invalidate( ...$unused ): void {
        delete_transient( self::cache_key() );
    }

    public function invalidate_term( int $term_id = 0, int $term_taxonomy_id = 0, string $taxonomy = '', ...$unused ): void {
        unset( $term_id, $term_taxonomy_id, $unused );
        if ( in_array( $taxonomy, [ 'category', 'post_tag' ], true ) ) {
            $this->invalidate();
        }
    }

    public static function cache_ttl(): int {
        $ttl = 900;
        if ( function_exists( 'apply_filters' ) ) {
            $ttl = (int) apply_filters( 'smpi_publication_manifest_cache_ttl', $ttl );
        }
        return max( 300, min( 3600, $ttl ) );
    }

    private static function cache_key(): string {
        return 'smpi_publication_manifest_v1_' . str_replace( '.', '_', Config::VERSION );
    }
}
