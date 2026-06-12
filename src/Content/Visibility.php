<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Visibility {
    private const HOME_META = '_smpi_shadow_home';
    private const ARCHIVE_META = '_smpi_shadow_archives';
    private const PR_OVERRIDE_META = '_smpi_pr_shadow_override';

    public function register(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );
        add_action( 'pre_get_posts', [ $this, 'filter_queries' ], 1000 );
        add_filter( 'posts_where', [ $this, 'filter_press_release_where' ], 10, 2 );
    }

    public function add_meta_boxes(): void {
        add_meta_box( 'smpi_visibility', 'SMP Publication Visibility', [ $this, 'render_meta_box' ], [ 'post', 'press-release' ], 'side', 'high' );
    }

    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'smpi_visibility_save', 'smpi_visibility_nonce' );
        $hide_home = (bool) get_post_meta( $post->ID, self::HOME_META, true );
        $hide_archives = (bool) get_post_meta( $post->ID, self::ARCHIVE_META, true );
        $pr_override = (string) get_post_meta( $post->ID, self::PR_OVERRIDE_META, true );
        ?>
        <p><label><input type="checkbox" name="smpi_shadow_home" value="1" <?php checked( $hide_home ); ?>> Hide this post from the home page query.</label></p>
        <p><label><input type="checkbox" name="smpi_shadow_archives" value="1" <?php checked( $hide_archives ); ?>> Hide this post from category and tag archive queries.</label></p>
        <?php if ( 'press-release' === $post->post_type ) : ?>
            <p><label for="smpi_pr_shadow_override"><strong>Press-release global shadow override</strong></label></p>
            <select id="smpi_pr_shadow_override" name="smpi_pr_shadow_override" style="width:100%;">
                <option value="" <?php selected( $pr_override, '' ); ?>>Use global setting</option>
                <option value="show" <?php selected( $pr_override, 'show' ); ?>>Always show on home/category/tag</option>
                <option value="hide" <?php selected( $pr_override, 'hide' ); ?>>Always hide on home/category/tag</option>
            </select>
        <?php endif; ?>
        <?php
    }

    public function save_meta( int $post_id, \WP_Post $post ): void {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, [ 'post', 'press-release' ], true ) ) {
            return;
        }
        if ( ! isset( $_POST['smpi_visibility_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smpi_visibility_nonce'] ) ), 'smpi_visibility_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        update_post_meta( $post_id, self::HOME_META, isset( $_POST['smpi_shadow_home'] ) ? '1' : '' );
        update_post_meta( $post_id, self::ARCHIVE_META, isset( $_POST['smpi_shadow_archives'] ) ? '1' : '' );
        if ( 'press-release' === $post->post_type ) {
            $override = isset( $_POST['smpi_pr_shadow_override'] ) ? sanitize_key( wp_unslash( $_POST['smpi_pr_shadow_override'] ) ) : '';
            update_post_meta( $post_id, self::PR_OVERRIDE_META, in_array( $override, [ 'show', 'hide' ], true ) ? $override : '' );
        }
    }

    public function filter_queries( \WP_Query $query ): void {
        if ( is_admin() || ! $query->is_main_query() || $query->is_feed() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }
        $is_home = $query->is_home() || is_front_page();
        $is_archive = $query->is_category() || $query->is_tag();
        if ( ! $is_home && ! $is_archive ) {
            return;
        }
        if ( $is_home ) {
            $this->append_meta_exclusion( $query, self::HOME_META );
        }
        if ( $is_archive ) {
            $this->append_meta_exclusion( $query, self::ARCHIVE_META );
        }
        if ( Settings::bool( 'shadow_press_releases' ) ) {
            $post_type = $query->get( 'post_type' );
            if ( empty( $post_type ) || 'post' === $post_type ) {
                $query->set( 'post_type', [ 'post', 'press-release' ] );
            }
            $query->set( 'smpi_press_release_shadow', true );
        }
    }

    public function filter_press_release_where( string $where, \WP_Query $query ): string {
        if ( ! $query->get( 'smpi_press_release_shadow' ) ) {
            return $where;
        }
        global $wpdb;
        return $where . $wpdb->prepare(
            " AND ( {$wpdb->posts}.post_type <> %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} smpi_pr_show WHERE smpi_pr_show.post_id = {$wpdb->posts}.ID AND smpi_pr_show.meta_key = %s AND smpi_pr_show.meta_value = %s ) )",
            'press-release',
            self::PR_OVERRIDE_META,
            'show'
        );
    }

    private function append_meta_exclusion( \WP_Query $query, string $meta_key ): void {
        $meta_query = (array) $query->get( 'meta_query' );
        $meta_query[] = [
            'relation' => 'OR',
            [ 'key' => $meta_key, 'compare' => 'NOT EXISTS' ],
            [ 'key' => $meta_key, 'value' => '1', 'compare' => '!=' ],
        ];
        $query->set( 'meta_query', $meta_query );
    }
}