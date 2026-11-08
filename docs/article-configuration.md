# Article configuration

The Custom Post Types settings tab owns the placeholder article selection and feature grid. Any registered public post type can be selected, including custom types. New types start with all features off. Saving requires manage_options and a WordPress nonce; writes use admin-post.php. Unchecked types retain dormant feature flags. No posts, taxonomies, terms, author assignments or field values are deleted.

## Storage and upgrade

`smpi_article_configuration` stores `version`, `article_types` and `features[feature][post_type]` boolean flags. On the first init, a one-time, add-only migration seeds post, press-release, knowledge-base and resources. Resolution intersects the list with currently registered public types, so absent types remain dormant. Both legacy article-type functions delegate to this same resolver.

Site-wide switches seed every selected type from their existing value. Breadcrumb exclusions and cleanup inclusions seed their respective rows; current HWS Base Tools breadcrumb settings take precedence when present because that plugin now owns breadcrumb display. The old settings remain intact for compatibility. Changing an old global switch applies that feature to every selected type; the matrix is authoritative thereafter. Other unrelated settings are not rewritten by the grid.

ACF summary and FAQ groups retain stable keys and build their OR locations from the feature policy. A group with no enabled types is inactive. The existing ACF registry's whole-structure switch remains an additional dependency. Taxonomy and multi-author editor registrations use their respective enabled type lists. Disabling a feature suppresses plugin output, not saved data; native theme output and other plugins retain their own ownership.

## Feature catalog

The engine owns summary (What to Know), FAQs, article types, read time, table of contents, multiple authors, bylines, article schema, automatic content blocks, cleanup, breadcrumbs, heading styles, numbered lists, drop caps, inline photos, featured image captions, featured image requirement, primary category, author social icons, Muck Rack verification, content generation, post dates, Going Live checklist, visibility controls and editor-list defaults.

Feature policy supplements existing layout, placement, style and per-post choices. Automatic content blocks also require the summary/FAQ row. Starting summary or FAQ generation requires both generation and that target's feature. Disabling article schema retains ordinary page/site identity schema. Breadcrumb markup remains owned by HWS Base Tools; a read-only option adapter applies this plugin's article-type policy without changing its stored settings. Press-release distribution decisions remain owned by the distributor.

## Manifest and extension API

GET `/wp-json/smpi/v1/publication-manifest` includes `article_configuration.article_types` and `article_configuration.features`, where each feature value is its effective list of enabled article types. The section is included in the fingerprint. Configuration changes invalidate the manifest cache.

`ArticleFeatures::types($feature)` returns enabled types. `enabled($feature, $post_id)` evaluates one object; pass explicit IDs for shortcodes, REST and background work. `setting($legacy_key, $post_id)` is the compatibility counterpart for runtime consumers. Global `Settings::bool()` reports whether a feature is on anywhere, while `Settings::all()` retains raw legacy values for migrations and older screens.

Filters `smpi_article_post_types($types)` and `smpi_article_feature_post_types($types, $feature)` accept arrays of registered public type slugs. Feature results are intersected with selected article types. To add another feature, add its stable ID, label and optional legacy switch in ArticleFeatures::definitions, then consume its policy at the feature's editor and runtime entry points.

The grid loads no additional assets. Existing article assets retain their existing context checks and now also consult feature policy.
