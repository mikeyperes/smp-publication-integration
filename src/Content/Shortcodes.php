<?php
namespace smp_publication_integration\Content;

use Hexa\PluginCore\FaqSets\FaqSetManager;
use smp_publication_integration\Support\Fields;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class Shortcodes {
    private const POST_ACF_FIELDS = [ "post_summary", "post_faq_items" ];

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
            "smp_post_acf" => "render_post_acf",
            "smp_post_summary" => "render_post_summary",
            "smp_post_faqs" => "render_post_faqs",
        ];
    }

    public function render_field( array $atts = [] ): string {
        $atts = shortcode_atts(
            [
                "field" => "",
                "format" => "html",
                "row" => "",
                "index" => "",
                "sub_field" => "",
                "separator" => ", ",
            ],
            $atts,
            "smp_publication_field"
        );
        $field = sanitize_key( (string) $atts["field"] );
        if ( "" === $field ) {
            return "";
        }

        $value = Fields::option( $field );
        $value = $this->extract_indexed_value( $value, (string) $atts["row"], (string) $atts["index"] );
        $value = $this->extract_sub_field( $value, sanitize_key( (string) $atts["sub_field"] ) );
        return $this->format_value( $value, sanitize_key( (string) $atts["format"] ), (string) $atts["separator"] );
    }

    public function render_post_acf( array $atts = [] ): string {
        $atts = shortcode_atts(
            [
                "field" => "",
                "post_id" => 0,
                "format" => "html",
                "separator" => ", ",
            ],
            $atts,
            "smp_post_acf"
        );
        $field = sanitize_key( (string) $atts["field"] );
        if ( ! in_array( $field, self::POST_ACF_FIELDS, true ) ) {
            return "";
        }
        $post_id = $this->resolve_post_id( (int) $atts["post_id"] );
        if ( ! $post_id ) {
            return "";
        }
        return $this->format_value( Fields::get( $post_id, $field ), sanitize_key( (string) $atts["format"] ), (string) $atts["separator"] );
    }

    public function render_post_summary( array $atts = [] ): string {
        $atts = shortcode_atts( [ "post_id" => 0, "format" => "html", "style" => "" ], $atts, "smp_post_summary" );
        $atts["field"] = "post_summary";
        $html = $this->render_post_acf( $atts );
        return "html" === sanitize_key( (string) $atts["format"] ) ? ArticleStyles::wrap_post_summary( $html, sanitize_key( (string) $atts["style"] ) ) : $html;
    }

    public function render_post_faqs( array $atts = [] ): string {
        $atts = shortcode_atts( [ "post_id" => 0, "format" => "html", "style" => "" ], $atts, "smp_post_faqs" );
        $post_id = $this->resolve_post_id( (int) $atts["post_id"] );
        if ( ! $post_id ) {
            return "";
        }

        $rows = Schema::faq_rows_for_post( $post_id, false );
        if ( ! $rows ) {
            return "";
        }

        $manager = new FaqSetManager();
        $items = $manager->normalizeItems( $rows );
        if ( empty( $items ) ) {
            return "";
        }

        if ( "text" === sanitize_key( (string) $atts["format"] ) ) {
            $text = [];
            foreach ( $items as $item ) {
                $text[] = "Q: " . $item["question"] . " A: " . wp_strip_all_tags( (string) $item["answer"] );
            }
            return esc_html( implode( " ", $text ) );
        }

        $set = [
            "name"  => "Post FAQs",
            "slug"  => "post-" . $post_id . "-faqs",
            "items" => $items,
        ];
        $html = $manager->renderFaqs( $set, [
            "style"         => "list",
            "inject_schema" => false,
        ] );

        return ArticleStyles::wrap_post_faqs( $html, sanitize_key( (string) $atts["style"] ) );
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
        $mode = sanitize_key( (string) $atts["mode"] );
        if ( "content" === $mode ) {
            $post = get_post( $page_id );
            return $post ? apply_filters( "the_content", $post->post_content ) : "";
        }
        if ( "id" === $mode ) {
            return esc_html( (string) $page_id );
        }
        if ( "title" === $mode ) {
            return esc_html( get_the_title( $page_id ) );
        }
        $url = Settings::page_slug_url( $page_id );
        if ( "url" === $mode ) {
            return esc_url( $url );
        }
        return sprintf( "<a href=\"%s\">%s</a>", esc_url( $url ), esc_html( get_the_title( $page_id ) ) );
    }


    public function render_debug_url(): string {
        return esc_url( rest_url( "smpi/v1/debug" ) );
    }


    private function resolve_post_id( int $explicit_post_id = 0 ): int {
        if ( $explicit_post_id > 0 && get_post( $explicit_post_id ) ) {
            return $explicit_post_id;
        }
        $post = get_post();
        return $post ? (int) $post->ID : 0;
    }

    private function extract_indexed_value( $value, string $row, string $index ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }
        $offset = null;
        if ( "" !== $row && is_numeric( $row ) ) {
            $offset = max( 0, (int) $row - 1 );
        } elseif ( "" !== $index && is_numeric( $index ) ) {
            $offset = max( 0, (int) $index );
        }
        if ( null === $offset ) {
            return $value;
        }
        $values = array_values( $value );
        return $values[ $offset ] ?? "";
    }

    private function extract_sub_field( $value, string $sub_field ) {
        if ( "" === $sub_field || ! is_array( $value ) ) {
            return $value;
        }
        return array_key_exists( $sub_field, $value ) ? $value[ $sub_field ] : "";
    }

    private function format_value( $value, string $format = "html", string $separator = ", " ): string {
        if ( null === $value || false === $value || "" === $value || ( is_array( $value ) && empty( $value ) ) ) {
            return "";
        }
        if ( "json" === $format ) {
            return esc_html( wp_json_encode( $value ) );
        }
        if ( is_array( $value ) || is_object( $value ) ) {
            return esc_html( $this->flatten_value( $value, $separator ) );
        }
        if ( is_bool( $value ) ) {
            return $value ? "1" : "";
        }
        $value = (string) $value;
        if ( "text" === $format ) {
            return esc_html( wp_strip_all_tags( $value ) );
        }
        return wp_kses_post( $value );
    }

    private function flatten_value( $value, string $separator ): string {
        if ( is_object( $value ) ) {
            $value = get_object_vars( $value );
        }
        if ( ! is_array( $value ) ) {
            return is_scalar( $value ) ? trim( (string) $value ) : "";
        }
        foreach ( [ "url", "value", "label", "title", "name", "ID" ] as $key ) {
            if ( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ) {
                return trim( (string) $value[ $key ] );
            }
        }
        $flat = [];
        array_walk_recursive(
            $value,
            static function ( $item ) use ( &$flat ): void {
                if ( is_scalar( $item ) && "" !== trim( (string) $item ) ) {
                    $flat[] = trim( (string) $item );
                }
            }
        );
        return implode( $separator, array_slice( array_unique( $flat ), 0, 20 ) );
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
