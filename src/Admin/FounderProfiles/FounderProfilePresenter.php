<?php

namespace smp_publication_integration\Admin\FounderProfiles;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FounderProfilePresenter {
    private const GROUPS = [
        'identity'   => 'Identity',
        'biography'  => 'Biography',
        'contact'    => 'Contact',
        'social'     => 'Web & Social',
        'personal'   => 'Personal',
        'career'     => 'Career & Affiliations',
        'media'      => 'Media',
        'additional' => 'Additional Profile Fields',
        'record'     => 'WordPress Record',
    ];

    private const EXCLUDED_KEYS = [
        'post_id',
        'schema_markup',
        'rank_math_seo_score',
        'rank_math_analytic_object_id',
    ];

    private const BOOLEAN_KEYS = [
        'featured',
        'is_contributor',
        'is_council_member',
        'social_profiles_muckrack_verified',
        'staff_writer',
        'team_member',
    ];

    /** @param array<int,mixed> $profile_ids */
    public function collection_html( array $profile_ids ): string {
        $html = '';
        foreach ( array_values( array_unique( array_map( 'absint', $profile_ids ) ) ) as $profile_id ) {
            $html .= $this->card_html( $profile_id );
        }

        return '' !== $html ? $html : $this->empty_html();
    }

    public function empty_html(): string {
        return '<div class="smpi-empty-founder-profiles"><span class="dashicons dashicons-groups" aria-hidden="true"></span><div><strong>No founder profiles selected</strong><p>Search above to connect the first Verified Profile.</p></div></div>';
    }

    public function card_html( int $profile_id ): string {
        $post = get_post( $profile_id );
        if ( ! $post instanceof \WP_Post || 'profile' !== get_post_type( $post ) ) {
            return '';
        }

        $title       = get_the_title( $post ) ?: 'Profile #' . (string) $profile_id;
        $status      = get_post_status( $post ) ?: 'unknown';
        $job_title   = $this->first_meta_value( $profile_id, [ 'title' ] );
        $short_bio   = $this->first_meta_value( $profile_id, [ 'biography_short', 'short_description' ] );
        $website     = $this->first_meta_value( $profile_id, [ 'url_website', 'url', 'url_website_url' ] );
        $email       = $this->first_meta_value( $profile_id, [ 'contact_information_email_email', 'email' ] );
        $groups      = $this->profile_groups( $post, [ 'title', 'biography_short', 'url_website', 'url', 'contact_information_email_email', 'email' ] );
        $field_count = array_sum( array_map( static fn( array $group ): int => count( $group['fields'] ), $groups ) );
        $photo       = get_the_post_thumbnail(
            $profile_id,
            'thumbnail',
            [
                'class'   => 'smpi-founder-photo',
                'alt'     => $title . ' profile photo',
                'loading' => 'lazy',
            ]
        );

        if ( '' === $photo ) {
            $photo = '<span class="dashicons dashicons-id-alt" aria-hidden="true"></span>';
        }

        $html  = '<article class="smpi-founder-profile-card" data-profile-id="' . esc_attr( (string) $profile_id ) . '">';
        $html .= '<header class="smpi-founder-profile-head"><div class="smpi-founder-thumb">' . $photo . '</div>';
        $html .= '<div class="smpi-founder-info"><h4>' . esc_html( $title ) . '</h4>';
        if ( '' !== $job_title ) {
            $html .= '<p class="smpi-founder-role">' . esc_html( wp_strip_all_tags( $job_title ) ) . '</p>';
        }
        $html .= '<p class="smpi-founder-record-line">Verified Profile #' . esc_html( (string) $profile_id ) . ' &middot; ' . esc_html( $this->status_label( $status ) ) . ' &middot; ' . esc_html( (string) $field_count ) . ' populated fields</p></div>';
        $html .= '<div class="smpi-founder-actions"><a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="' . esc_url( get_edit_post_link( $profile_id, 'raw' ) ) . '">Edit</a><a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="' . esc_url( get_permalink( $profile_id ) ) . '">View</a><button type="button" class="button-link-delete smpi-remove-founder-profile">Remove</button></div></header>';

        if ( '' !== $short_bio || '' !== $website || '' !== $email ) {
            $html .= '<div class="smpi-founder-overview">';
            if ( '' !== $short_bio ) {
                $html .= '<div class="smpi-founder-summary"><h5>Profile summary</h5>' . wpautop( wp_kses_post( $short_bio ) ) . '</div>';
            }
            if ( '' !== $website || '' !== $email ) {
                $html .= '<dl class="smpi-founder-primary-links">';
                if ( '' !== $website ) {
                    $html .= $this->definition_html( 'Website', $this->linked_value_html( $website ) );
                }
                if ( '' !== $email ) {
                    $html .= $this->definition_html( 'Email', '<a href="mailto:' . esc_attr( sanitize_email( $email ) ) . '">' . esc_html( $email ) . '</a>' );
                }
                $html .= '</dl>';
            }
            $html .= '</div>';
        }

        $html .= '<div class="smpi-founder-detail-groups">';
        foreach ( $groups as $group ) {
            $html .= '<section class="smpi-founder-detail-group" data-profile-group="' . esc_attr( $group['key'] ) . '"><div class="smpi-founder-detail-heading"><h5>' . esc_html( $group['label'] ) . '</h5>' . CoreUi::pill( count( $group['fields'] ) . ' fields', 'dark' ) . '</div><dl class="smpi-founder-field-list">';
            foreach ( $group['fields'] as $field ) {
                $html .= $this->definition_html( $field['label'], $field['html'], $field['key'] );
            }
            $html .= '</dl></section>';
        }
        $html .= '</div></article>';

        return $html;
    }

