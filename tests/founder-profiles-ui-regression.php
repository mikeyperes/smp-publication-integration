<?php

declare(strict_types=1);

$root       = dirname( __DIR__ );
$controller = (string) file_get_contents( $root . '/src/Admin/Dashboard/DashboardController.php' );
$ajax       = (string) file_get_contents( $root . '/src/Admin/Ajax/AjaxController.php' );
$navigation = (string) file_get_contents( $root . '/src/Admin/Navigation/AdminNavigation.php' );
$presenter  = (string) file_get_contents( $root . '/src/Admin/FounderProfiles/FounderProfilePresenter.php' );
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
    'Founder manager has a live selected count and server-rendered profile details.' => str_contains( $controller, 'data-smpi-founder-count' )
        && str_contains( $controller, 'function updateFounderCount(panel,count)' )
        && str_contains( $controller, 'x.data.html' )
        && str_contains( $ajax, 'FounderProfilePresenter' )
        && str_contains( $ajax, '"html"     => ( new FounderProfilePresenter() )->collection_html( $ids )' ),
    'Presenter discovers and groups all populated profile metadata.' => str_contains( $presenter, 'final class FounderProfilePresenter' )
        && str_contains( $presenter, 'private function raw_meta' )
        && str_contains( $presenter, 'private function extract_repeaters' )
        && str_contains( $presenter, 'Web & Social' )
        && str_contains( $presenter, 'WordPress Record' )
        && str_contains( $presenter, 'button-link-delete smpi-remove-founder-profile' ),
    'Founder layout is flat, responsive, and suppresses hidden Core search output.' => str_contains( $css, '.smpi-dashboard .smpi-founder-page{' )
        && str_contains( $css, '.smpi-dashboard .smpi-founder-workspace.smpi-profile-picker{' )
        && str_contains( $css, '.hpc-smart-search-selected[hidden]' )
        && str_contains( $css, 'display:none!important' )
        && str_contains( $css, '.smpi-dashboard .smpi-founder-detail-groups{' )
        && str_contains( $css, '.smpi-dashboard .smpi-founder-field-list{' ),
];

foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

echo "PASS: Founder Profiles uses a dedicated tab and dynamically renders complete selected-profile details.\n";
