<?php

namespace smp_publication_integration\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The publication's founders, read from HWS Base Tools, the one place they are defined
 * (Settings, HWS Base Tools, Founders). SMP never stores founders itself: its structured
 * data, [smp_company field="founders"], [smp_publication_founders] and the Founders tab
 * all read this list. Empty while Base Tools is missing or older than its founders API.
 */
final class PublicationFounders {
    public static function available(): bool {
        return function_exists( 'hws_publication_founders' );
    }

    /** @return array<int,array{user_id:int,name:string,title:string,url:string}> in Base Tools' order. */
    public static function all(): array {
        if ( ! self::available() ) {
            return [];
        }
        $people = [];
        foreach ( (array) hws_publication_founders() as $row ) {
            $row     = (array) $row;
            $user_id = absint( $row['user_id'] ?? $row['id'] ?? 0 );
            $user    = $user_id ? get_userdata( $user_id ) : false;
            if ( ! $user ) {
                continue;
            }
            $people[] = [
                'user_id' => $user_id,
                'name'    => (string) ( $row['name'] ?? $user->display_name ),
                'title'   => (string) ( $row['title'] ?? '' ),
                'url'     => (string) ( $row['url'] ?? get_author_posts_url( $user_id ) ),
            ];
        }
        return $people;
    }

    /** @return int[] */
    public static function user_ids(): array {
        return array_column( self::all(), 'user_id' );
    }

    public static function edit_url(): string {
        return function_exists( 'hws_publication_founders_edit_url' ) ? (string) hws_publication_founders_edit_url() : admin_url( 'options-general.php?page=hws-core-tools' );
    }
}
