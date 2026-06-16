<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Fields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Schema {
    public function register(): void {
        add_action( 'save_post_' . PublicationPostType::POST_TYPE, [ $this, 'save_schema' ], 20, 2 );
        add_action( 'wp_head', [ $this, 'inject_schema' ], 1 );
        add_action( 'wp_ajax_smpi_reprocess_schema', [ $this, 'ajax_reprocess_schema' ] );
    }

    public function save_schema( int $post_id, \WP_Post $post ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || PublicationPostType::POST_TYPE !== $post->post_type ) {
            return;
        }

        $this->store_schema( $post_id );
    }

    public function inject_schema(): void {
        if ( ! is_singular( PublicationPostType::POST_TYPE ) ) {
            return;
        }

        $post_id = get_queried_object_id();
        if ( ! $post_id ) {
            return;
        }

        $schema = $this->get_stored_schema( $post_id );
        if ( '' === $schema ) {
            $schema = $this->generate_schema_json( $post_id );
        }

        if ( '' !== $schema ) {
            echo "\n" . '<script type="application/ld+json">' . $schema . '</script>' . "\n";
        }
    }

    public function ajax_reprocess_schema(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
        }

        $offset     = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
        $batch_size = isset( $_POST['batch_size'] ) ? min( 50, max( 1, (int) $_POST['batch_size'] ) ) : 20;
        $counts     = wp_count_posts( PublicationPostType::POST_TYPE );
        $total      = $counts && isset( $counts->publish ) ? (int) $counts->publish : 0;

        $query = new \WP_Query(
            [
                'post_type'      => PublicationPostType::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => $batch_size,
                'offset'         => $offset,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        $items = [];
        foreach ( $query->posts as $post_id ) {
            $schema  = $this->store_schema( (int) $post_id );
            $items[] = [
                'post_id'        => (int) $post_id,
                'title'          => get_the_title( $post_id ),
                'schema'         => $schema,
                'admin_link'     => get_edit_post_link( $post_id ),
                'view_link'      => get_permalink( $post_id ),
                'validator_link' => 'https://validator.schema.org/#url=' . rawurlencode( get_permalink( $post_id ) ),
            ];
        }

        wp_send_json_success(
            [
                'total'  => $total,
                'batch'  => count( $items ),
                'offset' => $offset,
                'items'  => $items,
            ]
        );
    }

    public function store_schema( int $post_id ): string {
        $schema = $this->generate_schema_json( $post_id );

        if ( function_exists( 'update_field' ) ) {
            update_field( 'smpi_schema_markup', $schema, $post_id );
        }

        update_post_meta( $post_id, '_smpi_schema_markup', $schema );
        return $schema;
    }

    public function get_stored_schema( int $post_id ): string {
        $schema = '';
        if ( function_exists( 'get_field' ) ) {
            $schema = (string) get_field( 'smpi_schema_markup', $post_id, false );
        }

        if ( '' === trim( $schema ) ) {
            $schema = (string) get_post_meta( $post_id, '_smpi_schema_markup', true );
        }

        return trim( $schema );
    }

    public function generate_schema_json( int $post_id ): string {
        $schema = $this->generate_schema_array( $post_id );
        return wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    }


    public function generate_schema_array( int $post_id ): array {
        $website  = Fields::get( $post_id, "website" );
        $summary  = Fields::get( $post_id, "summary" );
        $mission  = Fields::get( $post_id, "mission_statement" );
        $logo     = Fields::get( $post_id, "logo" );
        $founders = Fields::get( $post_id, "founders", [] );
        $founder_users = Fields::get( $post_id, "founder_users", [] );
        $founding_date = Fields::get( $post_id, "founding_date" );
        $headquarters = Fields::get( $post_id, "headquarters" );
        $headquarters_wiki = Fields::get( $post_id, "headquarters_wikipedia_url" );
        $contact_email = Fields::get( $post_id, "contact_email" );
        $google_news_url = Fields::get( $post_id, "google_news_url" );

        $founder_items = array_merge( $this->normalize_founders( $founders ), $this->normalize_founder_users( $founder_users ) );
        $same_as = array_values( array_filter( [ $google_news_url, $headquarters_wiki ] ) );

        $schema = [
            "@context" => "https://schema.org",
            "@type"    => "NewsMediaOrganization",
            "@id"      => trailingslashit( get_permalink( $post_id ) ) . "#organization",
            "name"     => get_the_title( $post_id ),
            "url"      => $website ?: get_permalink( $post_id ),
            "mainEntityOfPage" => get_permalink( $post_id ),
            "description"      => wp_strip_all_tags( (string) ( $summary ?: $mission ?: get_the_excerpt( $post_id ) ) ),
            "slogan"           => wp_strip_all_tags( (string) $mission ),
            "logo"             => $this->normalize_logo( $logo ),
            "founder"          => $founder_items,
            "foundingDate"     => $founding_date,
            "email"            => $contact_email ? sanitize_email( (string) $contact_email ) : null,
            "sameAs"           => $same_as,
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
        if ( is_array( $logo ) && ! empty( $logo['url'] ) ) {
            return esc_url_raw( (string) $logo['url'] );
        }

        if ( is_numeric( $logo ) ) {
            return wp_get_attachment_image_url( (int) $logo, 'full' ) ?: null;
        }

        return is_string( $logo ) ? esc_url_raw( $logo ) : null;
    }

    private function normalize_founders( $founders ): array {
        $founders = is_array( $founders ) ? $founders : [ $founders ];
        $items    = [];

        foreach ( $founders as $founder ) {
            $founder_id = is_object( $founder ) && isset( $founder->ID ) ? (int) $founder->ID : (int) $founder;
            if ( ! $founder_id ) {
                continue;
            }

            $items[] = [
                '@type' => 'Person',
                'name'  => get_the_title( $founder_id ),
                'url'   => get_permalink( $founder_id ),
            ];
        }

        return $items;
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

            if ( null === $value || false === $value || '' === $value || [] === $value ) {
                unset( $schema[ $key ] );
            } else {
                $schema[ $key ] = $value;
            }
        }

        return $schema;
    }
}

