<?php

declare( strict_types=1 );

namespace SMP\PublicationIntegration\PublicationManifest;

use smp_publication_integration\Content\ArticleTypes;
use smp_publication_integration\Content\PublicationContentTypes;
use smp_publication_integration\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class WordPressCollector {
    private TaxonomyPolicy $policy;

    public function __construct( ?TaxonomyPolicy $policy = null ) {
        $this->policy = $policy ?? new TaxonomyPolicy();
    }

    /** @return array<string,mixed> */
    public function publication_identity(): array {
        $custom_logo_id = (int) get_theme_mod( 'custom_logo', 0 );
        $custom_logo    = $custom_logo_id > 0 ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';

        return [
            'name'        => (string) get_bloginfo( 'name' ),
            'description' => (string) get_bloginfo( 'description' ),
            'url'         => (string) home_url( '/' ),
            'language'    => (string) get_bloginfo( 'language' ),
            'locale'      => (string) get_locale(),
            'timezone'    => (string) wp_timezone_string(),
            'site_icon'   => (string) get_site_icon_url( 512 ),
            'logo'        => is_string( $custom_logo ) ? $custom_logo : '',
            'visibility'  => (int) get_option( 'blog_public', 1 ) === 1 ? 'public' : 'discourage_search_engines',
        ];
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    public function taxonomy_catalog(): array {
        return [
            'categories' => $this->category_catalog(),
            'tags'       => $this->tag_catalog(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function recent_content(): array {
        $post_types = $this->article_post_types();
        $limit      = 50;
        if ( function_exists( 'apply_filters' ) ) {
            $limit = (int) apply_filters( 'smpi_publication_manifest_recent_content_limit', $limit );
        }
        $limit = max( 10, min( 100, $limit ) );

        $posts = get_posts(
            [
                'post_type'              => $post_types,
                'post_status'            => 'publish',
                'posts_per_page'         => $limit,
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'ignore_sticky_posts'    => true,
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => true,
            ]
        );

        $records = [];
        foreach ( $posts as $post ) {
            if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
                continue;
            }

            $categories = [];
            foreach ( get_the_category( $post->ID ) as $term ) {
                $categories[] = [
                    'id'   => (int) $term->term_id,
                    'name' => (string) $term->name,
                    'slug' => (string) $term->slug,
                ];
            }

            $tags = [];
            $post_tags = wp_get_post_terms( $post->ID, 'post_tag' );
            if ( ! is_wp_error( $post_tags ) ) {
                foreach ( $post_tags as $term ) {
                    $tags[] = [
                        'id'   => (int) $term->term_id,
                        'name' => (string) $term->name,
                        'slug' => (string) $term->slug,
                    ];
                }
            }

            $records[] = [
                'id'             => (int) $post->ID,
                'post_type'      => (string) $post->post_type,
                'title'          => (string) get_the_title( $post ),
                'url'            => (string) get_permalink( $post ),
                'published_at'   => (string) get_the_date( DATE_ATOM, $post ),
                'modified_at'    => (string) get_the_modified_date( DATE_ATOM, $post ),
                'author'         => [
                    'id'   => (int) $post->post_author,
                    'name' => (string) get_the_author_meta( 'display_name', $post->post_author ),
                    'slug' => (string) get_the_author_meta( 'user_nicename', $post->post_author ),
                ],
                'categories'     => $categories,
                'tags'           => $tags,
                'word_count'     => $this->word_count( (string) $post->post_content ),
                'featured_image' => (string) ( get_the_post_thumbnail_url( $post, 'full' ) ?: '' ),
            ];
        }

        return $records;
    }

    /** @return array<int,array<string,mixed>> */
    public function public_authors(): array {
        $post_types = $this->article_post_types();
        $users      = get_users(
            [
                'has_published_posts' => $post_types,
                'orderby'             => 'display_name',
                'order'               => 'ASC',
                'fields'              => [ 'ID', 'display_name', 'user_nicename', 'description' ],
            ]
        );

        $authors = [];
        foreach ( $users as $user ) {
            $user_id = (int) ( $user->ID ?? 0 );
            if ( $user_id < 1 ) {
                continue;
            }
            $published_count = 0;
            foreach ( $post_types as $post_type ) {
                $published_count += (int) count_user_posts( $user_id, $post_type, true );
            }
            if ( $published_count < 1 ) {
                continue;
            }

            $authors[] = [
                'id'                   => $user_id,
                'name'                 => (string) ( $user->display_name ?? '' ),
                'slug'                 => (string) ( $user->user_nicename ?? '' ),
                'profile_url'          => (string) get_author_posts_url( $user_id, (string) ( $user->user_nicename ?? '' ) ),
                'public_bio'           => (string) ( $user->description ?? '' ),
                'published_post_count' => $published_count,
            ];
        }

        return $authors;
    }

    /** @return array<int,array<string,mixed>> */
    public function post_types(): array {
        $records = [];
        foreach ( $this->article_post_types() as $post_type ) {
            $object = get_post_type_object( $post_type );
            if ( ! is_object( $object ) || empty( $object->public ) ) {
                continue;
            }
            $supports = array_keys( get_all_post_type_supports( $post_type ) );
            sort( $supports );

            $taxonomies = $this->post_type_taxonomies( $post_type );

            $records[] = [
                'slug'        => $post_type,
                'label'       => (string) ( $object->labels->name ?? $object->label ?? $post_type ),
                'rest_enabled' => ! empty( $object->show_in_rest ),
                'rest_base'    => isset( $object->rest_base ) && '' !== (string) $object->rest_base ? (string) $object->rest_base : $post_type,
                'supports'    => $supports,
                'taxonomies'  => $taxonomies,
            ];
        }
        return $records;
    }

    /** @return array<string,mixed> */
    public function publishing_requirements(): array {
        return [
            'publish_statuses'        => [ 'draft', 'pending', 'future', 'publish' ],
            'featured_image_required' => Settings::bool( 'post_featured_image_required' ),
            'title_required'          => false,
            'content_required'        => false,
            'word_count'              => [
                'minimum'  => null,
                'maximum'  => null,
                'enforced' => false,
                'note'     => 'Word-count policy is campaign-owned and is not enforced by WordPress.',
            ],
            'taxonomies'              => $this->registered_article_taxonomies(),
            'default_category_id'     => (int) get_option( 'default_category', 0 ),
        ];
    }

    /** @return array<string,mixed> */
    public function media_profile(): array {
        $sizes = [];
        foreach ( wp_get_registered_image_subsizes() as $name => $definition ) {
            $sizes[] = [
                'name'   => sanitize_key( (string) $name ),
                'width'  => (int) ( $definition['width'] ?? 0 ),
                'height' => (int) ( $definition['height'] ?? 0 ),
                'crop'   => (bool) ( $definition['crop'] ?? false ),
            ];
        }

        $mime_types = [];
        foreach ( get_allowed_mime_types() as $extension => $mime_type ) {
            if ( str_starts_with( (string) $mime_type, 'image/' ) ) {
                $mime_types[] = [ 'extensions' => (string) $extension, 'mime_type' => (string) $mime_type ];
            }
        }

        return [
            'featured_image_supported' => post_type_supports( 'post', 'thumbnail' ),
            'featured_image_required'  => Settings::bool( 'post_featured_image_required' ),
            'registered_sizes'         => $sizes,
            'accepted_image_types'     => $mime_types,
        ];
    }

    /** @return array<string,mixed> */
    public function seo_profile(): array {
        $provider = 'wordpress';
        if ( defined( 'RANK_MATH_VERSION' ) || function_exists( 'rank_math' ) ) {
            $provider = 'rank_math';
        } elseif ( defined( 'WPSEO_VERSION' ) ) {
            $provider = 'yoast';
        } elseif ( defined( 'AIOSEO_VERSION' ) ) {
            $provider = 'aioseo';
        }

        return [
            'provider'              => $provider,
            'pretty_permalinks'     => '' !== (string) get_option( 'permalink_structure', '' ),
            'canonical_supported'   => function_exists( 'wp_get_canonical_url' ),
            'native_sitemap_url'    => (string) home_url( '/wp-sitemap.xml' ),
            'seo_sitemap_url'       => (string) home_url( '/sitemap_index.xml' ),
            'search_engine_visible' => 1 === (int) get_option( 'blog_public', 1 ),
        ];
    }

    /** @return array<string,mixed> */
    public function schema_profile(): array {
        $profile = [
            'format'           => 'JSON-LD',
            'article_types'    => [ 'Article', 'NewsArticle', 'BlogPosting' ],
            'entity_types'     => [ 'Organization', 'Person', 'ImageObject' ],
            'supporting_types' => [ 'BreadcrumbList', 'FAQPage', 'WebSite', 'WebPage' ],
        ];
        if ( in_array( ArticleTypes::TAXONOMY, $this->registered_article_taxonomies(), true ) ) {
            $profile['article_type_taxonomy'] = ArticleTypes::TAXONOMY;
        }

        return $profile;
    }

    /** @return array<string,mixed> */
    public function delivery_capabilities(): array {
        $taxonomies = $this->registered_article_taxonomies();

        return [
            'rest_api'         => true,
            'post_types'       => array_column( $this->post_types(), 'slug' ),
            'taxonomies'       => $taxonomies,
            'categories'       => in_array( 'category', $taxonomies, true ),
            'tags'             => in_array( 'post_tag', $taxonomies, true ),
            'authors'          => true,
            'featured_media'   => post_type_supports( 'post', 'thumbnail' ),
            'scheduled_posts'  => true,
            'revisions'        => post_type_supports( 'post', 'revisions' ),
            'public_manifest'  => [
                'namespace' => 'smpi/v1',
                'route'     => '/publication-manifest',
                'version'   => 1,
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function category_catalog(): array {
        $terms = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false, 'orderby' => 'term_id', 'order' => 'ASC' ] );
        if ( is_wp_error( $terms ) ) {
            return [];
        }
        return array_map( fn( object $term ): array => $this->policy->category_record( $term ), $terms );
    }

    /** @return array<int,array<string,mixed>> */
    private function tag_catalog(): array {
        $terms = get_terms( [ 'taxonomy' => 'post_tag', 'hide_empty' => false, 'orderby' => 'term_id', 'order' => 'ASC' ] );
        if ( is_wp_error( $terms ) ) {
            return [];
        }

        $records = [];
        foreach ( $terms as $term ) {
            $link = get_term_link( $term );
            $records[] = [
                'id'                   => (int) $term->term_id,
                'name'                 => (string) $term->name,
                'slug'                 => (string) $term->slug,
                'url'                  => is_wp_error( $link ) ? '' : (string) $link,
                'published_post_count' => (int) $term->count,
            ];
        }
        return $records;
    }

    /** @return array<int,string> */
    private function article_post_types(): array {
        return PublicationContentTypes::active_article_post_types();
    }

    /** @return array<int,string> */
    private function registered_article_taxonomies(): array {
        $taxonomies = [];
        foreach ( $this->article_post_types() as $post_type ) {
            $taxonomies = array_merge( $taxonomies, $this->post_type_taxonomies( $post_type ) );
        }
        $taxonomies = array_values( array_unique( $taxonomies ) );
        sort( $taxonomies );

        return $taxonomies;
    }

    /** @return array<int,string> */
    private function post_type_taxonomies( string $post_type ): array {
        $taxonomies = [];
        foreach ( (array) get_object_taxonomies( $post_type, 'names' ) as $taxonomy ) {
            $taxonomy = sanitize_key( (string) $taxonomy );
            if ( '' !== $taxonomy && taxonomy_exists( $taxonomy ) ) {
                $taxonomies[] = $taxonomy;
            }
        }
        $taxonomies = array_values( array_unique( $taxonomies ) );
        sort( $taxonomies );

        return $taxonomies;
    }

    private function word_count( string $content ): int {
        $text  = trim( wp_strip_all_tags( strip_shortcodes( $content ) ) );
        $words = '' === $text ? [] : preg_split( '/\s+/u', $text );
        return is_array( $words ) ? count( $words ) : 0;
    }
}
