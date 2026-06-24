<?php

namespace smp_publication_integration\Admin;

use Hexa\PluginCore\SnippetRegistry\SnippetDefinition;
use Hexa\PluginCore\SnippetRegistry\SnippetRegistry;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Dense, category-grouped table view for snippets with a single inline detail
 * panel per row (no accordions, no column grid). Reuses the core
 * SnippetRegistry / SnippetDefinition data contract; presentation only.
 */
final class SnippetsTableRenderer {
    public function render( SnippetRegistry|array $snippets, array $args = [] ): string {
        $registry = $snippets instanceof SnippetRegistry ? $snippets : ( new SnippetRegistry() )->add_many( $snippets );
        $summary  = $registry->summary();

        $title       = isset( $args["title"] ) ? (string) $args["title"] : "Snippets";
        $description = isset( $args["description"] ) ? (string) $args["description"] : "";
        $ajax_url    = isset( $args["ajax_url"] ) ? (string) $args["ajax_url"] : ( function_exists( "admin_url" ) ? admin_url( "admin-ajax.php" ) : "" );
        $toggle      = isset( $args["toggle_action"] ) ? sanitize_key( (string) $args["toggle_action"] ) : "";
        $test        = isset( $args["test_action"] ) ? sanitize_key( (string) $args["test_action"] ) : "";
        $nonce       = isset( $args["nonce"] ) ? (string) $args["nonce"] : "";
        $nonce_field = isset( $args["nonce_field"] ) ? sanitize_key( (string) $args["nonce_field"] ) : "nonce";
        $categories  = isset( $args["categories"] ) && is_array( $args["categories"] ) ? $args["categories"] : [];

        ob_start();
        ?>
        <div class="smpi-snips" data-smpi-snips data-ajax-url="<?php echo esc_url( $ajax_url ); ?>" data-toggle-action="<?php echo esc_attr( $toggle ); ?>" data-test-action="<?php echo esc_attr( $test ); ?>" data-nonce-field="<?php echo esc_attr( $nonce_field ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <?php echo $this->assets(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="smpi-snips-bar">
                <div>
                    <h2><?php echo esc_html( $title ); ?></h2>
                    <?php if ( "" !== $description ) : ?><p><?php echo esc_html( $description ); ?></p><?php endif; ?>
                </div>
                <div class="smpi-snips-counts">
                    <span class="smpi-snip-pill is-dark"><?php echo (int) $summary["total"]; ?> snippets</span>
                    <span class="smpi-snip-pill is-ok"><?php echo (int) $summary["enabled"]; ?> active</span>
                    <span class="smpi-snip-pill is-warn"><?php echo (int) $summary["disabled"]; ?> inactive</span>
                </div>
            </div>

            <?php foreach ( $registry->grouped() as $category_id => $definitions ) : ?>
                <?php $cat = $this->category_config( (string) $category_id, $categories ); ?>
                <section class="smpi-snip-group">
                    <div class="smpi-snip-group-head">
                        <div>
                            <h3><?php echo esc_html( $cat["label"] ); ?></h3>
                            <?php if ( "" !== $cat["description"] ) : ?><p><?php echo esc_html( $cat["description"] ); ?></p><?php endif; ?>
                        </div>
                        <span class="smpi-snip-pill is-dark"><?php echo (int) count( $definitions ); ?> <?php echo 1 === count( $definitions ) ? "item" : "items"; ?></span>
                    </div>
                    <table class="smpi-snip-table">
                        <thead>
                            <tr>
                                <th class="c-name">Snippet</th>
                                <th class="c-status">Status</th>
                                <th class="c-test">Test</th>
                                <th class="c-toggle">Enabled</th>
                                <th class="c-exp"><span class="screen-reader-text">Details</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $definitions as $definition ) : ?>
                                <?php echo $this->row( $registry, $definition, "" !== $toggle, "" !== $test ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function row( SnippetRegistry $registry, SnippetDefinition $definition, bool $can_toggle, bool $can_test ): string {
        $enabled = $registry->is_enabled( $definition );
        $test    = $registry->test( $definition->id );
        $status  = (string) ( $test["status"] ?? "fail" );
        $tone    = "pass" === $status ? "is-ok" : ( "warn" === $status ? "is-warn" : "is-bad" );

        ob_start();
        ?>
        <tr class="smpi-snip-row" data-snippet-id="<?php echo esc_attr( $definition->id ); ?>">
            <td class="c-name">
                <button type="button" class="smpi-snip-name" data-snippet-expand>
                    <span class="smpi-snip-caret" aria-hidden="true"></span>
                    <span class="smpi-snip-id"><?php echo esc_html( $definition->id ); ?></span>
                    <span class="smpi-snip-title"><?php echo esc_html( $definition->name ); ?></span>
                    <?php if ( $definition->deprecated ) : ?><span class="smpi-snip-pill is-bad">Deprecated</span><?php endif; ?>
                    <?php if ( $definition->scope_admin_only ) : ?><span class="smpi-snip-pill is-dark">Admin only</span><?php endif; ?>
                </button>
            </td>
            <td class="c-status"><span class="smpi-snip-pill <?php echo $enabled ? "is-ok" : "is-warn"; ?>" data-snippet-status><?php echo $enabled ? "Active" : "Inactive"; ?></span></td>
            <td class="c-test"><span class="smpi-snip-pill <?php echo esc_attr( $tone ); ?>" data-snippet-test-pill><?php echo esc_html( "Test " . ucfirst( $status ) ); ?></span></td>
            <td class="c-toggle">
                <label class="smpi-snip-switch">
                    <input type="checkbox" data-snippet-toggle <?php checked( $enabled ); ?> <?php disabled( ! $can_toggle ); ?>>
                    <span></span>
                </label>
            </td>
            <td class="c-exp"><button type="button" class="smpi-snip-expand-btn" data-snippet-expand aria-label="Toggle details"><span class="smpi-snip-caret" aria-hidden="true"></span></button></td>
        </tr>
        <tr class="smpi-snip-detail" data-snippet-detail hidden>
            <td colspan="5">
                <div class="smpi-snip-detail-inner">
                    <?php echo $this->detail_line( "Description", $this->description_html( $definition ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $this->detail_line( "Hooks &amp; code", $this->list_html( $definition->snippets, "No snippet internals registered." ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php if ( ! empty( $definition->shortcodes ) ) : ?>
                        <?php echo $this->detail_line( "Shortcodes", $this->list_html( $definition->shortcodes, "" ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                    <?php echo $this->detail_line( "Test", $this->test_html( $test, $can_test ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $this->detail_line( "Readme", "<pre class=\"smpi-snip-readme\">" . esc_html( $this->readme_text( $definition ) ) . "</pre>" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    private function detail_line( string $label, string $value_html ): string {
        return "<div class=\"smpi-snip-detail-row\"><span class=\"smpi-snip-k\">" . wp_kses_post( $label ) . "</span><div class=\"smpi-snip-v\">" . $value_html . "</div></div>";
    }

    private function description_html( SnippetDefinition $definition ): string {
        $body = "" !== $definition->description ? "<p>" . wp_kses_post( $definition->description ) . "</p>" : "<p class=\"smpi-snip-muted\">No description registered.</p>";
        if ( "" !== $definition->info ) {
            $body .= "<div class=\"smpi-snip-info\">" . wp_kses_post( $definition->info ) . "</div>";
        }
        return $body;
    }

    private function list_html( array $items, string $empty ): string {
        if ( empty( $items ) ) {
            return "" === $empty ? "" : "<p class=\"smpi-snip-muted\">" . esc_html( $empty ) . "</p>";
        }
        $body = "<ul class=\"smpi-snip-list\">";
        foreach ( $items as $item ) {
            $label = isset( $item["label"] ) && is_scalar( $item["label"] ) ? (string) $item["label"] : "Item";
            $value = isset( $item["value"] ) && is_scalar( $item["value"] ) ? (string) $item["value"] : ( isset( $item["tag"] ) && is_scalar( $item["tag"] ) ? "[" . (string) $item["tag"] . "]" : "" );
            $desc  = isset( $item["description"] ) && is_scalar( $item["description"] ) ? (string) $item["description"] : "";
            $body .= "<li><strong>" . esc_html( $label ) . "</strong>";
            if ( "" !== $value ) {
                $body .= " <code>" . esc_html( $value ) . "</code>";
            }
            if ( "" !== $desc ) {
                $body .= "<span>" . esc_html( $desc ) . "</span>";
            }
            $body .= "</li>";
        }
        return $body . "</ul>";
    }

    private function test_html( array $test, bool $can_test ): string {
        $rules = isset( $test["rules"] ) && is_array( $test["rules"] ) ? $test["rules"] : [];
        $body  = "<div class=\"smpi-snip-test\">";
        $body .= "<p>" . esc_html( (string) ( $test["message"] ?? "" ) ) . " <button type=\"button\" class=\"button button-secondary smpi-snip-test-btn\" data-snippet-test " . ( $can_test ? "" : "disabled" ) . ">Run test</button></p>";
        $body .= "<div class=\"smpi-snip-test-rules\" data-snippet-test-rules>";
        foreach ( $rules as $rule ) {
            $passed = ! empty( $rule["passed"] );
            $body  .= "<div class=\"smpi-snip-rule " . ( $passed ? "is-pass" : "is-fail" ) . "\"><span>" . ( $passed ? "PASS" : "FAIL" ) . "</span>" . esc_html( (string) ( $rule["label"] ?? "Rule" ) ) . ( ! empty( $rule["required"] ) ? " <em>(required)</em>" : "" ) . "</div>";
        }
        $body .= "</div></div>";
        return $body;
    }

    private function readme_text( SnippetDefinition $definition ): string {
        $readme = $definition->readme;
        if ( "" === $readme ) {
            $readme = $definition->name . "\n\n" . ( "" !== $definition->description ? wp_strip_all_tags( $definition->description ) : "No readme registered." );
        }
        return $readme;
    }

    private function category_config( string $category_id, array $categories ): array {
        $config = isset( $categories[ $category_id ] ) && is_array( $categories[ $category_id ] ) ? $categories[ $category_id ] : [];
        return [
            "label"       => isset( $config["label"] ) ? (string) $config["label"] : ucwords( str_replace( [ "-", "_" ], " ", $category_id ) ),
            "description" => isset( $config["description"] ) ? (string) $config["description"] : "",
        ];
    }

    private function assets(): string {
        static $done = false;
        if ( $done ) {
            return "";
        }
        $done = true;

        return <<<'HTML'
<style>
.smpi-snips{--ss-ok:#16794a;--ss-okbg:#e7f6ee;--ss-warn:#9a6a00;--ss-warnbg:#fdf3df;--ss-bad:#b42318;--ss-badbg:#fdecea;--ss-line:#e4e8ef;--ss-ink:#1f2733;--ss-muted:#667085}
.smpi-snips *{box-sizing:border-box}
.smpi-snips-bar{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;background:#fff;border:1px solid var(--ss-line);border-radius:10px;padding:18px 20px;margin:0 0 14px}
.smpi-snips-bar h2{margin:0;font-size:20px;color:var(--ss-ink)}
.smpi-snips-bar p{margin:4px 0 0;color:var(--ss-muted)}
.smpi-snips-counts{display:flex;gap:8px;flex-wrap:wrap}
.smpi-snip-pill{display:inline-flex;align-items:center;font-size:12px;font-weight:600;line-height:1;padding:5px 10px;border-radius:999px;white-space:nowrap}
.smpi-snip-pill.is-dark{background:#1f2733;color:#fff}
.smpi-snip-pill.is-ok{background:var(--ss-okbg);color:var(--ss-ok)}
.smpi-snip-pill.is-warn{background:var(--ss-warnbg);color:var(--ss-warn)}
.smpi-snip-pill.is-bad{background:var(--ss-badbg);color:var(--ss-bad)}
.smpi-snip-group{background:#fff;border:1px solid var(--ss-line);border-radius:10px;overflow:hidden;margin:0 0 14px}
.smpi-snip-group-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:13px 16px;background:#f8fafc;border-bottom:1px solid var(--ss-line)}
.smpi-snip-group-head h3{margin:0;font-size:15px;color:var(--ss-ink)}
.smpi-snip-group-head p{margin:3px 0 0;color:var(--ss-muted);font-size:12px}
.smpi-snip-table{width:100%;border-collapse:collapse;font-size:13px}
.smpi-snip-table thead th{text-align:left;font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:var(--ss-muted);font-weight:600;padding:9px 14px;border-bottom:1px solid var(--ss-line)}
.smpi-snip-table th.c-status,.smpi-snip-table th.c-test,.smpi-snip-table th.c-toggle{width:1%;white-space:nowrap}
.smpi-snip-table th.c-exp{width:40px}
.smpi-snip-row>td{padding:10px 14px;border-bottom:1px solid #eef1f6;vertical-align:middle}
.smpi-snip-row:last-child>td{border-bottom:0}
.smpi-snip-row:hover>td{background:#fafbfe}
.smpi-snip-name{display:inline-flex;align-items:center;gap:10px;background:none;border:0;cursor:pointer;padding:0;text-align:left;flex-wrap:wrap}
.smpi-snip-id{font-family:Menlo,Consolas,monospace;font-size:11px;color:#5b6472;background:#f1f3f7;border-radius:5px;padding:2px 6px}
.smpi-snip-title{font-weight:600;color:var(--ss-ink);font-size:14px}
.smpi-snip-caret{width:7px;height:7px;border-right:2px solid #98a2b3;border-bottom:2px solid #98a2b3;transform:rotate(-45deg);transition:transform .15s ease;display:inline-block;flex:0 0 auto}
.smpi-snip-row.is-open .smpi-snip-name .smpi-snip-caret,.smpi-snip-expand-btn.is-open .smpi-snip-caret{transform:rotate(45deg)}
.smpi-snip-expand-btn{background:none;border:0;cursor:pointer;padding:7px;display:inline-flex}
.smpi-snip-switch{position:relative;display:inline-block;width:40px;height:22px}
.smpi-snip-switch input{opacity:0;width:0;height:0;position:absolute;margin:0}
.smpi-snip-switch span{position:absolute;inset:0;background:#cbd5e1;border-radius:999px;transition:.18s;cursor:pointer}
.smpi-snip-switch span:before{content:"";position:absolute;width:16px;height:16px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.18s;box-shadow:0 1px 2px rgba(0,0,0,.2)}
.smpi-snip-switch input:checked+span{background:var(--ss-ok)}
.smpi-snip-switch input:checked+span:before{transform:translateX(18px)}
.smpi-snip-switch input:disabled+span{opacity:.5;cursor:not-allowed}
.smpi-snip-detail>td{padding:0;background:#f9fbfd;border-bottom:1px solid var(--ss-line)}
.smpi-snip-detail-inner{padding:14px 16px 16px 40px;display:grid;gap:10px}
.smpi-snip-detail-row{display:grid;grid-template-columns:130px 1fr;gap:14px;align-items:start}
.smpi-snip-k{font-size:11px;font-weight:700;color:var(--ss-muted);text-transform:uppercase;letter-spacing:.03em;padding-top:3px}
.smpi-snip-v{color:#374151;line-height:1.5;min-width:0}
.smpi-snip-v p{margin:0 0 6px}
.smpi-snip-v p:last-child{margin-bottom:0}
.smpi-snip-info{background:#fff;border:1px solid var(--ss-line);border-radius:7px;padding:8px 10px;margin-top:4px}
.smpi-snip-list{margin:0;padding:0;list-style:none;display:grid;gap:7px}
.smpi-snip-list li{display:flex;flex-wrap:wrap;align-items:baseline;gap:6px}
.smpi-snip-list code{font-family:Menlo,Consolas,monospace;font-size:11px;background:#eef1f6;border-radius:4px;padding:1px 5px;word-break:break-all}
.smpi-snip-list span{flex:1 1 100%;color:var(--ss-muted);font-size:12px}
.smpi-snip-test-rules{display:grid;gap:5px;margin-top:8px}
.smpi-snip-rule{font-size:12px;border-left:3px solid #cbd5e1;padding:5px 9px;border-radius:0 6px 6px 0;background:#fff}
.smpi-snip-rule.is-pass{border-left-color:var(--ss-ok)}
.smpi-snip-rule.is-fail{border-left-color:var(--ss-bad)}
.smpi-snip-rule span{font-weight:700;margin-right:7px;font-size:10px}
.smpi-snip-rule.is-pass span{color:var(--ss-ok)}
.smpi-snip-rule.is-fail span{color:var(--ss-bad)}
.smpi-snip-readme{font-family:Menlo,Consolas,monospace;font-size:11px;white-space:pre-wrap;background:#fff;border:1px solid var(--ss-line);border-radius:7px;padding:10px;margin:0;color:#374151}
.smpi-snip-test-btn{margin-left:6px!important}
.smpi-snip-muted{color:var(--ss-muted)}
@media(max-width:782px){.smpi-snip-detail-row{grid-template-columns:1fr}}
</style>
<script>
(function(){
  if (window.smpiSnipsReady) return; window.smpiSnipsReady = true;
  function post(root, params){
    return fetch(root.getAttribute('data-ajax-url') || window.ajaxurl, {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: params.toString()
    }).then(function(r){ return r.json(); });
  }
  function cap(s){ s = String(s||'fail'); return s.charAt(0).toUpperCase()+s.slice(1); }
  document.addEventListener('click', function(e){
    var ex = e.target.closest('[data-snippet-expand]');
    if (ex){
      var row = ex.closest('.smpi-snip-row');
      if (!row) return;
      var detail = row.nextElementSibling;
      var isOpen = detail && detail.classList.contains('smpi-snip-detail') && !detail.hasAttribute('hidden');
      var tbody = row.parentNode;
      tbody.querySelectorAll('.smpi-snip-detail').forEach(function(d){ d.setAttribute('hidden',''); });
      tbody.querySelectorAll('.smpi-snip-row').forEach(function(r){ r.classList.remove('is-open'); });
      tbody.querySelectorAll('.smpi-snip-expand-btn').forEach(function(b){ b.classList.remove('is-open'); });
      if (detail && !isOpen){
        detail.removeAttribute('hidden');
        row.classList.add('is-open');
        var b = row.querySelector('.smpi-snip-expand-btn');
        if (b) b.classList.add('is-open');
      }
      return;
    }
    var testBtn = e.target.closest('[data-snippet-test]');
    if (testBtn){
      var detailRow = testBtn.closest('.smpi-snip-detail');
      var dataRow = detailRow ? detailRow.previousElementSibling : null;
      var root = testBtn.closest('[data-smpi-snips]');
      if (!dataRow || !root) return;
      var id = dataRow.getAttribute('data-snippet-id');
      var p = new URLSearchParams();
      p.set('action', root.getAttribute('data-test-action'));
      p.set(root.getAttribute('data-nonce-field')||'nonce', root.getAttribute('data-nonce'));
      p.set('snippet_id', id);
      testBtn.disabled = true; var label = testBtn.textContent; testBtn.textContent = 'Testing...';
      post(root, p).then(function(res){
        var data = (res && res.data) ? res.data : res;
        var status = (data && data.status) ? data.status : 'fail';
        var pill = dataRow.querySelector('[data-snippet-test-pill]');
        if (pill){ pill.className = 'smpi-snip-pill ' + (status==='pass'?'is-ok':(status==='warn'?'is-warn':'is-bad')); pill.textContent = 'Test ' + cap(status); }
        var box = detailRow.querySelector('[data-snippet-test-rules]');
        if (box && data && data.rules){
          box.textContent = '';
          data.rules.forEach(function(ru){
            var d = document.createElement('div');
            d.className = 'smpi-snip-rule ' + (ru.passed?'is-pass':'is-fail');
            var s = document.createElement('span'); s.textContent = ru.passed?'PASS':'FAIL';
            d.appendChild(s); d.appendChild(document.createTextNode(ru.label||'Rule'));
            if (ru.required){ var em = document.createElement('em'); em.textContent = ' (required)'; d.appendChild(em); }
            box.appendChild(d);
          });
        }
      }).catch(function(){}).finally(function(){ testBtn.disabled=false; testBtn.textContent=label||'Run test'; });
      return;
    }
  });
  document.addEventListener('change', function(e){
    var input = e.target.closest('[data-snippet-toggle]');
    if (!input) return;
    var root = input.closest('[data-smpi-snips]');
    var row = input.closest('.smpi-snip-row');
    if (!root || !row) return;
    var id = row.getAttribute('data-snippet-id');
    var p = new URLSearchParams();
    p.set('action', root.getAttribute('data-toggle-action'));
    p.set(root.getAttribute('data-nonce-field')||'nonce', root.getAttribute('data-nonce'));
    p.set('snippet_id', id);
    p.set('enable', input.checked ? '1':'0');
    input.disabled = true;
    post(root, p).then(function(res){
      if (!res || !res.success){ throw new Error('save failed'); }
      var data = res.data || {};
      var pill = row.querySelector('[data-snippet-status]');
      if (pill){ pill.className = 'smpi-snip-pill ' + (data.enabled?'is-ok':'is-warn'); pill.textContent = data.enabled?'Active':'Inactive'; }
    }).catch(function(){ input.checked = !input.checked; }).finally(function(){ input.disabled=false; });
  });
})();
</script>
HTML;
    }
}
