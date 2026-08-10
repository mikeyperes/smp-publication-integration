<?php

namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Applies one site-wide loading mode to Elementor's current-query post loop
 * on author archives while leaving the Elementor template as the markup owner.
 */
final class AuthorArchiveLoading {
    public const MODE_PAGINATION      = 'pagination';
    public const MODE_INFINITE_SCROLL = 'infinite_scroll';
    public const MODE_LOAD_MORE       = 'load_more';

    public const STYLE_NONE             = 'none';
    public const STYLE_EDITORIAL_TEXT   = 'editorial_text';
    public const STYLE_ACCENT_UNDERLINE = 'accent_underline';
    public const STYLE_HAIRLINE_OUTLINE = 'hairline_outline';
    public const STYLE_SOLID_ACCENT     = 'solid_accent';
    public const STYLE_SOFT_PILL        = 'soft_pill';

    private const SUPPORTED_WIDGETS = [ 'loop-grid', 'posts', 'archive-posts' ];

    public function register(): void {
        add_action( 'elementor/frontend/widget/before_render', [ $this, 'configure_widget' ], 5 );
        add_action( 'wp_head', [ $this, 'print_styles' ], 34 );
    }

    /**
     * Keep Elementor Pro responsible for querying, AJAX, history, accessibility,
     * and pagination markup. SMP changes only the active native control values.
     */
    public function configure_widget( $widget ): void {
        if ( ! self::enabled_for_request()
            || ! is_object( $widget )
            || ! method_exists( $widget, 'get_name' )
            || ! method_exists( $widget, 'get_settings' )
            || ! method_exists( $widget, 'set_settings' )
            || ! method_exists( $widget, 'add_render_attribute' )
        ) {
            return;
        }

        $name = (string) $widget->get_name();
        if ( ! in_array( $name, self::SUPPORTED_WIDGETS, true ) ) {
            return;
        }

        $settings = $widget->get_settings();
        if ( ! is_array( $settings ) || ! self::uses_current_archive_query( $name, $settings ) ) {
            return;
        }

        $mode = self::mode();
        $widget->set_settings( 'pagination_type', self::elementor_pagination_type( $mode ) );

        if ( self::MODE_PAGINATION === $mode ) {
            $widget->set_settings( 'pagination_load_type', 'page_reload' );
            $widget->set_settings( 'pagination_prev_label', 'Previous' );
            $widget->set_settings( 'pagination_next_label', 'Next' );
        } elseif ( self::MODE_LOAD_MORE === $mode ) {
            $widget->set_settings( 'text', 'Load more' );
        }

        // Settings may have been parsed before this hook. Reset only Elementor's
        // render caches, then add the stable wrapper hooks used by the templates.
        if ( method_exists( $widget, 'reset_render_state' ) ) {
            $widget->reset_render_state();
        }

        $style = self::style();
        $widget->add_render_attribute(
            '_wrapper',
            'class',
            [
                'smpi-author-loading',
                'smpi-author-loading--mode-' . sanitize_html_class( $mode ),
                'smpi-author-loading--style-' . sanitize_html_class( $style ),
                self::STYLE_NONE === $style ? 'smpi-author-loading--unstyled' : 'smpi-author-loading--styled',
            ]
        );
        $widget->add_render_attribute( '_wrapper', 'data-smpi-author-loading', $mode );
    }

    public function print_styles(): void {
        if ( ! self::enabled_for_request() || self::STYLE_NONE === self::style() ) {
            return;
        }

        echo '<style id="smpi-author-archive-loading-css">' . self::frontend_css( self::style() ) . '</style>';
    }

    public static function mode(): string {
        $mode = sanitize_key( (string) Settings::get( 'author_archive_loading_mode', self::MODE_PAGINATION ) );
        return in_array( $mode, self::modes(), true ) ? $mode : self::MODE_PAGINATION;
    }

    public static function style(): string {
        $style = sanitize_key( (string) Settings::get( 'author_archive_loading_style', self::STYLE_NONE ) );
        return in_array( $style, self::styles(), true ) ? $style : self::STYLE_NONE;
    }

    /** @return array<int,string> */
    public static function modes(): array {
        return [ self::MODE_PAGINATION, self::MODE_INFINITE_SCROLL, self::MODE_LOAD_MORE ];
    }

