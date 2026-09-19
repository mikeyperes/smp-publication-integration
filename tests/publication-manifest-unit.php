<?php

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['smpi_manifest_actions'] = [];
$GLOBALS['smpi_manifest_route']   = [];
$GLOBALS['smpi_manifest_terms']   = [];
$GLOBALS['smpi_manifest_post_meta'] = [];
$GLOBALS['smpi_manifest_object_taxonomies'] = [ 'post' => [ 'category', 'post_tag' ] ];

final class WP_Error {}
final class WP_REST_Request {}
final class WP_REST_Response {}

function sanitize_key( string $value ): string {
    return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $value ) );
}

function sanitize_text_field( string $value ): string {
    return trim( strip_tags( $value ) );
}

function wp_strip_all_tags( string $value ): string {
    return strip_tags( $value );
}

function wp_json_encode( $value ): string {
    return (string) json_encode( $value );
}

function absint( $value ): int {
    return abs( (int) $value );
}

function get_option( string $key, $default = false ) {
    return 'default_category' === $key ? 1 : $default;
}

function get_post_meta( int $post_id, string $key, bool $single = false ) {
    unset( $single );
    return $GLOBALS['smpi_manifest_post_meta'][ $post_id ][ $key ] ?? '';
}

function get_term_link( object $term ): string {
    return 'https://example.test/category/' . $term->slug . '/';
}

function is_wp_error( $value ): bool {
    return $value instanceof WP_Error;
}

function post_type_exists( string $post_type ): bool {
    return in_array( $post_type, [ 'post', 'team-member' ], true );
}

function get_object_taxonomies( string $post_type, string $output = 'names' ): array {
    return $GLOBALS['smpi_manifest_object_taxonomies'][ $post_type ] ?? [];
}

function taxonomy_exists( string $taxonomy ): bool {
    foreach ( $GLOBALS['smpi_manifest_object_taxonomies'] as $taxonomies ) {
        if ( in_array( $taxonomy, $taxonomies, true ) ) {
            return true;
        }
    }

    return false;
}

function get_term( int $term_id, string $taxonomy ) {
    return 'category' === $taxonomy ? ( $GLOBALS['smpi_manifest_terms'][ $term_id ] ?? null ) : null;
}

function get_term_by( string $field, string $value, string $taxonomy ) {
    if ( 'category' !== $taxonomy ) {
        return null;
    }
    foreach ( $GLOBALS['smpi_manifest_terms'] as $term ) {
        if ( isset( $term->{$field} ) && strtolower( (string) $term->{$field} ) === strtolower( $value ) ) {
            return $term;
        }
    }
    return null;
}

function get_permalink( int $post_id ): string {
    return 'https://example.test/?page_id=' . $post_id;
}

function get_the_title( int $post_id ): string {
    return 42 === $post_id ? 'Home' : '';
}

function home_url( string $path = '/' ): string {
    return 'https://example.test' . $path;
}

function get_bloginfo( string $field ): string {
    return 'name' === $field ? 'Example Publication' : '';
}

function apply_filters( string $hook, $value, ...$arguments ) {
    if ( isset( $GLOBALS['smpi_manifest_filters'][ $hook ] ) ) {
        return ( $GLOBALS['smpi_manifest_filters'][ $hook ] )( $value, ...$arguments );
    }
    return $value;
}

function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
    $GLOBALS['smpi_manifest_actions'][ $hook ] = [ $callback, $priority, $accepted_args ];
}

function register_rest_route( string $namespace, string $route, array $args ): void {
    $GLOBALS['smpi_manifest_route'] = compact( 'namespace', 'route', 'args' );
}

