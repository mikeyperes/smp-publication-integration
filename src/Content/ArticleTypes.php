<?php
namespace smp_publication_integration\Content;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class ArticleTypes {
    public const TAXONOMY = "smpi_article_type";
    private const FIELD_NAME = "smpi_article_type_radio";
    private const NONCE_NAME = "smpi_article_type_radio_nonce";
    private const NONCE_ACTION = "smpi_article_type_radio";

    public function register(): void {
        add_action( "init", [ $this, "register_taxonomy" ], 8 );
        add_action( "init", [ $this, "ensure_terms" ], 20 );
        add_action( "save_post", [ $this, "save_radio_selection" ], 20, 2 );
    }

    public function register_taxonomy(): void {
        if ( ! self::is_enabled() ) {
            return;
        }

        register_taxonomy(
            self::TAXONOMY,
            self::supported_post_types(),
            [
                "labels" => [
                    "name" => "Article Types",
                    "singular_name" => "Article Type",
                    "search_items" => "Search Article Types",
                    "all_items" => "All Article Types",
                    "edit_item" => "Edit Article Type",
                    "update_item" => "Update Article Type",
                    "add_new_item" => "Add Article Type",
                    "new_item_name" => "New Article Type",
                    "menu_name" => "Article Type",
                ],
                "public" => false,
                "show_ui" => true,
                "show_in_menu" => false,
                "show_admin_column" => true,
                "show_in_quick_edit" => false,
                "show_tagcloud" => false,
                "show_in_rest" => true,
                "hierarchical" => true,
                "meta_box_cb" => [ $this, "render_radio_metabox" ],
                "rewrite" => false,
                "query_var" => true,
                "capabilities" => [
                    "manage_terms" => "manage_options",
                    "edit_terms" => "manage_options",
                    "delete_terms" => "manage_options",
                    "assign_terms" => "edit_posts",
                ],
            ]
        );
    }

    public function ensure_terms(): void {
        if ( ! taxonomy_exists( self::TAXONOMY ) ) {
            return;
        }

        $is_cli = defined( "WP_CLI" ) && WP_CLI;
        if ( ! is_admin() && ! $is_cli ) {
            return;
        }

        foreach ( self::terms() as $slug => $config ) {
            $term = term_exists( $slug, self::TAXONOMY );
            if ( $term ) {
                $term_id = is_array( $term ) ? (int) $term["term_id"] : (int) $term;
                $current = get_term( $term_id, self::TAXONOMY );
                if (
                    $current instanceof \WP_Term
                    && (string) $current->name === (string) $config["label"]
                    && (string) $current->description === (string) $config["description"]
                ) {
                    continue;
                }

                wp_update_term(
                    $term_id,
                    self::TAXONOMY,
                    [
                        "name" => $config["label"],
                        "description" => $config["description"],
                    ]
                );
                continue;
            }
            wp_insert_term(
                $config["label"],
                self::TAXONOMY,
                [ "slug" => $slug, "description" => $config["description"] ]
            );
        }
    }

