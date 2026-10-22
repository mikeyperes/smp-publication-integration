<?php
namespace smp_publication_integration\Admin\Dashboard\Tabs;

use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\ActivityLog\ActivityLogConfig;
use Hexa\PluginCore\ActivityLog\ActivityLogRenderer;
use Hexa\PluginCore\PluginChecks\PluginInventoryRenderer;
use Hexa\PluginCore\SmartSearch\SmartSearchRenderer;
use Hexa\PluginCore\FieldStructures\FieldStructureRenderer;
use Hexa\PluginCore\FieldStructures\AcfFieldGroupRenderer;
use Hexa\PluginCore\ContentTypes\ContentTypeRenderer;
use Hexa\PluginCore\Taxonomies\TaxonomyRenderer;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;
use Hexa\PluginCore\SnippetRegistry\SnippetRegistry;
use Hexa\PluginCore\SnippetRegistry\SnippetsTableRenderer;
use Hexa\PluginCore\WpAdminTabs\HostTabsRenderer;
use Hexa\PluginCore\PluginUpdates\PluginUpdateStatus;
use Hexa\PluginCore\CorePackageUpdates\CorePackageStatus;
use Hexa\PluginCore\WpAdminTabs\TabDefinition;
use Hexa\PluginCore\WpAdminTabs\TabRegistry;
use Hexa\PluginCore\WpAdminComponents\ColorControl;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\TypographyControl;
use Hexa\PluginCore\WpAdminComponents\TemplateColorControl;
use Hexa\PluginCore\WpAdminComponents\ScopedCssOverride;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;
use Hexa\PluginCore\Typography\TypographyPreservation;
use Hexa\PluginCore\Typography\TemplateTypography;
use Hexa\PluginCore\SiteStructure\SiteStructureRenderer;
use Hexa\PluginCore\SchemaDetection\SchemaPageScanner;
use Hexa\PluginCore\SchemaDetection\SchemaScanRenderer;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Admin\QuickStartCleanupWorkflow;

