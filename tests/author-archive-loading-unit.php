<?php

declare( strict_types=1 );

namespace smp_publication_integration\Support {
    final class Settings {
        public static array $values = [
            'author_archive_loading_enabled' => false,
            'author_archive_loading_mode'    => 'pagination',
            'author_archive_loading_style'   => 'none',
        ];

        public static function bool( string $key ): bool {
            return ! empty( self::$values[ $key ] );
        }

        public static function get( string $key, mixed $default = null ): mixed {
            return self::$values[ $key ] ?? $default;
        }
    }
}

namespace {
    define( 'ABSPATH', '/tmp/smpi-wordpress/' );

    $GLOBALS['smpi_author_loading_hooks'] = [];
    $GLOBALS['smpi_is_author'] = true;

    function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $GLOBALS['smpi_author_loading_hooks'][ $hook ][] = [ $callback, $priority, $accepted_args ];
    }
    function is_author(): bool { return (bool) $GLOBALS['smpi_is_author']; }
    function sanitize_key( mixed $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: ''; }
    function sanitize_html_class( mixed $value ): string { return preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ?: ''; }
    function esc_attr( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
    function esc_html( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES ); }

    final class SmpiAuthorLoadingWidget {
        public array $attributes = [];
        public bool $reset = false;

        public function __construct( public string $name, public array $settings ) {}
        public function get_name(): string { return $this->name; }
        public function get_settings(): array { return $this->settings; }
        public function set_settings( string $key, mixed $value ): void { $this->settings[ $key ] = $value; }
        public function reset_render_state(): void { $this->reset = true; $this->attributes = []; }
        public function add_render_attribute( string $element, string $key, mixed $value ): void { $this->attributes[ $element ][ $key ][] = $value; }
    }

    require dirname( __DIR__ ) . '/src/Content/AuthorArchiveLoading.php';

    use smp_publication_integration\Content\AuthorArchiveLoading;
    use smp_publication_integration\Support\Settings;

    $fail = static function ( string $message ): never {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    };

    $feature = new AuthorArchiveLoading();
    $feature->register();
    if ( empty( $GLOBALS['smpi_author_loading_hooks']['elementor/frontend/widget/before_render'] )
        || empty( $GLOBALS['smpi_author_loading_hooks']['wp_head'] )
    ) {
        $fail( 'Author archive loading did not register its Elementor and style hooks.' );
    }

    $disabled = new SmpiAuthorLoadingWidget( 'loop-grid', [ 'post_query_post_type' => 'current_query', 'pagination_type' => 'load_more_on_click' ] );
    $feature->configure_widget( $disabled );
    if ( 'load_more_on_click' !== $disabled->settings['pagination_type'] || [] !== $disabled->attributes ) {
        $fail( 'Disabled author loading changed the Elementor widget.' );
    }

    Settings::$values = [
        'author_archive_loading_enabled' => true,
        'author_archive_loading_mode'    => 'infinite_scroll',
        'author_archive_loading_style'   => 'hairline_outline',
    ];
    $infinite = new SmpiAuthorLoadingWidget( 'loop-grid', [ 'post_query_post_type' => 'current_query', 'pagination_type' => 'numbers' ] );
    $feature->configure_widget( $infinite );
    $classes = $infinite->attributes['_wrapper']['class'][0] ?? [];
    if ( 'load_more_infinite_scroll' !== $infinite->settings['pagination_type']
        || ! $infinite->reset
        || ! in_array( 'smpi-author-loading--mode-infinite_scroll', $classes, true )
        || ! in_array( 'smpi-author-loading--style-hairline_outline', $classes, true )
    ) {
        $fail( 'Infinite scroll did not map to Elementor Pro with scoped style hooks.' );
    }

    $unrelated = new SmpiAuthorLoadingWidget( 'loop-grid', [ 'post_query_post_type' => 'post', 'pagination_type' => 'numbers' ] );
    $feature->configure_widget( $unrelated );
    if ( 'numbers' !== $unrelated->settings['pagination_type'] || [] !== $unrelated->attributes ) {
        $fail( 'A non-current-query Loop Grid was changed.' );
    }

    Settings::$values['author_archive_loading_mode'] = 'load_more';
    $load_more = new SmpiAuthorLoadingWidget( 'archive-posts', [ 'pagination_type' => 'numbers' ] );
    $feature->configure_widget( $load_more );
    if ( 'load_more_on_click' !== $load_more->settings['pagination_type'] || 'Load more' !== $load_more->settings['text'] ) {
        $fail( 'Load-more mode did not map to Elementor Pro.' );
    }

    Settings::$values['author_archive_loading_mode'] = 'pagination';
    $pagination = new SmpiAuthorLoadingWidget( 'posts', [ 'query_post_type' => 'current_query', 'pagination_type' => '' ] );
    $feature->configure_widget( $pagination );
    if ( 'numbers_and_prev_next' !== $pagination->settings['pagination_type']
        || 'page_reload' !== $pagination->settings['pagination_load_type']
        || 'Previous' !== $pagination->settings['pagination_prev_label']
        || 'Next' !== $pagination->settings['pagination_next_label']
    ) {
        $fail( 'Pagination mode did not map to Elementor Pro.' );
    }

    $css = AuthorArchiveLoading::frontend_css( 'hairline_outline' );
    if ( ! str_contains( $css, '.smpi-author-loading--style-hairline_outline' )
        || ! str_contains( $css, 'min-height:44px' )
        || ! str_contains( $css, 'prefers-reduced-motion:reduce' )
        || '' !== AuthorArchiveLoading::frontend_css( 'none' )
    ) {
        $fail( 'Style output is not scoped, accessible, or presentation-free in No Style mode.' );
    }

    if ( 6 !== count( AuthorArchiveLoading::styles() ) || 3 !== count( AuthorArchiveLoading::modes() ) ) {
        $fail( 'The loading/style option contract is incomplete.' );
    }

    echo "PASS: Author archives use Elementor-native loading modes with six scoped presentation choices.\n";
}