    public function render_radio_metabox( \WP_Post $post, array $box = [] ): void {
        if ( ! self::is_supported_post_type( (string) $post->post_type ) ) {
            return;
        }

        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
        $current = self::selected_slug_for_post( (int) $post->ID );
        if ( "" === $current ) {
            $current = self::default_slug_for_post( (int) $post->ID );
        }

        $is_live          = "publish" === (string) $post->post_status;
        $page_url         = $is_live ? self::current_page_url( $post ) : "";
        $validator_url    = $page_url ? "https://validator.schema.org/#url=" . rawurlencode( $page_url ) : "";
        $rich_results_url = $page_url ? "https://search.google.com/test/rich-results?url=" . rawurlencode( $page_url ) : "";

        CoreUi::render_assets();
        echo "<style>.smpi-article-type-box{display:grid;gap:12px}.smpi-article-type-box .hpc-small{margin:0;color:#6b7280;font-size:12px;line-height:1.45}.smpi-article-type-radio-list{display:grid;gap:3px}.smpi-article-type-choice{display:flex;align-items:center;flex-wrap:wrap;gap:9px;margin:0;padding:8px 10px;border:1px solid transparent;border-radius:9px;cursor:pointer;transition:background .12s ease,border-color .12s ease}.smpi-article-type-choice:hover{background:#f4f6f9}.smpi-article-type-box .smpi-article-type-choice input[type=radio]{margin:0;flex:0 0 auto;width:16px;height:16px;accent-color:#3157d5}.smpi-article-type-label{flex:1 1 auto;min-width:0;font-weight:600;font-size:13px;color:#1f2733;line-height:1.25}.smpi-article-type-schema{flex:0 0 auto;margin-left:auto;white-space:nowrap;font-size:10px;font-weight:600;line-height:1;letter-spacing:.02em;padding:4px 8px;border-radius:999px;background:#eef1f6;color:#5b6472}.smpi-article-type-choice:has(input:checked){background:#eef3ff;border-color:#cdd9f7}.smpi-article-type-choice:has(input:checked) .smpi-article-type-label{color:#16223a}.smpi-article-type-choice:has(input:checked) .smpi-article-type-schema{background:#dbe5ff;color:#2944ad}.smpi-article-type-box .smpi-article-type-option .hpc-inline-details{margin:0;padding:1px 8px 5px 35px}.smpi-article-type-box .hpc-inline-details summary{font-size:11px;font-weight:600;color:#8b93a1}.smpi-article-type-box .hpc-inline-details-body{font-size:11px;line-height:1.5;color:#5b6472;padding-left:0;margin-top:4px}.smpi-article-type-actions{display:grid;gap:7px;border-top:1px solid #e7ebf1;margin-top:2px;padding-top:12px}.smpi-article-type-box .smpi-article-type-actions .hpc-button{width:100%;justify-content:center;font-size:12px;font-weight:600;padding:9px 12px;border-radius:8px}.smpi-article-type-box .smpi-schema-link{background:none!important;border:0!important;box-shadow:none!important;padding:0!important;margin:0;color:#5b6472;font-size:11px;font-weight:600;text-decoration:underline;text-decoration-color:#c3cad6;text-underline-offset:2px;cursor:pointer}.smpi-article-type-box .smpi-schema-link:hover{color:#3157d5;text-decoration-color:#3157d5}.smpi-article-type-box .smpi-schema-link.hpc-external:after{font-size:.72em;font-weight:700;margin-left:3px;transform:none;opacity:.6}.smpi-article-type-actions .hpc-button{transition:background .14s ease,border-color .14s ease,color .14s ease,box-shadow .14s ease}.smpi-article-type-actions .hpc-button.secondary:hover{background:#eef3ff;border-color:#3157d5;color:#26408f;box-shadow:0 1px 2px rgba(49,87,213,.12)}.smpi-article-type-actions .hpc-button:not(.secondary):hover{background:#2543b0;border-color:#2543b0;box-shadow:0 1px 3px rgba(37,67,176,.28)}.smpi-article-type-actions .hpc-button:focus-visible{outline:2px solid #3157d5;outline-offset:2px}.smpi-article-type-actions .hpc-external:after{transform:none;font-size:.78em;font-weight:700;margin-left:6px;align-self:center;opacity:.8}.smpi-article-type-actions .smpi-action-disabled{background:#eef0f4!important;border-color:#d6dbe4!important;color:#8a93a3!important;cursor:not-allowed;box-shadow:none!important;pointer-events:none}.smpi-live-required{background:#fff8e1;border:1px solid #f2d675;border-radius:8px;color:#9a6700;font-size:12px;font-weight:800;line-height:1.4;margin:0;padding:9px 10px;text-align:center}</style>";
        echo "<div class=\"hpc-ui smpi-article-type-box\">";
        echo "<p class=\"hpc-small\">Select one schema-backed article type. Expand details only when needed.</p>";
        echo "<div class=\"smpi-article-type-radio-list\" role=\"radiogroup\" aria-label=\"Article Type\">";
        foreach ( self::terms() as $slug => $config ) {
            $id         = self::FIELD_NAME . "-" . sanitize_html_class( $slug );
            $schema_url = "https://schema.org/" . rawurlencode( (string) $config["schema_type"] );
            $details    = "<p>" . esc_html( (string) $config["description"] ) . "</p><p>" . CoreUi::external_link( $schema_url, "Schema object", "smpi-schema-link" ) . "</p>";
            echo "<div class=\"smpi-article-type-option\">";
            echo "<label class=\"smpi-article-type-choice\" for=\"" . esc_attr( $id ) . "\">";
            echo "<input id=\"" . esc_attr( $id ) . "\" type=\"radio\" name=\"" . esc_attr( self::FIELD_NAME ) . "\" value=\"" . esc_attr( $slug ) . "\" " . checked( $current, $slug, false ) . ">";
            echo "<span class=\"smpi-article-type-label\">" . esc_html( $config["label"] ) . "</span>";
            echo "<span class=\"smpi-article-type-schema\">" . esc_html( $config["schema_type"] ) . "</span>";
            echo "</label>";
            echo CoreUi::inline_details( "Details", $details );
            echo "</div>";
        }
        echo "</div>";
        echo "<div class=\"hpc-actions hpc-actions-bottom smpi-article-type-actions\">";
        if ( $is_live && "" !== $page_url ) {
            echo CoreUi::external_link( $page_url, "Open current page" );
            echo CoreUi::external_link( $validator_url, "Schema validator", "hpc-button" );
            echo CoreUi::external_link( $rich_results_url, "Rich results test" );
        } else {
            echo "<span class=\"hpc-button secondary smpi-action-disabled\" aria-disabled=\"true\">Open current page</span>";
            echo "<span class=\"hpc-button smpi-action-disabled\" aria-disabled=\"true\">Schema validator</span>";
            echo "<span class=\"hpc-button secondary smpi-action-disabled\" aria-disabled=\"true\">Rich results test</span>";
            echo "<p class=\"smpi-live-required\">Must be live to test schema. Publish this post first.</p>";
        }
        echo "</div></div>";
    }

