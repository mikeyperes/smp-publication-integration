<?php

namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class ReadingProgress {
    public const ENABLED_SETTING = "reading_progress_enabled";
    public const STYLE_SETTING = "reading_progress_style";
    public const COLOR_SETTING = "reading_progress_color";
    public const DEFAULT_STYLE = "thin";
    public const DEFAULT_COLOR = "#00ff41";

    private bool $markup_rendered = false;

    public function register(): void {
        add_action( "wp_head", [ $this, "print_styles" ], 31 );
        add_action( "wp_body_open", [ $this, "print_markup" ], 1 );
        add_action( "wp_footer", [ $this, "print_script" ], 31 );
    }

    public static function designs(): array {
        return [
            "thin" => [
                "label" => "Thin top line",
                "description" => "A 2px page-edge indicator matching the Hexa Cloud reference.",
            ],
            "track" => [
                "label" => "Full-width track",
                "description" => "A 5px bar with a quiet background rail behind the progress fill.",
            ],
            "glow" => [
                "label" => "Luminous edge",
                "description" => "A slim line with a stronger glow for dark or image-heavy headers.",
            ],
            "floating" => [
                "label" => "Floating capsule",
                "description" => "A rounded 6px rail inset from the viewport edges.",
            ],
            "segmented" => [
                "label" => "Segmented rail",
                "description" => "A 6px sequence of compact blocks that fills across the page.",
            ],
        ];
    }

    public static function style_keys(): array {
        return array_keys( self::designs() );
    }

    public static function normalize_style( string $style ): string {
        $style = sanitize_key( $style );
        return in_array( $style, self::style_keys(), true ) ? $style : self::DEFAULT_STYLE;
    }

    public static function preview_html( string $style ): string {
        $style = self::normalize_style( $style );
        return '<span class="smpi-reading-progress-preview smpi-reading-progress--' . esc_attr( $style ) . '" style="--smpi-reading-progress-scale:.68;--smpi-reading-progress-value:68%"><span class="smpi-reading-progress__track"><span class="smpi-reading-progress__fill"></span></span></span>';
    }

    public static function preview_css(): string {
        return ".smpi-reading-progress-preview{background:#f8fafc;box-sizing:border-box;display:block;min-height:34px;overflow:hidden;padding:16px 0;position:relative;width:100%}.smpi-reading-progress-preview *{box-sizing:border-box}.smpi-reading-progress-preview.smpi-reading-progress--floating .smpi-reading-progress__track{margin:0 12px;width:calc(100% - 24px)}" . self::shared_design_css();
    }

    public function print_styles(): void {
        if ( ! $this->should_render() ) {
            return;
        }

        echo '<style id="smpi-reading-progress-css">' . self::frontend_css() . '</style>';
    }

    public function print_markup(): void {
        if ( $this->markup_rendered || ! $this->should_render() ) {
            return;
        }

        $settings = Settings::all();
        $style = self::normalize_style( (string) ( $settings[ self::STYLE_SETTING ] ?? self::DEFAULT_STYLE ) );
        $color = sanitize_hex_color( (string) ( $settings[ self::COLOR_SETTING ] ?? self::DEFAULT_COLOR ) ) ?: self::DEFAULT_COLOR;
        $inline_style = "--smpi-reading-progress-scale:0;--smpi-reading-progress-value:0%;" . self::color_variables( $color );

        echo '<div id="smpi-reading-progress" class="smpi-reading-progress smpi-reading-progress--' . esc_attr( $style ) . '" role="progressbar" aria-label="Article reading progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="' . esc_attr( $inline_style ) . '"><span class="smpi-reading-progress__track"><span class="smpi-reading-progress__fill"></span></span></div>';
        $this->markup_rendered = true;
    }

    public function print_script(): void {
        if ( ! $this->should_render() ) {
            return;
        }
        if ( ! $this->markup_rendered ) {
            $this->print_markup();
        }
        ?>
        <script id="smpi-reading-progress-script" data-no-optimize="1" data-cfasync="false">
        (function(){
            var indicator=document.getElementById("smpi-reading-progress");
            if(!indicator||indicator.dataset.smpiBound==="1"){return;}
            indicator.dataset.smpiBound="1";
            var root=document.documentElement;
            var ticking=false;
            var lastValue=-1;
            var resizeObserver=null;
            function update(){
                ticking=false;
                var bodyHeight=document.body?document.body.scrollHeight:0;
                var pageHeight=Math.max(root.scrollHeight,bodyHeight);
                var maximum=Math.max(0,pageHeight-window.innerHeight);
                var offset=Math.max(0,window.scrollY||window.pageYOffset||root.scrollTop||0);
                var value=maximum>0?Math.max(0,Math.min(100,(offset/maximum)*100)):0;
                if(Math.abs(value-lastValue)<0.01){return;}
                lastValue=value;
                indicator.style.setProperty("--smpi-reading-progress-scale",(value/100).toFixed(5));
                indicator.style.setProperty("--smpi-reading-progress-value",value.toFixed(3)+"%");
                indicator.setAttribute("aria-valuenow",String(Math.round(value)));
                indicator.classList.toggle("is-complete",value>=99.9);
            }
            function requestUpdate(){
                if(ticking){return;}
                ticking=true;
                window.requestAnimationFrame(update);
            }
            window.addEventListener("scroll",requestUpdate,{passive:true});
            window.addEventListener("resize",requestUpdate,{passive:true});
            window.addEventListener("pageshow",requestUpdate,{passive:true});
            window.addEventListener("load",requestUpdate,{once:true,passive:true});
            if("ResizeObserver" in window&&document.body){resizeObserver=new ResizeObserver(requestUpdate);resizeObserver.observe(document.body);}
            if(document.fonts&&document.fonts.ready){document.fonts.ready.then(requestUpdate);}
            update();
        })();
        </script>
        <?php
    }

    private function should_render(): bool {
        return RuntimeContext::is_public_dom_context()
            && Settings::bool( self::ENABLED_SETTING )
            && is_singular( "post" );
    }

    private static function frontend_css(): string {
        return ".smpi-reading-progress{box-sizing:border-box;left:0;pointer-events:none;position:fixed;right:0;top:0;width:auto;z-index:2147483000}.smpi-reading-progress *{box-sizing:border-box}.smpi-reading-progress--floating{left:12px;right:12px;top:8px}@media(max-width:600px){.smpi-reading-progress--floating{left:8px;right:8px;top:6px}}@media(prefers-reduced-motion:reduce){.smpi-reading-progress__fill{transition:none!important}}" . self::shared_design_css();
    }

    private static function color_variables( string $color ): string {
        return "--smpi-reading-progress-color:" . $color
            . ";--smpi-reading-progress-soft:" . self::rgba( $color, 0.18 )
            . ";--smpi-reading-progress-glow:" . self::rgba( $color, 0.55 );
    }

    private static function rgba( string $color, float $alpha ): string {
        $hex = ltrim( strtolower( $color ), "#" );
        if ( ! preg_match( "/^[0-9a-f]{6}$/", $hex ) ) {
            $hex = ltrim( self::DEFAULT_COLOR, "#" );
        }
        return "rgba(" . hexdec( substr( $hex, 0, 2 ) ) . "," . hexdec( substr( $hex, 2, 2 ) ) . "," . hexdec( substr( $hex, 4, 2 ) ) . "," . $alpha . ")";
    }

    private static function shared_design_css(): string {
        return ".smpi-reading-progress__track,.smpi-reading-progress__fill{display:block;width:100%}.smpi-reading-progress__track{overflow:hidden;position:relative}.smpi-reading-progress__fill{background:var(--smpi-reading-progress-color,#00ff41);height:100%;transform:scaleX(var(--smpi-reading-progress-scale,0));transform-origin:0 50%;transition:transform .08s linear}.smpi-reading-progress--thin .smpi-reading-progress__track{background:transparent;height:2px}.smpi-reading-progress--thin .smpi-reading-progress__fill{box-shadow:0 0 12px var(--smpi-reading-progress-glow,rgba(0,255,65,.55))}.smpi-reading-progress--track .smpi-reading-progress__track{background:var(--smpi-reading-progress-soft,rgba(37,99,235,.18));height:5px}.smpi-reading-progress--glow .smpi-reading-progress__track{background:transparent;height:3px;overflow:visible}.smpi-reading-progress--glow .smpi-reading-progress__fill{box-shadow:0 0 5px var(--smpi-reading-progress-color,#00ff41),0 0 18px var(--smpi-reading-progress-glow,rgba(0,255,65,.55))}.smpi-reading-progress--floating .smpi-reading-progress__track{background:var(--smpi-reading-progress-soft,rgba(37,99,235,.18));border:1px solid var(--smpi-reading-progress-soft,rgba(37,99,235,.18));border-radius:999px;box-shadow:0 2px 12px rgba(15,23,42,.18);height:6px}.smpi-reading-progress--floating .smpi-reading-progress__fill{border-radius:999px}.smpi-reading-progress--segmented .smpi-reading-progress__track{background:repeating-linear-gradient(90deg,var(--smpi-reading-progress-soft,rgba(37,99,235,.18)) 0 24px,transparent 24px 29px);height:6px;overflow:visible}.smpi-reading-progress--segmented .smpi-reading-progress__fill{-webkit-clip-path:inset(0 calc(100% - var(--smpi-reading-progress-value,0%)) 0 0);background:repeating-linear-gradient(90deg,var(--smpi-reading-progress-color,#00ff41) 0 24px,transparent 24px 29px);clip-path:inset(0 calc(100% - var(--smpi-reading-progress-value,0%)) 0 0);transform:none;transition:clip-path .08s linear}";
    }
}
