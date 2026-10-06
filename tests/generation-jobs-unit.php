<?php
/**
 * Generation job lifecycle against a simulated Publish API: start, de-duplicate,
 * throttle, settle (done / failed / missing / unreachable / overdue), FAQ rows,
 * start_missing and the notify ping.
 */
namespace Hexa\PluginCore\CredentialVault {
    final class CredentialStore {
        public function get( string $slug, string $key ) { return "site-key-123"; }
    }
}

namespace Hexa\PluginCore\Fields {
    final class Field {
        public static function available(): bool { return false; }
    }
}

namespace smp_publication_integration\Support {
    final class Settings {
        public static array $values = [ "content_generation_enabled" => true ];
        public static function bool( string $key ): bool { return ! empty( self::$values[ $key ] ); }
        public static function get( string $key, $default = null ) { return self::$values[ $key ] ?? $default; }
    }
}

namespace smp_publication_integration {
    final class Config { public const VERSION = "test"; }
}

namespace {
    define( "ABSPATH", __DIR__ );

    final class WP_Post {
        public int $ID; public string $post_type = "press-release"; public string $post_content; public string $post_excerpt = "";
        public function __construct( int $id, string $content ) { $this->ID = $id; $this->post_content = $content; }
    }
    final class WP_Error {
        private string $message;
        public function __construct( string $code = "", string $message = "" ) { $this->message = $message; }
        public function get_error_message(): string { return $this->message; }
    }
    final class WP_REST_Request {
        private array $params;
        public function __construct( array $params ) { $this->params = $params; }
        public function get_param( string $key ) { return $this->params[ $key ] ?? null; }
    }
    final class WP_REST_Response {
        public $data; public int $status;
        public function __construct( $data, int $status ) { $this->data = $data; $this->status = $status; }
    }

    $GLOBALS["posts"] = [];
    $GLOBALS["meta"] = [];
    $GLOBALS["scheduled"] = [];
    $GLOBALS["calls"] = [];
    $GLOBALS["publish"] = null; // callable( path, payload ) => [ code, body ]
    $GLOBALS["now_shift"] = 0;

    function get_post( $id ) { return $GLOBALS["posts"][ (int) $id ] ?? null; }
    function get_post_field( string $field, int $id ) { return $GLOBALS["posts"][ $id ]->$field ?? ""; }
    function get_post_meta( int $id, string $key, bool $single = false ) { return $GLOBALS["meta"][ $id ][ $key ] ?? ""; }
    function update_post_meta( int $id, string $key, $value ) { $GLOBALS["meta"][ $id ][ $key ] = $value; return true; }
    function wp_update_post( array $data, bool $wp_error = false ) { foreach ( $data as $k => $v ) { if ( "ID" !== $k ) { $GLOBALS["posts"][ $data["ID"] ]->$k = $v; } } return $data["ID"]; }
    function wp_next_scheduled( string $hook, array $args ) { return $GLOBALS["scheduled"][ $hook . json_encode( $args ) ] ?? false; }
    function wp_schedule_single_event( int $time, string $hook, array $args ) { $GLOBALS["scheduled"][ $hook . json_encode( $args ) ] = $time; return true; }
    function add_action( ...$args ) { return true; }
    function apply_filters( string $hook, $value ) { return $value; }
    function current_time( string $type ) { return gmdate( "Y-m-d H:i:s" ); }
    function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
    function sanitize_textarea_field( string $value ): string { return trim( $value ); }
    function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
    function wp_kses_post( string $value ): string { return $value; }
    function wp_strip_all_tags( string $value ): string { return trim( strip_tags( $value ) ); }
    function strip_shortcodes( string $value ): string { return $value; }
    function get_bloginfo( string $key ): string { return "UTF-8"; }
    function home_url(): string { return "https://site.test"; }
    function get_the_title( $post ): string { return "Title " . $post->ID; }
    function get_permalink( $post ): string { return "https://site.test/?p=" . $post->ID; }
    function wp_json_encode( $value ) { return json_encode( $value ); }
    function wp_remote_post( string $url, array $args ) {
        $path = substr( $url, strrpos( $url, "/" ) );
        $payload = json_decode( $args["body"], true );
        $GLOBALS["calls"][] = [ "path" => $path, "payload" => $payload, "key" => $args["headers"]["X-SMP-Content-Key"] ?? "" ];
        $answer = ( $GLOBALS["publish"] )( $path, $payload );
        return $answer instanceof WP_Error ? $answer : [ "code" => $answer[0], "body" => json_encode( $answer[1] ) ];
    }
    function wp_remote_retrieve_response_code( $response ) { return $response["code"]; }
    function wp_remote_retrieve_body( $response ) { return $response["body"]; }

