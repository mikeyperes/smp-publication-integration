# SMP Publication Integration

Editorial, publication-profile, article-type, authorship, design, and structured-data tooling for Scale My Publication websites.

> Feature base for HWS Skills. Read before building on this plugin; use or
> extend these features instead of rebuilding them.

**Purpose:** editorial, authorship, verification and publication-profile features for Scale My Publication news sites. **Admin:** WP Admin → SMP Publication (`admin.php?page=smp-publication-integration`); every switch below is a key in the `smpi_settings` option. **Depends on:** ACF Pro, Rank Math (breadcrumbs), HWS Base Tools (primary entity); Elementor optional.

## Features

### Breadcrumbs band
- **Does:** a breadcrumb band built from Rank Math breadcrumbs, injected below the header on single articles and archives.
- **Switch:** automatic on singular pages and term archives when Rank Math breadcrumbs are on (`rank_math_breadcrumb_check_enabled`); hide on the home page `breadcrumbs_hide_home`, on category and tag archives `breadcrumbs_hide_term_archives`; custom CSS on the dashboard Breadcrumbs tab.
- **Use:** `[smp_breadcrumbs style=""]` to place it manually.
- **Style:** `.smpi-breadcrumbs-band`, `.smpi-breadcrumb-separator`.
- **Code:** `src/Content/Breadcrumbs.php`

### MuckRack verified badge
- **Does:** a "Verified by MuckRack" icon or label next to verified authors and the publication, in loop cards, bylines and author boxes.
- **Switch:** `muckrack_verified_enabled`, where it shows `muckrack_verified_contexts`, `muckrack_author_always_show`; look `muckrack_verified_style`, `muckrack_icon_style`, `muckrack_icon_color`, `muckrack_verified_font_*`, `muckrack_verified_text_color`. Publication badge: `publication_muckrack_verified_enabled`, `publication_muckrack_placements`, `publication_muckrack_*`.
- **Use:** automatic in its contexts; `[muckrack_verified type="icon|text" user_id="" post_id="" author_index="" style="" context=""]`, `[author_muckrack_verified]`, `[smp_publication_muckrack_verified class="" style="" color=""]`.
- **Style:** `.smpi-muckrack-inline-pair`, `.smpi-muckrack-icon`, `.smpi-muckrack-link`.
- **Code:** `src/Content/MuckRackVerification.php`

### Author fields
- **Does:** any author's name, bio, title, socials, image or ACF field for the current post's author (or a given user / co-author).
- **Use:** `[author_name]`, `[author_bio format="html"]`, `[author_bio_short words="35"]`, `[author_title]`, `[author_subtitle]`, `[author_image size="thumbnail" output="html" class="smpi-author-image"]`, `[author_facebook]` `[author_instagram]` `[author_x]` `[author_linkedin]` `[author_youtube]` `[author_website]` `[author_crunchbase]` `[author_muckrack]` `[author_email]`, `[acf_author_field field=""]`. All accept `user_id`, `post_id`, `author_index`.
- **Code:** `src/Content/AuthorShortcodes.php`

### Author social icons
- **Does:** the author's social links as styled icons, added automatically to author boxes and archives.
- **Switch:** `author_social_icons_enabled`, `author_social_style`, `author_social_size`, `author_social_color`, `author_social_networks`, `author_social_auto_contexts`, `author_social_archive_position`; empty-link cleanup `author_social_cleanup`.
- **Use:** `[smp_author_social_icons style="" size="" color="" networks="" class="" label=""]`.
- **Style:** `.smpi-author-social-icon`, `.smpi-author-social-label`.
- **Code:** `src/Content/AuthorSocialIcons.php`

### Multiple authors
- **Does:** several authors on one post, shown in bylines and loops.
- **Switch:** `multi_authors_enabled`, loop output `multi_authors_loop_output`.
- **Use:** `[smp_post_authors]`, `[smp_post_author_names]`, `[smp_post_author_ids post_id="" separator=","]`.
- **Style:** `.smpi-authors`, `.smpi-authors__list`, `.smpi-authors__item`, `.smpi-authors__name`.
- **Code:** `src/Content/MultiAuthors.php`

### Staff and contributor grids
- **Does:** grids of the publication's staff or contributors.
- **Switch:** `author_listing_hide_without_articles`, `author_listing_hide_without_featured_image`, `author_listing_show_press_releases`.
- **Use:** `[staff_grid]`, `[contributors_grid]`.
- **Code:** `src/Content/AuthorListings.php`

### Author archive loading
- **Does:** load-more or infinite loading on author archive pages.
- **Switch:** `author_archive_loading_enabled`, `author_archive_loading_mode`, `author_archive_loading_style`.
- **Code:** `src/Content/AuthorArchiveLoading.php`

