<?php
namespace smp_publication_integration\Content;

use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use smp_publication_integration\Support\Fields;
use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class Schema {
    private const REST_NS = "smpi/v1";

    public function register(): void {
        add_action( "wp_head", [ $this, "inject_schema" ], 1 );
        ( new AjaxActionRegistry(
            [
                'capability'   => 'manage_options',
                'nonce_action' => \smp_publication_integration\Admin\Ajax::NONCE,
                'nonce_field'  => 'nonce',
            ]
        ) )->register(
            [
                'smpi_reprocess_schema' => [ 'callback' => [ $this, 'ajax_reprocess_schema' ] ],
            ]
        );
        add_action( "rest_api_init", [ $this, "register_rest_routes" ] );
        add_filter( "rank_math/json_ld", [ $this, "filter_rank_math_schema" ], 9999, 2 );
        add_action( "wp", [ $this, "disable_rank_math_schema_output" ], 1 );
        add_action( "rank_math/head", [ $this, "disable_rank_math_schema_output" ], 1 );
    }

    public function disable_rank_math_schema_output(): void {
        if ( ! RuntimeContext::is_public_frontend() ) {
            return;
        }
        remove_all_actions( "rank_math/head", 90 );
    }

    public function register_rest_routes(): void {
        register_rest_route(
            self::REST_NS,
            "/schema",
            [
                "methods" => "GET",
                "callback" => [ $this, "rest_schema" ],
                "permission_callback" => [ $this, "rest_schema_permission" ],
                "args" => [
                    "url" => [ "sanitize_callback" => "esc_url_raw" ],
                    "post_id" => [ "sanitize_callback" => "absint" ],
                ],
            ]
        );
    }

    public function rest_schema_permission( \WP_REST_Request $request ): bool {
        return current_user_can( "manage_options" );
    }

    public function inject_schema(): void {
        if ( ! RuntimeContext::is_public_frontend() ) {
            return;
        }
        $schema = "";
        if ( is_front_page() || is_home() ) {
            $schema = $this->generate_home_schema_json();
        } elseif ( is_singular( [ "post", "press-release", "imported-news" ] ) ) {
            $post = get_queried_object();
            if ( $post instanceof \WP_Post ) {
                $schema = $this->generate_single_schema_json( (int) $post->ID );
            }
        }

        if ( "" !== $schema ) {
            echo "\n<script type=\"application/ld+json\" id=\"smpi-schema-jsonld\">" . $schema . "</script>\n";
        }
    }

    public function filter_rank_math_schema( $data, $jsonld = null ) {
        if ( ! RuntimeContext::is_public_frontend() ) {
            return $data;
        }
        return [];
    }

    public function rest_schema( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id = absint( $request->get_param( "post_id" ) );
        $url = (string) $request->get_param( "url" );
        $context = "home";
        $schema = null;

        if ( $post_id > 0 && get_post( $post_id ) ) {
            $context = "single";
            $schema = $this->generate_single_schema_array( $post_id );
        } elseif ( "" !== $url ) {
            $post_id = url_to_postid( $url );
            if ( $post_id > 0 ) {
                $context = "single";
                $schema = $this->generate_single_schema_array( $post_id );
            }
        }

        if ( null === $schema ) {
            $schema = $this->generate_home_schema_array();
        }

        return new \WP_REST_Response(
            [
                "plugin" => "smp-publication-integration",
                "context" => $context,
                "requested_url" => $url,
                "post_id" => $post_id,
                "types" => self::schema_types( $schema ),
                "integrity" => self::integrity_report( "single" === $context ? $post_id : 0 ),
                "schema" => $schema,
            ]
        );
    }

    public function ajax_reprocess_schema( AjaxRequest $request ): array {
        $home = $this->store_schema();
        $items = [
            [
                "title" => get_bloginfo( "name" ) . " homepage graph",
                "schema" => $home,
                "admin_link" => admin_url( "options-general.php?page=smp-publication-integration&tab=publication_options" ),
                "view_link" => home_url( "/" ),
                "validator_link" => "https://validator.schema.org/#url=" . rawurlencode( home_url( "/" ) ),
            ],
        ];

        $post_types = [ "post" ];
        if ( post_type_exists( "press-release" ) ) {
            $post_types[] = "press-release";
        }
        $posts = get_posts( [ "post_type" => $post_types, "post_status" => "publish", "posts_per_page" => 3, "fields" => "ids" ] );
        foreach ( $posts as $post_id ) {
            $items[] = [
                "title" => get_the_title( $post_id ),
                "schema" => $this->generate_single_schema_json( (int) $post_id ),
                "admin_link" => get_edit_post_link( $post_id, "raw" ),
                "view_link" => get_permalink( $post_id ),
                "validator_link" => "https://validator.schema.org/#url=" . rawurlencode( get_permalink( $post_id ) ),
            ];
        }

        return [ "total" => count( $items ), "batch" => count( $items ), "offset" => 0, "items" => $items ];
    }

    public function store_schema(): string {
        $schema = $this->generate_home_schema_json();
        if ( function_exists( "update_field" ) ) {
            update_field( "smpi_schema_markup", $schema, "option" );
        }
        update_option( "_smpi_schema_markup", $schema, false );
        return $schema;
    }

    public function get_stored_schema(): string {
        $schema = "";
        if ( function_exists( "get_field" ) ) {
            $schema = (string) get_field( "smpi_schema_markup", "option", false );
        }
        if ( "" === trim( $schema ) ) {
            $schema = (string) get_option( "_smpi_schema_markup", "" );
        }
        return trim( $schema );
    }

    public function generate_schema_json(): string {
        return $this->generate_home_schema_json();
    }

    public function generate_schema_array(): array {
        return $this->generate_home_schema_array();
    }

    public function generate_home_schema_json(): string {
        return wp_json_encode( $this->generate_home_schema_array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    }

    public function generate_single_schema_json( int $post_id ): string {
        return wp_json_encode( $this->generate_single_schema_array( $post_id ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    }

    public function generate_home_schema_array(): array {
        $org = $this->publication_entity();
        $home_url = trailingslashit( home_url( "/" ) );
        $website_id = $home_url . "#website";
        $page_id = $home_url . "#webpage";
        $list_id = $home_url . "#homepage-itemlist";
        $org_id = (string) ( $org["@id"] ?? $home_url . "#organization" );
        $description = get_bloginfo( "description" );
        $last_modified = $this->site_last_modified();

        $website = $this->clean_schema( [
            "@type" => "WebSite",
            "@id" => $website_id,
            "url" => $home_url,
            "name" => get_bloginfo( "name" ),
            "description" => $description,
            "publisher" => [ "@id" => $org_id ],
            "about" => [ "@id" => $org_id ],
            "inLanguage" => get_bloginfo( "language" ) ?: "en-US",
            "dateModified" => $last_modified,
            "potentialAction" => [ "@type" => "SearchAction", "target" => $home_url . "?s={search_term_string}", "query-input" => "required name=search_term_string" ],
        ] );

        $item_list = $this->homepage_item_list( $list_id, $org_id );
        $collection = $this->clean_schema( [
            "@type" => "CollectionPage",
            "@id" => $page_id,
            "url" => $home_url,
            "name" => get_bloginfo( "name" ),
            "headline" => get_bloginfo( "name" ),
            "description" => $description,
            "isPartOf" => [ "@id" => $website_id ],
            "about" => [ "@id" => $org_id ],
            "publisher" => [ "@id" => $org_id ],
            "mainEntity" => [ "@id" => $list_id ],
            "hasPart" => [ [ "@id" => $list_id ] ],
            "inLanguage" => get_bloginfo( "language" ) ?: "en-US",
            "dateModified" => $last_modified,
        ] );

        return [ "@context" => "https://schema.org", "@graph" => [ $org, $website, $collection, $item_list ] ];
    }

    public function generate_single_schema_array( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return $this->generate_home_schema_array();
        }

        $org = $this->publication_entity();
        $home_url = trailingslashit( home_url( "/" ) );
        $org_id = (string) ( $org["@id"] ?? $home_url . "#organization" );
        $website_id = $home_url . "#website";
        $permalink = get_permalink( $post );
        $webpage_id = $permalink . "#webpage";
        $article_id = $permalink . "#article";
        $article_type = ArticleTypes::schema_type_for_post( $post_id );
        $image = $this->primary_image_entity( $post_id, $permalink . "#primaryimage", $org["logo"] ?? null );
        $authors = $this->author_entities_for_post( $post_id );
        $org_ref = [ "@id" => $org_id ];
        $website_ref = [ "@id" => $website_id ];
        $webpage_ref = [ "@id" => $webpage_id ];
        $article_ref = [ "@id" => $article_id ];
        $image_ref = ! empty( $image["@id"] ) ? [ "@id" => (string) $image["@id"] ] : null;
        $author_ref = $authors ? array_values(
            array_filter(
                array_map(
                    static fn( array $author ): array => ! empty( $author["@id"] ) ? [ "@id" => (string) $author["@id"] ] : [],
                    $authors
                )
            )
        ) : [ $org_ref ];
        $faq_rows = self::faq_rows_for_post( $post_id, true );
        $faq = $faq_rows ? $this->faq_entity( $post_id, $permalink . "#faq", $faq_rows, $webpage_id ) : [];
        $breadcrumb = $this->breadcrumb_entity( $post_id, $permalink . "#breadcrumb" );
        $breadcrumb_ref = ! empty( $breadcrumb["@id"] ) ? [ "@id" => (string) $breadcrumb["@id"] ] : null;
        $faq_ref = ! empty( $faq["@id"] ) ? [ "@id" => (string) $faq["@id"] ] : null;
        $description = $this->post_description( $post );
        $language = get_bloginfo( "language" ) ?: "en-US";

        $website = $this->clean_schema( [
            "@type" => "WebSite",
            "@id" => $website_id,
            "url" => $home_url,
            "name" => get_bloginfo( "name" ),
            "description" => get_bloginfo( "description" ),
            "publisher" => $org_ref,
            "about" => $org_ref,
            "inLanguage" => $language,
        ] );

        $webpage = $this->clean_schema( [
            "@type" => "WebPage",
            "@id" => $webpage_id,
            "url" => $permalink,
            "name" => get_the_title( $post ),
            "description" => $description,
            "isPartOf" => $website_ref,
            "about" => $org_ref,
            "publisher" => $org_ref,
            "primaryImageOfPage" => $image_ref,
            "breadcrumb" => $breadcrumb_ref,
            "mainEntity" => $article_ref,
            "hasPart" => $faq_ref ? [ $faq_ref ] : null,
            "datePublished" => get_the_date( DATE_W3C, $post ),
            "dateModified" => get_the_modified_date( DATE_W3C, $post ),
            "inLanguage" => $language,
        ] );

        $article = $this->clean_schema( [
            "@type" => $article_type,
            "@id" => $article_id,
            "mainEntityOfPage" => $webpage_ref,
            "isPartOf" => $webpage_ref,
            "headline" => get_the_title( $post ),
            "name" => get_the_title( $post ),
            "description" => $description,
            "articleBody" => $this->post_article_body( $post ),
            "wordCount" => $this->post_word_count( $post ),
            "url" => $permalink,
            "datePublished" => get_the_date( DATE_W3C, $post ),
            "dateModified" => get_the_modified_date( DATE_W3C, $post ),
            "author" => 1 === count( $author_ref ) ? $author_ref[0] : $author_ref,
            "publisher" => $org_ref,
            "image" => $image_ref,
            "thumbnailUrl" => $image["url"] ?? null,
            "articleSection" => $this->article_sections( $post_id ),
            "keywords" => $this->post_keywords( $post_id ),
            "about" => $this->post_things( $post_id, "category" ),
            "mentions" => $this->post_things( $post_id, "post_tag" ),
            "isAccessibleForFree" => true,
            "inLanguage" => $language,
            "genre" => ArticleTypes::schema_type_label( $article_type ),
            "copyrightYear" => get_the_date( "Y", $post ),
            "copyrightHolder" => $org_ref,
            "commentCount" => (int) get_comments_number( $post_id ),
            "discussionUrl" => get_comments_link( $post_id ),
            "hasPart" => $faq_ref ? [ $faq_ref ] : null,
            "speakable" => [
                "@type" => "SpeakableSpecification",
                "cssSelector" => "h1, .elementor-widget-theme-post-content p:first-of-type, .elementor-widget-post-content p:first-of-type, article .entry-content p:first-of-type, .entry-content p:first-of-type, .post-content p:first-of-type",
            ],
        ] );

        $graph = array_values( array_filter( array_merge( [ $org, $website, $webpage, $article ], $authors, [ $image, $breadcrumb, $faq ] ) ) );
        $schema = [ "@context" => "https://schema.org", "@graph" => $graph ];
        return apply_filters( "smpi_single_schema_array", $schema, $post_id );
    }

    public static function schema_types( array $schema ): array {
        $nodes = isset( $schema["@graph"] ) && is_array( $schema["@graph"] ) ? $schema["@graph"] : [ $schema ];
        $types = [];
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) || empty( $node["@type"] ) ) {
                continue;
            }
            $node_types = is_array( $node["@type"] ) ? $node["@type"] : [ $node["@type"] ];
            foreach ( $node_types as $type ) {
                $types[] = (string) $type;
            }
        }
        return array_values( array_unique( $types ) );
    }

    public static function integrity_report( int $post_id = 0 ): array {
        $self = new self();
        $schema = $post_id ? $self->generate_single_schema_array( $post_id ) : $self->generate_home_schema_array();
        $types = self::schema_types( $schema );
        $expected = $post_id ? [ "NewsMediaOrganization", "WebSite", "WebPage", ArticleTypes::schema_type_for_post( $post_id ) ] : [ "NewsMediaOrganization", "WebSite", "CollectionPage", "ItemList" ];
        if ( $post_id && self::faq_rows_for_post( $post_id, true ) ) {
            $expected[] = "FAQPage";
        }

        $checks = [];
        foreach ( $expected as $type ) {
            $checks[] = [ "label" => $type . " node present", "status" => in_array( $type, $types, true ) ? "green" : "red" ];
        }

        $org = $schema["@graph"][0] ?? [];
        foreach ( [ "name", "url", "logo" ] as $property ) {
            $checks[] = [ "label" => "Publisher " . $property . " populated", "status" => ! empty( $org[ $property ] ) ? "green" : "yellow" ];
        }
        foreach ( [ "publishingPrinciples", "verificationFactCheckingPolicy", "correctionsPolicy", "ethicsPolicy", "masthead", "ownershipFundingInfo" ] as $property ) {
            $checks[] = [ "label" => "Publisher " . $property . " populated", "status" => ! empty( $org[ $property ] ) ? "green" : "yellow" ];
        }

        if ( $post_id ) {
            $checks[] = [ "label" => "Article featured image populated", "status" => has_post_thumbnail( $post_id ) ? "green" : "yellow" ];
            $checks[] = [ "label" => "Article FAQ repeater rows available for schema", "status" => self::faq_rows_for_post( $post_id, true ) ? "green" : "yellow" ];
            $article_types_enabled = ArticleTypes::is_enabled();
            $checks[] = [
                "label" => $article_types_enabled ? "Article type selector enabled and taxonomy exists" : "Article type selector disabled; schema fallback active",
                "status" => ( ! $article_types_enabled || taxonomy_exists( ArticleTypes::TAXONOMY ) ) ? "green" : "red",
            ];
        }

        return [ "context" => $post_id ? "single" : "home", "post_id" => $post_id, "types" => $types, "checks" => $checks ];
    }

    public static function faq_rows_for_post( int $post_id, bool $schema_only = true ): array {
        if ( $schema_only && ! self::faq_schema_enabled_for_post( $post_id ) ) {
            return [];
        }

        $rows = Fields::get( $post_id, "post_faq_items", [] );
        if ( ! is_array( $rows ) ) {
            return [];
        }

        $out = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $question = trim( wp_strip_all_tags( (string) ( $row["question"] ?? "" ) ) );
            $answer   = trim( (string) ( $row["answer"] ?? "" ) );
            if ( "" === $question || "" === wp_strip_all_tags( $answer ) ) {
                continue;
            }
            $out[] = [ "question" => $question, "answer" => wp_kses_post( $answer ), "enabled_for_schema" => true ];
        }
        return $out;
    }

    private static function faq_schema_enabled_for_post( int $post_id ): bool {
        $value = Fields::get( $post_id, "post_faq_schema_enabled", "__smpi_default_on__" );
        if ( "__smpi_default_on__" === $value || "" === $value || null === $value ) {
            return true;
        }

        return ! in_array( (string) $value, [ "0", "false", "off", "no" ], true );
    }

    private function publication_entity(): array {
        $website = Fields::option( "website", home_url( "/" ) );
        $summary = Fields::raw_option( "smpi_publication_summary" );
        if ( ! Fields::has_value( $summary ) ) {
            $summary = Fields::option( "summary" );
        }
        $mission = Fields::raw_option( "smpi_mission_statement" );
        if ( ! Fields::has_value( $mission ) ) {
            $mission = Fields::option( "mission_statement" );
        }
        $logo = $this->normalize_logo( Fields::option( "logo" ) );
        $org_id = trailingslashit( home_url( "/" ) ) . "#organization";
        $publication_user_id = absint( Fields::option( "publication_user", Settings::get( "system_publication_user_id", 0 ) ) );
        $same_as = $this->publication_same_as( $publication_user_id );
        $policy_map = [
            "publishingPrinciples" => [ "publishing_principles", "publishing_principles" ],
            "verificationFactCheckingPolicy" => [ "verification_fact_checking_policy", "verification_fact_checking_policy" ],
            "correctionsPolicy" => [ "corrections_policy", "corrections_policy" ],
            "ethicsPolicy" => [ "ethics_policy", "ethics_policy" ],
            "diversityPolicy" => [ "diversity_policy", "diversity_policy" ],
            "diversityStaffingReport" => [ "diversity_staffing_report", "diversity_staffing_report" ],
            "masthead" => [ "masthead", "masthead" ],
            "missionCoveragePrioritiesPolicy" => [ "mission_coverage_priorities_policy", "mission_coverage_priorities_policy" ],
            "noBylinesPolicy" => [ "no_bylines_policy", "no_bylines_policy" ],
            "unnamedSourcesPolicy" => [ "unnamed_sources_policy", "unnamed_sources_policy" ],
            "actionableFeedbackPolicy" => [ "actionable_feedback_policy", "actionable_feedback_policy" ],
        ];

        $schema = [
            "@type" => "NewsMediaOrganization",
            "@id" => $org_id,
            "name" => get_bloginfo( "name" ),
            "legalName" => $this->text_field( "legal_name" ),
            "alternateName" => $this->csv_field( "alternate_name" ),
            "url" => $website ?: home_url( "/" ),
            "description" => wp_strip_all_tags( (string) ( $summary ?: $mission ?: get_bloginfo( "description" ) ) ),
            "slogan" => wp_strip_all_tags( (string) $mission ),
            "logo" => $logo,
            "image" => $logo,
            "founder" => array_merge( $this->normalize_founders( Fields::option( "founders", [] ) ), $this->normalize_founder_users( Fields::option( "founder_users", [] ) ) ),
            "foundingDate" => Fields::option( "founding_date" ),
            "foundingLocation" => $this->place_from_fields( "founding_location", "founding_location_url" ),
            "address" => $this->postal_address(),
            "location" => $this->place_from_headquarters(),
            "email" => Fields::option( "contact_email" ) ? sanitize_email( (string) Fields::option( "contact_email" ) ) : null,
            "telephone" => $this->text_field( "telephone" ),
            "contactPoint" => $this->contact_points(),
            "parentOrganization" => $this->parent_organization(),
            "ownershipFundingInfo" => wp_strip_all_tags( (string) Fields::option( "ownership_funding_info" ) ),
            "areaServed" => $this->csv_field( "area_served" ),
            "knowsAbout" => $this->csv_field( "knows_about" ),
            "keywords" => $this->csv_field( "knows_about" ),
            "sameAs" => $same_as,
        ];

        foreach ( $policy_map as $property => $config ) {
            $url = $this->policy_url( $config[0], $config[1] );
            if ( $url ) {
                $schema[ $property ] = $url;
            }
        }

        return $this->clean_schema( $schema );
    }

    private function homepage_item_list( string $list_id, string $org_id ): array {
        $post_types = [ "post" ];
        if ( post_type_exists( "press-release" ) ) {
            $post_types[] = "press-release";
        }
        $posts = get_posts( [ "post_type" => $post_types, "post_status" => "publish", "posts_per_page" => 20, "ignore_sticky_posts" => true, "suppress_filters" => false ] );
        $items = [];
        $position = 1;
        foreach ( $posts as $post ) {
            $permalink = get_permalink( $post );
            $items[] = $this->clean_schema( [
                "@type" => "ListItem",
                "position" => $position,
                "url" => $permalink,
                "name" => get_the_title( $post ),
                "item" => [
                    "@type" => ArticleTypes::schema_type_for_post( (int) $post->ID ),
                    "@id" => $permalink . "#article",
                    "url" => $permalink,
                    "name" => get_the_title( $post ),
                    "headline" => get_the_title( $post ),
                    "datePublished" => get_the_date( DATE_W3C, $post ),
                    "dateModified" => get_the_modified_date( DATE_W3C, $post ),
                    "publisher" => [ "@id" => $org_id ],
                ],
            ] );
            $position++;
        }
        return $this->clean_schema( [ "@type" => "ItemList", "@id" => $list_id, "itemListOrder" => "https://schema.org/ItemListOrderDescending", "numberOfItems" => count( $items ), "itemListElement" => $items ] );
    }

    private function author_entity( int $user_id ): array {
        $user = $user_id ? get_userdata( $user_id ) : false;
        if ( ! $user ) {
            return [];
        }
        $url = get_author_posts_url( $user_id );
        $description = get_the_author_meta( "description", $user_id );
        $title = $this->user_field( $user_id, "title" ) ?: $this->user_field( $user_id, "job_title" );
        return $this->clean_schema( [
            "@type" => "Person",
            "@id" => $url . "#person",
            "name" => $user->display_name,
            "url" => $url,
            "image" => $this->user_avatar_url( $user_id ),
            "description" => wp_strip_all_tags( (string) $description ),
            "jobTitle" => wp_strip_all_tags( (string) $title ),
            "sameAs" => $this->user_same_as( $user_id ),
        ] );
    }

    private function author_entities_for_post( int $post_id ): array {
        $authors = [];
        foreach ( MultiAuthors::author_ids_for_post( $post_id, true ) as $user_id ) {
            $entity = $this->author_entity( (int) $user_id );
            if ( ! empty( $entity["@id"] ) ) {
                $authors[ (string) $entity["@id"] ] = $entity;
            }
        }
        return array_values( $authors );
    }

    private function user_avatar_url( int $user_id ): string {
        try {
            $avatar = get_avatar_url( $user_id, [ "size" => 256 ] );
            return is_string( $avatar ) ? $avatar : "";
        } catch ( \Throwable $e ) {
            return "";
        }
    }

    private function primary_image_entity( int $post_id, string $id, $fallback_logo = null ): array {
        $image = $this->featured_image_entity( $post_id, $id );
        if ( $image ) {
            return $image;
        }

        if ( is_array( $fallback_logo ) && ! empty( $fallback_logo["url"] ) ) {
            $width = isset( $fallback_logo["width"] ) && (int) $fallback_logo["width"] > 0 ? (int) $fallback_logo["width"] : null;
            $height = isset( $fallback_logo["height"] ) && (int) $fallback_logo["height"] > 0 ? (int) $fallback_logo["height"] : null;
            return $this->clean_schema( [ "@type" => "ImageObject", "@id" => $id, "url" => $fallback_logo["url"], "contentUrl" => $fallback_logo["url"], "thumbnailUrl" => $fallback_logo["url"], "width" => $width, "height" => $height, "caption" => get_bloginfo( "name" ) ] );
        }

        return [];
    }

    private function featured_image_entity( int $post_id, string $id ): array {
        $attachment_id = get_post_thumbnail_id( $post_id );
        if ( ! $attachment_id ) {
            return [];
        }
        $src = wp_get_attachment_image_src( $attachment_id, "full" );
        if ( ! $src ) {
            return [];
        }
        $width = isset( $src[1] ) && (int) $src[1] > 0 ? (int) $src[1] : null;
        $height = isset( $src[2] ) && (int) $src[2] > 0 ? (int) $src[2] : null;
        $url = esc_url_raw( (string) $src[0] );
        return $this->clean_schema( [ "@type" => "ImageObject", "@id" => $id, "url" => $url, "contentUrl" => $url, "thumbnailUrl" => $url, "width" => $width, "height" => $height, "name" => get_the_title( $attachment_id ), "caption" => wp_strip_all_tags( (string) wp_get_attachment_caption( $attachment_id ) ), "representativeOfPage" => true ] );
    }

    private function breadcrumb_entity( int $post_id, string $id ): array {
        $items = [ [ "@type" => "ListItem", "position" => 1, "name" => get_bloginfo( "name" ), "item" => home_url( "/" ) ] ];
        $cats = get_the_category( $post_id );
        if ( ! empty( $cats ) ) {
            $items[] = [ "@type" => "ListItem", "position" => 2, "name" => $cats[0]->name, "item" => get_category_link( $cats[0] ) ];
            $position = 3;
        } else {
            $position = 2;
        }
        $items[] = [ "@type" => "ListItem", "position" => $position, "name" => get_the_title( $post_id ), "item" => get_permalink( $post_id ) ];
        return $this->clean_schema( [ "@type" => "BreadcrumbList", "@id" => $id, "itemListElement" => $items ] );
    }

    private function faq_entity( int $post_id, string $id, array $rows, string $webpage_id = "" ): array {
        $items = [];
        foreach ( $rows as $row ) {
            $items[] = [ "@type" => "Question", "name" => $row["question"], "acceptedAnswer" => [ "@type" => "Answer", "text" => wp_strip_all_tags( (string) $row["answer"] ) ] ];
        }
        $webpage_ref = "" !== $webpage_id ? [ "@id" => $webpage_id ] : null;
        return $this->clean_schema( [ "@type" => "FAQPage", "@id" => $id, "url" => get_permalink( $post_id ) . "#faq", "isPartOf" => $webpage_ref, "mainEntityOfPage" => $webpage_ref, "mainEntity" => $items ] );
    }

    private function post_article_body( \WP_Post $post ): string {
        $content = strip_shortcodes( (string) $post->post_content );
        $content = wp_strip_all_tags( $content );
        $content = preg_replace( "/\s+/", " ", $content );
        return trim( (string) $content );
    }

    private function post_word_count( \WP_Post $post ): int {
        $body = $this->post_article_body( $post );
        return $body ? count( preg_split( "/\s+/", $body ) ?: [] ) : 0;
    }

    private function post_things( int $post_id, string $taxonomy ): array {
        $terms = get_the_terms( $post_id, $taxonomy );
        if ( ! is_array( $terms ) ) {
            return [];
        }

        $items = [];
        foreach ( $terms as $term ) {
            $url = get_term_link( $term, $taxonomy );
            $items[] = $this->clean_schema( [ "@type" => "Thing", "name" => $term->name ?? "", "url" => is_wp_error( $url ) ? "" : $url ] );
        }
        return array_values( array_filter( $items ) );
    }

    private function site_last_modified(): string {
        $modified = get_lastpostmodified( "blog" );
        return $modified ? mysql2date( DATE_W3C, $modified, false ) : current_time( DATE_W3C );
    }

    private function post_description( \WP_Post $post ): string {
        $excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
        return wp_strip_all_tags( (string) $excerpt );
    }

    private function article_sections( int $post_id ): array {
        $terms = get_the_terms( $post_id, "category" );
        if ( ! is_array( $terms ) ) {
            return [];
        }
        return array_values( array_filter( array_map( static fn( $term ) => $term->name ?? "", $terms ) ) );
    }

    private function post_keywords( int $post_id ): array {
        $terms = get_the_terms( $post_id, "post_tag" );
        if ( ! is_array( $terms ) ) {
            return [];
        }
        return array_values( array_filter( array_map( static fn( $term ) => $term->name ?? "", $terms ) ) );
    }

    private function normalize_logo( $logo ) {
        $url = "";
        $width = null;
        $height = null;
        if ( is_array( $logo ) && ! empty( $logo["url"] ) ) {
            $url = esc_url_raw( (string) $logo["url"] );
            $width = isset( $logo["width"] ) ? (int) $logo["width"] : null;
            $height = isset( $logo["height"] ) ? (int) $logo["height"] : null;
        } elseif ( is_numeric( $logo ) ) {
            $src = wp_get_attachment_image_src( (int) $logo, "full" );
            if ( $src ) {
                $url = esc_url_raw( (string) $src[0] );
                $width = isset( $src[1] ) ? (int) $src[1] : null;
                $height = isset( $src[2] ) ? (int) $src[2] : null;
            }
        } elseif ( is_string( $logo ) ) {
            $url = esc_url_raw( $logo );
        }
        if ( ! $url ) {
            $url = get_site_icon_url( 512 );
        }
        return $url ? $this->clean_schema( [ "@type" => "ImageObject", "url" => $url, "width" => $width, "height" => $height ] ) : null;
    }

    private function normalize_founders( $founders ): array {
        $founders = is_array( $founders ) ? $founders : [ $founders ];
        $items = [];
        foreach ( $founders as $founder ) {
            $founder_id = $this->founder_profile_id( $founder );
            if ( ! $founder_id ) {
                continue;
            }
            $items[] = [ "@type" => "Person", "name" => get_the_title( $founder_id ), "url" => get_permalink( $founder_id ) ];
        }
        return $items;
    }

    private function founder_profile_id( $founder ): int {
        if ( is_array( $founder ) && isset( $founder["profile"] ) ) {
            $founder = $founder["profile"];
        }
        if ( is_object( $founder ) && isset( $founder->ID ) ) {
            return (int) $founder->ID;
        }
        return is_numeric( $founder ) ? (int) $founder : 0;
    }

    private function normalize_founder_users( $users ): array {
        $users = is_array( $users ) ? $users : [ $users ];
        $items = [];
        foreach ( $users as $user_id ) {
            $user_id = is_object( $user_id ) && isset( $user_id->ID ) ? (int) $user_id->ID : (int) $user_id;
            $user = $user_id ? get_user_by( "id", $user_id ) : false;
            if ( ! $user ) {
                continue;
            }
            $items[] = [ "@type" => "Person", "name" => $user->display_name, "url" => get_author_posts_url( $user_id ) ];
        }
        return $items;
    }

    private function text_field( string $field ): string {
        return wp_strip_all_tags( (string) Fields::option( $field ) );
    }

    private function csv_field( string $field ): array {
        $value = Fields::option( $field );
        if ( is_array( $value ) ) {
            return array_values( array_filter( array_map( "strval", $value ) ) );
        }
        $parts = preg_split( "/[,\\n]+/", (string) $value );
        return array_values( array_filter( array_map( static fn( $item ) => trim( wp_strip_all_tags( $item ) ), $parts ?: [] ) ) );
    }

    private function policy_url( string $field, string $page_type ): string {
        $value = Fields::option( $field );
        $url = $this->value_to_url( $value );
        if ( $url ) {
            return $url;
        }
        $page_id = Settings::page_assignment_id( $page_type );
        return $page_id ? (string) get_permalink( $page_id ) : "";
    }

    private function value_to_url( $value ): string {
        if ( is_object( $value ) && isset( $value->ID ) ) {
            return (string) get_permalink( (int) $value->ID );
        }
        if ( is_numeric( $value ) ) {
            return (string) get_permalink( (int) $value );
        }
        if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
            return esc_url_raw( $value );
        }
        return "";
    }

    private function place_from_headquarters(): array {
        $name = Fields::option( "headquarters" );
        if ( ! $name ) {
            return [];
        }
        return $this->clean_schema( [ "@type" => "Place", "name" => wp_strip_all_tags( (string) $name ), "sameAs" => Fields::option( "headquarters_wikipedia_url" ) ] );
    }

    private function place_from_fields( string $name_field, string $url_field ): array {
        $name = Fields::option( $name_field );
        if ( ! $name ) {
            return [];
        }
        return $this->clean_schema( [ "@type" => "Place", "name" => wp_strip_all_tags( (string) $name ), "sameAs" => Fields::option( $url_field ) ] );
    }

    private function postal_address(): array {
        $value = Fields::option( "postal_address" );
        if ( ! is_array( $value ) ) {
            return [];
        }
        return $this->clean_schema( [
            "@type" => "PostalAddress",
            "streetAddress" => $value["street_address"] ?? "",
            "addressLocality" => $value["address_locality"] ?? "",
            "addressRegion" => $value["address_region"] ?? "",
            "postalCode" => $value["postal_code"] ?? "",
            "addressCountry" => $value["address_country"] ?? "",
        ] );
    }

    private function parent_organization(): array {
        $group = Fields::option( "parent_organization", [] );
        $name = is_array( $group ) ? ( $group["name"] ?? "" ) : "";
        $url = is_array( $group ) ? ( $group["url"] ?? "" ) : "";

        if ( ! $name ) {
            $name = Fields::option( "parent_organization_name" );
        }
        if ( ! $url ) {
            $url = Fields::option( "parent_organization_url" );
        }
        if ( ! $name && ! $url ) {
            return [];
        }
        return $this->clean_schema( [ "@type" => "Organization", "name" => wp_strip_all_tags( (string) $name ), "url" => $url ] );
    }

    private function contact_points(): array {
        $rows = Fields::option( "contact_points", [] );
        if ( ! is_array( $rows ) ) {
            return [];
        }
        $items = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $items[] = $this->clean_schema( [ "@type" => "ContactPoint", "contactType" => $row["contact_type"] ?? "", "email" => $row["email"] ?? "", "telephone" => $row["telephone"] ?? "", "url" => $row["url"] ?? "" ] );
        }
        return array_values( array_filter( $items ) );
    }

    private function publication_same_as( int $user_id ): array {
        $items = array_values( array_filter( [ Fields::option( "google_news_url" ), Fields::option( "headquarters_wikipedia_url" ), Fields::option( "publication_muckrack_url" ) ] ) );
        return array_values( array_unique( array_merge( $items, $this->user_same_as( $user_id ) ) ) );
    }

    private function user_same_as( int $user_id ): array {
        if ( ! $user_id ) {
            return [];
        }
        $fields = [ "facebook_url", "instagram_url", "linkedin_url", "twitter_url", "x_url", "youtube_url", "crunchbase_url", "muckrack_url", "url_facebook", "url_instagram", "url_linkedin", "url_x", "url_youtube", "url_crunchbase", "website" ];
        $items = [];
        foreach ( $fields as $field ) {
            $value = $this->user_field( $user_id, $field );
            if ( is_scalar( $value ) && $value && filter_var( (string) $value, FILTER_VALIDATE_URL ) ) {
                $items[] = esc_url_raw( (string) $value );
            }
        }
        return array_values( array_unique( $items ) );
    }

    private function user_field( int $user_id, string $field ) {
        if ( function_exists( "get_field" ) ) {
            $value = get_field( $field, "user_" . $user_id );
            if ( Fields::has_value( $value ) ) {
                return is_scalar( $value ) ? (string) $value : $value;
            }
        }
        return get_user_meta( $user_id, $field, true );
    }

    private function clean_schema( array $schema ): array {
        foreach ( $schema as $key => $value ) {
            if ( is_array( $value ) ) {
                $value = $this->clean_schema( $value );
            }
            if ( null === $value || false === $value || "" === $value || [] === $value ) {
                unset( $schema[ $key ] );
            } else {
                $schema[ $key ] = $value;
            }
        }
        return $schema;
    }
}
