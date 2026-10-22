<?php
namespace smp_publication_integration\Admin\Dashboard\Tabs;

use Hexa\PluginCore\WpAdminAjax\AjaxGuard;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Pages\PageLinker;
use smp_publication_integration\Pages\PageTemplates;
use smp_publication_integration\Pages\PageTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Publication Pages tab: every SMP page type, grouped, with its linked page,
 * status, default template (view and edit) and one create/apply button.
 */
final class PagesTab {
    public function register(): void {
        foreach ( [ 'link', 'save_template', 'reset_template', 'apply' ] as $action ) {
            add_action( 'wp_ajax_smpi_pages_' . $action, [ $this, 'ajax_' . $action ] );
        }
    }

    public function render(): void {
        echo '<div class="smpi-panel smpi-pages"><h2>Publication Pages</h2>';
        echo '<p>Every standard publication page. Each one is linked to the WordPress page at its usual address automatically; use Change to pick another. The template is the starting content; Create or Apply puts it into the page and keeps the old content as a revision.</p>';
        foreach ( PageTypes::grouped() as $group => $types ) {
            echo '<h3 class="smpi-pages-group">' . esc_html( PageTypes::GROUPS[ $group ] ) . '</h3>';
            echo '<table class="widefat striped smpi-pages-table"><thead><tr><th>Page</th><th>Linked WordPress page</th><th>Status</th><th>Template</th><th></th></tr></thead><tbody>';
            foreach ( array_keys( $types ) as $key ) {
                echo self::row_html( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo '</tbody></table>';
        }
        echo '<p class="smpi-muted">Verified profile pages and leadership or culture council pages are managed by their own plugins. Company facts used in these templates (name, founding date, headquarters, founders, parent company, contact) are read from HWS Base Tools.</p>';
        echo '</div>';
        echo '<script type="application/json" id="smpi-pages-options">' . wp_json_encode( self::page_options() ) . '</script>';
        self::assets();
    }

    public static function row_html( string $key ): string {
        $type  = PageTypes::get( $key );
        $link  = PageLinker::link( $key );
        $id    = (int) $link['id'];
        $badge = '';
        if ( ! empty( $type['google_news'] ) ) {
            $badge .= ' <span class="smpi-pages-badge">Google News</span>';
        }
        if ( ! empty( $type['base_tools'] ) ) {
            $badge .= ' <span class="smpi-pages-badge smpi-pages-badge--flag" title="' . esc_attr( (string) $type['base_tools'] ) . '">Belongs in Base Tools</span>';
        }

        if ( $id ) {
            $post    = get_post( $id );
            $url     = (string) get_permalink( $id );
            $page    = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $id ) ?: '(no title)' ) . '</a><br><code>' . esc_html( (string) wp_make_link_relative( $url ) ) . '</code>';
            $page   .= 'found' === $link['source'] ? ' <span class="smpi-muted">found automatically</span>' : '';
            $status_obj = get_post_status_object( (string) $post->post_status );
            $status  = '<span class="' . ( 'publish' === $post->post_status ? 'smpi-ok' : 'smpi-warn' ) . '">' . esc_html( $status_obj ? $status_obj->label : $post->post_status ) . '</span>';
            if ( PageLinker::is_elementor( $id ) ) {
                $status .= '<br><span class="smpi-muted">Built with Elementor</span>';
            }
            $button = 'Apply template';
            $edit   = ' <a href="' . esc_url( (string) get_edit_post_link( $id, 'raw' ) ) . '" target="_blank" rel="noopener">Edit</a>';
        } else {
            $page   = '<span class="smpi-muted">No page found at /' . esc_html( PageTypes::slug( $key ) ) . '/</span>';
            $status = '<span class="smpi-bad">Missing</span>';
            $button = 'Create page';
            $edit   = '';
        }

        $template_state = PageTemplates::is_customized( $key ) ? 'Edited for this site' : 'Default';
        $html  = '<tr class="smpi-pages-row" data-key="' . esc_attr( $key ) . '" data-page-id="' . esc_attr( (string) $id ) . '">';
        $html .= '<td><strong>' . esc_html( (string) $type['label'] ) . '</strong>' . $badge . '<br><span class="smpi-muted">' . esc_html( (string) $type['description'] ) . '</span></td>';
        $html .= '<td class="smpi-pages-link">' . $page . '<br><button type="button" class="button-link smpi-pages-change">Change</button>' . $edit . '</td>';
        $html .= '<td>' . $status . '</td>';
        $html .= '<td>' . esc_html( $template_state ) . '<br><button type="button" class="button-link smpi-pages-view">View / edit</button></td>';
        $html .= '<td><button type="button" class="button button-secondary smpi-pages-apply" data-label="' . esc_attr( $button ) . '">' . esc_html( $button ) . '</button><span class="smpi-pages-msg" aria-live="polite"></span></td>';
        $html .= '</tr>';
        $html .= '<tr class="smpi-pages-editor" data-key="' . esc_attr( $key ) . '" hidden><td colspan="5"><textarea class="large-text code" rows="14">' . esc_textarea( PageTemplates::get( $key ) ) . '</textarea>';
        $html .= '<p><button type="button" class="button button-primary smpi-pages-save">Save template</button> <button type="button" class="button smpi-pages-reset">Back to default</button> <span class="smpi-muted">Shortcodes fill in the publication name, dates, places and people.</span></p></td></tr>';
        return $html;
    }

