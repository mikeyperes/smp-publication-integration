<?php
namespace smp_publication_integration\Authorship;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * The one place an editor sets authors.
 *
 * With the site feature on, supported editors get a single "Authors" box:
 * the native author, a "Multiple authors" switch (off by default), and an
 * ordered co-author list that only appears once the switch is on. In the
 * Classic Editor the box replaces WordPress's own Author box and submits the
 * native author through core's post_author_override field; in the block
 * editor the native author stays in the sidebar and this box adds the switch.
 */
final class AuthorEditorPanel {
    private const BOX_ID = "smpi-authors";
    private const NONCE_ACTION = "smpi_save_authors";
    private const NONCE_FIELD = "smpi_authors_nonce";
    private const SEARCH_ACTION = "smpi_author_search";

    private AuthorAssignmentRepository $repository;

    public function __construct( AuthorAssignmentRepository $repository ) {
        $this->repository = $repository;
    }

    public function register(): void {
        add_action( "add_meta_boxes", [ $this, "add_box" ], 20, 2 );
        add_action( "save_post", [ $this, "save" ], 20, 2 );
        add_action( "wp_ajax_" . self::SEARCH_ACTION, [ $this, "search" ] );
    }

    public function add_box( string $post_type, $post ): void {
        if ( ! AuthorAssignmentRepository::site_enabled() || ! in_array( $post_type, AuthorAssignmentRepository::supported_post_types(), true ) ) {
            return;
        }
        if ( ! $this->is_block_editor( $post ) ) {
            remove_meta_box( "authordiv", $post_type, "normal" );
        }
        add_meta_box( self::BOX_ID, "Authors", [ $this, "render" ], $post_type, "side", "high" );
    }

    public function render( \WP_Post $post ): void {
        $switch_on = $this->repository->switch_on( (int) $post->ID );
        $co_authors = $this->repository->stored_co_author_ids( (int) $post->ID );
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
        ?>
        <div class="smpi-authors" data-smpi-authors data-search-nonce="<?php echo esc_attr( wp_create_nonce( self::SEARCH_ACTION ) ); ?>">
            <div class="smpi-authors__primary">
                <?php $this->render_primary( $post ); ?>
            </div>
            <label class="smpi-authors__switch">
                <input type="checkbox" name="smpi_multi_authors" value="1" data-smpi-authors-switch <?php checked( $switch_on ); ?>>
                <span>Multiple authors</span>
            </label>
            <div class="smpi-authors__co" data-smpi-authors-co <?php echo $switch_on ? "" : "hidden"; ?>>
                <p class="smpi-authors__label">Co-authors <span>shown after the author, in this order</span></p>
                <ol class="smpi-authors__list" data-smpi-authors-list>
                    <?php foreach ( $co_authors as $user_id ) : ?>
                        <?php $this->render_item( $user_id ); ?>
                    <?php endforeach; ?>
                </ol>
                <div class="smpi-authors__search">
                    <input type="search" class="widefat" placeholder="Add co-author…" autocomplete="off" data-smpi-authors-search aria-label="Search users to add as co-author">
                    <ul class="smpi-authors__results" data-smpi-authors-results hidden></ul>
                </div>
            </div>
        </div>
        <template data-smpi-authors-item><?php $this->render_item( 0 ); ?></template>
        <?php
        $this->print_assets();
    }

    public function save( int $post_id, \WP_Post $post ): void {
        if (
            ! isset( $_POST[ self::NONCE_FIELD ] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION )
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || ! current_user_can( "edit_post", $post_id )
            || ! AuthorAssignmentRepository::site_enabled()
        ) {
            return;
        }
        $co_authors = isset( $_POST["smpi_co_authors"] ) ? array_map( "absint", (array) wp_unslash( $_POST["smpi_co_authors"] ) ) : [];
        $this->repository->save( $post_id, ! empty( $_POST["smpi_multi_authors"] ), $co_authors );
    }

    public function search(): void {
        check_ajax_referer( self::SEARCH_ACTION, "nonce" );
        if ( ! current_user_can( "edit_posts" ) ) {
            wp_send_json_error( [ "message" => "Not allowed." ], 403 );
        }
        $term = sanitize_text_field( wp_unslash( (string) ( $_GET["q"] ?? "" ) ) );
        $query = new \WP_User_Query(
            [
                "search" => "*" . $term . "*",
                "search_columns" => [ "display_name", "user_login", "user_email", "user_nicename" ],
                "capability" => [ "edit_posts" ],
                "number" => 20,
                "orderby" => "display_name",
                "fields" => [ "ID", "display_name", "user_login" ],
            ]
        );
        $results = [];
        foreach ( $query->get_results() as $user ) {
            $results[] = [ "id" => (int) $user->ID, "label" => $this->label( (int) $user->ID ) ];
        }
        wp_send_json_success( $results );
    }

    private function render_primary( \WP_Post $post ): void {
        if ( $this->is_block_editor( $post ) ) {
            echo '<p class="smpi-authors__label">Author</p><p class="description">Set in the post sidebar. It is always listed first.</p>';
            return;
        }
        $type = get_post_type_object( $post->post_type );
        $selected = (int) $post->post_author > 0 ? (int) $post->post_author : get_current_user_id();
        echo '<label class="smpi-authors__label" for="post_author_override">Author</label>';
        if ( ! $type || ! current_user_can( $type->cap->edit_others_posts ) ) {
            echo '<p>' . esc_html( $this->label( $selected ) ) . '</p>';
            return;
        }
        wp_dropdown_users(
            [
                "capability" => [ $type->cap->edit_posts ],
                "name" => "post_author_override",
                "id" => "post_author_override",
                "class" => "widefat",
                "selected" => $selected,
                "include_selected" => true,
                "show" => "display_name_with_login",
            ]
        );
    }

