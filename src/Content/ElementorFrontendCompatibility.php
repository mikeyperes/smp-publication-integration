<?php

namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class ElementorFrontendCompatibility {
    public function register(): void {
        add_action( "wp_head", [ $this, "print_styles" ], 33 );
    }

    public function print_styles(): void {
        if ( ! RuntimeContext::is_public_dom_context() ) {
            return;
        }

        $css = self::navigation_css();
        if ( Settings::bool( "table_of_contents_enabled" ) ) {
            $css .= self::table_of_contents_loading_css();
        }

        echo '<style id="smpi-elementor-frontend-compatibility-css">' . $css . '</style>';
    }

    public static function navigation_css(): string {
        return ".elementor-sticky__spacer{pointer-events:none!important;visibility:hidden!important}"
            . ".elementor-widget-nav-menu a>.sub-arrow~.sub-arrow{display:none!important}";
    }

    public static function table_of_contents_loading_css(): string {
        return ".elementor-widget-table-of-contents .elementor-toc__body{max-height:none!important;overflow:visible!important}"
            . ".elementor-widget-table-of-contents .elementor-toc__spinner-container{box-sizing:border-box;height:50px;min-height:50px;overflow:hidden;padding:8px 0;text-align:left}"
            . ".elementor-widget-table-of-contents .elementor-toc__spinner{display:none!important}"
            . ".elementor-widget-table-of-contents .elementor-toc__spinner-container:before{background:currentColor;border-radius:2px;box-shadow:0 14px 0 currentColor,0 28px 0 currentColor;content:\"\";display:block;height:5px;margin:2px 0;opacity:.12;width:78%}";
    }
}
