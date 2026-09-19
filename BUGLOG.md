# SMP Publication Integration Bug Log

## SMP-MANIFEST-BUG-002 — Unfiltered homepage widgets lacked native category evidence

- **Status:** Released in plugin `2.0.10`; Her Forward live acceptance pending.
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
