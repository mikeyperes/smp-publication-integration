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

$mutated_graph = static function ( array $source_graph, callable $mutate ): array {
    $indexes = [];
    foreach ( $source_graph as $index => $node ) {
        $indexes[ (string) ( $node['@type'] ?? '' ) ] = $index;
    }
    $mutate( $source_graph, $indexes );
    return $source_graph;
};
$graph_markup = static fn( array $nodes ): string => $markup_prefix
    . $script( [ '@context' => 'https://schema.org', '@graph' => $nodes ] )
    . $markup_suffix;

$variants['article_without_site_parent'] = $graph_markup(
    $mutated_graph(
        $graph,
        static function ( array &$nodes, array $indexes ): void {
            unset( $nodes[ $indexes['NewsArticle'] ]['isPartOf'] );
        }
    )
);
$variants['article_without_site_or_org_refs'] = $graph_markup(
    $mutated_graph(
        $graph,
        static function ( array &$nodes, array $indexes ): void {
            unset(
                $nodes[ $indexes['NewsArticle'] ]['isPartOf'],
                $nodes[ $indexes['NewsArticle'] ]['publisher'],
                $nodes[ $indexes['NewsArticle'] ]['copyrightHolder']
            );
        }
    )
);
$variants['article_with_anonymous_publisher'] = $graph_markup(
    $mutated_graph(
        $graph,
        static function ( array &$nodes, array $indexes ): void {
            $org = $nodes[ $indexes['NewsMediaOrganization'] ];
            $publisher = [
                '@type' => 'Organization',
                'name'  => $org['name'] ?? '',
                'url'   => $org['url'] ?? '',
            ];
            unset( $nodes[ $indexes['NewsArticle'] ]['isPartOf'] );
            $nodes[ $indexes['NewsArticle'] ]['publisher'] = $publisher;
            $nodes[ $indexes['NewsArticle'] ]['copyrightHolder'] = $publisher;
        }
    )
);
$variants['article_with_anonymous_dependencies'] = $graph_markup(
    $mutated_graph(
        $graph,
        static function ( array &$nodes, array $indexes ): void {
            $org = $nodes[ $indexes['NewsMediaOrganization'] ];
            $person = $nodes[ $indexes['Person'] ];
            $image = $nodes[ $indexes['ImageObject'] ];
            $publisher = [ '@type' => 'Organization', 'name' => $org['name'] ?? '', 'url' => $org['url'] ?? '' ];
            unset( $nodes[ $indexes['NewsArticle'] ]['isPartOf'] );
            $nodes[ $indexes['NewsArticle'] ]['publisher'] = $publisher;
            $nodes[ $indexes['NewsArticle'] ]['copyrightHolder'] = $publisher;
            $nodes[ $indexes['NewsArticle'] ]['author'] = [ '@type' => 'Person', 'name' => $person['name'] ?? '', 'url' => $person['url'] ?? '' ];
            $nodes[ $indexes['NewsArticle'] ]['image'] = $image['url'] ?? '';
        }
    )
);
$variants['all_links_as_url_values'] = $graph_markup(
    $mutated_graph(
        $graph,
        static function ( array &$nodes, array $indexes ): void {
            $org_url = (string) ( $nodes[ $indexes['NewsMediaOrganization'] ]['url'] ?? '' );
            $website_url = (string) ( $nodes[ $indexes['WebSite'] ]['url'] ?? '' );
            $person_url = (string) ( $nodes[ $indexes['Person'] ]['url'] ?? '' );
            $image_url = (string) ( $nodes[ $indexes['ImageObject'] ]['url'] ?? '' );
            $breadcrumb_url = (string) ( $nodes[ $indexes['BreadcrumbList'] ]['@id'] ?? '' );
            $nodes[ $indexes['WebSite'] ]['publisher'] = $org_url;
            $nodes[ $indexes['WebSite'] ]['about'] = $org_url;
            $nodes[ $indexes['WebPage'] ]['isPartOf'] = $website_url;
            $nodes[ $indexes['WebPage'] ]['about'] = $org_url;
            $nodes[ $indexes['WebPage'] ]['publisher'] = $org_url;
            $nodes[ $indexes['WebPage'] ]['primaryImageOfPage'] = $image_url;
            $nodes[ $indexes['WebPage'] ]['breadcrumb'] = $breadcrumb_url;
            $nodes[ $indexes['NewsArticle'] ]['isPartOf'] = $website_url;
            $nodes[ $indexes['NewsArticle'] ]['author'] = $person_url;
            $nodes[ $indexes['NewsArticle'] ]['publisher'] = $org_url;
            $nodes[ $indexes['NewsArticle'] ]['image'] = $image_url;
            $nodes[ $indexes['NewsArticle'] ]['copyrightHolder'] = $org_url;
        }
    )
);
$variants['all_graph_nodes_unlinked'] = $graph_markup(
    $mutated_graph(
        $graph,
        static function ( array &$nodes, array $indexes ): void {
            unset( $nodes[ $indexes['WebSite'] ]['publisher'], $nodes[ $indexes['WebSite'] ]['about'] );
            unset(
                $nodes[ $indexes['WebPage'] ]['isPartOf'],
                $nodes[ $indexes['WebPage'] ]['about'],
                $nodes[ $indexes['WebPage'] ]['publisher'],
                $nodes[ $indexes['WebPage'] ]['primaryImageOfPage'],
                $nodes[ $indexes['WebPage'] ]['breadcrumb']
            );
            unset(
                $nodes[ $indexes['NewsArticle'] ]['isPartOf'],
                $nodes[ $indexes['NewsArticle'] ]['author'],
                $nodes[ $indexes['NewsArticle'] ]['publisher'],
                $nodes[ $indexes['NewsArticle'] ]['image'],
                $nodes[ $indexes['NewsArticle'] ]['copyrightHolder']
            );
        }
    )
);

