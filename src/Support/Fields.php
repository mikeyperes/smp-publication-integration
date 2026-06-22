<?php
namespace smp_publication_integration\Support;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class Fields {
    public static function get( int $post_id, string $field, $default = "" ) {
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
            "mission_statement" => [ "mission_statement", "publication_mission_statement", "smpi_mission_statement", "smpi_mission_statement_override" ],
            "mission_statement_extended" => [ "mission_statement_extended", "publication_mission_statement_extended", "smpi_mission_statement_extended" ],
            "summary" => [ "short_summary", "publication_summary", "summary", "description", "smpi_publication_summary" ],
            "website" => [ "url", "website", "publication_website", "smpi_publication_website" ],
            "logo" => [ "logo", "publication_logo", "smpi_publication_logo" ],
            "brand_assets" => [ "brand_assets", "publication_brand_assets", "brand_assets_gallery", "smpi_brand_assets" ],
            "publication_user" => [ "publication_user", "smpi_publication_user" ],
            "founders" => [ "founders", "publication_founders", "smpi_founder_profiles", "smpi_founders" ],
            "founder_profiles" => [ "founder_profiles", "publication_founder_profiles", "smpi_founder_profiles" ],
            "founder_users" => [ "founder_users", "publication_founder_users", "smpi_founder_users" ],
            "headquarters" => [ "headquarters", "headquarters_location", "publication_headquarters", "smpi_headquarters" ],
            "headquarters_wikipedia_url" => [ "headquarters_wikipedia_url", "smpi_headquarters_wikipedia_url" ],
            "recent_media" => [ "recent_media", "publication_recent_media", "smpi_recent_media" ],
            "quotes" => [ "quotes", "publication_quotes", "smpi_quotes" ],
            "founding_date" => [ "founding_date", "publication_founding_date", "smpi_founding_date" ],
            "founding_date_extended" => [ "founding_date_extended", "smpi_founding_date_extended" ],
            "headquarters_extended" => [ "headquarters_extended", "smpi_headquarters_extended" ],
            "contact" => [ "contact", "publication_contact", "smpi_contact" ],
            "contact_email" => [ "contact_email", "public_contact_email", "smpi_contact_email" ],
            "dmca" => [ "dmca", "dmca_policy", "smpi_dmca" ],
            "terms" => [ "terms_of_use", "terms", "smpi_terms_of_use" ],
            "privacy" => [ "privacy_policy", "privacy", "smpi_privacy_policy" ],
            "become_contributor" => [ "become_contributor", "become_a_contributor", "smpi_become_contributor" ],
            "has_podcast" => [ "has_podcast", "publication_has_podcast", "smpi_has_podcast" ],
            "rss_feed" => [ "rss_feed", "publication_rss_feed", "smpi_rss_feed" ],
            "google_news_url" => [ "google_news_url", "smpi_google_news_url" ],
            "publication_muckrack_verified" => [ "publication_muckrack_verified", "smpi_publication_muckrack_verified" ],
            "publication_muckrack_url" => [ "publication_muckrack_url", "smpi_publication_muckrack_url", "muckrack_url" ],
            "publishing_principles" => [ "publishing_principles", "publishingPrinciples", "smpi_publishing_principles_page" ],
            "verification_fact_checking_policy" => [ "verification_fact_checking_policy", "verificationFactCheckingPolicy", "smpi_verification_fact_checking_policy_page" ],
            "corrections_policy" => [ "corrections_policy", "correctionsPolicy", "smpi_corrections_policy_page" ],
            "ethics_policy" => [ "ethics_policy", "ethicsPolicy", "smpi_ethics_policy_page" ],
            "diversity_policy" => [ "diversity_policy", "diversityPolicy", "smpi_diversity_policy_page" ],
            "diversity_staffing_report" => [ "diversity_staffing_report", "diversityStaffingReport", "smpi_diversity_staffing_report_page" ],
            "masthead" => [ "masthead", "smpi_masthead_page" ],
            "mission_coverage_priorities_policy" => [ "mission_coverage_priorities_policy", "missionCoveragePrioritiesPolicy", "smpi_mission_coverage_priorities_policy_page" ],
            "no_bylines_policy" => [ "no_bylines_policy", "noBylinesPolicy", "smpi_no_bylines_policy_page" ],
            "unnamed_sources_policy" => [ "unnamed_sources_policy", "unnamedSourcesPolicy", "smpi_unnamed_sources_policy_page" ],
            "actionable_feedback_policy" => [ "actionable_feedback_policy", "actionableFeedbackPolicy", "smpi_actionable_feedback_policy_page" ],
            "ownership_funding_info" => [ "ownership_funding_info", "ownershipFundingInfo", "smpi_ownership_funding_info" ],
            "parent_organization" => [ "parent_organization", "parentOrganization", "smpi_parent_organization" ],
            "parent_organization_name" => [ "parent_organization_name", "smpi_parent_organization_name" ],
            "parent_organization_url" => [ "parent_organization_url", "smpi_parent_organization_url" ],
            "legal_name" => [ "legal_name", "legalName", "smpi_legal_name" ],
            "alternate_name" => [ "alternate_name", "alternateName", "smpi_alternate_name" ],
            "telephone" => [ "telephone", "phone", "smpi_telephone", "smpi_public_telephone" ],
            "contact_points" => [ "contact_points", "contactPoint", "smpi_contact_points" ],
            "postal_address" => [ "postal_address", "PostalAddress", "smpi_postal_address" ],
            "founding_location" => [ "founding_location", "foundingLocation", "smpi_founding_location" ],
            "founding_location_url" => [ "founding_location_url", "smpi_founding_location_url" ],
            "area_served" => [ "area_served", "areaServed", "smpi_area_served" ],
            "knows_about" => [ "knows_about", "keywords", "smpi_knows_about" ],
            "post_faq_items" => [ "post_faq_items", "smpi_post_faq_items" ],
            "post_faq_schema_enabled" => [ "post_faq_schema_enabled", "smpi_post_faq_schema_enabled" ],
            "breadcrumb_disabled_objects" => [ "breadcrumb_disabled_objects", "breadcrumbs_disabled_objects", "smpi_breadcrumb_disabled_objects" ],
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
        if ( function_exists( "get_field" ) ) {
            $value = get_field( $field, $post_id );
            if ( self::has_value( $value ) ) {
                return $value;
            }
        }

        return get_post_meta( $post_id, $field, true );
    }

    public static function has_value( $value ): bool {
        if ( null === $value || false === $value || "" === $value ) {
            return false;
        }

        return ! ( is_array( $value ) && empty( $value ) );
    }
}
