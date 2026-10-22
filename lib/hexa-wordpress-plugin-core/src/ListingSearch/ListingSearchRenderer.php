<?php

namespace Hexa\PluginCore\ListingSearch;

use Hexa\PluginCore\PublicComponents\PublicComponent;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\QueryFilter\QuerySql;

/**
 * The listing search box: a text field, the profile's filters with the number
 * of results behind each option, a clear link and a result line.
 *
 * It is an ordinary GET form for the page's own address, so it works without
 * JavaScript and every state is a shareable link. With JavaScript, typing
 * and ticking reload the same address in the background and swap in the new
 * listing (every element matching the profile's target, by default
 * `.hexa-listing-results`) and the new filter counts; Elementor widgets in
 * the swapped listing are started again, so Load more keeps working. The
 * markup inherits the site's fonts and colours; it ships three looks (see
 * css()), restyled through classes `hls-*` and custom
 * properties (`--hls-accent`, `--hls-border`, `--hls-radius`, `--hls-panel-bg`,
 * `--hls-field-height`, `--hls-field-width`, `--hls-font-size`, `--hls-small`).
 */
final class ListingSearchRenderer {
    /** @var string[] Profiles rendered on this page, so a host can skip adding a second box. */
    private static array $rendered = [];

    /**
     * @param array<string,mixed> $options style (none|default-1|default-2), placeholder, target, class, hidden (extra hidden fields),
     *                                    filters (keys to show; all by default), results_one, results_many, empty, clear.
     */
    public function render( string $profile_id, array $options = [] ): string {
        $state = ListingSearch::state( $profile_id );
        if ( null === $state ) {
            return '';
        }

        $profile = $state['profile'];
        $style   = in_array( $options['style'] ?? '', self::STYLES, true ) ? (string) $options['style'] : 'default-1';
        $labels  = array_merge( $profile['labels'], array_filter( array_intersect_key( $options, array_flip( [ 'results_one', 'results_many', 'empty', 'clear' ] ) ), static fn( $value ): bool => is_string( $value ) && '' !== trim( $value ) ) );
        $context = $state['context'];
        $search  = 'search' === $context['type'];
        $target  = ListingSearchProfile::selector( (string) ( $options['target'] ?? $profile['target'] ) );
        $text_name = $search ? 's' : ListingSearch::PARAM_TEXT;

        $placeholder = trim( (string) ( $options['placeholder'] ?? '' ) );
        if ( '' === $placeholder ) {
            $placeholder = '' !== $context['title'] ? sprintf( $labels['placeholder_context'], $context['title'] ) : $labels['placeholder'];
        }

        $shown = array_filter( array_map( 'trim', is_array( $options['filters'] ?? null ) ? $options['filters'] : explode( ',', (string) ( $options['filters'] ?? '' ) ) ) );
        $shown_filters = [] !== $shown ? array_intersect_key( $state['filters'], array_flip( $shown ) ) : $state['filters'];

        global $wpdb;
        $counts = [];
        $total  = 0;
        $active = $state['active'] || ( $search && '' !== $state['text'] );
        if ( is_object( $wpdb ) ) {
            $counts = QueryFilterSet::counts( $wpdb, $shown_filters, $state['values'], $state['scope'], static fn( array $values ): string => ListingSearch::ids_sql( $state, $values ) );
            if ( $active ) {
                $total = QuerySql::count( $wpdb, ListingSearch::ids_sql( $state, $state['values'] ) );
            }
        }

        $base = PublicComponent::sanitize_base( self::page_url(), [ ListingSearch::PARAM_PROFILE, ListingSearch::PARAM_TEXT, ListingSearch::PARAM_FILTER, 's', 'paged' ] );
        [ $base_path, $base_args ] = PublicComponent::split_base( $base );
        $hidden = array_merge( $base_args, array_filter( (array) ( $options['hidden'] ?? [] ), 'is_scalar' ) );
        unset( $hidden[ ListingSearch::QUERY_VAR ] );

        $dom_id = 'hls-' . $profile['id'];
        $status = $active ? ( 0 === $total ? $labels['empty'] : sprintf( 1 === $total ? $labels['results_one'] : $labels['results_many'], $total ) ) : '';

        $html = '<form class="' . esc_attr( trim( 'hls hls--' . $style . ' ' . $profile['class'] . ' ' . (string) ( $options['class'] ?? '' ) ) ) . '" id="' . esc_attr( $dom_id ) . '" role="search" method="get" action="' . esc_url( $base_path ) . '"'
            . ' data-hls-target="' . esc_attr( $target ) . '" data-hls-min="' . esc_attr( (string) $profile['min_chars'] ) . '" data-hls-error="' . esc_attr( $labels['error'] ) . '">'
            . PublicComponent::hidden_inputs( array_map( 'strval', $hidden ) )
            . '<input type="hidden" name="' . esc_attr( ListingSearch::PARAM_PROFILE ) . '" value="' . esc_attr( $profile['id'] ) . '">'
            . '<div class="hls-bar">'
            . '<label class="hls-field hls-field--text"><span class="hls-visually-hidden">' . esc_html( $labels['search'] ) . '</span>'
            . '<svg class="hls-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
            . '<input class="hls-input" type="search" name="' . esc_attr( $text_name ) . '" value="' . esc_attr( $state['text'] ) . '" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off" enterkeyhint="search"></label>'
            . '<div class="hls-filters">' . QueryFilterSet::controls( $shown_filters, $state['values'], ListingSearch::PARAM_FILTER, 'hls', $state['scope'], $counts ) . '</div>'
            . '<a class="hls-clear" href="' . esc_url( $base . ( $search ? ( str_contains( $base, '?' ) ? '&' : '?' ) . 's=' : '' ) ) . '"' . ( $active ? '' : ' hidden' ) . '>' . esc_html( $labels['clear'] ) . '</a>'
            . '<button class="hls-submit hls-visually-hidden" type="submit">' . esc_html( $labels['search'] ) . '</button>'
            . '</div>'
            . '<p class="hls-status" role="status" aria-live="polite"' . ( '' === $status ? ' hidden' : '' ) . '>' . esc_html( $status ) . '</p>'
            . '</form>';

        self::$rendered[] = $profile['id'];

        // Visitor text is echoed back; keep it inert to a later do_shortcode() pass.
        return $this->assets( $style ) . PublicComponent::inert( $html );
    }

