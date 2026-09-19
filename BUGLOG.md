# SMP Publication Integration Bug Log

## SMP-MANIFEST-BUG-001 — Elementor homepage manifests could be incomplete or include excluded categories

- **Status:** Patched, focused fixture-tested, and released in plugin `2.0.9`; awaiting target installation.
- **Owner:** Shared SMP publication-manifest collector.
- **Cause:** Template widgets were skipped, taxonomy operators were ignored, generic term-token parsing did not distinguish include from exclude controls, fully hidden widgets were treated as visible, and unresolved query behavior had no explicit completeness signal.
- **Correction:** Traverse nested Elementor templates with cycle/depth guards; preserve stable template-chain evidence IDs; interpret supported positive and negative taxonomy controls; exclude all-device-hidden widgets; retain navigation/footer and reserved-category policy; publish `homepage.collection_status` and machine-readable `homepage.collection_warnings`; expose native category descriptions as plain first-party input.
- **Regression proof:** `php tests/publication-manifest-unit.php` passes with nested-template, cycle, depth-limit, include/exclude, hidden-widget, unsupported-query, evidence-ID, category-description, navigation, and Digital Magazine fixtures.
- **Release note:** Source changes are not active on publication sites until the canonical release is versioned, published, installed, and the live manifest is read back.
