<?php

namespace smp_publication_integration\Admin;

use smp_publication_integration\Pages\SourceNotice;
use smp_publication_integration\Support\PublicationFounders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings, SMP, Founders: a read-only view of the founders defined in HWS Base Tools,
 * under Core's source banner with a link to edit them there. SMP stores no founders.
 */
final class FoundersTab {
    public const TAB = 'founders_list';

    public function register(): void {
        add_filter( 'smpi_render_dashboard_tab', [ $this, 'maybe_render' ], 10, 2 );
    }

    public function maybe_render( $rendered, string $tab ) {
        if ( self::TAB !== $tab ) {
            return $rendered;
        }
        $this->render();
        return true;
    }

    private function render(): void {
        $edit = PublicationFounders::edit_url();
        echo '<div class="smpi-panel"><h2>Founders</h2>';
        echo SourceNotice::html( $edit ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if ( ! PublicationFounders::available() ) {
            echo '<p>HWS Base Tools is missing or older than its Founders section, so no founders are shown. Update HWS Base Tools to set them.</p></div>';
            return;
        }
        $people = PublicationFounders::all();
        if ( ! $people ) {
            echo '<p>No founders are set yet. <a href="' . esc_url( $edit ) . '">Add them in HWS Base Tools.</a></p>';
        } else {
            echo '<ol class="smpi-founders-readonly">';
            foreach ( $people as $person ) {
                echo '<li><strong>' . esc_html( $person['name'] ) . '</strong>' . ( '' !== $person['title'] ? ' - ' . esc_html( $person['title'] ) : '' ) . ' <a href="' . esc_url( get_edit_user_link( $person['user_id'] ) ) . '">profile</a></li>';
            }
            echo '</ol>';
        }
        echo '<p class="description">SMP uses this list in the publication\'s search-engine data, in [smp_company field="founders"] and [smp_publication_founders]. Pages show the founders with [hws_publication_founders], which HWS Base Tools owns.</p></div>';
    }
}
