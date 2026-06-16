<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Fields;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class Shortcodes {
    public function register(): void {
        add_action( "init", [ $this, "register_shortcodes" ] );
    }

    public function register_shortcodes(): void {
        foreach ( self::shortcodes() as $tag => $callback ) {
            add_shortcode( $tag, [ $this, $callback ] );
        }
    }

    public static function shortcodes(): array {
        return [
            "smp_publication_field" => "render_field",
            "smp_publication_mission_statement" => "render_mission_statement",
            "smp_publication_founders" => "render_founders",
            "smp_publication_user" => "render_publication_user",
            "smp_publication_profile" => "render_profile",
            "smp_publication_validate_schema" => "render_validate_schema",
            "smp_publication_page" => "render_page_assignment",
            "smp_publication_debug_url" => "render_debug_url",
        ];
    }

    public function render_field( array $atts = [] ): string {
        $atts = shortcode_atts( [ "field" => "" ], $atts, "smp_publication_field" );
        $field = sanitize_key( (string) $atts["field"] );
        if ( "" === $field ) {
            return "";
        }

        $value = Fields::option( $field );
        return is_array( $value ) || is_object( $value ) ? esc_html( wp_json_encode( $value ) ) : wp_kses_post( (string) $value );
    }

    public function render_mission_statement( array $atts = [] ): string {
        $mission = Fields::option( "mission_statement" );
        return $mission ? "<div class=\"smpi-publication-mission\">" . wp_kses_post( wpautop( (string) $mission ) ) . "</div>" : "";
    }

    public function render_founders( array $atts = [] ): string {
        if ( ! Settings::bool( "founders_enabled" ) ) {
            return "";
        }

        $items = array_merge(
            $this->founder_profile_items( Fields::option( "founders", [] ) ),
            $this->founder_user_items( Fields::option( "founder_users", [] ) )
        );

        return $items ? "<ul class=\"smpi-publication-founders\">" . implode( "", $items ) . "</ul>" : "";
    }

    public function render_publication_user( array $atts = [] ): string {
        $user_id = (int) Fields::option( "publication_user", Settings::get( "system_publication_user_id", 0 ) );
        $user = $user_id ? get_userdata( $user_id ) : false;
        return $user ? "<span class=\"smpi-publication-user\">" . esc_html( $user->display_name ) . "</span>" : "";
    }

    public function render_profile( array $atts = [] ): string {
        $website = Fields::option( "website", home_url( "/" ) );
        $summary = Fields::option( "summary" );
        $mission = Fields::option( "mission_statement" );
        $founders = $this->render_founders();

        ob_start();
        ?>
        <article class="smpi-publication-profile">
            <h2 class="smpi-publication-profile__title"><?php echo esc_html( get_bloginfo( "name" ) ); ?></h2>
            <?php if ( $website ) : ?><p><a href="<?php echo esc_url( (string) $website ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $website ); ?></a></p><?php endif; ?>
            <?php if ( $summary ) : ?><div><?php echo wp_kses_post( wpautop( (string) $summary ) ); ?></div><?php endif; ?>
            <?php if ( $mission ) : ?><h3>Mission Statement</h3><div><?php echo wp_kses_post( wpautop( (string) $mission ) ); ?></div><?php endif; ?>
            <?php if ( $founders ) : ?><h3>Founders</h3><?php echo $founders; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
        </article>
        <?php
        return trim( (string) ob_get_clean() );
    }

    public function render_validate_schema( array $atts = [] ): string {
        return sprintf( "<a class=\"smpi-publication-schema-validator\" href=\"%s\" target=\"_blank\" rel=\"noopener noreferrer\">%s</a>", esc_url( "https://validator.schema.org/#url=" . rawurlencode( home_url( "/" ) ) ), esc_html( "Validate homepage publication schema" ) );
    }

    public function render_page_assignment( array $atts = [] ): string {
        $atts = shortcode_atts( [ "type" => "", "mode" => "link" ], $atts, "smp_publication_page" );
        $settings = Settings::all();
        $type = sanitize_key( (string) $atts["type"] );
        $page_id = isset( $settings["page_assignments"][ $type ] ) ? (int) $settings["page_assignments"][ $type ] : 0;
        if ( ! $page_id ) {
            return "";
        }
        if ( "content" === $atts["mode"] ) {
            $post = get_post( $page_id );
            return $post ? apply_filters( "the_content", $post->post_content ) : "";
        }
        return sprintf( "<a href=\"%s\">%s</a>", esc_url( get_permalink( $page_id ) ), esc_html( get_the_title( $page_id ) ) );
    }

    public function render_debug_url(): string {
        return esc_url( rest_url( "smpi/v1/debug" ) );
    }

    private function founder_profile_items( $founders ): array {
        $founders = is_array( $founders ) ? $founders : [ $founders ];
        $items = [];
        foreach ( $founders as $founder ) {
            $founder_id = $this->founder_profile_id( $founder );
            if ( $founder_id && get_the_title( $founder_id ) ) {
                $items[] = sprintf( "<li><a href=\"%s\">%s</a></li>", esc_url( get_permalink( $founder_id ) ), esc_html( get_the_title( $founder_id ) ) );
            }
        }
        return $items;
    }

    private function founder_profile_id( $founder ): int {
        if ( is_array( $founder ) && isset( $founder["profile"] ) ) {
            $founder = $founder["profile"];
        }
        if ( is_object( $founder ) && isset( $founder->ID ) ) {
            return (int) $founder->ID;
        }
        return is_numeric( $founder ) ? (int) $founder : 0;
    }

    private function founder_user_items( $users ): array {
        $users = is_array( $users ) ? $users : [ $users ];
        $items = [];
        foreach ( $users as $user_id ) {
            $user_id = is_object( $user_id ) && isset( $user_id->ID ) ? (int) $user_id->ID : (int) $user_id;
            $user = $user_id ? get_user_by( "id", $user_id ) : false;
            if ( $user ) {
                $items[] = sprintf( "<li><a href=\"%s\">%s</a></li>", esc_url( get_author_posts_url( $user_id ) ), esc_html( $user->display_name ) );
            }
        }
        return $items;
    }
}
