<?php

declare(strict_types=1);

namespace {
    define( "ABSPATH", dirname( __DIR__ ) . "/" );

    $GLOBALS["smpi_query_safety_options"] = [
        "show_on_front" => "page",
        "page_on_front" => 275820,
    ];
    $GLOBALS["smpi_query_safety_request"] = [
        "admin" => false,
        "ajax" => false,
        "cron" => false,
        "rest" => false,
    ];
    $GLOBALS["smpi_query_safety_actions"] = [];
    $GLOBALS["smpi_query_safety_filters"] = [];
    $GLOBALS["smpi_query_safety_current_filter"] = "";
    $GLOBALS["smpi_query_safety_is_singular"] = false;
    $GLOBALS["smpi_query_safety_is_singular_calls"] = 0;

    final class WP_Query {
        private array $vars;
        private array $flags;

        public function __construct( array $vars = [], array $flags = [] ) {
            $this->vars = $vars;
            $this->flags = $flags;
        }

        public function get( string $key, $default = "" ) {
            return $this->vars[ $key ] ?? $default;
        }

        public function set( string $key, $value ): void {
            $this->vars[ $key ] = $value;
        }

        public function is_main_query(): bool { return (bool) ( $this->flags["main"] ?? false ); }
        public function is_page(): bool { return (bool) ( $this->flags["page"] ?? false ); }
        public function is_home(): bool { return (bool) ( $this->flags["home"] ?? false ); }
        public function is_category(): bool { return (bool) ( $this->flags["category"] ?? false ); }
        public function is_tag(): bool { return (bool) ( $this->flags["tag"] ?? false ); }
        public function is_author(): bool { return (bool) ( $this->flags["author"] ?? false ); }
        public function is_search(): bool { return (bool) ( $this->flags["search"] ?? false ); }
        public function is_feed(): bool { return (bool) ( $this->flags["feed"] ?? false ); }
        public function is_post_type_archive(): bool { return (bool) ( $this->flags["post_type_archive"] ?? false ); }
    }

    final class QuerySafetyWpdb {
        public string $posts = "wp_posts";
        public string $postmeta = "wp_postmeta";

        public function prepare( string $sql, ...$args ): string {
            return $sql;
        }
    }

    $GLOBALS["wpdb"] = new QuerySafetyWpdb();

    function is_admin(): bool { return (bool) $GLOBALS["smpi_query_safety_request"]["admin"]; }
    function wp_doing_ajax(): bool { return (bool) $GLOBALS["smpi_query_safety_request"]["ajax"]; }
    function wp_doing_cron(): bool { return (bool) $GLOBALS["smpi_query_safety_request"]["cron"]; }
    function wp_is_serving_rest_request(): bool { return (bool) $GLOBALS["smpi_query_safety_request"]["rest"]; }
    function is_singular( $post_types = "" ): bool {
        $GLOBALS["smpi_query_safety_is_singular_calls"]++;
        return "post" === $post_types && (bool) $GLOBALS["smpi_query_safety_is_singular"];
    }
    function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $GLOBALS["smpi_query_safety_actions"][ $hook ][] = [ $callback, $priority, $accepted_args ];
    }
    function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $GLOBALS["smpi_query_safety_filters"][ $hook ][] = [ $callback, $priority, $accepted_args ];
    }
    function current_filter(): string { return (string) $GLOBALS["smpi_query_safety_current_filter"]; }
    function get_option( string $key, $default = false ) { return $GLOBALS["smpi_query_safety_options"][ $key ] ?? $default; }
    function sanitize_key( string $value ): string { return strtolower( preg_replace( "/[^a-z0-9_-]/i", "", $value ) ); }
    function post_type_exists( string $post_type ): bool { return in_array( $post_type, [ "post", "page", "press-release" ], true ); }
}

namespace Elementor {
    class Widget_Base {
        private string $name;

        public function __construct( string $name ) {
            $this->name = $name;
        }

        public function get_name(): string {
            return $this->name;
        }
    }
}

namespace smp_publication_integration\Support {
    final class Settings {
        public static array $values = [
            "shadow_posts_enabled" => true,
            "shadow_press_releases" => false,
            "press_release_include_enabled" => false,
            "press_release_include_contexts" => [],
            "hide_home_posts_without_featured_image" => true,
        ];
        public static int $reads = 0;

        public static function bool( string $key ): bool {
            self::$reads++;
            return (bool) ( self::$values[ $key ] ?? false );
        }

        public static function array( string $key ): array {
            self::$reads++;
            $value = self::$values[ $key ] ?? [];
            return is_array( $value ) ? $value : [];
        }
    }

