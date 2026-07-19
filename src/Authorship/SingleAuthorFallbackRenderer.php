<?php

namespace smp_publication_integration\Authorship;

use smp_publication_integration\Content\MuckRackVerification;
use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SingleAuthorFallbackRenderer {
    private const SHORTCODE = 'display_profiles_featured_in_single_post';
    private const EMPTY_MARKER = 'data-smp-vp-empty-loop="single-post"';

    private AuthorAssignmentRepository $repository;

    public function __construct( AuthorAssignmentRepository $repository ) {
        $this->repository = $repository;
    }

    public function register(): void {
        add_filter( 'do_shortcode_tag', [ $this, 'filter_shortcode_output' ], 20, 4 );
        add_action( 'wp_head', [ $this, 'print_styles' ], 31 );
    }

    public function filter_shortcode_output( string $output, string $tag, $attributes = [], $match = [] ): string {
        if (
            self::SHORTCODE !== $tag
            || false === strpos( $output, self::EMPTY_MARKER )
            || ! Settings::bool( 'multi_authors_enabled' )
            || ! RuntimeContext::is_public_dom_context()
            || ! is_singular( $this->repository->supported_post_types() )
        ) {
            return $output;
        }

        $post = get_post();
        if ( ! $post instanceof \WP_Post ) {
            return $output;
        }

        $authors = $this->repository->records_for_post( (int) $post->ID, true );
        if ( empty( $authors ) ) {
            return $output;
        }

        $items = [];
        foreach ( $authors as $author ) {
            if ( $author instanceof AuthorRecord ) {
                $items[] = $this->render_author( $author );
            }
        }

        if ( empty( $items ) ) {
            return $output;
        }

        return '<span class="smpi-single-author-fallback" data-smpi-author-fallback="canonical" aria-label="Author">'
            . '<span class="smpi-single-author-fallback__label">By</span>'
            . implode( '<span class="smpi-single-author-fallback__separator" aria-hidden="true">,</span>', $items )
            . '</span>';
    }

    public function print_styles(): void {
        if ( ! Settings::bool( 'multi_authors_enabled' ) || ! RuntimeContext::is_public_dom_context() ) {
            return;
        }

        echo '<style id="smpi-single-author-fallback-styles">.smpi-single-author-fallback{display:flex;align-items:center;flex-wrap:wrap;gap:.42em;color:inherit;font:inherit;line-height:1.35}.smpi-single-author-fallback__label{opacity:.72}.smpi-single-author-fallback__item{display:inline-flex;align-items:center;max-width:100%}.smpi-single-author-fallback__name{color:inherit!important;font-weight:700;text-decoration:none}.smpi-single-author-fallback__name:hover{text-decoration:underline}.smpi-single-author-fallback__separator{margin-left:-.2em}</style>';
    }

    private function render_author( AuthorRecord $author ): string {
        $data = $author->to_array();
        $badge = '';
        if (
            Settings::bool( 'muckrack_verified_enabled' )
            && in_array( 'single_author', Settings::array( 'muckrack_verified_contexts' ), true )
        ) {
            $badge = MuckRackVerification::verification_icon(
                $author->id(),
                (string) Settings::get( 'muckrack_verified_style', 'tooltip' ),
                'single_author'
            );
        }

        return '<span class="smpi-single-author-fallback__item smpi-muckrack-inline-pair" data-smpi-author-id="' . esc_attr( (string) $author->id() ) . '">'
            . '<a class="smpi-single-author-fallback__name smpi-post-journalist-link" rel="author" href="' . esc_url( (string) $data['url'] ) . '">'
            . esc_html( (string) $data['name'] )
            . '</a>'
            . $badge
            . '</span>';
    }
}
