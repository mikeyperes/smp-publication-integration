<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Config;
use smp_publication_integration\Support\PluginRegistry;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DebugEndpoint {
    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_route' ] );
    }

    public function register_route(): void {
        register_rest_route(
            'smpi/v1',
            '/debug',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'render' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function render(): \WP_REST_Response {
        if ( ! Settings::bool( 'public_debug_enabled' ) ) {
            return new \WP_REST_Response( [ 'message' => 'SMP public debug is disabled.' ], 404 );
        }
        return new \WP_REST_Response(
            [
                'plugin' => [ 'name' => Config::$plugin_name, 'version' => Config::VERSION, 'namespace' => 'smp_publication_integration', 'github' => Config::$github_repo ],
                'site' => [ 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ), 'wp_version' => get_bloginfo( 'version' ), 'theme' => wp_get_theme()->get( 'Name' ), 'site_icon' => get_site_icon_url( 64 ) ],
                'settings' => Settings::all(),
                'plugins' => PluginRegistry::all(),
                'counts' => $this->counts(),
                'shortcodes' => array_keys( Shortcodes::shortcodes() ),
            ]
        );
    }

    private function counts(): array {
        $out = [];
        foreach ( [ 'post', 'page', 'publication', 'profile', 'press-release' ] as $type ) {
            $count = wp_count_posts( $type );
            $out[ $type ] = $count && isset( $count->publish ) ? (int) $count->publish : 0;
        }
        return $out;
    }
}