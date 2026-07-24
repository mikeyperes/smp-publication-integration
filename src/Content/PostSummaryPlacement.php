<?php
namespace smp_publication_integration\Content;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class PostSummaryPlacement extends PostContentBlockPlacement {
    public const SETTING = "post_summary_placement";
    public const MANUAL = parent::MANUAL;
    public const ABOVE_CONTENT = parent::ABOVE_CONTENT;
    public const BELOW_CONTENT = parent::BELOW_CONTENT;

    public static function normalize( string $placement ): string {
        return self::normalize_placement( $placement, [ self::MANUAL, self::ABOVE_CONTENT, self::BELOW_CONTENT ] );
    }

    protected function setting_key(): string {
        return self::SETTING;
    }

    protected function enabled_key(): string {
        return "post_summary_acf_enabled";
    }

    protected function shortcode_tag(): string {
        return "smp_post_summary";
    }

    protected function acf_field(): string {
        return "post_summary";
    }

    protected function block_selector(): string {
        return ".smpi-post-summary";
    }

    protected function placement_attribute(): string {
        return "data-smpi-summary-placement";
    }

    protected function placement_class_prefix(): string {
        return "smpi-summary-placement--";
    }

    protected function script_id(): string {
        return "smpi-post-summary-placement";
    }

    protected function allowed_placements(): array {
        return [ self::MANUAL, self::ABOVE_CONTENT, self::BELOW_CONTENT ];
    }

    protected function render_block( int $post_id ): string {
        return trim( $this->shortcodes->render_post_summary( [ "post_id" => $post_id ] ) );
    }
}
