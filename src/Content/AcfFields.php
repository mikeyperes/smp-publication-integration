<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Dependencies;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AcfFields {
    public function register(): void {
        add_action( 'acf/init', [ $this, 'register_fields' ] );
    }

    public function register_fields(): void {
        if ( ! Dependencies::acf_active() ) {
            return;
        }
        $this->register_publication_profile_fields();
        $this->register_muckrack_user_fields();
        $this->register_visibility_fields();
        $this->register_post_header_fields();
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

                    [ "key" => "field_smpi_publication_info_tab", "label" => "Publication Info", "name" => "", "type" => "tab", "placement" => "top" ],
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
                    [ "key" => "field_smpi_content_tab", "label" => "Publication Copy", "name" => "", "type" => "tab", "placement" => "top" ],
                    [ "key" => "field_smpi_mission_statement", "label" => "Mission Statement", "name" => "smpi_mission_statement", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_mission_statement_extended", "label" => "Mission Statement Extended", "name" => "smpi_mission_statement_extended", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_founding_date_extended", "label" => "Founding Date Extended", "name" => "smpi_founding_date_extended", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_headquarters_extended", "label" => "Headquarters Extended", "name" => "smpi_headquarters_extended", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_legal_contact_tab", "label" => "Legal and Contact", "name" => "", "type" => "tab", "placement" => "top" ],
                    [ "key" => "field_smpi_contact", "label" => "Contact", "name" => "smpi_contact", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_contact_email", "label" => "Contact Email Public", "name" => "smpi_contact_email", "type" => "email" ],
                    [ "key" => "field_smpi_dmca", "label" => "DMCA", "name" => "smpi_dmca", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_terms_of_use", "label" => "Terms of Use", "name" => "smpi_terms_of_use", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_privacy_policy", "label" => "Privacy Policy", "name" => "smpi_privacy_policy", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_become_contributor", "label" => "Become a Contributor", "name" => "smpi_become_contributor", "type" => "wysiwyg", "tabs" => "all", "toolbar" => "basic", "media_upload" => 0 ],
                    [ "key" => "field_smpi_distribution_tab", "label" => "Distribution", "name" => "", "type" => "tab", "placement" => "top" ],
                    [ "key" => "field_smpi_has_podcast", "label" => "Publication Has a Podcast", "name" => "smpi_has_podcast", "type" => "true_false", "ui" => 1 ],
                    [ "key" => "field_smpi_rss_feed", "label" => "RSS Feed", "name" => "smpi_rss_feed", "type" => "text" ],
                    [ "key" => "field_smpi_google_news_url", "label" => "Google News URL", "name" => "smpi_google_news_url", "type" => "url" ],
                    [ "key" => "field_smpi_publication_verification_tab", "label" => "Verification", "name" => "", "type" => "tab", "placement" => "top" ],
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
            $fields[] = [
                "key" => "field_65ab7bc1e849c",
                "label" => "Post FAQs",
                "name" => "post_faqs",
                "type" => "wysiwyg",
                "instructions" => "If there are FAQs, insert them here.

Example:

Q: How to do ABC.
A: Just XYZ.",
                "tabs" => "all",
                "toolbar" => "full",
                "media_upload" => 1,
                "delay" => 0,
            ];
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

    private function register_muckrack_user_fields(): void {
        acf_add_local_field_group(
            [
                "key" => "group_smpi_muckrack_user_fields",
                "title" => "SMP MuckRack Verification",
                "fields" => [
                    [ "key" => "field_smpi_muckrack_verified", "label" => "MuckRack Verified", "name" => "muckrack_verified", "type" => "true_false", "ui" => 1, "instructions" => "Marks this author as verified by the MuckRack editorial team." ],
                    [ "key" => "field_smpi_muckrack_url", "label" => "MuckRack URL", "name" => "muckrack_url", "type" => "url", "instructions" => "Public MuckRack profile or media outlet URL." ],
                    [ "key" => "field_smpi_what_best_describe_you", "label" => "What Best Describes You", "name" => "what_best_describe_you", "type" => "text", "instructions" => "Short descriptor used in verification copy, for example Journalist or Publication." ],
                ],
                "location" => [ [ [ "param" => "user_form", "operator" => "==", "value" => "all" ] ] ],
                "position" => "normal",
                "style" => "default",
            ]
        );
    }

    private function register_visibility_fields(): void {
        acf_add_local_field_group(
            [
                'key' => 'group_smpi_visibility_controls',
                'title' => 'SMP Visibility Controls',
                'fields' => [
                    [ 'key' => 'field_smpi_shadow_home', 'label' => 'Hide From Home Page', 'name' => '_smpi_shadow_home', 'type' => 'true_false', 'ui' => 1, 'instructions' => 'Hide this item from the home page main query.' ],
                    [ 'key' => 'field_smpi_shadow_archives', 'label' => 'Hide From Category/Tag Archives', 'name' => '_smpi_shadow_archives', 'type' => 'true_false', 'ui' => 1, 'instructions' => 'Hide this item from category and tag main queries.' ],
                    [ 'key' => 'field_smpi_pr_shadow_override', 'label' => 'Press Release Shadow Override', 'name' => '_smpi_pr_shadow_override', 'type' => 'select', 'choices' => [ '' => 'Use global setting', 'show' => 'Always show', 'hide' => 'Always hide' ], 'ui' => 1 ],
                ],
                'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ], [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'press-release' ] ] ],
                'position' => 'side',
                'style' => 'default',
            ]
        );
    }
}
