<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Authorship\AuthorAssignmentRepository;
use smp_publication_integration\Authorship\AuthorQueryIntegration;
use smp_publication_integration\Support\RuntimeContext;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Archive listings for Elementor templates (category, tag, taxonomy, author and search):
 * a lead story and the rest of the archive as two native Loop Grid / Posts widgets.
 *
 * Elementor's "Current Query" source keeps the main query's paging, so a template cannot
 * show the newest story apart from the list. Two Query IDs fix that while Elementor keeps
 * rendering, Load more and pagination:
 * - smpi_archive_lead: the current archive's newest story (one post).
 * - smpi_archive_rest: the same archive from the second story on, in the widget's own
 *   batch size, so every Load more batch fills whole rows.
 * [smp_archive_count] prints how many stories the current archive holds.
 */
final class ArchiveListing {
    public const LEAD_QUERY_ID = "smpi_archive_lead";
    public const REST_QUERY_ID = "smpi_archive_rest";
    public const COUNT_SHORTCODE = "smp_archive_count";
    /** Templates' infinite-scroll class; HWS Base Tools serves it (its own class is hws-auto-load-more). */
    public const AUTO_LOAD_CLASS = "smpi-auto-load-more";

    private const OFFSET_BASE_VAR = "smpi_archive_offset_base";
    // The smallest batch a listing uses (one row of four): pages past the main query's last
    // page stay reachable for the rest listing instead of answering 404.
    private const MIN_BATCH = 4;

    public function register(): void {
        add_filter( "elementor/query/query_args", [ $this, "filter_query_args" ], 10, 2 );
        add_filter( "found_posts", [ $this, "filter_found_posts" ], 10, 2 );
        add_filter( "pre_handle_404", [ $this, "keep_later_batches_reachable" ], 10, 2 );
        add_action( "init", [ $this, "register_shortcode" ] );
    }

    public function register_shortcode(): void {
        add_shortcode( self::COUNT_SHORTCODE, [ $this, "render_count_shortcode" ] );
    }

    /**
     * Point a widget whose Query ID is one of ours at the current archive. Runs before the
     * multiple-authors integration, which then widens an author listing to co-authored posts.
     */
    public function filter_query_args( $query_args, $widget = null ) {
        if ( ! is_array( $query_args ) || RuntimeContext::is_background_request() ) {
            return $query_args;
        }

        $query_id = self::widget_query_id( $widget );
        if ( self::LEAD_QUERY_ID !== $query_id && self::REST_QUERY_ID !== $query_id ) {
            return $query_args;
        }

        $archive = self::archive_args();
        if ( null === $archive ) {
            // Editor previews and pages that are not archives keep the widget's own query.
            return $query_args;
        }

        foreach ( [ "cat", "category_name", "category__in", "tag", "tag_id", "tag__in", "tax_query", "author", "author_name", "author__in", "s", "post__in", "post__not_in", "offset" ] as $key ) {
            unset( $query_args[ $key ] );
        }
        $query_args = array_merge( $query_args, $archive );
        $query_args["post_status"] = "publish";
        $query_args["ignore_sticky_posts"] = true;
        $query_args["orderby"] = "date";
        $query_args["order"] = "DESC";

        if ( self::LEAD_QUERY_ID === $query_id ) {
            $query_args["posts_per_page"] = 1;
            $query_args["paged"] = 1;
            // Elementor reads a zero result count as "nothing found", so the count stays on.
            return $query_args;
        }

        $per_page = max( 1, (int) ( $query_args["posts_per_page"] ?? get_option( "posts_per_page" ) ) );
        $paged = max( 1, (int) ( $query_args["paged"] ?? 1 ) );
        $query_args["posts_per_page"] = $per_page;
        $query_args["offset"] = 1 + ( $paged - 1 ) * $per_page;
        $query_args[ self::OFFSET_BASE_VAR ] = 1;
        return $query_args;
    }

    /** The rest listing skips the lead story, so its total and page count leave it out too. */
    public function filter_found_posts( $found_posts, $query ) {
        if ( ! $query instanceof \WP_Query || 1 !== (int) $query->get( self::OFFSET_BASE_VAR ) ) {
            return $found_posts;
        }
        return max( 0, (int) $found_posts - 1 );
    }

