<?php
namespace {
    define( "ABSPATH", __DIR__ );

    final class WP_User {
        public int $ID;
        public string $display_name;
        public string $user_nicename;
        public string $user_email;
        public function __construct( int $id, string $name, string $slug, string $email ) {
            $this->ID = $id;
            $this->display_name = $name;
            $this->user_nicename = $slug;
            $this->user_email = $email;
        }
    }

    final class WP_Post {
        public int $ID;
        public int $post_author;
        public string $post_type;
        public string $post_status = "publish";
        public function __construct( int $id, int $author, string $type = "post" ) {
            $this->ID = $id;
            $this->post_author = $author;
            $this->post_type = $type;
        }
    }

    final class WP_Query {
        public array $posts = [];
        public int $found_posts = 0;
        public function __construct( array $args = [] ) {
            $this->posts = [];
        }
    }

    final class TestWpdb {
        public string $posts = "wp_posts";
        public string $term_relationships = "wp_term_relationships";
        public string $term_taxonomy = "wp_term_taxonomy";
        public string $termmeta = "wp_termmeta";
        public function update( string $table, array $data, array $where, array $formats = [], array $where_formats = [] ): int {
            $post_id = (int) ( $where["ID"] ?? 0 );
            if ( isset( $GLOBALS["test_posts"][ $post_id ] ) && isset( $data["post_author"] ) ) {
                $GLOBALS["test_posts"][ $post_id ]->post_author = (int) $data["post_author"];
            }
            return 1;
        }
        public function prepare( string $sql, ...$args ): string {
            return $sql;
        }
    }

    $GLOBALS["wpdb"] = new TestWpdb();
    $GLOBALS["test_users"] = [
        1 => new WP_User( 1, "Alpha Author", "alpha-author", "alpha@example.com" ),
        2 => new WP_User( 2, "Beta Author", "beta-author", "beta@example.com" ),
    ];
    $GLOBALS["test_posts"] = [ 10 => new WP_Post( 10, 1 ) ];
    $GLOBALS["test_meta"] = [ 10 => [ "smpi_post_authors" => [ 1, 2 ] ] ];
    $GLOBALS["test_user_meta"] = [
        1 => [
            "title" => "Editorial Lead",
            "muckrack_url" => "https://muckrack.com/alpha-author",
        ],
        2 => [
            "author_title" => "Contributor",
        ],
    ];
    $GLOBALS["test_terms"] = [];
    $GLOBALS["test_term_meta"] = [];
    $GLOBALS["test_relationships"] = [];
    $GLOBALS["test_cache"] = [];
    $GLOBALS["post"] = $GLOBALS["test_posts"][10];
    $GLOBALS["test_is_singular"] = true;

