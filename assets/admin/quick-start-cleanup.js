(function(window, document){
    'use strict';

    if (window.SmpiQuickStartCleanupWorkflow) return;

    function init(config){
        config = config || {};
        var root = document.getElementById(config.rootId || '');
        if (!root || root.dataset.smpiQuickCleanupReady === '1' || !root.hexaChecklistApi) return;

        root.dataset.smpiQuickCleanupReady = '1';
        var api = root.hexaChecklistApi;

        function text(value){ return api.text(value); }
        function esc(value){ return api.escapeHtml(value); }
        function css(value){ return api.cssSelectorValue(value); }
        function safeStatus(status){
            status = text(status || 'queued').toLowerCase().replace(/[^a-z_-]/g, '');
            return status || 'queued';
        }
        function statusBadge(status, label){
            status = safeStatus(status);
            label = label || ({queued:'Pending', deleting:'Deleting', deleted:'Deleted', failed:'Failed', preserved:'Kept', skipped:'Kept'}[status] || status);
            var icon = '<span class="smpi-qc-status-icon">-</span>';
            if (status === 'deleting') icon = '<span class="spinner is-active"></span>';
            if (status === 'deleted') icon = '<span class="smpi-qc-status-icon">&#10003;</span>';
            if (status === 'failed') icon = '<span class="smpi-qc-status-icon">X</span>';
            return '<span class="smpi-qc-status ' + esc(status) + '">' + icon + '<span>' + esc(label) + '</span></span>';
        }
        function chevron(){
            return '<span class="smpi-qc-chevron" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>';
        }
        function workflowInputs(row){
            var inputs = api.collectRowInputs(row);
            return {
                keep_recent: inputs[config.keepInput] || config.defaultKeep || '10',
                confirmation: inputs[config.confirmationInput] || ''
            };
        }
        function mediaHtml(row){
            var media = Array.isArray(row.media) ? row.media : [];
            var initialStatus = row.cleanup_status === 'preserved' ? 'preserved' : 'queued';
            var initialLabel = initialStatus === 'preserved' ? 'Kept' : 'Pending';
            if (!media.length) return '<div class="smpi-qc-media-meta">No featured or inline media detected.</div>';
            return '<div class="smpi-qc-media">' + media.map(function(item){
                var id = text(item.id || '');
                return '<div class="smpi-qc-media-row" data-qc-media data-media-id="' + esc(id).replace(/"/g, '&quot;') + '" data-media-status="' + esc(initialStatus) + '">'
                    + statusBadge(initialStatus, initialLabel)
                    + '<div><div class="smpi-qc-media-title">#' + esc(id) + ' ' + esc(item.title || 'Media') + '</div>'
                    + '<div class="smpi-qc-media-meta">' + esc(item.source || 'media') + '</div></div></div>';
            }).join('') + '</div>';
        }
        function postHtml(row){
            var status = row.cleanup_status === 'preserved' ? 'preserved' : 'queued';
            var label = status === 'preserved' ? 'Kept' : 'Pending';
            var postId = text(row.id || '');
            return '<div class="smpi-qc-post" data-qc-post data-post-id="' + esc(postId).replace(/"/g, '&quot;') + '" data-cleanup-status="' + esc(status) + '">'
                + '<div class="smpi-qc-post-main"><div><div class="smpi-qc-title">#' + esc(postId) + ' ' + esc(row.title || '(untitled)') + '</div>'
                + '<div class="smpi-qc-meta">' + esc(row.published_label || '') + ' | ' + esc(row.status || '') + ' | ' + esc(row.slug || '') + '</div>'
                + '<div class="smpi-qc-meta" data-qc-post-message>' + esc(row.cleanup_label || label) + '</div></div>'
                + '<div data-qc-post-status>' + statusBadge(status, label) + '</div></div>'
                + mediaHtml(row) + '</div>';
        }
        function listHtml(rows, emptyText){
            rows = rows || [];
            if (!rows.length) return '<div class="smpi-qc-post"><div class="smpi-qc-media-meta">' + esc(emptyText) + '</div></div>';
            return rows.map(postHtml).join('');
        }
        function renderPlan(row, plan){
            var target = api.reportTarget(row);
            if (!target) return;
            var rows = Array.isArray(plan.rows) ? plan.rows : [];
            var queued = rows.filter(function(item){ return item && item.cleanup_status !== 'preserved'; });
            var kept = rows.filter(function(item){ return item && item.cleanup_status === 'preserved'; });
            var deleteCount = parseInt(plan.delete_count || 0, 10) || 0;
            var mediaCount = parseInt(plan.delete_media_count || plan.media_count || 0, 10) || 0;
            var warning = plan.has_more ? '<span class="smpi-qc-pill danger">More posts exist than the visible plan limit</span>' : '';
            target.innerHTML = '<div class="smpi-qc-panel" data-qc-panel>'
                + '<div class="smpi-qc-overview"><div><strong>Article Cleanup Progress</strong><span>Newest posts are protected first. Older posts and their detected media are processed one at a time.</span></div>'
                + '<div class="smpi-qc-summary"><span class="smpi-qc-pill danger">' + esc(deleteCount) + ' to delete</span><span class="smpi-qc-pill">' + esc(kept.length) + ' kept</span><span class="smpi-qc-pill">' + esc(mediaCount) + ' media found</span>' + warning + '</div></div>'
                + '<details class="smpi-qc-window" data-qc-window open><summary class="smpi-qc-window-head"><div class="smpi-qc-window-title"><strong>Working Window</strong><span>Queued, deleting, deleted, and failed posts remain visible while cleanup runs.</span></div><div class="smpi-qc-summary"><span class="smpi-qc-pill danger">' + esc(deleteCount) + ' queued</span>' + chevron() + '</div></summary>'
                + '<div class="smpi-qc-body"><div class="smpi-qc-live"><strong data-qc-live-summary>Ready to delete queued posts.</strong><span data-qc-live-detail>Each post and media item updates as it is processed.</span></div>'
                + '<section class="smpi-qc-section"><div class="smpi-qc-section-label"><strong>Queued / Processed Posts</strong><span>' + esc(queued.length) + ' rows</span></div><div class="smpi-qc-list" data-qc-list>' + listHtml(queued, 'No regular posts are queued for deletion.') + '</div></section></div></details>'
                + '<details class="smpi-qc-kept"><summary class="smpi-qc-section-head"><div class="smpi-qc-section-title"><strong>Kept Posts</strong><span>Newest posts protected by the Posts to keep value.</span></div><div class="smpi-qc-summary"><span class="smpi-qc-pill">' + esc(kept.length) + ' kept</span>' + chevron() + '</div></summary><div class="smpi-qc-list">' + listHtml(kept, 'No posts were protected by the newest-post rule.') + '</div></details></div>';
            target.hidden = false;
        }
        function panel(row){
            var target = api.reportTarget(row);
            return target ? target.querySelector('[data-qc-panel]') : null;
        }
        function postNode(row, postId){
            var host = panel(row);
            return host ? host.querySelector('[data-qc-post][data-post-id="' + css(postId) + '"]') : null;
        }
        function nextQueued(row){
            var host = panel(row);
            return host ? host.querySelector('[data-qc-post][data-cleanup-status="queued"]') : null;
        }
        function setSummary(row, summary, detail){
            var host = panel(row);
            if (!host) return;
            var summaryNode = host.querySelector('[data-qc-live-summary]');
            var detailNode = host.querySelector('[data-qc-live-detail]');
            if (summaryNode) summaryNode.textContent = summary || '';
            if (detailNode) detailNode.textContent = detail || '';
        }
        function setPostStatus(row, postId, status, label, message){
            var post = postNode(row, postId);
            if (!post) return null;
            status = safeStatus(status);
            post.dataset.cleanupStatus = status;
            var badge = post.querySelector('[data-qc-post-status]');
            var messageNode = post.querySelector('[data-qc-post-message]');
            if (badge) badge.innerHTML = statusBadge(status, label);
            if (messageNode && message) messageNode.textContent = message;
            return post;
        }
        function setMediaState(post, status, label){
            if (!post) return;
            status = safeStatus(status);
            post.querySelectorAll('[data-qc-media]').forEach(function(media){
                media.dataset.mediaStatus = status;
                var badge = media.querySelector('.smpi-qc-status');
                if (badge) badge.outerHTML = statusBadge(status, label);
            });
        }
        function applyMediaStatuses(row, postId, statuses){
            var post = postNode(row, postId);
            if (!post) return;
            (statuses || []).forEach(function(item){
                var media = post.querySelector('[data-qc-media][data-media-id="' + css(item.id || '') + '"]');
                if (!media) return;
                var status = safeStatus(item.status || 'skipped');
                var label = status === 'deleted' ? 'Deleted' : (status === 'failed' ? 'Failed' : 'Kept');
                media.dataset.mediaStatus = status;
                var badge = media.querySelector('.smpi-qc-status');
                var meta = media.querySelector('.smpi-qc-media-meta');
                if (badge) badge.outerHTML = statusBadge(status, label);
                if (meta) meta.textContent = text(item.source || 'media') + (item.message ? ' | ' + text(item.message) : '');
            });
        }
        function applyPreserved(row, ids){
            (ids || []).forEach(function(id){
                var post = setPostStatus(row, id, 'preserved', 'Kept', 'Protected as one of the newest posts.');
                setMediaState(post, 'preserved', 'Kept');
            });
        }
        function applyResult(row, result){
            result = result || {};
            var status = safeStatus(result.status || 'deleted');
            var post = setPostStatus(row, result.id || '', status, status === 'failed' ? 'Failed' : 'Deleted', result.message || status);
            applyMediaStatuses(row, result.id || '', result.media_status || []);
            if (post && status === 'failed' && !(result.media_status || []).length) setMediaState(post, 'failed', 'Failed');
        }
        async function run(row){
            if (!api.validateRowInputs(row, true)) {
                api.setRowState(row, 'failed', 'Needs Input');
                api.addLog({level:'error', message:'Required cleanup input is missing or invalid.', context:{step_id:config.stepId}});
                api.refreshInputState();
                return false;
            }

            var inputs = workflowInputs(row);
            var totals = {deleted:0, failed:0, media:0, batches:0};
            var exclude = [];
            var planned = 0;
            api.setRowState(row, 'running', 'Scanning');
            api.clearReport(row);
            api.addLog({level:'warning', message:'Building article cleanup plan.', context:{keep_recent:inputs.keep_recent}});

            try {
                var plan = await api.postAction(config.scanAction, inputs);
                api.addLogs(plan.log);
                renderPlan(row, plan);
                planned = parseInt(plan.delete_count || 0, 10) || 0;
                if (!planned) {
                    setSummary(row, 'No posts need deletion.', 'The newest-post rule leaves no older regular posts to delete.');
                    api.setRowState(row, 'success', 'No deletion needed');
                    return true;
                }

                api.setRowState(row, 'running', 'Deleting');
                while (nextQueued(row)) {
                    var next = nextQueued(row);
                    var postId = next.dataset.postId || '';
                    totals.batches++;
                    setPostStatus(row, postId, 'deleting', 'Deleting', 'Deleting post and detected media now.');
                    setMediaState(next, 'deleting', 'Deleting');
                    setSummary(row, 'Deleting post #' + postId + ' (' + totals.batches + ' of ' + planned + ').', 'Featured image and inline media update under the post as they are deleted.');

                    var result = await api.postAction(config.batchAction, {
                        keep_recent: inputs.keep_recent,
                        confirmation: inputs.confirmation,
                        exclude_ids: exclude
                    });
                    api.addLogs(result.log);
                    applyPreserved(row, result.preserved_ids || []);
                    (result.post_results || []).forEach(function(item){ applyResult(row, item); });
                    exclude = result.exclude_ids || exclude;
                    totals.deleted += parseInt(result.deleted_count || 0, 10) || 0;
                    totals.failed += parseInt(result.failed_count || 0, 10) || 0;
                    totals.media += parseInt(result.deleted_media_count || 0, 10) || 0;

                    if (result.has_more && !nextQueued(row)) {
                        setSummary(row, 'More matching posts remain beyond the visible plan.', 'Reload the cleanup plan and run again to continue.');
                        api.setRowState(row, 'failed', 'More posts remain');
                        return false;
                    }
                }

                if (totals.failed > 0) {
                    setSummary(row, 'Cleanup finished with failures.', totals.deleted + ' posts deleted, ' + totals.media + ' media items deleted, ' + totals.failed + ' posts failed.');
                    api.setRowState(row, 'failed', 'Failed');
                    return false;
                }

                setSummary(row, 'Cleanup complete and verified.', totals.deleted + ' posts deleted and ' + totals.media + ' media items deleted.');
                api.setRowState(row, 'success', 'Deleted');
                api.addLog({level:'success', message:'Article cleanup finished.', context:totals});
                return true;
            } catch (error) {
                var activePanel = panel(row);
                if (activePanel) {
                    activePanel.querySelectorAll('[data-qc-post][data-cleanup-status="deleting"]').forEach(function(post){
                        setPostStatus(row, post.dataset.postId || '', 'failed', 'Failed', error.message || 'Delete failed.');
                        setMediaState(post, 'failed', 'Failed');
                    });
                }
                setSummary(row, 'Cleanup failed.', error.message || 'AJAX request failed.');
                api.setRowState(row, 'failed', error.message || 'Failed');
                api.addLog({level:'error', message:error.message || 'Article cleanup failed.', context:{step_id:config.stepId}});
                return false;
            }
        }

        root.addEventListener('hexa:checklist:run', function(event){
            var detail = event.detail || {};
            if (detail.scope !== 'step' || detail.stepId !== config.stepId) return;
            detail.handled = true;
            detail.promise = run(detail.row);
        });
    }

    window.SmpiQuickStartCleanupWorkflow = {init:init};
})(window, document);
