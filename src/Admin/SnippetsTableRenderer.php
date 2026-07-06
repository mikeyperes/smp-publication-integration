<?php

namespace smp_publication_integration\Admin;

use Hexa\PluginCore\SnippetRegistry\SnippetRegistry;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Compatibility adapter for the SMP snippets page.
 *
 * The snippets table UI is owned by Hexa WP Core. SMP keeps this class so
 * older dashboard code paths still resolve, but all rendering is delegated
 * to the shared core renderer.
 */
final class SnippetsTableRenderer {
    public function render( SnippetRegistry|array $snippets, array $args = [] ): string {
        return ( new \Hexa\PluginCore\SnippetRegistry\SnippetsTableRenderer() )->render( $snippets, $args );
    }
}
