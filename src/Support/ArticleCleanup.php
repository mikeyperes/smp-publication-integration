<?php
namespace smp_publication_integration\Support;

use Hexa\PluginCore\ContentCleanup\ArticleMediaCleanupAjaxController;
use Hexa\PluginCore\ContentCleanup\ArticleMediaCleanupConfig;
use Hexa\PluginCore\ContentCleanup\ArticleMediaCleanupRenderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ArticleCleanup {
    public static function config(): ArticleMediaCleanupConfig {
        return new ArticleMediaCleanupConfig(
            [
                'root_id'             => 'smpi-article-media-cleanup',
                'title'               => 'Article & Media Cleanup',
                'description'         => 'Scan regular posts, review associated media, and delete posts in live AJAX batches. Associated media deletion must be explicitly enabled before a delete action runs.',
                'capability'          => 'manage_options',
                'nonce_action'        => 'smpi_admin',
                'nonce_field'         => 'nonce',
                'scan_action'         => 'smpi_article_media_cleanup_scan',
                'delete_action'       => 'smpi_article_media_cleanup_delete',
                'batch_delete_action' => 'smpi_article_media_cleanup_batch_delete',
                'auto_scan'           => true,
                'post_types'          => [ 'post' => 'Posts' ],
                'statuses'            => [
                    'publish' => 'Published',
                    'draft'   => 'Draft',
                    'private' => 'Private',
                    'pending' => 'Pending',
                    'any'     => 'Any active status',
                ],
                'default_post_type'   => 'post',
                'default_status'      => 'any',
                'default_keep_recent' => 10,
                'default_limit'       => 50,
                'max_limit'           => 5000,
                'default_batch_size'  => 1,
                'max_batch_size'      => 100,
                'empty_message'       => 'No matching regular posts were found for the selected filters.',
            ]
        );
    }

    public static function register_ajax(): void {
        static $registered = false;

        if ( $registered ) {
            return;
        }

        ( new ArticleMediaCleanupAjaxController( self::config() ) )->register();

        $registered = true;
    }

    public static function render(): void {
        self::register_ajax();

        echo '<div class="smpi-hero"><p class="smpi-kicker">Cleanup</p><h2>Article &amp; Media Cleanup</h2><p>Use the full Hexa Core cleanup view when you need visible scan results, associated media details, and per-batch progress instead of a Quick Start summary.</p></div>';
        echo '<div class="smpi-alert smpi-alert-warning"><strong>Destructive action.</strong><p>Deletes are permanent. When a media cleanup toggle is enabled, detected featured, inline, and gallery attachments are deleted with each deleted post.</p></div>';

        ( new ArticleMediaCleanupRenderer( self::config() ) )->render();
    }
}
