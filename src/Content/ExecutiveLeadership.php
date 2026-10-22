<?php

namespace smp_publication_integration\Content;

use smp_publication_integration\Content\People\PeopleCards;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The publication's Executive Leadership Team: users marked on their profile
 * (or picked on Settings, SMP, Executive Leadership), each with a title used
 * only on the leadership page, shown by [smpi_executive_leadership].
 *
 * Off by default. While off, the profile fields are not registered and the
 * shortcode prints nothing. Markup and classes: docs/executive-leadership.md.
 */
final class ExecutiveLeadership {
    public const OPTION       = 'smpi_executive_leadership';
    public const SHORTCODE    = 'smpi_executive_leadership';
    public const META_MEMBER  = 'smpi_executive_leadership';
    public const META_TITLE   = 'smpi_executive_leadership_title';
    public const FIELD_MEMBER = 'field_smpi_executive_leadership';
    public const FIELD_TITLE  = 'field_smpi_executive_leadership_title';
    public const PAGE_TYPE    = 'executive_team';
    public const STYLES       = PeopleCards::STYLES;

    public function register(): void {
        add_action( 'init', [ $this, 'register_shortcode' ] );
        add_action( 'init', [ $this, 'register_profile_fields' ], 5 );
        add_action( 'profile_update', [ self::class, 'remember_order' ], 20 );
    }

    /** @return array{enabled:bool,style:string,order:int[]} */
    public static function settings(): array {
        $saved = get_option( self::OPTION, [] );
        $saved = is_array( $saved ) ? $saved : [];
        $style = (string) ( $saved['style'] ?? 'cards' );

        return [
            'enabled' => ! empty( $saved['enabled'] ),
            'style'   => isset( self::STYLES[ $style ] ) ? $style : 'cards',
            'order'   => array_values( array_filter( array_map( 'absint', (array) ( $saved['order'] ?? [] ) ) ) ),
        ];
    }

    public static function enabled(): bool {
        return self::settings()['enabled'];
    }

    public static function update_settings( array $changes ): void {
        $settings = array_merge( self::settings(), $changes );
        update_option( self::OPTION, $settings, true );
    }

    public function register_shortcode(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
    }

    /** The profile toggle and title, only while the feature is on. */
    public function register_profile_fields(): void {
        if ( ! self::enabled() || ! class_exists( '\Hexa\PluginCore\Fields\FieldGroups' ) ) {
            return;
        }

        \Hexa\PluginCore\Fields\FieldGroups::add(
            [
                'key'      => 'group_smpi_executive_leadership',
                'title'    => 'Executive Leadership',
                'fields'   => [
                    [
                        'key'          => self::FIELD_MEMBER,
                        'label'        => 'Executive leadership',
                        'name'         => self::META_MEMBER,
                        'type'         => 'true_false',
                        'message'      => 'Is part of the executive leadership team',
                        'ui'           => 1,
                        'default_value' => 0,
                        'instructions' => 'Shows this person on the Executive Leadership Team page.',
                    ],
                    [
                        'key'               => self::FIELD_TITLE,
                        'label'             => 'Executive Leadership Title',
                        'name'              => self::META_TITLE,
                        'type'              => 'text',
                        'instructions'      => 'Shown under the name on the Executive Leadership Team page only, for example "Editor-in-Chief".',
                        'conditional_logic' => [ [ [ 'field' => self::FIELD_MEMBER, 'operator' => '==', 'value' => '1' ] ] ],
                    ],
                ],
                'location' => [ [ [ 'param' => 'user_form', 'operator' => '==', 'value' => 'all' ] ] ],
                'menu_order' => 5,
            ]
        );
    }

    public static function is_member( int $user_id ): bool {
        return '1' === (string) get_user_meta( $user_id, self::META_MEMBER, true );
    }

    public static function title( int $user_id ): string {
        return trim( (string) get_user_meta( $user_id, self::META_TITLE, true ) );
    }

    /** Marks or unmarks a user; the same values the profile toggle and title save. */
    public static function set_member( int $user_id, bool $member, ?string $title = null ): void {
        update_user_meta( $user_id, self::META_MEMBER, $member ? '1' : '0' );
        update_user_meta( $user_id, '_' . self::META_MEMBER, self::FIELD_MEMBER );
        if ( null !== $title ) {
            update_user_meta( $user_id, self::META_TITLE, sanitize_text_field( $title ) );
            update_user_meta( $user_id, '_' . self::META_TITLE, self::FIELD_TITLE );
        }
    }

    /** Leadership members in the saved order; people marked on their profile without a place go last, by name. */
    public static function member_ids(): array {
        $marked = get_users( [ 'meta_key' => self::META_MEMBER, 'meta_value' => '1', 'fields' => 'ID', 'orderby' => 'display_name' ] );
        $marked = array_map( 'intval', $marked );
        $order  = array_values( array_intersect( self::settings()['order'], $marked ) );

        return array_values( array_unique( array_merge( $order, $marked ) ) );
    }

    /** Keeps a person turned on from their profile at the end of the saved order. */
    public static function remember_order( $user_id ): void {
        $user_id = (int) $user_id;
        $order   = self::settings()['order'];
        if ( self::is_member( $user_id ) && ! in_array( $user_id, $order, true ) ) {
            $order[] = $user_id;
        } elseif ( ! self::is_member( $user_id ) ) {
            $order = array_values( array_diff( $order, [ $user_id ] ) );
        }
        self::update_settings( [ 'order' => $order ] );
    }

    public static function render( $atts = [] ): string {
        if ( ! self::enabled() ) {
            return '';
        }
        $atts = shortcode_atts( PeopleCards::ATTRIBUTES, (array) $atts, self::SHORTCODE );
        return PeopleCards::render( self::member_ids(), $atts, [ 'block' => 'smpi-leadership', 'item' => 'smpi-leader', 'style' => self::settings()['style'], 'title' => [ self::class, 'title' ] ] );
    }

    public static function css(): string {
        return PeopleCards::css( 'smpi-leadership', 'smpi-leader' );
    }

    /** The default page content: a short line and the leadership list. */
    public static function default_template(): string {
        return "<!-- wp:paragraph -->\n<p>Meet the people who lead [smp_publication_field field=legal_name format=text fallback=site_name].</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[" . self::SHORTCODE . "]\n<!-- /wp:shortcode -->";
    }

    /** The leadership page: the assigned one, an existing page at a usual address, or a new one with the default template. */
    public static function ensure_page(): array {
        $found = PeopleCards::find_page( self::PAGE_TYPE, [ 'about/executive-leadership-team', 'executive-leadership-team', 'leadership-team', 'executive-team', 'leadership' ] );
        if ( $found ) {
            return [ 'id' => $found, 'created' => false ];
        }
        return PeopleCards::create_page( self::PAGE_TYPE, 'Executive Leadership Team', 'executive-leadership-team', self::default_template() ) + [ 'created' => true ];
    }

    public static function page_has_list( int $page_id ): bool {
        return PeopleCards::page_has_shortcode( $page_id, self::SHORTCODE );
    }

    public static function apply_default_template( int $page_id ): void {
        PeopleCards::apply_template( $page_id, self::default_template() );
    }
}