    private function render_item( int $user_id ): void {
        ?>
        <li class="smpi-authors__item" data-id="<?php echo esc_attr( (string) $user_id ); ?>">
            <input type="hidden" name="smpi_co_authors[]" value="<?php echo esc_attr( (string) $user_id ); ?>">
            <span class="smpi-authors__name"><?php echo esc_html( $user_id > 0 ? $this->label( $user_id ) : "" ); ?></span>
            <button type="button" class="button-link" data-smpi-authors-move="-1" aria-label="Move up">↑</button>
            <button type="button" class="button-link" data-smpi-authors-move="1" aria-label="Move down">↓</button>
            <button type="button" class="button-link smpi-authors__remove" data-smpi-authors-remove aria-label="Remove">✕</button>
        </li>
        <?php
    }

    private function label( int $user_id ): string {
        $user = get_user_by( "id", $user_id );
        return $user instanceof \WP_User ? $user->display_name . " (" . $user->user_login . ")" : "#" . $user_id;
    }

    private function is_block_editor( $post ): bool {
        return function_exists( "use_block_editor_for_post" ) && $post instanceof \WP_Post && use_block_editor_for_post( $post );
    }

    private function print_assets(): void {
        ?>
        <style>
            .smpi-authors__label{display:block;font-weight:600;margin:0 0 6px}.smpi-authors__label span{color:#646970;font-weight:400}
            .smpi-authors__primary{margin-bottom:12px}
            .smpi-authors__switch{align-items:center;display:flex;gap:6px;margin:0 0 10px}
            .smpi-authors__co{border-top:1px solid #dcdcde;padding-top:10px}
            .smpi-authors__list{margin:0 0 8px;padding:0;list-style:none}
            .smpi-authors__item{align-items:center;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;display:flex;gap:4px;margin:0 0 4px;padding:4px 6px}
            .smpi-authors__name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .smpi-authors__item .button-link{color:#50575e;padding:0 3px;text-decoration:none}.smpi-authors__remove:hover{color:#b32d2e!important}
            .smpi-authors__search{position:relative}
            .smpi-authors__results{background:#fff;border:1px solid #8c8f94;border-top:0;left:0;margin:0;max-height:220px;overflow:auto;position:absolute;right:0;z-index:10}
            .smpi-authors__results li{cursor:pointer;margin:0;padding:6px 8px}.smpi-authors__results li:hover,.smpi-authors__results li.is-active{background:#2271b1;color:#fff}
        </style>
        <script>
        (function(){
            var root=document.querySelector('[data-smpi-authors]');
            if(!root){return;}
            var sw=root.querySelector('[data-smpi-authors-switch]'),co=root.querySelector('[data-smpi-authors-co]'),
                list=root.querySelector('[data-smpi-authors-list]'),search=root.querySelector('[data-smpi-authors-search]'),
                results=root.querySelector('[data-smpi-authors-results]'),tpl=document.querySelector('template[data-smpi-authors-item]'),
                primary=document.getElementById('post_author_override'),timer=null;
            function ids(){return Array.prototype.map.call(list.children,function(li){return li.getAttribute('data-id');});}
            function add(id,label){
                if(!id||ids().indexOf(String(id))>-1||(primary&&String(primary.value)===String(id))){return;}
                var li=tpl.content.firstElementChild.cloneNode(true);
                li.setAttribute('data-id',id);li.querySelector('input').value=id;li.querySelector('.smpi-authors__name').textContent=label;
                list.appendChild(li);
            }
            sw.addEventListener('change',function(){co.hidden=!sw.checked;if(sw.checked){search.focus();}});
            list.addEventListener('click',function(e){
                var b=e.target.closest('button');if(!b){return;}var li=b.closest('li');
                if(b.hasAttribute('data-smpi-authors-remove')){li.remove();return;}
                var dir=parseInt(b.getAttribute('data-smpi-authors-move'),10);
                if(dir<0&&li.previousElementSibling){list.insertBefore(li,li.previousElementSibling);}
                if(dir>0&&li.nextElementSibling){list.insertBefore(li.nextElementSibling,li);}
            });
            function close(){results.hidden=true;results.innerHTML='';}
            search.addEventListener('input',function(){
                clearTimeout(timer);var q=search.value.trim();if(q.length<2){close();return;}
                timer=setTimeout(function(){
                    var url=window.ajaxurl+'?action=<?php echo esc_js( self::SEARCH_ACTION ); ?>&nonce='+encodeURIComponent(root.getAttribute('data-search-nonce'))+'&q='+encodeURIComponent(q);
                    fetch(url,{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(x){
                        results.innerHTML='';var taken=ids();if(primary){taken.push(String(primary.value));}
                        (x&&x.success?x.data:[]).filter(function(u){return taken.indexOf(String(u.id))<0;}).forEach(function(u){
                            var li=document.createElement('li');li.textContent=u.label;li.setAttribute('data-id',u.id);results.appendChild(li);
                        });
                        results.hidden=!results.children.length;
                    });
                },250);
            });
            results.addEventListener('mousedown',function(e){var li=e.target.closest('li');if(!li){return;}e.preventDefault();add(li.getAttribute('data-id'),li.textContent);search.value='';close();});
            search.addEventListener('keydown',function(e){
                if(e.key==='Enter'){e.preventDefault();var first=results.querySelector('li');if(first){add(first.getAttribute('data-id'),first.textContent);search.value='';close();}}
                if(e.key==='Escape'){close();}
            });
            search.addEventListener('blur',function(){setTimeout(close,150);});
            if(primary){primary.addEventListener('change',function(){var dup=list.querySelector('li[data-id="'+primary.value+'"]');if(dup){dup.remove();}});}
        })();
        </script>
        <?php
    }
}
