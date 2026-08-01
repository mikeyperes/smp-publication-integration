<?php

declare( strict_types=1 );

namespace {
    define( 'ABSPATH', __DIR__ . '/' );
    $GLOBALS['smpi_public_frontend'] = true;
    $GLOBALS['smpi_schema_flags'] = [];
    $GLOBALS['smpi_removed_rank_math_actions'] = [];
}

namespace smp_publication_integration\Support {
    final class RuntimeContext {
        public static function is_public_frontend(): bool {
            return (bool) $GLOBALS['smpi_public_frontend'];
        }
    }
}

namespace smp_publication_integration\StructuredData {
    function schema_flag( string $key ): bool {
        return (bool) ( $GLOBALS['smpi_schema_flags'][ $key ] ?? false );
    }

    function is_front_page(): bool {
        return schema_flag( 'front_page' );
    }

    function is_home(): bool {
        return schema_flag( 'home' );
    }

    function is_page(): bool {
        return schema_flag( 'page' );
    }

    function is_category(): bool {
        return schema_flag( 'category' );
    }

    function is_tag(): bool {
        return schema_flag( 'tag' );
    }

    function is_tax(): bool {
        return schema_flag( 'tax' );
    }

    function is_post_type_archive(): bool {
        return schema_flag( 'post_type_archive' );
    }

    function remove_all_actions( string $hook, int $priority ): void {
        $GLOBALS['smpi_removed_rank_math_actions'][] = [ $hook, $priority ];
    }
}

namespace {
    require dirname( __DIR__ ) . '/src/StructuredData/SchemaManager.php';

    use smp_publication_integration\StructuredData\SchemaManager;

    $manager = new SchemaManager();
    $rank_math_graph = [ 'WebPage' => [ '@type' => 'WebPage' ] ];

    $assert = static function ( bool $condition, string $label ): void {
        if ( ! $condition ) {
            fwrite( STDERR, "FAIL: {$label}\n" );
            exit( 1 );
        }
    };

    $run = static function ( array $flags, bool $public = true ) use ( $manager, $rank_math_graph ): array {
        $GLOBALS['smpi_schema_flags'] = $flags;
        $GLOBALS['smpi_public_frontend'] = $public;
        $GLOBALS['smpi_removed_rank_math_actions'] = [];
        $filtered = $manager->filter_rank_math_schema( $rank_math_graph );
        $manager->disable_rank_math_schema_output();
        return [ $filtered, $GLOBALS['smpi_removed_rank_math_actions'] ];
    };

    [ $filtered, $removed ] = $run( [ 'page' => true ], false );
    $assert( $rank_math_graph === $filtered && [] === $removed, 'Non-public requests remain untouched.' );

    foreach ( [ 'page', 'category', 'tag', 'tax', 'post_type_archive' ] as $context ) {
        [ $filtered, $removed ] = $run( [ $context => true ] );
        $assert( $rank_math_graph === $filtered, "Rank Math graph is preserved for {$context}." );
        $assert( [] === $removed, "Rank Math head output is preserved for {$context}." );
    }

    [ $filtered, $removed ] = $run( [ 'front_page' => true, 'page' => true ] );
    $assert( [] === $filtered, 'SMP still replaces Rank Math on the static front page.' );
    $assert( [ [ 'rank_math/head', 90 ] ] === $removed, 'Front-page Rank Math output is removed once.' );

    [ $filtered, $removed ] = $run( [] );
    $assert( [] === $filtered, 'The existing singular integration suppression boundary is retained.' );
    $assert( [ [ 'rank_math/head', 90 ] ] === $removed, 'Singular integration Rank Math output is removed once.' );

    echo "PASS: Rank Math schema is preserved only on non-owned page and archive contexts.\n";
}
