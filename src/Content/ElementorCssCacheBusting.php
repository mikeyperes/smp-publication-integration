<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class ElementorCssCacheBusting {
    private const LITESPEED_ELEMENTOR_PURGE_ALL = "LiteSpeed\\Thirdparty\\Elementor::regenerate_litespeed_cache";
    private const LOOP_CSS_CLASS = "ElementorPro\\Modules\\LoopBuilder\\Files\\Css\\Loop";

    public function register(): void {
        add_filter( "style_loader_src", [ $this, "bust_elementor_upload_css" ], 9999, 1 );
        add_action( "elementor/core/files/clear_cache", [ $this, "rebuild_css_files" ], 1 );
    }

    /**
     * Elementor deletes every generated CSS file when its cache is cleared (each Site Settings
     * save does this), while cached pages still link the deleted files. Rebuilding the files in
     * the same request keeps cached pages styled, so LiteSpeed's built-in listener that purges
     * the whole page cache on this event is removed instead of emptying every cached page.
     */
    public function rebuild_css_files(): void {
        $files_manager = class_exists( "\\Elementor\\Plugin" ) && isset( \Elementor\Plugin::$instance->files_manager ) ? \Elementor\Plugin::$instance->files_manager : null;
        if ( ! is_object( $files_manager ) || ! method_exists( $files_manager, "generate_css" ) ) {
            return;
        }
        remove_action( "elementor/core/files/clear_cache", self::LITESPEED_ELEMENTOR_PURGE_ALL );
        $files_manager->generate_css();
        $this->rebuild_loop_css_files();
    }

    private function rebuild_loop_css_files(): void {
        $loop_css_class = self::LOOP_CSS_CLASS;
        if ( ! class_exists( $loop_css_class ) ) {
            return;
        }
        $template_ids = get_posts( [
            "post_type" => "elementor_library",
            "post_status" => "publish",
            "numberposts" => -1,
            "fields" => "ids",
            "meta_key" => "_elementor_template_type",
            "meta_value" => "loop-item",
        ] );
        foreach ( $template_ids as $template_id ) {
            $loop_css_class::create( (int) $template_id )->update();
        }
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
