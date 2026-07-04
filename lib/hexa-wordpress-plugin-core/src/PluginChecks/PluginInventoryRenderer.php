<?php

namespace Hexa\PluginCore\PluginChecks;

use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;

final class PluginInventoryRenderer {
    /**
     * @param array<int,PluginCheckDefinition|array<string,mixed>> $definitions
     * @param array<string,mixed> $args
     */
    public function render( array $definitions, array $args = [] ): string {
        $args = $this->args( $args );

        ob_start();
        CoreUi::render_assets();
        DynamicButton::render_assets();
        ?>
        <div class="hpc-ui hpc-plugin-inventory" data-hpc-plugin-inventory data-ajax-url="<?php echo esc_url( $args['ajax_url'] ); ?>" data-status-action="<?php echo esc_attr( $args['actions']['status'] ); ?>" data-refresh-action="<?php echo esc_attr( $args['actions']['refresh'] ); ?>" data-install-action="<?php echo esc_attr( $args['actions']['install_activate'] ); ?>" data-activate-action="<?php echo esc_attr( $args['actions']['activate'] ); ?>" data-nonce-field="<?php echo esc_attr( $args['nonce_field'] ); ?>" data-nonce="<?php echo esc_attr( $args['nonce'] ); ?>">
            <?php echo $this->assets(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php
            echo CoreUi::collapsible(
                [
                    'title'       => (string) $args['title'],
                    'open'        => (bool) $args['open'],
                    'persist_key' => (string) $args['persist_key'],
                    'meta_html'   => $this->summary_meta( $definitions ),
                    'body_html'   => $this->body_html( $definitions, $args ),
                ]
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<int,PluginCheckDefinition|array<string,mixed>> $definitions
     * @param array<string,mixed> $args
     */
    public function render_content( array $definitions, array $args = [] ): string {
        $args         = $this->args( $args );
        $items        = PluginCheckService::normalize_definitions( $definitions );
        $statuses     = PluginCheckService::statuses( $items );
        $summary      = PluginCheckService::summary( $statuses );
        $status_by_id = [];

        foreach ( $statuses as $status ) {
            $status_by_id[ (string) $status['id'] ] = $status;
        }

        ob_start();
        ?>
        <div class="hpc-plugin-inventory-summary">
            <?php echo $this->summary_item( 'Ready', (int) $summary['ready'], 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo $this->summary_item( 'Missing', (int) $summary['missing'], 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo $this->summary_item( 'Inactive', (int) $summary['inactive'], 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo $this->summary_item( 'Outdated', (int) $summary['outdated'], 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo $this->summary_item( 'Configured', (int) $summary['total'], 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>

        <div class="hpc-plugin-inventory-table-wrap">
            <table class="hpc-plugin-inventory-table">
                <thead>
                    <tr>
                        <th>Plugin</th>
                        <th>Status</th>
                        <?php if ( ! empty( $args['columns']['auto_update'] ) ) : ?>
                            <th>Auto-Update</th>
                        <?php endif; ?>
                        <?php if ( ! empty( $args['columns']['version'] ) ) : ?>
                            <th>Version</th>
                        <?php endif; ?>
                        <?php if ( ! empty( $args['columns']['source'] ) ) : ?>
                            <th>Source</th>
                        <?php endif; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( [] === $items ) : ?>
                        <tr><td colspan="<?php echo esc_attr( (string) $this->column_count( $args ) ); ?>"><?php echo esc_html( (string) $args['empty_text'] ); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ( $items as $definition ) : ?>
                        <?php echo $this->row_html( $definition, $status_by_id[ $definition->id ] ?? PluginCheckService::status( $definition ), $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="hpc-plugin-inventory-actions">
            <?php echo DynamicButton::render( [ 'label' => 'Refresh checks', 'working_label' => 'Refreshing...', 'success_label' => 'Refreshed', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-plugin-inventory-refresh' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php if ( ! empty( $args['show_install_all'] ) ) : ?>
                <?php echo DynamicButton::render( [ 'label' => 'Install and activate missing', 'working_label' => 'Processing...', 'success_label' => 'Processed', 'class' => 'hpc-button', 'attrs' => [ 'data-plugin-inventory-install-all' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
            <?php if ( function_exists( 'admin_url' ) ) : ?>
                <?php echo CoreUi::external_link( admin_url( 'plugins.php' ), 'Open plugins', 'hpc-button secondary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<int,PluginCheckDefinition|array<string,mixed>> $definitions
     * @param array<string,mixed> $args
     */
    private function body_html( array $definitions, array $args ): string {
        ob_start();
        ?>
        <?php if ( '' !== (string) $args['description'] ) : ?>
            <p class="hpc-plugin-inventory-description"><?php echo esc_html( (string) $args['description'] ); ?></p>
        <?php endif; ?>
        <div data-plugin-inventory-content>
            <?php echo $this->render_content( $definitions, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <section class="hpc-plugin-inventory-log" aria-live="polite">
            <div class="hpc-plugin-inventory-log-head">
                <strong>Activity log</strong>
                <button type="button" class="hpc-button secondary" data-plugin-inventory-clear-log>Clear</button>
            </div>
            <pre data-plugin-inventory-log>Ready.</pre>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $status
     * @param array<string,mixed> $args
     */
    private function row_html( PluginCheckDefinition $definition, array $status, array $args ): string {
        $installed = ! empty( $status['installed'] );
        $active    = ! empty( $status['active'] );
        $required  = $this->is_required( $definition, $status );
        $row_class = trim(
            ( $installed ? 'is-installed' : 'is-missing' )
            . ' '
            . ( $required ? 'is-required' : 'is-optional' )
            . ' '
            . ( ! $installed && $required ? 'is-required-missing' : '' )
        );

        ob_start();
        ?>
        <tr class="<?php echo esc_attr( $row_class ); ?>" data-plugin-inventory-row data-plugin-id="<?php echo esc_attr( $definition->id ); ?>" data-plugin-installed="<?php echo $installed ? '1' : '0'; ?>" data-plugin-active="<?php echo $active ? '1' : '0'; ?>" data-plugin-required="<?php echo $required ? '1' : '0'; ?>">
            <td class="hpc-plugin-inventory-plugin-cell">
                <div class="hpc-plugin-inventory-title">
                    <?php echo $this->icon( $installed, $installed ? 'Plugin installed' : 'Plugin missing' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <strong><?php echo esc_html( $definition->name ); ?></strong>
                    <?php echo $this->requirement_badge( $required ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div class="hpc-plugin-inventory-meta">
                    <code><?php echo esc_html( (string) $status['plugin_file'] ?: $definition->plugin_file ?: $definition->slug ); ?></code>
                    <?php if ( '' !== $definition->notes ) : ?>
                        <span><?php echo esc_html( wp_strip_all_tags( $definition->notes ) ); ?></span>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <?php echo $this->status_text( $active, $active ? 'Active' : 'Inactive' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </td>
            <?php if ( ! empty( $args['columns']['auto_update'] ) ) : ?>
                <td>
                    <?php if ( $installed ) : ?>
                        <?php echo $this->status_text( ! empty( $status['auto_update'] ), ! empty( $status['auto_update'] ) ? 'Enabled' : 'Disabled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php else : ?>
                        <span class="hpc-plugin-inventory-muted">Not installed</span>
                    <?php endif; ?>
                </td>
            <?php endif; ?>
            <?php if ( ! empty( $args['columns']['version'] ) ) : ?>
                <td>
                    <?php if ( ! empty( $status['version'] ) ) : ?>
                        <strong><?php echo esc_html( (string) $status['version'] ); ?></strong>
                        <?php if ( ! empty( $status['update_available'] ) ) : ?>
                            <br><span class="hpc-plugin-inventory-update">Update: <?php echo esc_html( (string) ( $status['new_version'] ?: 'available' ) ); ?></span>
                        <?php endif; ?>
                    <?php else : ?>
                        <span class="hpc-plugin-inventory-muted">None</span>
                    <?php endif; ?>
                </td>
            <?php endif; ?>
            <?php if ( ! empty( $args['columns']['source'] ) ) : ?>
                <td><?php echo $this->source_html( $definition, $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
            <?php endif; ?>
            <td class="hpc-plugin-inventory-action-cell">
                <?php echo $this->actions_html( $definition, $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $status
     */
    private function actions_html( PluginCheckDefinition $definition, array $status ): string {
        if ( empty( $status['installed'] ) ) {
            if ( ! empty( $status['installable'] ) ) {
                return DynamicButton::render(
                    [
                        'label'         => 'Install and activate',
                        'working_label' => 'Installing...',
                        'success_label' => 'Installed',
                        'error_label'   => 'Failed',
                        'class'         => 'hpc-button',
                        'attrs'         => [
                            'data-plugin-inventory-action' => 'install_activate',
                            'data-plugin-id'               => $definition->id,
                        ],
                    ]
                );
            }

            if ( ! empty( $status['download_url'] ) ) {
                return CoreUi::external_link( (string) $status['download_url'], (string) $status['download_label'], 'hpc-button secondary' );
            }

            return '<span class="hpc-plugin-inventory-muted">Manual install required</span>';
        }

        if ( empty( $status['active'] ) && ! empty( $definition->checks['active'] ) ) {
            return DynamicButton::render(
                [
                    'label'         => 'Activate',
                    'working_label' => 'Activating...',
                    'success_label' => 'Activated',
                    'error_label'   => 'Failed',
                    'class'         => 'hpc-button secondary',
                    'attrs'         => [
                        'data-plugin-inventory-action' => 'activate',
                        'data-plugin-id'               => $definition->id,
                    ],
                ]
            );
        }

        if ( empty( $definition->checks['active'] ) ) {
            return '<span class="hpc-plugin-inventory-muted">No action required</span>';
        }

        if ( ! empty( $status['update_available'] ) && function_exists( 'admin_url' ) ) {
            return CoreUi::external_link( admin_url( 'update-core.php' ), 'Open updates', 'hpc-button secondary' );
        }

        return '<span class="hpc-plugin-inventory-ready">' . $this->icon( true, 'Ready' ) . ' Ready</span>';
    }

    /**
     * @param array<string,mixed> $status
     */
    private function source_html( PluginCheckDefinition $definition, array $status ): string {
        if ( 'github' === $definition->source && '' !== $definition->github_repo ) {
            $repo = PluginProvisioner::normalize_github_repo( $definition->github_repo );
            return CoreUi::external_link( 'https://github.com/' . $repo, $repo, 'hpc-plugin-inventory-source-link' );
        }

        if ( 'wordpress_org' === $definition->source && ! empty( $status['download_url'] ) ) {
            return CoreUi::external_link( (string) $status['download_url'], 'WordPress.org', 'hpc-plugin-inventory-source-link' );
        }

        if ( 'pro' === $definition->source ) {
            return '<span class="hpc-plugin-inventory-source-text">Pro/manual</span>';
        }

        if ( 'must_use' === $definition->source ) {
            return '<span class="hpc-plugin-inventory-source-text">Must-use</span>';
        }

        if ( 'dropin' === $definition->source ) {
            return '<span class="hpc-plugin-inventory-source-text">Drop-in</span>';
        }

        return '<span class="hpc-plugin-inventory-source-text">Manual</span>';
    }

    private function status_text( bool $passed, string $label ): string {
        return '<span class="hpc-plugin-inventory-status ' . ( $passed ? 'is-pass' : 'is-fail' ) . '">' . $this->icon( $passed, $label ) . ' ' . esc_html( $label ) . '</span>';
    }

    /**
     * @param array<string,mixed> $status
     */
    private function is_required( PluginCheckDefinition $definition, array $status ): bool {
        if ( array_key_exists( 'required', $status ) ) {
            return (bool) $status['required'];
        }

        return property_exists( $definition, 'required' ) ? (bool) $definition->required : true;
    }

    private function icon( bool $passed, string $label ): string {
        $path    = $passed
            ? 'M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z'
            : 'M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z';
        $viewbox = $passed ? '0 0 448 512' : '0 0 384 512';

        return '<span class="hpc-plugin-inventory-fa ' . ( $passed ? 'hpc-plugin-inventory-fa-check' : 'hpc-plugin-inventory-fa-xmark' ) . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '" role="img"><svg class="hpc-plugin-inventory-fa-svg" viewBox="' . esc_attr( $viewbox ) . '" aria-hidden="true" focusable="false"><path d="' . esc_attr( $path ) . '"></path></svg></span>';
    }

    private function requirement_badge( bool $required ): string {
        return '<span class="hpc-plugin-inventory-requirement ' . ( $required ? 'is-required' : 'is-optional' ) . '">' . ( $required ? 'Required' : 'Optional' ) . '</span>';
    }

    private function summary_item( string $label, int $count, string $tone ): string {
        return '<span class="hpc-plugin-inventory-summary-item is-' . esc_attr( $tone ) . '"><strong>' . $count . '</strong> ' . esc_html( $label ) . '</span>';
    }

    /**
     * @param array<string,mixed> $args
     */
    private function column_count( array $args ): int {
        $count = 3; // Plugin, Status, Action.

        if ( ! empty( $args['columns']['auto_update'] ) ) {
            $count++;
        }
        if ( ! empty( $args['columns']['version'] ) ) {
            $count++;
        }
        if ( ! empty( $args['columns']['source'] ) ) {
            $count++;
        }

        return $count;
    }

    /**
     * @param array<int,PluginCheckDefinition|array<string,mixed>> $definitions
     */
    private function summary_meta( array $definitions ): string {
        $summary = PluginCheckService::summary( PluginCheckService::statuses( $definitions ) );
        $tone    = empty( $summary['attention'] ) ? 'success' : 'warning';

        return CoreUi::pill( (int) $summary['ready'] . ' / ' . (int) $summary['total'] . ' ready', $tone );
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    private function args( array $args ): array {
        $action_prefix = isset( $args['action_prefix'] ) ? trim( (string) $args['action_prefix'], '_' ) : 'hexa_plugin_inventory';
        $actions       = isset( $args['actions'] ) && is_array( $args['actions'] ) ? $args['actions'] : [];
        $columns       = isset( $args['columns'] ) && is_array( $args['columns'] ) ? $args['columns'] : [];

        $normalized = array_merge(
            [
                'title'            => 'Plugin Inventory',
                'description'      => 'Check installation, activation, and update status for this plugin set.',
                'ajax_url'         => function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '',
                'nonce'            => '',
                'nonce_field'      => 'nonce',
                'open'             => true,
                'persist_key'      => 'hexa-plugin-inventory',
                'empty_text'       => 'No plugins configured.',
                'show_install_all' => true,
            ],
            $args
        );
        $normalized['columns'] = array_merge(
            [
                'auto_update' => false,
                'version'     => true,
                'source'      => true,
            ],
            $columns
        );
        $normalized['actions'] = [
            'status'           => $actions['status'] ?? $action_prefix . '_status',
            'refresh'          => $actions['refresh'] ?? $action_prefix . '_refresh',
            'install_activate' => $actions['install_activate'] ?? $action_prefix . '_install_activate',
            'activate'         => $actions['activate'] ?? $action_prefix . '_activate',
        ];

        return $normalized;
    }

    private function assets(): string {
        static $done = false;
        if ( $done ) {
            return '';
        }
        $done = true;

        return <<<'HTML'
<style>
.hpc-plugin-inventory{display:grid;gap:14px}
.hpc-plugin-inventory-description{color:#3f4d63;font-size:13px;line-height:1.55;margin:0 0 14px!important;max-width:960px}
.hpc-plugin-inventory-summary{align-items:center;display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px}
.hpc-plugin-inventory-summary-item{align-items:center;background:#eef2ff;border:1px solid #dbe4ff;border-radius:999px;color:#2944ad;display:inline-flex;font-size:12px;font-weight:800;gap:4px;line-height:1;padding:7px 10px}
.hpc-plugin-inventory-summary-item.is-success{background:#eaf8ef;border-color:#ccefd7;color:var(--hpc-green)}
.hpc-plugin-inventory-summary-item.is-warning{background:#fff7e0;border-color:#f5df9c;color:var(--hpc-amber)}
.hpc-plugin-inventory-summary-item.is-danger{background:#fff0f2;border-color:#ffd0d8;color:var(--hpc-red)}
.hpc-plugin-inventory-table-wrap{border:1px solid var(--hpc-line);border-radius:8px;overflow:auto}
.hpc-plugin-inventory-table{border-collapse:collapse;width:100%}
.hpc-plugin-inventory-table th{background:#f8fafc;border-bottom:1px solid var(--hpc-line);color:#253650;font-size:12px;font-weight:900;letter-spacing:.02em;padding:11px 12px;text-align:left;text-transform:uppercase;white-space:nowrap}
.hpc-plugin-inventory-table td{border-bottom:1px solid #edf1f6;padding:12px;vertical-align:middle}
.hpc-plugin-inventory-table tr:last-child td{border-bottom:0}
.hpc-plugin-inventory-table tr.is-missing td{background:#f8fafc;color:#546179}
.hpc-plugin-inventory-table tr.is-required-missing td:first-child{box-shadow:inset 4px 0 0 var(--hpc-red)}
.hpc-plugin-inventory-table tr.is-required-missing .hpc-plugin-inventory-title strong{color:#3f4d63}
.hpc-plugin-inventory-plugin-cell{min-width:280px}
.hpc-plugin-inventory-title{align-items:center;display:flex;gap:8px;margin:0 0 7px}
.hpc-plugin-inventory-title strong{font-size:14px}
.hpc-plugin-inventory-requirement{border-radius:999px;display:inline-flex;font-size:10px;font-weight:900;line-height:1;padding:5px 7px;text-transform:uppercase}
.hpc-plugin-inventory-requirement.is-required{background:#fff0f2;border:1px solid #ffd0d8;color:var(--hpc-red)}
.hpc-plugin-inventory-requirement.is-optional{background:#eef2ff;border:1px solid #dbe4ff;color:#2944ad}
.hpc-plugin-inventory-meta{display:grid;gap:5px}
.hpc-plugin-inventory-meta code{background:#eef0f2;border-radius:5px;color:#2f3a4a;font-size:12px;padding:2px 5px;word-break:break-all}
.hpc-plugin-inventory-meta span{color:var(--hpc-muted);font-size:12px;line-height:1.35}
.hpc-plugin-inventory-status{align-items:center;display:inline-flex;font-size:13px;font-weight:900;gap:5px;white-space:nowrap}
.hpc-plugin-inventory-status.is-pass{color:var(--hpc-green)}
.hpc-plugin-inventory-status.is-fail{color:var(--hpc-red)}
.hpc-plugin-inventory-fa{align-items:center;border-radius:999px;display:inline-flex;height:18px;justify-content:center;line-height:1;min-width:18px;width:18px}
.hpc-plugin-inventory-fa-check{color:var(--hpc-green)}
.hpc-plugin-inventory-fa-xmark{color:var(--hpc-red)}
.hpc-plugin-inventory-fa-svg{display:block;fill:currentColor;height:14px;width:14px}
.hpc-plugin-inventory-source-link{color:var(--hpc-blue);font-size:12px;font-weight:800;text-decoration:none;white-space:nowrap}
.hpc-plugin-inventory-source-text,.hpc-plugin-inventory-muted{color:var(--hpc-muted);font-size:12px}
.hpc-plugin-inventory-update{color:var(--hpc-amber);font-size:12px;font-weight:800}
.hpc-plugin-inventory-action-cell{min-width:150px}
.hpc-plugin-inventory-ready{align-items:center;color:var(--hpc-green);display:inline-flex;font-weight:900;gap:5px;white-space:nowrap}
.hpc-plugin-inventory-actions{align-items:center;display:flex;flex-wrap:wrap;gap:10px;margin:13px 0 0}
.hpc-plugin-inventory-log{background:#111827;border-radius:8px;color:#dbe7f3;margin-top:14px;overflow:hidden}
.hpc-plugin-inventory-log-head{align-items:center;border-bottom:1px solid #263244;display:flex;justify-content:space-between;padding:10px 12px}
.hpc-plugin-inventory-log-head strong{color:#fff}
.hpc-plugin-inventory-log-head .hpc-button{padding:7px 10px}
.hpc-plugin-inventory-log pre{background:transparent;color:#dbe7f3;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;margin:0;max-height:240px;overflow:auto;padding:12px;white-space:pre-wrap}
@media(max-width:900px){.hpc-plugin-inventory-table th,.hpc-plugin-inventory-table td{padding:10px}.hpc-plugin-inventory-action-cell{min-width:130px}}
</style>
<script>
(function(){
  if(window.HexaPluginInventoryReady)return; window.HexaPluginInventoryReady=true;
  function log(root,message){
    var box=root.querySelector('[data-plugin-inventory-log]');
    if(!box)return;
    var stamp=new Date().toLocaleTimeString();
    var current=(box.textContent||'').trim();
    if(current==='Ready.') current='';
    box.textContent=(current?current+"\n":"")+"["+stamp+"] "+message;
    box.scrollTop=box.scrollHeight;
  }
  function body(root,action,extra){
    var params=new URLSearchParams();
    params.set('action',action);
    params.set(root.dataset.nonceField||'nonce',root.dataset.nonce||'');
    Object.keys(extra||{}).forEach(function(key){params.set(key,extra[key]);});
    return params;
  }
  function post(root,action,extra){
    return fetch(root.dataset.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body(root,action,extra).toString()}).then(function(response){return response.json();});
  }
  function replaceContent(root,payload){
    var target=root.querySelector('[data-plugin-inventory-content]');
    if(target&&payload&&payload.content_html)target.innerHTML=payload.content_html;
    if(window.hexaPluginCoreInitPersistentDetails)window.hexaPluginCoreInitPersistentDetails(root);
    if(payload&&payload.log)payload.log.forEach(function(line){log(root,line);});
  }
  function handleFailure(root,button,error,fallback){
    log(root,'ERROR: '+(error&&error.message?error.message:fallback));
    if(button&&window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(button,'Failed');
  }
  function refresh(root,button,useUpdateCache){
    if(button&&window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(button);
    return post(root,useUpdateCache?root.dataset.refreshAction:root.dataset.statusAction,{}).then(function(response){
      if(!response||!response.success)throw new Error((response&&response.data&&response.data.message)||'Refresh failed');
      replaceContent(root,response.data);
      if(button&&window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.success(button,'Refreshed');
      return response.data;
    }).catch(function(error){handleFailure(root,button,error,'Refresh failed');throw error;});
  }
  document.addEventListener('click',function(event){
    var root=event.target.closest('[data-hpc-plugin-inventory]');
    if(!root)return;
    var clear=event.target.closest('[data-plugin-inventory-clear-log]');
    if(clear){var box=root.querySelector('[data-plugin-inventory-log]');if(box)box.textContent='Ready.';return;}
    var refreshButton=event.target.closest('[data-plugin-inventory-refresh]');
    if(refreshButton){refresh(root,refreshButton,true);return;}
    var installAllButton=event.target.closest('[data-plugin-inventory-install-all]');
    if(installAllButton){
      var actions=Array.prototype.slice.call(root.querySelectorAll('[data-plugin-inventory-action="install_activate"],[data-plugin-inventory-action="activate"]')).map(function(button){return {id:button.dataset.pluginId,mode:button.dataset.pluginInventoryAction};});
      if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(installAllButton);
      log(root,'Starting plugin processing for '+actions.length+' plugin(s).');
      actions.reduce(function(promise,item){
        return promise.then(function(){
          var action=item.mode==='activate'?root.dataset.activateAction:root.dataset.installAction;
          log(root,'Processing '+item.id+'.');
          return post(root,action,{plugin_id:item.id}).then(function(response){
            if(!response||!response.success)throw new Error((response&&response.data&&response.data.message)||('Failed: '+item.id));
            if(response.data&&response.data.log)response.data.log.forEach(function(line){log(root,line);});
          });
        });
      },Promise.resolve()).then(function(){return refresh(root,null,false);}).then(function(){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.success(installAllButton,'Processed');}).catch(function(error){handleFailure(root,installAllButton,error,'Plugin processing failed');});
      return;
    }
    var actionButton=event.target.closest('[data-plugin-inventory-action]');
    if(actionButton){
      var actionName=actionButton.dataset.pluginInventoryAction==='activate'?root.dataset.activateAction:root.dataset.installAction;
      if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(actionButton);
      log(root,'Processing '+(actionButton.dataset.pluginId||'plugin')+'.');
      post(root,actionName,{plugin_id:actionButton.dataset.pluginId||''}).then(function(response){
        if(!response||!response.success)throw new Error((response&&response.data&&response.data.message)||'Plugin action failed');
        replaceContent(root,response.data);
        if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.success(actionButton,'Done');
      }).catch(function(error){handleFailure(root,actionButton,error,'Plugin action failed');});
    }
  });
})();
</script>
HTML;
    }
}
