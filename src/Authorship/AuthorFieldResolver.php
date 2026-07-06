<?php
namespace smp_publication_integration\Authorship;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class AuthorFieldResolver {
    private const ALIASES = [
        "bio_short" => [ "author_bio_short", "bio_short", "short_bio", "user_short_bio", "description_short", "what_best_describe_you" ],
        "bio" => [ "author_bio", "bio", "biography", "description", "user_description" ],
        "title" => [ "author_title", "title", "role", "job_title", "position", "profession", "what_best_describe_you" ],
        "job_title" => [ "job_title", "author_job_title", "author_title", "title", "role", "position", "profession", "what_best_describe_you" ],
        "subtitle" => [ "author_subtitle", "subtitle", "tagline", "short_title", "headline" ],
        "facebook" => [ "author_facebook", "facebook", "facebook_url", "profile_facebook", "social_facebook", "url_facebook" ],
        "instagram" => [ "author_instagram", "instagram", "instagram_url", "profile_instagram", "social_instagram", "url_instagram" ],
        "x" => [ "author_x", "x", "x_url", "twitter", "twitter_url", "profile_twitter", "social_twitter", "url_x", "url_twitter" ],
        "linkedin" => [ "author_linkedin", "linkedin", "linkedin_url", "profile_linkedin", "social_linkedin", "url_linkedin" ],
        "youtube" => [ "author_youtube", "youtube", "youtube_url", "profile_youtube", "social_youtube", "url_youtube" ],
        "website" => [ "author_website", "website", "website_url", "user_url", "url", "url_website" ],
        "crunchbase" => [ "author_crunchbase", "crunchbase", "crunchbase_url", "url_crunchbase" ],
        "muckrack" => [ "author_muckrack", "author_muck_rack", "muckrack", "muckrack_url", "muck_rack_url", "muckrack_profile" ],
        "email" => [ "author_email", "email", "user_email" ],
        "image" => [ "author_image", "profile_photo", "profile_image", "headshot", "photo", "avatar" ],
    ];

    private const AVATAR_SIZES = [ 40, 80, 96, 100, 150, 300, 450 ];

    public static function aliases(): array {
        return self::ALIASES;
    }

    public function record( int $user_id ): ?AuthorRecord {
        $user = get_user_by( "id", $user_id );
        if ( ! $user instanceof \WP_User ) {
            return null;
        }

        $avatars = [];
        foreach ( self::AVATAR_SIZES as $size ) {
            $url = get_avatar_url( $user_id, [ "size" => $size ] );
            if ( is_string( $url ) && "" !== $url ) {
                $avatars[ $size ] = $url;
            }
        }

        $fields = [];
        foreach ( array_keys( self::ALIASES ) as $key ) {
            if ( "image" === $key ) {
                continue;
            }
            $fields[ $key ] = $this->value( $user_id, $key );
        }
        $fields["description"] = "" !== $fields["bio"]
            ? $fields["bio"]
            : trim( (string) get_the_author_meta( "description", $user_id ) );
        $fields["email"] = "" !== (string) $user->user_email ? "mailto:" . $user->user_email : "";

        return new AuthorRecord(
            $user_id,
            (string) $user->display_name,
            (string) $user->user_nicename,
            get_author_posts_url( $user_id ),
            (string) $user->user_email,
            (string) ( $avatars[300] ?? get_avatar_url( $user_id, [ "size" => 300 ] ) ),
            $avatars,
            $fields
        );
    }

    public function value( int $user_id, string $key ): string {
        $aliases = self::ALIASES[ $key ] ?? [ $key ];
        foreach ( $aliases as $field ) {
            $value = $this->raw_value( $user_id, (string) $field );
            if ( null === $value || false === $value || "" === $value || is_object( $value ) ) {
                continue;
            }
            if ( is_array( $value ) ) {
                foreach ( [ "url", "value", "label", "title" ] as $array_key ) {
                    if ( isset( $value[ $array_key ] ) && is_scalar( $value[ $array_key ] ) ) {
                        return trim( (string) $value[ $array_key ] );
                    }
                }
                continue;
            }
            return trim( (string) $value );
        }
        return "";
    }

    public function social_url( int $user_id, string $key ): string {
        $value = trim( $this->value( $user_id, $key ) );
        if ( "" === $value ) {
            return "";
        }
        if ( "email" === $key ) {
            return 0 === stripos( $value, "mailto:" ) ? $value : "mailto:" . $value;
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
            "linkedin" => "https://linkedin.com/in/",
            "youtube" => "https://youtube.com/",
            "crunchbase" => "https://crunchbase.com/person/",
            "muckrack" => "https://muckrack.com/",
        ];
        return isset( $bases[ $key ] ) ? $bases[ $key ] . rawurlencode( $handle ) : "";
    }

    public function image_url( int $user_id, string $size = "thumbnail" ): string {
        foreach ( self::ALIASES["image"] as $field ) {
            $value = $this->raw_value( $user_id, $field );
            $url = $this->image_value_to_url( $value, $size );
            if ( "" !== $url ) {
                return $url;
            }
        }
        $pixels = [ "thumbnail" => 150, "medium" => 300, "medium_large" => 768, "large" => 1024, "full" => 1024 ];
        $url = get_avatar_url( $user_id, [ "size" => $pixels[ $size ] ?? 150 ] );
        return is_string( $url ) ? $url : "";
    }

    private function raw_value( int $user_id, string $field ) {
        if ( function_exists( "get_field" ) ) {
            $value = get_field( $field, "user_" . $user_id );
            if ( null !== $value && false !== $value && "" !== $value && [] !== $value ) {
                return $value;
            }
        }
        $value = get_user_meta( $user_id, $field, true );
        if ( "" !== $value && null !== $value && false !== $value ) {
            return $value;
        }
        return get_the_author_meta( $field, $user_id );
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
        return is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ? $value : "";
    }
}