### Table of contents
- **Does:** a table of contents from the article's headings.
- **Switch:** `table_of_contents_enabled`; automatic on single posts with `table_of_contents_auto_single`.
- **Use:** `[smp_table_of_contents post_id="" title="Table of Contents" style="" levels="h2,h3,h4" open="" markers=""]`. `levels` accepts any of `h2,h3,h4`; `open="always"` renders open at every width; `open="desktop"` renders open above 1024px and closed at smaller widths; `markers="leading-zero"` adds explicit `01`, `02` marker spans. Defaults preserve the existing closed h2-h4 output. An explicitly supplied `title` is used by every style, including `unstyled`.
- **Scroll tracking:** as the reader scrolls, the item for the section being read gets `aria-current="location"` and `smpi-toc-link--active` (its item `smpi-toc-item--active`, the list `smpi-toc-has-active`); styled templates colour it with the contents accent, and `unstyled` leaves the look to the site. Links find their headings by heading text.
- **Code:** `src/Content/TableOfContents.php`

### Estimated read time
- **Switch:** `estimated_read_time_enabled`.
- **Use:** `[smp_estimated_read_time post_id="" unit="minutes" wpm="" format="friendly" output="" suffix="read"]`.
- **Code:** `src/Content/EstimatedReadTime.php`

### Published date
- **Does:** the article's original published date and time in the site timezone, without the relative-date display filter.
- **Use:** `[smp_post_date post_id="" format="M j, Y · g:i A"]`.
- **Code:** `src/Content/PostTime.php`

### Post summary and FAQs
- **Does:** an article's ACF summary and FAQ blocks, placed automatically or by shortcode.
- **Switch:** `post_summary_acf_enabled`, `post_faqs_acf_enabled`.
- **Use:** `[smp_post_summary post_id="" format="html" style=""]`, `[smp_post_faqs post_id="" format="html|accordion|text" style=""]`, any post ACF field `[smp_post_acf field="" post_id="" format="html"]`. `format="accordion"` renders structured FAQ rows as native closed `details` and `summary` elements. It does not parse or migrate FAQ text already embedded in older article content.
- **Code:** `src/Content/Shortcodes.php`, `src/Content/PostSummaryPlacement.php`, `src/Content/PostFaqPlacement.php`

### Publication profile values
- **Does:** the publication's own profile: fields, mission, founders, system user, assigned pages and page templates.
- **Use:** `[smp_publication_field field="" format="html" row="" index="" sub_field="" fallback=""]`, `[smp_publication_mission_statement]`, `[smp_publication_founders]`, `[smp_publication_profile]`, `[smp_publication_user]`, `[smp_publication_page type="" mode="link"]`, `[smp_publication_page_template type=""]` (alias `[smp_page_template]`).
- **Code:** `src/Content/Shortcodes.php`

### Primary category
- **Does:** a post's primary category in Elementor (Rank Math or Yoast primary, else its first category). The default category steps aside for any other; a post filed only under it shows it, except WordPress's "Uncategorized".
- **Switch:** `elementor_primary_category_enabled`, `elementor_primary_category_exclude_default`.
- **Use:** Elementor dynamic tag `smpi-primary-category`.
- **Code:** `src/Elementor/PrimaryCategoryTag.php`

### Content types and article types
- **Does:** Knowledge Base (`knowledge-base`), Resources (`resources`), Press Release (`press-release`) and Publication Profile (`publication-profile`) post types; article types; shadow posts and press-release inclusion in lists.
- **Switch:** `article_types_enabled`, `shadow_posts_enabled`, `shadow_press_releases`, `press_release_include_enabled`, `press_release_include_contexts`, `post_list_defaults_enabled`.
- **Code:** `src/Content/PublicationContentTypes.php`, `src/Content/ArticleTypes.php`

### Publishing guards
- **Switch:** `post_featured_image_required`, `hide_home_posts_without_featured_image`, post time display `post_time_mode`, Elementor CSS cache busting `elementor_css_cache_busting`.

### Plugin requirements

- The Plugins tab is the single source of truth for which plugins a publication must, may and must not have.
- Required: Classic Editor, Elementor, Elementor Pro, GPLVault Update Manager, Hexa PR Wire Distributor, HWS Base Tools, LiteSpeed Cache, Media Cleaner Pro, Rank Math SEO, Rank Math SEO PRO, Simple Local Avatars, Site Kit by Google, SMP Publication Integration, SMP WP Text To Speech, Wordfence and WP Mail SMTP.
- Optional (installed only on request): Visibility Logic for Elementor and Verified Profiles.
- Forbidden: JetEngine, WP User Avatars, WP-Optimize, WP Sweep (HWS Base Tools does database cleanup), Echo RSS Feed Post Generator and FIFU - Featured Image from URL.
- Read in code from `PluginInventory::recommended_definitions()`, `optional_definitions()` and `forbidden_definitions()`.

