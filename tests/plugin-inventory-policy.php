<?php

declare(strict_types=1);

namespace smp_publication_integration\Admin {
    final class Ajax {
        public static function nonce(): string {
            return 'test-nonce';
        }
    }
}

namespace {
    use smp_publication_integration\Support\PluginInventory;

    define( 'ABSPATH', dirname( __DIR__ ) . '/' );

    function admin_url( string $path = '' ): string {
        return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
    }

    function assert_true( bool $condition, string $message ): void {
        if ( $condition ) {
            return;
        }

        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }

    $root = dirname( __DIR__ );

    require_once $root . '/lib/hexa-wordpress-plugin-core/src/PluginChecks/PluginRecommendationRegistry.php';
    require_once $root . '/src/Support/PluginInventory.php';

    $recommended = PluginInventory::recommended_definitions();
    $recommended_by_file = [];
    foreach ( $recommended as $definition ) {
        $recommended_by_file[ (string) $definition['plugin_file'] ] = $definition;
    }

    assert_true(
        ! isset( $recommended_by_file['simple-local-avatars/simple-local-avatars.php'] ),
        'Simple Local Avatars must not appear in the required plugin list.'
    );

    $pr_wire_file = 'hexa-pr-wire-distributor/hexa-pr-wire-distributor.php';
    assert_true( isset( $recommended_by_file[ $pr_wire_file ] ), 'Hexa PR Wire Distributor requirement is missing.' );
    assert_true( true === $recommended_by_file[ $pr_wire_file ]['required'], 'Hexa PR Wire Distributor must be required.' );
    assert_true( true === $recommended_by_file[ $pr_wire_file ]['checks']['installed'], 'Hexa PR Wire Distributor must check installation.' );
    assert_true( true === $recommended_by_file[ $pr_wire_file ]['checks']['active'], 'Hexa PR Wire Distributor must check activation.' );

    $forbidden = PluginInventory::forbidden_definitions();
    $forbidden_by_file = [];
    foreach ( $forbidden as $definition ) {
        $forbidden_by_file[ (string) $definition['plugin_file'] ] = $definition;
    }

    $expected_forbidden = [
        'jet-engine/jet-engine.php',
        'simple-local-avatars/simple-local-avatars.php',
    ];
    $actual_forbidden = array_keys( $forbidden_by_file );
    sort( $expected_forbidden );
    sort( $actual_forbidden );

    assert_true( $expected_forbidden === $actual_forbidden, 'Forbidden policy must contain exactly JetEngine and Simple Local Avatars.' );

    foreach ( $forbidden_by_file as $plugin_file => $definition ) {
        assert_true( true === $definition['should_not_contain'], $plugin_file . ' must be explicitly forbidden.' );
        assert_true( true === $definition['checks']['not_installed'], $plugin_file . ' must require absence.' );
        assert_true( false === $definition['required'], $plugin_file . ' must not also be required.' );
        assert_true( false === $definition['recommended'], $plugin_file . ' must not also be recommended.' );
    }

    $required_args = PluginInventory::recommended_renderer_args();
    assert_true( 'Required Plugins' === $required_args['title'], 'Required section title is incorrect.' );

    $forbidden_args = PluginInventory::forbidden_renderer_args();
    assert_true( 'Forbidden Plugins' === $forbidden_args['title'], 'Forbidden section title is incorrect.' );
    assert_true( false === $forbidden_args['hide_compliant_forbidden'], 'Absent forbidden plugins must remain visible.' );
    assert_true(
        str_contains( $forbidden_args['description'], 'explicitly listed by SMP policy' ),
        'Forbidden section must explain its explicit policy metric.'
    );

    $source = (string) file_get_contents( $root . '/src/Support/PluginInventory.php' );
    assert_true(
        ! str_contains( $source, 'get_installed_not_recommended_definitions' ),
        'SMP must not infer forbidden policy from the recommendation registry.'
    );
    assert_true( ! str_contains( $source, 'foreach ( get_plugins()' ), 'SMP must not infer forbidden policy by scanning all installed plugins.' );
    assert_true( ! str_contains( $source, 'forbidden_slugs' ), 'Legacy dynamic forbidden fallback remains.' );

    echo "PASS: SMP plugin requirements and explicit forbidden policy are isolated correctly.\n";
}
