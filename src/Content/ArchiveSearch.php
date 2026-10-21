<?php
namespace smp_publication_integration\Content;

use Hexa\PluginCore\ListingSearch\ListingSearchRenderer;
use smp_publication_integration\Authorship\AuthorAssignmentRepository;
use smp_publication_integration\Authorship\AuthorQueryIntegration;
use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Search and filters on the publication's category, tag, author and search pages.
 *
 * The shared listing search (HexaWP Core, `[hws_search]` from HWS Base Tools) narrows the
 * page's own story list. This class tells it which list that is on a publication: the archive's
 * Loop Grid, Posts or Archive Posts widget that shows the current page's stories (Elementor's
 * "Current Query", or a loop pinned to the page's own category or tag), or the lead and rest listings (smpi_archive_lead / smpi_archive_rest).
 * Unless the template already places the shortcode, the search box is added right above the
 * first of them, so every existing publication gets it without template edits. An archive the
 * theme draws itself (no Elementor template) gets the box above its first story, and its
 * stories are wrapped as the list to refresh.
 *
 * It also keeps the publication's own rules inside the search: co-authored stories belong to
 * an author's page and author filter, and archive visibility rules apply to every count.
 */
final class ArchiveSearch {
    public const SETTING = "archive_search_enabled";
    public const BOX_CLASS = "smpi-archive-search";

    private const WIDGETS = [ "loop-grid", "posts", "archive-posts" ];

    private bool $box_printed = false;

    private bool $wrapper_open = false;

    public function register(): void {
        add_filter( "hexa_plugin_core_listing_search_target", [ $this, "is_listing" ], 10, 2 );
        add_action( "elementor/frontend/widget/before_render", [ $this, "print_search_box" ], 5 );
        add_action( "loop_start", [ $this, "open_theme_listing" ] );
        add_action( "loop_end", [ $this, "close_theme_listing" ] );
        add_filter( "hexa_plugin_core_listing_context_args", [ $this, "context_args" ], 10, 2 );
        add_filter( "hexa_plugin_core_query_filter_author_condition", [ $this, "co_author_condition" ], 10, 4 );
    }

    public static function enabled(): bool {
        return Settings::bool( self::SETTING );
    }

