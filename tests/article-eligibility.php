<?php
namespace { define('ABSPATH','/'); class WP_Post { public function __construct(public int $ID, public string $post_type='post'){} }
$GLOBALS['type']='';$GLOBALS['site']='news_outlet';$GLOBALS['terms']=[];
function apply_filters($tag,$value,$id){return $GLOBALS['type'];} function get_option($key,$default=''){return $GLOBALS['site'];}
function wp_get_object_terms(...$args){return $GLOBALS['terms'];}function is_wp_error($v){return false;}
}
namespace smp_publication_integration\Content { class PublicationContentTypes {static function active_article_post_types(){return ['post','press-release'];}} class ArticleTypes {const TAXONOMY='article_type';} }
namespace {
require dirname(__DIR__).'/lib/hexa-wordpress-plugin-core/src/SchemaTools/SchemaNodes.php';
require dirname(__DIR__).'/lib/hexa-wordpress-plugin-core/src/SchemaTools/SchemaSources.php';
require dirname(__DIR__).'/src/Content/ArticleEligibility.php';
$cases=[['news_outlet','',[], 'post',true],['podcast','PodcastEpisode',[], 'post',false],['company_website','VideoObject',[], 'post',false],['podcast','Article',[], 'post',true],['company_website','',[], 'post',false],['company_website','',[1], 'post',true],['podcast','',[], 'press-release',true]];
foreach($cases as [$site,$type,$terms,$post_type,$expected]){$GLOBALS['site']=$site;$GLOBALS['type']=$type;$GLOBALS['terms']=$terms;
if(\smp_publication_integration\Content\ArticleEligibility::allows(new WP_Post(1,$post_type))!==$expected){fwrite(STDERR,'FAIL eligibility matrix');exit(1);}}
echo 'PASS: seven generic eligibility cases, including news, declared non-articles, editorial content and press releases.'.PHP_EOL;
}
