<?php
namespace smp_publication_integration\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** The read-only "Original content at HWS Base Tools" notice, drawn by Hexa WP Core's SourceBanner. */
final class SourceNotice {
    public static function html(): string {
        $args = [ 'owner' => 'HWS Base Tools', 'edit_url' => CompanyFacts::edit_url() ];
        if ( class_exists( '\Hexa\PluginCore\WpAdminComponents\SourceBanner' ) ) {
            ob_start();
            $out = \Hexa\PluginCore\WpAdminComponents\SourceBanner::render( $args );
            $echoed = (string) ob_get_clean();
            return is_string( $out ) && '' !== $out ? $out : $echoed;
        }
        return '<div class="notice notice-info inline"><p>Original content at HWS Base Tools. <a href="' . esc_url( $args['edit_url'] ) . '">Click here to edit.</a></p></div>';
    }
}
