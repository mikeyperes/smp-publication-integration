<?php
namespace smp_publication_integration\Admin\Dashboard\Tabs;

use Hexa\PluginCore\BrandColors\BrandColorProvider;
use smp_publication_integration\Admin\CodeViews;
use Hexa\PluginCore\ActivityLog\ActivityLogConfig;
use Hexa\PluginCore\ActivityLog\ActivityLogRenderer;
use Hexa\PluginCore\PluginChecks\PluginInventoryRenderer;
use Hexa\PluginCore\SmartSearch\SmartSearchRenderer;
use Hexa\PluginCore\FieldStructures\FieldStructureRenderer;
use Hexa\PluginCore\FieldStructures\AcfFieldGroupRenderer;
use Hexa\PluginCore\ContentTypes\ContentTypeRenderer;
use Hexa\PluginCore\Taxonomies\TaxonomyRenderer;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;
use Hexa\PluginCore\SnippetRegistry\SnippetRegistry;
use Hexa\PluginCore\SnippetRegistry\SnippetsTableRenderer;
use Hexa\PluginCore\WpAdminTabs\HostTabsRenderer;
use Hexa\PluginCore\PluginUpdates\PluginUpdateStatus;
use Hexa\PluginCore\CorePackageUpdates\CorePackageStatus;
use Hexa\PluginCore\WpAdminTabs\TabDefinition;
use Hexa\PluginCore\WpAdminTabs\TabRegistry;
use Hexa\PluginCore\WpAdminComponents\ColorControl;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\TypographyControl;
use Hexa\PluginCore\WpAdminComponents\TemplateColorControl;
use Hexa\PluginCore\WpAdminComponents\ScopedCssOverride;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;
use Hexa\PluginCore\Typography\TypographyPreservation;
use Hexa\PluginCore\Typography\TemplateTypography;
use Hexa\PluginCore\SiteStructure\SiteStructureRenderer;
use Hexa\PluginCore\SchemaDetection\SchemaPageScanner;
use Hexa\PluginCore\SchemaDetection\SchemaScanRenderer;
use smp_publication_integration\Admin\Ajax;
use smp_publication_integration\Admin\QuickStartCleanupWorkflow;

use smp_publication_integration\Admin\Navigation\AdminNavigation;
use smp_publication_integration\Config;
use smp_publication_integration\Content\AcfFields;
use smp_publication_integration\Content\AuthorSocialIcons;
use smp_publication_integration\Content\AuthorArchiveLoading;
use smp_publication_integration\Content\MuckRackVerification;
use smp_publication_integration\Content\MultiAuthors;
use smp_publication_integration\Content\PublicationContentTypes;
use smp_publication_integration\Content\Schema;
use smp_publication_integration\Design\TemplateBackground;
use smp_publication_integration\Design\TemplateDesignRegistry;
use smp_publication_integration\Support\Dependencies;
use smp_publication_integration\Support\ArticleCleanup;
use smp_publication_integration\Support\Fields;
use smp_publication_integration\Support\PageStructure;
use smp_publication_integration\Support\PluginInventory;
use smp_publication_integration\Support\PluginRegistry;
use smp_publication_integration\Support\QuickStartFeatures;
use smp_publication_integration\Support\Settings;
use smp_publication_integration\Support\SnippetDefinitions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Shortcodes tab: searchable catalog with live values. */
trait ShortcodesTab {


