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
            'shadow_posts_enabled' => true,
            'shadow_press_releases' => false,
            'post_time_mode'        => 'native',
            'author_social_cleanup' => true,
            'public_debug_enabled'  => true,
            'estimated_read_time_enabled' => true,
            'elementor_css_cache_busting' => true,
            'publication_social_cleanup' => true,
            'muckrack_verified_enabled' => true,
            'muckrack_author_always_show' => false,
            'muckrack_verified_contexts' => [ 'single_author', 'single_footer', 'author', 'home', 'loop_cards' ],
            'muckrack_verified_style' => 'tooltip',
            'muckrack_icon_color' => '#2d5277',
            'muckrack_icon_style' => 'circle_check',
            "muckrack_icon_size" => 18,
            "muckrack_icon_color_single_author" => "",
            "muckrack_icon_size_single_author" => 0,
            "muckrack_icon_color_single_footer" => "",
            "muckrack_icon_size_single_footer" => 0,
            "muckrack_icon_color_loop_cards" => "",
            "muckrack_icon_size_loop_cards" => 0,
            "muckrack_icon_color_home" => "",
            "muckrack_icon_size_home" => 0,
            "muckrack_icon_color_author" => "",
            "muckrack_icon_size_author" => 0,
            'publication_muckrack_verified_enabled' => false,
            'publication_muckrack_text_mode' => 'news_outlet',
            'publication_muckrack_style' => 'block',
            'publication_muckrack_color' => '#2d5277',
            "publication_muckrack_font_size" => 14,
            'publication_muckrack_placements' => [ 'bottom_article' ],
            'press_release_include_enabled' => true,
            'press_release_include_contexts' => [ 'home', 'category_tag', 'author', 'single_recent' ],
            'post_summary_acf_enabled' => false,
            'post_faqs_acf_enabled' => false,
            'table_of_contents_enabled' => false,
            'table_of_contents_auto_single' => false,
            "table_of_contents_style" => "toc02",
            "table_of_contents_accent_color" => "#2563eb",
            "table_of_contents_text_font_style" => "normal",
            "table_of_contents_text_font_size" => 15,
            "table_of_contents_text_color" => "#1f2937",
            "inline_photo_treatments_enabled" => false,
            "inline_photo_treatment" => "none",
            "inline_photo_accent_color" => "#d63428",
            "inline_photo_caption_font_style" => "italic",
            "inline_photo_caption_font_size" => 16,
            "inline_photo_caption_text_color" => "#272727",
            "post_summary_style" => "none",
            "post_faqs_style" => "none",
            "post_faqs_accent_color" => "#2563eb",
            "post_faqs_text_font_style" => "normal",
            "post_faqs_text_font_size" => 16,
            "post_faqs_text_color" => "#1f2937",
            'rank_math_breadcrumb_check_enabled' => true,
            'hws_masked_admin_report_enabled' => true,
            'system_publication_user_id' => 0,
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

            if ( "system_publication_user_id" === $key ) {
                $settings[ $key ] = absint( $value );
                continue;
            }

            if ( in_array( $key, [ "muckrack_icon_size", "publication_muckrack_font_size", "table_of_contents_text_font_size", "inline_photo_caption_font_size", "post_faqs_text_font_size", "muckrack_icon_size_single_author", "muckrack_icon_size_single_footer", "muckrack_icon_size_loop_cards", "muckrack_icon_size_home", "muckrack_icon_size_author" ], true ) ) {
                $value = absint( $value );
                if ( 0 === strpos( $key, "muckrack_icon_size_" ) ) {
                    $settings[ $key ] = 0 === $value ? 0 : max( 8, min( 64, $value ) );
                    continue;
                }
                $font_size_defaults = [
                    "publication_muckrack_font_size" => 14,
                    "table_of_contents_text_font_size" => 15,
                    "inline_photo_caption_font_size" => 16,
                    "post_faqs_text_font_size" => 16,
                ];
                $default = $font_size_defaults[ $key ] ?? 18;
                $settings[ $key ] = max( 8, min( 64, $value ?: $default ) );
                continue;
            }

            $style_options = [
                "table_of_contents_style" => [ "none", "toc00", "toc01", "toc02", "toc03", "toc04" ],
                "inline_photo_treatment" => [ "none", "fig1", "fig2", "fig4", "fig5" ],
                "post_summary_style" => [ "none", "sum00", "sum01", "sum02", "sum03", "sum04" ],
                "post_faqs_style" => [ "none", "faq00", "faq01", "faq02", "faq03", "faq04" ],
                "table_of_contents_text_font_style" => [ "normal", "italic" ],
                "inline_photo_caption_font_style" => [ "normal", "italic" ],
                "post_faqs_text_font_style" => [ "normal", "italic" ],
            ];
            if ( isset( $style_options[ $key ] ) ) {
                $value = sanitize_key( (string) $value );
                $settings[ $key ] = in_array( $value, $style_options[ $key ], true ) ? $value : $style_options[ $key ][0];
                continue;
            }

            if ( "inline_photo_treatments_enabled" === $key ) {
                $settings[ $key ] = (bool) $value;
                continue;
            }

            if ( in_array( $key, [ 'founders_enabled', 'shadow_posts_enabled', 'shadow_press_releases', 'author_social_cleanup', 'public_debug_enabled', 'estimated_read_time_enabled', 'elementor_css_cache_busting', 'publication_social_cleanup', 'muckrack_verified_enabled', 'muckrack_author_always_show', 'publication_muckrack_verified_enabled', 'press_release_include_enabled', 'post_summary_acf_enabled', 'post_faqs_acf_enabled', 'table_of_contents_enabled', 'table_of_contents_auto_single', 'rank_math_breadcrumb_check_enabled', 'hws_masked_admin_report_enabled' ], true ) ) {
                $settings[ $key ] = (bool) $value;
                continue;
            }

            if ( 'muckrack_verified_style' === $key ) {
                $allowed = [ 'tooltip', 'text', 'compact_block' ];
                $settings[ $key ] = in_array( $value, $allowed, true ) ? $value : 'tooltip';
                continue;
            }

            if ( 'muckrack_icon_style' === $key ) {
                $allowed = [ "circle_check", "circle_outline_check", "check" ];
                $settings[ $key ] = in_array( $value, $allowed, true ) ? $value : 'circle_check';
                continue;
            }

            if ( 'muckrack_icon_color' === $key || 0 === strpos( $key, 'muckrack_icon_color_' ) ) {
                $raw = trim( (string) $value );
                if ( 0 === strpos( $key, 'muckrack_icon_color_' ) && '' === $raw ) {
                    $settings[ $key ] = '';
                    continue;
                }
                $color = sanitize_hex_color( $raw );
                $settings[ $key ] = $color ?: ( 0 === strpos( $key, 'muckrack_icon_color_' ) ? '' : '#2d5277' );
                continue;
            }

            if ( 'publication_muckrack_text_mode' === $key ) {
                $allowed = [ 'news_outlet', 'publication_name' ];
                $settings[ $key ] = in_array( $value, $allowed, true ) ? $value : 'news_outlet';
                continue;
            }

            if ( 'publication_muckrack_style' === $key ) {
                $allowed = [ 'block', 'mini_block', 'compact', 'minimalist' ];
                $settings[ $key ] = in_array( $value, $allowed, true ) ? $value : 'block';
                continue;
            }

            if ( 'publication_muckrack_color' === $key ) {
                $color = sanitize_hex_color( (string) $value );
                $settings[ $key ] = $color ?: '#2d5277';
                continue;
            }

            if ( in_array( $key, [ "table_of_contents_accent_color", "table_of_contents_text_color", "inline_photo_accent_color", "inline_photo_caption_text_color", "post_faqs_accent_color", "post_faqs_text_color" ], true ) ) {
                $color_defaults = [
                    "table_of_contents_accent_color" => "#2563eb",
                    "table_of_contents_text_color" => "#1f2937",
                    "inline_photo_accent_color" => "#d63428",
                    "inline_photo_caption_text_color" => "#272727",
                    "post_faqs_accent_color" => "#2563eb",
                    "post_faqs_text_color" => "#1f2937",
                ];
                $color = sanitize_hex_color( (string) $value );
                $settings[ $key ] = $color ?: $color_defaults[ $key ];
                continue;
            }

            if ( 'muckrack_verified_contexts' === $key ) {
                $allowed = [ 'single_author', 'single_footer', 'author', 'home', 'loop_cards' ];
                $items = is_array( $value ) ? array_map( 'sanitize_key', $value ) : [];
                $settings[ $key ] = array_values( array_intersect( $allowed, $items ) );
                continue;
            }

            if ( 'publication_muckrack_placements' === $key ) {
                $allowed = [ 'below_author', 'bottom_article' ];
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
            'become_contributor' => [ 'label' => 'Become a Contributor', 'description' => 'Contributor guidelines, submission expectations, and application path.', 'template' => true ],

            "writers" => [ "label" => "Writers", "description" => "Canonical writers directory for publication authors.", "template" => false ],
            "contributors" => [ "label" => "Contributors", "description" => "Directory or landing page for contributor profiles.", "template" => false ],
            "staff" => [ "label" => "Staff", "description" => "Staff directory for editorial, operations, and business contacts.", "template" => false ],
            "executive_team" => [ "label" => "Executive Team", "description" => "Leadership and executive team page.", "template" => false ],
            "headquarters" => [ "label" => "Headquarters", "description" => "Canonical page for headquarters location and company presence.", "template" => false ],
            "founding_date" => [ "label" => "Founding Date", "description" => "Canonical page for publication founding history and timeline.", "template" => false ],
            "mission_statement" => [ "label" => "Mission Statement", "description" => "Editorial mission, audience promise, and publication purpose.", "template" => true ],
            "founders" => [ "label" => "Founders", "description" => "Founder profiles and founding team context.", "template" => false ],
            "editorial_guidelines" => [ "label" => "Editorial Guidelines", "description" => "Editorial standards, sourcing rules, corrections, and transparency.", "template" => true ],
            "parent_organization" => [ "label" => "Parent Organization", "description" => "Ownership, parent company, funding, and independence disclosure.", "template" => true ],

            "publishing_principles" => [ "label" => "Publishing Principles", "description" => "Editorial principles page for NewsMediaOrganization publishingPrinciples.", "template" => true ],
            "verification_fact_checking_policy" => [ "label" => "Verification and Fact Checking Policy", "description" => "Fact checking and verification standards for schema verificationFactCheckingPolicy.", "template" => true ],
            "diversity_policy" => [ "label" => "Diversity Policy", "description" => "Newsroom diversity policy for schema diversityPolicy.", "template" => true ],
            "diversity_staffing_report" => [ "label" => "Diversity Staffing Report", "description" => "Staffing diversity report for schema diversityStaffingReport.", "template" => true ],
            "mission_coverage_priorities_policy" => [ "label" => "Mission and Coverage Priorities Policy", "description" => "Coverage priorities and audience promise for schema missionCoveragePrioritiesPolicy.", "template" => true ],
            "no_bylines_policy" => [ "label" => "No Bylines Policy", "description" => "Policy explaining anonymous, staff, or no byline articles.", "template" => true ],
            "unnamed_sources_policy" => [ "label" => "Unnamed Sources Policy", "description" => "Policy explaining anonymous source usage.", "template" => true ],
            "actionable_feedback_policy" => [ "label" => "Actionable Feedback Policy", "description" => "Reader feedback and public engagement policy.", "template" => true ],
            'terms' => [ 'label' => 'Terms of Use', 'description' => 'Terms governing use of the website and its content.', 'template' => true ],
            'dmca' => [ 'label' => 'DMCA', 'description' => 'Copyright takedown policy and designated contact instructions.', 'template' => true ],
            'privacy' => [ 'label' => 'Privacy Policy', 'description' => 'Privacy practices, data use, cookies, and user rights.', 'template' => true ],
            'contact' => [ 'label' => 'Contact', 'description' => 'General, editorial, advertising, and legal contact points.', 'template' => true ],
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

            "mission_statement" => "This page should explain the publication mission, who it serves, what it covers, and the editorial promise made to readers.",
            "become_contributor" => "This page should explain contributor eligibility, pitch requirements, editorial review, attribution, conflicts, and how to submit.",
            "editorial_guidelines" => "Our editorial guidelines describe sourcing, review, attribution, corrections, conflicts, sponsored content labels, and standards for accuracy.",
            "contact" => "Use this page for general contact information, editorial inquiries, advertising inquiries, corrections, legal requests, and public contact email details.",
            "parent_organization" => "This page should disclose the parent organization, ownership structure, funding sources, and any relationships that could affect editorial independence.",

            "publishing_principles" => "This page should explain editorial principles, sourcing standards, independent review, attribution, and separation from advertising.",
            "verification_fact_checking_policy" => "This page should explain how claims are verified, how sources are checked, and how editors handle fact checking before publication.",
            "diversity_policy" => "This page should explain newsroom diversity commitments for staffing, sourcing, and coverage.",
            "diversity_staffing_report" => "This page should publish or summarize staffing diversity information and the reporting period covered.",
            "mission_coverage_priorities_policy" => "This page should explain what the publication covers, why those beats matter, and how coverage priorities are set.",
            "no_bylines_policy" => "This page should explain when articles may use staff, newsroom, wire, or anonymous bylines.",
            "unnamed_sources_policy" => "This page should explain when unnamed sources are allowed and what editorial approval is required.",
            "actionable_feedback_policy" => "This page should explain how readers can send feedback, corrections, tips, or public engagement input.",
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