    public function save_radio_selection( int $post_id, \WP_Post $post ): void {
        if ( ! self::is_enabled() || ! self::is_supported_post_type( (string) $post->post_type ) || ! taxonomy_exists( self::TAXONOMY ) ) {
            return;
        }
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( ! current_user_can( "edit_post", $post_id ) || ! current_user_can( "assign_terms", self::TAXONOMY ) ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
            return;
        }

        $posted = isset( $_POST[ self::FIELD_NAME ] ) ? sanitize_key( wp_unslash( $_POST[ self::FIELD_NAME ] ) ) : "";
        $slug = array_key_exists( $posted, self::terms() ) ? $posted : self::default_slug_for_post( $post_id );
        wp_set_object_terms( $post_id, $slug, self::TAXONOMY, false );
    }

    public static function is_enabled(): bool {
        return Settings::bool( "article_types_enabled" );
    }

    public static function supported_post_types(): array {
        $post_types = [ "post" ];
        foreach ( [ "press-release", "imported-news" ] as $post_type ) {
            if ( post_type_exists( $post_type ) ) {
                $post_types[] = $post_type;
            }
        }
        return array_values( array_unique( $post_types ) );
    }

    public static function is_supported_post_type( string $post_type ): bool {
        return in_array( $post_type, self::supported_post_types(), true );
    }

