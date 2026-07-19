# SMP Publication Integration

WordPress plugin for Scale My Publication publication profiles.

## Identity

- Plugin slug: `smp-publication-integration`
- GitHub slug: `mikeyperes/smp-publication-integration`
- PHP namespace: `smp_publication_integration`
- Version: `0.6.196`

## Architecture

- `Bootstrap`: host lifecycle and Core module orchestration.
- `Settings`: canonical `smpi_settings` persistence and sanitization.
- `Admin/Navigation`: flat tab registry and legacy route resolution.
- `Admin/Ajax`: guarded admin action implementation behind the stable `Admin\Ajax` facade.
- `Admin/Dashboard`: dashboard implementation behind the stable `Admin\Dashboard` facade.
- `StructuredData`: schema graph generation behind the stable `Content\Schema` facade.
- `Authorship`: author assignment, query, lifecycle, and Elementor rendering services.
- `Content`: compatibility-facing editorial modules and public shortcodes.
- `Support`: integration adapters and stable legacy helpers.

The bundled `Hexa\PluginCore` package is registered through the shared package resolver. One selected Core root owns the namespace when multiple Hexa plugins are active.

## 0.6.196 Updates

- Updated the vendored Hexa WP Core to 0.19.44 with separate policy, installation, and runtime status states.
- Required plugins now show a green satisfied policy when their checks pass.
- Restricted forbidden policy to the explicit JetEngine and Simple Local Avatars entries; absent forbidden entries remain visible as compliant.
- Installed plugins outside SMP policy are no longer inferred to be forbidden.

## 0.6.195 Updates

- Removed SMP-specific host-tab layout CSS so the registered tabs use Hexa WP Core's native wrapped tab component without any horizontal-scroll implementation.

## 0.6.194 Updates

- Registered every SMP dashboard tab as a Hexa WP Core `TabDefinition` in the shared `TabRegistry`, including its renderer and capability.
- Restored the native wrapped, multi-row Core tab layout and removed horizontal tab scrolling.

## 0.6.193 Updates

- Replaced grouped admin areas and secondary navigation with one ordered, horizontally scrollable tab row while preserving legacy admin URLs.

## 0.6.192 Updates

- Removed the misplaced single-author fallback. Existing Elementor author structures remain the sole source of single-post author layout.

## 0.6.191 Updates

- Corrected the Quick Start H2/H3 preset so applying the heading feature enables its selected frontend style.
- Added a one-time, signature-scoped repair for sites that received the defective disabled heading preset.

## 0.6.190 Updates

- Added the Block Editorial missing single-post author fallback regression to the implementation queue. No author rendering behavior changed in this release.

## 0.6.189 Updates

- Added the breadcrumb dark-background control and full-surface preview/frontend parity requirements to the implementation queue. No breadcrumb runtime behavior changed in this release.

## 0.6.187 Updates

- Detect plain-text Elementor author names above single-post content so enabled header verification badges do not require an author link.
- Deduplicate exact-text and linked author targets before applying the shared horizontal badge pair.

## 0.6.186 Updates

- Keep verification badges at their intrinsic width inside Elementor full-width icon lists so author names retain readable horizontal space.
- Preserve word-level wrapping when an author name and verification badge share a narrow loop-card row.

## 0.6.185 Updates

- Keep automatic author-badge alignment local to the exact author label when an Elementor article card uses a card-wide link.
- Prevent repeated placement passes from appending duplicate badges to an existing author pair.

## 0.6.184 Updates

- Center author verification badges against the exact author-name row across Elementor headers, loop cards, and cloned multi-author units.
- Render the Elementor CSS cache-busting feature through a Hexa WordPress Plugin Core collapsible that is closed by default.
- Bundle Hexa WordPress Plugin Core `0.19.42`.

## 0.6.183 Updates

- Harden breadcrumb rendering on category and tag archives, isolate Rank Math failures, and reject invalid category links.

## 0.6.182 Updates

- Forced responsive preview and shortcode grids to use zero-minimum single-column tracks so higher-specificity desktop rules cannot widen mobile admin pages.

## 0.6.181 Updates

