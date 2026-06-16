<?php
namespace smp_publication_integration\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Fields {
    public static function get( int $post_id, string $field, $default = '' ) {
        $aliases = self::aliases()[ $field ] ?? [ $field ];

        foreach ( $aliases as $alias ) {
            $value = self::raw( $post_id, $alias );
            if ( self::has_value( $value ) ) {
                return $value;
            }
        }

        return $default;
    }

    public static function aliases(): array {
        return [
            "mission_statement" => [
                "mission_statement",
                "publication_mission_statement",
                "smpi_mission_statement",
                "smpi_mission_statement_override",
            ],
            "mission_statement_extended" => [
                "mission_statement_extended",
                "publication_mission_statement_extended",
                "smpi_mission_statement_extended",
            ],
            "summary" => [
                "short_summary",
                "publication_summary",
                "summary",
                "description",
                "smpi_publication_summary",
            ],
            "website" => [
                "url",
                "website",
                "publication_website",
                "smpi_publication_website",
            ],
            "logo" => [
                "logo",
                "publication_logo",
                "smpi_publication_logo",
            ],
            "publication_user" => [
                "publication_user",
                "smpi_publication_user",
            ],
            "founders" => [
                "founders",
                "smpi_founders",
            ],
            "founder_users" => [
                "founder_users",
                "publication_founder_users",
                "smpi_founder_users",
            ],
            "headquarters" => [
                "headquarters",
                "headquarters_location",
                "publication_headquarters",
                "smpi_headquarters",
            ],
            "headquarters_wikipedia_url" => [
                "headquarters_wikipedia_url",
                "smpi_headquarters_wikipedia_url",
            ],
            "recent_media" => [
                "recent_media",
                "publication_recent_media",
                "smpi_recent_media",
            ],
            "founding_date" => [
                "founding_date",
                "publication_founding_date",
                "smpi_founding_date",
            ],
            "founding_date_extended" => [
                "founding_date_extended",
                "smpi_founding_date_extended",
            ],
            "headquarters_extended" => [
                "headquarters_extended",
                "smpi_headquarters_extended",
            ],
            "contact" => [
                "contact",
                "publication_contact",
                "smpi_contact",
            ],
            "contact_email" => [
                "contact_email",
                "public_contact_email",
                "smpi_contact_email",
            ],
            "dmca" => [
                "dmca",
                "dmca_policy",
                "smpi_dmca",
            ],
            "terms" => [
                "terms_of_use",
                "terms",
                "smpi_terms_of_use",
            ],
            "privacy" => [
                "privacy_policy",
                "privacy",
                "smpi_privacy_policy",
            ],
            "become_contributor" => [
                "become_contributor",
                "become_a_contributor",
                "smpi_become_contributor",
            ],
            "has_podcast" => [
                "has_podcast",
                "publication_has_podcast",
                "smpi_has_podcast",
            ],
            "rss_feed" => [
                "rss_feed",
                "publication_rss_feed",
                "smpi_rss_feed",
            ],
            "google_news_url" => [
                "google_news_url",
                "smpi_google_news_url",
            ],
        ];
    }

    public static function option( string $field, $default = "" ) {
        $aliases = self::aliases()[ $field ] ?? [ $field ];

        foreach ( $aliases as $alias ) {
            $value = self::raw_option( $alias );
            if ( self::has_value( $value ) ) {
                return $value;
            }
        }

        return $default;
    }

    public static function raw_option( string $field ) {
        if ( function_exists( "get_field" ) ) {
            $value = get_field( $field, "option" );
            if ( self::has_value( $value ) ) {
                return $value;
            }
        }

        $value = get_option( "options_" . $field, null );
        if ( self::has_value( $value ) ) {
            return $value;
        }

        return get_option( $field, "" );
    }

    public static function raw( int $post_id, string $field ) {
        if ( function_exists( 'get_field' ) ) {
            $value = get_field( $field, $post_id );
            if ( self::has_value( $value ) ) {
                return $value;
            }
        }

        return get_post_meta( $post_id, $field, true );
    }

    public static function has_value( $value ): bool {
        if ( null === $value || false === $value || '' === $value ) {
            return false;
        }

        return ! ( is_array( $value ) && empty( $value ) );
    }
}