    function apply_filters( string $hook, $value ) { return $value; }
    function do_action( string $hook, ...$args ): void {}
    function add_action( string $hook, $callback, int $priority = 10, int $args = 1 ): void {}
    function add_filter( string $hook, $callback, int $priority = 10, int $args = 1 ): void {}
    function add_shortcode( string $tag, $callback ): void {}
    function register_taxonomy( string $taxonomy, array $types, array $args ): void {}
    function register_rest_field( string $type, string $field, array $args ): void {}
    function post_type_exists( string $type ): bool { return true; }
    function get_user_by( string $field, $value ) {
        if ( "id" === $field ) {
            return $GLOBALS["test_users"][(int) $value] ?? false;
        }
        foreach ( $GLOBALS["test_users"] as $user ) {
            if ( "slug" === $field && $user->user_nicename === $value ) {
                return $user;
            }
        }
        return false;
    }
    function get_post( $post_id = null ) {
        if ( $post_id instanceof WP_Post ) {
            return $post_id;
        }
        if ( null === $post_id || 0 === (int) $post_id ) {
            return $GLOBALS["post"] ?? null;
        }
        return $GLOBALS["test_posts"][(int) $post_id] ?? null;
    }
    function get_post_meta( int $post_id, string $key, bool $single = false ) {
        return $GLOBALS["test_meta"][ $post_id ][ $key ] ?? ( $single ? "" : [] );
    }
    function update_post_meta( int $post_id, string $key, $value ): bool {
        $GLOBALS["test_meta"][ $post_id ][ $key ] = $value;
        return true;
    }
    function get_user_meta( int $user_id, string $key, bool $single = false ) {
        return $GLOBALS["test_user_meta"][ $user_id ][ $key ] ?? "";
    }
    function get_the_author_meta( string $key, int $user_id ) {
        $user = get_user_by( "id", $user_id );
        if ( ! $user ) {
            return "";
        }
        if ( "display_name" === $key ) {
            return $user->display_name;
        }
        if ( "description" === $key ) {
            return $GLOBALS["test_user_meta"][ $user_id ]["description"] ?? "";
        }
        return get_user_meta( $user_id, $key, true );
    }
    function get_author_posts_url( int $user_id ): string {
        return "https://example.test/author/" . $GLOBALS["test_users"][ $user_id ]->user_nicename . "/";
    }
    function get_avatar_url( int $user_id, array $args = [] ): string {
        return "https://example.test/avatar-" . $user_id . "-" . (int) ( $args["size"] ?? 96 ) . ".jpg";
    }
    function wp_get_attachment_image_url( int $id, string $size ) { return false; }
    function wp_get_object_terms( int $post_id, string $taxonomy, array $args = [] ) {
        $out = [];
        foreach ( $GLOBALS["test_relationships"][ $post_id ] ?? [] as $term_id ) {
            $out[] = (object) [ "term_id" => $term_id ];
        }
        return $out;
    }
    function wp_set_object_terms( int $post_id, array $terms, string $taxonomy, bool $append = false ): array {
        $GLOBALS["test_relationships"][ $post_id ] = array_values( $terms );
        return $terms;
    }
    function get_term_meta( int $term_id, string $key, bool $single = false ) {
        return $GLOBALS["test_term_meta"][ $term_id ][ $key ] ?? "";
    }
    function update_term_meta( int $term_id, string $key, $value ): bool {
        $GLOBALS["test_term_meta"][ $term_id ][ $key ] = $value;
        return true;
    }
    function get_terms( array $args ) {
        $wanted = (int) ( $args["meta_query"][0]["value"] ?? 0 );
        foreach ( $GLOBALS["test_term_meta"] as $term_id => $meta ) {
            if ( (int) ( $meta["_smpi_user_id"] ?? 0 ) === $wanted ) {
                return [ $term_id ];
            }
        }
        return [];
    }
    function wp_insert_term( string $name, string $taxonomy, array $args ) {
        $term_id = count( $GLOBALS["test_terms"] ) + 1;
        $GLOBALS["test_terms"][ $term_id ] = [ "name" => $name, "slug" => $args["slug"] ];
        return [ "term_id" => $term_id ];
    }
    function wp_delete_term( int $term_id, string $taxonomy ): bool { return true; }
    function wp_cache_get( string $key, string $group = "" ) {
        return $GLOBALS["test_cache"][ $group ][ $key ] ?? false;
    }
    function wp_cache_set( string $key, $value, string $group = "" ): bool {
        $GLOBALS["test_cache"][ $group ][ $key ] = $value;
        return true;
    }
    function wp_cache_delete( string $key, string $group = "" ): bool {
        unset( $GLOBALS["test_cache"][ $group ][ $key ] );
        return true;
    }
    function clean_post_cache( int $post_id ): void {}
    function current_user_can( string $capability, ...$args ): bool { return true; }
    function get_option( string $key, $default = false ) { return $default; }
    function update_option( string $key, $value, bool $autoload = false ): bool { return true; }
    function is_admin(): bool { return false; }
    function is_author(): bool { return false; }
    function is_singular( $types = null ): bool { return (bool) $GLOBALS["test_is_singular"]; }
    function in_the_loop(): bool { return true; }
    function get_queried_object_id(): int { return 10; }
    function get_queried_object() { return null; }
    function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $value ) ); }
    function sanitize_title( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( $value ) ) ); }
    function sanitize_html_class( string $value ): string { return preg_replace( '/[^A-Za-z0-9_-]/', '', $value ); }
    function esc_html( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, "UTF-8" ); }
    function esc_attr( string $value ): string { return esc_html( $value ); }
    function esc_url( string $value ): string { return $value; }
    function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); }
    function wp_kses_post( string $value ): string { return $value; }
    function wpautop( string $value ): string { return "<p>" . $value . "</p>"; }
    function wp_json_encode( $value ): string { return json_encode( $value ); }
    function untrailingslashit( string $value ): string { return rtrim( $value, "/" ); }
    function trailingslashit( string $value ): string { return rtrim( $value, "/" ) . "/"; }
    function shortcode_atts( array $defaults, array $atts, string $tag = "" ): array { return array_merge( $defaults, $atts ); }
    function absint( $value ): int { return abs( (int) $value ); }
    function is_wp_error( $value ): bool { return false; }
    function get_posts( array $args ): array { return []; }
    function wp_is_post_revision( int $post_id ) { return false; }
    function wp_is_post_autosave( int $post_id ) { return false; }
}

namespace smp_publication_integration\Support {
    final class Settings {
        public static function bool( string $key ): bool {
            return ! in_array( $key, [ "multi_authors_disable_loop_cards", "muckrack_verified_enabled" ], true );
        }
        public static function get( string $key, $default = null ) {
            return "multi_authors_loop_output" === $key ? "comma" : $default;
        }
        public static function array( string $key ): array { return []; }
    }

    final class RuntimeContext {
        public static function has_article_loop_context(): bool { return true; }
        public static function is_public_dom_context(): bool { return true; }
    }
}

namespace {
    require_once dirname( __DIR__ ) . "/src/Support/Autoloader.php";
    \smp_publication_integration\Support\Autoloader::register( dirname( __DIR__ ) . "/src" );

    use smp_publication_integration\Authorship\AuthorAssignmentRepository;
    use smp_publication_integration\Authorship\AuthorContext;
    use smp_publication_integration\Authorship\ElementorAuthorRenderer;
    use smp_publication_integration\Authorship\LoopBylineRenderer;
    use smp_publication_integration\Content\MuckRackVerification;

