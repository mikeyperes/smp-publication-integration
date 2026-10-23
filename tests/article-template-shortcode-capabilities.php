<?php

declare(strict_types=1);

namespace {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );

    final class WP_Post {
        public int $ID = 44;
        public string $post_content = '<h2>First section</h2><h3>Nested section</h3><h2 id="last-section">Last section</h2>';
    }

    $GLOBALS['smpi_fixture_post'] = new WP_Post();

    function add_action( ...$args ): void {}
    function add_filter( ...$args ): void {}
    function add_shortcode( ...$args ): void {}
    function shortcode_atts( array $defaults, array $atts, string $tag = '' ): array { return array_merge( $defaults, array_intersect_key( $atts, $defaults ) ); }
    function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: ''; }
    function sanitize_title( string $value ): string { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $value ) ) ?: '', '-' ); }
    function esc_attr( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
    function esc_html( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
    function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); }
    function wp_json_encode( $value ): string { return (string) json_encode( $value ); }
    function wp_kses_post( string $value ): string { return $value; }
    function wpautop( string $value ): string { return '<p>' . $value . '</p>'; }
    function get_post( $post = null ): ?WP_Post { return $GLOBALS['smpi_fixture_post']; }
    function get_option( string $key, $default = false ) { return $default; }
    function get_post_timestamp( WP_Post $post, string $field = 'date' ): int {
        return ( new DateTimeImmutable( '2025-10-17 12:30:00', new DateTimeZone( 'America/New_York' ) ) )->getTimestamp();
    }
    function wp_timezone(): DateTimeZone { return new DateTimeZone( 'America/New_York' ); }
    function wp_date( string $format, int $timestamp, ?DateTimeZone $timezone = null ): string {
        return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone ?: wp_timezone() )->format( $format );
    }
}

namespace smp_publication_integration\Support {
    final class Fields {
        public static function get( int $post_id, string $field, $default = '' ) { return $default; }
        public static function has_value( $value ): bool { return ! empty( $value ); }
    }
    final class RuntimeContext {}
    final class Settings {
        public static function bool( string $key ): bool { return in_array( $key, [ 'table_of_contents_enabled', 'post_faqs_acf_enabled' ], true ); }
        public static function get( string $key, $default = null ) { return $default; }
    }
}

namespace Hexa\PluginCore\FaqSets {
    final class FaqSetManager {
        public function normalizeItems( $items ): array { return is_array( $items ) ? $items : []; }
        public function renderItems( $items, array $args = [] ): string { return ''; }
    }
}

namespace smp_publication_integration\Content {
    final class TemplateMarkup {
        public static function root_classes( string $component, array $classes = [] ): string {
            return implode( ' ', array_merge( [ 'smpi-template', 'smpi-template--' . $component ], $classes ) );
        }
        public static function decorate_rich_text( string $html, string $component ): string { return $html; }
    }
    final class ArticleStyles {
        public static function normalize_toc_style( string $style = '' ): string { return '' !== $style ? $style : 'toc02'; }
        public static function normalize_summary_style( string $style = '' ): string { return $style; }
        public static function summary_title( string $style ): string { return 'What to know'; }
        public static function wrap_post_faqs( string $html, string $style = '' ): string {
            return '<section class="smpi-post-faqs smpi-' . $style . '">' . $html . '</section>';
        }
    }
    final class Schema {
        public static array $rows = [
            [ 'question' => 'First question?', 'answer' => 'First answer.' ],
            [ 'question' => 'Second question?', 'answer' => 'Second answer.' ],
        ];
        public static function faq_rows_for_post( int $post_id, bool $schema_only = true ): array { return self::$rows; }
    }
}

namespace {
    require dirname( __DIR__ ) . '/src/Content/TableOfContents.php';
    require dirname( __DIR__ ) . '/src/Content/Shortcodes.php';
    require dirname( __DIR__ ) . '/src/Content/PostTime.php';

    $fail = static function ( bool $condition, string $message ): void {
        if ( ! $condition ) {
            fwrite( STDERR, "FAIL: {$message}\n" );
            exit( 1 );
        }
    };

    $toc = ( new \smp_publication_integration\Content\TableOfContents() )->render_shortcode(
        [ 'style' => 'unstyled', 'title' => 'Contents', 'levels' => 'h2', 'open' => 'desktop', 'markers' => 'leading-zero' ]
    );
    $fail( str_contains( $toc, '>Contents<' ) && ! str_contains( $toc, 'On this page' ), 'Explicit unstyled TOC title was not preserved.' );
    $fail( str_contains( $toc, 'data-smpi-open="desktop"' ) && 1 === preg_match( '/<details\\b[^>]*\\sopen(?:\\s|>)/', $toc ) && str_contains( $toc, 'smpi-toc-responsive-open-script' ) && str_contains( $toc, 'data-no-optimize="1"' ), 'Desktop-open TOC state or optimization exclusion was not rendered.' );
    $fail( str_contains( $toc, 'First section' ) && str_contains( $toc, 'Last section' ) && ! str_contains( $toc, 'Nested section' ), 'TOC heading-level filter did not keep only h2 headings.' );
    $fail( str_contains( $toc, 'data-smpi-markers="leading-zero"' ) && str_contains( $toc, '>01</span>' ) && str_contains( $toc, '>02</span>' ), 'Leading-zero TOC markers were not rendered.' );

    $faq = ( new \smp_publication_integration\Content\Shortcodes() )->render_post_faqs( [ 'format' => 'accordion', 'style' => 'unstyled' ] );
    $fail( 2 === substr_count( $faq, '<details ' ) && 2 === substr_count( $faq, '<summary ' ), 'Structured FAQ accordion did not render native details and summary pairs.' );
    \smp_publication_integration\Content\Schema::$rows = [];
    $fail( '' === ( new \smp_publication_integration\Content\Shortcodes() )->render_post_faqs( [ 'format' => 'accordion' ] ), 'FAQ accordion rendered without structured FAQ rows.' );

    $date = ( new \smp_publication_integration\Content\PostTime() )->render_date_shortcode( [ 'format' => 'M j, Y · g:i A' ] );
    $fail( 'Oct 17, 2025 · 12:30 PM' === $date, 'Published-date shortcode did not use the original timestamp in the site timezone.' );

    echo "PASS: Article template shortcodes render server-side TOC, structured FAQ accordion, and absolute published date.\n";
}
