<?php
namespace smp_publication_integration\StructuredData;

use smp_publication_integration\Support\PublicationFounders;
use Hexa\PluginCore\DataNormalization\ValueNormalizer;
use Hexa\PluginCore\FaqSets\FaqSetManager;
use Hexa\PluginCore\FaqSets\FaqSourceResolver;
use Hexa\PluginCore\SchemaTools\SchemaDocumentRenderer;
use Hexa\PluginCore\SchemaTools\SchemaGraph;
use Hexa\PluginCore\SchemaTools\SchemaIds;
use Hexa\PluginCore\SchemaTools\SchemaNodes;
use Hexa\PluginCore\SchemaTools\SchemaPage;
use Hexa\PluginCore\SchemaTools\SchemaSources;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use smp_publication_integration\Content\ArticleTypes;
use smp_publication_integration\Content\MultiAuthors;
use smp_publication_integration\Content\PublicationContentTypes;
use smp_publication_integration\Support\Fields;
use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * The publication's schema: which pages SMP covers and the publication data
 * on them. Every node is built with HexaWP Core's schema library and printed
 * in Core's one shared schema block.
 *
 * SMP covers the whole publication site: the site organization and website on
 * every public page, the homepage collection, articles and press releases,
 * pages, and archives. Testimonials get no schema. More specific plugins
 * (verified profiles, organizations) add their nodes for their own pages and
 * win on any property they both set.
 */
class SchemaManager {
    public const SOURCE = "smp-publication";
    public const PRIORITY = 20;

    private const REST_NS = "smpi/v1";
    private const PRESS_RELEASE_POST_TYPE = "press-release";
    private const NO_SCHEMA_POST_TYPES = [ "testimonial" ];

    private ?int $person_site_user_id = null;

    public function register(): void {
        SchemaPage::register( self::SOURCE, [ $this, "current_nodes" ], self::PRIORITY );
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

    /**
     * The nodes SMP adds to the page being viewed.
     *
     * @return list<array<string,mixed>>
     */
    public function current_nodes(): array {
        if ( ! RuntimeContext::is_public_frontend() || is_404() ) {
            return [];
        }
        if ( is_front_page() || ( is_home() && ! get_option( "page_for_posts" ) ) ) {
            return $this->home_nodes();
        }
        if ( is_singular() ) {
            $post = get_queried_object();
            if ( ! $post instanceof \WP_Post || in_array( $post->post_type, self::NO_SCHEMA_POST_TYPES, true ) ) {
                return [];
            }
            return in_array( $post->post_type, PublicationContentTypes::active_article_post_types(), true )
                ? $this->article_nodes( $post )
                : $this->page_nodes( $post );
        }
        if ( is_archive() || is_home() ) {
            return $this->archive_nodes();
        }
        return $this->site_nodes();
    }

    public function rest_schema( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id = absint( $request->get_param( "post_id" ) );
        $url = (string) $request->get_param( "url" );
        if ( ! $post_id && "" !== $url ) {
            $post_id = url_to_postid( $url );
        }
        $context = $post_id > 0 && get_post( $post_id ) ? "single" : "home";
        $schema = "single" === $context ? $this->generate_single_schema_array( $post_id ) : $this->generate_home_schema_array();

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
        $items = [
            [
                "title" => get_bloginfo( "name" ) . " homepage graph",
                "schema" => $this->generate_home_schema_json(),
                "admin_link" => admin_url( "options-general.php?page=smp-publication-integration&tab=publication_options" ),
                "view_link" => home_url( "/" ),
                "validator_link" => SchemaGraph::validator_url( home_url( "/" ) ),
            ],
        ];

        $posts = get_posts( [ "post_type" => PublicationContentTypes::active_article_post_types(), "post_status" => "publish", "posts_per_page" => 3, "fields" => "ids" ] );
        foreach ( $posts as $post_id ) {
            $items[] = [
                "title" => get_the_title( $post_id ),
                "schema" => $this->generate_single_schema_json( (int) $post_id ),
                "admin_link" => get_edit_post_link( $post_id, "raw" ),
                "view_link" => get_permalink( $post_id ),
                "validator_link" => SchemaGraph::validator_url( (string) get_permalink( $post_id ) ),
            ];
        }

        return [ "total" => count( $items ), "batch" => count( $items ), "offset" => 0, "items" => $items ];
    }

    public function generate_schema_json(): string {
        return $this->generate_home_schema_json();
    }

    public function generate_schema_array(): array {
        return $this->generate_home_schema_array();
    }

    public function generate_home_schema_json(): string {
        return ( new SchemaDocumentRenderer() )->json( $this->generate_home_schema_array() );
    }

    public function generate_single_schema_json( int $post_id ): string {
        return ( new SchemaDocumentRenderer() )->json( $this->generate_single_schema_array( $post_id ) );
    }

    /** SMP's homepage graph on its own, for previews and checks. */
    public function generate_home_schema_array(): array {
        return self::document( $this->home_nodes() );
    }

    /** SMP's graph for one article or page on its own, for previews and checks. */
    public function generate_single_schema_array( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return $this->generate_home_schema_array();
        }
        $nodes = in_array( $post->post_type, PublicationContentTypes::active_article_post_types(), true )
            ? $this->article_nodes( $post )
            : $this->page_nodes( $post );
        return self::document( $nodes );
    }

