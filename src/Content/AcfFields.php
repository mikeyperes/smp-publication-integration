<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Dependencies;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AcfFields {
    public function register(): void {
        add_action( 'acf/init', [ $this, 'register_fields' ] );
    }

    public function register_fields(): void {
        if ( ! Dependencies::acf_active() ) {
            return;
        }

        $this->register_publication_profile_fields();
        $this->register_user_binding_fields();
    }

    private function register_publication_profile_fields(): void {
        acf_add_local_field_group(
            [
                'key'    => 'group_smpi_publication_profile',
                'title'  => 'SMP Publication Profile',
                'fields' => [
                    [
                        'key'           => 'field_smpi_publication_user',
                        'label'         => 'Publication User',
                        'name'          => 'smpi_publication_user',
                        'type'          => 'user',
                        'instructions'  => 'Bind this publication profile to the WordPress user responsible for managing it.',
                        'role'          => '',
                        'return_format' => 'id',
                        'multiple'      => 0,
                    ],
                    [
                        'key'           => 'field_smpi_founders',
                        'label'         => 'Founders',
                        'name'          => 'smpi_founders',
                        'type'          => 'relationship',
                        'instructions'  => 'Bind one or more founder person profiles from the SFPF/profile system.',
                        'post_type'     => [ 'profile' ],
                        'taxonomy'      => '',
                        'filters'       => [ 'search', 'post_type' ],
                        'elements'      => [ 'featured_image' ],
                        'return_format' => 'id',
                    ],
                    [
                        'key'          => 'field_smpi_mission_statement_override',
                        'label'        => 'Mission Statement Fallback',
                        'name'         => 'smpi_mission_statement_override',
                        'type'         => 'textarea',
                        'instructions' => 'Shortcodes read existing imported fields such as mission_statement first. Use this only when no imported mission statement exists.',
                        'rows'         => 4,
                        'new_lines'    => 'wpautop',
                    ],
                    [
                        'key'          => 'field_smpi_publication_summary',
                        'label'        => 'Publication Summary Fallback',
                        'name'         => 'smpi_publication_summary',
                        'type'         => 'textarea',
                        'instructions' => 'Fallback summary for publication cards and profile shortcodes.',
                        'rows'         => 4,
                        'new_lines'    => 'wpautop',
                    ],
                    [
                        'key'          => 'field_smpi_publication_website',
                        'label'        => 'Publication Website Fallback',
                        'name'         => 'smpi_publication_website',
                        'type'         => 'url',
                        'instructions' => 'Fallback URL. Existing imported website/url fields are preferred by shortcodes.',
                    ],
                    [
                        'key'           => 'field_smpi_publication_logo',
                        'label'         => 'Publication Logo Fallback',
                        'name'          => 'smpi_publication_logo',
                        'type'          => 'image',
                        'instructions'  => 'Fallback logo when no imported logo/publication_logo field exists.',
                        'return_format' => 'array',
                        'preview_size'  => 'medium',
                        'library'       => 'all',
                    ],
                    [
                        'key'          => 'field_smpi_imported_source_url',
                        'label'        => 'Imported Source URL',
                        'name'         => 'smpi_imported_source_url',
                        'type'         => 'url',
                        'instructions' => 'Reference-only source URL for imported publication records.',
                    ],
                    [
                        'key'          => 'field_smpi_schema_markup',
                        'label'        => 'Publication Schema Markup',
                        'name'         => 'smpi_schema_markup',
                        'type'         => 'textarea',
                        'instructions' => 'Generated JSON-LD for this publication profile. Refresh from the Schema tab.',
                        'rows'         => 10,
                        'readonly'     => 1,
                    ],
                ],
                'location' => [
                    [
                        [
                            'param'    => 'post_type',
                            'operator' => '==',
                            'value'    => PublicationPostType::POST_TYPE,
                        ],
                    ],
                ],
                'position' => 'normal',
                'style'    => 'default',
            ]
        );
    }

    private function register_user_binding_fields(): void {
        acf_add_local_field_group(
            [
                'key'    => 'group_smpi_publication_user_bindings',
                'title'  => 'SMP Publication Bindings',
                'fields' => [
                    [
                        'key'           => 'field_smpi_primary_publication',
                        'label'         => 'Primary Publication',
                        'name'          => 'smpi_primary_publication',
                        'type'          => 'post_object',
                        'instructions'  => 'Primary publication profile bound to this user.',
                        'post_type'     => [ PublicationPostType::POST_TYPE ],
                        'return_format' => 'id',
                        'ui'            => 1,
                    ],
                    [
                        'key'           => 'field_smpi_managed_publications',
                        'label'         => 'Managed Publications',
                        'name'          => 'smpi_managed_publications',
                        'type'          => 'relationship',
                        'instructions'  => 'Additional publication profiles this user can manage.',
                        'post_type'     => [ PublicationPostType::POST_TYPE ],
                        'filters'       => [ 'search' ],
                        'return_format' => 'id',
                    ],
                ],
                'location' => [
                    [
                        [
                            'param'    => 'user_form',
                            'operator' => '==',
                            'value'    => 'all',
                        ],
                    ],
                ],
                'position' => 'normal',
                'style'    => 'default',
            ]
        );
    }
}
