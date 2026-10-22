<?php

namespace smp_publication_integration\Content;

use smp_publication_integration\Content\People\PeopleCards;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The publication's founders, shown by [smpi_founders]: user accounts picked on
 * Settings, SMP, Founders, each with an optional title, under a heading that defaults
 * to "Founders" (it can read "Executives" or anything else). Photo, name and bio come
 * from each user's profile. Off by default; while off the shortcode prints nothing.
 * Markup and classes: docs/founders.md.
 */
final class Founders {
    public const OPTION    = 'smpi_founders';
    public const SHORTCODE = 'smpi_founders';

    public function register(): void {
        add_action( 'init', [ $this, 'register_shortcode' ] );
    }

    public function register_shortcode(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
    }

    /** @return array{enabled:bool,heading:string,ids:int[],titles:array<int,string>,style:string} */
    public static function settings(): array {
        $saved  = get_option( self::OPTION, [] );
        $saved  = is_array( $saved ) ? $saved : [];
        $style  = (string) ( $saved['style'] ?? 'cards' );
        $titles = [];
        foreach ( (array) ( $saved['titles'] ?? [] ) as $id => $title ) {
            $titles[ absint( $id ) ] = sanitize_text_field( (string) $title );
        }
        return [
            'enabled' => ! empty( $saved['enabled'] ),
            'heading' => isset( $saved['heading'] ) ? sanitize_text_field( (string) $saved['heading'] ) : 'Founders',
            'ids'     => array_values( array_unique( array_filter( array_map( 'absint', (array) ( $saved['ids'] ?? [] ) ) ) ) ),
            'titles'  => $titles,
            'style'   => isset( PeopleCards::STYLES[ $style ] ) ? $style : 'cards',
        ];
    }

    public static function update_settings( array $changes ): void {
        update_option( self::OPTION, array_merge( self::settings(), $changes ), true );
    }

    public static function enabled(): bool {
        return self::settings()['enabled'];
    }

    public static function member_ids(): array {
        return array_values( array_filter( self::settings()['ids'], static fn( int $id ): bool => (bool) get_userdata( $id ) ) );
    }

    public static function title( int $id ): string {
        return (string) ( self::settings()['titles'][ $id ] ?? '' );
    }

    public static function render( $atts = [] ): string {
        if ( ! self::enabled() ) {
            return '';
        }
        $settings = self::settings();
        // section_title, not heading: "heading" is already the tag used for each person's name.
        $atts     = shortcode_atts( PeopleCards::ATTRIBUTES + [ 'section_title' => $settings['heading'], 'section_tag' => 'h2' ], (array) $atts, self::SHORTCODE );
        $list     = PeopleCards::render( self::member_ids(), $atts, [ 'block' => 'smpi-founders', 'item' => 'smpi-founder', 'style' => $settings['style'], 'title' => [ self::class, 'title' ] ] );
        if ( '' === $list ) {
            return '';
        }
        $heading = trim( (string) $atts['section_title'] );
        $tag     = in_array( (string) $atts['section_tag'], [ 'h2', 'h3', 'h4', 'p' ], true ) ? (string) $atts['section_tag'] : 'h2';
        // section_title="none" (or empty) leaves the title out, for a page that already has its own.
        $head    = ( '' !== $heading && 'none' !== strtolower( $heading ) ) ? '<' . $tag . ' class="smpi-founders__heading">' . esc_html( $heading ) . '</' . $tag . '>' : '';
        return '<section class="smpi-founders-section">' . $head . $list . '</section>';
    }
}