    public static function schema_types( array $schema ): array {
        return SchemaGraph::types( $schema );
    }

    public static function integrity_report( int $post_id = 0 ): array {
        $self = new self();
        $schema = $post_id ? $self->generate_single_schema_array( $post_id ) : $self->generate_home_schema_array();
        $types = self::schema_types( $schema );
        $site_type = $self->person_site_user_id() > 0 ? "Person" : "NewsMediaOrganization";
        $expected = $post_id ? [ $site_type, "WebSite", "WebPage", ArticleTypes::schema_type_for_post( $post_id ) ] : [ $site_type, "WebSite", "CollectionPage", "ItemList" ];
        if ( $post_id && self::faq_rows_for_post( $post_id, true ) ) {
            $expected[] = "FAQPage";
        }

        $checks = [];
        foreach ( $expected as $type ) {
            $checks[] = [ "label" => $type . " node present", "status" => in_array( $type, $types, true ) ? "green" : "red" ];
        }

        $org = $schema["@graph"][0] ?? [];
        $checks[] = [ "label" => "Publisher name populated", "status" => ! empty( $org["name"] ) ? "green" : "yellow" ];
        $checks[] = [ "label" => "Publisher URL is a valid HTTP(S) URL", "status" => "" !== SchemaGraph::web_url( $org["url"] ?? "" ) ? "green" : "red" ];
        $checks[] = [ "label" => "Publisher logo populated", "status" => ! empty( $org["logo"] ) ? "green" : "yellow" ];
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

        $semantic_issues = SchemaGraph::validation_issues( $schema );
        $checks[] = [
            "label" => $semantic_issues ? count( $semantic_issues ) . " malformed schema property value(s) detected" : "Schema property values are semantically valid",
            "status" => $semantic_issues ? "red" : "green",
        ];

        return [ "context" => $post_id ? "single" : "home", "post_id" => $post_id, "types" => $types, "checks" => $checks, "semantic_issues" => $semantic_issues ];
    }

    public static function faq_rows_for_post( int $post_id, bool $schema_only = true ): array {
        if ( $schema_only && ! self::faq_schema_enabled_for_post( $post_id ) ) {
            return [];
        }

        return array_map(
            static fn( array $row ): array => $row + [ 'enabled_for_schema' => true ],
            ( new FaqSourceResolver() )->rows( Fields::get( $post_id, "post_faq_items", [] ) )
        );
    }

    // ─── Page types ─────────────────────────────────────────────────────────

    /**
     * The site's own entity and website, on every public page. A
     * publication site is its NewsMediaOrganization; a person's site (the
     * site's primary entity is a person) is that person, so the site never
     * claims a second, organization identity with the person's name.
     */
    private function site_nodes(): array {
        $site_entity = $this->person_site_user_id() > 0
            ? SchemaSources::user_person( $this->person_site_user_id(), SchemaIds::person() )
            : $this->publication_entity();
        return [ $site_entity, SchemaSources::website( $this->publisher_id(), [ "about" => SchemaNodes::ref( $this->publisher_id() ) ] ) ];
    }

