<?php
namespace smp_publication_integration\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The one list of SMP publication page types: group, label, the usual
 * addresses used to find the page automatically, and whether the page is
 * really generic (flagged to move to HWS Base Tools later).
 *
 * Verified-profile and leadership/culture council pages belong to their own
 * plugins and are not listed here.
 */
final class PageTypes {
    public const GROUPS = [
        'about'    => 'About',
        'people'   => 'People',
        'policies' => 'Policies',
        'reader'   => 'Reader',
    ];

    /** Old keys folded into another type: old key => kept key. */
    public const MERGED = [
        'advertise_with_us' => 'advertise',
        'writers'           => 'contributors',
        'founder_about'     => 'founders',
    ];

    /**
     * @return array<string,array{label:string,group:string,slugs:array<int,string>,description:string,google_news?:bool,base_tools?:string,optional?:bool,requires?:string,source?:string}>
     */
    public static function all(): array {
        return [
            // About
            'about_publication' => [ 'group' => 'about', 'label' => 'About', 'slugs' => [ 'about', 'about-us' ], 'description' => 'Who the publication is, what it covers and who it serves.' ],
            'mission_statement' => [ 'group' => 'about', 'label' => 'Mission Statement', 'slugs' => [ 'mission-statement', 'mission', '*mission-statement' ], 'description' => 'The publication mission and its promise to readers.' ],
            'founders' => [ 'group' => 'about', 'label' => 'Founders', 'slugs' => [ 'founders', 'founder', 'founding-team', '*-founder-*', 'founder-*' ], 'description' => 'The founders, from HWS Base Tools (the primary entity when it is a person marked as a founder, plus founders added by hand). No founding date.' ],
            'founding_date' => [ 'group' => 'about', 'label' => 'Founding Date', 'slugs' => [ 'founding-date', 'founded' ], 'description' => 'When and where the publication was founded.', 'requires' => 'company' ],
            'headquarters' => [ 'group' => 'about', 'label' => 'Headquarters', 'slugs' => [ 'headquarters', 'location' ], 'description' => 'Where the company behind the publication is based.', 'base_tools' => 'Any company website has a headquarters page; its facts already live in HWS Base Tools.' ],
            'brand_assets' => [ 'group' => 'about', 'label' => 'Logo', 'slugs' => [ 'logo', 'brand-assets', 'press-kit' ], 'description' => 'The official logo and brand files, with usage rules.', 'base_tools' => 'Logo and brand files are owned by HWS Base Tools Brand Assets and fit any website.' ],
            'parent_organization' => [ 'group' => 'about', 'label' => 'Parent Network', 'slugs' => [ 'parent-network', 'parent-organization', 'parent-company' ], 'description' => 'The parent company that owns the publication.', 'optional' => true ],
            'powered_by_smp' => [ 'group' => 'about', 'label' => 'Powered by Scale My Publication', 'slugs' => [ 'powered-by-scale-my-publication', 'powered-by' ], 'description' => 'The platform the publication runs on.' ],
            'ownership_funding' => [ 'group' => 'about', 'label' => 'Ownership and Funding', 'slugs' => [ 'ownership-and-funding', 'ownership' ], 'description' => 'Who owns and funds the publication.', 'google_news' => true, 'optional' => true ],
            // People
            'executive_team' => [ 'group' => 'people', 'label' => 'Executive Leadership Team', 'slugs' => [ 'executive-leadership-team', 'executive-team', 'leadership' ], 'description' => 'The people who lead the publication.' ],
            'team' => [ 'group' => 'people', 'label' => 'Team', 'slugs' => [ 'team', 'our-team' ], 'description' => 'Everyone behind the publication, from HWS Base Tools\' Team (read-only here).', 'source' => 'team' ],
            'staff' => [ 'group' => 'people', 'label' => 'Staff', 'slugs' => [ 'staff', 'meet-the-staff' ], 'description' => 'The newsroom staff.' ],
            'contributors' => [ 'group' => 'people', 'label' => 'Contributors', 'slugs' => [ 'contributors', 'meet-our-contributors', 'writers' ], 'description' => 'The writers and experts who contribute.' ],
            'masthead' => [ 'group' => 'people', 'label' => 'Masthead', 'slugs' => [ 'masthead' ], 'description' => 'Named editors accountable for the publication.', 'google_news' => true ],
            // Policies
            'editorial_guidelines' => [ 'group' => 'policies', 'label' => 'Editorial Guidelines', 'slugs' => [ 'editorial-guidelines' ], 'description' => 'How stories are reported, edited and labeled.' ],
            'editorial_policy' => [ 'group' => 'policies', 'label' => 'Editorial Policy', 'slugs' => [ 'editorial-policy' ], 'description' => 'Editorial independence and review.' ],
            'dmca' => [ 'group' => 'policies', 'label' => 'DMCA Policy', 'slugs' => [ 'dmca-policy', 'dmca', 'dmca-takedown-request' ], 'description' => 'How copyright owners ask for material to be removed.' ],
            'privacy' => [ 'group' => 'policies', 'label' => 'Privacy Policy', 'slugs' => [ 'privacy-policy', 'privacy' ], 'description' => 'What reader data is collected and how it is used.' ],
            'terms' => [ 'group' => 'policies', 'label' => 'Terms of Use', 'slugs' => [ 'terms-of-use', 'terms', 'terms-and-conditions' ], 'description' => 'The rules for using the website.' ],
            'accessibility' => [ 'group' => 'policies', 'label' => 'Accessibility', 'slugs' => [ 'accessibility' ], 'description' => 'The accessibility commitment and how to report a barrier.' ],
            'publishing_principles' => [ 'group' => 'policies', 'label' => 'Publishing Principles', 'slugs' => [ 'publishing-principles' ], 'description' => 'The principles behind what is published.', 'google_news' => true ],
            'verification_fact_checking_policy' => [ 'group' => 'policies', 'label' => 'Verification and Fact Checking Policy', 'slugs' => [ 'verification-and-fact-checking-policy', 'fact-checking-policy' ], 'description' => 'How facts are checked before publication.', 'google_news' => true ],
            'corrections_policy' => [ 'group' => 'policies', 'label' => 'Corrections Policy', 'slugs' => [ 'corrections-policy', 'corrections' ], 'description' => 'How errors are corrected.', 'google_news' => true ],
            'ethics_policy' => [ 'group' => 'policies', 'label' => 'Ethics Policy', 'slugs' => [ 'ethics-policy', 'ethics' ], 'description' => 'Conflicts of interest, gifts and independence.', 'google_news' => true ],
            'diversity_policy' => [ 'group' => 'policies', 'label' => 'Diversity Policy', 'slugs' => [ 'diversity-policy' ], 'description' => 'Diversity of voices and staff.', 'google_news' => true ],
            'diversity_staffing_report' => [ 'group' => 'policies', 'label' => 'Diversity Staffing Report', 'slugs' => [ 'diversity-staffing-report' ], 'description' => 'Report on newsroom diversity.', 'google_news' => true ],
            'mission_coverage_priorities_policy' => [ 'group' => 'policies', 'label' => 'Mission and Coverage Priorities Policy', 'slugs' => [ 'mission-and-coverage-priorities-policy', 'coverage-priorities' ], 'description' => 'What the publication covers and why.', 'google_news' => true ],
            'no_bylines_policy' => [ 'group' => 'policies', 'label' => 'No Bylines Policy', 'slugs' => [ 'no-bylines-policy' ], 'description' => 'When a story runs without a named author.', 'google_news' => true ],
            'unnamed_sources_policy' => [ 'group' => 'policies', 'label' => 'Unnamed Sources Policy', 'slugs' => [ 'unnamed-sources-policy' ], 'description' => 'When anonymous sources are used.', 'google_news' => true ],
            'actionable_feedback_policy' => [ 'group' => 'policies', 'label' => 'Actionable Feedback Policy', 'slugs' => [ 'actionable-feedback-policy', 'feedback-policy' ], 'description' => 'How reader feedback is handled.', 'google_news' => true ],
            // Reader
            'faqs' => [ 'group' => 'reader', 'label' => 'FAQs', 'slugs' => [ 'faqs', 'faq' ], 'description' => 'Common reader questions.', 'base_tools' => 'An FAQ page fits any website; Hexa WP Core already holds the FAQ sets.' ],
            'rss_feed' => [ 'group' => 'reader', 'label' => 'RSS Feed', 'slugs' => [ 'rss-feed', 'rss', 'feeds' ], 'description' => 'Every RSS feed readers can follow.' ],
            'press_releases' => [ 'group' => 'reader', 'label' => 'Latest Press Releases', 'slugs' => [ 'latest-press-releases', 'press-releases' ], 'description' => 'The newest press releases.' ],
            'submit_press_release' => [ 'group' => 'reader', 'label' => 'Submit Your Press Release', 'slugs' => [ 'submit-your-press-release', 'submit-a-press-release' ], 'description' => 'How to send a press release.' ],
            'advertise' => [ 'group' => 'reader', 'label' => 'Advertise', 'slugs' => [ 'advertise', 'advertise-with-us' ], 'description' => 'Advertising and partnership options.' ],
        ];
    }

