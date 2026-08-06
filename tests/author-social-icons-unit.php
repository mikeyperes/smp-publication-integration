<?php

declare( strict_types=1 );

namespace {
    define( "ABSPATH", __DIR__ );

    function add_action( ...$args ): void {}
    function add_filter( ...$args ): void {}
    function add_shortcode( ...$args ): void {}
    function wp_enqueue_style( ...$args ): void {}
    function plugins_url( string $path, string $plugin = "" ): string { return "https://example.test/" . ltrim( $path, "/" ); }
    function sanitize_key( $value ): string { return strtolower( preg_replace( "/[^a-z0-9_-]/", "", (string) $value ) ?: "" ); }
    function sanitize_html_class( $value ): string { return preg_replace( "/[^A-Za-z0-9_-]/", "", (string) $value ) ?: ""; }
    function sanitize_hex_color( $value ): ?string { return preg_match( "/^#[0-9a-f]{6}$/i", (string) $value ) ? strtolower( (string) $value ) : null; }
    function shortcode_atts( array $defaults, array $atts, string $tag = "" ): array { return array_merge( $defaults, array_intersect_key( $atts, $defaults ) ); }
    function wp_strip_all_tags( $value ): string { return strip_tags( (string) $value ); }
    function esc_attr( $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES, "UTF-8" ); }
    function esc_html( $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES, "UTF-8" ); }
    function esc_url( $value ): string { return filter_var( (string) $value, FILTER_VALIDATE_URL ) ? (string) $value : ""; }
    function get_the_author_meta( string $key, int $user_id = 0 ): string { return "Example Author"; }
    function is_author(): bool { return false; }
    function get_queried_object_id(): int { return 0; }
    function is_admin(): bool { return false; }
    function is_singular( string $type = "" ): bool { return false; }
    function in_the_loop(): bool { return true; }
    function is_main_query(): bool { return true; }
    function get_the_ID(): int { return 55; }
}

namespace Hexa\PluginCore\BrandColors {
    final class TemplateColorResolver {
        public const CUSTOM = "custom";
    }
}

namespace smp_publication_integration {
    final class Config {
        public const VERSION = "test";
    }
}

namespace smp_publication_integration\Support {
    final class Settings {
        public static array $settings = [
            "author_social_icons_enabled" => true,
            "author_social_style" => "social-solid",
            "author_social_size" => 24,
            "author_social_networks" => [ "website", "linkedin", "x", "instagram" ],
            "author_social_auto_contexts" => [],
        ];

        public static function bool( string $key ): bool { return ! empty( self::$settings[ $key ] ); }
        public static function all(): array { return self::$settings; }
        public static function get( string $key, $fallback = null ) { return self::$settings[ $key ] ?? $fallback; }
    }
}

namespace smp_publication_integration\Authorship {
    final class AuthorFieldResolver {
        public static array $urls = [
            "website" => "https://example.test/author",
            "linkedin" => "https://linkedin.com/in/example",
            "x" => "",
            "instagram" => "https://instagram.com/example",
        ];

        public function social_url( int $author_id, string $network ): string {
            return (string) ( self::$urls[ $network ] ?? "" );
        }
    }
}

namespace smp_publication_integration\Design {
    final class TemplateDesignRegistry {
        public static function css_declarations( string $surface, array $settings ): string {
            $color = (string) ( $settings["author_social_color"] ?? "#2563eb" );
            return "--smpi-author-social-color:" . ( "" !== $color ? $color : "#2563eb" ) . ";--smpi-author-social-ink:#fff;--smpi-author-social-soft:rgba(37,99,235,.12)";
        }
    }
}

namespace smp_publication_integration\Content {
    final class MultiAuthors {
        public static function resolve_author_id( int $user_id = 0, int $post_id = 0, int $author_index = 0 ): int { return 9; }
    }
}

namespace {
    require dirname( __DIR__ ) . "/src/Content/AuthorSocialIcons.php";

    use smp_publication_integration\Authorship\AuthorFieldResolver;
    use smp_publication_integration\Content\AuthorSocialIcons;
    use smp_publication_integration\Support\Settings;

    function expect_author_social( bool $condition, string $message ): void {
        if ( ! $condition ) {
            fwrite( STDERR, "FAIL: " . $message . "\n" );
            exit( 1 );
        }
    }

    $styles = AuthorSocialIcons::styles();
    expect_author_social( 6 === count( $styles ), "The feature exposes five styled templates plus No Style." );
    expect_author_social( isset( $styles["unstyled"] ), "No Style is a first-class template." );

