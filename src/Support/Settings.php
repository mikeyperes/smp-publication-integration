<?php
namespace smp_publication_integration\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Settings {
    public const OPTION = 'smpi_settings';

    public static function defaults(): array {
        return [
            'founders_enabled'      => true,
            'shadow_press_releases' => false,
            'post_time_mode'        => 'native',
            'author_social_cleanup' => true,
            'public_debug_enabled'  => true,
            'elementor_css_cache_busting' => true,
            'publication_social_cleanup' => true,
            'muckrack_verified_enabled' => true,
            'muckrack_verified_contexts' => [ 'single_author', 'single_footer', 'author', 'home' ],
            'muckrack_verified_style' => 'tooltip',
            'press_release_include_enabled' => true,
            'press_release_include_contexts' => [ 'home', 'category_tag', 'author', 'single_recent' ],
            'page_assignments'      => [],
            'page_templates'        => self::default_page_templates(),
        ];
    }

    public static function all(): array {
        $settings = get_option( self::OPTION, [] );
        return wp_parse_args( is_array( $settings ) ? $settings : [], self::defaults() );
    }

    public static function get( string $key, $default = null ) {
        $settings = self::all();
        return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
    }

    public static function bool( string $key ): bool {
        return (bool) self::get( $key, false );
    }

    public static function array( string $key ): array {
        $value = self::get( $key, [] );
        return is_array( $value ) ? array_values( array_filter( array_map( 'sanitize_key', $value ) ) ) : [];
    }

    public static function update( array $changes ): array {
        $settings = self::all();
        foreach ( $changes as $key => $value ) {
            if ( 'post_time_mode' === $key ) {
                $allowed = [ 'native', 'relative_then_date', 'friendly_date' ];
                $settings[ $key ] = in_array( $value, $allowed, true ) ? $value : 'native';
                continue;
            }

            if ( in_array( $key, [ 'founders_enabled', 'shadow_press_releases', 'author_social_cleanup', 'public_debug_enabled', 'elementor_css_cache_busting', 'publication_social_cleanup', 'muckrack_verified_enabled', 'press_release_include_enabled' ], true ) ) {
                $settings[ $key ] = (bool) $value;
                continue;
            }

            if ( 'muckrack_verified_style' === $key ) {
                $allowed = [ 'tooltip', 'text' ];
                $settings[ $key ] = in_array( $value, $allowed, true ) ? $value : 'tooltip';
                continue;
            }

            if ( 'muckrack_verified_contexts' === $key ) {
                $allowed = [ 'single_author', 'single_footer', 'author', 'home' ];
                $items = is_array( $value ) ? array_map( 'sanitize_key', $value ) : [];
                $settings[ $key ] = array_values( array_intersect( $allowed, $items ) );
                continue;
            }

            if ( 'press_release_include_contexts' === $key ) {
                $allowed = [ 'home', 'category_tag', 'author', 'single_recent' ];
                $items = is_array( $value ) ? array_map( 'sanitize_key', $value ) : [];
                $settings[ $key ] = array_values( array_intersect( $allowed, $items ) );
                continue;
            }
        }

        update_option( self::OPTION, $settings, false );
        self::log( 'Settings updated: ' . implode( ', ', array_keys( $changes ) ) );
        return $settings;
    }

    public static function update_page( string $type, int $page_id, string $template = '' ): array {
        $page_types = self::page_types();
        if ( ! isset( $page_types[ $type ] ) ) {
            return self::all();
        }

        $settings = self::all();
        $settings['page_assignments'][ $type ] = $page_id > 0 ? $page_id : 0;
        if ( '' !== $template || ! empty( $page_types[ $type ]['template'] ) ) {
            $settings['page_templates'][ $type ] = wp_kses_post( $template );
        }

        update_option( self::OPTION, $settings, false );
        self::log( 'Page assignment updated: ' . $type );
        return $settings;
    }

    public static function activity_log(): array {
        $log = get_option( 'smpi_activity_log', [] );
        return is_array( $log ) ? array_slice( $log, 0, 25 ) : [];
    }