    /** @return array<int,string> */
    public static function styles(): array {
        return [ self::STYLE_NONE, self::STYLE_EDITORIAL_TEXT, self::STYLE_ACCENT_UNDERLINE, self::STYLE_HAIRLINE_OUTLINE, self::STYLE_SOLID_ACCENT, self::STYLE_SOFT_PILL ];
    }

    public static function elementor_pagination_type( string $mode ): string {
        return match ( $mode ) {
            self::MODE_INFINITE_SCROLL => 'load_more_infinite_scroll',
            self::MODE_LOAD_MORE       => 'load_more_on_click',
            default                    => 'numbers_and_prev_next',
        };
    }

    public static function preview_html( string $style ): string {
        $style = in_array( $style, self::styles(), true ) ? $style : self::STYLE_NONE;
        $label = self::STYLE_NONE === $style ? 'No plugin style' : ucwords( str_replace( '_', ' ', $style ) );
        return '<span class="smpi-author-load-preview smpi-author-load-preview--' . esc_attr( $style ) . '">'
            . '<span class="smpi-author-load-preview__page">1</span>'
            . '<span class="smpi-author-load-preview__page is-current">2</span>'
            . '<span class="smpi-author-load-preview__page">3</span>'
            . '<span class="smpi-author-load-preview__action">Load more</span>'
            . '</span><code>.smpi-author-loading--style-' . esc_html( $style ) . '</code>'
            . '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
    }

    public static function preview_css(): string {
        return '.smpi-author-load-preview{align-items:center;display:flex;flex-wrap:wrap;gap:7px;min-height:46px}'
            . '.smpi-author-load-preview__page,.smpi-author-load-preview__action{align-items:center;box-sizing:border-box;color:#374151;display:inline-flex;font-size:12px;font-weight:700;justify-content:center;line-height:1;min-height:30px;padding:8px 10px}'
            . '.smpi-author-load-preview__page{min-width:30px}'
            . '.smpi-author-load-preview--none .smpi-author-load-preview__page,.smpi-author-load-preview--none .smpi-author-load-preview__action{padding-inline:3px;text-decoration:underline;text-underline-offset:3px}'
            . '.smpi-author-load-preview--editorial_text .smpi-author-load-preview__page,.smpi-author-load-preview--editorial_text .smpi-author-load-preview__action{padding-inline:5px}'
            . '.smpi-author-load-preview--editorial_text .is-current{color:#923131;text-decoration:underline;text-underline-offset:4px}'
            . '.smpi-author-load-preview--accent_underline .smpi-author-load-preview__page,.smpi-author-load-preview--accent_underline .smpi-author-load-preview__action{border-bottom:2px solid transparent;padding-inline:5px}'
            . '.smpi-author-load-preview--accent_underline .is-current,.smpi-author-load-preview--accent_underline .smpi-author-load-preview__action{border-color:#923131;color:#923131}'
            . '.smpi-author-load-preview--hairline_outline .smpi-author-load-preview__page,.smpi-author-load-preview--hairline_outline .smpi-author-load-preview__action{border:1px solid #e5e1dd;border-radius:2px}'
            . '.smpi-author-load-preview--hairline_outline .is-current{background:#f5f5f5;border-color:#923131;color:#923131}'
            . '.smpi-author-load-preview--solid_accent .smpi-author-load-preview__page{border:1px solid transparent;border-radius:2px}'
            . '.smpi-author-load-preview--solid_accent .is-current,.smpi-author-load-preview--solid_accent .smpi-author-load-preview__action{background:#923131;border-radius:2px;color:#fff}'
            . '.smpi-author-load-preview--soft_pill .smpi-author-load-preview__page,.smpi-author-load-preview--soft_pill .smpi-author-load-preview__action{background:#f5f5f5;border:1px solid transparent;border-radius:999px}'
            . '.smpi-author-load-preview--soft_pill .is-current{border-color:#923131;color:#923131}'
            . '.smpi-author-load-preview+code{display:block;margin-top:8px;overflow-wrap:anywhere;white-space:normal}';
    }