- Fixed the dashboard stylesheet URL and renamed the asset to match its broader admin-layout ownership.
- Contained feature report tables and page shortcode tables so they scroll within their sections instead of widening mobile admin pages.

## 0.6.180 Updates

- Fixed the Snippets catalog renderer import after the dashboard namespace migration so canonical and legacy Snippets routes render completely.
- Escaped literal `$post_id` variables in Custom Fields code examples to prevent admin-page PHP warnings.
- Normalized the shared Core section to `hexa_core` while preserving the legacy `hexa-core` URL alias.

## 0.6.179 Updates

- Replaced competing Core autoloaders with the shared one-package runtime and synchronized canonical Core `0.19.39`.
- Adopted `PluginContext` and `CoreBootstrap` for module lifecycle orchestration.
- Moved settings, dashboard, AJAX, and schema implementations behind namespaced compatibility facades.
- Replaced the large top-level tab list with Overview, Publication, Editorial, Structured Data, Operations, and Advanced areas while preserving legacy tab URLs.
- Added one lazy Pages workspace instead of rendering a detail block and editor for every page.
- Moved Quick Start cleanup-specific UI out of shared Core and into SMP admin assets.
- Made Snippets a metadata/test catalog without duplicate feature toggles.
- Fixed invalid color fallback handling in settings updates.

## 0.6.178 Updates

- Sectionalized the Quick Start article cleanup progress panel into overview, collapsible working window, queued/processed rows, and kept posts.

## 0.6.177 Updates

- Fixed homepage ItemList schema so it uses the same frontend query filters as homepage post lists, keeping home-hidden posts out of structured data.

## 0.6.176 Updates

- Fixed Shadow Posts so home-only hidden posts are excluded from frontend home/front-page post queries, including homepage builder loops.
- Updated Shadow Posts snippet copy to reflect the query marker and SQL guard behavior.

## 0.6.175 Updates
- Quick Start article cleanup now scans first, shows newest posts first, renders every queued post with detected featured/inline/gallery media, and deletes one post per AJAX request with live post/media statuses.
- Article cleanup deletion now verifies deleted post and attachment records before reporting success.

## 0.6.174 Updates

- Preserved Quick Start required input min, max, and step metadata through checklist model normalization.
- Added generic server-side numeric min/max validation for Quick Start required number inputs.

## 0.6.173 Updates

- Fixed Quick Start Posts to keep validation to allow whole numbers from 0 to 5000 instead of incorrectly capping at 250.
- Added min, max, and step attributes to generic Quick Start required number inputs.

## 0.6.172 Updates

- Reworked Article Cleanup so the post table shows row-level cleanup state directly.
- Added per-media status badges during deletion: pending, deleting, deleted, kept, or failed.
- Auto-scans the cleanup tab so the table is visible when the page opens.

## 0.6.171 Updates

- Set the Article Cleanup tab default batch size to 1 so batch deletes show post/media progress one request at a time unless the user raises the batch size.

## 0.6.170 Updates

- Added an Article Cleanup tab that uses the original Hexa WP Core Article & Media Cleanup renderer and AJAX controller.
- The full cleanup view supports live scans, associated media review, row deletes, selected deletes, batch deletes, and the Core activity log.

## 0.6.169 Updates

- Added a Posts to keep input to the guarded Quick Start cleanup action; the default is 10 and 0 is the explicit delete-all option.
- Updated the confirmation copy to state that matching posts and their associated featured, inline, and gallery media are deleted.

## 0.6.168 Updates

- Restored the guarded Quick Start action for deleting old regular posts while preserving the newest 10.
- The action uses typed confirmation and the existing Article & Media Cleanup scanner; it does not run unless `DELETE OLD POSTS` is entered.

## 0.6.167 Updates

- Removed the SMP Quick Start button-label override so Core defaults render `Apply`.
- Removed custom Quick Start report summary cards so Core renders the default report table without duplicate before/action/verified text.

## 0.6.166 Updates

- Restored HWS-style Quick Start reports for every item after Apply Settings runs.
- Each Quick Start report now shows before state, action taken, verified after state, and what changed.
- Kept the simplified Quick Start row copy and hidden request-type badges.

