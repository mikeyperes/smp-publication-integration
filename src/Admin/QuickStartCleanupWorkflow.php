<?php

namespace smp_publication_integration\Admin;

use smp_publication_integration\Support\QuickStartFeatures;

final class QuickStartCleanupWorkflow {
    public static function render_assets(): void {
        $css_file = dirname( __DIR__, 2 ) . '/assets/admin/quick-start-cleanup.css';
        $js_file  = dirname( __DIR__, 2 ) . '/assets/admin/quick-start-cleanup.js';

        if ( is_readable( $css_file ) ) {
            echo '<style data-smpi-quick-cleanup-style>' . (string) file_get_contents( $css_file ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        if ( is_readable( $js_file ) ) {
            echo '<script data-smpi-quick-cleanup-script>' . (string) file_get_contents( $js_file ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        echo '<script>window.SmpiQuickStartCleanupWorkflow&&window.SmpiQuickStartCleanupWorkflow.init('
            . wp_json_encode( QuickStartFeatures::cleanup_workflow_config() )
            . ');</script>';
    }
}
