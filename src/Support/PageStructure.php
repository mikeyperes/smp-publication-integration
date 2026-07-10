<?php
namespace smp_publication_integration\Support;

use Hexa\PluginCore\SiteStructure\PageStructureManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PageStructure {
    /**
     * @return array<string,string>
     */
    public static function ajax_actions(): array {
        return [
            'assign_page'              => 'smpi_site_assign_page',
            'create_page'              => 'smpi_site_create_page',
            'delete_page'              => 'smpi_site_delete_page',
            'create_navigation_menu'   => 'smpi_site_create_navigation_menu',
            'delete_navigation_menu'   => 'smpi_site_delete_navigation_menu',
            'create_menu_item'         => 'smpi_site_create_menu_item',
            'attach_page_to_menu_item' => 'smpi_site_attach_page_to_menu_item',
            'attach_menu_structure'    => 'smpi_site_attach_menu_structure',
            'menu_inventory'           => 'smpi_site_menu_inventory',
            'save_template'            => 'smpi_site_save_template',
            'apply_template'           => 'smpi_site_apply_template',
            'page_details'             => 'smpi_site_page_details',
            'page_workspace'           => 'smpi_site_page_workspace',
            'update_page_slug'         => 'smpi_site_update_page_slug',
        ];
    }

    public static function manager( bool $include_hws_owned_pages = false ): PageStructureManager {
        return new PageStructureManager(
            [
                'pages'                => self::page_definitions( $include_hws_owned_pages ),
                'menu_structures'      => self::menu_structures(),
                'managed_meta_key'     => '_smpi_managed_page',
                'managed_key_meta_key' => '_smpi_page_key',
                'readonly_page_keys'   => $include_hws_owned_pages ? self::hws_owned_page_keys() : [],
                'created_page_status'  => 'publish',
                'select_post_statuses' => [ 'publish', 'draft', 'private', 'pending' ],
                'assignment_statuses'  => [ 'publish', 'draft', 'private', 'pending' ],
                'reuse_existing_pages' => true,
                'default_templates'    => Settings::default_page_templates(),
                'assignment_getter'    => [ self::class, 'assigned_page_id' ],
                'assignment_saver'     => [ self::class, 'save_assignment' ],
                'assignment_deleter'   => [ self::class, 'delete_assignment' ],
                'template_getter'      => [ self::class, 'stored_template' ],
                'template_saver'       => [ self::class, 'save_template' ],
                'page_detail_renderer' => [ self::class, 'page_detail_html' ],
                'logger'               => [ Settings::class, 'log' ],
                'menu_guess_terms'     => [
                    'header' => [ 'header', 'main', 'primary', 'top' ],
                    'footer' => [ 'footer', 'bottom' ],
                    'legal'  => [ 'legal', 'sub-footer', 'sub footer', 'subfooter', 'terms', 'privacy' ],
                    'policy' => [ 'policy', 'policies', 'editorial' ],
                    'team'   => [ 'team', 'about', 'staff' ],
                ],
            ]
        );
    }

    public static function menu_manager(): PageStructureManager {
        return self::manager( true );
    }

    public static function hws_owned_page_keys(): array {
        return Settings::hws_owned_page_keys();
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function page_definitions( bool $include_hws_owned_pages = false ): array {
        $pages = [];
        foreach ( Settings::page_types() as $key => $config ) {
            if ( ! $include_hws_owned_pages && in_array( (string) $key, self::hws_owned_page_keys(), true ) ) {
                continue;
            }
            $title = (string) ( $config['label'] ?? $key );
            $pages[ (string) $key ] = [
                'title'       => $title,
                'slug'        => Settings::page_slug( (string) $key ),
                'description' => (string) ( $config['description'] ?? '' ),
                'template'    => ! empty( $config['template'] ),
            ];
        }

        return $pages;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function menu_structures(): array {
        $legal = [ 'terms', 'privacy', 'dmca', 'accessibility' ];
        $editorial_pages = array_values(
            array_filter(
                array_keys( Settings::page_types() ),
                static fn( string $key ): bool => ! in_array( $key, $legal, true )
            )
        );

        return [
            'legal' => [
                'title'       => 'Legal',
                'description' => 'Legal and compliance pages from the publication page set.',
                'page_keys'   => $legal,
            ],
            'editorial_pages' => [
                'title'       => 'Editorial pages',
                'description' => 'All non-legal publication pages from the Pages tab.',
                'page_keys'   => $editorial_pages,
            ],
        ];
    }

    public static function assigned_page_id( string $page_key ): int {
        return Settings::page_assignment_id( $page_key );
    }

    public static function save_assignment( string $page_key, int $page_id ): void {
        if ( ! isset( Settings::page_types()[ $page_key ] ) ) {
            return;
        }

        $settings = Settings::all();
        $settings['page_assignments'][ $page_key ] = max( 0, absint( $page_id ) );
        if ( ! isset( $settings['page_templates'][ $page_key ] ) ) {
            $settings['page_templates'][ $page_key ] = self::stored_template( $page_key );
        }
        update_option( Settings::OPTION, $settings, false );
        Settings::log( 'Page assignment updated: ' . $page_key );
    }

    public static function delete_assignment( string $page_key ): void {
        if ( ! isset( Settings::page_types()[ $page_key ] ) ) {
            return;
        }

        $settings = Settings::all();
        $settings['page_assignments'][ $page_key ] = 0;
        update_option( Settings::OPTION, $settings, false );
        Settings::log( 'Page assignment cleared: ' . $page_key );
    }

    public static function stored_template( string $page_key ): string {
        $settings = Settings::all();
        $stored = isset( $settings['page_templates'][ $page_key ] ) ? trim( (string) $settings['page_templates'][ $page_key ] ) : '';
        if ( '' !== $stored ) {
            return (string) $settings['page_templates'][ $page_key ];
        }

        $defaults = Settings::default_page_templates();
        return (string) ( $defaults[ $page_key ] ?? '' );
    }

    public static function save_template( string $page_key, string $template ): void {
        if ( ! isset( Settings::page_types()[ $page_key ] ) ) {
            return;
        }

        $settings = Settings::all();
        $settings['page_templates'][ $page_key ] = wp_kses_post( $template );
        update_option( Settings::OPTION, $settings, false );
        Settings::log( 'Page template updated: ' . $page_key );
    }

    public static function page_detail_html( int $page_id ): string {
        $post = get_post( $page_id );
        if ( ! $post || 'page' !== $post->post_type ) {
            return '';
        }

        $status = (string) $post->post_status;
        $status_obj = get_post_status_object( $status );
        $status_label = $status_obj ? (string) $status_obj->label : ucfirst( $status );
        $permalink = (string) Settings::page_slug_url( $page_id );
        $edit_url = (string) get_edit_post_link( $page_id, 'raw' );
        $date = get_the_date( 'M j, Y g:i a', $page_id );
        $modified = get_the_modified_date( 'M j, Y g:i a', $page_id );
        $author = get_the_author_meta( 'display_name', (int) $post->post_author );

        ob_start();
        ?>
        <div class="smpi-page-detail hpc-page-detail" data-page-id="<?php echo esc_attr( (string) $page_id ); ?>">
            <div class="smpi-page-detail-head">
                <div>
                    <h3><?php echo esc_html( get_the_title( $page_id ) ); ?></h3>
                    <p class="smpi-muted">Selected WordPress page for this publication requirement.</p>
                </div>
                <span class="smpi-pill smpi-pill--saved"><?php echo esc_html( $status_label ); ?></span>
            </div>
            <dl class="smpi-page-meta">
                <div><dt>ID</dt><dd><code>#<?php echo esc_html( (string) $page_id ); ?></code></dd></div>
                <div><dt>Status</dt><dd><?php echo esc_html( $status_label ); ?> <code><?php echo esc_html( $status ); ?></code></dd></div>
                <div><dt>Author</dt><dd><?php echo esc_html( $author ?: 'Unknown' ); ?></dd></div>
                <div><dt>Created</dt><dd><?php echo esc_html( $date ); ?></dd></div>
                <div><dt>Modified</dt><dd><?php echo esc_html( $modified ); ?></dd></div>
                <div class="smpi-page-meta-wide"><dt>Permalink</dt><dd><a href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $permalink ); ?></a></dd></div>
                <div class="smpi-page-meta-wide smpi-page-slug-row"><dt>Slug</dt><dd><input type="text" class="regular-text hpc-page-slug-input" value="<?php echo esc_attr( (string) $post->post_name ); ?>" aria-label="Page slug"><button type="button" class="button hpc-save-page-slug">Save Slug</button><span class="spinner"></span><span class="hpc-page-slug-status smpi-save-state"></span></dd></div>
            </dl>
            <p class="smpi-page-actions"><a class="button button-secondary" href="<?php echo esc_url( $edit_url ); ?>" target="_blank" rel="noopener noreferrer">Edit Page</a> <a class="button button-secondary" href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener noreferrer">View Page</a></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
