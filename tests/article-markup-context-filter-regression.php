<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$article = (string) file_get_contents( $root . "/src/Content/ArticleStyles.php" );
$runtime = (string) file_get_contents( $root . "/src/Content/PublicDomRuntime.php" );

$checks = [
    "Article markup exposes a context filter for non-editorial singular templates." => str_contains( $article, 'apply_filters(' )
        && str_contains( $article, '"smpi_article_markup_enabled"' ),
    "The context filter retains saved feature state as its first value." => str_contains( $article, '"smpi_article_markup_enabled",' )
        && str_contains( $article, '$enabled,' ),
    "The context filter receives the queried object and post type." => str_contains( $article, '(int) get_queried_object_id()' )
        && str_contains( $article, '(string) get_post_type()' ),
    "Server decoration and the external runtime share the filtered gate." => str_contains( $article, 'self::article_markup_enabled()' )
        && str_contains( $runtime, 'ArticleStyles::article_markup_enabled()' ),
    "The context-aware gate is public for the runtime body-state contract." => str_contains( $article, 'public static function article_markup_enabled()' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: Article markup can be disabled for native non-editorial singular templates.\n";