    /** The @id of whoever publishes this site: the publication, or the person whose site it is. */
    private function publisher_id(): string {
        return $this->person_site_user_id() > 0 ? SchemaIds::person() : SchemaIds::organization();
    }

    /** The user a person's site belongs to, when the site's primary entity is a person. */
    private function person_site_user_id(): int {
        if ( null !== $this->person_site_user_id ) {
            return $this->person_site_user_id;
        }
        $this->person_site_user_id = 0;
        $entity = class_exists( \Hexa\PluginCore\EntitySources\CanonicalEntityResolver::class ) ? \Hexa\PluginCore\EntitySources\CanonicalEntityResolver::resolve() : null;
        if ( is_array( $entity ) && "person" === (string) ( $entity["entity_type"] ?? "" ) ) {
            $this->person_site_user_id = absint( $entity["attached_user_id"] ?? 0 );
        }
        return $this->person_site_user_id;
    }

    private function home_nodes(): array {
        $home = SchemaIds::home();
        $list_id = $home . "#homepage-itemlist";
        $posts = get_posts( [ "post_type" => PublicationContentTypes::active_article_post_types(), "post_status" => "publish", "posts_per_page" => 20, "ignore_sticky_posts" => true, "suppress_filters" => false ] );
        $list = SchemaNodes::item_list( array_map( [ $this, "article_summary" ], $posts ), $list_id, "descending" );

        $page = SchemaNodes::web_page(
            [
                "@id" => SchemaIds::webpage( $home ),
                "url" => $home,
                "name" => get_bloginfo( "name" ),
                "headline" => get_bloginfo( "name" ),
                "description" => get_bloginfo( "description" ),
                "isPartOf" => SchemaNodes::ref( SchemaIds::website() ),
                "about" => SchemaNodes::ref( $this->publisher_id() ),
                "publisher" => SchemaNodes::ref( $this->publisher_id() ),
                "mainEntity" => [] !== $list ? SchemaNodes::ref( $list_id ) : null,
                "inLanguage" => SchemaSources::language(),
                "dateModified" => $this->site_last_modified(),
            ],
            "CollectionPage"
        );

        return array_merge( $this->site_nodes(), [ $page, $list ] );
    }

