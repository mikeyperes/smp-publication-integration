<?php

declare(strict_types=1);

const TARGET_URL = 'https://herforward.com/personal-journey-that-inspired-marcia-neumanns-coaching-philosophy/';
const VALIDATOR_URL = 'https://validator.schema.org/validate';

$page = curl_init( TARGET_URL );
curl_setopt_array(
    $page,
    [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'SMP schema display-root probe',
    ]
);
$html = curl_exec( $page );
$page_status = (int) curl_getinfo( $page, CURLINFO_RESPONSE_CODE );
$page_error = curl_error( $page );
curl_close( $page );

if ( ! is_string( $html ) || ! preg_match( '/<script[^>]+id=["\']smpi-schema-jsonld["\'][^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
    fwrite( STDERR, sprintf( "Unable to read the SMP JSON-LD graph (HTTP %d, %s).\n", $page_status, $page_error ) );
    exit( 1 );
}

$schema = json_decode( html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5 ), true );
$graph = is_array( $schema['@graph'] ?? null ) ? $schema['@graph'] : [];
if ( [] === $graph ) {
    fwrite( STDERR, "The SMP graph is empty.\n" );
    exit( 1 );
}

$script = static fn( array $document ): string => '<script type="application/ld+json">'
    . json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
    . '</script>';

$split_scripts = '';
$array_documents = [];
foreach ( $graph as $node ) {
    $document = [ '@context' => 'https://schema.org' ] + $node;
    $split_scripts .= $script( $document );
    $array_documents[] = $document;
}

$markup_prefix = '<!doctype html><html><head><title>Schema display probe</title></head><body>'
    . '<h1>Schema display probe</h1><article><div class="entry-content"><p>Schema display probe paragraph.</p></div></article>';
$markup_suffix = '</body></html>';
$variants = [
    'single_graph' => $markup_prefix . $script( $schema ) . $markup_suffix,
    'split_scripts' => $markup_prefix . $split_scripts . $markup_suffix,
    'top_level_array' => $markup_prefix . $script( $array_documents ) . $markup_suffix,
];

$results = [];
foreach ( $variants as $name => $markup ) {
    $request = curl_init( VALIDATOR_URL );
    curl_setopt_array(
        $request,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query( [ 'html' => $markup ] ),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [ 'Content-Type: application/x-www-form-urlencoded' ],
            CURLOPT_ENCODING       => '',
        ]
    );
    $body = (string) curl_exec( $request );
    $status = (int) curl_getinfo( $request, CURLINFO_RESPONSE_CODE );
    $error = curl_error( $request );
    curl_close( $request );

    $decoded = json_decode( (string) preg_replace( '/^\)\]\}\x27\s*/', '', $body ), true );
    $groups = [];
    foreach ( (array) ( $decoded['tripleGroups'] ?? [] ) as $group ) {
        $groups[] = [
            'type'     => '' !== (string) ( $group['type'] ?? '' ) ? (string) $group['type'] : 'Unspecified Type',
            'errors'   => (int) ( $group['numErrors'] ?? 0 ),
            'warnings' => (int) ( $group['numWarnings'] ?? 0 ),
        ];
    }

    $results[ $name ] = [
        'http_status'  => $status,
        'request_error'=> $error,
        'objects'      => isset( $decoded['numObjects'] ) ? (int) $decoded['numObjects'] : null,
        'errors'       => isset( $decoded['totalNumErrors'] ) ? (int) $decoded['totalNumErrors'] : null,
        'warnings'     => isset( $decoded['totalNumWarnings'] ) ? (int) $decoded['totalNumWarnings'] : null,
        'groups'       => $groups,
    ];
    sleep( 2 );
}

$output = __DIR__ . '/schema-display-root-probe.json';
file_put_contents( $output, json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL );
echo json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
echo 'PROOF=' . $output . PHP_EOL;

