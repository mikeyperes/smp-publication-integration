<?php
namespace smp_publication_integration\Content;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use smp_publication_integration\Support\Dependencies;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class Visibility {
    private const HOME_META = "_smpi_shadow_home";
    private const ARCHIVE_META = "_smpi_shadow_archives";
    private const COMPLETE_META = "_smpi_shadow_complete";
    private const PR_OVERRIDE_META = "_smpi_pr_shadow_override";
    public function register(): void {
        add_action( "init", [ $this, "register_press_release_taxonomies" ], 20 );
        add_action( "add_meta_boxes", [ $this, "add_meta_boxes" ] );
        add_action( "save_post", [ $this, "save_meta" ], 10, 2 );
        add_action( "pre_get_posts", [ $this, "filter_queries" ], 1000 );
        add_filter( "posts_where", [ $this, "filter_press_release_where" ], 10, 2 );
    }

    public function register_press_release_taxonomies(): void {
        if ( post_type_exists( "press-release" ) ) {
            register_taxonomy_for_object_type( "category", "press-release" );
            register_taxonomy_for_object_type( "post_tag", "press-release" );
        }
    }

    public function add_meta_boxes(): void {
        add_meta_box( "smpi_visibility", "Post visibility", [ $this, "render_meta_box" ], [ "post", "press-release" ], "side", "high" );
    }

    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( "smpi_visibility_save", "smpi_visibility_nonce" );
        $shadow_enabled = Settings::bool( "shadow_posts_enabled" );
        $hide_home = (bool) get_post_meta( $post->ID, self::HOME_META, true );
        $hide_complete = (bool) get_post_meta( $post->ID, self::COMPLETE_META, true );
        $pr_override = (string) get_post_meta( $post->ID, self::PR_OVERRIDE_META, true );
        CoreUi::render_assets();
        ?>
        <div class="hpc-ui smpi-visibility-metabox">
        <?php if ( $shadow_enabled && "post" === $post->post_type ) : ?>
            <div class="hpc-toggle-list">
                <div class="hpc-toggle-row"><?php echo CoreUi::toggle( "smpi_shadow_complete", $hide_complete, "Hide from home and archives", [ "id" => "smpi_shadow_complete", "tooltip" => "Direct URL still works. The post is removed from the home page, category archives, and tag archives." ] ); ?></div>
                <div class="hpc-toggle-row"><?php echo CoreUi::toggle( "smpi_shadow_home", $hide_home, "Hide from homepage only", [ "id" => "smpi_shadow_home", "tooltip" => "The post is removed from the home page query only. Category and tag archives can still show it." ] ); ?></div>
            </div>
        <?php elseif ( "post" === $post->post_type ) : ?>
            <p class="hpc-small">Shadow Posts is disabled in SMP Publication Integration > Features.</p>
        <?php endif; ?>
        <?php if ( "press-release" === $post->post_type ) : ?>
            <label class="hpc-field" for="smpi_pr_shadow_override"><span>PR visibility <?php echo CoreUi::tooltip( "Overrides SMP-managed loops without changing the single press-release URL." ); ?></span>
            <select id="smpi_pr_shadow_override" name="smpi_pr_shadow_override">
                <option value="" <?php selected( $pr_override, "" ); ?>>Use global setting</option>
                <option value="show" <?php selected( $pr_override, "show" ); ?>>Always show</option>
                <option value="hide" <?php selected( $pr_override, "hide" ); ?>>Always hide</option>
            </select></label>
        <?php endif; ?>
        </div>
        <?php
    }

    public function save_meta( int $post_id, \WP_Post $post ): void {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, [ "post", "press-release" ], true ) ) {
            return;
        }
        if ( ! isset( $_POST["smpi_visibility_nonce"] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST["smpi_visibility_nonce"] ) ), "smpi_visibility_save" ) || ! current_user_can( "edit_post", $post_id ) ) {
            return;
        }
        if ( Settings::bool( "shadow_posts_enabled" ) && "post" === $post->post_type ) {
            update_post_meta( $post_id, self::COMPLETE_META, isset( $_POST["smpi_shadow_complete"] ) ? "1" : "" );
            update_post_meta( $post_id, self::HOME_META, isset( $_POST["smpi_shadow_home"] ) ? "1" : "" );
            update_post_meta( $post_id, self::ARCHIVE_META, isset( $_POST["smpi_shadow_complete"] ) ? "1" : "" );
        }
        if ( "press-release" === $post->post_type ) {
            $override = isset( $_POST["smpi_pr_shadow_override"] ) ? sanitize_key( wp_unslash( $_POST["smpi_pr_shadow_override"] ) ) : "";
            update_post_meta( $post_id, self::PR_OVERRIDE_META, in_array( $override, [ "show", "hide" ], true ) ? $override : "" );
        }
    }

    public function filter_queries( \WP_Query $query ): void {
        if ( is_admin() || $query->is_search() || $query->is_feed() || ( function_exists( "wp_doing_ajax" ) && wp_doing_ajax() ) || ( defined( "REST_REQUEST" ) && REST_REQUEST ) || ( $query->is_post_type_archive() && ! ( function_exists( "is_author" ) && is_author() ) ) ) {
            return;
        }
        $context = $this->query_context( $query );
        if ( "" === $context ) {
            return;
        }
        if ( Settings::bool( "shadow_posts_enabled" ) && $query->is_main_query() && "home" === $context ) {
            $this->append_meta_exclusion( $query, self::COMPLETE_META );
            $this->append_meta_exclusion( $query, self::HOME_META );
        }
        if ( Settings::bool( "shadow_posts_enabled" ) && $query->is_main_query() && "category_tag" === $context ) {
            $this->append_meta_exclusion( $query, self::COMPLETE_META );
            $this->append_meta_exclusion( $query, self::ARCHIVE_META );
        }
        if ( $this->should_include_press_releases( $context, $query ) ) {
            $this->ensure_press_release_post_type( $query );
            $query->set( "smpi_press_release_force_exclude", true );
        }
        if ( Settings::bool( "shadow_press_releases" ) && in_array( $context, [ "home", "category_tag" ], true ) ) {
            $this->ensure_press_release_post_type( $query );
            $query->set( "smpi_press_release_shadow", true );
            $query->set( "smpi_press_release_force_exclude", true );
        }
    }

    public function filter_press_release_where( string $where, \WP_Query $query ): string {
        global $wpdb;
        if ( $query->get( "smpi_press_release_shadow" ) ) {
            $where .= $wpdb->prepare( " AND ( {$wpdb->posts}.post_type <> %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} smpi_pr_show WHERE smpi_pr_show.post_id = {$wpdb->posts}.ID AND smpi_pr_show.meta_key = %s AND smpi_pr_show.meta_value = %s ) )", "press-release", self::PR_OVERRIDE_META, "show" );
        }
        if ( $query->get( "smpi_press_release_force_exclude" ) ) {
            $where .= $wpdb->prepare( " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} smpi_pr_hide WHERE smpi_pr_hide.post_id = {$wpdb->posts}.ID AND smpi_pr_hide.meta_key = %s AND smpi_pr_hide.meta_value = %s )", self::PR_OVERRIDE_META, "hide" );
        }
        return $where;
    }

    private function query_context( \WP_Query $query ): string {
        if ( $query->is_main_query() && ( $query->is_home() || is_front_page() ) ) {
            return "home";
        }
        if ( $query->is_main_query() && ( $query->is_category() || $query->is_tag() ) ) {
            return "category_tag";
        }
        if ( $query->is_main_query() && $query->is_author() ) {
            return "author";
        }
        if ( ! $query->is_main_query() && function_exists( "is_author" ) && is_author() && ! is_singular() ) {
            return "author";
        }
        if ( ! $query->is_main_query() && is_singular( "post" ) ) {
            return "single_recent";
        }
        return "";
    }

    private function should_include_press_releases( string $context, \WP_Query $query ): bool {
        if ( ! Settings::bool( "press_release_include_enabled" ) || ! Dependencies::hpr_active() ) {
            return false;
        }
        if ( ! in_array( $context, Settings::array( "press_release_include_contexts" ), true ) ) {
            return false;
        }
        return !( "single_recent" === $context && $query->is_main_query() );
    }

    private function ensure_press_release_post_type( \WP_Query $query ): void {
        $current = $query->get( "post_type" );
        if ( empty( $current ) || "post" === $current ) {
            $query->set( "post_type", [ "post", "press-release" ] );
            return;
        }
        if ( is_array( $current ) && in_array( "post", $current, true ) && ! in_array( "press-release", $current, true ) ) {
            $current[] = "press-release";
            $query->set( "post_type", array_values( array_unique( $current ) ) );
        }
    }


    private function append_meta_exclusion( \WP_Query $query, string $meta_key ): void {
        $current = $query->get( "meta_query" );
        $meta_query = is_array( $current ) ? $current : [];
        $meta_query[] = [ "relation" => "OR", [ "key" => $meta_key, "compare" => "NOT EXISTS" ], [ "key" => $meta_key, "value" => "1", "compare" => "!=" ] ];
        $query->set( "meta_query", $meta_query );
    }

    public static function author_report( int $limit = 10 ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT u.ID, u.display_name, MAX(p.post_date) AS latest_post, SUM(CASE WHEN p.post_type = %s THEN 1 ELSE 0 END) AS press_releases, SUM(CASE WHEN p.post_type = %s THEN 1 ELSE 0 END) AS posts FROM {$wpdb->users} u LEFT JOIN {$wpdb->posts} p ON p.post_author = u.ID AND p.post_status = %s AND p.post_type IN (%s, %s) GROUP BY u.ID HAVING latest_post IS NOT NULL ORDER BY latest_post DESC LIMIT %d", "press-release", "post", "publish", "post", "press-release", $limit ) );
        $expected = Settings::bool( "press_release_include_enabled" ) && in_array( "author", Settings::array( "press_release_include_contexts" ), true ) && Dependencies::hpr_active();
        $out = [];
        foreach ( $rows as $row ) {
            $out[] = [ "user_id" => (int) $row->ID, "display_name" => $row->display_name, "latest_post" => $row->latest_post, "posts" => (int) $row->posts, "press_releases" => (int) $row->press_releases, "expected" => $expected, "consistent" => Dependencies::hpr_active() && post_type_exists( "press-release" ) ];
        }
        return $out;
    }
}
