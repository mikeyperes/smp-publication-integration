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
use smp_publication_integration\Admin\FounderProfiles\FounderProfilePresenter;
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

/** Founders tab, second section: Verified Profile records linked as founders (was the Founder Profiles tab). */
trait FounderProfilesSection {

    private function founder_profiles(): void {
        $readiness = Dependencies::verified_profiles_readiness();
        $ready = ! empty( $readiness["plugin_active"] ) && ! empty( $readiness["profile_cpt"] ) && ! empty( $readiness["profile_acf"] );
        $ids = $ready ? $this->founder_profile_ids() : [];
        ?>
        <div class="smpi-founder-page">
            <header class="smpi-founder-page-head">
                <div>
                    <p class="smpi-kicker">Publication people</p>
                    <h2>Founder Profiles</h2>
                    <p>Connect Verified Profile records to this publication. Search, add, and remove founders without leaving this screen.</p>
                </div>
                <div class="smpi-founder-count" aria-live="polite">
                    <strong data-smpi-founder-count><?php echo esc_html( (string) count( $ids ) ); ?></strong>
                    <span>Selected</span>
                </div>
            </header>
        <?php
        if ( empty( $readiness["plugin_active"] ) || empty( $readiness["profile_cpt"] ) || empty( $readiness["profile_acf"] ) ) {
            echo "<div class=\"smpi-founder-readiness smpi-founder-readiness--warning\"><div><strong>Verified Profiles setup required</strong><p>Founder selection unlocks once the plugin, Profile content type, and Profile ACF fields are active.</p><div class=\"smpi-status-rows\"><div class=\"smpi-status-row\">" . self::ico( ! empty( $readiness["plugin_active"] ), true ) . "<span>Verified Profiles plugin active</span></div><div class=\"smpi-status-row\">" . self::ico( ! empty( $readiness["profile_cpt"] ), true ) . "<span>Profile content type active</span></div><div class=\"smpi-status-row\">" . self::ico( ! empty( $readiness["profile_acf"] ), true ) . "<span>Profile ACF fields enabled</span></div></div></div>" . $this->verified_profiles_setup_actions_html( $readiness ) . "</div></div>";
            return;
        }

        echo "<div class=\"smpi-founder-readiness smpi-founder-readiness--ready\"><div><span class=\"smpi-founder-status-dot\" aria-hidden=\"true\"></span><strong>Verified Profiles connected</strong><span>Profile records are available for founder assignment.</span></div><a class=\"button\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" . esc_url( $readiness["settings_url"] ?? admin_url( "options-general.php?page=smp-verified-profiles" ) ) . "\">Manage Verified Profiles</a></div>";
        echo "<div class=\"smpi-profile-picker smpi-founder-workspace\"><section class=\"smpi-founder-search-section\"><div class=\"smpi-founder-section-copy\"><h3>Add a founder</h3><p>Search published Verified Profile records by name.</p></div><div class=\"smpi-smart-profile-search\">";
        ( new SmartSearchRenderer() )->render(
            [
                "id"          => "smpi-founder-profile-core-search",
                "label"       => "Search Verified Profiles",
                "placeholder" => "Search by profile name",
                "source"      => "smpi_profiles",
                "post_type"   => "profile",
                "limit"       => 20,
            ]
        );
        echo "</div><div class=\"smpi-founder-feedback\"><span class=\"spinner\"></span><span class=\"smpi-save-state\" aria-live=\"polite\"></span></div><div class=\"smpi-profile-results\" aria-live=\"polite\"></div></section>";
        echo "<section class=\"smpi-founder-selection-section\"><div class=\"smpi-founder-section-copy\"><h3>Selected founders</h3><p>Each selected record shows every populated profile field used by Verified Profiles and publication structured data.</p></div><div class=\"smpi-founder-selected\">";
        echo ( new FounderProfilePresenter() )->collection_html( $ids );
        echo "</div></section></div></div>";
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
        $rows = \Hexa\PluginCore\Fields\Field::available() ? \Hexa\PluginCore\Fields\Field::get( "smpi_founder_profiles", "option" ) : [];
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

        if ( empty( $ids ) && \Hexa\PluginCore\Fields\Field::available() ) {
            $legacy = \Hexa\PluginCore\Fields\Field::get( "smpi_founders", "option" );
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
}
