<?php
namespace smp_publication_integration\Content;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class PostFaqPlacement extends PostContentBlockPlacement {
    public const SETTING = "post_faqs_placement";
    public const MANUAL = parent::MANUAL;
    public const BELOW_CONTENT = parent::BELOW_CONTENT;
    public const BELOW_AUTHOR = parent::BELOW_AUTHOR;

    public static function normalize( string $placement ): string {
        return self::normalize_placement( $placement, [ self::MANUAL, self::BELOW_CONTENT, self::BELOW_AUTHOR ] );
    }

    protected function setting_key(): string {
        return self::SETTING;
    }

    protected function enabled_key(): string {
        return "post_faqs_acf_enabled";
    }

    protected function shortcode_tag(): string {
        return "smp_post_faqs";
    }

    protected function acf_field(): string {
        return "post_faq_items";
    }

    protected function block_selector(): string {
        return ".smpi-post-faqs";
    }

    protected function placement_attribute(): string {
        return "data-smpi-faq-placement";
    }

    protected function placement_class_prefix(): string {
        return "smpi-faq-placement--";
    }

    protected function script_id(): string {
        return "smpi-post-faq-placement";
    }

    protected function allowed_placements(): array {
        return [ self::MANUAL, self::BELOW_CONTENT, self::BELOW_AUTHOR ];
    }

    protected function render_block( int $post_id ): string {
        return trim( $this->shortcodes->render_post_faqs( [ "post_id" => $post_id ] ) );
    }
}
