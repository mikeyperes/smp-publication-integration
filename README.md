# SMP Publication Integration

WordPress plugin for Scale My Publication publication profiles.

## Identity

- Plugin slug: `smp-publication-integration`
- GitHub slug: `mikeyperes/smp-publication-integration`
- PHP namespace: `smp_publication_integration`
- Version: `0.6.38`

## Structure

- `smp-publication-integration.php`: canonical plugin bootstrap, dependency check, updater bootstrap. `initialization.php` is a legacy no-header loader for old installs.
- `GitHub_Updater.php`: legacy compatibility shim. GitHub update detection now delegates to bundled Hexa WordPress Plugin Core.
- `lib/hexa-wordpress-plugin-core`: vendored Hexa WordPress Plugin Core library used for shared updater, admin-AJAX guard, tab, activity log, and plugin provisioning primitives.
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

- Bundled Hexa WordPress Plugin Core and registered the Hexa Core tab through dashboard filters.
- Replaced the standalone GitHub updater with `Hexa\PluginCore\PluginUpdates`.
- Replaced repeated AJAX nonce/capability checks with `Hexa\PluginCore\WpAdminAjax\AjaxGuard`.
- Replaced the SMP activity log writer with `Hexa\PluginCore\ActivityLog` permanent option storage.
- Replaced Integrations plugin install/update mechanics with `Hexa\PluginCore\PluginProvisioning` and `Hexa\PluginCore\PluginUpdates`.
- Replaced SMP admin-AJAX action registration with `Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry` and request parsing with `AjaxRequest`.
- Replaced the Shortcodes debugger table with `Hexa\PluginCore\ShortcodeRegistry\ShortcodeDisplayRenderer`.
- Updater flow fixed: SMP uses GitHub API version detection and Hexa WordPress Plugin Core post-install handling.

## 0.6.38 Updates

- Replaced Hexa Core admin FAQ rendering in `[smp_post_faqs]` with frontend-safe SMP FAQ markup so admin CSS cannot print as visible FAQ text.
- Sanitized FAQ answers before display while preserving allowed editorial HTML.

## 0.6.36 Updates

- Added schema graph connection coverage for homepage ItemList entries, article pages, FAQPage nodes, images, authors, taxonomy fallback mappings, and publisher references.
- Added schema-safe fallbacks for missing post authors and image dimensions, plus category/tag based article type fallback for press releases, sponsored content, opinion, analysis, and reportage.

## 0.6.35 Updates

- Added schema-object, current-page, and Schema.org validator links to each Article Type radio option.
- Kept the Article Type selector predefined and single-choice only while making validation paths visible in the editor.

## 0.6.23 Updates

- Integrated bundled Hexa WordPress Plugin Core v0.10.0.
- Added SMP dashboard filters for core tab registration and rendering.
- Swapped updater, AJAX guard, activity logging, GitHub version lookup, and plugin install/update mechanics to shared core classes.

## 0.6.24 Updates

- Prepended the SMP Hexa core autoloader so SMP resolves `Hexa\PluginCore` classes from its bundled core instead of depending on HWS Base Tools load order.

## 0.6.26 Updates

- Added the opt-in Article type schema selector feature toggle.
- Converted `smpi_article_type` to a hierarchical, radio-only, predefined taxonomy UI when enabled.
- Hid the taxonomy when disabled and removed the free-text Add term workflow from post editors.

## 0.6.25 Updates

- Updated bundled Hexa WordPress Plugin Core to v0.11.0.
- Swapped SMP admin-AJAX actions to the shared core action registry and sanitized request object.
- Swapped the Shortcodes tab debugger to the shared core shortcode display renderer with descriptions, examples, parameters, and rendered output.

## 0.6.7 Updates

- Removed the plugin-owned author user ACF group for MuckRack fields. `muckrack_verified` and `muckrack_url` are now owned by `hws-base-tools`; this plugin only reads them for display and shortcodes.

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


## 0.6.4 Updates
- Added MuckRack author checkmark size and publication verification text size controls.
- Added state-aware Integrations tab action buttons so missing plugins no longer show impossible actions.
- Added selectable single-row design controls for table of contents, inline photo treatments, Post Summary, and Post FAQs.
- Added frontend style output for selected TOC, inline figure, summary, and FAQ treatments.

## 0.6.0 Updates
- Fixed MuckRack verified author automatic placement so Elementor-stripped server-side author markup no longer blocks visible SVG injection.
- Cleaned up MuckRack author and publication feature controls so style rows are one-per-line and contain their own compact visual previews without duplicate visual-example sections.
- Tightened single-post author auto-injection to the top byline area and kept manual shortcode placement available.

