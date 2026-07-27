<?php

namespace smp_publication_integration\Design;

final class TemplateBackground {
    public const TEMPLATE = "template";
    public const NONE = "none";
    public const CUSTOM = "custom";

    public static function options(): array {
        return [
            self::TEMPLATE => [
                "label" => "Template background",
                "description" => "Keep the background supplied by the selected design.",
            ],
            self::NONE => [
                "label" => "No background",
                "description" => "Use a transparent background.",
            ],
            self::CUSTOM => [
                "label" => "Custom background",
                "description" => "Apply the color selected below.",
            ],
        ];
    }

    public static function normalize_mode( string $mode ): string {
        $mode = sanitize_key( $mode );
        return array_key_exists( $mode, self::options() ) ? $mode : self::TEMPLATE;
    }

    public static function css_value( string $mode, string $color ): string {
        $mode = self::normalize_mode( $mode );
        if ( self::NONE === $mode ) {
            return "transparent";
        }
        if ( self::CUSTOM !== $mode ) {
            return "";
        }
        return sanitize_hex_color( $color ) ?: "#ffffff";
    }
}
