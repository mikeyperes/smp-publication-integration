<?php

namespace Hexa\PluginCore\WpAdminComponents;

use Hexa\PluginCore\BrandColors\FontFamilyProvider;

final class FontFamilyControl {
    public static function render( array $args ): string {
        $key = self::clean_key( (string) ( $args["key"] ?? "font_family" ) );
        $label = (string) ( $args["label"] ?? "Font" );
        $description = (string) ( $args["description"] ?? "" );
        $include_template = array_key_exists( "include_template", $args ) ? ! empty( $args["include_template"] ) : true;
        $options = FontFamilyProvider::options( $include_template );
        $fallback = $include_template ? FontFamilyProvider::TEMPLATE : FontFamilyProvider::NATIVE_PRIMARY;
        $value = FontFamilyProvider::normalize_selection( (string) ( $args["value"] ?? $fallback ), $fallback );
        $control_class = trim( "hpc-font-family-control " . (string) ( $args["control_class"] ?? "" ) );
        $select_class = trim( "hpc-font-family-select " . (string) ( $args["select_class"] ?? "" ) );
        $status_html = (string) ( $args["status_html"] ?? "" );
        $show_current = ! empty( $args["show_current"] );
        $name = (string) ( $args["name"] ?? $key );
        $id = self::clean_id( (string) ( $args["id"] ?? "hpc-font-family-" . $key ) );
        $disabled = ! empty( $args["disabled"] ) ? " disabled" : "";
        $groups = [];
        foreach ( $options as $option ) {
            $groups[ $option["group"] ][] = $option;
        }
        $selected = $options[ $value ] ?? reset( $options );
        $value = (string) $selected["value"];

        ob_start();
        echo self::assets_once(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <div
            class="<?php echo esc_attr( $control_class ); ?>"
            data-hpc-font-family-control
            data-key="<?php echo esc_attr( $key ); ?>"
            data-hpc-font-family-value="<?php echo esc_attr( (string) $selected["value"] ); ?>"
            data-hpc-font-family-css="<?php echo esc_attr( (string) $selected["css"] ); ?>"
        >
            <div class="hpc-font-family-head">
                <h3><?php echo esc_html( $label ); ?></h3>
                <?php if ( "" !== $description ) : ?>
                    <p><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>
            </div>
            <div class="hpc-font-family-row">
                <label class="hpc-font-family-field" for="<?php echo esc_attr( $id ); ?>">
                    <span>Font source</span>
                    <select
                        id="<?php echo esc_attr( $id ); ?>"
                        name="<?php echo esc_attr( $name ); ?>"
                        class="<?php echo esc_attr( $select_class ); ?>"
                        data-key="<?php echo esc_attr( $key ); ?>"
                        data-hpc-font-family-select
                        <?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    >
                        <?php foreach ( $groups as $group => $items ) : ?>
                            <optgroup label="<?php echo esc_attr( (string) $group ); ?>">
                                <?php foreach ( $items as $option ) : ?>
                                    <?php $option_label = self::option_label( $option ); ?>
                                    <option
                                        value="<?php echo esc_attr( (string) $option["value"] ); ?>"
                                        data-family="<?php echo esc_attr( (string) $option["family"] ); ?>"
                                        data-source="<?php echo esc_attr( (string) $option["source"] ); ?>"
                                        data-source-id="<?php echo esc_attr( (string) $option["source_id"] ); ?>"
                                        data-css="<?php echo esc_attr( (string) $option["css"] ); ?>"
                                        data-summary="<?php echo esc_attr( $option_label ); ?>"
                                        <?php echo (string) $option["value"] === $value ? " selected" : ""; ?>
                                        <?php echo empty( $option["available"] ) ? " disabled" : ""; ?>
                                    ><?php echo esc_html( $option_label ); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ( $show_current ) : ?>
                    <span class="hpc-font-family-current" data-hpc-font-family-current aria-live="polite"><?php echo esc_html( self::option_label( $selected ) ); ?></span>
                <?php endif; ?>
                <?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function option_label( array $option ): string {
        $label = (string) ( $option["label"] ?? "Font" );
        $family = (string) ( $option["family"] ?? "" );
        if ( empty( $option["available"] ) ) {
            return $label . " - unavailable";
        }
        if ( "" !== $family && false === stripos( $label, $family ) ) {
            return $label . " - " . $family;
        }
        return $label;
    }

    private static function assets_once(): string {
        static $rendered = false;
        if ( $rendered ) {
            return "";
        }
        $rendered = true;

        return <<<'HTML'
<style>.hpc-font-family-control{display:grid;gap:10px;min-width:0}.hpc-font-family-head h3{font-size:13px;letter-spacing:0;margin:0 0 5px;text-transform:uppercase}.hpc-font-family-head p{color:#64748b;margin:0}.hpc-font-family-row{align-items:end;display:flex;flex-wrap:wrap;gap:10px}.hpc-font-family-field{display:grid;gap:5px;min-width:min(100%,320px)}.hpc-font-family-field>span{color:#475569;font-size:11px;font-weight:800;letter-spacing:0;text-transform:uppercase}.hpc-font-family-select{background:#fff;border:1px solid #a9b4c3;border-radius:6px;min-height:40px;min-width:280px;padding:7px 34px 7px 10px;width:100%}.hpc-font-family-current{align-items:center;background:#eef2f7;border-radius:6px;color:#314056;display:inline-flex;font-size:12px;min-height:40px;padding:8px 10px}@media(max-width:680px){.hpc-font-family-field,.hpc-font-family-select{min-width:0;width:100%}.hpc-font-family-current{width:100%}}</style>
<script>(function(){if(window.hexaFontFamilyControlReady)return;window.hexaFontFamilyControlReady=true;document.addEventListener("change",function(event){var select=event.target.closest("[data-hpc-font-family-select]");if(!select)return;var control=select.closest("[data-hpc-font-family-control]");var option=select.options[select.selectedIndex];if(!control||!option)return;control.setAttribute("data-hpc-font-family-value",option.value||"");control.setAttribute("data-hpc-font-family-css",option.getAttribute("data-css")||"");var current=control.querySelector("[data-hpc-font-family-current]");if(current)current.textContent=option.getAttribute("data-summary")||option.textContent||"";});})();</script>
HTML;
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) {
            return sanitize_key( $value );
        }
        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "font_family";
    }

    private static function clean_id( string $value ): string {
        if ( function_exists( "sanitize_html_class" ) ) {
            return sanitize_html_class( $value );
        }
        return preg_replace( "/[^a-zA-Z0-9_\-]/", "-", $value ) ?: "hpc-font-family";
    }
}
