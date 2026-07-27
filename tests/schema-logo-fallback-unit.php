<?php

declare(strict_types=1);

define( "ABSPATH", dirname( __DIR__ ) . "/" );

$GLOBALS["smpi_test_site_icon_id"] = 55;
$GLOBALS["smpi_test_image_sources"] = [
    55 => [ "https://example.com/site-icon.png", 512, 512, false ],
    77 => [ "https://example.com/local-logo.png", 640, 320, false ],
];

function absint( mixed $value ): int { return abs( (int) $value ); }
function esc_url_raw( string $value ): string { return $value; }
function get_option( string $key, mixed $default = false ): mixed {
    return "site_icon" === $key ? $GLOBALS["smpi_test_site_icon_id"] : $default;
}
function get_site_icon_url( int $size = 512 ): string { return "https://example.com/generated-site-icon-{$size}.png"; }
function wp_get_attachment_image_src( int $attachment_id, string $size ): array|false {
    return $GLOBALS["smpi_test_image_sources"][ $attachment_id ] ?? false;
}
function attachment_url_to_postid( string $url ): int {
    return "https://example.com/local-logo.png" === $url ? 77 : 0;
}

require dirname( __DIR__ ) . "/src/StructuredData/SchemaManager.php";

$manager = new smp_publication_integration\StructuredData\SchemaManager();
$method = new ReflectionMethod( $manager, "normalize_logo" );

$site_icon = $method->invoke( $manager, null );
if (
    "https://example.com/site-icon.png" !== ( $site_icon["url"] ?? "" )
    || 512 !== ( $site_icon["width"] ?? 0 )
    || 512 !== ( $site_icon["height"] ?? 0 )
) {
    fwrite( STDERR, "FAIL: Site-icon fallback did not retain its attachment dimensions.\n" );
    exit( 1 );
}

$local_url = $method->invoke( $manager, "https://example.com/local-logo.png" );
if ( 640 !== ( $local_url["width"] ?? 0 ) || 320 !== ( $local_url["height"] ?? 0 ) ) {
    fwrite( STDERR, "FAIL: Local logo URL did not resolve its attachment dimensions.\n" );
    exit( 1 );
}

$GLOBALS["smpi_test_image_sources"][55] = false;
$generated_icon = $method->invoke( $manager, null );
if ( 512 !== ( $generated_icon["width"] ?? 0 ) || 512 !== ( $generated_icon["height"] ?? 0 ) ) {
    fwrite( STDERR, "FAIL: Generated 512px site-icon fallback did not expose its requested dimensions.\n" );
    exit( 1 );
}

echo "PASS: Publication logo fallbacks include valid image dimensions.\n";
