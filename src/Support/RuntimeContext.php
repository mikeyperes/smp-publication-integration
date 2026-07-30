<?php
namespace smp_publication_integration\Support;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class RuntimeContext {
    public static function is_background_request(): bool {
        if ( ( function_exists( "is_admin" ) && is_admin() )
            || ( ( function_exists( "wp_doing_ajax" ) && wp_doing_ajax() ) || ( defined( "DOING_AJAX" ) && DOING_AJAX ) )
            || ( ( function_exists( "wp_doing_cron" ) && wp_doing_cron() ) || ( defined( "DOING_CRON" ) && DOING_CRON ) )
            || ( defined( "WP_CLI" ) && WP_CLI )
            || ( ( function_exists( "wp_is_serving_rest_request" ) && wp_is_serving_rest_request() ) || ( defined( "REST_REQUEST" ) && REST_REQUEST ) )
            || ( defined( "XMLRPC_REQUEST" ) && XMLRPC_REQUEST )
        ) {
            return true;
        }

        return false;
    }

    public static function is_public_frontend(): bool {
        if ( self::is_background_request() ) {
            return false;
        }
        if ( function_exists( "is_feed" ) && is_feed() ) {
            return false;
        }
        if ( function_exists( "is_embed" ) && is_embed() ) {
            return false;
        }
        return ! self::is_elementor_editor_or_preview();
    }

    public static function is_public_dom_context(): bool {
        return self::is_public_frontend();
    }

    public static function is_elementor_editor_or_preview(): bool {
        foreach ( [ $_GET, $_POST, $_REQUEST ] as $source ) {
            if ( ! is_array( $source ) ) {
                continue;
            }
            if ( isset( $source["elementor-preview"] ) || isset( $source["elementor_library"] ) || isset( $source["elementor_page_id"] ) ) {
                return true;
            }
            if ( isset( $source["action"] ) && "elementor" === sanitize_key( (string) $source["action"] ) ) {
                return true;
            }
        }

        if ( class_exists( "\\Elementor\\Plugin" ) ) {
            try {
                $elementor = \Elementor\Plugin::$instance ?? null;
                if ( $elementor ) {
                    if ( isset( $elementor->editor ) && method_exists( $elementor->editor, "is_edit_mode" ) && $elementor->editor->is_edit_mode() ) {
                        return true;
                    }
                    if ( isset( $elementor->preview ) && method_exists( $elementor->preview, "is_preview_mode" ) && $elementor->preview->is_preview_mode() ) {
                        return true;
                    }
                }
            } catch ( \Throwable $e ) {
                return true;
            }
        }

        $post = get_post();
        return $post instanceof \WP_Post && "elementor_library" === (string) $post->post_type;
    }

    public static function has_article_loop_context(): bool {
        if ( ! self::is_public_dom_context() ) {
            return false;
        }
        return is_home() || is_front_page() || is_archive() || is_search() || is_author() || is_singular( "post" );
    }
}
