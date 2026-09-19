<?php

declare( strict_types=1 );

namespace SMP\PublicationIntegration\PublicationManifest;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the published posts returned by supported homepage widgets through
 * the widget providers' public query APIs. It never renders widget markup.
 */
final class NativeWidgetQueryCollector {
    private const DEFAULT_MAX_QUERIES = 24;
    private const DEFAULT_MAX_POSTS = 12;

    /** @var callable|null */
    private $fixture_resolver;
    private int $max_queries;
    private int $max_posts;
    private int $queries_used = 0;
    /** @var array<int,int> */
    private array $previous_post_ids = [];
    private bool $previous_results_complete = true;

    public function __construct( ?callable $fixture_resolver = null, int $max_queries = self::DEFAULT_MAX_QUERIES, int $max_posts = self::DEFAULT_MAX_POSTS ) {
        $this->fixture_resolver = $fixture_resolver;
        $this->max_queries      = max( 1, min( 50, $max_queries ) );
        $this->max_posts        = max( 1, min( 25, $max_posts ) );
    }

    public function begin_collection(): void {
        $this->queries_used              = 0;
        $this->previous_post_ids         = [];
        $this->previous_results_complete = true;
    }

    public function invalidate_previous_results(): void {
        $this->previous_results_complete = false;
    }

    /**
     * @param array<string,mixed> $node
     * @return array{resolved:bool,provider:string,category_ids:array<int,int>,post_count:int,result_limit:int,warning:?array<string,mixed>}
     */
    public function collect( array $node, int $document_id ): array {
        $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
        $affects_elementor_history = ! str_starts_with( $widget_type, 'jet-' );
        if ( $this->queries_used >= $this->max_queries ) {
            return $this->finalize_result( $this->failure(
                'native_query_budget_exhausted',
                'The bounded native widget-query budget was exhausted before this query could be inspected.',
                [ 'maximum_queries' => $this->max_queries ]
            ), $affects_elementor_history );
        }
        ++$this->queries_used;

        $settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
        if ( $affects_elementor_history && $this->requires_previous_results( $settings ) && ! $this->previous_results_complete ) {
            return $this->finalize_result( $this->failure(
                'elementor_previous_results_required',
                'This query depends on earlier widget results that could not be reproduced completely by the bounded native collector.'
            ), true );
        }

        if ( is_callable( $this->fixture_resolver ) ) {
            $result = ( $this->fixture_resolver )( $node, $document_id, $this->max_posts, array_values( $this->previous_post_ids ) );
            return $this->finalize_result( $this->normalize_result( is_array( $result ) ? $result : [] ), $affects_elementor_history );
        }

        if ( in_array( $widget_type, [ 'posts', 'loop-grid', 'loop-carousel' ], true ) ) {
            return $this->finalize_result( $this->collect_elementor_posts( $node, $document_id ), true );
        }
        if ( 'jet-listing-grid' === $widget_type ) {
            return $this->finalize_result( $this->collect_jet_listing( $node, $document_id ), false );
        }

        return $this->finalize_result( $this->failure(
            'native_query_provider_unsupported',
            'The query widget has no supported native post-query adapter.',
            [ 'widget_type' => $widget_type ]
        ), $affects_elementor_history );
    }

