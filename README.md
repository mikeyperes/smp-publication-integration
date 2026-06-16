# SMP Publication Integration

WordPress plugin for Scale My Publication publication profiles.

## Identity

- Plugin slug: `smp-publication-integration`
- GitHub slug: `mikeyperes/smp-publication-integration`
- PHP namespace: `smp_publication_integration`
- Version: `0.4.7`

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

## 0.4.5 Updates

- Added a plugin-registered Publication Theme Options quotes repeater with quote, name, and title subfields.

## 0.4.7 Updates

- Added opt-in post ACF add-ons for Post Summary and Post FAQs with independent AJAX feature toggles.
- Registers the supplied Post - Header ACF group on post and imported-news only when at least one add-on toggle is enabled.

## Pending Work Queue

- Dependency UX: in the Founder Profiles options area, detect whether SMP Verified Profiles is active, whether the `profile` CPT is registered by `register_profile_custom_post_type`, and whether the Verified Profiles ACF/profile field structures are enabled. Show a clear disclaimer with status rows and direct links to the Verified Profiles Snippets tab to activate missing pieces.
- Admin consolidation: remove the standalone `options-general.php?page=smpi-publication-options` Publication Theme Options page from the visible Settings menu and render the publication ACF fields inside `Settings > SMP Publication Integration`. Preserve existing option field names and saved values.
- Admin tab: add a `Brand` tab that reads HWS Base Tools brand assets/highlight colors for display and includes an edit link to `options-general.php?page=hws-core-tools&tab=brand-assets`.
- Integrations tab: expand plugin reporting with plugin name, expected slug, GitHub URL, installed status, active status, local version, repository version, update state, and AJAX refresh per plugin.
- Integrations tab: add AJAX download/install/activate flow for missing plugins with a real-time task-specific activity log.
- Integrations tab plugin list: `mikeyperes/hexa-pr-wire-distributor`, `mikeyperes/smp-publication-integration`, `mikeyperes/smp-core-podcast-integration`, `mikeyperes/smp-verified-profiles`, `mikeyperes/smp-contributor-network`, and `mikeyperes/sfpf-person-profile-integration`.
- Features tab: redesign every feature card. Replace checkmark-style pseudo controls with real enable/disable toggles wherever the feature can be enabled or disabled.
- Features tab: replace native multi-select controls with clearer placement/context selectors such as chips, segmented controls, or checkbox cards.
- Features tab: make activity logs task-specific to each feature card. Do not show unrelated global settings activity inside cards like Rank Math breadcrumb check.
- Features tab: redesign the full card layout so controls, custom ACF adjustments, instructions, code examples, test proof, and activity are visually separated and easier to scan.
- MuckRack verified authors: add an icon color picker.
- MuckRack verified authors: add an icon style chooser with visual selectable previews for `circle with check inside` and `plain check`.
- MuckRack verified authors: replace the native style dropdown with a UI that shows what `Tooltip icon` and `Inline text` look like before selection.
- Feature: table of contents for single posts; add a setting toggle named display table of contents in the single page, add a shortcode for the Elementor/widget area above single.php content, and make the toggle activate that shortcode output.
- Feature: highlight text override with enable/disable toggle, highlight background color, and highlight text color. When enabled, output cross-browser selection CSS for `::selection` and `::-moz-selection` so the site highlight colors override theme defaults.