    /** An article or press release with its page, authors, image, breadcrumb and FAQ. */
    private function article_nodes( \WP_Post $post ): array {
        $post_id = (int) $post->ID;
        $url = (string) get_permalink( $post );
        $webpage_id = SchemaIds::webpage( $url );
        $press_release = self::PRESS_RELEASE_POST_TYPE === $post->post_type;
        $type = ArticleTypes::schema_type_for_post( $post_id );

        $image = SchemaSources::post_image( $post_id, SchemaIds::primary_image( $url ) );
        $authors = $press_release ? array_filter( [ $this->press_release_issuer( $post ) ] ) : $this->author_entities_for_post( $post_id );
        $author_refs = $authors ? SchemaNodes::refs_to( $authors ) : [ SchemaNodes::ref( $this->publisher_id() ) ];
        $breadcrumb = SchemaSources::post_breadcrumb( $post_id, SchemaIds::breadcrumb( $url ) );
        $faq_rows = self::faq_rows_for_post( $post_id, true );
        $faq = $faq_rows ? $this->faq_entity( $post_id, $url . "#faq", $faq_rows, $webpage_id ) : [];
        $faq_ref = $faq ? [ SchemaNodes::ref( (string) $faq["@id"] ) ] : null;
        $text = SchemaSources::post_text( $post );
        $original = $press_release ? $this->original_url( $post_id, $url ) : "";

        $page = SchemaSources::post_page(
            $post,
            "WebPage",
            [
                "about" => SchemaNodes::ref( $this->publisher_id() ),
                "publisher" => SchemaNodes::ref( $this->publisher_id() ),
                "primaryImageOfPage" => $image ? SchemaNodes::ref( (string) $image["@id"] ) : null,
                "breadcrumb" => $breadcrumb ? SchemaNodes::ref( (string) $breadcrumb["@id"] ) : null,
                "mainEntity" => SchemaNodes::ref( SchemaIds::article( $url ) ),
                "hasPart" => $faq_ref,
            ]
        );

        $article = SchemaNodes::article(
            [
                "@id" => SchemaIds::article( $url ),
                "mainEntityOfPage" => SchemaNodes::ref( $webpage_id ),
                "isPartOf" => SchemaNodes::ref( SchemaIds::website() ),
                "headline" => get_the_title( $post ),
                "name" => ValueNormalizer::text( get_the_title( $post ) ),
                "description" => SchemaSources::post_description( $post ),
                "articleBody" => $text,
                "wordCount" => SchemaSources::word_count( $text ),
                "url" => $url,
                "datePublished" => get_the_date( DATE_W3C, $post ),
                "dateModified" => get_the_modified_date( DATE_W3C, $post ),
                "author" => 1 === count( $author_refs ) ? $author_refs[0] : $author_refs,
                "publisher" => SchemaNodes::ref( $this->publisher_id() ),
                "image" => $image ? SchemaNodes::ref( (string) $image["@id"] ) : null,
                "thumbnailUrl" => $image["url"] ?? null,
                "articleSection" => SchemaSources::term_names( $post_id, "category" ),
                "keywords" => SchemaSources::term_names( $post_id, "post_tag" ),
                "about" => SchemaSources::term_things( $post_id, "category" ),
                "mentions" => SchemaSources::term_things( $post_id, "post_tag" ),
                "isAccessibleForFree" => true,
                "inLanguage" => SchemaSources::language(),
                "genre" => $press_release ? "Press Release" : ArticleTypes::schema_type_label( $type ),
                "copyrightYear" => get_the_date( "Y", $post ),
                "copyrightHolder" => $press_release && $authors ? $author_refs[0] : SchemaNodes::ref( $this->publisher_id() ),
                "isBasedOn" => $original,
                "hasPart" => $faq_ref,
                "speakable" => [
                    "@type" => "SpeakableSpecification",
                    "cssSelector" => [ "h1", ".elementor-widget-theme-post-content p:first-of-type", ".entry-content p:first-of-type" ],
                ],
            ],
            $type
        );

        $nodes = array_merge( $this->site_nodes(), [ $page, $article ], $authors, [ $image, $breadcrumb, $faq ] );
        $schema = apply_filters( "smpi_single_schema_array", [ "@context" => "https://schema.org", "@graph" => array_values( array_filter( $nodes ) ) ], $post_id );
        return is_array( $schema ) && isset( $schema["@graph"] ) && is_array( $schema["@graph"] ) ? $schema["@graph"] : $nodes;
    }

    /** Any other single page or post type: its WebPage, image and breadcrumb. */
    private function page_nodes( \WP_Post $post ): array {
        $url = (string) get_permalink( $post );
        $image = SchemaSources::post_image( (int) $post->ID, SchemaIds::primary_image( $url ) );
        $breadcrumb = SchemaSources::post_breadcrumb( (int) $post->ID, SchemaIds::breadcrumb( $url ) );
        $type = $this->page_type( (int) $post->ID );
        $page = SchemaSources::post_page(
            $post,
            $type,
            [
                "about" => SchemaNodes::ref( $this->publisher_id() ),
                "publisher" => SchemaNodes::ref( $this->publisher_id() ),
                "mainEntity" => "AboutPage" === $type ? SchemaNodes::ref( $this->publisher_id() ) : null,
                "primaryImageOfPage" => $image ? SchemaNodes::ref( (string) $image["@id"] ) : null,
                "breadcrumb" => $breadcrumb ? SchemaNodes::ref( (string) $breadcrumb["@id"] ) : null,
            ]
        );
        return array_merge( $this->site_nodes(), [ $page, $image, $breadcrumb ] );
    }