    /** @param array<int,string> $consumed_keys @return array<int,array{key:string,label:string,fields:array<int,array{key:string,label:string,html:string}>}> */
    private function profile_groups( \WP_Post $post, array $consumed_keys ): array {
        $meta       = $this->raw_meta( (int) $post->ID );
        $repeaters  = $this->extract_repeaters( $meta );
        $child_keys = [];
        foreach ( $repeaters as $repeater ) {
            $child_keys = array_merge( $child_keys, $repeater['keys'] );
        }

        $groups = [];
        foreach ( self::GROUPS as $key => $label ) {
            $groups[ $key ] = [ 'key' => $key, 'label' => $label, 'fields' => [] ];
        }

        $seen_urls = [];
        foreach ( $this->ordered_meta( $meta ) as $key => $value ) {
            if ( in_array( $key, $consumed_keys, true ) || in_array( $key, $child_keys, true ) || isset( $repeaters[ $key ] ) ) {
                continue;
            }
            if ( ! $this->is_profile_key( (int) $post->ID, $key ) || ! $this->has_value( $key, $value ) || $this->is_legacy_duplicate( $key, $meta ) ) {
                continue;
            }
            $fingerprint = $this->url_fingerprint( $value );
            if ( '' !== $fingerprint && isset( $seen_urls[ $fingerprint ] ) ) {
                continue;
            }
            if ( '' !== $fingerprint ) {
                $seen_urls[ $fingerprint ] = true;
            }
            $group_key = $this->group_for_key( $key );
            $groups[ $group_key ]['fields'][] = [
                'key'   => $key,
                'label' => $this->field_label( (int) $post->ID, $key ),
                'html'  => $this->value_html( (int) $post->ID, $key, $value ),
            ];
        }

        foreach ( $repeaters as $key => $repeater ) {
            if ( empty( $repeater['rows'] ) ) {
                continue;
            }
            $group_key = $this->group_for_key( $key );
            $groups[ $group_key ]['fields'][] = [
                'key'   => $key,
                'label' => $this->field_label( (int) $post->ID, $key ),
                'html'  => $this->repeater_html( (int) $post->ID, $repeater['rows'] ),
            ];
        }

        $groups['record']['fields'] = $this->record_fields( $post );

        return array_values( array_filter( $groups, static fn( array $group ): bool => ! empty( $group['fields'] ) ) );
    }

    /** @return array<string,mixed> */
    private function raw_meta( int $profile_id ): array {
        $meta = [];
        foreach ( get_post_meta( $profile_id ) as $key => $values ) {
            if ( ! is_string( $key ) || str_starts_with( $key, '_' ) ) {
                continue;
            }
            $value        = is_array( $values ) && 1 === count( $values ) ? reset( $values ) : $values;
            $meta[ $key ] = maybe_unserialize( $value );
        }
        return $meta;
    }

