<?php

declare(strict_types=1);

$root       = dirname( __DIR__ );
$controller = (string) file_get_contents( $root . '/src/Admin/Dashboard/DashboardController.php' );
$navigation = (string) file_get_contents( $root . '/src/Admin/Navigation/AdminNavigation.php' );
$css        = (string) file_get_contents( $root . '/assets/admin/dashboard.css' );

$mapping_start = strpos( $controller, 'private function publication_mapping_panel' );
$founder_start = strpos( $controller, 'private function founder_profiles(): void' );
$mapping       = false !== $mapping_start && false !== $founder_start
    ? substr( $controller, $mapping_start, $founder_start - $mapping_start )
    : '';

$checks = [
    'Founder Profiles is a dedicated Core-backed dashboard route.' => str_contains( $navigation, "'founder_profiles'    => 'Founder Profiles'" )
        && str_contains( $controller, "if ( 'founder_profiles' === \$id ) { \$this->founder_profiles(); return; }" ),
    'Founder Profiles is assigned to the Publication sidebar group.' => str_contains( $navigation, "'profiles'            => 'Publication Profiles',\n            'founder_profiles'    => 'Founder Profiles'," ),
    'Current Publication no longer nests the founder manager.' => '' !== $mapping
        && ! str_contains( $mapping, 'founder_profiles' ),
    'Founder manager keeps the shared Core search and AJAX persistence contract.' => str_contains( $controller, 'new SmartSearchRenderer()' )
        && str_contains( $controller, 'smpi-founder-profile-core-search' )
        && str_contains( $controller, 'action:`smpi_save_founder_profiles`' ),
    'Founder manager has a live selected count and compact repeated rows.' => str_contains( $controller, 'data-smpi-founder-count' )
        && str_contains( $controller, 'function updateFounderCount(panel)' )
        && str_contains( $controller, '<article class=\"smpi-founder-profile-card\"' )
        && str_contains( $controller, 'button-link-delete smpi-remove-founder-profile' ),
    'Founder layout is flat, responsive, and suppresses hidden Core search output.' => str_contains( $css, '.smpi-dashboard .smpi-founder-page{' )
        && str_contains( $css, '.smpi-dashboard .smpi-founder-workspace.smpi-profile-picker{' )
        && str_contains( $css, '.hpc-smart-search-selected[hidden]' )
        && str_contains( $css, 'display:none!important' )
        && str_contains( $css, '.smpi-dashboard .smpi-founder-profile-card{' ),
];

foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

echo "PASS: Founder Profiles uses a dedicated tab and compact Core-backed manager.\n";
