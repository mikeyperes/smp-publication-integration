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
        if ( "" === $sizes ) {
            return $attributes;
        }
        $attributes["sizes"] = $sizes;
        $attachment_id = $resolver->explicit_image_attachment_id( $author_id );
        $image = $attachment_id ? wp_get_attachment_image_src( $attachment_id, $size ) : false;
        if ( ! is_array( $image ) || $image[0] !== $url || $image[1] <= 0 || $image[2] <= 0 ) {
            return $attributes;
        }

        $attributes["width"] = (int) $image[1];
        $attributes["height"] = (int) $image[2];
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
}
