<?php

namespace smp_publication_integration\Admin;

use Hexa\PluginCore\WpAdminAjax\AjaxGuard;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use smp_publication_integration\Admin\People\PeopleEditor;
use smp_publication_integration\Content\FoundingDate;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings, SMP, Founding Date: on/off switch, section title (default "Founded"), the
 * founding values as read from Publication Settings (edited there, not here), a live
 * example, the HTML structure and [smpi_founding_date]. The page itself is on the Pages tab.
 */
final class FoundingDateTab {
    public const TAB = 'founding_date';

    public function register(): void {
        add_filter( 'smpi_render_dashboard_tab', [ $this, 'maybe_render' ], 10, 2 );
        foreach ( [ 'toggle', 'heading' ] as $action ) {
            add_action( 'wp_ajax_smpi_founding_' . $action, [ $this, 'ajax_' . $action ] );
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
        FoundingDate::update_settings( [ 'enabled' => $on ] );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => $on ? 'Founding Date is on.' : 'Founding Date is off; the shortcode prints nothing. The details are kept.' ] );
    }

    public function ajax_heading(): void {
        $this->guard();
        FoundingDate::update_settings( [ 'heading' => sanitize_text_field( (string) wp_unslash( $_POST['heading'] ?? '' ) ) ] );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => 'Title saved.', 'example' => $this->example_html() ] );
    }

    private function example_html(): string {
        $html = FoundingDate::render();
        return '' !== $html ? $html : '<p class="description">Fill in the date or place above to see the example.</p>';
    }

    private function render(): void {
        $settings = FoundingDate::settings();
        $values   = FoundingDate::values();
        $nonce    = Ajax::nonce();
        $edit     = admin_url( 'options-general.php?page=smp-publication-integration&tab=publication_options' );
        $pages    = admin_url( 'options-general.php?page=smp-publication-integration&tab=pages' );
        ?>
        <div id="smpi-founding-tab" class="hpc-ui smpi-pe">
            <?php PeopleEditor::styles(); ?>
            <section>
                <h3>Founding Date</h3>
                <p class="lead">A section that says when and where the publication was founded, with its founding story. The same date and place feed the publication's structured data for search engines.</p>
                <?php echo CoreUi::toggle( 'smpi_founding_enabled', $settings['enabled'], 'Founding Date is on', [ 'id' => 'smpi-fd-enabled' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <div class="smpi-pe-notice" data-role="toggle-notice" role="status" aria-live="polite"></div>
            </section>
            <?php if ( $settings['enabled'] ) : ?>
            <section>
                <h3>Section title</h3>
                <p class="lead">"Founded" by default. Leave it empty for no title.</p>
                <input type="text" class="regular-text" id="smpi-fd-heading" value="<?php echo esc_attr( $settings['heading'] ); ?>" placeholder="Founded" style="max-width:420px;height:36px">
                <div class="smpi-pe-notice" data-role="heading-notice" role="status" aria-live="polite"></div>
            </section>
            <section>
                <h3>Founding details</h3>
                <p class="lead">Read from the publication's own fields on Publication Settings, the same ones the structured data uses. Edit them there.</p>
                <ul class="smpi-pe-attrs">
                    <li><strong>Founding Date:</strong> <?php echo esc_html( '' !== $values['date'] ? FoundingDate::display_date( $values['date'] ) : 'empty' ); ?></li>
                    <li><strong>foundingLocation:</strong> <?php echo esc_html( '' !== $values['place'] ? $values['place'] : 'empty' ); ?></li>
                    <li><strong>foundingLocation URL:</strong> <?php echo esc_html( '' !== $values['place_url'] ? $values['place_url'] : 'empty' ); ?></li>
                    <li><strong>Founding Date Extended (story):</strong> <?php echo esc_html( '' !== $values['story'] ? wp_trim_words( wp_strip_all_tags( $values['story'] ), 20 ) : 'empty' ); ?></li>
                </ul>
                <p><a class="hpc-button secondary" href="<?php echo esc_url( $edit ); ?>">Edit on Publication Settings</a> <a class="hpc-button secondary" href="<?php echo esc_url( $pages ); ?>">Founding Date page on the Pages tab</a></p>
            </section>
            <section>
                <h3>Example</h3>
                <div class="smpi-pe-example" data-role="example"><?php echo $this->example_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            </section>
            <section>
                <h3>CSS structure</h3>
                <p class="lead">Style it with these classes in the site's CSS or an Elementor widget's Custom CSS. <code>style="none"</code> prints the same markup without the built-in CSS.</p>
                <pre><?php echo esc_html( FoundingDate::structure() ); ?></pre>
                <details><summary>Built-in CSS</summary><pre><?php echo esc_html( str_replace( '}', "}\n", FoundingDate::css() ) ); ?></pre></details>
            </section>
            <section>
                <h3>Shortcode</h3>
                <p class="lead">Place it on any page. With no attributes it shows the date, the place and the story.</p>
                <p><code>[<?php echo esc_html( FoundingDate::SHORTCODE ); ?>]</code> <?php echo CoreUi::copy_button( '[' . FoundingDate::SHORTCODE . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                <ul class="smpi-pe-attrs">
                    <li><code>show="date,place,story"</code> which parts to show, in any combination.</li>
                    <li><code>date_format="F Y"</code> how the date is written, for example "F Y" for the month and year or "Y" for the year only. Default: F j, Y.</li>
                    <li><code>section_title="Our story"</code> another section title for this one use; <code>none</code> leaves it out.</li>
                    <li><code>section_tag="h2"</code> tag for the section title: h2, h3, h4 or p.</li>
                    <li><code>style="none"</code> no built-in CSS.</li>
                </ul>
            </section>
            <?php endif; ?>
        </div>
        <script>
        (function(){
            var root = document.getElementById('smpi-founding-tab'); if (!root || root.dataset.ready) return; root.dataset.ready = '1';
            var timer = null;
            function send(action, fields, role, reload){
                var note = root.querySelector('[data-role="' + role + '"]'); note.className = 'smpi-pe-notice'; note.textContent = 'Saving…';
                var body = new URLSearchParams(); body.set('action', 'smpi_founding_' + action); body.set('nonce', <?php echo wp_json_encode( $nonce ); ?>);
                Object.keys(fields).forEach(function(k){ body.set(k, fields[k]); });
                return fetch(window.ajaxurl, { method:'POST', credentials:'same-origin', body: body }).then(function(r){ return r.json(); }).then(function(p){
                    if (!p || !p.success) throw new Error(p && p.data && p.data.message ? p.data.message : 'Could not save.');
                    note.textContent = p.data.message; if (p.data.example) { var e = root.querySelector('[data-role="example"]'); if (e) e.innerHTML = p.data.example; }
                    if (reload) setTimeout(function(){ location.reload(); }, 900);
                }).catch(function(err){ note.className = 'smpi-pe-notice error'; note.textContent = err.message; });
            }
            var toggle = document.getElementById('smpi-fd-enabled');
            toggle.addEventListener('change', function(){ send('toggle', { on: toggle.checked ? '1' : '0' }, 'toggle-notice', true); });
            function save(){ var f = {}; root.querySelectorAll('[data-field]').forEach(function(i){ f[i.dataset.field] = i.value; }); queue = queue.then(function(){ return send('save', f, 'save-notice', false); }); }
            var heading = document.getElementById('smpi-fd-heading');
            if (heading) heading.addEventListener('input', function(){ clearTimeout(timer); timer = setTimeout(function(){ send('heading', { heading: heading.value }, 'heading-notice', false); }, 700); });
        })();
        </script>
        <?php
    }
}
