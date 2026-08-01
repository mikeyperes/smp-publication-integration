<?php

namespace smp_publication_integration\Content;

use smp_publication_integration\Config;
use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * External, idempotent DOM behavior shared by direct and persistent-player
 * navigation. Target-specific state is expressed through SSR body classes and
 * inert markup, never fetched executable code.
 */
final class PublicDomRuntime {
    public const HANDLE = 'smpi-public-dom';

    public function register(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 30 );
        add_filter( 'body_class', [ $this, 'body_classes' ] );
    }

    public function enqueue(): void {
        if ( ! RuntimeContext::is_public_dom_context() || ! self::needs_runtime() ) {
            return;
        }

        $plugin_file = dirname( __DIR__, 2 ) . '/smp-publication-integration.php';
        wp_enqueue_script(
            self::HANDLE,
            plugin_dir_url( $plugin_file ) . 'assets/frontend/public-dom.js',
            [],
            Config::VERSION,
            true
        );
        wp_script_add_data( self::HANDLE, 'strategy', 'defer' );
    }

    /** @param string[] $classes @return string[] */
    public function body_classes( array $classes ): array {
        if ( ! RuntimeContext::is_public_dom_context() ) {
            return $classes;
        }

        if ( Settings::bool( 'breadcrumbs_enabled' ) && Breadcrumbs::should_render() ) {
            $classes[] = 'smpi-runtime-breadcrumbs';
        }

        if ( is_singular( [ 'post', 'press-release' ] ) && ArticleStyles::article_markup_enabled() ) {
            $classes[] = 'smpi-runtime-article-markup';
            if ( Settings::bool( 'article_heading_styles_enabled' ) ) {
                $classes[] = 'smpi-runtime-article-headings';
            }
            if ( Settings::bool( 'article_drop_cap_enabled' ) ) {
                $classes[] = 'smpi-runtime-article-dropcap';
            }
            if ( Settings::bool( 'article_numbered_lists_enabled' ) ) {
                $style = ArticleStyles::normalize_article_numbered_list_style(
                    (string) Settings::get( 'article_numbered_list_style', 'nlist01' )
                );
                if ( 'none' !== $style ) {
                    $classes[] = 'smpi-runtime-article-numbered-lists';
                    $classes[] = 'smpi-runtime-numbered-list-' . $style;
                }
            }
        }

        return array_values( array_unique( $classes ) );
    }

    public static function needs_runtime(): bool {
        return Settings::bool( 'breadcrumbs_enabled' )
            || Settings::bool( 'article_heading_styles_enabled' )
            || Settings::bool( 'article_drop_cap_enabled' )
            || Settings::bool( 'article_numbered_lists_enabled' )
            || Settings::bool( 'inline_photo_treatments_enabled' )
            || Settings::bool( 'table_of_contents_enabled' );
    }
}
