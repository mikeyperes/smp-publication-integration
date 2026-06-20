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
            "accessibility" => [ "label" => "Accessibility", "description" => "Accessibility commitment, supported standards, known limitations, and barrier reporting process.", "template" => true ],
        ];
    }

    public static function default_page_templates(): array {
        $build = static function ( string $heading, string $purpose, array $sections ): string {
            $html = "<h2>" . esc_html( $heading ) . "</h2>" . "\n\n";
            $html .= "<p><strong>Purpose:</strong> " . esc_html( $purpose ) . "</p>" . "\n\n";
            $html .= "<p><strong>Publication:</strong> [smp_publication_field field=legal_name format=text]</p>" . "\n";
            $html .= "<p><strong>Website:</strong> [smp_publication_field field=website format=text]</p>" . "\n";
            $html .= "<p><strong>Contact:</strong> [smp_publication_field field=contact_email format=text]</p>" . "\n\n";
            $html .= "<h3>What this page should contain</h3>" . "\n<ul>\n";
            foreach ( $sections as $section ) {
                $html .= "<li>" . esc_html( (string) $section ) . "</li>" . "\n";
            }
            $html .= "</ul>" . "\n\n";
            $html .= "<h3>Starter copy</h3>" . "\n";
            $html .= "<p>Use this starter page as the public source of truth for " . esc_html( strtolower( $heading ) ) . ". Replace placeholders with approved publication-specific language before publishing.</p>" . "\n\n";
            $html .= "<p>[smp_publication_field field=mission_statement format=html]</p>";
            return $html;
        };

        $specifics = [
            "about_publication" => [ "Public overview for readers, partners, and schema validators.", [ "Explain who the outlet is, what it covers, and who it serves.", "Include mission, editorial focus, ownership context, headquarters, founding date, and contact links.", "Reference canonical publication profile fields with shortcodes instead of hardcoding facts." ] ],
            "founder_about" => [ "Founder biography and leadership context.", [ "Explain founder background, role, and relationship to the publication.", "Link to founder profile pages when Verified Profiles is active.", "Keep promotional language factual and verifiable." ] ],
            "writers" => [ "Public writer directory.", [ "List active writers or link to author archive pages.", "Explain how writer profiles are reviewed and updated.", "Include contributor standards and contact path for profile corrections." ] ],
            "contributors" => [ "Contributor directory and contributor expectations.", [ "Explain who qualifies as a contributor.", "Summarize pitch requirements, editorial review, and attribution standards.", "Link to the Become a Contributor page when available." ] ],
            "staff" => [ "Staff directory.", [ "List editorial, operations, and business contacts where public.", "Explain roles responsible for corrections, legal requests, and advertising.", "Keep private staff data off the public page." ] ],
            "executive_team" => [ "Executive and leadership directory.", [ "List leadership names, titles, and responsibilities.", "Identify editorial accountability roles separately from business roles.", "Link leadership profiles when available." ] ],
            "team" => [ "Combined publication team page.", [ "Group staff by editorial, contributor, leadership, and operations.", "Include public contact paths instead of private personal data.", "Link to Writers, Staff, and Executive Team pages if those are separate." ] ],
            "headquarters" => [ "Canonical headquarters and location page.", [ "Show headquarters city, region, country, and public mailing details when appropriate.", "Include Headquarters Wikipedia URL when available: [smp_publication_field field=headquarters_wikipedia_url format=text].", "Explain whether the publication operates remotely, locally, nationally, or globally." ] ],
            "founding_date" => [ "Founding history and date page.", [ "State founding date: [smp_publication_field field=founding_date format=text].", "Explain the founding context, original mission, and major milestones.", "Include founding location when available." ] ],
            "mission_statement" => [ "Mission and audience promise.", [ "State the publication mission plainly.", "Explain coverage priorities and target audience.", "Describe how editorial decisions support the mission." ] ],
            "founders" => [ "Founder profile and founding team page.", [ "List founder names, profile links, and roles.", "Use Verified Profiles where available.", "If founders are intentionally private, explain the policy without exposing private data." ] ],
            "become_contributor" => [ "Contributor application and submission page.", [ "Explain pitch format, topic fit, conflicts, and disclosure requirements.", "Describe review process, editing, attribution, and rejection policy.", "Provide an application or contact path." ] ],
            "dmca" => [ "Copyright takedown policy.", [ "Describe how rights holders can submit a DMCA notice.", "List required notice elements: work, URL, contact, good-faith statement, signature.", "Include contact email or form path: [smp_publication_field field=contact_email format=text]." ] ],
            "terms" => [ "Website terms of use.", [ "Explain allowed use of site content, submissions, and restrictions.", "Cover disclaimers, limitation of liability, user conduct, and governing terms.", "Have legal counsel review before publishing." ] ],
            "privacy" => [ "Privacy policy.", [ "Explain data collection, cookies, analytics, ads, forms, and retention.", "Include reader rights and privacy contact path.", "Have legal counsel review before publishing." ] ],
            "editorial_guidelines" => [ "Public editorial standards.", [ "Explain sourcing, attribution, review, conflicts, sponsored labels, and updates.", "Link corrections, ethics, fact-checking, and unnamed source policy pages.", "Use this as the human-readable editorial standard behind schema policy URLs." ] ],
            "editorial_policy" => [ "Editorial policy and independence page.", [ "Explain independence from advertisers, sponsors, and owners.", "Describe review workflow and standards for accuracy.", "Explain update labels, corrections, and transparency." ] ],
            "contact" => [ "Public contact page.", [ "List general, editorial, corrections, advertising, legal, and feedback contact paths.", "Use structured departments that match contactPoint schema where possible.", "Do not publish private personal contact details." ] ],
            "faqs" => [ "Publication FAQ page.", [ "Answer reader, contributor, correction, advertising, and privacy questions.", "Use short, direct Q and A sections.", "Keep article-specific FAQs in post FAQ repeater fields for FAQPage schema." ] ],
            "parent_organization" => [ "Parent organization and ownership page.", [ "Identify parent organization name and URL when applicable.", "Explain ownership, funding, and editorial independence.", "If independent, state that clearly." ] ],
            "publishing_principles" => [ "NewsMediaOrganization publishingPrinciples policy page.", [ "State editorial principles, independence, accuracy, fairness, attribution, and transparency.", "Explain separation between editorial and advertising.", "This page URL can populate NewsMediaOrganization.publishingPrinciples." ] ],
            "verification_fact_checking_policy" => [ "NewsMediaOrganization verificationFactCheckingPolicy page.", [ "Explain how facts, claims, links, quotes, and sources are verified before publication.", "Describe editor review and escalation for sensitive claims.", "This page URL can populate NewsMediaOrganization.verificationFactCheckingPolicy." ] ],
            "corrections_policy" => [ "NewsMediaOrganization correctionsPolicy page.", [ "Explain how readers request corrections or clarifications.", "Describe review timeline, update notes, and material correction labeling.", "This page URL can populate NewsMediaOrganization.correctionsPolicy." ] ],
            "ethics_policy" => [ "NewsMediaOrganization ethicsPolicy page.", [ "Cover conflicts of interest, gifts, sponsorships, anonymous sources, AI use if applicable, and disclosure standards.", "Explain how ethical concerns are escalated.", "This page URL can populate NewsMediaOrganization.ethicsPolicy." ] ],
            "diversity_policy" => [ "NewsMediaOrganization diversityPolicy page.", [ "Explain newsroom diversity commitments for staffing, sources, and coverage.", "Describe review cadence and accountability.", "This page URL can populate NewsMediaOrganization.diversityPolicy." ] ],
            "diversity_staffing_report" => [ "NewsMediaOrganization diversityStaffingReport page.", [ "Publish or summarize staffing diversity data and reporting period.", "Explain methodology and limitations.", "This page URL can populate NewsMediaOrganization.diversityStaffingReport." ] ],
            "masthead" => [ "NewsMediaOrganization masthead page.", [ "List editorial leadership and accountability contacts.", "Separate editorial leadership from business or advertising leadership.", "This page URL can populate NewsMediaOrganization.masthead." ] ],
            "mission_coverage_priorities_policy" => [ "NewsMediaOrganization missionCoveragePrioritiesPolicy page.", [ "Explain beats, audience, public mission, and coverage priorities.", "Describe how priorities are selected and reviewed.", "This page URL can populate NewsMediaOrganization.missionCoveragePrioritiesPolicy." ] ],
            "no_bylines_policy" => [ "NewsMediaOrganization noBylinesPolicy page.", [ "Explain when staff, wire, newsroom, anonymous, or no bylines may be used.", "Describe accountability and editorial review for those articles.", "This page URL can populate NewsMediaOrganization.noBylinesPolicy." ] ],
            "unnamed_sources_policy" => [ "NewsMediaOrganization unnamedSourcesPolicy page.", [ "Explain when unnamed sources are permitted.", "Describe editorial approval, verification requirements, and reader disclosure standards.", "This page URL can populate NewsMediaOrganization.unnamedSourcesPolicy." ] ],
            "actionable_feedback_policy" => [ "NewsMediaOrganization actionableFeedbackPolicy page.", [ "Explain how readers submit feedback, corrections, tips, and concerns.", "Describe review ownership and expected response workflow.", "This page URL can populate NewsMediaOrganization.actionableFeedbackPolicy." ] ],
            "ownership_funding" => [ "Ownership and funding disclosure page.", [ "State ownershipFundingInfo: [smp_publication_field field=ownership_funding_info format=text].", "List parent organization if applicable.", "Explain funding sources and editorial independence." ] ],
            "advertise" => [ "Advertising and partnership page.", [ "Explain available advertising or sponsorship opportunities.", "State how sponsored content is labeled and separated from editorial decisions.", "Provide business contact path." ] ],
            "accessibility" => [ "Accessibility commitment page.", [ "State accessibility goals and supported standards.", "Explain known limitations and remediation process.", "Provide a contact path for accessibility barriers." ] ],
        ];

        $templates = [];
        foreach ( self::page_types() as $type => $config ) {
            $data = $specifics[ $type ] ?? [ (string) $config["description"], [ (string) $config["description"], "Reference publication fields with shortcodes where values may change.", "Replace starter text before publishing." ] ];
            $templates[ $type ] = $build( (string) $config["label"], (string) $data[0], (array) $data[1] );
        }

        return $templates;
    }

}
