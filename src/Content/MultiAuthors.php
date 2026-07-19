<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Authorship\AuthorAssignmentRepository;
use smp_publication_integration\Authorship\AuthorContext;
use smp_publication_integration\Authorship\AuthorFieldResolver;
use smp_publication_integration\Authorship\AuthorLifecycle;
use smp_publication_integration\Authorship\AuthorQueryIntegration;
use smp_publication_integration\Authorship\ElementorArchiveContext;
use smp_publication_integration\Authorship\ElementorAuthorRenderer;
use smp_publication_integration\Authorship\LoopBylineRenderer;
use smp_publication_integration\Authorship\SingleAuthorFallbackRenderer;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class MultiAuthors {
    public const FIELD_NAME = AuthorAssignmentRepository::LEGACY_META_KEY;
    public const FIELD_KEY = "field_smpi_post_authors";

    private static ?AuthorAssignmentRepository $repository = null;
    private static ?ElementorArchiveContext $archive_context = null;

    public function register(): void {
        $repository = self::repository();
        $loop_renderer = new LoopBylineRenderer( $repository );
        $elementor_renderer = new ElementorAuthorRenderer( $repository );
        $single_author_fallback = new SingleAuthorFallbackRenderer( $repository );
        self::$archive_context = new ElementorArchiveContext();

        ( new AuthorLifecycle( $repository ) )->register();
        ( new AuthorQueryIntegration( $repository ) )->register();
        $single_author_fallback->register();

        add_shortcode( "smp_post_author_ids", [ $this, "render_author_ids_shortcode" ] );
        add_shortcode( "smp_post_authors", [ $this, "render_authors_shortcode" ] );
        add_shortcode( "smp_post_author_names", [ $this, "render_authors_shortcode" ] );

        add_filter( "the_author_posts_link", [ $loop_renderer, "filter" ], 15 );
        add_filter( "elementor/widget/render_content", [ $loop_renderer, "filter_widget_content" ], 18, 2 );
        add_filter( "elementor/widget/render_content", [ $elementor_renderer, "filter_widget" ], 20, 2 );
        add_filter( "elementor/frontend/the_content", [ $elementor_renderer, "filter_content" ], 20 );
        add_action( "elementor/frontend/before_render", [ self::$archive_context, "before_render" ], 1 );
        add_action( "elementor/frontend/after_render", [ self::$archive_context, "after_render" ], 999 );
    }

    public static function enabled(): bool {
        return Settings::bool( "multi_authors_enabled" );
    }

    public static function loop_cards_disabled(): bool {
        return Settings::bool( "multi_authors_disable_loop_cards" );
    }

    public static function loop_card_output_format(): string {
        $format = sanitize_key( (string) Settings::get( "multi_authors_loop_output", "comma" ) );
        return in_array( $format, [ "primary", "comma", "lines" ], true ) ? $format : "comma";
    }

    public static function supported_post_types(): array {
        return self::repository()->supported_post_types();
    }

    public static function author_ids_for_post( int $post_id, bool $fallback = true ): array {
        return self::repository()->ids_for_post( $post_id, $fallback );
    }

    public static function primary_author_id_for_post( int $post_id ): int {
        return (int) ( self::author_ids_for_post( $post_id, true )[0] ?? 0 );
    }

    public static function resolve_author_id( int $explicit_user_id = 0, int $explicit_post_id = 0, int $author_index = 0 ): int {
        if ( self::$archive_context instanceof ElementorArchiveContext && self::$archive_context->active() && is_author() && $explicit_post_id <= 0 ) {
            $archive_author_id = AuthorQueryIntegration::current_archive_author_id();
            if ( $archive_author_id > 0 ) {
                return $archive_author_id;
            }
        }
        return AuthorContext::resolve( self::repository(), $explicit_user_id, $explicit_post_id, $author_index );
    }

    public static function current_author_id(): int {
        return AuthorContext::current_id();
    }

    public static function with_author_context( int $author_id, callable $callback ) {
        return AuthorContext::run( $author_id, $callback );
    }

    public static function author_view_models_for_post( int $post_id, bool $fallback = true ): array {
        return array_map(
            static fn( $record ): array => $record->to_array(),
            self::repository()->records_for_post( $post_id, $fallback )
        );
    }

    public static function selected_author_view_models_for_post( int $post_id ): array {
        return self::author_view_models_for_post( $post_id, false );
    }

    public static function has_multiple_authors( int $post_id, bool $fallback = true ): bool {
        return count( self::author_ids_for_post( $post_id, $fallback ) ) > 1;
    }

    public function render_author_ids_shortcode( array $atts = [] ): string {
        $atts = shortcode_atts( [ "post_id" => 0, "separator" => "," ], $atts, "smp_post_author_ids" );
        $post_id = $this->resolve_post_id( (int) $atts["post_id"] );
        return esc_html( implode( (string) $atts["separator"], self::author_ids_for_post( $post_id, true ) ) );
    }

    public function render_authors_shortcode( array $atts = [], ?string $content = null, string $tag = "smp_post_authors" ): string {
        $raw_atts = is_array( $atts ) ? $atts : [];
        $explicit_format = array_key_exists( "format", $raw_atts );
        $atts = shortcode_atts(
            [
                "post_id" => 0,
                "field" => "name",
                "format" => "plain",
                "separator" => ", ",
                "context" => "",
                "class" => "smpi-post-authors",
            ],
            $atts,
            $tag
        );

        $post_id = $this->resolve_post_id( (int) $atts["post_id"] );
        if ( $post_id <= 0 ) {
            return "";
        }
        $authors = self::author_view_models_for_post( $post_id );
        if ( empty( $authors ) ) {
            return "";
        }

        if ( "card" === sanitize_key( (string) $atts["context"] ) ) {
            if ( self::loop_cards_disabled() ) {
                $authors = [ $authors[0] ];
            } elseif ( ! $explicit_format ) {
                $card_format = self::loop_card_output_format();
                if ( "primary" === $card_format ) {
                    $authors = [ $authors[0] ];
                } else {
                    $atts["format"] = "lines" === $card_format ? "lines" : "plain";
                    $atts["separator"] = ", ";
                }
            }
        }

        $field = sanitize_key( (string) $atts["field"] );
        $format = sanitize_key( (string) $atts["format"] );
        $values = [];
        foreach ( $authors as $author ) {
            $value = $this->author_value( $author, $field );
            if ( "" !== $value ) {
                $values[] = $value;
            }
        }
        if ( empty( $values ) ) {
            return "";
        }

        if ( "links" === $format ) {
            $links = [];
            foreach ( $authors as $author ) {
                $label = $this->author_value( $author, $field );
                if ( "" !== $label ) {
                    $links[] = '<a href="' . esc_url( (string) $author["url"] ) . '">' . esc_html( $label ) . '</a>';
                }
            }
            return implode( esc_html( (string) $atts["separator"] ), $links );
        }
        if ( in_array( $format, [ "lines", "line" ], true ) ) {
            return implode( "<br>\n", array_map( "esc_html", $values ) );
        }
        if ( in_array( $format, [ "list", "ul" ], true ) ) {
            $items = array_map( static fn( string $value ): string => "<li>" . esc_html( $value ) . "</li>", $values );
            return '<ul class="' . esc_attr( sanitize_html_class( (string) $atts["class"] ) ) . '">' . implode( "", $items ) . "</ul>";
        }
        return esc_html( implode( (string) $atts["separator"], $values ) );
    }

    public static function field_report( int $limit = 10 ): array {
        $query = new \WP_Query(
            [
                "post_type" => self::supported_post_types(),
                "post_status" => [ "publish", "draft", "pending", "future" ],
                "posts_per_page" => $limit,
                "orderby" => "modified",
                "order" => "DESC",
                "no_found_rows" => true,
            ]
        );
        $rows = [];
        foreach ( $query->posts as $post ) {
            $ids = self::author_ids_for_post( (int) $post->ID, true );
            $rows[] = [
                "post_id" => (int) $post->ID,
                "title" => get_the_title( $post ),
                "type" => (string) $post->post_type,
                "status" => (string) $post->post_status,
                "native_author" => (int) $post->post_author,
                "authors" => $ids,
                "count" => count( $ids ),
                "canonical" => self::repository()->selected_ids_for_post( (int) $post->ID ),
            ];
        }
        return [
            "enabled" => self::enabled(),
            "field" => self::FIELD_NAME,
            "field_key" => self::FIELD_KEY,
            "taxonomy" => AuthorAssignmentRepository::TAXONOMY,
            "supported_post_types" => self::supported_post_types(),
            "rows" => $rows,
        ];
    }

    public static function repository(): AuthorAssignmentRepository {
        if ( ! self::$repository instanceof AuthorAssignmentRepository ) {
            self::$repository = new AuthorAssignmentRepository();
        }
        return self::$repository;
    }

    private function resolve_post_id( int $post_id ): int {
        if ( $post_id > 0 ) {
            return $post_id;
        }
        $post = get_post();
        return $post instanceof \WP_Post ? (int) $post->ID : 0;
    }

    private function author_value( array $author, string $field ): string {
        $fields = isset( $author["fields"] ) && is_array( $author["fields"] ) ? $author["fields"] : [];
        if ( in_array( $field, [ "", "name", "display_name" ], true ) ) {
            return (string) $author["name"];
        }
        if ( in_array( $field, [ "id", "ids", "user_id" ], true ) ) {
            return (string) $author["id"];
        }
        if ( in_array( $field, [ "url", "author_url" ], true ) ) {
            return (string) $author["url"];
        }
        if ( "email" === $field ) {
            return (string) $author["email"];
        }
        return wp_strip_all_tags( (string) ( $fields[ $field ] ?? "" ) );
    }
}
