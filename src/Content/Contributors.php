<?php

namespace smp_publication_integration\Content;

use smp_publication_integration\Authorship\AuthorFieldResolver;
use smp_publication_integration\Content\People\PeopleCards;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The publication's contributors, shown by [smpi_contributors]: everyone with a
 * contributor role (Contributor by default), plus people added from other roles,
 * minus people removed, in the order set on Settings, SMP, Contributors.
 * Markup and classes: docs/contributors.md.
 */
final class Contributors {
    public const OPTION    = 'smpi_contributors';
    public const SHORTCODE = 'smpi_contributors';
    public const PAGE_TYPE = 'contributors';
    public const PATHS     = [ 'about/executive-leadership-team/meet-our-contributors', 'about/meet-our-contributors', 'meet-our-contributors', 'contributors', 'about/contributors' ];

    public function register(): void {
        add_filter( 'hws_base_tools_people_directory', [ PeopleCards::class, 'search_directory' ], 10, 2 );
        add_action( 'init', [ $this, 'register_shortcode' ] );
    }

    public function register_shortcode(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
    }

    /** @return array{roles:string[],include:int[],exclude:int[],order:int[],style:string} */
    public static function settings(): array {
        $saved = get_option( self::OPTION, [] );
        $saved = is_array( $saved ) ? $saved : [];
        $ids   = static fn( $value ): array => array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) );
        $roles = array_values( array_filter( array_map( 'sanitize_key', (array) ( $saved['roles'] ?? [ 'contributor' ] ) ) ) );
        $style = (string) ( $saved['style'] ?? 'cards' );

        return [
            'roles'   => $roles ? $roles : [ 'contributor' ],
            'include' => $ids( $saved['include'] ?? [] ),
            'exclude' => $ids( $saved['exclude'] ?? [] ),
            'order'   => $ids( $saved['order'] ?? [] ),
            'style'   => isset( PeopleCards::STYLES[ $style ] ) ? $style : 'cards',
        ];
    }

    public static function update_settings( array $changes ): void {
        update_option( self::OPTION, array_merge( self::settings(), $changes ), true );
    }

    /** Users who have one of the contributor roles, by name. */
    public static function role_member_ids( ?array $roles = null ): array {
        $roles = $roles ?? self::settings()['roles'];
        return array_map( 'intval', get_users( [ 'role__in' => $roles, 'fields' => 'ID', 'orderby' => 'display_name', 'order' => 'ASC' ] ) );
    }

    /** The list in order: the saved order first, then anyone new, by name. */
    public static function member_ids(): array {
        $settings = self::settings();
        $members  = array_values( array_diff( array_unique( array_merge( self::role_member_ids( $settings['roles'] ), $settings['include'] ) ), $settings['exclude'] ) );
        $members  = array_values( array_filter( $members, static fn( int $id ): bool => (bool) get_userdata( $id ) ) );
        $ordered  = array_values( array_intersect( $settings['order'], $members ) );
        return array_values( array_unique( array_merge( $ordered, $members ) ) );
    }

    /** Role members taken off the list. */
    public static function removed_ids(): array {
        return array_values( array_intersect( self::settings()['exclude'], self::role_member_ids() ) );
    }

    /**
     * Saves the list as shown on the tab: people outside the roles are added,
     * role members missing from it are removed, and the order is kept.
     */
    public static function save_list( array $ids ): void {
        $ids   = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
        $role  = self::role_member_ids();
        self::update_settings(
            [
                'include' => array_values( array_diff( $ids, $role ) ),
                'exclude' => array_values( array_diff( $role, $ids ) ),
                'order'   => $ids,
            ]
        );
    }

    public static function render( $atts = [] ): string {
        // By default the list follows the site's author-listing settings (Publication: hide authors without a photo or without articles).
        $atts = shortcode_atts(
            PeopleCards::ATTRIBUTES + [
                'roles'         => '',
                'with_articles' => Settings::bool( 'author_listing_hide_without_articles' ) ? '1' : '0',
                'with_photo'    => Settings::bool( 'author_listing_hide_without_featured_image' ) ? '1' : '0',
            ],
            (array) $atts,
            self::SHORTCODE
        );
        $ids  = self::member_ids();
        if ( '' !== trim( (string) $atts['roles'] ) ) {
            // A different set of roles for this one list; added and removed people still apply.
            $settings = self::settings();
            $roles    = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) $atts['roles'] ) ) ) );
            $ids      = array_values( array_diff( array_unique( array_merge( self::role_member_ids( $roles ), $settings['include'] ) ), $settings['exclude'] ) );
        }
        if ( in_array( (string) $atts['with_photo'], [ '1', 'yes', 'true' ], true ) ) {
            $fields = new AuthorFieldResolver();
            $ids    = array_values( array_filter( $ids, static fn( int $id ): bool => $fields->explicit_image_attachment_id( $id ) > 0 || '' !== $fields->explicit_image_url( $id, 'medium' ) ) );
        }
        if ( in_array( (string) $atts['with_articles'], [ '1', 'yes', 'true' ], true ) ) {
            $ids = array_values( array_filter( $ids, static fn( int $id ): bool => count_user_posts( $id, PublicationContentTypes::article_post_types(), true ) > 0 ) );
        }
        return PeopleCards::render( $ids, $atts, [ 'block' => 'smpi-contributors', 'item' => 'smpi-contributor', 'style' => self::settings()['style'], 'title' => in_array( (string) $atts['layout'], [ 'head', 'rows' ], true ) ? static fn( int $id ): string => ( new AuthorFieldResolver() )->value( $id, 'title' ) : null ] );
    }

    public static function css(): string {
        return PeopleCards::css( 'smpi-contributors', 'smpi-contributor' );
    }

    public static function default_template(): string {
        return "<!-- wp:paragraph -->\n<p>The experts and storytellers who write for [smp_publication_field field=legal_name format=text fallback=site_name].</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[" . self::SHORTCODE . "]\n<!-- /wp:shortcode -->";
    }
}
