<?php
namespace smp_publication_integration\Pages;

use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Default page templates live as files in src/Pages/templates/<type>.html.
 * They are generic: names, dates and places come from shortcodes, never typed in.
 * A site may save its own edited copy (stored in smpi_settings page_templates).
 */
final class PageTemplates {
    public static function default_template( string $key ): string {
        $key  = PageTypes::canonical( $key );
        $file = __DIR__ . '/templates/' . $key . '.html';
        // Headquarters and Logo pages built before HWS Base Tools owned them keep rendering their template.
        if ( ! ( PageTypes::exists( $key ) || PageTypes::base_tools_owned( $key ) ) || ! is_readable( $file ) ) {
            return '';
        }
        return trim( (string) file_get_contents( $file ) ) . "\n";
    }

    /** @return array<string,string> */
    public static function defaults(): array {
        $out = [];
        foreach ( array_keys( PageTypes::all() ) as $key ) {
            $out[ $key ] = self::default_template( $key );
        }
        return $out;
    }

    /** Old stored values that were only the placeholder shortcode count as "not edited". */
    public static function is_placeholder( string $template ): bool {
        $template = trim( $template );
        return '' === $template || 1 === preg_match( '/^\[smp_(publication_)?page_template type=["\']?[a-z_]+["\']?\]$/', $template );
    }

    public static function stored( string $key ): string {
        $key      = PageTypes::canonical( $key );
        $settings = Settings::all();
        $stored   = (string) ( $settings['page_templates'][ $key ] ?? '' );
        return self::is_placeholder( $stored ) ? '' : $stored;
    }

    public static function is_customized( string $key ): bool {
        return '' !== self::stored( $key );
    }

    public static function get( string $key ): string {
        $stored = self::stored( $key );
        return '' !== $stored ? $stored : self::default_template( $key );
    }

    public static function save( string $key, string $template ): void {
        $key = PageTypes::canonical( $key );
        if ( ! PageTypes::exists( $key ) ) {
            return;
        }
        $settings = Settings::all();
        $template = wp_kses_post( $template );
        // Saving the unchanged default keeps the page on the plugin's default.
        $settings['page_templates'][ $key ] = trim( $template ) === trim( self::default_template( $key ) ) ? '' : $template;
        update_option( Settings::OPTION, $settings, false );
        Settings::invalidate_cache();
        Settings::log( 'Page template saved: ' . $key );
    }

    public static function reset( string $key ): void {
        self::save( $key, self::default_template( $key ) );
    }

    /** Front end: [smp_publication_page_template type=x] prints the template, shortcodes run. */
    public static function render( string $key ): string {
        $template = self::get( $key );
        if ( '' === $template ) {
            return '';
        }
        $html = do_shortcode( shortcode_unautop( wpautop( $template ) ) );
        // A paragraph whose only sentence was left out (its fact or page is missing) disappears with it.
        return (string) preg_replace( '#<p>(?:\s|&nbsp;|<br\s*/?>)*</p>\s*#i', '', $html );
    }
}
