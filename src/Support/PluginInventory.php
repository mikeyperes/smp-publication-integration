<?php
namespace smp_publication_integration\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PluginInventory {
    public static function recommended_action_prefix(): string {
        return 'smpi_recommended_plugins';
    }

    public static function outside_action_prefix(): string {
        return 'smpi_outside_plugins';
    }

    public static function forbidden_action_prefix(): string {
        return 'smpi_forbidden_plugins';
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recommended_definitions(): array {
        return [
            self::normal( 'advanced-custom-fields-pro/acf.php', 'Advanced Custom Fields PRO', 'pro', true ),
            self::wp_org( 'classic-editor/classic-editor.php', 'Classic Editor', 'classic-editor', true ),
            self::wp_org( 'code-snippets/code-snippets.php', 'Code Snippets', 'code-snippets', true ),
            self::normal( 'rss-feed-post-generator-echo/rss-feed-post-generator-echo.php', 'Echo RSS Feed Post Generator', 'pro', true ),
            self::wp_org( 'elementor/elementor.php', 'Elementor', 'elementor', true ),
            self::normal( 'elementor-pro/elementor-pro.php', 'Elementor Pro', 'pro', true ),
            self::wp_org( 'featured-image-from-url/featured-image-from-url.php', 'Featured Image from URL (FIFU)', 'featured-image-from-url', true ),
            self::normal( 'gplvault-updater/gplvault-updater.php', 'GPLVault Update Manager', 'manual', true ),
            self::github( 'hexa-pr-wire-distributor/hexa-pr-wire-distributor.php', 'Hexa PR Wire - Distributor', 'mikeyperes/hexa-pr-wire-distributor', false ),
            self::github( 'hws-base-tools/hws-base-tools.php', 'Hexa Web Systems - Website Base Tool', 'mikeyperes/hws-base-tools', false ),
            self::wp_org( 'litespeed-cache/litespeed-cache.php', 'LiteSpeed Cache', 'litespeed-cache', true ),
            self::normal( 'media-cleaner-pro/media-cleaner-pro.php', 'Media Cleaner (Pro)', 'pro', true ),
            self::wp_org( 'seo-by-rank-math/rank-math.php', 'Rank Math SEO', 'seo-by-rank-math', true ),
            self::normal( 'seo-by-rank-math-pro/rank-math-pro.php', 'Rank Math SEO PRO', 'pro', true ),
            self::wp_org( 'simple-local-avatars/simple-local-avatars.php', 'Simple Local Avatars', 'simple-local-avatars', false ),
            self::wp_org( 'google-site-kit/google-site-kit.php', 'Site Kit by Google', 'google-site-kit', true ),
            self::github( 'smp-publication-integration/smp-publication-integration.php', 'SMP Publication Integration', 'mikeyperes/smp-publication-integration', false ),
            self::normal( 'smp-wp-text-to-speech/smp-wp-text-to-speech.php', 'SMP WP Text To Speech', 'manual', false ),
            self::definition( 'visibility-logic-elementor/conditional.php', 'Visibility Logic for Elementor', 'wordpress_org', 'visibility-logic-elementor', '', true, true, false, false ),
            self::wp_org( 'under-construction-page/under-construction.php', 'Under Construction', 'under-construction-page', false ),
            self::github( 'smp-verified-profiles/smp-verified-profiles.php', 'Verified Profiles - Scale My Publication (Michael Peres)', 'mikeyperes/smp-verified-profiles', false ),
            self::wp_org( 'wp-optimize/wp-optimize.php', 'WP-Optimize - Clean, Compress, Cache', 'wp-optimize', false ),
            self::wp_org( 'wp-mail-smtp/wp_mail_smtp.php', 'WP Mail SMTP', 'wp-mail-smtp', true ),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function outside_definitions(): array {
        return self::forbidden_definitions();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function forbidden_definitions(): array {
        self::load_plugin_functions();

        $recommended = array_fill_keys( self::recommended_plugin_files(), true );
        $forbidden_slugs = [
            'jet-engine' => true,
        ];
        $definitions = [
            self::forbidden( 'jet-engine/jet-engine.php', 'JetEngine', 'jet-engine' ),
        ];

        foreach ( get_plugins() as $plugin_file => $plugin_data ) {
            $plugin_file = (string) $plugin_file;
            if ( isset( $recommended[ $plugin_file ] ) ) {
                continue;
            }
            if ( isset( $forbidden_slugs[ dirname( $plugin_file ) ] ) ) {
                continue;
            }

            $definitions[] = self::forbidden(
                $plugin_file,
                (string) ( $plugin_data['Name'] ?? $plugin_file ),
                dirname( $plugin_file )
            );
        }

        return $definitions;
    }

    /**
     * @return array<string,mixed>
     */
    public static function recommended_renderer_args(): array {
        return [
            'title'            => 'Recommended Plugin Stack',
            'description'      => 'Required plugin stack audited from the active Mash Viral runtime. Missing items from this list are treated as required.',
            'action_prefix'    => self::recommended_action_prefix(),
            'nonce'            => \smp_publication_integration\Admin\Ajax::nonce(),
            'nonce_field'      => 'nonce',
            'persist_key'      => 'smpi-recommended-plugin-stack',
            'open'             => true,
            'show_install_all' => false,
            'columns'          => [
                'auto_update' => true,
                'version'     => true,
                'source'      => true,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function outside_renderer_args(): array {
        return self::forbidden_renderer_args();
    }

    /**
     * @return array<string,mixed>
     */
    public static function forbidden_renderer_args(): array {
        return [
            'title'            => "Everything That Shouldn't Be There",
            'description'      => 'Plugins that should not be installed for this SMP runtime. Installed matches expose Core-managed Deactivate and Delete actions.',
            'action_prefix'    => self::forbidden_action_prefix(),
            'nonce'            => \smp_publication_integration\Admin\Ajax::nonce(),
            'nonce_field'      => 'nonce',
            'persist_key'      => 'smpi-forbidden-plugin-stack',
            'open'             => true,
            'empty_text'       => 'No forbidden or outside-recommended plugins are installed.',
            'show_install_all' => false,
            'hide_compliant_forbidden' => true,
            'show_unwanted'    => true,
            'columns'          => [
                'auto_update' => true,
                'version'     => true,
                'source'      => true,
            ],
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function recommended_plugin_files(): array {
        return array_values(
            array_map(
                static fn( array $definition ): string => (string) $definition['plugin_file'],
                self::recommended_definitions()
            )
        );
    }

    private static function normal( string $plugin_file, string $name, string $source, bool $auto_update ): array {
        return self::definition( $plugin_file, $name, $source, '', '', true, true, true, $auto_update );
    }

    private static function wp_org( string $plugin_file, string $name, string $wp_org_slug, bool $auto_update ): array {
        return self::definition( $plugin_file, $name, 'wordpress_org', $wp_org_slug, '', true, true, true, $auto_update );
    }

    private static function github( string $plugin_file, string $name, string $repo, bool $auto_update ): array {
        return self::definition( $plugin_file, $name, 'github', '', $repo, true, true, true, $auto_update );
    }

    private static function outside( string $plugin_file, string $name, string $source = 'manual' ): array {
        return self::definition( $plugin_file, $name, $source, '', '', false, false, false, false );
    }

    private static function forbidden( string $plugin_file, string $name, string $slug, string $source = 'manual' ): array {
        $definition = self::definition( $plugin_file, $name, $source, '', '', false, false, false, false );
        $definition['slug'] = $slug;
        $definition['should_not_contain'] = true;
        $definition['checks'] = [
            'installed'     => false,
            'active'        => false,
            'up_to_date'    => false,
            'auto_update'   => false,
            'not_installed' => true,
        ];
        $definition['notes'] = 'This plugin should not be installed in the recommended Mash Viral SMP stack.';

        return $definition;
    }

    private static function definition( string $plugin_file, string $name, string $source, string $wp_org_slug, string $repo, bool $required, bool $recommended, bool $active_check, bool $auto_update ): array {
        $notes = $recommended
            ? 'Recommended Mash Viral plugin stack. Expected state: installed and active.'
            : 'Installed on this site but not part of the recommended Mash Viral plugin stack.';

        return [
            'id'                   => str_replace( [ '/', '.' ], '-', $plugin_file ),
            'name'                 => $name,
            'plugin_file'          => $plugin_file,
            'slug'                 => in_array( $source, [ 'must_use', 'dropin' ], true ) ? $plugin_file : dirname( $plugin_file ),
            'source'               => $source,
            'wp_org_slug'          => $wp_org_slug,
            'github_repo'          => $repo,
            'github_branch'        => 'main',
            'download_url'         => self::download_url( $source, $wp_org_slug, $repo ),
            'download_label'       => 'Open source',
            'required'             => $required,
            'recommended'          => $recommended,
            'auto_update_expected' => $auto_update,
            'checks'               => [
                'installed'   => $required,
                'active'      => $active_check,
                'up_to_date'  => false,
                'auto_update' => false,
            ],
            'notes'                => $notes,
        ];
    }

    private static function download_url( string $source, string $wp_org_slug, string $repo ): string {
        if ( 'wordpress_org' === $source && '' !== $wp_org_slug ) {
            return admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode( $wp_org_slug ) );
        }

        if ( 'github' === $source && '' !== $repo ) {
            return 'https://github.com/' . trim( $repo, '/' );
        }

        return admin_url( 'plugin-install.php?tab=upload' );
    }

    private static function load_plugin_functions(): void {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }
}
