<?php

namespace smp_publication_integration\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Every code block and shortcode explanation SMP shows in wp-admin, drawn by Hexa WP
 * Core's CodeDisplay (color-coded code with a copy button) and ShortcodeBreakdown (the
 * shortcode, a copy button and each attribute with its default). Plain fallbacks keep
 * the screens readable while a site still loads an older Core.
 */
final class CodeViews {
    /** @param string $language html, css, php, js, json, shortcode or text. */
    public static function code( string $code, string $language, string $title = '' ): string {
        if ( class_exists( '\Hexa\PluginCore\WpAdminComponents\CodeDisplay' ) ) {
            return \Hexa\PluginCore\WpAdminComponents\CodeDisplay::render( $code, [ 'language' => $language, 'title' => $title ] );
        }
        return ( '' !== $title ? '<p><strong>' . esc_html( $title ) . '</strong></p>' : '' ) . '<pre>' . esc_html( $code ) . '</pre>';
    }

    /**
     * @param array{tag:string,title?:string,description?:string,aliases?:string[],
     *   attributes?:array<string,array{default?:string,description?:string,options?:string[]}>,
     *   examples?:array<int,array{label:string,shortcode:string}>} $args
     */
    public static function shortcode( array $args ): string {
        if ( class_exists( '\Hexa\PluginCore\ShortcodeRegistry\ShortcodeBreakdown' ) ) {
            return \Hexa\PluginCore\ShortcodeRegistry\ShortcodeBreakdown::render( $args );
        }
        $html = '<p><code>[' . esc_html( trim( (string) $args['tag'], '[]' ) ) . ']</code></p>';
        if ( ! empty( $args['description'] ) ) {
            $html .= '<p>' . esc_html( (string) $args['description'] ) . '</p>';
        }
        $items = '';
        foreach ( (array) ( $args['attributes'] ?? [] ) as $name => $attribute ) {
            $default = (string) ( $attribute['default'] ?? '' );
            $items  .= '<li><code>' . esc_html( (string) $name ) . '</code> ' . esc_html( (string) ( $attribute['description'] ?? '' ) ) . ( '' !== $default ? ' Default: ' . esc_html( $default ) . '.' : '' ) . '</li>';
        }
        return $html . ( '' !== $items ? '<ul>' . $items . '</ul>' : '' );
    }
}
