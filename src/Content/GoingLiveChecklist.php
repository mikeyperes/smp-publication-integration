<?php
namespace smp_publication_integration\Content;

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

        $items = $this->items_for_post( $post );
        ?>
        <div id="smpi-going-live-checklist" class="postbox smpi-glc" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>" data-ajax-url="<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>" data-nonce="<?php echo esc_attr( Ajax::nonce() ); ?>">
            <div class="smpi-glc__head">
                <div class="smpi-glc__title">
                    <h2>Going Live</h2>
                    <span class="smpi-glc__progress" data-glc-progress>Checking…</span>
                </div>
                <div class="smpi-glc__controls">
                    <label class="smpi-glc__switch">
                        <?php wp_nonce_field( self::EXEMPT_NONCE, self::EXEMPT_NONCE ); ?>
                        <input type="checkbox" name="smpi_go_live_exempt" value="1" <?php checked( self::is_exempt( $post->ID ) ); ?>>
                        <span class="smpi-glc__track" aria-hidden="true"></span>
                        <span>Do not process this page</span>
                    </label>
                    <button type="button" class="button button-primary" data-glc-all>Complete all</button>
                </div>
            </div>
            <p class="smpi-glc__notice" data-glc-notice hidden></p>
            <ul class="smpi-glc__list">
                <?php foreach ( $items as $item ) : ?>
                    <li class="smpi-glc__row is-checking" data-glc-item="<?php echo esc_attr( $item["key"] ); ?>" data-glc-view-selector="<?php echo esc_attr( $item["selector"] ); ?>">
                        <span class="smpi-glc__badge" aria-hidden="true"></span>
                        <span class="smpi-glc__text">
                            <strong><?php echo esc_html( $item["label"] ); ?></strong>
                            <span class="smpi-glc__msg" data-glc-msg aria-live="polite"><?php echo esc_html( $item["description"] ); ?></span>
                        </span>
                        <span class="smpi-glc__actions">
                            <button type="button" class="button button-small" data-glc-generate>Generate</button>
                            <button type="button" class="button-link" data-glc-view>View</button>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <details class="smpi-glc__log">
                <summary>Activity <span data-glc-log-count></span></summary>
                <ol data-glc-log><li class="is-empty">No activity yet.</li></ol>
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
            #smpi-going-live-checklist.smpi-glc{margin-top:20px;border:1px solid #dcdcde;border-radius:4px;box-shadow:0 1px 1px rgba(0,0,0,.04);overflow:hidden}
            .smpi-glc__head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 14px;border-bottom:1px solid #f0f0f1;background:#fff}
            .smpi-glc__title{display:flex;align-items:center;gap:10px}.smpi-glc__title h2{margin:0!important;padding:0!important;font-size:14px!important;font-weight:600;line-height:1.4}
            .smpi-glc__progress{font-size:12px;line-height:20px;padding:0 9px;border-radius:10px;background:#f0f0f1;color:#50575e}.smpi-glc__progress.is-complete{background:#edfaef;color:#007017}
            .smpi-glc__controls{display:flex;align-items:center;gap:16px}
            .smpi-glc__switch{position:relative;display:inline-flex;align-items:center;gap:8px;font-size:13px;color:#3c434a;cursor:pointer;user-select:none}
            .smpi-glc__switch input{position:absolute;opacity:0;width:1px;height:1px;margin:0}
            .smpi-glc__track{position:relative;flex:0 0 30px;width:30px;height:16px;border-radius:8px;background:#a7aaad;transition:background .15s}
            .smpi-glc__track:after{content:"";position:absolute;top:2px;left:2px;width:12px;height:12px;border-radius:50%;background:#fff;transition:transform .15s}
            .smpi-glc__switch input:checked+.smpi-glc__track{background:#2271b1}.smpi-glc__switch input:checked+.smpi-glc__track:after{transform:translateX(14px)}
            .smpi-glc__switch input:focus-visible+.smpi-glc__track{outline:2px solid #2271b1;outline-offset:2px}
            .smpi-glc__notice{margin:0;padding:8px 14px;font-size:12px;border-bottom:1px solid #f0f0f1;background:#fcf9e8;color:#614a00}.smpi-glc__notice.is-ok{background:#edfaef;color:#007017}
            .smpi-glc__list{margin:0!important;padding:0;list-style:none;background:#fff}
            .smpi-glc.is-exempt .smpi-glc__list{opacity:.55}
            .smpi-glc__row{display:flex;align-items:center;gap:12px;margin:0!important;padding:9px 14px;border-bottom:1px solid #f0f0f1;min-height:28px}
            .smpi-glc__row:last-child{border-bottom:0}
            .smpi-glc__badge{position:relative;flex:0 0 18px;width:18px;height:18px;border-radius:50%;border:2px solid #c3c4c7;box-sizing:border-box}
            .smpi-glc__row.is-done .smpi-glc__badge{background:#00a32a;border-color:#00a32a}
            .smpi-glc__row.is-done .smpi-glc__badge:after{content:"";position:absolute;left:4px;top:1px;width:4px;height:8px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
            .smpi-glc__row.is-failed .smpi-glc__badge{border-color:#d63638}
            .smpi-glc__row.is-working .smpi-glc__badge,.smpi-glc__row.is-checking .smpi-glc__badge{border-color:#c3c4c7;border-top-color:#2271b1;animation:smpi-glc-spin .8s linear infinite}
            @keyframes smpi-glc-spin{to{transform:rotate(360deg)}}
            .smpi-glc__text{flex:1;min-width:0;display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
            .smpi-glc__text strong{font-size:13px;font-weight:600;color:#1d2327}
            .smpi-glc__msg{font-size:12px;color:#646970}.smpi-glc__row.is-failed .smpi-glc__msg{color:#b32d2e}
            .smpi-glc__actions{display:flex;align-items:center;gap:12px;flex:0 0 auto}
            .smpi-glc__actions .button-small{min-width:84px;text-align:center}
            .smpi-glc__row.is-done [data-glc-generate],.smpi-glc__row.is-checking [data-glc-generate]{visibility:hidden}
            .smpi-glc__log{padding:6px 14px;border-top:1px solid #f0f0f1;background:#f6f7f7;font-size:12px;color:#50575e}
            .smpi-glc__log summary{cursor:pointer;font-weight:600;padding:2px 0}.smpi-glc__log ol{margin:6px 0 4px 18px}.smpi-glc__log li{margin:2px 0}.smpi-glc__log li.is-error{color:#b32d2e}
            .smpi-go-live-highlight{box-shadow:0 0 0 3px #2271b1!important;transition:box-shadow .2s ease}
            @media (max-width:782px){.smpi-glc__head,.smpi-glc__row{flex-wrap:wrap}}
        </style>
        <script>
        jQuery(function($){
            const root = $("#smpi-going-live-checklist");
            if (!root.length) { return; }
            const cfg = {ajaxUrl: root.data("ajax-url") || window.ajaxurl, nonce: root.data("nonce") || "", postId: parseInt(root.data("post-id"), 10) || 0};
            const contentTargets = {excerpt: true, summary: true, faqs: true};
            const labels = {};
            root.find("[data-glc-item]").each(function(){ labels[$(this).data("glc-item")] = $(this).find("strong").text(); });

            function row(key) { return root.find("[data-glc-item='" + key + "']"); }
            function log(text, isError) {
                const box = root.find("[data-glc-log]");
                box.find(".is-empty").remove();
                const time = new Date().toLocaleTimeString([], {hour: "numeric", minute: "2-digit"});
                box.prepend($("<li/>").toggleClass("is-error", !!isError).text(time + " — " + text));
                root.find("[data-glc-log-count]").text("(" + box.children().length + ")");
            }
            function notice(text, ok) {
                const el = root.find("[data-glc-notice]");
                if (!text) { el.attr("hidden", true); return; }
                el.text(text).toggleClass("is-ok", !!ok).removeAttr("hidden");
            }
            function setState(key, state, message) {
                const item = row(key);
                if (!item.length) { return; }
                item.removeClass("is-done is-missing is-failed is-working is-checking").addClass("is-" + state);
                if (message) { item.find("[data-glc-msg]").text(message); }
                item.find("[data-glc-generate]").prop("disabled", state === "working").text(state === "failed" ? "Try again" : (state === "working" ? "Generating…" : "Generate"));
                const total = root.find("[data-glc-item]").length, done = root.find(".smpi-glc__row.is-done").length;
                root.find("[data-glc-progress]").text(done + " of " + total + " done").toggleClass("is-complete", done === total);
            }
            function refreshStatus() {
                return $.post(cfg.ajaxUrl, {action: "smpi_going_live_checklist_status", nonce: cfg.nonce, post_id: cfg.postId}).done(function(response){
                    const data = (response && response.data) || {};
                    if (!response || !response.success) { notice(data.message || "Could not check the checklist status."); return; }
                    $.each(data.items || {}, function(key, item){
                        if (row(key).hasClass("is-working") || row(key).hasClass("is-failed")) { return; }
                        setState(key, item.state === "done" ? "done" : "missing", item.message);
                    });
                }).fail(function(xhr){ notice("Could not check the checklist status (HTTP " + (xhr.status || 0) + ")."); });
            }
            function errorText(xhr, response) {
                const data = (response && response.data) || (xhr && xhr.responseJSON && xhr.responseJSON.data) || {};
                return data.message || data.error || ("Request failed (HTTP " + ((xhr && xhr.status) || 0) + ").");
            }
            function generate(key) {
                const deferred = $.Deferred();
                setState(key, "working", "Generating…");
                log("Generating " + labels[key] + ".");
                if (contentTargets[key]) {
                    $.post(cfg.ajaxUrl, {action: "smpi_generate_content", nonce: cfg.nonce, post_id: cfg.postId, target: key}).done(function(response){
                        if (!response || !response.success) { fail(key, errorText(null, response)); deferred.reject(); return; }
                        row(key).removeClass("is-working");
                        refreshStatus().always(function(){ log(labels[key] + " generated."); deferred.resolve(); });
                    }).fail(function(xhr){ fail(key, errorText(xhr)); deferred.reject(); });
                } else if (key === "tts" && $(".hexa-tts-generate-post").length) {
                    $(".hexa-tts-generate-post").first().trigger("click");
                    let tries = 0;
                    (function poll(){
                        setTimeout(function(){
                            row(key).removeClass("is-working");
                            refreshStatus().always(function(){
                                if (row(key).hasClass("is-done")) { log(labels[key] + " generated."); deferred.resolve(); return; }
                                if (++tries >= 60) { fail(key, "Audio is still being created. Check back in a few minutes."); deferred.reject(); return; }
                                row(key).addClass("is-working"); poll();
                            });
                        }, 3000);
                    })();
                } else {
                    fail(key, "This item can't be generated here. Use View to check it.");
                    deferred.reject();
                }
                return deferred.promise();
            }
            function fail(key, message) {
                setState(key, "failed", "Couldn't generate: " + message);
                log(labels[key] + ": " + message, true);
            }
            function view(key) {
                const parts = String(row(key).data("glc-view-selector") || "").split(",");
                let target = $();
                for (let i = 0; i < parts.length && !target.length; i++) { target = $(parts[i].trim()).first(); }
                if (!target.length) { notice(labels[key] + " isn't shown on this screen."); return; }
                $("html, body").animate({scrollTop: Math.max(0, target.offset().top - 90)}, 240);
                target.addClass("smpi-go-live-highlight");
                setTimeout(function(){ target.removeClass("smpi-go-live-highlight"); }, 1600);
            }

            root.toggleClass("is-exempt", root.find("[name=smpi_go_live_exempt]").is(":checked"));
            root.on("change", "[name=smpi_go_live_exempt]", function(){ root.toggleClass("is-exempt", this.checked); });
            root.on("click", "[data-glc-view]", function(){ view($(this).closest("[data-glc-item]").data("glc-item")); });
            root.on("click", "[data-glc-generate]", function(){ notice(""); generate($(this).closest("[data-glc-item]").data("glc-item")); });
            root.on("click", "[data-glc-all]", function(){
                const all = $(this).prop("disabled", true).text("Working…");
                const keys = root.find("[data-glc-item]").not(".is-done").map(function(){ return $(this).data("glc-item"); }).get();
                let failed = 0, chain = $.Deferred().resolve().promise();
                notice("");
                keys.forEach(function(key){ chain = chain.then(function(){ return generate(key).then(null, function(){ failed++; return $.Deferred().resolve().promise(); }); }); });
                chain.always(function(){
                    all.prop("disabled", false).text("Complete all");
                    if (!keys.length) { notice("Everything is already done.", true); }
                    else if (failed) { notice(failed + " of " + keys.length + " couldn't be generated. The reason is shown on each item."); }
                    else { notice("All items are done.", true); }
                });
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

}
