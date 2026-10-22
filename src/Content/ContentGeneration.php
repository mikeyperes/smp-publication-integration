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
    public const SCRIPT_HANDLE = "smpi-content-generation";
    private const POLL_MS = 4000;

    private GenerationJobs $jobs;

    public function __construct( ?GenerationJobs $jobs = null ) {
        $this->jobs = $jobs ?? new GenerationJobs();
    }

    public function register(): void {
        $this->jobs->register();
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


}