    public static function terms(): array {
        return [
            "editorial-news" => [ "label" => "Editorial News", "schema_type" => "NewsArticle", "description" => "Default factual editorial news article. Safe fallback for normal news posts." ],
            "analysis" => [ "label" => "Analysis", "schema_type" => "AnalysisNewsArticle", "description" => "Interpretive article that analyzes news, data, or trends." ],
            "opinion" => [ "label" => "Opinion", "schema_type" => "OpinionNewsArticle", "description" => "Opinion, column, or clearly subjective editorial article." ],
            "reportage" => [ "label" => "Reportage", "schema_type" => "ReportageNewsArticle", "description" => "Original journalistic reporting. Do not use for opinion, sponsored, satire, or PR content." ],
            "press-release" => [ "label" => "Press Release", "schema_type" => "Article", "description" => "Press release content. Handoff to Hexa PR Wire should use this for the press-release CPT." ],
            "sponsored" => [ "label" => "Sponsored", "schema_type" => "AdvertiserContentArticle", "description" => "Sponsored or partner content that should not be described as independent newsroom reporting." ],
        ];
    }

    public static function schema_type_for_post( int $post_id ): string {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return "NewsArticle";
        }

        $selected = self::selected_slug_for_post( $post_id );
        $slug = "" !== $selected ? $selected : self::default_slug_for_post( $post_id );
        $config = self::terms()[ $slug ] ?? null;

        return $config && ! empty( $config["schema_type"] ) ? (string) $config["schema_type"] : "NewsArticle";
    }

    public static function schema_type_label( string $type ): string {
        foreach ( self::terms() as $config ) {
            if ( $type === $config["schema_type"] ) {
                return $config["label"];
            }
        }
        return $type;
    }

    private static function current_page_url( \WP_Post $post ): string {
        $url = "publish" === (string) $post->post_status ? (string) get_permalink( $post ) : "";
        return $url ? esc_url_raw( $url ) : "";
    }

    private static function selected_slug_for_post( int $post_id ): string {
        $terms = get_the_terms( $post_id, self::TAXONOMY );
        if ( ! is_array( $terms ) ) {
            return "";
        }
        foreach ( $terms as $term ) {
            $slug = isset( $term->slug ) ? (string) $term->slug : "";
            if ( array_key_exists( $slug, self::terms() ) ) {
                return $slug;
            }
        }
        return "";
    }

    private static function default_slug_for_post( int $post_id ): string {
        if ( "press-release" === get_post_type( $post_id ) ) {
            return "press-release";
        }

        $taxonomy_slug = self::taxonomy_fallback_slug_for_post( $post_id );
        return "" !== $taxonomy_slug ? $taxonomy_slug : "editorial-news";
    }

    private static function taxonomy_fallback_slug_for_post( int $post_id ): string {
        $aliases = [
            "sponsored" => [ "sponsored", "sponsored-content", "partner-content", "advertiser-content", "advertorial" ],
            "press-release" => [ "press-release", "press-releases", "news-release", "press", "pr" ],
            "opinion" => [ "opinion", "opinions", "column", "columns", "editorial" ],
            "analysis" => [ "analysis", "analyses", "news-analysis", "market-analysis", "data-analysis" ],
            "reportage" => [ "reportage", "reporting", "investigation", "investigations", "investigative" ],
        ];

        foreach ( [ "category", "post_tag" ] as $taxonomy ) {
            $terms = get_the_terms( $post_id, $taxonomy );
            if ( ! is_array( $terms ) ) {
                continue;
            }
            foreach ( $terms as $term ) {
                $candidates = array_filter( [
                    isset( $term->slug ) ? sanitize_key( (string) $term->slug ) : "",
                    isset( $term->name ) ? sanitize_title( (string) $term->name ) : "",
                ] );
                foreach ( $aliases as $article_type_slug => $matches ) {
                    if ( array_intersect( $candidates, $matches ) ) {
                        return $article_type_slug;
                    }
                }
            }
        }

        return "";
    }
}