    /** @param array<string,mixed> $node */
    private function collect_elementor_posts( array $node, int $document_id ): array {
        if ( $document_id < 1 || ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
            return $this->failure(
                'elementor_native_query_unavailable',
                'Elementor is not initialized with a resolvable homepage document for native query inspection.'
            );
        }

        $settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
        $truncated = $this->requires_truncation( $settings );
        if ( $this->uses_current_query( $settings ) ) {
            return $this->failure(
                'elementor_current_query_context_unsupported',
                'This widget depends on a request-specific current query that cannot be reproduced by the public manifest endpoint.'
            );
        }

        $plugin = \Elementor\Plugin::$instance;
        if ( ! isset( $plugin->documents, $plugin->elements_manager, $plugin->db ) ) {
            return $this->failure(
                'elementor_native_query_unavailable',
                'Elementor native document or element services are unavailable.'
            );
        }

        $document = $plugin->documents->get( $document_id );
        if ( ! is_object( $document ) ) {
            return $this->failure(
                'elementor_document_unavailable',
                'Elementor could not resolve the saved homepage document.',
                [ 'document_id' => $document_id ]
            );
        }

        $bounded_node             = $node;
        $bounded_node['settings'] = $this->bounded_settings( $settings );
        $switched_document        = false;
        $switched_post            = false;
        $query_bounds             = (object) [ 'truncated' => $truncated, 'matched' => false ];
        $query_guard              = $this->query_guard( $query_bounds );
        $query_marker             = null;
        $displayed_ids_snapshot   = [];
        $displayed_ids_bound      = false;
        $displayed_ids_class      = '\\ElementorPro\\Modules\\QueryControl\\Module';

        if ( $this->requires_previous_results( $settings ) && ( ! class_exists( $displayed_ids_class ) || ! property_exists( $displayed_ids_class, 'displayed_ids' ) ) ) {
            return $this->failure(
                'elementor_previous_results_required',
                'Elementor previous-result state is unavailable, so this duplicate-avoiding query cannot be reproduced safely.'
            );
        }

        try {
            $plugin->documents->switch_to_document( $document );
            $switched_document = true;
            $plugin->db->switch_to_post( $document_id );
            $switched_post = true;

            $widget = $plugin->elements_manager->create_element_instance( $bounded_node );
            if ( ! is_object( $widget ) || ! method_exists( $widget, 'query_posts' ) || ! method_exists( $widget, 'get_query' ) ) {
                return $this->failure(
                    'elementor_widget_query_api_unavailable',
                    'The saved Elementor widget does not expose the supported native post-query API.',
                    [ 'widget_type' => sanitize_key( (string) ( $node['widgetType'] ?? '' ) ) ]
                );
            }

            if ( class_exists( $displayed_ids_class ) && property_exists( $displayed_ids_class, 'displayed_ids' ) ) {
                $displayed_ids_snapshot       = $displayed_ids_class::$displayed_ids;
                $displayed_ids_bound          = true;
                $displayed_ids_class::$displayed_ids = array_values( $this->previous_post_ids );
            }

            $query_marker = $this->query_marker( $widget, $query_bounds );
            add_filter( 'elementor/query/query_args', $query_marker, PHP_INT_MAX, 2 );
            add_action( 'pre_get_posts', $query_guard, PHP_INT_MAX );
            $widget->query_posts();
            $query = $widget->get_query();
            if ( ! $query instanceof \WP_Query || ! $query_bounds->matched ) {
                return $this->failure(
                    'elementor_native_query_result_unavailable',
                    'Elementor did not return a WordPress post query for the saved widget.'
                );
            }

            return $this->result_from_posts( (array) $query->posts, 'elementor_pro', $query_bounds->truncated );
        } catch ( \Throwable $throwable ) {
            return $this->failure(
                'elementor_native_query_failed',
                'Elementor could not execute the saved widget query through its native API.',
                [ 'exception' => sanitize_key( get_class( $throwable ) ) ]
            );
        } finally {
            remove_action( 'pre_get_posts', $query_guard, PHP_INT_MAX );
            if ( null !== $query_marker ) {
                remove_filter( 'elementor/query/query_args', $query_marker, PHP_INT_MAX );
            }
            if ( $displayed_ids_bound && class_exists( $displayed_ids_class ) ) {
                $displayed_ids_class::$displayed_ids = $displayed_ids_snapshot;
            }
            if ( $switched_post ) {
                $plugin->db->restore_current_post();
            }
            if ( $switched_document ) {
                $plugin->documents->restore_document();
            }
        }
    }

