<?php

declare(strict_types=1);

namespace smp_publication_integration\Support {
    final class Settings {
        public static array $values = [
            'elementor_primary_category_enabled'         => true,
            'elementor_primary_category_exclude_default' => true,
        ];

        public static function bool( string $key ): bool {
            return ! empty( self::$values[ $key ] );
        }
    }
}

namespace {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );

    $GLOBALS['smpi_terms'] = [];
    $GLOBALS['smpi_meta']  = [];

    function get_the_ID(): int { return 42; }
    function get_the_terms( int $post_id, string $taxonomy ): array { return $GLOBALS['smpi_terms']; }
    function get_option( string $key, mixed $default = false ): mixed { return 'default_category' === $key ? 1 : $default; }
    function get_post_meta( int $post_id, string $key, bool $single = false ): mixed { return $GLOBALS['smpi_meta'][ $key ] ?? ''; }
    function is_wp_error( mixed $value ): bool { return false; }
    function absint( mixed $value ): int { return abs( (int) $value ); }

    require dirname( __DIR__ ) . '/src/Content/ElementorPrimaryCategory.php';

    use smp_publication_integration\Content\ElementorPrimaryCategory;
    use smp_publication_integration\Support\Settings;

    $term = static fn( int $id, string $name ): object => (object) [ 'term_id' => $id, 'name' => $name ];

    $GLOBALS['smpi_terms'] = [ $term( 1, 'Uncategorized' ), $term( 2, 'Finance' ), $term( 3, 'Business' ) ];
    $GLOBALS['smpi_meta']['rank_math_primary_category'] = 3;
    $selected = ElementorPrimaryCategory::term( 42 );
    if ( ! $selected || 3 !== (int) $selected->term_id ) {
        fwrite( STDERR, "FAIL: Rank Math primary category was not selected.\n" );
        exit( 1 );
    }

    $GLOBALS['smpi_meta']['rank_math_primary_category'] = 1;
    $GLOBALS['smpi_meta']['_yoast_wpseo_primary_category'] = 2;
    $selected = ElementorPrimaryCategory::term( 42 );
    if ( ! $selected || 2 !== (int) $selected->term_id ) {
        fwrite( STDERR, "FAIL: The excluded default category blocked a valid fallback primary category.\n" );
        exit( 1 );
    }

    $GLOBALS['smpi_terms'] = [ $term( 1, 'Uncategorized' ) ];
    if ( null !== ElementorPrimaryCategory::term( 42 ) ) {
        fwrite( STDERR, "FAIL: Uncategorized must render nothing when it is the only category.\n" );
        exit( 1 );
    }

    Settings::$values['elementor_primary_category_exclude_default'] = false;
    $selected = ElementorPrimaryCategory::term( 42 );
    if ( ! $selected || 1 !== (int) $selected->term_id ) {
        fwrite( STDERR, "FAIL: The default-category exclusion setting could not be disabled.\n" );
        exit( 1 );
    }

    Settings::$values['elementor_primary_category_enabled'] = false;
    if ( null !== ElementorPrimaryCategory::term( 42 ) ) {
        fwrite( STDERR, "FAIL: Disabled Elementor primary-category output must be empty.\n" );
        exit( 1 );
    }

    echo "PASS: Elementor primary category selects one category and excludes Uncategorized by default.\n";
}
