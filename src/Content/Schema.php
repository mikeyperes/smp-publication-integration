<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Fields;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class Schema {
    public function register(): void {
        add_action( "wp_head", [ $this, "inject_schema" ], 1 );
        add_action( "wp_ajax_smpi_reprocess_schema", [ $this, "ajax_reprocess_schema" ] );
    }

    public function inject_schema(): void {
        if ( ! is_front_page() && ! is_home() ) {
            return;
        }

        $schema = $this->get_stored_schema();
        if ( "" === $schema ) {
            $schema = $this->generate_schema_json();
        }

        if ( "" !== $schema ) {
            echo "\n" . "<script type=\"application/ld+json\">" . $schema . "</script>" . "\n";
        }
    }

    public function ajax_reprocess_schema(): void {
        if ( ! current_user_can( "manage_options" ) ) {
            wp_send_json_error( [ "message" => "Permission denied." ], 403 );
        }
        check_ajax_referer( \smp_publication_integration\Admin\Ajax::NONCE, "nonce" );

        $schema = $this->store_schema();
        wp_send_json_success(
            [
                "total" => 1,
                "batch" => 1,
                "offset" => 0,
                "items" => [
                    [
                        "title" => get_bloginfo( "name" ),
                        "schema" => $schema,
                        "admin_link" => admin_url( "options-general.php?page=smp-publication-integration&tab=publication_options" ),
                        "view_link" => home_url( "/" ),
                        "validator_link" => "https://validator.schema.org/#url=" . rawurlencode( home_url( "/" ) ),
                    ],
                ],
            ]
        );
    }

    public function store_schema(): string {
        $schema = $this->generate_schema_json();

        if ( function_exists( "update_field" ) ) {
            update_field( "smpi_schema_markup", $schema, "option" );
        }

        update_option( "_smpi_schema_markup", $schema, false );
        return $schema;
    }

    public function get_stored_schema(): string {
        $schema = "";
        if ( function_exists( "get_field" ) ) {
            $schema = (string) get_field( "smpi_schema_markup", "option", false );
        }

        if ( "" === trim( $schema ) ) {
            $schema = (string) get_option( "_smpi_schema_markup", "" );
        }

        return trim( $schema );
    }

    public function generate_schema_json(): string {
        $schema = $this->generate_schema_array();
        return wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    }

    public function generate_schema_array(): array {
        $website = Fields::option( "website", home_url( "/" ) );
        $summary = Fields::option( "summary" );
        $mission = Fields::option( "mission_statement" );
        $logo = Fields::option( "logo" );
        $founders = Fields::option( "founders", [] );
        $founder_users = Fields::option( "founder_users", [] );
        $founding_date = Fields::option( "founding_date" );
        $headquarters = Fields::option( "headquarters" );
        $headquarters_wiki = Fields::option( "headquarters_wikipedia_url" );
        $contact_email = Fields::option( "contact_email" );
        $google_news_url = Fields::option( "google_news_url" );

        $same_as = array_values( array_filter( [ $google_news_url, $headquarters_wiki ] ) );
        $logo_url = $this->normalize_logo( $logo ) ?: get_site_icon_url( 512 );

        $schema = [
            "@context" => "https://schema.org",
            "@type" => "NewsMediaOrganization",
            "@id" => trailingslashit( home_url( "/" ) ) . "#organization",
            "name" => get_bloginfo( "name" ),
            "url" => $website ?: home_url( "/" ),
            "mainEntityOfPage" => home_url( "/" ),
            "description" => wp_strip_all_tags( (string) ( $summary ?: $mission ?: get_bloginfo( "description" ) ) ),
            "slogan" => wp_strip_all_tags( (string) $mission ),
            "logo" => $logo_url,
            "founder" => array_merge( $this->normalize_founders( $founders ), $this->normalize_founder_users( $founder_users ) ),
            "foundingDate" => $founding_date,
            "email" => $contact_email ? sanitize_email( (string) $contact_email ) : null,
            "sameAs" => $same_as,
        ];

        if ( $headquarters ) {
            $schema["location"] = [
                "@type" => "Place",
                "name" => wp_strip_all_tags( (string) $headquarters ),
                "sameAs" => $headquarters_wiki ?: null,
            ];
        }

        return $this->clean_schema( $schema );
    }

    private function normalize_logo( $logo ) {
        if ( is_array( $logo ) && ! empty( $logo["url"] ) ) {
            return esc_url_raw( (string) $logo["url"] );
        }

        if ( is_numeric( $logo ) ) {
            return wp_get_attachment_image_url( (int) $logo, "full" ) ?: null;
        }

        return is_string( $logo ) ? esc_url_raw( $logo ) : null;
    }

    private function normalize_founders( $founders ): array {
        $founders = is_array( $founders ) ? $founders : [ $founders ];
        $items = [];

        foreach ( $founders as $founder ) {
            $founder_id = $this->founder_profile_id( $founder );
            if ( ! $founder_id ) {
                continue;
            }

            $items[] = [
                "@type" => "Person",
                "name" => get_the_title( $founder_id ),
                "url" => get_permalink( $founder_id ),
            ];
        }

        return $items;
    }

    private function founder_profile_id( $founder ): int {
        if ( is_array( $founder ) && isset( $founder["profile"] ) ) {
            $founder = $founder["profile"];
        }
        if ( is_object( $founder ) && isset( $founder->ID ) ) {
            return (int) $founder->ID;
        }
        return is_numeric( $founder ) ? (int) $founder : 0;
    }

    private function normalize_founder_users( $users ): array {
        $users = is_array( $users ) ? $users : [ $users ];
        $items = [];

        foreach ( $users as $user_id ) {
            $user_id = is_object( $user_id ) && isset( $user_id->ID ) ? (int) $user_id->ID : (int) $user_id;
            $user = $user_id ? get_user_by( "id", $user_id ) : false;
            if ( ! $user ) {
                continue;
            }

            $items[] = [
                "@type" => "Person",
                "name" => $user->display_name,
                "url" => get_author_posts_url( $user_id ),
            ];
        }

        return $items;
    }

    private function clean_schema( array $schema ): array {
        foreach ( $schema as $key => $value ) {
            if ( is_array( $value ) ) {
                $value = $this->clean_schema( $value );
            }

            if ( null === $value || false === $value || "" === $value || [] === $value ) {
                unset( $schema[ $key ] );
            } else {
                $schema[ $key ] = $value;
            }
        }

        return $schema;
    }
}
