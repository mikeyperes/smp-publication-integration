<?php
namespace smp_publication_integration\Authorship;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/** Prepare responsive author copies only after photo or template changes. */
final class AuthorImageCopies {
    private array $authors = [];
    private bool $all_authors = false;

    public function register(): void {
        add_action( "added_user_meta", [ $this, "photo_changed" ], 20, 3 );
        add_action( "updated_user_meta", [ $this, "photo_changed" ], 20, 3 );
        add_action( "profile_update", [ $this, "queue_author" ], 20 );
        add_action( "added_post_meta", [ $this, "template_changed" ], 20, 3 );
        add_action( "updated_post_meta", [ $this, "template_changed" ], 20, 3 );
        add_action( "save_post", [ $this, "content_changed" ], 20, 2 );
        add_action( "shutdown", [ $this, "prepare_queued" ] );
    }

    public function photo_changed( $meta_id, int $user_id, string $key ): void {
        $fields = AuthorFieldResolver::aliases()["image"];
        if ( in_array( $key, $fields, true ) || "_avatar" === substr( $key, -7 ) ) {
            $this->queue_author( $user_id );
        }
    }

    public function queue_author( int $user_id ): void {
        $this->authors[ $user_id ] = $user_id;
    }

    public function template_changed( $meta_id, int $post_id, string $key ): void {
        if ( "_elementor_data" === $key ) {
            $this->all_authors = true;
        }
    }

    public function content_changed( int $post_id, \WP_Post $post ): void {
        if ( ! wp_is_post_revision( $post_id ) && has_shortcode( $post->post_content, "author_image" ) ) {
            $this->all_authors = true;
        }
    }

    public function prepare_queued(): void {
        if ( $this->all_authors ) {
            $this->regenerate();
        } elseif ( $this->authors ) {
            $this->regenerate( array_values( $this->authors ) );
        }
    }

    /** Explicit regeneration also prepares existing author photos during a rollout. */
    public function regenerate( ?array $author_ids = null ): void {
        $slots = $this->saved_slots();
        if ( ! $slots ) {
            return;
        }
        $author_ids = $author_ids ?? get_users( [ "fields" => "ID" ] );
        $resolver = new AuthorFieldResolver();
        foreach ( $author_ids as $author_id ) {
            $attachment_id = $resolver->explicit_image_attachment_id( (int) $author_id );
            if ( ! $attachment_id ) {
                continue;
            }
            foreach ( $slots as $slot ) {
                $image = wp_get_attachment_image_src( $attachment_id, $slot["size"] );
                if ( is_array( $image ) && $image[0] === $resolver->image_url( (int) $author_id, $slot["size"] ) ) {
                    self::ensure_slot_copies( $attachment_id, $image, $slot["sizes"] );
                }
            }
        }
    }

    private function saved_slots(): array {
        $types = array_values( get_post_types( [ "public" => true ] ) );
        $types[] = "elementor_library";
        $query = [ "post_type" => $types, "post_status" => [ "publish", "draft", "private" ], "posts_per_page" => -1, "fields" => "ids" ];
        $templates = get_posts( $query + [ "meta_key" => "_elementor_data" ] );
        $posts = get_posts( $query + [ "s" => "[author_image" ] );
        $slots = [];
        foreach ( array_unique( array_merge( $templates, $posts ) ) as $post_id ) {
            $this->collect_slots( (string) get_post_field( "post_content", $post_id ), $slots );
            $data = get_post_meta( $post_id, "_elementor_data", true );
            $this->collect_slots( is_string( $data ) ? json_decode( $data, true ) : $data, $slots );
        }
        return array_values( $slots );
    }

    private function collect_slots( $value, array &$slots ): void {
        if ( is_array( $value ) ) {
            foreach ( $value as $child ) {
                $this->collect_slots( $child, $slots );
            }
        } elseif ( is_string( $value ) && preg_match_all( '/' . get_shortcode_regex( [ "author_image" ] ) . '/s', $value, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $atts = shortcode_parse_atts( $match[3] );
                if ( ! is_array( $atts ) || empty( $atts["sizes"] ) || "[" === $match[1] ) {
                    continue;
                }
                $size = sanitize_key( (string) ( $atts["size"] ?? "thumbnail" ) );
                if ( ! in_array( $size, [ "thumbnail", "medium", "medium_large", "large", "full" ], true ) ) {
                    $size = "thumbnail";
                }
                $sizes = trim( (string) $atts["sizes"] );
                $slots[ $size . "|" . $sizes ] = [ "size" => $size, "sizes" => $sizes ];
            }
        }
    }

    /** Cache missing 1x/2x copies for declared pixel slots, retaining the selected crop. */
    private static function ensure_slot_copies( int $attachment_id, array $image, string $sizes ): void {
        $widths = [];
        foreach ( explode( ",", $sizes ) as $slot ) {
            if ( preg_match( '/(?:^|\s)(\d+(?:\.\d+)?)px\s*$/', trim( $slot ), $match ) ) {
                foreach ( [ 1, 2 ] as $density ) {
                    $width = (int) ceil( (float) $match[1] * $density );
                    if ( $width > 0 && $width < (int) $image[1] ) {
                        $widths[] = $width;
                    }
                }
            }
        }
        if ( ! $widths ) {
            return;
        }
        $metadata = wp_get_attachment_metadata( $attachment_id );
        $uploads = wp_get_upload_dir();
        if ( ! is_array( $metadata ) || empty( $metadata["file"] ) || ! empty( $uploads["error"] ) ) {
            return;
        }
        $source = path_join( dirname( path_join( $uploads["basedir"], $metadata["file"] ) ), wp_basename( $image[0] ) );
        if ( ! is_file( $source ) ) {
            return;
        }
        $changed = false;
        foreach ( array_unique( $widths ) as $width ) {
            $key = "smpi-author-" . $image[1] . "x" . $image[2] . "-" . $width;
            if ( isset( $metadata["sizes"][ $key ] ) ) {
                continue;
            }
            if ( ! function_exists( "image_make_intermediate_size" ) ) {
                require_once ABSPATH . "wp-admin/includes/image.php";
            }
            $copy = image_make_intermediate_size( $source, $width, 0, false );
            if ( is_array( $copy ) ) {
                $metadata["sizes"][ $key ] = $copy;
                $changed = true;
            }
        }
        if ( $changed ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
        }
    }
}