    /**
     * A still picture of the box in one look, for settings screens: the real markup and CSS
     * with sample filters (one ticked), never connected to a query or the live script.
     */
    public static function preview( string $style ): string {
        $style = in_array( $style, self::STYLES, true ) ? $style : 'default-1';
        $chev  = '';
        $html  = '<form class="hls hls--' . esc_attr( $style ) . '" data-hls-ready="1" onsubmit="return false" style="margin:0">'
            . '<div class="hls-bar"><label class="hls-field hls-field--text"><span class="hls-visually-hidden">Search</span>'
            . '<svg class="hls-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
            . '<input class="hls-input" type="search" value="" placeholder="Search Business" tabindex="-1"></label>'
            . '<div class="hls-filters">';
        foreach ( [ 'Category' => 1, 'Tag' => 0, 'Author' => 0 ] as $label => $ticked ) {
            $html .= '<details class="hls-filter' . ( $ticked ? ' is-active' : '' ) . '"><summary class="hls-filter-toggle" tabindex="-1"><span class="hls-filter-label">' . $label . '</span>'
                . ( $ticked ? '<span class="hls-filter-badge">1</span>' : '' ) . '</summary></details>';
        }

        return $html . '</div><a class="hls-clear" href="#" tabindex="-1">Clear</a></div><p class="hls-status">12 stories</p></form>'
            . '<style>' . self::css( $style ) . '</style>' . $chev;
    }

    /** Whether a profile's box was already printed on this page. */
    public static function rendered( string $profile_id ): bool {
        return in_array( $profile_id, self::$rendered, true );
    }

    /** The current page's address without its page number. */
    private static function page_url(): string {
        $uri  = PublicComponent::request_uri();
        $path = (string) parse_url( $uri, PHP_URL_PATH );
        $path = (string) preg_replace( '#/page/\d+/?$#', '/', $path );
        $query = (string) parse_url( $uri, PHP_URL_QUERY );

        return $path . ( '' !== $query ? '?' . $query : '' );
    }

    /**
     * The box's style and script travel with every box: another plugin may render the page
     * content once and discard it (a description or excerpt builder), so "print once per
     * request" could leave the visible box bare. The script runs once per page.
     */
    private function assets( string $style ): string {
        return '<style class="hexa-listing-search-css">' . self::css( $style ) . '</style><script class="hexa-listing-search-js" data-cfasync="false">' . self::js() . '</script>';
    }

    public const STYLES = [ 'none', 'default-1', 'default-2' ];