    /** @param array<string,mixed> $node */
    private function collect_jet_listing( array $node, int $document_id ): array {
        if ( ! function_exists( 'jet_engine' ) ) {
            return $this->failure(
                'jet_engine_native_query_unavailable',
                'JetEngine is not initialized for native listing-query inspection.'
            );
        }

        $engine = jet_engine();
        if ( ! is_object( $engine ) || ! isset( $engine->listings ) || ! method_exists( $engine->listings, 'get_render_instance' ) ) {
            return $this->failure(
                'jet_engine_native_query_unavailable',
                'JetEngine listing services are unavailable.'
            );
        }

        $raw_settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
        $truncated    = $this->requires_truncation( $raw_settings );
        $settings     = $this->bounded_settings( $raw_settings );
        $query_bounds = (object) [ 'truncated' => $truncated, 'matched' => false ];
        $query_guard = $this->query_guard( $query_bounds );
        $query_marker = null;
        $data_snapshot = $engine->listings->data ?? null;
        $post_existed = array_key_exists( 'post', $GLOBALS );
        $post_snapshot = $GLOBALS['post'] ?? null;

        if ( ! is_object( $data_snapshot ) || ! method_exists( $data_snapshot, 'set_listing_by_id' ) || ! method_exists( $data_snapshot, 'get_listing_source' ) ) {
            return $this->failure( 'jet_engine_listing_context_unavailable', 'JetEngine cannot bind the exact saved listing for this query.' );
        }
        if ( ! empty( $settings['is_archive_template'] ) || ! empty( $settings['use_random_posts_num'] ) ) {
            return $this->failure( 'jet_engine_query_context_unsupported', 'This listing requires archive or random query state that the bounded native post adapter cannot reproduce.' );
        }
        $listing_id = absint( $settings['lisitng_id'] ?? 0 );
        if ( $listing_id < 1 || ! get_post( $listing_id ) ) {
            return $this->failure( 'jet_engine_listing_missing', 'The widget has no existing saved listing document.' );
        }

        try {
            // Isolate all listing state, including main/current listing and object.
            // reset_listing() alone restores neither the original main listing nor object.
            $engine->listings->data = clone $data_snapshot;
            $engine->listings->data->set_listing_by_id( $listing_id );
            $GLOBALS['post'] = get_post( $document_id );
            $renderer = $engine->listings->get_render_instance( 'listing-grid', $settings );
            if ( ! is_object( $renderer ) || ! method_exists( $renderer, 'setup_listing_props' ) || ! method_exists( $renderer, 'get_query' ) || ! method_exists( $renderer, 'get_settings' ) ) {
                return $this->failure(
                    'jet_engine_widget_query_api_unavailable',
                    'JetEngine does not expose the supported listing-grid query API.'
                );
            }

            $renderer->setup_listing_props();
            $source = apply_filters( 'jet-engine/listing/grid/source', $engine->listings->data->get_listing_source(), $renderer->get_settings(), $renderer );
            if ( 'query' === $source ) {
                return $this->collect_jet_query_builder( $renderer, $listing_id );
            }
            if ( 'posts' !== $source ) {
                return $this->failure( 'jet_engine_listing_source_unsupported', 'Only native WordPress post listings and safely resolved post-returning Query Builder listings provide category evidence; other listing sources were not executed.' );
            }
            $query_marker = $this->query_marker( $renderer, $query_bounds );
            add_filter( 'jet-engine/listing/grid/posts-query-args', $query_marker, PHP_INT_MAX, 2 );
            add_action( 'pre_get_posts', $query_guard, PHP_INT_MAX );
            $posts = $renderer->get_query( $renderer->get_settings() );
            if ( ! is_array( $posts ) || ! $query_bounds->matched ) {
                return $this->failure(
                    'jet_engine_native_query_result_unsupported',
                    'The JetEngine listing source did not return a supported post result set.'
                );
            }

            return $this->result_from_posts( $posts, 'jet_engine', $query_bounds->truncated );
        } catch ( \Throwable $throwable ) {
            return $this->failure(
                'jet_engine_native_query_failed',
                'JetEngine could not execute the saved listing query through its native API.',
                [ 'exception' => sanitize_key( get_class( $throwable ) ) ]
            );
        } finally {
            remove_action( 'pre_get_posts', $query_guard, PHP_INT_MAX );
            if ( null !== $query_marker ) {
                remove_filter( 'jet-engine/listing/grid/posts-query-args', $query_marker, PHP_INT_MAX );
            }
            $engine->listings->data = $data_snapshot;
            if ( $post_existed ) {
                $GLOBALS['post'] = $post_snapshot;
            } else {
                unset( $GLOBALS['post'] );
            }
        }
    }

