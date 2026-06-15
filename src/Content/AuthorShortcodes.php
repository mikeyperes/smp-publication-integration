<?php
namespace smp_publication_integration\Content;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class AuthorShortcodes {
    private const FIELD_ALIASES = [
        "bio_short" => [ "author_bio_short", "bio_short", "short_bio", "user_short_bio", "description_short", "what_best_describe_you" ],
        "bio" => [ "author_bio", "bio", "biography", "description", "user_description" ],
        "facebook" => [ "author_facebook", "facebook", "facebook_url", "profile_facebook", "social_facebook" ],
        "instagram" => [ "author_instagram", "instagram", "instagram_url", "profile_instagram", "social_instagram" ],
        "x" => [ "author_x", "x", "x_url", "twitter", "twitter_url", "profile_twitter", "social_twitter" ],
        "youtube" => [ "author_youtube", "youtube", "youtube_url", "profile_youtube", "social_youtube" ],
        "muckrack" => [ "author_muckrack", "muckrack", "muckrack_url", "muckrack_profile" ],
        "image" => [ "author_image", "profile_photo", "profile_image", "headshot", "photo", "avatar" ],
    ];

    private const IMAGE_SIZE_PIXELS = [
        "thumbnail" => 150,
        "medium" => 300,
        "medium_large" => 768,
        "large" => 1024,
        "full" => 1024,
    ];

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
            "author_bio_short" => "render_bio_short",
            "author_bio" => "render_bio",
            "author_facebook" => "render_facebook",
            "author_instagram" => "render_instagram",
            "author_x" => "render_x",
            "author_youtube" => "render_youtube",
            "author_muckrack" => "render_muckrack",
            "author_muckrack_verified" => "render_muckrack_verified",
            "author_image" => "render_image",
        ];
    }

    public function render_bio_short( array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "words" => 35 ], $atts, "author_bio_short" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"] );
        if ( ! $author_id ) {
            return "";
        }
        $value = $this->first_author_field( $author_id, self::FIELD_ALIASES["bio_short"] );
        if ( "" === $value ) {
            $value = $this->first_author_field( $author_id, self::FIELD_ALIASES["bio"] );
        }
        return "" !== $value ? esc_html( wp_trim_words( wp_strip_all_tags( $value ), max( 1, (int) $atts["words"] ) ) ) : "";
    }

    public function render_bio( array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "format" => "html" ], $atts, "author_bio" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"] );
        if ( ! $author_id ) {
            return "";
        }
        $value = $this->first_author_field( $author_id, self::FIELD_ALIASES["bio"] );
        if ( "" === $value ) {
            return "";
        }
        if ( "text" === sanitize_key( (string) $atts["format"] ) ) {
            return esc_html( wp_strip_all_tags( $value ) );
        }
        return "<div class=\"smpi-author-bio\">" . wp_kses_post( wpautop( $value ) ) . "</div>";
    }

    public function render_facebook( array $atts = [] ): string {
        return $this->render_social_url( "facebook", $atts );
    }

    public function render_instagram( array $atts = [] ): string {
        return $this->render_social_url( "instagram", $atts );
    }

    public function render_x( array $atts = [] ): string {
        return $this->render_social_url( "x", $atts );
    }

    public function render_youtube( array $atts = [] ): string {
        return $this->render_social_url( "youtube", $atts );
    }

    public function render_muckrack( array $atts = [] ): string {
        return $this->render_social_url( "muckrack", $atts );
    }

    public function render_muckrack_verified( array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "type" => "icon", "style" => "" ], $atts, "author_muckrack_verified" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"] );
        if ( ! $author_id || ! MuckRackVerification::author_verified( $author_id ) ) {
            return "";
        }
        return "text" === sanitize_key( (string) $atts["type"] ) ? MuckRackVerification::verification_text( $author_id ) : MuckRackVerification::verification_icon( $author_id, sanitize_key( (string) $atts["style"] ) );
    }

    public function render_image( array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0, "size" => "thumbnail", "output" => "html", "class" => "smpi-author-image" ], $atts, "author_image" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"] );
        if ( ! $author_id ) {
            return "";
        }
        $size = sanitize_key( (string) $atts["size"] );
        if ( ! isset( self::IMAGE_SIZE_PIXELS[ $size ] ) ) {
            $size = "thumbnail";
        }
        $url = $this->author_image_url( $author_id, $size );
        if ( "" === $url ) {
            return "";
        }
        if ( "url" === sanitize_key( (string) $atts["output"] ) ) {
            return esc_url( $url );
        }
        $name = get_the_author_meta( "display_name", $author_id );
        return sprintf( "<img class=\"%s\" src=\"%s\" alt=\"%s\" loading=\"lazy\" decoding=\"async\">", esc_attr( (string) $atts["class"] ), esc_url( $url ), esc_attr( $name ) );
    }

    private function render_social_url( string $key, array $atts = [] ): string {
        $atts = shortcode_atts( [ "user_id" => 0, "post_id" => 0 ], $atts, "author_" . $key );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"] );
        if ( ! $author_id ) {
            return "";
        }
        $value = $this->first_author_field( $author_id, self::FIELD_ALIASES[ $key ] ?? [ $key ] );
        $url = $this->normalize_social_url( $key, $value );
        return "" !== $url ? esc_url( $url ) : "";
    }

    private function resolve_author_id( int $explicit_user_id = 0, int $explicit_post_id = 0 ): int {
        if ( $explicit_user_id > 0 && get_user_by( "id", $explicit_user_id ) ) {
            return $explicit_user_id;
        }
        if ( $explicit_post_id > 0 ) {
            $post = get_post( $explicit_post_id );
            return $post ? (int) $post->post_author : 0;
        }
        if ( is_author() ) {
            return (int) get_queried_object_id();
        }
        $post = get_post();
        return $post ? (int) $post->post_author : 0;
    }

    private function first_author_field( int $author_id, array $fields ): string {
        foreach ( $fields as $field ) {
            $value = MuckRackVerification::author_field( $author_id, $field );
            if ( $this->has_value( $value ) ) {
                return $this->field_to_string( $value );
            }
            $value = get_the_author_meta( $field, $author_id );
            if ( $this->has_value( $value ) ) {
                return $this->field_to_string( $value );
            }
        }
        return "";
    }

    private function author_image_url( int $author_id, string $size ): string {
        foreach ( self::FIELD_ALIASES["image"] as $field ) {
            $value = MuckRackVerification::author_field( $author_id, $field );
            $url = $this->image_value_to_url( $value, $size );
            if ( "" !== $url ) {
                return $url;
            }
        }
        $avatar = get_avatar_url( $author_id, [ "size" => self::IMAGE_SIZE_PIXELS[ $size ] ] );
        return is_string( $avatar ) ? $avatar : "";
    }

    private function image_value_to_url( $value, string $size ): string {
        if ( is_array( $value ) ) {
            if ( isset( $value["sizes"][ $size ] ) && is_string( $value["sizes"][ $size ] ) ) {
                return $value["sizes"][ $size ];
            }
            if ( isset( $value["url"] ) && is_string( $value["url"] ) ) {
                return $value["url"];
            }
            if ( isset( $value["ID"] ) ) {
                $url = wp_get_attachment_image_url( (int) $value["ID"], $size );
                return is_string( $url ) ? $url : "";
            }
        }
        if ( is_numeric( $value ) ) {
            $url = wp_get_attachment_image_url( (int) $value, $size );
            return is_string( $url ) ? $url : "";
        }
        if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
            return $value;
        }
        return "";
    }

    private function normalize_social_url( string $key, string $value ): string {
        $value = trim( $value );
        if ( "" === $value ) {
            return "";
        }
        if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
            return $value;
        }
        if ( 0 === strpos( $value, "www." ) || false !== strpos( $value, ".com/" ) ) {
            return "https://" . ltrim( $value, "/" );
        }
        $handle = ltrim( $value, "@/" );
        if ( "" === $handle || false !== strpos( $handle, " " ) ) {
            return "";
        }
        $bases = [
            "facebook" => "https://facebook.com/",
            "instagram" => "https://instagram.com/",
            "x" => "https://x.com/",
            "youtube" => "https://youtube.com/",
            "muckrack" => "https://muckrack.com/",
        ];
        return isset( $bases[ $key ] ) ? $bases[ $key ] . rawurlencode( $handle ) : "";
    }

    private function field_to_string( $value ): string {
        if ( is_array( $value ) ) {
            foreach ( [ "url", "value", "label", "title" ] as $key ) {
                if ( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ) {
                    return trim( (string) $value[ $key ] );
                }
            }
            return "";
        }
        return is_scalar( $value ) ? trim( (string) $value ) : "";
    }

    private function has_value( $value ): bool {
        if ( null === $value || false === $value || "" === $value ) {
            return false;
        }
        return ! ( is_array( $value ) && empty( $value ) );
    }
}