    /** Category, tag, author and post type archives: a CollectionPage of their latest posts. */
    private function archive_nodes(): array {
        global $wp_query;
        $url = SchemaSources::current_url();
        if ( "" === $url ) {
            return $this->site_nodes();
        }
        $posts = $wp_query instanceof \WP_Query ? array_filter( $wp_query->posts, static fn( $post ): bool => $post instanceof \WP_Post ) : [];
        $list_id = SchemaIds::for_url( $url, "itemlist" );
        $list = SchemaNodes::item_list( array_map( [ $this, "article_summary" ], array_values( $posts ) ), $list_id );
        $breadcrumb = SchemaSources::archive_breadcrumb( SchemaIds::breadcrumb( $url ) );
        $object = get_queried_object();
        $person = $object instanceof \WP_User ? SchemaSources::user_person( (int) $object->ID ) : [];
        $description = $object instanceof \WP_Term ? term_description( $object ) : ( $object instanceof \WP_User ? get_the_author_meta( "description", $object->ID ) : "" );

        $page = SchemaNodes::web_page(
            [
                "@id" => SchemaIds::webpage( $url ),
                "url" => $url,
                "name" => SchemaSources::archive_title(),
                "description" => ValueNormalizer::text( $description ),
                "isPartOf" => SchemaNodes::ref( SchemaIds::website() ),
                "about" => $person ? SchemaNodes::ref( (string) $person["@id"] ) : SchemaNodes::ref( $this->publisher_id() ),
                "mainEntity" => $person ? SchemaNodes::ref( (string) $person["@id"] ) : ( $list ? SchemaNodes::ref( $list_id ) : null ),
                "breadcrumb" => $breadcrumb ? SchemaNodes::ref( (string) $breadcrumb["@id"] ) : null,
                "inLanguage" => SchemaSources::language(),
            ],
            $person ? "ProfilePage" : "CollectionPage"
        );
        return array_merge( $this->site_nodes(), [ $page, $person, $list, $breadcrumb ] );
    }

    // ─── Publication data ───────────────────────────────────────────────────

    private function publication_entity(): array {
        $canonical = Fields::canonical_entity();
        $summary = Fields::raw_option( "smpi_publication_summary" );
        if ( ! Fields::has_value( $summary ) ) {
            $summary = Fields::option( "summary" );
        }
        $mission = Fields::raw_option( "smpi_mission_statement" );
        if ( ! Fields::has_value( $mission ) ) {
            $mission = Fields::option( "mission_statement" );
        }
        $logo = SchemaSources::logo( Fields::option( "logo" ), SchemaIds::home() . "#logo" );
        $publication_user_id = $canonical ? absint( $canonical['attached_user_id'] ?? 0 ) : 0;
        if ( ! $publication_user_id ) {
            $publication_user_id = absint( Fields::option( "publication_user", Settings::get( "system_publication_user_id", 0 ) ) );
        }
        $policy_map = [
            "publishingPrinciples" => "publishing_principles",
            "verificationFactCheckingPolicy" => "verification_fact_checking_policy",
            "correctionsPolicy" => "corrections_policy",
            "ethicsPolicy" => "ethics_policy",
            "diversityPolicy" => "diversity_policy",
            "diversityStaffingReport" => "diversity_staffing_report",
            "masthead" => "masthead",
            "missionCoveragePrioritiesPolicy" => "mission_coverage_priorities_policy",
            "noBylinesPolicy" => "no_bylines_policy",
            "unnamedSourcesPolicy" => "unnamed_sources_policy",
            "actionableFeedbackPolicy" => "actionable_feedback_policy",
        ];
        $policies = [];
        foreach ( $policy_map as $property => $field ) {
            $policies[ $property ] = $this->policy_url( $field );
        }

        return SchemaNodes::organization(
            $policies + [
                "@id" => SchemaIds::organization(),
                "name" => $canonical ? (string) $canonical['name'] : get_bloginfo( "name" ),
                "legalName" => $this->text_field( "legal_name" ),
                "alternateName" => $this->csv_field( "alternate_name" ),
                "url" => Fields::option_url( "website", home_url( "/" ) ),
                "description" => ValueNormalizer::text( (string) ( $summary ?: $mission ?: get_bloginfo( "description" ) ) ),
                "slogan" => $summary && $mission && $mission !== $summary ? ValueNormalizer::text( (string) $mission ) : null,
                "logo" => $logo ?: null,
                "image" => $logo ?: null,
                "founder" => $this->founders(),
                "foundingDate" => Fields::option( "founding_date" ),
                "foundingLocation" => SchemaNodes::place( [ "name" => Fields::option( "founding_location" ), "same_as" => Fields::option_url( "founding_location_url" ) ] ),
                "address" => is_array( Fields::option( "postal_address" ) ) ? SchemaNodes::postal_address( Fields::option( "postal_address" ) ) : [],
                "location" => SchemaNodes::place( [ "name" => Fields::option( "headquarters" ), "same_as" => Fields::option_url( "headquarters_wikipedia_url" ) ] ),
                "email" => Fields::option( "contact_email" ),
                "telephone" => $this->text_field( "telephone" ),
                "contactPoint" => array_values( array_filter( array_map( [ SchemaNodes::class, "contact_point" ], array_filter( (array) Fields::option( "contact_points", [] ), "is_array" ) ) ) ),
                "parentOrganization" => $this->parent_organization(),
                "ownershipFundingInfo" => ValueNormalizer::text( (string) Fields::option( "ownership_funding_info" ) ),
                "areaServed" => $this->csv_field( "area_served" ),
                "knowsAbout" => $this->csv_field( "knows_about" ),
                "sameAs" => SchemaNodes::same_as(
                    Fields::option_url( "google_news_url" ),
                    Fields::option_url( "headquarters_wikipedia_url" ),
                    Fields::option_url( "publication_muckrack_url" ),
                    SchemaSources::user_same_as( $publication_user_id ),
                    $this->canonical_same_as( $canonical )
                ),
            ],
            "NewsMediaOrganization"
        );
    }

