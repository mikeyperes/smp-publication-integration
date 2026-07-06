# Multiple Authors Audit

## Reference releases

- Co-Authors Plus 4.0.2
- PublishPress Authors 4.15.0
- Molongui Authorship 5.2.9

The audit covered every first-party PHP, JavaScript, and TypeScript file in the downloaded WordPress.org release packages. Vendored dependencies, generated translations, images, and compiled assets were inventoried but not treated as authorship design sources.

Source package coverage:

- PublishPress Authors 4.15.0: 1,000 files, 244 PHP files, 61,369 PHP lines, zip SHA-256 `0b2f72286d3faeb370ee5038434e38ed5bdcad275cfb2f79448b3d0b6777e365`.
- Molongui Authorship 5.2.9: 367 files, 270 PHP files, 42,365 PHP lines, zip SHA-256 `a366cd9196ffa6639a61dcb0e949fc6699b0a268e96a4e4fade6fa2d16cf8cdf`.
- Co-Authors Plus 4.0.2: 60 files, 31 PHP files, 9,812 PHP lines, zip SHA-256 `6e149204becc21e28d297db0b8c26de44cd9fe817cf2f8299d7e28b60372e05b`.

## Reference patterns

### Co-Authors Plus

- Uses a private ordered taxonomy for canonical post-author relationships.
- Synchronizes the native `post_author` column to a valid primary WordPress user.
- Covers classic and REST saves, autosaves, revisions, deletion, capabilities, archives, imports, CLI backfills, blocks, template tags, and cache invalidation.
- Provides a small public API centered on `get_coauthors()` and `is_coauthor_for_post()`.
- Best pattern for SMP's narrow use case: keep storage small and canonical, then expose compatibility through template tags, query filters, REST field handling, and native `post_author` sync.
- Avoided pattern: broad guest-author subsystem. SMP currently needs WordPress user authors only.

### PublishPress Authors

- Uses normalized author objects, utility services, query services, editor services, REST endpoints, migrations, capabilities, cache groups, and integration modules.
- Supports explicit primary-author synchronization and a stable `get_post_authors()` API.
- Keeps schema, Elementor, SEO, author boxes, and migrations outside the core author assignment method.
- Best pattern for SMP: normalized author records and separate service boundaries.
- Avoided pattern: the large author-profile, author-category, author-box, custom-field, and migration UI surface. That is too broad for SMP's current need.

### Molongui Authorship

- Uses ordered typed references and a normalized Author model.
- Separates persistence, byline formatting, schema, author boxes, query integration, deletion, counters, and compatibility adapters.
- Provides explicit controls for separators, linked names, number of authors shown, guest authors, REST output, and enabled post types.
- Best pattern for SMP: presentation controls are small settings that affect rendering only, not storage.
- Avoided pattern: author boxes, related posts, extensive settings panels, and guest author storage.

## SMP findings before refactor

- `MultiAuthors.php` combined persistence reads, fallback policy, runtime context, shortcodes, Elementor discovery, DOM cloning, field aliases, social links, and verification badges.
- The ACF field's serialized post meta was queried with `LIKE`, which was both unindexed and format-dependent.
- Native `post_author` was not reliably synchronized with the selected primary author.
- No canonical write API existed.
- No REST assignment path, deletion policy, migration state, capability boundary, or cache invalidation contract existed.
- Author field aliases and value resolution were duplicated.
- Author archives and Elementor archive queries used separate implementations.
- Loop bylines and single author modules used different author resolution paths.
- Presentation relied on broad HTML string replacement and heuristic DOM matching.
- Verification badge insertion was coupled to author module cloning.
- Supported post types were hard-coded.
- The repository contained no automated authorship test suite.
- Version records disagreed between the plugin header, README, and commit history.
- MuckRack verification kept a second field-alias resolver after the first refactor pass; v0.6.104 centralizes that on `AuthorFieldResolver`.

## Target architecture

1. `AuthorAssignmentRepository` owns ordered assignment storage, normalization, migration, native author synchronization, and cache invalidation.
2. `AuthorRecord` is the normalized author model used by every output consumer.
3. `AuthorFieldResolver` is the only alias and user-field lookup implementation.
4. `AuthorContext` resolves explicit user, archive, repeated author, and post fallback contexts.
5. `AuthorLifecycle` owns ACF, metadata, REST, deletion, and migration hooks.
6. `AuthorQueryIntegration` owns author archive and Elementor query behavior.
7. Shortcodes, schema, verification, loop bylines, and Elementor modules consume the same repository and records.
8. Elementor repetition is allowed only on explicitly marked author modules and must preserve the original Elementor structure and styling.

## Implemented in v0.6.103-v0.6.104

- Added `Authorship\AuthorAssignmentRepository` as the single canonical assignment store backed by a private ordered `smpi_author` taxonomy.
- Kept existing ACF field `smpi_post_authors` as the editor UI and synchronized it into canonical terms.
- Synced native `wp_posts.post_author` to the first selected author for compatibility with WordPress, themes, Elementor, feeds, and SEO integrations.
- Added `AuthorRecord`, `AuthorFieldResolver`, `AuthorContext`, `AuthorLifecycle`, `AuthorQueryIntegration`, `ElementorArchiveContext`, `ElementorAuthorRenderer`, and `LoopBylineRenderer`.
- Moved author archive and Elementor query support out of `Visibility`.
- Replaced serialized meta `LIKE` author archive matching with taxonomy relationship matching.
- Added REST field support for `smpi_post_authors`.
- Added delete-user cleanup and optional reassignment support.
- Added incremental admin migration from legacy ACF meta to canonical taxonomy terms.
- Added cache invalidation on assignment and term relationship changes.
- Added server-side DOM tests covering canonical order, primary author sync, author context, loop bylines, Elementor module repetition, avatar rebinding, URL rebinding, and shared field alias resolution.