    /** @param array<string,mixed> $meta @return array<string,mixed> */
    private function ordered_meta( array $meta ): array {
        uksort(
            $meta,
            static function ( string $left, string $right ): int {
                $priority = static function ( string $key ): int {
                    if ( str_starts_with( $key, 'url_' ) || 'master_verified_profile_id' === $key ) {
                        return 0;
                    }
                    if ( str_starts_with( $key, 'social_profiles_' ) || 'url' === $key || 'smp_master_verified_profile_id' === $key ) {
                        return 20;
                    }
                    return 10;
                };
                return [ $priority( $left ), $left ] <=> [ $priority( $right ), $right ];
            }
        );
        return $meta;
    }

    /** @param array<string,mixed> $meta @return array<string,array{rows:array<int,array<string,mixed>>,keys:array<int,string>}> */
    private function extract_repeaters( array $meta ): array {
        $repeaters = [];
        foreach ( $meta as $key => $value ) {
            if ( ! preg_match( '/^(.+)_([0-9]+)_(.+)$/', $key, $match ) || ! $this->has_value( $key, $value ) ) {
                continue;
            }
            $base  = (string) $match[1];
            $index = (int) $match[2];
            $field = (string) $match[3];
            $repeaters[ $base ]['rows'][ $index ][ $field ] = $value;
            $repeaters[ $base ]['keys'][]                    = $key;
        }
        foreach ( $repeaters as &$repeater ) {
            ksort( $repeater['rows'] );
            $repeater['rows'] = array_values( $repeater['rows'] );
            $repeater['keys'] = array_values( array_unique( $repeater['keys'] ) );
        }
        unset( $repeater );
        return $repeaters;
    }

    private function is_profile_key( int $profile_id, string $key ): bool {
        if ( in_array( $key, self::EXCLUDED_KEYS, true ) || preg_match( '/^(?:rank_math_|litespeed_|_elementor_|wp_)/', $key ) ) {
            return false;
        }
        if ( preg_match( '/password|secret|token|api[_-]?key|credential|nonce|billing|payment/i', $key ) ) {
            return false;
        }
        if ( preg_match( '/^(?:url(?:_|$)|social_profiles_|contact_information_|personal_|company_|organizations_founded|books(?:_|$)|photo_gallery|logo|profile_type|featured|title$|alternate_names|short_description|biography(?:_short)?|google_knowledge_graph_id|(?:smp_)?master_verified_profile_id|is_|team_member|staff_writer|what_best_describe_you|hexa_pr_wire_|additional_hexa_pr_wire_releases|notable_recognitions|did_you_knows|founded|headquarters|contributor_profile)/', $key ) ) {
            return true;
        }
        $field_key = get_post_meta( $profile_id, '_' . $key, true );
        return is_string( $field_key ) && str_starts_with( $field_key, 'field_' );
    }

    /** @param array<string,mixed> $meta */
    private function is_legacy_duplicate( string $key, array $meta ): bool {
        $aliases = [
            'url'                           => 'url_website',
            'smp_master_verified_profile_id' => 'master_verified_profile_id',
            'social_profiles_linkedin'      => 'url_linkedin',
            'social_profiles_crunchbase'    => 'url_crunchbase',
            'social_profiles_facebook'      => 'url_facebook',
            'social_profiles_twitter'       => 'url_x',
            'social_profiles_instagram'     => 'url_instagram',
            'social_profiles_tiktok'        => 'url_tiktok',
            'social_profiles_wikipedia'     => 'url_wikipedia',
            'social_profiles_imdb'          => 'url_imdb',
            'social_profiles_muckrack_url'  => 'url_muckrack',
            'social_profiles_soundcloud'    => 'url_soundcloud',
            'social_profiles_amazon_author' => 'url_amazon',
            'social_profiles_audible'       => 'url_audible',
            'social_profiles_github'        => 'url_github',
            'social_profiles_f6s'           => 'url_f6s',
            'social_profiles_youtube'       => 'url_youtube',
            'social_profiles_angel_list'    => 'url_angellist',
        ];
        return isset( $aliases[ $key ], $meta[ $aliases[ $key ] ] ) && $this->has_value( $aliases[ $key ], $meta[ $aliases[ $key ] ] );
    }

    private function has_value( string $key, $value ): bool {
        if ( in_array( $key, self::BOOLEAN_KEYS, true ) || str_ends_with( $key, '_preferred' ) ) {
            return true;
        }
        if ( null === $value || false === $value || '' === $value || [] === $value ) {
            return false;
        }
        if ( is_string( $value ) ) {
            $value = trim( $value );
            return '' !== $value && 1 === preg_match( '/[\pL\pN@]/u', wp_strip_all_tags( $value ) );
        }
        return true;
    }

