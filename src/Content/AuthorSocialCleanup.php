<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AuthorSocialCleanup {
    public function register(): void {
        add_action( 'wp_footer', [ $this, 'print_script' ], 30 );
    }

    public function print_script(): void {
        if ( ! Settings::bool( 'author_social_cleanup' ) || ( ! is_singular() && ! is_author() ) ) {
            return;
        }
        ?>
        <script id="smpi-author-social-cleanup">
        (function(){function e(a){var h=a.getAttribute('href');if(h===null)return true;h=h.trim();return h===''||h==='#'||h.toLowerCase().indexOf('javascript:')===0}function c(){document.querySelectorAll('a.elementor-social-icon,.elementor-social-icons-wrapper a,a[class*="social-icon"]').forEach(function(a){if(e(a)){var w=a.closest('.elementor-social-icon,.elementor-grid-item,li,.elementor-icon-list-item')||a;w.setAttribute('data-smpi-hidden-empty-social','1');w.style.display='none'}});document.querySelectorAll('.elementor-social-icons-wrapper').forEach(function(w){var v=Array.prototype.some.call(w.querySelectorAll('a'),function(a){return a.offsetParent!==null&&!e(a)});if(!v){var g=w.closest('.elementor-widget-social-icons');(g||w).style.display='none'}})}if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',c);else c();})();
        </script>
        <?php
    }
}