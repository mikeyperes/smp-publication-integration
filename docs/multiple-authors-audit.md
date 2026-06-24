# Multiple Authors Audit

## Reference releases

- Co-Authors Plus 4.0.2
- PublishPress Authors 4.15.0
- Molongui Authorship 5.2.9

The audit covered every first-party PHP, JavaScript, and TypeScript file in the downloaded WordPress.org release packages. Vendored dependencies, generated translations, images, and compiled assets were inventoried but not treated as authorship design sources.

## Reference patterns

### Co-Authors Plus

- Uses a private ordered taxonomy for canonical post-author relationships.
- Synchronizes the native `post_author` column to a valid primary WordPress user.
- Covers classic and REST saves, autosaves, revisions, deletion, capabilities, archives, imports, CLI backfills, blocks, template tags, and cache invalidation.
- Provides a small public API centered on `get_coauthors()` and `is_coauthor_for_post()`.

### PublishPress Authors

- Uses normalized author objects, utility services, query services, editor services, REST endpoints, migrations, capabilities, cache groups, and integration modules.
- Supports explicit primary-author synchronization and a stable `get_post_authors()` API.
- Keeps schema, Elementor, SEO, author boxes, and migrations outside the core author assignment method.

### Molongui Authorship

- Uses ordered typed references and a normalized Author model.
- Separates persistence, byline formatting, schema, author boxes, query integration, deletion, counters, and compatibility adapters.
- Provides explicit controls for separators, linked names, number of authors shown, guest authors, REST output, and enabled post types.

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

## Target architecture

1. `AuthorAssignmentRepository` owns ordered assignment storage, normalization, migration, native author synchronization, and cache invalidation.
2. `AuthorRecord` is the normalized author model used by every output consumer.
3. `AuthorFieldResolver` is the only alias and user-field lookup implementation.
4. `AuthorContext` resolves explicit user, archive, repeated author, and post fallback contexts.
5. `AuthorLifecycle` owns ACF, metadata, REST, deletion, and migration hooks.
6. `AuthorQueryIntegration` owns author archive and Elementor query behavior.
7. Shortcodes, schema, verification, loop bylines, and Elementor modules consume the same repository and records.
8. Elementor repetition is allowed only on explicitly marked author modules and must preserve the original Elementor structure and styling.