    public static function log( string $message ): void {
        $log = self::activity_log();
        array_unshift( $log, [ 'time' => current_time( 'mysql' ), 'message' => sanitize_text_field( $message ) ] );
        update_option( 'smpi_activity_log', array_slice( $log, 0, 50 ), false );
    }

    public static function page_types(): array {
        return [
            'about_publication' => [ 'label' => 'About The Publication', 'description' => 'Public overview of the outlet, editorial focus, audience, and mission.', 'template' => false ],
            'founder_about' => [ 'label' => 'Founder About Page', 'description' => 'Canonical page for founder biographies and leadership context.', 'template' => false ],
            'team' => [ 'label' => 'Team', 'description' => 'Editors, contributors, leadership, and operational contacts.', 'template' => false ],
            'become_contributor' => [ 'label' => 'Become a Contributor', 'description' => 'Contributor guidelines, submission expectations, and application path.', 'template' => false ],
            'terms' => [ 'label' => 'Terms of Use', 'description' => 'Terms governing use of the website and its content.', 'template' => true ],
            'dmca' => [ 'label' => 'DMCA', 'description' => 'Copyright takedown policy and designated contact instructions.', 'template' => true ],
            'privacy' => [ 'label' => 'Privacy Policy', 'description' => 'Privacy practices, data use, cookies, and user rights.', 'template' => true ],
            'contact' => [ 'label' => 'Contact', 'description' => 'General, editorial, advertising, and legal contact points.', 'template' => false ],
            'faqs' => [ 'label' => 'FAQs', 'description' => 'Common reader, contributor, and publication questions.', 'template' => false ],
            'editorial_policy' => [ 'label' => 'Editorial Policy', 'description' => 'Editorial standards, sourcing, independence, and review process.', 'template' => true ],
            'corrections_policy' => [ 'label' => 'Corrections Policy', 'description' => 'How corrections, clarifications, and updates are handled.', 'template' => true ],
            'ethics_policy' => [ 'label' => 'Ethics Policy', 'description' => 'Conflicts, gifts, sponsored content, and transparency rules.', 'template' => true ],
            'advertise' => [ 'label' => 'Advertise', 'description' => 'Advertising, sponsorship, and media kit path.', 'template' => false ],
            'masthead' => [ 'label' => 'Masthead', 'description' => 'Named editorial leadership and core staff directory.', 'template' => false ],
            'ownership_funding' => [ 'label' => 'Ownership and Funding', 'description' => 'Ownership, funding, and independence disclosure.', 'template' => true ],
            'accessibility' => [ 'label' => 'Accessibility', 'description' => 'Accessibility commitment and issue reporting process.', 'template' => true ],
        ];
    }

    public static function default_page_templates(): array {
        return [
            'terms' => 'These Terms of Use explain the rules for accessing and using this publication. Replace this starter text with counsel-reviewed terms before launch.',
            'dmca' => 'If you believe content on this website infringes your copyright, send a written notice with the work identified, the allegedly infringing URL, your contact information, a good-faith statement, and your signature.',
            'privacy' => 'This Privacy Policy explains what information this publication collects, how it is used, how cookies and analytics are handled, and how readers can contact us about privacy requests.',
            'editorial_policy' => 'Our editorial policy is to publish accurate, clearly sourced, and independently reviewed information. Sponsored or partner content should be labeled, and material updates should be disclosed when appropriate.',
            'corrections_policy' => 'Correction requests should identify the article, the disputed statement, and supporting evidence. Verified corrections are applied promptly with an update note when the change is material.',
            'ethics_policy' => 'Contributors and editors should avoid conflicts of interest, disclose relevant relationships, and separate editorial judgment from advertising or sponsorship activity.',
            'ownership_funding' => 'This page should disclose publication ownership, funding sources, and any relationships that could reasonably affect editorial independence.',
            'accessibility' => 'This publication aims to provide accessible content and welcomes reports of accessibility barriers through the contact page.',
        ];
    }
}