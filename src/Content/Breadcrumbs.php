<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Fields;
use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class Breadcrumbs {
    public const SHORTCODE = "smp_breadcrumbs";
    public const CSS_SETTING = "breadcrumbs_css_override";
    public const CSS_SELECTOR = 'body .smpi-breadcrumbs[class*="smpi-bc-"]';
    public const CSS_SCOPE_MARKER = '.smpi-breadcrumbs[class*="smpi-bc-"]';

    public function register(): void {
        add_shortcode( self::SHORTCODE, [ $this, "render_shortcode" ] );
        add_action( "wp_footer", [ $this, "print_auto_inject" ], 18 );
    }

    public function render_shortcode( array $atts = [] ): string {
        if ( ! Settings::bool( "breadcrumbs_enabled" ) || ! self::should_render() ) {
            return "";
        }
        $atts = shortcode_atts( [ "style" => "" ], $atts, self::SHORTCODE );
        return self::markup( sanitize_key( (string) $atts["style"] ) );
    }

    public function print_auto_inject(): void {
        if ( ! Settings::bool( "breadcrumbs_enabled" ) || ! self::should_render() ) {
            return;
        }

        $markup = self::markup();
        if ( "" === $markup ) {
            return;
        }

        $payload = wp_json_encode( [ "headerSelectors" => self::header_selectors() ] );
        ?>
        <div id="smpi-breadcrumbs-source" hidden><?php echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <script id="smpi-breadcrumbs-inject">
        (function(data){var source=document.getElementById("smpi-breadcrumbs-source");if(!source||document.querySelector("[data-smpi-breadcrumbs-injected]"))return;var bar=source.firstElementChild;if(!bar)return;function visible(el){var r=el.getBoundingClientRect();return r.width>1&&r.height>1&&window.getComputedStyle(el).display!=="none";}var selectors=(data&&data.headerSelectors)||[],target=null;for(var i=0;i<selectors.length;i++){var el=document.querySelector(selectors[i]);if(el&&visible(el)){target=el;break;}}bar.hidden=false;bar.setAttribute("data-smpi-breadcrumbs-injected","1");if(target&&target.parentNode){target.insertAdjacentElement("afterend",bar);}else if(document.body.firstElementChild){document.body.insertBefore(bar,document.body.firstElementChild);}else{document.body.appendChild(bar);}source.remove();})(<?php echo $payload ? $payload : "{}"; ?>);
        </script>
        <?php
    }

    public static function should_render(): bool {
        if ( ! RuntimeContext::is_public_dom_context() ) {
            return false;
        }

        if ( Settings::bool( "breadcrumbs_hide_home" ) && ( is_front_page() || is_home() ) ) {
            return false;
        }

        if ( is_category() || is_tag() ) {
            $term = get_queried_object();
            return $term instanceof \WP_Term && ! Settings::bool( "breadcrumbs_hide_term_archives" );
        }

        if ( ! is_singular() ) {
            return false;
        }

        $post = get_post();
        if ( ! $post instanceof \WP_Post ) {
            return false;
        }

        $post_type = get_post_type( $post );
        if ( $post_type && in_array( $post_type, Settings::array( "breadcrumbs_disabled_post_types" ), true ) ) {
            return false;
        }

        return ! in_array( (int) $post->ID, self::disabled_object_ids(), true );
    }

    public static function markup( string $style = "" ): string {
        $style = ArticleStyles::normalize_breadcrumb_style( $style );
        $crumbs = self::rank_math_markup();
        if ( "" === trim( $crumbs ) ) {
            $crumbs = self::fallback_markup();
        }
        if ( "" === trim( $crumbs ) ) {
            return "";
        }

        $vars = ArticleStyles::breadcrumb_var_values();
        $classes = "smpi-breadcrumbs smpi-" . $style;
        $style_attr = "--smpi-bc-accent:" . $vars["accent"] . ";--smpi-bc-tint:" . $vars["tint"] . ";--smpi-bc-background:" . $vars["background"] . ";--smpi-bc-font-size:" . $vars["size"];
        $title = self::current_title();
        $title_html = in_array( $style, [ "bc-b1", "bc-b5" ], true ) && "" !== $title ? "<div class=\"pt\">" . esc_html( $title ) . "</div>" : "";
        $content = "bc-b5" === $style ? $crumbs . $title_html : $title_html . $crumbs;

        return "<div class=\"" . esc_attr( $classes ) . "\" style=\"" . esc_attr( $style_attr ) . "\" data-smpi-breadcrumbs data-smpi-breadcrumbs-style=\"" . esc_attr( $style ) . "\">" . self::safe_markup( $content ) . "</div>";
    }

    public static function integrity_report(): array {
        $post = get_posts( [ "post_type" => "any", "post_status" => "publish", "posts_per_page" => 1 ] );
        $sample = $post[0] ?? null;
        return [
            "enabled" => Settings::bool( "breadcrumbs_enabled" ),
            "style" => ArticleStyles::normalize_breadcrumb_style( (string) Settings::get( "breadcrumbs_style", "bc-b2" ) ),
            "rank_math_active" => self::rank_math_available(),
            "hide_home" => Settings::bool( "breadcrumbs_hide_home" ),
            "hide_term_archives" => Settings::bool( "breadcrumbs_hide_term_archives" ),
            "disabled_post_types" => Settings::array( "breadcrumbs_disabled_post_types" ),
            "disabled_object_count" => count( self::disabled_object_ids() ),
            "shortcode" => "[" . self::SHORTCODE . "]",
            "sample_url" => $sample instanceof \WP_Post ? get_permalink( $sample ) : "",
        ];
    }

    public static function custom_css(): string {
        $result = self::validate_custom_css( (string) Settings::get( self::CSS_SETTING, "" ) );
        return ! empty( $result["valid"] ) ? (string) $result["css"] : "";
    }

    public static function validate_custom_css( string $css ): array {
        $css = trim( str_replace( [ chr( 13 ), chr( 0 ) ], "", $css ) );
        if ( "" === $css ) {
            return [ "valid" => true, "css" => "", "message" => "" ];
        }
        if ( strlen( $css ) > 20000 ) {
            return [ "valid" => false, "css" => "", "message" => "Breadcrumb CSS must be 20,000 characters or fewer." ];
        }
        if ( preg_match( '~<|@(?:import|charset|namespace)\b|javascript\s*:|expression\s*\(|behavior\s*:|-moz-binding\s*:~i', $css ) ) {
            return [ "valid" => false, "css" => "", "message" => "The CSS contains a blocked directive or unsafe value." ];
        }
        if ( substr_count( $css, "{" ) !== substr_count( $css, "}" ) ) {
            return [ "valid" => false, "css" => "", "message" => "The CSS has unbalanced braces." ];
        }

        $scan = preg_replace( '~/\*.*?\*/~s', "", $css );
        preg_match_all( '~(?:^|(?<=[{}]))\s*([^{}]+?)\s*\{~s', (string) $scan, $matches );
        foreach ( (array) ( $matches[1] ?? [] ) as $prelude ) {
            $prelude = trim( (string) $prelude );
            if ( "" === $prelude ) {
                continue;
            }
            if ( "@" === $prelude[0] ) {
                if ( ! preg_match( '~^@(media|supports|container|layer)\b~i', $prelude ) ) {
                    return [ "valid" => false, "css" => "", "message" => "Only media, supports, container, and layer wrappers are allowed." ];
                }
                continue;
            }
            foreach ( preg_split( '~,(?![^()]*\))~', $prelude ) ?: [] as $selector ) {
                if ( ! str_contains( trim( (string) $selector ), self::CSS_SCOPE_MARKER ) ) {
                    return [ "valid" => false, "css" => "", "message" => "Every selector must include " . self::CSS_SCOPE_MARKER . "." ];
                }
            }
        }

        return [ "valid" => true, "css" => $css, "message" => "" ];
    }

    private static function rank_math_markup(): string {
        if ( is_category() || is_tag() ) {
            return "";
        }
        if ( function_exists( "rank_math_get_breadcrumbs" ) ) {
            try {
                return (string) rank_math_get_breadcrumbs();
            } catch ( \Throwable $exception ) {
                return "";
            }
        }
        if ( shortcode_exists( "rank_math_breadcrumb" ) ) {
            return (string) do_shortcode( "[rank_math_breadcrumb]" );
        }
        if ( function_exists( "rank_math_the_breadcrumbs" ) ) {
            ob_start();
            rank_math_the_breadcrumbs();
            return (string) ob_get_clean();
        }
        return "";
    }

    private static function fallback_markup(): string {
        $items = [];
        $items[] = [ "url" => home_url( "/" ), "label" => __( "Home", "smp-publication-integration" ) ];
        $term = get_queried_object();
        if ( $term instanceof \WP_Term && ( is_category() || is_tag() ) ) {
            $term_link = get_term_link( $term );
            $items[] = [ "url" => is_wp_error( $term_link ) ? "" : $term_link, "label" => $term->name ];
            return self::items_markup( $items );
        }

        $post = get_post();
        if ( $post instanceof \WP_Post ) {
            if ( "post" === get_post_type( $post ) ) {
                $categories = get_the_category( $post->ID );
                if ( ! empty( $categories ) ) {
                    $category_link = get_category_link( $categories[0] );
                    $items[] = [ "url" => is_wp_error( $category_link ) ? "" : $category_link, "label" => $categories[0]->name ];
                }
            } else {
                $object = get_post_type_object( get_post_type( $post ) );
                if ( $object && ! empty( $object->labels->name ) ) {
                    $items[] = [ "url" => get_post_type_archive_link( get_post_type( $post ) ) ?: "", "label" => $object->labels->name ];
                }
            }
        }
        $last = self::current_title();
        if ( "" !== $last ) {
            $items[] = [ "url" => "", "label" => $last ];
        }
        return self::items_markup( $items );
    }

    private static function items_markup( array $items ): string {
        if ( count( $items ) < 2 ) {
            return "";
        }
        $html = "<nav aria-label=\"breadcrumbs\" class=\"rank-math-breadcrumb\"><p>";
        foreach ( $items as $index => $item ) {
            if ( $index > 0 ) {
                $html .= "<span class=\"separator\"> - </span>";
            }
            if ( $index === count( $items ) - 1 || "" === $item["url"] ) {
                $html .= "<span class=\"last\">" . esc_html( $item["label"] ) . "</span>";
            } else {
                $html .= "<a href=\"" . esc_url( $item["url"] ) . "\">" . esc_html( $item["label"] ) . "</a>";
            }
        }
        return $html . "</p></nav>";
    }

    private static function current_title(): string {
        $term = get_queried_object();
        if ( $term instanceof \WP_Term && ( is_category() || is_tag() ) ) {
            return wp_strip_all_tags( single_term_title( "", false ) );
        }

        $post = get_post();
        return $post instanceof \WP_Post ? wp_strip_all_tags( get_the_title( $post ) ) : "";
    }

    private static function safe_markup( string $html ): string {
        $allowed = wp_kses_allowed_html( "post" );
        $allowed["nav"] = [ "class" => true, "aria-label" => true ];
        $allowed["p"] = [ "class" => true ];
        $allowed["span"] = [ "class" => true ];
        $allowed["div"] = [ "class" => true, "role" => true, "aria-level" => true ];
        return wp_kses( $html, $allowed );
    }

    private static function disabled_object_ids(): array {
        $value = Fields::option( "breadcrumb_disabled_objects", [] );
        if ( ! is_array( $value ) ) {
            $value = [ $value ];
        }
        $ids = [];
        foreach ( $value as $item ) {
            if ( $item instanceof \WP_Post ) {
                $ids[] = (int) $item->ID;
            } elseif ( is_array( $item ) && isset( $item["ID"] ) ) {
                $ids[] = (int) $item["ID"];
            } else {
                $ids[] = (int) $item;
            }
        }
        return array_values( array_filter( array_unique( $ids ) ) );
    }

    private static function rank_math_available(): bool {
        return function_exists( "rank_math_get_breadcrumbs" ) || function_exists( "rank_math_the_breadcrumbs" ) || shortcode_exists( "rank_math_breadcrumb" );
    }

    private static function header_selectors(): array {
        return [
            ".elementor-location-header",
            "[data-elementor-type=\"header\"]",
            "header.site-header",
            "header#masthead",
            "header",
        ];
    }
}