### Extension points
- `smpi_dashboard_tabs`, `smpi_dashboard_tab_groups`, `smpi_dashboard_areas`, `smpi_dashboard_area_sections`, `smpi_dashboard_flat_tabs`, `smpi_render_dashboard_tab`, `smpi_publication_manifest_cache_ttl`, `smpi_publication_manifest_category_policy`, `smpi_publication_manifest_recent_content_limit`.

## Identity

- Repository: `mikeyperes/smp-publication-integration`
- Plugin slug: `smp-publication-integration`
- Namespace: `smp_publication_integration`
- GitHub branch: `main`
- Version: `2.4.14`

## Ownership

SMP Publication owns publication-specific behavior:

- Publication identity and publication article types.
- Editorial features, article templates, breadcrumbs, summaries, FAQs, tables of contents, captions, and inline-photo treatments.
- Optional multiple authors per article (per-post switch, off by default), bylines, author displays, and MuckRack verification.
- Publication and article schema object construction.
- Blog-like custom post types: `knowledge-base` and `resources`.

HWS Base Tools owns website classification, the optional primary entity, and the generic reading-progress feature. SMP consumes a canonical Publication or Organization entity from HWS and retains legacy publication-user settings only as migration fallback. Existing reading-progress values remain stored long enough for HWS Base Tools to migrate them without a direct database rewrite.

## Custom Post Types

The **Custom Post Types** tab registers `knowledge-base` and `resources` through `Hexa\PluginCore\ContentTypes`. Each structure supports:

- Enable/disable state.
- Editable public rewrite slug.
- Editable singular and plural labels.
- Related ACF structure toggles with detailed field breakdowns.

The WordPress post-type keys remain immutable so existing content cannot be orphaned. SMP contains no runtime registration for the retired `imported-news` type.

## Article Types

Article-type definitions remain the publication source of truth and feed editorial controls, templates, visibility rules, and schema. The plugin preserves existing stored article values and exposes them consistently to dependent renderers and schema builders.

## Canonical Entity and Authors

When HWS has an optional primary Publication or Organization selected, SMP reads it through Hexa WP Core. If the selected source is a post, Core resolves the attached WordPress author and exposes the same user fields used by existing publication output.

Every article's primary author is its WordPress author. With the site's Multiple authors feature on, an article's own **Multiple authors** switch (off by default, in the editor's Authors box) adds ordered co-authors after it. See `docs/multiple-authors-audit.md`.

## Schema

SMP decides which pages it covers and maps the publication's data; every node is built with Hexa WP Core's schema library (`SchemaNodes`, `SchemaSources`, `SchemaIds`) and added to Core's one shared `hexa-schema` block (`SchemaPage` source `smp-publication`, priority 20). Coverage: the site entity and WebSite on every public page; the homepage CollectionPage and ItemList; articles (type from Article Types) and press releases (NewsArticle, issuer Organization as author, `isBasedOn` the original); other pages (AboutPage and ContactPage from page assignments); category, tag, author (ProfilePage) and post type archives. Testimonials get no schema. A site whose primary entity is a person uses that person (`/#person`) as publisher instead of a news organization. FAQ schema uses the same shared FAQ source as visible FAQ output. Filters: `smpi_single_schema_array`, `smpi_press_release_issuer`. Rank Math's schema is controlled only by HWS Base Tools.

## Dashboard

The dashboard uses Hexa WP Core tabs, collapsible cards, dynamic controls, and activity logs. Major areas include:

- Quick Start and publication profile.
- Custom Post Types and article types.
- Editorial features and article design.
- Authors, templates, shortcodes, snippets, and schema.
- Plugins, system checks, and plugin/Core update reporting.

Quick Start contains only the reusable checklist workflow and is the second tab.
## Architecture

`smp-publication-integration.php` is the canonical plugin entry. `initialization.php` is a compatibility loader for older active-plugin records.

Namespaced domain code lives under `src/`. Reusable UI, AJAX, updater, CPT, ACF, entity, FAQ, schema, taxonomy, activity-log, color, typography, and template infrastructure comes from Hexa WordPress Plugin Core 3.22.0.

Elementor single-post recent-content loops opt into SMP publication filtering with the exact Query ID `smpi_single_recent`. Other Posts and Loop Grid widgets remain outside SMP query mutation.