    $root = dirname( __DIR__ );
    require $root . "/src/Content/Generation/PublishContentClient.php";
    require $root . "/src/Content/Generation/GeneratedValueStore.php";
    require $root . "/src/Content/Generation/GenerationJobs.php";

    use smp_publication_integration\Content\Generation\GenerationJobs;
    use smp_publication_integration\Support\Settings;

    $failures = 0;
    function check( string $label, bool $ok ): void {
        global $failures;
        echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
        if ( ! $ok ) { $failures++; }
    }
    function job( string $id, string $target, string $status, $data = null, ?string $error = null, int $attempts = 1 ): array {
        return [ "job_id" => $id, "target" => $target, "status" => $status, "mode" => "subscription", "attempts" => $attempts, "data" => $data, "error" => $error ];
    }
    function rewind_checks( int $post_id, int $seconds ): void {
        foreach ( $GLOBALS["meta"][ $post_id ] as $key => $record ) {
            if ( str_starts_with( $key, "_smpi_generation_job_" ) && is_array( $record ) ) {
                $record["checked_at"] -= $seconds;
                $record["started_at"] -= $seconds;
                $GLOBALS["meta"][ $post_id ][ $key ] = $record;
            }
        }
    }

    $article = str_repeat( "<p>Researchers reported new findings on GLP-1 treatment.</p>", 10 );
    $GLOBALS["posts"][10] = new WP_Post( 10, $article );
    $jobs = new GenerationJobs();
    $remote = [];
    $GLOBALS["publish"] = function ( string $path, array $payload ) use ( &$remote ) {
        if ( "/generate" === $path ) {
            $id = str_pad( $payload["target"], 26, "0" );
            $remote[ $id ] = job( $id, $payload["target"], "pending" );
            return [ 202, [ "success" => true ] + $remote[ $id ] ];
        }
        if ( "/jobs" === $path ) {
            return [ 200, [ "success" => true, "jobs" => array_values( array_intersect_key( $remote, array_flip( $payload["job_ids"] ) ) ) ] ];
        }
        return [ 404, [] ];
    };

    // 1. Start: one request with the site key and plain text, recorded as working, poll scheduled.
    $state = $jobs->start( 10, "summary" );
    $summary_id = str_pad( "summary", 26, "0" );
    check( "start records a working job", "working" === $state["status"] && $summary_id === $state["job_id"] );
    check( "start sends the site key and article text", 1 === count( $GLOBALS["calls"] ) && "site-key-123" === $GLOBALS["calls"][0]["key"] && ! str_contains( $GLOBALS["calls"][0]["payload"]["content_text"], "<p>" ) );
    check( "start schedules a background poll", (bool) wp_next_scheduled( GenerationJobs::POLL_EVENT, [ 10 ] ) );
    check( "the working state is stored on the post for a reload", "working" === $jobs->states( 10 )["summary"]["status"] );

    // 2. Repeated start while working does not start a second job.
    $jobs->start( 10, "summary" );
    check( "a second start while working makes no new request", 1 === count( $GLOBALS["calls"] ) );

    // 3. Throttle, then a still-running job.
    $jobs->refresh( 10 );
    check( "refresh right after a check does not ask Publish", 1 === count( $GLOBALS["calls"] ) );
    $remote[ $summary_id ]["status"] = "running";
    $remote[ $summary_id ]["attempts"] = 2;
    rewind_checks( 10, 10 );
    $states = $jobs->refresh( 10 );
    check( "a running job stays working and shows the retry", "working" === $states["summary"]["status"] && str_contains( $states["summary"]["message"], "try 2" ) );

    // 4. Completed: value saved, state done, editor gets the value.
    $html = "<ul><li>Point one.</li><li>Point two.</li></ul>";
    $remote[ $summary_id ] = job( $summary_id, "summary", "completed", [ "summary" => $html, "post_summary" => $html ] );
    $states = $jobs->refresh( 10, true );
    check( "a completed job is saved to post_summary", $html === get_post_meta( 10, "post_summary", true ) );
    check( "a completed job is done", "done" === $states["summary"]["status"] && $states["summary"]["finished_at"] > 0 );
    $present = $jobs->present( 10 );
    check( "present() hands the editor the saved value and activity", $html === $present["summary"]["value"] && count( $present["summary"]["log"] ) >= 2 );
    $before = count( $GLOBALS["calls"] );
    $jobs->refresh( 10, true );
    check( "nothing working means no further requests", $before === count( $GLOBALS["calls"] ) );

