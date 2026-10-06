<?php
namespace smp_publication_integration\Content;

use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Config;
use smp_publication_integration\Content\Generation\GeneratedValueStore;
use smp_publication_integration\Content\Generation\GenerationJobs;
use smp_publication_integration\Support\Settings;
use smp_publication_integration\Support\Dependencies;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class GoingLiveChecklist {
    private const ACTION_STATUS = "smpi_going_live_checklist_status";
    /** Post meta: "1" means this post is never processed automatically. */
    public const EXEMPT_META = "_smpi_go_live_exempt";
    private const EXEMPT_NONCE = "smpi_go_live_exempt_nonce";
    private const ASSET_HANDLE = "smpi-going-live-checklist";

    private GenerationJobs $jobs;

    public function __construct( ?GenerationJobs $jobs = null ) {
        $this->jobs = $jobs ?? new GenerationJobs();
    }

    public function register(): void {
        add_action( "edit_form_after_editor", [ $this, "render" ] );
        add_action( "admin_enqueue_scripts", [ $this, "enqueue_assets" ] );
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

    /** Loads the checklist's styles and script; content items run through the shared generation client. */
    public function enqueue_assets( string $hook ): void {
        $post = get_post();
        if ( ! in_array( $hook, [ "post.php", "post-new.php" ], true ) || ! $post instanceof \WP_Post || ! $this->supports_post( $post ) ) {
            return;
        }
        $base = plugin_dir_url( dirname( __DIR__, 2 ) . "/smp-publication-integration.php" ) . "assets/admin/";
        $deps = [ "jquery" ];
        if ( $this->jobs->enabled() ) {
            $deps[] = ContentGeneration::SCRIPT_HANDLE;
        }
        wp_enqueue_style( self::ASSET_HANDLE, $base . "going-live-checklist.css", [], Config::VERSION );
        wp_enqueue_script( self::ASSET_HANDLE, $base . "going-live-checklist.js", $deps, Config::VERSION, true );
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
     * Server-side "Complete all": starts generation of every empty excerpt,
     * summary and FAQ set, unless the post is marked "Do not process this
     * page". It returns at once; Publish writes in the background and any
     * editor opened meanwhile shows the items working.
     * Other plugins call do_action( "smpi_go_live_process", $post_id ).
     *
     * @return array<string,array> States of the targets that were started.
     */
    public function process_missing( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post || ! $this->supports_post( $post ) || self::is_exempt( $post_id ) ) return [];
        $targets = array_values( array_intersect( GeneratedValueStore::TARGETS, array_column( $this->items_for_post( $post ), "key" ) ) );
        return $this->jobs->start_missing( $post_id, $targets );
    }

    private function items_for_post( \WP_Post $post ): array {
        $items = [];
        if ( $this->uses_verified_profiles( $post ) ) {
            $items[] = [ "key" => "verified_profiles_created", "label" => "Verified Profiles Created", "description" => "Checks the verified profiles shortcode/output for this post.", "selector" => ".elementor-shortcode, [data-widget_type='shortcode.default'], [class*='verified-profile'], [id*='verified-profile']" ];
        }
        $items[] = [ "key" => "excerpt", "label" => "Excerpt customized", "description" => "Checks the native WordPress excerpt.", "selector" => "#postexcerpt, #excerpt" ];
        $items[] = [ "key" => "summary", "label" => "Article Summary Created", "description" => "Checks the post_summary ACF field.", "selector" => "[data-name='post_summary'], .acf-field[data-name='post_summary']" ];
        if ( Settings::bool( "post_faqs_acf_enabled" ) ) $items[] = [ "key" => "faqs", "label" => "FAQs created", "description" => "Checks structured FAQ rows.", "selector" => "[data-key='field_smpi_post_faq_accordion'], .acf-field-smpi-post-faq-accordion, .acf-field[data-name='post_faq_items']" ];
        if ( $this->uses_verified_profiles( $post ) ) {
            $items[] = [ "key" => "verified_profiles_internally_linked", "label" => "Verified profiles internally linked", "description" => "Checks that verified profile output contains links.", "selector" => ".elementor-shortcode, [data-widget_type='shortcode.default'], [class*='verified-profile'], [id*='verified-profile']" ];
        }
        $items[] = [ "key" => "tts", "label" => "Text to speech", "description" => "Checks the article_audio ACF field.", "selector" => ".hexa-tts-postbox, [data-acf-field='article_audio'], .acf-field[data-name='article_audio']" ];
        return $items;
    }

    private function status_for_item( string $key, \WP_Post $post ): array {
        if ( GeneratedValueStore::is_target( $key ) ) {
            $messages = [
                "excerpt" => [ "Excerpt is customized.", "No excerpt is saved." ],
                "summary" => [ "Article summary is saved.", "Article summary is empty." ],
                "faqs" => [ "Structured FAQs are saved.", "No structured FAQ rows found." ],
            ];
            return $this->status_from_bool( $this->jobs->store()->is_filled( $post->ID, $key ), $messages[ $key ][0], $messages[ $key ][1] );
        }
        if ( "tts" === $key ) {
            return $this->status_from_bool( $this->has_value( GeneratedValueStore::field_value( "article_audio", $post->ID ) ), "Text-to-speech audio is saved.", "No article_audio value found." );
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

    private function has_value( $value ): bool {
        if ( is_array( $value ) ) {
            return ! empty( array_filter( $value ) );
        }
        return "" !== trim( (string) $value );
    }
}
