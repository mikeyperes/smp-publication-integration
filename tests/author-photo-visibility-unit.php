<?php
namespace smp_publication_integration\Authorship {
    final class AuthorFieldResolver {
        public function explicit_image_url(int $id): string { return $id === 1 ? 'https://example.test/real.jpg' : ''; }
    }
}
namespace smp_publication_integration\Support {
    final class Settings { public static bool $enabled = true; public static function bool($key): bool { return self::$enabled; } }
    final class RuntimeContext { public static function is_public_dom_context(): bool { return true; } }
}
namespace {
    define('ABSPATH', __DIR__);
    define('DAY_IN_SECONDS', 86400);
    class WP_User { public function __construct(public int $ID) {} }
    class WP_Post { public int $post_author = 2; }
    function get_post() { return new WP_Post(); }
    function get_user_by($field, $value) { return new WP_User($field === 'slug' ? ($value === 'real' ? 1 : 2) : (int)$value); }
    function get_avatar_data($id, $args): array {
        return ['found_avatar' => $id !== 2, 'url' => $id === 2 ? 'https://example.test/default-avatar.png' : 'https://secure.gravatar.com/avatar/' . str_repeat((string)$id,32) . '?d=mm'];
    }
    function apply_filters($hook, $value, ...$args) { return $value; }
    function get_transient($key) { return false; }
    function set_transient(...$args) {}
    function wp_remote_head($url, $args) { return ['code'=>strpos($url,str_repeat('3',32)) !== false ? 404 : 200]; }
    function is_wp_error($response) { return false; }
    function wp_remote_retrieve_response_code($response) { return $response['code']; }
    require __DIR__.'/../src/Authorship/AuthorPhoto.php';
    require __DIR__.'/../src/Content/AuthorPhotoVisibility.php';
    require __DIR__.'/../src/Content/AuthorTemplateFunctions.php';
    $count=0;
    function check($ok, $message) { global $count; if (!$ok) { throw new \RuntimeException($message); } ++$count; }
    check(smp_author_has_photo(1), 'explicit real photo');
    check(!smp_author_has_photo(2), 'default person is not photo');
    check(!smp_author_has_photo(3), 'Gravatar default with found_avatar=true is not photo');
    check(smp_author_has_photo(4), 'real Gravatar photo retained');
    $renderer=new \smp_publication_integration\Content\AuthorPhotoVisibility();
    foreach (['single-top','single-footer','elementor-loop','homepage','category'] as $context) {
        foreach ([1,2] as $id) {
            $html='<div class="author '.$context.'"><div class="portrait-column"><div class="elementor-widget-image"><a href="/author/'.($id===1?'real':'missing').'/"><img class="avatar" data-smpi-author-id="'.$id.'" src="photo.jpg"></a></div></div><div class="bio">Author name</div></div>';
            $out=$renderer->render_html($html);
            check((strpos($out,'<img')!==false) === ($id===1), "$context photo visibility $id");
            check(strpos($out,'Author name')!==false, "$context name preserved $id");
            check($id===1 || strpos($out,'portrait-column')===false, "$context empty column removed $id");
            \smp_publication_integration\Support\Settings::$enabled=false;
            check($renderer->render_html($html)===$html, "$context off restores exact original $id");
            \smp_publication_integration\Support\Settings::$enabled=true;
        }
    }
    $mixed='<div><a href="/author/real/"><img class="elementor-avatar" src="real.jpg">Real</a><a href="/author/missing/"><span class="elementor-icon-list-icon"><img class="elementor-avatar" src="default.jpg"></span>Missing</a><img class="story" src="story.jpg"></div>';
    $out=$renderer->render_html($mixed);
    check(strpos($out,'real.jpg')!==false && strpos($out,'default.jpg')===false && strpos($out,'story.jpg')!==false, 'mixed author listing preserves photos and article image');
    check(strpos($out,'elementor-icon-list-icon')===false, 'icon spacing wrapper removed');
    $full='<!doctype html><html><head><script>window.example = "<sample>";</script></head><body>'.$mixed.'</body></html>';
    $out=$renderer->render_html($full);
    check(strpos($out,'<!DOCTYPE html>')!==false && strpos($out,'window.example = "<sample>";')!==false && strpos($out,'default.jpg')===false,'full page retains doctype and script');
    echo "PASS: $count author-photo assertions\n";
}
