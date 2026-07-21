<?php

namespace smp_publication_integration\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SettingsMigrations {
    private const HEADING_PRESET_MIGRATION = 'smpi_migration_heading_quick_start_0_6_191';
    private const BREADCRUMB_POST_TYPE_MIGRATION = 'smpi_migration_breadcrumb_post_types_0_6_218';

    public function register(): void {
        add_action( 'init', [ $this, 'repair_defective_heading_quick_start_preset' ], 5 );
        add_action( 'init', [ $this, 'migrate_breadcrumb_single_post_setting' ], 6 );
    }

    public function repair_defective_heading_quick_start_preset(): void {
        if ( get_option( self::HEADING_PRESET_MIGRATION, false ) ) {
            return;
        }

        $raw = get_option( SettingsRepository::OPTION, [] );
        if ( is_array( $raw ) && self::matches_defective_heading_quick_start_preset( $raw ) ) {
            SettingsRepository::update( [ 'article_heading_styles_enabled' => true ] );
        }

        update_option( self::HEADING_PRESET_MIGRATION, '0.6.191', false );
    }

    public function migrate_breadcrumb_single_post_setting(): void {
        if ( get_option( self::BREADCRUMB_POST_TYPE_MIGRATION, false ) ) {
            return;
        }

        $settings = get_option( SettingsRepository::OPTION, [] );
        if ( is_array( $settings ) ) {
            $hidden_post_types = isset( $settings['breadcrumbs_disabled_post_types'] ) && is_array( $settings['breadcrumbs_disabled_post_types'] )
                ? $settings['breadcrumbs_disabled_post_types']
                : [];

            if ( ! empty( $settings['breadcrumbs_hide_single_posts'] ) ) {
                $hidden_post_types[] = 'post';
            }

            $settings['breadcrumbs_disabled_post_types'] = array_values(
                array_unique(
                    array_filter( array_map( 'sanitize_key', $hidden_post_types ) )
                )
            );
            unset( $settings['breadcrumbs_hide_single_posts'] );
            update_option( SettingsRepository::OPTION, $settings, false );
        }

        update_option( self::BREADCRUMB_POST_TYPE_MIGRATION, '0.6.218', false );
    }

    public static function matches_defective_heading_quick_start_preset( array $settings ): bool {
        if ( ! array_key_exists( 'article_heading_styles_enabled', $settings ) || (bool) $settings['article_heading_styles_enabled'] ) {
            return false;
        }

        $signature = [
            'article_heading_style'         => 'h2-tick',
            'article_heading_accent_color'  => '#000033',
            'article_heading_h2_font_size'  => 23,
            'article_heading_h3_font_size'  => 20,
            'table_of_contents_enabled'     => true,
            'table_of_contents_style'       => 'toc03',
            'inline_photo_treatments_enabled' => true,
            'inline_photo_treatment'        => 'fig2',
        ];

        foreach ( $signature as $key => $expected ) {
            if ( ! array_key_exists( $key, $settings ) || $settings[ $key ] != $expected ) {
                return false;
            }
        }

        return true;
    }
}