    final class Dependencies {
        public static function hpr_active(): bool { return true; }
    }
}

namespace {
    $core_root = dirname( __DIR__ ) . "/lib/hexa-wordpress-plugin-core";

    if ( ! is_file( $core_root . "/src/QuerySafety/QueryEligibility.php" ) ) {
        fwrite( STDERR, "FAIL: Bundled Hexa WP Core QuerySafety classes are unavailable.\n" );
        exit( 1 );
    }

    require_once $core_root . "/src/CoreContracts/ModuleInterface.php";
    require_once $core_root . "/src/QuerySafety/QueryEligibility.php";
    require_once $core_root . "/src/QuerySafety/StaticFrontPageQueryGuard.php";
    require_once dirname( __DIR__ ) . "/src/Support/RuntimeContext.php";
    require_once dirname( __DIR__ ) . "/src/Content/Visibility.php";
    require_once dirname( __DIR__ ) . "/src/Content/FeaturedImageRequirements.php";

    use smp_publication_integration\Content\FeaturedImageRequirements;
    use smp_publication_integration\Content\Visibility;
    use smp_publication_integration\Support\Settings;

    function expect_query_safety( bool $condition, string $message ): void {
        if ( ! $condition ) {
            fwrite( STDERR, "FAIL: {$message}\n" );
            exit( 1 );
        }
    }

    $visibility = new Visibility();
    $featured = new FeaturedImageRequirements();

    $visibility->register();
    $single_recent_hook = "elementor/query/" . Visibility::ELEMENTOR_SINGLE_RECENT_QUERY_ID;
    $single_recent_registration = $GLOBALS["smpi_query_safety_actions"][ $single_recent_hook ][0] ?? [];
    expect_query_safety( is_callable( $single_recent_registration[0] ?? null ), "Visibility must register the exact smpi_single_recent Elementor Query ID adapter." );
    expect_query_safety( 2 === ( $single_recent_registration[2] ?? 0 ), "The exact Elementor adapter must receive both the query and its widget." );
    expect_query_safety( ! isset( $GLOBALS["smpi_query_safety_filters"]["elementor/query/query_args"] ), "Visibility must not attach a broad Elementor query-args filter." );
    expect_query_safety( ! isset( $GLOBALS["smpi_query_safety_filters"]["elementor/query/fallback_query_args"] ), "Visibility must not attach a broad Elementor fallback filter." );

    $GLOBALS["smpi_query_safety_is_singular"] = true;
    $GLOBALS["smpi_query_safety_current_filter"] = $single_recent_hook;
    $posts_widget = new \Elementor\Widget_Base( "posts" );
    $loop_grid_widget = new \Elementor\Widget_Base( "loop-grid" );
    $single_recent_callback = $single_recent_registration[0];

    $posts_query = new WP_Query( [ "post_type" => "post" ] );
    $single_recent_callback( $posts_query, $posts_widget );
    expect_query_safety( "single_recent" === $posts_query->get( Visibility::MANAGED_CONTEXT_QUERY_VAR ), "The exact Elementor Posts query must receive the explicit recent-post scope." );
    Settings::$values["press_release_include_enabled"] = true;
    Settings::$values["press_release_include_contexts"] = [ "single_recent" ];
    Settings::$reads = 0;
    $visibility->filter_queries( $posts_query );
    expect_query_safety( Settings::$reads > 0, "The exact Elementor adapter must produce a Core-eligible secondary query." );
    expect_query_safety( in_array( "press-release", $posts_query->get( "post_type" ), true ), "The marked single-recent query must retain its configured publication behavior." );
    expect_query_safety( str_contains( $visibility->filter_press_release_where( "BASE", $posts_query ), "smpi_pr_hide" ), "The marked single-recent query must activate only its paired SQL marker." );
    Settings::$values["press_release_include_enabled"] = false;
    Settings::$values["press_release_include_contexts"] = [];

    $loop_grid_query = new WP_Query( [ "post_type" => "post" ] );
    $single_recent_callback( $loop_grid_query, $loop_grid_widget );
    expect_query_safety( "single_recent" === $loop_grid_query->get( Visibility::MANAGED_CONTEXT_QUERY_VAR ), "The exact Elementor Loop Grid query must receive the explicit recent-post scope." );

