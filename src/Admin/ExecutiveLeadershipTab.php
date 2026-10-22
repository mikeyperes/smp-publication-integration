<?php

namespace smp_publication_integration\Admin;

use Hexa\PluginCore\WpAdminAjax\AjaxGuard;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use smp_publication_integration\Admin\People\PeopleEditor;
use smp_publication_integration\Content\ExecutiveLeadership;
use smp_publication_integration\Content\People\PeopleCards;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings, SMP, Executive Leadership: the on/off switch (which creates or links the
 * leadership page), the people and their titles, the style with a live example, the CSS
 * structure and the shortcode. Choosing someone here sets the same toggle and title
 * that their user profile shows. Layout and behavior come from PeopleEditor.
 */
final class ExecutiveLeadershipTab {
    public const TAB = 'executive_leadership';

    public function register(): void {
        add_filter( 'smpi_render_dashboard_tab', [ $this, 'maybe_render' ], 10, 2 );
        foreach ( [ 'toggle', 'save_people', 'style', 'apply_template', 'create_page' ] as $action ) {
            add_action( 'wp_ajax_smpi_leadership_' . $action, [ $this, 'ajax_' . $action ] );
        }
    }

    public function maybe_render( $rendered, string $tab ) {
        if ( self::TAB !== $tab ) {
            return $rendered;
        }
        $this->render();
        return true;
    }

