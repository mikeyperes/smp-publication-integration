<?php
/** Run with wp eval-file tests/elementor-native-author-widget.php on a site with Elementor Pro and a verified author. No stored data changes. */
use smp_publication_integration\Content\MuckRackVerification;
use smp_publication_integration\Content\ElementorPrimaryCategory;
use smp_publication_integration\Elementor\PrimaryCategoryTag;
use smp_publication_integration\Elementor\PrimaryCategoryUrlTag;

if ( ! defined( 'ABSPATH' ) || ! class_exists( '\ElementorPro\Modules\ThemeElements\Widgets\Post_Info' ) ) {
    throw new RuntimeException( 'Requires WordPress, Elementor Pro and SMP Publication Integration.' );
}
$check = static function ( bool $ok, string $message ): void {
    if ( ! $ok ) {
        throw new RuntimeException( $message );
    }
    echo "PASS: {$message}\n";
};
$candidate = null;
foreach ( get_posts( [ 'numberposts' => 100, 'post_type' => 'post', 'post_status' => 'publish' ] ) as $post ) {
    if ( MuckRackVerification::author_verified( (int) $post->post_author ) && ElementorPrimaryCategory::term( (int) $post->ID ) ) {
        $candidate = $post;
        break;
    }
}
$check( $candidate instanceof WP_Post, 'An existing categorized post has a verified author.' );
$original_post = $GLOBALS['post'] ?? null;
$elementor = \Elementor\Plugin::$instance;
$widget_data = [
    'id' => 'smpinativeproof', 'elType' => 'widget', 'widgetType' => 'post-info',
    'settings' => [ 'icon_list' => [ [ '_id' => 'nativeauthor', 'type' => 'author', 'link' => 'yes', 'show_icon' => 'none', 'show_avatar' => 'yes' ] ] ],
];
try {
    foreach ( [ 'unbound', 'featured' ] as $binding ) {
        $GLOBALS['post'] = $candidate;
        setup_postdata( $candidate );
        $image = [ 'id' => 'smpiimageproof', 'elType' => 'widget', 'widgetType' => 'theme-post-featured-image', 'settings' => [] ];
        if ( 'featured' === $binding ) {
            $image['settings']['__dynamic__']['image'] = '[elementor-tag id="smpiproof" name="post-featured-image" settings="%5B%5D"]';
        }
        $image_widget = $elementor->elements_manager->create_element_instance( $image );
        ob_start();
        $image_widget->print_element();
        ob_end_clean();
        $widget = $elementor->elements_manager->create_element_instance( $widget_data );
        ob_start();
        $widget->print_element();
        $html = ob_get_clean();
        $check( str_contains( $html, esc_url( get_author_posts_url( (int) $candidate->post_author ) ) ), "{$binding}: native author link renders." );
        $check( 1 === substr_count( $html, 'class="smpi-muckrack-icon ' ), "{$binding}: exactly one verified badge renders without a widget document method." );
        $filtered = ( new MuckRackVerification() )->filter_elementor_author_links( $html, $widget );
        $check( $filtered === $html, "{$binding}: repeated decoration preserves the original badge." );
    }
    $term = ElementorPrimaryCategory::term();
    $label = new PrimaryCategoryTag();
    ob_start();
    $label->render();
    $label_html = ob_get_clean();
    $check( '<span class="smpi-primary-category">' . esc_html( $term->name ) . '</span>' === $label_html, 'Primary Category label HTML is unchanged.' );
    $url = new PrimaryCategoryUrlTag();
    $check( [ 'url' ] === $url->get_categories() && get_term_link( $term ) === $url->get_value(), 'Primary Category URL returns the matching archive URL.' );
    $tags = $elementor->dynamic_tags->get_tags();
    $check( isset( $tags['smpi-primary-category-url'] ), 'Primary Category URL is registered with Elementor.' );
    $heading = $elementor->elements_manager->create_element_instance( [
        'id' => 'smpilinkproof', 'elType' => 'widget', 'widgetType' => 'heading',
        'settings' => [ 'title' => $term->name, '__dynamic__' => [ 'link' => '[elementor-tag id="smpiurlproof" name="smpi-primary-category-url" settings="%5B%5D"]' ] ],
    ] );
    ob_start();
    $heading->print_element();
    $heading_html = ob_get_clean();
    $check( str_contains( $heading_html, 'href="' . esc_url( get_term_link( $term ) ) . '"' ), 'Native Heading link consumes Primary Category URL.' );
    echo 'RESULT: ' . wp_json_encode( [ 'post_id' => $candidate->ID, 'author_id' => $candidate->post_author, 'category_url' => $url->get_value() ] ) . "\n";
} finally {
    $GLOBALS['post'] = $original_post;
    if ( $original_post instanceof WP_Post ) {
        setup_postdata( $original_post );
    } else {
        wp_reset_postdata();
    }
}
