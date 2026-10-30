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
        "tiktok"     => "TikTok",
        "medium"     => "Medium",
        "github"     => "GitHub",
        "soundcloud" => "SoundCloud",
        "flickr"     => "Flickr",
        "behance"    => "Behance",
        "imdb"       => "IMDb",
        "wikipedia"  => "Wikipedia",
        "signal"     => "NFX Signal",
        "everipedia" => "Everipedia",
        "wikitia"    => "Wikitia",
    ];

    /** Shorter visible names for labels="short"; the link's accessible name keeps the full name. */
    private const SHORT_NAMES = [
        "x" => "X",
    ];

    /** Logos drawn on their own canvas (view box, shapes), as the approved author drafts show them. */
    private const LOGOS = [
        "tiktok" => [ '0 0 448 512', '<path fill="currentColor" d="M448,209.91a210.06,210.06,0,0,1-122.77-39.25V349.38A162.55,162.55,0,1,1,185,188.31V278.2a74.62,74.62,0,1,0,52.23,71.18V0l88,0a121.18,121.18,0,0,0,1.86,22.17h0A122.18,122.18,0,0,0,381,102.39a121.43,121.43,0,0,0,67,20.14Z"/>' ],
        "medium" => [ '0 0 640 512', '<path fill="currentColor" d="M180.5,74.262C80.813,74.262,0,155.633,0,256S80.819,437.738,180.5,437.738,361,356.373,361,256,280.191,74.262,180.5,74.262Zm288.25,10.646c-49.845,0-90.245,76.619-90.245,171.095s40.406,171.1,90.251,171.1,90.251-76.619,90.251-171.1H559C559,161.5,518.6,84.908,468.752,84.908Zm139.506,17.821c-17.526,0-31.735,68.628-31.735,153.274s14.2,153.274,31.735,153.274S640,340.631,640,256C640,171.351,625.785,102.729,608.258,102.729Z"/>' ],
        "github" => [ '0 0 496 512', '<path fill="currentColor" d="M165.9 397.4c0 2-2.3 3.6-5.2 3.6-3.3.3-5.6-1.3-5.6-3.6 0-2 2.3-3.6 5.2-3.6 3-.3 5.6 1.3 5.6 3.6zm-31.1-4.5c-.7 2 1.3 4.3 4.3 4.9 2.6 1 5.6 0 6.2-2s-1.3-4.3-4.3-5.2c-2.6-.7-5.5.3-6.2 2.3zm44.2-1.7c-2.9.7-4.9 2.6-4.6 4.9.3 2 2.9 3.3 5.9 2.6 2.9-.7 4.9-2.6 4.6-4.6-.3-1.9-3-3.2-5.9-2.9zM244.8 8C106.1 8 0 113.3 0 252c0 110.9 69.8 205.8 169.5 239.2 12.8 2.3 17.3-5.6 17.3-12.1 0-6.2-.3-40.4-.3-61.4 0 0-70 15-84.7-29.8 0 0-11.4-29.1-27.8-36.6 0 0-22.9-15.7 1.6-15.4 0 0 24.9 2 38.6 25.8 21.9 38.6 58.6 27.5 72.9 20.9 2.3-16 8.8-27.1 16-33.7-55.9-6.2-112.3-14.3-112.3-110.5 0-27.5 7.6-41.3 23.6-58.9-2.6-6.5-11.1-33.3 2.6-67.9 20.9-6.5 69 27 69 27 20-5.6 41.5-8.5 62.8-8.5s42.8 2.9 62.8 8.5c0 0 48.1-33.6 69-27 13.7 34.7 5.2 61.4 2.6 67.9 16 17.7 25.8 31.5 25.8 58.9 0 96.5-58.9 104.2-114.8 110.5 9.2 7.9 17 22.9 17 46.4 0 33.7-.3 75.4-.3 83.6 0 6.5 4.6 14.4 17.3 12.1C428.2 457.8 496 362.9 496 252 496 113.3 383.5 8 244.8 8zM97.2 352.9c-1.3 1-1 3.3.7 5.2 1.6 1.6 3.9 2.3 5.2 1 1.3-1 1-3.3-.7-5.2-1.6-1.6-3.9-2.3-5.2-1zm-10.8-8.1c-.7 1.3.3 2.9 2.3 3.9 1.6 1 3.6.7 4.3-.7.7-1.3-.3-2.9-2.3-3.9-2-.6-3.6-.3-4.3.7zm32.4 35.6c-1.6 1.3-1 4.3 1.3 6.2 2.3 2.3 5.2 2.6 6.5 1 1.3-1.3.7-4.3-1.3-6.2-2.2-2.3-5.2-2.6-6.5-1zm-11.4-14.7c-1.6 1-1.6 3.6 0 5.9 1.6 2.3 4.3 3.3 5.6 2.3 1.6-1.3 1.6-3.9 0-6.2-1.4-2.3-4-3.3-5.6-2z"/>' ],
        "soundcloud" => [ '0 0 640 512', '<path fill="currentColor" d="M639.8 298.6c-1.3 23.1-11.5 44.8-28.4 60.5s-39.2 24.4-62.3 24.1h-218c-4.8 0-9.4-2-12.8-5.4s-5.3-8-5.3-12.8V130.2c-.2-4 .9-8 3.1-11.4s5.3-6.1 9-7.7c0 0 20.1-13.9 62.3-13.9c25.8 0 51.1 6.9 73.3 20.1c17.3 10.2 32.3 23.8 44.1 40.1s20 34.8 24.2 54.4c7.5-2.1 15.3-3.2 23.1-3.2c11.7-.1 23.3 2.2 34.2 6.7S606.8 226.6 615 235s14.6 18.3 18.9 29.3s6.3 22.6 5.9 34.3zm-354-153.5c.1-1 0-2-.3-2.9s-.8-1.8-1.5-2.6s-1.5-1.3-2.4-1.7s-1.9-.6-2.9-.6s-2 .2-2.9 .6s-1.7 1-2.4 1.7s-1.2 1.6-1.5 2.6s-.4 1.9-.3 2.9c-6 78.9-10.6 152.9 0 231.6c.2 1.7 1 3.3 2.3 4.5s3 1.8 4.7 1.8s3.4-.6 4.7-1.8s2.1-2.8 2.3-4.5c11.3-79.4 6.6-152 0-231.6zm-44 27.3c-.2-1.8-1.1-3.5-2.4-4.7s-3.1-1.9-5-1.9s-3.6 .7-5 1.9s-2.2 2.9-2.4 4.7c-7.9 67.9-7.9 136.5 0 204.4c.3 1.8 1.2 3.4 2.5 4.5s3.1 1.8 4.8 1.8s3.5-.6 4.8-1.8s2.2-2.8 2.5-4.5c8.8-67.8 8.8-136.5 .1-204.4zm-44.3-6.9c-.2-1.8-1-3.4-2.3-4.6s-3-1.8-4.8-1.8s-3.5 .7-4.8 1.8s-2.1 2.8-2.3 4.6c-6.7 72-10.2 139.3 0 211.1c0 1.9 .7 3.7 2.1 5s3.1 2.1 5 2.1s3.7-.7 5-2.1s2.1-3.1 2.1-5c10.5-72.8 7.3-138.2 .1-211.1zm-44 20.6c0-1.9-.8-3.8-2.1-5.2s-3.2-2.1-5.2-2.1s-3.8 .8-5.2 2.1s-2.1 3.2-2.1 5.2c-8.1 63.3-8.1 127.5 0 190.8c.2 1.8 1 3.4 2.4 4.6s3.1 1.9 4.8 1.9s3.5-.7 4.8-1.9s2.2-2.8 2.4-4.6c8.8-63.3 8.9-127.5 .3-190.8zM109 233.7c0-1.9-.8-3.8-2.1-5.1s-3.2-2.1-5.1-2.1s-3.8 .8-5.1 2.1s-2.1 3.2-2.1 5.1c-10.5 49.2-5.5 93.9 .4 143.6c.3 1.6 1.1 3.1 2.3 4.2s2.8 1.7 4.5 1.7s3.2-.6 4.5-1.7s2.1-2.5 2.3-4.2c6.6-50.4 11.6-94.1 .4-143.6zm-44.1-7.5c-.2-1.8-1.1-3.5-2.4-4.8s-3.2-1.9-5-1.9s-3.6 .7-5 1.9s-2.2 2.9-2.4 4.8c-9.3 50.2-6.2 94.4 .3 144.5c.7 7.6 13.6 7.5 14.4 0c7.2-50.9 10.5-93.8 .3-144.5zM20.3 250.8c-.2-1.8-1.1-3.5-2.4-4.8s-3.2-1.9-5-1.9s-3.6 .7-5 1.9s-2.3 2.9-2.4 4.8c-8.5 33.7-5.9 61.6 .6 95.4c.2 1.7 1 3.3 2.3 4.4s2.9 1.8 4.7 1.8s3.4-.6 4.7-1.8s2.1-2.7 2.3-4.4c7.5-34.5 11.2-61.8 .4-95.4z"/>' ],
        "flickr" => [ '0 0 448 512', '<path fill="currentColor" d="M400 32H48C21.5 32 0 53.5 0 80v352c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V80c0-26.5-21.5-48-48-48zM144.5 319c-35.1 0-63.5-28.4-63.5-63.5s28.4-63.5 63.5-63.5 63.5 28.4 63.5 63.5-28.4 63.5-63.5 63.5zm159 0c-35.1 0-63.5-28.4-63.5-63.5s28.4-63.5 63.5-63.5 63.5 28.4 63.5 63.5-28.4 63.5-63.5 63.5z"/>' ],
        "behance" => [ '0 0 576 512', '<path fill="currentColor" d="M232 237.2c31.8-15.2 48.4-38.2 48.4-74 0-70.6-52.6-87.8-113.3-87.8H0v354.4h171.8c64.4 0 124.9-30.9 124.9-102.9 0-44.5-21.1-77.4-64.7-89.7zM77.9 135.9H151c28.1 0 53.4 7.9 53.4 40.5 0 30.1-19.7 42.2-47.5 42.2h-79v-82.7zm83.3 233.7H77.9V272h84.9c34.3 0 56 14.3 56 50.6 0 35.8-25.9 47-57.6 47zm358.5-240.7H376V94h143.7v34.9zM576 305.2c0-75.9-44.4-139.2-124.9-139.2-78.2 0-131.3 58.8-131.3 135.8 0 79.9 50.3 134.7 131.3 134.7 61.3 0 101-27.6 120.1-86.3H509c-6.7 21.9-34.3 33.5-55.7 33.5-41.3 0-63-24.2-63-65.3h185.1c.3-4.2.6-8.7.6-13.2zM390.4 274c2.3-33.7 24.7-54.8 58.5-54.8 35.4 0 53.2 20.8 56.2 54.8H390.4z"/>' ],
        "imdb" => [ '0 0 448 512', '<path fill="currentColor" d="M89.5 323.6H53.93V186.2H89.5V323.6zM156.1 250.5L165.2 186.2H211.5V323.6H180.5V230.9L167.1 323.6H145.8L132.8 232.9L132.7 323.6H101.5V186.2H147.6C148.1 194.5 150.4 204.3 151.9 215.6L156.1 250.5zM223.7 323.6V186.2H250.3C267.3 186.2 277.3 187.1 283.3 188.6C289.4 190.3 294 192.8 297.2 196.5C300.3 199.8 302.3 203.1 303 208.5C303.9 212.9 304.4 221.6 304.4 234.7V282.9C304.4 295.2 303.7 303.4 302.5 307.6C301.4 311.7 299.4 315 296.5 317.3C293.7 319.7 290.1 321.4 285.8 322.3C281.6 323.1 275.2 323.6 266.7 323.6H223.7zM259.2 209.7V299.1C264.3 299.1 267.5 298.1 268.6 296.8C269.7 294.8 270.4 289.2 270.4 280.1V226.8C270.4 220.6 270.3 216.6 269.7 214.8C269.4 213 268.5 211.8 267.1 210.1C265.7 210.1 263 209.7 259.2 209.7V209.7zM316.5 323.6V186.2H350.6V230.1C353.5 227.7 356.7 225.2 360.1 223.5C363.7 222 368.9 221.1 372.9 221.1C377.7 221.1 381.8 221.9 385.2 223.3C388.6 224.8 391.2 226.8 393.2 229.5C394.9 232.1 395.9 234.8 396.3 237.3C396.7 239.9 396.1 245.3 396.1 253.5V292.1C396.1 300.3 396.3 306.4 395.3 310.5C394.2 314.5 391.5 318.1 387.5 320.1C383.4 324 378.6 325.4 372.9 325.4C368.9 325.4 363.7 324.5 360.2 322.9C356.7 321.1 353.5 318.4 350.6 314.9L348.5 323.6L316.5 323.6zM361.6 302.9C362.3 301.1 362.6 296.9 362.6 290.4V255C362.6 249.4 362.3 245.5 361.5 243.8C360.8 241.9 357.8 241.1 355.7 241.1C353.7 241.1 352.3 241.9 351.6 243.4C351 244.9 350.6 248.8 350.6 255V291.4C350.6 297.5 351 301.4 351.8 303C352.4 304.7 353.9 305.5 355.9 305.5C358.1 305.5 360.1 304.7 361.6 302.9L361.6 302.9zM418.4 32.04C434.1 33.27 447.1 47.28 447.1 63.92V448.1C447.1 464.5 435.2 478.5 418.9 479.1C418.6 479.1 418.4 480 418.1 480H29.88C29.6 480 29.32 479.1 29.04 479.9C13.31 478.5 1.093 466.1 0 449.7L.0186 61.78C1.081 45.88 13.82 33.09 30.26 31.1H417.7C417.9 31.1 418.2 32.01 418.4 32.04L418.4 32.04zM30.27 41.26C19 42.01 10.02 51.01 9.257 62.4V449.7C9.63 455.1 11.91 460.2 15.7 464C19.48 467.9 24.51 470.3 29.89 470.7H418.1C429.6 469.7 438.7 459.1 438.7 448.1V63.91C438.7 58.17 436.6 52.65 432.7 48.45C428.8 44.24 423.4 41.67 417.7 41.26L30.27 41.26z"/>' ],
        "wikipedia" => [ '0 0 640 512', '<path fill="currentColor" d="M640 51.2l-.3 12.2c-28.1.8-45 15.8-55.8 40.3-25 57.8-103.3 240-155.3 358.6H415l-81.9-193.1c-32.5 63.6-68.3 130-99.2 193.1-.3.3-15 0-15-.3C172 352.3 122.8 243.4 75.8 133.4 64.4 106.7 26.4 63.4.2 63.7c0-3.1-.3-10-.3-14.2h161.9v13.9c-19.2 1.1-52.8 13.3-43.3 34.2 21.9 49.7 103.6 240.3 125.6 288.6 15-29.7 57.8-109.2 75.3-142.8-13.9-28.3-58.6-133.9-72.8-160-9.7-17.8-36.1-19.4-55.8-19.7V49.8l142.5.3v13.1c-19.4.6-38.1 7.8-29.4 26.1 18.9 40 30.6 68.1 48.1 104.7 5.6-10.8 34.7-69.4 48.1-100.8 8.9-20.6-3.9-28.6-38.6-29.4.3-3.6 0-10.3.3-13.6 44.4-.3 111.1-.3 123.1-.6v13.6c-22.5.8-45.8 12.8-58.1 31.7l-59.2 122.8c6.4 16.1 63.3 142.8 69.2 156.7L559.2 91.8c-8.6-23.1-36.4-28.1-47.2-28.3V49.6l127.8 1.1.2.5z"/>' ],
    ];

    /** Networks with no logo, shown as a short text mark (class smpi-author-social-mono), as the drafts show them. */
    private const MONOGRAMS = [ "signal" => "NFX", "everipedia" => "E", "wikitia" => "Wt" ];

    private const ARCHIVE_POSITIONS = [
        "below_name"  => "Below name",
        "below_title" => "Below title",
        "below_image" => "Below image",
        "below_bio"   => "Below bio",
    ];

    private bool $archive_injected = false;
    private bool $single_injected = false;
    private ?array $single_plan = null;

    public function register(): void {
        add_action( "init", [ $this, "register_shortcode" ], 20 );
        add_action( "wp_enqueue_scripts", [ $this, "enqueue_styles" ] );
        add_filter( "the_content", [ $this, "inject_single_post" ], 25 );
        add_filter( "get_the_archive_description", [ $this, "inject_author_archive" ], 25 );
        add_filter( "elementor/widget/render_content", [ $this, "inject_elementor_author_archive" ], 25, 2 );
        add_filter( "elementor/widget/render_content", [ $this, "inject_elementor_single_post" ], 25, 2 );
    }

    public function register_shortcode(): void {
        add_shortcode( self::SHORTCODE, [ $this, "render_shortcode" ] );
    }

    /** Loads the icon styles on articles and author pages, where the icons are placed automatically. */
    public function enqueue_styles(): void {
        if ( ! Settings::bool( "author_social_icons_enabled" ) || ! ( is_singular( "post" ) || is_author() ) ) {
            return;
        }
        self::enqueue_stylesheet();
    }

    /** Also called by the shortcode, so any other page that uses it gets the styles. */
    public static function enqueue_stylesheet(): void {

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
        self::enqueue_stylesheet();

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
                "labels"       => "",
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
        $short = "short" === sanitize_key( (string) ( $args["labels"] ?? "" ) );
        $items = "";
        foreach ( $links as $network => $url ) {
            $network_label = self::NETWORKS[ $network ];
            $visible_label = $short && isset( self::SHORT_NAMES[ $network ] ) ? self::SHORT_NAMES[ $network ] : $network_label;
            $aria_label = "" !== $author_name
                ? sprintf( "%s on %s", $author_name, $network_label )
                : $network_label;
            $items .= "<li class=\"smpi-template-item smpi-author-social-item smpi-author-socials__item smpi-author-social-item--" . esc_attr( $network ) . "\" data-network=\"" . esc_attr( $network ) . "\"><a class=\"smpi-template-link smpi-author-social-link smpi-author-socials__link\" href=\"" . esc_url( $url ) . "\" target=\"_blank\" rel=\"me noopener noreferrer\" aria-label=\"" . esc_attr( $aria_label ) . "\"><span class=\"smpi-author-social-icon smpi-author-socials__icon\" aria-hidden=\"true\">" . self::icon_svg( $network ) . "</span><span class=\"smpi-author-social-label smpi-author-socials__label\">" . esc_html( $visible_label ) . "</span></a></li>";
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

        $post_id = (int) get_the_ID();
        if ( "content" !== $this->single_post_plan( $post_id )["mode"] ) {
            return $content;
        }

        $icons = $this->render_shortcode( [ "post_id" => $post_id ] );
        if ( "" === $icons ) {
            return $content;
        }

        $this->single_injected = true;
        return $content . $icons;
    }

    public function inject_elementor_single_post( string $content, $widget ): string {
        if (
            $this->single_injected
            || ! Settings::bool( "author_social_icons_enabled" )
            || ! $this->automatic_context_enabled( "single_post" )
            || is_admin()
            || ! is_singular( "post" )
            || false !== strpos( $content, "smpi-author-socials" )
        ) {
            return $content;
        }

        // Loop items (related stories, author cards in grids) belong to other posts.
        $post_id = (int) get_queried_object_id();
        if ( $post_id <= 0 || (int) get_the_ID() !== $post_id ) {
            return $content;
        }

        $plan = $this->single_post_plan( $post_id );
        $widget_id = is_object( $widget ) && method_exists( $widget, "get_id" ) ? (string) $widget->get_id() : "";
        if ( "widget" !== $plan["mode"] || "" === $widget_id || $widget_id !== $plan["widget_id"] ) {
            return $content;
        }

        $icons = $this->render_shortcode( [ "post_id" => $post_id ] );
        if ( "" === $icons ) {
            return $content;
        }

        $this->single_injected = true;
        return $content . $this->automatic_archive_wrapper( $icons, $plan["position"] );
    }

    /**
     * Decides once per request where automatic single-post icons belong:
     * "none" when the layout already places the shortcode by hand, "widget"
     * when the Elementor layout has an author widget to sit under, otherwise
     * "content" (end of the article body, for layouts with no author block).
     */
    public function single_post_plan( int $post_id ): array {
        if ( null !== $this->single_plan ) {
            return $this->single_plan;
        }

        $widgets = [];
        foreach ( $this->single_layout_elements( $post_id ) as $elements ) {
            $this->collect_widgets( $elements, $widgets );
        }

        foreach ( $widgets as $widget ) {
            $json = function_exists( "wp_json_encode" ) ? wp_json_encode( $widget["settings"] ) : json_encode( $widget["settings"] );
            if ( is_string( $json ) && ( false !== strpos( $json, self::SHORTCODE ) || false !== strpos( $json, "smpi-author-socials" ) ) ) {
                return $this->single_plan = [ "mode" => "none", "position" => "", "widget_id" => "" ];
            }
        }

        // A real author signal (author box, bio shortcode or dynamic tag) beats
        // a widget that is only named like one; the first widget wins a tie.
        foreach ( array_unique( [ $this->archive_position(), "below_bio" ] ) as $position ) {
            $best = null;
            $best_strength = 0;
            foreach ( $widgets as $widget ) {
                $strength = $this->widget_match_strength( $widget["name"], $widget["settings"], $position );
                if ( $strength > $best_strength && "" !== $widget["id"] ) {
                    $best = $widget;
                    $best_strength = $strength;
                }
            }
            if ( null !== $best ) {
                return $this->single_plan = [ "mode" => "widget", "position" => $position, "widget_id" => $best["id"] ];
            }
        }

        return $this->single_plan = [ "mode" => "content", "position" => "", "widget_id" => "" ];
    }

    private function single_layout_elements( int $post_id ): array {
        $layouts = [];
        if ( class_exists( "\\ElementorPro\\Modules\\ThemeBuilder\\Module" ) ) {
            $documents = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_documents_for_location( "single" );
            foreach ( (array) $documents as $document ) {
                if ( is_object( $document ) && method_exists( $document, "get_elements_data" ) ) {
                    $layouts[] = (array) $document->get_elements_data();
                }
            }
        }

        if ( $post_id > 0 && function_exists( "get_post_meta" ) ) {
            $own = get_post_meta( $post_id, "_elementor_data", true );
            $own = is_string( $own ) ? json_decode( $own, true ) : $own;
            if ( is_array( $own ) ) {
                $layouts[] = $own;
            }
        }

        return $layouts;
    }

    private function collect_widgets( array $elements, array &$widgets ): void {
        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            if ( "widget" === ( $element["elType"] ?? "" ) ) {
                $widgets[] = [
                    "id"       => (string) ( $element["id"] ?? "" ),
                    "name"     => sanitize_key( (string) ( $element["widgetType"] ?? "" ) ),
                    "settings" => (array) ( $element["settings"] ?? [] ),
                ];
            }
            $this->collect_widgets( (array) ( $element["elements"] ?? [] ), $widgets );
        }
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
        return $this->widget_data_matches_position( $widget_name, $settings, $position );
    }

    private function widget_data_matches_position( string $widget_name, array $settings, string $position ): bool {
        return $this->widget_match_strength( $widget_name, $settings, $position ) > 0;
    }

    /**
     * 2 = the widget renders that author field, 1 = only its admin title says
     * so, 0 = no match. Titles naming another part of the card (a bio
     * portrait, a bio name) never count as the bio itself.
     */
    private function widget_match_strength( string $widget_name, array $settings, string $position ): int {
        $settings_json = function_exists( "wp_json_encode" ) ? wp_json_encode( $settings ) : json_encode( $settings );
        $haystack = strtolower( rawurldecode( is_string( $settings_json ) ? $settings_json : "" ) );
        $admin_title = strtolower( trim( (string) ( $settings["_title"] ?? "" ) ) );
        $title_has = static function ( array $needles, array $exclude = [] ) use ( $admin_title ): bool {
            foreach ( $exclude as $word ) {
                if ( false !== strpos( $admin_title, $word ) ) {
                    return false;
                }
            }
            foreach ( $needles as $needle ) {
                if ( false !== strpos( $admin_title, $needle ) ) {
                    return true;
                }
            }
            return false;
        };

        if ( "below_image" === $position ) {
            if ( false !== strpos( $haystack, "[author_image" ) || false !== strpos( $haystack, "smpi-author-image" ) ) {
                return 2;
            }
            return "shortcode" === $widget_name && $title_has( [ "author image", "author portrait" ] ) ? 1 : 0;
        }

        if ( "below_name" === $position ) {
            if ( false !== strpos( $haystack, "[author_name" ) || false !== strpos( $haystack, 'name=\"author-name\"' ) ) {
                return 2;
            }
            return $title_has( [ "author name" ] ) ? 1 : 0;
        }

        if ( "below_title" === $position ) {
            if ( false !== strpos( $haystack, "[author_title" ) || false !== strpos( $haystack, "job_title" ) || false !== strpos( $haystack, ":subtitle" ) ) {
                return 2;
            }
            return $title_has( [ "author title", "author role" ] ) ? 1 : 0;
        }

        if ( "author-box" === $widget_name || false !== strpos( $haystack, "[author_bio" ) || false !== strpos( $haystack, 'name=\"author-info\"' ) ) {
            return 2;
        }
        return $title_has( [ "author bio", "author biography" ], [ "portrait", "image", "photo", "avatar", "name", "heading", "title" ] ) ? 1 : 0;
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
        if ( ! isset( $paths[ $network ] ) && isset( self::LOGOS[ $network ] ) ) {
            return '<svg viewBox="' . self::LOGOS[ $network ][0] . '" fill="currentColor" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">' . self::LOGOS[ $network ][1] . '</svg>';
        }
        if ( ! isset( $paths[ $network ] ) && isset( self::MONOGRAMS[ $network ] ) ) {
            return '<span class="smpi-author-social-mono" aria-hidden="true">' . esc_html( self::MONOGRAMS[ $network ] ) . '</span>';
        }
        $path = $paths[ $network ] ?? $paths["website"];
        return '<svg viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">' . $path . '</svg>';
    }
}
