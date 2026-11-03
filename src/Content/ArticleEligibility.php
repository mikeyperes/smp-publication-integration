<?php
namespace smp_publication_integration\Content;
use Hexa\PluginCore\SchemaTools\{SchemaSources,SchemaNodes};
/** Site identity supplies defaults; content owners supply authoritative semantic declarations. */
final class ArticleEligibility {
    public static function allows(\WP_Post $post): bool {
        if(!in_array($post->post_type,PublicationContentTypes::active_article_post_types(),true))return false;
        $type=SchemaSources::content_type((int)$post->ID);
        if($type!=='')return in_array($type,SchemaNodes::ARTICLE_TYPES,true);
        if($post->post_type!=='post')return true;
        $identity=(string)get_option('hws_site_type','');
        if(in_array($identity,['news_outlet','blog'],true))return true;
        // An explicit editorial classification is sufficient on any site type.
        $terms=wp_get_object_terms($post->ID,ArticleTypes::TAXONOMY,['fields'=>'ids']);
        return !is_wp_error($terms) && !empty($terms);
    }
}