    $unrelated_widget_query = new WP_Query( [ "post_type" => "post" ] );
    $single_recent_callback( $unrelated_widget_query, new \Elementor\Widget_Base( "portfolio" ) );
    expect_query_safety( "" === $unrelated_widget_query->get( Visibility::MANAGED_CONTEXT_QUERY_VAR ), "Unrelated Elementor widgets must not receive SMP query scope." );
    $fake_widget = new class() {
        public function get_name(): string { return "posts"; }
    };
    $fake_widget_query = new WP_Query( [ "post_type" => "post" ] );
    $single_recent_callback( $fake_widget_query, $fake_widget );
    expect_query_safety( "" === $fake_widget_query->get( Visibility::MANAGED_CONTEXT_QUERY_VAR ), "Objects that merely expose get_name() must not impersonate an Elementor widget." );

    $wrong_hook_query = new WP_Query( [ "post_type" => "post" ] );
    $GLOBALS["smpi_query_safety_current_filter"] = "elementor/query/unrelated";
    $visibility->mark_elementor_single_recent_query( $wrong_hook_query, $posts_widget );
    expect_query_safety( "" === $wrong_hook_query->get( Visibility::MANAGED_CONTEXT_QUERY_VAR ), "An unrelated Elementor Query ID must not receive the single-recent scope." );
    $GLOBALS["smpi_query_safety_current_filter"] = $single_recent_hook;

    $existing_context = new WP_Query( [ "post_type" => "post", Visibility::MANAGED_CONTEXT_QUERY_VAR => "author" ] );
    $single_recent_callback( $existing_context, $posts_widget );
    expect_query_safety( "author" === $existing_context->get( Visibility::MANAGED_CONTEXT_QUERY_VAR ), "Elementor marking must preserve an existing explicit SMP context." );
    $suppressed_elementor = new WP_Query( [ "post_type" => "post", "suppress_filters" => true ] );
    $single_recent_callback( $suppressed_elementor, $posts_widget );
    expect_query_safety( "" === $suppressed_elementor->get( Visibility::MANAGED_CONTEXT_QUERY_VAR ), "Elementor marking must preserve suppress_filters=true." );
    $non_post_query = new WP_Query( [ "post_type" => "page" ] );
    $single_recent_callback( $non_post_query, $posts_widget );
    expect_query_safety( "" === $non_post_query->get( Visibility::MANAGED_CONTEXT_QUERY_VAR ), "The exact Query ID must not scope a loop that cannot include posts." );
    $main_elementor_query = new WP_Query( [ "post_type" => "post" ], [ "main" => true ] );
    $single_recent_callback( $main_elementor_query, $posts_widget );
    expect_query_safety( "" === $main_elementor_query->get( Visibility::MANAGED_CONTEXT_QUERY_VAR ), "The secondary-loop adapter must never mark a main query." );

    $GLOBALS["smpi_query_safety_is_singular"] = false;
    $non_single_query = new WP_Query( [ "post_type" => "post" ] );
    $single_recent_callback( $non_single_query, $posts_widget );
    expect_query_safety( "" === $non_single_query->get( Visibility::MANAGED_CONTEXT_QUERY_VAR ), "Elementor loops outside singular posts must remain unmarked." );

    foreach ( [ "admin", "ajax", "cron", "rest" ] as $background_request ) {
        $GLOBALS["smpi_query_safety_request"][ $background_request ] = true;
        $GLOBALS["smpi_query_safety_is_singular"] = true;
        $GLOBALS["smpi_query_safety_is_singular_calls"] = 0;
        $background_query = new WP_Query( [ "post_type" => "post" ] );
        $single_recent_callback( $background_query, $posts_widget );
        expect_query_safety( "" === $background_query->get( Visibility::MANAGED_CONTEXT_QUERY_VAR ), "Elementor query marking must reject {$background_request} requests." );
        expect_query_safety( 0 === $GLOBALS["smpi_query_safety_is_singular_calls"], "The {$background_request} guard must run before conditional-tag evaluation." );
        $GLOBALS["smpi_query_safety_request"][ $background_request ] = false;
    }

    Settings::$reads = 0;
    $static_front = new WP_Query(
        [ "page_id" => 275820, "post_type" => "page" ],
        [ "main" => true, "page" => true, "home" => true ]
    );
    $visibility->filter_queries( $static_front );
    $featured->filter_home_queries( $static_front );
    expect_query_safety( 0 === Settings::$reads, "Static front-page guards must run before any SMP settings reads." );
    expect_query_safety( "page" === $static_front->get( "post_type" ), "The configured front page must remain a page query." );
    expect_query_safety( ! $static_front->get( "smpi_shadow_posts_home" ) && ! $static_front->get( "smpi_require_post_thumbnail_for_posts" ), "Static front pages must not receive post-loop SQL markers." );
    $static_front->set( "smpi_shadow_posts_home", true );
    $static_front->set( "smpi_require_post_thumbnail_for_posts", true );
    expect_query_safety( "BASE" === $visibility->filter_press_release_where( "BASE", $static_front ), "Visibility SQL must defensively reject a static front-page query even if a marker was injected." );
    expect_query_safety( "BASE" === $featured->filter_thumbnail_where( "BASE", $static_front ), "Featured-image SQL must defensively reject a static front-page query even if a marker was injected." );

