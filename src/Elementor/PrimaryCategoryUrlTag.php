<?php

namespace smp_publication_integration\Elementor;

use smp_publication_integration\Content\ElementorPrimaryCategory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Archive URL companion to the unchanged Primary Category label tag. */
final class PrimaryCategoryUrlTag extends \Elementor\Core\DynamicTags\Data_Tag {
    public function get_name(): string {
        return 'smpi-primary-category-url';
    }

    public function get_title(): string {
        return 'Primary Category URL';
    }

    public function get_group(): string {
        return ElementorPrimaryCategory::TAG_GROUP;
    }

    public function get_categories(): array {
        return [ 'url' ];
    }

    public function get_value( array $options = [] ): string {
        $term = ElementorPrimaryCategory::term();
        if ( ! $term ) {
            return '';
        }
        $url = get_term_link( $term );
        return is_wp_error( $url ) ? '' : esc_url_raw( $url );
    }
}
