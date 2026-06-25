<?php
namespace smp_publication_integration\Authorship;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class AuthorContext {
    private static array $stack = [];

    public static function current_id(): int {
        return empty( self::$stack ) ? 0 : (int) end( self::$stack );
    }

    public static function run( int $author_id, callable $callback ) {
        self::$stack[] = $author_id;
        try {
            return $callback();
        } finally {
            array_pop( self::$stack );
        }
    }

    public static function resolve(
        AuthorAssignmentRepository $repository,
        int $explicit_user_id = 0,
        int $explicit_post_id = 0,
        int $author_index = 0
    ): int {
        if ( $explicit_user_id > 0 && get_user_by( "id", $explicit_user_id ) ) {
            return $explicit_user_id;
        }

        $context_id = self::current_id();
        if ( $context_id > 0 ) {
            return $context_id;
        }

        if ( is_author() && $explicit_post_id <= 0 ) {
            $archive_author_id = AuthorQueryIntegration::current_archive_author_id();
            if ( $archive_author_id > 0 ) {
                return $archive_author_id;
            }
        }

        $post_id = $explicit_post_id;
        if ( $post_id <= 0 ) {
            $post = get_post();
            $post_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
        }
        if ( $post_id <= 0 ) {
            return 0;
        }

        $ids = $repository->ids_for_post( $post_id, true );
        $author_index = max( 0, $author_index );
        return isset( $ids[ $author_index ] ) ? (int) $ids[ $author_index ] : (int) ( $ids[0] ?? 0 );
    }
}
