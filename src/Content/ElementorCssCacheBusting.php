<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class ElementorCssCacheBusting {
    public function register(): void {
        add_filter( "style_loader_src", [ $this, "bust_elementor_upload_css" ], 9999, 1 );
    }

    public function bust_elementor_upload_css( string $src ): string {
        if ( ! RuntimeContext::is_public_frontend() || ! Settings::bool( "elementor_css_cache_busting" ) || false === strpos( $src, "/wp-content/uploads/elementor/css/" ) ) {
            return $src;
        }
        $path = wp_parse_url( $src, PHP_URL_PATH );
        if ( ! is_string( $path ) || "" === $path ) {
            return $src;
        }
        $file = ABSPATH . ltrim( $path, "/" );
        return is_readable( $file ) ? add_query_arg( "mv_css", (string) filemtime( $file ), $src ) : $src;
    }

    public static function test_report(): array {
        $files = glob( ABSPATH . "wp-content/uploads/elementor/css/*.css" ) ?: [];
        rsort( $files );
        $sample = [];
        foreach ( array_slice( $files, 0, 5 ) as $file ) {
            $relative = str_replace( ABSPATH, "", $file );
            $sample[] = [
                "file" => $relative,
                "readable" => is_readable( $file ),
                "mtime" => is_readable( $file ) ? filemtime( $file ) : 0,
                "query" => is_readable( $file ) ? "mv_css=" . filemtime( $file ) : "",
            ];
        }
        return [
            "enabled" => Settings::bool( "elementor_css_cache_busting" ),
            "css_files" => count( $files ),
            "sample_files" => $sample,
        ];
    }
}
