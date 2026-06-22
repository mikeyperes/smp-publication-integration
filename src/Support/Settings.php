<?php
namespace smp_publication_integration\Support;

use Hexa\PluginCore\ActivityLog\ActivityLogConfig;
use Hexa\PluginCore\ActivityLog\ActivityLogEntry;
use Hexa\PluginCore\ActivityLog\ActivityLogger;

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
            'article_types_enabled' => false,
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
            "featured_image_caption_templates_enabled" => false,
            "featured_image_caption_template" => "fig2",
            "featured_image_caption_accent_color" => "#d63428",
            "featured_image_caption_font_style" => "italic",
            "featured_image_caption_font_size" => 16,
            "featured_image_caption_text_color" => "#272727",
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
        $raw = get_option( self::OPTION, [] );
        $settings = wp_parse_args( is_array( $raw ) ? $raw : [], self::defaults() );
        $defaults = self::default_page_templates();

        if ( ! isset( $settings['page_templates'] ) || ! is_array( $settings['page_templates'] ) ) {
            $settings['page_templates'] = [];
        }

        foreach ( self::page_types() as $type => $config ) {
            if ( empty( $config['template'] ) ) {
                continue;
            }
            $stored = isset( $settings['page_templates'][ $type ] ) ? trim( (string) $settings['page_templates'][ $type ] ) : '';
            if ( '' === $stored || self::should_refresh_default_page_template( $stored ) ) {
                $settings['page_templates'][ $type ] = (string) ( $defaults[ $type ] ?? '' );
            }
        }

        return $settings;
    }

    private static function should_refresh_default_page_template( string $template ): bool {
        foreach ( [ '<strong>Purpose:</strong>', '<h3>What this page should contain</h3>', 'At [smp_publication_field field=legal_name format=text]' ] as $marker ) {
            if ( false !== strpos( $template, $marker ) ) {
                return true;
            }
        }
        return false;
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

            if ( in_array( $key, [ "muckrack_icon_size", "publication_muckrack_font_size", "table_of_contents_text_font_size", "inline_photo_caption_font_size", "featured_image_caption_font_size", "post_faqs_text_font_size", "muckrack_icon_size_single_author", "muckrack_icon_size_single_footer", "muckrack_icon_size_loop_cards", "muckrack_icon_size_home", "muckrack_icon_size_author" ], true ) ) {
                $value = absint( $value );
                if ( 0 === strpos( $key, "muckrack_icon_size_" ) ) {
                    $settings[ $key ] = 0 === $value ? 0 : max( 8, min( 64, $value ) );
                    continue;
                }
                $font_size_defaults = [
                    "publication_muckrack_font_size" => 14,
                    "table_of_contents_text_font_size" => 15,
                    "inline_photo_caption_font_size" => 16,
                    "featured_image_caption_font_size" => 16,
                    "post_faqs_text_font_size" => 16,
                ];
                $default = $font_size_defaults[ $key ] ?? 18;
                $settings[ $key ] = max( 8, min( 64, $value ?: $default ) );
                continue;
            }

            $style_options = [
                "table_of_contents_style" => [ "none", "toc00", "toc01", "toc02", "toc03", "toc04" ],
                "inline_photo_treatment" => [ "none", "fig1", "fig2", "fig4", "fig5" ],
                "featured_image_caption_template" => [ "none", "fig1", "fig2", "fig4", "fig5" ],
                "post_summary_style" => [ "none", "sum00", "sum01", "sum02", "sum03", "sum04" ],
                "post_faqs_style" => [ "none", "faq00", "faq01", "faq02", "faq03", "faq04" ],
                "table_of_contents_text_font_style" => [ "normal", "italic" ],
                "inline_photo_caption_font_style" => [ "normal", "italic" ],
                "featured_image_caption_font_style" => [ "normal", "italic" ],
                "post_faqs_text_font_style" => [ "normal", "italic" ],
            ];
            if ( isset( $style_options[ $key ] ) ) {
                $value = sanitize_key( (string) $value );
                $settings[ $key ] = in_array( $value, $style_options[ $key ], true ) ? $value : $style_options[ $key ][0];
                continue;
            }

            if ( "inline_photo_treatments_enabled" === $key || "featured_image_caption_templates_enabled" === $key ) {
                $settings[ $key ] = (bool) $value;
                continue;
            }

            if ( in_array( $key, [ 'founders_enabled', 'shadow_posts_enabled', 'shadow_press_releases', 'author_social_cleanup', 'public_debug_enabled', 'estimated_read_time_enabled', 'elementor_css_cache_busting', 'publication_social_cleanup', 'muckrack_verified_enabled', 'muckrack_author_always_show', 'publication_muckrack_verified_enabled', 'press_release_include_enabled', 'post_summary_acf_enabled', 'post_faqs_acf_enabled', 'article_types_enabled', 'table_of_contents_enabled', 'table_of_contents_auto_single', 'rank_math_breadcrumb_check_enabled', 'hws_masked_admin_report_enabled' ], true ) ) {
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

            if ( in_array( $key, [ "table_of_contents_accent_color", "table_of_contents_text_color", "inline_photo_accent_color", "inline_photo_caption_text_color", "featured_image_caption_accent_color", "featured_image_caption_text_color", "post_faqs_accent_color", "post_faqs_text_color" ], true ) ) {
                $color_defaults = [
                    "table_of_contents_accent_color" => "#2563eb",
                    "table_of_contents_text_color" => "#1f2937",
                    "inline_photo_accent_color" => "#d63428",
                    "inline_photo_caption_text_color" => "#272727",
                    "featured_image_caption_accent_color" => "#d63428",
                    "featured_image_caption_text_color" => "#272727",
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

    public static function hws_owned_page_keys(): array {
        return [ "terms", "privacy", "brand_assets", "headquarters", "contact", "faqs" ];
    }

    public static function page_assignment_id( string $type ): int {
        $type = sanitize_key( $type );
        $settings = self::all();
        $page_id = isset( $settings["page_assignments"][ $type ] ) ? absint( $settings["page_assignments"][ $type ] ) : 0;

        if ( $page_id <= 0 && in_array( $type, self::hws_owned_page_keys(), true ) && function_exists( "get_option" ) ) {
            $page_id = absint( get_option( "hws_site_page_assignment_" . $type, 0 ) );
        }

        return $page_id;
    }

    public static function activity_log(): array {
        $entries = array_reverse( self::activity_logger()->all() );
        $log     = [];

        foreach ( array_slice( $entries, 0, 25 ) as $entry ) {
            $data      = $entry->to_array();
            $timestamp = strtotime( (string) ( $data['timestamp'] ?? '' ) );
            $log[]     = [
                'time'    => $timestamp ? date_i18n( 'Y-m-d H:i:s', $timestamp ) : current_time( 'mysql' ),
                'message' => sanitize_text_field( (string) ( $data['message'] ?? '' ) ),
                'level'   => sanitize_key( (string) ( $data['level'] ?? 'info' ) ),
                'source'  => sanitize_text_field( (string) ( $data['source'] ?? '' ) ),
            ];
        }

        return $log;
    }

    public static function log( string $message ): void {
        self::activity_logger()->add(
            new ActivityLogEntry(
                sanitize_text_field( $message ),
                [],
                is_user_logged_in() ? wp_get_current_user()->user_login : 'system',
                'smp-publication-integration',
                null,
                'info'
            )
        );
    }

    private static function activity_logger(): ActivityLogger {
        return new ActivityLogger(
            new ActivityLogConfig(
                [
                    'id'          => 'smpi-activity-log',
                    'title'       => 'SMP Publication Activity',
                    'storage'     => ActivityLogConfig::STORAGE_PERMANENT,
                    'storage_key' => 'smpi_activity_log',
                    'max_entries' => 50,
                    'collapsed'   => true,
                    'dark'        => true,
                ]
            )
        );
    }

    public static function page_slug_url( int $page_id ): string {
        $post = get_post( $page_id );
        if ( ! $post || "page" !== $post->post_type ) {
            return "";
        }

        $uri = get_page_uri( $page_id );
        if ( $uri ) {
            return home_url( user_trailingslashit( $uri ) );
        }

        $permalink = get_permalink( $page_id );
        return is_string( $permalink ) ? $permalink : "";
    }

    public static function page_types(): array {
        return [
            "about_publication" => [ "label" => "About The Publication", "description" => "Public overview of the outlet, editorial focus, audience, ownership context, and mission. This is the canonical about page for readers and schema reviewers.", "template" => true ],
            "founder_about" => [ "label" => "Founder About Page", "description" => "Canonical founder biography page that explains the founder role, background, and relationship to the publication.", "template" => true ],
            "writers" => [ "label" => "Writers", "description" => "Directory page for writers and author profiles connected to the publication.", "template" => true ],
            "contributors" => [ "label" => "Contributors", "description" => "Contributor directory and explanation of contributor roles, standards, and submission expectations.", "template" => true ],
            "staff" => [ "label" => "Staff", "description" => "Staff directory for editorial, operations, business, and support contacts.", "template" => true ],
            "executive_team" => [ "label" => "Executive Team", "description" => "Leadership page for executive, editorial, and operational decision makers.", "template" => true ],
            "team" => [ "label" => "Team", "description" => "Combined team page for editors, contributors, leadership, and operational contacts.", "template" => true ],
            "headquarters" => [ "label" => "Headquarters", "description" => "Canonical headquarters page with public address context, service area, and location references for organization schema.", "template" => true ],
            "founding_date" => [ "label" => "Founding Date", "description" => "Canonical founding history page explaining when, where, and why the publication was founded.", "template" => true ],
            "mission_statement" => [ "label" => "Mission Statement", "description" => "Editorial mission, audience promise, coverage priorities, and publication purpose.", "template" => true ],
            "founders" => [ "label" => "Founders", "description" => "Founder profiles and founding team context, ideally linked to verified profile records where available.", "template" => true ],
            "become_contributor" => [ "label" => "Become a Contributor", "description" => "Contributor eligibility, pitch requirements, editorial review standards, attribution, and application path.", "template" => true ],
            "brand_assets" => [ "label" => "Brand Assets", "description" => "Canonical public brand asset page for logos, approved media assets, press kit images, usage rules, and media contact context.", "template" => true ],
            "submit_press_release" => [ "label" => "Submit Your Press Release", "description" => "Press release intake page with submission requirements, review expectations, disclosure rules, and contact path.", "template" => true ],
            "press_releases" => [ "label" => "Press Releases", "description" => "Public press release landing page or archive page for release coverage, submission context, and press release access.", "template" => true ],
            "dmca" => [ "label" => "DMCA", "description" => "Copyright takedown policy and designated contact instructions for rights holders.", "template" => true ],
            "terms" => [ "label" => "Terms of Use", "description" => "Terms governing use of the website, content, submissions, acceptable behavior, and legal limitations.", "template" => true ],
            "privacy" => [ "label" => "Privacy Policy", "description" => "Privacy practices, data collection, cookies, analytics, reader rights, and contact path for privacy requests.", "template" => true ],
            "editorial_guidelines" => [ "label" => "Editorial Guidelines", "description" => "Public editorial standards, sourcing rules, corrections process, transparency, and sponsored content labeling.", "template" => true ],
            "editorial_policy" => [ "label" => "Editorial Policy", "description" => "Editorial independence, review workflow, accuracy standards, attribution, and update practices.", "template" => true ],
            "contact" => [ "label" => "Contact", "description" => "General, editorial, advertising, corrections, legal, and reader feedback contact points.", "template" => true ],
            "faqs" => [ "label" => "FAQs", "description" => "Common reader, contributor, editorial, correction, and publication questions.", "template" => true ],
            "parent_organization" => [ "label" => "Parent Organization", "description" => "Ownership, parent company, funding, and editorial independence disclosure for organization schema.", "template" => true ],
            "publishing_principles" => [ "label" => "Publishing Principles", "description" => "Editorial principles page used by NewsMediaOrganization publishingPrinciples schema.", "template" => true ],
            "verification_fact_checking_policy" => [ "label" => "Verification and Fact Checking Policy", "description" => "Fact checking and verification standards used by NewsMediaOrganization verificationFactCheckingPolicy schema.", "template" => true ],
            "corrections_policy" => [ "label" => "Corrections Policy", "description" => "Correction, clarification, update, and reader challenge process used by NewsMediaOrganization correctionsPolicy schema.", "template" => true ],
            "ethics_policy" => [ "label" => "Ethics Policy", "description" => "Editorial ethics policy covering conflicts, gifts, sourcing, sponsor separation, and transparency.", "template" => true ],
            "diversity_policy" => [ "label" => "Diversity Policy", "description" => "Newsroom diversity policy used by NewsMediaOrganization diversityPolicy schema.", "template" => true ],
            "diversity_staffing_report" => [ "label" => "Diversity Staffing Report", "description" => "Staffing diversity report or disclosure used by NewsMediaOrganization diversityStaffingReport schema.", "template" => true ],
            "masthead" => [ "label" => "Masthead", "description" => "Named editorial leadership, senior staff, and accountability contacts used by NewsMediaOrganization masthead schema.", "template" => true ],
            "mission_coverage_priorities_policy" => [ "label" => "Mission and Coverage Priorities Policy", "description" => "Coverage priorities, audience promise, and editorial scope used by NewsMediaOrganization missionCoveragePrioritiesPolicy schema.", "template" => true ],
            "no_bylines_policy" => [ "label" => "No Bylines Policy", "description" => "Policy explaining anonymous, staff, wire, newsroom, or no-byline articles used by NewsMediaOrganization noBylinesPolicy schema.", "template" => true ],
            "unnamed_sources_policy" => [ "label" => "Unnamed Sources Policy", "description" => "Policy explaining anonymous source usage, editorial approval, and verification requirements used by NewsMediaOrganization unnamedSourcesPolicy schema.", "template" => true ],
            "actionable_feedback_policy" => [ "label" => "Actionable Feedback Policy", "description" => "Reader feedback, correction, tip, and public engagement process used by NewsMediaOrganization actionableFeedbackPolicy schema.", "template" => true ],
            "ownership_funding" => [ "label" => "Ownership and Funding", "description" => "Detailed public ownership and funding disclosure for schema ownershipFundingInfo and reader transparency.", "template" => true ],
            "advertise" => [ "label" => "Advertise", "description" => "Advertising, sponsorship, brand partnership, and media kit path with editorial separation language.", "template" => true ],
            "advertise_with_us" => [ "label" => "Advertise with Us", "description" => "Advertising inquiry page for sponsors, brand partners, media buyers, and partnership leads with public contact and editorial separation language.", "template" => true ],
            "accessibility" => [ "label" => "Accessibility", "description" => "Accessibility commitment, supported standards, known limitations, and barrier reporting process.", "template" => true ],
        ];
    }

    public static function default_page_templates(): array {
        $templates = [];
        foreach ( self::page_types() as $type => $config ) {
            if ( empty( $config['template'] ) ) {
                continue;
            }
            $templates[ $type ] = '[smp_publication_page_template type=' . sanitize_key( (string) $type ) . ']';
        }
        return $templates;
    }


}
