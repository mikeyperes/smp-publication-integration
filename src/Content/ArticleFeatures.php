<?php
namespace smp_publication_integration\Content;
use smp_publication_integration\Support\Settings;

/** The persisted article policy. Legacy settings seed it once and remain compatibility inputs. */
final class ArticleFeatures {
    public const OPTION = 'smpi_article_configuration';
    public static function definitions(): array {
        return [
            'summary' => ['What to Know', 'post_summary_acf_enabled'],
            'faqs' => ['FAQs', 'post_faqs_acf_enabled'],
            'article_types' => ['Article types', 'article_types_enabled'],
            'read_time' => ['Read time', 'estimated_read_time_enabled'],
            'table_of_contents' => ['Table of contents', 'table_of_contents_enabled'],
            'multiple_authors' => ['Multiple authors', 'multi_authors_enabled'],
            'bylines' => ['Bylines', null], 'schema' => ['Article schema', null],
            'content_blocks' => ['Automatic content blocks', null],
            'content_generation' => ['Content generation', 'content_generation_enabled'],
            'post_date' => ['Post date display', null],
            'going_live' => ['Going Live checklist', null],
            'visibility' => ['Post visibility controls', 'shadow_posts_enabled'],
            'editor_list' => ['Editor list defaults', 'post_list_defaults_enabled'],
            'post_cleanup' => ['Post cleanup', 'post_hygiene_enabled'],
            'breadcrumbs' => ['Breadcrumbs', 'breadcrumbs_enabled'],
            'heading_styles' => ['Heading styles', 'article_heading_styles_enabled'],
            'numbered_lists' => ['Numbered lists', 'article_numbered_lists_enabled'],
            'drop_cap' => ['Drop cap', 'article_drop_cap_enabled'],
            'inline_photos' => ['Inline photos', 'inline_photo_treatments_enabled'],
            'image_captions' => ['Image captions', 'featured_image_caption_templates_enabled'],
            'featured_image_required' => ['Featured image requirement', 'post_featured_image_required'],
            'primary_category' => ['Primary category', 'elementor_primary_category_enabled'],
            'author_social_icons' => ['Author social icons', 'author_social_icons_enabled'],
            'muckrack' => ['Muck Rack verification', 'muckrack_verified_enabled'],
        ];
    }
    public function register(): void {
        add_action('init', [self::class, 'migrate'], 99);
        add_action('admin_post_smpi_article_configuration', [self::class, 'save_form']);
        add_filter('option_hws_base_tools_breadcrumbs', [self::class, 'breadcrumb_settings']);
    }
    /** Read-only adapter: HWS Base Tools continues to own breadcrumb rendering and storage. */
    public static function breadcrumb_settings($settings) {
        if (!is_array($settings) || !is_array(get_option(self::OPTION, null))) return $settings;
        $selected = self::article_types();
        $enabled = self::types('breadcrumbs');
        $settings['disabled_post_types'] = array_values(array_unique(array_merge(
            array_diff((array) ($settings['disabled_post_types'] ?? []), $selected),
            array_diff($selected, $enabled)
        )));
        if ($enabled) $settings['enabled'] = true;
        return $settings;
    }
    public static function defaults(): array {
        $legacy = Settings::all();
        $types = ['post', 'press-release', 'knowledge-base', 'resources'];
        $features = [];
        $breadcrumb = get_option('hws_base_tools_breadcrumbs', null);
        foreach (self::definitions() as $id => $definition) foreach ($types as $type) {
            $on = null === $definition[1] || !empty($legacy[$definition[1]]);
            if ('breadcrumbs' === $id) $on = is_array($breadcrumb) ? !empty($breadcrumb['enabled']) && !in_array($type, $breadcrumb['disabled_post_types'] ?? [], true) : $on && !in_array($type, $legacy['breadcrumbs_disabled_post_types'] ?? [], true);
            if ('post_cleanup' === $id) $on = $on && in_array($type, $legacy['post_hygiene_allowed_post_types'] ?? ['post'], true);
            $features[$id][$type] = $on;
        }
        return ['version' => 1, 'article_types' => $types, 'features' => $features];
    }
    public static function configuration(): array {
        $saved = get_option(self::OPTION, null);
        return is_array($saved) ? $saved : self::defaults();
    }
    public static function migrate(): void {
        if (!is_array(get_option(self::OPTION, null))) add_option(self::OPTION, self::defaults(), '', false);
    }
    private static function public_types(array $types): array {
        return array_values(array_intersect(array_unique(array_map('sanitize_key', $types)), get_post_types(['public' => true], 'names')));
    }
    public static function article_types(): array {
        $types = apply_filters('smpi_article_post_types', self::configuration()['article_types'] ?? []);
        return self::public_types(is_array($types) ? $types : []);
    }
    public static function types(string $feature): array {
        $config = self::configuration();
        $types = array_values(array_filter(self::article_types(), static fn($type) => !empty($config['features'][$feature][$type])));
        $types = apply_filters('smpi_article_feature_post_types', $types, $feature);
        return array_values(array_intersect(self::article_types(), is_array($types) ? self::public_types($types) : []));
    }
    public static function enabled(string $feature, int $post_id = 0): bool {
        $post_id = $post_id ?: (int) get_the_ID();
        return in_array((string) get_post_type($post_id), self::types($feature), true);
    }
    public static function setting(string $key, int $post_id = 0): bool {
        foreach (self::definitions() as $feature => $definition) if ($key === $definition[1]) return self::enabled($feature, $post_id);
        return Settings::bool($key);
    }
    public static function update(array $config): void {
        $old = self::configuration();
        $types = self::public_types(is_array($config['article_types'] ?? null) ? $config['article_types'] : []);
        $old['article_types'] = $types;
        // Deselected types retain dormant flags; no content or field data is deleted.
        foreach (self::definitions() as $feature => $unused) foreach ($types as $type) $old['features'][$feature][$type] = !empty($config['features'][$feature][$type]);
        update_option(self::OPTION, $old, false);
    }
    public static function sync_legacy(array $changes): void {
        $config = self::configuration(); $changed = false;
        foreach (self::definitions() as $feature => $definition) {
            $key = $definition[1];
            if ($key && array_key_exists($key, $changes)) {
                foreach ($config['article_types'] as $type) $config['features'][$feature][$type] = (bool) $changes[$key];
                $changed = true;
            }
        }
        foreach (['breadcrumbs_disabled_post_types' => 'breadcrumbs', 'post_hygiene_allowed_post_types' => 'post_cleanup'] as $key => $feature) {
            if (!array_key_exists($key, $changes)) continue;
            foreach ($config['article_types'] as $type) {
                $contains = in_array($type, (array) $changes[$key], true);
                $config['features'][$feature][$type] = 'breadcrumbs' === $feature ? !$contains : $contains;
            }
            $changed = true;
        }
        if ($changed) update_option(self::OPTION, $config, false);
    }
    public static function manifest(): array {
        $features = [];
        foreach (self::definitions() as $id => $unused) $features[$id] = self::types($id);
        return ['article_types' => self::article_types(), 'features' => $features];
    }
    public static function save_form(): void {
        if (!current_user_can('manage_options')) wp_die('Insufficient permission.');
        check_admin_referer('smpi_article_configuration');
        $data = wp_unslash($_POST['article_configuration'] ?? []);
        self::update(is_array($data) ? $data : []);
        wp_safe_redirect(admin_url('options-general.php?page=smp-publication-integration&tab=custom_post_types')); exit;
    }
    public static function render_grid(): void {
        $config = self::configuration();
        echo '<section class="smpi-feature-card"><h2>Article content types and features</h2><p>Choose public content types and enable features per type. Disabling retains saved content and fields.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('smpi_article_configuration');
        echo '<input type="hidden" name="action" value="smpi_article_configuration">';
        $objects = get_post_types(['public' => true], 'objects');
        foreach ($objects as $type => $object) echo '<p><label><input type="checkbox" name="article_configuration[article_types][]" value="' . esc_attr($type) . '" ' . checked(in_array($type, $config['article_types'], true), true, false) . '> ' . esc_html($object->label . ' (' . $type . ')') . '</label></p>';
        echo '<table class="widefat striped"><thead><tr><th>Feature</th>';
        foreach ($objects as $type => $object) echo '<th scope="col">' . esc_html($object->label) . '</th>';
        echo '</tr></thead><tbody>';
        foreach (self::definitions() as $feature => $definition) {
            echo '<tr><th scope="row">' . esc_html($definition[0]) . '</th>';
            foreach ($objects as $type => $object) echo '<td><input type="checkbox" aria-label="' . esc_attr($definition[0] . ': ' . $object->label) . '" name="article_configuration[features][' . esc_attr($feature) . '][' . esc_attr($type) . ']" value="1" ' . checked(!empty($config['features'][$feature][$type]), true, false) . '></td>';
            echo '</tr>';
        }
        echo '</tbody></table><p>Only selected article types take effect. New types start with all features off.</p>';
        submit_button('Save article configuration'); echo '</form></section>';
    }
}
