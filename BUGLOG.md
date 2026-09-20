# SMP Publication Integration Bug Log

## SMP-MANIFEST-BUG-008 — Delivery manifest omitted article-audio support

- **Severity:** High
- **Status:** Patched for plugin `2.0.17`.
- **Owner:** Shared publication-manifest delivery capabilities.
- **Cause:** The manifest advertised WordPress delivery, taxonomy, media,
  scheduling, and revision support but omitted whether the separate SMP WP Text
  To Speech plugin was active. Publish therefore attempted narration on every
  site and spent roughly 12 seconds per article proving that unsupported sites
  lacked the plugin.
- **Correction:** The public manifest now exposes a generic boolean
  `delivery_capabilities.article_audio` derived from the loaded TTS plugin
  class. Activating or deactivating a plugin invalidates the cached manifest so
  the capability changes without waiting for unrelated content edits.
- **Regression:** The focused manifest fixture requires false without the TTS
  plugin, true when its runtime class is loaded, and cache invalidation hooks
  for plugin activation and deactivation.

---

## SMP-API-BUG-001 — External publishing was duplicated in two plugins

- **Status:** Patched for plugin `2.0.16`.
- **Owner:** External publication transport ownership.
- **Cause:** A generic post-submission bridge was added to SMP Publication Integration even though HWS Base Tools is the shared site-management plugin selected to own that boundary.
- **Correction:** Remove the SMP REST endpoint, dashboard control, runtime registration, and setting. Native WordPress REST remains owned by Publish, while the only plugin bridge is HWS Base Tools.
- **Regression:** Source and runtime inventories must contain no `smpi/v1/external-publishing` route or `external_publishing_enabled` SMP setting.

## SMP-MANIFEST-BUG-007 — Requested result size was mistaken for actual truncation

- **Status:** Patched for plugin `2.0.15`; the focused run exposed and corrected the constructor's older 25-result ceiling, and live SEO My Company acceptance is pending.
- **Owner:** Shared publication-manifest native widget-query bounds.
- **Cause:** The collector marked any unlimited or over-limit widget partial before seeing its result count. SEO My Company's category-capable `team-member` type has 24 homepage results, so the safe non-category shortcut in `2.0.14` correctly did not apply, but the old 12-result ceiling could not prove the finite result set was complete.
- **Correction:** Raise the per-widget evidence ceiling to 50 and execute oversized/unlimited queries with exactly one overflow sentinel (51 results). Results of 50 or fewer are complete; a returned 51st item proves truncation, is removed from evidence, and keeps the warning. Exact short widget limits remain unchanged, and the 24-query budget remains enforced.
- **Regression:** The focused manifest run reached the new overflow assertion and showed that the constructor still reduced the new 50-result default to its older 25-result ceiling. That exact cap was corrected to allow the bounded configuration; under the single-run boundary, the suite was not rerun. The fixture covers an unlimited 24-result category-capable grid, an unexecuted non-category grid, and 51-result overflow warnings for Elementor and Query Builder adapters.
- **Release boundary:** Source is not active until `2.0.15` is published, installed on SEO My Company, and its public manifest is complete without warnings.

## SMP-MANIFEST-BUG-006 — Non-category directory grids triggered article-query truncation

- **Status:** Released in plugin `2.0.14`; focused regression passed, but live SEO My Company correctly remained partial because its `team-member` type does expose the `category` taxonomy. Final completeness handling continues in BUG007.
- **Owner:** Shared publication-manifest native Elementor query adapter.
- **Cause:** Every Elementor Loop Grid was treated as possible article-category evidence. SEO My Company's unlimited `team-member` directory grid cannot contribute WordPress categories, but the generic 12-post article-query bound marked it partial before that distinction was made.
- **Correction:** Resolve explicit saved post types before native execution. When every statically declared post type exists and none has the `category` taxonomy, return complete empty category evidence without executing the grid. Unknown/current/any post types and custom query hooks retain bounded native execution and fail-closed warnings.
- **Regression:** `php tests/publication-manifest-unit.php` passed. The focused fixture covers an unlimited `team-member` grid and requires complete empty evidence with zero provider executions while retaining the existing oversized article-query truncation guard.
- **Release boundary:** `2.0.14` was installed on SEO My Company; its safe shortcut remained inactive for the category-capable content type, as required.

## SMP-MANIFEST-BUG-005 — Post-returning Jet Query Builder widgets were rejected

- **Status:** Patched for plugin `2.0.13`; the focused run reached a fixture expectation corrected after diagnosis, and live MediTech acceptance remains pending.
- **Owner:** Shared publication-manifest native JetEngine query adapter.
- **Cause:** The native collector accepted only Jet listing source `posts`. JetEngine changes a Listing Grid to source `query` when an exact Query Builder ID is selected, even when that saved query is a normal WordPress posts query.
- **Correction:** Resolve the widget's exact Query Builder ID through JetEngine's context-bound public API; accept only query type `posts` with no runtime-dependent values; clone the query; disable its cache; bind the existing result guard to that exact clone; and execute it with the same published-post, public-post-type, result-limit, state-restoration, and fail-closed rules. Non-post, dynamic, missing, mismatched, and unsupported query contexts remain unexecuted.
- **Regression:** The focused manifest fixture covers a bounded post-returning Query Builder result plus non-post and runtime-dependent rejection before execution. Its permitted run failed only because the low-level assertion omitted the reserved category that is intentionally removed by the higher manifest policy layer; that expectation was corrected without a second suite run.
- **Release boundary:** Source is not active until `2.0.13` is published, installed on MediTech Today, and its public manifest is complete without warnings.

