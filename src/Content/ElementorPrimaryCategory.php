<?php

namespace smp_publication_integration\Content;

use smp_publication_integration\Elementor\PrimaryCategoryTag;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ElementorPrimaryCategory {
    public const TAG_NAME = 'smpi-primary-category';
    public const TAG_GROUP = 'smpi-publication';

    public function register(): void {
        add_action( 'elementor/dynamic_tags/register', [ $this, 'register_dynamic_tag' ] );
    }

    public function register_dynamic_tag( object $dynamic_tags ): void {
        if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) || ! method_exists( $dynamic_tags, 'register' ) ) {
            return;
        }

        if ( method_exists( $dynamic_tags, 'register_group' ) ) {
            $dynamic_tags->register_group( self::TAG_GROUP, [ 'title' => 'SMP Publication' ] );
        }

        $dynamic_tags->register( new PrimaryCategoryTag() );
    }

    public static function term( int $post_id = 0 ): ?object {
        if ( ! Settings::bool( 'elementor_primary_category_enabled' ) ) {
            return null;
        }

        $post_id = $post_id > 0 ? $post_id : (int) get_the_ID();
        $terms   = get_the_terms( $post_id, 'category' );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return null;
        }

        $terms = array_values(
            array_filter(
                $terms,
                static fn( mixed $term ): bool => is_object( $term ) && isset( $term->term_id, $term->name )
            )
        );

        if ( Settings::bool( 'elementor_primary_category_exclude_default' ) ) {
            $default_category = absint( get_option( 'default_category', 0 ) );
            $terms = array_values(
                array_filter(
                    $terms,
                    static fn( object $term ): bool => (int) $term->term_id !== $default_category
                )
            );
        }

        if ( empty( $terms ) ) {
            return null;
        }

        foreach ( [ 'rank_math_primary_category', '_yoast_wpseo_primary_category' ] as $meta_key ) {
            $primary_id = absint( get_post_meta( $post_id, $meta_key, true ) );
            foreach ( $terms as $term ) {
                if ( $primary_id > 0 && (int) $term->term_id === $primary_id ) {
                    return $term;
                }
            }
        }

        return $terms[0];
    }
}
