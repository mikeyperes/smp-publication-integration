<?php
namespace smp_publication_integration\Pages;

use smp_publication_integration\Support\PublicationFounders;
use smp_publication_integration\Support\Fields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * [smp_company field="name"]: one company fact for page templates.
 *
 * Company facts are owned by HWS Base Tools (hws_site_identity(), "Site
 * identity" on its Website & Primary Entity tab). SMP only reads them. When
 * Base Tools has no company saved, the older SMP publication fields are used.
 *
 * Templates adjust to the facts present: before/after on [smp_company] are
 * printed only with a value (after="." is skipped when the value already
 * ends in a period), and [smp_if] keeps a sentence only when what it needs
 * exists: field="<fact>", page="<page type>" (linked page) or
 * shortcode="<tag>" (registered by its plugin, so feature-off shows nothing).
 */
final class CompanyFacts {
    public const SHORTCODE = 'smp_company';

    /** [smp_if field="x"]...[/smp_if]: prints its text only when the fact, page or shortcode it names is there. */
    public const IF_SHORTCODE = 'smp_if';

    /** Base Tools key => older SMP field used when Base Tools is empty. */
    private const LEGACY = [
        'name'                  => 'legal_name',
        'legal_name'            => 'legal_name',
        'alternate_names'       => 'alternate_name',
        'headquarters_city'     => 'headquarters',
        'headquarters_url'      => 'headquarters_wikipedia_url',
        'email'                 => 'contact_email',
        'phone'                 => 'telephone',
        'mission'               => 'mission_statement',
    ];

    public function register(): void {
        add_action( 'init', static function (): void {
            add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
            add_shortcode( self::IF_SHORTCODE, [ self::class, 'if_shortcode' ] );
        } );
        add_filter( 'hws_base_tools_pages_in_use', [ self::class, 'pages_in_use' ] );
    }

    /**
     * Tells HWS Base Tools which SMP pages that need its facts are switched on
     * (linked to a page and applying to this site), so it can warn when a fact
     * is missing. Base Tools reads it on its own admin pages, after init.
     *
     * @param array<string,string> $pages key => label
     * @return array<string,string>
     */
    public static function pages_in_use( $pages ): array {
        $pages = is_array( $pages ) ? $pages : [];
        // Founding Date and the other company pages moved to HWS Base Tools, which checks their facts itself.
        return $pages;
    }

    public static function base_tools_available(): bool {
        return function_exists( 'hws_site_identity' );
    }

    public static function edit_url(): string {
        return function_exists( 'hws_site_identity_edit_url' ) ? (string) hws_site_identity_edit_url() : admin_url( 'options-general.php?page=hws-core-tools' );
    }

    /** Raw value: Base Tools company fact first, then the older SMP field, then a site default. */
    public static function value( string $field ) {
        $field = sanitize_key( $field );
        if ( 'founders' === $field ) {
            return PublicationFounders::user_ids();
        }
        if ( self::base_tools_available() ) {
            $value = hws_site_identity( 'company.' . $field );
            if ( ! self::empty_value( $value ) ) {
                return $value;
            }
        }
        if ( isset( self::LEGACY[ $field ] ) && class_exists( Fields::class ) ) {
            $value = Fields::option( self::LEGACY[ $field ] );
            if ( 'parent_name' !== $field && ! self::empty_value( $value ) ) {
                return $value;
            }
        }
        if ( in_array( $field, [ 'parent_name', 'parent_url' ], true ) && class_exists( Fields::class ) ) {
            $parent = Fields::option( 'parent_organization' );
            $value  = is_array( $parent ) ? ( 'parent_name' === $field ? ( $parent['name'] ?? '' ) : ( $parent['url'] ?? '' ) ) : '';
            if ( ! self::empty_value( $value ) ) {
                return $value;
            }
        }
        switch ( $field ) {
            case 'name':
                return get_bloginfo( 'name' );
            case 'url':
                return home_url( '/' );
            case 'email':
                return get_option( 'admin_email' );
            case 'description':
                return get_bloginfo( 'description' );
        }
        return '';
    }

    public static function shortcode( $atts ): string {
        $atts  = shortcode_atts( [ 'field' => 'name', 'format' => '', 'sep' => ', ', 'size' => 'medium', 'fallback' => '', 'before' => '', 'after' => '' ], (array) $atts, self::SHORTCODE );
        $field = sanitize_key( (string) $atts['field'] );
        $value = self::value( $field );
        if ( self::empty_value( $value ) ) {
            return esc_html( (string) $atts['fallback'] );
        }
        $html  = self::format_value( $field, $value, $atts );
        if ( '' === trim( wp_strip_all_tags( $html ) ) && 'logo' !== $field ) {
            return esc_html( (string) $atts['fallback'] );
        }
        $after = (string) $atts['after'];
        if ( '' !== $after && '.' === $after[0] && '.' === substr( rtrim( wp_strip_all_tags( $html ) ), -1 ) ) {
            $after = substr( $after, 1 );
        }
        return esc_html( (string) $atts['before'] ) . $html . esc_html( $after );
    }

    /** Whether a template condition holds: a company fact has a value, a page type is linked, a shortcode is registered. */
    public static function condition( array $atts ): bool {
        $atts = shortcode_atts( [ 'field' => '', 'page' => '', 'shortcode' => '', 'not' => '' ], $atts, self::IF_SHORTCODE );
        $ok   = true;
        if ( '' !== $atts['field'] ) {
            $ok = $ok && ! self::empty_value( self::value( (string) $atts['field'] ) );
        }
        if ( '' !== $atts['page'] ) {
            $ok = $ok && PageLinker::linked_id( sanitize_key( (string) $atts['page'] ) ) > 0;
        }
        if ( '' !== $atts['shortcode'] ) {
            $ok = $ok && shortcode_exists( sanitize_key( (string) $atts['shortcode'] ) );
        }
        return '' !== $atts['not'] ? ! $ok : $ok;
    }

    public static function if_shortcode( $atts, $content = '' ): string {
        return self::condition( (array) $atts ) ? do_shortcode( (string) $content ) : '';
    }

    private static function format_value( string $field, $value, array $atts ): string {
        $format = sanitize_key( (string) $atts['format'] );
        $sep    = (string) $atts['sep'];

        if ( 'founding_date' === $field && 'raw' !== $format ) {
            $time = strtotime( (string) $value );
            return $time ? esc_html( date_i18n( 'year' === $format ? 'Y' : get_option( 'date_format', 'F j, Y' ), $time ) ) : esc_html( (string) $value );
        }
        if ( 'logo' === $field ) {
            return 'url' === $format ? esc_url( (string) wp_get_attachment_image_url( absint( $value ), 'full' ) ) : (string) wp_get_attachment_image( absint( $value ), sanitize_key( (string) $atts['size'] ), false, [ 'class' => 'smpi-company-logo', 'loading' => 'lazy' ] );
        }
        if ( 'founders' === $field ) {
            $names = [];
            foreach ( (array) $value as $user_id ) {
                $user = get_user_by( 'id', absint( is_object( $user_id ) ? $user_id->ID : $user_id ) );
                if ( $user ) {
                    $names[] = 'text' === $format ? esc_html( $user->display_name ) : '<a href="' . esc_url( get_author_posts_url( $user->ID ) ) . '">' . esc_html( $user->display_name ) . '</a>';
                }
            }
            return implode( esc_html( $sep ), $names );
        }
        if ( 'profiles' === $field || 'alternate_names' === $field ) {
            $items = is_array( $value ) ? $value : preg_split( '/[\r\n]+|,\s*/', (string) $value );
            $items = array_values( array_filter( array_map( 'trim', array_map( 'strval', (array) $items ) ) ) );
            if ( 'profiles' === $field && 'text' !== $format ) {
                $links = array_map( static fn( string $url ): string => '<li><a href="' . esc_url( $url ) . '" rel="noopener" target="_blank">' . esc_html( (string) wp_parse_url( $url, PHP_URL_HOST ) ) . '</a></li>', $items );
                return '<ul class="smpi-company-profiles">' . implode( '', $links ) . '</ul>';
            }
            return esc_html( implode( $sep, $items ) );
        }
        if ( 'email' === $field ) {
            $email = sanitize_email( (string) $value );
            return 'text' === $format ? esc_html( $email ) : '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
        }
        if ( in_array( $field, [ 'url', 'parent_url', 'headquarters_url', 'founding_location_url' ], true ) ) {
            return 'link' === $format ? '<a href="' . esc_url( (string) $value ) . '">' . esc_html( (string) $value ) . '</a>' : esc_url( (string) $value );
        }
        if ( is_array( $value ) ) {
            return esc_html( implode( $sep, array_filter( array_map( 'strval', $value ) ) ) );
        }
        return in_array( $field, [ 'mission', 'description', 'headquarters_address' ], true ) ? wp_kses_post( nl2br( (string) $value ) ) : esc_html( wp_strip_all_tags( (string) $value ) );
    }

    private static function empty_value( $value ): bool {
        return null === $value || false === $value || '' === $value || [] === $value || ( is_array( $value ) && [] === array_filter( $value ) );
    }
}