Native non-editorial singular templates can opt out of article-content decoration and its DOM fallback through `smpi_article_markup_enabled`. The filter receives the saved feature state, queried object ID, and post type; it does not disable breadcrumbs, tables of contents, or other surfaces independently.

The plugin updater targets the repository's canonical `main` branch and registers `Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater` directly.

## Requirements

| Requirement | Minimum |
| --- | --- |
| WordPress | 5.0 |
| PHP | 8.1 |
| Hexa WP Core bundle | 3.22.0 |

ACF Pro is not required: publication option and content field groups run on Hexa WP Core's custom fields, which use ACF when it is active and store the same data natively when it is not. Feature-specific integrations require their corresponding plugins.

## Installation

Install the repository as `wp-content/plugins/smp-publication-integration` and activate `smp-publication-integration.php`. Existing installations activated through `initialization.php` are handled by the compatibility migration.

## Development

Run every focused regression file with:

```bash
for file in tests/*.php; do php "$file" || exit 1; done
```

The suite covers navigation, article defaults, article/FAQ output, authorship, templates, colors, typography, breadcrumbs, content types, schema fallbacks, and updater configuration.

## Changelog

### 2.4.14

- Automatic breadcrumbs defer to an existing visible shortcode or theme placement, including after AJAX navigation, so an Elementor article does not receive a second breadcrumb band.

### 2.4.13

- The verified line's `size` and `text_color` now win over the global publication typography (they set its own variables), and a sized line is its own block at line height 1.5, so it no longer inherits the surrounding text's tall line.

### 2.4.12

- `[smp_publication_muckrack_verified]` takes `size` (px) and `text_color` for one placement, so an article line and a footer line can differ without changing the global publication settings.

### 2.4.11

- The publication verified mark takes the line's own size and accent colour instead of the author mark's, and the full-sentence wording keeps "MuckRack" in the sentence's own weight and colour. The minimalist line no longer inherits a tall line height.

### 2.4.10

- New text option for the publication verified line: "<Publication> is verified by MuckRack's editorial team." (`publication_sentence`), the wording publication footers use. The existing two options are unchanged.
- `[smp_publication_muckrack_verified icon="yes"]` shows the verified mark before the line, in the same style as the author mark.

### 2.4.9

- Echo RSS Feed Post Generator and FIFU - Featured Image from URL move to the Forbidden list.

### 2.4.8

- The publication verified line (`[smp_publication_muckrack_verified]`) prints only when the publication verification setting is on and the verified field is set; before, the shortcode printed it on every site. The "(learn more)" link shows only once the outlet's Muck Rack page is saved, instead of linking to muckrack.com.

### 2.4.7

- The table of contents marks the section being read and moves that mark as the reader scrolls (`aria-current="location"`, `smpi-toc-link--active`, `smpi-toc-item--active`, `smpi-toc-has-active`), for the shortcode and the automatic list alike; styled templates colour it with the contents accent.
- Shortcode contents links find their headings by heading text instead of position, so a list limited to some heading levels no longer sends readers to the wrong section.
- Contents scripts are marked so page caches never delay them; before, LiteSpeed's delayed scripts left every contents link dead until the reader first moved the mouse.

### 2.4.6

- Adds reusable article-template shortcodes for an SSR table of contents, structured FAQ rows and fixed published dates, including responsive desktop-open contents behavior and generated counter support.

### 2.4.5

- Elementor Primary Category shows a site's default category when it is a post's only category (it stayed empty, so builds typed a fallback label), except "Uncategorized".

### 2.4.4

- WP-Optimize and WP Sweep move to the Forbidden list: HWS Base Tools now does database cleanup and table optimization, and LiteSpeed Cache does caching.

### 2.4.3

- Press-release visibility has one owner: when Hexa PR Wire Distributor is active, its "Show press releases on" switches decide the home, category, tag and author pages, and SMP's author and inclusion switches step aside (they still apply without the Distributor). The SMP settings screens say so.

### 2.4.2

- The Plugins tab now has three lists: Required, Optional and Forbidden.
- Required adds Wordfence and drops Code Snippets, Echo RSS Feed Post Generator and Featured Image from URL.
- Visibility Logic for Elementor, Verified Profiles and WP-Optimize move to Optional.

### 2.4.1

- Automatic author social icons on articles now sit under the author bio in the Elementor single-post layout (Author Box widget, `[author_bio]` or the Author Info tag), instead of at the end of the article body. They fall back to the end of the article only when the layout has no author block, and are skipped entirely when the layout already places the icon shortcode by hand. Widgets merely named like the bio (a bio portrait or bio name) are no longer mistaken for it.