function expect_manifest( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$term_fixtures = [
    1    => [ 'Uncategorized', 'uncategorized' ],
    8    => [ 'Fashion', 'fashion' ],
    1496 => [ 'Travel', 'travel' ],
    2029 => [ 'Entrepreneur', 'entrepreneur' ],
    5712 => [ 'Celebrity', 'celebrity' ],
    6270 => [ 'Luxury', 'luxury' ],
    6271 => [ 'Business', 'business' ],
    6295 => [ 'Trending', 'trending' ],
    6343 => [ 'Real Estate', 'real-estate' ],
    6344 => [ 'Lifestyle', 'lifestyle' ],
    7548 => [ 'Digital Magazine', 'digital-magazine' ],
    9542 => [ 'Politics', 'politics' ],
    9999 => [ 'Menu Only', 'menu-only' ],
];

foreach ( $term_fixtures as $id => [ $name, $slug ] ) {
    $GLOBALS['smpi_manifest_terms'][ $id ] = (object) [
        'term_id' => $id,
        'name'    => $name,
        'slug'    => $slug,
        'description' => '<p>' . $name . ' reporting and analysis.</p>',
        'parent'  => 0,
        'count'   => 3,
    ];
}

require_once dirname( __DIR__ ) . '/src/PublicationManifest/TaxonomyPolicy.php';
require_once dirname( __DIR__ ) . '/src/PublicationManifest/ElementorQueryInspector.php';
require_once dirname( __DIR__ ) . '/src/PublicationManifest/NativeWidgetQueryCollector.php';
require_once dirname( __DIR__ ) . '/src/PublicationManifest/HomepageCollector.php';
require_once dirname( __DIR__ ) . '/src/Content/PublicationContentTypes.php';
require_once dirname( __DIR__ ) . '/src/Content/ArticleTypes.php';
require_once dirname( __DIR__ ) . '/src/PublicationManifest/WordPressCollector.php';
require_once dirname( __DIR__ ) . '/src/PublicationManifest/PayloadSanitizer.php';
require_once dirname( __DIR__ ) . '/src/PublicationManifest/ManifestBuilder.php';
require_once dirname( __DIR__ ) . '/src/PublicationManifest/ManifestEndpoint.php';

use SMP\PublicationIntegration\PublicationManifest\HomepageCollector;
use SMP\PublicationIntegration\PublicationManifest\ManifestEndpoint;
use SMP\PublicationIntegration\PublicationManifest\NativeWidgetQueryCollector;
use SMP\PublicationIntegration\PublicationManifest\PayloadSanitizer;
use SMP\PublicationIntegration\PublicationManifest\WordPressCollector;

$category_queries = [
    [ 'name', 'trending' ],
    [ 'slug', 'digital-magazine' ],
    [ 'slug', 'travel' ],
    [ 'slug', 'business' ],
    [ 'slug', 'celebrity' ],
    [ 'slug', 'entrepreneur' ],
    [ 'slug', 'lifestyle' ],
    [ 'slug', 'luxury' ],
    [ 'slug', 'fashion' ],
    [ 'slug', 'politics' ],
    [ 'slug', 'real-estate' ],
];

$elements = [];
foreach ( $category_queries as $index => [ $field, $term ] ) {
    $elements[] = [
        'id'       => 'section' . $index,
        'elType'   => 'container',
        'settings' => [ '_title' => ucwords( str_replace( '-', ' ', $term ) ) ],
        'elements' => [
            [
                'id'         => 'query' . $index,
                'elType'     => 'widget',
                'widgetType' => 'jet-listing-grid',
                'settings'   => [
                    'lisitng_id' => 50000 + $index,
                    'posts_num'  => 3,
                    'posts_query'=> [
                        [
                            'type'               => 'tax_query',
                            'tax_query_taxonomy' => 'category',
                            'tax_query_field'    => $field,
                            'tax_query_terms'    => $term,
                        ],
                    ],
                ],
                'elements'   => [],
            ],
        ],
    ];
}

$elements[] = [
    'id'       => 'elementor-terms',
    'elType'   => 'widget',
    'widgetType'=> 'posts',
    'settings' => [ 'posts_include_term_ids' => [ 'category:1496' ] ],
    'elements' => [],
];
$elements[] = [
    'id'       => 'header',
    'elType'   => 'container',
    'settings' => [ '_title' => 'Header', 'html_tag' => 'header' ],
    'elements' => [
        [
            'id'         => 'menu',
            'elType'     => 'widget',
            'widgetType' => 'nav-menu',
            'settings'   => [ 'posts_include_term_ids' => [ 'category:9999' ] ],
            'elements'   => [],
        ],
    ],
];

$homepage = ( new HomepageCollector() )->collect_from_elements( 42, $elements );
expect_manifest( 11 === count( $homepage['categories'] ), 'All 11 Rich Reporter homepage query categories must resolve.' );
expect_manifest( 10 === count( $homepage['campaign_categories'] ), 'Digital Magazine must be reserved, leaving 10 campaign categories.' );
expect_manifest( ! in_array( 9999, array_column( $homepage['categories'], 'id' ), true ), 'Menu/header taxonomy references must be excluded.' );
expect_manifest( in_array( 1496, array_column( $homepage['categories'], 'id' ), true ), 'Elementor category:ID query tokens must resolve.' );
expect_manifest( 'complete' === $homepage['collection_status'] && [] === $homepage['collection_warnings'], 'A fully interpreted homepage must report complete collection.' );
$travel_category = current( array_filter( $homepage['categories'], static fn( array $category ): bool => 1496 === $category['id'] ) );
expect_manifest( is_array( $travel_category ) && 'Travel reporting and analysis.' === $travel_category['description'], 'Native category descriptions must be exposed as plain first-party input.' );

$loop_widget = static fn( string $id, string $type, array $settings ): array => [
    'id'       => 'section-' . $id,
    'elType'   => 'container',
    'settings' => [ '_title' => 'Loop ' . $id ],
    'elements' => [ [ 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [] ] ],
];
$loop_ids = static fn( array $elements ): array => array_column( ( new HomepageCollector() )->collect_from_elements( 43, $elements )['categories'], 'id' );
// CAMPAIGN-BUG-008: Elementor Pro loop query controls store plain term IDs.
expect_manifest( [ 1496 ] === $loop_ids( [ $loop_widget( 'grid', 'loop-grid', [ 'post_query_include' => [ 'terms' ], 'post_query_include_term_ids' => [ '1496' ] ] ) ] ), 'Loop Grid include term IDs must resolve.' );
expect_manifest( [ 1496 ] === $loop_ids( [ $loop_widget( 'carousel', 'loop-carousel', [ 'post_query_include' => [ 'terms' ], 'post_query_include_term_ids' => [ '1496' ] ] ) ] ), 'Loop Carousel include term IDs must resolve.' );
expect_manifest( [] === $loop_ids( [ $loop_widget( 'off', 'loop-grid', [ 'post_query_include' => [], 'post_query_include_term_ids' => [ '1496' ] ] ) ] ), 'Term IDs without the terms include mode must be ignored.' );
expect_manifest( [] === $loop_ids( [ $loop_widget( 'exclude', 'loop-grid', [ 'post_query_exclude_term_ids' => [ '1496' ] ] ) ] ), 'Excluded term IDs must not become categories.' );

$mixed_taxonomy = $loop_widget(
    'mixed-taxonomy',
    'posts',
    [
        'posts_query' => [
            [
                'tax_query_taxonomy' => 'category',
                'tax_query_field'    => 'term_id',
                'tax_query_terms'    => [ 1496 ],
                'tax_query_operator' => 'IN',
            ],
            [
                'tax_query_taxonomy' => 'category',
                'tax_query_field'    => 'term_id',
                'tax_query_terms'    => [ 'category:6271' ],
                'tax_query_operator' => 'NOT IN',
            ],
        ],
        'posts_query_exclude_term_ids' => [ 'category:5712' ],
    ]
);
expect_manifest( [ 1496 ] === $loop_ids( [ $mixed_taxonomy ] ), 'NOT IN clauses and category tokens under exclude controls must not become positive categories.' );

$all_device_hidden = $loop_widget(
    'all-device-hidden',
    'posts',
    [
        'hide_desktop'           => 'yes',
        'hide_tablet'            => 'yes',
        'hide_mobile'            => 'yes',
        'posts_include_term_ids' => [ 'category:5712' ],
    ]
);
expect_manifest( [] === $loop_ids( [ $all_device_hidden ] ), 'A widget hidden on desktop, tablet, and mobile must provide no homepage category evidence.' );

$GLOBALS['smpi_manifest_post_meta'][200]['_elementor_data'] = wp_json_encode(
    [
        [
            'id'       => 'business-section',
            'elType'   => 'container',
            'settings' => [ '_title' => 'Business Desk' ],
            'elements' => [
                [
                    'id'         => 'business-heading',
                    'elType'     => 'widget',
                    'widgetType' => 'heading',
                    'settings'   => [ 'title' => 'Business Insights' ],
                    'elements'   => [],
                ],
                [
                    'id'         => 'shared-query',
                    'elType'     => 'widget',
                    'widgetType' => 'posts',
                    'settings'   => [ 'posts_include_term_ids' => [ 'category:6271' ] ],
                    'elements'   => [],
                ],
            ],
        ],
        [
            'id'         => 'nested-template',
            'elType'     => 'widget',
            'widgetType' => 'template',
            'settings'   => [ 'template_id' => 201 ],
            'elements'   => [],
        ],
        [
            'id'         => 'nested-menu',
            'elType'     => 'widget',
            'widgetType' => 'nav-menu',
            'settings'   => [ 'posts_include_term_ids' => [ 'category:9999' ] ],
            'elements'   => [],
        ],
    ]
);
$GLOBALS['smpi_manifest_post_meta'][201]['_elementor_data'] = wp_json_encode(
    [
        [
            'id'       => 'fashion-section',
            'elType'   => 'container',
            'settings' => [ '_title' => 'Fashion Desk' ],
            'elements' => [
                [
                    'id'         => 'fashion-heading',
                    'elType'     => 'widget',
                    'widgetType' => 'heading',
                    'settings'   => [ 'title' => 'Fashion Coverage' ],
                    'elements'   => [],
                ],
                [
                    'id'         => 'shared-query',
                    'elType'     => 'widget',
                    'widgetType' => 'posts',
                    'settings'   => [ 'posts_include_term_ids' => [ 'category:8' ] ],
                    'elements'   => [],
                ],
            ],
        ],
    ]
);
$template_homepage = ( new HomepageCollector() )->collect_from_elements(
    44,
    [
        [
            'id'         => 'homepage-template',
            'elType'     => 'widget',
            'widgetType' => 'template',
            'settings'   => [ 'template_id' => 200 ],
            'elements'   => [],
        ],
        [
            'id'         => 'homepage-template-copy',
            'elType'     => 'widget',
            'widgetType' => 'template',
            'settings'   => [ 'template_id' => 200 ],
            'elements'   => [],
        ],
    ]
);
$template_category_ids = array_column( $template_homepage['categories'], 'id' );
sort( $template_category_ids );
expect_manifest( [ 8, 6271 ] === $template_category_ids, 'Nested Elementor templates must contribute their explicit homepage query categories.' );
expect_manifest( ! in_array( 9999, $template_category_ids, true ), 'Navigation widgets inside templates must remain excluded.' );
$template_evidence_ids = array_column( $template_homepage['query_widgets'], 'elementor_id' );
expect_manifest( count( $template_evidence_ids ) === count( array_unique( $template_evidence_ids ) ), 'Template query evidence IDs must be stable and unique.' );
expect_manifest( in_array( 'template-homepage-template-200-shared-query', $template_evidence_ids, true ), 'Direct template query evidence must identify its stable template placement.' );
expect_manifest( in_array( 'template-homepage-template-200-nested-template-201-shared-query', $template_evidence_ids, true ), 'Nested template query evidence must identify its full stable template placement.' );
$template_section_headings = array_column( $template_homepage['sections'], 'heading' );
expect_manifest( in_array( 'Business Insights', $template_section_headings, true ) && in_array( 'Fashion Coverage', $template_section_headings, true ), 'Section headings inside nested Elementor templates must remain available as manifest input.' );
expect_manifest( range( 1, count( $template_homepage['sections'] ) ) === array_column( $template_homepage['sections'], 'order' ), 'Root and embedded homepage sections must retain stable sequential order.' );
foreach ( $template_homepage['categories'] as $template_category ) {
    foreach ( $template_category['sources'] as $source ) {
        expect_manifest( in_array( $source['elementor_id'], $template_evidence_ids, true ), 'Category source evidence must match a reported query-widget evidence ID.' );
    }
}

$GLOBALS['smpi_manifest_post_meta'][300]['_elementor_data'] = wp_json_encode(
    [
        [
            'id'         => 'cycle',
            'elType'     => 'widget',
            'widgetType' => 'template',
            'settings'   => [ 'template_id' => 300 ],
            'elements'   => [],
        ],
    ]
);
$cycle_homepage = ( new HomepageCollector() )->collect_from_elements(
    45,
    [ [ 'id' => 'cycle-root', 'elType' => 'widget', 'widgetType' => 'template', 'settings' => [ 'template_id' => 300 ], 'elements' => [] ] ]
);
expect_manifest( 'partial' === $cycle_homepage['collection_status'], 'A template cycle must mark homepage collection partial.' );
expect_manifest( in_array( 'template_cycle_detected', array_column( $cycle_homepage['collection_warnings'], 'code' ), true ), 'A template cycle must produce a machine-readable warning.' );

for ( $template_id = 400; $template_id <= 408; $template_id++ ) {
    $GLOBALS['smpi_manifest_post_meta'][ $template_id ]['_elementor_data'] = wp_json_encode(
        [
            [
                'id'         => 'depth-' . $template_id,
                'elType'     => 'widget',
                'widgetType' => 'template',
                'settings'   => [ 'template_id' => $template_id + 1 ],
                'elements'   => [],
            ],
        ]
    );
}
$depth_homepage = ( new HomepageCollector() )->collect_from_elements(
    46,
    [ [ 'id' => 'depth-root', 'elType' => 'widget', 'widgetType' => 'template', 'settings' => [ 'template_id' => 400 ], 'elements' => [] ] ]
);
expect_manifest( in_array( 'template_depth_limit_reached', array_column( $depth_homepage['collection_warnings'], 'code' ), true ), 'Nested template traversal must stop with a machine-readable depth warning.' );

$unsupported_query_homepage = ( new HomepageCollector() )->collect_from_elements(
    47,
    [
        $loop_widget(
            'unsupported-operator',
            'posts',
            [
                'posts_query' => [
                    [
                        'tax_query_taxonomy' => 'category',
                        'tax_query_field'    => 'term_id',
                        'tax_query_terms'    => [ 1496 ],
                        'tax_query_operator' => 'XOR',
                    ],
                ],
            ]
        ),
        $loop_widget( 'custom-hook', 'posts', [ 'query_id' => 'homepage_custom_query' ] ),
        $loop_widget( 'unscoped', 'posts', [] ),
    ]
);
$unsupported_codes = array_column( $unsupported_query_homepage['collection_warnings'], 'code' );
expect_manifest( in_array( 'unsupported_taxonomy_operator', $unsupported_codes, true ), 'Unsupported taxonomy operators must produce a machine-readable warning.' );
expect_manifest( in_array( 'custom_query_hook_not_inspected', $unsupported_codes, true ), 'Custom query hooks must produce a machine-readable warning.' );
expect_manifest( in_array( 'query_scope_not_statically_resolved', $unsupported_codes, true ), 'Unscoped query widgets must report that their categories were not fabricated.' );

// SMP-MANIFEST-BUG-004: ordered Elementor results must seed later
// avoid-duplicates queries without changing saved-query provenance.
$history_widget = static fn( string $id, array $settings, string $type = 'loop-grid' ): array => [
    'id'         => $id,
    'elType'     => 'widget',
    'widgetType' => $type,
    'settings'   => $settings,
    'elements'   => [],
];
$history_calls = [];
$history_collector = new NativeWidgetQueryCollector(
    static function ( array $node, int $document_id, int $maximum_posts, array $previous_post_ids ) use ( &$history_calls ): array {
        unset( $document_id, $maximum_posts );
        $history_calls[] = [
            'id'       => (string) $node['id'],
            'previous' => $previous_post_ids,
            'offset'   => $node['settings']['post_query_offset'] ?? null,
        ];
        if ( 'prior-static' === $node['id'] ) {
            return [ 'resolved' => true, 'provider' => 'fixture', 'category_ids' => [ 1496 ], 'post_ids' => [ 501, 502, 503 ], 'post_count' => 3 ];
        }
        return [ 'resolved' => true, 'provider' => 'fixture', 'category_ids' => [ 6271 ], 'post_ids' => [ 504, 505 ], 'post_count' => 2 ];
    }
);
$GLOBALS['smpi_manifest_post_meta'][600]['_elementor_data'] = wp_json_encode(
    [
        $history_widget(
            'prior-static',
            [
                'post_query_include'          => [ 'terms' ],
                'post_query_include_term_ids' => [ 1496 ],
                'post_query_posts_per_page'   => 3,
            ],
            'loop-carousel'
        ),
    ]
);
$history_homepage_collector = new HomepageCollector( null, null, $history_collector );
$history_homepage = $history_homepage_collector->collect_from_elements(
    48,
    [
        $history_widget( 'history-template', [ 'template_id' => 600 ], 'template' ),
        $history_widget(
            'dependent',
            [
                'post_query_avoid_duplicates' => 'yes',
                'post_query_offset'           => 3,
                'post_query_posts_per_page'   => 2,
            ]
        ),
    ]
);
expect_manifest( [ 501, 502, 503 ] === $history_calls[1]['previous'], 'A dependent Elementor query must receive the actual ordered results from its preceding widget.' );
expect_manifest( 3 === $history_calls[1]['offset'], 'A dependent query must retain its saved offset while prior result IDs are bound.' );
expect_manifest( 'saved_query' === $history_homepage['query_widgets'][0]['category_source'] && false === $history_homepage['query_widgets'][0]['native_query']['attempted'], 'Private prior-result collection must not change saved-query provenance or public native metadata.' );
expect_manifest( 'native_query_results' === $history_homepage['query_widgets'][1]['category_source'] && 'complete' === $history_homepage['collection_status'], 'A complete prior-result context must resolve a later duplicate-avoiding query natively.' );
expect_manifest( [ 1496, 6271 ] === array_column( $history_homepage['categories'], 'id' ), 'Private prior-result collection must preserve the existing saved/native category union.' );

$history_homepage_collector->collect_from_elements(
    49,
    [ $history_widget( 'dependent-reset', [ 'post_query_avoid_duplicates' => 'yes', 'post_query_offset' => 3 ] ) ]
);
expect_manifest( [] === $history_calls[2]['previous'], 'Each homepage collection must reset prior-result context instead of leaking IDs between manifests.' );

$zero_calls = [];
$zero_collector = new NativeWidgetQueryCollector(
    static function ( array $node, int $document_id, int $maximum_posts, array $previous_post_ids ) use ( &$zero_calls ): array {
        unset( $document_id, $maximum_posts );
        $zero_calls[] = [ (string) $node['id'], $previous_post_ids ];
        return 'zero-prior' === $node['id']
            ? [ 'resolved' => true, 'provider' => 'fixture', 'category_ids' => [], 'post_ids' => [], 'post_count' => 0 ]
            : [ 'resolved' => true, 'provider' => 'fixture', 'category_ids' => [ 6271 ], 'post_ids' => [ 506 ], 'post_count' => 1 ];
    }
);
$zero_homepage = ( new HomepageCollector( null, null, $zero_collector ) )->collect_from_elements(
    50,
    [
        $history_widget( 'zero-prior', [ 'post_query_include_term_ids' => [ 'category:1496' ] ] ),
        $history_widget( 'zero-dependent', [ 'post_query_avoid_duplicates' => 'yes' ] ),
    ]
);
expect_manifest( 2 === count( $zero_calls ) && [] === $zero_calls[1][1] && 'complete' === $zero_homepage['collection_status'], 'A proven zero-result prior query must keep an empty but complete avoid-list context.' );

$truncated_calls = [];
$truncated_collector = new NativeWidgetQueryCollector(
    static function ( array $node, int $document_id, int $maximum_posts, array $previous_post_ids ) use ( &$truncated_calls ): array {
        unset( $document_id, $maximum_posts, $previous_post_ids );
        $truncated_calls[] = (string) $node['id'];
        return [
            'resolved'     => true,
            'provider'     => 'fixture',
            'category_ids' => [ 1496 ],
            'post_ids'     => [ 601 ],
            'post_count'   => 1,
            'warning'      => [ 'code' => 'native_query_results_truncated', 'message' => 'Fixture result was truncated.', 'context' => [] ],
        ];
    }
);
$truncated_homepage = ( new HomepageCollector( null, null, $truncated_collector ) )->collect_from_elements(
    51,
    [
        $history_widget( 'truncated-prior', [ 'post_query_include_term_ids' => [ 'category:1496' ] ] ),
        $history_widget( 'truncated-dependent', [ 'post_query_avoid_duplicates' => 'yes' ] ),
    ]
);
expect_manifest( [ 'truncated-prior' ] === $truncated_calls, 'A dependent query must not execute against a truncated preceding-result context.' );
expect_manifest( in_array( 'elementor_previous_results_required', array_column( $truncated_homepage['collection_warnings'], 'code' ), true ), 'A truncated prior context must leave a dependent query explicitly partial.' );

$unsupported_calls = [];
$unsupported_prior_collector = new NativeWidgetQueryCollector(
    static function ( array $node, int $document_id, int $maximum_posts, array $previous_post_ids ) use ( &$unsupported_calls ): array {
        unset( $document_id, $maximum_posts, $previous_post_ids );
        $unsupported_calls[] = (string) $node['id'];
        return [
            'resolved' => false,
            'warning'  => [ 'code' => 'native_query_provider_unsupported', 'message' => 'Fixture provider is unsupported.', 'context' => [] ],
        ];
    }
);
$unsupported_prior_homepage = ( new HomepageCollector( null, null, $unsupported_prior_collector ) )->collect_from_elements(
    52,
    [
        $history_widget( 'unsupported-prior', [ 'posts_include_term_ids' => [ 'category:1496' ] ], 'archive-posts' ),
        $history_widget( 'unsupported-dependent', [ 'post_query_avoid_duplicates' => 'yes' ] ),
    ]
);
expect_manifest( [ 'unsupported-prior' ] === $unsupported_calls, 'A dependent query must not execute after an unsupported prior query.' );
expect_manifest( in_array( 'elementor_previous_results_required', array_column( $unsupported_prior_homepage['collection_warnings'], 'code' ), true ), 'An unsupported prior query must leave a dependent query explicitly partial.' );

$hidden_calls = [];
$hidden_collector = new NativeWidgetQueryCollector(
    static function ( array $node, int $document_id, int $maximum_posts, array $previous_post_ids ) use ( &$hidden_calls ): array {
        unset( $document_id, $maximum_posts );
        $hidden_calls[] = [ (string) $node['id'], $previous_post_ids ];
        return 'hidden-prior' === $node['id']
            ? [ 'resolved' => true, 'provider' => 'fixture', 'category_ids' => [ 1496 ], 'post_ids' => [ 701 ], 'post_count' => 1 ]
            : [ 'resolved' => true, 'provider' => 'fixture', 'category_ids' => [ 6271 ], 'post_ids' => [ 702 ], 'post_count' => 1 ];
    }
);
$hidden_homepage = ( new HomepageCollector( null, null, $hidden_collector ) )->collect_from_elements(
    53,
    [
        $history_widget( 'hidden-prior', [ 'hide_desktop' => 'yes', 'hide_tablet' => 'yes', 'hide_mobile' => 'yes', 'posts_include_term_ids' => [ 'category:1496' ] ] ),
        $history_widget( 'hidden-dependent', [ 'post_query_avoid_duplicates' => 'yes' ] ),
    ]
);
expect_manifest( [ 701 ] === $hidden_calls[1][1], 'A responsive-hidden query must remain private evidence while still contributing its server-side result history.' );
expect_manifest( [ 6271 ] === array_column( $hidden_homepage['categories'], 'id' ) && 'complete' === $hidden_homepage['collection_status'], 'Responsive-hidden categories must remain excluded from public evidence without losing complete dependent context.' );

$digital_magazine = current( array_filter( $homepage['categories'], static fn( array $category ): bool => 7548 === $category['id'] ) );
expect_manifest( is_array( $digital_magazine ) && 'reserved' === $digital_magazine['campaign_policy']['status'], 'Digital Magazine must be marked reserved.' );

$sanitized = ( new PayloadSanitizer() )->sanitize(
    [
        'publication' => [
            'name'          => 'Example',
            'support_email' => 'private@example.test',
            'public_bio'    => 'Contact private@example.test from /home/example/private.txt',
        ],
        'plugin_inventory' => [ 'secret-plugin' ],
    ]
);
expect_manifest( ! isset( $sanitized['publication']['support_email'] ), 'Email-bearing keys must be removed.' );
expect_manifest( ! str_contains( $sanitized['publication']['public_bio'], 'private@example.test' ), 'Email values must be redacted.' );
expect_manifest( ! str_contains( $sanitized['publication']['public_bio'], '/home/example' ), 'Filesystem paths must be redacted.' );
expect_manifest( ! isset( $sanitized['plugin_inventory'] ), 'Unapproved root fields must be removed.' );

$wordpress = new WordPressCollector();
expect_manifest( ! isset( $wordpress->schema_profile()['article_type_taxonomy'] ), 'Disabled article type taxonomy must not be advertised.' );
$registered_taxonomies = new ReflectionMethod( $wordpress, 'registered_article_taxonomies' );
expect_manifest( [ 'category', 'post_tag' ] === $registered_taxonomies->invoke( $wordpress ), 'Manifest must expose registered article taxonomies.' );
$GLOBALS['smpi_manifest_object_taxonomies']['post'][] = 'smpi_article_type';
expect_manifest( 'smpi_article_type' === $wordpress->schema_profile()['article_type_taxonomy'], 'Registered article type taxonomy must be advertised.' );
expect_manifest( in_array( 'smpi_article_type', $registered_taxonomies->invoke( $wordpress ), true ), 'Delivery capabilities must include the registered article type taxonomy.' );

$endpoint = new ManifestEndpoint();
$endpoint->register_route();
expect_manifest( 'smpi/v1' === $GLOBALS['smpi_manifest_route']['namespace'], 'The manifest must use the stable smpi/v1 namespace.' );
expect_manifest( '/publication-manifest' === $GLOBALS['smpi_manifest_route']['route'], 'The public manifest route must be registered.' );
expect_manifest( '__return_true' === $GLOBALS['smpi_manifest_route']['args']['permission_callback'], 'The read-only manifest must be public.' );

require __DIR__ . '/fixtures/native-widget-providers.php';
require __DIR__ . '/native-widget-query-assertions.php';

fwrite( STDOUT, "PASS: publication manifest static/template and native Elementor/Jet query fixtures, exact limits, public posts, context restoration, truncation, budget, exclusions and public route.\n" );
