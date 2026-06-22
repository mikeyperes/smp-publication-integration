<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Dependencies;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AcfFields {
    public function register(): void {
        add_action( "acf/init", [ $this, "register_fields" ] );
        add_action( "acf/input/admin_head", [ $this, "admin_faq_styles" ] );
        add_action( "acf/input/admin_footer", [ $this, "admin_faq_scripts" ] );
    }

    public function register_fields(): void {
        if ( ! Dependencies::acf_active() ) {
            return;
        }
        $this->register_publication_profile_fields();
        $this->register_post_header_fields();
        $this->register_visibility_fields();
    }

    private function register_publication_profile_fields(): void {
        acf_add_local_field_group(
            [
                'key' => 'group_smpi_publication_profile',
                'title' => 'SMP Publication Theme Options',
                'fields' => [
                    [ 'key' => 'field_smpi_publication_user', 'label' => 'Publication User', 'name' => 'smpi_publication_user', 'type' => 'user', 'instructions' => 'Select the WordPress author profile that represents this publication on the front end.', 'return_format' => 'id', 'multiple' => 0 ],
                    [ "key" => "field_smpi_founder_profiles_notice", "label" => "Founder Profiles Setup", "name" => "", "type" => "message", "message" => self::founder_profiles_message(), "esc_html" => 0, "new_lines" => "wpautop" ],
                    [ 'key' => 'field_smpi_mission_statement_override', 'label' => 'Mission Statement Fallback', 'name' => 'smpi_mission_statement_override', 'type' => 'wysiwyg', 'instructions' => 'Shortcodes read existing imported fields such as mission_statement first. Use this only when no imported mission statement exists.', 'tabs' => 'all', 'toolbar' => 'full', 'media_upload' => 1 ],
                    [ 'key' => 'field_smpi_publication_summary', 'label' => 'Publication Summary Fallback', 'name' => 'smpi_publication_summary', 'type' => 'wysiwyg', 'instructions' => 'Fallback summary for publication cards and profile shortcodes.', 'tabs' => 'all', 'toolbar' => 'full', 'media_upload' => 1 ],
                    [ 'key' => 'field_smpi_publication_website', 'label' => 'Publication Website Fallback', 'name' => 'smpi_publication_website', 'type' => 'url', 'instructions' => 'Fallback URL. Existing imported website/url fields are preferred by shortcodes.' ],
                    [ 'key' => 'field_smpi_publication_logo', 'label' => 'Publication Logo Fallback', 'name' => 'smpi_publication_logo', 'type' => 'image', 'instructions' => 'Fallback logo when no imported logo/publication_logo field exists.', 'return_format' => 'array', 'preview_size' => 'medium', 'library' => 'all' ],
                    [ "key" => "field_smpi_brand_assets", "label" => "Brand Assets Gallery", "name" => "smpi_brand_assets", "type" => "gallery", "instructions" => "Upload approved logos, marks, screenshots, media kit artwork, and press-use brand assets for the Brand Assets page.", "return_format" => "array", "preview_size" => "medium", "insert" => "append", "library" => "all", "mime_types" => "jpg,jpeg,png,gif,webp,svg" ],

                    [ "key" => "field_smpi_headquarters", "label" => "Headquarters", "name" => "smpi_headquarters", "type" => "text", "instructions" => "Primary headquarters location for this publication." ],
                    [ "key" => "field_smpi_headquarters_wikipedia_url", "label" => "Headquarters Wikipedia URL", "name" => "smpi_headquarters_wikipedia_url", "type" => "url", "instructions" => "Optional Wikipedia URL for the headquarters location." ],
                    [ "key" => "field_smpi_founding_date", "label" => "Founding Date", "name" => "smpi_founding_date", "type" => "date_picker", "display_format" => "F j, Y", "return_format" => "Y-m-d", "first_day" => 1 ],
                    [ "key" => "field_smpi_founder_users", "label" => "Founder Authors", "name" => "smpi_founder_users", "type" => "user", "instructions" => "Select one or more WordPress author accounts as publication founders.", "role" => "", "return_format" => "id", "multiple" => 1, "allow_null" => 1 ],
                    [ "key" => "field_smpi_recent_media", "label" => "Recent Media", "name" => "smpi_recent_media", "type" => "repeater", "instructions" => "Media mentions or external references with title and URL.", "layout" => "table", "button_label" => "Add Media Link", "sub_fields" => [
                        [ "key" => "field_smpi_recent_media_title", "label" => "Title", "name" => "title", "type" => "text" ],
                        [ "key" => "field_smpi_recent_media_url", "label" => "URL", "name" => "url", "type" => "url" ],
                    ] ],
                    [ "key" => "field_smpi_quotes", "label" => "Quotes", "name" => "smpi_quotes", "type" => "repeater", "instructions" => "Publication quotes or testimonials that can be reused by templates and shortcodes.", "layout" => "row", "button_label" => "Add Quote", "sub_fields" => [
                        [ "key" => "field_smpi_quotes_quote", "label" => "Quote", "name" => "quote", "type" => "textarea", "rows" => 4, "new_lines" => "br" ],
                        [ "key" => "field_smpi_quotes_name", "label" => "Name", "name" => "name", "type" => "text" ],
                        [ "key" => "field_smpi_quotes_title", "label" => "Title", "name" => "title", "type" => "text" ],
                    ] ],
                    [ "key" => "field_smpi_mission_statement", "label" => "Mission Statement", "name" => "smpi_mission_statement", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_mission_statement_extended", "label" => "Mission Statement Extended", "name" => "smpi_mission_statement_extended", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_founding_date_extended", "label" => "Founding Date Extended", "name" => "smpi_founding_date_extended", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_headquarters_extended", "label" => "Headquarters Extended", "name" => "smpi_headquarters_extended", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_contact", "label" => "Contact", "name" => "smpi_contact", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_contact_email", "label" => "Contact Email Public", "name" => "smpi_contact_email", "type" => "email" ],
                    [ "key" => "field_smpi_dmca", "label" => "DMCA", "name" => "smpi_dmca", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_terms_of_use", "label" => "Terms of Use", "name" => "smpi_terms_of_use", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_privacy_policy", "label" => "Privacy Policy", "name" => "smpi_privacy_policy", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_become_contributor", "label" => "Become a Contributor", "name" => "smpi_become_contributor", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_has_podcast", "label" => "Publication Has a Podcast", "name" => "smpi_has_podcast", "type" => "true_false", "ui" => 1 ],
                    [ "key" => "field_smpi_rss_feed", "label" => "RSS Feed", "name" => "smpi_rss_feed", "type" => "text" ],
                    [ "key" => "field_smpi_google_news_url", "label" => "Google News URL", "name" => "smpi_google_news_url", "type" => "url" ],

                    [ "key" => "field_smpi_publication_schema_policy_message", "label" => "NewsMediaOrganization Schema Fields", "name" => "", "type" => "message", "message" => "These fields populate the publisher NewsMediaOrganization schema. Page selectors should point to published policy pages. Text fields should be public, non sensitive publication facts.", "esc_html" => 0, "new_lines" => "wpautop" ],
                    [ "key" => "field_smpi_publishing_principles_page", "label" => "publishingPrinciples", "name" => "smpi_publishing_principles_page", "type" => "post_object", "instructions" => "URL or page for editorial principles. Example: Editorial Principles or Standards page explaining sourcing, review, and independence.", "post_type" => [ "page" ], "return_format" => "id", "ui" => 1, "allow_null" => 1 ],
                    [ "key" => "field_smpi_verification_fact_checking_policy_page", "label" => "verificationFactCheckingPolicy", "name" => "smpi_verification_fact_checking_policy_page", "type" => "post_object", "instructions" => "URL or page for fact checking standards. Example: Fact Checking Policy page explaining verification and correction workflow.", "post_type" => [ "page" ], "return_format" => "id", "ui" => 1, "allow_null" => 1 ],
                    [ "key" => "field_smpi_corrections_policy_page", "label" => "correctionsPolicy", "name" => "smpi_corrections_policy_page", "type" => "post_object", "instructions" => "URL or page for correction policy. Example: Corrections page with contact path and update standards.", "post_type" => [ "page" ], "return_format" => "id", "ui" => 1, "allow_null" => 1 ],
                    [ "key" => "field_smpi_ethics_policy_page", "label" => "ethicsPolicy", "name" => "smpi_ethics_policy_page", "type" => "post_object", "instructions" => "URL or page for editorial ethics. Example: Ethics Policy covering conflicts, gifts, sourcing, and sponsor separation.", "post_type" => [ "page" ], "return_format" => "id", "ui" => 1, "allow_null" => 1 ],
                    [ "key" => "field_smpi_diversity_policy_page", "label" => "diversityPolicy", "name" => "smpi_diversity_policy_page", "type" => "post_object", "instructions" => "URL or page for newsroom diversity policy. Example: Diversity Policy describing staffing and source diversity goals.", "post_type" => [ "page" ], "return_format" => "id", "ui" => 1, "allow_null" => 1 ],
                    [ "key" => "field_smpi_diversity_staffing_report_page", "label" => "diversityStaffingReport", "name" => "smpi_diversity_staffing_report_page", "type" => "post_object", "instructions" => "URL or report page for staffing diversity. Example: annual diversity report page or self reported staffing summary.", "post_type" => [ "page" ], "return_format" => "id", "ui" => 1, "allow_null" => 1 ],
                    [ "key" => "field_smpi_masthead_page", "label" => "masthead", "name" => "smpi_masthead_page", "type" => "post_object", "instructions" => "URL or page listing editorial leadership. Example: Masthead page with editors and leadership roles.", "post_type" => [ "page" ], "return_format" => "id", "ui" => 1, "allow_null" => 1 ],
                    [ "key" => "field_smpi_mission_coverage_priorities_policy_page", "label" => "missionCoveragePrioritiesPolicy", "name" => "smpi_mission_coverage_priorities_policy_page", "type" => "post_object", "instructions" => "URL or page explaining coverage priorities. Example: Mission and Coverage Priorities page describing beats and audience promise.", "post_type" => [ "page" ], "return_format" => "id", "ui" => 1, "allow_null" => 1 ],
                    [ "key" => "field_smpi_no_bylines_policy_page", "label" => "noBylinesPolicy", "name" => "smpi_no_bylines_policy_page", "type" => "post_object", "instructions" => "URL or page explaining anonymous or no byline policy. Example: policy page describing staff reports and wire summaries.", "post_type" => [ "page" ], "return_format" => "id", "ui" => 1, "allow_null" => 1 ],
                    [ "key" => "field_smpi_unnamed_sources_policy_page", "label" => "unnamedSourcesPolicy", "name" => "smpi_unnamed_sources_policy_page", "type" => "post_object", "instructions" => "URL or page explaining anonymous source policy. Example: policy page describing when unnamed sources are allowed.", "post_type" => [ "page" ], "return_format" => "id", "ui" => 1, "allow_null" => 1 ],
                    [ "key" => "field_smpi_actionable_feedback_policy_page", "label" => "actionableFeedbackPolicy", "name" => "smpi_actionable_feedback_policy_page", "type" => "post_object", "instructions" => "URL or page for reader feedback and public engagement. Example: Contact or Feedback page with correction and tip channels.", "post_type" => [ "page" ], "return_format" => "id", "ui" => 1, "allow_null" => 1 ],
                    [ "key" => "field_smpi_ownership_funding_info", "label" => "ownershipFundingInfo", "name" => "smpi_ownership_funding_info", "type" => "textarea", "instructions" => "Public ownership and funding disclosure. Example: Mash Viral is independently operated by Example Media LLC and funded by advertising, sponsorships, and owned operations.", "rows" => 4, "new_lines" => "br" ],
                    [ "key" => "field_smpi_parent_organization", "label" => "parentOrganization", "name" => "smpi_parent_organization", "type" => "group", "instructions" => "Parent company or owner entity for the publication. Example: Example Media Holdings LLC with URL https://examplemedia.com/.", "layout" => "block", "sub_fields" => [ [ "key" => "field_smpi_parent_organization_name", "label" => "Name", "name" => "name", "type" => "text", "instructions" => "Example: Example Media Holdings LLC." ], [ "key" => "field_smpi_parent_organization_url", "label" => "URL", "name" => "url", "type" => "url", "instructions" => "Example: https://examplemedia.com/" ] ] ],
                    [ "key" => "field_smpi_legal_name", "label" => "legalName", "name" => "smpi_legal_name", "type" => "text", "instructions" => "Registered legal publication or company name. Example: Mash Viral Media LLC." ],
                    [ "key" => "field_smpi_alternate_name", "label" => "alternateName", "name" => "smpi_alternate_name", "type" => "text", "instructions" => "Aliases or short brand names, comma separated. Example: MashViral, MV News." ],
                    [ "key" => "field_smpi_telephone", "label" => "telephone", "name" => "smpi_telephone", "type" => "text", "instructions" => "Public phone number in international format. Example: +1-212-555-0199." ],
                    [ "key" => "field_smpi_contact_points", "label" => "contactPoint", "name" => "smpi_contact_points", "type" => "repeater", "instructions" => "Structured contact departments for schema. Example: Editorial corrections, Advertising, Legal.", "layout" => "row", "button_label" => "Add Contact Point", "sub_fields" => [ [ "key" => "field_smpi_contact_point_type", "label" => "Contact Type", "name" => "contact_type", "type" => "text", "instructions" => "Example: Editorial corrections" ], [ "key" => "field_smpi_contact_point_email", "label" => "Email", "name" => "email", "type" => "email", "instructions" => "Example: corrections@example.com" ], [ "key" => "field_smpi_contact_point_telephone", "label" => "Telephone", "name" => "telephone", "type" => "text", "instructions" => "Example: +1-212-555-0199" ], [ "key" => "field_smpi_contact_point_url", "label" => "URL", "name" => "url", "type" => "url", "instructions" => "Example: https://example.com/contact/" ] ] ],
                    [ "key" => "field_smpi_postal_address", "label" => "PostalAddress", "name" => "smpi_postal_address", "type" => "group", "instructions" => "Street, city, region, postal code, and country for public headquarters or legal address. Example: 123 Market St, New York, NY 10001, US.", "layout" => "block", "sub_fields" => [ [ "key" => "field_smpi_postal_street", "label" => "Street Address", "name" => "street_address", "type" => "text" ], [ "key" => "field_smpi_postal_city", "label" => "City", "name" => "address_locality", "type" => "text" ], [ "key" => "field_smpi_postal_region", "label" => "Region", "name" => "address_region", "type" => "text" ], [ "key" => "field_smpi_postal_code", "label" => "Postal Code", "name" => "postal_code", "type" => "text" ], [ "key" => "field_smpi_postal_country", "label" => "Country", "name" => "address_country", "type" => "text" ] ] ],
                    [ "key" => "field_smpi_founding_location", "label" => "foundingLocation", "name" => "smpi_founding_location", "type" => "text", "instructions" => "Place where the publication was founded. Example: New York, New York, United States." ],
                    [ "key" => "field_smpi_founding_location_url", "label" => "foundingLocation URL", "name" => "smpi_founding_location_url", "type" => "url", "instructions" => "Optional reference URL for the founding place. Example: https://en.wikipedia.org/wiki/New_York_City" ],
                    [ "key" => "field_smpi_area_served", "label" => "areaServed", "name" => "smpi_area_served", "type" => "text", "instructions" => "Geography or audience served, comma separated. Example: United States, Canada, Global English speaking readers." ],
                    [ "key" => "field_smpi_knows_about", "label" => "knowsAbout or keywords", "name" => "smpi_knows_about", "type" => "textarea", "instructions" => "Editorial coverage topics, comma or line separated. Example: technology, startups, sports business, venture capital, digital media.", "rows" => 3, "new_lines" => "br" ],
                    [ "key" => "field_smpi_publication_muckrack_verified", "label" => "Publication Verified by MuckRack", "name" => "smpi_publication_muckrack_verified", "type" => "true_false", "ui" => 1, "instructions" => "Marks this news outlet as verified by the MuckRack editorial team. Display placement is controlled in Settings > SMP Publication Integration > Features." ],
                    [ "key" => "field_smpi_publication_muckrack_url", "label" => "Publication MuckRack URL", "name" => "smpi_publication_muckrack_url", "type" => "url", "instructions" => "Public MuckRack outlet/profile URL used by the publication verification text." ],
                    [ 'key' => 'field_smpi_imported_source_url', 'label' => 'Imported Source URL', 'name' => 'smpi_imported_source_url', 'type' => 'url', 'instructions' => 'Reference-only source URL for imported publication records.' ],
                    [ 'key' => 'field_smpi_schema_markup', 'label' => 'Publication Schema Markup', 'name' => 'smpi_schema_markup', 'type' => 'textarea', 'instructions' => 'Generated JSON-LD for the current site publication. Refresh from the Schema tab.', 'rows' => 10, 'readonly' => 1 ],
                ],
                "location" => [ [ [ "param" => "options_page", "operator" => "==", "value" => "smp-publication-integration" ] ] ],
                'position' => 'normal',
                'style' => 'default',
            ]
        );
    }


    private static function founder_profiles_message(): string {
        $state = Dependencies::verified_profiles_readiness();
        $lines = [];
        $lines[] = ! empty( $state["plugin_active"] ) ? "✓Verified Profiles plugin is active." : "✗Verified Profiles plugin is not active.";
        $lines[] = ! empty( $state["profile_cpt"] ) ? "✓Profile content type is active." : "✗Profile content type is not active.";
        $lines[] = ! empty( $state["profile_acf"] ) ? "✓Profile ACF fields are enabled." : "✗Profile ACF fields are not enabled.";
        $message = "Founder selection is managed in the SMP Overview tab. The selector appears only when all three checks pass.<br>" . implode( "<br>", array_map( "esc_html", $lines ) );
        $actions = [];
        $actions[] = "<a class=\"button\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( $state["settings_url"] ) . "\">Open Verified Profiles settings</a>";
        if ( empty( $state["profile_cpt"] ) ) {
            $actions[] = "<a class=\"button button-primary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( wp_nonce_url( admin_url( "admin-post.php?action=smpi_enable_verified_profile_snippet&snippet=register_profile_custom_post_type" ), "smpi_enable_verified_profile_snippet" ) ) . "\">Enable profile content type</a>";
        }
        if ( empty( $state["profile_acf"] ) ) {
            $actions[] = "<a class=\"button button-primary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( wp_nonce_url( admin_url( "admin-post.php?action=smpi_enable_verified_profile_snippet&snippet=register_profile_general_acf_fields" ), "smpi_enable_verified_profile_snippet" ) ) . "\">Enable profile ACF fields</a>";
        }
        return $message . "<p>" . implode( " ", $actions ) . "</p>";
    }

    private function register_post_header_fields(): void {
        $fields = [
            [
                "key" => "field_64a7290bc7625",
                "label" => "",
                "name" => "",
                "type" => "message",
                "message" => "",
                "new_lines" => "wpautop",
                "esc_html" => 0,
            ],
        ];

        if ( Settings::bool( "post_summary_acf_enabled" ) ) {
            $fields[] = [
                "key" => "field_65ab7ba0e849b",
                "label" => "Post Summary",
                "name" => "post_summary",
                "type" => "wysiwyg",
                "instructions" => "Insert an html list (you can copy paste html in the code editor)",
                "tabs" => "all",
                "toolbar" => "full",
                "media_upload" => 1,
                "delay" => 0,
            ];
        }

        if ( Settings::bool( "post_faqs_acf_enabled" ) ) {
            $fields[] = [ "key" => "field_smpi_post_faq_summary", "label" => "Post FAQ Summary", "name" => "", "type" => "message", "message" => "<div class=\"smpi-faq-summary-card\" data-smpi-faq-summary><strong>FAQ summary</strong><p class=\"smpi-faq-summary-empty\">No structured FAQ rows yet.</p></div>", "esc_html" => 0, "new_lines" => "wpautop", "instructions" => "Live read-only summary of the structured FAQ rows below." ];
            $fields[] = [ "key" => "field_smpi_post_faq_schema_enabled", "label" => "FAQ Schema", "name" => "post_faq_schema_enabled", "type" => "true_false", "ui" => 1, "default_value" => 1, "instructions" => "One switch for the whole FAQ block. On by default." ];
            $fields[] = [ "key" => "field_smpi_post_faq_accordion", "label" => "Structured FAQs", "name" => "", "type" => "accordion", "instructions" => "Expand to edit article-specific FAQ rows. Rows feed the FAQPage schema and the [smp_post_faqs] shortcode when FAQ Schema is on.", "open" => 0, "multi_expand" => 0, "endpoint" => 0 ];
            $fields[] = [ "key" => "field_smpi_post_faq_items", "label" => "Post FAQ Items", "name" => "post_faq_items", "type" => "repeater", "instructions" => "Use structured FAQ rows for reliable FAQPage schema. Add one question and one answer per row. Row order controls schema order.", "layout" => "row", "button_label" => "Add FAQ", "collapsed" => "field_smpi_post_faq_question", "sub_fields" => [ [ "key" => "field_smpi_post_faq_question", "label" => "Question", "name" => "question", "type" => "text", "instructions" => "Plain text question. Example: What record did Lionel Messi recently tie?" ], [ "key" => "field_smpi_post_faq_answer", "label" => "Answer", "name" => "answer", "type" => "wysiwyg", "instructions" => "Answer content. Keep it factual and concise. Sanitized HTML is allowed.", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0, "delay" => 0, "wrapper" => [ "class" => "smpi-faq-answer-field" ] ] ] ];
            $fields[] = [ "key" => "field_smpi_post_faq_accordion_end", "label" => "", "name" => "", "type" => "accordion", "endpoint" => 1 ];
        }


        if ( 1 === count( $fields ) ) {
            return;
        }

        acf_add_local_field_group(
            [
                "key" => "group_64a7290b61191",
                "title" => "Post - Header",
                "fields" => $fields,
                "location" => [
                    [ [ "param" => "post_type", "operator" => "==", "value" => "post" ] ],
                    [ [ "param" => "post_type", "operator" => "==", "value" => "press-release" ] ],
                    [ [ "param" => "post_type", "operator" => "==", "value" => "imported-news" ] ],
                ],
                "menu_order" => 0,
                "position" => "normal",
                "style" => "default",
                "label_placement" => "top",
                "instruction_placement" => "label",
                "hide_on_screen" => "",
                "active" => true,
                "description" => "",
                "show_in_rest" => 0,
            ]
        );
    }


    private function should_render_post_faq_admin_assets(): bool {
        if ( ! is_admin() || ! Settings::bool( "post_faqs_acf_enabled" ) ) {
            return false;
        }
        if ( ! function_exists( "get_current_screen" ) ) {
            return false;
        }
        $screen = get_current_screen();
        if ( ! $screen || "post" !== $screen->base ) {
            return false;
        }
        return in_array( (string) $screen->post_type, [ "post", "press-release", "imported-news" ], true );
    }

    public function admin_faq_styles(): void {
        if ( ! $this->should_render_post_faq_admin_assets() ) {
            return;
        }
        ?>
        <style>.acf-field-smpi-post-faq-summary .acf-label label{font-size:15px;font-weight:700}.smpi-faq-summary-card{border:1px solid #d8dee8;border-radius:12px;background:#f8fafc;padding:12px 14px;color:#1f2937}.smpi-faq-summary-card strong{display:block;margin-bottom:8px}.smpi-faq-summary-card p{margin:0;color:#64748b}.smpi-faq-summary-card ol{margin:8px 0 0 20px}.smpi-faq-summary-card li{margin:6px 0}.smpi-faq-summary-card small{display:block;color:#64748b;margin-top:2px}.acf-field-smpi-post-faq-answer .wp-editor-area,.acf-field-smpi-post-faq-answer iframe,.acf-field-smpi-post-faq-answer .mce-edit-area iframe,.smpi-faq-answer-field .wp-editor-area,.smpi-faq-answer-field iframe,.smpi-faq-answer-field .mce-edit-area iframe{height:225px!important;min-height:225px!important}.acf-field-smpi-post-faq-items>.acf-label label{font-size:14px}.acf-field-smpi-post-faq-accordion .acf-accordion-title label{font-size:15px;font-weight:700}</style>
        <?php
    }

    public function admin_faq_scripts(): void {
        if ( ! $this->should_render_post_faq_admin_assets() ) {
            return;
        }
        ?>
        <script>
        (function($){
            function faqFields(){return $(".acf-field-smpi-post-faq-items,.acf-field-smpi-post-faq-accordion-end");}
            function faqAccordion(){return $(".acf-field-smpi-post-faq-accordion").first();}
            function setFaqCollapsed(closed){var a=faqAccordion();a.toggleClass("smpi-faq-collapsed",closed).toggleClass("smpi-faq-expanded",!closed);faqFields().toggle(!closed);}
            function initFaqCollapse(){var a=faqAccordion();if(a.length&&!a.data("smpiFaqInit")){a.data("smpiFaqInit",1);setFaqCollapsed(true);}}
            function clean(v){return $("<div>").html(v||"").text().replace(/\s+/g," ").trim();}
            function ans(row){var f=row.find("[data-key=\"field_smpi_post_faq_answer\"]").first(),t=f.find("textarea.wp-editor-area,textarea").first(),id=t.attr("id");if(!t.length){return "";}if(window.tinymce&&id&&tinymce.get(id)&&!tinymce.get(id).isHidden()){return clean(tinymce.get(id).getContent());}return clean(t.val());}
            function upd(){var s=$("[data-smpi-faq-summary]").first(),r=$(".acf-field-smpi-post-faq-items").first(),rows=[];if(!s.length||!r.length){return;}var schemaField=$("[data-key=\"field_smpi_post_faq_schema_enabled\"] input[type=\"checkbox\"]").first(),schemaOn=!schemaField.length||schemaField.is(":checked");r.find("tr.acf-row:not(.acf-clone)").each(function(){var row=$(this),q=$.trim(row.find("[data-key=\"field_smpi_post_faq_question\"] input").first().val()||""),a=ans(row);if(q||a){rows.push({q:q,a:a});}});if(!rows.length){s.html("<strong>FAQ summary</strong><p class=\"smpi-faq-summary-empty\">No structured FAQ rows yet.</p>");return;}var h="<strong>FAQ summary</strong><p>"+rows.length+" FAQ row"+(rows.length===1?"":"s")+" entered. FAQ schema is "+(schemaOn?"on":"off")+".</p><ol>";rows.slice(0,6).forEach(function(x){h+="<li><b>"+_.escape(x.q||"Untitled question")+"</b><small>"+_.escape((x.a||"No answer yet").slice(0,180))+"</small></li>";});if(rows.length>6){h+="<li><small>"+(rows.length-6)+" more row"+(rows.length-6===1?"":"s")+"</small></li>";}s.html(h+"</ol>");}
            $(document).on("input change keyup",".acf-field-smpi-post-faq-items input,.acf-field-smpi-post-faq-items textarea,.acf-field-smpi-post-faq-schema-enabled input",upd);
            $(document).on("click",".acf-field-smpi-post-faq-items .acf-icon.-minus,.acf-field-smpi-post-faq-items .acf-icon.-plus,.acf-field-smpi-post-faq-items .acf-button",function(){setTimeout(upd,250);});
            $(document).on("click",".acf-field-smpi-post-faq-accordion .acf-accordion-title",function(e){e.preventDefault();setFaqCollapsed(!faqAccordion().hasClass("smpi-faq-collapsed"));setTimeout(upd,50);});
            if(window.acf){acf.addAction("append remove sortstop ready",function(){initFaqCollapse();setTimeout(upd,200);});}
            $(document).on("tinymce-editor-init",function(ev,ed){if(ed&&ed.on){ed.on("keyup change undo redo SetContent",upd);}setTimeout(upd,200);});
            $(function(){initFaqCollapse();upd();});setTimeout(function(){initFaqCollapse();upd();},700);
        })(jQuery);
        </script>
        <?php
    }

    private function register_visibility_fields(): void {
        // Visibility controls are owned by src/Content/Visibility.php.
        // Do not register an ACF side box here; it duplicates the custom metabox.
    }
}
