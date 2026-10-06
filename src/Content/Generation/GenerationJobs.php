<?php
namespace smp_publication_integration\Content\Generation;

use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * The life of every excerpt, summary and FAQ generation on a post.
 *
 * Starting a target asks Publish for a job and records it on the post, so any
 * screen that loads later (a reload after Publish, another editor, the
 * Going Live checklist) knows it is still being written. A working job is
 * settled by whichever comes first: Publish's ping when it finishes, the
 * editor's poll, or the background poll event. Settling saves the value and
 * records the outcome; it is safe to run more than once.
 *
 * Each target is stored in its own post meta row
 * (_smpi_generation_job_<target>) so two targets never overwrite each other.
 */
final class GenerationJobs {
    public const WORKING = "working";
    public const DONE = "done";
    public const FAILED = "failed";
    public const IDLE = "idle";
    public const POLL_EVENT = "smpi_generation_jobs_poll";
    public const LOG_META = "_smpi_content_generation_log";
    private const META_PREFIX = "_smpi_generation_job_";
    private const REST_NAMESPACE = "smpi/v1";
    /** Publish is asked again at most this often for the same post (seconds). */
    private const CHECK_INTERVAL = 3;
    /** The background poll runs this long after a start, then repeats while working. */
    private const POLL_DELAY = 30;
    /** A job still working after this long is given up (seconds). */
    private const GIVE_UP_AFTER = 900;

    private PublishContentClient $client;
    private GeneratedValueStore $store;

    public function __construct( ?PublishContentClient $client = null, ?GeneratedValueStore $store = null ) {
        $this->client = $client ?? new PublishContentClient();
        $this->store = $store ?? new GeneratedValueStore();
    }

    public function register(): void {
        add_action( self::POLL_EVENT, [ $this, "poll" ] );
        add_action( "rest_api_init", [ $this, "register_routes" ] );
    }

    public function enabled(): bool {
        return Settings::bool( "content_generation_enabled" );
    }

    public function store(): GeneratedValueStore {
        return $this->store;
    }

    /**
     * Starts generating one target. A target that is already working is left
     * alone and its current state is returned, so repeated clicks and a
     * publish during generation never start duplicate jobs.
     */
    public function start( int $post_id, string $target ): array {
        if ( ! GeneratedValueStore::is_target( $target ) ) {
            return $this->state_only( self::FAILED, "Unknown target " . $target . "." );
        }
        if ( ! $this->enabled() ) {
            return $this->state_only( self::FAILED, "Content generation is turned off in SMP settings." );
        }
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post ) {
            return $this->state_only( self::FAILED, "Post not found." );
        }
        $current = $this->record( $post_id, $target );
        if ( self::WORKING === $current["status"] && ! $this->overdue( $current ) ) {
            return $current;
        }

        $this->log( $post_id, $target, self::WORKING, "Asked Publish to write the " . GeneratedValueStore::noun( $target ) . "." );
        $job = $this->client->start( $post, $target );
        if ( is_wp_error( $job ) ) {
            return $this->finish( $post_id, $target, $current, self::FAILED, $job->get_error_message() );
        }

