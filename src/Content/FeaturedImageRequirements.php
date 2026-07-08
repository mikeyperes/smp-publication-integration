<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class FeaturedImageRequirements {
    private bool $blocked_classic_publish = false;

    public function register(): void {
        add_action( "pre_get_posts", [ $this, "filter_home_queries" ], 900 );
        add_filter( "posts_where", [ $this, "filter_thumbnail_where" ], 10, 2 );
        add_filter( "rest_pre_insert_post", [ $this, "validate_rest_featured_image" ], 10, 2 );
        add_filter( "wp_insert_post_data", [ $this, "prevent_classic_publish_without_featured_image" ], 10, 2 );
        add_filter( "redirect_post_location", [ $this, "classic_redirect_notice" ], 10, 2 );
        add_action( "admin_notices", [ $this, "render_admin_notice" ] );
        add_action( "admin_footer-post.php", [ $this, "print_editor_script" ] );
        add_action( "admin_footer-post-new.php", [ $this, "print_editor_script" ] );
    }

    public function filter_home_queries( \WP_Query $query ): void {
        if ( ! Settings::bool( "hide_home_posts_without_featured_image" ) || is_admin() || $query->is_search() || $query->is_feed() || ( function_exists( "wp_doing_ajax" ) && wp_doing_ajax() ) || ( defined( "REST_REQUEST" ) && REST_REQUEST ) ) {
            return;
        }

        if ( ! $this->is_home_context_query( $query ) || ! $this->query_can_include_posts( $query ) ) {
            return;
        }

        $query->set( "smpi_require_post_thumbnail_for_posts", true );
    }

    public function filter_thumbnail_where( string $where, \WP_Query $query ): string {
        if ( ! $query->get( "smpi_require_post_thumbnail_for_posts" ) ) {
            return $where;
        }

        global $wpdb;

        $where .= $wpdb->prepare(
            " AND ( {$wpdb->posts}.post_type <> %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} smpi_thumb WHERE smpi_thumb.post_id = {$wpdb->posts}.ID AND smpi_thumb.meta_key = %s AND smpi_thumb.meta_value <> '' AND smpi_thumb.meta_value <> '0' ) )",
            "post",
            "_thumbnail_id"
        );

        return $where;
    }

    public function validate_rest_featured_image( $prepared_post, \WP_REST_Request $request ) {
        if ( ! Settings::bool( "post_featured_image_required" ) || ! is_object( $prepared_post ) || "post" !== (string) ( $prepared_post->post_type ?? "post" ) ) {
            return $prepared_post;
        }

        $status = isset( $prepared_post->post_status ) ? (string) $prepared_post->post_status : "";
        $post_id = isset( $prepared_post->ID ) ? (int) $prepared_post->ID : absint( $request->get_param( "id" ) );
        if ( ! in_array( $status, [ "publish", "future", "pending" ], true ) || $this->request_has_featured_image( $post_id, $request ) ) {
            return $prepared_post;
        }

        return new \WP_Error(
            "smpi_featured_image_required",
            "Featured image is required before publishing this post.",
            [ "status" => 400 ]
        );
    }

    public function prevent_classic_publish_without_featured_image( array $data, array $postarr ): array {
        if ( ! Settings::bool( "post_featured_image_required" ) || "post" !== (string) ( $data["post_type"] ?? "" ) || ! in_array( (string) ( $data["post_status"] ?? "" ), [ "publish", "future", "pending" ], true ) ) {
            return $data;
        }

        $post_id = isset( $postarr["ID"] ) ? absint( $postarr["ID"] ) : 0;
        if ( $this->request_has_featured_image( $post_id ) ) {
            return $data;
        }

        $this->blocked_classic_publish = true;
        $data["post_status"] = "draft";

        return $data;
    }

    public function classic_redirect_notice( string $location, int $post_id ): string {
        if ( ! $this->blocked_classic_publish ) {
            return $location;
        }

        return add_query_arg( "smpi_featured_image_required", "1", $location );
    }

    public function render_admin_notice(): void {
        if ( ! Settings::bool( "post_featured_image_required" ) || ! $this->is_post_editor_screen() ) {
            return;
        }

        $post_id = $this->current_post_id();
        $forced = isset( $_GET["smpi_featured_image_required"] ) && "1" === sanitize_text_field( wp_unslash( $_GET["smpi_featured_image_required"] ) );
        if ( ! $forced && $post_id > 0 && has_post_thumbnail( $post_id ) ) {
            return;
        }

        echo '<div class="notice notice-error smpi-featured-image-required-notice"><p><strong>Featured image required.</strong> Add a featured image before publishing this post.</p></div>';
    }

    public function print_editor_script(): void {
        if ( ! Settings::bool( "post_featured_image_required" ) || ! $this->is_post_editor_screen() ) {
            return;
        }

        ?>
        <style>
            .smpi-featured-image-required-editor-notice {
                background: #b42336;
                border-left: 6px solid #7a1020;
                box-shadow: 0 1px 2px rgba(0,0,0,.08);
                color: #fff;
                font-weight: 700;
                margin: 12px 0;
                padding: 12px 14px;
            }
            .smpi-featured-image-required-editor-notice.is-hidden {
                display: none;
            }
        </style>
        <script>
        (function(){
            function currentClassicId(){
                var field = document.querySelector('#_thumbnail_id');
                if (!field) return 0;
                var value = parseInt(field.value || '0', 10);
                return isNaN(value) ? 0 : value;
            }
            function currentBlockId(){
                if (!window.wp || !wp.data || !wp.data.select) return 0;
                try {
                    var editor = wp.data.select('core/editor');
                    if (!editor || !editor.getEditedPostAttribute) return 0;
                    var value = parseInt(editor.getEditedPostAttribute('featured_media') || '0', 10);
                    return isNaN(value) ? 0 : value;
                } catch (e) {
                    return 0;
                }
            }
            function hasFeaturedImage(){
                return currentBlockId() > 0 || currentClassicId() > 0 || !!document.querySelector('#postimagediv img, .editor-post-featured-image img');
            }
            function notice(){
                var target = document.querySelector('.edit-post-visual-editor__post-title-wrapper, .edit-post-layout__content, #post-body-content, .wrap h1');
                var existing = document.querySelector('.smpi-featured-image-required-editor-notice');
                if (!existing) {
                    existing = document.createElement('div');
                    existing.className = 'smpi-featured-image-required-editor-notice';
                    existing.textContent = 'Featured image required before publishing this post.';
                    if (target && target.parentNode) {
                        target.parentNode.insertBefore(existing, target);
                    }
                }
                return existing;
            }
            function setLocked(locked){
                var item = notice();
                if (item) item.classList.toggle('is-hidden', !locked);
                document.querySelectorAll('#publish, .editor-post-publish-button, .editor-post-publish-panel__toggle').forEach(function(button){
                    if (locked) {
                        button.setAttribute('data-smpi-featured-image-lock', '1');
                        button.setAttribute('title', 'Featured image required before publishing this post.');
                    } else if (button.getAttribute('data-smpi-featured-image-lock') === '1') {
                        button.removeAttribute('data-smpi-featured-image-lock');
                        button.removeAttribute('title');
                    }
                });
            }
            function refresh(){
                setLocked(!hasFeaturedImage());
            }
            document.addEventListener('click', function(event){
                var lockedButton = event.target.closest('[data-smpi-featured-image-lock="1"]');
                if (lockedButton && !hasFeaturedImage()) {
                    event.preventDefault();
                    event.stopPropagation();
                    refresh();
                }
            }, true);
            if (window.wp && wp.data && wp.data.subscribe) {
                wp.data.subscribe(refresh);
            }
            if ('MutationObserver' in window) {
                new MutationObserver(refresh).observe(document.documentElement, { childList: true, subtree: true, attributes: true });
            }
            document.addEventListener('DOMContentLoaded', refresh);
            window.setTimeout(refresh, 500);
            window.setTimeout(refresh, 1500);
        })();
        </script>
        <?php
    }

    private function is_home_context_query( \WP_Query $query ): bool {
        return $query->is_home() || ( function_exists( "is_front_page" ) && is_front_page() );
    }

    private function query_can_include_posts( \WP_Query $query ): bool {
        $post_type = $query->get( "post_type" );
        return empty( $post_type ) || "post" === $post_type || ( is_array( $post_type ) && in_array( "post", $post_type, true ) );
    }

    private function request_has_featured_image( int $post_id, ?\WP_REST_Request $request = null ): bool {
        if ( $request instanceof \WP_REST_Request && $request->has_param( "featured_media" ) ) {
            return absint( $request->get_param( "featured_media" ) ) > 0;
        }

        if ( isset( $_POST["_thumbnail_id"] ) ) {
            return absint( wp_unslash( $_POST["_thumbnail_id"] ) ) > 0;
        }

        return $post_id > 0 && has_post_thumbnail( $post_id );
    }

    private function is_post_editor_screen(): bool {
        $screen = function_exists( "get_current_screen" ) ? get_current_screen() : null;
        return $screen instanceof \WP_Screen && "post" === (string) $screen->post_type && in_array( (string) $screen->base, [ "post", "post-new" ], true );
    }

    private function current_post_id(): int {
        if ( isset( $_GET["post"] ) ) {
            return absint( $_GET["post"] );
        }

        global $post;
        return $post instanceof \WP_Post ? (int) $post->ID : 0;
    }
}
