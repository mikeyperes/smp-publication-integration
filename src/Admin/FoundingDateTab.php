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
 * founding date, place, place link and story (the publication's own fields, also used by
 * the structured data), a live example, the HTML structure and [smpi_founding_date].
 */
final class FoundingDateTab {
    public const TAB = 'founding_date';

    public function register(): void {
        add_filter( 'smpi_render_dashboard_tab', [ $this, 'maybe_render' ], 10, 2 );
        foreach ( [ 'toggle', 'save' ] as $action ) {
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

    public function ajax_save(): void {
        $this->guard();
        $raw  = trim( sanitize_text_field( (string) wp_unslash( $_POST['date'] ?? '' ) ) );
        $date = FoundingDate::normalize_date( $raw );
        if ( '' !== $raw && '' === $date ) {
            wp_send_json_error( [ 'message' => 'The date was not understood. Pick a full date.' ] );
        }
        FoundingDate::update_settings( [ 'heading' => sanitize_text_field( (string) wp_unslash( $_POST['heading'] ?? '' ) ) ] );
        FoundingDate::save_values(
            [
                'date'      => $date,
                'place'     => sanitize_text_field( (string) wp_unslash( $_POST['place'] ?? '' ) ),
                'place_url' => esc_url_raw( (string) wp_unslash( $_POST['place_url'] ?? '' ) ),
                'story'     => wp_kses_post( (string) wp_unslash( $_POST['story'] ?? '' ) ),
            ]
        );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => 'Saved.', 'example' => $this->example_html() ] );
    }

    private function example_html(): string {
        $html = FoundingDate::render();
        return '' !== $html ? $html : '<p class="description">Fill in the date or place above to see the example.</p>';
    }

    private function render(): void {
        $settings = FoundingDate::settings();
        $values   = FoundingDate::values();
        $nonce    = Ajax::nonce();
        ?>
        <div id="smpi-founding-tab" class="hpc-ui smpi-pe">
            <?php PeopleEditor::styles(); ?>
            <style>
                #smpi-founding-tab .smpi-fd-field{display:block;margin:0 0 16px;max-width:620px}
                #smpi-founding-tab .smpi-fd-field>span{display:block;font-size:12px;font-weight:600;margin-bottom:6px}
                #smpi-founding-tab .smpi-fd-field input{width:100%;height:36px}
                #smpi-founding-tab .smpi-fd-field textarea{width:100%;min-height:140px}
                #smpi-founding-tab .smpi-fd-field small{display:block;color:#646970;margin-top:4px}
            </style>
            <section>
                <h3>Founding Date</h3>
                <p class="lead">A section that says when and where the publication was founded, with its founding story. The same date and place feed the publication's structured data for search engines.</p>
                <?php echo CoreUi::toggle( 'smpi_founding_enabled', $settings['enabled'], 'Founding Date is on', [ 'id' => 'smpi-fd-enabled' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <div class="smpi-pe-notice" data-role="toggle-notice" role="status" aria-live="polite"></div>
            </section>
            <?php if ( $settings['enabled'] ) : ?>
            <section>
                <h3>Details</h3>
                <p class="lead">Saved as you type.</p>
                <label class="smpi-fd-field"><span>Section title</span><input type="text" data-field="heading" value="<?php echo esc_attr( $settings['heading'] ); ?>" placeholder="Founded"><small>"Founded" by default. Leave it empty for no title.</small></label>
                <label class="smpi-fd-field"><span>Founding date</span><input type="date" data-field="date" value="<?php echo esc_attr( FoundingDate::normalize_date( $values['date'] ) ); ?>" style="max-width:220px"></label>
                <label class="smpi-fd-field"><span>Founded in</span><input type="text" data-field="place" value="<?php echo esc_attr( $values['place'] ); ?>" placeholder="New York, New York, United States"></label>
                <label class="smpi-fd-field"><span>Place link (optional)</span><input type="url" data-field="place_url" value="<?php echo esc_attr( $values['place_url'] ); ?>" placeholder="https://en.wikipedia.org/wiki/New_York_City"></label>
                <label class="smpi-fd-field"><span>Founding story</span><textarea data-field="story" placeholder="Why and how the publication started."><?php echo esc_textarea( $values['story'] ); ?></textarea></label>
                <div class="smpi-pe-notice" data-role="save-notice" role="status" aria-live="polite"></div>
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
            var queue = Promise.resolve(), timer = null;
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
            root.querySelectorAll('[data-field]').forEach(function(i){ i.addEventListener('input', function(){ clearTimeout(timer); timer = setTimeout(save, 800); }); if (i.type === 'date') i.addEventListener('change', save); });
        })();
        </script>
        <?php
    }
}