    Settings::$reads = 0;
    $suppressed = new WP_Query(
        [ "post_type" => "post", "suppress_filters" => true, Visibility::MANAGED_CONTEXT_QUERY_VAR => "home" ],
        [ "main" => true, "home" => true ]
    );
    $visibility->filter_queries( $suppressed );
    $featured->filter_home_queries( $suppressed );
    $suppressed->set( "smpi_shadow_posts_home", true );
    $suppressed->set( "smpi_require_post_thumbnail_for_posts", true );
    expect_query_safety( 0 === Settings::$reads, "Suppressed queries must be rejected before any SMP settings reads." );
    expect_query_safety( "BASE" === $visibility->filter_press_release_where( "BASE", $suppressed ), "Suppressed queries must never receive visibility SQL." );
    expect_query_safety( "BASE" === $featured->filter_thumbnail_where( "BASE", $suppressed ), "Suppressed queries must never receive featured-image SQL." );

    Settings::$reads = 0;
    $unmarked_secondary = new WP_Query( [ "post_type" => "post" ] );
    $visibility->filter_queries( $unmarked_secondary );
    $featured->filter_home_queries( $unmarked_secondary );
    expect_query_safety( 0 === Settings::$reads, "Unmarked secondary queries must exit before settings are loaded." );
    expect_query_safety( ! $unmarked_secondary->get( "smpi_shadow_posts_home" ) && ! $unmarked_secondary->get( "smpi_require_post_thumbnail_for_posts" ), "Unmarked secondary queries must remain unchanged." );

    Settings::$reads = 0;
    $invalid_secondary = new WP_Query( [ "post_type" => "post", Visibility::MANAGED_CONTEXT_QUERY_VAR => "front_page" ] );
    $visibility->filter_queries( $invalid_secondary );
    $featured->filter_home_queries( $invalid_secondary );
    expect_query_safety( 0 === Settings::$reads, "Unknown secondary-query contexts must be rejected before settings are loaded." );

    Settings::$reads = 0;
    $marked_secondary = new WP_Query( [ "post_type" => "post", Visibility::MANAGED_CONTEXT_QUERY_VAR => "home" ] );
    $visibility->filter_queries( $marked_secondary );
    $featured->filter_home_queries( $marked_secondary );
    expect_query_safety( Settings::$reads > 0, "An explicitly marked secondary home loop must evaluate SMP settings." );
    expect_query_safety( true === $marked_secondary->get( "smpi_shadow_posts_home" ), "The explicit home loop must receive the visibility marker." );
    expect_query_safety( true === $marked_secondary->get( "smpi_require_post_thumbnail_for_posts" ), "The explicit home loop must receive the featured-image marker." );
    expect_query_safety( str_contains( $visibility->filter_press_release_where( "BASE", $marked_secondary ), "smpi_shadow_home" ), "Visibility SQL must run only after the explicit marker is present." );
    expect_query_safety( str_contains( $featured->filter_thumbnail_where( "BASE", $marked_secondary ), "smpi_thumb" ), "Featured-image SQL must run only after the explicit marker is present." );

    $scope_without_sql_marker = new WP_Query( [ "post_type" => "post", Visibility::MANAGED_CONTEXT_QUERY_VAR => "home" ] );
    expect_query_safety( "BASE" === $visibility->filter_press_release_where( "BASE", $scope_without_sql_marker ), "An explicit context alone must not activate visibility SQL." );
    expect_query_safety( "BASE" === $featured->filter_thumbnail_where( "BASE", $scope_without_sql_marker ), "An explicit context alone must not activate featured-image SQL." );

    Settings::$reads = 0;
    $main_home = new WP_Query( [ "post_type" => "post" ], [ "main" => true, "home" => true ] );
    $visibility->filter_queries( $main_home );
    $featured->filter_home_queries( $main_home );
    expect_query_safety( true === $main_home->get( "smpi_shadow_posts_home" ) && true === $main_home->get( "smpi_require_post_thumbnail_for_posts" ), "The legitimate main posts page must retain both SMP filters." );

    echo "PASS: SMP query callbacks guard static, suppressed, and secondary queries with explicit SQL scope.\n";
}
