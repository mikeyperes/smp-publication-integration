<?php

namespace smp_publication_integration\Content\People;

use smp_publication_integration\Authorship\AuthorFieldResolver;
use smp_publication_integration\Content\MuckRackVerification;
use smp_publication_integration\Content\PublicationContentTypes;

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
    /** Extra parts a list may switch on with show="...": all off by default, so existing pages never change. */
    public const EXTRAS = [ 'badge', 'topics', 'count', 'since', 'latest', 'initials', 'all' ];
    /** The default page order of a card's text parts (the photo always comes first). */
    public const ORDER = [ 'name', 'title', 'topics', 'count', 'bio', 'latest', 'link', 'all' ];
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
        'show'       => '',
        'orderby'    => '',
        'topics'     => 3,
        'topics_label' => 'Writes about',
        'topics_separator' => ', ',
        'topics_last' => '',
        'latest'     => 'full',
        'order'      => '',
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
        $types = PublicationContentTypes::article_post_types();
        if ( 'posts' === (string) $atts['orderby'] ) {
            // Most stories first; equal counts keep the list's own order.
            $counts = [];
            foreach ( $ids as $n => $uid ) {
                $counts[ $uid ] = [ (int) count_user_posts( $uid, $types, true ), $n ];
            }
            usort( $ids, static fn( $a, $b ) => $counts[ $b ][0] <=> $counts[ $a ][0] ?: $counts[ $a ][1] <=> $counts[ $b ][1] );
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
        $extra   = array_intersect( array_map( 'trim', explode( ',', strtolower( (string) $atts['show'] ) ) ), self::EXTRAS );
        $on      = static fn( string $part ): bool => in_array( $part, $extra, true );

        $people = '';
        foreach ( $users as $user ) {
            $id    = (int) $user->ID;
            $name  = (string) $user->display_name;
            $url   = get_author_posts_url( $id );
            $title = isset( $list['title'] ) && is_callable( $list['title'] ) ? trim( (string) call_user_func( $list['title'], $id ) ) : '';
            $bio   = $words > 0 ? wp_trim_words( wp_strip_all_tags( (string) get_the_author_meta( 'description', $id ) ), $words, '…' ) : '';
            $photo = $fields->image_url( $id, (string) $atts['photo_size'] );
            if ( $on( 'initials' ) && $fields->explicit_image_attachment_id( $id ) <= 0 && '' === $fields->explicit_image_url( $id, (string) $atts['photo_size'] ) ) {
                $photo = ''; // No photo of their own: initials instead of a placeholder image.
            }

            $people .= '<article class="' . esc_attr( $item . ' ' . $item . '--' . $user->user_nicename ) . '">';
            if ( $show( 'photo' ) && '' === $photo && $on( 'initials' ) ) {
                $people .= '<span class="' . esc_attr( $item ) . '__photo ' . esc_attr( $item ) . '__initials" aria-hidden="true">' . esc_html( self::initials( $name ) ) . '</span>';
            }
            if ( $show( 'photo' ) && '' !== $photo ) {
                $img     = '<img class="' . esc_attr( $item ) . '__image" src="' . esc_url( $photo ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" decoding="async">';
                $people .= $link
                    ? '<a class="' . esc_attr( $item ) . '__photo" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>'
                    : '<span class="' . esc_attr( $item ) . '__photo">' . $img . '</span>';
            }
            $parts = [];
            $parts['name'] = '<' . $heading . ' class="' . esc_attr( $item ) . '__name">' . ( $link ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . ( $on( 'badge' ) && class_exists( MuckRackVerification::class ) ? MuckRackVerification::verification_icon( $id, 'tooltip', 'loop_cards' ) : '' ) . '</' . $heading . '>';
            if ( $show( 'title' ) && '' !== $title ) {
                $parts['title'] = '<p class="' . esc_attr( $item ) . '__title">' . esc_html( $title ) . '</p>';
            }
            if ( $on( 'topics' ) ) {
                $topics = do_shortcode( '[author_categories user_id="' . $id . '" limit="' . max( 1, (int) $atts['topics'] ) . '" separator="' . esc_attr( (string) $atts['topics_separator'] ) . '" last="' . esc_attr( (string) $atts['topics_last'] ) . '"]' );
                if ( '' !== $topics ) {
                    $label = (string) $atts['topics_label'];
                    $parts['topics'] = '<p class="' . esc_attr( $item ) . '__topics">' . ( '' !== $label ? '<span class="' . esc_attr( $item ) . '__topics-label">' . esc_html( $label ) . '</span> ' : '' ) . $topics . '</p>';
                }
            }
            if ( $on( 'count' ) || $on( 'since' ) ) {
                $n     = (int) count_user_posts( $id, $types, true );
                $line  = $on( 'count' ) ? '<b class="' . esc_attr( $item ) . '__count-number">' . esc_html( number_format_i18n( $n ) ) . '</b> ' . esc_html( _n( 'story', 'stories', $n, 'smp-publication-integration' ) ) : '';
                $first = $on( 'since' ) ? get_posts( [ 'author' => $id, 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => 1, 'order' => 'ASC', 'orderby' => 'date', 'no_found_rows' => true ] ) : [];
                if ( $first ) {
                    $line .= ( '' !== $line ? ' &middot; ' : '' ) . esc_html( sprintf( __( 'writing since %s', 'smp-publication-integration' ), mysql2date( 'F Y', $first[0]->post_date ) /* the stored date: date filters on the page (relative or custom formats) never change it */ ) );
                }
                if ( '' !== $line ) {
                    $parts['count'] = '<p class="' . esc_attr( $item ) . '__count">' . $line . '</p>';
                }
            }
            if ( $show( 'bio' ) && '' !== $bio ) {
                $parts['bio'] = '<p class="' . esc_attr( $item ) . '__bio">' . esc_html( $bio ) . '</p>';
            }
            if ( $on( 'latest' ) ) {
                $parts['latest'] = 'compact' === (string) $atts['latest']
                    ? self::latest_compact( $id, $types, $item )
                    : self::latest( $id, $types, $item, $name, '' !== $photo ? $fields->image_url( $id, 'thumbnail' ) : '', $on( 'badge' ) );
            }
            if ( $show( 'link' ) && $link ) {
                $parts['link'] = '<a class="' . esc_attr( $item ) . '__link" href="' . esc_url( $url ) . '">View profile</a>';
            }
            if ( $on( 'all' ) ) {
                $n     = (int) count_user_posts( $id, $types, true );
                $firstname = (string) ( preg_split( '/\s+/', trim( $name ) )[0] ?? $name );
                if ( $n > 0 ) {
                    $parts['all'] = '<p class="' . esc_attr( $item ) . '__all"><a href="' . esc_url( $url ) . '">' . esc_html( sprintf( _n( 'All %1$s story by %2$s', 'All %1$s stories by %2$s', $n, 'smp-publication-integration' ), number_format_i18n( $n ), $firstname ) ) . '</a></p>';
                }
            }
            // order="topics,name,..." sets the page order of the parts; anything left out follows in the default order.
            $order = array_values( array_unique( array_merge( array_map( 'trim', explode( ',', strtolower( (string) $atts['order'] ) ) ), self::ORDER ) ) );
            $people .= '<div class="' . esc_attr( $item ) . '__body">';
            foreach ( $order as $key ) {
                $people .= $parts[ $key ] ?? '';
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

    /** First letters of the first and last name, for a person without a photo. */
    private static function initials( string $name ): string {
        $parts = preg_split( '/\s+/', trim( $name ) ) ?: [];
        $first = $parts ? mb_substr( (string) $parts[0], 0, 1 ) : '';
        $last  = count( $parts ) > 1 ? mb_substr( (string) end( $parts ), 0, 1 ) : '';
        return mb_strtoupper( $first . $last );
    }

    /** The person's newest story: photo, section, headline and date; "" when there is none. */
    private static function latest( int $user_id, array $types, string $item, string $name = '', string $avatar = '', bool $badge = false ): string {
        $posts = get_posts( [ 'author' => $user_id, 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true ] );
        if ( ! $posts ) {
            return '';
        }
        $post  = $posts[0];
        $url   = get_permalink( $post );
        $cats  = get_the_category( $post->ID );
        $html  = '<div class="' . esc_attr( $item ) . '__latest"><p class="' . esc_attr( $item ) . '__latest-label">Latest story</p>';
        $thumb = get_the_post_thumbnail( $post, 'medium', [ 'class' => $item . '__latest-image', 'loading' => 'lazy' ] );
        if ( '' !== $thumb ) {
            $html .= '<a class="' . esc_attr( $item ) . '__latest-photo" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $thumb . '</a>';
        }
        $html .= '<div class="' . esc_attr( $item ) . '__latest-body">';
        if ( $cats ) {
            $html .= '<a class="' . esc_attr( $item ) . '__latest-section" href="' . esc_url( get_category_link( $cats[0] ) ) . '">' . esc_html( $cats[0]->name ) . '</a>';
        }
        $html .= '<a class="' . esc_attr( $item ) . '__latest-title" href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post ) ) . '</a>';
        $html .= '<div class="' . esc_attr( $item ) . '__latest-byline">';
        if ( '' !== $avatar ) {
            $html .= '<img class="' . esc_attr( $item ) . '__latest-avatar" src="' . esc_url( $avatar ) . '" alt="" width="48" height="48" loading="lazy" decoding="async">';
        } elseif ( '' !== $name ) {
            // No photo of their own: the same initials the card shows, never a placeholder image.
            $html .= '<span class="' . esc_attr( $item ) . '__latest-avatar ' . esc_attr( $item ) . '__initials" aria-hidden="true">' . esc_html( self::initials( $name ) ) . '</span>';
        }
        if ( '' !== $name ) {
            $html .= '<a class="' . esc_attr( $item ) . '__latest-author" href="' . esc_url( get_author_posts_url( $user_id ) ) . '">' . esc_html( $name ) . '</a>';
        }
        if ( $badge && class_exists( MuckRackVerification::class ) ) {
            $html .= MuckRackVerification::verification_icon( $user_id, 'tooltip', 'loop_cards' );
        }
        $html .= '<time class="' . esc_attr( $item ) . '__latest-date" datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>';
        return $html . '</div></div></div>';
    }

    /** The newest story as one line: label, headline and date; "" when there is none. */
    private static function latest_compact( int $user_id, array $types, string $item ): string {
        $posts = get_posts( [ 'author' => $user_id, 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true ] );
        if ( ! $posts ) {
            return '';
        }
        $post = $posts[0];
        return '<p class="' . esc_attr( $item ) . '__latest ' . esc_attr( $item ) . '__latest--compact"><span class="' . esc_attr( $item ) . '__latest-label">Latest story</span>'
            . '<a class="' . esc_attr( $item ) . '__latest-title" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>'
            . '<time class="' . esc_attr( $item ) . '__latest-date" datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( mysql2date( 'M j, Y', $post->post_date ) ) . '</time></p>';
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
            . ".$b.$b .$i .{$i}__name{margin:0;padding:0;font-size:1.25em;line-height:1.3}"
            // Scoped strongly enough that a theme or page template's blanket link colour cannot recolour the names.
            . ".$b.$b .$i .{$i}__name a,.$b.$b .$i .{$i}__name a:hover{color:inherit;text-decoration:none}"
            . ".$b.$b .$i .{$i}__title{margin:4px 0 0;padding:0;font-size:.9em;font-weight:600;letter-spacing:.02em;text-transform:uppercase;opacity:.7}"
            . ".$b.$b .$i .{$i}__bio{margin:10px 0 0;padding:0;font-size:.95em;line-height:1.55}"
            . ".$b.$b .$i .{$i}__link{display:inline-block;margin-top:10px;font-size:.9em;font-weight:600;color:inherit;text-decoration:underline;text-underline-offset:3px;transition:opacity .25s}"
            . ".$b.$b .$i .{$i}__link:hover{color:inherit;opacity:.7}"
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
