<?php
namespace smp_publication_integration\Pages;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The one list of SMP publication page types: group, label, the usual
 * addresses used to find the page automatically, and the parent page it sits
 * under. The company pages any website has (About and its company pages,
 * Contact, Privacy Policy, Terms of Use, Accessibility, FAQs, RSS Feed) belong
 * to HWS Base Tools (BASE_TOOLS_OWNED) and are only read here; SMP pages may
 * name one as their parent.
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
            'powered_by_smp' => [ 'parent' => 'parent_organization', 'group' => 'about', 'label' => 'Powered by Scale My Publication', 'slugs' => [ 'powered-by-scale-my-publication', 'powered-by' ], 'description' => 'The platform the publication runs on.' ],
            'ownership_funding' => [ 'parent' => 'about', 'group' => 'about', 'label' => 'Ownership and Funding', 'slugs' => [ 'ownership-and-funding', 'ownership' ], 'description' => 'Who owns and funds the publication.', 'google_news' => true, 'optional' => true ],
            // People
            'executive_team' => [ 'parent' => 'about', 'group' => 'people', 'label' => 'Executive Leadership Team', 'slugs' => [ 'executive-leadership-team', 'executive-team', 'leadership' ], 'description' => 'The people who lead the publication.' ],
            'staff' => [ 'parent' => 'about', 'group' => 'people', 'label' => 'Staff', 'slugs' => [ 'staff', 'meet-the-staff' ], 'description' => 'The newsroom staff.' ],
            'contributors' => [ 'parent' => 'about', 'group' => 'people', 'label' => 'Contributors', 'slugs' => [ 'contributors', 'meet-our-contributors', 'writers' ], 'description' => 'The writers and experts who contribute.' ],
            'masthead' => [ 'parent' => 'about', 'group' => 'people', 'label' => 'Masthead', 'slugs' => [ 'masthead' ], 'description' => 'Named editors accountable for the publication.', 'google_news' => true ],
            // Policies
            'policies' => [ 'group' => 'policies', 'label' => 'Policies', 'slugs' => [ 'policies', 'our-policies' ], 'description' => 'Every policy in one list, with links to each one.' ],
            'editorial_guidelines' => [ 'group' => 'policies', 'label' => 'Editorial Guidelines', 'slugs' => [ 'editorial-guidelines' ], 'description' => 'How stories are reported, edited and labeled.' ],
            'editorial_policy' => [ 'group' => 'policies', 'label' => 'Editorial Policy', 'slugs' => [ 'editorial-policy' ], 'description' => 'Editorial independence and review.' ],
            'dmca' => [ 'group' => 'policies', 'label' => 'DMCA Policy', 'slugs' => [ 'dmca-policy', 'dmca', 'dmca-takedown-request' ], 'description' => 'How copyright owners ask for material to be removed.' ],
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
            'press_releases' => [ 'group' => 'reader', 'label' => 'Latest Press Releases', 'slugs' => [ 'latest-press-releases', 'press-releases' ], 'description' => 'The newest press releases.' ],
            'submit_press_release' => [ 'group' => 'reader', 'label' => 'Submit Your Press Release', 'slugs' => [ 'submit-your-press-release', 'submit-a-press-release' ], 'description' => 'How to send a press release.' ],
            'advertise' => [ 'optional' => true, 'group' => 'reader', 'label' => 'Advertise', 'slugs' => [ 'advertise', 'advertise-with-us' ], 'description' => 'Advertising and partnership options.' ],
        ];
    }

    /** Pages HWS Base Tools owns: shown read-only on the Pages tab with a link to edit them there. */
    public const BASE_TOOLS_OWNED = [
        'headquarters' => [ 'label' => 'Headquarters', 'tab' => 'pages', 'description' => 'Where the company is based. HWS Base Tools owns this page and its facts.' ],
        'logo'         => [ 'label' => 'Logo', 'tab' => 'pages', 'description' => 'The official logo and brand files. HWS Base Tools owns this page.' ],
        'contact'      => [ 'label' => 'Contact', 'tab' => 'pages', 'description' => 'How readers reach the publication. HWS Base Tools owns this page.' ],
        'about'               => [ 'label' => 'About', 'tab' => 'pages', 'description' => 'Who the publication is. Parent of the About pages. HWS Base Tools owns this page.' ],
        'mission_statement'   => [ 'label' => 'Mission Statement', 'tab' => 'pages', 'description' => 'The mission, from Website Identity. HWS Base Tools owns this page.' ],
        'founders'            => [ 'label' => 'Founders', 'tab' => 'pages', 'description' => 'The founders and their profile pages. HWS Base Tools owns these pages.' ],
        'founding_date'       => [ 'label' => 'Founding Date', 'tab' => 'pages', 'description' => 'When and where the publication was founded. HWS Base Tools owns this page.' ],
        'parent_organization' => [ 'label' => 'Parent Network', 'tab' => 'pages', 'description' => 'The parent company. HWS Base Tools owns this page.' ],
        'team'                => [ 'label' => 'Team', 'tab' => 'pages', 'description' => 'Everyone on HWS Base Tools\' Team list. HWS Base Tools owns this page.' ],
        'privacy'             => [ 'label' => 'Privacy Policy', 'tab' => 'pages', 'description' => 'HWS Base Tools owns this page.' ],
        'terms'               => [ 'label' => 'Terms of Use', 'tab' => 'pages', 'description' => 'HWS Base Tools owns this page.' ],
        'accessibility'       => [ 'label' => 'Accessibility', 'tab' => 'pages', 'description' => 'HWS Base Tools owns this page.' ],
        'faqs'                => [ 'label' => 'FAQs', 'tab' => 'pages', 'description' => 'HWS Base Tools owns this page.' ],
        'rss_feed'            => [ 'label' => 'RSS Feed', 'tab' => 'pages', 'description' => 'HWS Base Tools owns this page.' ],
    ];

    /** Older SMP keys for pages HWS Base Tools now owns. */
    public const BASE_TOOLS_ALIASES = [ 'brand_assets' => 'logo', 'about_publication' => 'about' ];

    public static function base_tools_owned( string $key ): bool {
        $key = sanitize_key( $key );
        return isset( self::BASE_TOOLS_OWNED[ self::BASE_TOOLS_ALIASES[ $key ] ?? $key ] );
    }

    /** The page HWS Base Tools has linked for one of its page types (0 when none). */
    public static function base_tools_linked_id( string $key ): int {
        $key = sanitize_key( $key );
        $key = self::BASE_TOOLS_ALIASES[ $key ] ?? $key;
        if ( function_exists( 'hws_base_tools_linked_page' ) ) {
            $id = absint( hws_base_tools_linked_page( $key ) );
        } else {
            $id = absint( get_option( 'hws_site_page_assignment_' . $key, 0 ) );
        }
        return $id && get_post( $id ) ? $id : 0;
    }

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
