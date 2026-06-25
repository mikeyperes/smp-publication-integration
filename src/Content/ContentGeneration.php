<?php
namespace smp_publication_integration\Content;

use Hexa\PluginCore\CredentialVault\CredentialStore;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Config;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class ContentGeneration {
    private const TAB = "content_generation";
    private const CREDENTIAL_SLUG = "smp-publication-integration";
    private const CREDENTIAL_KEY = "content_generation_api_key";
    private const LOG_META_KEY = "_smpi_content_generation_log";
    private const DEFAULT_API_BASE = "https://publish.scalemypublication.com/api/smp-content-generation/v1";

    public function register(): void {
        add_filter( "smpi_dashboard_tabs", [ $this, "tabs" ] );
        add_filter( "smpi_render_dashboard_tab", [ $this, "render_tab" ], 10, 2 );
        add_action( "admin_footer", [ $this, "admin_footer_script" ] );
        add_action( "admin_footer-post.php", [ $this, "post_footer_script" ] );
        add_action( "admin_footer-post-new.php", [ $this, "post_footer_script" ] );
        add_action( "add_meta_boxes", [ $this, "add_meta_boxes" ] );
        add_action( "wp_ajax_smpi_content_generation_save_key", [ $this, "save_key" ] );
        add_action( "wp_ajax_smpi_content_generation_test", [ $this, "test_connection" ] );
        add_action( "wp_ajax_smpi_generate_content", [ $this, "generate_content" ] );
    }

    public function tabs( array $tabs ): array {
        $tabs[ self::TAB ] = "Content Generation";
        return $tabs;
    }

    public function render_tab( bool $rendered, string $id ): bool {
        if ( self::TAB !== $id ) {
            return $rendered;
        }
        $settings = Settings::all();
        $store = new CredentialStore();
        $masked = $store->get_masked( self::CREDENTIAL_SLUG, self::CREDENTIAL_KEY );
        $fallback = $this->tts_api_key() ? "TTS key fallback detected" : "No TTS fallback key detected";
        ?>
        <section class="smpi-section smpi-content-generation-tab">
            <div class="smpi-feature-card">
                <div class="smpi-feature-head">
                    <div><span class="smpi-kicker">Publish Scale API</span><h2>Content Generation</h2></div>
                    <?php echo $this->switch_control( "content_generation_enabled", ! empty( $settings["content_generation_enabled"] ) ); ?>
                </div>
                <p>Adds one-click generators to post edit screens for excerpts, post summaries, and structured FAQs. The writing rules live on publish.scalemypublication.com; this plugin sends post context and stores the returned fields.</p>
            </div>

            <div class="smpi-card-grid smpi-card-grid--three">
                <div class="smpi-feature-card"><h3>API base</h3><input class="regular-text smpi-setting" data-key="content_generation_api_base" value="<?php echo esc_attr( (string) $settings["content_generation_api_base"] ); ?>"><span class="spinner"></span><span class="smpi-save-state"></span></div>
                <div class="smpi-feature-card"><h3>Timeout</h3><label><input class="small-text smpi-setting" type="number" min="5" max="120" data-key="content_generation_timeout" value="<?php echo esc_attr( (string) $settings["content_generation_timeout"] ); ?>"> seconds</label><span class="spinner"></span><span class="smpi-save-state"></span></div>
                <div class="smpi-feature-card"><h3>API key</h3><p>Stored in Hexa Credential Vault. <?php echo esc_html( $fallback ); ?>.</p><p><code data-smpi-content-key-mask><?php echo esc_html( $masked ?: "No SMP key saved" ); ?></code></p><input class="regular-text" type="password" autocomplete="new-password" data-smpi-content-api-key placeholder="Paste SMP content API key"><p><button type="button" class="button button-primary" data-smpi-save-content-key>Save key</button> <button type="button" class="button" data-smpi-test-content-api>Test connection</button> <span class="spinner"></span> <span data-smpi-content-key-state></span></p></div>
            </div>

            <div class="smpi-feature-card">
                <h3>Post editor buttons</h3>
                <p>On supported post edit screens the module adds buttons near the existing excerpt, post summary, and FAQ fields. Each action has a creating state, an activity log, and a success or error result.</p>
                <ul>
                    <li><code>excerpt</code> updates the native WordPress excerpt.</li>
                    <li><code>summary</code> updates the <code>post_summary</code> ACF field.</li>
                    <li><code>faqs</code> updates the <code>post_faq_items</code> ACF repeater and enables FAQ schema for the post.</li>
                </ul>
                <p>Handoff for the publish-side reporting portal: <code>docs/publish-scale-content-generation-handoff.md</code></p>
            </div>
        </section>
        <?php
        return true;
    }

    public function add_meta_boxes(): void {
        if ( ! Settings::bool( "content_generation_enabled" ) ) {
            return;
        }
        foreach ( [ "post", "press-release", "press_release" ] as $post_type ) {
            add_meta_box( "smpi-content-generation", "SMP Content Generation", [ $this, "render_meta_box" ], $post_type, "side", "default" );
        }
    }

    public function render_meta_box( \WP_Post $post ): void {
        $log = $this->generation_log( $post->ID );
        echo "<p>Generate excerpt, summary, or FAQs from the current post content.</p>";
        foreach ( [ "excerpt" => "Generate excerpt", "summary" => "Generate summary", "faqs" => "Generate FAQs" ] as $target => $label ) {
            echo "<p><button type=\"button\" class=\"button button-secondary smpi-generate-content-button\" data-smpi-generate-target=\"" . esc_attr( $target ) . "\">" . esc_html( $label ) . "</button></p>";
        }
        echo "<div class=\"smpi-generation-log\" data-smpi-generation-log>";
        if ( empty( $log ) ) {
            echo "<p>No generation activity for this post yet.</p>";
        } else {
            foreach ( array_slice( array_reverse( $log ), 0, 5 ) as $entry ) {
                echo "<p><strong>" . esc_html( $entry["status"] ?? "log" ) . "</strong> " . esc_html( $entry["message"] ?? "" ) . "</p>";
            }
        }
        echo "</div>";
    }

    public function post_footer_script(): void {
        $screen = function_exists( "get_current_screen" ) ? get_current_screen() : null;
        if ( ! $screen || "post" !== $screen->base || ! Settings::bool( "content_generation_enabled" ) ) {
            return;
        }
        $post_id = isset( $_GET["post"] ) ? absint( $_GET["post"] ) : 0;
        if ( ! $post_id ) {
            return;
        }
        ?>
        <style>
        .smpi-generation-control{border:1px solid #d7deea;border-radius:10px;margin:12px 0;padding:12px;background:#f8fafc}.smpi-generation-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.smpi-generation-log{font-size:12px;line-height:1.5;margin-top:8px}.smpi-generation-status.is-ok{color:#138a36}.smpi-generation-status.is-error{color:#b42318}.smpi-generation-status.is-working{color:#3858e9}
        </style>
        <script>
        jQuery(function($){
            const config = {ajaxUrl: window.ajaxurl, nonce: <?php echo wp_json_encode( Ajax::nonce() ); ?>, postId: <?php echo (int) $post_id; ?>};
            const targets = [
                {key:"excerpt", label:"excerpt", button:"Generate excerpt", host:"#postexcerpt .inside, #submitpost", field:"#excerpt"},
                {key:"summary", label:"post summary", button:"Generate summary", host:"[data-name=\"post_summary\"], .acf-field[data-name=\"post_summary\"]", field:"[data-name=\"post_summary\"] textarea, .acf-field[data-name=\"post_summary\"] textarea"},
                {key:"faqs", label:"FAQs", button:"Generate FAQs", host:"[data-name=\"post_faq_items\"], .acf-field[data-name=\"post_faq_items\"]", field:""}
            ];
            function state(control, type, text){control.find(".smpi-generation-status").removeClass("is-ok is-error is-working").addClass("is-" + type).text(text);control.find(".spinner").toggleClass("is-active", type === "working");}
            function log(control, type, text){const line=$("<div/>").append($("<strong/>").text(type + " ")).append(document.createTextNode(text));control.find(".smpi-generation-log").prepend(line);}
            function updateVisibleField(target, value){if(!value){return;} const meta=targets.find(function(item){return item.key===target;}); if(!meta || !meta.field){return;} const field=$(meta.field).first(); if(!field.length){return;} field.val(value).trigger("change");}
            function install(meta){const host=$(meta.host).first(); if(!host.length || host.data("smpiGenerationInstalled")){return;} host.data("smpiGenerationInstalled", true); const control=$("<div class=\"smpi-generation-control\" data-smpi-generation-control=\""+meta.key+"\"><div class=\"smpi-generation-actions\"><button type=\"button\" class=\"button button-primary\" data-smpi-generate-target=\""+meta.key+"\">"+meta.button+"</button><span class=\"spinner\"></span><span class=\"smpi-generation-status\">Ready.</span></div><div class=\"smpi-generation-log\"></div></div>"); host.append(control);}
            targets.forEach(install);
            $(document).on("click", "[data-smpi-generate-target]", function(){
                const button=$(this); const target=button.data("smpi-generate-target"); const control=button.closest("[data-smpi-generation-control], #smpi-content-generation");
                button.prop("disabled", true); state(control, "working", "Creating " + target + "..."); log(control, "working", "Creating " + target + ".");
                $.post(config.ajaxUrl, {action:"smpi_generate_content", nonce:config.nonce, post_id:config.postId, target:target}).done(function(response){
                    const ok=!!(response && response.success); const data=(response && response.data) || {}; const message=data.message || (ok ? "Generated." : "Generation failed.");
                    state(control, ok ? "ok" : "error", (ok ? "✓ " : "✕ ") + message); log(control, ok ? "ok" : "error", message);
                    if(ok && data.value){ updateVisibleField(target, data.value); }
                    if(ok && target === "faqs"){ log(control, "ok", "FAQ rows saved. Reload the editor to see repeater rows."); }
                }).fail(function(xhr){ const message="HTTP " + (xhr.status || 0) + " request failed."; state(control, "error", "✕ " + message); log(control, "error", message); }).always(function(){ button.prop("disabled", false); });
            });
        });
        </script>
        <?php
    }

    public function admin_footer_script(): void {
        $screen = function_exists( "get_current_screen" ) ? get_current_screen() : null;
        if ( ! $screen || "settings_page_smp-publication-integration" !== $screen->id ) {
            return;
        }
        $tab = isset( $_GET["tab"] ) ? sanitize_key( wp_unslash( (string) $_GET["tab"] ) ) : "overview";
        if ( self::TAB !== $tab ) {
            return;
        }
        ?>
        <script>
        jQuery(function($){
            const nonce = (window.smpiAdmin && window.smpiAdmin.nonce) ? window.smpiAdmin.nonce : <?php echo wp_json_encode( Ajax::nonce() ); ?>;
            function state(card, type, text){
                card.find(".spinner").toggleClass("is-active", type === "working");
                card.find("[data-smpi-content-key-state]").removeClass("is-ok is-error is-working").addClass("is-" + type).text(text);
            }
            $(document).on("click", "[data-smpi-save-content-key]", function(){
                const button = $(this); const card = button.closest(".smpi-feature-card");
                button.prop("disabled", true); state(card, "working", "Saving API key...");
                $.post(window.ajaxurl, {action:"smpi_content_generation_save_key", nonce:nonce, api_key:card.find("[data-smpi-content-api-key]").val() || ""})
                    .done(function(response){ const data = (response && response.data) || {}; if (response && response.success) { card.find("[data-smpi-content-key-mask]").text(data.masked || "No SMP key saved"); card.find("[data-smpi-content-api-key]").val(""); state(card, "ok", "Saved: " + (data.message || "API key saved.")); } else { state(card, "error", "Failed: " + (data.message || "Save failed.")); } })
                    .fail(function(xhr){ state(card, "error", "Failed: HTTP " + (xhr.status || 0) + " save failed."); })
                    .always(function(){ button.prop("disabled", false); });
            });
            $(document).on("click", "[data-smpi-test-content-api]", function(){
                const button = $(this); const card = button.closest(".smpi-feature-card");
                button.prop("disabled", true); state(card, "working", "Testing API connection...");
                $.post(window.ajaxurl, {action:"smpi_content_generation_test", nonce:nonce})
                    .done(function(response){ const data = (response && response.data) || {}; state(card, response && response.success ? "ok" : "error", (response && response.success ? "Connected: " : "Failed: " ) + (data.message || "Connection test failed.")); })
                    .fail(function(xhr){ state(card, "error", "Failed: HTTP " + (xhr.status || 0) + " test failed."); })
                    .always(function(){ button.prop("disabled", false); });
            });
        });
        </script>
        <?php
    }

    public function save_key(): void {
        if ( ! current_user_can( "manage_options" ) || ! check_ajax_referer( Ajax::NONCE, "nonce", false ) ) {
            wp_send_json_error( [ "message" => "Not allowed." ], 403 );
        }
        $key = isset( $_POST["api_key"] ) ? trim( (string) wp_unslash( $_POST["api_key"] ) ) : "";
        $store = new CredentialStore();
        if ( "" === $key ) {
            $store->delete( self::CREDENTIAL_SLUG, self::CREDENTIAL_KEY );
            wp_send_json_success( [ "message" => "Content API key removed.", "masked" => "" ] );
        }
        $store->store( self::CREDENTIAL_SLUG, self::CREDENTIAL_KEY, $key );
        wp_send_json_success( [ "message" => "Content API key saved.", "masked" => $store->mask( $key ) ] );
    }

    public function test_connection(): void {
        if ( ! current_user_can( "manage_options" ) || ! check_ajax_referer( Ajax::NONCE, "nonce", false ) ) {
            wp_send_json_error( [ "message" => "Not allowed." ], 403 );
        }
        $result = $this->api_request( "/status", [ "site_url" => home_url(), "plugin_version" => Config::VERSION ], 15 );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ "message" => $result->get_error_message() ] );
        }
        wp_send_json_success( [ "message" => "API responded.", "response" => $result ] );
    }

    public function generate_content(): void {
        $post_id = isset( $_POST["post_id"] ) ? absint( $_POST["post_id"] ) : 0;
        $target = isset( $_POST["target"] ) ? sanitize_key( (string) wp_unslash( $_POST["target"] ) ) : "";
        if ( ! $post_id || ! in_array( $target, [ "excerpt", "summary", "faqs" ], true ) || ! current_user_can( "edit_post", $post_id ) || ! check_ajax_referer( Ajax::NONCE, "nonce", false ) ) {
            wp_send_json_error( [ "message" => "Not allowed or invalid request." ], 403 );
        }
        if ( ! Settings::bool( "content_generation_enabled" ) ) {
            wp_send_json_error( [ "message" => "Content generation is disabled." ] );
        }
        $this->add_generation_log( $post_id, "working", "Creating " . $target . "." );
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( [ "message" => "Post not found." ] );
        }
        $payload = $this->payload_for_post( $post, $target );
        $result = $this->api_request( "/generate", $payload, (int) Settings::get( "content_generation_timeout", 45 ) );
        if ( is_wp_error( $result ) ) {
            $message = $result->get_error_message();
            $this->add_generation_log( $post_id, "error", $message );
            wp_send_json_error( [ "message" => $message, "log" => $this->generation_log( $post_id ) ] );
        }
        $value = $this->extract_generated_value( $result, $target );
        if ( "" === $value && "faqs" !== $target ) {
            $message = "API response did not include " . $target . ".";
            $this->add_generation_log( $post_id, "error", $message );
            wp_send_json_error( [ "message" => $message, "response" => $result ] );
        }
        $saved = $this->save_generated_value( $post_id, $target, $value, $result );
        if ( is_wp_error( $saved ) ) {
            $message = $saved->get_error_message();
            $this->add_generation_log( $post_id, "error", $message );
            wp_send_json_error( [ "message" => $message ] );
        }
        $message = ucfirst( $target ) . " saved.";
        $this->add_generation_log( $post_id, "ok", $message );
        wp_send_json_success( [ "message" => $message, "value" => $value, "log" => $this->generation_log( $post_id ) ] );
    }

    private function switch_control( string $key, bool $enabled ): string {
        return "<label class=\"smpi-switch\"><input class=\"smpi-setting\" type=\"checkbox\" data-key=\"" . esc_attr( $key ) . "\" value=\"1\" " . checked( $enabled, true, false ) . "><span></span><strong>" . ( $enabled ? "Enabled" : "Disabled" ) . "</strong></label><span class=\"spinner\"></span><span class=\"smpi-save-state\"></span>";
    }

    private function api_request( string $path, array $payload, int $timeout = 45 ) {
        $api_key = $this->api_key();
        if ( "" === $api_key ) {
            return new \WP_Error( "smpi_content_api_key_missing", "No SMP content generation API key is configured." );
        }
        $base = rtrim( (string) Settings::get( "content_generation_api_base", self::DEFAULT_API_BASE ), "/" );
        $response = wp_remote_post( $base . $path, [
            "timeout" => max( 5, $timeout ),
            "headers" => [
                "Accept" => "application/json",
                "Content-Type" => "application/json",
                "X-SMP-Content-Key" => $api_key,
                "X-SMP-TTS-Key" => $api_key,
            ],
            "body" => wp_json_encode( $payload ),
        ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( $code < 200 || $code >= 300 ) {
            return new \WP_Error( "smpi_content_api_http", "Content API returned HTTP " . $code . ".", [ "body" => $body ] );
        }
        return is_array( $body ) ? $body : [];
    }

    private function api_key(): string {
        $store = new CredentialStore();
        $key = $store->get( self::CREDENTIAL_SLUG, self::CREDENTIAL_KEY );
        if ( is_string( $key ) && "" !== trim( $key ) ) {
            return trim( $key );
        }
        return $this->tts_api_key();
    }

    private function tts_api_key(): string {
        $settings = get_option( "hexa_tts_settings", [] );
        if ( is_array( $settings ) && ! empty( $settings["api_key"] ) && is_scalar( $settings["api_key"] ) ) {
            return trim( (string) $settings["api_key"] );
        }
        return "";
    }

    private function payload_for_post( \WP_Post $post, string $target ): array {
        return [
            "target" => $target,
            "site_url" => home_url(),
            "post_id" => $post->ID,
            "post_type" => $post->post_type,
            "title" => get_the_title( $post ),
            "permalink" => get_permalink( $post ),
            "excerpt" => $post->post_excerpt,
            "content_html" => $post->post_content,
            "content_text" => $this->content_text( $post->post_content ),
            "post_summary" => (string) get_post_meta( $post->ID, "post_summary", true ),
            "faqs" => $this->current_faqs( $post->ID ),
            "rules" => [
                "excerpt" => "Use the existing Publish Scale custom excerpt rules.",
                "summary" => "Use the existing Publish Scale post summary rules.",
                "faqs" => "Use the existing Publish Scale FAQ generation rules and return structured question and answer rows.",
            ],
        ];
    }

    private function content_text( string $content ): string {
        $content = strip_shortcodes( $content );
        $content = preg_replace( "/<script\b[^>]*>.*?<\/script>/is", " ", $content );
        $content = preg_replace( "/<style\b[^>]*>.*?<\/style>/is", " ", $content );
        $content = preg_replace( "/<\/(p|h[1-6]|li|blockquote)>/i", "\n", $content );
        $content = preg_replace( "/<br\s*\/?>/i", "\n", $content );
        $content = wp_strip_all_tags( $content );
        return trim( preg_replace( "/\n{3,}/", "\n\n", html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, get_bloginfo( "charset" ) ) ) );
    }

    private function current_faqs( int $post_id ): array {
        if ( function_exists( "get_field" ) ) {
            $faqs = get_field( "post_faq_items", $post_id );
            if ( is_array( $faqs ) ) {
                return $faqs;
            }
        }
        $meta = get_post_meta( $post_id, "post_faq_items", true );
        return is_array( $meta ) ? $meta : [];
    }

    private function extract_generated_value( array $result, string $target ) {
        if ( isset( $result["data"] ) && is_array( $result["data"] ) && array_key_exists( $target, $result["data"] ) ) {
            return $result["data"][ $target ];
        }
        if ( array_key_exists( $target, $result ) ) {
            return $result[ $target ];
        }
        if ( isset( $result["result"] ) ) {
            return $result["result"];
        }
        return "";
    }

    private function save_generated_value( int $post_id, string $target, $value, array $result ) {
        if ( "excerpt" === $target ) {
            $updated = wp_update_post( [ "ID" => $post_id, "post_excerpt" => sanitize_textarea_field( (string) $value ) ], true );
            return is_wp_error( $updated ) ? $updated : true;
        }
        if ( "summary" === $target ) {
            $summary = wp_kses_post( (string) $value );
            if ( function_exists( "update_field" ) ) {
                update_field( "post_summary", $summary, $post_id );
            } else {
                update_post_meta( $post_id, "post_summary", $summary );
            }
            return true;
        }
        if ( "faqs" === $target ) {
            $rows = $this->normalize_faq_rows( is_array( $value ) ? $value : $result );
            if ( empty( $rows ) ) {
                return new \WP_Error( "smpi_content_faq_empty", "API response did not include FAQ question and answer rows." );
            }
            if ( function_exists( "update_field" ) ) {
                update_field( "post_faq_items", $rows, $post_id );
                update_field( "post_faq_schema_enabled", 1, $post_id );
            } else {
                update_post_meta( $post_id, "post_faq_items", $rows );
                update_post_meta( $post_id, "post_faq_schema_enabled", 1 );
            }
            return true;
        }
        return new \WP_Error( "smpi_content_target", "Unsupported generation target." );
    }

    private function normalize_faq_rows( $value ): array {
        if ( isset( $value["data"] ) && is_array( $value["data"] ) ) {
            $value = $value["data"];
        }
        if ( isset( $value["faqs"] ) && is_array( $value["faqs"] ) ) {
            $value = $value["faqs"];
        }
        if ( isset( $value["faq_items"] ) && is_array( $value["faq_items"] ) ) {
            $value = $value["faq_items"];
        }
        if ( ! is_array( $value ) ) {
            return [];
        }
        $rows = [];
        foreach ( $value as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $question = isset( $row["question"] ) ? sanitize_text_field( (string) $row["question"] ) : "";
            $answer = isset( $row["answer"] ) ? wp_kses_post( (string) $row["answer"] ) : "";
            if ( "" !== $question && "" !== $answer ) {
                $rows[] = [ "question" => $question, "answer" => $answer ];
            }
        }
        return $rows;
    }

    private function generation_log( int $post_id ): array {
        $log = get_post_meta( $post_id, self::LOG_META_KEY, true );
        return is_array( $log ) ? $log : [];
    }

    private function add_generation_log( int $post_id, string $status, string $message ): void {
        $log = $this->generation_log( $post_id );
        $log[] = [ "time" => current_time( "mysql" ), "status" => $status, "message" => $message ];
        update_post_meta( $post_id, self::LOG_META_KEY, array_slice( $log, -30 ) );
    }
}