    /** Whether a widget is the archive's story list. @param bool $target @param object|null $widget */
    public function is_listing( $target, $widget = null ): bool {
        if ( $target ) {
            return true;
        }
        if ( ! is_object( $widget ) || ! method_exists( $widget, "get_name" ) || ! self::archive_page() ) {
            return false;
        }

        $name = (string) $widget->get_name();
        if ( ! in_array( $name, self::WIDGETS, true ) ) {
            return false;
        }
        if ( "archive-posts" === $name ) {
            return true;
        }

        $settings = method_exists( $widget, "get_settings_for_display" ) ? (array) $widget->get_settings_for_display() : [];
        foreach ( $settings as $key => $value ) {
            if ( ! is_string( $key ) || ! is_string( $value ) ) {
                continue;
            }
            if ( "query_id" === substr( $key, -8 ) && in_array( sanitize_key( $value ), [ ArchiveListing::LEAD_QUERY_ID, ArchiveListing::REST_QUERY_ID ], true ) ) {
                return true;
            }
            if ( "_post_type" === substr( $key, -10 ) && "current_query" === $value ) {
                return true;
            }
        }

        // A loop pinned to the page's own category or tag (Include: Terms) lists that page's stories too.
        $term = ( is_category() || is_tag() || is_tax() ) ? get_queried_object() : null;
        if ( $term instanceof \WP_Term ) {
            foreach ( $settings as $key => $value ) {
                if ( is_string( $key ) && "_include_term_ids" === substr( $key, -17 ) && is_array( $value ) && in_array( (int) $term->term_id, array_map( "intval", $value ), true ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Adds the search box above the archive's first story list. @param object $widget */
    public function print_search_box( $widget ): void {
        if ( $this->box_printed
            || ! self::enabled()
            || "" === self::shortcode()
            || ! $this->is_listing( false, $widget )
            || ( class_exists( ListingSearchRenderer::class ) && ListingSearchRenderer::rendered( "listing" ) )
        ) {
            return;
        }

        $this->box_printed = true;
        echo $this->box_html();
    }

    /**
     * A theme-drawn archive: the box goes above the main loop's first story and the stories are
     * wrapped (display: contents keeps the theme's grid) as the list the box refreshes.
     *
     * @param \WP_Query $query
     */
    public function open_theme_listing( $query ): void {
        if ( $this->box_printed
            || ! $query instanceof \WP_Query
            || ! $query->is_main_query()
            || ! self::enabled()
            || ! self::archive_page()
            || "" === self::shortcode()
            || ( class_exists( ListingSearchRenderer::class ) && ListingSearchRenderer::rendered( "listing" ) )
        ) {
            return;
        }

        $this->box_printed  = true;
        $this->wrapper_open = true;
        echo $this->box_html() . '<div class="hexa-listing-results smpi-archive-stories" style="display:contents">';
    }

    /** @param \WP_Query $query */
    public function close_theme_listing( $query ): void {
        if ( $this->wrapper_open && $query instanceof \WP_Query && $query->is_main_query() ) {
            $this->wrapper_open = false;
            echo "</div>";
        }
    }

    private function box_html(): string {
        return '<div class="' . esc_attr( self::BOX_CLASS ) . '" style="width:100%;flex:0 0 100%;grid-column:1/-1">'
            . do_shortcode( '[' . self::shortcode() . ' results_one="%d story" results_many="%d stories"]' )
            . "</div>";
    }

    /**
     * The archive as the publication lists it: its visibility rules, and on an author page the
     * stories the author co-wrote.
     *
     * @param array<string,mixed> $args
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function context_args( $args, $context ) {
        if ( ! is_array( $args ) || ! is_array( $context ) || RuntimeContext::is_background_request() ) {
            return $args;
        }

        if ( "term" === ( $context["type"] ?? "" ) && in_array( $context["taxonomy"] ?? "", [ "category", "post_tag" ], true ) ) {
            $args[ Visibility::MANAGED_CONTEXT_QUERY_VAR ] = "category_tag";
        }
        if ( "author" === ( $context["type"] ?? "" ) && is_author() ) {
            $args = ( new AuthorQueryIntegration( new AuthorAssignmentRepository() ) )->filter_elementor_query_args( $args + [ Visibility::MANAGED_CONTEXT_QUERY_VAR => "author" ] );
        }

        return $args;
    }

    /** An author filter also finds the stories a person co-wrote. */
    public function co_author_condition( $sql, $user_id, $table, $alias ): string {
        if ( ! AuthorAssignmentRepository::site_enabled() || (int) $user_id <= 0 ) {
            return (string) $sql;
        }

        global $wpdb;
        $alias = preg_replace( "/[^A-Za-z0-9_]/", "", (string) $alias ) . "_co";

        return "(" . $sql . " OR EXISTS (SELECT 1 FROM {$wpdb->term_relationships} {$alias}_tr"
            . " INNER JOIN {$wpdb->term_taxonomy} {$alias}_tt ON {$alias}_tt.term_taxonomy_id = {$alias}_tr.term_taxonomy_id"
            . " INNER JOIN {$wpdb->terms} {$alias}_t ON {$alias}_t.term_id = {$alias}_tt.term_id"
            . " WHERE {$alias}_tr.object_id = " . preg_replace( "/[^A-Za-z0-9_.]/", "", (string) $table ) . ".ID"
            . $wpdb->prepare( " AND {$alias}_tt.taxonomy = %s AND {$alias}_t.slug = %s)", AuthorAssignmentRepository::TAXONOMY, "smpi-user-" . (int) $user_id ) . ")";
    }

    /** The search box shortcode HWS Base Tools offers ([hws_search], or its former name on older copies), or ''. */
    private static function shortcode(): string {
        foreach ( [ "hws_search", "hws_listing_search" ] as $tag ) {
            if ( shortcode_exists( $tag ) ) {
                return $tag;
            }
        }
        return "";
    }

    private static function archive_page(): bool {
        return ! is_admin() && ( is_category() || is_tag() || is_tax() || is_author() || is_search() );
    }
}
