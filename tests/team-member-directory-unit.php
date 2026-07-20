<?php

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ?: '' ); }
function sanitize_html_class( string $value ): string { return sanitize_key( $value ); }
function esc_attr( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( mixed $value ): string { return (string) $value; }

require dirname( __DIR__ ) . '/src/Content/TeamMemberDirectory.php';

use smp_publication_integration\Content\TeamMemberDirectory;

$options = TeamMemberDirectory::template_options();
if ( [ 'portrait_grid', 'editorial_list', 'compact_directory' ] !== array_keys( $options ) ) {
    fwrite( STDERR, "FAIL: Team directory must expose exactly the three approved templates.\n" );
    exit( 1 );
}

if ( 'portrait_grid' !== TeamMemberDirectory::normalize_style( 'not-valid' ) ) {
    fwrite( STDERR, "FAIL: Invalid team template did not fall back to the minimal portrait grid.\n" );
    exit( 1 );
}

foreach ( array_keys( $options ) as $style ) {
    $preview = TeamMemberDirectory::preview_html( $style );
    if ( ! str_contains( $preview, 'smpi-team-directory--' . $style ) || 2 !== substr_count( $preview, 'class="smpi-team-card"' ) ) {
        fwrite( STDERR, "FAIL: {$style} does not render the shared two-person visual preview.\n" );
        exit( 1 );
    }
}

$css = TeamMemberDirectory::styles();
if (
    ! str_contains( $css, 'object-fit:cover' )
    || ! str_contains( $css, '@media(max-width:600px)' )
    || str_contains( $css, 'letter-spacing:-' )
) {
    fwrite( STDERR, "FAIL: Team directory CSS does not preserve images and responsive layout contracts.\n" );
    exit( 1 );
}

$root      = dirname( __DIR__ );
$settings  = (string) file_get_contents( $root . '/src/Settings/SettingsRepository.php' );
$ajax      = (string) file_get_contents( $root . '/src/Admin/Ajax/AjaxController.php' );
$bootstrap = (string) file_get_contents( $root . '/src/Bootstrap/Plugin.php' );
$dashboard = (string) file_get_contents( $root . '/src/Admin/Dashboard/DashboardController.php' );

if (
    ! str_contains( $settings, "'team_member_directory_enabled' => false" )
    || ! str_contains( $settings, '"team_member_directory_style" => [ "portrait_grid", "editorial_list", "compact_directory" ]' )
    || ! str_contains( $ajax, '"team_member_directory_enabled"' )
    || ! str_contains( $ajax, '"team_member_directory_style"' )
    || ! str_contains( $bootstrap, 'new Content\\TeamMemberDirectory()' )
    || ! str_contains( $dashboard, 'Team Member CPT (<code>team-member</code>)' )
    || ! str_contains( $dashboard, '[smp_team_members]' )
) {
    fwrite( STDERR, "FAIL: Team directory settings, AJAX, bootstrap, readiness check, or shortcode documentation is incomplete.\n" );
    exit( 1 );
}

echo "PASS: Team member directory exposes three shared templates, prerequisite reporting, and shortcode wiring.\n";
