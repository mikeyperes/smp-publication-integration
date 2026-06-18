<?php
namespace smp_publication_integration\Content;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class ArticleTypes {
    public const TAXONOMY = "smpi_article_type";

    public function register(): void {
        add_action( "init", [ $this, "register_taxonomy" ], 8 );
        add_action( "init", [ $this, "ensure_terms" ], 20 );
    }

    public function register_taxonomy(): void {
        $post_types = [ "post" ];
        if ( post_type_exists( "press-release" ) ) {
            $post_types[] = "press-release";
        }

        register_taxonomy(
            self::TAXONOMY,
            $post_types,
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
                "show_admin_column" => true,
                "show_in_rest" => true,
                "hierarchical" => false,
                "rewrite" => false,
                "query_var" => true,
                "capabilities" => [
                    "manage_terms" => "manage_categories",
                    "edit_terms" => "manage_categories",
                    "delete_terms" => "manage_categories",
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
            if ( term_exists( $slug, self::TAXONOMY ) ) {
                continue;
            }
            wp_insert_term(
                $config["label"],
                self::TAXONOMY,
                [ "slug" => $slug, "description" => $config["description"] ]
            );
        }
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

        $terms = get_the_terms( $post_id, self::TAXONOMY );
        if ( is_array( $terms ) ) {
            foreach ( $terms as $term ) {
                $slug = isset( $term->slug ) ? (string) $term->slug : "";
                $config = self::terms()[ $slug ] ?? null;
                if ( $config && ! empty( $config["schema_type"] ) ) {
                    return (string) $config["schema_type"];
                }
            }
        }

        if ( "press-release" === get_post_type( $post_id ) ) {
            return "Article";
        }

        return "NewsArticle";
    }

    public static function schema_type_label( string $type ): string {
        foreach ( self::terms() as $config ) {
            if ( $type === $config["schema_type"] ) {
                return $config["label"];
            }
        }
        return $type;
    }
}
