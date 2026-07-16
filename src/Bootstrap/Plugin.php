<?php

namespace smp_publication_integration\Bootstrap;

use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CorePackageUpdates\CorePackageAjaxController;
use Hexa\PluginCore\CoreRuntime\PluginContext;
use Hexa\PluginCore\PluginUpdates\UpdaterAjaxController;
use Hexa\PluginCore\WpAdminTabs\CoreTabConfig;
use Hexa\PluginCore\WpAdminTabs\CoreTabModule;
use smp_publication_integration\Admin;
use smp_publication_integration\Config;
use smp_publication_integration\Content;
use smp_publication_integration\Support\Dependencies;

final class Plugin {
    private bool $booted = false;

    public function boot(): void {
        if ( $this->booted ) {
            return;
        }

        $missing = Dependencies::missing_required_dependencies();
        if ( ! empty( $missing ) ) {
            add_action( 'admin_notices', [ Dependencies::class, 'render_missing_required_notice' ] );
            return;
        }

        $context = new PluginContext(
            [
                'slug'        => Config::$plugin_slug,
                'basename'    => Config::plugin_basename(),
                'version'     => Config::VERSION,
                'path'        => dirname( __DIR__, 2 ) . '/',
                'url'         => plugin_dir_url( dirname( __DIR__, 2 ) . '/smp-publication-integration.php' ),
                'github_repo' => Config::$github_repo,
                'admin_page'  => Config::$settings_page_slug,
                'capability'  => Config::$settings_page_capability,
            ]
        );

        $core = new CoreBootstrap( $context );
        foreach ( $this->content_modules() as $module ) {
            $core->add_module( new ModuleAdapter( $module ) );
        }

        if ( is_admin() || wp_doing_ajax() ) {
            $core->add_module( new UpdaterAjaxController( \smp_publication_integration\hexa_plugin_core_updater_config() ) );
            $core->add_module( new CorePackageAjaxController( \smp_publication_integration\hexa_plugin_core_package_config() ) );
            $core->add_module( $this->core_tab_module() );

            foreach ( $this->admin_modules() as $module ) {
                $core->add_module( new ModuleAdapter( $module ) );
            }
        }

        $core->boot();
        $this->booted = true;
    }

    /**
     * @return array<int,object>
     */
    private function content_modules(): array {
        return [
            new Content\AcfFields(),
            new Content\Shortcodes(),
            new Content\MultiAuthors(),
            new Content\AuthorShortcodes(),
            new Content\Schema(),
            new Content\ArticleTypes(),
            new Content\Visibility(),
            new Content\PostListDefaults(),
            new Content\PostTime(),
            new Content\EstimatedReadTime(),
            new Content\ElementorCssCacheBusting(),
            new Content\ElementorPrimaryCategory(),
            new Content\MuckRackVerification(),
            new Content\AuthorSocialCleanup(),
            new Content\Breadcrumbs(),
            new Content\TableOfContents(),
            new Content\InlinePhotoTreatments(),
            new Content\FeaturedImageCaptions(),
            new Content\ArticleStyles(),
            new Content\PostHygiene(),
            new Content\ContentGeneration(),
            new Content\GoingLiveChecklist(),
            new Content\FeaturedImageRequirements(),
            new Content\DebugEndpoint(),
        ];
    }

    /**
     * @return array<int,object>
     */
    private function admin_modules(): array {
        return [
            new Admin\UiCleanup(),
            new Admin\Ajax(),
            new Admin\Dashboard(),
        ];
    }

    private function core_tab_module(): CoreTabModule {
        $plugin_root = dirname( __DIR__, 2 );

        return new CoreTabModule(
            new CoreTabConfig(
                [
                    'tab_id'        => 'hexa_core',
                    'label'         => 'Hexa WP Core',
                    'tabs_filter'   => 'smpi_dashboard_tabs',
                    'render_filter' => 'smpi_render_dashboard_tab',
                    'capability'    => Config::$settings_page_capability,
                    'core_root'     => $plugin_root . '/lib/hexa-wordpress-plugin-core',
                    'readme_path'   => $plugin_root . '/lib/hexa-wordpress-plugin-core/README.md',
                    'library_path'  => $plugin_root . '/HEXA_PLUGIN_CORE_LIBRARY.md',
                ]
            )
        );
    }
}