    /** Resolve one statically bound JetEngine Query Builder posts query. */
    private function collect_jet_query_builder( object $renderer, int $listing_id ): array {
        $manager_class = '\\Jet_Engine\\Query_Builder\\Manager';
        if ( ! class_exists( $manager_class ) || ! method_exists( $manager_class, 'instance' ) ) {
            return $this->failure(
                'jet_engine_query_builder_unavailable',
                'JetEngine Query Builder is unavailable for native listing-query inspection.'
            );
        }

        $settings = $renderer->get_settings();
        $query_id = absint( $settings['custom_query_id'] ?? 0 );
        if ( $query_id < 1 ) {
            return $this->failure(
                'jet_engine_query_builder_id_missing',
                'The saved Query Builder listing has no exact query ID.'
            );
        }

        $manager = $manager_class::instance();
        if (
            ! is_object( $manager )
            || ! isset( $manager->listings )
            || ! method_exists( $manager->listings, 'get_query_id' )
            || ! method_exists( $manager, 'get_query_by_id_for_context' )
        ) {
            return $this->failure(
                'jet_engine_query_builder_api_unavailable',
                'JetEngine does not expose the supported context-bound Query Builder API.'
            );
        }

        $resolved_query_id = absint( $manager->listings->get_query_id( $listing_id, $settings ) );
        if ( $query_id !== $resolved_query_id ) {
            return $this->failure(
                'jet_engine_query_builder_binding_mismatch',
                'The listing did not resolve to its exact saved Query Builder query.'
            );
        }

        $query = $manager->get_query_by_id_for_context(
            $resolved_query_id,
            [
                'type'       => 'listing',
                'query_id'   => $resolved_query_id,
                'listing_id' => $listing_id,
                'settings'   => $settings,
            ]
        );
        if ( ! is_object( $query ) ) {
            return $this->failure(
                'jet_engine_query_builder_query_missing',
                'JetEngine could not resolve the exact saved Query Builder query.'
            );
        }

        $query_type = method_exists( $query, 'get_query_type' ) ? sanitize_key( (string) $query->get_query_type() ) : '';
        if ( 'posts' !== $query_type ) {
            return $this->failure(
                'jet_engine_query_builder_type_unsupported',
                'Only Query Builder queries that explicitly return WordPress posts provide category evidence.',
                [ 'query_type' => $query_type ]
            );
        }
        if ( $this->has_runtime_query_context( $query->dynamic_query ?? [] ) ) {
            return $this->failure(
                'jet_engine_query_builder_dynamic_context_unsupported',
                'This Query Builder query contains runtime-dependent values that the public manifest cannot reproduce safely.'
            );
        }
        if ( ! method_exists( $query, 'get_items' ) ) {
            return $this->failure(
                'jet_engine_query_builder_api_unavailable',
                'The saved Query Builder posts query does not expose its public item API.'
            );
        }

        $bounded_query = clone $query;
        if ( property_exists( $bounded_query, 'cache_query' ) ) {
            $bounded_query->cache_query = false;
        }
        if ( property_exists( $bounded_query, 'final_query' ) ) {
            $bounded_query->final_query = null;
        }
        if ( property_exists( $bounded_query, 'final_query_raw' ) ) {
            $bounded_query->final_query_raw = null;
        }
        if ( method_exists( $bounded_query, 'reset_query' ) ) {
            $bounded_query->reset_query();
        }

        $query_bounds = (object) [ 'truncated' => false, 'matched' => false ];
        $query_marker = $this->query_marker( $bounded_query, $query_bounds );
        $query_guard  = $this->query_guard( $query_bounds );

        try {
            add_filter( 'jet-engine/query-builder/types/posts-query/args', $query_marker, PHP_INT_MAX, 2 );
            add_action( 'pre_get_posts', $query_guard, PHP_INT_MAX );
            $posts = $bounded_query->get_items();
            if ( ! is_array( $posts ) || ! $query_bounds->matched ) {
                return $this->failure(
                    'jet_engine_query_builder_result_unsupported',
                    'The saved Query Builder posts query did not return a bounded WordPress post result set.'
                );
            }

            return $this->result_from_posts( $posts, 'jet_engine_query_builder', $query_bounds->truncated );
        } catch ( \Throwable $throwable ) {
            return $this->failure(
                'jet_engine_query_builder_failed',
                'JetEngine could not execute the saved Query Builder posts query through its public API.',
                [ 'exception' => sanitize_key( get_class( $throwable ) ) ]
            );
        } finally {
            remove_action( 'pre_get_posts', $query_guard, PHP_INT_MAX );
            remove_filter( 'jet-engine/query-builder/types/posts-query/args', $query_marker, PHP_INT_MAX );
        }
    }