### 2.4.0

- Schema is built with Hexa WP Core 3.22.0's shared schema library and printed in Core's one schema block per page. SMP covers the whole publication: the organization and website on every page, the homepage collection, articles, press releases, pages and archives (categories, tags, authors). Testimonials get no schema.
- Press releases are NewsArticle with genre Press Release, the issuing company as an Organization author, and `isBasedOn` pointing at the original when the release was syndicated.
- On a site whose primary entity is a person, the person is the publisher and SMP no longer adds a news organization with the person's name.
- Removed SMP's own Rank Math schema removal (now one HWS Base Tools setting) and the stored Publication Schema Markup field.

### 2.3.2

- Featured image caption fallback only decorates the article's own featured image; it never puts the caption frame on a related-post or loop card, and stops when the article image already has a caption.

### 2.3.1

- Generation messages name FAQs correctly ("Writing the FAQs…") and the Going Live activity log reads naturally.

### 2.3.0

- Content generation rebuilt around Publish's background jobs. Generate (inline or in Going Live) starts a job and returns at once; the field shows it being written, and keeps showing it after a reload or on another screen, until Publish finishes and the value is saved and filled in. Publish pings the site when a job finishes; the editor and a background event also check.
- Publish chooses the AI connection (subscription by default, or API) per job; nothing changes on the site.
- New `GenerationJobs`, `PublishContentClient` and `GeneratedValueStore` replace the one-shot request code; the Going Live checklist and inline buttons share one editor client (`assets/admin/content-generation.js`). The obsolete timeout setting and the `smpi_generate_content_for_post` filter are removed.

### 2.2.6

- Post date/time display no longer crashes when WordPress or Elementor passes no format (Elementor Post Info in loop items and live search results) and leaves machine formats such as `U` untouched.

### 2.2.5

- Multiple authors rebuilt: one **Authors** box per article with a **Multiple authors** switch that is off by default. While off, the post uses only its WordPress author; when on, ordered co-authors follow it. Replaces the two ACF fields ("SMP Post Authors" button and "Article Authors" picker). SMP no longer overwrites the WordPress author on save. Works the same with ACF Pro or HexaWP Core fields.
- One read gate for every consumer (bylines, Elementor, archives, schema, REST, shortcodes); shared list formatter; the archive index holds active co-authors only.
- Automatic migration of existing author lists; "Force primary author only on loop/cards" folded into the "Primary only" loop output; blank first row removed from Article Fields; single dashboard card.

### 2.2.4

- Going Live: Generate fills the generated excerpt, summary or FAQ rows into the open editor at once (shared `window.smpiApplyGenerated`), so the screen matches what was saved and a later Save keeps it. The FAQ row is hidden when Post FAQ fields are off.

### 2.2.3

- Content generation falls back to the text-to-speech plugin's working key through its `smp_tts_site_api_key` filter (SMP Text to Speech 1.4.1+). It used to read the stored, encrypted setting directly, so Publish rejected it with HTTP 401.

### 2.2.2

- Going Live Checklist rebuilt: consistent rows (status badge, name, message, Generate, View), a spinner while working, failures shown as a plain red message with the real reason and a Try again button (no red error buttons), Complete all with a summary line, and the activity log folded away. Excerpt, summary and FAQs are generated directly, so a failure shows at once instead of after a 30-second wait.

### 2.2.1

- Going Live Checklist redesigned: one compact box with a progress pill ("1 of 4 done"), a switch for "Do not process this page" (greys the list when on), one status badge per row, a Generate button that hides once an item is done, a quiet View link, and the activity log folded away.

### 2.2.0

- Going Live Checklist: "Do not process this page" switch at the top (post meta `_smpi_go_live_exempt`). New server-side processing: `do_action( 'smpi_go_live_process', $post_id )` generates every missing excerpt, summary and FAQ set unless the post is switched off, and `smpi_generate_content_for_post` exposes one-target generation to other plugins. The editor's Generate buttons use the same code path.

### 2.1.0

- Runs every custom field, option page and field group on Hexa WP Core 3.22.0
  `Hexa\PluginCore\Fields`, so ACF Pro is no longer required or recommended;
  sites that keep ACF are unchanged because Core delegates to it.

### 2.0.18

- Makes HWS Base Tools an optional publishing transport instead of an SMP boot
  dependency, so SMP publication features and the public manifest remain
  available on any WordPress host.
- Advertises the independently enabled Post Summary, FAQ repeater, SMP Article
  Types taxonomy, and article-audio capabilities in the publication manifest.

### 2.0.17

