<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$read = static fn( string $path ): string => (string) file_get_contents( $root . '/' . $path );
$fail = static function ( string $message ): never {
    fwrite( STDERR, "FAIL: {$message}\n" );
    exit( 1 );
};

$registry = $read( 'src/Content/PublicationContentTypes.php' );
$bootstrap = $read( 'src/Bootstrap/Plugin.php' );
$schema = $read( 'src/StructuredData/SchemaManager.php' );
$fields = $read( 'src/Support/Fields.php' );
$shortcodes = $read( 'src/Content/Shortcodes.php' );

foreach ( [ 'ContentTypeRegistry', 'AcfFieldGroupRegistry', "'knowledge-base'", "'resources'", 'active_article_post_types' ] as $needle ) {
    if ( ! str_contains( $registry, $needle ) ) {
        $fail( "Publication content registry is missing {$needle}." );
    }
}

if ( ! str_contains( $bootstrap, 'PublicationContentTypes::content_types()' ) || ! str_contains( $bootstrap, 'PublicationContentTypes::acf_groups()' ) ) {
    $fail( 'SMP does not boot both Core-managed CPT and ACF registries.' );
}

foreach ( [ 'CoreSchemaInjector', 'SchemaDocumentRenderer', 'SchemaGraph::clean', 'FaqSourceResolver', 'FaqSetManager' ] as $needle ) {
    if ( ! str_contains( $schema, $needle ) ) {
        $fail( "SMP schema is not delegated to Core {$needle}." );
    }
}

if ( ! str_contains( $fields, 'CanonicalEntityResolver::resolve()' ) || ! str_contains( $fields, 'CanonicalEntityResolver::field' ) ) {
    $fail( 'Publication fields do not consume the optional HWS canonical entity.' );
}

if ( ! str_contains( $shortcodes, 'FaqSetManager' ) || ! str_contains( $shortcodes, 'renderItems' ) ) {
    $fail( 'FAQ display does not use the shared Core renderer.' );
}

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
    if ( 'php' === strtolower( $file->getExtension() ) && str_contains( (string) file_get_contents( $file->getPathname() ), 'imported-news' ) ) {
        $fail( 'Legacy imported-news reference remains in ' . $file->getPathname() );
    }
}

echo "PASS: SMP CPT, ACF, entity, FAQ, and schema paths use Hexa WP Core with no imported-news runtime references.\n";
