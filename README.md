# SMP Publication Integration

Editorial, publication-profile, article-type, authorship, design, and structured-data tooling for Scale My Publication websites.

## Identity

- Repository: `mikeyperes/smp-publication-integration`
- Plugin slug: `smp-publication-integration`
- Namespace: `smp_publication_integration`
- GitHub branch: `main`
- Version: `1.0.10`

## Ownership

SMP Publication owns publication-specific behavior:

- Publication identity and publication article types.
- Editorial features, article templates, breadcrumbs, summaries, FAQs, tables of contents, captions, and inline-photo treatments.
- Multi-author assignment, bylines, author displays, and MuckRack verification.
- Publication and article schema object construction.
- Blog-like custom post types: `knowledge-base` and `resources`.

HWS Base Tools owns website classification and the optional primary entity. SMP consumes a canonical Publication or Organization entity from HWS and retains legacy publication-user settings only as migration fallback.

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

Article-level multi-author assignments, primary authors, Elementor bylines, author archives, and fallback WordPress authors retain their previous behavior.

## Schema

SMP constructs publication and article nodes; Hexa WP Core owns reusable schema normalization, deduplication, safe JSON-LD encoding, and output injection. FAQ schema uses the same shared FAQ source and renderer as visible FAQ output.

Rank Math coexistence, stable graph IDs, publication-logo fallbacks, article relationships, and author relationships must remain unchanged during upgrades.

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

Namespaced domain code lives under `src/`. Reusable UI, AJAX, updater, CPT, ACF, entity, FAQ, schema, taxonomy, activity-log, color, typography, and template infrastructure comes from Hexa WordPress Plugin Core 1.1.5.

The plugin updater targets the repository's canonical `main` branch and registers `Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater` directly.

## Requirements

| Requirement | Minimum |
| --- | --- |
| WordPress | 5.0 |
| PHP | 8.0 |
| Hexa WP Core bundle | 1.1.5 |

ACF Pro is required for publication option and content field groups. Feature-specific integrations require their corresponding plugins.

## Installation

Install the repository as `wp-content/plugins/smp-publication-integration` and activate `smp-publication-integration.php`. Existing installations activated through `initialization.php` are handled by the compatibility migration.

## Development

Run every focused regression file with:

```bash
for file in tests/*.php; do php "$file" || exit 1; done
```

The suite covers navigation, article defaults, article/FAQ output, authorship, templates, colors, typography, breadcrumbs, content types, schema fallbacks, and updater configuration.

## Changelog

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
