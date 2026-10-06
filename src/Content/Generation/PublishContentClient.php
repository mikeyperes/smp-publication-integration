<?php
namespace smp_publication_integration\Content\Generation;

use Hexa\PluginCore\CredentialVault\CredentialStore;
use smp_publication_integration\Config;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * The one connection to Publish Scale's content generation API.
 *
 * Publish writes excerpts, summaries and FAQs as background jobs: start() asks
 * for one and gets a job id back at once; jobs() reports the state of several
 * jobs, with the finished value once a job is completed. Which AI connection
 * Publish uses (subscription or API) is decided on Publish, not here.
 */
final class PublishContentClient {
    public const CREDENTIAL_SLUG = "smp-publication-integration";
    public const CREDENTIAL_KEY = "content_generation_api_key";
    public const DEFAULT_API_BASE = "https://publish.scalemypublication.com/api/smp-content-generation/v1";
    private const START_TIMEOUT = 20;
    private const READ_TIMEOUT = 10;

    /** @return array|\WP_Error */
    public function status() {
        return $this->request( "/status", [ "site_url" => home_url(), "plugin_version" => Config::VERSION ], self::READ_TIMEOUT );
    }

    /**
     * Starts one generation job for a post.
     *
     * @return array{job_id:string,target:string,status:string,mode:string,attempts:int,data:mixed,error:?string}|\WP_Error
     */
    public function start( \WP_Post $post, string $target ) {
        $result = $this->request( "/generate", $this->payload_for_post( $post, $target ), self::START_TIMEOUT );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( empty( $result["job_id"] ) || ! is_string( $result["job_id"] ) ) {
            return new \WP_Error( "smpi_content_api_response", "Publish did not return a job id." );
        }
        return $result;
    }

    /**
     * Reports jobs by id. Jobs Publish does not know (or that belong to
     * another site) are simply absent from the result.
     *
     * @param string[] $job_ids
     * @return array<string,array>|\WP_Error Keyed by job id.
     */
    public function jobs( array $job_ids ) {
        $job_ids = array_values( array_unique( array_filter( array_map( "strval", $job_ids ) ) ) );
        if ( empty( $job_ids ) ) {
            return [];
        }
        $result = $this->request( "/jobs", [ "job_ids" => array_slice( $job_ids, 0, 20 ) ], self::READ_TIMEOUT );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $jobs = [];
        foreach ( (array) ( $result["jobs"] ?? [] ) as $job ) {
            if ( is_array( $job ) && ! empty( $job["job_id"] ) ) {
                $jobs[ strtolower( (string) $job["job_id"] ) ] = $job;
            }
        }
        return $jobs;
    }

    public function api_key(): string {
        $key = ( new CredentialStore() )->get( self::CREDENTIAL_SLUG, self::CREDENTIAL_KEY );
        if ( is_string( $key ) && "" !== trim( $key ) ) {
            return trim( $key );
        }
        return $this->tts_api_key();
    }

    /** Falls back to the text-to-speech plugin's working key (it stores it encrypted). */
    public function tts_api_key(): string {
        $key = apply_filters( "smp_tts_site_api_key", "" );
        return is_string( $key ) ? trim( $key ) : "";
    }

    /** @return array|\WP_Error */
    private function request( string $path, array $payload, int $timeout ) {
        $api_key = $this->api_key();
        if ( "" === $api_key ) {
            return new \WP_Error( "smpi_content_api_key_missing", "No SMP content generation API key is configured." );
        }
        $base = rtrim( (string) Settings::get( "content_generation_api_base", self::DEFAULT_API_BASE ), "/" );
        $response = wp_remote_post( $base . $path, [
            "timeout" => $timeout,
            "headers" => [
                "Accept" => "application/json",
                "Content-Type" => "application/json",
                "X-SMP-Content-Key" => $api_key,
            ],
            "body" => wp_json_encode( $payload ),
        ] );
        if ( is_wp_error( $response ) ) {
            return new \WP_Error( "smpi_content_api_unreachable", "Could not reach Publish: " . $response->get_error_message() );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        $body = is_array( $body ) ? $body : [];
        if ( $code < 200 || $code >= 300 ) {
            $message = isset( $body["message"] ) && is_string( $body["message"] ) && "" !== $body["message"] ? $body["message"] : "Publish answered HTTP " . $code . ".";
            return new \WP_Error( "smpi_content_api_http", $message, [ "status" => $code ] );
        }
        return $body;
    }

    private function payload_for_post( \WP_Post $post, string $target ): array {
        return [
            "target" => $target,
            "site_url" => home_url(),
            "post_id" => $post->ID,
            "title" => get_the_title( $post ),
            "permalink" => get_permalink( $post ),
            "content_text" => $this->content_text( (string) $post->post_content ),
        ];
    }

    private function content_text( string $content ): string {
        $content = strip_shortcodes( $content );
        $content = preg_replace( "/<script\b[^>]*>.*?<\/script>/is", " ", $content );
        $content = preg_replace( "/<style\b[^>]*>.*?<\/style>/is", " ", $content );
        $content = preg_replace( "/<\/(p|h[1-6]|li|blockquote)>/i", "\n", $content );
        $content = preg_replace( "/<br\s*\/?>/i", "\n", $content );
        $content = wp_strip_all_tags( $content );
        return trim( preg_replace( "/\n{3,}/", "\n\n", html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, get_bloginfo( "charset" ) ) ) );
    }
}
