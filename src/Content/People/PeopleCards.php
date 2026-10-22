<?php

namespace smp_publication_integration\Content\People;

use smp_publication_integration\Authorship\AuthorFieldResolver;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The public markup and built-in look shared by the publication's people lists
 * (Executive Leadership, Contributors). Each list has its own class names, so a
 * site can style one without touching the other:
 *
 *     <div class="{block} {block}--cards" style="--{block}-columns:3">
 *       <article class="{item} {item}--{user-slug}"> photo, name, title, bio, link </article>
 *     </div>
 */
final class PeopleCards {
    public const STYLES = [
        'cards' => 'Cards: photo, name, title and short bio in a grid (default)',
        'list'  => 'List: one person per row with a small photo',
        'none'  => 'No styling: plain markup for the theme or Elementor to style',
    ];
    public const PARTS = [ 'photo', 'title', 'bio', 'link' ];
    public const ATTRIBUTES = [
        'style'      => '',
        'columns'    => 3,
        'ids'        => '',
        'limit'      => 0,
        'hide'       => '',
        'link'       => 'profile',
        'bio_words'  => 30,
        'photo_size' => 'medium',
        'heading'    => 'h3',
    ];

    /** @var array<string,bool> */
    private static array $printed = [];

    /**
     * @param int[]                $ids    People in order.
     * @param array<string,mixed>  $atts   Shortcode attributes (see ATTRIBUTES).
     * @param array{block:string,item:string,style:string,title?:callable} $list
     */
    public static function render( array $ids, array $atts, array $list ): string {
        if ( '' !== trim( (string) $atts['ids'] ) ) {
            $ids = array_values( array_filter( array_map( 'absint', explode( ',', (string) $atts['ids'] ) ) ) );
        }
        if ( (int) $atts['limit'] > 0 ) {
            $ids = array_slice( $ids, 0, (int) $atts['limit'] );
        }
        $users = array_values( array_filter( array_map( 'get_userdata', $ids ) ) );
        if ( ! $users ) {
            return '';
        }

        $block   = $list['block'];
        $item    = $list['item'];
        $style   = isset( self::STYLES[ (string) $atts['style'] ] ) ? (string) $atts['style'] : ( isset( self::STYLES[ $list['style'] ] ) ? $list['style'] : 'cards' );
        $hide    = array_intersect( array_map( 'trim', explode( ',', strtolower( (string) $atts['hide'] ) ) ), self::PARTS );
        $show    = static fn( string $part ): bool => ! in_array( $part, $hide, true );
        $heading = in_array( (string) $atts['heading'], [ 'h2', 'h3', 'h4', 'p' ], true ) ? (string) $atts['heading'] : 'h3';
        $link    = 'none' !== (string) $atts['link'];
        $words   = max( 0, (int) $atts['bio_words'] );
        $columns = max( 1, min( 6, (int) $atts['columns'] ) );
        $fields  = new AuthorFieldResolver();

        $people = '';
        foreach ( $users as $user ) {
            $id    = (int) $user->ID;
            $name  = (string) $user->display_name;
            $url   = get_author_posts_url( $id );
            $title = isset( $list['title'] ) && is_callable( $list['title'] ) ? trim( (string) call_user_func( $list['title'], $id ) ) : '';
            $bio   = $words > 0 ? wp_trim_words( wp_strip_all_tags( (string) get_the_author_meta( 'description', $id ) ), $words, '…' ) : '';
            $photo = $fields->image_url( $id, (string) $atts['photo_size'] );

            $people .= '<article class="' . esc_attr( $item . ' ' . $item . '--' . $user->user_nicename ) . '">';
            if ( $show( 'photo' ) && '' !== $photo ) {
                $img     = '<img class="' . esc_attr( $item ) . '__image" src="' . esc_url( $photo ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" decoding="async">';
                $people .= $link
                    ? '<a class="' . esc_attr( $item ) . '__photo" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>'
                    : '<span class="' . esc_attr( $item ) . '__photo">' . $img . '</span>';
            }
            $people .= '<div class="' . esc_attr( $item ) . '__body">';
            $people .= '<' . $heading . ' class="' . esc_attr( $item ) . '__name">' . ( $link ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . '</' . $heading . '>';
            if ( $show( 'title' ) && '' !== $title ) {
                $people .= '<p class="' . esc_attr( $item ) . '__title">' . esc_html( $title ) . '</p>';
            }
            if ( $show( 'bio' ) && '' !== $bio ) {
                $people .= '<p class="' . esc_attr( $item ) . '__bio">' . esc_html( $bio ) . '</p>';
            }
            if ( $show( 'link' ) && $link ) {
                $people .= '<a class="' . esc_attr( $item ) . '__link" href="' . esc_url( $url ) . '">View profile</a>';
            }
            $people .= '</div></article>';
        }

        $css = '';
        if ( 'none' !== $style && empty( self::$printed[ $block ] ) ) {
            self::$printed[ $block ] = true;
            $css = '<style id="' . esc_attr( $block ) . '-css">' . self::css( $block, $item ) . '</style>';
        }

        return $css . '<div class="' . esc_attr( $block . ' ' . $block . '--' . $style ) . '" style="--' . esc_attr( $block ) . '-columns:' . $columns . '">' . $people . '</div>';
    }

    /** The built-in look for one list. Every rule is one class, so site CSS overrides it. */
    public static function css( string $b, string $i ): string {
        return ".$b{display:grid;gap:28px}"
            . ".$b--cards{grid-template-columns:repeat(var(--$b-columns,3),minmax(0,1fr))}"
            . ".$b--cards .$i{display:flex;flex-direction:column;gap:14px}"
            . ".$b--cards .{$i}__photo{display:block;aspect-ratio:1/1;overflow:hidden;border-radius:6px;background:rgba(127,127,127,.12)}"
            . ".$b--list{grid-template-columns:1fr;gap:20px}"
            . ".$b--list .$i{display:flex;align-items:flex-start;gap:18px;padding-bottom:20px;border-bottom:1px solid rgba(127,127,127,.2)}"
            . ".$b--list .{$i}__photo{flex:0 0 96px;width:96px;height:96px;overflow:hidden;border-radius:50%;background:rgba(127,127,127,.12)}"
            . ".{$i}__image{display:block;width:100%;height:100%;object-fit:cover}"
            . ".{$i}__name{margin:0;font-size:1.15em;line-height:1.3}"
            . ".{$i}__name a{color:inherit;text-decoration:none}"
            . ".{$i}__title{margin:4px 0 0;font-size:.9em;font-weight:600;letter-spacing:.02em;text-transform:uppercase;opacity:.7}"
            . ".{$i}__bio{margin:10px 0 0;font-size:.95em;line-height:1.55}"
            . ".{$i}__link{display:inline-block;margin-top:10px;font-size:.9em;font-weight:600}"
            . "@media(max-width:900px){.$b--cards{grid-template-columns:repeat(min(2,var(--$b-columns,3)),minmax(0,1fr))}}"
            . "@media(max-width:560px){.$b--cards{grid-template-columns:1fr}}";
    }

    public static function structure( string $b, string $i, string $title_label ): string {
        return "<div class=\"$b $b--cards\" style=\"--$b-columns:3\">\n"
            . "  <article class=\"$i $i--{user-slug}\">\n"
            . "    <a class=\"{$i}__photo\" href=\"{author page}\"><img class=\"{$i}__image\" src=\"…\" alt=\"{Name}\"></a>\n"
            . "    <div class=\"{$i}__body\">\n"
            . "      <h3 class=\"{$i}__name\"><a href=\"{author page}\">{Name}</a></h3>\n"
            . ( '' !== $title_label ? "      <p class=\"{$i}__title\">{" . $title_label . "}</p>\n" : '' )
            . "      <p class=\"{$i}__bio\">{short bio}</p>\n"
            . "      <a class=\"{$i}__link\" href=\"{author page}\">View profile</a>\n"
            . "    </div>\n"
            . "  </article>\n"
            . "  …\n"
            . "</div>";
    }

    /** Finds the page for a page type: the assigned one, or an existing page at one of the usual addresses. */
    public static function find_page( string $page_type, array $paths ): int {
        $page_id = \smp_publication_integration\Support\PageStructure::assigned_page_id( $page_type );
        $page    = $page_id ? get_post( $page_id ) : null;
        if ( $page instanceof \WP_Post && 'trash' !== $page->post_status ) {
            return $page_id;
        }
        foreach ( $paths as $path ) {
            $found = get_page_by_path( $path );
            if ( $found instanceof \WP_Post && 'trash' !== $found->post_status ) {
                \smp_publication_integration\Support\PageStructure::save_assignment( $page_type, (int) $found->ID );
                return (int) $found->ID;
            }
        }
        return 0;
    }

    /** Creates a published page with the given content and assigns it to the page type. */
    public static function create_page( string $page_type, string $title, string $slug, string $content ): array {
        $id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ], true );
        if ( is_wp_error( $id ) ) {
            return [ 'id' => 0, 'error' => $id->get_error_message() ];
        }
        \smp_publication_integration\Support\PageStructure::save_assignment( $page_type, (int) $id );
        return [ 'id' => (int) $id ];
    }

    public static function page_has_shortcode( int $page_id, string $shortcode ): bool {
        $content = (string) get_post_field( 'post_content', $page_id ) . (string) get_post_meta( $page_id, '_elementor_data', true );
        return false !== strpos( $content, '[' . $shortcode );
    }

    /** Replaces a page's content; WordPress keeps the previous version as a revision. */
    public static function apply_template( int $page_id, string $content ): void {
        wp_update_post( [ 'ID' => $page_id, 'post_content' => $content ] );
        if ( 'builder' === get_post_meta( $page_id, '_elementor_edit_mode', true ) ) {
            delete_post_meta( $page_id, '_elementor_edit_mode' );
        }
    }
}
