<?php

declare(strict_types=1);

$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/StructuredData/SchemaManager.php' );
$fail = static function ( string $message ): never {
    fwrite( STDERR, "FAIL: {$message}\n" );
    exit( 1 );
};

$webpage_start = strpos( $source, '$webpage = $this->clean_schema' );
$article_start = strpos( $source, '$article = $this->clean_schema' );
$graph_start = strpos( $source, '$graph = array_values', $article_start ?: 0 );
if ( false === $webpage_start || false === $article_start || false === $graph_start ) {
    $fail( 'Single schema graph builders could not be isolated.' );
}

$webpage_block = substr( $source, $webpage_start, $article_start - $webpage_start );
$article_block = substr( $source, $article_start, $graph_start - $article_start );

if ( str_contains( $webpage_block, '"mainEntity" => $article_ref' ) ) {
    $fail( 'WebPage must not absorb the article through an internal mainEntity @id edge.' );
}
if ( ! str_contains( $article_block, '"mainEntityOfPage" => $permalink' ) ) {
    $fail( 'Article.mainEntityOfPage must use the canonical page URL.' );
}
if ( ! str_contains( $article_block, '"isPartOf" => $website_ref' ) ) {
    $fail( 'Article.isPartOf must reference WebSite so WebPage and NewsArticle remain independent validator items.' );
}
if ( str_contains( $article_block, '"mainEntityOfPage" => $webpage_ref' ) || str_contains( $article_block, '"isPartOf" => $webpage_ref' ) ) {
    $fail( 'Article must not retain an internal edge that folds it into WebPage.' );
}
if ( substr_count( $source, '$this->standalone_schema(' ) < 2 || ! str_contains( $source, 'SchemaGraph::standalone_nodes( $schema, self::LINKED_REFERENCE_PROPERTIES )' ) ) {
    $fail( 'Homepage and article graphs must use the shared Core identifier-linked standalone-node normalization path.' );
}
if ( ! str_contains( $source, '"publisher" => [ "@id", "@type" ]' ) || ! str_contains( $source, '"author" => [ "@id", "@type" ]' ) ) {
    $fail( 'Article publisher and author relationships must retain their independent top-level node identifiers.' );
}

echo "PASS: SMP graphs retain canonical identifier relationships and independently detectable Core-normalized nodes.\n";
