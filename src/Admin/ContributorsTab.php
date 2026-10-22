<?php

namespace smp_publication_integration\Admin;

use Hexa\PluginCore\WpAdminAjax\AjaxGuard;
use smp_publication_integration\Admin\People\PeopleEditor;
use smp_publication_integration\Content\Contributors;
use smp_publication_integration\Content\People\PeopleCards;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings, SMP, Contributors: which roles count as contributors, the list itself
 * (everyone with those roles, plus people added from other roles, minus people
 * removed, in a chosen order), the Contributors page, style, CSS structure and
 * [smpi_contributors]. Layout and behavior come from PeopleEditor.
 */
final class ContributorsTab {
    public const TAB = 'contributors';

    public function register(): void {
        add_filter( 'smpi_render_dashboard_tab', [ $this, 'maybe_render' ], 10, 2 );
        foreach ( [ 'roles', 'save_people', 'style', 'apply_template', 'create_page' ] as $action ) {
            add_action( 'wp_ajax_smpi_contributors_' . $action, [ $this, 'ajax_' . $action ] );
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

    public function ajax_roles(): void {
        $this->guard();
        $roles = array_values( array_intersect( array_map( 'sanitize_key', (array) json_decode( (string) wp_unslash( $_POST['roles'] ?? '[]' ), true ) ), array_keys( wp_roles()->roles ) ) );
        if ( ! $roles ) {
            wp_send_json_error( [ 'message' => 'Choose at least one role.' ] );
        }
        Contributors::update_settings( [ 'roles' => $roles ] );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => 'Saved. The list now follows the chosen roles.' ] );
    }

    public function ajax_save_people(): void {
        $this->guard();
        $people = json_decode( (string) wp_unslash( $_POST['people'] ?? '[]' ), true );
        $ids    = array_map( static fn( $p ): int => absint( is_array( $p ) ? ( $p['id'] ?? 0 ) : $p ), is_array( $people ) ? $people : [] );
        Contributors::save_list( array_values( array_filter( $ids, static fn( int $id ): bool => $id > 0 && (bool) get_userdata( $id ) ) ) );
        do_action( 'litespeed_purge_all' );
        $count = count( Contributors::member_ids() );
        wp_send_json_success( [ 'message' => $count . ( 1 === $count ? ' person is' : ' people are' ) . ' on the contributors list.', 'example' => $this->example_html() ] );
    }

