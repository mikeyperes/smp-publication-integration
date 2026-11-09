<?php
namespace smp_publication_integration\Elementor;

use smp_publication_integration\Content\ElementorPrimaryCategory;
use smp_publication_integration\Content\PostOpeningParagraph;

defined( 'ABSPATH' ) || exit;

final class PostOpeningParagraphTag extends \Elementor\Core\DynamicTags\Tag {
    public function get_name(): string { return PostOpeningParagraph::NAME; }
    public function get_title(): string { return 'Post Opening Paragraph'; }
    public function get_group(): string { return ElementorPrimaryCategory::TAG_GROUP; }
    public function get_categories(): array { return [ 'text' ]; }

    protected function register_controls(): void {
        $this->add_control( 'word_limit', [
            'label' => 'Word limit',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'min' => 0,
            'step' => 1,
            'default' => 0,
            'description' => '0 shows the complete opening paragraph.',
        ] );
    }

    public function render(): void {
        echo esc_html( PostOpeningParagraph::text( 0, max( 0, (int) $this->get_settings( 'word_limit' ) ) ) );
    }
}