    /** The company that issued a press release, as an Organization. */
    private function press_release_issuer( \WP_Post $post ): array {
        $user_id = (int) $post->post_author;
        $user = $user_id ? get_userdata( $user_id ) : false;
        $issuer = [
            "name" => $user ? (string) $user->display_name : "",
            "url" => $user ? (string) get_author_posts_url( $user_id ) : "",
            "sameAs" => [ get_post_meta( $post->ID, "author_url", true ), $user ? SchemaSources::user_same_as( $user_id ) : [] ],
        ];
        $issuer = (array) apply_filters( "smpi_press_release_issuer", $issuer, $post );
        if ( "" === ValueNormalizer::text( $issuer["name"] ?? "" ) ) {
            return [];
        }
        $url = SchemaGraph::web_url( $issuer["url"] ?? "" );
        return SchemaNodes::organization(
            [
                "@id" => SchemaIds::for_url( "" !== $url ? $url : (string) get_permalink( $post ), "organization" ),
                "name" => ValueNormalizer::text( $issuer["name"] ),
                "url" => $url,
                "logo" => $issuer["logo"] ?? null,
                "sameAs" => SchemaNodes::same_as( $issuer["sameAs"] ?? [] ),
            ]
        );
    }

    /** The original URL of a syndicated press release, when it was first published elsewhere. */
    private function original_url( int $post_id, string $url ): string {
        foreach ( [ "_hpr_canonical_source_url", "original_post_url" ] as $key ) {
            $original = SchemaGraph::web_url( get_post_meta( $post_id, $key, true ) );
            if ( "" !== $original && untrailingslashit( $original ) !== untrailingslashit( $url ) ) {
                return $original;
            }
        }
        return "";
    }

    private function author_entities_for_post( int $post_id ): array {
        $authors = [];
        foreach ( MultiAuthors::author_ids_for_post( $post_id ) as $user_id ) {
            $user_id = (int) $user_id;
            $entity = SchemaSources::user_person( $user_id, $user_id === $this->person_site_user_id() ? SchemaIds::person() : '' );
            if ( ! empty( $entity["@id"] ) ) {
                $authors[ (string) $entity["@id"] ] = $entity;
            }
        }
        return array_values( $authors );
    }

    /** A short Article entry for item lists. */
    private function article_summary( \WP_Post $post ): array {
        $url = (string) get_permalink( $post );
        $type = in_array( $post->post_type, PublicationContentTypes::active_article_post_types(), true ) ? ArticleTypes::schema_type_for_post( (int) $post->ID ) : "WebPage";
        return SchemaNodes::article(
            [
                "@id" => SchemaIds::article( $url ),
                "url" => $url,
                "headline" => get_the_title( $post ),
                "name" => ValueNormalizer::text( get_the_title( $post ) ),
                "datePublished" => get_the_date( DATE_W3C, $post ),
                "dateModified" => get_the_modified_date( DATE_W3C, $post ),
                "publisher" => SchemaNodes::ref( $this->publisher_id() ),
            ],
            $type
        );
    }