    // 5. FAQs: rows normalized and schema enabled; incomplete rows dropped.
    $jobs->start( 10, "faqs" );
    $faq_id = str_pad( "faqs", 26, "0" );
    $remote[ $faq_id ] = job( $faq_id, "faqs", "completed", [ "faqs" => [ [ "question" => "What did researchers find?", "answer" => "GLP-1 helped." ], [ "question" => "Empty?", "answer" => "" ] ] ] );
    $states = $jobs->refresh( 10, true );
    $rows = get_post_meta( 10, "post_faq_items", true );
    check( "FAQ rows saved and incomplete rows dropped", "done" === $states["faqs"]["status"] && 1 === count( $rows ) && "1" == get_post_meta( 10, "post_faq_schema_enabled", true ) );

    // 6. Failed job carries Publish's reason.
    $jobs->start( 10, "excerpt" );
    $excerpt_id = str_pad( "excerpt", 26, "0" );
    $remote[ $excerpt_id ] = job( $excerpt_id, "excerpt", "failed", null, "Quality review: excerpt repeats the headline." );
    $states = $jobs->refresh( 10, true );
    check( "a failed job shows Publish's reason", "failed" === $states["excerpt"]["status"] && str_contains( $states["excerpt"]["message"], "repeats the headline" ) );
    check( "a failed excerpt leaves the excerpt untouched", "" === $GLOBALS["posts"][10]->post_excerpt );

    // 7. Retry after failure starts a new job; Publish forgetting the job fails it.
    $state = $jobs->start( 10, "excerpt" );
    check( "a failed target can be started again", "working" === $state["status"] );
    unset( $remote[ $excerpt_id ] );
    $states = $jobs->refresh( 10, true );
    check( "a job Publish no longer has is failed", "failed" === $states["excerpt"]["status"] && str_contains( $states["excerpt"]["message"], "no longer has" ) );

    // 8. Publish unreachable: stays working; after 15 minutes it gives up.
    $jobs->start( 10, "excerpt" );
    $saved_publish = $GLOBALS["publish"];
    $GLOBALS["publish"] = fn () => new WP_Error( "http", "timeout" );
    $states = $jobs->refresh( 10, true );
    check( "an unreachable Publish keeps the job working", "working" === $states["excerpt"]["status"] );
    rewind_checks( 10, 1000 );
    $states = $jobs->refresh( 10, true );
    check( "a job working for over 15 minutes is given up", "failed" === $states["excerpt"]["status"] && str_contains( $states["excerpt"]["message"], "too long" ) );
    $GLOBALS["publish"] = $saved_publish;

    // 9. Start refused by Publish shows Publish's message.
    $GLOBALS["posts"][11] = new WP_Post( 11, "Short." );
    $GLOBALS["publish"] = fn () => [ 422, [ "success" => false, "message" => "The article needs at least 300 characters of text before metadata can be written." ] ];
    $state = $jobs->start( 11, "summary" );
    check( "a refused start fails with Publish's message", "failed" === $state["status"] && str_contains( $state["message"], "300 characters" ) );
    $GLOBALS["publish"] = $saved_publish;

    // 10. start_missing only starts empty targets.
    $GLOBALS["posts"][12] = new WP_Post( 12, $article );
    $GLOBALS["posts"][12]->post_excerpt = "Already written.";
    $remote = [];
    $started = $jobs->start_missing( 12, [ "excerpt", "summary", "faqs", "tts" ] );
    check( "start_missing skips the filled excerpt and unknown targets", [ "summary", "faqs" ] === array_keys( $started ) );

    // 11. Notify ping: a known job settles at once; an unknown one asks nothing.
    $remote[ $summary_id ] = job( $summary_id, "summary", "completed", [ "summary" => $html ] );
    $before = count( $GLOBALS["calls"] );
    $jobs->notified( new WP_REST_Request( [ "post_id" => 12, "job_id" => "zzzzzzzzzzzzzzzzzzzzzzzzzz" ] ) );
    check( "a ping for an unknown job makes no request", $before === count( $GLOBALS["calls"] ) );
    $jobs->notified( new WP_REST_Request( [ "post_id" => 12, "job_id" => strtoupper( $summary_id ) ] ) );
    check( "a ping for a working job settles it", "done" === $jobs->states( 12 )["summary"]["status"] );

    // 12. Disabled module refuses to start.
    Settings::$values["content_generation_enabled"] = false;
    check( "a disabled module does not start jobs", "failed" === $jobs->start( 12, "excerpt" )["status"] );

    if ( $failures ) {
        fwrite( STDERR, $failures . " generation job check(s) failed.\n" );
        exit( 1 );
    }
    echo "All generation job checks passed.\n";
}