    public static function frontend_css( string $style ): string {
        $style = in_array( $style, self::styles(), true ) ? $style : self::STYLE_NONE;
        if ( self::STYLE_NONE === $style ) {
            return '';
        }

        $scope = '.smpi-author-loading--style-' . $style;
        $pagination = $scope . '>.elementor-widget-container>.elementor-pagination';
        $page_links = $pagination . ' .page-numbers';
        $button = $scope . '>.elementor-widget-container>.elementor-button-wrapper>.elementor-button';
        $spinner = $scope . '>.elementor-widget-container>.e-load-more-spinner';
        $accent = 'var(--e-global-color-primary,#923131)';

        $css = $pagination . '{align-items:center;display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:34px}'
            . $page_links . '{align-items:center;box-sizing:border-box;display:inline-flex;justify-content:center;line-height:1;min-height:44px;min-width:44px;padding:12px;text-decoration:none;transition:background-color .18s ease,border-color .18s ease,color .18s ease,transform .18s ease}'
            . $button . '{box-sizing:border-box;font:inherit;font-weight:700;line-height:1.2;margin-top:34px;min-height:44px;padding:12px 18px;text-decoration:none;transition:background-color .18s ease,border-color .18s ease,color .18s ease,transform .18s ease}'
            . $button . ':focus-visible,' . $page_links . ':focus-visible{outline:2px solid ' . $accent . ';outline-offset:2px}'
            . $spinner . '{color:' . $accent . ';margin-top:28px}'
            . $scope . '>.elementor-widget-container>.e-load-more-message{font-size:13px;margin-top:16px;text-align:center}'
            . '@media(prefers-reduced-motion:reduce){' . $spinner . ' svg{animation:none!important}}';

        if ( self::STYLE_EDITORIAL_TEXT === $style ) {
            return $css
                . $page_links . ',' . $button . '{background:transparent;border:0;border-radius:0;color:inherit}'
                . $pagination . ' .page-numbers.current{color:' . $accent . ';font-weight:800;text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:5px}'
                . $button . ':hover,' . $page_links . ':hover{color:' . $accent . ';text-decoration:underline;text-underline-offset:5px}';
        }
        if ( self::STYLE_ACCENT_UNDERLINE === $style ) {
            return $css
                . $page_links . ',' . $button . '{background:transparent;border:0;border-bottom:2px solid transparent;border-radius:0;color:inherit}'
                . $pagination . ' .page-numbers.current,' . $button . '{border-color:' . $accent . ';color:' . $accent . '}'
                . $button . ':hover,' . $page_links . ':hover{border-color:' . $accent . ';color:' . $accent . '}';
        }
        if ( self::STYLE_HAIRLINE_OUTLINE === $style ) {
            return $css
                . $page_links . ',' . $button . '{background:transparent;border:1px solid #e5e1dd;border-radius:2px;box-shadow:none;color:inherit}'
                . $pagination . ' .page-numbers.current{background:#f5f5f5;border-color:' . $accent . ';color:' . $accent . ';font-weight:800}'
                . $button . ':hover,' . $page_links . ':hover{border-color:' . $accent . ';color:' . $accent . '}';
        }
        if ( self::STYLE_SOLID_ACCENT === $style ) {
            return $css
                . $page_links . '{background:transparent;border:1px solid transparent;border-radius:2px;color:inherit}'
                . $pagination . ' .page-numbers.current,' . $button . '{background:' . $accent . ';border:1px solid ' . $accent . ';border-radius:2px;box-shadow:none;color:#fff}'
                . $button . ':hover,' . $page_links . ':hover{background:#171717;border-color:#171717;color:#fff}';
        }

        return $css
            . $page_links . ',' . $button . '{background:#f5f5f5;border:1px solid transparent;border-radius:999px;box-shadow:none;color:#333}'
            . $pagination . ' .page-numbers.current{border-color:' . $accent . ';color:' . $accent . ';font-weight:800}'
            . $button . ':hover,' . $page_links . ':hover{border-color:' . $accent . ';color:' . $accent . '}';
    }

    private static function enabled_for_request(): bool {
        return function_exists( 'is_author' )
            && is_author()
            && Settings::bool( 'author_archive_loading_enabled' );
    }

    private static function uses_current_archive_query( string $name, array $settings ): bool {
        if ( 'archive-posts' === $name ) {
            return true;
        }

        foreach ( [ 'post_query_post_type', 'query_post_type', 'post_type' ] as $key ) {
            if ( 'current_query' === (string) ( $settings[ $key ] ?? '' ) ) {
                return true;
            }
        }

        return false;
    }
}