    /**
     * A rest listing in batches smaller than the main query's page reaches page numbers the
     * main query does not have; those pages still hold archive stories, so they are not a 404.
     */
    public function keep_later_batches_reachable( $preempt, $wp_query ) {
        if ( $preempt
            || ! $wp_query instanceof \WP_Query
            || ! $wp_query->is_main_query()
            || is_admin()
            || $wp_query->is_feed()
            || ! empty( $wp_query->posts )
            || ! ( $wp_query->is_category() || $wp_query->is_tag() || $wp_query->is_tax() || $wp_query->is_author() || $wp_query->is_search() )
        ) {
            return $preempt;
        }

        $paged = (int) $wp_query->get( "paged" );
        if ( $paged < 2 ) {
            return $preempt;
        }

        $total = self::archive_total();
        return $total > 1 + ( $paged - 1 ) * self::MIN_BATCH ? true : $preempt;
    }

    public function render_count_shortcode( $atts = [] ): string {
        $atts = shortcode_atts(
            [
                "singular" => "story",
                "plural" => "stories",
            ],
            is_array( $atts ) ? $atts : [],
            self::COUNT_SHORTCODE
        );

        $total = self::archive_total();
        if ( $total <= 0 ) {
            return "";
        }

        $word = 1 === $total ? (string) $atts["singular"] : (string) $atts["plural"];
        return '<span class="smpi-archive-count">' . esc_html( trim( number_format_i18n( $total ) . " " . $word ) ) . "</span>";
    }

    /** How many stories the current archive lists, counted the way the listing queries them. */
    public static function archive_total(): int {
        $archive = self::archive_args();
        if ( null === $archive ) {
            return 0;
        }

        $args = array_merge(
            $archive,
            [
                "post_status" => "publish",
                "posts_per_page" => 1,
                "fields" => "ids",
                "ignore_sticky_posts" => true,
            ]
        );
        if ( "author" === ( $args[ Visibility::MANAGED_CONTEXT_QUERY_VAR ] ?? "" ) ) {
            // Count co-authored stories the same way the author listing shows them.
            $args = ( new AuthorQueryIntegration( new AuthorAssignmentRepository() ) )->filter_elementor_query_args( $args );
        }
        $query = new \WP_Query( $args );
        return (int) $query->found_posts;
    }

    /** The current archive as query arguments, or null when the request is not an archive. */
    private static function archive_args(): ?array {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return null;
        }

        if ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( ! $term instanceof \WP_Term ) {
                return null;
            }
            $args = [ "post_type" => "post", Visibility::MANAGED_CONTEXT_QUERY_VAR => "category_tag" ];
            if ( "category" === $term->taxonomy ) {
                $args["cat"] = (int) $term->term_id;
            } elseif ( "post_tag" === $term->taxonomy ) {
                $args["tag_id"] = (int) $term->term_id;
            } else {
                $args["post_type"] = "any";
                $args["tax_query"] = [ [ "taxonomy" => $term->taxonomy, "field" => "term_id", "terms" => (int) $term->term_id ] ];
            }
            return $args;
        }

        if ( is_author() ) {
            $user = get_queried_object();
            if ( ! $user instanceof \WP_User ) {
                return null;
            }
            // The multiple-authors integration reads this context and adds co-authored posts.
            return [ "post_type" => "post", "author" => (int) $user->ID, Visibility::MANAGED_CONTEXT_QUERY_VAR => "author" ];
        }

        if ( is_search() ) {
            $term = get_search_query( false );
            return "" === $term ? null : [ "post_type" => "post", "s" => $term ];
        }

        return null;
    }

    /** The Query ID typed into a Loop Grid or Posts widget, whatever its control prefix. */
    private static function widget_query_id( $widget ): string {
        if ( ! is_object( $widget ) || ! method_exists( $widget, "get_settings_for_display" ) ) {
            return "";
        }
        $settings = $widget->get_settings_for_display();
        if ( ! is_array( $settings ) ) {
            return "";
        }
        foreach ( $settings as $key => $value ) {
            if ( is_string( $key ) && is_string( $value ) && "query_id" === substr( $key, -8 ) && "" !== $value ) {
                return sanitize_key( $value );
            }
        }
        return "";
    }
}
