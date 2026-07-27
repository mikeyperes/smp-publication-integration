<?php
namespace smp_publication_integration\Content;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Stable class contracts for SMP-owned front-end markup.
 */
final class TemplateMarkup {
    public const ROOT = "smpi-template";
    public const TITLE = "smpi-template-title";
    public const CONTENT = "smpi-template-content";
    public const LIST = "smpi-template-list";
    public const ITEM = "smpi-template-item";
    public const LINK = "smpi-template-link";
    public const IMAGE = "smpi-template-image";
    public const CAPTION = "smpi-template-caption";
    public const TEXT = "smpi-template-text";

    public static function root_classes( string $component, array $classes = [] ): string {
        array_unshift( $classes, self::ROOT, "smpi-template--" . self::class_slug( $component ) );
        return implode( " ", array_values( array_unique( array_filter( $classes ) ) ) );
    }

    public static function decorate_breadcrumbs( string $html ): string {
        return self::mutate(
            $html,
            static function ( $processor ): array {
                $tag = $processor->get_tag();
                if ( "NAV" === $tag ) {
                    return [ self::CONTENT, "smpi-breadcrumb-nav" ];
                }
                if ( in_array( $tag, [ "P", "OL", "UL" ], true ) ) {
                    return [ self::LIST, "smpi-breadcrumb-list" ];
                }
                if ( "LI" === $tag ) {
                    return [ self::ITEM, "smpi-breadcrumb-item" ];
                }
                if ( "A" === $tag ) {
                    return [ self::ITEM, self::LINK, "smpi-breadcrumb-item", "smpi-breadcrumb-link" ];
                }
                if ( "SPAN" === $tag && self::has_class( $processor, "separator" ) ) {
                    return [ "smpi-breadcrumb-separator" ];
                }
                if ( "SPAN" === $tag && self::has_class( $processor, "last" ) ) {
                    return [ self::ITEM, "smpi-breadcrumb-item", "smpi-breadcrumb-current" ];
                }
                return [];
            }
        );
    }

    public static function decorate_article_content( string $html, array $heading_ids = [] ): string {
        $heading_index = 0;
        $lead_found = false;

        return self::mutate(
            $html,
            static function ( $processor ) use ( &$heading_index, &$lead_found, $heading_ids ): array {
                $tag = $processor->get_tag();
                if ( in_array( $tag, [ "H2", "H3", "H4" ], true ) ) {
                    if ( isset( $heading_ids[ $heading_index ] ) && "" === (string) $processor->get_attribute( "id" ) ) {
                        $processor->set_attribute( "id", (string) $heading_ids[ $heading_index ] );
                    }
                    $heading_index++;
                    return [ self::TITLE, "smpi-article-heading", "smpi-article-heading--" . strtolower( $tag ) ];
                }
                if ( "P" === $tag ) {
                    $classes = [ self::TEXT, "smpi-article-paragraph" ];
                    if ( ! $lead_found && ! self::has_blocked_article_ancestor( $processor ) ) {
                        $classes[] = "smpi-article-lead";
                        $lead_found = true;
                    }
                    return $classes;
                }
                if ( "A" === $tag ) {
                    return [ self::LINK, "smpi-article-link" ];
                }
                if ( in_array( $tag, [ "OL", "UL" ], true ) ) {
                    return [ self::LIST, "smpi-article-list" ];
                }
                if ( "LI" === $tag ) {
                    return [ self::ITEM, "smpi-article-list-item" ];
                }
                if ( "IMG" === $tag ) {
                    return [ self::IMAGE, "smpi-article-image" ];
                }
                return [];
            }
        );
    }

