<?php

declare(strict_types=1);

namespace {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );

    $GLOBALS['smpi_test_acf_options'] = [
        'website' => [
            'footer_text' => '<p>Powered by an unrelated HWS settings group.</p>',
        ],
    ];
    $GLOBALS['smpi_test_wp_options'] = [];

    function get_field( string $field, $context = null ) {
        return $GLOBALS['smpi_test_acf_options'][ $field ] ?? null;
    }

    function get_option( string $field, $default = false ) {
        if ( 'site_icon' === $field ) {
            return 0;
        }
        return $GLOBALS['smpi_test_wp_options'][ $field ] ?? $default;
    }

    function home_url( string $path = '' ): string {
        return 'https://herforward.com' . ( '' === $path ? '' : '/' . ltrim( $path, '/' ) );
    }

    function trailingslashit( string $value ): string { return rtrim( $value, '/\\' ) . '/'; }
    function get_bloginfo( string $field ): string {
        return [
            'name'        => 'Her Forward',
            'description' => 'Women in business and leadership.',
            'language'    => 'en-US',
        ][ $field ] ?? '';
    }
    function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); }
    function sanitize_email( string $value ): string { return $value; }
    function absint( $value ): int { return abs( (int) $value ); }
    function get_site_icon_url( int $size = 512 ): string { return 'https://herforward.com/site-icon.png'; }
    function wp_get_attachment_image_src( int $attachment_id, string $size ) { return false; }
    function attachment_url_to_postid( string $url ): int { return 0; }
    function get_user_meta( int $user_id, string $field, bool $single = true ) { return ''; }
    function get_permalink( int $post_id = 0 ): string { return $post_id > 0 ? 'https://herforward.com/page-' . $post_id . '/' : ''; }
    function get_lastpostmodified( string $timezone = 'server' ): string { return '2026-07-28 20:43:10'; }
    function mysql2date( string $format, string $date, bool $translate = true ): string { return '2026-07-28T20:43:10+00:00'; }
    function current_time( string $type, bool $gmt = false ): string { return '2026-07-28T20:43:10+00:00'; }
    function post_type_exists( string $post_type ): bool { return false; }
    function get_posts( array $args = [] ): array { return []; }
}

namespace smp_publication_integration\Support {
    final class Settings {
        public static function get( string $key, $default = '' ) { return $default; }
        public static function page_assignment_id( string $type ): int { return 0; }
    }
}

namespace {
    require dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core/src/SchemaTools/SchemaGraph.php';
    require dirname( __DIR__ ) . '/src/Support/Fields.php';
    require dirname( __DIR__ ) . '/src/StructuredData/SchemaManager.php';

    use Hexa\PluginCore\SchemaTools\SchemaGraph;
    use smp_publication_integration\StructuredData\SchemaManager;
    use smp_publication_integration\Support\Fields;

    $fail = static function ( string $message ): void {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    };

    $home_url = 'https://herforward.com/';
    if ( $home_url !== Fields::option_url( 'website', $home_url ) ) {
        $fail( 'A foreign ACF website group did not fall back to the current site URL.' );
    }

    $GLOBALS['smpi_test_wp_options']['options_website'] = 'https://legacy.example.com/';
    if ( 'https://legacy.example.com/' !== Fields::option_url( 'website', $home_url ) ) {
        $fail( 'Typed resolution did not continue past a malformed ACF candidate to a valid source.' );
    }

    $GLOBALS['smpi_test_acf_options']['smpi_publication_website'] = 'https://publication.example.com/';
    if ( 'https://publication.example.com/' !== Fields::option_url( 'website', $home_url ) ) {
        $fail( 'The dedicated SMP publication URL did not take precedence over legacy sources.' );
    }

    unset( $GLOBALS['smpi_test_acf_options']['smpi_publication_website'] );
    unset( $GLOBALS['smpi_test_wp_options']['options_website'] );

    $manager = new SchemaManager();
    $method  = new \ReflectionMethod( $manager, 'publication_entity' );
    $entity  = $method->invoke( $manager );

    if ( $home_url !== ( $entity['url'] ?? '' ) ) {
        $fail( 'NewsMediaOrganization.url did not use the validated home URL fallback.' );
    }
    if ( [] !== SchemaGraph::validation_issues( $entity ) ) {
        $fail( 'The publication entity retained malformed schema URL values.' );
    }

    $home_schema = $manager->generate_home_schema_array();
    $collection  = [];
    $item_list   = [];
    foreach ( $home_schema['@graph'] ?? [] as $node ) {
        if ( 'CollectionPage' === ( $node['@type'] ?? '' ) ) {
            $collection = $node;
        }
        if ( 'ItemList' === ( $node['@type'] ?? '' ) ) {
            $item_list = $node;
        }
    }
    if ( '' === (string) ( $item_list['@id'] ?? '' ) || ( $collection['mainEntity']['@id'] ?? '' ) !== $item_list['@id'] ) {
        $fail( 'CollectionPage.mainEntity does not resolve to the homepage ItemList.' );
    }
    if ( isset( $collection['hasPart'] ) ) {
        $fail( 'CollectionPage.hasPart must not reference ItemList, which is not a CreativeWork.' );
    }

    echo "PASS: SMP rejects colliding ACF option groups and emits valid publication and homepage relationships.\n";
}
