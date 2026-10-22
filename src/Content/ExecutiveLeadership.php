<?php

namespace smp_publication_integration\Content;

use smp_publication_integration\Authorship\AuthorFieldResolver;
use smp_publication_integration\Support\PageStructure;

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
    public const STYLES       = [
        'cards' => 'Cards: photo, name, title and short bio in a grid (default)',
        'list'  => 'List: one person per row with a small photo',
        'none'  => 'No styling: plain markup for the theme or Elementor to style',
    ];
    public const PARTS        = [ 'photo', 'title', 'bio', 'link' ];

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
        $atts = shortcode_atts(
            [
                'style'      => self::settings()['style'],
                'columns'    => 3,
                'ids'        => '',
                'limit'      => 0,
                'hide'       => '',
                'link'       => 'profile',
                'bio_words'  => 30,
                'photo_size' => 'medium',
                'heading'    => 'h3',
            ],
            (array) $atts,
            self::SHORTCODE
        );

        $ids = '' !== trim( (string) $atts['ids'] )
            ? array_values( array_filter( array_map( 'absint', explode( ',', (string) $atts['ids'] ) ) ) )
            : self::member_ids();
        if ( (int) $atts['limit'] > 0 ) {
            $ids = array_slice( $ids, 0, (int) $atts['limit'] );
        }
        $users = array_values( array_filter( array_map( 'get_userdata', $ids ) ) );
        if ( ! $users ) {
            return '';
        }

        $style   = isset( self::STYLES[ (string) $atts['style'] ] ) ? (string) $atts['style'] : 'cards';
        $hide    = array_intersect( array_map( 'trim', explode( ',', strtolower( (string) $atts['hide'] ) ) ), self::PARTS );
        $show    = static fn( string $part ): bool => ! in_array( $part, $hide, true );
        $heading = in_array( (string) $atts['heading'], [ 'h2', 'h3', 'h4', 'p' ], true ) ? (string) $atts['heading'] : 'h3';
        $link    = 'none' !== (string) $atts['link'];
        $fields  = new AuthorFieldResolver();
        $columns = max( 1, min( 6, (int) $atts['columns'] ) );

        $people = '';
        foreach ( $users as $user ) {
            $id    = (int) $user->ID;
            $name  = (string) $user->display_name;
            $url   = get_author_posts_url( $id );
            $title = self::title( $id );
            $bio   = wp_trim_words( wp_strip_all_tags( (string) get_the_author_meta( 'description', $id ) ), max( 0, (int) $atts['bio_words'] ), '…' );
            $photo = $fields->image_url( $id, (string) $atts['photo_size'] );

            $people .= '<article class="smpi-leader smpi-leader--' . esc_attr( $user->user_nicename ) . '">';
            if ( $show( 'photo' ) && '' !== $photo ) {
                $img     = '<img class="smpi-leader__image" src="' . esc_url( $photo ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" decoding="async">';
                $people .= $link ? '<a class="smpi-leader__photo" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>' : '<span class="smpi-leader__photo">' . $img . '</span>';
            }
            $people .= '<div class="smpi-leader__body">';
            $people .= '<' . $heading . ' class="smpi-leader__name">' . ( $link ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . '</' . $heading . '>';
            if ( $show( 'title' ) && '' !== $title ) {
                $people .= '<p class="smpi-leader__title">' . esc_html( $title ) . '</p>';
            }
            if ( $show( 'bio' ) && '' !== $bio && (int) $atts['bio_words'] > 0 ) {
                $people .= '<p class="smpi-leader__bio">' . esc_html( $bio ) . '</p>';
            }
            if ( $show( 'link' ) && $link ) {
                $people .= '<a class="smpi-leader__link" href="' . esc_url( $url ) . '">View profile</a>';
            }
            $people .= '</div></article>';
        }

        return ( 'none' !== $style ? self::styles() : '' )
            . '<div class="smpi-leadership smpi-leadership--' . esc_attr( $style ) . '" style="--smpi-leadership-columns:' . $columns . '">' . $people . '</div>';
    }

    /** The built-in look, printed once per page. Every rule is one class so site CSS overrides it. */
    public static function css(): string {
        return '.smpi-leadership{display:grid;gap:28px}'
            . '.smpi-leadership--cards{grid-template-columns:repeat(var(--smpi-leadership-columns,3),minmax(0,1fr))}'
            . '.smpi-leadership--cards .smpi-leader{display:flex;flex-direction:column;gap:14px}'
            . '.smpi-leadership--cards .smpi-leader__photo{display:block;aspect-ratio:1/1;overflow:hidden;border-radius:6px;background:rgba(127,127,127,.12)}'
            . '.smpi-leadership--list{grid-template-columns:1fr;gap:20px}'
            . '.smpi-leadership--list .smpi-leader{display:flex;align-items:flex-start;gap:18px;padding-bottom:20px;border-bottom:1px solid rgba(127,127,127,.2)}'
            . '.smpi-leadership--list .smpi-leader__photo{flex:0 0 96px;width:96px;height:96px;overflow:hidden;border-radius:50%;background:rgba(127,127,127,.12)}'
            . '.smpi-leader__image{display:block;width:100%;height:100%;object-fit:cover}'
            . '.smpi-leader__name{margin:0;font-size:1.15em;line-height:1.3}'
            . '.smpi-leader__name a{color:inherit;text-decoration:none}'
            . '.smpi-leader__title{margin:4px 0 0;font-size:.9em;font-weight:600;letter-spacing:.02em;text-transform:uppercase;opacity:.7}'
            . '.smpi-leader__bio{margin:10px 0 0;font-size:.95em;line-height:1.55}'
            . '.smpi-leader__link{display:inline-block;margin-top:10px;font-size:.9em;font-weight:600}'
            . '@media(max-width:900px){.smpi-leadership--cards{grid-template-columns:repeat(min(2,var(--smpi-leadership-columns,3)),minmax(0,1fr))}}'
            . '@media(max-width:560px){.smpi-leadership--cards{grid-template-columns:1fr}}';
    }

    private static function styles(): string {
        static $printed = false;
        if ( $printed ) {
            return '';
        }
        $printed = true;
        return '<style id="smpi-leadership-css">' . self::css() . '</style>';
    }

    /** The default page content: a short line and the leadership list. */
    public static function default_template(): string {
        return "<!-- wp:paragraph -->\n<p>Meet the people who lead [smp_publication_field field=legal_name format=text fallback=site_name].</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[" . self::SHORTCODE . "]\n<!-- /wp:shortcode -->";
    }

    /** The leadership page: the assigned one, an existing page at a usual address, or a new one with the default template. */
    public static function ensure_page(): array {
        $page_id = PageStructure::assigned_page_id( self::PAGE_TYPE );
        $page    = $page_id ? get_post( $page_id ) : null;
        if ( $page instanceof \WP_Post && 'trash' !== $page->post_status ) {
            return [ 'id' => $page_id, 'created' => false ];
        }

        foreach ( [ 'about/executive-leadership-team', 'executive-leadership-team', 'leadership-team', 'executive-team', 'leadership' ] as $path ) {
            $found = get_page_by_path( $path );
            if ( $found instanceof \WP_Post && 'trash' !== $found->post_status ) {
                PageStructure::save_assignment( self::PAGE_TYPE, (int) $found->ID );
                return [ 'id' => (int) $found->ID, 'created' => false ];
            }
        }

        $new_id = wp_insert_post(
            [
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => 'Executive Leadership Team',
                'post_name'    => 'executive-leadership-team',
                'post_content' => self::default_template(),
            ],
            true
        );
        if ( is_wp_error( $new_id ) ) {
            return [ 'id' => 0, 'created' => false, 'error' => $new_id->get_error_message() ];
        }
        PageStructure::save_assignment( self::PAGE_TYPE, (int) $new_id );
        return [ 'id' => (int) $new_id, 'created' => true ];
    }

    /** Whether the page already shows the leadership list. */
    public static function page_has_list( int $page_id ): bool {
        $content = (string) get_post_field( 'post_content', $page_id ) . (string) get_post_meta( $page_id, '_elementor_data', true );
        return false !== strpos( $content, '[' . self::SHORTCODE );
    }

    /** Replaces the page's content with the default template; WordPress keeps the old version as a revision. */
    public static function apply_default_template( int $page_id ): void {
        wp_update_post( [ 'ID' => $page_id, 'post_content' => self::default_template() ] );
        if ( 'builder' === get_post_meta( $page_id, '_elementor_edit_mode', true ) ) {
            // Back to the WordPress editor so the new content shows instead of the Elementor layout.
            delete_post_meta( $page_id, '_elementor_edit_mode' );
        }
    }
}
