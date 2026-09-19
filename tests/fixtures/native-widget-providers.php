<?php

declare( strict_types=1 );

namespace {
    final class WP_Query {
        public array $posts = [];
        public function __construct( public array $query_vars = [] ) {}
        public function get( string $key ) { return $this->query_vars[ $key ] ?? null; }
        public function set( string $key, $value ): void { $this->query_vars[ $key ] = $value; }
    }
    function remove_action( string $hook, $callback, int $priority = 10 ): void {
        if ( ( $GLOBALS['smpi_manifest_actions'][ $hook ][0] ?? null ) === $callback ) {
            unset( $GLOBALS['smpi_manifest_actions'][ $hook ] );
        }
    }
    function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $GLOBALS['smpi_manifest_filters'][ $hook ] = $callback;
    }
    function remove_filter( string $hook, $callback, int $priority = 10 ): void {
        if ( ( $GLOBALS['smpi_manifest_filters'][ $hook ] ?? null ) === $callback ) {
            unset( $GLOBALS['smpi_manifest_filters'][ $hook ] );
        }
    }
    function get_post( int $id ) { return $id > 0 ? (object) [ 'ID' => $id ] : null; }
    function get_post_status( int $id ): string { return [ 103 => 'draft', 104 => 'private' ][$id] ?? 'publish'; }
    function get_post_type( int $id ): string { return 105 === $id ? 'private_type' : 'post'; }
    function get_post_type_object( string $type ): object { return (object) [ 'public' => 'post' === $type ]; }
    function is_post_type_viewable( object $type ): bool { return $type->public; }
    function wp_get_post_categories( int $id, array $args ): array {
        return [ 101 => [ 8 ], 102 => [ 6271 ], 103 => [ 5712 ], 104 => [ 9542 ], 105 => [ 1496 ], 106 => [ 7548 ] ][$id] ?? [];
    }
    function smpi_fixture_query( array $settings, object $widget, string $hook ): WP_Query {
        $GLOBALS['smpi_native_executions']++;
        // Providers can load templates with their own unbounded lookup. It must
        // neither be clamped nor mark the one-card article query truncated.
        $lookup = new WP_Query( [ 'posts_per_page' => -1, 'post_type' => 'elementor_library' ] );
        ( $GLOBALS['smpi_manifest_actions']['pre_get_posts'][0] )( $lookup );
        $GLOBALS['smpi_internal_lookup_unchanged'] = -1 === $lookup->get( 'posts_per_page' );
        $query = new WP_Query( apply_filters( $hook, [
            'posts_per_page' => $settings['fixture_hook_limit'] ?? $settings['post_query_posts_per_page'] ?? $settings['posts_num'] ?? 3,
            'post_status' => 'any',
        ], $widget ) );
        ( $GLOBALS['smpi_manifest_actions']['pre_get_posts'][0] )( $query );
        $GLOBALS['smpi_last_native_query'] = $query;
        if ( ! empty( $settings['fixture_throw'] ) ) { throw new \RuntimeException( 'fixture query failure' ); }
        $query->posts = array_slice( $settings['fixture_posts'] ?? [ 101, 102, 106 ], 0, $query->get( 'posts_per_page' ) );
        return $query;
    }
    final class SmpiFixtureDocuments {
        public $current = 'outer';
        private array $stack = [];
        public function get( int $id ): object { return (object) [ 'ID' => $id ]; }
        public function switch_to_document( object $document ): void { $this->stack[] = $this->current; $this->current = $document; }
        public function restore_document(): void { $this->current = array_pop( $this->stack ); }
    }
    final class SmpiFixtureElementorDb {
        private array $stack = [];
        public function switch_to_post( int $id ): void { $this->stack[] = $GLOBALS['post'] ?? null; $GLOBALS['post'] = get_post( $id ); }
        public function restore_current_post(): void { $GLOBALS['post'] = array_pop( $this->stack ); }
    }
    final class SmpiFixtureElements {
        public function create_element_instance( array $node ): object {
            return new class( $node['settings'] ?? [] ) {
                private WP_Query $query;
                public function __construct( private array $settings ) {}
                public function query_posts(): void {
                    $this->query = smpi_fixture_query( $this->settings, $this, 'elementor/query/query_args' );
                    \ElementorPro\Modules\QueryControl\Module::$displayed_ids = $this->query->posts;
                }
                public function get_query(): WP_Query { return $this->query; }
            };
        }
    }
    final class SmpiFixtureJetData {
        public int $listing = 987;
        public function set_listing_by_id( int $id ): void { $this->listing = $id; }
        public function get_listing_source(): string {
            if ( 999 === $this->listing ) { return 'users'; }
            if ( 501 === $this->listing ) { return 'query'; }
            return 'posts';
        }
    }
    final class SmpiFixtureJetListings {
        public SmpiFixtureJetData $data;
        public function __construct() { $this->data = new SmpiFixtureJetData(); }
        public function get_render_instance( string $type, array $settings ): object {
            return new class( $settings ) {
                public function __construct( private array $settings ) {}
                public function setup_listing_props(): void {}
                public function get_settings(): array { return $this->settings; }
                public function get_query( array $settings ): array {
                    expect_manifest( jet_engine()->listings->data->listing === (int) $settings['lisitng_id'], 'Jet must bind the exact saved listing before querying.' );
                    expect_manifest( 42 === $GLOBALS['post']->ID, 'Jet must use homepage post context.' );
                    return smpi_fixture_query( $settings, $this, 'jet-engine/listing/grid/posts-query-args' )->posts;
                }
            };
        }
    }
    final class SmpiFixtureJetQueryListings {
        public function get_query_id( int $listing_id, array $settings ): int {
            unset( $listing_id );
            return absint( $settings['custom_query_id'] ?? 0 );
        }
    }
    final class SmpiFixtureJetQuery {
        public $final_query = null;
        public $final_query_raw = null;
        public $cache_query = true;
        public array $dynamic_query = [];
        public function __construct( public int $id, public string $query_type = 'posts', array $dynamic_query = [] ) {
            $this->dynamic_query = $dynamic_query;
        }
        public function get_query_type(): string { return $this->query_type; }
        public function reset_query(): void {}
        public function get_items(): array {
            ++$GLOBALS['smpi_native_executions'];
            $arguments = apply_filters(
                'jet-engine/query-builder/types/posts-query/args',
                [ 'posts_per_page' => 100, 'post_status' => 'any' ],
                $this
            );
            $query = new WP_Query( $arguments );
            ( $GLOBALS['smpi_manifest_actions']['pre_get_posts'][0] )( $query );
            $GLOBALS['smpi_last_native_query'] = $query;
            return array_slice( array_merge( [ 101, 102, 106 ], range( 1000, 1047 ) ), 0, $query->get( 'posts_per_page' ) );
        }
    }
    function jet_engine(): object { return $GLOBALS['smpi_fixture_jet']; }
}

namespace Elementor {
    final class Plugin { public static $instance; }
}

namespace ElementorPro\Modules\QueryControl {
    final class Module { public static array $displayed_ids = [ 777 ]; }
}

namespace Jet_Engine\Query_Builder {
    final class Manager {
        public static $instance;
        public object $listings;
        /** @var array<int,object> */
        public array $queries = [];
        public static function instance(): self { return self::$instance; }
        public function get_query_by_id_for_context( int $id, array $context ) {
            if ( (int) ( $context['query_id'] ?? 0 ) !== $id ) { return false; }
            return $this->queries[ $id ] ?? false;
        }
    }
}
