<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Fields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Shortcodes {
    public function register(): void {
        add_action( 'init', [ $this, 'register_shortcodes' ] );
    }

    public function register_shortcodes(): void {
        foreach ( self::shortcodes() as $tag => $callback ) {
            add_shortcode( $tag, [ $this, $callback ] );
        }
    }

    public static function shortcodes(): array {
        return [
            'smp_publication_field'             => 'render_field',
            'smp_publication_mission_statement' => 'render_mission_statement',
            'smp_publication_founders'          => 'render_founders',
            'smp_publication_user'              => 'render_publication_user',
            'smp_publication_profile'           => 'render_profile',
            'smp_publication_validate_schema'   => 'render_validate_schema',
        ];
    }

    public function render_field( array $atts = [] ): string {
        $atts = shortcode_atts(
            [
                'id'    => 0,
                'field' => '',
            ],
            $atts,
            'smp_publication_field'
        );

        $post_id = $this->resolve_publication_id( (int) $atts['id'] );
        $field   = sanitize_key( (string) $atts['field'] );

        if ( ! $post_id || '' === $field ) {
            return '';
        }

        $value = Fields::get( $post_id, $field );
        if ( is_array( $value ) || is_object( $value ) ) {
            return esc_html( wp_json_encode( $value ) );
        }

        return wp_kses_post( (string) $value );
    }

    public function render_mission_statement( array $atts = [] ): string {
        $post_id = $this->resolve_publication_id( (int) ( $atts['id'] ?? 0 ) );
        if ( ! $post_id ) {
            return '';
        }

        $mission = Fields::get( $post_id, 'mission_statement' );
        return $mission ? '<div class="smpi-publication-mission">' . wp_kses_post( wpautop( (string) $mission ) ) . '</div>' : '';
    }

    public function render_founders( array $atts = [] ): string {
        $post_id = $this->resolve_publication_id( (int) ( $atts['id'] ?? 0 ) );
        if ( ! $post_id ) {
            return '';
        }

        $founders = Fields::get( $post_id, 'founders', [] );
        $founders = is_array( $founders ) ? $founders : [ $founders ];
        $founders = array_filter( array_map( [ $this, 'normalize_post_id' ], $founders ) );

        if ( empty( $founders ) ) {
            return '';
        }

        $items = [];
        foreach ( $founders as $founder_id ) {
            $title = get_the_title( $founder_id );
            if ( '' === $title ) {
                continue;
            }

            $items[] = sprintf(
                '<li><a href="%s">%s</a></li>',
                esc_url( get_permalink( $founder_id ) ),
                esc_html( $title )
            );
        }

        return $items ? '<ul class="smpi-publication-founders">' . implode( '', $items ) . '</ul>' : '';
    }

    public function render_publication_user( array $atts = [] ): string {
        $post_id = $this->resolve_publication_id( (int) ( $atts['id'] ?? 0 ) );
        if ( ! $post_id ) {
            return '';
        }

        $user_id = (int) Fields::get( $post_id, 'publication_user', 0 );
        if ( ! $user_id ) {
            return '';
        }

        $user = get_userdata( $user_id );
        return $user ? '<span class="smpi-publication-user">' . esc_html( $user->display_name ) . '</span>' : '';
    }

    public function render_profile( array $atts = [] ): string {
        $post_id = $this->resolve_publication_id( (int) ( $atts['id'] ?? 0 ) );
        if ( ! $post_id ) {
            return '';
        }

        $title    = get_the_title( $post_id );
        $website  = Fields::get( $post_id, 'website' );
        $summary  = Fields::get( $post_id, 'summary' );
        $mission  = Fields::get( $post_id, 'mission_statement' );
        $founders = $this->render_founders( [ 'id' => $post_id ] );

        ob_start();
        ?>
        <article class="smpi-publication-profile">
            <h2 class="smpi-publication-profile__title"><?php echo esc_html( $title ); ?></h2>
            <?php if ( $website ) : ?>
                <p class="smpi-publication-profile__website"><a href="<?php echo esc_url( (string) $website ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $website ); ?></a></p>
            <?php endif; ?>
            <?php if ( $summary ) : ?>
                <div class="smpi-publication-profile__summary"><?php echo wp_kses_post( wpautop( (string) $summary ) ); ?></div>
            <?php endif; ?>
            <?php if ( $mission ) : ?>
                <h3>Mission Statement</h3>
                <div class="smpi-publication-profile__mission"><?php echo wp_kses_post( wpautop( (string) $mission ) ); ?></div>
            <?php endif; ?>
            <?php if ( $founders ) : ?>
                <h3>Founders</h3>
                <?php echo $founders; ?>
            <?php endif; ?>
        </article>
        <?php
        return trim( (string) ob_get_clean() );
    }

    public function render_validate_schema( array $atts = [] ): string {
        $post_id = $this->resolve_publication_id( (int) ( $atts['id'] ?? 0 ) );
        if ( ! $post_id ) {
            return '';
        }

        return sprintf(
            '<a class="smpi-publication-schema-validator" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( 'https://validator.schema.org/#url=' . rawurlencode( get_permalink( $post_id ) ) ),
            esc_html( 'Validate schema for ' . get_the_title( $post_id ) )
        );
    }

    private function resolve_publication_id( int $explicit_id = 0 ): int {
        if ( $explicit_id && PublicationPostType::POST_TYPE === get_post_type( $explicit_id ) ) {
            return $explicit_id;
        }

        $post = get_post();
        if ( $post && PublicationPostType::POST_TYPE === $post->post_type ) {
            return (int) $post->ID;
        }

        return 0;
    }

    private function normalize_post_id( $value ): int {
        if ( is_object( $value ) && isset( $value->ID ) ) {
            return (int) $value->ID;
        }

        return (int) $value;
    }
}