    public function ajax_style(): void {
        $this->guard();
        $style = sanitize_key( (string) ( $_POST['style'] ?? '' ) );
        if ( ! isset( PeopleCards::STYLES[ $style ] ) ) {
            wp_send_json_error( [ 'message' => 'Unknown style.' ] );
        }
        Contributors::update_settings( [ 'style' => $style ] );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => 'Style saved.', 'example' => $this->example_html() ] );
    }

    public function ajax_apply_template(): void {
        $this->guard();
        $page_id = PeopleCards::find_page( Contributors::PAGE_TYPE, Contributors::PATHS );
        if ( ! $page_id ) {
            wp_send_json_error( [ 'message' => 'There is no Contributors page to update.' ] );
        }
        PeopleCards::apply_template( $page_id, Contributors::default_template() );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => 'The page now uses the default template. Its previous content is saved as a revision.' ] );
    }

    public function ajax_create_page(): void {
        $this->guard();
        if ( PeopleCards::find_page( Contributors::PAGE_TYPE, Contributors::PATHS ) ) {
            wp_send_json_success( [ 'message' => 'The Contributors page already exists.' ] );
        }
        $page = PeopleCards::create_page( Contributors::PAGE_TYPE, 'Contributors', 'contributors', Contributors::default_template() );
        if ( ! empty( $page['error'] ) ) {
            wp_send_json_error( [ 'message' => 'The page could not be created: ' . $page['error'] ] );
        }
        wp_send_json_success( [ 'message' => 'The Contributors page was created with the default template.' ] );
    }

    private function example_html(): string {
        $html = Contributors::render();
        return '' !== $html ? $html : '<p class="description">No one is on the list yet.</p>';
    }

    private function render(): void {
        $settings = Contributors::settings();
        $page_id  = PeopleCards::find_page( Contributors::PAGE_TYPE, Contributors::PATHS );
        $role_ids = Contributors::role_member_ids( $settings['roles'] );

        ob_start();
        ?>
        <section>
            <h3>Contributors</h3>
            <p class="lead">Everyone with the roles below is on the list automatically. Add people from other roles, or remove someone, in People further down.</p>
            <h4 style="margin:12px 0 6px">Roles that count as contributors</h4>
            <ul class="smpi-pe-roles" data-role="roles">
                <?php foreach ( wp_roles()->roles as $key => $role ) : ?>
                    <li><label><input type="checkbox" value="<?php echo esc_attr( $key ); ?>"<?php checked( in_array( $key, $settings['roles'], true ) ); ?>> <?php echo esc_html( translate_user_role( $role['name'] ) ); ?> <span style="color:#787c82">(<?php echo esc_html( (string) count( get_users( [ 'role' => $key, 'fields' => 'ID' ] ) ) ); ?>)</span></label></li>
                <?php endforeach; ?>
            </ul>
            <div class="smpi-pe-notice" data-role="roles-notice" role="status" aria-live="polite"></div>
        </section>
        <script>
        (function(){
            var box = document.querySelector('#smpi-contributors-tab [data-role="roles"]'); if (!box || box.dataset.ready) return; box.dataset.ready = '1';
            box.addEventListener('change', function(){
                var roles = Array.prototype.filter.call(box.querySelectorAll('input'), function(i){ return i.checked; }).map(function(i){ return i.value; });
                var note = document.querySelector('#smpi-contributors-tab [data-role="roles-notice"]'); note.className = 'smpi-pe-notice'; note.textContent = 'Saving…';
                var body = new URLSearchParams(); body.set('action', 'smpi_contributors_roles'); body.set('nonce', <?php echo wp_json_encode( Ajax::nonce() ); ?>); body.set('roles', JSON.stringify(roles));
                fetch(window.ajaxurl, { method:'POST', credentials:'same-origin', body: body }).then(function(r){ return r.json(); }).then(function(p){
                    if (!p || !p.success) throw new Error(p && p.data && p.data.message ? p.data.message : 'Could not save.');
                    note.textContent = p.data.message; setTimeout(function(){ location.reload(); }, 900);
                }).catch(function(err){ note.className = 'smpi-pe-notice error'; note.textContent = err.message; });
            });
        })();
        </script>
        <?php
        $top = (string) ob_get_clean();

        PeopleEditor::render(
            [
                'id'               => 'smpi-contributors-tab',
                'ajax_prefix'      => 'smpi_contributors_',
                'top_html'         => $top,
                'show_body'        => true,
                'reload_on_remove' => true,
                'page'             => [ 'id' => $page_id, 'has_list' => $page_id && PeopleCards::page_has_shortcode( $page_id, Contributors::SHORTCODE ) ],
                'page_label'       => 'Contributors',
                'list_label'       => 'Contributors',
                'people_intro'     => 'Drag to set the order of appearance. People with a contributor role are listed automatically; removing one keeps their role and only takes them off this list (put them back from "removed from the list"). People added from other roles are marked "Added".',
                'empty'            => 'No one is on the list. Choose a role above or search to add someone.',
                'members'          => Contributors::member_ids(),
                'removed'          => Contributors::removed_ids(),
                'row'              => static fn( int $id ): array => [ 'badge' => in_array( $id, $role_ids, true ) ? '' : 'Added' ],
                'added_badge'      => 'Added',
                'title_field'      => false,
                'title_label'      => '',
                'shortcode'        => Contributors::SHORTCODE,
                'block'            => 'smpi-contributors',
                'item'             => 'smpi-contributor',
                'style'            => $settings['style'],
                'example_html'     => $this->example_html(),
                'attributes'       => [
                    'roles="contributor,author"' => 'uses these roles for this one list instead of the roles chosen above (added and removed people still apply).',
                    'with_articles="1"'          => 'leaves out people who have not published anything yet.',
                ],
            ]
        );
    }
}
