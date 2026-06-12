<?php
namespace smp_publication_integration\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Dependencies {
    public static function required_dependencies(): array {
        return [
            'hws-base-tools/initialization.php' => [
                'label'   => 'HWS Base Tools',
                'message' => 'Required core services for Scale My Publication integrations.',
                'active'  => self::hws_base_tools_active(),
            ],
            'advanced-custom-fields-pro/acf.php' => [
                'label'   => 'Advanced Custom Fields Pro',
                'message' => 'Required for publication profile fields, repeaters, relationships, and admin field rendering.',
                'active'  => self::acf_active(),
            ],
        ];
    }

    public static function optional_dependencies(): array {
        return [
            'smp-verified-profiles/initialization.php' => [
                'label'   => 'SMP Verified Profiles',
                'message' => 'Recommended for founder/person profile binding and profile schema checks.',
                'active'  => self::sfpf_active(),
            ],
            'hexa-pr-wire-distributor/initialization.php' => [
                'label'   => 'Hexa PR Wire Distributor',
                'message' => 'Recommended when press-release CPT visibility controls are needed.',
                'active'  => self::hpr_active(),
            ],
            'seo-by-rank-math/seo-by-rank-math.php' => [
                'label'   => 'Rank Math SEO',
                'message' => 'Recommended for breadcrumbs, schema controls, and SEO integrity checks.',
                'active'  => self::rank_math_active(),
            ],
            'seo-by-rank-math-pro/rank-math-pro.php' => [
                'label'   => 'Rank Math SEO PRO',
                'message' => 'Recommended for advanced schema editing workflows.',
                'active'  => self::plugin_active( 'seo-by-rank-math-pro/rank-math-pro.php' ),
            ],
            'litespeed-cache/litespeed-cache.php' => [
                'label'   => 'LiteSpeed Cache',
                'message' => 'Recommended for publication performance checks and cache optimization reporting.',
                'active'  => self::litespeed_active(),
            ],
        ];
    }

    public static function missing_required_dependencies(): array {
        return array_filter(
            self::required_dependencies(),
            static fn( array $dependency ): bool => empty( $dependency['active'] )
        );
    }

    public static function required_notice_message( array $missing ): string {
        $items = [];
        foreach ( $missing as $dependency ) {
            $items[] = '<strong>' . esc_html( $dependency['label'] ) . '</strong> - ' . esc_html( $dependency['message'] );
        }

        return '<strong>SMP Publication Integration</strong> cannot run until these required dependencies are active:<br>' . implode( '<br>', $items );
    }

    public static function render_missing_required_notice(): void {
        $missing = self::missing_required_dependencies();
        if ( empty( $missing ) ) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . wp_kses_post( self::required_notice_message( $missing ) ) . '</p></div>';
    }

    public static function hws_base_tools_active(): bool {
        return self::plugin_active( 'hws-base-tools/initialization.php' )
            || defined( 'HWS_BASE_TOOLS_VERSION' )
            || function_exists( 'hws_base_tools\\hws_get_structured_plugin' )
            || function_exists( 'hws_base_tools\\check_plugin_status' );
    }

    public static function acf_active(): bool {
        return function_exists( 'acf_add_local_field_group' ) || function_exists( 'acf' ) || class_exists( 'ACF' );
    }

    public static function sfpf_active(): bool {
        return self::plugin_active( 'smp-verified-profiles/initialization.php' )
            || post_type_exists( 'profile' )
            || function_exists( 'smp_verified_profiles\\get_verified_profile_shortcodes' );
    }

    public static function hpr_active(): bool {
        return self::plugin_active( 'hexa-pr-wire-distributor/initialization.php' ) || post_type_exists( 'press-release' );
    }

    public static function rank_math_active(): bool {
        return self::plugin_active( 'seo-by-rank-math/seo-by-rank-math.php' ) || defined( 'RANK_MATH_VERSION' );
    }

    public static function litespeed_active(): bool {
        return self::plugin_active( 'litespeed-cache/litespeed-cache.php' ) || defined( 'LSCWP_V' );
    }

    public static function plugin_active( string $plugin_file ): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( $plugin_file );
    }
}