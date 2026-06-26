<?php
namespace smp_publication_integration\Content;

use Hexa\PluginCore\WpAdminComponents\DynamicButton;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Support\Dependencies;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class GoingLiveChecklist {
    private const ACTION_STATUS = "smpi_going_live_checklist_status";

    public function register(): void {
        add_action( "edit_form_after_editor", [ $this, "render" ] );
        add_action( "admin_footer-post.php", [ $this, "footer_assets" ] );
        add_action( "admin_footer-post-new.php", [ $this, "footer_assets" ] );
        add_action( "wp_ajax_" . self::ACTION_STATUS, [ $this, "ajax_status" ] );
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
        <div id="smpi-going-live-checklist" class="postbox smpi-going-live-checklist" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>" data-ajax-url="<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>" data-nonce="<?php echo esc_attr( Ajax::nonce() ); ?>">
            <div class="postbox-header smpi-going-live-checklist__header">
                <h2>Going Live Checklist</h2>
                <div class="smpi-going-live-checklist__header-actions">
                    <?php echo $this->dynamic_button( [ "label" => "Do all", "working_label" => "Processing...", "success_label" => "Checklist updated", "error_label" => "Stopped", "class" => "button button-primary", "attrs" => [ "data-smpi-go-live-all" => "1" ] ] ); ?>
                </div>
            </div>
            <div class="inside smpi-going-live-checklist__body">
                <div class="smpi-going-live-checklist__items" data-smpi-go-live-items>
                    <?php foreach ( $items as $item ) : ?>
                        <div class="smpi-go-live-item" data-smpi-go-live-item="<?php echo esc_attr( $item["key"] ); ?>" data-smpi-view-selector="<?php echo esc_attr( $item["selector"] ); ?>">
                            <div class="smpi-go-live-item__status" data-smpi-go-live-status aria-live="polite"><span class="smpi-go-live-item__circle">⚪</span><span class="smpi-go-live-item__mark">!</span></div>
                            <div class="smpi-go-live-item__content">
                                <strong><?php echo esc_html( $item["label"] ); ?></strong>
                                <span data-smpi-go-live-message><?php echo esc_html( $item["description"] ); ?></span>
                            </div>
                            <div class="smpi-go-live-item__actions">
                                <?php echo $this->dynamic_button( [ "label" => "Process", "working_label" => "Creating...", "success_label" => "Updated", "error_label" => "Failed", "class" => "button button-secondary smpi-go-live-process", "attrs" => [ "data-smpi-go-live-process" => $item["key"] ] ] ); ?>
                                <?php echo $this->dynamic_button( [ "label" => "View", "working_label" => "Finding...", "success_label" => "Found", "error_label" => "Missing", "class" => "button smpi-go-live-view", "attrs" => [ "data-smpi-go-live-view" => $item["key"] ] ] ); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="smpi-go-live-log" aria-live="polite">
                    <h3>Activity log</h3>
                    <div data-smpi-go-live-log><p>No checklist activity yet.</p></div>
                </div>
            </div>
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
            .smpi-going-live-checklist{margin-top:22px}.smpi-going-live-checklist .inside{margin:0;padding:0}.smpi-going-live-checklist__header{align-items:center}.smpi-going-live-checklist__header-actions{margin-left:auto;padding-right:12px}.smpi-going-live-checklist__body{background:#fff}.smpi-going-live-checklist__items{display:grid;gap:0}.smpi-go-live-item{align-items:center;border-bottom:1px solid #e6ebf2;display:grid;gap:12px;grid-template-columns:auto minmax(0,1fr) auto;padding:14px 16px}.smpi-go-live-item__status{align-items:center;display:flex;font-size:18px;font-weight:800;gap:8px;min-width:56px}.smpi-go-live-item__circle{font-size:16px}.smpi-go-live-item__mark{display:inline-block;min-width:16px;text-align:center}.smpi-go-live-item__status.is-done .smpi-go-live-item__mark{color:#16803c}.smpi-go-live-item__status.is-error .smpi-go-live-item__mark{color:#b42318}.smpi-go-live-item__status.is-warning .smpi-go-live-item__mark{color:#a15c00}.smpi-go-live-item__content{display:grid;gap:3px}.smpi-go-live-item__content strong{font-size:14px}.smpi-go-live-item__content span{color:#596579}.smpi-go-live-item__actions{display:flex;gap:8px;justify-content:flex-end}.smpi-go-live-item__actions .button{min-height:32px}.smpi-go-live-log{padding:16px}.smpi-go-live-log h3{font-size:13px;letter-spacing:.08em;margin:0 0 8px;text-transform:uppercase}.smpi-go-live-log p{margin:4px 0}.smpi-go-live-highlight{box-shadow:0 0 0 3px #3858e9!important;transition:box-shadow .2s ease}@media (max-width:782px){.smpi-go-live-item{grid-template-columns:1fr}.smpi-go-live-item__actions{justify-content:flex-start}}
        </style>
        <script>
        jQuery(function($){
            const root = $("#smpi-going-live-checklist");
            if (!root.length) { return; }
            const cfg = {ajaxUrl: root.data("ajax-url") || window.ajaxurl, nonce: root.data("nonce") || "", postId: parseInt(root.data("post-id"), 10) || 0};
            const btn = window.HexaWpCoreDynamicButton || {start:function(){}, success:function(){}, error:function(){}, reset:function(){}};
            const processMap = {excerpt:"[data-smpi-generate-target='excerpt']", summary:"[data-smpi-generate-target='summary']", faqs:"[data-smpi-generate-target='faqs']", tts:".hexa-tts-generate-post"};

            function addLog(type, text) {
                const box = root.find("[data-smpi-go-live-log]");
                const prefix = type === "ok" ? "✓" : (type === "error" ? "X" : "!");
                if (box.find("p").length === 1 && box.text().indexOf("No checklist activity") !== -1) { box.empty(); }
                box.prepend($("<p/>").append($("<strong/>").text(prefix + " ")).append(document.createTextNode(text)));
            }

            function setItemState(key, state, message) {
                const item = root.find("[data-smpi-go-live-item='" + key + "']");
                if (!item.length) { return; }
                const status = item.find("[data-smpi-go-live-status]");
                const mark = state === "done" ? "✓" : (state === "error" || state === "missing" ? "X" : "!");
                status.removeClass("is-done is-error is-warning").addClass(state === "done" ? "is-done" : (state === "error" || state === "missing" ? "is-error" : "is-warning"));
                status.find(".smpi-go-live-item__mark").text(mark);
                item.find("[data-smpi-go-live-message]").text(message || "Status checked.");
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

    public function ajax_status(): void {
        $post_id = isset( $_POST["post_id"] ) ? absint( $_POST["post_id"] ) : 0;
        if ( ! $post_id || ! current_user_can( "edit_post", $post_id ) || ! check_ajax_referer( Ajax::NONCE, "nonce", false ) ) {
            wp_send_json_error( [ "message" => "Not allowed." ], 403 );
        }
        $post = get_post( $post_id );
        if ( ! $post || ! $this->supports_post( $post ) ) {
            wp_send_json_error( [ "message" => "Unsupported post." ], 400 );
        }

        $items = [];
        foreach ( $this->items_for_post( $post ) as $item ) {
            $items[ $item["key"] ] = $this->status_for_item( $item["key"], $post );
        }
        wp_send_json_success( [ "items" => $items ] );
    }

    private function supports_post( \WP_Post $post ): bool {
        return in_array( $post->post_type, [ "post", "press-release" ], true );
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
        if ( function_exists( "get_field" ) ) {
            $value = get_field( $field, $post_id );
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
