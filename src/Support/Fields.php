<?php
namespace smp_publication_integration\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Fields {
    public static function get( int $post_id, string $field, $default = '' ) {
        $aliases = self::aliases()[ $field ] ?? [ $field ];

        foreach ( $aliases as $alias ) {
            $value = self::raw( $post_id, $alias );
            if ( self::has_value( $value ) ) {
                return $value;
            }
        }

        return $default;
    }

    public static function aliases(): array {
        return [
            'mission_statement' => [
                'mission_statement',
                'publication_mission_statement',
                'smpi_mission_statement_override',
            ],
            'summary' => [
                'publication_summary',
                'summary',
                'description',
                'smpi_publication_summary',
            ],
            'website' => [
                'url',
                'website',
                'publication_website',
                'smpi_publication_website',
            ],
            'logo' => [
                'logo',
                'publication_logo',
                'smpi_publication_logo',
            ],
            'publication_user' => [
                'publication_user',
                'smpi_publication_user',
            ],
            'founders' => [
                'founders',
                'smpi_founders',
            ],
        ];
    }

    public static function raw( int $post_id, string $field ) {
        if ( function_exists( 'get_field' ) ) {
            $value = get_field( $field, $post_id );
            if ( self::has_value( $value ) ) {
                return $value;
            }
        }

        return get_post_meta( $post_id, $field, true );
    }

    public static function has_value( $value ): bool {
        if ( null === $value || false === $value || '' === $value ) {
            return false;
        }

        return ! ( is_array( $value ) && empty( $value ) );
    }
}