- Adds explicit manifest reporting for article-audio support and invalidates
  cached capability data when plugins are activated or deactivated.

### 2.0.16

- Removes the duplicate external-publishing endpoint and setting. External plugin-bridge publishing is owned exclusively by HWS Base Tools; SMP remains focused on publication content behavior.

### 2.0.15

- Raises bounded native homepage evidence to 50 posts per widget and uses exactly one extra result as an overflow sentinel for oversized or unlimited queries.
- Marks a widget partial only when the 51st result proves evidence was truncated; finite unlimited grids with 50 or fewer results now remain complete.
- Adds focused coverage for a 24-result category-capable directory, an unexecuted non-category directory, and real overflow handling in Elementor and Query Builder adapters.

### 2.0.14

- Treats statically bound Elementor grids whose post types do not support WordPress categories as complete empty article-category evidence.
- Prevents unlimited team, directory, and similar non-category grids from making the homepage article manifest falsely partial while preserving fail-closed handling for custom hooks and unresolved post types.
- Adds focused coverage proving the non-category grid is not executed and the existing oversized article-query truncation guard remains intact.

### 2.0.13

- Supports statically bound JetEngine Query Builder widgets only when the exact saved query returns WordPress posts and has no runtime-dependent values.
- Executes a cloned, cache-disabled query through JetEngine's context-bound public API with exact-query result limits and the existing public-post/category safeguards; all other Query Builder types fail closed without execution.
- Adds focused coverage for bounded Query Builder post results and pre-execution rejection of non-post and dynamic queries.

### 2.0.12

- Reproduces Elementor's ordered prior-post context for duplicate-avoiding homepage widgets while preserving saved offsets, query bounds, provider state, and existing public manifest provenance.
- Privately primes server-side query history from statically categorized and responsive-hidden preceding widgets, and leaves dependent widgets explicitly partial whenever preceding results are incomplete.
- Adds focused coverage for prior-result ordering, reset and restoration, saved offsets, hidden/static priming, zero results, and fail-closed truncated or unsupported history. The first focused fixture run failed at the hidden-widget assertion; that traversal defect was corrected and the fixture was not rerun before release.

### 2.0.11

- Binds native query bounds to the exact Elementor or JetEngine widget query, leaving internal provider lookups untouched while preserving downward result limits and partial-evidence warnings.
- Adds regression coverage for provider hook markers, internal unlimited lookups, and marker cleanup.

### 2.0.10

- Adds bounded native Elementor Pro and JetEngine post-query collection for unfiltered homepage widgets, preserving exact query limits, public-post visibility, provider context, and explicit partial warnings when a query cannot be safely reproduced.
- Adds native-provider fixtures and regression coverage for query budgets, hook limits, context restoration, provenance, and reserved-category exclusion.

### 2.0.9

- Completes generic homepage publication manifests by traversing embedded Elementor templates with cycle and depth guards, interpreting supported taxonomy include/exclude rules, honoring device visibility, and reporting explicit evidence when a query cannot be statically resolved.
- Exposes native category descriptions and stable template/query evidence for downstream campaign setup while preserving reserved-category exclusions.

### 2.0.8

- Adds an administrator-controlled external publishing API, disabled by default, authenticated by WordPress Application Passwords, restricted by WordPress post capabilities, and protected by mutation idempotency keys.
- Adds verified create, read, update, and delete routes that accept only core post fields and registered REST taxonomy IDs.

### 2.0.7

- Publication manifests now report only taxonomies registered for active article post types. The optional `smpi_article_type` capability is omitted when Article Types is disabled, preventing downstream publishers from attempting an unavailable taxonomy.
- Manifest caches are version-scoped so an installed plugin update exposes the new capability payload immediately.

### 2.0.6

- Publication manifest now reads Elementor Pro Loop Grid and Loop Carousel category filters stored as plain term IDs (`post_query_include_term_ids`), so homepages built with them no longer report zero campaign categories. Exclusion term lists are ignored.

### 2.0.5

- Added the public `/wp-json/smpi/v1/publication-manifest` endpoint for sanitized publication identity, Elementor homepage structure and query categories, taxonomy policy, recent content, authors, publishing requirements, media, SEO, schema, and delivery capability data.
- Added deterministic manifest caching, invalidation hooks, ETags, fingerprints, and reserved/excluded taxonomy policy so downstream campaign setup can collect publication data without AI calls or HTML guessing.

### 2.0.4

- Added an opt-in author-archive loading policy that maps pagination, infinite scroll, or load-more selection to Elementor Pro's native current-query Loop Grid controls.
- Added five scoped minimalist loading-control designs plus a true No Style option, with backend visual previews and stable wrapper class documentation.

