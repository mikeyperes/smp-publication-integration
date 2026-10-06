<?php
namespace smp_publication_integration\Content;

use Hexa\PluginCore\CredentialVault\CredentialStore;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Config;
use smp_publication_integration\Content\Generation\GeneratedValueStore;
use smp_publication_integration\Content\Generation\GenerationJobs;
use smp_publication_integration\Content\Generation\PublishContentClient;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class ContentGeneration {
    private const TAB = "content_generation";
    public const SCRIPT_HANDLE = "smpi-content-generation";
    private const POLL_MS = 4000;

    private GenerationJobs $jobs;

    public function __construct( ?GenerationJobs $jobs = null ) {
        $this->jobs = $jobs ?? new GenerationJobs();
    }

    public function register(): void {
        $this->jobs->register();
        add_filter( "smpi_dashboard_tabs", [ $this, "tabs" ] );
        add_filter( "smpi_render_dashboard_tab", [ $this, "render_tab" ], 10, 2 );
        add_action( "admin_footer", [ $this, "admin_footer_script" ] );
        add_action( "admin_enqueue_scripts", [ $this, "enqueue_editor" ] );
        ( new AjaxActionRegistry(
            [
                'capability'   => 'manage_options',
                'nonce_action' => Ajax::NONCE,
                'nonce_field'  => 'nonce',
            ]
        ) )->register(
            [
                'smpi_content_generation_save_key' => [ 'callback' => [ $this, 'save_key' ] ],
                'smpi_content_generation_test'     => [ 'callback' => [ $this, 'test_connection' ] ],
                'smpi_generate_content'            => [
                    'capability' => '',
                    'callback'   => [ $this, 'start_generation' ],
                ],
                'smpi_generation_state'            => [
                    'capability' => '',
                    'callback'   => [ $this, 'generation_state' ],
                ],
            ]
        );
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
        $masked = ( new CredentialStore() )->get_masked( PublishContentClient::CREDENTIAL_SLUG, PublishContentClient::CREDENTIAL_KEY );
        $fallback = ( new PublishContentClient() )->tts_api_key() ? "TTS key fallback detected" : "No TTS fallback key detected";
        ?>
        <section class="smpi-section smpi-content-generation-tab">
            <div class="smpi-feature-card">
                <div class="smpi-feature-head">
                    <div><span class="smpi-kicker">Publish Scale API</span><h2>Content Generation</h2></div>
                    <?php echo $this->switch_control( "content_generation_enabled", ! empty( $settings["content_generation_enabled"] ) ); ?>
                </div>
                <p>Adds one-click generators to post edit screens for excerpts, post summaries, and structured FAQs. The writing rules live on publish.scalemypublication.com. Each one runs as a background job on Publish: the editor shows it working, even after a reload, and the field fills in when Publish finishes.</p>
            </div>

            <div class="smpi-card-grid smpi-card-grid--three">
                <div class="smpi-feature-card"><h3>API base</h3><input class="regular-text smpi-setting" data-key="content_generation_api_base" value="<?php echo esc_attr( (string) $settings["content_generation_api_base"] ); ?>"><span class="spinner"></span><span class="smpi-save-state"></span></div>
                                <div class="smpi-feature-card"><h3>API key</h3><p>Stored in Hexa Credential Vault. <?php echo esc_html( $fallback ); ?>.</p><p><code data-smpi-content-key-mask><?php echo esc_html( $masked ?: "No SMP key saved" ); ?></code></p><input class="regular-text" type="password" autocomplete="new-password" data-smpi-content-api-key placeholder="Paste SMP content API key"><p><?php echo $this->dynamic_button( [ "label" => "Save key", "working_label" => "Saving key...", "success_label" => "Key saved", "error_label" => "Save failed", "class" => "button button-primary", "attrs" => [ "data-smpi-save-content-key" => "1" ] ] ); ?> <?php echo $this->dynamic_button( [ "label" => "Test connection", "working_label" => "Testing...", "success_label" => "Connected", "error_label" => "Failed", "class" => "button", "attrs" => [ "data-smpi-test-content-api" => "1" ] ] ); ?> <span class="spinner"></span> <span data-smpi-content-key-state></span></p></div>
            </div>

            <div class="smpi-feature-card">
                <h3>Post editor buttons</h3>
                <p>On supported post edit screens the module adds buttons near the existing excerpt, post summary, and FAQ fields. Each one shows a working state while Publish writes, an activity log, and a success or error result. The Going Live checklist uses the same jobs.</p>
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

    /**
     * Loads the shared generation client on post edit screens. The inline
     * field buttons and the Going Live checklist both drive jobs through it,
     * and it starts with each target's saved state so a screen opened while
     * Publish is still writing shows the work in progress.
     */
    public function enqueue_editor( string $hook ): void {
        if ( ! in_array( $hook, [ "post.php", "post-new.php" ], true ) || ! $this->jobs->enabled() ) {
            return;
        }
        $post = get_post();
        if ( ! $post instanceof \WP_Post || ! current_user_can( "edit_post", $post->ID ) ) {
            return;
        }
        $plugin_file = dirname( __DIR__, 2 ) . "/smp-publication-integration.php";
        $base = plugin_dir_url( $plugin_file ) . "assets/admin/";
        wp_enqueue_style( self::SCRIPT_HANDLE, $base . "content-generation.css", [], Config::VERSION );
        wp_enqueue_script( self::SCRIPT_HANDLE, $base . "content-generation.js", [ "jquery" ], Config::VERSION, true );
        wp_add_inline_script(
            self::SCRIPT_HANDLE,
            "window.smpiGenerationConfig = " . wp_json_encode( [
                "ajaxUrl" => admin_url( "admin-ajax.php" ),
                "nonce" => Ajax::nonce(),
                "postId" => (int) $post->ID,
                "pollMs" => self::POLL_MS,
                "now" => time(),
                "states" => $this->jobs->present( (int) $post->ID ),
            ] ) . ";",
            "before"
        );
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
                button.prop("disabled", true); if(window.HexaWpCoreDynamicButton){ window.HexaWpCoreDynamicButton.start(button, "Saving key..."); } state(card, "working", "Saving API key...");
                $.post(window.ajaxurl, {action:"smpi_content_generation_save_key", nonce:nonce, api_key:card.find("[data-smpi-content-api-key]").val() || ""})
                    .done(function(response){ const data = (response && response.data) || {}; if (response && response.success) { card.find("[data-smpi-content-key-mask]").text(data.masked || "No SMP key saved"); card.find("[data-smpi-content-api-key]").val(""); state(card, "ok", "Saved: " + (data.message || "API key saved.")); if(window.HexaWpCoreDynamicButton){ window.HexaWpCoreDynamicButton.success(button, "Key saved"); } } else { state(card, "error", "Failed: " + (data.message || "Save failed.")); if(window.HexaWpCoreDynamicButton){ window.HexaWpCoreDynamicButton.error(button, "Save failed", false); } } })
                    .fail(function(xhr){ state(card, "error", "Failed: HTTP " + (xhr.status || 0) + " save failed."); if(window.HexaWpCoreDynamicButton){ window.HexaWpCoreDynamicButton.error(button, "Save failed", false); } })
                    .always(function(){ button.prop("disabled", false); });
            });
            $(document).on("click", "[data-smpi-test-content-api]", function(){
                const button = $(this); const card = button.closest(".smpi-feature-card");
                button.prop("disabled", true); if(window.HexaWpCoreDynamicButton){ window.HexaWpCoreDynamicButton.start(button, "Testing..."); } state(card, "working", "Testing API connection...");
                $.post(window.ajaxurl, {action:"smpi_content_generation_test", nonce:nonce})
                    .done(function(response){ const data = (response && response.data) || {}; state(card, response && response.success ? "ok" : "error", (response && response.success ? "Connected: " : "Failed: " ) + (data.message || "Connection test failed.")); if(window.HexaWpCoreDynamicButton){ response && response.success ? window.HexaWpCoreDynamicButton.success(button, "Connected") : window.HexaWpCoreDynamicButton.error(button, "Failed", false); } })
                    .fail(function(xhr){ state(card, "error", "Failed: HTTP " + (xhr.status || 0) + " test failed."); if(window.HexaWpCoreDynamicButton){ window.HexaWpCoreDynamicButton.error(button, "Failed", false); } })
                    .always(function(){ button.prop("disabled", false); });
            });
        });
        </script>
        <?php
    }

    public function save_key( AjaxRequest $request ): array {
        $raw_key = $request->raw( 'api_key', '', 'post' );
        $key     = is_scalar( $raw_key ) ? trim( (string) $raw_key ) : '';
        $store = new CredentialStore();
        if ( "" === $key ) {
            $store->delete( PublishContentClient::CREDENTIAL_SLUG, PublishContentClient::CREDENTIAL_KEY );
            return [ 'message' => 'Content API key removed.', 'masked' => '' ];
        }
        $store->store( PublishContentClient::CREDENTIAL_SLUG, PublishContentClient::CREDENTIAL_KEY, $key );
        return [ 'message' => 'Content API key saved.', 'masked' => $store->mask( $key ) ];
    }

    public function test_connection( AjaxRequest $request ): array {
        unset( $request );
        $result = ( new PublishContentClient() )->status();
        if ( is_wp_error( $result ) ) {
            throw AjaxFailure::bad_request( $result->get_error_message(), 'content_api_error' );
        }
        return [ 'message' => 'API responded.', 'response' => $result ];
    }

    /** Starts one target's job and answers at once with every target's state. */
    public function start_generation( AjaxRequest $request ): array {
        $post_id = $this->editable_post_id( $request );
        $target  = $request->key( 'target', '', 'post' );
        if ( ! GeneratedValueStore::is_target( $target ) ) {
            throw AjaxFailure::bad_request( 'Unknown generation target.', 'invalid_target' );
        }
        $state  = $this->jobs->start( $post_id, $target );
        $states = $this->jobs->present( $post_id );
        if ( GenerationJobs::FAILED === $state['status'] ) {
            throw AjaxFailure::bad_request( (string) $state['message'], 'content_generation_failed', [ 'states' => $states ] );
        }
        return [ 'message' => (string) $state['message'], 'states' => $states, 'now' => time() ];
    }

    /** Every target's state, after settling anything Publish has finished. */
    public function generation_state( AjaxRequest $request ): array {
        $post_id = $this->editable_post_id( $request );
        return [ 'states' => $this->jobs->present( $post_id, $this->jobs->refresh( $post_id ) ), 'now' => time() ];
    }

    private function editable_post_id( AjaxRequest $request ): int {
        $post_id = $request->int( 'post_id', 0, 'post' );
        if ( ! $post_id || ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            throw new AjaxFailure( 'Not allowed or invalid request.', 403, 'forbidden' );
        }
        return $post_id;
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

    private function switch_control( string $key, bool $enabled ): string {
        return "<label class=\"smpi-switch\"><input class=\"smpi-setting\" type=\"checkbox\" data-key=\"" . esc_attr( $key ) . "\" value=\"1\" " . checked( $enabled, true, false ) . "><span></span><strong>" . ( $enabled ? "Enabled" : "Disabled" ) . "</strong></label><span class=\"spinner\"></span><span class=\"smpi-save-state\"></span>";
    }
}