    function expect_same( $expected, $actual, string $message ): void {
        if ( $expected !== $actual ) {
            fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
            exit( 1 );
        }
    }

    $repository = new AuthorAssignmentRepository();
    expect_same( [ 2, 1 ], $repository->normalize_ids( [ 2, 1, 2, 999, 0 ] ), "IDs retain order and remove duplicates/invalid users." );
    expect_same( [ 1, 2 ], $repository->ids_for_post( 10, false ), "Legacy ACF values are readable before migration." );

    $saved = $repository->set_ids( 10, [ 2, 1, 2 ], true );
    expect_same( [ 2, 1 ], $saved, "Canonical save preserves order." );
    expect_same( 2, $GLOBALS["test_posts"][10]->post_author, "Native post_author synchronizes to primary selected author." );
    expect_same( [ 2, 1 ], $repository->ids_for_post( 10, false ), "Canonical taxonomy is the read source after save." );

    expect_same( 2, AuthorContext::resolve( $repository, 0, 10, 0 ), "Primary author context resolves from canonical assignments." );
    expect_same( 1, AuthorContext::resolve( $repository, 0, 10, 1 ), "Secondary author context resolves by index." );
    expect_same( 1, AuthorContext::run( 1, static fn(): int => AuthorContext::resolve( $repository, 0, 10, 0 ) ), "Explicit runtime context wins." );

    $GLOBALS["test_is_singular"] = false;
    $loop = new LoopBylineRenderer( $repository );
    $byline = $loop->filter( '<span class="byline"><a href="https://example.test/author/beta-author/">Beta Author</a></span>' );
    expect_same( 1, substr_count( $byline, 'href="https://example.test/author/beta-author/"' ), "Primary byline has its own URL." );
    expect_same( 1, substr_count( $byline, 'href="https://example.test/author/alpha-author/"' ), "Secondary byline has its own URL." );

    $GLOBALS["test_is_singular"] = true;
    $renderer = new ElementorAuthorRenderer( $repository );
    $template = '<section><div class="elementor-element smpi-author-module"><a href="https://example.test/author/beta-author/">Beta Author</a><img src="https://example.test/avatar-2-300.jpg" alt="Beta Author"></div><div class="share">SHARE</div></section>';
    $rendered = $renderer->filter_content( $template );
    expect_same( 2, substr_count( $rendered, "smpi-multi-author-item" ), "Marked author root repeats once per selected author." );
    expect_same( 1, substr_count( $rendered, '<div class="share">SHARE</div>' ), "Unrelated sibling markup is never duplicated." );
    expect_same( 1, substr_count( $rendered, "avatar-1-300.jpg" ), "Secondary avatar is rebound." );
    expect_same( 1, substr_count( $rendered, "alpha-author/" ), "Secondary author URL is rebound." );

    $primary_template = '<div class="elementor-element smp-author"><a href="https://example.test/author/beta-author/">Beta Author</a><span class="share">SHARE</span></div>';
    $primary_rendered = $renderer->filter_content( $primary_template );
    expect_same( 2, substr_count( $primary_rendered, "smpi-multi-author-item" ), "Primary smp-author contract repeats once per selected author." );
    expect_same( 1, substr_count( $primary_rendered, '<span class="share">SHARE</span>' ), "Non-author direct children inside a marked unit are preserved once." );
    expect_same( 1, substr_count( $primary_rendered, "alpha-author/" ), "Primary smp-author contract rebinds secondary author URLs." );

    $GLOBALS["test_is_singular"] = false;
    $loop_widget_template = '<div class="elementor-element smp-author"><a href="https://example.test/author/beta-author/">Beta Author</a></div>';
    $loop_widget_rendered = $renderer->filter_widget( $loop_widget_template, null );
    expect_same( 2, substr_count( $loop_widget_rendered, "smpi-multi-author-item" ), "Primary smp-author contract also runs inside public Elementor loop widgets." );
    expect_same( 1, substr_count( $loop_widget_rendered, "beta-author/" ), "Loop widget primary author keeps its own URL." );
    expect_same( 1, substr_count( $loop_widget_rendered, "alpha-author/" ), "Loop widget secondary author gets its own URL." );
    $GLOBALS["test_is_singular"] = true;

    expect_same( "Editorial Lead", MuckRackVerification::author_field( 1, "author_title" ), "MuckRack field lookup uses the canonical author field resolver aliases." );
    expect_same( "https://muckrack.com/alpha-author", MuckRackVerification::author_field( 1, "muckrack_url" ), "MuckRack URL lookup uses the canonical author field resolver aliases." );

    $repository->clear( 10 );
    $GLOBALS["test_meta"][10]["smpi_post_authors"] = [];
    $GLOBALS["test_posts"][10]->post_author = 1;
    $repository->clear_cache( 10 );
    $unchanged = $renderer->filter_content( $template );
    expect_same( $template, $unchanged, "Empty selection leaves native Elementor output untouched." );

    echo "PASS: multiple-author unit and DOM regression tests\n";
}