## 0.6.165 Updates

- Hid non-interactive Quick Start request-type badges such as `Feature Toggle`.
- Updated vendored Hexa WP Core to `0.19.35` for configurable checklist type badge rendering.

## 0.6.164 Updates

- Simplified Quick Start row copy so descriptions are short, normal admin text without target dumps.
- Removed bulky visible Quick Start setting reports after individual Apply actions; rows now show a compact applied state.

## 0.6.163 Updates

- Added Quick Start actions for hiding home posts without featured images and requiring featured images before regular posts can publish.
- Updated vendored Hexa WP Core to `0.19.34` so Quick Start renders simple actions as one continuous list without fake row expand/collapse controls.

## 0.6.162 Updates

- Added a Snippets toggle that hides regular posts without featured images from home/front-page post queries.
- Added a Snippets toggle that requires featured images before regular posts can be published, scheduled, or submitted for review.
- Added a red editor notice for missing featured images on regular post edit screens.

## 0.6.161 Updates

- Removed the legacy SMP Quick Start AJAX endpoint, card renderer, click handler, and custom Quick Start CSS.
- Quick Start actions now run only through the Hexa WP Core Getting Started Checklist AJAX controller.
- Preserved frontend cache purging after Quick Start setting writes from the Hexa Core checklist path.

## 0.6.160 Updates

- Quick Start preloads shared Hexa Core button assets outside the tab fragment so the tab panel contains only the checklist root.

## 0.6.159 Updates

- Quick Start now uses the Hexa WP Core Getting Started Checklist renderer and AJAX runner.
- The Quick Start tab is second, directly after Overview, and renders only the checklist.
- Each SMP Quick Start item now reports before/action/verified-after/what-changed rows when applied.

## 0.6.158 Updates

- Updated vendored Hexa WP Core to `0.19.33` so shared checklist report rendering matches HWS Base Tools when SMP loads Core first.
- Added support for Core before/action/verified-after checklist report summaries.

## 0.6.157 Updates

- Updated vendored Hexa WP Core to `0.19.32` so active SMP installs no longer load older checklist report labels before HWS Base Tools.
- Restored shared checklist report previews for generated PNG/ICO assets and renamed wp-config report columns to `Target Value` and `Verified Value`.

## 0.6.155 Updates

- Added SMP Quick Start feature checklist actions using the Mash Viral feature baseline.
- Added per-feature target descriptions with colors, templates, contexts, font sizes, and enabled states.

## 0.6.154 Updates

- Fixed drop cap size rendering so the shared CSS helper honors the full 48-180px saved range.

## 0.6.153 Updates

- Added an Article first-letter drop cap feature card to the Features tab.
- Added front-end CSS injection for the first paragraph drop cap on single post content.
- Added matching admin preview, color control, and size control.

## 0.6.152 Updates

- Removed Custom Post Type UI from the recommended plugin stack.
- Removed MU-plugin and drop-in runtime files from SMP plugin inventory output.
- Added Visibility Logic for Elementor as an installed but inactive recommended plugin.

## 0.6.149 Updates

- Loads SMP's vendored Hexa Core plugin inventory classes during bootstrap so the Plugins tab cannot be rendered by an older Core copy from another active plugin.

## 0.6.148 Updates

- Updated vendored Hexa WP Core to `0.19.9` so plugin inventory tables remove the Installed column and use inline Font Awesome SVG check/X title indicators.

## 0.6.128 Updates

- Improved Content Generation editor controls so side metabox actions use the same creating, success/error, and per-target activity log feedback as field-level controls.

## 0.6.127 Updates

- Added breadcrumb rendering support for category and tag archive pages.
- Added a `Hide on category and tag pages` Breadcrumbs setting that defaults off, so archive breadcrumbs show by default.
- Extended breadcrumb fallback markup and reporting for term archive contexts.

## 0.6.124 Updates

- Added inline-photo legacy caption normalization so raw editor markup like image plus italic `Photo credit` text is converted into a real figure/figcaption pair before treatments apply.
- Extended inline-photo treatment selectors to include normalized `.smpi-inline-photo` wrappers, making Treatment 2 caption plates work on older non-figure article markup.
- Brought the live author template helper bootstrap into the tracked package so the Git release matches the active site package.

