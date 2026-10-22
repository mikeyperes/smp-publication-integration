<?php

namespace smp_publication_integration\Admin;

use Hexa\PluginCore\WpAdminAjax\AjaxGuard;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use smp_publication_integration\Content\ExecutiveLeadership;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings, SMP, Executive Leadership: the on/off switch (which creates or links the
 * leadership page), the people and their titles, the style with a live example, the CSS
 * structure and the shortcode. Choosing someone here sets the same toggle and title
 * that their user profile shows.
 */
final class ExecutiveLeadershipTab {
    public const TAB = 'executive_leadership';

    public function register(): void {
        add_filter( 'smpi_render_dashboard_tab', [ $this, 'maybe_render' ], 10, 2 );
        foreach ( [ 'toggle', 'save_people', 'style', 'apply_template' ] as $action ) {
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
        $people = is_array( $people ) ? $people : [];
        $order  = [];
        foreach ( $people as $person ) {
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
        if ( ! isset( ExecutiveLeadership::STYLES[ $style ] ) ) {
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
        wp_send_json_success( [ 'message' => 'The page now uses the default template. Its previous content is saved as a revision.', 'reload' => true ] );
    }

    private function example_html(): string {
        $html = ExecutiveLeadership::render();
        return '' !== $html ? $html : '<p class="description">Add someone above to see the example.</p>';
    }

    private function render(): void {
        $settings = ExecutiveLeadership::settings();
        $nonce    = Ajax::nonce();
        $members  = ExecutiveLeadership::member_ids();
        $users    = get_users( [ 'role__not_in' => [ 'subscriber' ], 'orderby' => 'display_name', 'fields' => [ 'ID', 'display_name' ] ] );
        ?>
        <div id="smpi-leadership-tab" class="hpc-ui smpi-leadership-admin">
            <style>
                .smpi-leadership-admin{max-width:900px}
                .smpi-leadership-admin section{margin:0 0 30px}
                .smpi-leadership-admin h3{font-size:16px;margin:0 0 6px}
                .smpi-leadership-admin p.lead{color:#646970;margin:0 0 12px}
                .smpi-leadership-admin .smpi-la-notice{font-size:13px;margin-top:8px;color:#00701a}
                .smpi-leadership-admin .smpi-la-notice.error{color:#b32d2e}
                .smpi-leadership-admin .smpi-la-people{list-style:none;margin:0 0 12px;padding:0;max-width:640px}
                .smpi-leadership-admin .smpi-la-person{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f0f0f1}
                .smpi-leadership-admin .smpi-la-person img{width:40px;height:40px;border-radius:50%;object-fit:cover}
                .smpi-leadership-admin .smpi-la-person strong{min-width:150px}
                .smpi-leadership-admin .smpi-la-person input{flex:1;min-width:0}
                .smpi-leadership-admin .smpi-la-add{display:flex;gap:10px;align-items:center;max-width:640px}
                .smpi-leadership-admin .smpi-la-add select{flex:1}
                .smpi-leadership-admin .smpi-la-styles{list-style:none;margin:0;padding:0}
                .smpi-leadership-admin .smpi-la-styles li{margin:6px 0}
                .smpi-leadership-admin .smpi-la-example{border:1px solid #dcdcde;border-radius:6px;padding:24px;background:#fff;margin-top:12px}
                .smpi-leadership-admin pre{background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:12px;font-size:12px;overflow:auto;white-space:pre-wrap}
                .smpi-leadership-admin .smpi-la-attrs{list-style:none;margin:0;padding:0;max-width:760px}
                .smpi-leadership-admin .smpi-la-attrs li{padding:7px 0;border-bottom:1px solid #f0f0f1}
                .smpi-leadership-admin .smpi-la-page{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
            </style>

            <section>
                <h3>Executive Leadership</h3>
                <p class="lead">Turn on to create the Executive Leadership Team page from the default template (or link the one the site already has) and to add the leadership toggle and title to every user profile. Off hides those profile fields and the list.</p>
                <?php echo CoreUi::toggle( 'smpi_leadership_enabled', $settings['enabled'], 'Executive Leadership is on', [ 'id' => 'smpi-la-enabled' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <div class="smpi-la-notice" data-role="notice" role="status" aria-live="polite"></div>
            </section>

            <?php if ( $settings['enabled'] ) : ?>
                <?php
                $page_id  = (int) ExecutiveLeadership::ensure_page()['id'];
                $has_list = $page_id && ExecutiveLeadership::page_has_list( $page_id );
                ?>
                <section>
                    <h3>Page</h3>
                    <?php if ( $page_id ) : ?>
                        <div class="smpi-la-page">
                            <a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_permalink( $page_id ) ); ?></a>
                            <a class="hpc-button secondary" href="<?php echo esc_url( get_edit_post_link( $page_id, 'raw' ) ); ?>" target="_blank" rel="noopener">Edit page</a>
                        </div>
                        <?php if ( $has_list ) : ?>
                            <p>✓ The page shows the leadership list.</p>
                        <?php else : ?>
                            <p>This page does not show the leadership list yet<?php echo 'builder' === get_post_meta( $page_id, '_elementor_edit_mode', true ) ? ' (it is built in Elementor)' : ''; ?>. Add <code>[<?php echo esc_html( ExecutiveLeadership::SHORTCODE ); ?>]</code> to it, or replace its content with the default template. The current content is kept as a revision.</p>
                            <button type="button" class="hpc-button" data-action="apply-template">Use the default template on this page</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>

                <section>
                    <h3>Leadership team</h3>
                    <p class="lead">In order of appearance. Adding someone here turns on "Is part of the executive leadership team" on their profile, and the title here is their Executive Leadership Title. Changes on a profile show here too.</p>
                    <ul class="smpi-la-people" data-role="people">
                        <?php foreach ( $members as $id ) : $user = get_userdata( $id ); if ( ! $user ) { continue; } ?>
                            <li class="smpi-la-person" data-id="<?php echo esc_attr( (string) $id ); ?>">
                                <img src="<?php echo esc_url( get_avatar_url( $id, [ 'size' => 80 ] ) ); ?>" alt="">
                                <strong><?php echo esc_html( $user->display_name ); ?></strong>
                                <input type="text" class="regular-text" data-role="title" value="<?php echo esc_attr( ExecutiveLeadership::title( $id ) ); ?>" placeholder="Executive Leadership Title">
                                <button type="button" class="button" data-action="up" title="Move up">↑</button>
                                <button type="button" class="button" data-action="down" title="Move down">↓</button>
                                <button type="button" class="button-link-delete" data-action="remove">Remove</button>
                                <a href="<?php echo esc_url( get_edit_user_link( $id ) ); ?>" target="_blank" rel="noopener">Profile</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="smpi-la-add">
                        <select data-role="pick">
                            <option value="">Choose a person to add…</option>
                            <?php foreach ( $users as $user ) : ?>
                                <option value="<?php echo esc_attr( (string) $user->ID ); ?>" data-avatar="<?php echo esc_url( get_avatar_url( (int) $user->ID, [ 'size' => 80 ] ) ); ?>" data-edit="<?php echo esc_url( get_edit_user_link( (int) $user->ID ) ); ?>"><?php echo esc_html( $user->display_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="hpc-button" data-action="add">Add</button>
                    </div>
                    <div class="smpi-la-notice" data-role="people-notice" role="status" aria-live="polite"></div>
                </section>

                <section>
                    <h3>Style</h3>
                    <ul class="smpi-la-styles">
                        <?php foreach ( ExecutiveLeadership::STYLES as $value => $label ) : ?>
                            <li><label><input type="radio" name="smpi_leadership_style" value="<?php echo esc_attr( $value ); ?>"<?php checked( $settings['style'], $value ); ?>> <?php echo esc_html( $label ); ?></label></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="smpi-la-notice" data-role="style-notice" role="status" aria-live="polite"></div>
                    <p class="lead" style="margin-top:14px">Example, with the people above:</p>
                    <div class="smpi-la-example" data-role="example"><?php echo $this->example_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                </section>

                <section>
                    <h3>CSS structure</h3>
                    <p class="lead">Style it with these classes in the site's CSS or an Elementor widget's Custom CSS. "No styling" prints the same markup without the built-in CSS.</p>
                    <pre><?php echo esc_html( self::structure() ); ?></pre>
                    <details><summary>Built-in CSS</summary><pre><?php echo esc_html( str_replace( '}', "}\n", ExecutiveLeadership::css() ) ); ?></pre></details>
                </section>

                <section>
                    <h3>Shortcode</h3>
                    <p class="lead">Place it on any page. With no attributes it shows everyone above, in order, in the chosen style.</p>
                    <p><code>[<?php echo esc_html( ExecutiveLeadership::SHORTCODE ); ?>]</code> <?php echo CoreUi::copy_button( '[' . ExecutiveLeadership::SHORTCODE . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                    <ul class="smpi-la-attrs">
                        <li><code>style="cards|list|none"</code> overrides the style chosen above.</li>
                        <li><code>columns="3"</code> people per row in Cards (1 to 6; fewer on tablets and phones).</li>
                        <li><code>ids="12,7"</code> shows only these users, in this order (user IDs).</li>
                        <li><code>limit="4"</code> shows only the first people.</li>
                        <li><code>hide="photo,title,bio,link"</code> leaves those parts out (any of them, comma separated).</li>
                        <li><code>link="none"</code> stops names and photos linking to the person's author page.</li>
                        <li><code>bio_words="30"</code> length of the short bio from the profile's Biographical Info; 0 hides it.</li>
                        <li><code>photo_size="medium"</code> image size: thumbnail, medium, medium_large, large.</li>
                        <li><code>heading="h3"</code> tag for each name: h2, h3, h4 or p.</li>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
        <script>
        (function(){
            var root = document.getElementById('smpi-leadership-tab');
            if (!root || root.dataset.ready) return; root.dataset.ready = '1';
            var nonce = <?php echo wp_json_encode( $nonce ); ?>;
            function post(action, fields){
                var body = new URLSearchParams(); body.set('action', 'smpi_leadership_' + action); body.set('nonce', nonce);
                Object.keys(fields || {}).forEach(function(k){ body.set(k, fields[k]); });
                return fetch(window.ajaxurl, { method:'POST', credentials:'same-origin', body: body })
                    .then(function(r){ return r.json(); })
                    .then(function(p){ if (!p || !p.success) throw new Error(p && p.data && p.data.message ? p.data.message : 'Could not save.'); return p.data; });
            }
            function say(role, text, error){ var n = root.querySelector('[data-role="' + role + '"]'); if (!n) return; n.className = 'smpi-la-notice' + (error ? ' error' : ''); n.textContent = text; }
            function example(html){ var e = root.querySelector('[data-role="example"]'); if (e && typeof html === 'string') e.innerHTML = html; }
            var list = root.querySelector('[data-role="people"]');
            function people(){ return list ? Array.prototype.map.call(list.querySelectorAll('.smpi-la-person'), function(li){ return { id: li.dataset.id, title: li.querySelector('[data-role="title"]').value }; }) : []; }
            var timer = null;
            function savePeople(){ clearTimeout(timer); say('people-notice', 'Saving…'); post('save_people', { people: JSON.stringify(people()) }).then(function(d){ say('people-notice', d.message); example(d.example); }).catch(function(e){ say('people-notice', e.message, true); }); }

            root.addEventListener('change', function(e){
                if (e.target.id === 'smpi-la-enabled') {
                    say('notice', 'Saving…');
                    post('toggle', { on: e.target.checked ? '1' : '0' }).then(function(d){ say('notice', d.message); if (d.reload) setTimeout(function(){ location.reload(); }, 1200); }).catch(function(err){ say('notice', err.message, true); e.target.checked = !e.target.checked; });
                }
                if (e.target.name === 'smpi_leadership_style') {
                    say('style-notice', 'Saving…');
                    post('style', { style: e.target.value }).then(function(d){ say('style-notice', d.message); example(d.example); }).catch(function(err){ say('style-notice', err.message, true); });
                }
            });
            root.addEventListener('input', function(e){ if (e.target.dataset.role === 'title') { clearTimeout(timer); timer = setTimeout(savePeople, 800); } });
            root.addEventListener('click', function(e){
                var action = e.target.getAttribute('data-action'); if (!action) return;
                var li = e.target.closest('.smpi-la-person');
                if (action === 'remove' && li) { li.remove(); savePeople(); }
                if (action === 'up' && li && li.previousElementSibling) { list.insertBefore(li, li.previousElementSibling); savePeople(); }
                if (action === 'down' && li && li.nextElementSibling) { list.insertBefore(li.nextElementSibling, li); savePeople(); }
                if (action === 'add') {
                    var pick = root.querySelector('[data-role="pick"]'), opt = pick.options[pick.selectedIndex];
                    if (!pick.value) { say('people-notice', 'Choose a person first.', true); return; }
                    if (list.querySelector('[data-id="' + pick.value + '"]')) { say('people-notice', opt.text + ' is already on the team.', true); return; }
                    var row = document.createElement('li'); row.className = 'smpi-la-person'; row.dataset.id = pick.value;
                    row.innerHTML = '<img alt=""><strong></strong><input type="text" class="regular-text" data-role="title" placeholder="Executive Leadership Title"><button type="button" class="button" data-action="up" title="Move up">↑</button><button type="button" class="button" data-action="down" title="Move down">↓</button><button type="button" class="button-link-delete" data-action="remove">Remove</button><a target="_blank" rel="noopener">Profile</a>';
                    row.querySelector('img').src = opt.dataset.avatar; row.querySelector('strong').textContent = opt.text; row.querySelector('a').href = opt.dataset.edit;
                    list.appendChild(row); pick.value = ''; savePeople(); row.querySelector('input').focus();
                }
                if (action === 'apply-template') {
                    if (!window.confirm('Replace this page\'s content with the default template? The current content is kept as a revision.')) return;
                    say('notice', 'Updating the page…');
                    post('apply_template', {}).then(function(d){ say('notice', d.message); setTimeout(function(){ location.reload(); }, 1200); }).catch(function(err){ say('notice', err.message, true); });
                }
            });
        })();
        </script>
        <?php
    }

    public static function structure(): string {
        return <<<'HTML'
<div class="smpi-leadership smpi-leadership--cards" style="--smpi-leadership-columns:3">
  <article class="smpi-leader smpi-leader--{user-slug}">
    <a class="smpi-leader__photo" href="{author page}"><img class="smpi-leader__image" src="…" alt="{Name}"></a>
    <div class="smpi-leader__body">
      <h3 class="smpi-leader__name"><a href="{author page}">{Name}</a></h3>
      <p class="smpi-leader__title">{Executive Leadership Title}</p>
      <p class="smpi-leader__bio">{short bio}</p>
      <a class="smpi-leader__link" href="{author page}">View profile</a>
    </div>
  </article>
  …
</div>
HTML;
    }
}
