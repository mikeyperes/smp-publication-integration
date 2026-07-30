<?php
namespace {
    define( "ABSPATH", __DIR__ );

    final class WP_User {
        public int $ID;
        public string $display_name;
        public string $user_nicename;
        public string $user_email;
        public array $roles;

        public function __construct( int $id, string $name, string $slug, array $roles ) {
            $this->ID = $id;
            $this->display_name = $name;
            $this->user_nicename = $slug;
            $this->user_email = $slug . "@example.test";
            $this->roles = $roles;
        }
    }

    final class WP_Query {
        public array $posts = [];
        public int $found_posts = 0;

        public function __construct( array $args = [] ) {
            $author_id = (int) ( $args["author"] ?? 0 );
            $allowed_types = array_map( "strval", (array) ( $args["post_type"] ?? [ "post" ] ) );
            foreach ( $GLOBALS["test_posts"] as $post_id => $post ) {
                if ( $author_id === (int) $post["author"] && in_array( $post["type"], $allowed_types, true ) && in_array( $post["status"], (array) $args["post_status"], true ) ) {
                    $this->posts[] = $post_id;
                }
            }
            $this->found_posts = count( $this->posts );
        }
    }

    $GLOBALS["test_users"] = [
        1 => new WP_User( 1, "Alice Editor", "alice-editor", [ "author" ] ),
        2 => new WP_User( 2, "Blake Contributor", "blake-contributor", [ "contributor" ] ),
        3 => new WP_User( 3, "Casey Contributor", "casey-contributor", [ "contributor" ] ),
        4 => new WP_User( 4, "Drew Gravatar", "drew-gravatar", [ "contributor" ] ),
    ];
    $GLOBALS["test_user_meta"] = [
        1 => [
            "staff_writer" => "1",
            "simple_local_avatar" => [ "full" => "https://example.test/uploads/alice.jpg", "media_id" => 101 ],
        ],
        2 => [ "staff_writer" => "1" ],
        3 => [ "staff_writer" => "1", "wp_user_avatars" => 303 ],
        4 => [ "staff_writer" => "1" ],
    ];
    $GLOBALS["test_posts"] = [
        11 => [ "author" => 1, "type" => "post", "status" => "publish" ],
        22 => [ "author" => 2, "type" => "post", "status" => "publish" ],
        33 => [ "author" => 3, "type" => "press-release", "status" => "publish" ],
    ];
    $GLOBALS["test_settings"] = [
        "author_listing_hide_without_articles" => false,
        "author_listing_hide_without_featured_image" => true,
    ];
    $GLOBALS["test_found_avatars"] = [ 2 => false, 4 => true ];
    $GLOBALS["test_shortcodes"] = [];
    $GLOBALS["test_actions"] = [];

    function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void { $GLOBALS["test_actions"][ $hook ][ $priority ][] = $callback; }
    function add_shortcode( string $tag, $callback ): void { $GLOBALS["test_shortcodes"][ $tag ] = $callback; }
    function remove_shortcode( string $tag ): void { unset( $GLOBALS["test_shortcodes"][ $tag ] ); }
    function apply_filters( string $hook, $value, ...$args ) { return $value; }
    function get_users( array $args ): array {
        $roles = (array) ( $args["role__in"] ?? [] );
        $users = array_values( array_filter( $GLOBALS["test_users"], static fn( WP_User $user ): bool => ! empty( array_intersect( $roles, $user->roles ) ) ) );
        usort( $users, static fn( WP_User $left, WP_User $right ): int => strcmp( $left->display_name, $right->display_name ) );
        return $users;
    }
    function get_user_by( string $field, $value ) { return "id" === $field ? ( $GLOBALS["test_users"][(int) $value] ?? false ) : false; }
    function get_user_meta( int $user_id, string $key, bool $single = false ) { return $GLOBALS["test_user_meta"][ $user_id ][ $key ] ?? ""; }
    function get_the_author_meta( string $key, int $user_id ) { return get_user_meta( $user_id, $key, true ); }
    function get_field( string $key, string $scope ) {
        $user_id = (int) str_replace( "user_", "", $scope );
        return $GLOBALS["test_user_meta"][ $user_id ][ $key ] ?? null;
    }
    function get_author_posts_url( int $user_id ): string { return "https://example.test/author/" . $GLOBALS["test_users"][ $user_id ]->user_nicename . "/"; }
    function get_avatar_url( int $user_id, array $args = [] ): string { return "https://gravatar.test/avatar-" . $user_id . ".jpg"; }
    function get_avatar_data( int $user_id, array $args = [] ): array {
        return [
            "url" => get_avatar_url( $user_id, $args ),
            "found_avatar" => (bool) ( $GLOBALS["test_found_avatars"][ $user_id ] ?? false ),
        ];
    }
    function wp_get_attachment_image_url( int $attachment_id, string $size ) { return "https://example.test/uploads/attachment-" . $attachment_id . "-" . $size . ".jpg"; }
    function get_bloginfo( string $show ): string { return "Example Daily"; }
    function get_terms( array $args ): array { return []; }
    function post_type_exists( string $post_type ): bool { return in_array( $post_type, [ "post", "press-release" ], true ); }
    function sanitize_key( string $value ): string { return strtolower( preg_replace( "/[^a-z0-9_-]/i", "", $value ) ); }
    function absint( $value ): int { return abs( (int) $value ); }
    function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); }
    function esc_html( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, "UTF-8" ); }
    function esc_attr( string $value ): string { return esc_html( $value ); }
    function esc_url( string $value ): string { return $value; }
    function is_wp_error( $value ): bool { return false; }
}

