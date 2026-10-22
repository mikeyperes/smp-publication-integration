<?php
namespace smp_publication_integration\Admin\Dashboard\Tabs;

use Hexa\PluginCore\CorePackageUpdates\CorePackagePanelRenderer;
use Hexa\PluginCore\PluginUpdates\UpdaterPanelRenderer;
use SMP\PublicationIntegration\Infrastructure\Updates;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * The Update Center tab: Core's plugin updater (Git Report: repository, URL,
 * branch, GitHub version against the installed one, update button) and Core's
 * package panel (bundled Hexa WP Core against Core on GitHub).
 */
final class UpdateCenterTab {
    public const TAB = "update-center";

    public function register(): void {
        add_filter( "smpi_render_dashboard_tab", [ $this, "render_tab" ], 10, 2 );
    }

    public function render_tab( bool $rendered, string $id ): bool {
        if ( self::TAB !== $id ) {
            return $rendered;
        }
        echo '<section class="smpi-section smpi-update-center">';
        ( new UpdaterPanelRenderer( Updates::plugin_config() ) )->render();
        ( new CorePackagePanelRenderer( Updates::core_config() ) )->render();
        echo '</section>';
        return true;
    }
}
