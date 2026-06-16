# SMP Publication Integration

WordPress plugin for Scale My Publication publication profiles.

## Identity

- Plugin slug: `smp-publication-integration`
- GitHub slug: `mikeyperes/smp-publication-integration`
- PHP namespace: `smp_publication_integration`
- Version: `0.4.3`

## Structure

- `smp-publication-integration.php`: canonical plugin bootstrap, dependency check, updater bootstrap. `initialization.php` is a legacy no-header loader for old installs.
- `GitHub_Updater.php`: HWS-style GitHub update integration.
- `src/Content`: site publication options, ACF fields, shortcodes, and publication schema.
- `src/Admin`: tabbed admin dashboard.
- `src/Support`: autoloading, dependencies, and field alias helpers.

## Admin Tabs

- Overview
- Publication Profiles
- Shortcodes
- Schema
- Reports
- Integrations

## Dependencies

- Required: HWS Base Tools.
- Optional but expected: Advanced Custom Fields Pro.
- Optional integration: SFPF Verified Profiles, via the `profile` post type for founders.

## Completed

- Updater flow fixed: SMP uses GitHub API version detection and HWS Base Tools post-install handling. Verified 0.3.8 to 0.3.9 updates on HerForward and Mashviral.

## 0.4.0 Updates

- Removed Managed Publications from user profile ACF bindings.
- Added Overview publication author search binding with selected profile card.
- Added publication metadata ACF fields and page creation AJAX flow.


## 0.4.1 Updates

- Removed active public `publication` CPT registration from the plugin boot flow.
- Moved publication metadata fields to the site-level ACF Publication Theme Options page.
- Rebuilt the Overview publication binding UI around a plain-language WordPress author search.
- Removed the advanced publication CPT selector from the Overview screen.

## 0.4.3 Updates

- Added publication-level MuckRack verification as a separate feature from author/journalist badges.
- Added Publication Theme Options ACF fields for publication MuckRack verification and URL.
- Added Features tab controls for publication verification text mode, placement, shortcode reporting, and author always-show override.
- Fixed AJAX persistence for MuckRack publication controls, including clearing empty placement multi-select values.

## Pending Work Queue

- Feature: optional post ACF field post_summary matching HerForward label Post Summary and ACF key reference field_65ab7ba0e849b; add as a separate SMP setting so sites can opt into registering it on posts.
- Feature: optional post ACF field post_faqs matching HerForward label Post FAQs and ACF key reference field_65ab7bc1e849c; add as a separate SMP setting so sites can opt into registering it on posts.
- Feature: table of contents for single posts; add a setting toggle named display table of contents in the single page, add a shortcode for the Elementor/widget area above single.php content, and make the toggle activate that shortcode output.
- Feature: highlight text override with enable/disable toggle, highlight background color, and highlight text color. When enabled, output cross-browser selection CSS for `::selection` and `::-moz-selection` so the site highlight colors override theme defaults.