namespace smp_publication_integration\Support {
    final class Settings {
        public static function bool( string $key ): bool { return (bool) ( $GLOBALS["test_settings"][ $key ] ?? false ); }
    }
}

namespace {
    require_once dirname( __DIR__ ) . "/src/Support/Autoloader.php";
    \smp_publication_integration\Support\Autoloader::register( dirname( __DIR__ ) . "/src" );

    use smp_publication_integration\Authorship\AuthorFieldResolver;
    use smp_publication_integration\Content\AuthorListings;

    function expect_true( bool $condition, string $message ): void {
        if ( ! $condition ) {
            fwrite( STDERR, "FAIL: {$message}\n" );
            exit( 1 );
        }
    }

    $resolver = new AuthorFieldResolver();
    expect_true( $resolver->has_explicit_image( 1 ), "Simple Local Avatars metadata is recognized as an assigned image." );
    expect_true( ! $resolver->has_explicit_image( 2 ), "A Gravatar fallback is not treated as an assigned image." );
    expect_true( $resolver->has_explicit_image( 3 ), "Legacy WP User Avatars attachment metadata is recognized." );
    expect_true( $resolver->has_profile_image( 4 ), "A real found Gravatar counts as a profile image." );
    expect_true( ! $resolver->has_explicit_image( 4 ), "A Gravatar is not treated as an explicitly assigned listing image." );
    expect_true( ! $resolver->has_profile_image( 2 ), "A default avatar does not count as a profile image." );

    $listings = new AuthorListings();
    $listings->register();
    expect_true( isset( $GLOBALS["test_actions"]["init"][PHP_INT_MAX] ), "SMP replaces site shortcode registrations at the final init priority." );
    $listings->register_shortcodes();
    expect_true( isset( $GLOBALS["test_shortcodes"]["staff_grid"], $GLOBALS["test_shortcodes"]["contributors_grid"] ), "Both author-listing shortcodes are owned by SMP." );

    $staff = $listings->render_staff_grid();
    expect_true( 2 === substr_count( $staff, '<a class="user-card smpi-author-listing-card"' ), "Every visible staff card is one whole-card anchor." );
    expect_true( ! str_contains( $staff, "View Member" ), "Staff cards do not contain View Member text." );
    expect_true( ! str_contains( $staff, "Blake Contributor" ), "The image filter removes a member who only has a fallback avatar." );
    expect_true( ! str_contains( $staff, "Drew Gravatar" ), "The featured-image filter excludes Gravatar-only members." );
    expect_true( str_contains( $staff, "Staff Writer at Example Daily" ), "Staff labels use the current publication name." );

    $contributors = $listings->render_contributors_grid();
    expect_true( str_contains( $contributors, "Casey Contributor" ) && ! str_contains( $contributors, "Drew Gravatar" ) && ! str_contains( $contributors, "Blake Contributor" ), "The same assigned-image rule applies to the contributor shortcode." );
    expect_true( str_contains( $contributors, "Contributor at Example Daily" ), "Contributor labels use the current publication name." );

    $GLOBALS["test_settings"]["author_listing_hide_without_articles"] = true;
    $staff_with_articles = $listings->render_staff_grid();
    expect_true( str_contains( $staff_with_articles, "Alice Editor" ) && ! str_contains( $staff_with_articles, "Casey Contributor" ), "The article filter ignores press releases and keeps members with published articles." );

    $GLOBALS["test_settings"]["author_listing_hide_without_articles"] = false;
    $GLOBALS["test_settings"]["author_listing_hide_without_featured_image"] = false;
    $staff_with_fallbacks = $listings->render_staff_grid();
    expect_true( 4 === substr_count( $staff_with_fallbacks, 'data-smpi-user-id=' ), "Disabling both filters restores every staff member." );
    expect_true( str_contains( $staff_with_fallbacks, "https://gravatar.test/avatar-2.jpg" ), "Fallback avatars remain available when the explicit-image filter is disabled." );

    ob_start();
    $listings->print_styles();
    $styles = (string) ob_get_clean();
    expect_true( str_contains( $styles, ":focus-visible" ) && str_contains( $styles, "height:100%" ), "Whole-card links include scoped keyboard focus and stable card sizing." );

    $root = dirname( __DIR__ );
    $dashboard = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );
    $ajax = (string) file_get_contents( $root . "/src/Admin/Ajax/AjaxController.php" );
    $bootstrap = (string) file_get_contents( $root . "/src/Bootstrap/Plugin.php" );
    $author_query = (string) file_get_contents( $root . "/src/Authorship/AuthorQueryIntegration.php" );
    foreach ( [ "Hide members with no articles", "Hide members without a featured image", "Show press releases attached to the author" ] as $label ) {
        expect_true( str_contains( $dashboard, $label ), "The Authors tab contains the {$label} control." );
    }
    foreach ( [ "author_listing_hide_without_articles", "author_listing_hide_without_featured_image", "author_listing_show_press_releases" ] as $setting_key ) {
        expect_true( str_contains( $ajax, $setting_key ), "AJAX saving accepts {$setting_key}." );
    }
    expect_true( str_contains( $bootstrap, "new Content\\AuthorListings()" ), "The author listing module is bootstrapped by SMP." );
    expect_true(
        str_contains( $author_query, 'elementor_pro/query_control/get_query_args/current_query' )
        && str_contains( $author_query, 'elementor/query/fallback_query_args' )
        && str_contains( $author_query, 'PHP_INT_MAX - 5' ),
        "SMP author queries run after Hexa PR Wire across every Elementor query path."
    );

    echo "PASS: author listing cards, filters, image detection, and shortcodes\n";
}
