<?php

namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Fields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The publication's founding date section, shown by [smpi_founding_date]: when it was
 * founded, where, and the founding story. The values are the publication's own fields
 * (smpi_founding_date, smpi_founding_location, smpi_founding_location_url,
 * smpi_founding_date_extended), the same ones the structured data reads, so they are
 * edited in one place. Off by default; while off the shortcode prints nothing.
 * Markup and classes: docs/founding-date.md.
 */
final class FoundingDate {
    public const OPTION    = 'smpi_founding_date_section';
    public const SHORTCODE = 'smpi_founding_date';

    public function register(): void {
        add_action( 'init', [ $this, 'register_shortcode' ] );
    }

    public function register_shortcode(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
    }

    /** @return array{enabled:bool,heading:string} */
    public static function settings(): array {
        $saved = get_option( self::OPTION, [] );
        $saved = is_array( $saved ) ? $saved : [];
        return [
            'enabled' => ! empty( $saved['enabled'] ),
            'heading' => isset( $saved['heading'] ) ? sanitize_text_field( (string) $saved['heading'] ) : 'Founded',
        ];
    }

    public static function update_settings( array $changes ): void {
        update_option( self::OPTION, array_merge( self::settings(), $changes ), true );
    }

    /** @return array{date:string,place:string,place_url:string,story:string} */
    public static function values(): array {
        return [
            'date'      => trim( (string) Fields::option( 'founding_date' ) ),
            'place'     => trim( (string) Fields::option( 'founding_location' ) ),
            'place_url' => Fields::option_url( 'founding_location_url' ),
            'story'     => trim( (string) Fields::option( 'founding_date_extended' ) ),
        ];
    }

    /** "2019", "2019-04" or "2019-04-12" as the date is known; '' when it is not valid. */
    public static function normalize_date( string $date ): string {
        $date = trim( $date );
        if ( preg_match( '/^(\d{4})(?:-(\d{1,2})(?:-(\d{1,2}))?)?$/', $date, $m ) ) {
            if ( isset( $m[3] ) ) {
                return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] ) : '';
            }
            if ( isset( $m[2] ) ) {
                return ( (int) $m[2] >= 1 && (int) $m[2] <= 12 ) ? sprintf( '%04d-%02d', $m[1], $m[2] ) : '';
            }
            return $m[1];
        }
        $time = strtotime( $date );
        return $time ? gmdate( 'Y-m-d', $time ) : '';
    }

    /** The date as readers see it, "April 12, 2019" by default (date_format="F Y" or "Y" for less). */
    public static function display_date( string $date, string $format = '' ): string {
        $date = self::normalize_date( $date );
        if ( '' === $date ) {
            return '';
        }
        if ( 4 === strlen( $date ) ) {
            return $date;
        }
        $fmt = 7 === strlen( $date ) ? 'F Y' : ( '' !== $format ? $format : 'F j, Y' );
        return date_i18n( $fmt, strtotime( 7 === strlen( $date ) ? $date . '-01' : $date ) );
    }

    public static function render( $atts = [] ): string {
        $settings = self::settings();
        if ( ! $settings['enabled'] ) {
            return '';
        }
        $atts   = shortcode_atts( [ 'section_title' => $settings['heading'], 'section_tag' => 'h2', 'show' => 'date,place,story', 'date_format' => '', 'style' => 'default' ], (array) $atts, self::SHORTCODE );
        $show   = array_map( 'trim', explode( ',', strtolower( (string) $atts['show'] ) ) );
        $values = self::values();
        $date   = self::display_date( $values['date'], (string) $atts['date_format'] );
        $rows   = '';
        if ( in_array( 'date', $show, true ) && '' !== $date ) {
            $rows .= '<div class="smpi-founding__row smpi-founding__row--date"><dt class="smpi-founding__label">Founded</dt><dd class="smpi-founding__value"><time datetime="' . esc_attr( self::normalize_date( $values['date'] ) ) . '">' . esc_html( $date ) . '</time></dd></div>';
        }
        if ( in_array( 'place', $show, true ) && '' !== $values['place'] ) {
            $place = '' !== $values['place_url'] ? '<a href="' . esc_url( $values['place_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $values['place'] ) . '</a>' : esc_html( $values['place'] );
            $rows .= '<div class="smpi-founding__row smpi-founding__row--place"><dt class="smpi-founding__label">Founded in</dt><dd class="smpi-founding__value">' . $place . '</dd></div>';
        }
        $story = in_array( 'story', $show, true ) && '' !== $values['story'] ? '<div class="smpi-founding__story">' . wp_kses_post( wpautop( $values['story'] ) ) . '</div>' : '';
        if ( '' === $rows && '' === $story ) {
            return '';
        }
        $heading = trim( (string) $atts['section_title'] );
        $tag     = in_array( (string) $atts['section_tag'], [ 'h2', 'h3', 'h4', 'p' ], true ) ? (string) $atts['section_tag'] : 'h2';
        $head    = ( '' !== $heading && 'none' !== strtolower( $heading ) ) ? '<' . $tag . ' class="smpi-founding__heading">' . esc_html( $heading ) . '</' . $tag . '>' : '';
        $plain   = 'none' === (string) $atts['style'];
        $css     = $plain ? '' : '<style>' . self::css() . '</style>';
        return $css . '<section class="smpi-founding' . ( $plain ? '' : ' smpi-founding--default' ) . '">' . $head . ( '' !== $rows ? '<dl class="smpi-founding__facts">' . $rows . '</dl>' : '' ) . $story . '</section>';
    }

    public static function css(): string {
        $b = '.smpi-founding.smpi-founding--default';
        return "$b{margin:0 0 32px}"
            . "$b .smpi-founding__heading{margin:0 0 16px}"
            . "$b .smpi-founding__facts{display:flex;flex-wrap:wrap;gap:16px 40px;margin:0 0 20px;padding:0}"
            . "$b .smpi-founding__row{margin:0}"
            . "$b .smpi-founding__label{font-size:13px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;opacity:.7;margin:0 0 4px}"
            . "$b .smpi-founding__value{font-size:20px;margin:0}"
            . "$b .smpi-founding__value a{color:inherit;text-decoration:underline}"
            . "$b .smpi-founding__story p{margin:0 0 1em}";
    }

    public static function structure(): string {
        return "<section class=\"smpi-founding smpi-founding--default\">\n  <h2 class=\"smpi-founding__heading\">Founded</h2>\n  <dl class=\"smpi-founding__facts\">\n    <div class=\"smpi-founding__row smpi-founding__row--date\">\n      <dt class=\"smpi-founding__label\">Founded</dt>\n      <dd class=\"smpi-founding__value\"><time datetime=\"2019-04-12\">April 12, 2019</time></dd>\n    </div>\n    <div class=\"smpi-founding__row smpi-founding__row--place\">\n      <dt class=\"smpi-founding__label\">Founded in</dt>\n      <dd class=\"smpi-founding__value\"><a href=\"{place link}\">New York, New York</a></dd>\n    </div>\n  </dl>\n  <div class=\"smpi-founding__story\"><p>{founding story}</p></div>\n</section>";
    }
}
