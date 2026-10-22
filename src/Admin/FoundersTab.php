<?php

namespace smp_publication_integration\Admin;

use Hexa\PluginCore\WpAdminAjax\AjaxGuard;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use smp_publication_integration\Admin\People\PeopleEditor;
use smp_publication_integration\Content\Founders;
use smp_publication_integration\Content\People\PeopleCards;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings, SMP, Founders: on/off switch, the section title (default "Founders"),
 * the people (user accounts, each with an optional title), style with a live example,
 * HTML structure and [smpi_founders]. Layout and behavior come from PeopleEditor.
 */
final class FoundersTab {
    public const TAB = 'founders_list';

    public function register(): void {
        add_filter( 'smpi_render_dashboard_tab', [ $this, 'maybe_render' ], 10, 2 );
        foreach ( [ 'toggle', 'heading', 'save_people', 'style' ] as $action ) {
            add_action( 'wp_ajax_smpi_founders_' . $action, [ $this, 'ajax_' . $action ] );
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
        Founders::update_settings( [ 'enabled' => $on ] );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => $on ? 'Founders is on.' : 'Founders is off; the shortcode prints nothing. People and titles are kept.' ] );
    }

    public function ajax_heading(): void {
        $this->guard();
        Founders::update_settings( [ 'heading' => sanitize_text_field( (string) wp_unslash( $_POST['heading'] ?? '' ) ) ] );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => 'Title saved.', 'example' => $this->example_html() ] );
    }

    public function ajax_save_people(): void {
        $this->guard();
        $people = json_decode( (string) wp_unslash( $_POST['people'] ?? '[]' ), true );
        $ids    = [];
        $titles = [];
        foreach ( is_array( $people ) ? $people : [] as $person ) {
            $id = absint( $person['id'] ?? 0 );
            if ( $id && get_userdata( $id ) ) {
                $ids[]         = $id;
                $titles[ $id ] = sanitize_text_field( (string) ( $person['title'] ?? '' ) );
            }
        }
        Founders::update_settings( [ 'ids' => $ids, 'titles' => $titles ] );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => count( $ids ) . ( 1 === count( $ids ) ? ' person is' : ' people are' ) . ' in the section.', 'example' => $this->example_html() ] );
    }

    public function ajax_style(): void {
        $this->guard();
        $style = sanitize_key( (string) ( $_POST['style'] ?? '' ) );
        if ( ! isset( PeopleCards::STYLES[ $style ] ) ) {
            wp_send_json_error( [ 'message' => 'Unknown style.' ] );
        }
        Founders::update_settings( [ 'style' => $style ] );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => 'Style saved.', 'example' => $this->example_html() ] );
    }

    private function example_html(): string {
        $html = Founders::render();
        return '' !== $html ? $html : '<p class="description">Add someone above to see the example.</p>';
    }

    private function render(): void {
        $settings = Founders::settings();
        $nonce    = Ajax::nonce();
        ob_start();
        ?>
        <section>
            <h3>Founders</h3>
            <p class="lead">A section of the people who founded or run the publication. Each person's photo, name and short bio come from their user profile; the title is set here.</p>
            <?php echo CoreUi::toggle( 'smpi_founders_enabled', $settings['enabled'], 'Founders is on', [ 'id' => 'smpi-fo-enabled' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="smpi-pe-notice" data-role="toggle-notice" role="status" aria-live="polite"></div>
        </section>
        <?php if ( $settings['enabled'] ) : ?>
        <section>
            <h3>Section title</h3>
            <p class="lead">Shown above the people. "Founders" by default; it can be "Executives", "Our founders" or anything else. Leave it empty for no title.</p>
            <input type="text" class="regular-text" id="smpi-fo-heading" value="<?php echo esc_attr( $settings['heading'] ); ?>" placeholder="Founders" style="max-width:420px;height:36px">
            <div class="smpi-pe-notice" data-role="heading-notice" role="status" aria-live="polite"></div>
        </section>
        <?php endif; ?>
        <script>
        (function(){
            var root = document.getElementById('smpi-founders-tab'); if (!root || root.dataset.topReady) return; root.dataset.topReady = '1';
            function send(action, fields, role, reload){
                var note = root.querySelector('[data-role="' + role + '"]'); note.className = 'smpi-pe-notice'; note.textContent = 'Saving…';
                var body = new URLSearchParams(); body.set('action', 'smpi_founders_' + action); body.set('nonce', <?php echo wp_json_encode( $nonce ); ?>);
                Object.keys(fields).forEach(function(k){ body.set(k, fields[k]); });
                return fetch(window.ajaxurl, { method:'POST', credentials:'same-origin', body: body }).then(function(r){ return r.json(); }).then(function(p){
                    if (!p || !p.success) throw new Error(p && p.data && p.data.message ? p.data.message : 'Could not save.');
                    note.textContent = p.data.message; if (p.data.example) { var e = root.querySelector('[data-role="example"]'); if (e) e.innerHTML = p.data.example; }
                    if (reload) setTimeout(function(){ location.reload(); }, 900);
                }).catch(function(err){ note.className = 'smpi-pe-notice error'; note.textContent = err.message; });
            }
            var toggle = document.getElementById('smpi-fo-enabled');
            toggle.addEventListener('change', function(){ send('toggle', { on: toggle.checked ? '1' : '0' }, 'toggle-notice', true); });
            var heading = document.getElementById('smpi-fo-heading'), t = null;
            if (heading) heading.addEventListener('input', function(){ clearTimeout(t); t = setTimeout(function(){ send('heading', { heading: heading.value }, 'heading-notice', false); }, 700); });
        })();
        </script>
        <?php
        $top = (string) ob_get_clean();

        PeopleEditor::render(
            [
                'id'           => 'smpi-founders-tab',
                'ajax_prefix'  => 'smpi_founders_',
                'top_html'     => $top,
                'show_body'    => $settings['enabled'],
                'page'         => null,
                'list_label'   => '' !== $settings['heading'] ? $settings['heading'] : 'Founders',
                'people_intro' => 'Search for a user account to add it. Drag to set the order. The title is optional, for example "Founder & CEO".',
                'empty'        => 'No one yet. Search above to add the first person.',
                'members'      => $settings['enabled'] ? Founders::member_ids() : [],
                'row'          => static fn( int $id ): array => [ 'title' => Founders::title( $id ) ],
                'title_field'  => true,
                'title_label'  => 'Founder Title',
                'shortcode'    => Founders::SHORTCODE,
                'block'        => 'smpi-founders',
                'item'         => 'smpi-founder',
                'style'        => $settings['style'],
                'example_html' => $settings['enabled'] ? $this->example_html() : '',
                'attributes'   => [
                    'section_title="Executives"' => 'uses another section title for this one list; section_title="none" leaves it out.',
                    'section_tag="h2"'           => 'tag for the section title: h2, h3, h4 or p.',
                ],
            ]
        );
    }
}
