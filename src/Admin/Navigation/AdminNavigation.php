<?php

namespace smp_publication_integration\Admin\Navigation;

use Hexa\PluginCore\WpAdminTabs\TabDefinition;
use Hexa\PluginCore\WpAdminTabs\TabRegistry;

final class AdminNavigation {
    private const FLAT_TABS = [
        'overview'            => 'Dashboard',
        'quick_run'           => 'Quick Start',
        'publication_options' => 'Publication Settings',
        'profiles'            => 'Publication Fields',
        'brand'               => 'Brand Settings',
        'pages'               => 'Pages',
        'menu'                => 'Menus',
        'features'            => 'Article Design',
        'custom_fields'       => 'Post Fields',
        'multiple_authors'    => 'Authors',
        'content_generation'  => 'Content Generation',
        'snippets'            => 'Publishing Rules',
        'post_hygiene'        => 'Post HTML Cleanup',
        'schema'              => 'Schema Settings',
        'reports'             => 'Schema Tests',
        'verified_profiles'   => 'Verified Profiles',
        'article_cleanup'     => 'Article & Media Cleanup',
        'optimization'        => 'Database Optimization',
        'plugins'             => 'Plugins',
        'integrations'        => 'Integrations',
        'ui_cleanup'          => 'WordPress Admin',
        'shortcodes'          => 'Shortcodes',
        'hexa_core'           => 'Hexa WP Core',
    ];

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
    public function tabs(): array {
        $tabs = self::FLAT_TABS;

        foreach ( $this->legacy_tab_labels() as $id => $label ) {
            $id = sanitize_key( (string) $id );
            if ( '' !== $id && ! isset( $tabs[ $id ] ) && ! $this->known_section( $id ) ) {
                $tabs[ $id ] = (string) $label;
            }
        }

        return apply_filters( 'smpi_dashboard_flat_tabs', $tabs );
    }

    /**
     * Ordered tab groups for the sidebar navigation. Built from the existing
     * area/section map so the rail stays in sync with the flat tab list.
     *
     * @return array<int,array{label:string,tabs:array<int,string>}>
     */
    public function groups(): array {
        $tabs     = $this->tabs();
        $areas    = $this->areas();
        $assigned = [];
        $groups   = [];

        foreach ( $areas as $area => $area_label ) {
            $group_tabs = [];
            foreach ( array_keys( $this->sections( (string) $area ) ) as $id ) {
                $id = sanitize_key( (string) $id );
                if ( '' !== $id && isset( $tabs[ $id ] ) && ! isset( $assigned[ $id ] ) ) {
                    $group_tabs[]    = $id;
                    $assigned[ $id ] = true;
                }
            }

            if ( [] !== $group_tabs ) {
                $groups[] = [ 'label' => (string) $area_label, 'tabs' => $group_tabs ];
            }
        }

        $leftover = [];
        foreach ( array_keys( $tabs ) as $id ) {
            $id = sanitize_key( (string) $id );
            if ( '' !== $id && ! isset( $assigned[ $id ] ) ) {
                $leftover[] = $id;
            }
        }

        if ( [] !== $leftover ) {
            $groups[] = [ 'label' => 'More', 'tabs' => $leftover ];
        }

        return apply_filters( 'smpi_dashboard_tab_groups', $groups );
    }

    public function registry( callable $renderer, ?string $capability = null ): TabRegistry {
        $registry = new TabRegistry();

        foreach ( $this->tabs() as $id => $label ) {
            $id = sanitize_key( (string) $id );
            if ( '' === $id ) {
                continue;
            }

            $registry->add(
                new TabDefinition(
                    $id,
                    (string) $label,
                    static function () use ( $renderer, $id ): void {
                        $renderer( $id );
                    },
                    $capability
                )
            );
        }

        return $registry;
    }

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

        if ( 'hexa-core' === $tab ) {
            $tab = 'hexa_core';
        }

        if ( 'hexa-core' === $section ) {
            $section = 'hexa_core';
        }

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

        if ( isset( $this->tabs()[ $tab ] ) ) {
            return new AdminRoute( 'advanced', $tab );
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