    private function group_for_key( string $key ): string {
        if ( preg_match( '/(?:photo|image|gallery|logo)/', $key ) ) return 'media';
        if ( preg_match( '/^(?:url(?:_|$)|social_profiles_)/', $key ) ) return 'social';
        if ( preg_match( '/^(?:contact_information_|email|phone)/', $key ) ) return 'contact';
        if ( preg_match( '/(?:biography|description|did_you_know)/', $key ) ) return 'biography';
        if ( str_starts_with( $key, 'personal_' ) ) return 'personal';
        if ( preg_match( '/(?:organization|book|team_member|staff_writer|council|contributor|hexa_pr_wire|recognition|founded|headquarters)/', $key ) ) return 'career';
        if ( preg_match( '/(?:title|profile_type|alternate_name|knowledge_graph|master_verified_profile|featured)/', $key ) ) return 'identity';
        return 'additional';
    }

    private function field_label( int $profile_id, string $key ): string {
        $labels = [
            'biography_short'                       => 'Short Biography',
            'contact_information_calendly_preferred' => 'Calendly Preferred',
            'contact_information_email_preferred'   => 'Email Preferred',
            'contact_information_signal_preferred'  => 'Signal Preferred',
            'contact_information_telegram_preferred' => 'Telegram Preferred',
            'contact_information_whatsapp_preferred' => 'WhatsApp Preferred',
            'google_knowledge_graph_id'             => 'Google Knowledge Graph ID',
            'master_verified_profile_id'            => 'Master Verified Profile ID',
            'organizations_founded'                 => 'Organizations Founded',
            'personal_current_residence_name'       => 'Current Residence',
            'personal_education'                    => 'Education',
            'personal_location_born_name'           => 'Place of Birth',
            'social_profiles_muckrack_verified'     => 'MuckRack Verified',
            'what_best_describe_you'                => 'Profile Category',
        ];
        if ( isset( $labels[ $key ] ) ) {
            return $labels[ $key ];
        }

        $field_key = get_post_meta( $profile_id, '_' . $key, true );
        if ( is_string( $field_key ) && function_exists( 'acf_get_field' ) ) {
            $field = acf_get_field( $field_key );
            if ( is_array( $field ) && ! empty( $field['label'] ) ) {
                return (string) $field['label'];
            }
        }

        $label = preg_replace( '/^(?:social_profiles_|contact_information_|personal_|company_|url_)/', '', $key ) ?: $key;
        $label = ucwords( str_replace( '_', ' ', $label ) );
        return str_replace( [ 'Imdb', 'F6s', 'Id', 'Url', 'Muckrack', 'Tiktok', 'Angellist' ], [ 'IMDb', 'F6S', 'ID', 'URL', 'MuckRack', 'TikTok', 'AngelList' ], $label );
    }

