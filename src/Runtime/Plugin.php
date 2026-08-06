<?php

declare( strict_types=1 );

namespace SMP\PublicationIntegration\Runtime;

use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CoreContracts\RegisterMethodModule;
use Hexa\PluginCore\CorePackageUpdates\CorePackageAjaxController;
use Hexa\PluginCore\CoreRuntime\PluginContext;
use Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater;
use Hexa\PluginCore\PluginUpdates\UpdaterAjaxController;
use Hexa\PluginCore\WpAdminTabs\CoreTabConfig;
use Hexa\PluginCore\WpAdminTabs\CoreTabModule;
use SMP\PublicationIntegration\Infrastructure\Updates;
use smp_publication_integration\Admin;
use smp_publication_integration\Config;
use smp_publication_integration\Content;
use smp_publication_integration\Settings\SettingsMigrations;
use smp_publication_integration\Support\Dependencies;

defined( 'ABSPATH' ) || exit;

final class Plugin {
    private static ?PluginContext $context = null;
    private static ?CoreBootstrap $bootstrap = null;
    private static bool $booted = false;

    public function boot(): void {
        if ( self::$booted ) {
            return;
        }

        $context   = $this->context();
        $bootstrap = new CoreBootstrap( $context );
        $bootstrap->add_module( new GitHubPluginUpdater( Updates::plugin_config() ) );

        $missing = Dependencies::missing_required_dependencies();
        if ( ! empty( $missing ) ) {
            add_action( 'admin_notices', [ Dependencies::class, 'render_missing_required_notice' ] );
            $bootstrap->boot();
            self::$bootstrap = $bootstrap;
            self::$booted    = true;
            return;
        }

        $bootstrap
            ->add_module( Content\PublicationContentTypes::content_types() )
            ->add_module( Content\PublicationContentTypes::acf_groups() )
            ->add_module( Content\PublicationTaxonomies::registry() );

        foreach ( $this->content_modules() as $module ) {
            $bootstrap->add_module( new RegisterMethodModule( $module ) );
        }

        if ( $this->is_admin_runtime() ) {
            $bootstrap
                ->add_module( new UpdaterAjaxController( Updates::plugin_config() ) )
                ->add_module( new CorePackageAjaxController( Updates::core_config() ) )
                ->add_module( $this->core_tab_module() );

            foreach ( $this->admin_modules() as $module ) {
                $bootstrap->add_module( new RegisterMethodModule( $module ) );
            }
        }

        $bootstrap->boot();

        self::$bootstrap = $bootstrap;
        self::$booted    = true;

        do_action( 'smpi_core_booted', $context, $bootstrap );
    }

    public function context(): PluginContext {
        if ( self::$context instanceof PluginContext ) {
            return self::$context;
        }

        $plugin_file = dirname( __DIR__, 2 ) . '/smp-publication-integration.php';

        self::$context = new PluginContext(
            [
                'slug'        => Config::$plugin_slug,
                'basename'    => Config::plugin_basename(),
                'version'     => Config::VERSION,
                'path'        => dirname( __DIR__, 2 ) . '/',
                'url'         => plugin_dir_url( $plugin_file ),
                'github_repo' => Config::$github_repo,
                'admin_page'  => Config::$settings_page_slug,
                'capability'  => Config::$settings_page_capability,
            ]
        );

        return self::$context;
    }

    public function bootstrap(): ?CoreBootstrap {
        return self::$bootstrap;
    }

    /** @return array<int,object> */
    private function content_modules(): array {
        return [
            new SettingsMigrations(),
            new Content\AcfFields(),
            new Content\Shortcodes(),
            new Content\MultiAuthors(),
            new Content\AuthorShortcodes(),
            new Content\AuthorSocialIcons(),
            new Content\AuthorListings(),
            new Content\Schema(),
            new Content\ArticleTypes(),
            new Content\Visibility(),
            new Content\PostListDefaults(),
            new Content\PostTime(),
            new Content\EstimatedReadTime(),
            new Content\ElementorCssCacheBusting(),
            new Content\ElementorFrontendCompatibility(),
            new Content\ElementorPrimaryCategory(),
            new Content\MuckRackVerification(),
            new Content\AuthorSocialCleanup(),
            new Content\Breadcrumbs(),
            new Content\PublicDomRuntime(),
            new Content\TableOfContents(),
            new Content\InlinePhotoTreatments(),
            new Content\FeaturedImageCaptions(),
            new Content\ArticleStyles(),
            new Content\PostSummaryPlacement(),
            new Content\PostFaqPlacement(),
            new Content\PostHygiene(),
            new Content\ContentGeneration(),
            new Content\GoingLiveChecklist(),
            new Content\FeaturedImageRequirements(),
            new Content\DebugEndpoint(),
        ];
    }

    /** @return array<int,object> */
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

    private function is_admin_runtime(): bool {
        return is_admin() || wp_doing_ajax();
    }
}