use smp_publication_integration\Admin\Navigation\AdminNavigation;
use smp_publication_integration\Config;
use smp_publication_integration\Content\AcfFields;
use smp_publication_integration\Content\AuthorSocialIcons;
use smp_publication_integration\Content\AuthorArchiveLoading;
use smp_publication_integration\Content\Breadcrumbs;
use smp_publication_integration\Content\MuckRackVerification;
use smp_publication_integration\Content\MultiAuthors;
use smp_publication_integration\Content\PublicationContentTypes;
use smp_publication_integration\Content\Schema;
use smp_publication_integration\Design\TemplateBackground;
use smp_publication_integration\Design\TemplateDesignRegistry;
use smp_publication_integration\Support\Dependencies;
use smp_publication_integration\Support\ArticleCleanup;
use smp_publication_integration\Support\Fields;
use smp_publication_integration\Support\PageStructure;
use smp_publication_integration\Support\PluginInventory;
use smp_publication_integration\Support\PluginRegistry;
use smp_publication_integration\Support\QuickStartFeatures;
use smp_publication_integration\Support\Settings;
use smp_publication_integration\Support\SnippetDefinitions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Dashboard tab: plugin overview, publication mapping and core switches. */
trait OverviewTab {
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
            <?php $this->toggle( 'shadow_press_releases', 'Shadow all press releases from home/category/tag', $settings ); ?>
            <?php $this->toggle( 'author_social_cleanup', 'Hide empty author social icons', $settings ); ?>
            <?php $this->toggle( 'public_debug_enabled', 'Public safe debug endpoint', $settings ); ?>
            <tr><th>Post time display</th><td><select class="smpi-setting" data-key="post_time_mode"><option value="native" <?php selected( $settings['post_time_mode'], 'native' ); ?>>Native WordPress output</option><option value="relative_then_date" <?php selected( $settings['post_time_mode'], 'relative_then_date' ); ?>>5 min ago, then friendly date after 24 hours</option><option value="friendly_date" <?php selected( $settings['post_time_mode'], 'friendly_date' ); ?>>Always friendly date</option></select><span class="spinner"></span><span class="smpi-save-state"></span></td></tr>
        </tbody></table></div>
        <?php
        echo "<h2 style=\"margin:26px 0 10px\">Plugin &amp; Hexa WP Core</h2>";
        echo "<p class=\"smpi-muted\" style=\"margin:0 0 14px\">Version and update status for this plugin and the bundled Hexa WP Core. These two panels are provided by Hexa WP Core.</p>";
        ( new \Hexa\PluginCore\PluginUpdates\UpdaterPanelRenderer( \smp_publication_integration\hexa_plugin_core_updater_config() ) )->render();
        ( new \Hexa\PluginCore\CorePackageUpdates\CorePackagePanelRenderer( \smp_publication_integration\hexa_plugin_core_package_config() ) )->render();
    }


    private function publication_mapping_panel( array $settings ): void {
        $canonical = Fields::canonical_entity();
        if ( $canonical ) {
            echo '<div class="smpi-panel smpi-publication-map smpi-publication-author-panel">';
            echo '<div class="smpi-overview-section"><p class="smpi-kicker">Current Publication</p><h2>' . esc_html( (string) $canonical['name'] ) . '</h2><p>This publication identity is managed by HWS Base Tools and consumed here without a duplicate SMP selection.</p></div>';
            echo '<div class="smpi-author-binding-layout"><div class="smpi-author-search-card"><h3>Canonical source</h3><dl class="smpi-defs">';
            echo '<div class="smpi-def"><dt>Entity type</dt><dd><code>' . esc_html( (string) $canonical['entity_type'] ) . '</code></dd></div>';
            echo '<div class="smpi-def"><dt>Source</dt><dd><code>' . esc_html( (string) $canonical['source'] ) . '</code></dd></div>';
            echo '<div class="smpi-def"><dt>Object</dt><dd><code>' . esc_html( (string) $canonical['kind'] . ( ! empty( $canonical['post_type'] ) ? ':' . (string) $canonical['post_type'] : '' ) . ' #' . (string) $canonical['id'] ) . '</code></dd></div>';
            if ( ! empty( $canonical['attached_user_id'] ) ) {
                $author = (string) ( $canonical['attached_user_name'] ?? 'WordPress user #' . (int) $canonical['attached_user_id'] );
                $author_link = ! empty( $canonical['attached_user_edit_url'] ) ? '<a href="' . esc_url( (string) $canonical['attached_user_edit_url'] ) . '">' . esc_html( $author ) . '</a>' : esc_html( $author );
                echo '<div class="smpi-def"><dt>Attached author</dt><dd>' . $author_link . ' <code>#' . esc_html( (string) $canonical['attached_user_id'] ) . '</code></dd></div>';
            } else {
                echo '<div class="smpi-def"><dt>Attached author</dt><dd>Not set. SMP does not require one.</dd></div>';
            }
            echo '</dl><p class="smpi-action-row"><a class="button button-primary" href="' . esc_url( admin_url( 'options-general.php?page=hws-core-tools&tab=website-types' ) ) . '">Manage in HWS Base Tools</a>';
            if ( ! empty( $canonical['edit_url'] ) ) {
                echo ' <a class="button" href="' . esc_url( (string) $canonical['edit_url'] ) . '">Edit source record</a>';
            }
            echo '</p></div></div>';
            echo '</div>';
            return;
        }

        $user_id = isset( $settings["system_publication_user_id"] ) ? absint( $settings["system_publication_user_id"] ) : 0;

        $locked = $user_id > 0;
        echo "<div class=\"smpi-panel smpi-publication-map smpi-publication-author-panel\">";
        echo "<div class=\"smpi-overview-section\"><p class=\"smpi-kicker\">Current Publication</p><h2>Main Publication Profile</h2><p>Choose the WordPress profile that represents this publication on the front end. Search by name, username, or email &mdash; your choice locks in and saves automatically.</p></div>";
        echo "<div class=\"smpi-author-binding-layout\">";
        echo "<div class=\"smpi-user-picker" . ( $locked ? " is-locked" : "" ) . "\" data-selected-user=\"" . esc_attr( (string) $user_id ) . "\">";
        echo "<input type=\"hidden\" class=\"smpi-setting smpi-publication-user-setting\" data-key=\"system_publication_user_id\" value=\"" . esc_attr( (string) $user_id ) . "\">";
        echo "<div class=\"smpi-locked-view\"><div class=\"smpi-locked-bar\">" . self::ico( true ) . "<span class=\"smpi-locked-label\">Main publication profile</span><span class=\"smpi-pill smpi-pill--saved\">Saved</span><button type=\"button\" class=\"button smpi-change-user\">Change profile</button></div>";
        echo "<div class=\"smpi-current-user-summary\">" . $this->publication_user_card_html( $user_id ) . "</div></div>";
        echo "<div class=\"smpi-edit-view\"><div class=\"smpi-author-search-card\">";
        echo "<label for=\"smpi-publication-user-search\"><strong>Search for a publication profile</strong></label><p class=\"smpi-muted\">Search by publication name, username, or email, then click a result to lock it in. It saves automatically.</p>";
        echo "<input id=\"smpi-publication-user-search\" type=\"search\" class=\"regular-text smpi-user-search\" placeholder=\"Name, username, or email\" autocomplete=\"off\">";
        echo "<div class=\"smpi-user-results\" aria-live=\"polite\"></div>";
        echo "<div class=\"smpi-edit-actions\">" . ( $locked ? "<button type=\"button\" class=\"button smpi-cancel-user\">Cancel</button>" : "" ) . "<button type=\"button\" class=\"button-link smpi-clear-user\">Clear selection</button><span class=\"spinner\"></span><span class=\"smpi-save-state\"></span></div>";
        echo "</div></div>";
        echo "</div></div>";
        echo "</div>";
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
        if ( ! \Hexa\PluginCore\Fields\Field::available() ) {
            return "";
        }
        return \Hexa\PluginCore\Fields\Field::get( $field, "user_" . $user_id );
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
}
