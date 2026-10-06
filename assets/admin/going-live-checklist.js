/**
 * Going Live checklist on the post editor.
 *
 * Excerpt, summary and FAQ items run through window.smpiGeneration, the same
 * jobs the inline field buttons use, so an item keeps showing "Writing…" after
 * a reload until Publish finishes. Text to speech is driven through the TTS
 * plugin's own button.
 */
jQuery(function ($) {
    "use strict";

    var root = $("#smpi-going-live-checklist");
    if (!root.length) {
        return;
    }
    var cfg = {ajaxUrl: root.data("ajax-url") || window.ajaxurl, nonce: root.data("nonce") || "", postId: parseInt(root.data("post-id"), 10) || 0};
    var generation = window.smpiGeneration || null;
    var contentTargets = generation ? generation.targets() : [];
    var labels = {};
    root.find("[data-glc-item]").each(function () { labels[$(this).data("glc-item")] = $(this).find("strong").text(); });

    function row(key) { return root.find("[data-glc-item='" + key + "']"); }
    function isContent(key) { return contentTargets.indexOf(key) !== -1; }

    function log(text, isError) {
        var box = root.find("[data-glc-log]");
        box.find(".is-empty").remove();
        var time = new Date().toLocaleTimeString([], {hour: "numeric", minute: "2-digit"});
        box.prepend($("<li/>").toggleClass("is-error", !!isError).text(time + " — " + text));
        root.find("[data-glc-log-count]").text("(" + box.children().length + ")");
    }

    function notice(text, ok) {
        var el = root.find("[data-glc-notice]");
        if (!text) { el.attr("hidden", true); return; }
        el.text(text).toggleClass("is-ok", !!ok).removeAttr("hidden");
    }

    function setState(key, state, message) {
        var item = row(key);
        if (!item.length) { return; }
        item.removeClass("is-done is-missing is-failed is-working is-checking").addClass("is-" + state);
        if (message) { item.find("[data-glc-msg]").text(message); }
        item.find("[data-glc-generate]").prop("disabled", state === "working").text(state === "failed" ? "Try again" : (state === "working" ? "Writing…" : "Generate"));
        var total = root.find("[data-glc-item]").length, done = root.find(".smpi-glc__row.is-done").length;
        root.find("[data-glc-progress]").text(done + " of " + total + " done").toggleClass("is-complete", done === total);
    }

    function fail(key, message) {
        setState(key, "failed", "Couldn't generate: " + message);
        log(labels[key] + ": " + message, true);
    }

    function refreshStatus() {
        return $.post(cfg.ajaxUrl, {action: "smpi_going_live_checklist_status", nonce: cfg.nonce, post_id: cfg.postId}).done(function (response) {
            var data = (response && response.data) || {};
            if (!response || !response.success) { notice(data.message || "Could not check the checklist status."); return; }
            $.each(data.items || {}, function (key, item) {
                if (row(key).hasClass("is-working") || row(key).hasClass("is-failed")) { return; }
                setState(key, item.state === "done" ? "done" : "missing", item.message);
            });
        }).fail(function (xhr) { notice("Could not check the checklist status (HTTP " + (xhr.status || 0) + ")."); });
    }

    /** Mirrors a generation job onto its checklist row. */
    function follow(key, state, previous) {
        if (!row(key).length) { return; }
        if (state.status === "working") {
            setState(key, "working", state.message || "Writing…");
            return;
        }
        if (previous.status !== "working") {
            return;
        }
        if (state.status === "done") {
            row(key).removeClass("is-working");
            refreshStatus().always(function () { log(labels[key] + (state.filled === false ? " written and saved. Reload to see it in its field." : " written and filled in on this screen.")); });
        } else if (state.status === "failed") {
            fail(key, state.message || "Publish could not write it.");
        }
    }

    function generateTts() {
        var deferred = $.Deferred(), key = "tts", tries = 0;
        setState(key, "working", "Generating…");
        log("Generating " + labels[key] + ".");
        $(".hexa-tts-generate-post").first().trigger("click");
        (function poll() {
            setTimeout(function () {
                row(key).removeClass("is-working");
                refreshStatus().always(function () {
                    if (row(key).hasClass("is-done")) { log(labels[key] + " generated."); deferred.resolve(); return; }
                    if (++tries >= 60) { fail(key, "Audio is still being created. Check back in a few minutes."); deferred.reject(); return; }
                    row(key).addClass("is-working");
                    poll();
                });
            }, 3000);
        })();
        return deferred.promise();
    }

    function generate(key) {
        if (isContent(key)) {
            log(labels[key] + ": asked Publish to write it.");
            return generation.run(key);
        }
        if (key === "tts" && $(".hexa-tts-generate-post").length) {
            return generateTts();
        }
        var reason = (key === "excerpt" || key === "summary" || key === "faqs") ? "Content generation is turned off in SMP settings." : "This item can't be generated here. Use View to check it.";
        fail(key, reason);
        return $.Deferred().reject().promise();
    }

    function view(key) {
        var parts = String(row(key).data("glc-view-selector") || "").split(",");
        var target = $();
        for (var i = 0; i < parts.length && !target.length; i++) { target = $(parts[i].trim()).first(); }
        if (!target.length) { notice(labels[key] + " isn't shown on this screen."); return; }
        $("html, body").animate({scrollTop: Math.max(0, target.offset().top - 90)}, 240);
        target.addClass("smpi-go-live-highlight");
        setTimeout(function () { target.removeClass("smpi-go-live-highlight"); }, 1600);
    }

    root.toggleClass("is-exempt", root.find("[name=smpi_go_live_exempt]").is(":checked"));
    root.on("change", "[name=smpi_go_live_exempt]", function () { root.toggleClass("is-exempt", this.checked); });
    root.on("click", "[data-glc-view]", function () { view($(this).closest("[data-glc-item]").data("glc-item")); });
    root.on("click", "[data-glc-generate]", function () { notice(""); generate($(this).closest("[data-glc-item]").data("glc-item")); });
    root.on("click", "[data-glc-all]", function () {
        var all = $(this).prop("disabled", true).text("Working…");
        var keys = root.find("[data-glc-item]").not(".is-done, .is-working").map(function () { return $(this).data("glc-item"); }).get();
        var failed = 0;
        notice("");
        var runs = keys.map(function (key) {
            return generate(key).then(null, function () { failed++; return $.Deferred().resolve().promise(); });
        });
        $.when.apply($, runs).always(function () {
            all.prop("disabled", false).text("Complete all");
            if (!keys.length) { notice("Everything is already done.", true); }
            else if (failed) { notice(failed + " of " + keys.length + " couldn't be generated. The reason is shown on each item."); }
            else { notice("All items are done.", true); }
        });
    });

    if (generation) {
        generation.subscribe(follow);
        contentTargets.forEach(function (key) {
            var state = generation.state(key);
            if (state.status === "working") { follow(key, state, {status: "idle"}); }
        });
    }
    refreshStatus();
});