## 0.6.121 Updates

- Normalized server-side Elementor author badge insertion to use the same non-breaking author-to-badge adjacency as loop-card multi-author output.

## 0.6.120 Updates

- Preserved Elementor author-name link wrappers during multi-author rebinding so repeated authors keep distinct archive URLs without flattening the design.
- Rebuilt orphan Elementor social-list items per author: dead social items are removed, and text-only social items become links only when that author has the matching URL.
- Kept loop-card verification badges scoped to each author item so badges stay attached to the correct linked author.

## 0.6.119 Updates

- Rebuilt loop-card fallback bylines to replace the native author link with one detectable inner author group per loop item.
- Added per-author loop item markers and loop-mode debug output so the Multiple Authors test panel can prove comma, stacked, and primary-only rendering.
- Added regression coverage for stacked loop-card author output, distinct author links, and non-breaking author names in narrow card bylines.

## 0.6.118 Updates

- Restored the Multiple Authors tab frontend hook test module as a top-level diagnostic panel.
- Added visual loop-card author output choices for comma-separated, stacked, and primary-only bylines.
- Expanded the test report to show detected author unit classes, author links, loop mode, schema authors, and boundary warnings when Share or read-time content is inside the author unit.

## 0.6.117 Updates

- Allowed the explicit `smp-author` Elementor author-unit renderer to run inside public loop/archive/front-page Elementor widgets, not only single posts.
- Added a regression test that confirms marked Elementor loop widgets render every selected author with distinct author URLs.
- Routed loop-card badge context through `loop_cards`/`author` verification contexts instead of single-post contexts.

## 0.6.116 Updates

- Preserved the requested author identity on author archives while expanding archive queries to include secondary-authored posts.
- Routed author archive shortcodes, Elementor archive author fields, and Elementor author-query args through the same archive-author resolver so `/author/{slug}/` cannot fall back to the first post author.

## 0.6.115 Updates

- Added a dedicated Multiple Authors admin tab with the frontend hook/debug test runner, shortcode examples, loop-card output controls, and the new `smp-author` Elementor protocol.
- Kept legacy `.smpi-author-module` support while making `smp-author` the preferred author unit class.
- Routed Elementor loop-card author widgets through the same multi-author byline renderer so homepage loop items can show all assigned authors without shortcode-only templates.
- Prevented non-author direct children such as Share controls from being cloned with each repeated author unit.

## 0.6.114 Updates

- Tightened the post editor visibility metabox styling so labels are smaller, regular-weight, and scoped to the side metabox.
- Reduced the multi-author "Add current post author" editor button to a compact WordPress admin button size.

## 0.6.110 Updates

- Removed the nullable imported-class static property type from the admin UI cleanup registry to avoid a PHP 8.4 CLI lint segfault on the live cPanel runtime.

## 0.6.104 Updates

- Centralized MuckRack author-field aliases on the Authorship field resolver.
- Cached Elementor marked-template and binding discovery during each request.
- Made multi-author meta synchronization revision/autosave-safe and avoided redundant REST meta resync.

## 0.6.103 Updates

- Added an ordered, private author relationship taxonomy as the canonical multi-author assignment store.
- Kept the existing ACF multi-user field as the editor UI and synchronized its values into canonical assignments.
- Synchronized native `post_author` to the first selected WordPress author.
- Added normalized author records, one author field resolver, REST author assignment support, user deletion cleanup, cache invalidation, and incremental legacy migration.
- Replaced serialized post-meta author archive matching with indexed taxonomy relationships.

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