    private function shortcodes(): void {
        $user_id = $this->default_shortcode_user_id();
        $recent = get_posts( [ "post_type" => "post", "post_status" => "publish", "numberposts" => 1, "fields" => "ids" ] );
        $post_id = ! empty( $recent ) ? (int) $recent[0] : 0;
        ?>
        <div class="smpi-panel smpi-sc">
            <style>
            .smpi-sc{max-width:1020px}
            .smpi-sc h2{margin:0 0 4px}
            .smpi-sc-intro{margin:0 0 14px;color:#667085;font-size:13px;line-height:1.55;max-width:80ch}
            .smpi-sc-top{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 18px;padding:9px 11px;background:#fff;border:1px solid #e3e5e9;border-radius:11px;box-shadow:0 4px 14px rgba(16,24,40,.06);position:sticky;top:34px;z-index:30}
            .smpi-sc-search{position:relative}
            .smpi-sc-search--filter{flex:1 1 300px;min-width:230px}
            .smpi-sc-search--author,.smpi-sc-search--post{flex:0 1 210px;min-width:160px}
            .smpi-sc-top .hpc-smart-search{margin:0}
            .smpi-sc-top .hpc-field{margin:0;display:block}
            .smpi-sc-top .hpc-field>span{display:none}
            .smpi-sc-top .hpc-smart-search-input{width:100%;box-sizing:border-box;font-size:13px;padding:8px 12px;border:1px solid #cfd3da;border-radius:8px;background:#fff;margin:0;line-height:1.3}
            .smpi-sc-top .hpc-smart-search-input:focus{border-color:#2563eb;outline:none;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
            .smpi-sc-top .hpc-smart-search-status{display:none}
            .smpi-sc-top .hpc-smart-search-selected{display:none !important}
            .smpi-sc-top .hpc-smart-search-results{position:absolute;top:100%;left:0;right:0;z-index:40;background:#fff;border:1px solid #dcdfe4;border-radius:9px;box-shadow:0 12px 30px rgba(16,24,40,.16);margin-top:5px;max-height:330px;overflow:auto;padding:4px}
            .smpi-sc-top .hpc-smart-search-result{display:block;width:100%;text-align:left;border:0;background:none;border-radius:7px;padding:6px 10px;cursor:pointer;font-size:12.5px;height:auto;line-height:1.4}
            .smpi-sc-top .hpc-smart-search-result.active,.smpi-sc-top .hpc-smart-search-result:hover{background:#eff4ff}
            .smpi-sc-top .hpc-smart-search-result strong{display:block;color:#1f2937;font-weight:600;font-family:Menlo,Consolas,monospace;font-size:12px}
            .smpi-sc-top .hpc-smart-search-result span{display:block;color:#98a2b3;font-size:11px}
            .smpi-sc-top .hpc-smart-search-result em{display:none}
            .smpi-sc-spin{float:none;margin:0}
            .smpi-sc-card{border:1px solid #e3e5e9;border-radius:14px;background:#fff;margin:0 0 16px;overflow:hidden;box-shadow:0 1px 3px rgba(16,24,40,.04)}
            .smpi-sc-card>summary.smpi-sc-card-head{list-style:none;cursor:pointer;display:flex;align-items:center;gap:10px;padding:13px 18px;background:#f8f9fb;user-select:none}
            .smpi-sc-card[open]>summary.smpi-sc-card-head{border-bottom:1px solid #e7e9ee}
            .smpi-sc-card>summary::-webkit-details-marker{display:none}
            .smpi-sc-card>summary:hover{background:#f2f4f7}
            .smpi-sc-chev{width:8px;height:8px;border-right:2px solid #98a2b3;border-bottom:2px solid #98a2b3;transform:rotate(-45deg);transition:transform .15s;flex:0 0 auto}
            .smpi-sc-card[open]>summary .smpi-sc-chev{transform:rotate(45deg)}
            .smpi-sc-card-title{font-size:14px;font-weight:700;color:#101828}
            .smpi-sc-card-body{padding:0}
            .smpi-sc-card-blurb{margin:0;padding:11px 18px 0;font-size:12px;color:#667085;line-height:1.5;max-width:84ch}
            .smpi-sc-card-meta{margin:5px 0 0;padding:0 18px;font-size:10.5px;color:#98a2b3}
            .smpi-sc-card-meta code{background:#eef0f3;border-radius:4px;padding:1px 6px}
            .smpi-sc-count{font-size:11px;font-weight:700;color:#667085;background:#eceef1;border-radius:999px;padding:2px 9px}
            .smpi-sc-layer{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;border-radius:6px;padding:3px 9px;white-space:nowrap}
            .smpi-sc-layer--author{background:#dbeafe;color:#1e40af}
            .smpi-sc-layer--post{background:#dcfce7;color:#15803d}
            .smpi-sc-layer--publication{background:#ede9fe;color:#6d28d9}
            .smpi-sc-layer--external{background:#e5e7eb;color:#475467}
            .smpi-sc-rows{padding:6px 18px 16px}
            .smpi-sc-row{padding:14px 0}
            .smpi-sc-row + .smpi-sc-row{border-top:1px solid #eef0f3}
            .smpi-sc-desc{margin:0 0 10px;font-size:14px;color:#1f2937;line-height:1.5}
            .smpi-sc-use{color:#dc2626;font-weight:600;font-size:13px}
            .smpi-sc-block{background:#fafbfc;border:1px solid #eaecf0;border-radius:10px;padding:12px 14px}
            .smpi-sc-line{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
            .smpi-sc-tag{font-family:Menlo,Consolas,monospace;font-size:13px;font-weight:700;color:#334155;background:#fff;border:1px solid #d5d9e0;border-radius:7px;padding:3px 10px;white-space:nowrap}
            .smpi-sc-row.is-deprecated .smpi-sc-tag{color:#b42318;background:#fef3f2;border-color:#fdab9e}
            .smpi-sc-dep{font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#fff;background:#dc2626;border-radius:5px;padding:3px 8px;white-space:nowrap}
            .smpi-sc-copy{font-size:11px;font-weight:600;color:#1d4ed8;background:#fff;border:1px solid #c7d2e4;border-radius:6px;padding:3px 10px;cursor:pointer;min-width:52px;text-align:center}
            .smpi-sc-copy:hover{background:#eff4ff;border-color:#1d4ed8}
            .smpi-sc-copy.is-ok{color:#15803d;border-color:#86c69b;background:#f0fdf4}
            .smpi-sc-copy.is-err{color:#dc2626;border-color:#f3b4ae;background:#fef2f2}
            .smpi-sc-actions{display:flex;align-items:center;gap:14px;margin-left:auto}
            .smpi-sc-edit,.smpi-sc-det{font-size:11px;color:#98a2b3;text-decoration:none;background:none;border:0;cursor:pointer;padding:0}
            .smpi-sc-edit:hover,.smpi-sc-det:hover{color:#1d4ed8}
            .smpi-sc-k{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:#98a2b3;margin-right:9px;font-family:-apple-system,sans-serif}
            .smpi-sc-attrs{margin:10px 0 0;font-size:11.5px;color:#475467;font-family:Menlo,Consolas,monospace}
            .smpi-sc-out{margin:9px 0 0;font-size:10.5px;color:#667085;line-height:1.5;word-break:break-word}
            .smpi-sc-out code,.smpi-sc-vout code{background:#fff;border:1px solid #eaecf0;border-radius:4px;padding:1px 5px;color:#3c434a}
            .smpi-sc-vars{margin:10px 0 0;border-top:1px solid #eaecf0;padding-top:8px}
            .smpi-sc-var{display:flex;align-items:center;gap:9px;flex-wrap:wrap;padding:4px 0}
            .smpi-sc-vtag{font-family:Menlo,Consolas,monospace;font-size:11px;font-weight:600;color:#475467;white-space:nowrap;flex:0 0 auto;min-width:165px}
            .smpi-sc-vlabel{font-size:11px;color:#98a2b3;flex:0 0 auto;min-width:130px}
            .smpi-sc-vout{font-size:11px;color:#475467;flex:1 1 150px;word-break:break-word}
            .smpi-sc-copy--mini{min-width:0;font-size:10px;padding:1px 8px}
            .smpi-sc-vars-line{margin:10px 0 0;font-size:11px;color:#98a2b3;line-height:2}
            .smpi-sc-vchip{font-family:Menlo,Consolas,monospace;font-size:10.5px;color:#98a2b3;cursor:pointer;margin-right:14px;border-bottom:1px dashed #d0d5dd}
            .smpi-sc-vchip:hover{color:#1d4ed8;border-bottom-color:#1d4ed8}
            .smpi-sc-alias{margin:10px 0 0;font-size:10.5px;color:#98a2b3;font-family:Menlo,Consolas,monospace;word-break:break-word}
            .smpi-sc-detail{display:none;margin:10px 0 0;padding:11px 13px;background:#f8f9fb;border:1px solid #edeff2;border-radius:9px;font-size:11px;color:#5a616b}
            .smpi-sc-row.is-open .smpi-sc-detail{display:block}
            .smpi-sc-detail .r{margin:0 0 5px;word-break:break-word}
            .smpi-sc-detail .r:last-child{margin-bottom:0}
            .smpi-sc-detail b{color:#344054;font-weight:600}
            .smpi-sc-detail code{background:#eceef1;border-radius:4px;padding:1px 5px}
            .smpi-sc-detail a{color:#1d4ed8}.smpi-sc-req{margin:0 0 10px;font-size:12px;color:#9a3412;background:#fff7ed;border:1px solid #fdd9af;border-radius:8px;padding:6px 11px;line-height:1.45}.smpi-sc-card-req{margin:8px 18px 0;font-size:11.5px;color:#9a3412;background:#fff7ed;border:1px solid #fdd9af;border-radius:8px;padding:6px 11px;line-height:1.45}.smpi-sc-req-badge{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#fff;background:#ea801c;border-radius:4px;padding:2px 6px;margin-right:8px}.smpi-sc-req-state{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;border-radius:4px;padding:1px 7px}.smpi-sc-req-state.is-on{background:#dcfce7;color:#15803d}.smpi-sc-req-state.is-off{background:#fee2e2;color:#b42318}.smpi-sc-attrs-card{margin:10px 0 0;border:1px solid #e6e9ed;border-radius:9px;background:#fff;overflow:hidden}.smpi-sc-attrs-head{list-style:none;cursor:pointer;display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f8f9fb;font-size:11.5px;font-weight:700;color:#475467;user-select:none}.smpi-sc-attrs-card>summary::-webkit-details-marker{display:none}.smpi-sc-attrs-head:hover{background:#f2f4f7}.smpi-sc-chev2{width:7px;height:7px;border-right:2px solid #98a2b3;border-bottom:2px solid #98a2b3;transform:rotate(-45deg);transition:transform .15s;flex:0 0 auto}.smpi-sc-attrs-card[open]>summary .smpi-sc-chev2{transform:rotate(45deg)}.smpi-sc-attrs-count{font-size:10px;font-weight:700;color:#667085;background:#eceef1;border-radius:999px;padding:1px 8px;margin-left:auto}.smpi-sc-attrs-body{padding:2px 0 6px}.smpi-sc-attr{display:flex;align-items:center;gap:9px;flex-wrap:wrap;padding:5px 12px;border-top:1px solid #f2f4f7}.smpi-sc-attr-tag{font-family:Menlo,Consolas,monospace;font-size:10.5px;font-weight:600;color:#475467;white-space:nowrap;flex:0 0 auto;min-width:175px}.smpi-sc-attr-label{font-size:11px;color:#98a2b3;flex:0 0 auto;min-width:150px}.smpi-sc-attr-out{font-size:10.5px;color:#475467;flex:1 1 140px;word-break:break-word}.smpi-sc-attr-out code{background:#f6f7f9;border:1px solid #eaecf0;border-radius:4px;padding:1px 5px}
            .smpi-sc-noresults{display:none;padding:16px;color:#98a2b3}
            </style>
            <h2>Shortcodes</h2>
            <p class="smpi-sc-intro">Search a shortcode, or set a preview author and sample post to see live output. Open a card to view its shortcodes.</p>
            <div class="smpi-sc-top">
                <div class="smpi-sc-search smpi-sc-search--filter"><?php ( new SmartSearchRenderer() )->render( [ "id" => "smpi-sc-filter-search", "source" => "smpi_shortcodes", "min_chars" => 2, "limit" => 12, "label" => "Search shortcodes", "placeholder" => "Search shortcodes by tag or description..." ] ); ?></div>
                <div class="smpi-sc-search smpi-sc-search--author"><?php ( new SmartSearchRenderer() )->render( [ "id" => "smpi-sc-author-search", "source" => "users", "min_chars" => 2, "label" => "Preview author", "placeholder" => "Preview author..." ] ); ?></div>
                <div class="smpi-sc-search smpi-sc-search--post"><?php ( new SmartSearchRenderer() )->render( [ "id" => "smpi-sc-post-search", "source" => "posts", "post_type" => "post", "min_chars" => 2, "label" => "Sample post", "placeholder" => "Sample post..." ] ); ?></div>
                <span class="spinner smpi-sc-spin"></span>
            </div>
            <div id="smpi-shortcode-user-values" data-user="<?php echo esc_attr( (string) $user_id ); ?>" data-post="<?php echo esc_attr( (string) $post_id ); ?>"><?php echo self::shortcode_user_values_html( $user_id, $post_id ); ?></div>
            <p class="smpi-sc-noresults">No shortcodes match that search.</p>
        </div>
        <script>
        (function(){
            if (window.smpiScBound) return; window.smpiScBound = true;
            var $ = window.jQuery; if (!$) return;
            function filterValue(){ var el = document.querySelector("#smpi-sc-filter-search .hpc-smart-search-input"); return el ? (el.value || "") : ""; }
            function applyFilter(q){
                q = (q || "").toLowerCase().trim(); var total = 0;
                $(".smpi-sc-card").each(function(){
                    var vis = 0, card = this;
                    $(this).find(".smpi-sc-row").each(function(){
                        var ok = !q || (this.getAttribute("data-filter") || "").indexOf(q) !== -1;
                        this.style.display = ok ? "" : "none"; if (ok) vis++;
                    });
                    card.style.display = vis ? "" : "none";
                    if (q && vis) { card.setAttribute("open", ""); }
                    total += vis;
                });
                $(".smpi-sc-noresults").css("display", total ? "none" : "block");
            }
            function refresh(){
                var box = $("#smpi-shortcode-user-values");
                var uid = box.attr("data-user") || 0, pid = box.attr("data-post") || 0;
                $(".smpi-sc-spin").addClass("is-active");
                $.post(smpiAdmin.ajaxUrl, { action: "smpi_shortcode_user_preview", nonce: smpiAdmin.nonce, user_id: uid, post_id: pid })
                    .done(function(x){ if (x && x.success) { box.html(x.data.html || ""); if(window.hexaPluginCoreInitPersistentDetails)window.hexaPluginCoreInitPersistentDetails(box.get(0)); applyFilter(filterValue()); } })
                    .always(function(){ $(".smpi-sc-spin").removeClass("is-active"); });
            }
            function copyText(v, b){
                var done = function(ok){ b.classList.remove("is-ok","is-err"); b.classList.add(ok ? "is-ok" : "is-err"); b.textContent = ok ? "✓" : "✗"; setTimeout(function(){ b.classList.remove("is-ok","is-err"); b.textContent = "Copy"; }, 1100); };
                if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(v).then(function(){ done(true); }, function(){ done(false); }); }
                else { try { var ta = document.createElement("textarea"); ta.value = v; document.body.appendChild(ta); ta.select(); var ok = document.execCommand("copy"); document.body.removeChild(ta); done(ok); } catch (e) { done(false); } }
            }
            document.addEventListener("hexa-search-selected", function(ev){
                var d = ev.detail || {}, cid = d.component_id, item = d.item || {};
                var box = document.getElementById("smpi-shortcode-user-values");
                if (cid === "smpi-sc-author-search") { box.setAttribute("data-user", item.id || item.value || 0); refresh(); }
                else if (cid === "smpi-sc-post-search") { box.setAttribute("data-post", item.id || item.value || 0); refresh(); }
                else if (cid === "smpi-sc-filter-search") { applyFilter(String(item.value || "")); }
            });
            $(document).on("input", "#smpi-sc-filter-search .hpc-smart-search-input", function(){ applyFilter(this.value); });
            $(document).on("click", ".smpi-sc-det", function(){ $(this).closest(".smpi-sc-row").toggleClass("is-open"); });
            $(document).on("click", ".smpi-sc-copy", function(e){ e.preventDefault(); e.stopPropagation(); copyText(this.getAttribute("data-copy") || "", this); });
            $(document).on("click", ".smpi-sc-vchip", function(){ var v = this.getAttribute("data-copy") || this.textContent; if (navigator.clipboard) navigator.clipboard.writeText(v); var c = this, o = c.style.color; c.style.color = "#15803d"; setTimeout(function(){ c.style.color = o; }, 700); });
        })();
        </script>
        <?php
    }

    private function default_shortcode_user_id(): int {
        $configured = absint( Settings::get( "system_publication_user_id", 0 ) );
        if ( $configured && get_user_by( "id", $configured ) ) {
            return $configured;
        }
        $users = get_users( [ "number" => 1, "orderby" => "post_count", "order" => "DESC", "who" => "authors", "fields" => [ "ID" ] ] );
        return ! empty( $users[0]->ID ) ? (int) $users[0]->ID : 0;
    }

    public static function shortcode_selected_user_html( int $user_id ): string {
        $user = $user_id ? get_user_by( "id", $user_id ) : false;
        if ( ! $user ) {
            return "<div class=\"smpi-empty-state\"><strong>No author selected.</strong><p>Search above to load shortcode values for a WordPress author.</p></div>";
        }
        $avatar = get_avatar_url( $user_id, [ "size" => 96 ] );
        $edit = get_edit_user_link( $user_id );
        $view = get_author_posts_url( $user_id );
        ob_start();
        ?>
        <div class="smpi-profile-card smpi-shortcode-selected-user">
            <div class="smpi-profile-avatar"><img src="<?php echo esc_url( $avatar ); ?>" alt=""></div>
            <div class="smpi-profile-info">
                <h3><?php echo esc_html( $user->display_name ); ?> <code>#<?php echo esc_html( (string) $user_id ); ?></code></h3>
                <p><span class="dashicons dashicons-email"></span> <?php echo esc_html( $user->user_email ); ?></p>
                <p><a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $edit ); ?>">Edit User</a> <a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $view ); ?>">View Author Page</a></p>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function shortcode_catalog(): array {
        $A = "src/Content/AuthorShortcodes.php"; $AS = "src/Content/AuthorSocialIcons.php"; $S = "src/Content/Shortcodes.php"; $M = "src/Content/MultiAuthors.php"; $MR = "src/Content/MuckRackVerification.php"; $TOC = "src/Content/TableOfContents.php"; $BC = "src/Content/EstimatedReadTime.php"; $RT = "src/Content/EstimatedReadTime.php"; $PT = "src/Content/PostTime.php";
        $authDetail = function( $source, $file, $type ) use ( $A ) { return [ "field" => "user meta", "group" => "WP user profile", "source" => $source, "file" => $file, "plugin" => "SMP Publication Integration", "type" => $type, "edit" => "author" ]; };
        return [
            [ "key" => "author-identity", "title" => "Author identity & bio", "layer" => "Author layer", "live" => "author",
              "blurb" => "Identity fields for the post author: name, role, bio, photo, email. Read from the author WP user profile (ACF or user meta, with a WP user fallback).",
              "register_file" => $A, "access" => "Single-post author box and author archive header.",
              "items" => [
                [ "tag" => "author_name", "desc" => "The display name of the post author.", "type" => "Text", "params" => "user_id", "detail" => $authDetail( "WP user display_name", $A, "Text" ) ],
                [ "tag" => "author_title", "desc" => "The author role or job title (for example Staff Writer).", "type" => "Text", "params" => "user_id", "detail" => $authDetail( "ACF / user meta: title, role, job_title, position", $A, "Text" ) ],
                [ "tag" => "author_subtitle", "desc" => "The author tagline or headline shown under the name.", "type" => "Text", "params" => "user_id", "detail" => $authDetail( "ACF / user meta: subtitle, tagline, headline", $A, "Text" ) ],
                [ "tag" => "author_bio", "desc" => "The author full biography. Rich-text HTML by default, or plain text with format=text.", "type" => "HTML", "params" => "format (html|text), user_id", "variations" => [ [ "code" => "[author_bio format=\"text\"]", "label" => "Plain-text bio" ] ], "detail" => $authDetail( "ACF / user meta: biography, bio, description", $A, "HTML" ) ],
                [ "tag" => "author_bio_short", "desc" => "A shortened author bio, trimmed to a word count (default 35 words).", "type" => "Text", "params" => "words (default 35), user_id", "variations" => [ [ "code" => "[author_bio_short words=\"20\"]", "label" => "20-word bio" ] ], "detail" => $authDetail( "ACF / user meta: bio_short, short_bio, description_short", $A, "Text" ) ],
                [ "tag" => "author_image", "desc" => "The author photo as an img tag, or the bare image URL with output=url.", "type" => "Image", "params" => "size (thumbnail|medium|medium_large|large|full), output (html|url), class, loading (lazy|eager), sizes (optional display widths), user_id", "variations" => [ [ "code" => "[author_image output=\"url\"]", "label" => "Bare image URL" ], [ "code" => "[author_image size=\"large\"]", "label" => "Large size img" ] ], "detail" => $authDetail( "ACF / user meta: profile_photo, headshot, photo; WP avatar fallback", $A, "Image" ) ],
                [ "tag" => "author_email", "desc" => "The author email address.", "type" => "Text", "params" => "user_id", "detail" => $authDetail( "WP user user_email", $A, "Text" ) ],
              ] ],
            [ "key" => "author-social", "title" => "Author social links", "layer" => "Author layer", "live" => "author",
              "blurb" => "The author social profile URLs. Canonical form is [author url=\"network\"]; the per-network tags like [author_x] are working aliases of it. Output is a plain URL.",
              "register_file" => $A, "access" => "Single-post author box and author archive social row.",
              "items" => [
                [ "tag" => "smp_author_social_icons", "desc" => "A complete accessible author-social icon group. Uses the enabled networks and selected template by default, and omits every network whose author profile value is empty.", "type" => "HTML block", "params" => "user_id, post_id, author_index, style, size, color, networks, class, label, labels (short shows X instead of X (Twitter))",
                  "variations" => [
                    [ "code" => "[smp_author_social_icons]", "label" => "Saved backend design" ],
                    [ "code" => "[smp_author_social_icons style=\"unstyled\"]", "label" => "No Style for Elementor CSS" ],
                    [ "code" => "[smp_author_social_icons style=\"social-pills\" size=\"20\"]", "label" => "Labeled pills at 20px" ],
                    [ "code" => "[smp_author_social_icons networks=\"linkedin,x,website\"]", "label" => "Selected networks only" ],
                  ],
                  "detail" => $authDetail( "Resolved author social fields; empty URLs are excluded before markup is created", $AS, "HTML" ) ],
                [ "tag" => "author", "code" => "[author url=\"x\"]", "desc" => "The author profile URL for the chosen social network. Output is a plain URL you can drop into an href.", "type" => "URL", "params" => "url (x|linkedin|facebook|instagram|youtube|website|crunchbase|muckrack), user_id", "variations_live" => true,
                  "variations" => [
                    [ "code" => "[author url=\"x\"]", "label" => "X (Twitter) profile URL" ],
                    [ "code" => "[author url=\"linkedin\"]", "label" => "LinkedIn profile URL" ],
                    [ "code" => "[author url=\"facebook\"]", "label" => "Facebook profile URL" ],
                    [ "code" => "[author url=\"instagram\"]", "label" => "Instagram profile URL" ],
                    [ "code" => "[author url=\"youtube\"]", "label" => "YouTube channel URL" ],
                    [ "code" => "[author url=\"website\"]", "label" => "Personal website URL" ],
                    [ "code" => "[author url=\"crunchbase\"]", "label" => "Crunchbase profile URL" ],
                    [ "code" => "[author url=\"muckrack\"]", "label" => "Muck Rack profile URL" ],
                  ],
                  "aliases" => [ "[author_x] = [author url=\"x\"]", "[author_linkedin] = [author url=\"linkedin\"]", "[author_facebook]", "[author_instagram]", "[author_youtube]", "[author_website]", "[author_crunchbase]", "[author_muckrack]" ],
                  "detail" => $authDetail( "ACF / user meta social url fields per network (author_x, x, twitter, linkedin, ...)", $A, "URL" ) ],
              ] ],
            [ "key" => "author-verify", "title" => "Author verification", "layer" => "Author layer", "live" => "author",
              "blurb" => "The Muck Rack verified badge for the author, plus a legacy generic field reader. The badge only renders when the author is marked verified.",
              "register_file" => $MR, "access" => "Byline and author box.",
              "items" => [
                [ "tag" => "author_muckrack_verified", "desc" => "The Muck Rack verified badge for the author. Shows a checkmark with type=icon or a label with type=text. Renders only when the author is verified. size=\"0.6em\" sizes the checkmark to the text around it; icon=\"1\" puts the check icon before the label.", "type" => "Badge", "params" => "type (icon|text), size (px|em), icon (1|circle_check|circle_outline_check|check), context, user_id",
                  "variations" => [ [ "code" => "[author_muckrack_verified type=\"icon\"]", "label" => "Checkmark icon" ], [ "code" => "[author_muckrack_verified type=\"text\"]", "label" => "Text label" ] ],
                  "detail" => $authDetail( "ACF / user meta: muckrack_verified, muckrack_url, what_best_describe_you", $A, "Badge" ) ],
                [ "tag" => "muckrack_verified", "deprecated" => true, "use_instead" => "[author_muckrack_verified]", "desc" => "Legacy Muck Rack verified badge for the author. Same data as [author_muckrack_verified]; kept for older templates.", "type" => "Badge", "params" => "type (icon|text), user_id", "detail" => $authDetail( "ACF / user meta verified flag", $MR, "Badge" ) ],
                [ "tag" => "acf_author_field", "deprecated" => true, "use_instead" => "the specific author_* shortcodes", "desc" => "Reads any single ACF or user field for the author by name. Legacy generic accessor.", "type" => "Text", "params" => "field (the field name), user_id", "detail" => $authDetail( "any ACF / user field passed via field=", $MR, "Text" ) ],
              ] ],
            [ "key" => "authors-multi", "requires" => "the Multiple authors feature (Settings, Features tab)", "requires_key" => "multi_authors_enabled", "title" => "Authors on a post (multi-author)", "layer" => "Post layer", "live" => "post",
              "blurb" => "Every author of a post: the WordPress author, then the co-authors added in the editor's Authors box when Multiple authors is switched on. Use these for a multi-author byline.",
              "register_file" => $M, "access" => "Single-post multi-author byline.",
              "items" => [
                [ "tag" => "smp_post_authors", "desc" => "All authors assigned to the post as linked author objects, for a multi-author byline.", "type" => "List", "params" => "post_id, author_index", "detail" => [ "field" => "post_authors", "group" => "Authors box", "source" => "WordPress author + _smpi_co_authors", "file" => $M, "plugin" => "SMP Publication Integration", "type" => "List", "edit" => "post" ] ],
                [ "tag" => "smp_post_author_names", "desc" => "A formatted text list of the display names of every author on the post.", "type" => "Text", "params" => "post_id, author_index", "detail" => [ "field" => "post_authors", "group" => "Authors box", "source" => "WordPress author + _smpi_co_authors", "file" => $M, "plugin" => "SMP Publication Integration", "type" => "Text", "edit" => "post" ] ],
                [ "tag" => "smp_post_author_ids", "desc" => "The WordPress user IDs of every author on the post.", "type" => "IDs", "params" => "post_id, author_index", "detail" => [ "field" => "post_authors", "group" => "Authors box", "source" => "WordPress author + _smpi_co_authors", "file" => $M, "plugin" => "SMP Publication Integration", "type" => "IDs", "edit" => "post" ] ],
              ] ],
            [ "key" => "post-content", "title" => "Post content blocks", "layer" => "Post layer", "live" => "post",
              "blurb" => "Structured blocks rendered inside the single-post body: summary, FAQs, and a table of contents. Each takes a style variant. Summary and FAQs read post ACF fields; the TOC is built from the post headings.",
              "register_file" => $S, "access" => "Single-post body.",
              "items" => [
                [ "tag" => "smp_post_summary", "desc" => "The post summary (What to know) block, from the post summary ACF field. The style chooses the visual treatment.", "type" => "HTML block", "params" => "style (sum00..sum05), post_id",
                  "variations" => [ [ "code" => "[smp_post_summary style=\"sum00\"]", "label" => "Grey card" ], [ "code" => "[smp_post_summary style=\"sum01\"]", "label" => "Left accent card" ], [ "code" => "[smp_post_summary style=\"sum02\"]", "label" => "Top rule" ], [ "code" => "[smp_post_summary style=\"sum03\"]", "label" => "Bordered header" ], [ "code" => "[smp_post_summary style=\"sum04\"]", "label" => "Highlight panel" ], [ "code" => "[smp_post_summary style=\"sum05\"]", "label" => "Diamond briefing" ] ],
                  "detail" => [ "field" => "post_summary", "group" => "Post - Header", "source" => "ACF post field post_summary", "file" => $S, "plugin" => "SMP Publication Integration", "type" => "HTML", "edit" => "post" ] ],
                [ "tag" => "smp_post_faqs", "desc" => "The post FAQ block, from structured FAQ items. format=accordion uses native details and summary elements.", "type" => "HTML block", "params" => "format (html|accordion|text), style (faq00..faq04), post_id",
                  "variations" => [ [ "code" => "[smp_post_faqs format=\"accordion\" style=\"unstyled\"]", "label" => "Native accordion" ], [ "code" => "[smp_post_faqs style=\"faq00\"]", "label" => "Plain dividers" ], [ "code" => "[smp_post_faqs style=\"faq02\"]", "label" => "Cards" ], [ "code" => "[smp_post_faqs style=\"faq03\"]", "label" => "Numbered" ], [ "code" => "[smp_post_faqs style=\"faq04\"]", "label" => "Soft cards" ] ],
                  "detail" => [ "field" => "post_faq_items", "group" => "Post - Header", "source" => "ACF repeater post_faq_items", "file" => $S, "plugin" => "SMP Publication Integration", "type" => "HTML", "edit" => "post" ] ],
                [ "tag" => "smp_table_of_contents", "requires" => "the Table of contents feature (Settings, Features tab)", "requires_key" => "table_of_contents_enabled", "desc" => "A server-rendered table of contents built from selected post heading levels. The style chooses the visual treatment.", "type" => "HTML block", "params" => "style (toc00..toc04), title, levels (h2,h3,h4), open (always|desktop), markers (leading-zero), post_id",
                  "variations" => [ [ "code" => "[smp_table_of_contents style=\"unstyled\" title=\"Contents\" levels=\"h2\" open=\"desktop\" markers=\"leading-zero\"]", "label" => "Elementor h2 contents" ], [ "code" => "[smp_table_of_contents style=\"toc02\"]", "label" => "Soft grey box" ], [ "code" => "[smp_table_of_contents style=\"toc03\"]", "label" => "Numbered panel" ], [ "code" => "[smp_table_of_contents style=\"toc04\"]", "label" => "Pill chips" ] ],
                  "detail" => [ "field" => "-", "group" => "-", "source" => "parsed post headings (h2-h4)", "file" => $TOC, "plugin" => "SMP Publication Integration", "type" => "HTML", "edit" => "post" ] ],
                [ "tag" => "smp_post_acf", "attrs_enum" => [ [ "field" => "post_summary", "label" => "Post summary" ], [ "field" => "post_faq_items", "label" => "Post FAQ items" ] ], "desc" => "Renders a named post ACF field (post_summary or post_faq_items) with SMP block styling.", "type" => "HTML block", "params" => "field (post_summary|post_faq_items), style, post_id", "detail" => [ "field" => "post_summary | post_faq_items", "group" => "Post - Header", "source" => "ACF post field passed via field=", "file" => $S, "plugin" => "SMP Publication Integration", "type" => "HTML", "edit" => "post" ] ],
              ] ],
            [ "key" => "article-meta", "title" => "Article meta", "layer" => "Post layer", "live" => "post",
              "blurb" => "Small inline meta for an article: the estimated reading time and the published date. Both derive from the post itself. Breadcrumbs moved to HWS Base Tools.",
              "register_file" => $BC, "access" => "Single-post header or body.",
              "items" => [
                [ "tag" => "smp_estimated_read_time", "requires" => "the Estimated read time feature (Settings, Features tab)", "requires_key" => "estimated_read_time_enabled", "desc" => "The estimated reading time of the post, computed from its word count.", "type" => "Text", "params" => "post_id", "detail" => [ "field" => "-", "group" => "-", "source" => "computed from post word count", "file" => $RT, "plugin" => "SMP Publication Integration", "type" => "Text", "edit" => "post" ] ],
                [ "tag" => "smp_post_date", "desc" => "The original published date and time in the site timezone, independent of the relative-date display setting.", "type" => "Text", "params" => "format, post_id", "variations" => [ [ "code" => "[smp_post_date format=\"M j, Y · g:i A\"]", "label" => "Article date row" ] ], "detail" => [ "field" => "post_date", "group" => "WordPress post", "source" => "original WordPress publication timestamp", "file" => $PT, "plugin" => "SMP Publication Integration", "type" => "Text", "edit" => "post" ] ],
              ] ],
            [ "key" => "publication", "title" => "Publication / global", "layer" => "Publication layer", "live" => "publication",
              "blurb" => "Publication-wide data from the SMP publication profile (legal name, contact, mission, policy pages; founders come from HWS Base Tools) plus the publication verified badge. Edited in SMP settings, not per post.",
              "register_file" => $S, "access" => "Publication pages: about, masthead, contact, policies.",
              "items" => [
                [ "tag" => "smp_publication_field", "attrs_enum" => [ [ "field" => "legal_name", "label" => "Legal name" ], [ "field" => "alternate_name", "label" => "Alternate name" ], [ "field" => "telephone", "label" => "Telephone" ], [ "field" => "contact_email", "label" => "Public contact email" ], [ "field" => "website", "label" => "Website URL" ], [ "field" => "mission_statement", "label" => "Mission statement" ], [ "field" => "mission_statement_extended", "label" => "Mission statement (extended)" ], [ "field" => "summary", "label" => "Summary" ], [ "field" => "founding_date", "label" => "Founding date" ], [ "field" => "founding_location", "label" => "Founding location" ], [ "field" => "headquarters", "label" => "Headquarters" ], [ "field" => "area_served", "label" => "Area served" ], [ "field" => "knows_about", "label" => "Coverage topics (knowsAbout)" ], [ "field" => "ownership_funding_info", "label" => "Ownership and funding" ], [ "field" => "parent_organization_name", "label" => "Parent organization name" ], [ "field" => "parent_organization_url", "label" => "Parent organization URL" ], [ "field" => "rss_feed", "label" => "RSS feed" ], [ "field" => "google_news_url", "label" => "Google News URL" ], [ "field" => "has_podcast", "label" => "Has a podcast" ], [ "field" => "publication_muckrack_verified", "label" => "MuckRack verified" ], [ "field" => "publication_muckrack_url", "label" => "MuckRack URL" ], [ "field" => "masthead", "label" => "Masthead page" ], [ "field" => "publishing_principles", "label" => "Publishing principles page" ], [ "field" => "verification_fact_checking_policy", "label" => "Fact-checking policy page" ], [ "field" => "corrections_policy", "label" => "Corrections policy page" ], [ "field" => "ethics_policy", "label" => "Ethics policy page" ], [ "field" => "diversity_policy", "label" => "Diversity policy page" ], [ "field" => "privacy", "label" => "Privacy policy" ], [ "field" => "terms", "label" => "Terms of use" ], [ "field" => "dmca", "label" => "DMCA" ], [ "field" => "brand_assets", "label" => "Brand assets gallery" ] ], "code" => "[smp_publication_field field=\"legal_name\"]", "desc" => "Any single publication profile field by name (for example legal_name, telephone, mission_statement).", "type" => "Text", "params" => "field (the smpi field name), format (html), fallback", "detail" => [ "field" => "smpi_<name>", "group" => "Publication Profile (group_smpi_publication_profile)", "source" => "Publication profile ACF group", "file" => $S, "plugin" => "SMP Publication Integration", "type" => "Varies", "edit" => "publication" ] ],
                [ "tag" => "smp_publication_mission_statement", "desc" => "The publication mission statement (rich text).", "type" => "HTML", "params" => "none", "detail" => [ "field" => "smpi_mission_statement", "group" => "Publication Profile", "source" => "ACF: smpi_mission_statement", "file" => $S, "plugin" => "SMP Publication Integration", "type" => "HTML", "edit" => "publication" ] ],
                [ "tag" => "smp_publication_founders", "desc" => "The publication founders as a linked list, read from HWS Base Tools (Settings, HWS Base Tools, Founders).", "type" => "HTML", "params" => "none", "detail" => [ "field" => "hws_publication_founders()", "group" => "HWS Base Tools", "source" => "HWS Base Tools founders API", "file" => $S, "plugin" => "SMP Publication Integration", "type" => "HTML", "edit" => "publication" ] ],
                [ "tag" => "smp_publication_user", "desc" => "The WordPress author account that represents this publication.", "type" => "Text", "params" => "none", "detail" => [ "field" => "smpi_publication_user", "group" => "Publication Profile", "source" => "ACF: smpi_publication_user", "file" => $S, "plugin" => "SMP Publication Integration", "type" => "Text", "edit" => "publication" ] ],
                [ "tag" => "smp_publication_profile", "desc" => "A summary of the publication profile (name, logo, links).", "type" => "HTML", "params" => "none", "detail" => [ "field" => "smpi_* (profile)", "group" => "Publication Profile", "source" => "Publication profile ACF group", "file" => $S, "plugin" => "SMP Publication Integration", "type" => "HTML", "edit" => "publication" ] ],
                [ "tag" => "smp_publication_page", "desc" => "A link to an assigned publication page (about, contact, masthead, and so on).", "type" => "URL", "params" => "type (the page slug)", "detail" => [ "field" => "page assignments", "group" => "SMP settings", "source" => "page_assignments option", "file" => $S, "plugin" => "SMP Publication Integration", "type" => "URL", "edit" => "publication" ] ],
                [ "tag" => "smp_publication_page_template", "code" => "[smp_publication_page_template]", "desc" => "The configured publication page template. Alias: [smp_page_template].", "type" => "Text", "params" => "none", "aliases" => [ "[smp_page_template]" ], "detail" => [ "field" => "page assignments", "group" => "SMP settings", "source" => "page_assignments option", "file" => $S, "plugin" => "SMP Publication Integration", "type" => "Text", "edit" => "publication" ] ],
                [ "tag" => "smp_publication_debug_url", "desc" => "The public debug endpoint URL for this publication.", "type" => "URL", "params" => "none", "detail" => [ "field" => "debug endpoint", "group" => "SMP settings", "source" => "public debug endpoint setting", "file" => $S, "plugin" => "SMP Publication Integration", "type" => "URL", "edit" => "publication" ] ],
                [ "tag" => "smp_publication_validate_schema", "desc" => "A schema integrity report for the publication.", "type" => "HTML", "params" => "none", "detail" => [ "field" => "-", "group" => "-", "source" => "schema integrity report", "file" => $S, "plugin" => "SMP Publication Integration", "type" => "HTML", "edit" => "publication" ] ],
                [ "tag" => "smp_publication_muckrack_verified", "requires" => "the MuckRack verified publication feature (Settings, Features tab)", "requires_key" => "publication_muckrack_verified_enabled", "desc" => "The Muck Rack verified badge for the publication itself (not the author).", "type" => "Badge", "params" => "type (icon|text)", "variations" => [ [ "code" => "[smp_publication_muckrack_verified type=\"icon\"]", "label" => "Checkmark icon" ], [ "code" => "[smp_publication_muckrack_verified type=\"text\"]", "label" => "Text label" ] ], "detail" => [ "field" => "smpi_publication_muckrack_verified", "group" => "Publication Profile", "source" => "publication MuckRack ACF field and url", "file" => $MR, "plugin" => "SMP Publication Integration", "type" => "Badge", "edit" => "publication" ] ],
              ] ],
            [ "key" => "external", "requires" => "the matching external plugin to be installed and active", "title" => "External providers", "layer" => "Other plugins", "live" => "none",
              "blurb" => "Tags provided by other HWS plugins, not registered by this plugin. Listed for reference so you know they are not SMP-owned and are not run on this page.",
              "register_file" => "-", "access" => "Other HWS plugins and legacy author templates.",
              "items" => [
                [ "tag" => "founder", "code" => "[founder id=\"...\"]", "desc" => "A founder profile field from HWS Base Tools (external plugin). Reference only.", "type" => "Text", "params" => "id", "detail" => [ "field" => "-", "group" => "-", "source" => "external plugin: HWS Base Tools", "file" => "-", "plugin" => "HWS Base Tools (external)", "type" => "Text", "edit" => "external" ] ],
                [ "tag" => "company", "code" => "[company id=\"...\"]", "desc" => "A company profile field from HWS Base Tools (external plugin). Reference only.", "type" => "Text", "params" => "id", "detail" => [ "field" => "-", "group" => "-", "source" => "external plugin: HWS Base Tools", "file" => "-", "plugin" => "HWS Base Tools (external)", "type" => "Text", "edit" => "external" ] ],
                [ "tag" => "get_profile_field", "code" => "[get_profile_field field=\"...\"]", "desc" => "A profile field from SMP Verified Profiles (external plugin). Reference only.", "type" => "Text", "params" => "field", "detail" => [ "field" => "-", "group" => "-", "source" => "external plugin: SMP Verified Profiles", "file" => "-", "plugin" => "SMP Verified Profiles (external)", "type" => "Text", "edit" => "external" ] ],
              ] ],
        ];
    }

    private static function sc_edit_url( string $target, int $user_id, int $post_id ): string {
        if ( "author" === $target && $user_id > 0 ) { return admin_url( "user-edit.php?user_id=" . $user_id ); }
        if ( "post" === $target && $post_id > 0 ) { return admin_url( "post.php?post=" . $post_id . "&action=edit" ); }
        if ( "publication" === $target ) { return admin_url( "options-general.php?page=smp-publication-integration&tab=publication_options" ); }
        return "";
    }

    private static function sc_run( string $code, string $mode, int $user_id, int $post_id ): string {
        if ( "author" === $mode ) {
            if ( $user_id <= 0 ) { return "<span class=\"smpi-sc-hint\">pick a preview author</span>"; }
            $run = preg_replace( "/\\]\$/", " user_id=\"" . $user_id . "\"]", $code );
            return self::shortcode_value_html( $run );
        }
        if ( "post" === $mode ) {
            if ( $post_id <= 0 ) { return "<span class=\"smpi-sc-hint\">pick a sample post</span>"; }
            global $post;
            $saved = $post;
            $post = get_post( $post_id );
            if ( $post instanceof \WP_Post ) { setup_postdata( $post ); }
            $run = preg_replace( "/\\]\$/", " post_id=\"" . $post_id . "\"]", $code );
            $val = self::shortcode_value_html( $run );
            wp_reset_postdata();
            $post = $saved;
            return $val;
        }
        if ( "publication" === $mode ) { return self::shortcode_value_html( $code ); }
        return "<span class=\"smpi-sc-hint\">external provider, not run here</span>";
    }

    public static function shortcode_post_options( int $selected ): string {
        $ids = get_posts( [ "post_type" => "post", "post_status" => "publish", "numberposts" => 40, "orderby" => "date", "order" => "DESC", "fields" => "ids" ] );
        $out = "";
        foreach ( $ids as $pid ) {
            $sel = ( (int) $pid === $selected ) ? " selected" : "";
            $out .= "<option value=\"" . (int) $pid . "\"" . $sel . ">" . esc_html( wp_trim_words( (string) get_the_title( (int) $pid ), 9 ) ) . " (#" . (int) $pid . ")</option>";
        }
        return $out;
    }

    private static function sc_req_html( string $text, string $key, string $cls ): string {
        if ( "" === $text ) { return ""; }
        $chip = "";
        if ( "" !== $key && class_exists( "smp_publication_integration\\Support\\Settings" ) ) {
            $on = (bool) \smp_publication_integration\Support\Settings::bool( $key );
            $chip = " <span class=\"smpi-sc-req-state " . ( $on ? "is-on" : "is-off" ) . "\">" . ( $on ? "Enabled" : "Disabled" ) . "</span>";
        }
        return "<p class=\"" . esc_attr( $cls ) . "\"><span class=\"smpi-sc-req-badge\">Requires</span>" . esc_html( $text ) . $chip . "</p>";
    }

    public static function shortcode_user_values_html( int $user_id, int $post_id = 0 ): string {
        $layers = [ "Author layer" => "author", "Post layer" => "post", "Publication layer" => "publication", "Other plugins" => "external", "External layer" => "external" ];
        $out = "";
        foreach ( self::shortcode_catalog() as $ctx ) {
            $mode = (string) $ctx["live"];
            $layer = (string) $ctx["layer"];
            $lmod = isset( $layers[ $layer ] ) ? $layers[ $layer ] : "author";
            $ctxReq = isset( $ctx["requires"] ) ? (string) $ctx["requires"] : "";
            $rows = "";
            $count = count( $ctx["items"] );
            foreach ( $ctx["items"] as $item ) {
                $tag = (string) $item["tag"];
                $code = isset( $item["code"] ) ? (string) $item["code"] : "[" . $tag . "]";
                $desc = (string) $item["desc"];
                $type = isset( $item["type"] ) ? (string) $item["type"] : "";
                $params = isset( $item["params"] ) ? (string) $item["params"] : "";
                $detail = isset( $item["detail"] ) ? $item["detail"] : [];
                $source = isset( $detail["source"] ) ? (string) $detail["source"] : "";
                $variations = isset( $item["variations"] ) ? $item["variations"] : [];
                $varLive = ! empty( $item["variations_live"] );
                $aliases = isset( $item["aliases"] ) ? $item["aliases"] : [];
                $dep = ! empty( $item["deprecated"] );
                $useInstead = isset( $item["use_instead"] ) ? (string) $item["use_instead"] : "";
                $itemReq = isset( $item["requires"] ) ? (string) $item["requires"] : "";
                $attrs = isset( $item["attrs_enum"] ) ? $item["attrs_enum"] : [];
                $editUrl = self::sc_edit_url( isset( $detail["edit"] ) ? (string) $detail["edit"] : "none", $user_id, $post_id );
                $filter = strtolower( $tag . " " . $desc . " " . $source . " " . $params . " " . $type . ( $dep ? " deprecated legacy" : "" ) . ( "" !== $itemReq ? " requires" : "" ) );
                $rows .= "<div class=\"smpi-sc-row" . ( $dep ? " is-deprecated" : "" ) . "\" data-filter=\"" . esc_attr( $filter ) . "\">";
                $rows .= "<p class=\"smpi-sc-desc\">" . esc_html( $desc );
                if ( $dep && "" !== $useInstead ) { $rows .= " <span class=\"smpi-sc-use\">Use " . esc_html( $useInstead ) . " instead.</span>"; }
                $rows .= "</p>";
                $rows .= self::sc_req_html( $itemReq, isset( $item["requires_key"] ) ? (string) $item["requires_key"] : "", "smpi-sc-req" );
                $rows .= CodeViews::shortcode( [ "tag" => $code, "aliases" => array_map( "strval", $aliases ), "attributes" => self::sc_attributes( $params ) ] );
                $rows .= "<div class=\"smpi-sc-block\"><div class=\"smpi-sc-line\">";
                if ( $dep ) { $rows .= "<span class=\"smpi-sc-dep\">Deprecated</span>"; }
                $rows .= "<span class=\"smpi-sc-actions\">";
                if ( "" !== $editUrl ) { $rows .= "<a class=\"smpi-sc-edit\" href=\"" . esc_url( $editUrl ) . "\" target=\"_blank\" rel=\"noopener\" title=\"Edit the underlying data\">Edit</a>"; }
                $rows .= "<button type=\"button\" class=\"smpi-sc-det\">Details</button></span></div>";
                if ( $varLive && ! empty( $variations ) ) {
                    $rows .= "<div class=\"smpi-sc-vars\">";
                    foreach ( $variations as $v ) {
                        $vc = (string) $v["code"];
                        $rows .= "<div class=\"smpi-sc-var\"><code class=\"smpi-sc-vtag\">" . esc_html( $vc ) . "</code><button type=\"button\" class=\"smpi-sc-copy smpi-sc-copy--mini\" data-copy=\"" . esc_attr( $vc ) . "\">Copy</button><span class=\"smpi-sc-vlabel\">" . esc_html( (string) $v["label"] ) . "</span><span class=\"smpi-sc-vout\">" . self::sc_run( $vc, $mode, $user_id, $post_id ) . "</span></div>";
                    }
                    $rows .= "</div>";
                } elseif ( "none" !== $mode ) {
                    $rows .= "<div class=\"smpi-sc-out\"><span class=\"smpi-sc-k\">Output</span>" . self::sc_run( $code, $mode, $user_id, $post_id ) . "</div>";
                }
                $rows .= "</div>";
                if ( ! empty( $attrs ) ) {
                    $rows .= "<details class=\"smpi-sc-attrs-card\"><summary class=\"smpi-sc-attrs-head\"><span class=\"smpi-sc-chev2\" aria-hidden=\"true\"></span>All field values<span class=\"smpi-sc-attrs-count\">" . count( $attrs ) . "</span></summary><div class=\"smpi-sc-attrs-body\">";
                    foreach ( $attrs as $a ) {
                        $fv = (string) $a["field"];
                        $flabel = isset( $a["label"] ) ? (string) $a["label"] : $fv;
                        $fcode = "[" . $tag . " field=\"" . $fv . "\"]";
                        $rows .= "<div class=\"smpi-sc-attr\"><code class=\"smpi-sc-attr-tag\">field=&quot;" . esc_html( $fv ) . "&quot;</code><button type=\"button\" class=\"smpi-sc-copy smpi-sc-copy--mini\" data-copy=\"" . esc_attr( $fcode ) . "\">Copy</button><span class=\"smpi-sc-attr-label\">" . esc_html( $flabel ) . "</span><span class=\"smpi-sc-attr-out\">" . self::sc_run( $fcode, $mode, $user_id, $post_id ) . "</span></div>";
                    }
                    $rows .= "</div></details>";
                }
                if ( ! $varLive && ! empty( $variations ) ) {
                    $chips = "";
                    foreach ( $variations as $v ) { $chips .= "<span class=\"smpi-sc-vchip\" data-copy=\"" . esc_attr( (string) $v["code"] ) . "\" title=\"" . esc_attr( (string) $v["code"] . " - " . (string) $v["label"] ) . "\">" . esc_html( (string) $v["label"] ) . "</span>"; }
                    $rows .= "<div class=\"smpi-sc-vars-line\"><span class=\"smpi-sc-k\">Variations</span>" . $chips . "</div>";
                }
                $rows .= "<div class=\"smpi-sc-detail\">";
                if ( "" !== $source ) { $rows .= "<div class=\"r\"><b>Source</b> " . esc_html( $source ) . "</div>"; }
                if ( "" !== $itemReq ) { $rows .= "<div class=\"r\"><b>Requires</b> " . esc_html( $itemReq ) . "</div>"; }
                if ( $dep ) { $rows .= "<div class=\"r\"><b>Status</b> Deprecated" . ( "" !== $useInstead ? " - use " . esc_html( $useInstead ) . " instead" : "" ) . "</div>"; }
                $rows .= "<div class=\"r\"><b>Field</b> " . esc_html( isset( $detail["field"] ) ? (string) $detail["field"] : "-" ) . " &nbsp;&nbsp; <b>Group</b> " . esc_html( isset( $detail["group"] ) ? (string) $detail["group"] : "-" ) . "</div>";
                $rows .= "<div class=\"r\"><b>Type</b> " . esc_html( isset( $detail["type"] ) ? (string) $detail["type"] : "-" ) . " &nbsp;&nbsp; <b>Plugin</b> " . esc_html( isset( $detail["plugin"] ) ? (string) $detail["plugin"] : "-" ) . "</div>";
                $rows .= "<div class=\"r\"><b>Source file</b> <code>" . esc_html( isset( $detail["file"] ) ? (string) $detail["file"] : "-" ) . "</code></div>";
                if ( "" !== $editUrl ) { $rows .= "<div class=\"r\"><b>Edit page</b> <a href=\"" . esc_url( $editUrl ) . "\" target=\"_blank\" rel=\"noopener\">" . esc_html( $editUrl ) . "</a></div>"; }
                $rows .= "</div>";
                $rows .= "</div>";
            }
            $reg = (string) $ctx["register_file"];
            $access = (string) $ctx["access"];
            $query_key = "shortcode-" . sanitize_key( (string) $ctx["key"] );
            $out .= "<details class=\"smpi-sc-card\" data-ctx=\"" . esc_attr( (string) $ctx["key"] ) . "\" data-hpc-query-key=\"" . esc_attr( $query_key ) . "\">";
            $out .= "<summary class=\"smpi-sc-card-head\"><span class=\"smpi-sc-chev\" aria-hidden=\"true\"></span><span class=\"smpi-sc-layer smpi-sc-layer--" . esc_attr( $lmod ) . "\">" . esc_html( $layer ) . "</span><span class=\"smpi-sc-card-title\">" . esc_html( (string) $ctx["title"] ) . "</span><span class=\"smpi-sc-count\">" . (int) $count . "</span></summary>";
            $out .= "<div class=\"smpi-sc-card-body\">";
            $out .= "<p class=\"smpi-sc-card-blurb\">" . esc_html( (string) $ctx["blurb"] ) . "</p>";
            $out .= self::sc_req_html( $ctxReq, isset( $ctx["requires_key"] ) ? (string) $ctx["requires_key"] : "", "smpi-sc-card-req" );
            $out .= "<p class=\"smpi-sc-card-meta\"><b>Registered in</b> <code>" . esc_html( $reg ) . "</code> &nbsp; <b>Used</b> " . esc_html( $access ) . "</p>";
            $out .= "<div class=\"smpi-sc-rows\">" . $rows . "</div>";
            $out .= "</div></details>";
        }
        return $out;
    }



    /**
     * The catalog's "params" text ("type (icon|text), user_id", "words (default 35)") as
     * Core ShortcodeBreakdown attributes: choices from a|b lists, a default where named.
     *
     * @return array<string,array{default:string,description:string,options?:string[]}>
     */
    private static function sc_attributes( string $params ): array {
        $known = [
            "user_id"      => "The author to show (user ID). Default: the current article's author.",
            "post_id"      => "The post to read (post ID). Default: the current post.",
            "author_index" => "Which author of a multi-author post, starting at 1. Default: the first.",
            "id"           => "The record to show (ID).",
        ];
        $out = [];
        if ( "" === $params || "none" === $params ) {
            return $out;
        }
        preg_match_all( '/([a-z_]+)\s*(?:\(([^)]*)\))?/', $params, $m, PREG_SET_ORDER );
        foreach ( $m as $part ) {
            $name = $part[1];
            $note = isset( $part[2] ) ? trim( $part[2] ) : "";
            $attr = [ "default" => "", "description" => $known[ $name ] ?? "" ];
            if ( preg_match( '/^default\s+(.+)$/', $note, $d ) ) {
                $attr["default"] = $d[1];
            } elseif ( preg_match( '/^[a-z0-9_-]+([|,][a-z0-9_-]+)+$/i', $note ) ) {
                $attr["options"] = array_map( "trim", preg_split( '/[|,]/', $note ) );
            } elseif ( "" !== $note ) {
                $attr["description"] = ucfirst( $note ) . ".";
            }
            $out[ $name ] = $attr;
        }
        return $out;
    }

    private static function shortcode_value_html( string $code ): string {
        $value = do_shortcode( $code );
        if ( $value === $code ) {
            return "<span class=\"smpi-muted\">Provider not active or shortcode not registered here.</span>";
        }
        $text = trim( wp_strip_all_tags( (string) $value ) );
        if ( "" === $text ) {
            return "<span class=\"smpi-muted\">Empty</span>";
        }
        return "<code>" . esc_html( wp_trim_words( $text, 22 ) ) . "</code>";
    }
}