    /** @return array<int,array{id:int,label:string}> */
    private static function page_options(): array {
        $out   = [ [ 'id' => 0, 'label' => '— Not linked —' ] ];
        $pages = get_posts( [ 'post_type' => 'page', 'post_status' => [ 'publish', 'draft', 'private', 'pending' ], 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ] );
        foreach ( $pages as $page ) {
            $out[] = [ 'id' => (int) $page->ID, 'label' => get_the_title( $page ) . ' (/' . $page->post_name . '/' . ( 'publish' !== $page->post_status ? ', ' . $page->post_status : '' ) . ')' ];
        }
        return $out;
    }

    private function guard(): string {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'You are not allowed to change pages here.' ], 403 );
        }
        AjaxGuard::require_nonce_or_error( Ajax::NONCE, 'nonce' );
        $key = PageTypes::canonical( (string) wp_unslash( $_POST['key'] ?? '' ) );
        if ( ! PageTypes::exists( $key ) ) {
            wp_send_json_error( [ 'message' => 'Unknown page type.' ], 400 );
        }
        return $key;
    }

    private function done( string $key, string $message ): void {
        wp_send_json_success( [ 'message' => $message, 'row' => self::row_html( $key ) ] );
    }

    public function ajax_link(): void {
        $key = $this->guard();
        $id  = absint( $_POST['page_id'] ?? 0 );
        if ( $id && ! PageLinker::valid( $id ) ) {
            wp_send_json_error( [ 'message' => 'That page no longer exists.' ] );
        }
        PageLinker::assign( $key, $id );
        $this->done( $key, $id ? 'Linked.' : 'Unlinked.' );
    }

    public function ajax_save_template(): void {
        $key = $this->guard();
        PageTemplates::save( $key, (string) wp_unslash( $_POST['template'] ?? '' ) );
        $this->done( $key, 'Template saved.' );
    }

    public function ajax_reset_template(): void {
        $key = $this->guard();
        PageTemplates::reset( $key );
        $this->done( $key, 'Template is back to the default.' );
    }

    public function ajax_apply(): void {
        $key    = $this->guard();
        $result = PageLinker::create_or_apply( $key );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        do_action( 'litespeed_purge_post', $result['page_id'] );
        $this->done( $key, $result['created'] ? 'Page created.' : 'Template applied; the old content is saved as a revision.' );
    }

    private static function assets(): void {
        ?>
        <style>
            .smpi-pages-group{margin:22px 0 8px}
            .smpi-pages-table td{vertical-align:top}
            .smpi-pages-table td:first-child{width:30%}
            .smpi-pages-badge{display:inline-block;margin-left:4px;padding:0 6px;border-radius:9px;background:#eef2ff;color:#3730a3;font-size:11px;line-height:18px}
            .smpi-pages-badge--flag{background:#fff7ed;color:#9a3412}
            .smpi-pages-msg{display:block;margin-top:4px;font-size:12px}
            .smpi-pages-editor textarea{font-size:12px}
        </style>
        <script>
        (function($){
            if(window.smpiPagesReady){return;}window.smpiPagesReady=true;
            function post(row,action,data){
                var key=row.data('key'),msg=$('.smpi-pages-row[data-key="'+key+'"] .smpi-pages-msg');
                msg.text('Working...');
                return $.post(ajaxurl,$.extend({action:'smpi_pages_'+action,nonce:window.smpiAdmin?smpiAdmin.nonce:'',key:key},data||{})).done(function(r){
                    if(!r||!r.success){msg.text((r&&r.data&&r.data.message)||'Failed.');return;}
                    var old=$('.smpi-pages-row[data-key="'+key+'"]'),ed=old.next('.smpi-pages-editor'),open=!ed.prop('hidden');
                    ed.remove();old.replaceWith(r.data.row);
                    var fresh=$('.smpi-pages-row[data-key="'+key+'"]');fresh.next('.smpi-pages-editor').prop('hidden',!open);
                    fresh.find('.smpi-pages-msg').text(r.data.message);
                }).fail(function(){msg.text('Request failed.');});
            }
            $(document).on('click','.smpi-pages-view',function(){var ed=$(this).closest('tr').next('.smpi-pages-editor');ed.prop('hidden',!ed.prop('hidden'));});
            $(document).on('click','.smpi-pages-save',function(){var ed=$(this).closest('.smpi-pages-editor');post(ed,'save_template',{template:ed.find('textarea').val()});});
            $(document).on('click','.smpi-pages-reset',function(){var ed=$(this).closest('.smpi-pages-editor');if(confirm('Replace the edited template with the default?'))post(ed,'reset_template');});
            $(document).on('click','.smpi-pages-apply',function(){var row=$(this).closest('tr'),id=+row.data('page-id');if(id&&!confirm('Put the template into this page? Its current content is kept as a revision.'))return;post(row,'apply');});
            $(document).on('click','.smpi-pages-change',function(){
                var cell=$(this).closest('td'),row=cell.closest('tr');if(cell.find('select').length)return;
                var opts=JSON.parse($('#smpi-pages-options').text()||'[]'),sel=$('<select class="smpi-pages-select" style="max-width:100%"></select>');
                opts.forEach(function(o){sel.append($('<option>').val(o.id).text(o.label));});
                sel.val(String(row.data('page-id')));cell.append($('<div>').append(sel));
                sel.on('change',function(){post(row,'link',{page_id:sel.val()});});
            });
        })(jQuery);
        </script>
        <?php
    }
}