$scalarize_refs = static function ( $value ) use ( &$scalarize_refs ) {
    if ( ! is_array( $value ) ) {
        return $value;
    }
    if ( 1 === count( $value ) && isset( $value['@id'] ) && is_string( $value['@id'] ) ) {
        return $value['@id'];
    }
    foreach ( $value as $key => $item ) {
        $value[ $key ] = $scalarize_refs( $item );
    }
    return $value;
};
$variants['all_links_as_identifier_urls'] = $graph_markup( $scalarize_refs( $graph ) );

$variants['identifier_urls_with_typed_article_dependencies'] = $graph_markup(
    $mutated_graph(
        $scalarize_refs( $graph ),
        static function ( array &$nodes, array $indexes ): void {
            $org = $nodes[ $indexes['NewsMediaOrganization'] ];
            $person = $nodes[ $indexes['Person'] ];
            $image = $nodes[ $indexes['ImageObject'] ];
            $logo = is_array( $org['logo'] ?? null ) ? $org['logo'] : [];
            unset( $logo['@id'] );
            $publisher = [
                '@type' => 'NewsMediaOrganization',
                'name'  => $org['name'] ?? '',
                'url'   => $org['url'] ?? '',
                'logo'  => $logo,
            ];
            $author = [
                '@type' => 'Person',
                'name'  => $person['name'] ?? '',
                'url'   => $person['url'] ?? '',
            ];
            $primary_image = [
                '@type' => 'ImageObject',
                'url'   => $image['url'] ?? '',
                'width' => $image['width'] ?? null,
                'height'=> $image['height'] ?? null,
            ];
            $nodes[ $indexes['WebSite'] ]['publisher'] = $publisher;
            $nodes[ $indexes['WebPage'] ]['publisher'] = $publisher;
            $nodes[ $indexes['WebPage'] ]['primaryImageOfPage'] = $primary_image;
            $nodes[ $indexes['NewsArticle'] ]['publisher'] = $publisher;
            $nodes[ $indexes['NewsArticle'] ]['copyrightHolder'] = $publisher;
            $nodes[ $indexes['NewsArticle'] ]['author'] = $author;
            $nodes[ $indexes['NewsArticle'] ]['image'] = $image['url'] ?? '';
        }
    )
);

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
