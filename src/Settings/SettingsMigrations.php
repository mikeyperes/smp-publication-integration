<?php

namespace smp_publication_integration\Settings;

use Hexa\PluginCore\BrandColors\TemplateColorResolver;
use Hexa\PluginCore\Typography\TemplateTypography;
use smp_publication_integration\Design\TemplateDesignRegistry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SettingsMigrations {
    private const HEADING_PRESET_MIGRATION = 'smpi_migration_heading_quick_start_0_6_191';
    private const BREADCRUMB_POST_TYPE_MIGRATION = 'smpi_migration_breadcrumb_post_types_0_6_218';
    private const TEMPLATE_DESIGN_MODE_MIGRATION = 'smpi_migration_template_design_modes_0_6_243';

    public function register(): void {
        add_action( 'init', [ $this, 'repair_defective_heading_quick_start_preset' ], 5 );
        add_action( 'init', [ $this, 'migrate_breadcrumb_single_post_setting' ], 6 );
        add_action( 'init', [ $this, 'migrate_template_design_modes' ], 7 );
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

    public function migrate_template_design_modes(): void {
        if ( get_option( self::TEMPLATE_DESIGN_MODE_MIGRATION, false ) ) {
            return;
        }

        $settings = get_option( SettingsRepository::OPTION, [] );
        $settings = is_array( $settings ) ? $settings : [];
        $existing_installation = [] !== $settings;
        $legacy_colors = SettingsRepository::color_defaults();

        foreach ( TemplateDesignRegistry::definitions() as $definition ) {
            $source_key = (string) $definition['source_key'];
            $custom_key = (string) $definition['custom_key'];
            if ( $existing_installation ) {
                if ( ! array_key_exists( $custom_key, $settings ) ) {
                    $settings[ $custom_key ] = (string) ( $legacy_colors[ $custom_key ] ?? $definition['fallback'] );
                }
                $settings[ $source_key ] = TemplateColorResolver::CUSTOM;
            } elseif ( array_key_exists( $source_key, $settings ) ) {
                $settings[ $source_key ] = TemplateColorResolver::normalize_source( (string) $settings[ $source_key ] );
            } else {
                $settings[ $source_key ] = TemplateColorResolver::TEMPLATE_DEFAULT;
            }
        }

        foreach ( array_keys( SettingsRepository::typography_preservation_surfaces() ) as $prefix ) {
            $mode_key = TemplateTypography::setting_key( $prefix );
            if ( $existing_installation ) {
                $settings[ $mode_key ] = TemplateTypography::CUSTOM;
            } elseif ( array_key_exists( $mode_key, $settings ) ) {
                $settings[ $mode_key ] = TemplateTypography::normalize_mode( (string) $settings[ $mode_key ] );
            } else {
                $settings[ $mode_key ] = TemplateTypography::TEMPLATE_DEFAULT;
            }
        }

        // White was the old forced breadcrumb default, which suppressed the
        // native tinted and gradient backgrounds of several templates.
        if ( isset( $settings['breadcrumbs_background_color'] ) && '#ffffff' === strtolower( (string) $settings['breadcrumbs_background_color'] ) ) {
            $settings['breadcrumbs_background_color'] = '';
        }

        if ( [] !== $settings ) {
            update_option( SettingsRepository::OPTION, $settings, false );
        }
        update_option( self::TEMPLATE_DESIGN_MODE_MIGRATION, '0.6.243', false );
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
