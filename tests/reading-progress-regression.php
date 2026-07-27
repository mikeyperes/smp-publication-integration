<?php

declare(strict_types=1);

define( "ABSPATH", dirname( __DIR__ ) . "/" );

function sanitize_key( string $value ): string {
    return strtolower( preg_replace( "/[^a-zA-Z0-9_-]/", "", $value ) ?: "" );
}

function esc_attr( string $value ): string {
    return htmlspecialchars( $value, ENT_QUOTES, "UTF-8" );
}

$root = dirname( __DIR__ );
require $root . "/src/Content/ReadingProgress.php";

use smp_publication_integration\Content\ReadingProgress;

$module = (string) file_get_contents( $root . "/src/Content/ReadingProgress.php" );
$settings = (string) file_get_contents( $root . "/src/Settings/SettingsRepository.php" );
$ajax = (string) file_get_contents( $root . "/src/Admin/Ajax/AjaxController.php" );
$dashboard = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );
$bootstrap = (string) file_get_contents( $root . "/src/Bootstrap/Plugin.php" );
$main = (string) file_get_contents( $root . "/smp-publication-integration.php" );

$expected_styles = [ "thin", "track", "glow", "floating", "segmented" ];
$preview_css = ReadingProgress::preview_css();
$checks = [
    "Five canonical progress designs are registered in one module." => $expected_styles === ReadingProgress::style_keys()
        && "thin" === ReadingProgress::normalize_style( "invalid" )
        && str_contains( ReadingProgress::preview_html( "segmented" ), "smpi-reading-progress--segmented" ),
    "The content module is loaded and booted through the canonical plugin stack." => str_contains( $main, 'require_once __DIR__ . "/src/Content/ReadingProgress.php"' )
        && str_contains( $bootstrap, "new Content\\ReadingProgress()" ),
    "Reading progress is isolated to enabled public single posts." => str_contains( $module, "RuntimeContext::is_public_dom_context()" )
        && str_contains( $module, "Settings::bool( self::ENABLED_SETTING )" )
        && str_contains( $module, 'is_singular( "post" )' ),
    "The frontend uses semantic progress markup and an optimization-safe runtime." => str_contains( $module, 'role="progressbar"' )
        && str_contains( $module, 'aria-valuemin="0"' )
        && str_contains( $module, 'data-no-optimize="1"' )
        && str_contains( $module, 'data-cfasync="false"' ),
    "The progress calculation follows the reference page without scroll-event layout thrashing." => str_contains( $module, "pageHeight-window.innerHeight" )
        && str_contains( $module, "(offset/maximum)*100" )
        && str_contains( $module, "window.requestAnimationFrame(update)" )
        && str_contains( $module, "transform:scaleX(var(--smpi-reading-progress-scale,0))" )
        && str_contains( $module, 'window.addEventListener("scroll",requestUpdate,{passive:true})' )
        && str_contains( $module, "ResizeObserver" ),
    "All five designs consume the selected color and remain fixed at the viewport top." => str_contains( $module, "position:fixed" )
        && str_contains( $module, "top:0" )
        && str_contains( $module, "--smpi-reading-progress-color" )
        && str_contains( $module, ".smpi-reading-progress--thin" )
        && str_contains( $module, ".smpi-reading-progress--track" )
        && str_contains( $module, ".smpi-reading-progress--glow" )
        && str_contains( $module, ".smpi-reading-progress--floating" )
        && str_contains( $module, ".smpi-reading-progress--segmented" ),
    "Admin previews reuse the frontend design classes." => str_contains( $preview_css, ".smpi-reading-progress--thin" )
        && str_contains( $preview_css, ".smpi-reading-progress--segmented" )
        && str_contains( $preview_css, "--smpi-reading-progress-value" ),
    "Defaults, validation, and AJAX persistence cover enablement, design, and color." => str_contains( $settings, '"reading_progress_enabled" => false' )
        && str_contains( $settings, '"reading_progress_style" => "thin"' )
        && str_contains( $settings, '"reading_progress_color" => "#00ff41"' )
        && str_contains( $settings, '"reading_progress_style" => [ "thin", "track", "glow", "floating", "segmented" ]' )
        && str_contains( $ajax, '"reading_progress_enabled"' )
        && str_contains( $ajax, '"reading_progress_style"' )
        && str_contains( $ajax, "'reading_progress_color'" ),
    "Article Design renders one-row design choices and the reusable Hexa Core color control." => str_contains( $dashboard, '"Article reading progress"' )
        && str_contains( $dashboard, 'select_setting_html( "reading_progress_style"' )
        && str_contains( $dashboard, 'ColorControl::render( [' )
        && str_contains( $dashboard, '"--smpi-reading-progress-color" => "color"' )
        && str_contains( $dashboard, 'input[data-key="reading_progress_style"]' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

echo "PASS: Reading progress has five designs, a Core color picker, isolated settings, and a reference-compatible frontend runtime.\n";
