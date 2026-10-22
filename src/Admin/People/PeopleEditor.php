<?php

namespace smp_publication_integration\Admin\People;

use Hexa\PluginCore\SmartSearch\SmartSearchRenderer;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Content\People\PeopleCards;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The admin screen shared by the publication's people lists (Executive Leadership,
 * Contributors): page, people (Hexa WP Core user search, drag to order, remove),
 * style with a live example, CSS structure and shortcode. Each tab supplies its
 * own texts, AJAX prefix and row details; this class owns the layout and behavior.
 */
final class PeopleEditor {
    /**
     * @param array<string,mixed> $c Tab configuration:
     *   id, ajax_prefix, list_label, people_intro, empty, shortcode, block, item, title_label,
     *   style, example_html, members (int[]), row (callable(int):array{title?:string,badge?:string}),
     *   title_field (bool), removed (int[]), page (array{id,has_list,paths_note}), page_label,
     *   attributes (array<string,string>), extra_sections_html (string), top_html (string)
     */
    public static function render( array $c ): void {
        $members = (array) $c['members'];
        $removed = (array) ( $c['removed'] ?? [] );
        $count   = count( $members );
        ?>
        <div id="<?php echo esc_attr( $c['id'] ); ?>" class="hpc-ui smpi-pe"<?php echo ! empty( $c['reload_on_remove'] ) ? ' data-reload-on-remove="1"' : ''; ?>>
            <?php self::styles(); ?>
            <?php echo $c['top_html'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <?php if ( ! empty( $c['show_body'] ) ) : ?>
            <?php if ( null !== ( $c['page'] ?? null ) ) : ?>
            <section>
                <h3>Page</h3>
                <?php $page = (array) $c['page']; ?>
                <?php if ( ! empty( $page['id'] ) ) : ?>
                    <div class="smpi-pe-page">
                        <a href="<?php echo esc_url( get_permalink( (int) $page['id'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_permalink( (int) $page['id'] ) ); ?></a>
                        <a class="hpc-button secondary" href="<?php echo esc_url( get_edit_post_link( (int) $page['id'], 'raw' ) ); ?>" target="_blank" rel="noopener">Edit page</a>
                    </div>
                    <?php if ( ! empty( $page['has_list'] ) ) : ?>
                        <p class="smpi-pe-ok">✓ The page shows the list.</p>
                    <?php else : ?>
                        <p>This page does not show the list yet<?php echo 'builder' === get_post_meta( (int) $page['id'], '_elementor_edit_mode', true ) ? ' (it is built in Elementor)' : ''; ?>. Add <code>[<?php echo esc_html( $c['shortcode'] ); ?>]</code> to it, or replace its content with the default template. The current content is kept as a revision.</p>
                        <button type="button" class="hpc-button" data-action="apply-template">Use the default template on this page</button>
                    <?php endif; ?>
                <?php else : ?>
                    <p>There is no <?php echo esc_html( $c['page_label'] ); ?> page yet.</p>
                    <button type="button" class="hpc-button" data-action="create-page">Create the <?php echo esc_html( $c['page_label'] ); ?> page</button>
                <?php endif; ?>
                <div class="smpi-pe-notice" data-role="page-notice" role="status" aria-live="polite"></div>
            </section>
            <?php endif; ?>

            <?php echo $c['extra_sections_html'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <section>
                <h3>People</h3>
                <p class="lead"><?php echo esc_html( $c['people_intro'] ); ?></p>
                <div class="smpi-pe-team">
                    <div class="smpi-pe-head"><strong><?php echo esc_html( $c['list_label'] ); ?></strong><span class="smpi-pe-count" data-role="count"><?php echo esc_html( $count . ( 1 === $count ? ' person' : ' people' ) ); ?></span></div>
                    <div class="smpi-pe-search">
                        <?php
                        ( new SmartSearchRenderer() )->render(
                            [
                                'id'          => $c['id'] . '-search',
                                'label'       => 'Add a person',
                                'placeholder' => 'Type a name or email',
                                'source'      => 'users',
                                'min_chars'   => 2,
                                'limit'       => 10,
                            ]
                        );
                        ?>
                    </div>
                    <ul class="smpi-pe-people" data-role="people">
                        <?php foreach ( $members as $id ) : ?>
                            <?php echo self::person_html( (int) $id, (array) call_user_func( $c['row'], (int) $id ), ! empty( $c['title_field'] ), (string) ( $c['title_label'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </ul>
                    <div class="smpi-pe-empty" data-role="empty"<?php echo $count ? ' hidden' : ''; ?>><?php echo esc_html( $c['empty'] ); ?></div>
                    <?php if ( $removed ) : ?>
                        <details class="smpi-pe-removed">
                            <summary><?php echo esc_html( count( $removed ) . ' removed from the list' ); ?></summary>
                            <ul>
                                <?php foreach ( $removed as $id ) : $u = get_userdata( (int) $id ); if ( ! $u ) { continue; } ?>
                                    <li data-id="<?php echo esc_attr( (string) $id ); ?>"><img src="<?php echo esc_url( get_avatar_url( (int) $id, [ 'size' => 64 ] ) ); ?>" alt=""><span><?php echo esc_html( $u->display_name ); ?></span><button type="button" class="button-link" data-action="restore">Put back</button></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                    <div class="smpi-pe-notice" data-role="people-notice" role="status" aria-live="polite"></div>
                </div>
            </section>

            <section>
                <h3>Style</h3>
                <ul class="smpi-pe-styles">
                    <?php foreach ( PeopleCards::STYLES as $value => $label ) : ?>
                        <li><label><input type="radio" name="<?php echo esc_attr( $c['id'] ); ?>_style" data-role="style" value="<?php echo esc_attr( $value ); ?>"<?php checked( $c['style'], $value ); ?>> <?php echo esc_html( $label ); ?></label></li>
                    <?php endforeach; ?>
                </ul>
                <div class="smpi-pe-notice" data-role="style-notice" role="status" aria-live="polite"></div>
                <p class="lead" style="margin-top:14px">Example, with the people above:</p>
                <div class="smpi-pe-example" data-role="example"><?php echo $c['example_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            </section>

            <section>
                <h3>CSS structure</h3>
                <p class="lead">Style it with these classes in the site's CSS or an Elementor widget's Custom CSS. "No styling" prints the same markup without the built-in CSS.</p>
                <pre><?php echo esc_html( PeopleCards::structure( $c['block'], $c['item'], (string) ( $c['title_label'] ?? '' ) ) ); ?></pre>
                <details><summary>Built-in CSS</summary><pre><?php echo esc_html( str_replace( '}', "}\n", PeopleCards::css( $c['block'], $c['item'] ) ) ); ?></pre></details>
            </section>

            <section>
                <h3>Shortcode</h3>
                <p class="lead">Place it on any page. With no attributes it shows everyone above, in order, in the chosen style.</p>
                <p><code>[<?php echo esc_html( $c['shortcode'] ); ?>]</code> <?php echo CoreUi::copy_button( '[' . $c['shortcode'] . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                <ul class="smpi-pe-attrs">
                    <?php foreach ( array_merge( self::attributes(), (array) ( $c['attributes'] ?? [] ) ) as $code => $text ) : ?>
                        <li><code><?php echo esc_html( $code ); ?></code> <?php echo esc_html( $text ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>
        </div>
        <?php
        self::script( $c );
    }

    /** @return array<string,string> */
    public static function attributes(): array {
        return [
            'style="cards|list|none"'        => 'overrides the style chosen above.',
            'columns="3"'                    => 'people per row in Cards (1 to 6; fewer on tablets and phones).',
            'ids="12,7"'                     => 'shows only these users, in this order (user IDs).',
            'limit="4"'                      => 'shows only the first people.',
            'hide="photo,title,bio,link"'    => 'leaves those parts out (any of them, comma separated).',
            'link="none"'                    => 'stops names and photos linking to the person\'s author page.',
            'bio_words="30"'                 => 'length of the short bio from the profile\'s Biographical Info; 0 hides it.',
            'photo_size="medium"'            => 'image size: thumbnail, medium, medium_large, large.',
            'heading="h3"'                   => 'tag for each name: h2, h3, h4 or p.',
        ];
    }

    /** One person row; with $id 0 it is the template the script fills when someone is added. */
    public static function person_html( int $id, array $row, bool $title_field, string $title_label ): string {
        $user  = $id ? get_userdata( $id ) : null;
        $name  = $user instanceof \WP_User ? (string) $user->display_name : '';
        $roles = $user instanceof \WP_User ? implode( ', ', array_map( 'ucfirst', (array) $user->roles ) ) : '';
        $meta  = implode( ' · ', array_filter( [ $roles, $user instanceof \WP_User ? (string) $user->user_email : '' ] ) );
        $badge = (string) ( $row['badge'] ?? '' );

        return '<li class="smpi-pe-person" draggable="true" data-id="' . esc_attr( (string) $id ) . '">'
            . '<span class="smpi-pe-handle dashicons dashicons-menu" title="Drag to reorder" aria-hidden="true"></span>'
            . '<img class="smpi-pe-avatar" src="' . esc_url( $id ? (string) get_avatar_url( $id, [ 'size' => 104 ] ) : '' ) . '" alt="">'
            . '<div class="smpi-pe-who"><div class="smpi-pe-name"><strong>' . esc_html( $name ) . '</strong><span class="smpi-pe-badge"' . ( '' === $badge ? ' hidden' : '' ) . '>' . esc_html( $badge ) . '</span></div><div class="smpi-pe-meta">' . esc_html( $meta ) . '</div>'
            . ( $title_field ? '<label class="smpi-pe-title"><span>' . esc_html( $title_label ) . '</span><input type="text" data-role="title" value="' . esc_attr( (string) ( $row['title'] ?? '' ) ) . '" placeholder="For example: Editor-in-Chief"></label>' : '' )
            . '</div><div class="smpi-pe-actions">'
            . '<button type="button" class="smpi-pe-icon" data-action="up" title="Move up" aria-label="Move up"><span class="dashicons dashicons-arrow-up-alt2"></span></button>'
            . '<button type="button" class="smpi-pe-icon" data-action="down" title="Move down" aria-label="Move down"><span class="dashicons dashicons-arrow-down-alt2"></span></button>'
            . '<a class="smpi-pe-icon" data-role="profile" href="' . esc_url( $id ? (string) get_edit_user_link( $id ) : '#' ) . '" target="_blank" rel="noopener" title="Open profile" aria-label="Open profile"><span class="dashicons dashicons-admin-users"></span></a>'
            . '<button type="button" class="smpi-pe-icon is-remove" data-action="remove" title="Remove from the list" aria-label="Remove from the list"><span class="dashicons dashicons-no-alt"></span></button>'
            . '</div></li>';
    }

    private static function styles(): void {
        static $printed = false;
        if ( $printed ) {
            return;
        }
        $printed = true;
        ?>
        <style>
            .smpi-pe{max-width:900px}
            .smpi-pe section{margin:0 0 30px}
            .smpi-pe h3{font-size:16px;margin:0 0 6px}
            .smpi-pe p.lead{color:#646970;margin:0 0 12px}
            .smpi-pe .smpi-pe-notice{font-size:13px;margin-top:8px;color:#00701a}
            .smpi-pe .smpi-pe-notice.error{color:#b32d2e}
            .smpi-pe .smpi-pe-ok{color:#00701a}
            .smpi-pe .smpi-pe-page{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
            .smpi-pe .smpi-pe-roles{list-style:none;margin:0;padding:0}
            .smpi-pe .smpi-pe-roles li{margin:6px 0}
            .smpi-pe .smpi-pe-team{max-width:760px;background:#fff;border:1px solid #dcdcde;border-radius:8px;overflow:hidden}
            .smpi-pe .smpi-pe-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid #f0f0f1;background:#f9fafb}
            .smpi-pe .smpi-pe-head strong{font-size:14px}
            .smpi-pe .smpi-pe-count{font-size:12px;color:#646970;background:#fff;border:1px solid #dcdcde;border-radius:999px;padding:2px 10px}
            .smpi-pe .smpi-pe-search{padding:14px 18px;border-bottom:1px solid #f0f0f1}
            .smpi-pe .smpi-pe-search .hpc-field>span{display:block;font-size:12px;font-weight:600;margin-bottom:6px}
            .smpi-pe .smpi-pe-search .hpc-smart-search-input{width:100%;height:40px;font-size:14px}
            .smpi-pe .smpi-pe-search .hpc-smart-search-selected{display:none!important}
            .smpi-pe .smpi-pe-people{list-style:none;margin:0;padding:0;max-height:640px;overflow:auto}
            .smpi-pe .smpi-pe-person{display:grid;grid-template-columns:20px 52px minmax(0,1fr) auto;align-items:center;gap:14px;padding:14px 18px;border-bottom:1px solid #f0f0f1;background:#fff;transition:background .15s;margin:0}
            .smpi-pe .smpi-pe-person:last-child{border-bottom:0}
            .smpi-pe .smpi-pe-person.is-dragging{opacity:.45}
            .smpi-pe .smpi-pe-person.is-over{background:#f0f6fc}
            .smpi-pe .smpi-pe-handle{cursor:grab;color:#a7aaad;user-select:none}
            .smpi-pe .smpi-pe-handle:hover{color:#50575e}
            .smpi-pe .smpi-pe-avatar{width:52px;height:52px;border-radius:50%;object-fit:cover;background:#f0f0f1}
            .smpi-pe .smpi-pe-who{min-width:0}
            .smpi-pe .smpi-pe-name{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
            .smpi-pe .smpi-pe-name strong{font-size:14px}
            .smpi-pe .smpi-pe-badge{font-size:11px;padding:1px 8px;border-radius:999px;background:#f0f6fc;color:#2271b1;border:1px solid #c5d9ed}
            .smpi-pe .smpi-pe-meta{font-size:12px;color:#787c82;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .smpi-pe .smpi-pe-title{display:block;margin-top:8px}
            .smpi-pe .smpi-pe-title span{display:block;font-size:11px;font-weight:600;letter-spacing:.03em;text-transform:uppercase;color:#646970;margin-bottom:4px}
            .smpi-pe .smpi-pe-title input{width:100%;max-width:420px;height:34px}
            .smpi-pe .smpi-pe-actions{display:flex;align-items:center;gap:4px}
            .smpi-pe .smpi-pe-icon{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #dcdcde;border-radius:6px;background:#fff;color:#50575e;cursor:pointer;text-decoration:none;padding:0}
            .smpi-pe .smpi-pe-icon:hover{border-color:#2271b1;color:#2271b1}
            .smpi-pe .smpi-pe-icon.is-remove:hover{border-color:#d63638;color:#d63638}
            .smpi-pe .smpi-pe-icon .dashicons{font-size:16px;width:16px;height:16px;pointer-events:none}
            .smpi-pe .smpi-pe-empty{padding:28px 18px;text-align:center;color:#646970}
            .smpi-pe .smpi-pe-removed{border-top:1px solid #f0f0f1;padding:10px 18px}
            .smpi-pe .smpi-pe-removed summary{cursor:pointer;color:#646970;font-size:12px}
            .smpi-pe .smpi-pe-removed ul{list-style:none;margin:8px 0 0;padding:0}
            .smpi-pe .smpi-pe-removed li{display:flex;align-items:center;gap:10px;padding:6px 0;margin:0}
            .smpi-pe .smpi-pe-removed img{width:28px;height:28px;border-radius:50%}
            .smpi-pe .smpi-pe-removed li span{flex:1}
            .smpi-pe .smpi-pe-team .smpi-pe-notice{padding:0 18px 12px;margin:0}
            .smpi-pe .smpi-pe-styles{list-style:none;margin:0;padding:0}
            .smpi-pe .smpi-pe-styles li{margin:6px 0}
            .smpi-pe .smpi-pe-example{border:1px solid #dcdcde;border-radius:6px;padding:24px;background:#fff;margin-top:12px;max-height:720px;overflow:auto}
            .smpi-pe pre{background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:12px;font-size:12px;overflow:auto;white-space:pre-wrap}
            .smpi-pe .smpi-pe-attrs{list-style:none;margin:0;padding:0;max-width:760px}
            .smpi-pe .smpi-pe-attrs li{padding:7px 0;border-bottom:1px solid #f0f0f1}
        </style>
        <?php
    }

    private static function script( array $c ): void {
        $template = self::person_html( 0, [], ! empty( $c['title_field'] ), (string) ( $c['title_label'] ?? '' ) );
        ?>
        <script>
        (function(){
            var root = document.getElementById(<?php echo wp_json_encode( $c['id'] ); ?>);
            if (!root || root.dataset.ready) return; root.dataset.ready = '1';
            var nonce = <?php echo wp_json_encode( Ajax::nonce() ); ?>, prefix = <?php echo wp_json_encode( $c['ajax_prefix'] ); ?>;
            var tpl = <?php echo wp_json_encode( $template ); ?>, addedBadge = <?php echo wp_json_encode( (string) ( $c['added_badge'] ?? '' ) ); ?>;
            function post(action, fields){
                var body = new URLSearchParams(); body.set('action', prefix + action); body.set('nonce', nonce);
                Object.keys(fields || {}).forEach(function(k){ body.set(k, fields[k]); });
                return fetch(window.ajaxurl, { method:'POST', credentials:'same-origin', body: body })
                    .then(function(r){ return r.json(); })
                    .then(function(p){ if (!p || !p.success) throw new Error(p && p.data && p.data.message ? p.data.message : 'Could not save.'); return p.data; });
            }
            function say(role, text, error){ var n = root.querySelector('[data-role="' + role + '"]'); if (!n) return; n.className = 'smpi-pe-notice' + (error ? ' error' : ''); n.textContent = text; }
            function example(html){ var e = root.querySelector('[data-role="example"]'); if (e && typeof html === 'string') e.innerHTML = html; }
            var list = root.querySelector('[data-role="people"]'), timer = null;
            function refresh(){ var n = list ? list.children.length : 0; var c = root.querySelector('[data-role="count"]'); if (c) c.textContent = n + (n === 1 ? ' person' : ' people'); var em = root.querySelector('[data-role="empty"]'); if (em) em.hidden = n > 0; }
            function people(){ return list ? Array.prototype.map.call(list.children, function(li){ var t = li.querySelector('[data-role="title"]'); return { id: li.dataset.id, title: t ? t.value : '' }; }) : []; }
            // Saves run one after another, each sending the list as it is when it starts, so a slow save never overwrites a newer one.
            var queue = Promise.resolve();
            function save(){ clearTimeout(timer); say('people-notice', 'Saving…'); queue = queue.then(function(){ return post('save_people', { people: JSON.stringify(people()) }).then(function(d){ say('people-notice', d.message); example(d.example); }).catch(function(e){ say('people-notice', e.message, true); }); }); return queue; }
            function addRow(item){
                var holder = document.createElement('ul'); holder.innerHTML = tpl; var row = holder.firstElementChild;
                row.dataset.id = String(item.id);
                row.querySelector('.smpi-pe-avatar').src = item.thumbnail || '';
                row.querySelector('.smpi-pe-name strong').textContent = item.name || '';
                var roles = String(item.status || '').split(', ').filter(Boolean).map(function(r){ return r.charAt(0).toUpperCase() + r.slice(1); }).join(', ');
                row.querySelector('.smpi-pe-meta').textContent = [roles, item.email].filter(Boolean).join(' · ');
                row.querySelector('[data-role="profile"]').href = item.edit_url || '#';
                var badge = row.querySelector('.smpi-pe-badge'); if (badge && addedBadge) { badge.textContent = addedBadge; badge.hidden = false; }
                list.appendChild(row); refresh(); return row;
            }
            document.addEventListener('hexa-search-selected', function(ev){
                if (!ev.detail || ev.detail.component_id !== root.id + '-search' || !list) return;
                var item = ev.detail.item || {}, search = document.getElementById(root.id + '-search');
                var input = search && search.querySelector('.hpc-smart-search-input'); if (input) input.value = '';
                if (!item.id) return;
                if (list.querySelector('.smpi-pe-person[data-id="' + item.id + '"]')) { say('people-notice', (item.name || 'This person') + ' is already on the list.', true); return; }
                var row = addRow(item);
                var st = search && search.querySelector('.hpc-smart-search-status'); if (st) st.textContent = 'Added ' + (item.name || '') + '.';
                save(); var t = row.querySelector('[data-role="title"]'); if (t) t.focus();
            });
            var dragged = null;
            if (list) {
                list.addEventListener('dragstart', function(e){ var li = e.target.closest('.smpi-pe-person'); if (!li) return; dragged = li; li.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; });
                list.addEventListener('dragover', function(e){ var li = e.target.closest('.smpi-pe-person'); if (!dragged || !li || li === dragged) return; e.preventDefault(); Array.prototype.forEach.call(list.children, function(x){ x.classList.remove('is-over'); }); li.classList.add('is-over'); var r = li.getBoundingClientRect(); list.insertBefore(dragged, (e.clientY - r.top) > r.height / 2 ? li.nextSibling : li); });
                list.addEventListener('dragend', function(){ if (!dragged) return; dragged.classList.remove('is-dragging'); Array.prototype.forEach.call(list.children, function(x){ x.classList.remove('is-over'); }); dragged = null; save(); });
            }
            root.addEventListener('input', function(e){ if (e.target.dataset.role === 'title') { clearTimeout(timer); timer = setTimeout(save, 800); } });
            root.addEventListener('change', function(e){
                if (e.target.dataset.role === 'style') {
                    say('style-notice', 'Saving…');
                    post('style', { style: e.target.value }).then(function(d){ say('style-notice', d.message); example(d.example); }).catch(function(err){ say('style-notice', err.message, true); });
                }
            });
            root.addEventListener('click', function(e){
                var btn = e.target.closest('[data-action]'); var action = btn ? btn.getAttribute('data-action') : ''; if (!action) return;
                var li = e.target.closest('.smpi-pe-person');
                if (action === 'remove' && li) { li.remove(); refresh(); save().then(function(){ if (root.dataset.reloadOnRemove) location.reload(); }); }
                if (action === 'up' && li && li.previousElementSibling) { list.insertBefore(li, li.previousElementSibling); save(); }
                if (action === 'down' && li && li.nextElementSibling) { list.insertBefore(li.nextElementSibling, li); save(); }
                if (action === 'restore') { var r = e.target.closest('li'); var body = new URLSearchParams(); var ids = people().map(function(p){ return p.id; }); ids.push(r.dataset.id); say('people-notice', 'Saving…'); post('save_people', { people: JSON.stringify(ids.map(function(id){ return { id: id }; })) }).then(function(){ location.reload(); }).catch(function(err){ say('people-notice', err.message, true); }); }
                if (action === 'apply-template' || action === 'create-page') {
                    if (action === 'apply-template' && !window.confirm('Replace this page\'s content with the default template? The current content is kept as a revision.')) return;
                    say('page-notice', 'Updating the page…');
                    post(action === 'apply-template' ? 'apply_template' : 'create_page', {}).then(function(d){ say('page-notice', d.message); setTimeout(function(){ location.reload(); }, 1200); }).catch(function(err){ say('page-notice', err.message, true); });
                }
            });
        })();
        </script>
        <?php
    }
}
