<?php

namespace smp_publication_integration\Admin\Navigation;

final class AdminNavigation {
    private const AREAS = [
        'overview'        => 'Overview',
        'publication'     => 'Publication',
        'editorial'       => 'Editorial',
        'structured_data' => 'Structured Data',
        'operations'      => 'Operations',
        'advanced'        => 'Advanced',
    ];

    private const SECTIONS = [
        'overview' => [
            'overview' => 'Overview',
        ],
        'publication' => [
            'publication_options' => 'Publication Options',
            'profiles'            => 'Publication Profiles',
            'brand'               => 'Brand',
            'pages'               => 'Pages',
            'menu'                => 'Menu',
        ],
        'editorial' => [
            'features'           => 'Features',
            'custom_fields'      => 'Custom Fields',
            'multiple_authors'   => 'Multiple Authors',
            'content_generation' => 'Content Generation',
            'post_hygiene'       => 'Post Hygiene',
        ],
        'structured_data' => [
            'schema'            => 'Schema',
            'reports'           => 'Reports',
            'verified_profiles' => 'Verified Profiles',
        ],
        'operations' => [
            'quick_run'       => 'Quick Start',
            'article_cleanup' => 'Article Cleanup',
            'optimization'    => 'Optimization',
            'plugins'         => 'Plugins',
            'integrations'    => 'Integrations',
        ],
        'advanced' => [
            'snippets'   => 'Snippets',
            'shortcodes' => 'Shortcodes',
            'ui_cleanup' => 'UI Cleanup',
            'hexa_core'  => 'Hexa WP Core',
        ],
    ];

    /**
     * @return array<string,string>
     */
    public function areas(): array {
        return apply_filters( 'smpi_dashboard_areas', self::AREAS );
    }

    /**
     * @return array<string,string>
     */
    public function sections( string $area ): array {
        $area     = sanitize_key( $area );
        $sections = self::SECTIONS[ $area ] ?? [];
        $legacy   = $this->legacy_tab_labels();

        foreach ( $sections as $id => $label ) {
            if ( isset( $legacy[ $id ] ) ) {
                $sections[ $id ] = (string) $legacy[ $id ];
            }
        }

        if ( 'advanced' === $area ) {
            foreach ( $legacy as $id => $label ) {
                if ( ! $this->known_section( (string) $id ) ) {
                    $sections[ sanitize_key( (string) $id ) ] = (string) $label;
                }
            }
        }

        return apply_filters( 'smpi_dashboard_area_sections', $sections, $area );
    }

    public function resolve( string $tab, string $section = '' ): AdminRoute {
        $tab     = sanitize_key( $tab );
        $section = sanitize_key( $section );
        $areas   = $this->areas();

        if ( isset( $areas[ $tab ] ) ) {
            $sections = $this->sections( $tab );
            if ( '' === $section || ! isset( $sections[ $section ] ) ) {
                $section = (string) array_key_first( $sections );
            }

            return new AdminRoute( $tab, $section );
        }

        foreach ( array_keys( self::AREAS ) as $area ) {
            if ( isset( $this->sections( $area )[ $tab ] ) ) {
                return new AdminRoute( $area, $tab );
            }
        }

        return new AdminRoute( 'overview', 'overview' );
    }

    public function section_url( string $page_url, string $area, string $section ): string {
        return add_query_arg(
            [
                'tab'     => sanitize_key( $area ),
                'section' => sanitize_key( $section ),
            ],
            $page_url
        );
    }

    private function known_section( string $section ): bool {
        foreach ( self::SECTIONS as $sections ) {
            if ( isset( $sections[ $section ] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,string>
     */
    private function legacy_tab_labels(): array {
        $tabs = [];
        foreach ( self::SECTIONS as $sections ) {
            $tabs = array_merge( $tabs, $sections );
        }

        return apply_filters( 'smpi_dashboard_tabs', $tabs );
    }
}
