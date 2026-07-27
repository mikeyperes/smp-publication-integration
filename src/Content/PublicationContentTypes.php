<?php

namespace smp_publication_integration\Content;

use Hexa\PluginCore\ContentTypes\ContentTypeRegistry;
use Hexa\PluginCore\FieldStructures\AcfFieldGroupRegistry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PublicationContentTypes {
    private static ?ContentTypeRegistry $content_types = null;
    private static ?AcfFieldGroupRegistry $acf_groups = null;

    public static function content_types(): ContentTypeRegistry {
        if ( self::$content_types instanceof ContentTypeRegistry ) {
            return self::$content_types;
        }

        self::$content_types = new ContentTypeRegistry(
            [
                'option_name'   => 'smpi_content_type_settings',
                'capability'    => 'manage_options',
                'ajax_action'   => 'smpi_save_content_type',
                'nonce_action'  => 'smpi_content_types',
                'nonce_field'   => 'nonce',
                'hook_priority' => 6,
            ]
        );

        self::$content_types
            ->add( self::blog_type( 'knowledge-base', 'Knowledge Base Article', 'Knowledge Base', 'knowledge-base', 'Structured reference and support articles maintained by the publication.', 'dashicons-welcome-learn-more', 24 ) )
            ->add( self::blog_type( 'resources', 'Resource', 'Resources', 'resources', 'Guides, downloads, research, and other reusable publication resources.', 'dashicons-media-document', 25 ) );

        return self::$content_types;
    }

    public static function acf_groups(): AcfFieldGroupRegistry {
        if ( self::$acf_groups instanceof AcfFieldGroupRegistry ) {
            return self::$acf_groups;
        }

        self::$acf_groups = new AcfFieldGroupRegistry(
            [
                'option_name'   => 'smpi_acf_structure_settings',
                'capability'    => 'manage_options',
                'ajax_action'   => 'smpi_save_acf_structure',
                'nonce_action'  => 'smpi_acf_structures',
                'nonce_field'   => 'nonce',
                'hook_priority' => 7,
            ]
        );

        self::$acf_groups
            ->add(
                [
                    'id'              => 'publication-profile',
                    'label'           => 'Publication Profile Fields',
                    'description'     => 'Publication identity, policies, contact details, brand assets, founders, and generated schema fields.',
                    'group_key'       => 'group_smpi_publication_profile',
                    'enabled_default' => true,
                    'definition'      => [ AcfFields::class, 'publication_profile_group' ],
                    'location'        => 'SMP Publication settings',
                    'fields'          => [ 'Publication identity', 'Publication author', 'Brand assets', 'Editorial policies', 'Founders', 'Contact information', 'Schema metadata' ],
                    'dependencies'    => [ 'Advanced Custom Fields Pro' ],
                ]
            )
            ->add(
                [
                    'id'              => 'article-fields',
                    'label'           => 'Article Editor Fields',
                    'description'     => 'Shared author, summary, and FAQ fields for posts, press releases, Knowledge Base articles, and Resources.',
                    'group_key'       => 'group_64a7290b61191',
                    'enabled_default' => true,
                    'definition'      => [ AcfFields::class, 'article_fields_group' ],
                    'location'        => implode( ', ', self::article_post_types() ) . ' editors',
                    'fields'          => [ 'Article Authors', 'Post Summary', 'FAQ Schema', 'Structured FAQs' ],
                    'dependencies'    => [ 'Advanced Custom Fields Pro', 'SMP article feature settings' ],
                ]
            );

        return self::$acf_groups;
    }

    /** @return array<int,string> */
    public static function article_post_types(): array {
        return [ 'post', 'press-release', 'knowledge-base', 'resources' ];
    }

    /** @return array<int,string> */
    public static function active_article_post_types(): array {
        return array_values(
            array_filter(
                self::article_post_types(),
                static fn( string $post_type ): bool => 'post' === $post_type || post_type_exists( $post_type )
            )
        );
    }

    /** @return array<string,mixed> */
    private static function blog_type( string $key, string $singular, string $plural, string $slug, string $description, string $icon, int $position ): array {
        return [
            'id'              => $key,
            'owner'           => 'SMP Publication Integration',
            'description'     => $description,
            'enabled_default' => false,
            'post_type'       => [
                'key'          => $key,
                'singular'     => $singular,
                'plural'       => $plural,
                'rewrite_slug' => $slug,
                'args'         => [
                    'public'             => true,
                    'publicly_queryable' => true,
                    'show_ui'            => true,
                    'show_in_menu'       => true,
                    'show_in_nav_menus'  => true,
                    'show_in_admin_bar'  => true,
                    'show_in_rest'       => true,
                    'menu_position'      => $position,
                    'menu_icon'          => $icon,
                    'capability_type'    => 'post',
                    'hierarchical'       => false,
                    'supports'           => [ 'title', 'author', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ],
                    'taxonomies'         => [ 'category', 'post_tag' ],
                    'has_archive'        => true,
                    'rewrite'            => [ 'with_front' => false ],
                    'query_var'          => true,
                    'delete_with_user'   => false,
                ],
            ],
        ];
    }
}
