# Hexa PR Wire schema handoff

SMP now owns editorial article schema for normal posts. Hexa PR Wire should mirror the reporting and test pattern for the `press-release` CPT without forcing press releases into editorial NewsArticle types.

Recommended press release graph for a single press release:

- NewsMediaOrganization for the publishing outlet, using the same SMP publication option fields where available.
- WebSite for the domain.
- WebPage for the press release URL.
- Article as the main content node with `genre` set to `Press Release`.
- Organization as the source company when the press release provides one.
- Person or Organization for author when an author exists.
- ImageObject for featured image.
- BreadcrumbList when breadcrumbs are visible.
- FAQPage only when structured FAQ rows exist and are enabled for schema.

Required plugin work in Hexa PR Wire:

- Register or reuse `smpi_article_type` on `press-release` and default to the `press-release` term.
- Add a schema tab or report section that shows ideal graph, actual graph, and integrity checks.
- Add a public JSON endpoint equivalent to `/wp-json/smpi/v1/schema?post_id=ID`.
- Filter Rank Math overlap for press release schema only when Hexa PR Wire outputs its own complete graph.
- Test with validator.schema.org and front end JSON LD extraction.

Do not describe PR content as `ReportageNewsArticle` unless it is true newsroom reporting. Press release content should normally be `Article` with clear press release genre metadata.
