# SMP Publication Integration

WordPress plugin for Scale My Publication publication profiles.

## Identity

- Plugin slug: `smp-publication-integration`
- GitHub slug: `mikeyperes/smp-publication-integration`
- PHP namespace: `smp_publication_integration`
- Version: `0.6.5`

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
- Added the inline photo treatment, caption, Post Summary style, and Post FAQ style work item to the Pending Work Queue.

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
- Added the Shortcodes tab dynamic user selector/request to the Pending Work Queue.

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

## Pending Work Queue
- Feature: inline photo styles for posts and `press-release` CPTs. Import visual treatments 1, 2, 4, and 5 from `https://herforward.com/inline-redesign/`, use MashViral caption styling from `https://mashviral.com/article-styles/`, provide visual selectable previews, and include a `No style` option.
- Feature: Post Summary and Post FAQs style pickers. Import the Summary and FAQ designs from `https://mashviral.com/article-styles/`, show visual previews below the ACF add-on controls, allow click-to-apply selection, and include a `No style` option for each.
- Shortcodes tab: add a dynamic user selector that loads one row per shortcode/field with the selected user, the exact shortcode, and the rendered shortcode value for that user.
- Shortcodes tab: scan and flag external shortcode providers. Import or list Verified Profiles discovered/provider shortcodes from `smp_vp_discover_shortcodes()` / `get_verified_profile_shortcodes()` when `smp-verified-profiles` is active, and flag HWS Base Tools shortcodes such as `[founder id="url_facebook"]` / `[company id="subtitle"]` as generated outside SMP. Clearly separate profile CPT shortcodes like `[get_profile_field field="title"]` from user.php author shortcodes.
- Shortcodes tab: add complete author.php/user-author shortcode coverage. Include missing user-field shortcodes such as `[author_title]`, `[author_subtitle]`, `[author_linkedin]`, `[author_website]`, `[author_crunchbase]`, `[author_muckrack]` aliases including `muck_rack_url`, and any URL/social/additional fields shown on the WordPress user edit screen; show each shortcode with the current value for the selected user and the ACF/meta source.
- Features tab / MuckRack verified authors: re-check narrower responsive widths after the full-width report fix and clean up any remaining status-label wrapping if it appears.

- Dependency UX: in the Founder Profiles options area, detect whether SMP Verified Profiles is active, whether the `profile` CPT is registered by `register_profile_custom_post_type`, and whether the Verified Profiles ACF/profile field structures are enabled. Show a clear disclaimer with status rows and direct links to the Verified Profiles Snippets tab to activate missing pieces.
- Admin tab: add a `Brand` tab that reads HWS Base Tools brand assets/highlight colors for display and includes an edit link to `options-general.php?page=hws-core-tools&tab=brand-assets`.
- Integrations tab: expand plugin reporting with plugin name, expected slug, GitHub URL, installed status, active status, local version, repository version, update state, and AJAX refresh per plugin.
- Integrations tab: add AJAX download/install/activate flow for missing plugins with a real-time task-specific activity log.
- Integrations tab: revise plugin action buttons so impossible actions are hidden or disabled based on plugin state. Missing plugins must not show Update, Activate, Deactivate, or Delete; show Download/Install only when a GitHub repo/package is available. Installed inactive plugins should show Activate/Delete and Update only when a version comparison exists. Active plugins should show Deactivate and Update only when a real update exists; Delete should be guarded or hidden for active/required dependencies. Every action should refresh the row via AJAX and write to a task-specific activity log.
- Features tab: add one-row visual design selectors for Table of Contents, inline photo treatments, Post Summary/article summary, and Post FAQ blocks for single.php. Each selector must show the actual rendered design inside the selectable row, include a No style option, and avoid duplicate Visual examples sections. Import inline photo treatments 1, 2, 4, and 5 from https://herforward.com/inline-redesign/ and caption/Summary/FAQ styles from https://mashviral.com/article-styles/.
- Integrations tab plugin list: `mikeyperes/hexa-pr-wire-distributor`, `mikeyperes/smp-publication-integration`, `mikeyperes/smp-core-podcast-integration`, `mikeyperes/smp-verified-profiles`, `mikeyperes/smp-contributor-network`, and `mikeyperes/sfpf-person-profile-integration`.
- MuckRack verified authors: add an icon color picker.
- MuckRack verified authors: add an icon style chooser with visual selectable previews for `circle with check inside` and `plain check`.
- MuckRack verified authors: replace the native style dropdown with a UI that shows what `Tooltip icon` and `Inline text` look like before selection.
- Feature: table of contents for single posts; add a setting toggle named display table of contents in the single page, add a shortcode for the Elementor/widget area above single.php content, and make the toggle activate that shortcode output.
- Feature: highlight text override with enable/disable toggle, highlight background color, and highlight text color. When enabled, output cross-browser selection CSS for `::selection` and `::-moz-selection` so the site highlight colors override theme defaults.
