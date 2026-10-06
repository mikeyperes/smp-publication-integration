/**
 * SMP content generation in the post editor.
 *
 * window.smpiGeneration is the one client for excerpt, summary and FAQ jobs on
 * this screen. It starts jobs, polls while any is working, fills a field in
 * when its job finishes, and tells subscribers (the inline field controls
 * below and the Going Live checklist) about every change. It starts from the
 * states saved on the post, so a screen opened while Publish is still writing
 * shows the work in progress and picks up the result.
 */
(function ($) {
    "use strict";

    var cfg = window.smpiGenerationConfig;
    if (!cfg || !cfg.postId) {
        return;
    }

    var FIELDS = {
        excerpt: {host: "#postexcerpt .inside", field: "#excerpt", place: "afterField"},
        summary: {host: ".acf-field[data-name='post_summary']", field: ".acf-field[data-name='post_summary'] textarea", place: "afterHost"},
        faqs: {host: "[data-key='field_smpi_post_faq_accordion'], .acf-field-smpi-post-faq-accordion, .acf-field[data-name='post_faq_items']", field: "", place: "beforeHost"}
    };

    var states = {};
    var listeners = [];
    var timer = null;
    var clockOffset = cfg.now ? cfg.now - Date.now() / 1000 : 0;

    function serverNow() { return Date.now() / 1000 + clockOffset; }
    function syncClock(now) { if (now) { clockOffset = now - Date.now() / 1000; } }
    function request(data) { return $.post(cfg.ajaxUrl, $.extend({nonce: cfg.nonce, post_id: cfg.postId}, data)); }
    function errorData(xhr) { return (xhr && xhr.responseJSON && xhr.responseJSON.data) || {}; }
    function failed(previous, message) { return $.extend({}, previous, {status: "failed", message: message}); }

    function changed(previous, state) {
        var a = (previous.log || [])[0] || {}, b = (state.log || [])[0] || {};
        return previous.status !== state.status || previous.message !== state.message || previous.job_id !== state.job_id || a.time !== b.time || a.message !== b.message;
    }

    function update(next) {
        $.each(next || {}, function (target, state) {
            var previous = states[target] || {status: "idle"};
            states[target] = state;
            if (previous.status === "working" && state.status === "done" && Object.prototype.hasOwnProperty.call(state, "value")) {
                state.filled = paint(target, state.value);
            }
            if (changed(previous, state)) {
                listeners.forEach(function (listener) { listener(target, state, previous); });
            }
        });
        schedule();
    }

    function working() {
        return Object.keys(states).some(function (target) { return states[target].status === "working"; });
    }

    function schedule() {
        if (timer || !working()) {
            return;
        }
        timer = setTimeout(poll, cfg.pollMs || 4000);
    }

    function poll() {
        timer = null;
        request({action: "smpi_generation_state"}).done(function (response) {
            if (response && response.success) {
                syncClock(response.data.now);
                update(response.data.states);
                return;
            }
            schedule();
        }).fail(schedule);
    }

    function start(target) {
        update((function () { var o = {}; o[target] = $.extend({}, states[target], {status: "working", message: "Starting…", log: (states[target] || {}).log}); return o; })());
        return request({action: "smpi_generate_content", target: target}).then(function (response) {
            var data = (response && response.data) || {};
            syncClock(data.now);
            update(data.states);
            return states[target];
        }, function (xhr) {
            var data = errorData(xhr);
            var next = data.states || {};
            if (!next[target] || next[target].status !== "failed") {
                next[target] = failed(states[target], data.message || ("Could not start (HTTP " + ((xhr && xhr.status) || 0) + ")."));
            }
            update(next);
            return $.Deferred().reject(states[target].message).promise();
        });
    }

    function subscribe(listener) {
        listeners.push(listener);
        return function () { listeners = listeners.filter(function (item) { return item !== listener; }); };
    }

    /** Resolves when the target's job is done, rejects when it fails. */
    function settled(target) {
        var deferred = $.Deferred();
        var stop = subscribe(check);
        function check(name, state) {
            if (name !== target || state.status === "working") { return; }
            stop();
            if (state.status === "done") { deferred.resolve(state); } else { deferred.reject(state.message); }
        }
        check(target, states[target] || {status: "working"});
        return deferred.promise();
    }

    function run(target) {
        return start(target).then(function () { return settled(target); });
    }

    // ---- Filling fields in -------------------------------------------------

    function syncEditor(field, value) {
        field.val(value).trigger("input").trigger("change");
        var id = field.attr("id");
        var editor = id && window.tinymce ? window.tinymce.get(id) : null;
        if (editor) {
            editor.setContent(value || "");
            editor.fire("change");
            window.tinymce.triggerSave();
        }
    }

    function flash(target) {
        var host = $(FIELDS[target].host).first();
        host.removeClass("smpi-generation-filled");
        void (host[0] && host[0].offsetWidth);
        host.addClass("smpi-generation-filled");
    }

    function paint(target, value) {
        if (value === undefined || value === null || !FIELDS[target]) {
            return false;
        }
        var ok = target === "faqs" ? paintFaqs(value) : (function () {
            var field = $(FIELDS[target].field).first();
            if (!field.length) { return false; }
            syncEditor(field, value);
            return true;
        })();
        if (ok) { flash(target); }
        return ok;
    }

    function faqRows(repeater) {
        return repeater.find(".acf-row:not(.acf-clone)").filter(function () {
            return $(this).closest(".acf-field[data-name='post_faq_items']")[0] === repeater[0];
        });
    }

    function setFaqSubField(row, name, value) {
        var input = row.find(".acf-field[data-name='" + name + "']").first().find("textarea, input[type='text']").first();
        if (input.length) { syncEditor(input, value || ""); }
    }

    function paintFaqs(value) {
        var rows = Array.isArray(value) ? value : [];
        var repeater = $(".acf-field[data-name='post_faq_items']").first();
        var clone = repeater.find(".acf-row.acf-clone").first();
        if (!rows.length || !repeater.length || !clone.length) {
            return false;
        }
        faqRows(repeater).each(function () {
            var row = $(this);
            if (window.tinymce) {
                row.find("textarea.wp-editor-area").each(function () {
                    var editor = this.id ? window.tinymce.get(this.id) : null;
                    if (editor) { window.tinymce.remove(editor); }
                });
            }
            row.remove();
        });
        rows.forEach(function (item, index) {
            var row = clone.clone(false, false).removeClass("acf-clone").removeAttr("data-id").show();
            ["id", "for", "name"].forEach(function (attr) {
                row.find("[" + attr + "]").each(function () {
                    $(this).attr(attr, ($(this).attr(attr) || "").replace(/acfcloneindex/g, index));
                });
            });
            row.find("input, textarea, select, button").prop("disabled", false).removeAttr("disabled");
            clone.before(row);
            setFaqSubField(row, "question", item.question || "");
            setFaqSubField(row, "answer", item.answer || "");
            if (window.acf && window.acf.doAction) { window.acf.doAction("append", row); }
        });
        var schema = $("[data-key='field_smpi_post_faq_schema_enabled'] input[type=checkbox]").first();
        if (schema.length && !schema.is(":checked")) { schema.prop("checked", true).trigger("change"); }
        if (window.tinymce) { window.tinymce.triggerSave(); }
        if (window.acf && window.acf.doAction) { window.acf.doAction("change", repeater); }
        return faqRows(repeater).length === rows.length;
    }

    window.smpiGeneration = {
        start: start,
        run: run,
        settled: settled,
        subscribe: subscribe,
        state: function (target) { return states[target] || {status: "idle"}; },
        targets: function () { return Object.keys(FIELDS); }
    };

    // ---- Inline controls beside each field ---------------------------------

    var BUTTON = {excerpt: "Generate excerpt", summary: "Generate summary", faqs: "Generate FAQs"};

    function control(target) { return $("[data-smpi-generation-control='" + target + "']"); }

    function install(target) {
        var meta = FIELDS[target];
        var host = $(meta.host).first();
        if (!host.length || control(target).length) {
            return;
        }
        var box = $(
            "<div class='smpi-generation-control is-idle'>" +
            "<div class='smpi-generation-control__main'><div class='smpi-generation-actions'>" +
            "<button type='button' class='button' data-smpi-generate-target></button>" +
            "<span class='smpi-generation-status' aria-live='polite'></span></div>" +
            "<ol class='smpi-generation-log'></ol></div></div>"
        ).attr("data-smpi-generation-control", target);
        box.find("button").attr("data-smpi-generate-target", target).text(BUTTON[target]);
        if (meta.place === "beforeHost") { host.before(box); }
        else if (meta.place === "afterField" && $(meta.field).length) { $(meta.field).first().after(box); }
        else { host.after(box); }
    }

    function elapsed(state) {
        var seconds = Math.max(0, Math.round(serverNow() - (state.started_at || 0)));
        if (!state.started_at) { return ""; }
        return seconds < 60 ? seconds + "s" : Math.floor(seconds / 60) + "m " + (seconds % 60) + "s";
    }

    function render(target, state) {
        var box = control(target);
        if (!box.length) { return; }
        var isWorking = state.status === "working";
        var status = state.message || "";
        if (isWorking && elapsed(state)) { status += " · " + elapsed(state); }
        if (state.status === "done" && state.filled === false) { status += " Reload the page to see it here."; }
        box.removeClass("is-idle is-working is-done is-failed").addClass("is-" + (state.status || "idle"));
        box.find(".smpi-generation-status").text(status);
        box.find("button").prop("disabled", isWorking).text(isWorking ? "Writing…" : (state.status === "failed" ? "Try again" : BUTTON[target]));
        $(FIELDS[target].host).first().addClass("smpi-generation-target").toggleClass("is-generating", isWorking);
        var list = box.find(".smpi-generation-log").empty();
        (state.log || []).forEach(function (entry) {
            var time = String(entry.time || "").slice(11, 16);
            $("<li/>").toggleClass("is-error", entry.status === "error").append($("<time/>").text(time)).append(document.createTextNode(entry.message || "")).appendTo(list);
        });
    }

    $(function () {
        Object.keys(FIELDS).forEach(install);
        subscribe(render);
        update(cfg.states || {});
        $(document).on("click", "[data-smpi-generate-target]", function () {
            start($(this).attr("data-smpi-generate-target"));
        });
        setInterval(function () {
            Object.keys(states).forEach(function (target) {
                if (states[target].status === "working") { render(target, states[target]); }
            });
        }, 1000);
    });
}(jQuery));
