<?php

declare( strict_types=1 );

namespace SMP\PublicationIntegration\PublicationManifest;

defined( 'ABSPATH' ) || exit;

final class PayloadSanitizer {
    private const FORBIDDEN_KEY_PARTS = [
        'email', 'password', 'passwd', 'secret', 'token', 'credential', 'cookie', 'session',
        'api_key', 'private_key', 'filesystem', 'file_path', 'server_path', 'plugin_inventory',
    ];

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function sanitize( array $payload ): array {
        $allowed_roots = [
            'api_version', 'plugin', 'publication', 'homepage', 'taxonomies', 'recent_content',
            'authors', 'post_types', 'publishing_requirements', 'media', 'seo', 'schema',
            'delivery_capabilities', 'meta',
        ];

        $clean = [];
        foreach ( $allowed_roots as $root ) {
            if ( array_key_exists( $root, $payload ) ) {
                $clean[ $root ] = $this->sanitize_value( $payload[ $root ] );
            }
        }
        return $clean;
    }

    private function sanitize_value( $value ) {
        if ( is_array( $value ) ) {
            $clean = [];
            foreach ( $value as $key => $child ) {
                if ( is_string( $key ) && $this->forbidden_key( $key ) ) {
                    continue;
                }
                $clean[ $key ] = $this->sanitize_value( $child );
            }
            return $clean;
        }

        if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
            return $value;
        }

        if ( ! is_scalar( $value ) ) {
            return null;
        }

        $text = wp_strip_all_tags( (string) $value );
        $text = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted]', $text ) ?? '';
        $text = preg_replace( '#(?<![A-Za-z0-9])(?:/home|/root|/Users|/var/www|[A-Z]:\\\\Users)[^\s]*#i', '[redacted]', $text ) ?? '';
        $text = preg_replace( '#(https?://)([^/@\s]+):([^/@\s]+)@#i', '$1', $text ) ?? '';
        return trim( $text );
    }

    private function forbidden_key( string $key ): bool {
        $key = strtolower( $key );
        foreach ( self::FORBIDDEN_KEY_PARTS as $part ) {
            if ( str_contains( $key, $part ) ) {
                return true;
            }
        }
        return false;
    }
}
