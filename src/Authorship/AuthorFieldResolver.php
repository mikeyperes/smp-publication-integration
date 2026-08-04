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
        "image" => [ "author_image", "profile_photo", "profile_image", "profile_picture", "featured_image", "headshot", "photo", "avatar", "profile_photo_id", "avatar_media_id", "wp_user_avatar", "simple_local_avatar", "wp_user_avatars" ],
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
        $explicit_url = $this->explicit_image_url( $user_id, $size );
        if ( "" !== $explicit_url ) {
            return $explicit_url;
        }
        $pixels = [ "thumbnail" => 150, "medium" => 300, "medium_large" => 768, "large" => 1024, "full" => 1024 ];
        $url = get_avatar_url( $user_id, [ "size" => $pixels[ $size ] ?? 150 ] );
        return is_string( $url ) ? $url : "";
    }

    public function explicit_image_url( int $user_id, string $size = "thumbnail" ): string {
        foreach ( self::ALIASES["image"] as $field ) {
            $value = $this->raw_value( $user_id, $field );
            $url = $this->image_value_to_url( $value, $size );
            if ( "" !== $url ) {
                return $url;
            }
        }
        return "";
    }

    public function explicit_image_attachment_id( int $user_id ): int {
        foreach ( self::ALIASES["image"] as $field ) {
            $attachment_id = $this->image_value_to_attachment_id( $this->raw_value( $user_id, $field ) );
            if ( 0 < $attachment_id ) {
                return $attachment_id;
            }
        }
        return 0;
    }

    public function has_explicit_image( int $user_id ): bool {
        return "" !== $this->explicit_image_url( $user_id, "thumbnail" );
    }

    /**
     * Default avatar URLs do not count as profile images. WordPress core and
     * avatar plugins expose the reliable found_avatar signal for remote/local
     * avatars that are not already represented by explicit user metadata.
     */
    public function has_profile_image( int $user_id ): bool {
        if ( $this->has_explicit_image( $user_id ) ) {
            return (bool) apply_filters( "smpi_author_has_profile_image", true, $user_id, "explicit" );
        }

        $found = false;
        if ( function_exists( "get_avatar_data" ) ) {
            $data = get_avatar_data( $user_id, [ "size" => 96, "force_default" => false ] );
            $url = is_array( $data ) && isset( $data["url"] ) && is_string( $data["url"] ) ? $data["url"] : "";
            $found = is_array( $data ) && ! empty( $data["found_avatar"] ) && "" !== $url && (bool) filter_var( $url, FILTER_VALIDATE_URL );
        }

        return (bool) apply_filters( "smpi_author_has_profile_image", $found, $user_id, $found ? "found_avatar" : "default_avatar" );
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
        if ( is_string( $value ) && function_exists( "maybe_unserialize" ) ) {
            $unserialized = maybe_unserialize( $value );
            if ( $unserialized !== $value ) {
                return $this->image_value_to_url( $unserialized, $size );
            }
        }

        $attachment_id = $this->image_value_to_attachment_id( $value );
        if ( 0 < $attachment_id ) {
            $url = wp_get_attachment_image_url( $attachment_id, $size );
            if ( is_string( $url ) && "" !== $url ) {
                return $url;
            }
        }

        if ( is_array( $value ) ) {
            if ( isset( $value["sizes"][ $size ] ) && is_string( $value["sizes"][ $size ] ) ) {
                return $value["sizes"][ $size ];
            }
            if ( isset( $value["sizes"] ) && is_array( $value["sizes"] ) ) {
                foreach ( $value["sizes"] as $candidate ) {
                    if ( is_string( $candidate ) && filter_var( $candidate, FILTER_VALIDATE_URL ) ) {
                        return $candidate;
                    }
                }
            }
            $pixels = [ "thumbnail" => 150, "medium" => 300, "medium_large" => 768, "large" => 1024 ];
            $url_keys = isset( $pixels[ $size ] ) ? [ $size, $pixels[ $size ], "full", "url" ] : [ $size, "full", "url" ];
            foreach ( $url_keys as $url_key ) {
                if ( isset( $value[ $url_key ] ) && is_string( $value[ $url_key ] ) && filter_var( $value[ $url_key ], FILTER_VALIDATE_URL ) ) {
                    return $value[ $url_key ];
                }
            }
            foreach ( $value as $key => $candidate ) {
                if ( is_numeric( $key ) && is_string( $candidate ) && filter_var( $candidate, FILTER_VALIDATE_URL ) ) {
                    return $candidate;
                }
            }
        }
        return is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ? $value : "";
    }

    private function image_value_to_attachment_id( $value ): int {
        if ( is_string( $value ) && function_exists( "maybe_unserialize" ) ) {
            $unserialized = maybe_unserialize( $value );
            if ( $unserialized !== $value ) {
                return $this->image_value_to_attachment_id( $unserialized );
            }
        }

        if ( is_numeric( $value ) ) {
            return abs( (int) $value );
        }

        if ( is_array( $value ) ) {
            foreach ( [ "ID", "id", "media_id", "attachment_id" ] as $id_key ) {
                if ( isset( $value[ $id_key ] ) && is_numeric( $value[ $id_key ] ) ) {
                    return abs( (int) $value[ $id_key ] );
                }
            }

            foreach ( [ "full", "url" ] as $url_key ) {
                if ( isset( $value[ $url_key ] ) ) {
                    $attachment_id = $this->attachment_id_from_url( $value[ $url_key ] );
                    if ( 0 < $attachment_id ) {
                        return $attachment_id;
                    }
                }
            }
        }

        return $this->attachment_id_from_url( $value );
    }

    private function attachment_id_from_url( $value ): int {
        if ( ! is_string( $value ) || ! filter_var( $value, FILTER_VALIDATE_URL ) || ! function_exists( "attachment_url_to_postid" ) ) {
            return 0;
        }
        return abs( (int) attachment_url_to_postid( $value ) );
    }
}
