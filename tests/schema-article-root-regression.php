<?php

declare(strict_types=1);

$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/StructuredData/SchemaManager.php' );
$fail = static function ( string $message ): never {
    fwrite( STDERR, "FAIL: {$message}\n" );
    exit( 1 );
};

$article_start = strpos( $source, '$article = SchemaNodes::article(' );
$article_end = strpos( $source, '$nodes = array_merge', $article_start ?: 0 );
if ( false === $article_start || false === $article_end ) {
    $fail( 'The article node builder could not be isolated.' );
}
$article_block = substr( $source, $article_start, $article_end - $article_start );

if ( ! str_contains( $article_block, '"mainEntityOfPage" => SchemaNodes::ref( $webpage_id )' ) ) {
    $fail( 'Article.mainEntityOfPage must reference the page node.' );
}
if ( ! str_contains( $article_block, '"isPartOf" => SchemaNodes::ref( SchemaIds::website() )' ) ) {
    $fail( 'Article.isPartOf must reference the shared WebSite node.' );
}
if ( ! str_contains( $article_block, '"isBasedOn" => $original' ) ) {
    $fail( 'Syndicated press releases must point back to their original.' );
}
if ( ! str_contains( $source, 'SchemaPage::register( self::SOURCE' ) ) {
    $fail( 'SMP must add its nodes to the shared Core schema page.' );
}
if ( preg_match( '/rank_math|remove_all_actions/', $source ) ) {
    $fail( 'Rank Math schema output is controlled only by the HWS Base Tools switch, never by SMP.' );
}
if ( ! str_contains( $source, 'NO_SCHEMA_POST_TYPES = [ "testimonial" ]' ) ) {
    $fail( 'Testimonials must get no schema.' );
}

echo "PASS: SMP builds its graph with Core, adds it to the shared page, and leaves Rank Math to Base Tools.\n";