    /** Runtime/dynamic values are request-specific even when their containers are sparse. */
    private function has_runtime_query_context( $value ): bool {
        if ( is_array( $value ) ) {
            foreach ( $value as $nested ) {
                if ( $this->has_runtime_query_context( $nested ) ) {
                    return true;
                }
            }
            return false;
        }
        if ( is_object( $value ) ) {
            return true;
        }
        return null !== $value && false !== $value && '' !== trim( (string) $value );
    }

    /** @param array<string,mixed> $settings @return array<string,mixed> */
    private function bounded_settings( array $settings ): array {
        foreach ( $settings as $key => $value ) {
            if ( is_string( $key ) && ( 'posts_num' === $key || 'max_posts_num' === $key || 'posts_per_page' === $key || str_ends_with( $key, '_posts_per_page' ) ) ) {
                $requested = (int) $value;
                $settings[ $key ] = $requested < 1 ? $this->max_posts : min( $this->max_posts, $requested );
            }
        }
        $settings['current_page'] = 1;
        $settings['post_status']  = [ 'publish' ];
        return $settings;
    }

    /** Bind only the provider's exact widget query; leave template/helper lookups unchanged. */
    private function query_marker( object $widget, object $bounds ): callable {
        return static function ( array $arguments, $query_widget ) use ( $widget, $bounds ): array {
            if ( $query_widget === $widget ) {
                $arguments['_smpi_manifest_scope'] = spl_object_id( $bounds );
            }
            return $arguments;
        };
    }

    /** @return callable */
    private function query_guard( object $bounds ): callable {
        $max_posts = $this->max_posts;
        return static function ( $query ) use ( $max_posts, $bounds ): void {
            if ( ! is_object( $query ) || ! method_exists( $query, 'set' ) || ! method_exists( $query, 'get' ) ) {
                return;
            }
            if ( $query->get( '_smpi_manifest_scope' ) !== spl_object_id( $bounds ) ) {
                return;
            }
            $bounds->matched = true;
            // Clamp only downwards: a three-card widget must never contribute
            // categories from twelve posts. Inspect the effective hook-adjusted limit.
            $requested = (int) $query->get( 'posts_per_page' );
            if ( $requested < 1 ) {
                $requested = (int) get_option( 'posts_per_page', 10 );
            }
            if ( $query->get( 'nopaging' ) || -1 === (int) $query->get( 'posts_per_page' ) || $requested > $max_posts ) {
                $bounds->truncated = true;
            }
            $query->set( 'nopaging', false );
            $query->set( 'posts_per_page', max( 1, min( $max_posts, $requested ) ) );
            $query->set( 'post_status', 'publish' );
            $query->set( 'paged', 1 );
            $query->set( 'no_found_rows', true );
        };
    }

