<?php

namespace smp_publication_integration\Admin;

use Hexa\PluginCore\WpAdminAjax\AjaxGuard;
use Hexa\PluginCore\SmartSearch\SmartSearchRenderer;
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
        ?>
        <div id="smpi-leadership-tab" class="hpc-ui smpi-leadership-admin">
            <style>
                .smpi-leadership-admin{max-width:900px}
                .smpi-leadership-admin section{margin:0 0 30px}
                .smpi-leadership-admin h3{font-size:16px;margin:0 0 6px}
                .smpi-leadership-admin p.lead{color:#646970;margin:0 0 12px}
                .smpi-leadership-admin .smpi-la-notice{font-size:13px;margin-top:8px;color:#00701a}
                .smpi-leadership-admin .smpi-la-notice.error{color:#b32d2e}
                .smpi-leadership-admin .smpi-la-team{max-width:760px;background:#fff;border:1px solid #dcdcde;border-radius:8px;overflow:hidden}
                .smpi-leadership-admin .smpi-la-team-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid #f0f0f1;background:#f9fafb}
                .smpi-leadership-admin .smpi-la-team-head strong{font-size:14px}
                .smpi-leadership-admin .smpi-la-count{font-size:12px;color:#646970;background:#fff;border:1px solid #dcdcde;border-radius:999px;padding:2px 10px}
                .smpi-leadership-admin .smpi-la-search{padding:14px 18px;border-bottom:1px solid #f0f0f1}
                .smpi-leadership-admin .smpi-la-search .hpc-field>span{display:block;font-size:12px;font-weight:600;margin-bottom:6px}
                .smpi-leadership-admin .smpi-la-search .hpc-smart-search-input{width:100%;height:40px;font-size:14px}
                .smpi-leadership-admin .smpi-la-search .hpc-smart-search-selected{display:none!important}
                .smpi-leadership-admin .smpi-la-people{list-style:none;margin:0;padding:0}
                .smpi-leadership-admin .smpi-la-person{display:grid;grid-template-columns:20px 52px minmax(0,1fr) auto;align-items:center;gap:14px;padding:14px 18px;border-bottom:1px solid #f0f0f1;background:#fff;transition:background .15s}
                .smpi-leadership-admin .smpi-la-person:last-child{border-bottom:0}
                .smpi-leadership-admin .smpi-la-person.is-dragging{opacity:.45}
                .smpi-leadership-admin .smpi-la-person.is-over{background:#f0f6fc}
                .smpi-leadership-admin .smpi-la-handle{cursor:grab;color:#a7aaad;user-select:none}.smpi-leadership-admin .smpi-la-handle:hover{color:#50575e}
                .smpi-leadership-admin .smpi-la-avatar{width:52px;height:52px;border-radius:50%;object-fit:cover;background:#f0f0f1}
                .smpi-leadership-admin .smpi-la-who{min-width:0}
                .smpi-leadership-admin .smpi-la-name{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}
                .smpi-leadership-admin .smpi-la-name strong{font-size:14px}
                .smpi-leadership-admin .smpi-la-meta{font-size:12px;color:#787c82;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                .smpi-leadership-admin .smpi-la-title{display:block;margin-top:8px}
                .smpi-leadership-admin .smpi-la-title span{display:block;font-size:11px;font-weight:600;letter-spacing:.03em;text-transform:uppercase;color:#646970;margin-bottom:4px}
                .smpi-leadership-admin .smpi-la-title input{width:100%;max-width:420px;height:34px}
                .smpi-leadership-admin .smpi-la-actions{display:flex;align-items:center;gap:4px}
                .smpi-leadership-admin .smpi-la-icon{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #dcdcde;border-radius:6px;background:#fff;color:#50575e;cursor:pointer;text-decoration:none;padding:0}
                .smpi-leadership-admin .smpi-la-icon:hover{border-color:#2271b1;color:#2271b1}
                .smpi-leadership-admin .smpi-la-icon.is-remove:hover{border-color:#d63638;color:#d63638}
                .smpi-leadership-admin .smpi-la-icon .dashicons{font-size:16px;width:16px;height:16px;pointer-events:none}
                .smpi-leadership-admin .smpi-la-empty{padding:28px 18px;text-align:center;color:#646970}
                .smpi-leadership-admin .smpi-la-team .smpi-la-notice{padding:0 18px 12px;margin:0}
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
                    <h3>People</h3>
                    <p class="lead">Drag to set the order of appearance. Adding someone here turns on "Is part of the executive leadership team" on their profile, and the title here is their Executive Leadership Title. Changes on a profile show here too.</p>
                    <div class="smpi-la-team">
                        <div class="smpi-la-team-head"><strong>Leadership team</strong><span class="smpi-la-count" data-role="count"><?php echo esc_html( count( $members ) . ( 1 === count( $members ) ? ' person' : ' people' ) ); ?></span></div>
                        <div class="smpi-la-search">
                            <?php
                            ( new SmartSearchRenderer() )->render(
                                [
                                    'id'          => 'smpi-leadership-user-search',
                                    'label'       => 'Add a person',
                                    'placeholder' => 'Type a name or email',
                                    'source'      => 'users',
                                    'min_chars'   => 2,
                                    'limit'       => 10,
                                ]
                            );
                            ?>
                        </div>
                        <ul class="smpi-la-people" data-role="people">
                            <?php foreach ( $members as $id ) : $user = get_userdata( $id ); if ( ! $user ) { continue; } ?>
                                <?php echo self::person_html( (int) $id, (string) $user->display_name, (string) $user->user_email, ExecutiveLeadership::title( (int) $id ), get_avatar_url( (int) $id, [ 'size' => 104 ] ), (string) get_edit_user_link( (int) $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endforeach; ?>
                        </ul>
                        <div class="smpi-la-empty" data-role="empty"<?php echo $members ? ' hidden' : ''; ?>>No one yet. Search above to add the first person.</div>
                        <div class="smpi-la-notice" data-role="people-notice" role="status" aria-live="polite"></div>
                    </div>
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
            function refresh(){ var n = list ? list.children.length : 0; var c = root.querySelector('[data-role="count"]'); if (c) c.textContent = n + (n === 1 ? ' person' : ' people'); var em = root.querySelector('[data-role="empty"]'); if (em) em.hidden = n > 0; }
            var tpl = <?php echo wp_json_encode( self::person_html( 0, '', '', '', '', '' ) ); ?>;
            document.addEventListener('hexa-search-selected', function(ev){
                if (!ev.detail || ev.detail.component_id !== 'smpi-leadership-user-search' || !list) return;
                var item = ev.detail.item || {}, id = String(item.id || '');
                var search = document.getElementById('smpi-leadership-user-search'), input = search && search.querySelector('.hpc-smart-search-input');
                if (input) input.value = '';
                if (!id) return;
                if (list.querySelector('.smpi-la-person[data-id="' + id + '"]')) { say('people-notice', (item.name || 'This person') + ' is already on the team.', true); return; }
                var holder = document.createElement('ul'); holder.innerHTML = tpl; var row = holder.firstElementChild;
                row.dataset.id = id;
                row.querySelector('.smpi-la-avatar').src = item.thumbnail || '';
                row.querySelector('.smpi-la-name strong').textContent = item.name || '';
                var roles = String(item.status || '').split(', ').filter(Boolean).map(function(r){ return r.charAt(0).toUpperCase() + r.slice(1); }).join(', ');
                row.querySelector('.smpi-la-meta').textContent = [roles, item.email].filter(Boolean).join(' · ');
                var st = search && search.querySelector('.hpc-smart-search-status'); if (st) st.textContent = 'Added ' + (item.name || '') + '.';
                row.querySelector('[data-role="profile"]').href = item.edit_url || '#';
                list.appendChild(row); refresh(); savePeople(); row.querySelector('[data-role="title"]').focus();
            });
            var dragged = null;
            if (list) {
                list.addEventListener('dragstart', function(e){ var li = e.target.closest('.smpi-la-person'); if (!li) return; dragged = li; li.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; });
                list.addEventListener('dragover', function(e){ var li = e.target.closest('.smpi-la-person'); if (!dragged || !li || li === dragged) return; e.preventDefault(); Array.prototype.forEach.call(list.children, function(x){ x.classList.remove('is-over'); }); li.classList.add('is-over'); var r = li.getBoundingClientRect(); list.insertBefore(dragged, (e.clientY - r.top) > r.height / 2 ? li.nextSibling : li); });
                list.addEventListener('dragend', function(){ if (!dragged) return; dragged.classList.remove('is-dragging'); Array.prototype.forEach.call(list.children, function(x){ x.classList.remove('is-over'); }); dragged = null; savePeople(); });
            }
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
                var btn = e.target.closest('[data-action]'); var action = btn ? btn.getAttribute('data-action') : ''; if (!action) return;
                var li = e.target.closest('.smpi-la-person');
                if (action === 'remove' && li) { li.remove(); refresh(); savePeople(); }
                if (action === 'up' && li && li.previousElementSibling) { list.insertBefore(li, li.previousElementSibling); savePeople(); }
                if (action === 'down' && li && li.nextElementSibling) { list.insertBefore(li.nextElementSibling, li); savePeople(); }
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

    /** One person row; also the template the script fills when someone is added. */
    public static function person_html( int $id, string $name, string $email, string $title, string $avatar, string $edit ): string {
        $user  = $id ? get_userdata( $id ) : null;
        $roles = $user instanceof \WP_User ? implode( ', ', array_map( 'ucfirst', (array) $user->roles ) ) : '';
        $meta  = implode( ' · ', array_filter( [ $roles, $email ] ) );
        return '<li class="smpi-la-person" draggable="true" data-id="' . esc_attr( (string) $id ) . '">'
            . '<span class="smpi-la-handle dashicons dashicons-menu" title="Drag to reorder" aria-hidden="true"></span>'
            . '<img class="smpi-la-avatar" src="' . esc_url( $avatar ) . '" alt="">'
            . '<div class="smpi-la-who"><div class="smpi-la-name"><strong>' . esc_html( $name ) . '</strong></div><div class="smpi-la-meta">' . esc_html( $meta ) . '</div>'
            . '<label class="smpi-la-title"><span>Executive Leadership Title</span><input type="text" data-role="title" value="' . esc_attr( $title ) . '" placeholder="For example: Editor-in-Chief"></label></div>'
            . '<div class="smpi-la-actions">'
            . '<button type="button" class="smpi-la-icon" data-action="up" title="Move up" aria-label="Move up"><span class="dashicons dashicons-arrow-up-alt2"></span></button>'
            . '<button type="button" class="smpi-la-icon" data-action="down" title="Move down" aria-label="Move down"><span class="dashicons dashicons-arrow-down-alt2"></span></button>'
            . '<a class="smpi-la-icon" data-role="profile" href="' . esc_url( $edit ) . '" target="_blank" rel="noopener" title="Open profile" aria-label="Open profile"><span class="dashicons dashicons-admin-users"></span></a>'
            . '<button type="button" class="smpi-la-icon is-remove" data-action="remove" title="Remove from the team" aria-label="Remove from the team"><span class="dashicons dashicons-no-alt"></span></button>'
            . '</div></li>';
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
