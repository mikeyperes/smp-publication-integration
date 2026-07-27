<?php

namespace smp_publication_integration\Elementor;

use smp_publication_integration\Content\ElementorPrimaryCategory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PrimaryCategoryTag extends \Elementor\Core\DynamicTags\Tag {
    public function get_name(): string {
        return ElementorPrimaryCategory::TAG_NAME;
    }

    public function get_title(): string {
        return 'Primary Category';
    }

    public function get_group(): string {
        return ElementorPrimaryCategory::TAG_GROUP;
    }

    public function get_categories(): array {
        return [ 'text' ];
    }

    public function render(): void {
        $term = ElementorPrimaryCategory::term();
        if ( ! $term ) {
            return;
        }

        echo '<span class="smpi-primary-category">' . esc_html( (string) $term->name ) . '</span>';
    }
}
