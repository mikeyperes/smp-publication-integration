<?php
namespace smp_publication_integration\Content;

use smp_publication_integration\Authorship\AuthorFieldResolver;
use smp_publication_integration\Support\Fields;
use smp_publication_integration\Support\RuntimeContext;
use smp_publication_integration\Support\Settings;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class MuckRackVerification {
    private const FIELD_VERIFIED = "muckrack_verified";
    private const FIELD_URL = "muckrack_url";
    private const FIELD_DESCRIPTION = "what_best_describe_you";

    public function register(): void {
        add_action( "init", [ $this, "register_shortcodes" ], 100 );
        add_filter( "the_author", [ $this, "filter_author_name" ], 20, 1 );
        add_filter( "the_content", [ $this, "filter_content" ], 30 );
        add_action( "wp_head", [ $this, "print_styles" ], 30 );
        add_action( "wp_enqueue_scripts", [ $this, "enqueue_icon_styles" ], 20 );
        add_action( "wp_footer", [ $this, "print_elementor_injection_script" ], 30 );
    }

    public function register_shortcodes(): void {
        add_shortcode( "acf_author_field", [ $this, "render_author_field_shortcode" ] );
        add_shortcode( "muckrack_verified", [ $this, "render_muckrack_shortcode" ] );
        add_shortcode( "smp_publication_muckrack_verified", [ $this, "render_publication_shortcode" ] );
    }

    public function enqueue_icon_styles(): void {
        return;
    }

    public function print_styles(): void {
        if ( ! RuntimeContext::is_public_dom_context() ) {
            return;
        }
        if ( ! Settings::bool( "muckrack_verified_enabled" ) && ! Settings::bool( "publication_muckrack_verified_enabled" ) ) {
            return;
        }
        $icon_size = self::setting_int( "muckrack_icon_size", 16, 8, 64 );
        $publication_font = self::setting_int( "publication_muckrack_font_size", 14, 8, 64 );
        $publication_mini_font = max( 8, $publication_font - 2 );
        echo "<style id=smpi-muckrack-styles>.smpi-muckrack-icon{display:inline-flex;align-items:center;justify-content:center;width:1em;height:1em;min-width:1em;margin-left:.28em;vertical-align:middle;line-height:1;--smpi-muckrack-color:#2d5277;color:var(--smpi-muckrack-color,#2d5277);background:transparent;font-size:" . esc_attr( (string) $icon_size ) . "px}.smpi-muckrack-icon svg{display:block;width:1em;height:1em}.smpi-muckrack-icon-check svg{width:1em;height:1em}.smpi-muckrack-link{text-decoration:none;display:inline-flex;align-items:center}.smpi-muckrack-inline-pair{display:inline-flex;align-items:center;max-width:100%;vertical-align:middle}.smpi-muckrack-inline-pair>.smpi-muckrack-author-label{min-width:min-content;word-break:normal;overflow-wrap:normal}.smpi-muckrack-inline-pair>.smpi-muckrack-link,.smpi-muckrack-inline-pair>.smpi-muckrack-icon,.smpi-muckrack-inline-pair>.smpi-muckrack-author-note{align-self:center;flex:0 0 auto}.smpi-muckrack-inline-pair>.smpi-muckrack-link{width:auto!important;max-width:none}.smpi-muckrack-brand{color:var(--smpi-muckrack-color,#2d5277);font-weight:700}.smpi-muckrack-footer-note,.smpi-muckrack-js-below-author,.smpi-muckrack-js-bottom-article{margin:24px 0 0}.smpi-muckrack-author-note{display:inline-flex;align-items:center;gap:.28em;margin:.18em 0 .18em .38em;padding:.34em .55em;border-left:2px solid var(--smpi-muckrack-color,#2d5277);background:#f5f8fb;color:#64748b;font-size:.72em;line-height:1.28;vertical-align:middle}.smpi-muckrack-author-note .smpi-muckrack-brand{color:var(--smpi-muckrack-color,#2d5277)}.smpi-muckrack-author-note a{color:inherit}.smpi-muckrack-footer-note{padding:12px 14px;border-left:3px solid var(--smpi-muckrack-color,#2d5277);background:#f5f8fb;font-size:.95em}.smpi-muckrack-publication-text{--smpi-muckrack-color:#2d5277;font-size:" . esc_attr( (string) $publication_font ) . "px}.smpi-muckrack-publication-note{display:block;clear:both;margin:12px 0 0;line-height:1.35;color:#334155}.smpi-muckrack-publication-footer{font-size:" . esc_attr( (string) $publication_font ) . "px}.smpi-muckrack-publication-block{display:block;padding:10px 12px;border-left:3px solid var(--smpi-muckrack-color,#2d5277);background:#f5f8fb}.smpi-muckrack-publication-mini_block{display:block;padding:7px 10px;border-left:2px solid var(--smpi-muckrack-color,#2d5277);background:#f6f8fb;color:#475569;line-height:1.3;letter-spacing:.005em;font-size:" . esc_attr( (string) $publication_mini_font ) . "px}.smpi-muckrack-publication-compact{display:inline-flex;align-items:center;gap:.35em;padding:.28em .7em;border:1px solid var(--smpi-muckrack-color,#2d5277);border-radius:999px;background:#fff}.smpi-muckrack-publication-minimalist{display:inline;color:inherit}.smpi-muckrack-publication-compact a,.smpi-muckrack-publication-minimalist a,.smpi-muckrack-publication-block a,.smpi-muckrack-publication-mini_block a{color:inherit}</style>";
    }

    public function render_author_field_shortcode( array $atts = [] ): string {
        $atts = shortcode_atts( [ "field" => "", "user_id" => 0, "post_id" => 0, "author_index" => 0 ], $atts, "acf_author_field" );
        $field = sanitize_key( (string) $atts["field"] );
        if ( "" === $field ) {
            return "";
        }
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"], (int) $atts["author_index"] );
        if ( ! $author_id ) {
            return "";
        }
        $value = self::author_field( $author_id, $field );
        return is_array( $value ) || is_object( $value ) ? esc_html( wp_json_encode( $value ) ) : wp_kses_post( (string) $value );
    }

    public function render_muckrack_shortcode( array $atts = [] ): string {
        $atts = shortcode_atts( [ "type" => "icon", "user_id" => 0, "post_id" => 0, "author_index" => 0, "style" => "", "context" => "" ], $atts, "muckrack_verified" );
        $author_id = $this->resolve_author_id( (int) $atts["user_id"], (int) $atts["post_id"], (int) $atts["author_index"] );
        if ( ! $author_id || ! self::author_verified( $author_id ) ) {
            return "";
        }
        $context = sanitize_key( (string) $atts["context"] );
        if ( "" === $context ) {
            $context = $this->author_context();
        }
        return "text" === sanitize_key( (string) $atts["type"] ) ? self::verification_text( $author_id ) : self::verification_icon( $author_id, sanitize_key( (string) $atts["style"] ), $context );
    }

    public function render_publication_shortcode( array $atts = [] ): string {
        $atts = shortcode_atts( [ 'class' => '', 'style' => '', 'color' => '' ], $atts, 'smp_publication_muckrack_verified' );
        return self::publication_verification_markup( sanitize_html_class( (string) $atts['class'] ), sanitize_key( (string) $atts['style'] ), sanitize_hex_color( (string) $atts['color'] ) ?: '' );
    }

    public function filter_author_name( string $display_name ): string {
        return $display_name;
    }

    public function filter_content( string $content ): string {
        return $content;
    }

    public function print_elementor_injection_script(): void {
        if ( ! RuntimeContext::is_public_dom_context() ) {
            return;
        }

        $contexts = Settings::array( "muckrack_verified_contexts" );
        $style = (string) Settings::get( "muckrack_verified_style", "tooltip" );
        $author_id = $this->resolve_author_id( 0 );
        $author_name = "";
        $author_header_badge = "";
        $author_footer_badge = "";
        $author_map = [];

        if ( Settings::bool( "muckrack_verified_enabled" ) ) {
            if ( $author_id && self::author_verified( $author_id ) ) {
                $user = get_user_by( "id", $author_id );
                $author_name = $user ? (string) $user->display_name : "";
                if ( in_array( $this->author_context(), [ "single_author", "author", "home" ], true ) && in_array( $this->author_context(), $contexts, true ) ) {
                    $author_header_badge = self::verification_icon( $author_id, $style, $this->author_context() );
                }
                if ( is_singular( "post" ) && in_array( "single_footer", $contexts, true ) ) {
                    $author_footer_badge = self::verification_icon( $author_id, $style, "single_footer" );
                }
            }

            if ( RuntimeContext::has_article_loop_context() && ( in_array( "loop_cards", $contexts, true ) || in_array( "home", $contexts, true ) || is_author() ) ) {
                $author_map = self::author_badge_map( $style, "loop_cards" );
            }
        }

        $publication_below = "";
        $publication_bottom = "";
        if ( is_singular( "post" ) && self::publication_enabled() ) {
            if ( in_array( "below_author", Settings::array( "publication_muckrack_placements" ), true ) ) {
                $publication_below = self::publication_verification_text( "smpi-muckrack-publication-note" );
            }
            if ( in_array( "bottom_article", Settings::array( "publication_muckrack_placements" ), true ) ) {
                $publication_bottom = self::publication_verification_text( "smpi-muckrack-publication-footer" );
            }
        }

        if ( "" === $author_header_badge && "" === $author_footer_badge && empty( $author_map ) && "" === $publication_below && "" === $publication_bottom ) {
            return;
        }

        $payload = wp_json_encode(
            [
                "authorHeaderBadge" => $author_header_badge,
                "authorFooterBadge" => $author_footer_badge,
                "authorName" => $author_name,
                "authors" => $author_map,
                "contexts" => $contexts,
                "context" => $this->author_context(),
                "publicationBelow" => $publication_below,
                "publicationBottom" => $publication_bottom,
            ]
        );
        $script = <<<SMPI_JS
(function(data){
if(!data)return;
function clean(s){return String(s||"").replace(/\s+/g," ").trim();}
function norm(s){return clean(s).toLowerCase().replace(/[^a-z0-9]+/g,"");}
function htmlNode(html){var t=document.createElement("template");t.innerHTML=(html||"").trim();return t.content.firstElementChild;}
function visible(el){if(!el)return false;var r=el.getBoundingClientRect();var st=window.getComputedStyle(el);return r.width>1&&r.height>1&&st.display!=="none"&&st.visibility!=="hidden"&&st.opacity!=="0";}
function unique(nodes){var out=[];nodes.forEach(function(n){if(n&&out.indexOf(n)<0)out.push(n);});return out;}
function y(el){var r=el.getBoundingClientRect();return r.top+window.scrollY;}
function q(sel,root){return Array.prototype.slice.call((root||document).querySelectorAll(sel));}
function contentWidget(){var selectors=[".elementor-widget-theme-post-content",".elementor-widget-post-content","article .entry-content",".entry-content",".post-content"];for(var i=0;i<selectors.length;i++){var el=document.querySelector(selectors[i]);if(el&&visible(el))return el;}return null;}
function contentTop(){var el=contentWidget();return el?y(el):null;}
function isLoop(el){return !!(el&&el.closest(".e-loop-item,.elementor-loop-item,.elementor-post,.elementor-grid-item,.elementor-widget-loop-grid article,.elementor-posts-container article"));}
function isAdminOrHidden(el){return !!(el&&el.closest("#wpadminbar,.elementor-editor-active,.elementor-location-popup,.smpi-multi-author-item,script,style,noscript"));}
function isInvalidPlacement(el){return !!(el&&el.closest(".elementor-pagination,.pagination,.nav-links,[class*='pagination']"));}
var bySlug={},byName={};
(data.authors||[]).forEach(function(a){if(!a||!a.badge)return;if(a.slug)bySlug[String(a.slug).toLowerCase()]=a;if(a.name)byName[norm(a.name)]=a;});
function slugFromHref(href){var m=String(href||"").match(/\/author\/([^\/?#]+)/i);return m?decodeURIComponent(m[1]).toLowerCase():"";}
function recordFor(el,fallbackName){if(!el)return null;var link=el.matches&&el.matches("a[href]")?el:el.closest&&el.closest("a[href]");var slug=link?slugFromHref(link.getAttribute("href")):"";if(slug&&bySlug[slug])return bySlug[slug];var txt=norm(fallbackName||clean(el.textContent));if(txt&&byName[txt])return byName[txt];return null;}
function badgeFor(el,fallbackBadge,fallbackName){var rec=recordFor(el,fallbackName);return rec&&rec.badge?rec.badge:(fallbackBadge||"");}
function authorRoot(el){return el.closest(".elementor-post-info__item,.elementor-widget-post-info,.elementor-widget-theme-post-author,.elementor-author-box,.elementor-widget-author-box,.elementor-widget-heading,.elementor-icon-list-item,.byline,.author,.e-loop-item,.elementor-post,.elementor-grid-item")||el.parentElement;}
function hasBadge(root){return !!(root&&root.querySelector(".smpi-muckrack-link,.smpi-muckrack-icon,.smpi-muckrack-author-note"));}
function exactAuthorTarget(el){if(!el)return null;if(el.matches("a[href]"))return el;var link=el.closest("a[href]");return link&&(link.matches("a[href*='/author/'],a[rel='author']")||norm(link.textContent)===norm(el.textContent))?link:el;}
function pairBadge(el,node){var target=exactAuthorTarget(el);if(!target||!target.parentNode||!node||isInvalidPlacement(target))return false;var existing=target.closest(".smpi-muckrack-inline-pair");if(existing){if(hasBadge(existing))return false;existing.appendChild(node);return true;}var pair=document.createElement("span");pair.className="smpi-muckrack-inline-pair";if(!target.matches("a[href]")&&target.children.length===0){var label=document.createElement("span");label.className="smpi-muckrack-author-label";while(target.firstChild)label.appendChild(target.firstChild);pair.appendChild(label);pair.appendChild(node);target.appendChild(pair);return true;}target.parentNode.insertBefore(pair,target);pair.appendChild(target);pair.appendChild(node);return true;}
function insertAfter(el,html,root){if(!el||!html)return false;var scope=root||authorRoot(el);if(hasBadge(scope))return false;var node=htmlNode(html);return node?pairBadge(el,node):false;}
function exactTextTargets(root,name){var target=norm(name);if(!root||!target)return [];var out=[];q("a[href*=\"/author/\"],a[rel=\"author\"],.elementor-heading-title,.elementor-icon-list-text,.elementor-author-box__name,*",root).forEach(function(el){if(!visible(el)||isAdminOrHidden(el)||isInvalidPlacement(el))return;var tx=norm(el.textContent);if(tx!==target)return;var childExact=Array.prototype.some.call(el.children||[],function(ch){return norm(ch.textContent)===target;});if(childExact)return;out.push(el);});return unique(out).sort(function(a,b){return a.getBoundingClientRect().height-b.getBoundingClientRect().height;});}
function structuralAuthorLinks(root){var selectors=[".elementor-post-info__item--type-author a[href*=\"/author/\"]",".elementor-widget-post-info a[href*=\"/author/\"]",".elementor-widget-theme-post-author a[href*=\"/author/\"]",".elementor-author-box a[href*=\"/author/\"]",".elementor-widget-heading a[href*=\"/author/\"]",".elementor-icon-list-item a[href*=\"/author/\"]","a[rel=\"author\"]",".byline a[href*=\"/author/\"]","[class*=\"author\"] a[href*=\"/author/\"]"];var out=[];selectors.forEach(function(sel){q(sel,root).forEach(function(el){if(visible(el)&&!isAdminOrHidden(el)&&!isInvalidPlacement(el))out.push(el);});});return unique(out);}
function isCurrentAuthorTarget(el){var current=norm(data.authorName);if(!el||!current)return false;var link=el.matches&&el.matches("a[href]")?el:el.closest&&el.closest("a[href]");var slug=link?slugFromHref(link.getAttribute("href")):"";if(slug&&bySlug[slug])return norm(bySlug[slug].name)===current;return norm(el.textContent)===current;}
function topAuthorTargets(){var ct=contentTop();var linked=structuralAuthorLinks(document).filter(function(el){if(isLoop(el)||!isCurrentAuthorTarget(el))return false;if(ct!==null&&y(el)>ct)return false;return true;});var out=linked.slice();exactTextTargets(document,data.authorName).forEach(function(el){if(isLoop(el)||!isCurrentAuthorTarget(el))return;if(ct!==null&&y(el)>ct)return;if(linked.some(function(link){return link===el||link.contains(el)||el.contains(link);}))return;out.push(el);});return unique(out).sort(function(a,b){return y(a)-y(b);});}
function authorCardContainers(){var ct=contentTop();var want=norm(data.authorName);var out=[];q(".elementor-author-box,.elementor-widget-theme-post-author,.elementor-widget-author-box").forEach(function(el){if(visible(el)&&!isLoop(el))out.push(el);});q(".e-con,.elementor-section,.elementor-container,.elementor-element").forEach(function(el){if(!visible(el)||isLoop(el)||isAdminOrHidden(el))return;if(ct!==null&&y(el)<ct)return;var tx=clean(el.textContent);var ntx=norm(tx);if(want&&ntx.indexOf(want)===-1)return;var lower=tx.toLowerCase();var hasAbout=lower.indexOf("about the author")!==-1;var hasSocial=/twitter\s*\/\s*x|linkedin|email/.test(lower);var hasImage=!!el.querySelector("img,.elementor-widget-image");var isReasonable=el.getBoundingClientRect().height<900&&el.getBoundingClientRect().width>120;if((hasAbout||hasSocial||hasImage)&&isReasonable)out.push(el);});return unique(out).sort(function(a,b){return a.getBoundingClientRect().height-b.getBoundingClientRect().height;});}
function footerAuthorTargets(){var out=[];authorCardContainers().forEach(function(card){exactTextTargets(card,data.authorName).forEach(function(el){out.push({el:el,root:card});});});return out;}
function loopCards(){var ct=contentTop();var selectors=".e-loop-item,.elementor-loop-item,.elementor-widget-loop-grid article,.elementor-posts-container article,.elementor-posts .elementor-post,.elementor-widget-posts .elementor-post,.elementor-grid .elementor-grid-item";return unique(q(selectors).filter(function(card){if(!visible(card)||isAdminOrHidden(card))return false;if(ct!==null&&y(card)<=ct)return false;return true;}));}
function injectFooter(){if(!data.authorFooterBadge)return;footerAuthorTargets().forEach(function(pair){insertAfter(pair.el,data.authorFooterBadge,pair.root);});}
function injectLoops(){if((data.contexts||[]).indexOf("loop_cards")<0)return;var ct=contentTop();loopCards().forEach(function(card){var targets=structuralAuthorLinks(card).filter(function(el){return ct===null||y(el)>ct;});if(!targets.length){Object.keys(byName).forEach(function(key){exactTextTargets(card,byName[key].name).forEach(function(el){if(ct===null||y(el)>ct)targets.push(el);});});targets=unique(targets);}targets.forEach(function(el){var badge=badgeFor(el,"",clean(el.textContent));if(badge)insertAfter(el,badge,authorRoot(el));});});}
function removeBadge(icon){if(!icon)return;var link=icon.closest(".smpi-muckrack-link");var node=link&&link.contains(icon)?link:icon;var pair=node.closest(".smpi-muckrack-inline-pair");node.remove();if(pair&&!hasBadge(pair)&&pair.parentNode){var label=pair.querySelector(":scope > .smpi-muckrack-author-label");if(label){while(label.firstChild)pair.parentNode.insertBefore(label.firstChild,pair);label.remove();}while(pair.firstChild)pair.parentNode.insertBefore(pair.firstChild,pair);pair.remove();}}
function cleanupInvalidBadges(){q(".elementor-pagination .smpi-muckrack-icon,.pagination .smpi-muckrack-icon,.nav-links .smpi-muckrack-icon,[class*='pagination'] .smpi-muckrack-icon").forEach(removeBadge);}
function removeTopAuthorBadges(){var ct=contentTop();q(".smpi-muckrack-icon").forEach(function(icon){if(isLoop(icon))return;if(ct!==null&&y(icon)>ct)return;removeBadge(icon);});}
function normalizeTopBadge(){if(!data.authorHeaderBadge)return;var targets=topAuthorTargets();if(!targets.length)return;removeTopAuthorBadges();targets=topAuthorTargets();if(!targets.length)return;var target=targets[0];insertAfter(target,data.authorHeaderBadge,authorRoot(target));}
function contentPlacementRoot(){var faqs=q(".smpi-post-faqs").filter(visible).sort(function(a,b){return y(a)-y(b);});if(faqs.length)return faqs[faqs.length-1];var cw=contentWidget();return cw||document.querySelector("article")||null;}
function footerAuthorPlacementRoot(){var cards=authorCardContainers();return cards.length?cards[0]:null;}
function markerExists(cls){return !!document.querySelector("."+cls);}
function insertBlockAfter(target,html,cls){if(!target||!html||markerExists(cls))return false;var wrap=document.createElement("div");wrap.className=cls;wrap.innerHTML=html;target.insertAdjacentElement("afterend",wrap);return true;}
function injectPublicationBottom(){if(!data.publicationBottom)return;insertBlockAfter(contentPlacementRoot(),data.publicationBottom,"smpi-muckrack-js-bottom-article");}
function injectPublicationBelowAuthor(){if(!data.publicationBelow)return;insertBlockAfter(footerAuthorPlacementRoot(),data.publicationBelow,"smpi-muckrack-js-below-author");}
function run(){cleanupInvalidBadges();normalizeTopBadge();injectFooter();injectLoops();cleanupInvalidBadges();injectPublicationBottom();injectPublicationBelowAuthor();}
if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",run);}else{run();}
setTimeout(run,500);setTimeout(run,1300);setTimeout(run,2600);
})(
SMPI_JS;
        echo "<script id=\"smpi-muckrack-elementor-placement\">" . $script . $payload . ");</script>";
    }

    private static function author_badge_map( string $style, string $context = "loop_cards" ): array {
        $users = get_users(
            [
                "has_published_posts" => [ "post" ],
                "fields" => [ "ID", "display_name", "user_nicename" ],
                "number" => 300,
                "orderby" => "post_count",
                "order" => "DESC",
            ]
        );
        $out = [];
        foreach ( $users as $user ) {
            $author_id = (int) $user->ID;
            if ( ! self::author_verified( $author_id ) ) {
                continue;
            }
            $badge = self::verification_icon( $author_id, $style, $context );
            if ( "" === $badge ) {
                continue;
            }
            $out[] = [
                "id" => $author_id,
                "name" => (string) $user->display_name,
                "slug" => (string) $user->user_nicename,
                "badge" => $badge,
            ];
        }
        return $out;
    }

    private function author_context(): string {
        if ( is_singular( "post" ) ) {
            return "single_author";
        }
        if ( is_author() ) {
            return "author";
        }
        if ( is_home() || is_front_page() ) {
            return "home";
        }
        return "";
    }

    private function resolve_author_id( int $explicit_id = 0, int $explicit_post_id = 0, int $author_index = 0 ): int {
        return MultiAuthors::resolve_author_id( $explicit_id, $explicit_post_id, max( 0, $author_index ) );
    }

    public static function author_field( int $author_id, string $field ) {
        $field = sanitize_key( $field );
        if ( "" === $field ) {
            return "";
        }

        $resolver = new AuthorFieldResolver();
        $canonical_field = self::canonical_author_field( $field );
        if ( "" !== $canonical_field ) {
            $value = $resolver->value( $author_id, $canonical_field );
            if ( self::has_author_field_value( $value ) ) {
                return $value;
            }
        }

        $value = self::raw_author_field( $author_id, $field );
        if ( self::has_author_field_value( $value ) ) {
            return $value;
        }

        return "";
    }

    private static function canonical_author_field( string $field ): string {
        $aliases = AuthorFieldResolver::aliases();
        if ( isset( $aliases[ $field ] ) ) {
            return $field;
        }
        foreach ( $aliases as $canonical => $field_aliases ) {
            if ( in_array( $field, array_map( "sanitize_key", $field_aliases ), true ) ) {
                return (string) $canonical;
            }
        }
        return "";
    }

    private static function raw_author_field( int $author_id, string $field ) {
        if ( function_exists( "get_field" ) ) {
            $value = get_field( $field, "user_" . $author_id );
            if ( null !== $value && false !== $value && "" !== $value ) {
                return $value;
            }
        }
        $meta = get_user_meta( $author_id, $field, true );
        if ( null !== $meta && false !== $meta && "" !== $meta ) {
            return $meta;
        }
        $user = get_userdata( $author_id );
        if ( ! $user ) {
            return "";
        }
        if ( in_array( $field, [ "name", "author_name" ], true ) ) {
            return (string) $user->display_name;
        }
        if ( isset( $user->data->{$field} ) && is_scalar( $user->data->{$field} ) ) {
            return (string) $user->data->{$field};
        }
        return "";
    }

    private static function has_author_field_value( $value ): bool {
        return null !== $value && false !== $value && "" !== $value && [] !== $value;
    }

    public static function author_acf_verified( int $author_id ): bool {
        return self::truthy( self::author_field( $author_id, self::FIELD_VERIFIED ) );
    }

    public static function author_verified( int $author_id ): bool {
        return self::author_acf_verified( $author_id ) || Settings::bool( "muckrack_author_always_show" );
    }


    public static function verification_icon( int $author_id, string $style = "tooltip", string $context = "" ): string {
        if ( ! self::author_verified( $author_id ) ) {
            return "";
        }
        if ( "text" === $style ) {
            return self::verification_text( $author_id );
        }
        if ( "compact_block" === $style ) {
            return self::verification_author_note( $author_id, $context );
        }
        $url = esc_url( (string) self::author_field( $author_id, self::FIELD_URL ) );
        $label = esc_attr( "Verified by MuckRack editorial team" );
        $color = self::author_context_color( $context );
        $style_key = (string) Settings::get( "muckrack_icon_style", "circle_check" );
        if ( ! in_array( $style_key, [ "circle_check", "circle_outline_check", "check" ], true ) ) {
            $style_key = "circle_check";
        }
        $icon_class = "check" === $style_key ? "smpi-muckrack-icon-check" : ( "circle_outline_check" === $style_key ? "smpi-muckrack-icon-outline" : "smpi-muckrack-icon-circle" );
        $quote = chr( 34 );
        $size = self::author_context_icon_size( $context );
        $margin_left = self::author_context_margin( "left", $context );
        $margin_top = self::author_context_margin( "top", $context );
        $context_class = "" !== self::author_context_key( "smpi", $context ) ? " smpi-muckrack-context-" . sanitize_html_class( sanitize_key( $context ) ) : "";
        $icon = "<span class=" . $quote . "smpi-muckrack-icon " . esc_attr( $icon_class . $context_class ) . $quote . " data-smpi-muckrack-context=" . $quote . esc_attr( sanitize_key( $context ) ) . $quote . " title=" . $quote . $label . $quote . " aria-label=" . $quote . $label . $quote . " style=" . $quote . "--smpi-muckrack-color:" . esc_attr( $color ) . ";--smpi-muckrack-margin-left:" . esc_attr( (string) $margin_left ) . "px;--smpi-muckrack-margin-top:" . esc_attr( (string) $margin_top ) . "px;color:" . esc_attr( $color ) . ";font-size:" . esc_attr( (string) $size ) . "px;margin-left:" . esc_attr( (string) $margin_left ) . "px;margin-top:" . esc_attr( (string) $margin_top ) . "px" . $quote . ">" . self::icon_svg_html( $style_key ) . "</span>";
        return $url ? "<a class=smpi-muckrack-link href=" . $quote . $url . $quote . " target=_blank rel=noopener>" . $icon . "</a>" : $icon;
    }

    private static function icon_svg_html( string $style ): string {
        if ( "check" === $style ) {
            return "<svg class=\"smpi-muckrack-svg\" xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 -0.5 25 25\" fill=\"none\" aria-hidden=\"true\" focusable=\"false\"><path d=\"M9 12.0002L11.333 14.3332L16 9.66724\" stroke=\"currentColor\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"></path></svg>";
        }
        if ( "circle_outline_check" === $style ) {
            return "<svg class=\"smpi-muckrack-svg\" xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 -0.5 25 25\" fill=\"none\" aria-hidden=\"true\" focusable=\"false\"><path fill-rule=\"evenodd\" clip-rule=\"evenodd\" d=\"M5.5 12.0002C5.50024 8.66068 7.85944 5.78639 11.1348 5.1351C14.4102 4.48382 17.6895 6.23693 18.9673 9.32231C20.2451 12.4077 19.1655 15.966 16.3887 17.8212C13.6119 19.6764 9.91127 19.3117 7.55 16.9502C6.23728 15.6373 5.49987 13.8568 5.5 12.0002Z\" stroke=\"currentColor\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"></path><path d=\"M9 12.0002L11.333 14.3332L16 9.66724\" stroke=\"currentColor\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"></path></svg>";
        }
        return "<svg class=\"smpi-muckrack-svg\" xmlns=\"http://www.w3.org/2000/svg\" width=\"1em\" height=\"1em\" viewBox=\"0 0 24 24\" fill=\"none\" aria-hidden=\"true\" focusable=\"false\"><path fill-rule=\"evenodd\" clip-rule=\"evenodd\" d=\"M2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12ZM15.7071 9.29289C16.0976 9.68342 16.0976 10.3166 15.7071 10.7071L12.0243 14.3899C11.4586 14.9556 10.5414 14.9556 9.97568 14.3899L8.29289 12.7071C7.90237 12.3166 7.90237 11.6834 8.29289 11.2929C8.68342 10.9024 9.31658 10.9024 9.70711 11.2929L11 12.5858L14.2929 9.29289C14.6834 8.90237 15.3166 8.90237 15.7071 9.29289Z\" fill=\"currentColor\"></path></svg>";
    }

    public static function verification_text( int $author_id ): string {
        if ( ! self::author_verified( $author_id ) ) {
            return "";
        }
        $url = (string) self::author_field( $author_id, self::FIELD_URL );
        $description = trim( (string) self::author_field( $author_id, self::FIELD_DESCRIPTION ) );
        $description = "" !== $description ? $description : "Author";
        $target = "" !== $url ? $url : "https://muckrack.com/";
        return esc_html( $description ) . " verified by <span class=\"smpi-muckrack-brand\">MuckRack</span> editorial team <a href=\"" . esc_url( $target ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">(learn more)</a>";
    }

    public static function verification_author_note( int $author_id, string $context = "" ): string {
        if ( ! self::author_verified( $author_id ) ) {
            return "";
        }
        $url = (string) self::author_field( $author_id, self::FIELD_URL );
        $target = "" !== $url ? $url : "https://muckrack.com/";
        $color = self::author_context_color( $context );
        $font_size = max( 10, self::author_context_icon_size( $context ) - 4 );
        return '<span class="smpi-muckrack-author-note" style="--smpi-muckrack-color:' . esc_attr( $color ) . ';font-size:' . esc_attr( (string) $font_size ) . 'px">Author verified by <span class="smpi-muckrack-brand">MuckRack</span> editorial team <a href="' . esc_url( $target ) . '" target="_blank" rel="noopener">(learn more)</a></span>';
    }

    private static function author_context_key( string $prefix, string $context ): string {
        $context = sanitize_key( $context );
        $allowed = [ "single_author", "single_footer", "loop_cards", "home", "author" ];
        return in_array( $context, $allowed, true ) ? $prefix . "_" . $context : "";
    }

    private static function author_context_color( string $context = "" ): string {
        $override_key = self::author_context_key( "muckrack_icon_color", $context );
        if ( "" !== $override_key ) {
            $override = sanitize_hex_color( (string) Settings::get( $override_key, "" ) );
            if ( $override ) {
                return $override;
            }
        }
        return sanitize_hex_color( (string) Settings::get( "muckrack_icon_color", "#2d5277" ) ) ?: "#2d5277";
    }

    private static function author_context_icon_size( string $context = "" ): int {
        $override_key = self::author_context_key( "muckrack_icon_size", $context );
        if ( "" !== $override_key ) {
            $override = absint( Settings::get( $override_key, 0 ) );
            if ( $override > 0 ) {
                return max( 8, min( 64, $override ) );
            }
        }
        return self::setting_int( "muckrack_icon_size", 16, 8, 64 );
    }

    private static function author_context_margin( string $axis, string $context = "" ): int {
        $axis = "top" === $axis ? "top" : "left";
        $override_key = self::author_context_key( "muckrack_icon_margin_" . $axis, $context );
        if ( "" !== $override_key ) {
            $override = Settings::get( $override_key, "" );
            if ( is_scalar( $override ) && "" !== trim( (string) $override ) ) {
                $override = (int) $override;
                return max( -32, min( 64, $override ) );
            }
        }
        return self::setting_signed_int( "muckrack_icon_margin_" . $axis, "top" === $axis ? 0 : 2, -32, 64 );
    }

    public static function publication_verified(): bool {
        return self::truthy( Fields::option( "publication_muckrack_verified" ) );
    }

    public static function publication_enabled(): bool {
        return Settings::bool( "publication_muckrack_verified_enabled" ) && self::publication_verified();
    }

    public static function publication_verification_text( string $class = "" ): string {
        if ( ! self::publication_enabled() ) {
            return "";
        }

        return self::publication_verification_markup( $class );
    }

    public static function publication_verification_markup( string $class = "", string $style_override = "", string $color_override = "" ): string {
        $mode = (string) Settings::get( "publication_muckrack_text_mode", "news_outlet" );
        $label = "publication_name" === $mode ? get_bloginfo( "name" ) : "News outlet";
        $url = trim( (string) Fields::option( "publication_muckrack_url" ) );
        $target = "" !== $url ? $url : "https://muckrack.com/";
        $style = sanitize_key( "" !== $style_override ? $style_override : (string) Settings::get( "publication_muckrack_style", "block" ) );
        if ( ! in_array( $style, [ "block", "mini_block", "compact", "minimalist" ], true ) ) {
            $style = "block";
        }
        $color = sanitize_hex_color( "" !== $color_override ? $color_override : (string) Settings::get( "publication_muckrack_color", "#2d5277" ) ) ?: "#2d5277";
        $font_size = self::setting_int( "publication_muckrack_font_size", 14, 8, 64 );
        if ( "mini_block" === $style ) {
            $font_size = max( 8, $font_size - 2 );
        }
        $classes = trim( "smpi-muckrack-publication-text smpi-muckrack-publication-" . $style . " " . $class );

        return '<span class="' . esc_attr( $classes ) . '" style="--smpi-muckrack-color:' . esc_attr( $color ) . ';font-size:' . esc_attr( (string) $font_size ) . 'px">' . esc_html( $label ) . ' verified by <span class="smpi-muckrack-brand">MuckRack</span> editorial team <a href="' . esc_url( $target ) . '" target="_blank" rel="noopener noreferrer">(learn more)</a></span>';
    }

    public static function publication_report(): array {
        return [
            "enabled" => Settings::bool( "publication_muckrack_verified_enabled" ),
            "acf_verified" => self::publication_verified(),
            "effective" => self::publication_enabled(),
            "text_mode" => (string) Settings::get( "publication_muckrack_text_mode", "news_outlet" ),
            "style" => (string) Settings::get( "publication_muckrack_style", "block" ),
            "color" => sanitize_hex_color( (string) Settings::get( "publication_muckrack_color", "#2d5277" ) ) ?: "#2d5277",
            "font_size" => self::setting_int( "publication_muckrack_font_size", 14, 8, 64 ),
            "placements" => Settings::array( "publication_muckrack_placements" ),
            "url" => trim( (string) Fields::option( "publication_muckrack_url" ) ),
            "shortcode" => "[smp_publication_muckrack_verified]",
            "preview" => wp_strip_all_tags( self::publication_verification_text() ),
            "preview_html" => self::publication_verification_text(),
        ];
    }

    public static function integrity_report( int $limit = 10 ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT u.ID, u.display_name, COUNT(p.ID) AS posts FROM {$wpdb->users} u LEFT JOIN {$wpdb->posts} p ON p.post_author = u.ID AND p.post_type = %s AND p.post_status = %s GROUP BY u.ID ORDER BY posts DESC, u.ID ASC LIMIT %d", "post", "publish", $limit ) );
        $out = [];
        foreach ( $rows as $row ) {
            $author_id = (int) $row->ID;
            $out[] = [
                "user_id" => $author_id,
                "display_name" => $row->display_name,
                "posts" => (int) $row->posts,
                "acf_verified" => self::author_acf_verified( $author_id ),
                "verified" => self::author_verified( $author_id ),
                "forced" => Settings::bool( "muckrack_author_always_show" ),
                "has_url" => "" !== trim( (string) self::author_field( $author_id, self::FIELD_URL ) ),
                "has_description" => "" !== trim( (string) self::author_field( $author_id, self::FIELD_DESCRIPTION ) ),
                "shortcode_icon" => "" !== self::verification_icon( $author_id ),
            ];
        }
        return $out;
    }

    private static function setting_int( string $key, int $default, int $min, int $max ): int {
        $value = absint( Settings::get( $key, $default ) );
        return max( $min, min( $max, $value ?: $default ) );
    }

    private static function setting_signed_int( string $key, int $default, int $min, int $max ): int {
        $raw = Settings::get( $key, $default );
        $value = is_scalar( $raw ) && "" !== trim( (string) $raw ) ? (int) $raw : $default;
        return max( $min, min( $max, $value ) );
    }

    private static function truthy( $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }
        $value = strtolower( trim( (string) $value ) );
        return in_array( $value, [ "1", "true", "yes", "on", "verified" ], true );
    }
}