    $renderer = new AuthorSocialIcons();
    $html = $renderer->render_shortcode();
    expect_author_social( str_contains( $html, "smpi-author-socials--social-solid" ), "Saved visual template is applied." );
    expect_author_social( str_contains( $html, 'data-network="website"' ), "A populated enabled website is rendered." );
    expect_author_social( str_contains( $html, 'data-network="linkedin"' ), "A populated enabled LinkedIn value is rendered." );
    expect_author_social( ! str_contains( $html, 'data-network="x"' ), "An enabled network with no value is omitted." );
    expect_author_social( 3 === substr_count( $html, "<li " ), "Only populated enabled networks create list items." );
    expect_author_social( str_contains( $html, 'role="list"' ) && str_contains( $html, 'aria-label="Example Author on LinkedIn"' ), "The output carries semantic list and accessible link labels." );

    $narrowed = $renderer->render_shortcode( [ "networks" => "linkedin,youtube", "size" => "64", "color" => "#942929" ] );
    expect_author_social( str_contains( $narrowed, 'data-network="linkedin"' ), "A shortcode can narrow output to an admin-enabled network." );
    expect_author_social( ! str_contains( $narrowed, 'data-network="youtube"' ), "A shortcode cannot re-enable a network disabled in the backend." );
    expect_author_social( str_contains( $narrowed, "--smpi-author-social-size:64px" ) && str_contains( $narrowed, "#942929" ), "Validated size and color overrides reach the styled output." );

    $unstyled = $renderer->render_shortcode( [ "style" => "unstyled", "networks" => "linkedin" ] );
    expect_author_social( str_contains( $unstyled, "smpi-unstyled" ) && str_contains( $unstyled, 'data-smpi-skin="unstyled"' ), "No Style uses an explicit unstyled state." );
    expect_author_social( ! str_contains( $unstyled, " style=" ), "No Style emits no plugin design variables or inline presentation." );
    expect_author_social( str_contains( $unstyled, "smpi-author-socials__list" ) && str_contains( $unstyled, "smpi-author-socials__link" ), "No Style preserves stable customization hooks." );

    AuthorFieldResolver::$urls = [];
    expect_author_social( "" === $renderer->render_shortcode(), "An author with no populated social values produces no wrapper." );

    Settings::$settings["author_social_icons_enabled"] = false;
    AuthorFieldResolver::$urls = [ "website" => "https://example.test" ];
    expect_author_social( "" === $renderer->render_shortcode(), "The feature toggle disables shortcode output." );

    $root = dirname( __DIR__ );
    $dashboard = (string) file_get_contents( $root . "/src/Admin/Dashboard/DashboardController.php" );
    $ajax = (string) file_get_contents( $root . "/src/Admin/Ajax/AjaxController.php" );
    $runtime = (string) file_get_contents( $root . "/src/Runtime/Plugin.php" );
    $registry = (string) file_get_contents( $root . "/src/Design/TemplateDesignRegistry.php" );
    $css = (string) file_get_contents( $root . "/assets/frontend/author-social-icons.css" );
    expect_author_social( str_contains( $dashboard, '"Author social icons"' ) && str_contains( $dashboard, '"author_social_icons_enabled"' ), "The Authors feature group includes the reusable feature toggle card." );
    expect_author_social( str_contains( $dashboard, '"author_social_networks"' ) && str_contains( $dashboard, '"author_social_auto_contexts"' ), "The backend exposes network visibility and optional automatic contexts." );
    expect_author_social( str_contains( $dashboard, 'Advanced &gt; Custom CSS' ) && str_contains( $dashboard, 'start every rule with <code>selector</code>' ), "The backend includes scoped Elementor No Style instructions." );
    expect_author_social( str_contains( $dashboard, 'Visual HTML output' ) && str_contains( $dashboard, 'smpi-author-socials__item' ), "The backend shows the stable HTML structure visually." );
    expect_author_social( str_contains( $ajax, 'author_social_style' ) && str_contains( $ajax, 'author_social_size' ) && str_contains( $ajax, 'author_social_color' ), "AJAX persistence allowlists every author-social design control." );
    expect_author_social( str_contains( $runtime, 'new Content\\AuthorSocialIcons()' ), "The runtime registers the new module." );
    expect_author_social( str_contains( $registry, '"author_social" => [' ) && str_contains( $registry, '"--smpi-author-social-soft"' ), "The shared template color registry owns author-social design variables." );
    expect_author_social( str_contains( $css, 'max(44px,calc(var(--smpi-author-social-size) + 20px))' ) && str_contains( $css, ':not(.smpi-author-socials--unstyled)' ), "Styled templates keep 44px targets and exclude No Style from plugin presentation." );

    echo "PASS: Author social icons expose six presentation modes, accessible markup, backend visibility constraints, and empty-value suppression.\n";
}