- Added an opt-in Multiple post authors feature card on the Features tab.
- Added one ACF multi-user field, `smpi_post_authors`, for posts, press releases, and imported-news entries.
- Added a Multiple post authors frontend hook test button and server-side Elementor `.smpi-author-module` duplication path.
- Added shared multi-author resolution for schema and author shortcodes with native `post_author` fallback.
- Updated `[acf_author_field]` and author shortcodes to support `author_index` while preserving default primary-author behavior.
- Added `[smp_post_authors]` text/list/link output modes, a loop-card disable toggle, server-side verified badge support for repeated author modules, and friendly default output for `[smp_estimated_read_time]`.
- Tightened the multiple-author Elementor duplication protocol so SMP clones only author-content child nodes, leaves sibling controls like Share untouched, and injects no multi-author frontend CSS.
- Expanded author archives so secondary authors selected in `smpi_post_authors` appear on their WordPress author pages.
- Expanded author archive handling to Elementor and secondary archive queries, not just the native main author query.
- Added explicit multiple-author shortcode examples in the Features tab and filled missing footer bio widgets from the existing Elementor author module structure.
- Removed fallback-only Publication Options ACF fields and appended shortcode examples to remaining publication option field instructions.
- Added loop/card multiple-author output options for primary-only, comma-separated, and one-author-per-line rendering.
- Made author role/title shortcodes alias-aware so `job_title` and `subtitle` can resolve `what_best_describe_you` without changing Elementor markup.
- Added an Elementor Loop Grid author-archive query bridge so secondary-author posts are included when the archive template uses a separate Elementor query.
- Added a default-enabled Breadcrumbs setting to hide SMP breadcrumb injection on the front page/home page and wired it through AJAX saves and frontend rendering.
- Added `[author_name]` and guarded `[author_image]` against avatar-plugin image-editor failures.
- Added a shared runtime guard so automatic frontend injections skip Elementor editor/preview, AJAX, REST, feeds, embeds, cron, CLI, and admin contexts.
- Rebuilt multi-author loop and footer output so author links remain separate, MuckRack badges do not duplicate empty wrapper links, and footer bio widgets render only from marked Elementor author fields.
- Disabled the legacy frontend author-social cleanup script; multi-author server-side cloning now removes empty Elementor social widgets before output.
- Fixed single selected SMP author rendering so Elementor author modules are rebound even when only one SMP author is selected.
- Skipped multi-author Elementor rebinding when the selected SMP author exactly matches the native WordPress post author.
- Rebound single selected SMP authors from the native WordPress author template source so Elementor author names, URLs, avatars, and fields update together.
- Unified single article, footer author, and loop-card author replacement through one native-source to SMP-selected-author resolver.
- Guarded author archive profile headers so multi-author name rewriting only affects actual article loop/card output, not the queried author identity, URL, or avatar.
- Scoped Elementor's native author dynamic tags to the queried author while an author archive profile widget renders, without changing loop-card author context or template markup.
- Updated article Speakable schema selectors to target the live Elementor headline, excerpt, and article-content structures.
- Replaced unsafe loop-card author-name rewriting with full author-link HTML rewriting so each selected author keeps their own author archive URL and loop-card checkmark.
- Made MuckRack badge injection idempotent by cleaning wrapper links as well as icon nodes before timed reinjection.
- Bundled Hexa WordPress Plugin Core and registered the Hexa Core tab through dashboard filters.
- Replaced the standalone GitHub updater with `Hexa\PluginCore\PluginUpdates`.
- Replaced repeated AJAX nonce/capability checks with `Hexa\PluginCore\WpAdminAjax\AjaxGuard`.
- Replaced the SMP activity log writer with `Hexa\PluginCore\ActivityLog` permanent option storage.
- Replaced Integrations plugin install/update mechanics with `Hexa\PluginCore\PluginProvisioning` and `Hexa\PluginCore\PluginUpdates`.
- Replaced SMP admin-AJAX action registration with `Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry` and request parsing with `AjaxRequest`.
- Replaced the Shortcodes debugger table with `Hexa\PluginCore\ShortcodeRegistry\ShortcodeDisplayRenderer`.
- Updater flow fixed: SMP uses GitHub API version detection and Hexa WordPress Plugin Core post-install handling.

## 0.6.66 Updates

- Imported Hexa WordPress Plugin Core brand color controls into the SMP Features tab.
- Feature color controls now show picker, editable hex, RGB, swatch, copy action, and HWS Base Tools primary-color import.
- Feature primary/accent defaults now resolve from HWS Base Tools Brand Assets primary color when not customized.
- Moved Elementor CSS cache busting and publication social link cleanup into snippet-backed feature metadata.
- Updated bundled Hexa WordPress Plugin Core to include BrandColors and ColorControl.

