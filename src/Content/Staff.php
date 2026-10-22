<?php

namespace smp_publication_integration\Content;

use smp_publication_integration\Authorship\AuthorFieldResolver;
use smp_publication_integration\Content\People\PeopleCards;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Staff writer membership belongs to HWS Base Tools; this list stores only its presentation. */
final class Staff {
    public const OPTION = 'smpi_staff';
    public const SHORTCODE = 'smpi_staff';
    public const PAGE_TYPE = 'staff';
    public const META_MEMBER = 'staff_writer';
    public const FIELD_MEMBER = 'field_hws_additional_staff_writer';
    public const META_TITLE = 'smpi_staff_title';
    public const PATHS = [ 'about/executive-leadership-team/meet-the-staff', 'about/meet-the-staff', 'meet-the-staff', 'staff', 'about/staff' ];

    public function register(): void {
        add_action( 'init', [ $this, 'register_shortcode' ] );
    }

    public function register_shortcode(): void {
        add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
    }

    /** @return array{style:string,order:int[]} */
    public static function settings(): array {
        $saved = get_option( self::OPTION, [] );
        $saved = is_array( $saved ) ? $saved : [];
        $style = (string) ( $saved['style'] ?? 'cards' );
        return [
            'style' => isset( PeopleCards::STYLES[ $style ] ) ? $style : 'cards',
            'order' => array_values( array_unique( array_filter( array_map( 'absint', (array) ( $saved['order'] ?? [] ) ) ) ) ),
        ];
    }

    public static function update_settings( array $changes ): void {
        update_option( self::OPTION, array_merge( self::settings(), $changes ), true );
    }

    public static function title( int $id ): string {
        return trim( (string) get_user_meta( $id, self::META_TITLE, true ) );
    }

    /** Query the existing switch on every read so profile changes appear immediately. No role restrictions. */
    public static function member_ids(): array {
        $marked = array_map( 'intval', get_users( [ 'meta_key' => self::META_MEMBER, 'meta_value' => '1', 'fields' => 'ID', 'orderby' => 'display_name', 'order' => 'ASC' ] ) );
        $order = array_values( array_intersect( self::settings()['order'], $marked ) );
        return array_values( array_unique( array_merge( $order, $marked ) ) );
    }

    public static function set_member( int $id, bool $member, ?string $title = null ): void {
        // Use the field owner to keep its value/cache and field-key reference in step.
        if ( \Hexa\PluginCore\Fields\Field::available() ) {
            \Hexa\PluginCore\Fields\Field::update( self::META_MEMBER, $member ? 1 : 0, 'user_' . $id );
        } else {
            update_user_meta( $id, self::META_MEMBER, $member ? '1' : '0' );
            update_user_meta( $id, '_' . self::META_MEMBER, self::FIELD_MEMBER );
        }
        if ( null !== $title ) {
            update_user_meta( $id, self::META_TITLE, sanitize_text_field( $title ) );
        }
    }

    public static function render( $atts = [] ): string {
        $atts = shortcode_atts( PeopleCards::ATTRIBUTES + [
            'with_photo' => Settings::bool( 'author_listing_hide_without_featured_image' ) ? '1' : '0',
            'with_articles' => Settings::bool( 'author_listing_hide_without_articles' ) ? '1' : '0',
        ], (array) $atts, self::SHORTCODE );
        $ids = self::member_ids();
        // Apply the same filters to explicit IDs too, before the shared renderer limits the list.
        if ( '' !== trim( (string) $atts['ids'] ) ) {
            $ids = array_values( array_filter( array_map( 'absint', explode( ',', (string) $atts['ids'] ) ) ) );
            $atts['ids'] = '';
        }
        if ( in_array( (string) $atts['with_photo'], [ '1', 'yes', 'true' ], true ) ) {
            $fields = new AuthorFieldResolver();
            $ids = array_values( array_filter( $ids, static fn( int $id ): bool => $fields->explicit_image_attachment_id( $id ) > 0 || '' !== $fields->explicit_image_url( $id, 'medium' ) ) );
        }
        if ( in_array( (string) $atts['with_articles'], [ '1', 'yes', 'true' ], true ) ) {
            $ids = array_values( array_filter( $ids, static fn( int $id ): bool => count_user_posts( $id, PublicationContentTypes::article_post_types(), true ) > 0 ) );
        }
        return PeopleCards::render( $ids, $atts, [ 'block' => 'smpi-staff', 'item' => 'smpi-staff-member', 'style' => self::settings()['style'], 'title' => [ self::class, 'title' ] ] );
    }

    public static function default_template(): string {
        return "<!-- wp:paragraph -->\n<p>Meet the staff of [smp_publication_field field=legal_name format=text fallback=site_name].</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[" . self::SHORTCODE . " columns=\"4\"]\n<!-- /wp:shortcode -->";
    }
}
