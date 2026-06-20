<?php
namespace smp_publication_integration\Content;

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

        foreach ( self::terms() as $slug => $config ) {
            $term = term_exists( $slug, self::TAXONOMY );
            if ( $term ) {
                wp_update_term(
                    is_array( $term ) ? (int) $term["term_id"] : (int) $term,
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

        $page_url = self::current_page_url( $post );
        $validator_url = $page_url ? "https://validator.schema.org/#url=" . rawurlencode( $page_url ) : "";

        echo "<p class=\"description\">Select exactly one predefined schema article type. Free-text article types are disabled. Each type links to its schema object and the current page validator. Save/update after changing the type before validating.</p>";
        echo "<div class=\"smpi-article-type-radio-list\" role=\"radiogroup\" aria-label=\"Article Type\">";
        foreach ( self::terms() as $slug => $config ) {
            $id = self::FIELD_NAME . "-" . sanitize_html_class( $slug );
            $schema_url = "https://schema.org/" . rawurlencode( (string) $config["schema_type"] );
            echo "<div class=\"smpi-article-type-option\" style=\"display:block;margin:0 0 14px;line-height:1.35;\">";
            echo "<label for=\"" . esc_attr( $id ) . "\" style=\"display:block;margin:0;\">";
            echo "<input id=\"" . esc_attr( $id ) . "\" type=\"radio\" name=\"" . esc_attr( self::FIELD_NAME ) . "\" value=\"" . esc_attr( $slug ) . "\" " . checked( $current, $slug, false ) . "> ";
            echo "<strong>" . esc_html( $config["label"] ) . "</strong> <code>" . esc_html( $config["schema_type"] ) . "</code><br>";
            echo "<span class=\"description\" style=\"display:block;margin-left:28px;\">" . esc_html( $config["description"] ) . "</span>";
            echo "</label>";
            echo "<span class=\"smpi-article-type-actions\" style=\"display:flex;gap:8px;flex-wrap:wrap;margin:5px 0 0 28px;\">";
            echo "<a href=\"" . esc_url( $schema_url ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">Schema object</a>";
            if ( "" !== $page_url ) {
                echo "<a href=\"" . esc_url( $page_url ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">Open current page</a>";
                echo "<a href=\"" . esc_url( $validator_url ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">Verify schema</a>";
            } else {
                echo "<span class=\"description\">Save this post before page validation links are available.</span>";
            }
            echo "</span>";
            echo "</div>";
        }
        echo "</div>";
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
        $url = (string) get_permalink( $post );
        if ( ! in_array( (string) $post->post_status, [ "publish", "future" ], true ) ) {
            $preview_url = get_preview_post_link( $post );
            if ( is_string( $preview_url ) && "" !== $preview_url ) {
                $url = $preview_url;
            }
        }
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
