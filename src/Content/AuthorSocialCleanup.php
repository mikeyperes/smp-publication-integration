<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AuthorSocialCleanup {
    public function register(): void {
        add_action( "wp_footer", [ $this, "print_script" ], 120 );
    }

    public function print_script(): void {
        if ( ! RuntimeContext::is_public_dom_context() ) {
            return;
        }
        $author_context = is_single() || is_author();
        $should_run = ( Settings::bool( "author_social_cleanup" ) && $author_context ) || Settings::bool( "publication_social_cleanup" );
        if ( ! $should_run ) {
            return;
        }
        $script = <<<'JS'
(function(){
var socialPattern=/(twitter|x\s*\/\s*twitter|twitter\s*\/\s*x|x-twitter|x\.com|linkedin|facebook|instagram|youtube|tiktok|threads|pinterest|soundcloud)/i;
function invalidLink(a){if(!a)return true;var h=a.getAttribute("href");if(h===null)return true;h=h.trim();return h===""||h==="#"||h.toLowerCase().indexOf("javascript:")===0;}
function visible(el){return !!(el&&el.offsetParent!==null&&getComputedStyle(el).display!=="none"&&getComputedStyle(el).visibility!=="hidden");}
function label(el){return ((el&&el.textContent)||"")+" "+((el&&el.getAttribute&&el.getAttribute("href"))||"")+" "+((el&&el.getAttribute&&el.getAttribute("class"))||"")+" "+((el&&el.getAttribute&&el.getAttribute("title"))||"")+" "+((el&&el.getAttribute&&el.getAttribute("aria-label"))||"");}
function socialLike(el){return socialPattern.test(label(el));}
function hide(el){if(!el||el.getAttribute("data-smpi-hidden-empty-social")==="1")return;el.setAttribute("data-smpi-hidden-empty-social","1");el.style.display="none";}
function cleanupLinks(root){root.querySelectorAll("a.elementor-social-icon,.elementor-social-icons-wrapper a,a[class*='social-icon'],.elementor-icon-list-item a,.elementor-widget-button a").forEach(function(a){if(invalidLink(a)&&(socialLike(a)||a.closest(".elementor-social-icons-wrapper"))){hide(a.closest(".elementor-grid-item,.elementor-icon-list-item,.elementor-widget-button,.elementor-social-icon")||a);}});}
function cleanupIconItems(root){root.querySelectorAll(".elementor-icon-list-item").forEach(function(item){var links=item.querySelectorAll("a");var hasValid=false;links.forEach(function(a){if(!invalidLink(a))hasValid=true;});if(socialLike(item)&&(!links.length||!hasValid)){hide(item);}});}
function collapse(root){root.querySelectorAll(".elementor-social-icons-wrapper").forEach(function(w){var valid=false;w.querySelectorAll("a").forEach(function(a){if(visible(a)&&!invalidLink(a))valid=true;});if(!valid)hide(w.closest(".elementor-widget-social-icons")||w);});root.querySelectorAll(".elementor-icon-list-items").forEach(function(w){var items=Array.prototype.slice.call(w.querySelectorAll(".elementor-icon-list-item"));if(items.length&&items.every(function(item){return !visible(item);})){hide(w.closest(".elementor-widget-icon-list")||w);}});}
function cleanup(root){root=root&&root.querySelectorAll?root:document;cleanupLinks(root);cleanupIconItems(root);collapse(root);}
function schedule(root){clearTimeout(schedule.t);schedule.t=setTimeout(function(){cleanup(root||document);},30);}
if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",function(){cleanup(document);});}else{cleanup(document);}
window.addEventListener("load",function(){cleanup(document);});
setTimeout(function(){cleanup(document);},400);
if("MutationObserver" in window){new MutationObserver(function(muts){for(var i=0;i<muts.length;i++){if(muts[i].addedNodes&&muts[i].addedNodes.length){schedule(document);break;}}}).observe(document.documentElement,{childList:true,subtree:true});}
})();
JS;
        echo "<script id=\"smpi-author-social-cleanup\">" . $script . "</script>";
    }
}