    private function page_type( int $post_id ): string {
        if ( $post_id === Settings::page_assignment_id( "about_publication" ) ) {
            return "AboutPage";
        }
        if ( $post_id === Settings::page_assignment_id( "contact" ) ) {
            return "ContactPage";
        }
        return "WebPage";
    }

    private function faq_entity( int $post_id, string $id, array $rows, string $webpage_id = "" ): array {
        $node = ( new FaqSetManager() )->buildSchemaNode(
            $rows,
            [ 'id' => $id, 'url' => get_permalink( $post_id ) . '#faq', 'is_part_of' => $webpage_id ]
        );
        if ( '' !== $webpage_id ) {
            $node['mainEntityOfPage'] = [ '@id' => $webpage_id ];
        }
        return SchemaGraph::sanitize_urls( $node );
    }

    private static function faq_schema_enabled_for_post( int $post_id ): bool {
        $value = Fields::get( $post_id, "post_faq_schema_enabled", "__smpi_default_on__" );
        if ( "__smpi_default_on__" === $value || "" === $value || null === $value ) {
            return true;
        }

        return ! in_array( (string) $value, [ "0", "false", "off", "no" ], true );
    }

    private function site_last_modified(): string {
        $modified = get_lastpostmodified( "blog" );
        return $modified ? mysql2date( DATE_W3C, $modified, false ) : current_time( DATE_W3C );
    }

    /** Founders come only from HWS Base Tools. */
    private function founders(): array {
        $items = [];
        foreach ( PublicationFounders::all() as $person ) {
            $items[] = SchemaNodes::person( [ "name" => $person["name"], "url" => $person["url"] ] );
        }
        return array_values( array_filter( $items ) );
    }

    private function text_field( string $field ): string {
        return ValueNormalizer::text( (string) Fields::option( $field ) );
    }

    private function csv_field( string $field ): array {
        $value = Fields::option( $field );
        return is_array( $value ) ? array_values( array_filter( array_map( "strval", $value ) ) ) : ValueNormalizer::strings( (string) $value );
    }

    private function policy_url( string $field ): string {
        $value = Fields::option( $field );
        if ( is_object( $value ) && isset( $value->ID ) ) {
            return (string) get_permalink( (int) $value->ID );
        }
        if ( is_numeric( $value ) && (int) $value > 0 ) {
            return (string) get_permalink( (int) $value );
        }
        $url = SchemaGraph::web_url( $value );
        if ( "" !== $url ) {
            return $url;
        }
        $page_id = Settings::page_assignment_id( $field );
        return $page_id ? (string) get_permalink( $page_id ) : "";
    }

    private function parent_organization(): array {
        $group = Fields::option( "parent_organization", [] );
        $name = is_array( $group ) ? ( $group["name"] ?? "" ) : "";
        $url = is_array( $group ) ? SchemaGraph::web_url( $group["url"] ?? "" ) : "";
        $name = $name ?: Fields::option( "parent_organization_name" );
        $url = $url ?: Fields::option_url( "parent_organization_url" );
        return $name || $url ? SchemaNodes::organization( [ "name" => ValueNormalizer::text( (string) $name ), "url" => $url ] ) : [];
    }

    private function canonical_same_as( ?array $entity ): array {
        if ( ! $entity ) {
            return [];
        }
        $items = [];
        foreach ( [ 'same_as', 'identity_links' ] as $field ) {
            $value = \Hexa\PluginCore\EntitySources\CanonicalEntityResolver::field( $entity, $field, [] );
            $items[] = is_array( $value ) ? $value : [ $value ];
        }
        return SchemaNodes::same_as( ...$items );
    }

    /** @param list<array<string,mixed>> $nodes */
    private static function document( array $nodes ): array {
        $nodes = array_values( array_filter( $nodes ) );
        return [] === $nodes ? [] : SchemaGraph::standalone_nodes( SchemaGraph::sanitize_urls( [ "@context" => "https://schema.org", "@graph" => $nodes ] ) );
    }
}
