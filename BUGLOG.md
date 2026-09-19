# SMP Publication Integration Bug Log

## SMP-MANIFEST-BUG-004 — Duplicate-avoiding widgets lacked ordered prior results

- **Status:** Released in plugin `2.0.12`; focused regression remains pending after the final traversal correction.
- **Owner:** Shared publication-manifest native widget-query adapter.
- **Cause:** The native adapter rejected every Elementor `*_avoid_duplicates=yes` query and reset `Module::$displayed_ids` to an empty array around each widget. Homepage traversal also skipped native execution for statically categorized prior widgets, so it could not reproduce Elementor's ordered preceding-result state from actual query results.
- **Correction:** Maintain a private, collection-scoped sequence of actual supported native post IDs; bind only that sequence to Elementor's displayed-ID state for each ordered widget query; restore the provider's preexisting global state in `finally`; and privately inspect statically categorized or responsive-hidden preceding queries without changing their public manifest provenance. Saved offsets, bounded limits, scoped query guards, visibility rules, and the public widget schema remain unchanged. Any failed, unsupported, truncated, or otherwise incomplete prior result makes a dependent query explicitly partial.
- **Regression:** `php tests/publication-manifest-unit.php` reached the new responsive-hidden assertion and failed because top-level excluded nodes were filtered before the private-history traversal. That exact traversal defect was corrected; the fixture also covers a static preceding query plus a dependent native query, saved-offset preservation, collection reset, complete zero results, truncated and unsupported fail-closed behavior, category union/provenance, existing provider-state restoration, query budgets, and scoped internal-query isolation. It was not rerun under the single-pass check boundary.
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
