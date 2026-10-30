<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Authorship\AuthorFieldResolver;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class AuthorShortcodes {
    public function register(): void {
        add_action( "init", [ $this, "register_shortcodes" ], 20 );
    }

    public function register_shortcodes(): void {
        foreach ( self::shortcodes() as $tag => $callback ) {
            add_shortcode( $tag, [ $this, $callback ] );
        }
    }

    public static function shortcodes(): array {
        return [
            "author_name" => "render_name",
            "author_bio_short" => "render_bio_short",
            "author_bio" => "render_bio",
            "author_title" => "render_title",
            "author_subtitle" => "render_subtitle",
            "author_facebook" => "render_facebook",
            "author_instagram" => "render_instagram",
            "author" => "render_author",
            "author_x" => "render_x",
            "author_linkedin" => "render_linkedin",
            "author_youtube" => "render_youtube",
            "author_website" => "render_website",
            "author_url" => "render_url",
            "author_crunchbase" => "render_crunchbase",
            "author_muckrack" => "render_muckrack",
            "author_muck_rack" => "render_muckrack",
            "author_email" => "render_email",
            "author_muckrack_verified" => "render_muckrack_verified",
            "author_image" => "render_image",
            "author_categories" => "render_categories",
        ];
    }

    public function render_name( array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "author_index" => 0 ], $atts, "author_name" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"], (int) $atts["author_index"] );
        if ( ! $author_id ) {
            return "";
        }
        return esc_html( (string) get_the_author_meta( "display_name", $author_id ) );
    }

    public function render_bio_short( array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "author_index" => 0, "words" => 35 ], $atts, "author_bio_short" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"], (int) $atts["author_index"] );
        if ( ! $author_id ) {
            return "";
        }
        $resolver = new AuthorFieldResolver();
        $value = $resolver->value( $author_id, "bio_short" );
        if ( "" === $value ) {
            $value = $resolver->value( $author_id, "bio" );
        }
        return "" !== $value ? esc_html( wp_trim_words( wp_strip_all_tags( $value ), max( 1, (int) $atts["words"] ) ) ) : "";
    }

    public function render_bio( array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "author_index" => 0, "format" => "html" ], $atts, "author_bio" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"], (int) $atts["author_index"] );
        if ( ! $author_id ) {
            return "";
        }
        $value = ( new AuthorFieldResolver() )->value( $author_id, "bio" );
        if ( "" === $value ) {
            return "";
        }
        if ( "text" === sanitize_key( (string) $atts["format"] ) ) {
            return esc_html( wp_strip_all_tags( $value ) );
        }
        return "<div class=\"smpi-author-bio\">" . wp_kses_post( wpautop( $value ) ) . "</div>";
    }

    public static function field_aliases(): array {
        return AuthorFieldResolver::aliases();
    }

    public function render_title( array $atts = [] ): string {
        return $this->render_author_text( "title", $atts );
    }

    public function render_subtitle( array $atts = [] ): string {
        return $this->render_author_text( "subtitle", $atts );
    }

    public function render_facebook( array $atts = [] ): string {
        return $this->render_social_url( "facebook", $atts );
    }

    public function render_instagram( array $atts = [] ): string {
        return $this->render_social_url( "instagram", $atts );
    }

    public function render_author( array $atts = [] ): string {
        $atts = shortcode_atts( [ "url" => "", "user_id" => 0, "post_id" => 0, "author_index" => 0 ], $atts, "author" );
        $key = sanitize_key( (string) $atts["url"] );
        if ( "twitter" === $key || "tw" === $key ) {
            $key = "x";
        }
        $allowed = [ "facebook", "instagram", "x", "linkedin", "youtube", "crunchbase", "muckrack", "website" ];
        if ( ! in_array( $key, $allowed, true ) ) {
            return "";
        }
        return $this->render_social_url( $key, $atts );
    }

    public function render_x( array $atts = [] ): string {
        return $this->render_social_url( "x", $atts );
    }

    public function render_linkedin( array $atts = [] ): string {
        return $this->render_social_url( "linkedin", $atts );
    }

    public function render_youtube( array $atts = [] ): string {
        return $this->render_social_url( "youtube", $atts );
    }

    /** The byline author's archive page (the same author [author_name] shows), for links such as "View more articles". */
    public function render_url( array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "author_index" => 0 ], $atts, "author_url" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"], (int) $atts["author_index"] );
        return $author_id ? esc_url( get_author_posts_url( $author_id ) ) : "";
    }

    public function render_website( array $atts = [] ): string {
        return $this->render_social_url( "website", $atts );
    }

    public function render_crunchbase( array $atts = [] ): string {
        return $this->render_social_url( "crunchbase", $atts );
    }

    public function render_muckrack( array $atts = [] ): string {
        return $this->render_social_url( "muckrack", $atts );
    }

    public function render_email( array $atts = [] ): string {
        return $this->render_author_text( "email", $atts );
    }

    public function render_muckrack_verified( array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "author_index" => 0, "type" => "icon", "style" => "", "context" => "", "size" => "", "icon" => "" ], $atts, "author_muckrack_verified" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"], (int) $atts["author_index"] );
        if ( ! $author_id || ! MuckRackVerification::author_verified( $author_id ) ) {
            return "";
        }
        $context = sanitize_key( (string) $atts["context"] );
        if ( "" === $context ) {
            if ( is_singular( "post" ) ) {
                $context = "single_author";
            } elseif ( is_author() ) {
                $context = "author";
            } elseif ( is_home() || is_front_page() ) {
                $context = "home";
            }
        }
        return "text" === sanitize_key( (string) $atts["type"] ) ? MuckRackVerification::verification_text( $author_id, (string) $atts["icon"] ) : MuckRackVerification::verification_icon( $author_id, sanitize_key( (string) $atts["style"] ), $context, true, (string) $atts["size"] );
    }

    /** The sections an author writes in most, as links, most stories first; "" when none. */
    public function render_categories( array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "author_index" => 0, "limit" => 4, "separator" => ", ", "last" => "" ], $atts, "author_categories" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"], (int) $atts["author_index"] );
        if ( ! $author_id ) {
            return "";
        }
        $limit = max( 1, min( 12, absint( $atts["limit"] ) ) );
        // WordPress moves the posts "last changed" marker whenever a story changes, so the cached list renews itself.
        $key = "smpi_author_categories_v2_" . md5( $author_id . "|" . $limit . "|" . wp_cache_get_last_changed( "posts" ) );
        $top = get_transient( $key );
        if ( ! is_array( $top ) ) {
            $ids = get_posts( [ "post_type" => "post", "post_status" => "publish", "author" => $author_id, "posts_per_page" => 200, "fields" => "ids", "no_found_rows" => true ] );
            $skip = (int) get_option( "default_category" );
            $counts = [];
            foreach ( empty( $ids ) ? [] : wp_get_object_terms( $ids, "category", [ "fields" => "all_with_object_id" ] ) as $term ) {
                if ( (int) $term->term_id !== $skip ) {
                    $counts[ (int) $term->term_id ] = ( $counts[ (int) $term->term_id ] ?? 0 ) + 1;
                }
            }
            // Most stories first; equal counts fall back to the oldest category, so the order never shuffles.
            uksort( $counts, static fn( $a, $b ) => $counts[ $b ] <=> $counts[ $a ] ?: $a <=> $b );
            $top = array_slice( array_keys( $counts ), 0, $limit );
            set_transient( $key, $top, 12 * HOUR_IN_SECONDS );
        }
        $links = [];
        foreach ( $top as $term_id ) {
            $term = get_term( (int) $term_id, "category" );
            $url = $term instanceof \WP_Term ? get_term_link( $term ) : "";
            if ( $term instanceof \WP_Term && ! is_wp_error( $url ) ) {
                $links[] = '<a class="smpi-author-category" href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . "</a>";
            }
        }
        if ( empty( $links ) ) {
            return "";
        }
        // last=" and " joins the final two sections differently ("A, B and C").
        $tail = array_pop( $links );
        $head = implode( esc_html( (string) $atts["separator"] ), $links );
        $join = "" !== (string) $atts["last"] ? (string) $atts["last"] : (string) $atts["separator"];
        return '<span class="smpi-author-categories">' . ( "" !== $head ? $head . esc_html( $join ) : "" ) . $tail . "</span>";
    }

    public function render_image( array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "author_index" => 0, "size" => "thumbnail", "output" => "html", "class" => "smpi-author-image", "loading" => "lazy", "sizes" => "" ], $atts, "author_image" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"], (int) $atts["author_index"] );
        if ( ! $author_id ) {
            return "";
        }
        $size = sanitize_key( (string) $atts["size"] );
        if ( ! in_array( $size, [ "thumbnail", "medium", "medium_large", "large", "full" ], true ) ) {
            $size = "thumbnail";
        }
        $resolver = new AuthorFieldResolver();
        $url = $resolver->image_url( $author_id, $size );
        if ( "" === $url ) {
            return "";
        }
        if ( "url" === sanitize_key( (string) $atts["output"] ) ) {
            return esc_url( $url );
        }
        $name = get_the_author_meta( "display_name", $author_id );
        // A photo on the first screen (an author page header) loads at once: loading="eager".
        $loading = "eager" === sanitize_key( (string) $atts["loading"] ) ? "loading=\"eager\" fetchpriority=\"high\"" : "loading=\"lazy\"";
        // Opt in so existing templates retain their exact markup and loading behavior.
        $responsive = "";
        $sizes = trim( (string) $atts["sizes"] );
        if ( "" !== $sizes ) {
            $responsive = ' sizes="' . esc_attr( $sizes ) . '"';
            $attachment_id = $resolver->explicit_image_attachment_id( $author_id );
            $image = $attachment_id ? wp_get_attachment_image_src( $attachment_id, $size ) : false;
            // Never attach dimensions or candidates from a different resolved photo.
            if ( is_array( $image ) && $image[0] === $url && 0 < (int) $image[1] && 0 < (int) $image[2] ) {
                $responsive .= sprintf( ' width="%d" height="%d"', (int) $image[1], (int) $image[2] );
                $srcset = wp_get_attachment_image_srcset( $attachment_id, $size );
                if ( is_string( $srcset ) && "" !== $srcset ) {
                    $responsive .= ' srcset="' . esc_attr( $srcset ) . '"';
                }
            }
        }
        return sprintf( "<img class=\"%s\" src=\"%s\" alt=\"%s\" %s decoding=\"async\"%s>", esc_attr( (string) $atts["class"] ), esc_url( $url ), esc_attr( $name ), $loading, $responsive );
    }

    private function render_author_text( string $key, array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "author_index" => 0 ], $atts, "author_" . $key );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"], (int) $atts["author_index"] );
        if ( ! $author_id ) {
            return "";
        }
        if ( "email" === $key ) {
            $user = get_user_by( "id", $author_id );
            return $user ? esc_html( (string) $user->user_email ) : "";
        }
        $value = ( new AuthorFieldResolver() )->value( $author_id, $key );
        return "" !== $value ? esc_html( wp_strip_all_tags( $value ) ) : "";
    }

    private function render_social_url( string $key, array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "author_index" => 0 ], $atts, "author_" . $key );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"], (int) $atts["author_index"] );
        if ( ! $author_id ) {
            return "";
        }
        $url = ( new AuthorFieldResolver() )->social_url( $author_id, $key );
        return "" !== $url ? esc_url( $url ) : "";
    }

    private function resolve_author_id( int $explicit_user_id = 0, int $explicit_post_id = 0, int $author_index = 0 ): int {
        return MultiAuthors::resolve_author_id( $explicit_user_id, $explicit_post_id, max( 0, $author_index ) );
    }

}
