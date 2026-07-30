<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Authorship\AuthorAssignmentRepository;
use smp_publication_integration\Authorship\AuthorFieldResolver;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class AuthorListings {
    private AuthorFieldResolver $fields;
    private AuthorAssignmentRepository $assignments;
    private array $article_cache = [];

    public function __construct( ?AuthorFieldResolver $fields = null, ?AuthorAssignmentRepository $assignments = null ) {
        $this->fields = $fields ?? new AuthorFieldResolver();
        $this->assignments = $assignments ?? new AuthorAssignmentRepository();
    }

    public function register(): void {
        add_action( "init", [ $this, "register_shortcodes" ], PHP_INT_MAX );
        add_action( "wp_head", [ $this, "print_styles" ], 35 );
    }

    public function register_shortcodes(): void {
        foreach ( [ "staff_grid", "contributors_grid" ] as $tag ) {
            remove_shortcode( $tag );
        }
        add_shortcode( "staff_grid", [ $this, "render_staff_grid" ] );
        add_shortcode( "contributors_grid", [ $this, "render_contributors_grid" ] );
    }

    public function render_staff_grid(): string {
        $users = get_users(
            [
                "role__in" => [ "administrator", "editor", "author", "contributor", "subscriber", "verified_profile_manager", "customer" ],
                "orderby" => "display_name",
                "order" => "ASC",
                "number" => -1,
            ]
        );
        $users = array_values( array_filter( $users, fn( $user ): bool => $user instanceof \WP_User && $this->is_staff_writer( (int) $user->ID ) ) );
        return $this->render_grid( $users, "staff" );
    }

    public function render_contributors_grid(): string {
        $users = get_users(
            [
                "role__in" => [ "contributor" ],
                "orderby" => "display_name",
                "order" => "ASC",
                "number" => -1,
            ]
        );
        return $this->render_grid( $users, "contributor" );
    }

    public function print_styles(): void {
        echo '<style id="smpi-author-listing-styles">.smpi-author-listing-card.user-card{box-sizing:border-box;color:inherit;cursor:pointer;display:block;height:100%;text-decoration:none}.smpi-author-listing-card.user-card:link,.smpi-author-listing-card.user-card:visited,.smpi-author-listing-card.user-card:hover,.smpi-author-listing-card.user-card:active{color:inherit;text-decoration:none}.smpi-author-listing-card.user-card:focus-visible{outline:3px solid currentColor;outline-offset:3px}.smpi-author-listing-card .smpi-author-listing-image{display:block;object-fit:cover}</style>';
    }

    private function render_grid( array $users, string $listing ): string {
        $users = apply_filters( "smpi_author_listing_users", $users, $listing );
        $hide_without_image = Settings::bool( "author_listing_hide_without_featured_image" );
        $hide_without_articles = Settings::bool( "author_listing_hide_without_articles" );
        $cards = [];
        $seen = [];

        foreach ( is_array( $users ) ? $users : [] as $user ) {
            if ( ! $user instanceof \WP_User || isset( $seen[ $user->ID ] ) ) {
                continue;
            }
            $seen[ $user->ID ] = true;
            $explicit_image = $this->fields->explicit_image_url( (int) $user->ID, "medium" );
            if ( $hide_without_image && "" === $explicit_image ) {
                continue;
            }
            if ( $hide_without_articles && ! $this->has_published_article( (int) $user->ID ) ) {
                continue;
            }
            $cards[] = $this->render_card( $user, $listing, $explicit_image );
        }

        return '<div class="user-grid smpi-author-listing-grid" data-smpi-author-listing="' . esc_attr( $listing ) . '">' . implode( "", $cards ) . "</div>";
    }

    private function render_card( \WP_User $user, string $listing, string $explicit_image ): string {
        $user_id = (int) $user->ID;
        $name = trim( (string) $user->display_name );
        $profile_url = get_author_posts_url( $user_id );
        $image_url = "" !== $explicit_image ? $explicit_image : $this->fields->image_url( $user_id, "medium" );
        $site_name = trim( wp_strip_all_tags( (string) get_bloginfo( "name" ) ) );
        $role = "staff" === $listing ? "Staff Writer" : "Contributor";
        $role_text = "" !== $site_name ? $role . " at " . $site_name : $role;
        $role_text = (string) apply_filters( "smpi_author_listing_role_text", $role_text, $user, $listing );
        $image = "" !== $image_url
            ? '<img class="avatar avatar-250 photo smpi-author-listing-image" src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $name ) . '" width="250" height="250" loading="lazy" decoding="async">'
            : "";

        return '<a class="user-card smpi-author-listing-card" href="' . esc_url( $profile_url ) . '" data-smpi-author-listing-card="' . esc_attr( $listing ) . '" data-smpi-user-id="' . esc_attr( (string) $user_id ) . '">'
            . $image
            . "<h3>" . esc_html( $name ) . "</h3>"
            . "<p>" . esc_html( $role_text ) . "</p>"
            . "</a>";
    }

    private function is_staff_writer( int $user_id ): bool {
        if ( function_exists( "get_field" ) ) {
            $value = get_field( "staff_writer", "user_" . $user_id );
            if ( null !== $value && false !== $value && "" !== $value ) {
                return (bool) $value;
            }
        }
        return (bool) get_user_meta( $user_id, "staff_writer", true );
    }

    private function has_published_article( int $user_id ): bool {
        if ( isset( $this->article_cache[ $user_id ] ) ) {
            return $this->article_cache[ $user_id ];
        }
        $post_types = array_values( array_diff( $this->assignments->supported_post_types(), [ "press-release" ] ) );
        $this->article_cache[ $user_id ] = ! empty( $this->assignments->post_ids_for_user( $user_id, [ "publish" ], $post_types ) );
        return $this->article_cache[ $user_id ];
    }
}
