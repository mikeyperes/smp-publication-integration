<?php
namespace smp_publication_integration\Admin;

use smp_publication_integration\Config;
use smp_publication_integration\Content\PublicationPostType;
use smp_publication_integration\Content\Schema;
use smp_publication_integration\Content\Shortcodes;
use smp_publication_integration\Support\Dependencies;
use smp_publication_integration\Support\Fields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Dashboard {
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
    }

    public function add_settings_page(): void {
        add_options_page(
            Config::$settings_page_name,
            Config::$settings_page_name,
            Config::$settings_page_capability,
            Config::$settings_page_slug,
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        if ( ! current_user_can( Config::$settings_page_capability ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'smp-publication-integration' ) );
        }

        $tabs       = $this->tabs();
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
        if ( ! isset( $tabs[ $active_tab ] ) ) {
            $active_tab = 'overview';
        }

        ?>
        <div class="wrap smpi-dashboard">
            <h1><?php echo esc_html( Config::$settings_page_display_title ); ?></h1>
            <?php $this->render_styles(); ?>
            <div class="smpi-tabs-nav">
                <?php foreach ( $tabs as $tab_id => $label ) : ?>
                    <button type="button" class="smpi-tab-btn<?php echo $tab_id === $active_tab ? ' active' : ''; ?>" data-tab="<?php echo esc_attr( $tab_id ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php foreach ( $tabs as $tab_id => $label ) : ?>
                <section id="smpi-tab-<?php echo esc_attr( $tab_id ); ?>" class="smpi-tab-content<?php echo $tab_id === $active_tab ? ' active' : ''; ?>">
                    <?php $this->render_tab( $tab_id ); ?>
                </section>
            <?php endforeach; ?>
        </div>
        <script>
        jQuery(function($) {
            $('.smpi-tab-btn').on('click', function() {
                var tabId = $(this).data('tab');
                $('.smpi-tab-btn').removeClass('active');
                $(this).addClass('active');
                $('.smpi-tab-content').removeClass('active');
                $('#smpi-tab-' + tabId).addClass('active');

                if (window.history && window.history.replaceState && typeof window.URL === 'function') {
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', tabId);
                    window.history.replaceState({}, '', url.toString());
                }
            });
        });
        </script>
        <?php
    }

    private function tabs(): array {
        return [
            'overview'     => 'Overview',
            'profiles'     => 'Publication Profiles',
            'shortcodes'   => 'Shortcodes',
            'schema'       => 'Schema',
            'reports'      => 'Reports',
            'integrations' => 'Integrations',
        ];
    }

    private function render_tab( string $tab_id ): void {
        switch ( $tab_id ) {
            case 'profiles':
                $this->render_profiles_tab();
                break;
            case 'shortcodes':
                $this->render_shortcodes_tab();
                break;
            case 'reports':
                $this->render_reports_tab();
                break;
            case 'schema':
                $this->render_schema_tab();
                break;
            case 'integrations':
                $this->render_integrations_tab();
                break;
            case 'overview':
            default:
                $this->render_overview_tab();
                break;
        }
    }

    private function render_overview_tab(): void {
        ?>
        <div class="smpi-hero">
            <p class="smpi-kicker">Publication Profiles</p>
            <h2>Scale My Publication profile layer for publications.</h2>
            <p>This plugin is conceptually parallel to the SFPF verified profile plugin: SFPF binds and renders people profiles; this plugin binds and renders publication profiles.</p>
        </div>
        <div class="smpi-grid">
            <?php $this->card( 'Namespace', '<code>smp_publication_integration</code>' ); ?>
            <?php $this->card( 'Plugin Slug', '<code>smp-publication-integration</code>' ); ?>
            <?php $this->card( 'GitHub Slug', '<code>mikeyperes/smp-publication-integration</code>' ); ?>
            <?php $this->card( 'Dependency', Dependencies::hws_base_tools_active() ? '<span class="smpi-ok">HWS Base Tools active</span>' : '<span class="smpi-bad">HWS Base Tools missing</span>' ); ?>
        </div>
        <?php
    }

    private function render_profiles_tab(): void {
        ?>
        <div class="smpi-panel">
            <h2>Publication Profile Structure</h2>
            <p>The plugin registers the public <code>publication</code> post type only when a site does not already have one. It then adds ACF field groups for publication profile bindings and user.php publication bindings.</p>
            <table class="widefat striped">
                <thead><tr><th>Field</th><th>Purpose</th><th>Duplicate Policy</th></tr></thead>
                <tbody>
                    <tr><td><code>smpi_publication_user</code></td><td>Bind a publication profile to a WordPress user.</td><td>Namespaced plugin-owned field.</td></tr>
                    <tr><td><code>smpi_founders</code></td><td>Bind multiple founder people profiles from the SFPF <code>profile</code> CPT.</td><td>Namespaced plugin-owned field.</td></tr>
                    <tr><td><code>smpi_mission_statement_override</code></td><td>Fallback mission statement.</td><td>Shortcodes read imported <code>mission_statement</code> first.</td></tr>
                    <tr><td><code>smpi_primary_publication</code></td><td>User profile primary publication binding.</td><td>Stored on <code>user.php</code>.</td></tr>
                    <tr><td><code>smpi_managed_publications</code></td><td>User profile multi-publication binding.</td><td>Stored on <code>user.php</code>.</td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_shortcodes_tab(): void {
        ?>
        <div class="smpi-panel">
            <h2>Shortcodes</h2>
            <p>These follow the SFPF pattern of central shortcode registration, but are publication-specific.</p>
            <table class="widefat striped">
                <thead><tr><th>Shortcode</th><th>Use</th></tr></thead>
                <tbody>
                <?php foreach ( Shortcodes::shortcodes() as $shortcode => $callback ) : ?>
                    <tr>
                        <td><code>[<?php echo esc_html( $shortcode ); ?>]</code></td>
                        <td><?php echo esc_html( $this->shortcode_description( $shortcode ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                    <tr><td><code>[smp_publication_field field="mission_statement"]</code></td><td>Read imported mission statement first, then the namespaced fallback.</td></tr>
                    <tr><td><code>[smp_publication_profile id="123"]</code></td><td>Render a specific publication profile card outside a publication page.</td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_reports_tab(): void {
        $counts = wp_count_posts( PublicationPostType::POST_TYPE );
        $total  = 0;
        if ( $counts ) {
            foreach ( (array) $counts as $count ) {
                $total += (int) $count;
            }
        }

        $profiles_count = wp_count_posts( 'profile' );
        $profiles_total = $profiles_count ? array_sum( array_map( 'intval', (array) $profiles_count ) ) : 0;
        $audit          = $this->publication_audit();
        ?>
        <div class="smpi-grid">
            <?php $this->card( 'WordPress Version', '<code>' . esc_html( get_bloginfo( 'version' ) ) . '</code>' ); ?>
            <?php $this->card( 'Publication Profiles', '<strong>' . esc_html( (string) $total ) . '</strong>' ); ?>
            <?php $this->card( 'SFPF Person Profiles', '<strong>' . esc_html( (string) $profiles_total ) . '</strong>' ); ?>
            <?php $this->card( 'Theme', '<code>' . esc_html( wp_get_theme()->get( 'Name' ) ) . '</code>' ); ?>
        </div>
        <div class="smpi-panel">
            <h2>Publication Field Audit</h2>
            <table class="widefat striped">
                <tbody>
                    <tr><th>Missing Mission Statement</th><td><?php echo esc_html( (string) $audit['missing_mission'] ); ?></td></tr>
                    <tr><th>Missing Publication User</th><td><?php echo esc_html( (string) $audit['missing_user'] ); ?></td></tr>
                    <tr><th>Missing Founders</th><td><?php echo esc_html( (string) $audit['missing_founders'] ); ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_schema_tab(): void {
        $counts = wp_count_posts( PublicationPostType::POST_TYPE );
        $total  = $counts && isset( $counts->publish ) ? (int) $counts->publish : 0;
        $sample = $this->latest_publication_id();
        $schema = $sample ? ( new Schema() )->generate_schema_json( $sample ) : '';
        ?>
        <div class="smpi-panel">
            <h2>Publication Schema</h2>
            <p>This follows the SFPF schema pattern: generate JSON-LD, store it on the profile, inject it into the single profile page, and provide validator links.</p>
            <table class="widefat striped">
                <tbody>
                    <tr><th>Schema Type</th><td><code>NewsMediaOrganization</code></td></tr>
                    <tr><th>Stored Field</th><td><code>smpi_schema_markup</code> with fallback meta <code>_smpi_schema_markup</code></td></tr>
                    <tr><th>Published Publications</th><td><?php echo esc_html( (string) $total ); ?></td></tr>
                    <tr><th>Injected On</th><td><code>is_singular('publication')</code></td></tr>
                    <tr><th>Validator Shortcode</th><td><code>[smp_publication_validate_schema]</code></td></tr>
                </tbody>
            </table>
        </div>

        <div class="smpi-panel">
            <h2>Reprocess Publication Schema Objects</h2>
            <p>Regenerates schema for published publication profiles in batches, matching the SFPF reprocess workflow.</p>
            <button id="smpi-reprocess-schema" type="button" class="button button-primary">Reprocess all publication schema objects</button>
            <div id="smpi-schema-report" style="margin-top:16px; padding:14px; background:#fff; border:1px solid #dcdcde; max-height:420px; overflow:auto;"></div>
        </div>

        <?php if ( $schema ) : ?>
            <div class="smpi-panel">
                <h2>Latest Publication Schema Preview</h2>
                <pre style="white-space:pre-wrap;background:#f6f7f7;border:1px solid #dcdcde;padding:14px;"><?php echo esc_html( $schema ); ?></pre>
            </div>
        <?php endif; ?>

        <script>
        jQuery(function($) {
            var offset = 0;
            var batchSize = 20;
            var total = 0;

            $('#smpi-reprocess-schema').on('click', function() {
                offset = 0;
                total = 0;
                $(this).prop('disabled', true);
                $('#smpi-schema-report').empty().append('<p>Starting schema reprocess...</p>');
                processBatch();
            });

            function processBatch() {
                $.post(ajaxurl, {
                    action: 'smpi_reprocess_schema',
                    offset: offset,
                    batch_size: batchSize
                }).done(function(response) {
                    if (!response || !response.success) {
                        $('#smpi-schema-report').append('<p style="color:#b32d2e;">Error: ' + ((response && response.data && response.data.message) || 'Unknown error') + '</p>');
                        $('#smpi-reprocess-schema').prop('disabled', false);
                        return;
                    }

                    total = response.data.total || 0;
                    $.each(response.data.items || [], function(i, item) {
                        var schema = $('<div>').text(item.schema || '').html();
                        $('#smpi-schema-report').append(
                            '<div style="margin-bottom:18px;">' +
                            '<p><strong>' + item.title + ' (ID ' + item.post_id + ')</strong> - ' +
                            '<a href="' + item.admin_link + '" target="_blank">Edit</a> | ' +
                            '<a href="' + item.view_link + '" target="_blank">View</a> | ' +
                            '<a href="' + item.validator_link + '" target="_blank">Validate Schema</a></p>' +
                            '<pre style="background:#f9f9f9;padding:10px;border:1px solid #ddd;white-space:pre-wrap;">' + schema + '</pre>' +
                            '</div>'
                        );
                    });

                    offset += batchSize;
                    $('#smpi-schema-report').append('<p>Processed ' + Math.min(offset, total) + ' of ' + total + '</p>');

                    if (offset < total) {
                        processBatch();
                    } else {
                        $('#smpi-schema-report').append('<p><strong>Completed processing ' + total + ' publication profiles.</strong></p>');
                        $('#smpi-reprocess-schema').prop('disabled', false);
                    }
                }).fail(function() {
                    $('#smpi-schema-report').append('<p style="color:#b32d2e;">AJAX request failed.</p>');
                    $('#smpi-reprocess-schema').prop('disabled', false);
                });
            }
        });
        </script>
        <?php
    }


    private function render_integrations_tab(): void {
        ?>
        <div class="smpi-panel">
            <h2>Integration Status</h2>
            <table class="widefat striped">
                <tbody>
                    <tr><th>HWS Base Tools</th><td><?php echo Dependencies::hws_base_tools_active() ? '<span class="smpi-ok">Active</span>' : '<span class="smpi-bad">Missing</span>'; ?></td></tr>
                    <tr><th>Advanced Custom Fields</th><td><?php echo Dependencies::acf_active() ? '<span class="smpi-ok">Active</span>' : '<span class="smpi-warn">Missing - fields will not register</span>'; ?></td></tr>
                    <tr><th>SFPF / Profile CPT</th><td><?php echo Dependencies::sfpf_active() ? '<span class="smpi-ok">Available</span>' : '<span class="smpi-warn">Profile CPT not detected</span>'; ?></td></tr>
                    <tr><th>Hexa PR Wire Distributor</th><td><?php echo Dependencies::plugin_active( 'hexa-pr-wire-distributor/initialization.php' ) ? '<span class="smpi-ok">Active</span>' : '<span class="smpi-warn">Not active</span>'; ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function publication_audit(): array {
        $query = new \WP_Query(
            [
                'post_type'      => PublicationPostType::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 200,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        $audit = [
            'missing_mission'  => 0,
            'missing_user'     => 0,
            'missing_founders' => 0,
        ];

        foreach ( $query->posts as $post_id ) {
            if ( ! Fields::has_value( Fields::get( (int) $post_id, 'mission_statement' ) ) ) {
                $audit['missing_mission']++;
            }
            if ( ! Fields::has_value( Fields::get( (int) $post_id, 'publication_user' ) ) ) {
                $audit['missing_user']++;
            }
            if ( ! Fields::has_value( Fields::get( (int) $post_id, 'founders' ) ) ) {
                $audit['missing_founders']++;
            }
        }

        return $audit;
    }

    private function card( string $title, string $content ): void {
        echo '<div class="smpi-card"><h3>' . esc_html( $title ) . '</h3><p>' . wp_kses_post( $content ) . '</p></div>';
    }

    private function shortcode_description( string $shortcode ): string {
        $descriptions = [
            'smp_publication_field'             => 'Render a publication field by key or alias.',
            'smp_publication_mission_statement' => 'Render the mission statement block.',
            'smp_publication_founders'          => 'Render linked founder person profiles.',
            'smp_publication_user'              => 'Render the bound publication user display name.',
            'smp_publication_profile'           => 'Render a complete publication profile card.',
            'smp_publication_validate_schema'   => 'Render a Schema.org validator link for the current publication.',
        ];

        return $descriptions[ $shortcode ] ?? '';
    }

    private function latest_publication_id(): int {
        $query = new \WP_Query(
            [
                'post_type'      => PublicationPostType::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'orderby'        => 'date',
                'order'          => 'DESC',
                'no_found_rows'  => true,
            ]
        );

        return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
    }

    private function render_styles(): void {
        ?>
        <style>
            .smpi-dashboard { max-width: 1180px; }
            .smpi-tabs-nav { display:flex; flex-wrap:wrap; gap:8px; margin:18px 0; border-bottom:1px solid #dcdcde; }
            .smpi-tab-btn { border:1px solid #dcdcde; border-bottom:none; background:#f6f7f7; padding:10px 14px; border-radius:8px 8px 0 0; cursor:pointer; color:#1d2327; }
            .smpi-tab-btn.active { background:#fff; color:#2271b1; font-weight:700; }
            .smpi-tab-content { display:none; }
            .smpi-tab-content.active { display:block; }
            .smpi-hero { margin:18px 0; padding:28px 30px; border:1px solid #dcdcde; border-radius:14px; background:linear-gradient(135deg,#fff 0%,#f6f7f7 100%); box-shadow:0 10px 28px rgba(0,0,0,.05); }
            .smpi-kicker { margin:0 0 8px; color:#2271b1; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
            .smpi-hero h2 { margin:0 0 10px; font-size:28px; line-height:1.2; }
            .smpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:16px; margin:18px 0; }
            .smpi-card, .smpi-panel { padding:18px; border:1px solid #dcdcde; border-radius:12px; background:#fff; }
            .smpi-card h3, .smpi-panel h2 { margin-top:0; }
            .smpi-ok { color:#008a20; font-weight:700; }
            .smpi-warn { color:#996800; font-weight:700; }
            .smpi-bad { color:#b32d2e; font-weight:700; }
        </style>
        <?php
    }
}
