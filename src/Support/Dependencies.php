<?php
namespace smp_publication_integration\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Dependencies {
    public static function hws_base_tools_active(): bool {
        return self::plugin_active( 'hws-base-tools/initialization.php' )
            || defined( 'HWS_BASE_TOOLS_VERSION' )
            || function_exists( 'hws_base_tools\\hws_get_structured_plugin' );
    }

    public static function acf_active(): bool {
        return function_exists( 'acf_add_local_field_group' ) || function_exists( 'acf' ) || class_exists( 'ACF' );
    }

    public static function sfpf_active(): bool {
        return self::plugin_active( 'smp-verified-profiles/initialization.php' )
            || post_type_exists( 'profile' )
            || function_exists( 'smp_verified_profiles\\get_verified_profile_shortcodes' );
    }

    public static function plugin_active( string $plugin_file ): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( $plugin_file );
    }

    public static function render_missing_hws_notice(): void {
        echo '<div class="notice notice-error"><p><strong>SMP Publication Integration</strong> requires <code>hws-base-tools</code> to be installed and active.</p></div>';
    }
}
