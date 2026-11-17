<?php
namespace smp_publication_integration\Authorship;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/** Prepare document-scoped author copies in a background event after saves. */
final class AuthorImageCopies {
    private const HOOK = "smpi_prepare_author_image_copies";
    private const SLOTS_OPTION = "smpi_author_image_document_slots";

    public function register(): void {
        add_action( "added_user_meta", [ $this, "photo_changed" ], 20, 3 );
        add_action( "updated_user_meta", [ $this, "photo_changed" ], 20, 3 );
        add_action( "profile_update", [ $this, "author_changed" ], 20 );
        add_action( "added_post_meta", [ $this, "template_changed" ], 20, 3 );
        add_action( "updated_post_meta", [ $this, "template_changed" ], 20, 3 );
        add_action( "save_post", [ $this, "document_changed" ], 20 );
        add_action( self::HOOK, [ $this, "prepare" ] );
    }

    public function photo_changed( $meta_id, int $user_id, string $key ): void {
        $fields = AuthorFieldResolver::aliases()["image"];
        if ( in_array( $key, $fields, true ) || "_avatar" === substr( $key, -7 ) ) {
            $this->author_changed( $user_id );
        }
    }

    public function author_changed( int $user_id ): void {
        $pairs = [];
        foreach ( (array) get_option( self::SLOTS_OPTION, [] ) as $document ) {
            foreach ( $document["slots"] as $slot ) {
                if ( $slot["user_id"] === $user_id || ( ! $slot["user_id"] && ( ! $document["authors"] || in_array( $user_id, $document["authors"], true ) ) ) ) {
                    $pairs[] = [ "author_id" => $user_id, "size" => $slot["size"], "sizes" => $slot["sizes"] ];
                }
            }
        }
        $this->schedule( $pairs );
    }

    public function template_changed( $meta_id, int $post_id, string $key ): void {
        if ( "_elementor_data" === $key ) {
            $this->document_changed( $post_id );
        }
    }

    /** Only this saved document is parsed; no post or user inventory is loaded. */
    public function document_changed( int $post_id ): void {
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post ) {
            return;
        }
        $slots = [];
        $this->collect_slots( $post->post_content, $slots );
        $data = get_post_meta( $post_id, "_elementor_data", true );
        $this->collect_slots( is_string( $data ) ? json_decode( $data, true ) : $data, $slots );
        $authors = "elementor_library" === $post->post_type ? [] : $this->post_authors( $post_id );
        $index = (array) get_option( self::SLOTS_OPTION, [] );
        if ( $slots ) {
            $index[ $post_id ] = [ "authors" => $authors, "slots" => array_values( $slots ) ];
        } else {
            unset( $index[ $post_id ] );
        }
        update_option( self::SLOTS_OPTION, $index, false );
        $pairs = [];
        foreach ( $slots as $slot ) {
            $targets = $slot["user_id"] ? [ $slot["user_id"] ] : $authors;
            foreach ( $targets as $author_id ) {
                $pairs[] = [ "author_id" => $author_id, "size" => $slot["size"], "sizes" => $slot["sizes"] ];
            }
        }
        $this->schedule( $pairs );
    }

    private function post_authors( int $post_id ): array {
        $records = ( new AuthorAssignmentRepository() )->records_for_post( $post_id );
        return array_map( static fn( AuthorRecord $author ): int => $author->id(), $records );
    }

    /** One scheduled event carries the deduplicated author/slot batch as its argument. */
    private function schedule( array $pairs ): void {
        $unique = [];
        foreach ( $pairs as $pair ) {
            $unique[ wp_json_encode( $pair ) ] = $pair;
        }
        ksort( $unique );
        $args = [ array_values( $unique ) ];
        if ( $unique && ! wp_next_scheduled( self::HOOK, $args ) ) {
            wp_schedule_single_event( time(), self::HOOK, $args );
        }
    }

    /** Explicitly supplied batches can also regenerate existing photos during rollout. */
    public function prepare( array $pairs ): void {
        $resolver = new AuthorFieldResolver();
        foreach ( $pairs as $pair ) {
            $author_id = (int) $pair["author_id"];
            $attachment_id = $resolver->explicit_image_attachment_id( $author_id );
            $image = $attachment_id ? wp_get_attachment_image_src( $attachment_id, $pair["size"] ) : false;
            if ( is_array( $image ) && $image[0] === $resolver->image_url( $author_id, $pair["size"] ) ) {
                self::ensure_slot_copies( $attachment_id, $image, $pair["sizes"] );
            }
        }
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
                if ( ! in_array( $size, array_merge( get_intermediate_image_sizes(), [ "full" ] ), true ) ) {
                    $size = "thumbnail";
                }
                $slot = [ "size" => $size, "sizes" => trim( (string) $atts["sizes"] ), "user_id" => absint( $atts["user_id"] ?? 0 ) ];
                $slots[ wp_json_encode( $slot ) ] = $slot;
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
