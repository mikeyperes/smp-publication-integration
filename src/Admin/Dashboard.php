<?php
namespace smp_publication_integration\Admin;

use Hexa\PluginCore\ShortcodeRegistry\ShortcodeDisplayRenderer;
use Hexa\PluginCore\SmartSearch\SmartSearchRenderer;
use Hexa\PluginCore\FieldStructures\FieldStructureRenderer;
use Hexa\PluginCore\WpAdminTabs\HostTabsRenderer;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\SiteStructure\SiteStructureRenderer;
use Hexa\PluginCore\SchemaDetection\SchemaPageScanner;
use Hexa\PluginCore\SchemaDetection\SchemaScanRenderer;
use smp_publication_integration\Config;
use smp_publication_integration\Content\AuthorShortcodes;
use smp_publication_integration\Content\Schema;
use smp_publication_integration\Content\Shortcodes;
use smp_publication_integration\Support\Dependencies;
use smp_publication_integration\Support\PageStructure;
use smp_publication_integration\Support\PluginRegistry;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Dashboard {
    private const SCHEMA_DETECTION_CACHE_KEY = 'smpi_schema_detection_report';
    private const SCHEMA_DETECTION_REFRESH_LOCK_KEY = 'smpi_schema_detection_refresh_lock';

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function add_settings_page(): void {
        add_options_page( Config::$settings_page_name, Config::$settings_page_name, Config::$settings_page_capability, Config::$settings_page_slug, [ $this, 'render' ] );
    }

    public function enqueue_admin_assets(): void {
        $page = isset( $_GET["page"] ) ? sanitize_key( wp_unslash( $_GET["page"] ) ) : "";
        if ( Config::$settings_page_slug !== $page ) {
            return;
        }

        wp_enqueue_editor();
        wp_enqueue_media();

        if ( Dependencies::acf_active() && function_exists( "acf_enqueue_scripts" ) ) {
            acf_enqueue_scripts();
        }
    }

    public function render(): void {
        if ( ! current_user_can( Config::$settings_page_capability ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'smp-publication-integration' ) );
        }
        $tabs = $this->tabs();
        $active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
        $active = isset( $tabs[ $active ] ) ? $active : 'overview';
        if ( Dependencies::acf_active() && function_exists( "acf_form_head" ) ) {
            acf_form_head();
        }
        ?>
        <div class="wrap smpi-dashboard">
            <h1><?php echo esc_html( Config::$settings_page_display_title ); ?></h1>
            <?php $this->styles(); ?>
            <?php CoreUi::render_assets(); ?>
            <?php
            ( new HostTabsRenderer() )->render(
                [
                    "tabs"            => $tabs,
                    "active"          => $active,
                    "page_url"        => admin_url( "options-general.php?page=" . Config::$settings_page_slug ),
                    "ajax_action"     => "smpi_load_tab",
                    "nonce"           => Ajax::nonce(),
                    "nonce_field"     => "nonce",
                    "root_id"         => "smpi-core-tabs",
                    "panel_id"        => "smpi-tab-panel",
                    "label"           => "SMP Publication Integration sections",
                    "render_callback" => function( string $tab ): void { $this->tab( $tab ); },
                ]
            );
            ?>
        </div>
        <?php $this->scripts(); ?>
        <?php
    }

    public function tab_fragment( string $id ): array {
        $tabs = $this->tabs();
        $active = isset( $tabs[ $id ] ) ? $id : "overview";

        ob_start();
        if ( "publication_options" === $active && Dependencies::acf_active() && function_exists( "acf_form_head" ) ) {
            acf_form_head();
        }
        $this->tab( $active );
        $html = ob_get_clean();

        return [
            "tab" => $active,
            "label" => $tabs[ $active ],
            "html" => is_string( $html ) ? $html : "",
        ];
    }

    private function tabs(): array {
        return apply_filters( 'smpi_dashboard_tabs', [
            'overview' => 'Overview',
            'publication_options' => 'Publication Options',
            'profiles' => 'Publication Profiles',
            'brand' => 'Brand',
            'shortcodes' => 'Shortcodes',
            'schema' => 'Schema',
            'reports' => 'Reports',
            'custom_fields' => 'Custom Fields',
            'features' => 'Features',
            'ui_cleanup' => 'UI Cleanup',
            'optimization' => 'Optimization',
            'pages' => 'Pages',
            'verified_profiles' => 'Verified Profiles',
            'integrations' => 'Integrations',
            'quick_run' => 'Quick Run',
        ] );
    }

    private function tab( string $id ): void {
        if ( apply_filters( 'smpi_render_dashboard_tab', false, $id ) ) {
            return;
        }

        if ( 'publication_options' === $id ) { $this->publication_options(); return; }
        if ( 'profiles' === $id ) { $this->profiles(); return; }
        if ( 'brand' === $id ) { $this->brand(); return; }
        if ( 'shortcodes' === $id ) { $this->shortcodes(); return; }
        if ( 'schema' === $id ) { $this->schema(); return; }
        if ( 'reports' === $id ) { $this->reports(); return; }
        if ( "custom_fields" === $id ) { $this->custom_fields(); return; }
        if ( 'features' === $id ) { $this->features(); return; }
        if ( 'ui_cleanup' === $id ) { $this->ui_cleanup(); return; }
        if ( 'optimization' === $id ) { $this->optimization(); return; }
        if ( 'pages' === $id ) { $this->pages(); return; }
        if ( 'verified_profiles' === $id ) { $this->verified_profiles(); return; }
        if ( 'integrations' === $id ) { $this->integrations(); return; }
        if ( 'quick_run' === $id ) { echo '<div class="smpi-panel"><h2>Quick Run</h2><p>Reserved for safe setup scripts once the exact setup sequence is supplied.</p></div>'; return; }
        $this->overview();
    }

    private function overview(): void {
        $settings = Settings::all();
        ?>
        <div class="smpi-hero">
            <p class="smpi-kicker">Publication OS</p>
            <h2>Publication profiles, schema, dependency checks, page assignments &amp; performance reporting.</h2>
            <p>Front-end code runs only where needed &mdash; archive filters, optional time formatting, and single/author social cleanup.</p>
        </div>
        <div class="smpi-panel smpi-system">
            <h2>System</h2>
            <dl class="smpi-defs">
                <div class="smpi-def"><dt>Namespace</dt><dd><code>smp_publication_integration</code></dd></div>
                <div class="smpi-def"><dt>Plugin slug</dt><dd><code>smp-publication-integration</code></dd></div>
                <div class="smpi-def"><dt>GitHub</dt><dd><code>mikeyperes/smp-publication-integration</code></dd></div>
                <div class="smpi-def"><dt>Debug URL</dt><dd><code><?php echo esc_html( rest_url( 'smpi/v1/debug' ) ); ?></code></dd></div>
                <div class="smpi-def smpi-def-block"><dt>HWS masked admin</dt><dd><?php echo $this->hws_masked_login_report_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
            </dl>
        </div>
        <?php $this->publication_mapping_panel( $settings ); ?>
        <div class="smpi-panel"><h2>Core Settings</h2><table class="widefat striped"><tbody>
            <?php $this->toggle( 'founders_enabled', 'Show founder marketing', $settings ); ?>
            <?php $this->toggle( 'shadow_press_releases', 'Shadow all press releases from home/category/tag', $settings ); ?>
            <?php $this->toggle( 'author_social_cleanup', 'Hide empty author social icons', $settings ); ?>
            <?php $this->toggle( 'public_debug_enabled', 'Public safe debug endpoint', $settings ); ?>
            <tr><th>Post time display</th><td><select class="smpi-setting" data-key="post_time_mode"><option value="native" <?php selected( $settings['post_time_mode'], 'native' ); ?>>Native WordPress output</option><option value="relative_then_date" <?php selected( $settings['post_time_mode'], 'relative_then_date' ); ?>>5 min ago, then friendly date after 24 hours</option><option value="friendly_date" <?php selected( $settings['post_time_mode'], 'friendly_date' ); ?>>Always friendly date</option></select><span class="spinner"></span><span class="smpi-save-state"></span></td></tr>
        </tbody></table></div>
        <?php
    }


    private function publication_mapping_panel( array $settings ): void {
        $user_id = isset( $settings["system_publication_user_id"] ) ? absint( $settings["system_publication_user_id"] ) : 0;

        $locked = $user_id > 0;
        echo "<div class=\"smpi-panel smpi-publication-map smpi-publication-author-panel\">";
        echo "<div class=\"smpi-overview-section\"><p class=\"smpi-kicker\">Current Publication</p><h2>Main Publication Profile</h2><p>Choose the WordPress profile that represents this publication on the front end. Search by name, username, or email &mdash; your choice locks in and saves automatically.</p></div>";
        echo "<div class=\"smpi-author-binding-layout\">";
        echo "<div class=\"smpi-user-picker" . ( $locked ? " is-locked" : "" ) . "\" data-selected-user=\"" . esc_attr( (string) $user_id ) . "\">";
        echo "<input type=\"hidden\" class=\"smpi-setting smpi-publication-user-setting\" data-key=\"system_publication_user_id\" value=\"" . esc_attr( (string) $user_id ) . "\">";
        echo "<div class=\"smpi-locked-view\"><div class=\"smpi-locked-bar\">" . $this->ico( true ) . "<span class=\"smpi-locked-label\">Main publication profile</span><span class=\"smpi-pill smpi-pill--saved\">Saved</span><button type=\"button\" class=\"button smpi-change-user\">Change profile</button></div>";
        echo "<div class=\"smpi-current-user-summary\">" . $this->publication_user_card_html( $user_id ) . "</div></div>";
        echo "<div class=\"smpi-edit-view\"><div class=\"smpi-author-search-card\">";
        echo "<label for=\"smpi-publication-user-search\"><strong>Search for a publication profile</strong></label><p class=\"smpi-muted\">Search by publication name, username, or email, then click a result to lock it in. It saves automatically.</p>";
        echo "<input id=\"smpi-publication-user-search\" type=\"search\" class=\"regular-text smpi-user-search\" placeholder=\"Name, username, or email\" autocomplete=\"off\">";
        echo "<div class=\"smpi-user-results\" aria-live=\"polite\"></div>";
        echo "<div class=\"smpi-edit-actions\">" . ( $locked ? "<button type=\"button\" class=\"button smpi-cancel-user\">Cancel</button>" : "" ) . "<button type=\"button\" class=\"button-link smpi-clear-user\">Clear selection</button><span class=\"spinner\"></span><span class=\"smpi-save-state\"></span></div>";
        echo "</div></div>";
        echo "</div></div>";
        $this->founder_profiles_panel();
        echo "</div>";
    }

    private function founder_profiles_panel(): void {
        echo "<div class=\"smpi-founder-profile-panel\"><div class=\"smpi-founder-header\"><h3>Founder Profiles</h3><p class=\"smpi-muted\">Requires the Verified Profiles integration. Select founder records from the profile post type.</p></div>";

        $readiness = Dependencies::verified_profiles_readiness();
        if ( empty( $readiness["plugin_active"] ) || empty( $readiness["profile_cpt"] ) || empty( $readiness["profile_acf"] ) ) {
            echo "<div class=\"smpi-alert smpi-alert-warning\"><strong>Verified Profiles setup required.</strong><p>Founder selection unlocks once all three requirements below are met.</p><div class=\"smpi-status-rows\"><div class=\"smpi-status-row\">" . $this->ico( ! empty( $readiness["plugin_active"] ), true ) . "<span>Verified Profiles plugin active</span></div><div class=\"smpi-status-row\">" . $this->ico( ! empty( $readiness["profile_cpt"] ), true ) . "<span>Profile content type active</span></div><div class=\"smpi-status-row\">" . $this->ico( ! empty( $readiness["profile_acf"] ), true ) . "<span>Profile ACF fields enabled</span></div></div>" . $this->verified_profiles_setup_actions_html( $readiness ) . "</div></div>";
            return;
        }

        $ids = $this->founder_profile_ids();
        echo "<div class=\"smpi-profile-picker\"><div class=\"smpi-smart-profile-search\">";
        ( new SmartSearchRenderer() )->render(
            [
                "id"          => "smpi-founder-profile-core-search",
                "label"       => "Add founder profile",
                "placeholder" => "Search verified profile records",
                "source"      => "smpi_profiles",
                "post_type"   => "profile",
                "limit"       => 20,
            ]
        );
        echo "<p class=\"smpi-muted\">Search verified profile posts by name, then add the founder profile to this publication.</p></div><span class=\"spinner\"></span><span class=\"smpi-save-state\"></span><div class=\"smpi-profile-results\" aria-live=\"polite\"></div>";
        echo "<div class=\"smpi-founder-selected\">";
        if ( empty( $ids ) ) {
            echo $this->empty_founder_profiles_html();
        } else {
            foreach ( $ids as $id ) {
                echo $this->founder_profile_card_html( $id );
            }
        }
        echo "</div></div></div>";
    }

    private function verified_profiles_setup_actions_html( array $readiness ): string {
        $actions = [];
        $actions[] = "<a class=\"button\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( $readiness["settings_url"] ?? admin_url( "options-general.php?page=smp-verified-profiles" ) ) . "\">Open Verified Profiles settings</a>";

        if ( empty( $readiness["profile_cpt"] ) ) {
            $actions[] = "<a class=\"button button-primary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( wp_nonce_url( admin_url( "admin-post.php?action=smpi_enable_verified_profile_snippet&snippet=register_profile_custom_post_type" ), "smpi_enable_verified_profile_snippet" ) ) . "\">Enable profile content type</a>";
        }

        if ( empty( $readiness["profile_acf"] ) ) {
            $actions[] = "<a class=\"button button-primary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( wp_nonce_url( admin_url( "admin-post.php?action=smpi_enable_verified_profile_snippet&snippet=register_profile_general_acf_fields" ), "smpi_enable_verified_profile_snippet" ) ) . "\">Enable profile ACF fields</a>";
        }

        return "<p class=\"smpi-action-row\">" . implode( " ", $actions ) . "</p>";
    }

    private function founder_profile_ids(): array {
        $ids = [];
        $rows = function_exists( "get_field" ) ? get_field( "smpi_founder_profiles", "option" ) : [];
        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $value = is_array( $row ) && isset( $row["profile"] ) ? $row["profile"] : $row;
                if ( is_object( $value ) && isset( $value->ID ) ) {
                    $value = $value->ID;
                }
                $id = absint( $value );
                if ( $id && "profile" === get_post_type( $id ) ) {
                    $ids[] = $id;
                }
            }
        }

        if ( empty( $ids ) && function_exists( "get_field" ) ) {
            $legacy = get_field( "smpi_founders", "option" );
            $legacy = is_array( $legacy ) ? $legacy : [ $legacy ];
            foreach ( $legacy as $value ) {
                if ( is_object( $value ) && isset( $value->ID ) ) {
                    $value = $value->ID;
                }
                $id = absint( $value );
                if ( $id && "profile" === get_post_type( $id ) ) {
                    $ids[] = $id;
                }
            }
        }

        $fallback = get_option( "smpi_founder_profile_ids", [] );
        if ( empty( $ids ) && is_array( $fallback ) ) {
            foreach ( $fallback as $value ) {
                $id = absint( $value );
                if ( $id && "profile" === get_post_type( $id ) ) {
                    $ids[] = $id;
                }
            }
        }

        return array_values( array_unique( $ids ) );
    }

    private function founder_profile_card_html( int $profile_id ): string {
        $post = get_post( $profile_id );
        if ( ! $post || "profile" !== get_post_type( $post ) ) {
            return "";
        }
        $thumb = get_the_post_thumbnail_url( $post, "thumbnail" );
        $media = $thumb ? "<img src=\"" . esc_url( $thumb ) . "\" alt=\"\">" : "<span class=\"dashicons dashicons-id-alt\"></span>";
        return "<div class=\"smpi-founder-profile-card\" data-profile-id=\"" . esc_attr( (string) $profile_id ) . "\"><div class=\"smpi-founder-thumb\">" . $media . "</div><div class=\"smpi-founder-info\"><strong>" . esc_html( get_the_title( $post ) ) . "</strong><p class=\"smpi-muted\">Profile #" . esc_html( (string) $profile_id ) . "</p><p><a class=\"button button-secondary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( get_edit_post_link( $profile_id, "raw" ) ) . "\">Edit Profile</a> <a class=\"button button-secondary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( get_permalink( $profile_id ) ) . "\">View Profile</a> <button type=\"button\" class=\"button smpi-remove-founder-profile\">Remove</button></p></div></div>";
    }

    private function empty_founder_profiles_html(): string {
        return "<div class=\"smpi-empty-state smpi-empty-founder-profiles\"><strong>No founder profiles selected.</strong><p>Use the search above to add founder records from Verified Profiles.</p></div>";
    }

    private function selected_user_label( int $user_id ): string {
        $user = $user_id ? get_user_by( "id", $user_id ) : false;
        return $user ? $user->display_name : "";
    }

    private function publication_user_card_html( int $user_id ): string {
        $user = $user_id ? get_user_by( "id", $user_id ) : false;
        if ( ! $user ) {
            return "<div class=\"smpi-empty-state\"><strong>No main publication profile selected.</strong><p>Search by publication name, username, or email and choose the profile that represents this publication.</p></div>";
        }

        $title = $this->acf_user_value( $user_id, "title" );
        $bio_short = $this->acf_user_value( $user_id, "biography_short" );
        $bio = $this->acf_user_value( $user_id, "biography" );
        $mission = $this->acf_user_value( $user_id, "mission_statement" );
        $urls = $this->acf_user_value( $user_id, "urls" );
        $roles = is_array( $user->roles ) ? implode( ", ", $user->roles ) : "";
        $html = "<div class=\"smpi-profile-card\"><div class=\"smpi-profile-avatar\"><img src=\"" . esc_url( get_avatar_url( $user_id, [ "size" => 128 ] ) ) . "\" alt=\"" . esc_attr( $user->display_name ) . "\"></div><div class=\"smpi-profile-info\"><h3>" . esc_html( $user->display_name ) . "</h3>";
        if ( $title ) {
            $html .= "<p class=\"smpi-muted\">" . esc_html( $this->format_preview_value( $title ) ) . "</p>";
        }
        $html .= "<p><span class=\"dashicons dashicons-email\"></span> " . esc_html( $user->user_email ) . "</p>";
        if ( $roles ) {
            $html .= "<p class=\"smpi-muted\">Roles: " . esc_html( $roles ) . "</p>";
        }
        $html .= "<p><a class=\"button button-secondary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( get_edit_user_link( $user_id ) ) . "\">Edit Profile</a> <a class=\"button button-secondary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( get_author_posts_url( $user_id ) ) . "\">View Author Page</a></p></div></div>";

        $rows = [
            [ "Title", "title", $title, "[smp_publication_user field=\"title\"]" ],
            [ "Short Bio", "biography_short", $bio_short, "[author_bio_short]" ],
            [ "Biography", "biography", $bio, "[author_bio]" ],
            [ "Mission Statement", "mission_statement", $mission, "[smp_publication_field field=\"mission_statement\"]" ],
            [ "Social URLs", "urls", $urls, "[author_facebook] [author_instagram] [author_x] [author_youtube]" ],
        ];

        $html .= "<div class=\"smpi-profile-fields\">";
        foreach ( $rows as $row ) {
            $preview = $this->format_preview_value( $row[2] );
            if ( "" === $preview ) {
                continue;
            }
            $html .= "<div class=\"smpi-field-preview\"><div><strong>" . esc_html( $row[0] ) . "</strong> <code>" . esc_html( $row[3] ) . "</code></div><p>" . esc_html( wp_trim_words( wp_strip_all_tags( $preview ), 36, "..." ) ) . "</p></div>";
        }
        $html .= "</div>";
        return $html;
    }

    private function acf_user_value( int $user_id, string $field ) {
        if ( ! function_exists( "get_field" ) ) {
            return "";
        }
        return get_field( $field, "user_" . $user_id );
    }

    private function format_preview_value( $value ): string {
        if ( null === $value || false === $value || "" === $value ) {
            return "";
        }
        if ( is_array( $value ) ) {
            $flat = [];
            array_walk_recursive( $value, function ( $item ) use ( &$flat ): void {
                if ( is_scalar( $item ) && "" !== trim( (string) $item ) ) {
                    $flat[] = trim( (string) $item );
                }
            } );
            return implode( ", ", array_slice( array_unique( $flat ), 0, 8 ) );
        }
        if ( is_object( $value ) ) {
            return wp_json_encode( $value );
        }
        return trim( (string) $value );
    }


    private function publication_options(): void {
        echo "<div class=smpi-hero><p class=smpi-kicker>Theme Options</p><h2>Publication Options</h2><p>These fields describe the current publication for this website. They are rendered inside SMP settings, not on a separate ACF settings page.</p></div>";
        if ( ! Dependencies::acf_active() || ! function_exists( "acf_form" ) ) {
            echo "<div class=smpi-panel><h2>Advanced Custom Fields</h2><p><span class=smpi-warn>&#9888;</span> ACF is recommended but not active. SMP still runs, but publication option fields are unavailable until ACF is active.</p><p><a class=button href=" . esc_url( admin_url( "options-general.php?page=smp-publication-integration&tab=integrations" ) ) . ">Open Integrations</a></p></div>";
            return;
        }
        echo "<div class=smpi-panel><h2>Current Publication Fields</h2>";
        acf_form( [ "post_id" => "option", "field_groups" => [ "group_smpi_publication_profile" ], "form" => true, "submit_value" => "Save Publication Options", "updated_message" => "Publication options saved.", "return" => admin_url( "options-general.php?page=smp-publication-integration&tab=publication_options&updated=1" ) ] );
        echo "</div>";
        echo $this->publication_acf_shortcode_reference_html();
    }

    private function brand(): void {
        $enabled = (bool) get_option( "hws_brand_highlight_enabled", "1" );
        $background = sanitize_hex_color( (string) get_option( "hws_brand_highlight_background_color", "#facc15" ) ) ?: "#facc15";
        $text = sanitize_hex_color( (string) get_option( "hws_brand_highlight_text_color", "#111827" ) ) ?: "#111827";
        $edit = admin_url( "options-general.php?page=hws-core-tools&tab=brand-assets" );
        echo "<div class=smpi-hero><p class=smpi-kicker>Brand</p><h2>HWS Brand Assets</h2><p>SMP reports shared HWS brand values here. Editing remains owned by HWS Base Tools.</p></div>";
        echo "<div class=smpi-grid><div class=smpi-card><h3>Highlight override</h3><p>" . ( $enabled ? $this->ico( true ) . "Enabled" : $this->ico( false ) . "Disabled" ) . "</p></div><div class=smpi-card><h3>Highlight background</h3><p><span class=smpi-color-swatch style=background:" . esc_attr( $background ) . "></span> <code>" . esc_html( $background ) . "</code></p></div><div class=smpi-card><h3>Highlight text</h3><p><span class=smpi-color-swatch style=background:" . esc_attr( $text ) . "></span> <code>" . esc_html( $text ) . "</code></p></div></div>";
        echo "<div class=smpi-panel><h2>Edit Source</h2><p>These settings come from HWS Base Tools Brand Assets.</p><p><a class=button target=_blank rel=noopener href=" . esc_url( $edit ) . ">Open HWS Brand Assets</a></p></div>";
    }

    private function profiles(): void {
        echo "<div class=\"smpi-panel\"><h2>Publication Field Structure</h2><p>Publication details live on the site options page for the current publication. Author and founder fields connect those options to existing WordPress author profiles.</p><table class=\"widefat striped\"><tbody>";
        foreach ( [ "smpi_publication_user" => "Selected publication author", "smpi_founder_users" => "Founder author accounts", "smpi_founder_profiles" => "Founder profile repeater from Verified Profiles", "smpi_founders" => "Legacy verified profile relationship", "smpi_headquarters" => "Headquarters", "smpi_founding_date" => "Founding Date", "smpi_mission_statement" => "Mission Statement", "smpi_contact_email" => "Public contact email", "smpi_google_news_url" => "Google News URL", "_smpi_shadow_home" => "Hide from home query", "_smpi_shadow_archives" => "Hide from category/tag query" ] as $field => $label ) {
            echo "<tr><th><code>" . esc_html( $field ) . "</code></th><td>" . esc_html( $label ) . "</td></tr>";
        }
        echo "</tbody></table></div>";
    }


    private function shortcodes(): void {
        $user_id = $this->default_shortcode_user_id();
        ?>
        <div class="smpi-panel smpi-shortcode-debugger">
            <h2>Shortcodes</h2>
            <p>Select a WordPress author to debug author.php and single.php shortcode output. Rows show the provider, source fields, exact shortcode, and rendered value.</p>
            <div class="smpi-user-picker smpi-shortcode-user-picker" data-selected-user="<?php echo esc_attr( (string) $user_id ); ?>">
                <label><strong>Select author</strong></label>
                <p class="smpi-muted">Search by name, username, or email. The shortcode table refreshes without a page reload.</p>
                <input type="search" class="regular-text smpi-shortcode-user-search" placeholder="Name, username, or email" autocomplete="off">
                <span class="spinner"></span><span class="smpi-save-state"></span>
                <div class="smpi-shortcode-user-results smpi-user-results" aria-live="polite"></div>
            </div>
            <div id="smpi-shortcode-selected-user"><?php echo self::shortcode_selected_user_html( $user_id ); ?></div>
            <div id="smpi-shortcode-user-values"><?php echo self::shortcode_user_values_html( $user_id ); ?></div>
        </div>
        <?php
        echo $this->publication_acf_shortcode_reference_html();
        echo $this->post_acf_shortcode_reference_html();
    }

    private function default_shortcode_user_id(): int {
        $configured = absint( Settings::get( "system_publication_user_id", 0 ) );
        if ( $configured && get_user_by( "id", $configured ) ) {
            return $configured;
        }
        $users = get_users( [ "number" => 1, "orderby" => "post_count", "order" => "DESC", "who" => "authors", "fields" => [ "ID" ] ] );
        return ! empty( $users[0]->ID ) ? (int) $users[0]->ID : 0;
    }

    public static function shortcode_selected_user_html( int $user_id ): string {
        $user = $user_id ? get_user_by( "id", $user_id ) : false;
        if ( ! $user ) {
            return "<div class=\"smpi-empty-state\"><strong>No author selected.</strong><p>Search above to load shortcode values for a WordPress author.</p></div>";
        }
        $avatar = get_avatar_url( $user_id, [ "size" => 96 ] );
        $edit = get_edit_user_link( $user_id );
        $view = get_author_posts_url( $user_id );
        ob_start();
        ?>
        <div class="smpi-profile-card smpi-shortcode-selected-user">
            <div class="smpi-profile-avatar"><img src="<?php echo esc_url( $avatar ); ?>" alt=""></div>
            <div class="smpi-profile-info">
                <h3><?php echo esc_html( $user->display_name ); ?> <code>#<?php echo esc_html( (string) $user_id ); ?></code></h3>
                <p><span class="dashicons dashicons-email"></span> <?php echo esc_html( $user->user_email ); ?></p>
                <p><a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $edit ); ?>">Edit User</a> <a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $view ); ?>">View Author Page</a></p>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function shortcode_user_values_html( int $user_id ): string {
        if ( $user_id <= 0 || ! get_user_by( "id", $user_id ) ) {
            return "<div class=\"smpi-empty-state\"><strong>No shortcode values loaded.</strong><p>Select an author to render shortcode output.</p></div>";
        }
        $rows = self::shortcode_user_rows( $user_id );
        return ( new ShortcodeDisplayRenderer() )->render(
            array_map( [ self::class, 'shortcode_row_display_item' ], $rows ),
            [
                'title'       => 'Rendered shortcode output',
                'description' => 'Rows are generated through Hexa Plugin Core and show shortcode, description, current output, and parameter examples for the selected author.',
            ]
        );
    }

    private static function shortcode_row_display_item( array $row ): array {
        $shortcode = (string) ( $row['shortcode'] ?? '' );
        $tag = self::shortcode_tag_from_code( $shortcode );
        $parameters = self::shortcode_parameters_from_code( $shortcode );

        return [
            'label'       => $tag ?: (string) ( $row['group'] ?? 'Shortcode' ),
            'shortcode'   => $shortcode,
            'description' => self::shortcode_description_from_row( $row, $tag ),
            'provider'    => (string) ( $row['provider'] ?? '' ),
            'source'      => (string) ( $row['source'] ?? '' ),
            'output_html' => (string) ( $row['value'] ?? '' ),
            'evaluate'    => false,
            'examples'    => [
                [
                    'label'      => 'Current selected context',
                    'shortcode'  => $shortcode,
                    'parameters' => $parameters,
                ],
            ],
        ];
    }

    private static function shortcode_description_from_row( array $row, string $tag ): string {
        $group = (string) ( $row['group'] ?? '' );
        if ( 'Publication/global' === $group ) {
            return 'Renders publication-level data from SMP settings, publication options, assigned pages, or schema helpers.';
        }
        if ( 'Author/user.php' === $group ) {
            return 'Renders user profile data for the selected WordPress author.';
        }
        if ( 'Compatibility' === $group ) {
            return 'Legacy compatibility shortcode for existing MuckRack and ACF author-field templates.';
        }
        if ( 'External provider' === $group ) {
            return 'External provider shortcode listed for reference; SMP does not execute this row in the debugger.';
        }
        return '' !== $tag ? 'Displays the live output for [' . $tag . '].' : 'Displays the live output for this shortcode.';
    }

    private static function shortcode_tag_from_code( string $shortcode ): string {
        return preg_match( '/^\[([a-zA-Z0-9_\-]+)/', trim( $shortcode ), $matches ) ? (string) $matches[1] : '';
    }

    private static function shortcode_parameters_from_code( string $shortcode ): array {
        $parameters = [];
        if ( preg_match_all( '/([a-zA-Z0-9_\-]+)="([^"]*)"/', $shortcode, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $parameters[ (string) $match[1] ] = (string) $match[2];
            }
        }
        return $parameters;
    }

    private static function shortcode_user_rows( int $user_id ): array {
        $rows = [];
        foreach ( Shortcodes::shortcodes() as $tag => $callback ) {
            if ( 0 === strpos( $tag, "smp_post_" ) ) { continue; }
            $code = "[" . $tag . "]";
            $rows[] = [ "group" => "Publication/global", "provider" => "SMP Publication Integration", "source" => self::publication_shortcode_source( (string) $tag ), "shortcode" => $code, "value" => self::shortcode_value_html( $code ) ];
        }
        $aliases = AuthorShortcodes::field_aliases();
        foreach ( AuthorShortcodes::shortcodes() as $tag => $callback ) {
            $code = "[" . $tag . " user_id=\"" . $user_id . "\"";
            if ( "author_image" === $tag ) { $code .= " size=\"thumbnail\" output=\"url\""; }
            if ( "author_muckrack_verified" === $tag ) { $code .= " type=\"icon\" context=\"single_author\""; }
            $code .= "]";
            $rows[] = [ "group" => "Author/user.php", "provider" => "SMP Publication Integration", "source" => self::author_shortcode_source( (string) $tag, $aliases ), "shortcode" => $code, "value" => self::shortcode_value_html( $code ) ];
        }
        $legacy = [ "[acf_author_field field=\"muckrack_url\" user_id=\"" . $user_id . "\"]", "[muckrack_verified user_id=\"" . $user_id . "\" type=\"icon\"]", "[muckrack_verified user_id=\"" . $user_id . "\" type=\"text\"]" ];
        foreach ( $legacy as $code ) {
            $rows[] = [ "group" => "Compatibility", "provider" => "SMP MuckRack compatibility", "source" => "ACF/user fields: muckrack_verified, muckrack_url, what_best_describe_you", "shortcode" => $code, "value" => self::shortcode_value_html( $code ) ];
        }
        return array_merge( $rows, self::external_shortcode_rows( $user_id ) );
    }

    private static function publication_shortcode_source( string $tag ): string {
        $map = [ "smp_publication_field" => "Publication option ACF field parameter", "smp_publication_mission_statement" => "mission_statement publication option", "smp_publication_founders" => "smpi_founder_profiles option / profile CPT", "smp_publication_user" => "system_publication_user_id setting", "smp_publication_profile" => "publication options and mapped user", "smp_publication_validate_schema" => "schema integrity report", "smp_publication_page" => "page_assignments option", "smp_publication_debug_url" => "public debug endpoint setting" ];
        return $map[ $tag ] ?? "SMP publication option";
    }

    private static function author_shortcode_source( string $tag, array $aliases ): string {
        $key = str_replace( "author_", "", $tag );
        if ( "muck_rack" === $key ) { $key = "muckrack"; }
        if ( "muckrack_verified" === $key ) { return "ACF/user fields: muckrack_verified, muckrack_url, what_best_describe_you"; }
        return isset( $aliases[ $key ] ) ? "ACF/user meta aliases: " . implode( ", ", $aliases[ $key ] ) : "WordPress user meta";
    }

    private static function external_shortcode_rows( int $user_id ): array {
        $rows = [];
        $external = [ [ "HWS Base Tools", "[founder id=\"url_facebook\"]", "External founder/company shortcode provider" ], [ "HWS Base Tools", "[company id=\"subtitle\"]", "External founder/company shortcode provider" ], [ "SMP Verified Profiles", "[get_profile_field field=\"title\"]", "External profile CPT shortcode provider" ] ];
        foreach ( $external as $row ) {
            $rows[] = [ "group" => "External provider", "provider" => $row[0], "source" => $row[2], "shortcode" => $row[1], "value" => "<span class=\"smpi-muted\">External provider shortcode. SMP lists it but does not execute it in this debugger.</span>" ];
        }
        $providers = [];
        if ( function_exists( "smp_vp_discover_shortcodes" ) ) { $providers[] = smp_vp_discover_shortcodes(); }
        $fn = "smp_verified_profiles\get_verified_profile_shortcodes";
        if ( function_exists( $fn ) ) { $providers[] = $fn(); }
        foreach ( $providers as $provider_rows ) {
            if ( ! is_array( $provider_rows ) ) { continue; }
            foreach ( $provider_rows as $key => $value ) {
                $tag = is_string( $key ) ? $key : ( is_string( $value ) ? $value : "" );
                if ( "" === $tag ) { continue; }
                $code = 0 === strpos( $tag, "[" ) ? $tag : "[" . trim( $tag ) . "]";
                $rows[] = [ "group" => "External provider", "provider" => "SMP Verified Profiles", "source" => "Discovered provider shortcode", "shortcode" => $code, "value" => "<span class=\"smpi-muted\">External provider shortcode. SMP lists it but does not execute it in this debugger.</span>" ];
            }
        }
        return $rows;
    }

    private static function shortcode_value_html( string $code ): string {
        $value = do_shortcode( $code );
        if ( $value === $code ) {
            return "<span class=\"smpi-muted\">Provider not active or shortcode not registered here.</span>";
        }
        $text = trim( wp_strip_all_tags( (string) $value ) );
        if ( "" === $text ) {
            return "<span class=\"smpi-muted\">Empty</span>";
        }
        return "<code>" . esc_html( wp_trim_words( $text, 22 ) ) . "</code>";
    }

    private function publication_acf_shortcode_reference_html(): string {
        $fields = [];
        if ( function_exists( "acf_get_fields" ) ) {
            $acf_fields = acf_get_fields( "group_smpi_publication_profile" );
            $fields = is_array( $acf_fields ) ? $acf_fields : [];
        }
        if ( empty( $fields ) && function_exists( "acf_get_local_field_group" ) ) {
            $group = acf_get_local_field_group( "group_smpi_publication_profile" );
            $fields = is_array( $group ) && isset( $group["fields"] ) && is_array( $group["fields"] ) ? $group["fields"] : [];
        }
        $html = "<div class=smpi-panel><h2>Publication Options ACF shortcode examples</h2><p>Every publication option field can be rendered with <code>[smp_publication_field]</code>. Repeater rows use <code>row=1</code> and <code>sub_field</code>.</p><table class=widefat><thead><tr><th>Field</th><th>ACF name</th><th>Primary shortcode</th><th>Variations / parameters</th></tr></thead><tbody>";
        foreach ( $fields as $field ) {
            if ( empty( $field["name"] ) ) {
                continue;
            }
            $name = (string) $field["name"];
            $label = ! empty( $field["label"] ) ? (string) $field["label"] : $name;
            $variation = "[smp_publication_field field=" . $name . " format=text]";
            if ( isset( $field["type"] ) && "repeater" === $field["type"] && ! empty( $field["sub_fields"] ) && is_array( $field["sub_fields"] ) ) {
                $examples = [];
                foreach ( $field["sub_fields"] as $sub_field ) {
                    if ( ! empty( $sub_field["name"] ) ) {
                        $examples[] = "[smp_publication_field field=" . $name . " row=1 sub_field=" . $sub_field["name"] . "]";
                    }
                }
                $variation = implode( " ", $examples );
            } elseif ( "smpi_publication_user" === $name ) {
                $variation = "[smp_publication_user]";
            } elseif ( "smpi_publication_muckrack_verified" === $name ) {
                $variation = "[smp_publication_muckrack_verified] [smp_publication_field field=publication_muckrack_verified]";
            } elseif ( in_array( $name, [ "smpi_founder_users", "smpi_publication_logo", "smpi_schema_markup" ], true ) ) {
                $variation = "[smp_publication_field field=" . $name . " format=json]";
            }
            $html .= "<tr><td>" . esc_html( $label ) . "</td><td><code>" . esc_html( $name ) . "</code></td><td><code>" . esc_html( "[smp_publication_field field=" . $name . "]" ) . "</code></td><td><code>" . esc_html( $variation ) . "</code></td></tr>";
        }
        return $html . "</tbody></table></div>";
    }

    private function pages_shortcode_reference_html(): string {
        $settings = Settings::all();
        $variables = [
            [ "Publication name", "[smp_publication_field field=legal_name format=text]", get_bloginfo( "name" ) ],
            [ "Publication URL", "[smp_publication_field field=website format=text]", home_url( "/" ) ],
            [ "Mission statement", "[smp_publication_field field=mission_statement format=text]", "" ],
            [ "Publication summary", "[smp_publication_field field=summary format=text]", "" ],
            [ "Public contact email", "[smp_publication_field field=contact_email format=text]", "" ],
            [ "Debug JSON URL", "[smp_publication_debug_url]", rest_url( "smpi/v1/debug" ) ],
        ];

        ob_start();
        ?>
        <div class="smpi-panel smpi-shortcode-reference smpi-pages-shortcodes">
            <h2>Page and publication shortcodes</h2>
            <p>Use these in starter pages so publication names, URLs, policy links, and profile values stay dynamic.</p>
            <h3>Publication variables</h3>
            <table class="widefat striped"><thead><tr><th>Use</th><th>Shortcode</th><th>Current value</th></tr></thead><tbody>
            <?php foreach ( $variables as $row ) :
                $value = self::shortcode_value_html( (string) $row[1] );
                if ( false !== strpos( $value, "smpi-muted" ) && "" !== (string) $row[2] ) {
                    $value = "<code>" . esc_html( (string) $row[2] ) . "</code>";
                }
            ?>
                <tr><td><?php echo esc_html( (string) $row[0] ); ?></td><td><code><?php echo esc_html( (string) $row[1] ); ?></code></td><td><?php echo $value; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <h3>Assigned page links</h3>
            <table class="widefat striped"><thead><tr><th>Page requirement</th><th>URL shortcode</th><th>Link shortcode</th><th>Current page URL</th></tr></thead><tbody>
            <?php foreach ( Settings::page_types() as $type => $config ) :
                $page_id = Settings::page_assignment_id( $type );
                $url = $page_id ? Settings::page_slug_url( $page_id ) : "";
                $url_code = "[smp_publication_page type=" . $type . " mode=url]";
                $link_code = "[smp_publication_page type=" . $type . " mode=link]";
            ?>
                <tr><td><?php echo esc_html( (string) $config["label"] ); ?></td><td><code><?php echo esc_html( $url_code ); ?></code></td><td><code><?php echo esc_html( $link_code ); ?></code></td><td><?php echo $url ? "<a href=\"" . esc_url( $url ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">" . esc_html( $url ) . "</a>" : "<span class=\"smpi-muted\">Not assigned</span>"; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function post_acf_shortcode_reference_html(): string {
        $rows = [
            [ "Post Summary", "post_summary", "[smp_post_summary]", "[smp_post_acf field=post_summary] [smp_post_summary post_id=123 format=text]" ],
            [ "Post FAQs Structured Repeater", "post_faq_items", "[smp_post_faqs]", "[smp_post_acf field=post_faq_items format=json] [smp_post_faqs post_id=123 format=text]" ],
        ];
        $html = "<div class=smpi-panel><h2>Post ACF add-on shortcode examples</h2><p>These match the Post ACF add-ons toggles. They resolve the current post inside single.php; pass <code>post_id</code> when testing elsewhere.</p><table class=widefat><thead><tr><th>Field</th><th>ACF name</th><th>Primary shortcode</th><th>Variations / parameters</th></tr></thead><tbody>";
        foreach ( $rows as $row ) {
            $html .= "<tr><td>" . esc_html( $row[0] ) . "</td><td><code>" . esc_html( $row[1] ) . "</code></td><td><code>" . esc_html( $row[2] ) . "</code></td><td><code>" . esc_html( $row[3] ) . "</code></td></tr>";
        }
        return $html . "</tbody></table></div>";
    }


    private function article_type_selector_options_html(): string {
        $html = "<div class=smpi-control-group><h3>Allowed article type schema objects</h3><table class=widefat><thead><tr><th>Editor choice</th><th>Term slug</th><th>Schema object</th><th>Use case</th></tr></thead><tbody>";
        foreach ( \smp_publication_integration\Content\ArticleTypes::terms() as $slug => $config ) {
            $html .= "<tr><td>" . esc_html( $config["label"] ) . "</td><td><code>" . esc_html( $slug ) . "</code></td><td><code>" . esc_html( $config["schema_type"] ) . "</code></td><td>" . esc_html( $config["description"] ) . "</td></tr>";
        }
        return $html . "</tbody></table></div>";
    }

    private function article_type_selector_report_html(): string {
        $enabled = \smp_publication_integration\Content\ArticleTypes::is_enabled();
        $registered = taxonomy_exists( \smp_publication_integration\Content\ArticleTypes::TAXONOMY );
        $post_types = \smp_publication_integration\Content\ArticleTypes::supported_post_types();
        $html = $this->simple_status_html( ! $enabled || $registered, $enabled ? "Enabled: the taxonomy is registered and uses the radio-only metabox." : "Disabled: the Article Types metabox is hidden and schema falls back to post type defaults." );
        $html .= "<table class=widefat><tbody>";
        $html .= "<tr><th>Setting key</th><td><code>article_types_enabled</code></td></tr>";
        $html .= "<tr><th>Taxonomy</th><td><code>" . esc_html( \smp_publication_integration\Content\ArticleTypes::TAXONOMY ) . "</code></td></tr>";
        $html .= "<tr><th>Registered now</th><td>" . esc_html( $registered ? "yes" : "no" ) . "</td></tr>";
        $html .= "<tr><th>Post types</th><td><code>" . esc_html( implode( ", ", $post_types ) ) . "</code></td></tr>";
        $html .= "<tr><th>Editor behavior</th><td>One radio selection only. No Add field. No free-text term creation.</td></tr>";
        return $html . "</tbody></table>";
    }



    private function article_type_selector_report_text(): string {
        $enabled = \smp_publication_integration\Content\ArticleTypes::is_enabled();
        $registered = taxonomy_exists( \smp_publication_integration\Content\ArticleTypes::TAXONOMY );
        $post_types = \smp_publication_integration\Content\ArticleTypes::supported_post_types();
        return "Article type selector is " . ( $enabled ? "enabled" : "disabled" ) . "; taxonomy registered: " . ( $registered ? "yes" : "no" ) . "; post types: " . implode( ", ", $post_types ) . ".";
    }

    private function schema(): void {
        $schema = new Schema();
        $post_id = $this->latest_post_id();
        $home_schema = $schema->generate_home_schema_array();
        $single_schema = $post_id ? $schema->generate_single_schema_array( $post_id ) : [];
        $home_report = Schema::integrity_report( 0 );
        $single_report = $post_id ? Schema::integrity_report( $post_id ) : [ "types" => [], "checks" => [] ];
        $debug_url = rest_url( "smpi/v1/schema" );
        $single_debug_url = $post_id ? add_query_arg( "post_id", $post_id, $debug_url ) : $debug_url;

        echo "<div class=\"smpi-hero\"><p class=\"smpi-kicker\">Schema</p><h2>Publication and article schema integrity</h2><p>Home uses NewsMediaOrganization, WebSite, CollectionPage, and ItemList. Singles use WebPage, article schema, publisher, author, image, breadcrumbs, and FAQPage when structured FAQ rows exist.</p></div>";
        echo "<div class=\"smpi-grid\">";
        $this->status_card( "Home schema graph", in_array( "NewsMediaOrganization", $home_report["types"], true ) && in_array( "CollectionPage", $home_report["types"], true ), "Types: " . implode( ", ", $home_report["types"] ) );
        $this->status_card( "Single schema graph", $post_id && in_array( "WebPage", $single_report["types"], true ), $post_id ? "Latest post #" . $post_id . " types: " . implode( ", ", $single_report["types"] ) : "No published post found." );
        $article_types_enabled = \smp_publication_integration\Content\ArticleTypes::is_enabled();
        $article_types_taxonomy_ready = taxonomy_exists( \smp_publication_integration\Content\ArticleTypes::TAXONOMY );
        $this->status_card( "Article type selector", ! $article_types_enabled || $article_types_taxonomy_ready, $article_types_enabled ? "Enabled and taxonomy registered." : "Feature disabled; schema falls back by post type." );
        $this->status_card( "Debug JSON URL", true, esc_url( $debug_url ) );
        echo "</div>";

        echo "<div class=\"smpi-panel\"><h2>Run Schema Integrity Test</h2><p><button id=\"smpi-reprocess-schema\" type=\"button\" class=\"button button-primary\">Run schema refresh and sample test</button></p><p><a class=\"button\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( $debug_url ) . "\">Open home schema JSON</a> " . ( $post_id ? "<a class=\"button\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( $single_debug_url ) . "\">Open latest post schema JSON</a>" : "" ) . "</p><div id=\"smpi-schema-report\" class=\"smpi-code-panel\"></div></div>";

        echo $this->schema_detection_report_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        echo "<div class=\"smpi-panel\"><h2>Home Page Integrity</h2><table class=\"widefat striped\"><tbody>";
        foreach ( $home_report["checks"] as $check ) {
            $class = "green" === $check["status"] ? "smpi-ok" : ( "red" === $check["status"] ? "smpi-bad" : "smpi-warn" );
            echo "<tr><th>" . esc_html( $check["label"] ) . "</th><td><span class=\"" . esc_attr( $class ) . "\">" . esc_html( strtoupper( $check["status"] ) ) . "</span></td></tr>";
        }
        echo "</tbody></table></div>";

        echo "<div class=\"smpi-panel\"><h2>Single Page Integrity</h2><table class=\"widefat striped\"><tbody>";
        foreach ( $single_report["checks"] as $check ) {
            $class = "green" === $check["status"] ? "smpi-ok" : ( "red" === $check["status"] ? "smpi-bad" : "smpi-warn" );
            echo "<tr><th>" . esc_html( $check["label"] ) . "</th><td><span class=\"" . esc_attr( $class ) . "\">" . esc_html( strtoupper( $check["status"] ) ) . "</span></td></tr>";
        }
        echo "</tbody></table></div>";

        echo "<div class=\"smpi-panel\"><h2>Article Type Taxonomy Mapping</h2><table class=\"widefat striped\"><thead><tr><th>Term</th><th>Schema Type</th><th>Use Case</th></tr></thead><tbody>";
        foreach ( \smp_publication_integration\Content\ArticleTypes::terms() as $slug => $config ) {
            echo "<tr><td><code>" . esc_html( $slug ) . "</code></td><td><code>" . esc_html( $config["schema_type"] ) . "</code></td><td>" . esc_html( $config["description"] ) . "</td></tr>";
        }
        echo "</tbody></table></div>";

        $ideal_home = [ "@context" => "https://schema.org", "@graph" => [ [ "@type" => "NewsMediaOrganization" ], [ "@type" => "WebSite" ], [ "@type" => "CollectionPage" ], [ "@type" => "ItemList" ] ] ];
        $ideal_single = [ "@context" => "https://schema.org", "@graph" => [ [ "@type" => "NewsMediaOrganization" ], [ "@type" => "WebSite" ], [ "@type" => "WebPage" ], [ "@type" => "NewsArticle" ], [ "@type" => "Person" ], [ "@type" => "ImageObject" ], [ "@type" => "BreadcrumbList" ], [ "@type" => "FAQPage" ] ] ];
        echo "<div class=\"smpi-grid\"><div class=\"smpi-panel\"><h2>Ideal Home Graph</h2><pre class=\"smpi-code\">" . esc_html( wp_json_encode( $ideal_home, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . "</pre></div><div class=\"smpi-panel\"><h2>Actual Home Graph</h2><pre class=\"smpi-code\">" . esc_html( wp_json_encode( $home_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . "</pre></div></div>";
        if ( $post_id ) {
            echo "<div class=\"smpi-grid\"><div class=\"smpi-panel\"><h2>Ideal Single Graph</h2><pre class=\"smpi-code\">" . esc_html( wp_json_encode( $ideal_single, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . "</pre></div><div class=\"smpi-panel\"><h2>Actual Latest Single Graph</h2><pre class=\"smpi-code\">" . esc_html( wp_json_encode( $single_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . "</pre></div></div>";
        }
    }


    private function reports(): void {
        echo '<div class="smpi-grid">';
        foreach ( $this->counts() as $type => $count ) {
            $this->card( ucwords( str_replace( '-', ' ', $type ) ), '<strong>' . esc_html( (string) $count ) . '</strong>' );
        }
        echo '</div><div class="smpi-panel"><h2>Author ACF Field Sources</h2><table class="widefat striped"><tbody>';
        foreach ( [ 'urls' => 'HWS/SFPF/HPR user ACF social URL group', 'facebook_url' => 'SFPF legacy user field', 'instagram_url' => 'SFPF legacy user field', 'twitter_url' => 'SFPF legacy user field', 'linkedin_url' => 'SFPF legacy user field', 'biography' => 'HWS/SFPF user field', 'title' => 'HWS/SFPF user field' ] as $field => $source ) {
            echo '<tr><th><code>' . esc_html( $field ) . '</code></th><td>' . esc_html( $source ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }





    private function custom_fields(): void {
        $settings = Settings::all();
        $acf_active = Dependencies::acf_active();
        $post_header_group_registered = function(): bool {
            return function_exists( "acf_get_field_group" ) && (bool) acf_get_field_group( "group_64a7290b61191" );
        };
        $visibility_group_registered = function(): bool {
            return function_exists( "acf_get_field_group" ) && (bool) acf_get_field_group( "group_smpi_visibility_controls" );
        };

        $definitions = [
            [
                "id" => "publication_options_acf",
                "label" => "Publication Options ACF",
                "type" => "acf",
                "enabled" => true,
                "registered" => function() use ( $acf_active ): bool { return $acf_active && function_exists( "acf_get_field_group" ) && (bool) acf_get_field_group( "group_smpi_publication_profile" ); },
                "acf_group_key" => "group_smpi_publication_profile",
                "object_name" => "smp-publication-integration options page",
                "location" => "ACF options page: smp-publication-integration",
                "description" => "Main publication profile fields, schema policy references, contact points, founder setup guidance, and fallback publication identity fields.",
                "instructions" => "Edit these fields from the Publication Options tab. They stay always on because SMP schema and shortcodes depend on them.",
                "fields" => [ "smpi_publication_user", "smpi_founder_profiles", "smpi_publication_summary", "smpi_publication_logo", "schema policy pages", "contactPoint", "postalAddress", "smpi_schema_markup" ],
                "dependencies" => [ "ACF Pro", "SMP publication options page" ],
                "code_example" => "[smp_publication_field field=legal_name format=text]",
                "test_report" => "ACF is " . ( $acf_active ? "active" : "inactive" ) . "; publication options group should be registered on the options page.",
            ],
            [
                "id" => "post_summary_acf",
                "label" => "Post Summary ACF",
                "type" => "acf",
                "setting_key" => "post_summary_acf_enabled",
                "enabled" => Settings::bool( "post_summary_acf_enabled" ),
                "registered" => function(): bool { return Settings::bool( "post_summary_acf_enabled" ) && function_exists( "acf_get_field" ) && (bool) acf_get_field( "field_65ab7ba0e849b" ); },
                "acf_group_key" => "group_64a7290b61191",
                "object_name" => "post_summary",
                "location" => "post, press-release, imported-news editors",
                "description" => "Optional article summary field used by shortcodes and single article treatments.",
                "instructions" => "Enable this when editors need a reusable post summary separate from the article body. Styling controls remain in Features.",
                "fields" => [ "post_summary" ],
                "dependencies" => [ "ACF Pro", "Post Header local field group" ],
                "code_example" => "[smp_post_summary style=\"sum00\"]",
                "test_report" => $this->post_acf_addons_report_text(),
            ],
            [
                "id" => "post_faq_acf",
                "label" => "Post FAQ ACF",
                "type" => "acf",
                "setting_key" => "post_faqs_acf_enabled",
                "enabled" => Settings::bool( "post_faqs_acf_enabled" ),
                "registered" => function(): bool { return Settings::bool( "post_faqs_acf_enabled" ) && function_exists( "acf_get_field" ) && (bool) acf_get_field( "field_smpi_post_faq_items" ); },
                "acf_group_key" => "group_64a7290b61191",
                "object_name" => "post_faq_items",
                "location" => "post, press-release, imported-news editors",
                "description" => "Structured FAQ rows that power FAQPage schema and display shortcodes.",
                "instructions" => "Enable this when article editors need repeatable question and answer rows. Schema rows can be disabled one by one in the editor.",
                "fields" => [ "question", "answer", "enabled_for_schema" ],
                "dependencies" => [ "ACF Pro", "FAQPage schema output", "SMP post FAQ shortcode" ],
                "code_example" => "[smp_post_faqs style=\"faq02\"]",
                "test_report" => $this->post_acf_addons_report_text(),
            ],
            [
                "id" => "shadow_visibility_acf",
                "label" => "Post Shadow Visibility ACF",
                "type" => "acf",
                "setting_key" => "shadow_posts_enabled",
                "enabled" => Settings::bool( "shadow_posts_enabled" ),
                "registered" => function() use ( $visibility_group_registered ): bool { return Settings::bool( "shadow_posts_enabled" ) && $visibility_group_registered(); },
                "acf_group_key" => "group_smpi_visibility_controls",
                "object_name" => "_smpi_shadow_complete, _smpi_shadow_home",
                "location" => "post and press-release side metabox",
                "description" => "Editor controls that hide posts from archive queries while keeping direct URLs accessible.",
                "instructions" => "Enable this for editorial shadowing controls. The front end still allows direct single URLs.",
                "fields" => [ "_smpi_shadow_complete", "_smpi_shadow_home" ],
                "dependencies" => [ "ACF Pro", "pre_get_posts archive filters" ],
                "code_example" => "get_field(\"_smpi_shadow_complete\", $post_id)",
                "test_report" => "Shadow posts setting is " . ( Settings::bool( "shadow_posts_enabled" ) ? "enabled" : "disabled" ) . ".",
            ],
            [
                "id" => "press_release_visibility_acf",
                "label" => "Press Release Visibility ACF",
                "type" => "acf",
                "setting_key" => "press_release_include_enabled",
                "enabled" => Settings::bool( "press_release_include_enabled" ) || Settings::bool( "shadow_press_releases" ),
                "registered" => function() use ( $visibility_group_registered ): bool { return ( Settings::bool( "press_release_include_enabled" ) || Settings::bool( "shadow_press_releases" ) ) && $visibility_group_registered(); },
                "acf_group_key" => "group_smpi_visibility_controls",
                "object_name" => "_smpi_pr_shadow_override",
                "location" => "press-release side metabox",
                "description" => "Per-post override for global press-release inclusion or hiding rules.",
                "instructions" => "Use this when press releases need force-show or force-hide control independent of global defaults.",
                "fields" => [ "_smpi_pr_shadow_override" ],
                "dependencies" => [ "ACF Pro", "press-release CPT" ],
                "code_example" => "get_field(\"_smpi_pr_shadow_override\", $post_id)",
                "test_report" => "Press-release inclusion setting is " . ( Settings::bool( "press_release_include_enabled" ) ? "enabled" : "disabled" ) . ".",
            ],
            [
                "id" => "article_type_taxonomy",
                "label" => "Article Type Taxonomy",
                "type" => "taxonomy",
                "setting_key" => "article_types_enabled",
                "enabled" => Settings::bool( "article_types_enabled" ),
                "registered" => function(): bool { return Settings::bool( "article_types_enabled" ) && taxonomy_exists( \smp_publication_integration\Content\ArticleTypes::TAXONOMY ); },
                "object_name" => \smp_publication_integration\Content\ArticleTypes::TAXONOMY,
                "location" => "post, press-release, imported-news editors",
                "description" => "Controlled taxonomy that maps editorial selections to schema article types.",
                "instructions" => "Enable this to show the radio-only Article Type selector. Terms are managed by code so editors cannot create arbitrary schema labels.",
                "fields" => array_keys( \smp_publication_integration\Content\ArticleTypes::terms() ),
                "dependencies" => [ "WordPress taxonomy API", "SMP article schema mapper" ],
                "code_example" => "register_taxonomy(\"smpi_article_type\", [\"post\", \"press-release\", \"imported-news\"]);",
                "test_report" => $this->article_type_selector_report_text(),
            ],
        ];

        echo ( new FieldStructureRenderer() )->render(
            $definitions,
            [
                "title" => "Custom Fields and Content Structures",
                "description" => "SMP field groups, editor structures, taxonomies, dependencies, and live registration status rendered through Hexa WordPress Plugin Core.",
                "save_action" => "smpi_save_settings",
                "nonce" => Ajax::nonce(),
                "nonce_field" => "nonce",
            ]
        );
    }

    private function features(): void {
        $settings = Settings::all();
        echo "<div class=\"smpi-hero\"><p class=\"smpi-kicker\">Features</p><h2>Feature controls, implementation notes, code examples, live test reports, and activity logs.</h2><p>Each feature is isolated behind settings so it can be enabled, tested, or removed without mixing concerns.</p></div>";
        echo "<style id=smpi-design-preview-css>" . \smp_publication_integration\Content\ArticleStyles::preview_bundle_css() . "</style>";
        echo "<div class=\"smpi-design-host\">";
        $this->feature_card( "Elementor CSS cache busting", "elementor_css_cache_busting", "No custom ACF fields needed.", "Adds filemtime mv_css query args only to Elementor upload CSS files under /wp-content/uploads/elementor/css/. This prevents stale Elementor CSS after rebuilds without touching global assets.", "add_filter(\"style_loader_src\",function(\$src){if(false===strpos(\$src,\"/wp-content/uploads/elementor/css/\"))return \$src;\$path=wp_parse_url(\$src,PHP_URL_PATH);\$file=ABSPATH.ltrim(\$path,\"/\");return is_readable(\$file)?add_query_arg(\"mv_css\",filemtime(\$file),\$src):\$src;},9999,1);", $this->elementor_css_report_html(), $this->activity_log_html() );
        $this->feature_card( "Default post list view", "post_list_defaults_enabled", "No custom ACF fields needed.", "Applies the preferred Posts list Screen Options for users who have not customized that screen: 20 items, compact view, Author, Tags, Article Types, Date, and SEO Details visible.", "Hidden columns: categories, post source, comments, FIFU, SEO title, SEO description. Visible columns: author, tags, article types, date, SEO details.", $this->post_list_defaults_report_html(), $this->activity_log_html() );
        $this->feature_card( "Shadow posts", "shadow_posts_enabled", "Registers post meta controls: <code>_smpi_shadow_complete</code> and <code>_smpi_shadow_home</code>.", "When enabled, post editors get two visibility toggles. Completely shadowed posts remain link-accessible only and are excluded from home, category, and tag main queries. Home-only shadowed posts are excluded from the home query but still appear in category and tag pages.", "Post editor toggles: Completely shadow post / Shadow from home page only. Query guard: front-end main query only; single URLs remain accessible.", $this->shadow_posts_report_html(), $this->activity_log_html() );
        $author_muckrack_controls = $this->author_muckrack_mode_help_html( $settings ) . $this->author_muckrack_shortcodes_html() . $this->context_select_html( "muckrack_verified_contexts", [ "single_author" => "single.php header author mention", "single_footer" => "single.php footer/about-author mention", "loop_cards" => "Loop card authors: show checkmark", "home" => "Home page author mention", "author" => "author.php author mention" ], $settings, "Placement contexts" ) . $this->select_setting_html( "muckrack_verified_style", [ "tooltip" => [ "label" => "Tooltip icon", "description" => "Small badge beside the author name. Hover or focus explains the verification without adding sentence-length text.", "preview" => $this->author_tooltip_preview_html( $settings ) ], "text" => [ "label" => "Inline text", "description" => "Writes the full verification sentence directly into the author area.", "preview" => $this->author_inline_preview_html( $settings ) ], "compact_block" => [ "label" => "Small editorial block", "description" => "Smaller author version of the editorial verification block with left accent and compact text.", "preview" => $this->author_compact_block_preview_html( $settings ) ] ], $settings, "Display style" ) . $this->icon_style_setting_html( $settings ) . $this->color_setting_html( "muckrack_icon_color", "Default icon color", $settings ) . $this->number_setting_html( "muckrack_icon_size", "Default checkmark size", $settings, 8, 64, "px" ) . $this->number_setting_html( "muckrack_icon_margin_left", "Default checkmark margin-left", $settings, -32, 64, "px" ) . $this->number_setting_html( "muckrack_icon_margin_top", "Default checkmark margin-top", $settings, -32, 64, "px" ) . $this->author_context_overrides_html( $settings ) . $this->inline_toggle_setting_html( "muckrack_author_always_show", "Always show for every author" );
        $this->feature_card( "MuckRack verified authors", "muckrack_verified_enabled", "Uses the author user fields owned by hws-base-tools: muckrack_verified and muckrack_url.", "Supports both automatic Elementor-aware author placement and manual shortcode placement. Auto placement detects supported byline and about-author structures; shortcodes are for exact Elementor shortcode widgets or templates. The override can force the effective author badge for every author even when the individual ACF checkbox is empty.", "[author_muckrack_verified]
[author_muckrack_verified type=\"text\"]
[author_muckrack_verified style=\"compact_block\"]
[author_muckrack]
[muckrack_verified type=\"icon\" user_id=\"54\"]", $this->muckrack_report_html(), $this->activity_log_html(), $author_muckrack_controls );
        $publication_muckrack_controls = $this->select_setting_html( "publication_muckrack_text_mode", [ "news_outlet" => [ "label" => "News outlet verified by MuckRack editorial team", "description" => "Generic wording when you do not want the site name in the sentence." ], "publication_name" => [ "label" => get_bloginfo( "name" ) . " verified by MuckRack editorial team", "description" => "Uses the current publication name in the verification sentence." ] ], $settings, "Text option" ) . $this->select_setting_html( "publication_muckrack_style", [ "block" => [ "label" => "Editorial block", "description" => "Small article footer block with a left accent bar.", "preview" => $this->publication_preview_sample_html( "block", $settings ) ], "mini_block" => [ "label" => "Mini editorial block", "description" => "Same left-accent editorial concept with smaller text and a quieter footprint.", "preview" => $this->publication_preview_sample_html( "mini_block", $settings ) ], "compact" => [ "label" => "Compact pill", "description" => "Small inline badge for tight author or header layouts.", "preview" => $this->publication_preview_sample_html( "compact", $settings ) ], "minimalist" => [ "label" => "Minimalist text", "description" => "Plain text treatment that blends into existing article copy.", "preview" => $this->publication_preview_sample_html( "minimalist", $settings ) ] ], $settings, "Display style" ) . $this->color_setting_html( "publication_muckrack_color", "Accent color", $settings ) . $this->number_setting_html( "publication_muckrack_font_size", "Verification text size", $settings, 8, 64, "px" ) . $this->context_select_html( "publication_muckrack_placements", [ "below_author" => "Below author", "bottom_article" => "Bottom of article" ], $settings );
        $this->feature_card( "MuckRack verified publication", "publication_muckrack_verified_enabled", "Registers site option ACF fields on Publication Theme Options: smpi_publication_muckrack_verified and smpi_publication_muckrack_url.", "Displays publication-level MuckRack verification text separately from journalist verification. Use this for the site/news-outlet claim, not the author badge.", "[smp_publication_muckrack_verified]", $this->publication_muckrack_report_html(), $this->activity_log_html(), $publication_muckrack_controls );
        $this->feature_card( "Press-release inclusion controls", "press_release_include_enabled", "Uses existing press-release CPT and _smpi_pr_shadow_override meta. ACF/local fields are registered for force include or force exclude.", "Includes Hexa PR Wire press-release posts in selected blog-like loops: home, category/tag, author.php, and single.php recent article secondary queries. Force exclude is honored through the press-release visibility meta box.", "add_action(\"pre_get_posts\", function (WP_Query \$q) { /* SMP uses the same main-query guard pattern and selected contexts. */ });", $this->press_release_report_html(), $this->activity_log_html(), $this->context_select_html( "press_release_include_contexts", [ "home" => "Home page", "category_tag" => "Category and tag pages", "author" => "author.php", "single_recent" => "single.php recent article queries" ], $settings ) );
        $this->feature_card( "Article type schema selector", "", "Moved to the Custom Fields tab. Registers the <code>smpi_article_type</code> taxonomy only when enabled there.", "Adds one radio-only Article Type box to supported article editors. The field is hidden when disabled and only allows predefined schema-backed values.", "editorial-news => NewsArticle\nanalysis => AnalysisNewsArticle\nopinion => OpinionNewsArticle\nreportage => ReportageNewsArticle\npress-release => Article\nsponsored => AdvertiserContentArticle", $this->article_type_selector_report_html(), $this->activity_log_html(), "<p class=\"smpi-muted\">Registration toggle lives in Custom Fields.</p>" . $this->article_type_selector_options_html() );
        $this->feature_card( "Estimated read time", "estimated_read_time_enabled", "No custom ACF fields needed. Reads the selected post content directly.", "Calculates reading time from post_content after stripping HTML and shortcodes. The shortcode returns a plain numeric value in minutes by default or seconds when unit=seconds is passed.", "[smp_estimated_read_time]\n[smp_estimated_read_time unit=\"seconds\"]\n[smp_estimated_read_time post_id=\"123\" unit=\"minutes\"]", $this->estimated_read_time_report_html(), $this->activity_log_html() );
        $breadcrumb_controls = $this->select_setting_html( "breadcrumbs_style", $this->breadcrumb_style_options(), $settings, "Breadcrumb template" ) . $this->color_setting_html( "breadcrumbs_accent_color", "Breadcrumb primary color", $settings ) . $this->number_setting_html( "breadcrumbs_font_size", "Breadcrumb font size", $settings, 8, 64, "px" ) . $this->context_select_html( "breadcrumbs_disabled_post_types", $this->breadcrumb_post_type_options(), $settings, "Disable on custom post type single templates" );
        $this->feature_card( "Breadcrumbs", "breadcrumbs_enabled", "Registers a Hexa core-generated ACF multi post selector on Publication Theme Options: <code>smpi_breadcrumb_disabled_objects</code>. No repeater.", "Injects a selected Rank Math-compatible breadcrumb design directly below the site header on singular templates. Disable it by custom post type here, or by selecting individual posts/pages in the ACF field.", "[smp_breadcrumbs]\n[smp_breadcrumbs style=\"bc-b2\"]\nACF option: smpi_breadcrumb_disabled_objects", $this->breadcrumbs_report_html(), $this->activity_log_html(), $breadcrumb_controls );
        $toc_controls = $this->inline_toggle_setting_html( "table_of_contents_auto_single", "Automatically show above single.php content" ) . $this->select_setting_html( "table_of_contents_style", $this->toc_style_options(), $settings, "Table of contents design" ) . $this->color_setting_html( "table_of_contents_accent_color", "Table of contents accent color", $settings ) . $this->font_style_setting_html( "table_of_contents_text_font_style", "Table of contents text font style", $settings ) . $this->number_setting_html( "table_of_contents_text_font_size", "Table of contents text font size", $settings, 8, 64, "px" ) . $this->color_setting_html( "table_of_contents_text_color", "Table of contents text color", $settings );
        $this->feature_card( "Table of contents", "table_of_contents_enabled", "No ACF changes. Parses post headings from post_content.", "Adds [smp_table_of_contents] and optional automatic display above single.php content. Select the single.php display treatment here or use style= on the shortcode.", "[smp_table_of_contents]\n[smp_table_of_contents style=\"toc02\"]\n[smp_table_of_contents post_id=\"123\" title=\"In this article\"]", $this->table_of_contents_report_html(), $this->activity_log_html(), $toc_controls );
        $inline_photo_controls = $this->select_setting_html( "inline_photo_treatment", $this->inline_photo_treatment_options(), $settings, "Inline photo treatment" ) . $this->color_setting_html( "inline_photo_accent_color", "Inline photo accent color", $settings ) . $this->font_style_setting_html( "inline_photo_caption_font_style", "Caption text font style", $settings ) . $this->number_setting_html( "inline_photo_caption_font_size", "Caption text font size", $settings, 8, 64, "px" ) . $this->color_setting_html( "inline_photo_caption_text_color", "Caption text color", $settings );
        $this->feature_card( "Inline photo treatments", "inline_photo_treatments_enabled", "No ACF changes. Applies selected treatment to inline figures in posts and press-release articles.", "Prestyles inline photos and captions in single.php without editing each article. Treatments 1, 2, 4, and 5 are imported from the HerForward inline redesign page.", "No shortcode needed. Enable the feature and select a treatment.", $this->simple_status_html( Settings::bool( "inline_photo_treatments_enabled" ), "Current treatment: " . (string) Settings::get( "inline_photo_treatment", "none" ) . "." ), $this->activity_log_html(), $inline_photo_controls );
        $featured_image_caption_controls = $this->select_setting_html( "featured_image_caption_template", $this->featured_image_caption_template_options(), $settings, "Featured image caption template" ) . $this->color_setting_html( "featured_image_caption_accent_color", "Featured caption accent color", $settings ) . $this->font_style_setting_html( "featured_image_caption_font_style", "Featured caption font style", $settings ) . $this->number_setting_html( "featured_image_caption_font_size", "Featured caption font size", $settings, 8, 64, "px" ) . $this->color_setting_html( "featured_image_caption_text_color", "Featured caption text color", $settings );
        $this->feature_card( "Featured image caption templates", "featured_image_caption_templates_enabled", "No ACF changes. Reads the caption from the media attachment used as the post featured image.", "Auto-detects the single post or press-release featured image and applies a selected caption template. The designs intentionally duplicate the inline image treatments but use separate settings, selectors, and rendering code.", "No shortcode needed. Add a media caption to the featured image, enable this feature, and select a template.", $this->featured_image_caption_report_html(), $this->activity_log_html(), $featured_image_caption_controls );
        $post_acf_controls = "<div class=\"smpi-control-group\"><h3>Field registration</h3><p>Field registration toggles live in Custom Fields. Use this section only for display styling and shortcode reference.</p></div>" . $this->select_setting_html( "post_summary_style", $this->post_summary_style_options(), $settings, "Post Summary design" ) . $this->select_setting_html( "post_faqs_style", $this->post_faq_style_options(), $settings, "Post FAQ design" ) . $this->color_setting_html( "post_faqs_accent_color", "FAQ accent color", $settings ) . $this->font_style_setting_html( "post_faqs_text_font_style", "FAQ text font style", $settings ) . $this->number_setting_html( "post_faqs_text_font_size", "FAQ text font size", $settings, 8, 64, "px" ) . $this->color_setting_html( "post_faqs_text_color", "FAQ text color", $settings ) . $this->post_acf_shortcode_reference_html();
        $this->feature_card( "Post ACF add-ons", "", "Registration moved to the Custom Fields tab. This card keeps style controls and shortcode examples for summary and FAQ output.", "Summary and structured FAQ shortcodes can render raw content or the selected single.php style.", "[smp_post_summary style=\"sum00\"]\n[smp_post_faqs style=\"faq02\"]\n[smp_post_acf field=\"post_summary\"]", $this->post_acf_addons_report_html(), $this->activity_log_html(), $post_acf_controls );
        $this->feature_card( "Author social icons", "author_social_cleanup", "No ACF changes. Reads rendered Elementor/social-icon anchors.", "Runs only on single posts and author archives. Empty social anchors are hidden when href is missing, blank, hash, or javascript. Fully empty Elementor social wrappers are collapsed.", "No shortcode needed. Toggle this feature on and inspect single.php or author.php social widgets.", $this->simple_status_html( Settings::bool( "author_social_cleanup" ), "Cleanup script active on single posts and author archives only." ), $this->activity_log_html() );
        $this->feature_card( "Publication social icons", "publication_social_cleanup", "No dedicated ACF change. Reads rendered Elementor/social-icon anchors in global publication areas.", "Runs the same empty-social cleanup safely across frontend pages for publication-level header and footer social widgets. Empty href, #, and javascript anchors are hidden without touching valid social links.", "No shortcode needed. Toggle this on and inspect header/footer publication social widgets.", $this->simple_status_html( Settings::bool( "publication_social_cleanup" ), "Publication social cleanup script active on frontend pages." ), $this->activity_log_html() );
        $this->feature_card( "Rank Math breadcrumb check", "rank_math_breadcrumb_check_enabled", "No ACF changes.", "Reports Rank Math breadcrumb status from rank-math-options-general. Mutation filters should only be added after exact breadcrumb rules are supplied.", "add_filter(\"rank_math/frontend/breadcrumb/items\", function(\$crumbs){ return \$crumbs; }, 10, 1);", $this->rank_math_breadcrumb_report_html(), $this->activity_log_html() );
        $this->feature_card( "HWS masked admin URL", "hws_masked_admin_report_enabled", "HWS Base Tools owns this feature. SMP only reports status and links to it.", "Confirms whether HWS Base Tools masked login is enabled and exposes the masked URL in the Overview and Features tabs.", "HWS option: hws_login_mask_options with slug hexa-admin.", $this->hws_masked_login_report_html(), $this->activity_log_html() );
        echo "</div>";
    }


    private function feature_card( string $title, string $toggle_key, string $acf, string $description, string $code, string $test_report, string $activity_log, string $extra_controls = "" ): void {
        $log_key = $toggle_key ?: sanitize_key( $title );
        echo "<article class=smpi-feature-card><div class=smpi-feature-head><div><p class=smpi-kicker>Feature</p><h2>" . esc_html( $title ) . "</h2></div><div class=smpi-feature-toggle>" . ( $toggle_key ? $this->inline_toggle_html( $toggle_key ) : "<span class=smpi-warn>Report only</span>" ) . "</div></div>";
        if ( "" !== $extra_controls ) {
            echo "<div class=smpi-feature-controls>" . $extra_controls . "</div>";
        }
        echo "<div class=smpi-feature-grid><section><h3>Custom ACF adjustments</h3><p>" . wp_kses_post( $acf ) . "</p></section><section><h3>Description / use instructions</h3><p>" . esc_html( $description ) . "</p></section><section><h3>Code example</h3><pre class=smpi-code>" . esc_html( $code ) . "</pre></section><section class=smpi-feature-report><h3>Test report, active and proof working</h3>" . wp_kses_post( $test_report ) . "</section><section class=smpi-feature-activity><h3>Activity log</h3>" . wp_kses_post( $this->activity_log_html( $log_key ) ) . "</section></div></article>";
    }

    private function inline_toggle_html( string $key ): string {
        $enabled = Settings::bool( $key );
        return "<label class=smpi-switch><input class=smpi-setting type=checkbox data-key=" . esc_attr( $key ) . " value=1 " . checked( $enabled, true, false ) . "><span></span><strong>" . ( $enabled ? "Enabled" : "Disabled" ) . "</strong></label><span class=spinner></span><span class=smpi-save-state></span>";
    }

    private function inline_toggle_setting_html( string $key, string $label ): string {
        $enabled = Settings::bool( $key );
        return "<div class=smpi-control-row><label class=smpi-switch><input class=smpi-setting type=checkbox data-key=" . esc_attr( $key ) . " value=1 " . checked( $enabled, true, false ) . "><span></span><strong>" . esc_html( $label ) . "</strong></label><span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function context_select_html( string $key, array $options, array $settings, string $label = "Placement contexts" ): string {
        $selected = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? $settings[ $key ] : [];
        $html = "<div class=\"smpi-control-group smpi-context-control\"><h3>" . esc_html( $label ) . "</h3><div class=smpi-choice-list>";
        foreach ( $options as $value => $option_label ) {
            $choice = $this->choice_data( $option_label, $this->context_description( $key, (string) $value ) );
            $is_selected = in_array( $value, $selected, true );
            $html .= "<label class=\"smpi-choice-card smpi-context-choice" . ( $is_selected ? " is-selected" : "" ) . "\"><input class=smpi-setting-array type=checkbox data-key=" . esc_attr( $key ) . " value=" . esc_attr( $value ) . " " . checked( $is_selected, true, false ) . "><span class=smpi-choice-body><strong>" . esc_html( $choice["label"] ) . "</strong><small>" . esc_html( $choice["description"] ) . "</small></span>" . ( $is_selected ? "<span class=smpi-selected-pill>Selected</span>" : "" ) . "</label>";
        }
        return $html . "</div><span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function select_setting_html( string $key, array $options, array $settings, string $label = "Style" ): string {
        $current = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : "";
        $html = "<div class=smpi-control-group><h3>" . esc_html( $label ) . "</h3><div class=smpi-choice-grid>";
        foreach ( $options as $value => $option_label ) {
            $choice = $this->choice_data( $option_label );
            $is_selected = $current === (string) $value;
            $html .= "<label class=\"smpi-choice-card" . ( $is_selected ? " is-selected" : "" ) . "\"><input class=smpi-setting type=radio name=" . esc_attr( "smpi_" . $key ) . " data-key=" . esc_attr( $key ) . " value=" . esc_attr( $value ) . " " . checked( $is_selected, true, false ) . "><span class=smpi-choice-body><strong>" . esc_html( $choice["label"] ) . "</strong>" . ( "" !== $choice["description"] ? "<small>" . esc_html( $choice["description"] ) . "</small>" : "" ) . ( "" !== $choice["preview"] ? "<span class=smpi-choice-preview>" . $this->preview_html( $choice["preview"] ) . "</span>" : "" ) . "</span>" . ( $is_selected ? "<span class=smpi-selected-pill>Selected</span>" : "" ) . "</label>";
        }
        return $html . "</div><span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function color_setting_html( string $key, string $label, array $settings ): string {
        $default = "#2d5277";
        $color = sanitize_hex_color( (string) ( $settings[ $key ] ?? $default ) ) ?: $default;
        return "<div class=smpi-control-group><h3>" . esc_html( $label ) . "</h3><label class=smpi-color-control><input class=\"smpi-setting smpi-color-setting\" type=color data-key=" . esc_attr( $key ) . " value=" . esc_attr( $color ) . "><code class=smpi-color-hex data-smpi-color-hex>" . esc_html( $color ) . "</code><span class=smpi-color-swatch style=\"background:" . esc_attr( $color ) . "\"></span>" . CoreUi::copy_button( $color, "Copy hex" ) . "</label><span class=spinner></span><span class=\"smpi-save-state\" aria-live=\"polite\"></span></div>";
    }

    private function number_setting_html( string $key, string $label, array $settings, int $min = 8, int $max = 64, string $suffix = "" ): string {
        $value = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : $min;
        $value = max( $min, min( $max, $value ) );
        return "<div class=smpi-control-group><h3>" . esc_html( $label ) . "</h3><label class=smpi-number-control><input class=smpi-setting type=number min=" . esc_attr( (string) $min ) . " max=" . esc_attr( (string) $max ) . " data-key=" . esc_attr( $key ) . " value=" . esc_attr( (string) $value ) . "><span>" . esc_html( $suffix ) . "</span></label><span class=spinner></span><span class=smpi-save-state></span></div>";
    }

    private function font_style_setting_html( string $key, string $label, array $settings ): string {
        return $this->select_setting_html( $key, [
            "normal" => [ "label" => "Normal text", "description" => "Keep the selected text upright." ],
            "italic" => [ "label" => "Italic text", "description" => "Use an editorial italic treatment." ],
        ], $settings, $label );
    }

    private function author_context_overrides_html( array $settings ): string {
        $contexts = [
            "single_author" => [ "label" => "Single header author", "description" => "Overrides the badge beside the top byline on single.php." ],
            "single_footer" => [ "label" => "Footer/about-author", "description" => "Overrides the badge beside the bottom author profile name." ],
            "loop_cards" => [ "label" => "Loop card authors", "description" => "Overrides author badges in Elementor loop/recent-article cards." ],
            "home" => [ "label" => "Home page authors", "description" => "Overrides author badges in home page article lists." ],
            "author" => [ "label" => "Author archive/profile", "description" => "Overrides badges on author.php archive/profile pages." ],
        ];
        $default_color = sanitize_hex_color( (string) ( $settings["muckrack_icon_color"] ?? "#2d5277" ) ) ?: "#2d5277";
        $html = "<div class=\"smpi-control-group smpi-context-overrides\"><h3>Context overrides</h3><p class=smpi-muted>Use the color picker for a context override. Clear returns that context to the default color above.</p><div class=smpi-context-override-list>";
        foreach ( $contexts as $context => $meta ) {
            $color_key = "muckrack_icon_color_" . $context;
            $size_key = "muckrack_icon_size_" . $context;
            $margin_left_key = "muckrack_icon_margin_left_" . $context;
            $margin_top_key = "muckrack_icon_margin_top_" . $context;
            $color = sanitize_hex_color( (string) ( $settings[ $color_key ] ?? "" ) ) ?: "";
            $effective = "" !== $color ? $color : $default_color;
            $size = isset( $settings[ $size_key ] ) ? absint( $settings[ $size_key ] ) : 0;
            $size = $size > 0 ? max( 8, min( 64, $size ) ) : 0;
            $margin_left = isset( $settings[ $margin_left_key ] ) && "" !== (string) $settings[ $margin_left_key ] ? max( -32, min( 64, (int) $settings[ $margin_left_key ] ) ) : "";
            $margin_top = isset( $settings[ $margin_top_key ] ) && "" !== (string) $settings[ $margin_top_key ] ? max( -32, min( 64, (int) $settings[ $margin_top_key ] ) ) : "";
            $html .= "<div class=smpi-context-override-row><div><strong>" . esc_html( $meta["label"] ) . "</strong><small>" . esc_html( $meta["description"] ) . "</small></div><label class=smpi-color-control>Color override <input class=smpi-color-picker type=color data-smpi-sync-key=" . esc_attr( $color_key ) . " value=" . esc_attr( $effective ) . "><input class=\"smpi-setting smpi-color-hidden\" type=hidden data-key=" . esc_attr( $color_key ) . " value=\"" . esc_attr( $color ) . "\"><code class=smpi-color-hex data-smpi-color-hex data-smpi-empty-label=inherit>" . esc_html( "" !== $color ? $color : "inherit" ) . "</code><span class=smpi-color-swatch style=\"background:" . esc_attr( $effective ) . "\"></span>" . CoreUi::copy_button( $effective, "" !== $color ? "Copy hex" : "Copy inherited hex" ) . "<button type=button class=\"button button-link smpi-color-inherit\" data-smpi-sync-key=" . esc_attr( $color_key ) . ">Inherit</button></label><label>Size override <input class=smpi-setting type=number min=0 max=64 data-key=" . esc_attr( $size_key ) . " value=\"" . esc_attr( (string) $size ) . "\"><span>px</span></label><label>Margin left <input class=smpi-setting type=number min=-32 max=64 placeholder=inherit data-key=" . esc_attr( $margin_left_key ) . " value=\"" . esc_attr( (string) $margin_left ) . "\"><span>px</span></label><label>Margin top <input class=smpi-setting type=number min=-32 max=64 placeholder=inherit data-key=" . esc_attr( $margin_top_key ) . " value=\"" . esc_attr( (string) $margin_top ) . "\"><span>px</span></label></div>";
        }
        return $html . "</div><span class=spinner></span><span class=\"smpi-save-state\" aria-live=\"polite\"></span></div>";
    }

    private function breadcrumb_style_options(): array {
        return [
            "bc-b1" => [ "label" => "Option 1: Tinted Band + Title", "description" => "Page title above a soft brand-tinted band; breadcrumb beneath in muted text.", "preview" => $this->breadcrumb_design_preview_html( "bc-b1" ) ],
            "bc-b2" => [ "label" => "Option 2: Minimal Hairline", "description" => "Thin row over a hairline divider, chevron separators, current page in bold ink.", "preview" => $this->breadcrumb_design_preview_html( "bc-b2" ) ],
            "bc-b3" => [ "label" => "Option 3: Uppercase Eyebrow", "description" => "Letter-spaced uppercase crumbs over a short accent rule.", "preview" => $this->breadcrumb_design_preview_html( "bc-b3" ) ],
            "bc-b4" => [ "label" => "Option 4: Soft Chips", "description" => "Each crumb is a rounded chip; current page becomes a filled accent pill.", "preview" => $this->breadcrumb_design_preview_html( "bc-b4" ) ],
            "bc-b5" => [ "label" => "Option 5: Gradient Lead-in", "description" => "Breadcrumb above a large serif headline on a soft top-down gradient.", "preview" => $this->breadcrumb_design_preview_html( "bc-b5" ) ],
        ];
    }

    private function breadcrumb_post_type_options(): array {
        $options = [];
        foreach ( get_post_types( [ "public" => true, "_builtin" => false ], "objects" ) as $type => $object ) {
            $options[ $type ] = isset( $object->labels->name ) ? (string) $object->labels->name : $type;
        }
        return $options;
    }

    private function toc_style_options(): array {
        return [
            "none" => [ "label" => "No style", "description" => "Bare list markup only.", "preview" => $this->toc_design_preview_html( "none" ) ],
            "toc00" => [ "label" => "Minimal List", "description" => "Quiet label and plain links.", "preview" => $this->toc_design_preview_html( "toc00" ) ],
            "toc01" => [ "label" => "Side Rule", "description" => "Left margin hairline treatment.", "preview" => $this->toc_design_preview_html( "toc01" ) ],
            "toc02" => [ "label" => "Soft Box", "description" => "Contained gray card with numbered items.", "preview" => $this->toc_design_preview_html( "toc02" ) ],
            "toc03" => [ "label" => "Numbered Rows", "description" => "Divided rows with blue indexes.", "preview" => $this->toc_design_preview_html( "toc03" ) ],
            "toc04" => [ "label" => "Jump Pills", "description" => "Horizontal pill links.", "preview" => $this->toc_design_preview_html( "toc04" ) ],
        ];
    }

    private function inline_photo_treatment_options(): array {
        return [
            "none" => [ "label" => "No style", "description" => "Leave theme figure styles untouched.", "preview" => $this->inline_photo_preview_html( "none" ) ],
            "fig1" => [ "label" => "Treatment 1: Clean rounded", "description" => "Rounded image, soft shadow, serif caption rule.", "preview" => $this->inline_photo_preview_html( "fig1" ) ],
            "fig2" => [ "label" => "Treatment 2: Caption plate", "description" => "Image with flush caption plate.", "preview" => $this->inline_photo_preview_html( "fig2" ) ],
            "fig4" => [ "label" => "Treatment 4: Gradient overlay", "description" => "Caption overlays the photo bottom.", "preview" => $this->inline_photo_preview_html( "fig4" ) ],
            "fig5" => [ "label" => "Treatment 5: Framed plate", "description" => "Thin framed card and editorial caption.", "preview" => $this->inline_photo_preview_html( "fig5" ) ],
        ];
    }

    private function featured_image_caption_template_options(): array {
        return [
            "none" => [ "label" => "No style", "description" => "Detect the featured image but leave caption output disabled.", "preview" => $this->featured_image_caption_preview_html( "none" ) ],
            "fig1" => [ "label" => "Treatment 1: Clean rounded", "description" => "Duplicate of inline treatment 1 for featured images only.", "preview" => $this->featured_image_caption_preview_html( "fig1" ) ],
            "fig2" => [ "label" => "Treatment 2: Caption plate", "description" => "Duplicate of inline treatment 2 for featured images only.", "preview" => $this->featured_image_caption_preview_html( "fig2" ) ],
            "fig4" => [ "label" => "Treatment 4: Gradient overlay", "description" => "Duplicate of inline treatment 4 for featured images only.", "preview" => $this->featured_image_caption_preview_html( "fig4" ) ],
            "fig5" => [ "label" => "Treatment 5: Framed plate", "description" => "Duplicate of inline treatment 5 for featured images only.", "preview" => $this->featured_image_caption_preview_html( "fig5" ) ],
        ];
    }

    private function post_summary_style_options(): array {
        return [
            "none" => [ "label" => "No style", "description" => "Render the field exactly as entered.", "preview" => $this->summary_design_preview_html( "none" ) ],
            "sum00" => [ "label" => "Reuters Plate", "description" => "Gray summary plate with strong heading underline.", "preview" => $this->summary_design_preview_html( "sum00" ) ],
            "sum01" => [ "label" => "Key Points Card", "description" => "White card with blue accent edge.", "preview" => $this->summary_design_preview_html( "sum01" ) ],
            "sum02" => [ "label" => "Eyebrow Bullets", "description" => "Minimal rules and compact bullets.", "preview" => $this->summary_design_preview_html( "sum02" ) ],
            "sum03" => [ "label" => "Numbered Brief", "description" => "Dark header and briefing structure.", "preview" => $this->summary_design_preview_html( "sum03" ) ],
            "sum04" => [ "label" => "Highlight Callout", "description" => "Soft blue callout box.", "preview" => $this->summary_design_preview_html( "sum04" ) ],
        ];
    }

    private function post_faq_style_options(): array {
        return [
            "none" => [ "label" => "No style", "description" => "Render the FAQ field exactly as entered.", "preview" => $this->faq_design_preview_html( "none" ) ],
            "faq00" => [ "label" => "Accordion", "description" => "Accessible details style when markup supports it.", "preview" => $this->faq_design_preview_html( "faq00" ) ],
            "faq01" => [ "label" => "Stacked Q and A", "description" => "Divided editorial Q and A rows.", "preview" => $this->faq_design_preview_html( "faq01" ) ],
            "faq02" => [ "label" => "Card List", "description" => "Each question in its own card.", "preview" => $this->faq_design_preview_html( "faq02" ) ],
            "faq03" => [ "label" => "Numbered", "description" => "Oversized ghost numbers.", "preview" => $this->faq_design_preview_html( "faq03" ) ],
            "faq04" => [ "label" => "People Also Ask", "description" => "Soft Google-like Q and A cards.", "preview" => $this->faq_design_preview_html( "faq04" ) ],
        ];
    }

    private function preview_image_url(): string {
        return "https://picsum.photos/seed/smpi-inline/720/420";
    }

    private function toc_preview_label( string $style ): string {
        if ( "toc00" === $style ) { return "In this article"; }
        if ( "toc02" === $style ) { return "On this page"; }
        if ( "toc04" === $style ) { return "Jump to"; }
        return "Table of Contents";
    }

    private function breadcrumb_design_preview_html( string $style ): string {
        $style = \smp_publication_integration\Content\ArticleStyles::normalize_breadcrumb_style( $style );
        $title = "Amazon Shelves Guadagnino Film";
        $crumbs = "<nav aria-label=\"breadcrumbs\" class=\"rank-math-breadcrumb\"><p><a href=\"#\">Home</a><span class=\"separator\"> - </span><a href=\"#\">Entertainment</a><span class=\"separator\"> - </span><span class=\"last\">" . esc_html( $title ) . "</span></p></nav>";
        $title_html = in_array( $style, [ "bc-b1", "bc-b5" ], true ) ? "<div class=\"pt\">" . esc_html( $title ) . "</div>" : "";
        $inner = "bc-b5" === $style ? $crumbs . $title_html : $title_html . $crumbs;
        return "<div class=\"smpi-breadcrumbs smpi-" . esc_attr( $style ) . "\">" . $inner . "</div>";
    }

    private function toc_design_preview_html( string $style ): string {
        $label = esc_html( $this->toc_preview_label( $style ) );
        if ( "toc04" === $style ) {
            return "<nav class=\"smpi-table-of-contents smpi-" . esc_attr( $style ) . "\"><span class=\"smpi-toc-label\">" . $label . "</span><a href=\"#\">The rise of new technology</a><a href=\"#\">What the data reveals</a></nav>";
        }
        return "<nav class=\"smpi-table-of-contents smpi-" . esc_attr( $style ) . "\"><p class=\"smpi-toc-label\">" . $label . "</p><ol><li><a href=\"#\">The rise of new technology</a></li><li><a href=\"#\">What the data reveals</a></li></ol></nav>";
    }

    private function inline_photo_preview_html( string $style ): string {
        $img = "<img src=\"" . esc_url( $this->preview_image_url() ) . "\" alt=\"\">";
        $cap = "<figcaption>Collegiate summer leagues provide a platform for emerging talent.</figcaption>";
        return "<figure class=\"smpi-pp smpi-pp-" . esc_attr( $style ) . "\">" . $img . $cap . "</figure>";
    }

    private function featured_image_caption_preview_html( string $style ): string {
        $img = "<img src=\"" . esc_url( $this->preview_image_url() ) . "\" alt=\"\">";
        $cap = "<span class=\"smpi-featured-image-caption-text\">Featured image caption pulled from the media attachment.</span>";
        return "<figure class=\"smpi-fi-preview smpi-fi-preview-" . esc_attr( $style ) . "\">" . $img . $cap . "</figure>";
    }

    private function featured_image_caption_report_html(): string {
        $enabled = Settings::bool( "featured_image_caption_templates_enabled" );
        $style = (string) Settings::get( "featured_image_caption_template", "fig2" );
        $html = $this->simple_status_html( $enabled, "Current template: " . $style . ". Auto-detects single post and press-release featured images with media captions." );
        $items = get_posts( [
            "post_type"      => [ "post", "press-release" ],
            "post_status"    => [ "publish", "pending", "draft" ],
            "posts_per_page" => 8,
            "orderby"        => "date",
            "order"          => "DESC",
            "meta_query"     => [ [ "key" => "_thumbnail_id", "compare" => "EXISTS" ] ],
        ] );
        if ( empty( $items ) ) {
            return $html . "<p class=smpi-muted>No recent posts with featured images were found.</p>";
        }
        $html .= "<table class=\"widefat striped\"><thead><tr><th>Post</th><th>Type</th><th>Featured image caption</th><th>Proof URL</th></tr></thead><tbody>";
        foreach ( $items as $item ) {
            $thumb_id = (int) get_post_thumbnail_id( $item->ID );
            $caption = $thumb_id ? trim( (string) wp_get_attachment_caption( $thumb_id ) ) : "";
            $url = get_permalink( $item );
            $html .= "<tr><td>" . esc_html( get_the_title( $item ) ) . "</td><td><code>" . esc_html( get_post_type( $item ) ) . "</code></td><td>" . ( "" !== $caption ? esc_html( $caption ) : "<span class=smpi-warn>No media caption</span>" ) . "</td><td>" . ( $url ? "<a href=\"" . esc_url( $url ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">Open</a>" : "" ) . "</td></tr>";
        }
        return $html . "</tbody></table>";
    }

    private function summary_design_preview_html( string $style ): string {
        $content = "<ul><li>Commitments exceed \$150 billion across five regions.</li><li>The fund becomes operational after final approval.</li></ul>";
        if ( "none" === $style ) {
            return "<aside class=\"smpi-post-summary smpi-none\">" . $content . "</aside>";
        }
        $title = esc_html( \smp_publication_integration\Content\ArticleStyles::summary_title( $style ) );
        return "<aside class=\"smpi-post-summary smpi-" . esc_attr( $style ) . "\"><h2>" . $title . "</h2><div class=\"smpi-post-summary-content\">" . $content . "</div></aside>";
    }

    private function faq_design_preview_html( string $style ): string {
        $content = "<ul class=\"smpi-post-faq-list\"><li class=\"smpi-post-faq-item\"><h3 class=\"smpi-post-faq-question\">What record did Messi tie?</h3><div class=\"smpi-post-faq-answer\"><p>He tied Klose's World Cup goals record with a hat trick against Algeria.</p></div></li><li class=\"smpi-post-faq-item\"><h3 class=\"smpi-post-faq-question\">What injury did Jose Ramirez sustain?</h3><div class=\"smpi-post-faq-answer\"><p>Surgery on a broken hamate bone, about five to seven weeks of recovery.</p></div></li></ul>";
        if ( "none" === $style ) {
            return "<section class=\"smpi-post-faqs smpi-none\">" . $content . "</section>";
        }
        $title = esc_html( \smp_publication_integration\Content\ArticleStyles::faq_title( $style ) );
        return "<section class=\"smpi-post-faqs smpi-" . esc_attr( $style ) . "\"><h2>" . $title . "</h2><div class=\"smpi-post-faqs-content\">" . $content . "</div></section>";
    }

    private function style_vars( array $vars ): string {
        $parts = [];
        foreach ( $vars as $key => $value ) {
            $parts[] = "--" . sanitize_key( (string) $key ) . ":" . sanitize_text_field( (string) $value );
        }
        return implode( ";", $parts );
    }

    private function font_style_value( string $value ): string {
        return "italic" === $value ? "italic" : "normal";
    }

    private function icon_style_setting_html( array $settings ): string {
        return $this->select_setting_html( "muckrack_icon_style", [
            "circle_check" => [ "label" => "Solid circle check", "description" => "Uses the supplied filled-circle SVG. The selected icon color controls the SVG fill.", "preview" => $this->author_icon_preview_html( $settings, "circle_check" ) ],
            "circle_outline_check" => [ "label" => "Outline circle check", "description" => "Uses the supplied outline-circle SVG. The selected icon color controls the SVG stroke.", "preview" => $this->author_icon_preview_html( $settings, "circle_outline_check" ) ],
            "check" => [ "label" => "Plain check", "description" => "Uses the supplied check path only, with no circle.", "preview" => $this->author_icon_preview_html( $settings, "check" ) ],
        ], $settings, "Icon style" );
    }

    private function preview_html( string $html ): string {
        $allowed = wp_kses_allowed_html( "post" );
        $allowed["svg"] = [ "class" => true, "xmlns" => true, "width" => true, "height" => true, "viewBox" => true, "viewbox" => true, "fill" => true, "aria-hidden" => true, "focusable" => true ];
        $allowed["path"] = [ "fill-rule" => true, "clip-rule" => true, "d" => true, "fill" => true, "stroke" => true, "stroke-width" => true, "stroke-linecap" => true, "stroke-linejoin" => true ];
        return wp_kses( $html, $allowed );
    }

    private function choice_data( $choice, string $fallback_description = "" ): array {
        if ( is_array( $choice ) ) {
            return [
                "label" => (string) ( $choice["label"] ?? "" ),
                "description" => (string) ( $choice["description"] ?? $fallback_description ),
                "preview" => (string) ( $choice["preview"] ?? "" ),
            ];
        }
        return [ "label" => (string) $choice, "description" => $fallback_description, "preview" => "" ];
    }

    private function context_description( string $key, string $value ): string {
        $descriptions = [
            "muckrack_verified_contexts" => [
                "single_author" => "Adds the author verification badge beside the byline on single article templates.",
                "single_footer" => "Adds the selected author verification badge to the footer/about-author profile name.",
                "loop_cards" => "Adds author checkmarks to Elementor loop and recent-article cards using each card author link or author text.",
                "home" => "Applies the author badge when author names render in home page article lists.",
                "author" => "Applies the author badge on author archive/profile pages.",
            ],
            "publication_muckrack_placements" => [
                "below_author" => "Places publication verification after the footer/about-author card, never inside the header byline.",
                "bottom_article" => "Places publication verification immediately after the article content widget and before the footer/profile area.",
            ],
            "press_release_include_contexts" => [
                "home" => "Allows press releases to appear in the main home page feed.",
                "category_tag" => "Allows press releases in category and tag archive queries.",
                "author" => "Allows press releases to appear on author archive pages.",
                "single_recent" => "Allows press releases in recent-article modules shown on single posts.",
            ],
            "breadcrumbs_disabled_post_types" => $this->breadcrumb_post_type_descriptions(),
        ];
        return $descriptions[ $key ][ $value ] ?? "Controls where this feature is allowed to run on the front end.";
    }

    private function breadcrumb_post_type_descriptions(): array {
        $descriptions = [];
        foreach ( get_post_types( [ "public" => true, "_builtin" => false ], "objects" ) as $type => $object ) {
            $label = isset( $object->labels->singular_name ) ? (string) $object->labels->singular_name : $type;
            $descriptions[ $type ] = "Do not inject SMP breadcrumbs on single " . $label . " templates.";
        }
        return $descriptions;
    }

    private function author_icon_preview_html( array $settings, string $style = "" ): string {
        $color = sanitize_hex_color( (string) ( $settings["muckrack_icon_color"] ?? "#2d5277" ) ) ?: "#2d5277";
        $style = "" !== $style ? $style : (string) ( $settings["muckrack_icon_style"] ?? "circle_check" );
        if ( ! in_array( $style, [ "circle_check", "circle_outline_check", "check" ], true ) ) {
            $style = "circle_check";
        }
        $class = "check" === $style ? "smpi-muckrack-preview-check" : ( "circle_outline_check" === $style ? "smpi-muckrack-preview-outline" : "smpi-muckrack-preview-circle" );
        $size = isset( $settings['muckrack_icon_size'] ) ? absint( $settings['muckrack_icon_size'] ) : 22;
        $size = max( 8, min( 64, $size ?: 22 ) );
        $margin_left = isset( $settings['muckrack_icon_margin_left'] ) ? (int) $settings['muckrack_icon_margin_left'] : 2;
        $margin_top = isset( $settings['muckrack_icon_margin_top'] ) ? (int) $settings['muckrack_icon_margin_top'] : 0;
        $margin_left = max( -32, min( 64, $margin_left ) );
        $margin_top = max( -32, min( 64, $margin_top ) );
        return '<span class="' . esc_attr( $class ) . '" style="--smpi-muckrack-color:' . esc_attr( $color ) . ';color:' . esc_attr( $color ) . ';font-size:' . esc_attr( (string) $size ) . 'px;margin-left:' . esc_attr( (string) $margin_left ) . 'px;margin-top:' . esc_attr( (string) $margin_top ) . 'px" aria-label="Verified by MuckRack editorial team">' . $this->muckrack_icon_svg_html( $style ) . '</span>';
    }

    private function muckrack_icon_svg_html( string $style ): string {
        if ( "check" === $style ) {
            return "<svg class=\"smpi-muckrack-svg\" xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 -0.5 25 25\" fill=\"none\" aria-hidden=\"true\" focusable=\"false\"><path d=\"M9 12.0002L11.333 14.3332L16 9.66724\" stroke=\"currentColor\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"></path></svg>";
        }
        if ( "circle_outline_check" === $style ) {
            return "<svg class=\"smpi-muckrack-svg\" xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 -0.5 25 25\" fill=\"none\" aria-hidden=\"true\" focusable=\"false\"><path fill-rule=\"evenodd\" clip-rule=\"evenodd\" d=\"M5.5 12.0002C5.50024 8.66068 7.85944 5.78639 11.1348 5.1351C14.4102 4.48382 17.6895 6.23693 18.9673 9.32231C20.2451 12.4077 19.1655 15.966 16.3887 17.8212C13.6119 19.6764 9.91127 19.3117 7.55 16.9502C6.23728 15.6373 5.49987 13.8568 5.5 12.0002Z\" stroke=\"currentColor\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"></path><path d=\"M9 12.0002L11.333 14.3332L16 9.66724\" stroke=\"currentColor\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"></path></svg>";
        }
        return "<svg class=\"smpi-muckrack-svg\" xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 0 24 24\" fill=\"none\" aria-hidden=\"true\" focusable=\"false\"><path fill-rule=\"evenodd\" clip-rule=\"evenodd\" d=\"M2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12ZM15.7071 9.29289C16.0976 9.68342 16.0976 10.3166 15.7071 10.7071L12.0243 14.3899C11.4586 14.9556 10.5414 14.9556 9.97568 14.3899L8.29289 12.7071C7.90237 12.3166 7.90237 11.6834 8.29289 11.2929C8.68342 10.9024 9.31658 10.9024 9.70711 11.2929L11 12.5858L14.2929 9.29289C14.6834 8.90237 15.3166 8.90237 15.7071 9.29289Z\" fill=\"currentColor\"></path></svg>";
    }

    private function author_tooltip_preview_html( array $settings ): string {
        return "<span class=smpi-tooltip-demo><strong>Jane Reporter</strong> " . $this->author_icon_preview_html( $settings ) . "<span class=smpi-tooltip-bubble>Verified by MuckRack editorial team</span></span>";
    }

    private function author_inline_preview_html( array $settings ): string {
        $color = sanitize_hex_color( (string) ( $settings["muckrack_icon_color"] ?? "#2d5277" ) ) ?: "#2d5277";
        return "<span class=smpi-author-inline-demo>Journalist verified by <strong style=\"color:" . esc_attr( $color ) . "\">MuckRack</strong> editorial team <a href=#>(learn more)</a></span>";
    }

    private function author_compact_block_preview_html( array $settings ): string {
        $color = sanitize_hex_color( (string) ( $settings["muckrack_icon_color"] ?? "#2d5277" ) ) ?: "#2d5277";
        return "<span class=smpi-author-block-demo style=--smpi-muckrack-color:" . esc_attr( $color ) . ">Author verified by <strong>MuckRack</strong> editorial team <a href=#>(learn more)</a></span>";
    }

    private function author_muckrack_mode_help_html( array $settings ): string {
        $forced = Settings::bool( "muckrack_author_always_show" );
        $style_key = (string) ( $settings["muckrack_icon_style"] ?? "circle_check" );
        $style = "check" === $style_key ? "plain supplied SVG check" : ( "circle_outline_check" === $style_key ? "supplied outline circle SVG" : "supplied solid circle SVG" );
        return "<div class=\"smpi-control-group smpi-feature-mode\"><h3>How this works</h3><div class=smpi-mode-grid><div><strong>Automatic placement</strong><p>SMP detects common Elementor author/byline widgets and adds the selected MuckRack badge beside the visible author when the context is enabled.</p></div><div><strong>Shortcode placement</strong><p>Use a shortcode widget when you need exact manual placement inside an Elementor template.</p></div><div><strong>Current badge style</strong><p>" . esc_html( $style ) . ". Always show for every author is " . ( $forced ? "enabled" : "disabled" ) . ".</p></div></div></div>";
    }

    private function author_muckrack_shortcodes_html(): string {
        $rows = [
            [ "Current post author badge", "[author_muckrack_verified]", "Use inside a single article template or Elementor shortcode widget." ],
            [ "Current post author text", "[author_muckrack_verified type=\"text\"]", "Full verification sentence for the current post author." ],
            [ "Current post author small block", "[author_muckrack_verified style=\"compact_block\"]", "Smaller editorial-block verification treatment for author areas." ],
            [ "Current post author MuckRack URL", "[author_muckrack]", "Returns the current author MuckRack URL." ],
            [ "Specific user badge", "[muckrack_verified type=\"icon\" user_id=\"54\"]", "Use when you know the WordPress user ID." ],
            [ "Specific user text", "[muckrack_verified type=\"text\" user_id=\"54\"]", "Specific user verification sentence." ],
            [ "Specific user raw field", "[acf_author_field field=\"muckrack_url\" user_id=\"54\"]", "Reads a raw author ACF/user field." ],
        ];
        $html = "<div class=\"smpi-control-group smpi-shortcode-reference\"><h3>Copy-ready shortcodes</h3><div class=smpi-shortcode-list>";
        foreach ( $rows as $row ) {
            $html .= "<div class=smpi-shortcode-row><strong>" . esc_html( $row[0] ) . "</strong><code>" . esc_html( $row[1] ) . "</code><small>" . esc_html( $row[2] ) . "</small></div>";
        }
        return $html . "</div></div>";
    }

    private function author_muckrack_examples_html( array $settings ): string {
        return "<div class=\"smpi-control-group smpi-preview-group\"><h3>Visual examples</h3><div class=smpi-preview-stack><div class=smpi-preview-demo><strong>Tooltip / hover badge</strong><p>" . $this->author_tooltip_preview_html( $settings ) . "</p></div><div class=smpi-preview-demo><strong>Inline text</strong><p>" . $this->author_inline_preview_html( $settings ) . "</p></div><div class=smpi-preview-demo><strong>Small editorial block</strong><p>" . $this->author_compact_block_preview_html( $settings ) . "</p></div></div></div>";
    }

    private function publication_preview_sample_html( string $style, array $settings ): string {
        $color = sanitize_hex_color( (string) ( $settings["publication_muckrack_color"] ?? "#2d5277" ) ) ?: "#2d5277";
        $label = "publication_name" === (string) ( $settings["publication_muckrack_text_mode"] ?? "news_outlet" ) ? get_bloginfo( "name" ) : "News outlet";
        $class = "smpi-publication-preview-" . sanitize_html_class( $style );
        $font_size = isset( $settings['publication_muckrack_font_size'] ) ? absint( $settings['publication_muckrack_font_size'] ) : 14;
        $font_size = max( 8, min( 64, $font_size ?: 14 ) );
        return '<span class="' . esc_attr( $class ) . '" style="--smpi-muckrack-color:' . esc_attr( $color ) . ';font-size:' . esc_attr( (string) $font_size ) . 'px">' . esc_html( $label ) . ' verified by <strong class="smpi-publication-preview-brand">MuckRack</strong> editorial team <a href="#">(learn more)</a></span>';
    }

    private function publication_muckrack_preview_html( array $settings ): string {
        $html = "<div class=\"smpi-control-group smpi-preview-group\"><h3>Visual examples</h3><div class=smpi-publication-preview-list>";
        $font_size = isset( $settings['publication_muckrack_font_size'] ) ? absint( $settings['publication_muckrack_font_size'] ) : 14;
        $font_size = max( 8, min( 64, $font_size ?: 14 ) );
        foreach ( [ "block" => "Editorial block", "mini_block" => "Mini editorial block", "compact" => "Compact pill", "minimalist" => "Minimalist text" ] as $style => $label ) {
            $html .= "<div class=smpi-publication-preview-item><strong>" . esc_html( $label ) . "</strong><p>" . $this->publication_preview_sample_html( $style, $settings ) . "</p></div>";
        $font_size = isset( $settings['publication_muckrack_font_size'] ) ? absint( $settings['publication_muckrack_font_size'] ) : 14;
        $font_size = max( 8, min( 64, $font_size ?: 14 ) );
        }
        return $html . "</div></div>";
    }

    private function ico( bool $ok, bool $strict = false ): string {
        if ( $ok ) {
            return "<span class=\"smpi-ico smpi-ico--ok\" aria-hidden=\"true\">&#10003;</span>";
        }
        $class = $strict ? "smpi-ico--bad" : "smpi-ico--warn";
        $glyph = $strict ? "&#10007;" : "&#9888;";
        return "<span class=\"smpi-ico " . $class . "\" aria-hidden=\"true\">" . $glyph . "</span>";
    }

    private function simple_status_html( bool $ok, string $message ): string {
        return "<p class=\"smpi-status-line\">" . $this->ico( $ok ) . "<span>" . esc_html( $message ) . "</span></p>";
    }


    private function post_list_defaults_report_html(): string {
        $enabled = Settings::bool( "post_list_defaults_enabled" );
        $hidden = \smp_publication_integration\Content\PostListDefaults::hidden_columns();
        $user_id = get_current_user_id();
        $user_hidden = $user_id > 0 ? get_user_option( "manageedit-postcolumnshidden", $user_id ) : false;
        $per_page = $user_id > 0 ? get_user_option( "edit_post_per_page", $user_id ) : false;
        $mode = function_exists( "get_user_setting" ) ? get_user_setting( "posts_list_mode", "" ) : "";

        $html = $this->simple_status_html( $enabled, "Default post list view is " . ( $enabled ? "enabled" : "disabled" ) . ". New/default users get 20 items, compact view, and a cleaned column set." );
        $html .= "<table class=\"widefat striped\"><tbody>";
        $html .= "<tr><th>Hidden columns enforced for default users</th><td><code>" . esc_html( implode( "</code>, <code>", $hidden ) ) . "</code></td></tr>";
        $html .= "<tr><th>Visible columns expected</th><td>Author, Tags, Article Types, Date, SEO Details</td></tr>";
        $html .= "<tr><th>Items per page</th><td><code>20</code></td></tr>";
        $html .= "<tr><th>View mode</th><td><code>compact/list</code></td></tr>";
        $html .= "<tr><th>Current user hidden columns</th><td><code>" . esc_html( is_array( $user_hidden ) ? implode( ", ", array_filter( array_map( "strval", $user_hidden ) ) ) : "not customized" ) . "</code></td></tr>";
        $html .= "<tr><th>Current user per page</th><td><code>" . esc_html( false === $per_page ? "not customized" : (string) $per_page ) . "</code></td></tr>";
        $html .= "<tr><th>Current user mode</th><td><code>" . esc_html( "" !== $mode ? $mode : "not set" ) . "</code></td></tr>";
        return $html . "</tbody></table>";
    }

    private function shadow_posts_report_html(): string {
        global $wpdb;
        $complete = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", "_smpi_shadow_complete", "1" ) );
        $home = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", "_smpi_shadow_home", "1" ) );
        $enabled = Settings::bool( "shadow_posts_enabled" );
        $html = $this->simple_status_html( $enabled, "Feature toggle: " . ( $enabled ? "on" : "off" ) . ". Single URLs remain accessible; home/category/tag main queries are filtered only when enabled." );
        $html .= "<table class=widefat><tbody><tr><th>Complete shadow posts</th><td>" . esc_html( (string) $complete ) . "</td></tr><tr><th>Home-only shadow posts</th><td>" . esc_html( (string) $home ) . "</td></tr><tr><th>Post editor controls</th><td><code>Completely shadow post</code> and <code>Shadow from home page only</code></td></tr></tbody></table>";
        return $html;
    }

    private function post_acf_addons_report_html(): string {
        $summary_enabled = Settings::bool( "post_summary_acf_enabled" );
        $faqs_enabled = Settings::bool( "post_faqs_acf_enabled" );
        $summary_registered = function_exists( "acf_get_field" ) && (bool) acf_get_field( "field_65ab7ba0e849b" );
        $faqs_registered = function_exists( "acf_get_field" ) && (bool) acf_get_field( "field_smpi_post_faq_items" );
        $ok = ( ! $summary_enabled || $summary_registered ) && ( ! $faqs_enabled || $faqs_registered );
        $html = $this->simple_status_html( $ok, "Post Summary enabled: " . ( $summary_enabled ? "yes" : "no" ) . ". Post FAQs enabled: " . ( $faqs_enabled ? "yes" : "no" ) . "." );
        $html .= "<table class=\"widefat striped\"><thead><tr><th>Field</th><th>Setting</th><th>ACF runtime</th><th>Key</th><th>Locations</th></tr></thead><tbody>";
        $html .= "<tr><td>Post Summary</td><td>" . ( $summary_enabled ? "Enabled" : "Disabled" ) . "</td><td>" . $this->ico( (bool) $summary_registered ) . "</td><td><code>field_65ab7ba0e849b</code></td><td><code>post</code>, <code>press-release</code>, <code>imported-news</code></td></tr>";
        $html .= "<tr><td>Post FAQs</td><td>" . ( $faqs_enabled ? "Enabled" : "Disabled" ) . "</td><td>" . $this->ico( (bool) $faqs_registered ) . "</td><td><code>field_smpi_post_faq_items</code></td><td><code>post</code>, <code>press-release</code>, <code>imported-news</code></td></tr>";
        return $html . "</tbody></table>";
    }



    private function post_acf_addons_report_text(): string {
        $summary_enabled = Settings::bool( "post_summary_acf_enabled" );
        $faqs_enabled = Settings::bool( "post_faqs_acf_enabled" );
        $summary_registered = function_exists( "acf_get_field" ) && (bool) acf_get_field( "field_65ab7ba0e849b" );
        $faqs_registered = function_exists( "acf_get_field" ) && (bool) acf_get_field( "field_smpi_post_faq_items" );
        return "Post Summary enabled: " . ( $summary_enabled ? "yes" : "no" ) . "; registered: " . ( $summary_registered ? "yes" : "no" ) . ". Post FAQs enabled: " . ( $faqs_enabled ? "yes" : "no" ) . "; registered: " . ( $faqs_registered ? "yes" : "no" ) . ".";
    }

    private function breadcrumbs_report_html(): string {
        $report = \smp_publication_integration\Content\Breadcrumbs::integrity_report();
        $disabled = ! empty( $report["disabled_post_types"] ) ? implode( ", ", array_map( "strval", $report["disabled_post_types"] ) ) : "none";
        $html = $this->simple_status_html( ! empty( $report["enabled"] ), "Breadcrumb feature: " . ( ! empty( $report["enabled"] ) ? "enabled" : "disabled" ) . ". Rank Math renderer available: " . ( ! empty( $report["rank_math_active"] ) ? "yes" : "fallback renderer will be used" ) . "." );
        $html .= "<table class=\"widefat striped\"><tbody>";
        $html .= "<tr><th>Current template</th><td><code>" . esc_html( (string) $report["style"] ) . "</code></td></tr>";
        $html .= "<tr><th>Disabled custom post types</th><td><code>" . esc_html( $disabled ) . "</code></td></tr>";
        $html .= "<tr><th>ACF disabled posts/pages selected</th><td><code>" . esc_html( (string) (int) $report["disabled_object_count"] ) . "</code></td></tr>";
        $html .= "<tr><th>Shortcode</th><td><code>" . esc_html( (string) $report["shortcode"] ) . "</code></td></tr>";
        $html .= "<tr><th>Sample proof URL</th><td>" . ( "" !== (string) $report["sample_url"] ? "<a target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( (string) $report["sample_url"] ) . "\">Open sample</a>" : "<span class=smpi-warn>No published sample found</span>" ) . "</td></tr>";
        return $html . "</tbody></table>";
    }

    private function table_of_contents_report_html(): string {
        $report = \smp_publication_integration\Content\TableOfContents::integrity_report();
        $html = $this->simple_status_html( ! empty( $report["enabled"] ), "Shortcode active: " . (string) $report["shortcode"] . ". Auto single.php placement: " . ( ! empty( $report["auto_single"] ) ? "on" : "off" ) . "." );
        $html .= "<table class=widefat><tbody><tr><th>Sample post</th><td>#" . esc_html( (string) $report["sample_post_id"] ) . "</td></tr><tr><th>Headings found</th><td>" . esc_html( (string) $report["heading_count"] ) . "</td></tr></tbody></table>";
        return $html;
    }

    private function estimated_read_time_report_html(): string {
        $report = \smp_publication_integration\Content\EstimatedReadTime::integrity_report( 5 );
        $html = $this->simple_status_html( ! empty( $report["enabled"] ), "Shortcode active: " . (string) $report["shortcode"] . ". Default output is minutes; use unit=seconds for seconds." );
        $html .= "<table class=\"widefat striped\"><thead><tr><th>Recent post</th><th>Words</th><th>Seconds</th><th>Minutes</th></tr></thead><tbody>";
        foreach ( $report["posts"] as $row ) {
            $html .= "<tr><td>" . esc_html( $row["title"] ) . " (#" . esc_html( (string) $row["post_id"] ) . ")</td><td>" . esc_html( (string) $row["words"] ) . "</td><td>" . esc_html( (string) $row["seconds"] ) . "</td><td>" . esc_html( (string) $row["minutes"] ) . "</td></tr>";
        }
        return $html . "</tbody></table>";
    }

    private function elementor_css_report_html(): string {
        $report = \smp_publication_integration\Content\ElementorCssCacheBusting::test_report();
        $html = $this->simple_status_html( ! empty( $report["enabled"] ) && (int) $report["css_files"] > 0, "Elementor CSS files found: " . (int) $report["css_files"] );
        $html .= "<table class=\"widefat striped\"><thead><tr><th>File</th><th>Readable</th><th>Cache query</th></tr></thead><tbody>";
        foreach ( $report["sample_files"] as $file ) {
            $html .= "<tr><td><code>" . esc_html( $file["file"] ) . "</code></td><td>" . $this->ico( (bool) $file["readable"], true ) . "</td><td><code>" . esc_html( $file["query"] ) . "</code></td></tr>";
        }
        return $html . "</tbody></table>";
    }

    private function muckrack_report_html(): string {
        $rows = \smp_publication_integration\Content\MuckRackVerification::integrity_report( 10 );
        $forced = Settings::bool( "muckrack_author_always_show" );
        $html = $this->simple_status_html( Settings::bool( "muckrack_verified_enabled" ), "Top 10 authors by published posts checked for MuckRack ACF/user fields. Always-show override: " . ( $forced ? "on" : "off" ) . "." );
        $html .= "<table class=\"widefat striped\"><thead><tr><th>User</th><th>Posts</th><th>ACF verified</th><th>Effective</th><th>Forced</th><th>URL</th><th>Description</th></tr></thead><tbody>";
        foreach ( $rows as $row ) {
            $html .= "<tr><td>" . esc_html( $row["display_name"] ) . " (#" . esc_html( (string) $row["user_id"] ) . ")</td><td>" . esc_html( (string) $row["posts"] ) . "</td><td>" . $this->ico( (bool) $row["acf_verified"] ) . "</td><td>" . $this->ico( (bool) $row["verified"] ) . "</td><td>" . ( $row["forced"] ? "YES" : "NO" ) . "</td><td>" . $this->ico( (bool) $row["has_url"] ) . "</td><td>" . $this->ico( (bool) $row["has_description"] ) . "</td></tr>";
        }
        return $html . "</tbody></table>";
    }

    private function publication_muckrack_report_html(): string {
        $report = \smp_publication_integration\Content\MuckRackVerification::publication_report();
        $placements = ! empty( $report["placements"] ) ? implode( ", ", array_map( "sanitize_key", (array) $report["placements"] ) ) : "none selected";
        $html = $this->simple_status_html( ! empty( $report["effective"] ), "Feature toggle: " . ( ! empty( $report["enabled"] ) ? "on" : "off" ) . ". Publication ACF verified: " . ( ! empty( $report["acf_verified"] ) ? "yes" : "no" ) . "." );
        $html .= "<table class=\"widefat striped\"><tbody>";
        $html .= "<tr><th>Text option</th><td><code>" . esc_html( (string) $report["text_mode"] ) . "</code></td></tr>";
        $html .= "<tr><th>Display style</th><td><code>" . esc_html( (string) $report["style"] ) . "</code></td></tr>";
        $html .= "<tr><th>Accent color</th><td><span class=smpi-color-swatch style=\"background:" . esc_attr( (string) $report["color"] ) . "\"></span> <code>" . esc_html( (string) $report["color"] ) . "</code></td></tr>";
        $html .= "<tr><th>Placement</th><td>" . esc_html( $placements ) . "</td></tr>";
        $html .= "<tr><th>MuckRack URL</th><td>" . ( "" !== $report["url"] ? esc_html( (string) $report["url"] ) : $this->ico( false ) . "Missing optional URL." ) . "</td></tr>";
        $html .= "<tr><th>Shortcode</th><td><code>" . esc_html( (string) $report["shortcode"] ) . "</code></td></tr>";
        $html .= "<tr><th>Actual preview</th><td>" . ( "" !== $report["preview_html"] ? wp_kses_post( (string) $report["preview_html"] ) : $this->ico( false ) . "Enable feature and verify publication ACF to render the live shortcode preview. Visual examples are shown above." ) . "</td></tr>";
        return $html . "</tbody></table>";
    }

    private function press_release_report_html(): string {
        $rows = \smp_publication_integration\Content\Visibility::author_report( 10 );
        $hpr = Dependencies::hpr_active();
        $html = $this->simple_status_html( $hpr && Settings::bool( "press_release_include_enabled" ), "Hexa PR Wire active: " . ( $hpr ? "yes" : "no" ) . ". Press-release CPT exists: " . ( post_type_exists( "press-release" ) ? "yes" : "no" ) . "." );
        $html .= "<table class=\"widefat striped\"><thead><tr><th>Recent author</th><th>Posts</th><th>Press releases</th><th>Expected on author.php</th><th>Consistent</th></tr></thead><tbody>";
        foreach ( $rows as $row ) {
            $html .= "<tr><td>" . esc_html( $row["display_name"] ) . " (#" . esc_html( (string) $row["user_id"] ) . ")</td><td>" . esc_html( (string) $row["posts"] ) . "</td><td>" . esc_html( (string) $row["press_releases"] ) . "</td><td>" . ( $row["expected"] ? "YES" : "NO" ) . "</td><td>" . $this->ico( (bool) $row["consistent"], true ) . "</td></tr>";
        }
        return $html . "</tbody></table>";
    }

    private function rank_math_breadcrumb_report_html(): string {
        $rank = get_option( "rank-math-options-general", [] );
        $on = is_array( $rank ) && ( $rank["breadcrumbs"] ?? "" ) === "on";
        return $this->simple_status_html( $on, $on ? "Rank Math breadcrumbs are enabled." : "Rank Math breadcrumbs are disabled or missing." );
    }

    private function hws_masked_login_status(): array {
        $opts = get_option( "hws_login_mask_options", [] );
        $slug = is_array( $opts ) && ! empty( $opts["slug"] ) ? sanitize_title( (string) $opts["slug"] ) : "hexa-admin";
        $enabled = is_array( $opts ) && ! empty( $opts["enabled"] ) && Dependencies::hws_base_tools_active();
        return [ "enabled" => $enabled, "slug" => $slug, "url" => home_url( "/" . $slug . "/" ), "hws_active" => Dependencies::hws_base_tools_active() ];
    }

    private function hws_masked_login_report_html(): string {
        $status = $this->hws_masked_login_status();
        return $this->simple_status_html( ! empty( $status["enabled"] ), "Masked login URL: " . $status["url"] ) . "<p><a class=\"button\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( $status["url"] ) . "\">Open masked admin URL</a></p><p><small>Owned by HWS Base Tools. SMP only reports this.</small></p>";
    }

    private function activity_log_html( string $feature_key = "" ): string {
        $log = Settings::activity_log();
        if ( "" !== $feature_key ) {
            $needle = sanitize_key( $feature_key );
            $log = array_values( array_filter( $log, static function ( array $item ) use ( $needle ): bool {
                return false !== strpos( sanitize_key( (string) ( $item["message"] ?? "" ) ), $needle );
            } ) );
        }
        if ( empty( $log ) ) {
            return "<p>No activity logged for this feature yet.</p>";
        }
        $html = "<ul>";
        foreach ( array_slice( $log, 0, 5 ) as $item ) {
            $html .= "<li><code>" . esc_html( (string) ( $item["time"] ?? "" ) ) . "</code> " . esc_html( (string) ( $item["message"] ?? "" ) ) . "</li>";
        }
        return $html . "</ul>";
    }

    private function ui_cleanup(): void {
        echo "<div class=\"smpi-panel\"><h2>UI Cleanup</h2><p>Hide noisy editor panels from the screens where they actually render. The cleanup behavior is registered admin-wide, not only inside this settings tab.</p><p><strong>Target screens:</strong> <code>profile.php</code>, <code>user-edit.php</code>, <code>post.php</code>, and <code>post-new.php</code>.</p></div>";
        UiCleanup::registry()->render();
    }

    private function optimization(): void {
        echo '<div class="smpi-panel"><h2>Optimization</h2><p>Settings rerooting is intentionally parked until target values are supplied. LiteSpeed checks report current concrete values.</p><button id="smpi-refresh-optimization" class="button button-primary" type="button">Refresh Optimization Report</button><span class="spinner"></span></div><div id="smpi-optimization-report">' . self::render_optimization_report_html() . '</div>';
    }

    public static function render_optimization_report_html(): string {
        $rank = get_option( 'rank-math-options-general', [] );
        $plugin = PluginRegistry::info( 'litespeed-cache/litespeed-cache.php' );
        $checks = [
            [ 'LiteSpeed installed', $plugin['installed'], $plugin['installed'] ? 'LiteSpeed Cache installed.' : 'LiteSpeed Cache missing.' ],
            [ 'LiteSpeed active', $plugin['active'], $plugin['active'] ? 'Version ' . $plugin['version'] : 'Plugin inactive.' ],
            [ 'LiteSpeed update', ! $plugin['update_available'], $plugin['update_available'] ? 'Update available: ' . $plugin['update_version'] : 'No update reported.' ],
            [ 'RankMath breadcrumbs', is_array( $rank ) && ( $rank['breadcrumbs'] ?? '' ) === 'on', is_array( $rank ) && ( $rank['breadcrumbs'] ?? '' ) === 'on' ? 'Breadcrumbs enabled.' : 'Breadcrumbs disabled.' ],
            [ 'Site favicon', (bool) get_site_icon_url(), get_site_icon_url( 32 ) ?: 'No site icon configured.' ],
        ];
        foreach ( [ 'litespeed.conf._version', 'litespeed.conf.cache', 'litespeed.conf.cache-browser', 'litespeed.conf.cache-mobile', 'litespeed.conf.cache-favicon', 'litespeed.conf.optm-css_min', 'litespeed.conf.optm-js_min', 'litespeed.conf.optm-html_min', 'litespeed.conf.media-lazy', 'litespeed.conf.img_optm-auto' ] as $key ) {
            $value = get_option( $key, '__missing__' );
            $checks[] = [ $key, '__missing__' !== $value && '' !== $value, '<code>' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</code>' ];
        }
        ob_start();
        echo '<div class="smpi-grid">';
        foreach ( $checks as $check ) {
            echo '<div class="smpi-card"><h3>' . esc_html( $check[0] ) . '</h3><p>' . $this->ico( (bool) $check[1] ) . wp_kses_post( $check[2] ) . '</p></div>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }


    private function pages_cross_plugin_links_html(): string {
        $items = [
            [
                "plugin_file" => "hws-base-tools/hws-base-tools.php",
                "label" => "HWS Base Tools Pages",
                "url" => admin_url( "options-general.php?page=hws-core-tools&tab=pages" ),
                "description" => "Manage shared site pages: Terms of Use, Privacy Policy, Brand Assets, Headquarters, Contact, and FAQs.",
            ],
            [
                "plugin_file" => "smp-verified-profiles/initialization.php",
                "label" => "SMP Verified Profiles",
                "url" => admin_url( "options-general.php?page=smp-verified-profiles" ),
                "description" => "Manage verified profile pages and profile schema tools.",
            ],
            [
                "plugin_file" => "hexa-pr-wire-distributor/hexa-pr-wire-distributor.php",
                "label" => "Hexa PR Wire",
                "url" => admin_url( "options-general.php?page=hpr-distributor" ),
                "description" => "Manage press-release distribution pages, settings, and release tooling.",
            ],
            [
                "plugin_file" => "sfpf-person-profile-integration/initialization.php",
                "label" => "SFPF Person Profile Integration",
                "url" => admin_url( "options-general.php?page=sfpf-person-profile-integration" ),
                "description" => "Manage SFPF person-profile pages when the plugin is installed.",
            ],
        ];

        $cards = "";
        foreach ( $items as $item ) {
            $info = PluginRegistry::info( (string) $item["plugin_file"] );
            if ( empty( $info["installed"] ) ) {
                continue;
            }

            $active = ! empty( $info["active"] );
            $cards .= "<div class=\"smpi-card smpi-pages-link-card\">";
            $cards .= "<h3>" . esc_html( (string) $item["label"] ) . "</h3>";
            $cards .= "<p>" . esc_html( (string) $item["description"] ) . "</p>";
            $cards .= "<p><span class=\"" . ( $active ? "smpi-ok" : "smpi-warn" ) . "\">" . ( $active ? "Active" : "Installed, inactive" ) . "</span>";
            if ( ! empty( $info["version"] ) ) {
                $cards .= " <code>v" . esc_html( (string) $info["version"] ) . "</code>";
            }
            $cards .= "</p>";
            $cards .= "<p><a class=\"button button-secondary\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( (string) $item["url"] ) . "\">Open in new tab</a></p>";
            $cards .= "</div>";
        }

        if ( "" === $cards ) {
            return "";
        }

        return "<div class=\"smpi-panel smpi-pages-other-plugins\"><h2>Other Plugin Pages</h2><p>Shared site pages now belong in HWS Base Tools. Installed companion page tools are linked here and open in a new tab.</p><div class=\"smpi-grid\">" . $cards . "</div></div>";
    }

    private function pages(): void {
        $manager = PageStructure::manager();
        echo "<div class=\"smpi-panel\"><h2>Publication Pages</h2><p>Assign canonical pages, manage starter templates, and build matching WordPress menus through Hexa Core SiteStructure.</p></div>";
        echo $this->pages_cross_plugin_links_html();
        echo $this->pages_shortcode_reference_html();
        echo ( new SiteStructureRenderer(
            $manager,
            [
                'instance_id'                   => 'smpi-publication-pages',
                'nonce'                         => Ajax::nonce(),
                'card_class'                    => 'smpi-panel hpc-card',
                'table_class'                   => 'widefat striped hpc-table',
                'enable_templates'              => true,
                'enable_template_editors'       => true,
                'template_editor_media_buttons' => false,
                'template_editor_rows'          => 8,
                'show_page_details'             => true,
                'actions'                       => PageStructure::ajax_actions(),
                'labels'                        => [
                    'pages_title'       => 'Critical Publication Pages',
                    'pages_heading'     => 'Publication page assignments',
                    'pages_description' => 'Assign existing pages or create published canonical pages using stored starter templates.',
                    'menus_title'       => 'Publication Navigation Menus',
                    'menus_heading'     => 'Create menus and attach assigned pages',
                    'menus_description' => 'Create WordPress menus, custom menu items, attach assigned pages, and attach publication menu blueprints.',
                ],
            ]
        ) )->render();
    }



    public static function page_detail_html( int $page_id ): string {
        $post = get_post( $page_id );
        if ( ! $post || "page" !== $post->post_type ) {
            return "";
        }

        $status = (string) $post->post_status;
        $status_obj = get_post_status_object( $status );
        $status_label = $status_obj ? (string) $status_obj->label : ucfirst( $status );
        $permalink = (string) Settings::page_slug_url( $page_id );
        $edit_url = (string) get_edit_post_link( $page_id, "raw" );
        $date = get_the_date( "M j, Y g:i a", $page_id );
        $modified = get_the_modified_date( "M j, Y g:i a", $page_id );
        $author = get_the_author_meta( "display_name", (int) $post->post_author );

        ob_start();
        ?>
        <div class="smpi-page-detail" data-page-id="<?php echo esc_attr( (string) $page_id ); ?>">
            <div class="smpi-page-detail-head">
                <div>
                    <h3><?php echo esc_html( get_the_title( $page_id ) ); ?></h3>
                    <p class="smpi-muted">Selected WordPress page for this publication requirement.</p>
                </div>
                <span class="smpi-pill smpi-pill--saved"><?php echo esc_html( $status_label ); ?></span>
            </div>
            <dl class="smpi-page-meta">
                <div><dt>ID</dt><dd><code>#<?php echo esc_html( (string) $page_id ); ?></code></dd></div>
                <div><dt>Status</dt><dd><?php echo esc_html( $status_label ); ?> <code><?php echo esc_html( $status ); ?></code></dd></div>
                <div><dt>Author</dt><dd><?php echo esc_html( $author ?: "Unknown" ); ?></dd></div>
                <div><dt>Created</dt><dd><?php echo esc_html( $date ); ?></dd></div>
                <div><dt>Modified</dt><dd><?php echo esc_html( $modified ); ?></dd></div>
                <div class="smpi-page-meta-wide"><dt>Permalink</dt><dd><a class="smpi-page-permalink" href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $permalink ); ?></a></dd></div>
                <div class="smpi-page-meta-wide smpi-page-slug-row"><dt>Slug</dt><dd><input type="text" class="regular-text smpi-page-slug-input" value="<?php echo esc_attr( (string) $post->post_name ); ?>" aria-label="Page slug"><button type="button" class="button smpi-save-page-slug">Save Slug</button><span class="spinner"></span><span class="smpi-save-state"></span></dd></div>
            </dl>
            <p class="smpi-page-actions"><a class="button button-secondary" href="<?php echo esc_url( $edit_url ); ?>" target="_blank" rel="noopener noreferrer">Edit Page</a> <a class="button button-secondary" href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener noreferrer">View Page</a></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function verified_profiles(): void {
        echo '<div class="smpi-panel"><h2>Verified Profiles Integration</h2><p>Recommended for founder/person bindings. SMP still runs without it when founder marketing is disabled.</p>';
        $this->plugin_table( [ 'smp-verified-profiles/initialization.php' => PluginRegistry::info( 'smp-verified-profiles/initialization.php' ) ] );
        echo '</div>';
    }

    private function integrations(): void {
        echo '<div class="smpi-panel"><h2>Dependency and Plugin Registry</h2><p>Required dependencies block boot when missing. Recommended dependencies are reported when inactive.</p>';
        $this->plugin_table( PluginRegistry::all() );
        echo '</div>';
    }

    public function plugin_row_fragment( string $plugin_file ): string {
        return $this->plugin_row_html( $plugin_file, PluginRegistry::info( $plugin_file ) );
    }

    private function plugin_table( array $plugins ): void {
        echo "<table class=\"widefat striped smpi-plugin-registry\"><thead><tr><th>Plugin</th><th>Requirement</th><th>Status</th><th>Version</th><th>GitHub</th><th>Actions</th></tr></thead><tbody>";
        foreach ( $plugins as $file => $info ) {
            echo $this->plugin_row_html( (string) $file, $info );
        }
        echo "</tbody></table>";
    }

    private function plugin_row_html( string $file, array $info ): string {
        $status = ! empty( $info["active"] ) ? "<span class=\"smpi-ok\">Active</span>" : ( ! empty( $info["installed"] ) ? "<span class=\"smpi-warn\">Installed inactive</span>" : "<span class=\"smpi-bad\">Missing</span>" );
        $version = esc_html( (string) ( $info["version"] ?: "n/a" ) );
        if ( ! empty( $info["github_version"] ) ) {
            $version .= "<br><small>GitHub: " . esc_html( (string) $info["github_version"] ) . "</small>";
        }
        $repo = ! empty( $info["github_repo"] ) ? "<code>" . esc_html( "https://github.com/" . $info["github_repo"] ) . "</code>" : "n/a";
        return "<tr data-plugin-file=\"" . esc_attr( $file ) . "\"><td><strong>" . esc_html( (string) $info["label"] ) . "</strong><br><code>" . esc_html( $file ) . "</code></td><td>" . esc_html( (string) $info["type"] ) . "</td><td>" . $status . "</td><td>" . $version . "</td><td>" . $repo . "</td><td>" . $this->plugin_actions_html( $info ) . "<span class=\"spinner\"></span><span class=\"smpi-save-state\"></span></td></tr>";
    }

    private function plugin_actions_html( array $info ): string {
        $actions = [];
        $installed = ! empty( $info["installed"] );
        $active = ! empty( $info["active"] );
        $type = (string) ( $info["type"] ?? "" );
        if ( ! $installed ) {
            if ( ! empty( $info["github_repo"] ) ) {
                $actions[] = $this->plugin_action_button_html( "install", "Download / Install", true );
            } else {
                $actions[] = "<span class=\"smpi-muted\">No automated install source.</span>";
            }
            return implode( " ", $actions );
        }
        if ( ! $active ) {
            $actions[] = $this->plugin_action_button_html( "activate", "Activate", true );
        }
        if ( $this->plugin_update_available( $info ) ) {
            $actions[] = $this->plugin_action_button_html( "update", "Update", ! empty( $info["github_repo"] ) );
        }
        if ( $active && ! in_array( $type, [ "required", "current" ], true ) ) {
            $actions[] = $this->plugin_action_button_html( "deactivate", "Deactivate", true );
        }
        if ( ! $active && ! in_array( $type, [ "required", "current" ], true ) ) {
            $actions[] = $this->plugin_action_button_html( "delete", "Delete", true );
        }
        if ( empty( $actions ) ) {
            $actions[] = "<span class=\"smpi-muted\">No safe action needed.</span>";
        }
        return implode( " ", $actions );
    }

    private function plugin_action_button_html( string $operation, string $label, bool $enabled ): string {
        $disabled = $enabled ? "" : " disabled";
        return "<button type=\"button\" class=\"button smpi-plugin-action\" data-operation=\"" . esc_attr( $operation ) . "\"" . $disabled . ">" . esc_html( $label ) . "</button>";
    }

    private function plugin_update_available( array $info ): bool {
        if ( ! empty( $info["update_available"] ) ) {
            return true;
        }
        $local = (string) ( $info["version"] ?? "" );
        $remote = (string) ( $info["github_version"] ?? "" );
        return "" !== $local && "" !== $remote && version_compare( $remote, $local, ">" );
    }

    private function counts(): array {
        $out = [];
        foreach ( [ 'post', 'page', 'profile', 'press-release' ] as $type ) {
            $count = wp_count_posts( $type );
            $out[ $type ] = $count && isset( $count->publish ) ? (int) $count->publish : 0;
        }
        return $out;
    }

    private function latest_post_id(): int {
        $query = new \WP_Query( [ "post_type" => "post", "post_status" => "publish", "posts_per_page" => 1, "fields" => "ids", "no_found_rows" => true ] );
        return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
    }

    private function schema_detection_report_html(): string {
        $cached = get_transient( self::SCHEMA_DETECTION_CACHE_KEY );
        if ( is_array( $cached ) && isset( $cached["scans"] ) && is_array( $cached["scans"] ) ) {
            $html = $this->render_schema_detection_report( $cached["scans"] );
            $generated_at = isset( $cached["generated_at"] ) ? (int) $cached["generated_at"] : 0;
            if ( $generated_at > 0 ) {
                $html .= "<p class=\"smpi-muted\">Schema detection cache generated " . esc_html( human_time_diff( $generated_at, time() ) ) . " ago. The report refreshes in the background and no longer blocks this tab.</p>";
            }

            self::schedule_schema_detection_refresh();
            return $html;
        }

        self::schedule_schema_detection_refresh();
        return "<div class=\"smpi-panel\"><h2>Hexa Core Schema Detection</h2><p>Schema detection is queued for background refresh. This tab no longer fetches the homepage and recent posts during page render.</p><p class=\"smpi-muted\">Refresh this tab after a minute to view the cached scan.</p></div>";
    }

    public static function refresh_schema_detection_report(): void {
        $scanner = new SchemaPageScanner();
        $scans = [];

        $scans[] = $scanner->scanUrl( home_url( "/" ), [
            "title"      => "Homepage",
            "cache_bust" => true,
            "timeout"    => 5,
        ] );

        foreach ( self::recent_schema_post_ids( 10 ) as $post_id ) {
            $title = get_the_title( $post_id );
            $scans[] = $scanner->scanUrl( get_permalink( $post_id ), [
                "title"      => "Post #" . $post_id . ( "" !== $title ? ": " . $title : "" ),
                "cache_bust" => true,
                "timeout"    => 5,
            ] );
        }

        set_transient(
            self::SCHEMA_DETECTION_CACHE_KEY,
            [
                "generated_at" => time(),
                "scans"        => $scans,
            ],
            6 * HOUR_IN_SECONDS
        );
        delete_transient( self::SCHEMA_DETECTION_REFRESH_LOCK_KEY );
    }

    private function render_schema_detection_report( array $scans ): string {
        return ( new SchemaScanRenderer() )->renderReport( $scans, [
            "title"    => "Hexa Core Schema Detection",
            "subtitle" => "Scans the homepage and the 10 most recent published posts through Hexa WordPress Plugin Core.",
            "expected" => [
                "Homepage: NewsMediaOrganization, WebSite, CollectionPage, and ItemList.",
                "Posts: NewsMediaOrganization, WebSite, WebPage, article schema, author, image, BreadcrumbList, and FAQPage when article FAQ rows are enabled.",
            ],
            "debug"    => false,
        ] );
    }

    private static function schedule_schema_detection_refresh(): void {
        if ( false !== get_transient( self::SCHEMA_DETECTION_REFRESH_LOCK_KEY ) ) {
            return;
        }

        if ( ! wp_next_scheduled( "smpi_refresh_schema_detection_report" ) ) {
            wp_schedule_single_event( time() + MINUTE_IN_SECONDS, "smpi_refresh_schema_detection_report" );
        }

        set_transient( self::SCHEMA_DETECTION_REFRESH_LOCK_KEY, "1", 15 * MINUTE_IN_SECONDS );
    }

    private static function recent_schema_post_ids( int $limit = 10 ): array {
        $query = new \WP_Query( [
            "post_type"      => "post",
            "post_status"    => "publish",
            "posts_per_page" => max( 1, $limit ),
            "fields"         => "ids",
            "no_found_rows"  => true,
        ] );

        return array_map( "intval", (array) $query->posts );
    }
    private function toggle( string $key, string $label, array $settings ): void {
        echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input class="smpi-setting" type="checkbox" data-key="' . esc_attr( $key ) . '" value="1" ' . checked( ! empty( $settings[ $key ] ), true, false ) . '> Enabled</label><span class="spinner"></span><span class="smpi-save-state"></span></td></tr>';
    }

    private function card( string $title, string $content ): void {
        echo '<div class="smpi-card"><h3>' . esc_html( $title ) . '</h3><p>' . wp_kses_post( $content ) . '</p></div>';
    }

    private function status_card( string $title, bool $ok, string $message ): void {
        $this->card( $title, $this->ico( $ok ) . esc_html( $message ) );
    }



    private function scripts(): void {
        $active = isset( $_GET["tab"] ) ? sanitize_key( wp_unslash( $_GET["tab"] ) ) : "overview";
        ?>
        <script>
        window.smpiAdmin={ajaxUrl:ajaxurl,nonce:<?php echo wp_json_encode( Ajax::nonce() ); ?>,pageUrl:<?php echo wp_json_encode( admin_url( "options-general.php?page=" . Config::$settings_page_slug ) ); ?>,activeTab:<?php echo wp_json_encode( $active ); ?>};
        jQuery(function($){
            function tabPanel(){return $(`#smpi-tab-panel`)}
            function tabUrl(tab){return smpiAdmin.pageUrl+`&tab=`+encodeURIComponent(tab)}
            function destroyDynamicEditors(root){if(window.acf){try{acf.doAction(`remove`,root)}catch(e){}}if(!window.tinymce)return;root.find(`textarea.wp-editor-area`).each(function(){var id=this.id;if(id&&tinymce.get(id)){tinymce.get(id).remove()}})}
            function initAcfFields(root){if(window.acf){try{acf.doAction(`append`,root)}catch(e){}}}
            function initDynamicEditors(root){if(!window.wp||!wp.editor)return;root.find(`.smpi-page-template-editor textarea`).each(function(){var id=this.id;if(!id)return;if(window.tinymce&&tinymce.get(id)){tinymce.get(id).remove()}try{wp.editor.initialize(id,{tinymce:true,quicktags:true,mediaButtons:$(this).closest(`.smpi-page-template-editor`).length>0})}catch(e){}})}
            function setActiveTab(tab){$(`.smpi-tab-btn`).removeClass(`active`).attr(`aria-selected`,`false`);$(`.smpi-tab-btn[data-tab="${tab}"]`).addClass(`active`).attr(`aria-selected`,`true`);smpiAdmin.activeTab=tab}
            function loadTab(tab,href,push){var panel=tabPanel(),status=$(`.smpi-tab-status`),msg=status.find(`.smpi-tab-message`);if(!tab||panel.data(`loading`))return;panel.data(`loading`,1).addClass(`is-loading`).attr(`aria-busy`,`true`);status.find(`.spinner`).addClass(`is-active`);msg.text(`Loading...`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_load_tab`,nonce:smpiAdmin.nonce,tab:tab}).done(function(x){if(!x||!x.success){msg.text(`Error loading tab.`);return}destroyDynamicEditors(panel);panel.html(x.data.html||``).attr(`data-active-tab`,x.data.tab).attr(`aria-busy`,`false`);setActiveTab(x.data.tab);msg.text(`Loaded ${x.data.label}.`);if(push!==false&&window.history&&history.pushState){history.pushState({smpiTab:x.data.tab},``,href||tabUrl(x.data.tab))}initAcfFields(panel);initDynamicEditors(panel)}).fail(function(){msg.text(`Error loading tab.`)}).always(function(){panel.removeData(`loading`).removeClass(`is-loading`).attr(`aria-busy`,`false`);status.find(`.spinner`).removeClass(`is-active`)})}
            initDynamicEditors(tabPanel());
            document.addEventListener("hexa-core-host-tab-before-load",function(ev){if(!ev.detail||!ev.detail.panel||ev.detail.panel.id!=="smpi-tab-panel")return;destroyDynamicEditors($(ev.detail.panel))});
            document.addEventListener("hexa-core-host-tab-loaded",function(ev){if(!ev.detail||!ev.detail.panel||ev.detail.panel.id!=="smpi-tab-panel")return;smpiAdmin.activeTab=ev.detail.tab||smpiAdmin.activeTab;initAcfFields($(ev.detail.panel));initDynamicEditors($(ev.detail.panel))});
            function refreshActiveFragment(fragment){if(!fragment||!fragment.html)return;var panel=tabPanel(),y=window.scrollY||window.pageYOffset||0;destroyDynamicEditors(panel);panel.html(fragment.html).attr(`data-active-tab`,fragment.tab||smpiAdmin.activeTab).attr(`aria-busy`,`false`);initAcfFields(panel);initDynamicEditors(panel);if(window.scrollTo){window.scrollTo(0,y)}}function saveMessage(x,fallback){if(x&&x.data){if(typeof x.data.message===`string`&&x.data.message)return x.data.message;if(typeof x.data.error===`string`&&x.data.error)return x.data.error}return fallback||`Unknown error`}function saveRoot(e){return e.closest(`.smpi-control-group,td,.smpi-user-picker,.smpi-profile-picker,.smpi-feature-card`)}function setSaveState(root,state,message){var s=root.find(`.smpi-save-state`).first();if(!s.length)return;s.removeClass(`is-saving is-saved is-error smpi-ok smpi-bad`).attr(`aria-live`,`polite`);if(state===`saving`){s.addClass(`is-saving`).text(`Saving...`);return}if(state===`saved`){s.addClass(`is-saved`).text(`✓ Saved`);return}s.addClass(`is-error`).text(`✕ Error: `+(message||`Save failed`))}function saveSetting(e, done){var k=e.data(`key`),d={action:`smpi_save_settings`,nonce:smpiAdmin.nonce,tab:smpiAdmin.activeTab},v;if(!k)return;if(e.hasClass(`smpi-setting-array`)){var g=$(`.smpi-setting-array[data-key="${k}"]`);d[k+`_present`]=1;d[k]=g.filter(`:checked`).map(function(){return $(this).val()}).get()}else{v=e.is(`:checkbox`)?(e.is(`:checked`)?1:0):e.val();d[k]=v}var r=saveRoot(e);r.find(`.spinner`).addClass(`is-active`);setSaveState(r,`saving`);$.post(smpiAdmin.ajaxUrl,d).done(function(x){var ok=!!(x&&x.success);setSaveState(r,ok?`saved`:`error`,saveMessage(x,`Server rejected the save.`));if(ok&&x.data&&x.data.fragment){refreshActiveFragment(x.data.fragment);$(`.smpi-tab-message`).text(`Saved and refreshed ${x.data.fragment.label}.`)}else if(ok){$(`.smpi-tab-message`).text(saveMessage(x,`Saved.`))}if(done)done(x)}).fail(function(xhr){setSaveState(r,`error`,`HTTP ${xhr.status||0} ${xhr.statusText||`request failed`}`);if(done)done(null)}).always(function(){r.find(`.spinner`).removeClass(`is-active`)})}
            $(document).on(`change`,`.smpi-setting,.smpi-setting-array`,function(){saveSetting($(this))});
            function smpiHex(v){v=String(v||``).trim().toLowerCase();if(v&&v.charAt(0)!==`#`)v=`#`+v;return /^#[0-9a-f]{6}$/.test(v)?v:``}
            function smpiSyncColor(wrap,value,isEmpty){var hex=smpiHex(value),display=isEmpty?((wrap.find(`[data-smpi-empty-label]`).data(`smpi-empty-label`)||`inherit`)):hex;if(hex){wrap.find(`.smpi-color-swatch`).css(`background`,hex);wrap.find(`[data-hpc-copy]`).attr(`data-hpc-copy`,hex)}wrap.find(`[data-smpi-color-hex]`).text(display||hex||`inherit`)}
            $(document).on(`input`,`.smpi-color-setting,.smpi-color-picker`,function(){var input=$(this),wrap=input.closest(`.smpi-color-control`);smpiSyncColor(wrap,input.val(),false)});
            $(document).on(`change`,`.smpi-color-picker`,function(){var input=$(this),key=input.data(`smpi-sync-key`),hidden=$(`.smpi-color-hidden[data-key="${key}"]`).first(),hex=smpiHex(input.val());smpiSyncColor(input.closest(`.smpi-color-control`),hex,false);hidden.val(hex).trigger(`change`)});
            $(document).on(`click`,`.smpi-color-inherit`,function(){var b=$(this),key=b.data(`smpi-sync-key`),hidden=$(`.smpi-color-hidden[data-key="${key}"]`).first(),picker=$(`.smpi-color-picker[data-smpi-sync-key="${key}"]`).first();hidden.val(``).trigger(`change`);smpiSyncColor(b.closest(`.smpi-color-control`),picker.val(),true)});
            var smpiPV={'breadcrumbs_accent_color':['--smpi-bc-accent',''],'breadcrumbs_font_size':['--smpi-bc-font-size','px'],'table_of_contents_accent_color':['--smpi-toc-accent',''],'table_of_contents_text_color':['--smpi-toc-text',''],'table_of_contents_text_font_size':['--smpi-toc-size','px'],'table_of_contents_text_font_style':['--smpi-toc-fstyle',''],'post_faqs_accent_color':['--smpi-faq-accent',''],'post_faqs_text_color':['--smpi-faq-text',''],'post_faqs_text_font_size':['--smpi-faq-size','px'],'post_faqs_text_font_style':['--smpi-faq-fstyle',''],'inline_photo_accent_color':['--smpi-photo-accent',''],'inline_photo_caption_text_color':['--smpi-photo-cap-color',''],'inline_photo_caption_font_size':['--smpi-photo-cap-size','px'],'inline_photo_caption_font_style':['--smpi-photo-cap-fstyle',''],'featured_image_caption_accent_color':['--smpi-fi-accent',''],'featured_image_caption_text_color':['--smpi-fi-cap-color',''],'featured_image_caption_font_size':['--smpi-fi-cap-size','px'],'featured_image_caption_font_style':['--smpi-fi-cap-fstyle','']};
            $(document).on(`input change`,`.smpi-setting`,function(){var k=$(this).data(`key`),m=smpiPV[k];if(!m)return;var host=document.querySelector(`.smpi-design-host`);if(host){host.style.setProperty(m[0],String($(this).val())+m[1])}});
            var userTimer=null;
            function lockUserCard(u){return `<div class="smpi-profile-card"><div class="smpi-profile-avatar"><img src="${u.avatar}" alt=""></div><div class="smpi-profile-info"><h3>${u.name||u.label}</h3><p><span class="dashicons dashicons-email"></span> ${u.email||``}</p><p><a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="${u.edit_url}">Edit Profile</a> <a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="${u.view_url}">View Author Page</a></p></div></div>`}
            $(document).on(`input`,`.smpi-user-search`,function(){var input=$(this),picker=input.closest(`.smpi-user-picker`),box=picker.find(`.smpi-user-results`),term=input.val();clearTimeout(userTimer);if(term.length<2){box.empty();return}userTimer=setTimeout(function(){picker.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_search_users`,nonce:smpiAdmin.nonce,term:term}).done(function(x){box.empty();if(!x.success||!x.data.users.length){box.html(`<p class="smpi-muted">No matching users.</p>`);return}$.each(x.data.users,function(i,u){var b=$(`<button type="button" class="button smpi-user-result"></button>`).html(`<strong>${u.label}</strong> <span class="smpi-muted">${u.email}</span>`).data(`user`,u);box.append(b)})}).always(function(){picker.find(`.spinner`).removeClass(`is-active`)})},250)});
            $(document).on(`click`,`.smpi-user-result`,function(){var u=$(this).data(`user`),picker=$(this).closest(`.smpi-user-picker`);picker.find(`.smpi-publication-user-setting`).val(u.id);picker.find(`.smpi-current-user-summary`).html(lockUserCard(u));picker.find(`.smpi-user-results`).empty();picker.find(`.smpi-user-search`).val(``);picker.addClass(`is-locked`);saveSetting(picker.find(`.smpi-publication-user-setting`),function(x){picker.find(`.smpi-save-state`).text(x&&x.success?` Saved`:` Error`)})});
            $(document).on(`click`,`.smpi-change-user`,function(){var picker=$(this).closest(`.smpi-user-picker`);picker.removeClass(`is-locked`);picker.find(`.smpi-user-results`).empty();picker.find(`.smpi-user-search`).val(``).trigger(`focus`)});
            $(document).on(`click`,`.smpi-cancel-user`,function(){var picker=$(this).closest(`.smpi-user-picker`);if(parseInt(picker.find(`.smpi-publication-user-setting`).val(),10)>0){picker.addClass(`is-locked`)}picker.find(`.smpi-user-results`).empty()});
            $(document).on(`click`,`.smpi-clear-user`,function(){var picker=$(this).closest(`.smpi-user-picker`);picker.find(`.smpi-user-search`).val(``);picker.find(`.smpi-publication-user-setting`).val(0);picker.find(`.smpi-user-results`).empty();picker.removeClass(`is-locked`);picker.find(`.smpi-current-user-summary`).html(`<div class="smpi-empty-state"><strong>No main publication profile selected.</strong><p>Search by publication name, username, or email and choose the profile that represents this publication.</p></div>`);saveSetting(picker.find(`.smpi-publication-user-setting`))});
            var shortcodeUserTimer=null;
            function smpiLoadShortcodeUser(wrap,u){wrap.attr(`data-selected-user`,u.id);wrap.find(`.smpi-save-state`).text(`Loading...`);wrap.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_shortcode_user_preview`,nonce:smpiAdmin.nonce,user_id:u.id}).done(function(x){if(x&&x.success){$(`#smpi-shortcode-selected-user`).html(x.data.user||``);$(`#smpi-shortcode-user-values`).html(x.data.html||``);wrap.find(`.smpi-save-state`).text(`Loaded `+u.label)}else{wrap.find(`.smpi-save-state`).text(`Error`)}}).fail(function(){wrap.find(`.smpi-save-state`).text(`Error`)}).always(function(){wrap.find(`.spinner`).removeClass(`is-active`)})}
            $(document).on(`input` ,`.smpi-shortcode-user-search`,function(){var input=$(this),wrap=input.closest(`.smpi-shortcode-user-picker`),box=wrap.find(`.smpi-shortcode-user-results`),term=input.val();clearTimeout(shortcodeUserTimer);if(term.length<2){box.empty();return}shortcodeUserTimer=setTimeout(function(){wrap.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_search_users`,nonce:smpiAdmin.nonce,term:term}).done(function(x){box.empty();if(!x.success||!x.data.users.length){box.html(`<p class="smpi-muted">No matching users.</p>`);return}$.each(x.data.users,function(i,u){var b=$(`<button type="button" class="button smpi-shortcode-user-result"></button>`).html(`<strong>`+u.label+`</strong> <span class="smpi-muted">`+u.email+`</span>`).data(`user`,u);box.append(b)})}).always(function(){wrap.find(`.spinner`).removeClass(`is-active`)})},250)});
            $(document).on(`click` ,`.smpi-shortcode-user-result`,function(){var u=$(this).data(`user`),wrap=$(this).closest(`.smpi-shortcode-user-picker`);wrap.find(`.smpi-shortcode-user-results`).empty();wrap.find(`.smpi-shortcode-user-search`).val(u.name||u.label);smpiLoadShortcodeUser(wrap,u)});

            function founderIds(panel){return panel.find(`.smpi-founder-profile-card`).map(function(){return $(this).data(`profile-id`)}).get()}
            function founderEmptyHtml(){return `<div class="smpi-empty-state smpi-empty-founder-profiles"><strong>No founder profiles selected.</strong><p>Use the search above to add founder records from Verified Profiles.</p></div>`}
            function profileCard(p){var media=p.thumbnail?`<img src="${p.thumbnail}" alt="">`:`<span class="dashicons dashicons-id-alt"></span>`;return `<div class="smpi-founder-profile-card" data-profile-id="${p.id}"><div class="smpi-founder-thumb">${media}</div><div class="smpi-founder-info"><strong>${p.label}</strong><p class="smpi-muted">Profile #${p.id}</p><p><a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="${p.edit_url}">Edit Profile</a> <a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="${p.view_url}">View Profile</a> <button type="button" class="button smpi-remove-founder-profile">Remove</button></p></div></div>`}
            function smpiProfileFromCoreItem(item){var id=parseInt(item.id||item.value,10)||0;return{id:id,label:item.label||item.name||(`Profile #`+id),status:item.status||``,edit_url:item.edit_url||item.url||``,view_url:item.view_url||``,thumbnail:item.thumbnail||``}}
            document.addEventListener("hexa-search-selected",function(ev){if(!ev.detail||ev.detail.component_id!=="smpi-founder-profile-core-search")return;var p=smpiProfileFromCoreItem(ev.detail.item||{});if(!p.id)return;var wrap=$("#smpi-founder-profile-core-search").closest(".smpi-profile-picker"),selected=wrap.find(".smpi-founder-selected");if(!selected.find(".smpi-founder-profile-card[data-profile-id=\""+p.id+"\"]").length){selected.find(".smpi-empty-founder-profiles").remove();selected.append(profileCard(p));saveFounderProfiles(selected)}wrap.find(".hpc-smart-search-input").val("");wrap.find(".hpc-smart-search-selected").attr("hidden",true).empty();wrap.find(".hpc-smart-search-status").text("Added founder profile.")});
            function saveFounderProfiles(panel){var ids=founderIds(panel),wrap=panel.closest(`.smpi-profile-picker`);wrap.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_save_founder_profiles`,nonce:smpiAdmin.nonce,founder_profile_ids:ids}).done(function(x){wrap.find(`.smpi-save-state`).text(x.success?` Saved`:` Error`)}).always(function(){wrap.find(`.spinner`).removeClass(`is-active`)})}
            function pageTemplateValue(row){var wrap=row.find(`.smpi-page-template-editor`).first(),id=wrap.data(`editor-id`);if(!id)return ``;if(window.tinymce&&tinymce.get(id)){tinymce.get(id).save()}return $(`#${id}`).val()||``}
            var profileTimer=null;
            $(document).on(`input`,`.smpi-profile-search`,function(){var input=$(this),wrap=input.closest(`.smpi-profile-picker`),box=wrap.find(`.smpi-profile-results`),term=input.val();clearTimeout(profileTimer);if(term.length<2){box.empty();return}profileTimer=setTimeout(function(){wrap.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_search_profiles`,nonce:smpiAdmin.nonce,term:term}).done(function(x){box.empty();if(!x.success||!x.data.profiles.length){box.html(`<p class="smpi-muted">No matching profiles.</p>`);return}$.each(x.data.profiles,function(i,p){var b=$(`<button type="button" class="button smpi-profile-result"></button>`).text(p.label+` (#`+p.id+`)`).data(`profile`,p);box.append(b)})}).always(function(){wrap.find(`.spinner`).removeClass(`is-active`)})},250)});
            $(document).on(`click`,`.smpi-profile-result`,function(){var p=$(this).data(`profile`),wrap=$(this).closest(`.smpi-profile-picker`),selected=wrap.find(`.smpi-founder-selected`);if(!selected.find(`.smpi-founder-profile-card[data-profile-id="${p.id}"]`).length){selected.find(`.smpi-empty-founder-profiles`).remove();selected.append(profileCard(p));saveFounderProfiles(selected)}wrap.find(`.smpi-profile-search`).val(``);wrap.find(`.smpi-profile-results`).empty()});
            $(document).on(`click`,`.smpi-remove-founder-profile`,function(){var selected=$(this).closest(`.smpi-founder-selected`);$(this).closest(`.smpi-founder-profile-card`).remove();if(!selected.find(`.smpi-founder-profile-card`).length){selected.html(founderEmptyHtml())}saveFounderProfiles(selected)});
            function smpiSetPageDetail(row,page){var wrap=row.find(`.smpi-page-detail-wrap`);if(page&&page.detail_html){wrap.html(page.detail_html)}else{wrap.empty()}}
            function smpiUpdatePageOption(row,page){if(!page||!page.id)return;var sel=row.find(`.smpi-page-select`),label=(page.title||`Untitled`)+` (#`+page.id+`)`,opt=sel.find(`option[value="`+page.id+`"]`);if(!opt.length){sel.append($(`<option></option>`).attr(`value`,page.id).text(label))}else{opt.text(label)}sel.val(page.id)}
            function smpiPageState(row,message,ok){var state=row.find(`> p .smpi-save-state`).first();state.text(message||``).toggleClass(`smpi-ok`,!!ok).toggleClass(`smpi-bad`,ok===false)}
            $(document).on(`change`,`.smpi-page-select`,function(){var r=$(this).closest(`.smpi-page-row`),pageId=parseInt($(this).val(),10)||0;if(!pageId){smpiSetPageDetail(r,null);smpiPageState(r,`Not assigned`,false);return}r.find(`> p .spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_page_details`,nonce:smpiAdmin.nonce,page_id:pageId}).done(function(x){if(x&&x.success&&x.data&&x.data.page){smpiSetPageDetail(r,x.data.page);smpiPageState(r,`Loaded page details`,true)}else{smpiPageState(r,`Could not load page details`,false)}}).always(function(){r.find(`> p .spinner`).removeClass(`is-active`)})});
            $(document).on(`click`,`.smpi-save-page`,function(){var r=$(this).closest(`.smpi-page-row`),d={action:`smpi_save_page_assignment`,nonce:smpiAdmin.nonce,page_type:r.data(`page-type`),page_id:r.find(`.smpi-page-select`).val(),template:pageTemplateValue(r)};r.find(`> p .spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,d).done(function(x){if(x&&x.success){if(x.data&&x.data.page){smpiSetPageDetail(r,x.data.page)}else{smpiSetPageDetail(r,null)}smpiPageState(r,`Saved page assignment`,true)}else{smpiPageState(r,`Error saving page assignment`,false)}}).always(function(){r.find(`> p .spinner`).removeClass(`is-active`)})});
            $(document).on(`click`,`.smpi-create-page`,function(){var r=$(this).closest(`.smpi-page-row`),d={action:`smpi_create_page_assignment`,nonce:smpiAdmin.nonce,page_type:r.data(`page-type`)};r.find(`> p .spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,d).done(function(x){if(x&&x.success&&x.data&&x.data.page){smpiUpdatePageOption(r,x.data.page);smpiSetPageDetail(r,x.data.page);smpiPageState(r,x.data.message||`Page created and assigned`,true)}else{smpiPageState(r,(x&&x.data&&x.data.message)||`Error creating page`,false)}}).fail(function(){smpiPageState(r,`Error creating page`,false)}).always(function(){r.find(`> p .spinner`).removeClass(`is-active`)})});
            $(document).on(`click`,`.smpi-save-page-slug`,function(){var b=$(this),card=b.closest(`.smpi-page-detail`),r=b.closest(`.smpi-page-row`),slug=card.find(`.smpi-page-slug-input`).val();card.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_update_page_slug`,nonce:smpiAdmin.nonce,page_type:r.data(`page-type`),page_id:card.data(`page-id`),slug:slug}).done(function(x){if(x&&x.success&&x.data&&x.data.page){smpiUpdatePageOption(r,x.data.page);smpiSetPageDetail(r,x.data.page);smpiPageState(r,x.data.message||`Slug updated`,true)}else{card.find(`.smpi-save-state`).text((x&&x.data&&x.data.message)||`Error`).addClass(`smpi-bad`)}}).fail(function(){card.find(`.smpi-save-state`).text(`Error`).addClass(`smpi-bad`)}).always(function(){card.find(`.spinner`).removeClass(`is-active`)})});
            $(document).on(`click`,`#smpi-refresh-optimization`,function(){var s=$(this).next(`.spinner`);s.addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_refresh_optimization`,nonce:smpiAdmin.nonce}).done(function(x){if(x.success)$(`#smpi-optimization-report`).html(x.data.html)}).always(function(){s.removeClass(`is-active`)})});
            $(document).on(`click`,`.smpi-plugin-action`,function(){var b=$(this),r=b.closest(`tr`);if(b.prop(`disabled`))return;if(b.data(`operation`)===`delete`&&!confirm(`Delete this plugin?`))return;r.find(`.spinner`).addClass(`is-active`);$.post(smpiAdmin.ajaxUrl,{action:`smpi_plugin_action`,nonce:smpiAdmin.nonce,plugin_file:r.data(`plugin-file`),operation:b.data(`operation`)}).done(function(x){if(x&&x.success&&x.data&&x.data.row_html){r.replaceWith(x.data.row_html)}else{r.find(`.smpi-save-state`).text(` Error`)}}).fail(function(){r.find(`.smpi-save-state`).text(` Error`)}).always(function(){r.find(`.spinner`).removeClass(`is-active`)})});
            var o=0,bs=20,total=0;$(document).on(`click`,`#smpi-reprocess-schema`,function(){o=0;total=0;$(this).prop(`disabled`,true);$(`#smpi-schema-report`).empty().append(`<p>Starting...</p>`);p()});function p(){$.post(ajaxurl,{action:`smpi_reprocess_schema`,nonce:smpiAdmin.nonce,offset:o,batch_size:bs}).done(function(x){if(!x||!x.success){$(`#smpi-reprocess-schema`).prop(`disabled`,false);return}total=x.data.total||0;$.each(x.data.items||[],function(i,it){$(`#smpi-schema-report`).append(`<pre class="smpi-code">${$(`<div>`).text(it.schema||``).html()}</pre>`)});o+=bs;if(o<total)p();else $(`#smpi-reprocess-schema`).prop(`disabled`,false)})}
        });</script>
    <?php }

    private function styles(): void { ?>
        <style>.smpi-dashboard{max-width:1280px}.smpi-tabs-nav{display:flex;flex-wrap:wrap;gap:8px;margin:18px 0;border-bottom:1px solid #dcdcde}.smpi-tab-btn{border:1px solid #dcdcde;border-bottom:none;background:#f6f7f7;padding:10px 14px;border-radius:8px 8px 0 0;cursor:pointer;text-decoration:none;color:#1d2327;display:inline-block}.smpi-tab-btn.active{background:#fff;color:#2271b1;font-weight:700}.smpi-tab-status{display:flex;align-items:center;gap:6px;min-height:24px;margin:-8px 0 8px}.smpi-tab-status .spinner{float:none;margin:0}.smpi-tab-message{color:#646970;font-size:12px}.smpi-tab-content{display:none;position:relative}.smpi-tab-content.active{display:block}.smpi-tab-content.is-loading{opacity:.55;pointer-events:none}.smpi-hero{margin:18px 0;padding:28px 30px;border:1px solid #dcdcde;border-radius:14px;background:linear-gradient(135deg,#fff 0%,#eef6fb 100%);box-shadow:0 10px 28px rgba(0,0,0,.05)}.smpi-kicker{margin:0 0 8px;color:#2271b1;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.smpi-hero h2{margin:0 0 10px;font-size:22px;line-height:1.3;max-width:54ch}.smpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;margin:18px 0}.smpi-card,.smpi-panel{padding:18px;border:1px solid #dcdcde;border-radius:12px;background:#fff;margin:16px 0}.smpi-card h3,.smpi-panel h2{margin-top:0}.smpi-panel h2{font-size:16px;margin-bottom:14px}.smpi-ok{color:#008a20;font-weight:700}.smpi-warn{color:#996800;font-weight:700}.smpi-bad{color:#b32d2e;font-weight:700}.smpi-code,.smpi-code-panel{white-space:pre-wrap;background:#101517;color:#e6edf3;border:1px solid #1f2933;border-radius:10px;padding:14px;max-height:520px;overflow:auto}.smpi-page-select,.smpi-mapping-select{min-width:320px}.smpi-save-state{align-items:center;border-radius:999px;display:inline-flex;font-weight:800;margin-left:8px;min-height:24px;padding:3px 9px}.smpi-save-state:empty{display:none}.smpi-save-state.is-saving{background:#eef2f7;color:#475569}.smpi-save-state.is-saved{background:#e6f4ea;color:#137333}.smpi-save-state.is-error{background:#fce8e6;color:#b32d2e;white-space:normal}.smpi-user-picker{padding:14px;border:1px solid #dcdcde;border-radius:10px;background:#f9fafb;margin:12px 0}.smpi-user-results{display:grid;gap:6px;margin-top:10px;max-width:720px}.smpi-user-result{text-align:left;justify-content:flex-start}.smpi-profile-card{display:flex;gap:18px;align-items:center;padding:18px;border:1px solid #e5e7eb;border-radius:12px;background:#f9fafb;margin:14px 0}.smpi-profile-avatar img{width:96px;height:96px;border-radius:999px;background:#fff;object-fit:cover;box-shadow:0 2px 10px rgba(0,0,0,.08)}.smpi-profile-info h3{margin:0 0 6px}.smpi-profile-fields{display:grid;gap:10px;margin-top:12px}.smpi-field-preview{padding:11px 14px;border:1px solid #e5e7eb;border-radius:8px;background:#fff}.smpi-field-preview p{margin:6px 0 0}.smpi-muted{color:#646970}.smpi-alert{padding:12px 14px;border-radius:8px;border:1px solid #f0c36d;background:#fff8e5}.smpi-alert-warning{color:#664d03}.smpi-publication-author-panel{padding:0;overflow:hidden}.smpi-publication-author-panel .smpi-overview-section{padding:24px 28px;background:linear-gradient(135deg,#111827 0%,#1f3a5f 100%);color:#fff}.smpi-publication-author-panel .smpi-overview-section .smpi-kicker{color:#9bd4ff}.smpi-publication-author-panel .smpi-overview-section h2{font-size:30px;margin:0 0 8px}.smpi-publication-author-panel .smpi-overview-section p:last-child{max-width:760px;font-size:15px;color:#e5edf7}.smpi-author-binding-layout{display:grid;grid-template-columns:minmax(320px,520px) 1fr;gap:22px;padding:24px 28px}.smpi-author-search-card{padding:18px;border:1px solid #d8dee8;border-radius:14px;background:#f8fafc}.smpi-user-picker{padding:0;border:0;background:transparent;margin:12px 0 0}.smpi-user-search{width:min(100%,420px);font-size:16px;padding:8px 12px}.smpi-empty-state{padding:22px;border:1px dashed #b9c2cf;border-radius:14px;background:#fbfcfe;color:#334155}.smpi-empty-state p{margin:8px 0 0}.smpi-advanced-map{display:none}.smpi-founder-profile-panel{border-top:1px solid #e5e7eb;padding:22px 28px}.smpi-founder-header h3{margin:0 0 6px}.smpi-profile-picker{padding:18px;border:1px solid #d8dee8;border-radius:14px;background:#f8fafc}.smpi-profile-search{width:min(100%,420px);font-size:16px;padding:8px 12px}.smpi-profile-results{display:grid;gap:6px;margin-top:10px;max-width:720px}.smpi-profile-result{text-align:left;justify-content:flex-start}.smpi-founder-selected{display:grid;gap:12px;margin-top:16px}.smpi-founder-profile-card{display:flex;gap:14px;align-items:center;padding:14px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}.smpi-founder-thumb img,.smpi-founder-thumb span{width:58px;height:58px;border-radius:999px;display:flex;align-items:center;justify-content:center;background:#eef2f7;object-fit:cover}.smpi-founder-info p{margin:6px 0 0}.smpi-system{margin-top:18px}.smpi-defs{margin:0}.smpi-def{display:grid;grid-template-columns:190px 1fr;gap:18px;align-items:center;padding:13px 0;border-top:1px solid #eef0f2}.smpi-def:first-child{border-top:0;padding-top:2px}.smpi-def-block{align-items:start}.smpi-defs dt{margin:0;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#646970}.smpi-defs dd{margin:0}.smpi-defs code{background:#f0f1f3;border-radius:6px;padding:4px 9px;font-size:13px;color:#1d2327;word-break:break-all}@media(max-width:782px){.smpi-def{grid-template-columns:1fr;gap:6px}}.smpi-feature-card{padding:22px;border:1px solid #d8dee8;border-radius:18px;background:#fff;margin:18px 0;box-shadow:0 10px 28px rgba(15,23,42,.05)}.smpi-feature-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;border-bottom:1px solid #eef0f2;padding-bottom:16px;margin-bottom:16px}.smpi-feature-head h2{font-size:22px;margin:0}.smpi-feature-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px}.smpi-feature-grid section,.smpi-feature-controls{border:1px solid #eef0f2;border-radius:14px;background:#f8fafc;padding:14px}.smpi-feature-report,.smpi-feature-activity{grid-column:1/-1;overflow-x:auto}.smpi-feature-report table{min-width:720px;max-width:100%}.smpi-feature-report .widefat{width:100%}.smpi-feature-grid h3,.smpi-control-group h3{margin:0 0 9px;font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:#475569}.smpi-choice-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:10px}.smpi-control-group:has(input[data-key="muckrack_verified_style"]) .smpi-choice-grid,.smpi-control-group:has(input[data-key="muckrack_icon_style"]) .smpi-choice-grid,.smpi-control-group:has(input[data-key="publication_muckrack_style"]) .smpi-choice-grid{grid-template-columns:1fr}.smpi-control-group:has(input[data-key="muckrack_verified_style"]) .smpi-choice-preview,.smpi-control-group:has(input[data-key="muckrack_icon_style"]) .smpi-choice-preview,.smpi-control-group:has(input[data-key="publication_muckrack_style"]) .smpi-choice-preview{max-width:620px}.smpi-control-group:has(input[data-key="muckrack_icon_style"]) .smpi-choice-preview{display:flex;align-items:center;gap:10px}.smpi-control-group:has(input[data-key="muckrack_icon_style"]) .smpi-choice-preview:before{content:"Jane Reporter";font-weight:700;color:#1f2937}.smpi-control-group:has(input[data-key="muckrack_icon_style"]) .smpi-choice-preview:after{content:"Verified by MuckRack editorial team";font-size:12px;color:#64748b}.smpi-choice-list{display:grid;grid-template-columns:1fr;gap:10px}.smpi-choice-card{display:flex;gap:12px;align-items:flex-start;position:relative;padding:14px 16px;border:1px solid #d8dee8;border-radius:14px;background:#fff;cursor:pointer;box-shadow:inset 0 0 0 1px transparent}.smpi-choice-card input{margin-top:3px}.smpi-choice-card strong{display:block;color:#1f2937}.smpi-choice-card small{display:block;margin-top:3px;color:#64748b;line-height:1.35}.smpi-choice-body{min-width:0;flex:1}.smpi-selected-pill{margin-left:auto;align-self:flex-start;background:#2271b1;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}.smpi-choice-card.is-selected,.smpi-choice-card:has(input:checked){border-color:#2271b1;background:#eef6fb;box-shadow:inset 0 0 0 1px #2271b1}.smpi-choice-preview{display:block;margin-top:10px;padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#fff}.smpi-preview-stack,.smpi-publication-preview-list{display:grid;gap:12px}.smpi-mode-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}.smpi-mode-grid>div,.smpi-shortcode-row{padding:12px;border:1px solid #d8dee8;border-radius:12px;background:#fff}.smpi-shortcode-list{display:grid;gap:10px}.smpi-shortcode-row{display:grid;grid-template-columns:minmax(180px,240px) minmax(260px,1fr);gap:8px 14px;align-items:start}.smpi-shortcode-row code{white-space:normal;word-break:break-word}.smpi-shortcode-row small{grid-column:2;color:#64748b}.smpi-shortcode-reference{margin-bottom:14px}.smpi-preview-demo,.smpi-publication-preview-item{padding:12px;border:1px solid #d8dee8;border-radius:12px;background:#fff}.smpi-tooltip-demo{display:flex;align-items:center;gap:6px;flex-wrap:wrap}.smpi-tooltip-bubble{background:#111827;color:#fff;border-radius:8px;padding:6px 8px;font-size:12px;white-space:nowrap}.smpi-author-inline-demo{display:inline-block}.smpi-author-block-demo{display:inline-flex;align-items:center;gap:.25em;border-left:2px solid var(--smpi-muckrack-color,#2d5277);background:#f5f8fb;color:#64748b;padding:6px 8px;font-size:11px;line-height:1.25}.smpi-author-block-demo strong{color:var(--smpi-muckrack-color,#2d5277)}.smpi-publication-preview-block{display:block;border-left:3px solid var(--smpi-muckrack-color,#2d5277);background:#f5f8fb;padding:8px 10px;font-size:12px;line-height:1.3;max-width:520px}.smpi-publication-preview-mini_block{display:block;border-left:2px solid var(--smpi-muckrack-color,#2d5277);background:#f6f8fb;padding:6px 9px;font-size:11px;line-height:1.25;max-width:520px;color:#475569}.smpi-publication-preview-compact{display:inline-flex;align-items:center;gap:5px;border:1px solid var(--smpi-muckrack-color,#2d5277);border-radius:999px;padding:4px 9px;background:#fff;font-size:12px;line-height:1.25}.smpi-publication-preview-minimalist{display:inline;color:#334155;font-size:12px;line-height:1.3}.smpi-publication-preview-brand{color:var(--smpi-muckrack-color,#2d5277);font-weight:700}.smpi-switch{display:inline-flex;gap:10px;align-items:center}.smpi-switch input{position:absolute;opacity:0}.smpi-switch span{width:42px;height:24px;border-radius:999px;background:#cbd5e1;position:relative;display:inline-block}.smpi-switch span:before{content:"";width:18px;height:18px;border-radius:999px;background:#fff;position:absolute;left:3px;top:3px;transition:.18s}.smpi-switch input:checked+span{background:#2271b1}.smpi-switch input:checked+span:before{transform:translateX(18px)}.smpi-control-row{margin:10px 0}.smpi-color-control{align-items:center;display:flex;gap:10px;flex-wrap:wrap}.smpi-color-control input[type=color]{height:38px;width:54px}.smpi-color-control code{background:#eef0f3;border-radius:4px;min-width:76px;padding:5px 8px;text-align:center}.smpi-color-control .hpc-button{padding:7px 10px}.smpi-color-swatch{display:inline-block;width:32px;height:32px;border-radius:8px;border:1px solid #cbd5e1;vertical-align:middle}.smpi-icon-preview{display:flex;gap:10px;margin-top:8px}.smpi-muckrack-preview-circle,.smpi-muckrack-preview-outline,.smpi-muckrack-preview-check{display:inline-flex;align-items:center;justify-content:center;--smpi-muckrack-color:#2d5277;color:var(--smpi-muckrack-color,#2d5277);line-height:1}.smpi-muckrack-preview-circle svg,.smpi-muckrack-preview-outline svg,.smpi-muckrack-preview-check svg{display:block;width:1em;height:1em}.smpi-ico{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;min-width:20px;border-radius:999px;font-size:12px;font-weight:700;line-height:1;margin-right:8px;vertical-align:middle}.smpi-ico--ok{background:#e6f4ea;color:#137333}.smpi-ico--bad{background:#fce8e6;color:#c5221f}.smpi-ico--warn{background:#fef7e0;color:#9a6700}.smpi-status-line{display:flex;align-items:center;margin:8px 0}.smpi-status-line span,.smpi-status-row span{color:#1f2937}.smpi-status-rows{display:grid;gap:10px;margin:14px 0}.smpi-status-row{display:flex;align-items:center;font-weight:600}.smpi-pill{display:inline-flex;align-items:center;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.smpi-pill--saved{background:#e6f4ea;color:#137333}.smpi-author-binding-layout{display:block;padding:24px 28px}.smpi-author-search-card{padding:18px;border:1px solid #d8dee8;border-radius:14px;background:#f8fafc;max-width:760px}.smpi-user-picker.is-locked .smpi-edit-view{display:none}.smpi-user-picker:not(.is-locked) .smpi-locked-view{display:none}.smpi-locked-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 16px;border:1px solid #cfe3d6;background:#f3faf5;border-radius:12px}.smpi-locked-label{font-weight:700;color:#0f5132}.smpi-locked-bar .smpi-change-user{margin-left:auto}.smpi-edit-actions{display:flex;align-items:center;gap:14px;margin-top:12px}.smpi-user-result{display:block;width:100%;text-align:left;padding:8px 12px;height:auto;line-height:1.5}.smpi-save-state.is-saved{color:#137333}.smpi-dashboard a[target="_blank"]::after{content:"\2197";font-size:.82em;margin-left:3px;line-height:1;opacity:.6;font-weight:700;text-decoration:none}.smpi-number-control{display:flex;align-items:center;gap:8px}.smpi-number-control input{width:90px}.smpi-context-override-list{display:grid;gap:10px}.smpi-context-override-row{display:grid;grid-template-columns:minmax(220px,1fr) minmax(180px,220px) minmax(160px,200px);gap:12px;align-items:center;padding:12px;border:1px solid #d8dee8;border-radius:12px;background:#fff}.smpi-context-override-row strong,.smpi-context-override-row small{display:block}.smpi-context-override-row small{color:#64748b;margin-top:3px;line-height:1.35}.smpi-context-override-row input[type=text]{width:100%;max-width:150px}.smpi-context-override-row input[type=color]{width:54px}.smpi-context-override-row input[type=number]{width:80px}.smpi-control-group:has(input[data-key="table_of_contents_style"]) .smpi-choice-grid,.smpi-control-group:has(input[data-key="inline_photo_treatment"]) .smpi-choice-grid,.smpi-control-group:has(input[data-key="featured_image_caption_template"]) .smpi-choice-grid,.smpi-control-group:has(input[data-key="post_summary_style"]) .smpi-choice-grid,.smpi-control-group:has(input[data-key="post_faqs_style"]) .smpi-choice-grid{grid-template-columns:1fr}.smpi-design-preview{display:block;max-width:640px;color:#334155}.smpi-design-preview ol,.smpi-design-preview ul{margin:8px 0 0;padding-left:20px}.smpi-design-preview a{color:#2563eb;text-decoration:none}.smpi-design-photo{margin:0}.smpi-photo-block{display:block;width:100%;height:92px;background:linear-gradient(135deg,#d8dee8,#f8fafc);border-radius:10px}.smpi-design-photo figcaption{margin-top:9px;font-size:12px;line-height:1.45;color:#64748b}.smpi-design-fig1 .smpi-photo-block{box-shadow:0 14px 28px -22px #111;border-radius:14px}.smpi-design-fig1 figcaption{padding-left:12px;border-left:3px solid #d63428;font-family:Georgia,serif;font-style:italic;color:#1f2937}.smpi-design-fig2{border-radius:14px;overflow:hidden;box-shadow:0 16px 34px -28px #111}.smpi-design-fig2 .smpi-photo-block{border-radius:0}.smpi-design-fig2 figcaption{background:#fafafa;border-top:3px solid #d63428;padding:12px}.smpi-design-fig4{position:relative;border-radius:14px;overflow:hidden}.smpi-design-fig4 .smpi-photo-block{height:110px;border-radius:0}.smpi-design-fig4 figcaption{position:absolute;left:0;right:0;bottom:0;color:#fff;background:linear-gradient(transparent,rgba(0,0,0,.78));padding:38px 14px 12px}.smpi-design-fig5{border:1px solid #e5e7eb;border-radius:14px;padding:10px;box-shadow:0 14px 30px -24px #111}.smpi-design-summary,.smpi-design-faq{font-size:13px;line-height:1.45}.smpi-design-summary h2,.smpi-design-faq h2{margin:0 0 8px;font-size:14px}.smpi-sum00{background:#f5f6f7;padding:16px}.smpi-sum01{border:1px solid #e5e7eb;border-left:4px solid #2563eb;border-radius:12px;padding:16px}.smpi-sum02{border-top:2px solid #0a0a0a;border-bottom:1px solid #e5e7eb;padding:14px 0}.smpi-sum03{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.smpi-sum03 h2{background:#0a0a0a;color:#fff;padding:10px 14px}.smpi-sum03 ul{padding:10px 24px}.smpi-sum04{background:#eff4ff;border-radius:14px;padding:16px}.smpi-faq02>div,.smpi-faq04>div{border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fff}.smpi-faq04>div{background:#f8fafc}.smpi-faq03>div{display:grid;grid-template-columns:auto 1fr;gap:12px}.smpi-faq03>div:before{content:"01";font-size:22px;font-weight:800;color:#c7d6ff}.smpi-plugin-registry .button{margin:0 4px 6px 0}.smpi-design-toc{color:var(--smpi-toc-text-color,#1f2937);font-size:var(--smpi-toc-font-size,15px);font-style:var(--smpi-toc-font-style,normal)}.smpi-design-toc a,.smpi-design-toc .smpi-toc-label{color:var(--smpi-toc-accent,#2563eb)}.smpi-design-toc.smpi-toc01{border-left-color:var(--smpi-toc-accent,#2563eb)}.smpi-design-toc.smpi-toc02 li:before,.smpi-design-toc.smpi-toc03 a:before{color:var(--smpi-toc-accent,#2563eb)}.smpi-design-photo figcaption{color:var(--smpi-inline-photo-caption-color,#272727);font-size:var(--smpi-inline-photo-caption-size,16px);font-style:var(--smpi-inline-photo-caption-style,italic)}.smpi-design-fig1 figcaption{border-left-color:var(--smpi-inline-photo-accent,#d63428)}.smpi-design-fig2 figcaption{border-top-color:var(--smpi-inline-photo-accent,#d63428)}.smpi-design-fig5{border-color:var(--smpi-inline-photo-accent,#d63428)}.smpi-design-faq{color:var(--smpi-faq-text-color,#1f2937);font-size:var(--smpi-faq-font-size,16px);font-style:var(--smpi-faq-font-style,normal)}.smpi-design-faq h2,.smpi-design-faq strong{color:var(--smpi-faq-accent,#2563eb)}.smpi-design-faq.smpi-faq02>div,.smpi-design-faq.smpi-faq04>div{border-left:3px solid var(--smpi-faq-accent,#2563eb)}.smpi-design-faq.smpi-faq03>div:before{color:var(--smpi-faq-accent,#2563eb)}.smpi-page-detail-wrap{margin-top:14px}.smpi-page-detail{border:1px solid #d8dee8;border-radius:14px;background:#f8fafc;padding:16px;margin-top:12px}.smpi-page-detail-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;border-bottom:1px solid #e5e7eb;padding-bottom:12px;margin-bottom:12px}.smpi-page-detail h3{margin:0 0 4px;font-size:16px}.smpi-page-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px 16px;margin:0}.smpi-page-meta div{padding:10px;border:1px solid #e5e7eb;border-radius:10px;background:#fff}.smpi-page-meta dt{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin:0 0 4px}.smpi-page-meta dd{margin:0;word-break:break-word}.smpi-page-meta-wide{grid-column:1/-1}.smpi-page-slug-row dd{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.smpi-page-slug-input{max-width:360px}.smpi-page-actions{margin:12px 0 0}.smpi-page-detail-missing{border-color:#f0c36d;background:#fff8e5}</style>
        <style id="smpi-author-badge-spacing-admin-css">.smpi-context-overrides .smpi-context-override-row{grid-template-columns:minmax(220px,1fr) minmax(180px,220px) repeat(3,minmax(118px,160px));}.smpi-context-overrides .smpi-context-override-row label{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}.smpi-context-overrides .smpi-context-override-row input[type=number]{width:82px;}@media(max-width:1200px){.smpi-context-overrides .smpi-context-override-row{grid-template-columns:1fr 1fr;}.smpi-context-overrides .smpi-context-override-row>div{grid-column:1/-1;}}@media(max-width:782px){.smpi-context-overrides .smpi-context-override-row{grid-template-columns:1fr;}}</style>
    <?php }
}
