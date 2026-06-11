<?php
namespace smp_publication_integration\Content;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PublicationPostType {
    public const POST_TYPE = 'publication';

    public function register(): void {
        add_action( 'init', [ $this, 'register_post_type' ], 9 );
    }

    public function register_post_type(): void {
        if ( post_type_exists( self::POST_TYPE ) ) {
            return;
        }

        register_post_type(
            self::POST_TYPE,
            [
                'labels' => [
                    'name'               => 'Publications',
                    'singular_name'      => 'Publication',
                    'add_new_item'       => 'Add New Publication',
                    'edit_item'          => 'Edit Publication',
                    'new_item'           => 'New Publication',
                    'view_item'          => 'View Publication',
                    'search_items'       => 'Search Publications',
                    'not_found'          => 'No publications found',
                    'not_found_in_trash' => 'No publications found in Trash',
                ],
                'public'       => true,
                'show_ui'      => true,
                'show_in_rest' => true,
                'menu_icon'    => 'dashicons-media-document',
                'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ],
                'has_archive'  => true,
                'rewrite'      => [ 'slug' => 'publication' ],
            ]
        );
    }
}