    private function value_html( int $profile_id, string $key, $value ): string {
        if ( in_array( $key, self::BOOLEAN_KEYS, true ) || str_ends_with( $key, '_preferred' ) ) {
            return CoreUi::pill( filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? 'Yes' : 'No', filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? 'success' : 'dark' );
        }
        if ( is_array( $value ) ) {
            return $this->array_html( $profile_id, $key, $value );
        }
        if ( 'contributor_profile' === $key && is_numeric( $value ) ) {
            $user = get_user_by( 'id', absint( $value ) );
            if ( $user instanceof \WP_User ) {
                return '<a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $user->display_name ) . '</a> <span class="smpi-founder-value-meta">User #' . esc_html( (string) $user->ID ) . '</span>';
            }
        }
        if ( is_numeric( $value ) && preg_match( '/(?:organization|profile|book)$/', $key ) ) {
            $linked_post = get_post( absint( $value ) );
            if ( $linked_post instanceof \WP_Post ) {
                return '<a href="' . esc_url( get_edit_post_link( $linked_post->ID, 'raw' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( get_the_title( $linked_post ) ?: 'Post #' . $linked_post->ID ) . '</a> <span class="smpi-founder-value-meta">#' . esc_html( (string) $linked_post->ID ) . '</span>';
            }
        }
        if ( is_string( $value ) && is_email( $value ) ) {
            return '<a href="mailto:' . esc_attr( sanitize_email( $value ) ) . '">' . esc_html( $value ) . '</a>';
        }
        if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
            return $this->linked_value_html( $value );
        }
        if ( preg_match( '/(?:biography|description|summary)/', $key ) ) {
            return '<div class="smpi-founder-long-value">' . wpautop( wp_kses_post( (string) $value ) ) . '</div>';
        }
        return esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value, JSON_UNESCAPED_SLASHES ) );
    }

    /** @param array<int|string,mixed> $value */
    private function array_html( int $profile_id, string $key, array $value ): string {
        if ( preg_match( '/(?:photo|image|gallery|logo)/', $key ) ) {
            $images = '';
            array_walk_recursive(
                $value,
                static function ( $item ) use ( &$images ): void {
                    $attachment_id = absint( $item );
                    if ( $attachment_id ) {
                        $images .= wp_get_attachment_image( $attachment_id, 'thumbnail', false, [ 'loading' => 'lazy' ] );
                    }
                }
            );
            if ( '' !== $images ) {
                return '<div class="smpi-founder-media-grid">' . $images . '</div>';
            }
        }

        $items = [];
        array_walk_recursive(
            $value,
            static function ( $item ) use ( &$items ): void {
                if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
                    $items[] = trim( (string) $item );
                }
            }
        );
        if ( empty( $items ) ) {
            return '<span class="smpi-founder-value-meta">No populated values</span>';
        }
        return '<ul class="smpi-founder-value-list"><li>' . implode( '</li><li>', array_map( 'esc_html', array_values( array_unique( $items ) ) ) ) . '</li></ul>';
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function repeater_html( int $profile_id, array $rows ): string {
        $html = '<div class="smpi-founder-repeater">';
        foreach ( $rows as $index => $row ) {
            $html .= '<div class="smpi-founder-repeater-row"><span class="smpi-founder-repeater-index">' . esc_html( (string) ( $index + 1 ) ) . '</span><dl>';
            foreach ( $row as $key => $value ) {
                $html .= $this->definition_html( $this->field_label( $profile_id, (string) $key ), $this->value_html( $profile_id, (string) $key, $value ), (string) $key );
            }
            $html .= '</dl></div>';
        }
        return $html . '</div>';
    }

    /** @return array<int,array{key:string,label:string,html:string}> */
    private function record_fields( \WP_Post $post ): array {
        return [
            [ 'key' => 'post_id', 'label' => 'Profile ID', 'html' => esc_html( (string) $post->ID ) ],
            [ 'key' => 'post_status', 'label' => 'Status', 'html' => esc_html( $this->status_label( $post->post_status ) ) ],
            [ 'key' => 'post_name', 'label' => 'Slug', 'html' => '<code>' . esc_html( $post->post_name ) . '</code>' ],
            [ 'key' => 'post_date', 'label' => 'Published', 'html' => esc_html( get_the_date( 'F j, Y g:i a', $post ) ) ],
            [ 'key' => 'post_modified', 'label' => 'Last Modified', 'html' => esc_html( get_the_modified_date( 'F j, Y g:i a', $post ) ) ],
            [ 'key' => 'permalink', 'label' => 'Public URL', 'html' => $this->linked_value_html( get_permalink( $post ) ) ],
        ];
    }

    private function definition_html( string $label, string $value_html, string $key = '' ): string {
        return '<div class="smpi-founder-field"' . ( '' !== $key ? ' data-profile-field="' . esc_attr( $key ) . '"' : '' ) . '><dt>' . esc_html( $label ) . '</dt><dd>' . $value_html . '</dd></div>';
    }

    private function linked_value_html( string $url ): string {
        return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a>';
    }

    /** @param array<int,string> $keys */
    private function first_meta_value( int $profile_id, array $keys ): string {
        foreach ( $keys as $key ) {
            $value = get_post_meta( $profile_id, $key, true );
            if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                return trim( (string) $value );
            }
        }
        return '';
    }

    private function url_fingerprint( $value ): string {
        if ( ! is_string( $value ) || ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
            return '';
        }
        return strtolower( rtrim( $value, '/' ) );
    }

    private function status_label( string $status ): string {
        return 'publish' === $status ? 'Published' : ucwords( str_replace( [ '-', '_' ], ' ', $status ) );
    }
}