    /**
     * The box's CSS for one look: `none` (only what the dropdowns need to work), `default-1`
     * (compact boxed field and filter buttons) or `default-2` (underlined field, text filters).
     * Rules are scoped `.hls.hls …` so a theme's or Elementor kit's general field, label and
     * checkbox styles cannot reshape the box; a site restyles it by setting the --hls-* custom
     * properties on `.hls` (no selector fight), or picks `none` and styles it from scratch.
     */
    public static function css( string $style = 'default-1' ): string {
        $css = ':where(.hls){--hls-accent:currentColor;--hls-border:rgba(127,127,127,.35);--hls-radius:6px;--hls-panel-bg:Canvas;--hls-field-height:36px;--hls-field-width:360px;--hls-font-size:14px;--hls-small:13px}'
            . '.hls.hls{margin:0 0 20px;font:inherit;color:inherit}'
            . '.hls.hls .hls-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px}'
            . '.hls.hls .hls-field--text{position:relative;margin:0}'
            . '.hls.hls .hls-filters{display:contents}'
            . '.hls.hls .hls-filter{position:relative}'
            . '.hls.hls .hls-filter-toggle{display:flex;align-items:center;gap:6px;cursor:pointer;list-style:none;white-space:nowrap}'
            . '.hls.hls .hls-filter-toggle::-webkit-details-marker{display:none}'
            . '.hls.hls .hls-filter-panel{position:absolute;z-index:50;top:calc(100% + 6px);left:0;min-width:200px;max-width:min(320px,90vw);max-height:300px;overflow:auto;padding:6px;background:var(--hls-panel-bg);border:1px solid var(--hls-border);border-radius:var(--hls-radius);box-shadow:0 10px 28px rgba(0,0,0,.12)}'
            . '.hls.hls .hls-option{display:flex;width:auto;margin:0;font-weight:inherit;line-height:1.3;text-transform:none;letter-spacing:normal;align-items:center;gap:8px;min-height:34px;padding:0 8px 0 calc(8px + var(--hls-depth,0) * 14px);cursor:pointer;font-size:var(--hls-small)}'
            . '.hls.hls .hls-option input{appearance:auto;-webkit-appearance:auto;flex:none;width:15px;height:15px;margin:0;padding:0;border:0;box-shadow:none}'
            . '.hls.hls .hls-filter-toggle{margin:0;line-height:1.2;font-weight:inherit;background:none}'
            . '.hls.hls .hls-option-label{flex:1}.hls.hls .hls-count{opacity:.6;font-size:12px}'
            . '.hls.hls .hls-clear[hidden],.hls.hls .hls-status[hidden]{display:none}'
            . '.hls.hls .hls-visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}'
            . '.hls.hls .hls-label{display:none}'
            . '.hexa-listing-results{transition:opacity .2s}.hexa-listing-results.hls-loading{opacity:.45;pointer-events:none}'
            . '@media(max-width:767px){.hls.hls .hls-field--text{flex-basis:100%}}'
            . '@media(prefers-reduced-motion:reduce){.hexa-listing-results{transition:none}}';

        if ( 'default-1' === $style ) {
            $css .= '.hls.hls.hls--default-1 .hls-field--text{flex:0 1 var(--hls-field-width)}'
                . '.hls.hls.hls--default-1 .hls-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);opacity:.55;pointer-events:none}'
                . '.hls.hls.hls--default-1 .hls-input{box-sizing:border-box;width:100%;height:var(--hls-field-height);margin:0;padding:0 12px 0 34px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);background:transparent;color:inherit;font:inherit;font-size:var(--hls-font-size);box-shadow:none}'
                . '.hls.hls.hls--default-1 .hls-input:focus{outline:none;border-color:var(--hls-accent)}'
                . '.hls.hls.hls--default-1 .hls-filter-toggle{height:var(--hls-field-height);padding:0 12px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);font-size:var(--hls-small);transition:border-color .2s}'
                . '.hls.hls.hls--default-1 .hls-filter[open] .hls-filter-toggle,.hls.hls.hls--default-1 .hls-filter.is-active .hls-filter-toggle,.hls.hls.hls--default-1 .hls-filter-toggle:hover{border-color:var(--hls-accent)}'
                . '.hls.hls.hls--default-1 .hls-filter-toggle::after{content:"";width:5px;height:5px;border-right:1.5px solid currentColor;border-bottom:1.5px solid currentColor;transform:translateY(-2px) rotate(45deg);opacity:.55}'
                . '.hls.hls.hls--default-1 .hls-filter-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border:1px solid currentColor;border-radius:9px;background:none;color:inherit;font-size:11px;font-weight:600;line-height:1}'
                . '.hls.hls.hls--default-1 .hls-select{height:var(--hls-field-height);padding:0 10px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);background:transparent;color:inherit;font:inherit;font-size:var(--hls-small)}'
                . '.hls.hls.hls--default-1 .hls-clear{font-size:var(--hls-small);color:inherit;opacity:.7;text-decoration:underline;text-underline-offset:3px}'
                . '.hls.hls.hls--default-1 .hls-status{margin:10px 0 0;font-size:var(--hls-small);opacity:.7}'
                . '.hls.hls.hls--default-1 .hls-option:hover{background:rgba(127,127,127,.1);border-radius:4px}'
                . '.hls.hls.hls--default-1 .hls-option input{width:15px;height:15px;margin:0;accent-color:var(--hls-accent)}';
        } elseif ( 'default-2' === $style ) {
            $css .= '.hls.hls.hls--default-2 .hls-bar{gap:18px}'
                . '.hls.hls.hls--default-2 .hls-field--text{flex:0 1 var(--hls-field-width)}'
                . '.hls.hls.hls--default-2 .hls-icon{position:absolute;left:0;top:50%;transform:translateY(-50%);opacity:.6;pointer-events:none}'
                . '.hls.hls.hls--default-2 .hls-input{box-sizing:border-box;width:100%;height:var(--hls-field-height);margin:0;padding:0 0 0 26px;border:0;border-bottom:1px solid var(--hls-border);border-radius:0;background:transparent;color:inherit;font:inherit;font-size:var(--hls-font-size);box-shadow:none}'
                . '.hls.hls.hls--default-2 .hls-input:focus{outline:none;border-bottom-color:var(--hls-accent)}'
                . '.hls.hls.hls--default-2 .hls-filter-toggle{height:var(--hls-field-height);font-size:12px;letter-spacing:.08em;text-transform:uppercase}'
                . '.hls.hls.hls--default-2 .hls-filter-toggle::after{content:"+";opacity:.6}'
                . '.hls.hls.hls--default-2 .hls-filter[open] .hls-filter-toggle::after{content:"−"}'
                . '.hls.hls.hls--default-2 .hls-filter.is-active .hls-filter-label{text-decoration:underline;text-underline-offset:4px}'
                . '.hls.hls.hls--default-2 .hls-filter-badge{font-size:11px;opacity:.7}'
                . '.hls.hls.hls--default-2 .hls-filter-badge::before{content:"("}.hls.hls.hls--default-2 .hls-filter-badge::after{content:")"}'
                . '.hls.hls.hls--default-2 .hls-select{height:var(--hls-field-height);border:0;border-bottom:1px solid var(--hls-border);background:transparent;color:inherit;font:inherit;font-size:12px}'
                . '.hls.hls.hls--default-2 .hls-clear{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:inherit;opacity:.7}'
                . '.hls.hls.hls--default-2 .hls-status{margin:10px 0 0;font-size:12px;letter-spacing:.06em;text-transform:uppercase;opacity:.6}'
                . '.hls.hls.hls--default-2 .hls-option input{width:14px;height:14px;margin:0;accent-color:var(--hls-accent)}';
        } else {
            $css .= '.hls.hls.hls--none .hls-icon{display:none}';
        }

        return $css;
    }

