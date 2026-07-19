<?php

declare(strict_types=1);

$root            = dirname( __DIR__ );
$controller_file = $root . '/src/Admin/Dashboard/DashboardController.php';
$controller      = file_get_contents( $controller_file );

if ( ! is_string( $controller ) ) {
    fwrite( STDERR, "FAIL: Dashboard controller source could not be read.\n" );
    exit( 1 );
}

if ( ! str_contains( $controller, 'use Hexa\\PluginCore\\SnippetRegistry\\SnippetsTableRenderer;' ) ) {
    fwrite( STDERR, "FAIL: Dashboard controller must import the shared SnippetsTableRenderer.\n" );
    exit( 1 );
}

if ( str_contains( $controller, 'use Hexa\\PluginCore\\SnippetRegistry\\SnippetRenderer;' ) ) {
    fwrite( STDERR, "FAIL: Dashboard controller retains the obsolete SnippetRenderer import.\n" );
    exit( 1 );
}

$bootstrap = file_get_contents( $root . '/src/Bootstrap/Plugin.php' );
if ( ! is_string( $bootstrap ) || ! str_contains( $bootstrap, "'tab_id'        => 'hexa_core'" ) ) {
    fwrite( STDERR, "FAIL: Shared Core tab module must use SMP's canonical hexa_core section ID.\n" );
    exit( 1 );
}

if ( ! str_contains( $controller, "plugins_url( 'assets/admin/dashboard.css', dirname( __DIR__, 3 )" ) ) {
    fwrite( STDERR, "FAIL: Dashboard stylesheet URL must resolve from the plugin root.\n" );
    exit( 1 );
}

$dashboard_css = $root . '/assets/admin/dashboard.css';
if ( ! is_readable( $dashboard_css ) || ! str_contains( (string) file_get_contents( $dashboard_css ), '.smpi-table-scroll' ) ) {
    fwrite( STDERR, "FAIL: Responsive dashboard stylesheet is missing table containment rules.\n" );
    exit( 1 );
}

if (
    ! str_contains( $controller, 'use Hexa\\PluginCore\\WpAdminTabs\\TabDefinition;' )
    || ! str_contains( $controller, 'use Hexa\\PluginCore\\WpAdminTabs\\TabRegistry;' )
    || ! str_contains( $controller, '$tabs       = $registry->all();' )
    || ! str_contains( $controller, 'render_registered_tab( $registry, $tab )' )
) {
    fwrite( STDERR, "FAIL: Dashboard tabs must render through Hexa WP Core tab definitions and registry.\n" );
    exit( 1 );
}

$dashboard_css_source = (string) file_get_contents( $dashboard_css );
$core_ui_source       = (string) file_get_contents( $root . '/lib/hexa-wordpress-plugin-core/src/WpAdminComponents/CoreUi.php' );
$core_tabs_source     = (string) file_get_contents( $root . '/lib/hexa-wordpress-plugin-core/src/WpAdminTabs/HostTabsRenderer.php' );

if (
    str_contains( $dashboard_css_source, '#smpi-core-tabs .hpc-host-tab' )
    || ! str_contains( $core_ui_source, '.hpc-host-tabs{' )
    || ! str_contains( $core_ui_source, 'display:flex;flex-wrap:wrap' )
) {
    fwrite( STDERR, "FAIL: SMP must leave host-tab layout to Hexa WP Core's wrapped tab component.\n" );
    exit( 1 );
}

if (
    ! str_contains( $controller, '"layout"          => "sidebar"' )
    || ! str_contains( $controller, '"groups"          => $navigation->groups()' )
    || ! str_contains( $controller, '"sidebar_collapsible" => true' )
    || ! str_contains( $controller, '"sidebar_collapsed"   => false' )
    || ! str_contains( $controller, '"sidebar_persist"     => true' )
) {
    fwrite( STDERR, "FAIL: SMP must configure the reusable Core sidebar shell explicitly.\n" );
    exit( 1 );
}

foreach (
    [
        'smpi-tabs-nav',
        'smpi-tab-btn',
        'smpi-tab-status',
        'smpi-tab-content',
        'function setActiveTab',
        'function loadTab(tab',
        'smpiAdmin.pageUrl',
    ] as $legacy_shell_token
) {
    if ( str_contains( $controller, $legacy_shell_token ) ) {
        fwrite( STDERR, "FAIL: Dashboard controller retains legacy shell code: {$legacy_shell_token}.\n" );
        exit( 1 );
    }
}

if (
    ! str_contains( $controller, 'function setTabMessage(text)' )
    || ! str_contains( $controller, '#smpi-core-tabs [data-hpc-tab-message]' )
    || str_contains( $controller, '.smpi-tab-message' )
) {
    fwrite( STDERR, "FAIL: SMP save reporting must target the Core tab status element.\n" );
    exit( 1 );
}

$navigation_source = (string) file_get_contents( $root . '/src/Admin/Navigation/AdminNavigation.php' );
if (
    is_file( $root . '/src/Admin/Navigation/SectionNavigation.php' )
    || str_contains( $navigation_source, 'function section_url(' )
    || str_contains( $dashboard_css_source, 'smpi-section-tabs' )
    || str_contains( $dashboard_css_source, 'smpi-section-tab' )
) {
    fwrite( STDERR, "FAIL: The retired two-level SMP navigation layer still exists.\n" );
    exit( 1 );
}

$rail_rule = '';
if ( preg_match( '/\.hpc-host-rail\{([^}]*)\}/', $core_ui_source, $matches ) ) {
    $rail_rule = (string) $matches[1];
}

if (
    ! str_contains( $core_ui_source, '--hpc-host-sidebar-width:214px' )
    || ! str_contains( $core_ui_source, 'grid-template-columns:44px minmax(0,1fr)' )
    || ! str_contains( $rail_rule, 'max-height:none' )
    || ! str_contains( $rail_rule, 'overflow:visible' )
    || str_contains( $rail_rule, 'overflow:auto' )
    || ! str_contains( $core_tabs_source, 'data-hpc-sidebar-toggle' )
    || ! str_contains( $core_tabs_source, 'window.localStorage.setItem(root.dataset.sidebarStorageKey' )
) {
    fwrite( STDERR, "FAIL: Vendored Core sidebar does not match the canonical collapsible shell contract.\n" );
    exit( 1 );
}

foreach ( [ '_smpi_shadow_complete', '_smpi_pr_shadow_override' ] as $field_name ) {
    $expected = '\'get_field("' . $field_name . '", $post_id)\'';
    if ( ! str_contains( $controller, $expected ) ) {
        fwrite( STDERR, "FAIL: Literal post ID code example is not safely quoted for {$field_name}.\n" );
        exit( 1 );
    }
}

echo "PASS: Dashboard uses the canonical Core sidebar and contains no retired SMP navigation layer.\n";
