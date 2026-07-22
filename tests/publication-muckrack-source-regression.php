<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
$acf = (string) file_get_contents( $root . '/src/Content/AcfFields.php' );
$ajax = (string) file_get_contents( $root . '/src/Admin/Ajax/AjaxController.php' );
$dashboard = (string) file_get_contents( $root . '/src/Admin/Dashboard/DashboardController.php' );

function assert_publication_muckrack_source( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$save_start = strpos( $ajax, 'public function save_publication_muckrack_source' );
$save_end = false !== $save_start ? strpos( $ajax, 'public function import_brand_primary_color', $save_start ) : false;
$save_method = false !== $save_start && false !== $save_end ? substr( $ajax, $save_start, $save_end - $save_start ) : '';
$source_start = strpos( $dashboard, 'private function publication_muckrack_source_html' );
$source_end = false !== $source_start ? strpos( $dashboard, 'private function publication_muckrack_source_status_html', $source_start ) : false;
$source_method = false !== $source_start && false !== $source_end ? substr( $dashboard, $source_start, $source_end - $source_start ) : '';

assert_publication_muckrack_source(
    str_contains( $acf, "PUBLICATION_MUCKRACK_VERIFIED_FIELD_KEY = 'field_smpi_publication_muckrack_verified'" )
        && str_contains( $acf, "PUBLICATION_MUCKRACK_URL_FIELD_KEY = 'field_smpi_publication_muckrack_url'" )
        && str_contains( $acf, '"key" => self::PUBLICATION_MUCKRACK_VERIFIED_FIELD_KEY' )
        && str_contains( $acf, '"key" => self::PUBLICATION_MUCKRACK_URL_FIELD_KEY' ),
    'MuckRack source controls must reuse the registered ACF field keys.'
);
assert_publication_muckrack_source(
    str_contains( $ajax, "'smpi_save_publication_muckrack_source' => [ 'callback' => [ \$this, 'save_publication_muckrack_source' ] ]" )
        && str_contains( $save_method, "update_field( AcfFields::PUBLICATION_MUCKRACK_VERIFIED_FIELD_KEY, \$verified ? 1 : 0, 'option' )" )
        && str_contains( $save_method, "update_field( AcfFields::PUBLICATION_MUCKRACK_URL_FIELD_KEY, \$url, 'option' )" ),
    'The AJAX action must write both values directly to the canonical ACF options.'
);
assert_publication_muckrack_source(
    ! str_contains( $save_method, 'Settings::update' )
        && ! str_contains( $save_method, 'update_option' ),
    'The ACF source save must not duplicate either value in plugin settings or raw options.'
);
assert_publication_muckrack_source(
    str_contains( $source_method, "'tab'  => 'publication_options'" )
        && str_contains( $source_method, 'target="_blank"' )
        && str_contains( $source_method, 'smpi-publication-muckrack-source-link' ),
    'The card must show the actual Publication Options URL as a new-tab link.'
);
assert_publication_muckrack_source(
    str_contains( $source_method, "publication_muckrack_source_status_html( 'verified'" )
        && str_contains( $source_method, "publication_muckrack_source_status_html( 'url'" )
        && str_contains( $source_method, "publication_muckrack_source_status_html( 'effective'" )
        && str_contains( $source_method, "'input_class' => 'smpi-publication-muckrack-source-verified'" )
        && str_contains( $source_method, 'data-smpi-save-publication-muckrack-source' ),
    'The source UI must expose status, verification, URL, and an explicit save action.'
);
assert_publication_muckrack_source(
    strpos( $dashboard, '$this->publication_muckrack_source_html()' ) < strpos( $dashboard, '$this->select_setting_html( "publication_muckrack_text_mode"' )
        && str_contains( $dashboard, 'action:`smpi_save_publication_muckrack_source`' )
        && str_contains( $dashboard, 'setPublicationMuckrackStatus' ),
    'The ACF source editor must appear first and update through AJAX without a page reload.'
);

echo 'PASS: Publication MuckRack controls edit only the canonical ACF option source.' . PHP_EOL;
