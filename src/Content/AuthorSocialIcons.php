<?php

namespace smp_publication_integration\Content;

use Hexa\PluginCore\BrandColors\TemplateColorResolver;
use smp_publication_integration\Authorship\AuthorFieldResolver;
use smp_publication_integration\Config;
use smp_publication_integration\Design\TemplateDesignRegistry;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class AuthorSocialIcons {
    public const SHORTCODE = "smp_author_social_icons";

    private const STYLES = [
        "social-solid"   => "Solid circles",
        "social-outline" => "Outline circles",
        "social-soft"    => "Soft tiles",
        "social-pills"   => "Labeled pills",
        "social-minimal" => "Minimal icons",
        "unstyled"       => "No Style (Elementor/custom CSS)",
    ];

    private const NETWORKS = [
        "website"    => "Website",
        "linkedin"   => "LinkedIn",
        "x"          => "X (Twitter)",
        "instagram"  => "Instagram",
        "facebook"   => "Facebook",
        "youtube"    => "YouTube",
        "crunchbase" => "Crunchbase",
        "muckrack"   => "Muck Rack",
    ];

    private const ARCHIVE_POSITIONS = [
        "below_name"  => "Below name",
        "below_title" => "Below title",
        "below_image" => "Below image",
        "below_bio"   => "Below bio",
    ];

    private bool $archive_injected = false;

    public function register(): void {
        add_action( "init", [ $this, "register_shortcode" ], 20 );
        add_action( "wp_enqueue_scripts", [ $this, "enqueue_styles" ] );
        add_filter( "the_content", [ $this, "inject_single_post" ], 25 );
        add_filter( "get_the_archive_description", [ $this, "inject_author_archive" ], 25 );
        add_filter( "elementor/widget/render_content", [ $this, "inject_elementor_author_archive" ], 25, 2 );
    }

    public function register_shortcode(): void {
        add_shortcode( self::SHORTCODE, [ $this, "render_shortcode" ] );
    }

    public function enqueue_styles(): void {
        if ( ! Settings::bool( "author_social_icons_enabled" ) ) {
            return;
        }

        wp_enqueue_style(
            "smpi-author-social-icons",
            plugins_url( "assets/frontend/author-social-icons.css", dirname( __DIR__, 2 ) . "/smp-publication-integration.php" ),
            [],
            Config::VERSION
        );
    }

    public static function styles(): array {
        return self::STYLES;
    }

    public static function networks(): array {
        return self::NETWORKS;
    }

    public static function archive_positions(): array {
        return self::ARCHIVE_POSITIONS;
    }

    public static function normalize_archive_position( string $position ): string {
        $position = sanitize_key( $position );
        return isset( self::ARCHIVE_POSITIONS[ $position ] ) ? $position : "below_bio";
    }

    public static function normalize_style( string $style ): string {
        $style = sanitize_key( $style );
        return isset( self::STYLES[ $style ] ) ? $style : "social-solid";
    }

    public static function normalize_networks( $networks ): array {
        if ( is_string( $networks ) ) {
            $networks = preg_split( "/[\s,]+/", strtolower( $networks ) ) ?: [];
        }
        if ( ! is_array( $networks ) ) {
            return [];
        }

        $allowed = array_keys( self::NETWORKS );
        $items = array_values( array_unique( array_map( "sanitize_key", $networks ) ) );
        return array_values( array_intersect( $allowed, $items ) );
    }

    public function render_shortcode( array $atts = [] ): string {
        if ( ! Settings::bool( "author_social_icons_enabled" ) ) {
            return "";
        }

        $atts = shortcode_atts(
            [
                "user_id"      => 0,
                "post_id"      => 0,
                "author_index" => 0,
                "style"        => "",
                "size"         => "",
                "color"        => "",
                "networks"     => "",
                "class"        => "",
                "label"        => "Author social links",
            ],
            $atts,
            self::SHORTCODE
        );

        $author_id = $this->resolve_author_id(
            (int) $atts["user_id"],
            (int) $atts["post_id"],
            (int) $atts["author_index"]
        );
        if ( $author_id <= 0 ) {
            return "";
        }

        return $this->render_for_author( $author_id, $atts );
    }

    public function render_for_author( int $author_id, array $args = [] ): string {
        if ( $author_id <= 0 ) {
            return "";
        }

        $settings = Settings::all();
        $style = "" !== trim( (string) ( $args["style"] ?? "" ) )
            ? self::normalize_style( (string) $args["style"] )
            : self::normalize_style( (string) ( $settings["author_social_style"] ?? "social-solid" ) );
        $size = "" !== trim( (string) ( $args["size"] ?? "" ) )
            ? (int) $args["size"]
            : (int) ( $settings["author_social_size"] ?? 24 );
        $size = max( 12, min( 64, $size ?: 24 ) );

        $enabled_networks = self::normalize_networks( $settings["author_social_networks"] ?? array_keys( self::NETWORKS ) );
        $requested_networks = trim( (string) ( $args["networks"] ?? "" ) );
        $networks = "" !== $requested_networks
            ? array_values( array_intersect( $enabled_networks, self::normalize_networks( $requested_networks ) ) )
            : $enabled_networks;
        if ( [] === $networks ) {
            return "";
        }

        $resolver = new AuthorFieldResolver();
        $links = [];
        foreach ( $networks as $network ) {
            $url = $resolver->social_url( $author_id, $network );
            if ( "" === $url ) {
                continue;
            }
            $links[ $network ] = $url;
        }
        if ( [] === $links ) {
            return "";
        }

        $classes = [ "smpi-template", "smpi-template--author-socials", "smpi-author-socials", "smpi-author-socials--" . $style, "smpi-" . $style ];
        if ( "unstyled" === $style ) {
            $classes[] = "smpi-unstyled";
        }
        foreach ( preg_split( "/\s+/", trim( (string) ( $args["class"] ?? "" ) ) ) ?: [] as $class ) {
            $class = sanitize_html_class( $class );
            if ( "" !== $class ) {
                $classes[] = $class;
            }
        }

        $style_attribute = "";
        if ( "unstyled" !== $style ) {
            $design_settings = $settings;
            $design_settings["author_social_style"] = $style;
            $custom_color = sanitize_hex_color( (string) ( $args["color"] ?? "" ) );
            if ( is_string( $custom_color ) && "" !== $custom_color ) {
                $design_settings["author_social_color_source"] = TemplateColorResolver::CUSTOM;
                $design_settings["author_social_color"] = $custom_color;
            }
            $declarations = TemplateDesignRegistry::css_declarations( "author_social", $design_settings );
            $style_attribute = " style=\"" . esc_attr( $declarations . ";--smpi-author-social-size:" . $size . "px" ) . "\"";
        }

        $label = trim( wp_strip_all_tags( (string) ( $args["label"] ?? "Author social links" ) ) );
        if ( "" === $label ) {
            $label = "Author social links";
        }
        $author_name = trim( (string) get_the_author_meta( "display_name", $author_id ) );
        $items = "";
        foreach ( $links as $network => $url ) {
            $network_label = self::NETWORKS[ $network ];
            $aria_label = "" !== $author_name
                ? sprintf( "%s on %s", $author_name, $network_label )
                : $network_label;
            $items .= "<li class=\"smpi-template-item smpi-author-social-item smpi-author-socials__item smpi-author-social-item--" . esc_attr( $network ) . "\" data-network=\"" . esc_attr( $network ) . "\"><a class=\"smpi-template-link smpi-author-social-link smpi-author-socials__link\" href=\"" . esc_url( $url ) . "\" target=\"_blank\" rel=\"me noopener noreferrer\" aria-label=\"" . esc_attr( $aria_label ) . "\"><span class=\"smpi-author-social-icon smpi-author-socials__icon\" aria-hidden=\"true\">" . self::icon_svg( $network ) . "</span><span class=\"smpi-author-social-label smpi-author-socials__label\">" . esc_html( $network_label ) . "</span></a></li>";
        }

        return "<nav class=\"" . esc_attr( implode( " ", array_unique( $classes ) ) ) . "\" data-smpi-skin=\"" . ( "unstyled" === $style ? "unstyled" : "styled" ) . "\" aria-label=\"" . esc_attr( $label ) . "\"" . $style_attribute . "><ul class=\"smpi-template-list smpi-author-social-list smpi-author-socials__list\" role=\"list\">" . $items . "</ul></nav>";
    }

    public function inject_single_post( string $content ): string {
        if (
            ! Settings::bool( "author_social_icons_enabled" )
            || ! $this->automatic_context_enabled( "single_post" )
            || is_admin()
            || ! is_singular( "post" )
            || ! in_the_loop()
            || ! is_main_query()
            || false !== strpos( $content, "smpi-author-socials" )
        ) {
            return $content;
        }

        $icons = $this->render_shortcode( [ "post_id" => (int) get_the_ID() ] );
        return "" !== $icons ? $content . $icons : $content;
    }

    public function inject_author_archive( string $description ): string {
        if (
            $this->archive_injected
            || ! Settings::bool( "author_social_icons_enabled" )
            || ! $this->automatic_context_enabled( "author_archive" )
            || "below_bio" !== $this->archive_position()
            || is_admin()
            || ! is_author()
            || false !== strpos( $description, "smpi-author-socials" )
            || ( function_exists( "did_action" ) && did_action( "elementor/loaded" ) )
        ) {
            return $description;
        }

        $author_id = (int) get_queried_object_id();
        $icons = $this->render_for_author( $author_id );
        if ( "" === $icons ) {
            return $description;
        }

        $this->archive_injected = true;
        return $description . $this->automatic_archive_wrapper( $icons, "below_bio" );
    }

    public function inject_elementor_author_archive( string $content, $widget ): string {
        if (
            $this->archive_injected
            || ! Settings::bool( "author_social_icons_enabled" )
            || ! $this->automatic_context_enabled( "author_archive" )
            || is_admin()
            || ! is_author()
            || false !== strpos( $content, "smpi-author-socials" )
        ) {
            return $content;
        }

        $position = $this->archive_position();
        if ( ! $this->elementor_widget_matches_position( $widget, $position ) ) {
            return $content;
        }

        $author_id = (int) get_queried_object_id();
        $icons = $this->render_for_author( $author_id );
        if ( "" === $icons ) {
            return $content;
        }

        $this->archive_injected = true;
        return $content . $this->automatic_archive_wrapper( $icons, $position );
    }

    public static function preview_html( string $style, int $size = 24 ): string {
        $style = self::normalize_style( $style );
        $size = max( 12, min( 64, $size ) );
        $style_attribute = "unstyled" === $style ? "" : " style=\"--smpi-author-social-size:" . $size . "px\"";
        $items = "";
        foreach ( [ "x", "linkedin", "instagram" ] as $network ) {
            $items .= "<li class=\"smpi-template-item smpi-author-social-item smpi-author-socials__item smpi-author-social-item--" . esc_attr( $network ) . "\" data-network=\"" . esc_attr( $network ) . "\"><span class=\"smpi-template-link smpi-author-social-link smpi-author-socials__link\"><span class=\"smpi-author-social-icon smpi-author-socials__icon\" aria-hidden=\"true\">" . self::icon_svg( $network ) . "</span><span class=\"smpi-author-social-label smpi-author-socials__label\">" . esc_html( self::NETWORKS[ $network ] ) . "</span></span></li>";
        }
        $classes = "smpi-template smpi-template--author-socials smpi-author-socials smpi-author-socials--" . $style . " smpi-" . $style . ( "unstyled" === $style ? " smpi-unstyled" : "" );
        return "<nav class=\"" . esc_attr( $classes ) . "\" data-smpi-skin=\"" . ( "unstyled" === $style ? "unstyled" : "styled" ) . "\" aria-label=\"Author social links preview\"" . $style_attribute . "><ul class=\"smpi-template-list smpi-author-social-list smpi-author-socials__list\" role=\"list\">" . $items . "</ul></nav>";
    }

    private function resolve_author_id( int $explicit_user_id, int $explicit_post_id, int $author_index ): int {
        if ( $explicit_user_id > 0 ) {
            return $explicit_user_id;
        }
        if ( is_author() && $explicit_post_id <= 0 ) {
            $queried_id = (int) get_queried_object_id();
            if ( $queried_id > 0 ) {
                return $queried_id;
            }
        }
        return MultiAuthors::resolve_author_id( 0, $explicit_post_id, max( 0, $author_index ) );
    }

    private function automatic_context_enabled( string $context ): bool {
        $contexts = Settings::get( "author_social_auto_contexts", [] );
        $contexts = is_array( $contexts ) ? array_map( "sanitize_key", $contexts ) : [];
        return in_array( $context, $contexts, true );
    }

    private function archive_position(): string {
        return self::normalize_archive_position( (string) Settings::get( "author_social_archive_position", "below_bio" ) );
    }

    private function automatic_archive_wrapper( string $icons, string $position ): string {
        $position = self::normalize_archive_position( $position );
        return "<div class=\"smpi-author-social-auto smpi-author-social-auto--" . esc_attr( $position ) . "\" data-smpi-author-social-placement=\"" . esc_attr( $position ) . "\">" . $icons . "</div>";
    }

    private function elementor_widget_matches_position( $widget, string $position ): bool {
        if ( ! is_object( $widget ) ) {
            return false;
        }

        $settings = [];
        if ( method_exists( $widget, "get_settings_for_display" ) ) {
            $settings = (array) $widget->get_settings_for_display();
        } elseif ( method_exists( $widget, "get_settings" ) ) {
            $settings = (array) $widget->get_settings();
        }

        $widget_name = method_exists( $widget, "get_name" ) ? sanitize_key( (string) $widget->get_name() ) : "";
        $settings_json = function_exists( "wp_json_encode" ) ? wp_json_encode( $settings ) : json_encode( $settings );
        $haystack = strtolower( rawurldecode( is_string( $settings_json ) ? $settings_json : "" ) );
        $admin_title = strtolower( trim( (string) ( $settings["_title"] ?? "" ) ) );

        if ( "below_image" === $position ) {
            return false !== strpos( $haystack, "[author_image" )
                || false !== strpos( $haystack, "smpi-author-image" )
                || ( "shortcode" === $widget_name && ( false !== strpos( $admin_title, "author image" ) || false !== strpos( $admin_title, "author portrait" ) ) );
        }

        if ( "below_name" === $position ) {
            return false !== strpos( $haystack, "[author_name" )
                || false !== strpos( $haystack, 'name=\"author-name\"' )
                || false !== strpos( $admin_title, "author name" );
        }

        if ( "below_title" === $position ) {
            return false !== strpos( $haystack, "[author_title" )
                || false !== strpos( $haystack, "job_title" )
                || false !== strpos( $haystack, ":subtitle" )
                || false !== strpos( $admin_title, "author title" )
                || false !== strpos( $admin_title, "author role" );
        }

        return false !== strpos( $haystack, "[author_bio" )
            || false !== strpos( $haystack, 'name=\"author-info\"' )
            || false !== strpos( $admin_title, "author bio" )
            || false !== strpos( $admin_title, "author biography" );
    }

    private static function icon_svg( string $network ): string {
        $paths = [
            "x"          => '<path d="M18.9 2H22l-6.8 7.8L23.2 22H17l-4.9-6.4L6.5 22H3.3l7.3-8.4L2.8 2h6.4l4.4 5.8L18.9 2Zm-1.1 17.9h1.7L8.2 4H6.4l11.4 15.9Z"/>',
            "linkedin"   => '<path d="M6.9 8.5H3.4V20h3.5V8.5ZM5.1 3A2.1 2.1 0 1 0 5.1 7.2 2.1 2.1 0 0 0 5.1 3ZM20.6 13.4c0-3.5-1.9-5.2-4.5-5.2-2.1 0-3 1.1-3.5 1.9V8.5H9.1V20h3.5v-5.7c0-1.5.3-3 2.2-3 1.9 0 1.9 1.8 1.9 3.1V20h3.5l.4-6.6Z"/>',
            "facebook"   => '<path d="M14.2 22v-8h2.7l.4-3.1h-3.1v-2c0-.9.3-1.5 1.6-1.5h1.7V4.6c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.1H8V14h2.8v8h3.4Z"/>',
            "instagram"  => '<path d="M12 2.2c3.2 0 3.6 0 4.9.1 3.3.1 4.8 1.7 5 5 .1 1.3.1 1.7.1 4.9 0 3.2 0 3.6-.1 4.9-.1 3.3-1.7 4.8-5 5-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-3.3-.1-4.8-1.7-5-5C2 15.8 2 15.4 2 12.2c0-3.2 0-3.6.1-4.9.1-3.3 1.7-4.8 5-5 1.3-.1 1.7-.1 4.9-.1Zm0 1.8c-3.1 0-3.5 0-4.8.1-2.4.1-3.2 1.3-3.3 3.3-.1 1.3-.1 1.7-.1 4.8s0 3.5.1 4.8c.1 2.4 1.3 3.2 3.3 3.3 1.3.1 1.7.1 4.8.1s3.5 0 4.8-.1c2.4-.1 3.2-1.3 3.3-3.3.1-1.3.1-1.7.1-4.8s0-3.5-.1-4.8c-.1-2.4-1.3-3.2-3.3-3.3C15.5 4 15.1 4 12 4Zm0 3.1a5.1 5.1 0 1 1 0 10.2 5.1 5.1 0 0 1 0-10.2Zm0 8.4a3.3 3.3 0 1 0 0-6.6 3.3 3.3 0 0 0 0 6.6Zm6.5-8.6a1.2 1.2 0 1 1-2.4 0 1.2 1.2 0 0 1 2.4 0Z"/>',
            "youtube"    => '<path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4L15.8 12l-6.2 3.6Z"/>',
            "website"    => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.9 6h-3a15 15 0 0 0-1.4-3.5A8.1 8.1 0 0 1 18.9 8ZM12 4c.9 1.1 1.6 2.4 1.9 4h-3.8c.3-1.6 1-2.9 1.9-4ZM4.3 14a8 8 0 0 1 0-4h3.8a16 16 0 0 0 0 4H4.3Zm.8 2h3a15 15 0 0 0 1.4 3.5A8.1 8.1 0 0 1 5.1 16Zm3-8h-3a8.1 8.1 0 0 1 4.4-3.5A15 15 0 0 0 8.1 8Zm3.9 12c-.9-1.1-1.6-2.4-1.9-4h3.8c-.3 1.6-1 2.9-1.9 4Zm2.3-6H9.7a14 14 0 0 1 0-4h4.6a14 14 0 0 1 0 4Zm.2 5.5a15 15 0 0 0 1.4-3.5h3a8.1 8.1 0 0 1-4.4 3.5Zm1.4-5.5a16 16 0 0 0 0-4h3.8a8 8 0 0 1 0 4h-3.8Z"/>',
            "crunchbase" => '<path d="M4.7 8.2a3.8 3.8 0 1 0 3.2 5.8H5.8a1.9 1.9 0 1 1 0-2.1h2.1a3.8 3.8 0 0 0-3.2-3.7Zm9.6 0c-.8 0-1.6.3-2.2.8V4h-2v11.9h2v-.6a3.8 3.8 0 1 0 2.2-7.1Zm0 5.7a1.9 1.9 0 1 1 0-3.8 1.9 1.9 0 0 1 0 3.8Z"/>',
            "muckrack"   => '<path d="M3 5h4.2l4.8 7 4.8-7H21v14h-4V11l-5 7-5-7v8H3V5Z"/>',
        ];
        $path = $paths[ $network ] ?? $paths["website"];
        return '<svg viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">' . $path . '</svg>';
    }
}
