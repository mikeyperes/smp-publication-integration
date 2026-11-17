<?php
namespace smp_publication_integration\Authorship;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/** Responsive photo attributes shared by shortcodes and Elementor author rebinding. */
final class AuthorImage {
    public static function attributes( int $author_id, string $size, string $sizes ): array {
        $resolver = new AuthorFieldResolver();
        $url = $resolver->image_url( $author_id, $size );
        $attributes = [ "src" => $url ];
        if ( "" !== $sizes ) {
            $attributes["sizes"] = $sizes;
        }
        if ( "" === $sizes ) {
            return $attributes;
        }
        $attachment_id = $resolver->explicit_image_attachment_id( $author_id );
        $image = $attachment_id ? wp_get_attachment_image_src( $attachment_id, $size ) : false;
        if ( ! is_array( $image ) || $image[0] !== $url || $image[1] <= 0 || $image[2] <= 0 ) {
            return $attributes;
        }

        $attributes["width"] = (int) $image[1];
        $attributes["height"] = (int) $image[2];
        self::ensure_slot_copies( $attachment_id, $image, $sizes );
        $srcset = wp_get_attachment_image_srcset( $attachment_id, $size );
        $candidates = [];
        foreach ( explode( ",", (string) $srcset ) as $candidate ) {
            if ( preg_match( '/^\s*(\S+)\s+(\d+)w\s*$/', $candidate, $match ) && (int) $match[2] <= (int) $image[1] ) {
                $candidates[ (int) $match[2] ] = $match[1];
            }
        }
        if ( $candidates ) {
            ksort( $candidates, SORT_NUMERIC );
            $attributes["src"] = reset( $candidates );
            $attributes["srcset"] = implode( ", ", array_map(
                static fn( $width, $candidate ) => $candidate . " " . $width . "w",
                array_keys( $candidates ),
                array_values( $candidates )
            ) );
        }
        return $attributes;
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