        $now = time();
        $record = [
            "status" => self::WORKING,
            "job_id" => strtolower( (string) $job["job_id"] ),
            "mode" => (string) ( $job["mode"] ?? "" ),
            "message" => "Writing the " . GeneratedValueStore::noun( $target ) . "…",
            "started_at" => $now,
            "checked_at" => $now,
            "finished_at" => 0,
        ];
        $this->save_record( $post_id, $target, $record );
        $this->schedule_poll( $post_id );
        if ( in_array( (string) ( $job["status"] ?? "" ), [ "completed", "failed" ], true ) ) {
            return $this->settle( $post_id, $target, $record, $job );
        }
        return $record;
    }

    /**
     * Starts every listed target that is still empty and not already working.
     *
     * @param string[] $targets
     * @return array<string,array> States of the targets that were started.
     */
    public function start_missing( int $post_id, array $targets ): array {
        $started = [];
        foreach ( array_filter( $targets, [ GeneratedValueStore::class, "is_target" ] ) as $target ) {
            if ( $this->store->is_filled( $post_id, $target ) ) {
                continue;
            }
            $started[ $target ] = $this->start( $post_id, $target );
        }
        return $started;
    }

    /**
     * Brings working targets up to date with Publish and returns every
     * target's state. Without $force, Publish is asked at most once every
     * few seconds per post however many screens are polling.
     *
     * @return array<string,array>
     */
    public function refresh( int $post_id, bool $force = false ): array {
        $states = $this->states( $post_id );
        $working = array_filter( $states, static fn ( array $state ): bool => self::WORKING === $state["status"] );
        if ( empty( $working ) ) {
            return $states;
        }
        $now = time();
        if ( ! $force && max( array_column( $working, "checked_at" ) ) > $now - self::CHECK_INTERVAL ) {
            return $states;
        }

        $jobs = $this->client->jobs( array_column( $working, "job_id" ) );
        foreach ( $working as $target => $record ) {
            if ( is_wp_error( $jobs ) ) {
                $record["checked_at"] = $now;
                $record["message"] = "Still writing. Publish could not be checked just now.";
                $states[ $target ] = $this->overdue( $record ) ? $this->give_up( $post_id, $target, $record ) : $this->save_record( $post_id, $target, $record );
                continue;
            }
            $job = $jobs[ $record["job_id"] ] ?? null;
            if ( null === $job ) {
                $states[ $target ] = $this->finish( $post_id, $target, $record, self::FAILED, "Publish no longer has this job. Generate it again." );
                continue;
            }
            $states[ $target ] = $this->settle( $post_id, $target, array_merge( $record, [ "checked_at" => $now ] ), $job );
        }
        return $states;
    }

    /** @return array<string,array> Every target's stored state, without asking Publish. */
    public function states( int $post_id ): array {
        $states = [];
        foreach ( GeneratedValueStore::TARGETS as $target ) {
            $states[ $target ] = $this->record( $post_id, $target );
        }
        return $states;
    }

    /**
     * States for the editor: each with its recent activity, and the saved
     * value once done so an open screen can fill the field in.
     *
     * @return array<string,array>
     */
    public function present( int $post_id, ?array $states = null ): array {
        $states = $states ?? $this->states( $post_id );
        $log = $this->activity( $post_id );
        $out = [];
        foreach ( $states as $target => $state ) {
            $state["label"] = GeneratedValueStore::label( $target );
            $state["log"] = array_values( array_slice( array_reverse( array_filter( $log, static fn ( array $entry ): bool => ( $entry["target"] ?? "" ) === $target ) ), 0, 5 ) );
            if ( self::DONE === $state["status"] ) {
                $state["value"] = $this->store->value( $post_id, $target );
            }
            unset( $state["checked_at"] );
            $out[ $target ] = $state;
        }
        return $out;
    }

    public function has_working( int $post_id ): bool {
        foreach ( $this->states( $post_id ) as $state ) {
            if ( self::WORKING === $state["status"] ) {
                return true;
            }
        }
        return false;
    }

    /** Background poll: settle what Publish has finished, then come back while anything is still working. */
    public function poll( int $post_id ): void {
        $this->refresh( $post_id, true );
        if ( $this->has_working( $post_id ) ) {
            $this->schedule_poll( $post_id );
        }
    }

    public function register_routes(): void {
        register_rest_route( self::REST_NAMESPACE, "/generation-jobs/notify", [
            "methods" => "POST",
            "callback" => [ $this, "notified" ],
            // Publish's ping only names a post and job; the result is then read
            // from Publish with this site's key, so the ping itself grants nothing.
            "permission_callback" => "__return_true",
            "args" => [
                "post_id" => [ "required" => true, "type" => "integer", "minimum" => 1 ],
                "job_id" => [ "required" => true, "type" => "string" ],
            ],
        ] );
    }

    public function notified( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id = (int) $request->get_param( "post_id" );
        $job_id = strtolower( (string) $request->get_param( "job_id" ) );
        foreach ( $this->states( $post_id ) as $state ) {
            if ( self::WORKING === $state["status"] && "" !== $job_id && $state["job_id"] === $job_id ) {
                // The ping names a job only this site and Publish know, so it may skip the throttle.
                $this->refresh( $post_id, true );
                break;
            }
        }
        return new \WP_REST_Response( [ "success" => true ], 200 );
    }

    /** @return array<int,array{time:string,status:string,target:string,message:string}> */
    public function activity( int $post_id ): array {
        $log = get_post_meta( $post_id, self::LOG_META, true );
        return is_array( $log ) ? array_values( array_filter( $log, "is_array" ) ) : [];
    }

    private function settle( int $post_id, string $target, array $record, array $job ): array {
        $status = (string) ( $job["status"] ?? "" );
        if ( "completed" === $status ) {
            $value = $this->store->extract( $job["data"] ?? null, $target );
            $saved = null === $value ? new \WP_Error( "smpi_content_empty", "Publish finished without the " . GeneratedValueStore::noun( $target ) . "." ) : $this->store->save( $post_id, $target, $value );
            if ( is_wp_error( $saved ) ) {
                return $this->finish( $post_id, $target, $record, self::FAILED, $saved->get_error_message() );
            }
            return $this->finish( $post_id, $target, $record, self::DONE, GeneratedValueStore::label( $target ) . " written and saved." );
        }
        if ( "failed" === $status ) {
            $error = trim( (string) ( $job["error"] ?? "" ) );
            return $this->finish( $post_id, $target, $record, self::FAILED, "" !== $error ? $error : "Publish could not write it." );
        }
        if ( $this->overdue( $record ) ) {
            return $this->give_up( $post_id, $target, $record );
        }
        $attempts = (int) ( $job["attempts"] ?? 0 );
        $record["message"] = $attempts > 1 ? "Writing the " . GeneratedValueStore::noun( $target ) . "… (try " . $attempts . ")" : $record["message"];
        return $this->save_record( $post_id, $target, $record );
    }

    private function give_up( int $post_id, string $target, array $record ): array {
        return $this->finish( $post_id, $target, $record, self::FAILED, "Publish took too long. Generate it again." );
    }

    private function finish( int $post_id, string $target, array $record, string $status, string $message ): array {
        $record = array_merge( $this->blank(), $record, [ "status" => $status, "message" => $message, "finished_at" => time() ] );
        $this->log( $post_id, $target, self::DONE === $status ? "ok" : "error", $message );
        return $this->save_record( $post_id, $target, $record );
    }

    private function overdue( array $record ): bool {
        return (int) $record["started_at"] > 0 && (int) $record["started_at"] < time() - self::GIVE_UP_AFTER;
    }

    private function record( int $post_id, string $target ): array {
        $stored = get_post_meta( $post_id, self::META_PREFIX . $target, true );
        return array_merge( $this->blank(), is_array( $stored ) ? $stored : [] );
    }

    private function save_record( int $post_id, string $target, array $record ): array {
        update_post_meta( $post_id, self::META_PREFIX . $target, $record );
        return $record;
    }

    private function blank(): array {
        return [ "status" => self::IDLE, "job_id" => "", "mode" => "", "message" => "", "started_at" => 0, "checked_at" => 0, "finished_at" => 0 ];
    }

    private function state_only( string $status, string $message ): array {
        return array_merge( $this->blank(), [ "status" => $status, "message" => $message ] );
    }

    private function schedule_poll( int $post_id ): void {
        if ( ! wp_next_scheduled( self::POLL_EVENT, [ $post_id ] ) ) {
            wp_schedule_single_event( time() + self::POLL_DELAY, self::POLL_EVENT, [ $post_id ] );
        }
    }

    private function log( int $post_id, string $target, string $status, string $message ): void {
        $log = $this->activity( $post_id );
        $log[] = [ "time" => current_time( "mysql" ), "status" => $status, "target" => $target, "message" => $message ];
        update_post_meta( $post_id, self::LOG_META, array_slice( $log, -30 ) );
    }
}