    /** Pages HWS Base Tools owns: shown read-only on the Pages tab with a link to edit them there. */
    public const BASE_TOOLS_OWNED = [
        'contact' => [ 'label' => 'Contact', 'tab' => 'pages', 'description' => 'How readers reach the publication. HWS Base Tools owns this page.' ],
    ];

    /** Whether the page applies to this site now ('requires' => 'company' needs Base Tools' Website Identity to represent a company). */
    public static function applies( string $key ): bool {
        $type = self::get( $key );
        if ( 'company' === ( $type['requires'] ?? '' ) ) {
            // Website Identity reads fields, which must never run before init (ACF would boot before rewrite exists).
            if ( ! did_action( 'init' ) ) {
                return true;
            }
            return function_exists( 'hws_site_identity' ) && 'company' === hws_site_identity( 'type' );
        }
        return ! empty( $type );
    }

    /** The default page set: every page type that applies, without the optional ones (created only on request). */
    public static function default_set(): array {
        return array_filter( self::all(), static fn( array $type, string $key ): bool => empty( $type['optional'] ) && self::applies( $key ), ARRAY_FILTER_USE_BOTH );
    }

    public static function get( string $key ): array {
        $key = self::canonical( $key );
        return self::all()[ $key ] ?? [];
    }

    public static function canonical( string $key ): string {
        $key = sanitize_key( $key );
        return self::MERGED[ $key ] ?? $key;
    }

    public static function exists( string $key ): bool {
        return isset( self::all()[ self::canonical( $key ) ] );
    }

    /** The first usual address, used when creating a page. */
    public static function slug( string $key ): string {
        $slugs = (array) ( self::get( $key )['slugs'] ?? [] );
        foreach ( $slugs as $slug ) {
            if ( false === strpos( (string) $slug, '*' ) ) {
                return (string) $slug;
            }
        }
        return sanitize_title( (string) ( self::get( $key )['label'] ?? $key ) );
    }

    /** @return array<string,array<string,array>> group => key => type */
    public static function grouped(): array {
        $out = array_fill_keys( array_keys( self::GROUPS ), [] );
        foreach ( self::all() as $key => $type ) {
            $out[ $type['group'] ][ $key ] = $type;
        }
        return $out;
    }

    /** @return array<string,string> key => reason, for page types that belong in HWS Base Tools. */
    public static function base_tools_candidates(): array {
        $out = [];
        foreach ( self::all() as $key => $type ) {
            if ( ! empty( $type['base_tools'] ) ) {
                $out[ $key ] = (string) $type['base_tools'];
            }
        }
        return $out;
    }
}
