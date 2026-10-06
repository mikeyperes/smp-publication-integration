<?php
namespace smp_publication_integration\Content;

use Hexa\PluginCore\WpAdminComponents\DynamicButton;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Support\Dependencies;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class GoingLiveChecklist {
    private const ACTION_STATUS = "smpi_going_live_checklist_status";
    /** Post meta: "1" means this post is never processed automatically. */
    public const EXEMPT_META = "_smpi_go_live_exempt";
    private const EXEMPT_NONCE = "smpi_go_live_exempt_nonce";

    public function register(): void {
        add_action( "edit_form_after_editor", [ $this, "render" ] );
        add_action( "admin_footer-post.php", [ $this, "footer_assets" ] );
        add_action( "admin_footer-post-new.php", [ $this, "footer_assets" ] );
        add_action( "save_post", [ $this, "save_exempt" ], 10, 2 );
        add_action( "smpi_go_live_process", [ $this, "process_missing" ] );
        ( new AjaxActionRegistry(
            [
                'capability'   => '',
                'nonce_action' => Ajax::NONCE,
                'nonce_field'  => 'nonce',
            ]
        ) )->register(
            [
                self::ACTION_STATUS => [ 'callback' => [ $this, 'ajax_status' ] ],
            ]
        );
    }

    public function render( \WP_Post $post ): void {
        if ( ! $this->supports_post( $post ) || ! current_user_can( "edit_post", $post->ID ) ) {
            return;
        }

        if ( class_exists( DynamicButton::class ) ) {
            DynamicButton::render_assets();
        }

        $items = $this->items_for_post( $post );
        ?>
        <div id="smpi-going-live-checklist" class="postbox smpi-glc" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>" data-ajax-url="<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>" data-nonce="<?php echo esc_attr( Ajax::nonce() ); ?>">
            <div class="smpi-glc__head">
                <div class="smpi-glc__title">
                    <h2>Going Live</h2>
                    <span class="smpi-glc__progress" data-smpi-go-live-progress>Checking…</span>
                </div>
                <div class="smpi-glc__controls">
                    <label class="smpi-glc__switch" title="When on, this page is skipped by automatic processing.">
                        <?php wp_nonce_field( self::EXEMPT_NONCE, self::EXEMPT_NONCE ); ?>
                        <input type="checkbox" name="smpi_go_live_exempt" value="1" <?php checked( self::is_exempt( $post->ID ) ); ?>>
                        <span class="smpi-glc__track" aria-hidden="true"></span>
                        <span>Do not process this page</span>
                    </label>
                    <?php echo $this->dynamic_button( [ "label" => "Complete all", "working_label" => "Processing…", "success_label" => "Done", "error_label" => "Stopped", "class" => "button button-primary", "attrs" => [ "data-smpi-go-live-all" => "1" ] ] ); ?>
                </div>
            </div>
            <ul class="smpi-glc__list" data-smpi-go-live-items>
                <?php foreach ( $items as $item ) : ?>
                    <li class="smpi-glc__row" data-smpi-go-live-item="<?php echo esc_attr( $item["key"] ); ?>" data-smpi-view-selector="<?php echo esc_attr( $item["selector"] ); ?>">
                        <span class="smpi-glc__badge" data-smpi-go-live-status aria-live="polite"><span class="smpi-go-live-item__mark"></span></span>
                        <span class="smpi-glc__text">
                            <strong><?php echo esc_html( $item["label"] ); ?></strong>
                            <span data-smpi-go-live-message><?php echo esc_html( $item["description"] ); ?></span>
                        </span>
                        <span class="smpi-glc__actions">
                            <?php echo $this->dynamic_button( [ "label" => "Generate", "working_label" => "Generating…", "success_label" => "Done", "error_label" => "Failed", "class" => "button button-small smpi-go-live-process", "attrs" => [ "data-smpi-go-live-process" => $item["key"] ] ] ); ?>
                            <?php echo $this->dynamic_button( [ "label" => "View", "working_label" => "Finding…", "success_label" => "View", "error_label" => "Not found", "class" => "button-link smpi-go-live-view", "attrs" => [ "data-smpi-go-live-view" => $item["key"] ] ] ); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <details class="smpi-glc__log">
                <summary>Activity <span data-smpi-go-live-log-count></span></summary>
                <div data-smpi-go-live-log><p>No activity yet.</p></div>
            </details>
        </div>
        <?php
    }

    public function footer_assets(): void {
        $screen = function_exists( "get_current_screen" ) ? get_current_screen() : null;
        if ( ! $screen || "post" !== $screen->base ) {
            return;
        }
        ?>
        <style>
            .smpi-glc{margin-top:20px;border:1px solid #dcdcde;border-radius:6px;overflow:hidden;box-shadow:none}
            .smpi-glc__head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 16px;border-bottom:1px solid #f0f0f1;background:#fff}
            .smpi-glc__title{display:flex;align-items:center;gap:10px}.smpi-glc__title h2{margin:0;padding:0;font-size:14px;font-weight:600}
            .smpi-glc__progress{font-size:12px;color:#50575e;background:#f0f0f1;border-radius:999px;padding:2px 10px}.smpi-glc__progress.is-complete{background:#edfaef;color:#00691f}
            .smpi-glc__controls{display:flex;align-items:center;gap:14px}
            .smpi-glc__switch{display:inline-flex;align-items:center;gap:8px;font-size:12px;color:#50575e;cursor:pointer}.smpi-glc__switch input{position:absolute;opacity:0;width:1px;height:1px}
            .smpi-glc__track{position:relative;width:32px;height:18px;border-radius:999px;background:#c3c4c7;transition:background .15s}.smpi-glc__track:after{content:"";position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform .15s}
            .smpi-glc__switch input:checked+.smpi-glc__track{background:#d63638}.smpi-glc__switch input:checked+.smpi-glc__track:after{transform:translateX(14px)}.smpi-glc__switch input:focus-visible+.smpi-glc__track{box-shadow:0 0 0 2px #2271b1}
            .smpi-glc.is-exempt .smpi-glc__list{opacity:.5}
            .smpi-glc__list{margin:0;padding:0;list-style:none;background:#fff}
            .smpi-glc__row{display:flex;align-items:center;gap:12px;margin:0;padding:10px 16px;border-bottom:1px solid #f0f0f1}
            .smpi-glc__badge{flex:0 0 20px;width:20px;height:20px;border-radius:50%;border:2px solid #c3c4c7;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff}
            .smpi-glc__badge.is-done{background:#00a32a;border-color:#00a32a}.smpi-glc__badge.is-done .smpi-go-live-item__mark:after{content:"✓"}
            .smpi-glc__badge.is-error{border-color:#d63638}.smpi-glc__badge.is-warning{border-color:#dba617}
            .smpi-glc__text{flex:1;min-width:0;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}.smpi-glc__text strong{font-size:13px;font-weight:600;color:#1d2327}.smpi-glc__text span{font-size:12px;color:#646970}
            .smpi-glc__actions{display:flex;align-items:center;gap:10px}.smpi-glc__row.is-done .smpi-go-live-process{display:none}
            .smpi-glc__log{padding:8px 16px;background:#fcfcfc;font-size:12px;color:#50575e}.smpi-glc__log summary{cursor:pointer;font-weight:600}.smpi-glc__log p{margin:4px 0}
            .smpi-go-live-highlight{box-shadow:0 0 0 3px #3858e9!important;transition:box-shadow .2s ease}
            @media (max-width:782px){.smpi-glc__head{flex-wrap:wrap}.smpi-glc__row{flex-wrap:wrap}}
        </style>
        <script>
        jQuery(function($){
            const root = $("#smpi-going-live-checklist");
            if (!root.length) { return; }
            const cfg = {ajaxUrl: root.data("ajax-url") || window.ajaxurl, nonce: root.data("nonce") || "", postId: parseInt(root.data("post-id"), 10) || 0};
            const btn = window.HexaWpCoreDynamicButton || {start:function(){}, success:function(){}, error:function(){}, reset:function(){}};
            root.toggleClass("is-exempt", root.find("[name=smpi_go_live_exempt]").is(":checked"));
            root.on("change", "[name=smpi_go_live_exempt]", function(){ root.toggleClass("is-exempt", this.checked); });
            const processMap = {excerpt:"[data-smpi-generate-target='excerpt']", summary:"[data-smpi-generate-target='summary']", faqs:"[data-smpi-generate-target='faqs']", tts:".hexa-tts-generate-post"};

            function addLog(type, text) {
                const box = root.find("[data-smpi-go-live-log]");
                const prefix = type === "ok" ? "✓" : (type === "error" ? "X" : "!");
                if (box.find("p").length === 1 && box.text().indexOf("No activity yet") !== -1) { box.empty(); }
                box.prepend($("<p/>").append($("<strong/>").text(prefix + " ")).append(document.createTextNode(text)));
                root.find("[data-smpi-go-live-log-count]").text("(" + box.find("p").length + ")");
            }

            function setItemState(key, state, message) {
                const item = root.find("[data-smpi-go-live-item='" + key + "']");
                if (!item.length) { return; }
                const status = item.find("[data-smpi-go-live-status]");
                status.removeClass("is-done is-error is-warning").addClass(state === "done" ? "is-done" : (state === "error" || state === "missing" ? "is-error" : "is-warning"));
                item.toggleClass("is-done", state === "done");
                item.find("[data-smpi-go-live-message]").text(message || "Status checked.");
                const total = root.find("[data-smpi-go-live-item]").length, done = root.find(".smpi-glc__row.is-done").length;
                root.find("[data-smpi-go-live-progress]").text(done + " of " + total + " done").toggleClass("is-complete", done === total);
            }

            function refreshStatus() {
                return $.post(cfg.ajaxUrl, {action:"smpi_going_live_checklist_status", nonce:cfg.nonce, post_id:cfg.postId}).done(function(response){
                    const data = (response && response.data) || {};
                    if (!response || !response.success) { addLog("error", data.message || "Checklist status failed."); return; }
                    $.each(data.items || {}, function(key, item){ setItemState(key, item.state || "warning", item.message || "Status checked."); });
                }).fail(function(xhr){ addLog("error", "Checklist status HTTP " + (xhr.status || 0) + "."); });
            }

            function findTarget(selector) {
                if (!selector) { return $(); }
                const parts = selector.split(",");
                for (let i = 0; i < parts.length; i++) {
                    const found = $(parts[i].trim()).first();
                    if (found.length) { return found; }
                }
                return $();
            }

            function viewItem(key, button) {
                const item = root.find("[data-smpi-go-live-item='" + key + "']");
                const target = findTarget(item.data("smpi-view-selector") || "");
                btn.start(button, "Finding...");
                if (!target.length) {
                    btn.error(button, "Missing");
                    addLog("error", "Could not find the " + key + " field on this screen.");
                    return false;
                }
                $("html, body").animate({scrollTop: Math.max(0, target.offset().top - 90)}, 240);
                target.addClass("smpi-go-live-highlight");
                setTimeout(function(){ target.removeClass("smpi-go-live-highlight"); }, 1600);
                btn.success(button, "Found");
                addLog("ok", "Scrolled to " + key + ".");
                return true;
            }

            function processItem(key, button) {
                btn.start(button, "Creating...");
                addLog("working", "Processing " + key + ".");
                if (processMap[key]) {
                    const target = $(processMap[key]).not(root.find("button")).first();
                    if (!target.length) {
                        btn.error(button, "Missing", false);
                        addLog("error", "No existing processor found for " + key + ".");
                        return $.Deferred().reject().promise();
                    }
                    target.trigger("click");
                    return waitForStatusChange(key, button);
                }
                if (key === "verified_profiles_created" || key === "verified_profiles_internally_linked") {
                    viewItem(key, root.find("[data-smpi-go-live-view='" + key + "']").first());
                    return refreshStatus().then(function(){ btn.success(button, "Checked"); });
                }
                btn.error(button, "Unknown", false);
                addLog("error", "Unknown checklist item " + key + ".");
                return $.Deferred().reject().promise();
            }

            function waitForStatusChange(key, button) {
                let tries = 0;
                const maxTries = key === "tts" ? 90 : 18;
                const deferred = $.Deferred();
                function tick() {
                    tries++;
                    refreshStatus().always(function(){
                        const state = root.find("[data-smpi-go-live-item='" + key + "'] [data-smpi-go-live-status]").hasClass("is-done");
                        if (state) {
                            btn.success(button, "Updated");
                            addLog("ok", key + " is complete.");
                            deferred.resolve();
                            return;
                        }
                        if (tries >= maxTries) {
                            btn.error(button, "Check field", false);
                            addLog("error", key + " did not report complete after processing.");
                            deferred.reject();
                            return;
                        }
                        setTimeout(tick, 1600);
                    });
                }
                setTimeout(tick, 1800);
                return deferred.promise();
            }

            $(document).on("click", "[data-smpi-go-live-view]", function(){ viewItem($(this).data("smpi-go-live-view"), this); });
            $(document).on("click", "[data-smpi-go-live-process]", function(){ processItem($(this).data("smpi-go-live-process"), this); });
            $(document).on("click", "[data-smpi-go-live-all]", function(){
                const allButton = this;
                const keys = root.find("[data-smpi-go-live-item]").map(function(){ return $(this).data("smpi-go-live-item"); }).get();
                let chain = $.Deferred().resolve().promise();
                btn.start(allButton, "Processing...");
                keys.forEach(function(key){ chain = chain.then(function(){
                    if (root.find("[data-smpi-go-live-item='" + key + "'] [data-smpi-go-live-status]").hasClass("is-done")) {
                        addLog("ok", key + " already complete.");
                        return $.Deferred().resolve().promise();
                    }
                    return processItem(key, root.find("[data-smpi-go-live-process='" + key + "']").first()[0]);
                }); });
                chain.done(function(){ btn.success(allButton, "Checklist updated"); addLog("ok", "Do all finished."); }).fail(function(){ btn.error(allButton, "Stopped", false); addLog("error", "Do all stopped on a failed item."); });
            });
            refreshStatus();
        });
        </script>
        <?php
    }

    public function ajax_status( AjaxRequest $request ): array {
        $post_id = $request->int( 'post_id', 0, 'post' );
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            throw new AjaxFailure( 'Not allowed.', 403, 'forbidden' );
        }
        $post = get_post( $post_id );
        if ( ! $post || ! $this->supports_post( $post ) ) {
            throw AjaxFailure::bad_request( 'Unsupported post.' );
        }

        $items = [];
        foreach ( $this->items_for_post( $post ) as $item ) {
            $items[ $item["key"] ] = $this->status_for_item( $item["key"], $post );
        }
        return [ 'items' => $items ];
    }

    private function supports_post( \WP_Post $post ): bool {
        return in_array( $post->post_type, [ "post", "press-release" ], true );
    }

    public static function is_exempt( int $post_id ): bool {
        return "1" === (string) get_post_meta( $post_id, self::EXEMPT_META, true );
    }

    public function save_exempt( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST[ self::EXEMPT_NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::EXEMPT_NONCE ] ) ), self::EXEMPT_NONCE ) ) return;
        if ( wp_is_post_revision( $post_id ) || ! current_user_can( "edit_post", $post_id ) ) return;
        if ( empty( $_POST["smpi_go_live_exempt"] ) ) delete_post_meta( $post_id, self::EXEMPT_META );
        else update_post_meta( $post_id, self::EXEMPT_META, "1" );
    }

    /**
     * Server-side "Do all": generates every missing excerpt, summary and FAQ
     * set for a post, unless the post is marked "Do not process this page".
     * Other plugins call do_action( "smpi_go_live_process", $post_id ).
     *
     * @return array<string,array{ok:bool,message:string}>
     */
    public function process_missing( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post || ! $this->supports_post( $post ) || self::is_exempt( $post_id ) ) return [];
        $results = [];
        foreach ( [ "excerpt", "summary", "faqs" ] as $target ) {
            $status = $this->status_for_item( $target, get_post( $post_id ) );
            if ( "done" === $status["state"] ) continue;
            $results[ $target ] = apply_filters( "smpi_generate_content_for_post", [ "ok" => false, "message" => "Content generation is not available." ], $post_id, $target );
        }
        return $results;
    }

    private function items_for_post( \WP_Post $post ): array {
        $items = [];
        if ( $this->uses_verified_profiles( $post ) ) {
            $items[] = [ "key" => "verified_profiles_created", "label" => "Verified Profiles Created", "description" => "Checks the verified profiles shortcode/output for this post.", "selector" => ".elementor-shortcode, [data-widget_type='shortcode.default'], [class*='verified-profile'], [id*='verified-profile']" ];
        }
        $items[] = [ "key" => "excerpt", "label" => "Excerpt customized", "description" => "Checks the native WordPress excerpt.", "selector" => "#postexcerpt, #excerpt" ];
        $items[] = [ "key" => "summary", "label" => "Article Summary Created", "description" => "Checks the post_summary ACF field.", "selector" => "[data-name='post_summary'], .acf-field[data-name='post_summary']" ];
        $items[] = [ "key" => "faqs", "label" => "FAQS created", "description" => "Checks structured FAQ rows.", "selector" => "[data-key='field_smpi_post_faq_accordion'], .acf-field-smpi-post-faq-accordion, .acf-field[data-name='post_faq_items']" ];
        if ( $this->uses_verified_profiles( $post ) ) {
            $items[] = [ "key" => "verified_profiles_internally_linked", "label" => "Verified profiles internally linked", "description" => "Checks that verified profile output contains links.", "selector" => ".elementor-shortcode, [data-widget_type='shortcode.default'], [class*='verified-profile'], [id*='verified-profile']" ];
        }
        $items[] = [ "key" => "tts", "label" => "Text to speech", "description" => "Checks the article_audio ACF field.", "selector" => ".hexa-tts-postbox, [data-acf-field='article_audio'], .acf-field[data-name='article_audio']" ];
        return $items;
    }

    private function status_for_item( string $key, \WP_Post $post ): array {
        if ( "excerpt" === $key ) {
            return $this->status_from_bool( "" !== trim( (string) $post->post_excerpt ), "Excerpt is customized.", "No excerpt is saved." );
        }
        if ( "summary" === $key ) {
            return $this->status_from_bool( $this->has_value( $this->field_value( "post_summary", $post->ID ) ), "Article summary is saved.", "Article summary is empty." );
        }
        if ( "faqs" === $key ) {
            return $this->status_from_bool( ! empty( $this->current_faqs( $post->ID ) ), "Structured FAQs are saved.", "No structured FAQ rows found." );
        }
        if ( "tts" === $key ) {
            return $this->status_from_bool( $this->has_value( $this->field_value( "article_audio", $post->ID ) ), "Text-to-speech audio is saved.", "No article_audio value found." );
        }
        if ( "verified_profiles_created" === $key ) {
            $html = $this->rendered_verified_profiles( $post );
            return $this->status_from_bool( "" !== trim( wp_strip_all_tags( $html ) ) || false !== stripos( $html, "profile" ), "Verified profile output exists.", "Verified profile output is empty." );
        }
        if ( "verified_profiles_internally_linked" === $key ) {
            $html = $this->rendered_verified_profiles( $post );
            return $this->status_from_bool( (bool) preg_match( '/<a\s[^>]*href=["\']https?:\/\/[^"\']+/i', $html ), "Verified profile output contains links.", "Verified profile output has no links." );
        }
        return [ "state" => "error", "message" => "Unknown checklist item." ];
    }

    private function status_from_bool( bool $done, string $done_message, string $missing_message ): array {
        return [ "state" => $done ? "done" : "missing", "message" => $done ? $done_message : $missing_message ];
    }

    private function uses_verified_profiles( \WP_Post $post ): bool {
        if ( ! Dependencies::verified_profiles_plugin_active() ) {
            return false;
        }
        $haystack = (string) $post->post_content . "\n" . (string) get_post_meta( $post->ID, "_elementor_data", true );
        return false !== stripos( $haystack, "verified_profiles_loop" ) || false !== stripos( $haystack, "verified-profile" ) || false !== stripos( $haystack, "smp_verified" );
    }

    private function rendered_verified_profiles( \WP_Post $post ): string {
        if ( ! shortcode_exists( "verified_profiles_loop" ) ) {
            return "";
        }
        $previous_post = $GLOBALS["post"] ?? null;
        $GLOBALS["post"] = $post;
        setup_postdata( $post );
        $html = do_shortcode( '[verified_profiles_loop id="single-post"]' );
        if ( $previous_post instanceof \WP_Post ) {
            $GLOBALS["post"] = $previous_post;
            setup_postdata( $previous_post );
        } else {
            wp_reset_postdata();
        }
        return is_string( $html ) ? $html : "";
    }

    private function field_value( string $field, int $post_id ) {
        if ( \Hexa\PluginCore\Fields\Field::available() ) {
            $value = \Hexa\PluginCore\Fields\Field::get( $field, $post_id );
            if ( null !== $value && false !== $value && "" !== $value ) {
                return $value;
            }
        }
        return get_post_meta( $post_id, $field, true );
    }

    private function has_value( $value ): bool {
        if ( is_array( $value ) ) {
            return ! empty( array_filter( $value ) );
        }
        return "" !== trim( (string) $value );
    }

    private function current_faqs( int $post_id ): array {
        $value = $this->field_value( "post_faq_items", $post_id );
        if ( ! is_array( $value ) ) {
            return [];
        }
        $rows = [];
        foreach ( $value as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $question = isset( $row["question"] ) ? trim( (string) $row["question"] ) : "";
            $answer = isset( $row["answer"] ) ? trim( (string) $row["answer"] ) : "";
            if ( "" !== $question && "" !== $answer ) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function dynamic_button( array $args ): string {
        if ( class_exists( DynamicButton::class ) ) {
            return DynamicButton::render( $args );
        }
        $label = (string) ( $args["label"] ?? "Run" );
        $class = trim( (string) ( $args["class"] ?? "button" ) );
        $attrs = "";
        foreach ( (array) ( $args["attrs"] ?? [] ) as $name => $value ) {
            if ( null === $value || false === $value ) {
                continue;
            }
            $attrs .= " " . esc_attr( (string) $name );
            if ( true !== $value ) {
                $attrs .= "=\"" . esc_attr( (string) $value ) . "\"";
            }
        }
        return "<button type=\"button\" class=\"" . esc_attr( $class ) . "\"" . $attrs . ">" . esc_html( $label ) . "</button>";
    }
}