    /** @param array<string,mixed> $settings */
    private function uses_current_query( array $settings ): bool {
        foreach ( $settings as $key => $value ) {
            if ( is_array( $value ) && $this->uses_current_query( $value ) ) {
                return true;
            }
            if ( is_string( $key ) && str_ends_with( $key, 'post_type' ) && 'current_query' === $value ) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $settings */
    private function requires_previous_results( array $settings ): bool {
        foreach ( $settings as $key => $value ) {
            if ( is_array( $value ) && $this->requires_previous_results( $value ) ) {
                return true;
            }
            if ( is_string( $key ) && str_ends_with( $key, 'avoid_duplicates' ) && 'yes' === $value ) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $settings */
    private function requires_truncation( array $settings ): bool {
        foreach ( $settings as $key => $value ) {
            if ( is_array( $value ) && $this->requires_truncation( $value ) ) {
                return true;
            }
            if ( ! is_string( $key ) || ! ( 'posts_num' === $key || 'posts_per_page' === $key || str_ends_with( $key, '_posts_per_page' ) ) || ! is_scalar( $value ) ) {
                continue;
            }
            $requested = (int) $value;
            if ( -1 === $requested || $requested > $this->max_posts ) {
                return true;
            }
        }
        return false;
    }

    /** @param array<int,mixed> $posts */
    private function result_from_posts( array $posts, string $provider, bool $truncated = false ): array {
        $truncated = $truncated || count( $posts ) > $this->max_posts;
        $post_ids = [];
        foreach ( array_slice( $posts, 0, $this->max_posts ) as $post ) {
            if ( ! ( is_object( $post ) && isset( $post->ID ) ) && ! is_int( $post ) && ! ( is_string( $post ) && ctype_digit( $post ) ) ) {
                continue;
            }
            $post_id = is_object( $post ) && isset( $post->ID ) ? absint( $post->ID ) : absint( $post );
            if ( $post_id < 1 || 'publish' !== (string) get_post_status( $post_id ) ) {
                continue;
            }
            $post_type = get_post_type( $post_id );
            $post_type_object = is_string( $post_type ) && '' !== $post_type ? get_post_type_object( $post_type ) : null;
            $viewable = is_object( $post_type_object ) && function_exists( 'is_post_type_viewable' )
                ? is_post_type_viewable( $post_type_object )
                : is_object( $post_type_object ) && ! empty( $post_type_object->public );
            if ( ! $viewable ) {
                continue;
            }
            $post_ids[ $post_id ] = $post_id;
        }

        if ( ! empty( $posts ) && empty( $post_ids ) ) {
            return $this->failure(
                'native_query_result_type_unsupported',
                'The native widget query returned content that is not a published WordPress post result.',
                [ 'provider' => $provider ]
            );
        }

        $category_ids = [];
        foreach ( $post_ids as $post_id ) {
            $ids = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );
            if ( is_wp_error( $ids ) || ! is_array( $ids ) ) {
                continue;
            }
            foreach ( $ids as $category_id ) {
                $category_id = absint( $category_id );
                if ( $category_id > 0 ) {
                    $category_ids[ $category_id ] = $category_id;
                }
            }
        }

        return [
            'resolved'     => true,
            'provider'     => sanitize_key( $provider ),
            'category_ids' => array_values( $category_ids ),
            'post_ids'     => array_values( $post_ids ),
            'post_count'   => count( $post_ids ),
            'result_limit' => $this->max_posts,
            'warning'      => $truncated ? [
                'code'    => 'native_query_results_truncated',
                'message' => 'The saved widget requests more posts than the bounded manifest query limit, so its category evidence is partial.',
                'context' => [ 'result_limit' => $this->max_posts, 'provider' => sanitize_key( $provider ) ],
            ] : null,
        ];
    }

    /** @param array<string,mixed> $result */
    private function normalize_result( array $result ): array {
        if ( empty( $result['resolved'] ) ) {
            $warning = isset( $result['warning'] ) && is_array( $result['warning'] ) ? $result['warning'] : [];
            return $this->failure(
                (string) ( $warning['code'] ?? 'native_query_fixture_failed' ),
                (string) ( $warning['message'] ?? 'The native widget query fixture did not resolve.' ),
                isset( $warning['context'] ) && is_array( $warning['context'] ) ? $warning['context'] : []
            );
        }

        $category_ids = [];
        foreach ( (array) ( $result['category_ids'] ?? [] ) as $category_id ) {
            $category_id = absint( $category_id );
            if ( $category_id > 0 ) {
                $category_ids[ $category_id ] = $category_id;
            }
        }

        $post_ids = [];
        foreach ( (array) ( $result['post_ids'] ?? [] ) as $post_id ) {
            $post_id = absint( $post_id );
            if ( $post_id > 0 ) {
                $post_ids[ $post_id ] = $post_id;
            }
        }

        $post_count = min( $this->max_posts, absint( $result['post_count'] ?? count( $post_ids ) ) );

        return [
            'resolved'     => true,
            'provider'     => sanitize_key( (string) ( $result['provider'] ?? 'fixture' ) ),
            'category_ids' => array_values( $category_ids ),
            'post_ids'     => array_values( $post_ids ),
            'post_count'   => $post_count,
            'result_limit' => $this->max_posts,
            'warning'      => isset( $result['warning'] ) && is_array( $result['warning'] ) ? [
                'code'    => sanitize_key( (string) ( $result['warning']['code'] ?? 'native_query_warning' ) ),
                'message' => sanitize_text_field( (string) ( $result['warning']['message'] ?? 'Native widget query evidence is partial.' ) ),
                'context' => isset( $result['warning']['context'] ) && is_array( $result['warning']['context'] ) ? $result['warning']['context'] : [],
            ] : null,
        ];
    }

    /**
     * Retain actual preceding post IDs only inside this collection. The public
     * native-query record deliberately keeps its existing schema.
     *
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    private function finalize_result( array $result, bool $affects_elementor_history ): array {
        $post_ids = [];
        foreach ( (array) ( $result['post_ids'] ?? [] ) as $post_id ) {
            $post_id = absint( $post_id );
            if ( $post_id > 0 ) {
                $post_ids[ $post_id ] = $post_id;
            }
        }

        if ( $affects_elementor_history ) {
            if ( empty( $result['resolved'] ) || is_array( $result['warning'] ?? null ) || (int) ( $result['post_count'] ?? 0 ) !== count( $post_ids ) ) {
                $this->previous_results_complete = false;
            }
            foreach ( $post_ids as $post_id ) {
                $this->previous_post_ids[ $post_id ] = $post_id;
            }
        }

        unset( $result['post_ids'] );
        return $result;
    }

    /** @param array<string,mixed> $context */
    private function failure( string $code, string $message, array $context = [] ): array {
        return [
            'resolved'     => false,
            'provider'     => '',
            'category_ids' => [],
            'post_ids'     => [],
            'post_count'   => 0,
            'result_limit' => $this->max_posts,
            'warning'      => [
                'code'    => sanitize_key( $code ),
                'message' => sanitize_text_field( $message ),
                'context' => $context,
            ],
        ];
    }
}