## 0.5.9 Updates

- Tightened MuckRack Elementor injection so publication below-author placement ignores lower author bio/profile and related-article author links after the post content.
- Removed author-footer verification from the default MuckRack author placement contexts so publication footer verification does not stack with an author footer block by default.
- Added the inline photo treatment, caption, Post Summary style, and Post FAQ style work item to the Implementation Queue.

## 0.5.8 Updates

- Removed ACF tab controls from the Publication Options field group so all publication fields render in one continuous settings page.
- Replaced MuckRack author verification icons with the supplied inline SVG paths for solid circle, outline circle, and plain check styles.
- Fixed MuckRack verified publication below-author placement for MashViral Elementor heading author links and verified both frontend placements with Puppeteer.

## 0.5.5 Updates

- Replaced handmade MuckRack author checkmarks with selectable badge markup.
- Added Elementor-aware MuckRack author and publication placement fallbacks.
- Refreshed the Features tab markup after Ajax saves so selected states and previews match immediately.
- Exposed copy-ready MuckRack author shortcodes directly in the feature card.

## 0.5.4 Updates

- Fixed ACF WYSIWYG fields inside AJAX-loaded Publication Options by running the ACF append lifecycle after tab content swaps.
- Prevented stale TinyMCE instances from surviving tab changes before the next tab fragment is inserted.

## 0.5.1 Updates

- Added AJAX-loaded admin settings tabs so switching SMP tabs swaps server-rendered tab content without a full WordPress page reload.
- Added the Shortcodes tab dynamic user selector/request to the Implementation Queue.

## 0.5.0 Updates

- Changed Publication Pages starter/template text fields from plain textareas to WordPress WYSIWYG editors.
- Added new-tab enable links for missing Verified Profiles profile CPT and ACF snippets in Founder Profiles dependency warnings.

## 0.4.9 Updates

- Rebuilt Features tab placement controls so each context is one row with plain-language descriptions.
- Added MuckRack verified author visual examples for tooltip and inline text, clearer selected states, and icon previews.
- Added MuckRack verified publication display style and accent color controls with block, compact, and minimalist previews.

## 0.4.8 Updates

- Moved publication/theme option fields into `Settings > SMP Publication Integration > Publication Options` and removed the standalone `smpi-publication-options` ACF settings page registration.
- Changed ACF from a hard required dependency to a recommended dependency; SMP still boots without ACF and shows admin guidance where fields are unavailable.
- Added Verified Profiles readiness checks for founder selection: plugin active, Profile content type active, and Profile ACF fields enabled.
- Redesigned the Features tab cards with enable/disable toggles, checkbox-card placement controls, MuckRack icon color/style controls, Table of Contents controls, and feature-specific activity logs.
- Added `[smp_table_of_contents]` plus optional automatic single-post Table of Contents insertion above content.
- Added a Brand tab that reports HWS Base Tools highlight background/text colors and links to the HWS Brand Assets editor.
- Changed settings tabs to server-render only the active tab so ACF forms and heavy controls do not load on unrelated tabs.
- Fixed the MuckRack verified authors feature report to use a full-width responsive proof section instead of overflowing the card grid.
- Expanded the Integrations catalog to include Hexa PR Wire, SMP Publication Integration, SMP Core Podcast Integration, SMP Verified Profiles, SMP Contributor Network, and SFPF Person Profile Integration.
- Fixed dependency detection for live HWS Base Tools and Hexa PR Wire installs whose active plugin files use slug-matched main files instead of `initialization.php`.
- Updated Integrations reporting to display full `https://github.com/...` repository URLs instead of only repository slugs.

## 0.4.7 Updates

- Added opt-in post ACF add-ons for Post Summary and Post FAQs with independent AJAX feature toggles.
- Registers the supplied Post - Header ACF group on post and imported-news only when at least one add-on toggle is enabled.

## 0.6.19 Updates

- Completed the Pages tab create/select flow: Create New Page now creates or reuses a WordPress page, assigns it immediately, displays ID/status/author/created/modified/permalink, and supports inline slug updates over AJAX.
- Completed the Shortcodes tab debugger: select a WordPress author by name, username, or email and refresh shortcode rows over AJAX with provider, source, exact shortcode, and rendered value.
- Expanded author shortcode coverage to include author_title, author_subtitle, author_linkedin, author_website, author_crunchbase, author_email, and the author_muck_rack alias.
- External provider shortcodes are now flagged as external instead of executed inside the SMP debugger.

## Implementation Queue

- No active README-tracked implementation queue remains. Optimization settings rerooting is intentionally parked in the UI until target values are supplied.