## SMP-MANIFEST-BUG-004 — Duplicate-avoiding widgets lacked ordered prior results

- **Status:** Released in plugin `2.0.12`; corrected focused regression passed.
- **Owner:** Shared publication-manifest native widget-query adapter.
- **Cause:** The native adapter rejected every Elementor `*_avoid_duplicates=yes` query and reset `Module::$displayed_ids` to an empty array around each widget. Homepage traversal also skipped native execution for statically categorized prior widgets, so it could not reproduce Elementor's ordered preceding-result state from actual query results.
- **Correction:** Maintain a private, collection-scoped sequence of actual supported native post IDs; bind only that sequence to Elementor's displayed-ID state for each ordered widget query; restore the provider's preexisting global state in `finally`; and privately inspect statically categorized or responsive-hidden preceding queries without changing their public manifest provenance. Saved offsets, bounded limits, scoped query guards, visibility rules, and the public widget schema remain unchanged. Any failed, unsupported, truncated, or otherwise incomplete prior result makes a dependent query explicitly partial.
- **Regression:** The first focused run reached the new responsive-hidden assertion and exposed top-level filtering before private-history traversal. That exact defect was corrected, and the permitted rerun passed. The fixture covers a static preceding query plus a dependent native query, saved-offset preservation, collection reset, complete zero results, truncated and unsupported fail-closed behavior, category union/provenance, existing provider-state restoration, query budgets, and scoped internal-query isolation.
- **Release boundary:** Published source is not active on an affected site until the release owner installs and verifies plugin `2.0.12` there.

## SMP-MANIFEST-BUG-003 — Native query bounds touched internal provider lookups

- **Status:** Released in plugin `2.0.11`; live acceptance verified through `2.0.11`.
- **Cause:** A temporary `pre_get_posts` guard covered every query executed inside the widget API. Her Forward's one-card widget also loaded an internal Elementor query with an unlimited result setting; this falsely marked its article results truncated and could alter provider lookup semantics.
- **Correction:** Use the providers' public query-argument hooks to mark only the exact widget/renderer query. Clamp only matching queries, require the intended query to pass that guard, and remove both hooks in `finally`. Internal template lookups are untouched. No result limits are raised and no partial-result guard is weakened.
- **Regression:** Native fixture includes an unrelated unlimited Elementor-library query before the one-card post query and checks both unchanged lookup settings and complete article evidence.

## SMP-MANIFEST-BUG-002 — Unfiltered homepage widgets lacked native category evidence

- **Status:** Released in plugin `2.0.10`; live acceptance verified through `2.0.11`.
- **Owner:** Shared publication-manifest native widget-query adapter.
- **Cause:** Static query settings cannot enumerate categories returned by unfiltered Elementor/Jet widgets or Elementor custom hooks. The first native adapter draft also expanded short widget limits and did not bind/restore exact Jet listing state.
- **Correction:** Query supported Elementor Pro and native Jet post widgets through their public APIs without rendering; bind homepage/document/listing state; clamp effective query limits downward before execution; retain only published public-post evidence; restore context and filters in `finally`. Budgets, unsupported request-dependent queries, and truncation remain explicit partial warnings. No publication IDs, domains, taxonomy-wide fallback, or HTML scraping are used.
- **Regression:** `php tests/publication-manifest-unit.php` passed with native provider fixtures for exact limits, hook limits, context restoration, post visibility, budget, unsupported sources, manifest provenance, and reserved-category exclusion. Initial check identified a lost unresolved-query warning; the collector now preserves it on unresolved results and does not add it to successfully resolved empty result sets.
- **Release boundary:** Deploy Her Forward first and require the released Laravel compiler to accept its real public manifest before continuing sequential rollout.

## SMP-MANIFEST-BUG-001 — Elementor homepage manifests could be incomplete or include excluded categories

- **Status:** Patched, focused fixture-tested, and released in plugin `2.0.9`; awaiting target installation.
- **Owner:** Shared SMP publication-manifest collector.
- **Cause:** Template widgets were skipped, taxonomy operators were ignored, generic term-token parsing did not distinguish include from exclude controls, fully hidden widgets were treated as visible, and unresolved query behavior had no explicit completeness signal.
- **Correction:** Traverse nested Elementor templates with cycle/depth guards; preserve stable template-chain evidence IDs; interpret supported positive and negative taxonomy controls; exclude all-device-hidden widgets; retain navigation/footer and reserved-category policy; publish `homepage.collection_status` and machine-readable `homepage.collection_warnings`; expose native category descriptions as plain first-party input.
- **Regression proof:** `php tests/publication-manifest-unit.php` passes with nested-template, cycle, depth-limit, include/exclude, hidden-widget, unsupported-query, evidence-ID, category-description, navigation, and Digital Magazine fixtures.
- **Release note:** Source changes are not active on publication sites until the canonical release is versioned, published, installed, and the live manifest is read back.
