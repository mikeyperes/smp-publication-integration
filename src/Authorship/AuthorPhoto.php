<?php
namespace smp_publication_integration\Authorship;

/** Personal-photo detection shared by all rendering contexts. */
final class AuthorPhoto {
    private static array $known = [];

    public static function has_photo( int $user_id ): bool {
        if ( $user_id <= 0 || ! get_user_by( 'id', $user_id ) ) {
            return false;
        }
        if ( array_key_exists( $user_id, self::$known ) ) {
            return self::$known[$user_id];
        }
        $resolver = new AuthorFieldResolver();
        $explicit = $resolver->explicit_image_url( $user_id );
        $data = get_avatar_data( $user_id, [ 'force_default' => false ] );
        $url = $explicit ?: (string) ( $data['url'] ?? '' );
        $found = $explicit !== '' || ! empty( $data['found_avatar'] );
        // WordPress's found_avatar means a hash exists, not that Gravatar has a photo.
        $host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
        if ( $found && ( $host === 'gravatar.com' || substr( $host, -13 ) === '.gravatar.com' ) ) {
            if ( preg_match( '~/avatar/([a-f0-9]{32,64})(?:[/?]|$)~i', $url, $match ) ) {
                $key = 'smpi_author_photo_' . $match[1];
                $cached = get_transient( $key );
                if ( false === $cached ) {
                    $response = wp_remote_head( 'https://secure.gravatar.com/avatar/' . $match[1] . '?d=404&s=96', [ 'timeout' => 3, 'redirection' => 0 ] );
                    $status = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
                    // A temporary network failure must not hide a potentially real photo.
                    $found = 404 !== $status;
                    if ( 200 === $status || 404 === $status ) {
                        set_transient( $key, $found ? 'yes' : 'no', DAY_IN_SECONDS );
                    }
                } else {
                    $found = 'yes' === $cached;
                }
            } else {
                $found = false;
            }
        }
        if ( preg_match( '~(?:/default-avatar[./-]|/mystery[-_]man[./-]|/avatar/(?:default|blank)(?:[./?]|$))~i', $url ) ) {
            $found = false;
        }
        return self::$known[$user_id] = (bool) apply_filters( 'smpi_author_has_photo', $found, $user_id );
    }
}
