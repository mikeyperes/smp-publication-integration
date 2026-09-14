<?php

declare( strict_types=1 );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['smpi_manifest_actions'] = [];
$GLOBALS['smpi_manifest_route']   = [];
$GLOBALS['smpi_manifest_terms']   = [];

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

function absint( $value ): int {
    return abs( (int) $value );
}

function get_option( string $key, $default = false ) {
    return 'default_category' === $key ? 1 : $default;
}

function get_term_link( object $term ): string {
    return 'https://example.test/category/' . $term->slug . '/';
}

function is_wp_error( $value ): bool {
    return $value instanceof WP_Error;
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

function apply_filters( string $hook, $value ) {
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
        'parent'  => 0,
        'count'   => 3,
    ];
}

require_once dirname( __DIR__ ) . '/src/PublicationManifest/TaxonomyPolicy.php';
require_once dirname( __DIR__ ) . '/src/PublicationManifest/HomepageCollector.php';
require_once dirname( __DIR__ ) . '/src/PublicationManifest/WordPressCollector.php';
require_once dirname( __DIR__ ) . '/src/PublicationManifest/PayloadSanitizer.php';
require_once dirname( __DIR__ ) . '/src/PublicationManifest/ManifestBuilder.php';
require_once dirname( __DIR__ ) . '/src/PublicationManifest/ManifestEndpoint.php';

use SMP\PublicationIntegration\PublicationManifest\HomepageCollector;
use SMP\PublicationIntegration\PublicationManifest\ManifestEndpoint;
use SMP\PublicationIntegration\PublicationManifest\PayloadSanitizer;

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

$endpoint = new ManifestEndpoint();
$endpoint->register_route();
expect_manifest( 'smpi/v1' === $GLOBALS['smpi_manifest_route']['namespace'], 'The manifest must use the stable smpi/v1 namespace.' );
expect_manifest( '/publication-manifest' === $GLOBALS['smpi_manifest_route']['route'], 'The public manifest route must be registered.' );
expect_manifest( '__return_true' === $GLOBALS['smpi_manifest_route']['args']['permission_callback'], 'The read-only manifest must be public.' );

fwrite( STDOUT, "PASS: publication manifest extracts 11 homepage categories, excludes reserved/menu data, sanitizes public output, and registers the public route.\n" );
