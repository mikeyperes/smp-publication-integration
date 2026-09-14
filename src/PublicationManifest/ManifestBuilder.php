<?php

declare( strict_types=1 );

namespace SMP\PublicationIntegration\PublicationManifest;

use smp_publication_integration\Config;

defined( 'ABSPATH' ) || exit;

final class ManifestBuilder {
    private HomepageCollector $homepage;
    private WordPressCollector $wordpress;
    private PayloadSanitizer $sanitizer;

    public function __construct(
        ?HomepageCollector $homepage = null,
        ?WordPressCollector $wordpress = null,
        ?PayloadSanitizer $sanitizer = null
    ) {
        $policy          = new TaxonomyPolicy();
        $this->homepage  = $homepage ?? new HomepageCollector( $policy );
        $this->wordpress = $wordpress ?? new WordPressCollector( $policy );
        $this->sanitizer = $sanitizer ?? new PayloadSanitizer();
    }

    /** @return array<string,mixed> */
    public function build(): array {
        $payload = [
            'api_version'             => 1,
            'plugin'                  => [
                'name'      => Config::$plugin_name,
                'slug'      => Config::$plugin_slug,
                'version'   => Config::VERSION,
                'namespace' => 'smpi/v1',
            ],
            'publication'             => $this->wordpress->publication_identity(),
            'homepage'                => $this->homepage->collect(),
            'taxonomies'              => $this->wordpress->taxonomy_catalog(),
            'recent_content'          => $this->wordpress->recent_content(),
            'authors'                 => $this->wordpress->public_authors(),
            'post_types'              => $this->wordpress->post_types(),
            'publishing_requirements' => $this->wordpress->publishing_requirements(),
            'media'                   => $this->wordpress->media_profile(),
            'seo'                     => $this->wordpress->seo_profile(),
            'schema'                  => $this->wordpress->schema_profile(),
            'delivery_capabilities'   => $this->wordpress->delivery_capabilities(),
            'meta'                    => [
                'generated_at' => gmdate( DATE_ATOM ),
                'cache_seconds' => ManifestEndpoint::cache_ttl(),
            ],
        ];

        $payload = $this->sanitizer->sanitize( $payload );
        $stable  = $payload;
        unset( $stable['meta']['generated_at'] );
        $encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $stable ) : json_encode( $stable );
        $payload['meta']['fingerprint'] = hash( 'sha256', is_string( $encoded ) ? $encoded : '' );

        return $payload;
    }
}
