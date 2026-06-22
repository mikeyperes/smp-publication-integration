<?php
namespace smp_publication_integration\Admin;

use Hexa\PluginCore\WpAdminUiCleanup\CleanupRegistry;
use smp_publication_integration\Config;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class UiCleanup {
    private static ?CleanupRegistry $registry = null;

    public function register(): void {
        self::registry()->register();
    }

    public static function registry(): CleanupRegistry {
        if ( self::$registry instanceof CleanupRegistry ) {
            return self::$registry;
        }

        self::$registry = new CleanupRegistry(
            [
                "option_prefix" => "smpi_ui_cleanup_",
                "ajax_action"   => "smpi_ui_cleanup_toggle",
                "nonce_action"  => Ajax::NONCE,
                "nonce_field"   => "nonce",
                "capability"    => Config::$settings_page_capability,
                "root_id"       => "smpi-ui-cleanup",
                "sections"      => [
                    "wordpress" => [
                        "title"       => "WordPress User & Editor Screens",
                        "description" => "Admin cleanup behavior runs on profile.php, user-edit.php, post.php, and post-new.php target screens.",
                        "icon"        => "WP",
                    ],
                    "rankmath" => [
                        "title"       => "Rank Math SEO",
                        "description" => "Plugin-specific Rank Math panels and editor boxes.",
                        "icon"        => "SEO",
                    ],
                ],
                "options"       => [
                    "hide_rankmath_link_suggestions" => [
                        "key"         => "hide_rankmath_link_suggestions",
                        "label"       => "Post editor Link Suggestions",
                        "description" => "Hides the Rank Math Link Suggestions metabox and its Screen Options checkbox on post editor screens.",
                        "section"     => "rankmath",
                        "mode"        => "postbox_hide",
                        "default"     => false,
                        "admin_pages" => [ "post.php", "post-new.php" ],
                        "selectors"   => [
                            "#rank_math_metabox_link_suggestions",
                            "#rank_math_metabox_link_suggestions-hide",
                            "label[for=rank_math_metabox_link_suggestions-hide]",
                        ],
                    ],
                ],
            ]
        );

        return self::$registry;
    }
}
