<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\PublicationFounders;
use Hexa\PluginCore\FaqSets\FaqSetManager;
use smp_publication_integration\Pages\PageLinker;
use smp_publication_integration\Pages\PageTemplates;
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
            "smp_publication_page_template" => "render_page_template",
            "smp_page_template" => "render_page_template",
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
                "fallback" => "",
            ],
            $atts,
            "smp_publication_field"
        );
        $field = sanitize_key( (string) $atts["field"] );
        if ( "" === $field ) {
            return "";
        }

        $value = "website" === $field ? Fields::option_url( $field ) : Fields::option( $field );
        $value = $this->extract_indexed_value( $value, (string) $atts["row"], (string) $atts["index"] );
        $value = $this->extract_sub_field( $value, sanitize_key( (string) $atts["sub_field"] ) );
        if ( $this->is_empty_value( $value ) && "" !== (string) $atts["fallback"] ) {
            $value = $this->fallback_value( sanitize_key( (string) $atts["fallback"] ) );
        }
        return $this->format_value( $value, sanitize_key( (string) $atts["format"] ), (string) $atts["separator"] );
    }

    public function render_post_acf( array $atts = [] ): string {
        $atts = shortcode_atts(
            [
                "field" => "",
                "post_id" => 0,
                "format" => "html",
                "style" => "",
                "separator" => ", ",
            ],
            $atts,
            "smp_post_acf"
        );
        $field = sanitize_key( (string) $atts["field"] );
        if ( ! in_array( $field, self::POST_ACF_FIELDS, true ) ) {
            return "";
        }
        if ( ! Settings::bool( "post_summary" === $field ? "post_summary_acf_enabled" : "post_faqs_acf_enabled" ) ) {
            return "";
        }
        $post_id = $this->resolve_post_id( (int) $atts["post_id"] );
        if ( ! $post_id ) {
            return "";
        }

        $format = sanitize_key( (string) $atts["format"] );
        if ( "html" === $format ) {
            if ( "post_summary" === $field ) {
                return $this->render_post_summary( [ "post_id" => $post_id, "style" => (string) $atts["style"] ] );
            }
            if ( "post_faq_items" === $field ) {
                return $this->render_post_faqs( [ "post_id" => $post_id, "style" => (string) $atts["style"] ] );
            }
        }

        return $this->render_post_field_value( $field, $post_id, $format, (string) $atts["separator"] );
    }

    public function render_post_summary( array $atts = [] ): string {
        $atts = shortcode_atts( [ "post_id" => 0, "format" => "html", "style" => "" ], $atts, "smp_post_summary" );
        if ( ! Settings::bool( "post_summary_acf_enabled" ) ) {
            return "";
        }
        $post_id = $this->resolve_post_id( (int) $atts["post_id"] );
        if ( ! $post_id ) {
            return "";
        }
        $format = sanitize_key( (string) $atts["format"] );
        $html = $this->render_post_field_value( "post_summary", $post_id, $format, ", " );
        return "html" === $format ? ArticleStyles::wrap_post_summary( $html, sanitize_key( (string) $atts["style"] ) ) : $html;
    }

    public function render_post_faqs( array $atts = [] ): string {
        $atts = shortcode_atts( [ "post_id" => 0, "format" => "html", "style" => "" ], $atts, "smp_post_faqs" );
        if ( ! Settings::bool( "post_faqs_acf_enabled" ) ) {
            return "";
        }
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

        $format = sanitize_key( (string) $atts["format"] );
        if ( "text" === $format ) {
            $text = [];
            foreach ( $items as $item ) {
                $text[] = "Q: " . $item["question"] . " A: " . wp_strip_all_tags( (string) $item["answer"] );
            }
            return esc_html( implode( " ", $text ) );
        }

        if ( "accordion" === $format ) {
            $html = '<div class="smpi-template-list smpi-post-faq-list smpi-post-faq-accordion">';
            foreach ( $items as $item ) {
                $answer = TemplateMarkup::decorate_rich_text( wpautop( (string) $item["answer"] ), "post-faq" );
                $html .= '<details class="smpi-template-item smpi-post-faq-item smpi-post-faq-accordion-item">';
                $html .= '<summary class="smpi-template-title smpi-post-faq-question smpi-post-faq-accordion-question"><span class="smpi-template-text smpi-post-faq-question-text">' . esc_html( (string) $item["question"] ) . '</span></summary>';
                $html .= '<div class="smpi-template-content smpi-post-faq-answer smpi-post-faq-accordion-answer">' . wp_kses_post( $answer ) . '</div>';
                $html .= '</details>';
            }
            return ArticleStyles::wrap_post_faqs( $html . '</div>', sanitize_key( (string) $atts["style"] ) );
        }

        $html = $manager->renderItems(
            $items,
            [
                'wrapper_tag' => 'ul', 'wrapper_class' => 'smpi-template-list smpi-post-faq-list',
                'item_tag' => 'li', 'item_class' => 'smpi-template-item smpi-post-faq-item',
                'question_tag' => 'h3', 'question_class' => 'smpi-template-title smpi-post-faq-question',
                'answer_tag' => 'div', 'answer_class' => 'smpi-template-content smpi-post-faq-answer',
                'answer_renderer' => static fn( string $answer ): string => TemplateMarkup::decorate_rich_text( wpautop( $answer ), 'post-faq' ),
            ]
        );
        return ArticleStyles::wrap_post_faqs( $html, sanitize_key( (string) $atts["style"] ) );
    }

    private function render_post_field_value( string $field, int $post_id, string $format, string $separator ): string {
        return $this->format_value( Fields::get( $post_id, $field ), $format, $separator );
    }

    public function render_mission_statement( array $atts = [] ): string {
        $mission = Fields::option( "mission_statement" );
        return $mission ? "<div class=\"smpi-publication-mission\">" . wp_kses_post( wpautop( (string) $mission ) ) . "</div>" : "";
    }

    /** The founders' names, linked to their author pages; the list comes only from HWS Base Tools. */
    public function render_founders( array $atts = [] ): string {
        $items = [];
        foreach ( PublicationFounders::all() as $person ) {
            $items[] = sprintf( "<li><a href=\"%s\">%s</a></li>", esc_url( $person["url"] ), esc_html( $person["name"] ) );
        }
        return $items ? "<ul class=\"smpi-publication-founders\">" . implode( "", $items ) . "</ul>" : "";
    }

    public function render_publication_user( array $atts = [] ): string {
        $entity = Fields::canonical_entity();
        $user_id = $entity ? (int) ( $entity['attached_user_id'] ?? 0 ) : 0;
        if ( ! $user_id ) {
            $user_id = (int) Fields::option( "publication_user", Settings::get( "system_publication_user_id", 0 ) );
        }
        $user = $user_id ? get_userdata( $user_id ) : false;
        return $user ? "<span class=\"smpi-publication-user\">" . esc_html( $user->display_name ) . "</span>" : "";
    }

    public function render_profile( array $atts = [] ): string {
        $website = Fields::option_url( "website", home_url( "/" ) );
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
        $type = sanitize_key( (string) $atts["type"] );
        $page_id = PageLinker::linked_id( $type );
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


    /** Prints a page type's template (default file or the site's edited copy); see src/Pages/. */
    public function render_page_template( array $atts = [] ): string {
        $atts = shortcode_atts( [ "type" => "" ], $atts, "smp_publication_page_template" );
        return PageTemplates::render( sanitize_key( (string) $atts["type"] ) );
    }

    private function is_empty_value( $value ): bool {
        return null === $value || false === $value || "" === $value || ( is_array( $value ) && empty( $value ) );
    }

    private function fallback_value( string $fallback ) {
        if ( "site_name" === $fallback || "publication_name" === $fallback ) {
            return get_bloginfo( "name" );
        }
        if ( "home_url" === $fallback ) {
            return home_url( "/" );
        }
        if ( "site_url" === $fallback ) {
            return site_url( "/" );
        }
        if ( "admin_email" === $fallback || "contact_email" === $fallback ) {
            return get_option( "admin_email" );
        }
        return "";
    }

    private function format_gallery( $value ): string {
        $items = is_array( $value ) ? $value : [ $value ];
        $html = "";
        $quote = chr( 34 );
        foreach ( $items as $item ) {
            $id = 0;
            $url = "";
            $alt = "";
            if ( is_array( $item ) ) {
                $id = absint( $item["ID"] ?? $item["id"] ?? 0 );
                $url = isset( $item["url"] ) ? (string) $item["url"] : "";
                $alt = isset( $item["alt"] ) ? (string) $item["alt"] : (string) ( $item["title"] ?? "" );
            } elseif ( is_object( $item ) ) {
                $id = absint( $item->ID ?? $item->id ?? 0 );
                $url = isset( $item->url ) ? (string) $item->url : "";
                $alt = isset( $item->alt ) ? (string) $item->alt : (string) ( $item->title ?? "" );
            } elseif ( is_numeric( $item ) ) {
                $id = absint( $item );
            } elseif ( is_string( $item ) ) {
                $url = $item;
            }
            if ( $id ) {
                $image = wp_get_attachment_image( $id, "medium", false, [ "loading" => "lazy" ] );
                if ( $image ) {
                    $html .= "<figure>" . $image . "</figure>";
                    continue;
                }
            }
            if ( "" !== $url ) {
                $html .= "<figure><img src=" . $quote . esc_url( $url ) . $quote . " alt=" . $quote . esc_attr( $alt ) . $quote . " loading=" . $quote . "lazy" . $quote . "></figure>";
            }
        }
        return "" !== $html ? "<div>" . $html . "</div>" : "";
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
        if ( $this->is_empty_value( $value ) ) {
            return "";
        }
        if ( "json" === $format ) {
            return esc_html( wp_json_encode( $value ) );
        }
        if ( "gallery" === $format ) {
            return $this->format_gallery( $value );
        }
        if ( is_array( $value ) || is_object( $value ) ) {
            $value = $this->flatten_value( $value, $separator );
        }
        if ( is_bool( $value ) ) {
            return $value ? "1" : "";
        }
        $value = (string) $value;
        if ( "url" === $format ) {
            return esc_url( $value );
        }
        if ( "email" === $format ) {
            return esc_html( sanitize_email( $value ) );
        }
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
}
