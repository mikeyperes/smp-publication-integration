<?php

namespace smp_publication_integration\Admin\Navigation;

use Hexa\PluginCore\WpAdminTabs\TabDefinition;
use Hexa\PluginCore\WpAdminTabs\TabRegistry;

final class AdminNavigation {
    private const FLAT_TABS = [
        'overview'             => 'Dashboard',
        'quick_run'            => 'Quick Start',
        'publication_options'  => 'Publication Settings',
        'pages'                => 'Publication Pages',
        'menu'                 => 'Menus',
        'founders_list'        => 'Founders',
        'executive_leadership' => 'Executive Leadership',
        'staff'                => 'Staff',
        'contributors'         => 'Contributors',
        'multiple_authors'     => 'Authors',
        'custom_post_types'    => 'Custom Post Types',
        'features'             => 'Article Design',
        'custom_fields'        => 'Post Fields',
        'content_generation'   => 'Content Generation',
        'snippets'             => 'Publishing Rules',
        'post_hygiene'         => 'Post HTML Cleanup',
        'schema'               => 'Schema Settings',
        'reports'              => 'Schema Tests',
        'article_cleanup'      => 'Article & Media Cleanup',
        'plugins'              => 'Plugins',
        'shortcodes'           => 'Shortcodes',
        'hexa_core'            => 'Hexa WP Core',
    ];

    private const AREAS = [
        'overview'      => 'Overview',
        'publication'   => 'Publication',
        'people'        => 'People',
        'articles'      => 'Articles',
        'search_schema' => 'Search & Schema',
        'maintenance'   => 'Maintenance',
    ];

    private const SECTIONS = [
        'overview' => [
            'overview'  => 'Dashboard',
            'quick_run' => 'Quick Start',
        ],
        'publication' => [
            'publication_options' => 'Publication Settings',
            'pages'               => 'Publication Pages',
            'menu'                => 'Menus',
        ],
        'people' => [
            'founders_list'        => 'Founders',
            'executive_leadership' => 'Executive Leadership',
            'staff'                => 'Staff',
            'contributors'         => 'Contributors',
            'multiple_authors'     => 'Authors',
        ],
        'articles' => [
            'custom_post_types'  => 'Custom Post Types',
            'features'           => 'Article Design',
            'custom_fields'      => 'Post Fields',
            'content_generation' => 'Content Generation',
            'snippets'           => 'Publishing Rules',
            'post_hygiene'       => 'Post HTML Cleanup',
        ],
        'search_schema' => [
            'schema'  => 'Schema Settings',
            'reports' => 'Schema Tests',
        ],
        'maintenance' => [
            'article_cleanup' => 'Article & Media Cleanup',
            'plugins'         => 'Plugins',
            'shortcodes'      => 'Shortcodes',
            'hexa_core'       => 'Hexa WP Core',
        ],
    ];

    /** Removed or merged tabs (old links keep working): old id => where it lives now. */
    private const MOVED = [
        'founder_profiles'  => 'founders_list',
        'founding_date'     => 'pages',
        'integrations'      => 'plugins',
        'verified_profiles' => 'plugins',
        'profiles'          => 'publication_options',
        'brand'             => 'publication_options',
        'optimization'      => 'plugins',
        'ui_cleanup'        => 'overview',
    ];

    /** Old group ids => new group ids. */
    private const MOVED_AREAS = [
        'editorial'       => 'articles',
        'structured_data' => 'search_schema',
        'operations'      => 'maintenance',
        'advanced'        => 'maintenance',
    ];

    /**
     * @return array<string,string>
     */
    public function tabs(): array {
        $tabs = self::FLAT_TABS;

        foreach ( $this->legacy_tab_labels() as $id => $label ) {
            $id = sanitize_key( (string) $id );
            if ( '' !== $id && ! isset( $tabs[ $id ] ) && ! $this->known_section( $id ) && ! isset( self::MOVED[ $id ] ) ) {
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

        if ( 'maintenance' === $area ) {
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

        $tab     = self::MOVED_AREAS[ $tab ] ?? ( self::MOVED[ $tab ] ?? $tab );
        $section = self::MOVED[ $section ] ?? $section;

        if ( 'maintenance' === $tab && 'quick_run' === $section ) {
            return new AdminRoute( 'overview', 'quick_run' );
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
            return new AdminRoute( 'maintenance', $tab );
        }

        return new AdminRoute( 'overview', 'overview' );
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