    public static function js(): string {
        return <<<'JS'
(function(){
if(window.hexaListingSearch)return;window.hexaListingSearch=1;
function init(form){
if(form.getAttribute('data-hls-ready'))return;form.setAttribute('data-hls-ready','1');
if(!window.fetch||!window.DOMParser||!window.URLSearchParams)return;
var sel=form.getAttribute('data-hls-target')||'.hexa-listing-results',min=parseInt(form.getAttribute('data-hls-min')||'2',10);
var text=form.querySelector('.hls-input'),timer=null,controller=null,seq=0,last=location.pathname+location.search;
function url(){var p=new URLSearchParams(),on=false;Array.prototype.forEach.call(form.elements,function(el){if(!el.name||el.disabled||el.type==='submit')return;if((el.type==='checkbox'||el.type==='radio')&&!el.checked)return;var v=(el.value||'').trim();if(v!==''||el.name==='s'){p.append(el.name,v);if(el.type!=='hidden'&&v!=='')on=true;}});if(!on)p.delete('hls');var a=form.getAttribute('action')||location.pathname,q=p.toString();return a+(q?(a.indexOf('?')>-1?'&':'?')+q:'');}
function busy(on){form.setAttribute('aria-busy',on?'true':'false');Array.prototype.forEach.call(document.querySelectorAll(sel),function(t){t.classList.toggle('hls-loading',on);});}
function restart(el){if(!window.jQuery||!window.elementorFrontend||!elementorFrontend.elementsHandler)return;var $=window.jQuery;$(el).find('.elementor-element').addBack('.elementor-element').each(function(){try{elementorFrontend.elementsHandler.runReadyTrigger($(this));}catch(e){}});}
function go(){var u=url();if(u===last)return;last=u;var id=++seq;if(controller)controller.abort();controller=window.AbortController?new AbortController():null;busy(true);
var current=document.querySelectorAll(sel);if(!current.length){location.assign(u);return;}
fetch(u,{credentials:'same-origin',headers:{'Accept':'text/html'},signal:controller?controller.signal:undefined}).then(function(r){if(!r.ok&&r.status!==404)throw new Error('HTTP '+r.status);return r.text();}).then(function(html){if(id!==seq)return;var doc=new DOMParser().parseFromString(html,'text/html');
var fresh=doc.querySelectorAll(sel);Array.prototype.forEach.call(current,function(t,i){var n=fresh[i];if(n){var m=document.importNode(n,true);t.parentNode.replaceChild(m,t);restart(m);}else{t.innerHTML='';}});
var nf=doc.getElementById(form.id);if(nf){var box=form.querySelector('.hls-filters'),nb=nf.querySelector('.hls-filters');if(box&&nb){var open=[];Array.prototype.forEach.call(box.querySelectorAll('details[open]'),function(d){open.push(d.getAttribute('data-hls-key'));});box.innerHTML=nb.innerHTML;Array.prototype.forEach.call(box.querySelectorAll('details'),function(d){if(open.indexOf(d.getAttribute('data-hls-key'))>-1)d.open=true;});}
['.hls-status','.hls-clear'].forEach(function(s){var a=form.querySelector(s),b=nf.querySelector(s);if(a&&b){a.textContent=b.textContent;if(b.hasAttribute('hidden'))a.setAttribute('hidden','');else a.removeAttribute('hidden');}});}
if(window.history&&history.replaceState)history.replaceState(history.state,'',u);
document.dispatchEvent(new CustomEvent('hexa-listing:updated',{detail:{form:form,url:u}}));
}).catch(function(e){if(e&&e.name==='AbortError')return;if(id!==seq)return;last=null;var s=form.querySelector('.hls-status');if(s){s.textContent=form.getAttribute('data-hls-error')||'';s.removeAttribute('hidden');}}).then(function(){if(id===seq)busy(false);});}
form.addEventListener('submit',function(e){e.preventDefault();window.clearTimeout(timer);go();});
if(text){text.addEventListener('input',function(){window.clearTimeout(timer);var v=text.value.trim();if(v&&v.length<min)return;timer=window.setTimeout(go,350);});}
form.addEventListener('change',function(e){if(e.target===text)return;window.clearTimeout(timer);go();});
var clear=form.querySelector('.hls-clear');if(clear){clear.addEventListener('click',function(e){e.preventDefault();if(text)text.value='';Array.prototype.forEach.call(form.querySelectorAll('.hls-filters input,.hls-filters select'),function(el){if(el.type==='checkbox')el.checked=false;else el.value='';});Array.prototype.forEach.call(form.querySelectorAll('details[open]'),function(d){d.open=false;});go();});}
document.addEventListener('click',function(e){Array.prototype.forEach.call(form.querySelectorAll('details[open]'),function(d){if(!d.contains(e.target))d.open=false;});});
form.addEventListener('toggle',function(e){var d=e.target;if(!d||d.tagName!=='DETAILS'||!d.open)return;var p=d.querySelector('.hls-filter-panel');if(!p)return;p.style.left='';p.style.right='';if(p.getBoundingClientRect().right>document.documentElement.clientWidth-8){p.style.left='auto';p.style.right='0';}},true);
form.addEventListener('keydown',function(e){if(e.key==='Escape'){var d=e.target.closest?e.target.closest('details'):null;if(d){d.open=false;var s=d.querySelector('summary');if(s)s.focus();}}});
}
function boot(){Array.prototype.forEach.call(document.querySelectorAll('form.hls'),init);}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',boot);}else{boot();}
})();
JS;
    }
}
