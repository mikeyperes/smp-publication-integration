<?php
namespace smp_publication_integration;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

function init_github_updater( array $config ) {
    if ( empty( $config["plugin_file"] ) || empty( $config["github_repo"] ) ) {
        return false;
    }

    $hws_updater = trailingslashit( WP_PLUGIN_DIR ) . "hws-base-tools/GitHub_Updater.php";
    if ( ! function_exists( "\hws_base_tools\hws_init_github_updater" ) && is_readable( $hws_updater ) ) {
        require_once $hws_updater;
    }

    if ( function_exists( "\hws_base_tools\hws_init_github_updater" ) ) {
        return \hws_base_tools\hws_init_github_updater( $config );
    }

    return false;
}