## 0.6.44 Updates

- Added an opt-in Featured image caption templates feature with independent settings from inline photo treatments.
- Added automatic featured-image detection for single posts and press releases, using the media attachment caption as the caption source.
- Added backend live previews and controls for featured-image caption template, accent color, font style, font size, and text color.
- Added the shortcode-driven Become a Contributor page template update to the work queue and default page template set.
- Added managed Pages-tab entries for Brand Assets, Submit Your Press Release, Press Releases, and Advertise with Us.
- Added a Publication Options Brand Assets Gallery ACF field.

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

## 0.6.46 Updates

- Added cross-plugin page links to the SMP Pages tab and moved shared site page management to HWS Base Tools Pages.
- SMP page assignment shortcodes and schema policy URLs now fall back to HWS Base Tools assignments for Terms of Use, Privacy Policy, Brand Assets, Headquarters, Contact, and FAQs.

## Implementation Queue

- Restore the visible single-post author byline on `https://blockeditorial.com/north-korean-hackers-deploy-ai-to-steal-crypto-at-scale-prompting-ecosystem-defense-overhaul/`. Post `27085` retains WordPress author `46` (`Block Editorial Staff`) and valid author schema, but the Elementor single template's Verified Profiles loop returns an empty-loop marker and hides its only author area. Implement the fallback in the owning author/profile integration so an empty Verified Profiles result renders the canonical WordPress or SMP multi-author source, while verified-profile output remains unchanged when present. Do not patch the post or one Elementor template manually. Prevent duplicate names, preserve MuckRack badge alignment, and verify this post plus posts with a linked verified profile and multiple authors.
- Add a WordPress hex color control for the Breadcrumbs background so dark themes can set the complete breadcrumb surface. The saved value must update the admin preview and frontend from the same setting, apply to the outer injected breadcrumb wrapper and every template-owned background layer, and prevent hard-coded white, soft, or gradient backgrounds from showing through.
- Add AJAX save/update behavior to the Publication Options tab at `Settings > SMP Publication Integration > Publication Options` so saving does not require a full page reload.
- Remove fallback-only Publication Options ACF fields that duplicate imported publication data, including Mission Statement Fallback and Publication Summary Fallback.
- Add a matching shortcode example to the description/instructions for every remaining Publication Options ACF field.
- Update breadcrumbs visibility defaults so SMP never injects breadcrumbs into Elementor Floating Element templates by default.
- Add a breadcrumbs visibility option to hide breadcrumbs on the front page/home page, with that option selected by default.
- In breadcrumbs visibility controls, list every registered custom post type and allow disabling breadcrumb injection on each CPT single template.

### 0.6.67 Updates
- Hardened Core color controls with max-length hex input and visible invalid-hex rejection before save.
- Synced vendored Hexa WordPress Plugin Core to 0.18.2.


### 0.6.71 Updates
- Fixed `[smp_table_of_contents]` shortcode rendering when automatic single-post injection is disabled.
- Shortcode mode now parses post headings correctly and assigns matching frontend anchor IDs for Elementor-rendered post content.
- Number inputs on the Features tab now save on blur/Enter instead of queuing AJAX saves on every step change.

### 0.6.69 Updates
- Post editor schema test buttons are disabled for unpublished content and show a live-post requirement notice.
- Renamed the SMP visibility metabox to Post visibility and clarified the toggle labels.

## Content Generation and Post Hygiene

SMP adds two publication-wide admin modules:

- `Content Generation` stores an SMP content API key and adds one-click post editor buttons for excerpts, post summaries, and structured FAQs. The Publish Scale API remains the source of truth for writing rules.
- `Post Hygiene` runs save-time cleanup on selected post types. It removes imported inline formatting such as `<span style="font-weight: 400;">` while preserving headings, paragraphs, lists, links, images, figures, captions, and tables.

The publish-side handoff for the content generation reporting portal lives in `docs/publish-scale-content-generation-handoff.md`.