    private function guard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'You are not allowed to change these settings.' ], 403 );
        }
        AjaxGuard::require_nonce_or_error( Ajax::NONCE, 'nonce' );
    }

    public function ajax_toggle(): void {
        $this->guard();
        $on = '1' === (string) ( $_POST['on'] ?? '' );
        ExecutiveLeadership::update_settings( [ 'enabled' => $on ] );
        $message = 'Executive Leadership is off. The profile fields are hidden and the shortcode prints nothing; people and titles are kept.';
        if ( $on ) {
            $page    = ExecutiveLeadership::ensure_page();
            $message = ! empty( $page['error'] )
                ? 'Executive Leadership is on, but the page could not be created: ' . $page['error']
                : ( $page['created'] ? 'Executive Leadership is on. The Executive Leadership Team page was created with the default template.' : 'Executive Leadership is on and linked to the existing Executive Leadership Team page.' );
        }
        wp_send_json_success( [ 'message' => $message, 'reload' => true ] );
    }

    public function ajax_save_people(): void {
        $this->guard();
        $people = json_decode( (string) wp_unslash( $_POST['people'] ?? '[]' ), true );
        $order  = [];
        foreach ( is_array( $people ) ? $people : [] as $person ) {
            $id = absint( $person['id'] ?? 0 );
            if ( $id && get_userdata( $id ) ) {
                ExecutiveLeadership::set_member( $id, true, (string) ( $person['title'] ?? '' ) );
                $order[] = $id;
            }
        }
        foreach ( ExecutiveLeadership::member_ids() as $id ) {
            if ( ! in_array( $id, $order, true ) ) {
                ExecutiveLeadership::set_member( $id, false );
            }
        }
        ExecutiveLeadership::update_settings( [ 'order' => $order ] );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => count( $order ) . ( 1 === count( $order ) ? ' person is' : ' people are' ) . ' on the leadership team. Their profiles were updated.', 'example' => $this->example_html() ] );
    }

    public function ajax_style(): void {
        $this->guard();
        $style = sanitize_key( (string) ( $_POST['style'] ?? '' ) );
        if ( ! isset( PeopleCards::STYLES[ $style ] ) ) {
            wp_send_json_error( [ 'message' => 'Unknown style.' ] );
        }
        ExecutiveLeadership::update_settings( [ 'style' => $style ] );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => 'Style saved.', 'example' => $this->example_html() ] );
    }

    public function ajax_apply_template(): void {
        $this->guard();
        $page_id = (int) ExecutiveLeadership::ensure_page()['id'];
        if ( ! $page_id ) {
            wp_send_json_error( [ 'message' => 'There is no leadership page to update.' ] );
        }
        ExecutiveLeadership::apply_default_template( $page_id );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => 'The page now uses the default template. Its previous content is saved as a revision.' ] );
    }

    public function ajax_create_page(): void {
        $this->guard();
        $page = ExecutiveLeadership::ensure_page();
        if ( ! empty( $page['error'] ) ) {
            wp_send_json_error( [ 'message' => 'The page could not be created: ' . $page['error'] ] );
        }
        wp_send_json_success( [ 'message' => 'The Executive Leadership Team page is ready.' ] );
    }

    private function example_html(): string {
        $html = ExecutiveLeadership::render();
        return '' !== $html ? $html : '<p class="description">Add someone above to see the example.</p>';
    }

    private function render(): void {
        $settings = ExecutiveLeadership::settings();
        $page_id  = $settings['enabled'] ? (int) ExecutiveLeadership::ensure_page()['id'] : 0;
        ob_start();
        ?>
        <section>
            <h3>Executive Leadership</h3>
            <p class="lead">Turn on to create the Executive Leadership Team page from the default template (or link the one the site already has) and to add the leadership toggle and title to every user profile. Off hides those profile fields and the list.</p>
            <?php echo CoreUi::toggle( 'smpi_leadership_enabled', $settings['enabled'], 'Executive Leadership is on', [ 'id' => 'smpi-la-enabled' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="smpi-pe-notice" data-role="toggle-notice" role="status" aria-live="polite"></div>
        </section>
        <script>
        (function(){
            var input = document.getElementById('smpi-la-enabled'); if (!input || input.dataset.ready) return; input.dataset.ready = '1';
            input.addEventListener('change', function(){
                var note = document.querySelector('#smpi-leadership-tab [data-role="toggle-notice"]'); note.className = 'smpi-pe-notice'; note.textContent = 'Saving…';
                var body = new URLSearchParams(); body.set('action', 'smpi_leadership_toggle'); body.set('nonce', <?php echo wp_json_encode( Ajax::nonce() ); ?>); body.set('on', input.checked ? '1' : '0');
                fetch(window.ajaxurl, { method:'POST', credentials:'same-origin', body: body }).then(function(r){ return r.json(); }).then(function(p){
                    if (!p || !p.success) throw new Error(p && p.data && p.data.message ? p.data.message : 'Could not save.');
                    note.textContent = p.data.message; setTimeout(function(){ location.reload(); }, 1200);
                }).catch(function(err){ note.className = 'smpi-pe-notice error'; note.textContent = err.message; input.checked = !input.checked; });
            });
        })();
        </script>
        <?php
        $top = (string) ob_get_clean();

        PeopleEditor::render(
            [
                'id'           => 'smpi-leadership-tab',
                'ajax_prefix'  => 'smpi_leadership_',
                'top_html'     => $top,
                'show_body'    => $settings['enabled'],
                'page'         => [ 'id' => $page_id, 'has_list' => $page_id && ExecutiveLeadership::page_has_list( $page_id ) ],
                'page_label'   => 'Executive Leadership Team',
                'list_label'   => 'Leadership team',
                'people_intro' => 'Drag to set the order of appearance. Adding someone here turns on "Is part of the executive leadership team" on their profile, and the title here is their Executive Leadership Title. Changes on a profile show here too.',
                'empty'        => 'No one yet. Search above to add the first person.',
                'members'      => $settings['enabled'] ? ExecutiveLeadership::member_ids() : [],
                'row'          => static fn( int $id ): array => [ 'title' => ExecutiveLeadership::title( $id ) ],
                'title_field'  => true,
                'title_label'  => 'Executive Leadership Title',
                'shortcode'    => ExecutiveLeadership::SHORTCODE,
                'block'        => 'smpi-leadership',
                'item'         => 'smpi-leader',
                'style'        => $settings['style'],
                'example_html' => $settings['enabled'] ? $this->example_html() : '',
            ]
        );
    }
}