    public static function decorate_inline_photos( string $html, string $style ): string {
        $style = self::class_slug( $style );
        $active_depth = 0;

        return self::mutate(
            $html,
            static function ( $processor ) use ( $style, &$active_depth ): array {
                $tag = $processor->get_tag();
                $depth = self::depth( $processor );
                if ( $active_depth > 0 && $depth > 0 && $depth <= $active_depth ) {
                    $active_depth = 0;
                }

                $is_root = "FIGURE" === $tag || self::has_class( $processor, "wp-caption" ) || self::has_class( $processor, "smpi-inline-photo" );
                if ( $is_root ) {
                    $active_depth = $depth > 0 ? $depth : 1;
                    return [
                        self::ROOT,
                        "smpi-template--inline-photo",
                        "smpi-inline-photo",
                        "smpi-inline-photo--" . $style,
                    ];
                }

                $inside_figure = in_array( "FIGURE", self::breadcrumbs( $processor ), true );
                if ( $active_depth <= 0 && ! $inside_figure ) {
                    return [];
                }
                if ( "A" === $tag ) {
                    return [ self::LINK, "smpi-inline-photo-link" ];
                }
                if ( "IMG" === $tag ) {
                    return [ self::IMAGE, "smpi-inline-photo-image" ];
                }
                if ( "FIGCAPTION" === $tag || self::has_class( $processor, "wp-caption-text" ) ) {
                    return [ self::CAPTION, "smpi-inline-photo-caption" ];
                }
                return [];
            }
        );
    }

    public static function decorate_featured_media( string $html ): string {
        return self::mutate(
            $html,
            static function ( $processor ): array {
                $tag = $processor->get_tag();
                if ( "A" === $tag ) {
                    return [ self::LINK, "smpi-featured-image-caption-link" ];
                }
                if ( "IMG" === $tag ) {
                    return [ self::IMAGE, "smpi-featured-image-caption-image" ];
                }
                return [];
            }
        );
    }

    public static function decorate_rich_text( string $html, string $component ): string {
        $component = self::class_slug( $component );
        return self::mutate(
            $html,
            static function ( $processor ) use ( $component ): array {
                $tag = $processor->get_tag();
                if ( "A" === $tag ) {
                    return [ self::LINK, "smpi-" . $component . "-link" ];
                }
                if ( in_array( $tag, [ "OL", "UL" ], true ) ) {
                    return [ self::LIST, "smpi-" . $component . "-list" ];
                }
                if ( "LI" === $tag ) {
                    return [ self::ITEM, "smpi-" . $component . "-item" ];
                }
                if ( "P" === $tag ) {
                    return [ self::TEXT, "smpi-" . $component . "-text" ];
                }
                if ( "IMG" === $tag ) {
                    return [ self::IMAGE, "smpi-" . $component . "-image" ];
                }
                if ( in_array( $tag, [ "H2", "H3", "H4", "H5", "H6" ], true ) ) {
                    return [ self::TITLE, "smpi-" . $component . "-heading" ];
                }
                return [];
            }
        );
    }

    private static function mutate( string $html, callable $resolver ): string {
        if ( "" === trim( $html ) ) {
            return $html;
        }

        $processor = self::processor( $html );
        if ( ! $processor ) {
            return $html;
        }

        try {
            while ( $processor->next_token() ) {
                if ( "#tag" !== $processor->get_token_type() || $processor->is_tag_closer() ) {
                    continue;
                }
                foreach ( (array) $resolver( $processor ) as $class_name ) {
                    $class_name = self::class_slug( (string) $class_name );
                    if ( "" !== $class_name ) {
                        $processor->add_class( $class_name );
                    }
                }
            }
            return $processor->get_updated_html();
        } catch ( \Throwable $exception ) {
            return $html;
        }
    }

    private static function processor( string $html ) {
        if ( class_exists( "\\WP_HTML_Processor" ) ) {
            return \WP_HTML_Processor::create_fragment( $html );
        }
        if ( class_exists( "\\WP_HTML_Tag_Processor" ) ) {
            return new \WP_HTML_Tag_Processor( $html );
        }
        return null;
    }

    private static function has_class( $processor, string $class_name ): bool {
        return true === $processor->has_class( $class_name );
    }

    private static function breadcrumbs( $processor ): array {
        return method_exists( $processor, "get_breadcrumbs" ) ? $processor->get_breadcrumbs() : [];
    }

    private static function depth( $processor ): int {
        return method_exists( $processor, "get_current_depth" ) ? (int) $processor->get_current_depth() : 0;
    }

    private static function has_blocked_article_ancestor( $processor ): bool {
        return (bool) array_intersect( [ "ASIDE", "BLOCKQUOTE", "FIGURE", "NAV" ], self::breadcrumbs( $processor ) );
    }

    private static function class_slug( string $value ): string {
        return trim( (string) preg_replace( "/[^a-z0-9_-]+/", "-", strtolower( $value ) ), "-" );
    }
}