### 2.0.3

- Added selectable author-archive social-icon placement below the name, title, image, or bio, with server-rendered Elementor injection and a compact No Style backend preview.

### 2.0.2

- Preserved independent top-level publisher and author relationships in normalized article schema graphs with Hexa WP Core 3.0.1.

### 2.0.1

- Added the opt-in Author social icons feature with accessible shortcode output, five styled templates plus No Style, scoped Elementor CSS guidance, configurable networks and sizing, and optional single-post or author-archive placement.

### 2.0.0

- Coordinated major release for the consolidated publication lifecycle, runtime, editorial, schema, and admin infrastructure, synchronized with Hexa WordPress Plugin Core 3.0.0.

### 1.0.27

- Restricted breadcrumb companion cleanup to SMP-owned bands so Elementor page-title containers cannot be removed, and aligned generated breadcrumb content to the canonical 1140px responsive grid.
- Consolidated plugin lifecycle, updater, registries, and admin AJAX dispatch under the shared Core bootstrap and synchronized the bundled Hexa WP Core 2.1.4 package.

### 1.0.26

- Resolved local author avatars through their media attachment before stored full-size URLs and rendered listing cards with native WordPress responsive image markup.

### 1.0.25

- Removed the final stale reading-progress reference from the Article Design description after ownership moved to HWS Base Tools.

### 1.0.24

- Moved generic reading-progress rendering and configuration ownership to HWS Base Tools, removing the duplicate SMP runtime, Article Design controls, defaults, validation, and AJAX inputs while leaving existing stored values available for the HWS migration.

### 1.0.23

- Prevented the featured-image publishing guard from recursively retriggering its own editor-wide mutation observer and freezing post editor screens.

### 1.0.22

- Added a wrapper-preserving, presentation-free FAQ mode for Elementor-owned styling.

### 1.0.21

- Normalized article drop caps after WordPress paragraph generation so unwrapped post content cannot move the first-letter design to a later contact block.
- Made the browser fallback remove stale lead classes and reject contact, telephone, email, hidden, and non-article paragraphs.

### 1.0.20

- Prevented disabled article heading and drop-cap features from emitting typography CSS or adding their feature-specific markup classes.
- Kept table-of-contents anchor generation independent from article heading and drop-cap styling.

### 1.0.19

- Added an opt-in reading-progress scope control for posts, posts plus the front page, or the full public frontend while preserving posts-only behavior by default.
- Made the progress indicator's accessible label reflect whether it is rendering on an article or another public page.

### 1.0.18

- Restored Rank Math JSON-LD on ordinary pages, taxonomy archives, and post-type archives while preserving SMP's duplicate-schema suppression on owned home and singular integration contexts.

### 1.0.17

- Added the context-aware `smpi_article_markup_enabled` filter so native podcast episode templates can suppress editorial post-content normalization without brittle cross-plugin callback removal.
- Replaced target-only breadcrumb and article-normalizer executable blocks with one deferred, idempotent public DOM asset that preserves SSR markup and reinitializes on `smp:content-ready`.

### 1.0.16

- Guarded SMP query mutations and paired SQL filters with shared Hexa WP Core eligibility and static-front-page checks.
- Scoped secondary publication loops through exact host-owned Elementor Query IDs, preserved queries that suppress filters, and removed Elementor author post-ID prefetching.
- Cached normalized SMP settings per site and request with invalidation after writes, direct option updates, and multisite blog switches.
- Replaced author-directory full-ID scans with bounded existence queries while retaining full enumeration for user-removal workflows.
- Updated the bundled Hexa WP Core to 1.2.0 and the minimum PHP version to 8.1.

### 1.0.15

- Made the author press-release setting override Hexa PR Wire exclusions in native and Elementor author queries.

### 1.0.14

- Added reusable author-listing controls for member image and article requirements plus author-archive press releases.
- Made staff and contributor directory cards fully clickable and removed the separate View Member link.
- Added real profile-image detection for ACF, Simple Local Avatars, WP User Avatars, and non-default Gravatars.

### 1.0.13

- Added explicit `Strip all design (Elementor)` modes for article summaries and tables of contents.
- Preserved semantic markup, headings, anchors, ARIA behavior, and shortcode hooks while excluding stripped components from all plugin-owned presentation and typography selectors.

### 1.0.11

- Normalized SMP graphs through Hexa WP Core 1.1.6 so every top-level schema entity remains independently detectable in Schema.org Validator.
- Retained typed author, publisher, copyright-holder, and image summaries while converting reference-only objects to identifier URL relationships.

