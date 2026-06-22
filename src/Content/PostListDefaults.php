<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class PostListDefaults {
    private const PER_PAGE = 20;
    private const MODE     = "list";

    public function register(): void {
        add_filter( "default_hidden_columns", [ $this, "default_hidden_columns" ], 10, 2 );
        add_filter( "hidden_columns", [ $this, "filter_hidden_columns" ], 10, 3 );
        add_filter( "edit_post_per_page", [ $this, "default_per_page" ], 10, 2 );
        add_action( "current_screen", [ $this, "apply_user_defaults" ] );
    }

    public function default_hidden_columns( array $hidden, \WP_Screen $screen ): array {
        if ( ! $this->enabled() || ! $this->is_post_list_screen( $screen ) ) {
            return $hidden;
        }

        return self::hidden_columns();
    }

    public function filter_hidden_columns( array $hidden, \WP_Screen $screen, bool $use_defaults ): array {
        if ( ! $this->enabled() || ! $this->is_post_list_screen( $screen ) ) {
            return $hidden;
        }

        return ( $use_defaults || self::is_defaultish_hidden_columns( $hidden ) ) ? self::hidden_columns() : $hidden;
    }

    public function default_per_page( int $per_page, string $post_type = "post" ): int {
        if ( ! $this->enabled() || "post" !== $post_type ) {
            return $per_page;
        }

        return self::PER_PAGE;
    }

    public function apply_user_defaults( \WP_Screen $screen ): void {
        if ( ! $this->enabled() || ! $this->is_post_list_screen( $screen ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return;
        }

        $hidden = get_user_option( "manageedit-postcolumnshidden", $user_id );
        if ( ! is_array( $hidden ) || self::is_defaultish_hidden_columns( $hidden ) ) {
            update_user_option( $user_id, "manageedit-postcolumnshidden", self::hidden_columns(), true );
        }

        if ( false === get_user_option( "edit_post_per_page", $user_id ) ) {
            update_user_option( $user_id, "edit_post_per_page", self::PER_PAGE, true );
        }

        if ( "" === get_user_setting( "posts_list_mode", "" ) ) {
            set_user_setting( "posts_list_mode", self::MODE );
        }
    }

    public static function hidden_columns(): array {
        return [
            "categories",
            "taxonomy-coderevolution_post_source",
            "comments",
            "featured_image",
            "rank_math_title",
            "rank_math_description",
        ];
    }

    private static function is_defaultish_hidden_columns( array $hidden ): bool {
        $normalized = array_values( array_unique( array_filter( array_map( "strval", $hidden ), static function( string $value ): bool { return "" !== trim( $value ); } ) ) );
        sort( $normalized );
        $rank_math_only = [ "rank_math_description", "rank_math_title" ];
        sort( $rank_math_only );
        return [] === $normalized || $rank_math_only === $normalized;
    }

    private function enabled(): bool {
        return Settings::bool( "post_list_defaults_enabled" );
    }

    private function is_post_list_screen( \WP_Screen $screen ): bool {
        return "edit-post" === (string) $screen->id;
    }
}
