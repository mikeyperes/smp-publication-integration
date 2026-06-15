<?php
namespace smp_publication_integration\Support;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class BootstrapMigration {
    public static function register( string $folder, string $canonical_file ): void {
        add_action(
            "plugins_loaded",
            static function () use ( $folder, $canonical_file ): void {
                self::migrate_active_plugin_basename( $folder, $canonical_file );
            },
            1
        );
    }

    private static function migrate_active_plugin_basename( string $folder, string $canonical_file ): void {
        $canonical = trim( $folder, "/" ) . "/" . basename( $canonical_file );
        $legacy = trim( $folder, "/" ) . "/initialization.php";

        if ( $canonical === $legacy ) {
            return;
        }

        $active_plugins = (array) get_option( "active_plugins", [] );
        $changed = false;
        foreach ( $active_plugins as $index => $plugin ) {
            if ( $legacy === $plugin ) {
                $active_plugins[ $index ] = $canonical;
                $changed = true;
            }
        }

        if ( $changed ) {
            update_option( "active_plugins", array_values( array_unique( $active_plugins ) ), false );
        }

        if ( is_multisite() ) {
            $network_plugins = (array) get_site_option( "active_sitewide_plugins", [] );
            if ( isset( $network_plugins[ $legacy ] ) ) {
                $network_plugins[ $canonical ] = $network_plugins[ $legacy ];
                unset( $network_plugins[ $legacy ] );
                update_site_option( "active_sitewide_plugins", $network_plugins );
            }
        }
    }
}