### 1.0.10

- Kept `WebPage` and article nodes as independent Schema.org validator items by using the canonical URL for `mainEntityOfPage` and `WebSite` for `isPartOf`.
- Added regression coverage that prevents internal page/article edges from folding `NewsArticle` into `WebPage`.

### 1.0.9

- Removed the invalid homepage `CollectionPage.hasPart` reference to `ItemList`; the valid `mainEntity` relationship remains the single link to the list.
- Added homepage relationship regression coverage after the independent Schema.org validator exposed the range mismatch.

### 1.0.8

- Added typed publication URL resolution that skips malformed option values and continues through valid aliases and sources before using the current site URL.
- Added fail-closed schema URL sanitization after graph filters plus semantic integrity reporting for syntactically valid JSON-LD with invalid property types.
- Prevented unrelated ACF option groups from leaking into schema, publication profile, and MuckRack URLs.
- Updated the complete bundled Hexa WordPress Plugin Core package to 1.1.5 with URL guards and semantic schema scan reporting.

### 1.0.7

- Replaced wildcard MuckRack author-name matching with ranked semantic targets for author links, headings, author boxes, image/icon-box titles, and bylines.
- Excluded biographies, excerpts, article copy, pagination, and post loops from author badge placement.
- Limited footer verification to one author-name target so badges stay beside the displayed author title across custom Elementor layouts.

### 1.0.6

- Restored the native queried author object during `pre_get_posts` so Elementor Theme Builder author-archive conditions work consistently for cached, uncached, and authenticated requests.

### 1.0.5

- Refined the soft-tint Summary template as compact secondary content with smaller typography, tighter spacing, sharper corners, and a restrained border and title marker.

### 1.0.4

- Expanded selected Founder Profiles into complete, responsive profile records with photos, biographies, contact details, social URLs, personal information, affiliations, media, and WordPress record data.
- Made founder selection and removal replace the UI from the saved AJAX response so every profile field appears immediately and remains synchronized with persisted settings.
- Added dynamic legacy ACF metadata discovery, repeater formatting, duplicate URL suppression, and protected/system-field filtering.

### 1.0.3

- Moved Founder Profiles from the Overview publication card into a dedicated Publication sidebar tab.
- Rebuilt founder management as a compact, responsive screen while preserving Hexa WP Core search and AJAX assignment behavior.
- Corrected the empty Core search-selection state and added live selected-profile counts and clearer save feedback.

### 1.0.2

- Removed excessive implicit grid rows from the divider, number-tile, and circular-marker ordered-list designs.
- Kept arbitrary direct list-item content in the text column so classic-editor markup remains compact and aligned.
- Replaced every Summary template name with a clear description of its visible treatment.

### 1.0.1

- Added Numbered article list styles with five clearly labeled, one-row templates for top-level ordered lists while leaving nested and plugin-owned lists unchanged.
- Registered numbered-list color and typography through the same Hexa WP Core controls used by the other Article Design surfaces and added the feature to Quick Start.
- Added the bordered Summary template with a compact What to know label and diamond bullets while limiting selected-color changes to its intended accent areas.
- Renamed affected Article Design cards and template choices with labels that state the visible treatment without changing internal setting or template keys.

### 1.0.0

- Established the stable publication integration baseline for article types, authorship, editorial features, templates, CPTs, FAQs, and schema.
- Updated all reusable registration, rendering, injection, updater, AJAX, and admin UI infrastructure to Hexa WP Core 1.0.0.
- Preserved existing publication settings, author relationships, article output, schema graph IDs, and optional HWS canonical-entity fallback behavior.

### 0.6.253

- Preserved the live reading-progress, Elementor frontend compatibility, and configurable Summary background features.
- Kept CPT, entity, FAQ rendering, and schema infrastructure on their canonical Hexa WP Core paths.
- Removed SMP's Elementor TOC markup replacement so Elementor remains the sole owner of native TOC output.
- Extended regression coverage for the combined Core and live feature contracts.

### 0.6.250

- Added Core-managed Knowledge Base and Resources CPT/ACF controls with editable labels and public slugs.
- Consumed the optional HWS canonical entity and attached WordPress author without making a primary entity mandatory.
- Routed FAQ and schema document rendering through Hexa WP Core.
- Removed the legacy updater wrapper and corrected GitHub updates to the canonical `main` branch.
- Updated the bundled Hexa WordPress Plugin Core to 0.19.78.
- Consolidated this README; complete historical release details remain in Git history.

## Support

Report issues at <https://github.com/mikeyperes/smp-publication-integration/issues>.

## License

Proprietary Scale My Publication software unless a source file states otherwise.
