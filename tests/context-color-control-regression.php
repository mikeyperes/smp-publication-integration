<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$dashboard = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );
$core = (string) file_get_contents( $root . "/lib/hexa-wordpress-plugin-core/src/WpAdminComponents/ColorControl.php" );
$core_version = trim( (string) file_get_contents( $root . "/lib/hexa-wordpress-plugin-core/VERSION" ) );

$start = strpos( $dashboard, "private function author_context_overrides_html" );
$end = false !== $start ? strpos( $dashboard, "private function breadcrumb_style_options", $start ) : false;
$context_renderer = false !== $start && false !== $end ? substr( $dashboard, $start, $end - $start ) : "";

$checks = [
    "Context overrides require the inherited-color Core release." => version_compare( $core_version, "0.19.61", ">=" ),
    "Every context color renders through the shared Core control." => str_contains( $context_renderer, "ColorControl::render(" )
        && str_contains( $context_renderer, '"allow_inherit" => true' )
        && str_contains( $context_renderer, '"inherited_value" => $default_color' )
        && str_contains( $context_renderer, '"value_input_class" => "smpi-setting smpi-color-hidden"' ),
    "The context renderer contains no hand-built color picker." => ! str_contains( $context_renderer, "class=smpi-color-picker" )
        && ! str_contains( $context_renderer, "data-smpi-sync-key" )
        && ! str_contains( $context_renderer, "CoreUi::copy_button" ),
    "The shared control owns editable hex, picker, import, and inherit actions." => str_contains( $core, "data-hpc-color-hex-input" )
        && str_contains( $core, "data-hpc-color-picker" )
        && str_contains( $core, "data-hpc-brand-color-import" )
        && str_contains( $core, "data-hpc-color-inherit" ),
    "The shared control separates displayed and persisted inherited values." => str_contains( $core, "data-hpc-color-value-input" )
        && str_contains( $core, '$explicit_value' )
        && str_contains( $core, 'valueInput.value=""' )
        && str_contains( $core, "dispatchChange(valueInput)" ),
    "SMP removed its duplicate picker and inherit event handlers." => ! str_contains( $dashboard, '$(document).on(`change`,`.smpi-color-picker`' )
        && ! str_contains( $dashboard, '$(document).on(`click`,`.smpi-color-inherit`' ),
    "Context layout does not restyle nested Core labels or force overflow." => str_contains( $dashboard, ".smpi-context-overrides .smpi-context-override-row>label" )
        && str_contains( $dashboard, "@media(max-width:1500px)" )
        && ! str_contains( $dashboard, ".smpi-context-overrides .smpi-context-override-row label{display:flex" ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: " . $message . "\n" );
        exit( 1 );
    }
}

echo "PASS: Author context colors use the reusable inherited Hexa WP Core control.\n";
