# SMP Publication Integration

Editorial, publication-profile, article-type, authorship, design, and structured-data tooling for Scale My Publication websites.

> Feature base for HWS Skills. Read before building on this plugin; use or
> extend these features instead of rebuilding them.

**Purpose:** editorial, authorship, verification and publication-profile features for Scale My Publication news sites. **Admin:** WP Admin → SMP Publication (`admin.php?page=smp-publication-integration`); every switch below is a key in the `smpi_settings` option. **Depends on:** ACF Pro, Rank Math (breadcrumbs), HWS Base Tools (primary entity); Elementor optional.

## Design examples and styling

Start with [Article design examples](docs/article-design-examples.md). Each
example includes its shortcode, representative rendered HTML, editable Elementor
CSS, a selector map and a link to the owning renderer:

- [What to know: HTML and CSS](docs/article-design-examples.md#what-to-know)
- [In this article / Contents: HTML and CSS](docs/article-design-examples.md#in-this-article)
- [FAQs: HTML and CSS](docs/article-design-examples.md#frequently-asked-questions)
- [Writes about (author categories): HTML and CSS](docs/article-design-examples.md#writes-about)
- [Listen / text-to-speech: HTML and CSS](https://github.com/mikeyperes/smp-wp-text-to-speech/blob/main/docs/player-design-examples.md), owned by the companion audio plugin.

To see every built-in visual variant, open **SMP Publication > Features > Article
design** on an installed site:
`/wp-admin/admin.php?page=smp-publication-integration&tab=features#smpi-feature-group-article-design`.
The Summary, Table of contents and FAQ design cards use the same renderer/styles
as the frontend. The examples explain named skins versus Elementor-owned skins.

## Features

### Breadcrumbs (moved)
- **Does:** nothing here any more. The breadcrumb bar under the site header moved to HWS Base Tools (WP Admin → Settings → HWS Base Tools → Breadcrumbs, `options-general.php?page=hws-core-tools&tab=breadcrumbs`), where it is switched off by default.
- **Carry-over:** the old `breadcrumbs_*` keys stay in `smpi_settings` only so Base Tools can copy a site's look the first time it is switched on there. `[smp_breadcrumbs]` keeps working through Base Tools.
- **Schema:** the `BreadcrumbList` schema stays in SMP (`src/StructuredData/SchemaManager.php`).
### MuckRack verified badge
- **Does:** a "Verified by MuckRack" icon or label next to verified authors and the publication, in loop cards, bylines and author boxes.
- **Switch:** `muckrack_verified_enabled`, where it shows `muckrack_verified_contexts`, `muckrack_author_always_show`; look `muckrack_verified_style`, `muckrack_icon_style`, `muckrack_icon_color`, `muckrack_verified_font_*`, `muckrack_verified_text_color`. Publication badge: `publication_muckrack_verified_enabled`, `publication_muckrack_placements`, `publication_muckrack_*`.
- **Use:** automatic in its contexts; `[muckrack_verified type="icon|text" user_id="" post_id="" author_index="" style="" context=""]`, `[author_muckrack_verified]`, `[smp_publication_muckrack_verified class="" style="" color=""]`.
- **Style:** `.smpi-muckrack-inline-pair`, `.smpi-muckrack-icon`, `.smpi-muckrack-link`.
- **Code:** `src/Content/MuckRackVerification.php`

### Archive listings (lead story, the rest, story count)
- **Does:** lets an Elementor archive template (category, tag, taxonomy, author or search) show the newest story on its own and then the rest of the archive in whole-row batches with Load more, plus the archive's story count. Author listings include co-authored stories.
- **Use:** give one Loop Grid or Posts widget (Source: Posts) the Query ID `smpi_archive_lead` for the lead story, and a second one the Query ID `smpi_archive_rest` for the rest; the rest listing starts at the second story and uses the widget's own posts per page (8 for four per row). `[smp_archive_count singular="story" plural="stories"]` prints the count ("125 stories") and nothing when the archive is empty. Infinite scroll with a visible Load more button comes from HWS Base Tools (CSS class `hws-auto-load-more`; `smpi-auto-load-more` still works).
- **Style:** `.smpi-archive-count`.
- **Code:** `src/Content/ArchiveListing.php`

### Archive search
- **Does:** category, tag, author and search pages get a search box with Category, Tag and Author filters right above the stories; the stories, counts and address refresh as readers type or tick. The archive's story list (a Loop Grid, Posts or Archive Posts widget showing the page's own stories or pinned to the page's own category or tag, or the lead and rest listings) is found by itself, so existing templates need no edit. On a page with a lead story above the story grid, the box sits under the lead, directly on top of the grid it narrows. An archive the theme draws itself gets the box above its first story. Category, tag and author feeds never get the box, so they stay valid RSS. Author pages and the Author filter include co-authored stories; archive visibility rules apply to every count.
- **Use:** on by default (Settings: "Show the search box on archive pages"). A template that places `[hws_search]` itself keeps its own box. Needs HWS Base Tools 13.17.1 (Hexa WP Core 3.30.2).
- **Code:** `src/Content/ArchiveSearch.php`

### Related tags
- **Does:** on a tag or category archive, links to the tags used most often on the same stories, the archive's own tag left out. Prints nothing, label included, when there are none.
- **Use:** `[smp_related_tags limit="8" label="Related tags" class=""]`.
- **Style:** `.smpi-related-tags`, `.smpi-related-tags-label`, `.smpi-related-tag`.
- **Code:** `src/Content/RelatedTags.php`

### Author fields
- **Does:** any author's name, bio, title, socials, image or ACF field for the current post's author (or a given user / co-author).
- **Use:** `[author_name]`, `[author_bio format="html"]`, `[author_bio_short words="35"]`, `[author_title]`, `[author_subtitle]`, `[author_image size="thumbnail" output="html" class="smpi-author-image" loading="lazy" sizes=""]` (`loading="eager"` for a photo on the first screen), `[author_categories limit="4" separator=", "]` (the "Writes about" list: the author's published-post categories, most posts first, ties by oldest category, Uncategorized skipped, empty when none; [HTML and CSS](docs/article-design-examples.md#writes-about); `.smpi-author-categories`, `.smpi-author-category`), `[author_facebook]` `[author_instagram]` `[author_x]` `[author_linkedin]` `[author_youtube]` `[author_website]` `[author_crunchbase]` `[author_muckrack]` `[author_email]`, `[acf_author_field field=""]`. All accept `user_id`, `post_id`, `author_index`.
- **Responsive photos:** optional `sizes` supplies the real display widths. A nonempty value adds escaped `sizes`, and, for a matching media-library image, its own `width`, `height` and available `srcset`. Omitted or empty `sizes` preserves the previous HTML exactly; `output="url"` is unchanged. Remote photos without matching attachment metadata receive only `sizes`. [Markup and build example](docs/author-image.md).
- **Code:** `src/Content/AuthorShortcodes.php`

### Author social icons
- **Does:** the author's social links as styled icons, added automatically to author boxes and archives. Networks: Website, LinkedIn, X, Instagram, Facebook, YouTube, Crunchbase and Muck Rack (on by default), plus TikTok, Medium, GitHub, SoundCloud, Flickr, Behance, IMDb, Wikipedia, NFX Signal, Everipedia and Wikitia (off until picked in `author_social_networks`; NFX Signal, Everipedia and Wikitia show as short text marks, class `smpi-author-social-mono`).
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

### Executive leadership
- **Does:** the publication's Executive Leadership Team. Each user profile gets "Is part of the executive leadership team" and an Executive Leadership Title (used only on that page); people can also be added on the tab by typing a name or email (Hexa WP Core's user search), dragged into order, titled and removed, and both stay in step. Turning it on links the leadership page or creates it from the default template; off hides the profile fields and the list. Styles: Cards (default), List or No styling, with a live example on the tab.
- **Switch:** Settings → SMP Publication Integration → Executive Leadership (`options-general.php?page=smp-publication-integration&tab=executive_leadership`); option `smpi_executive_leadership`, user meta `smpi_executive_leadership` and `smpi_executive_leadership_title`.
- **Use:** `[smpi_executive_leadership style="cards|list|none" columns="3" ids="" limit="" hide="photo,title,bio,link" link="profile|none" bio_words="30" photo_size="medium" heading="h3"]`.
- **HTML structure and styling:** [docs/executive-leadership.md](docs/executive-leadership.md): markup, classes (`.smpi-leadership--<style>`, `.smpi-leader--<user>`, `__photo`, `__image`, `__name`, `__title`, `__bio`, `__link`), built-in CSS and how to style it.
- **Code:** `src/Content/ExecutiveLeadership.php`, `src/Admin/ExecutiveLeadershipTab.php`; shared list pieces `src/Content/People/PeopleCards.php`, `src/Admin/People/PeopleEditor.php`

### Founders (read from HWS Base Tools)
- **Does:** founders are defined only in HWS Base Tools (Settings → HWS Base Tools → Founders). SMP stores none: its Founders tab is a read-only list under Hexa WP Core's source banner with a link to edit them in Base Tools; the publication's search-engine data (`founder` in the organization data), `[smp_company field="founders"]` and `[smp_publication_founders]` read Base Tools' list.
- **Read:** `hws_publication_founders()` (each `user_id`, `name`, `title`, `url`), `hws_publication_founders_edit_url()`; SMP's single reader is `src/Support/PublicationFounders.php`.
- **Use on pages:** `[hws_publication_founders]` (Base Tools; `[smpi_founders]` stays as its alias). The Founder and Founders page templates show only this, with no founding date.
- **Removed in 2.5.19:** SMP's own Founders settings and shortcode (`smpi_founders` option is read once by Base Tools to carry the data over), the Founder Profiles section (Verified Profile records), the "Founder Authors" field `smpi_founder_users`, and the "Show founder marketing" switch.
- **Code:** `src/Support/PublicationFounders.php`, `src/Admin/FoundersTab.php`.

### Founding Date (from HWS Base Tools)
- **Does:** the founding date and city come only from HWS Base Tools' Website Identity (`[smp_company field="founding_date"]`, `field="founding_location"`); the publication's search-engine data (`foundingDate`, `foundingLocation`) reads the same facts. The Founding Date page applies only to company sites.
- **Removed in 2.5.21:** SMP's own founding fields (`smpi_founding_date`, `smpi_founding_location`, `smpi_founding_location_url`, `smpi_founding_date_extended`), the `[smpi_founding_date]` section, its switch `smpi_founding_date_section` and `docs/founding-date.md`.

### Contributors
- **Does:** the publication's contributors: everyone with the chosen roles (Contributor by default), plus people added from other roles, minus people removed, in a chosen order. The tab has role checkboxes, a type-to-search people list (Hexa WP Core user search) with drag ordering, "Added" badges and a "removed from the list" section to put people back, the Contributors page (create it or apply the default template), styles with a live example, CSS structure and the shortcode.
- **Use:** `[smpi_contributors]`; `show="badge,topics,count,latest,initials"` adds the verified mark, "Writes about" sections, story count, latest story and an initials fallback (all off by default), and `orderby="posts"` puts the most-published first ([docs/contributors.md](docs/contributors.md)).
- **Switch:** Settings → SMP Publication Integration → Contributors (`options-general.php?page=smp-publication-integration&tab=contributors`); option `smpi_contributors`.
- **Use:** `[smpi_contributors]` with the Executive Leadership attributes plus `roles="contributor,author"`, `with_photo="1|0"` and `with_articles="1|0"` (both default to the site's author-listing settings, like `[contributors_grid]`). The older `[contributors_grid]` is unchanged.
- **HTML structure and styling:** [docs/contributors.md](docs/contributors.md) (`.smpi-contributors--<style>`, `.smpi-contributor--<user>`, `__photo`, `__image`, `__name`, `__bio`, `__link`).
- **Code:** `src/Content/Contributors.php`, `src/Admin/ContributorsTab.php`; shared with Executive Leadership: `src/Content/People/PeopleCards.php`, `src/Admin/People/PeopleEditor.php`.

### Staff
- **Does:** everyone with HWS Base Tools' existing Staff writer profile switch on, with no role restriction. Add people by typing a name or email, drag to order, remove them, and optionally set a Staff Title shown under their name. Adding and removing update that same profile switch; changes made on a profile show on the tab too. Shared people-list renderer and editor, Cards/List/No styling with a live example, Staff page tools, CSS structure and full shortcode attributes.
- **Switch:** Settings → SMP Publication Integration → Staff (`options-general.php?page=smp-publication-integration&tab=staff`); option `smpi_staff` (style/order), existing user meta `staff_writer`, and `smpi_staff_title`.
- **Use:** `[smpi_staff style="cards|list|none" columns="4" ids="" limit="" hide="photo,title,bio,link" link="profile|none" bio_words="30" photo_size="medium" heading="h3" with_photo="1|0" with_articles="1|0"]`. Photo/article defaults follow the site's author-listing settings, like Contributors. The older `[staff_grid]` is unchanged.
- **HTML structure and styling:** [docs/staff.md](docs/staff.md): markup, isolated classes, CSS, page defaults and all attributes.
- **Code:** `src/Content/Staff.php`, `src/Admin/StaffTab.php`; shared `src/Content/People/PeopleCards.php`, `src/Admin/People/PeopleEditor.php`.

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
- **HTML/CSS example:** [In this article](docs/article-design-examples.md#in-this-article).

### Estimated read time
- **Switch:** `estimated_read_time_enabled`.
- **Use:** `[smp_estimated_read_time post_id="" unit="minutes" wpm="" format="friendly" output="" suffix="read"]`.
- **Code:** `src/Content/EstimatedReadTime.php`

### Published date
- **Does:** the article's original published date and time in the site timezone, without the relative-date display filter.
- **Use:** `[smp_post_date post_id="" format="M j, Y · g:i A"]`.
- **Code:** `src/Content/PostTime.php`

### Site feature switches (read route)
- **Does:** tells Publish and the skills, in one quick public request, which article features this site has switched on, so a campaign never produces a feature the site turned off.
- **Use:** `GET https://<site>/wp-json/smpi/v1/publication-manifest`, field `delivery_capabilities`:
  - `post_summary` - "What to know" summary (`post_summary_acf_enabled`)
  - `post_faq_items` - FAQs (`post_faq_items` / `post_faqs_acf_enabled`)
  - `article_audio` - "Listen to this article", reported from SMP WP Text To Speech's switch (`smp_wp_text_to_speech_article_audio_enabled`)
  - Quick read: `curl -s https://<site>/wp-json/smpi/v1/publication-manifest | jq .delivery_capabilities`
- **Extend:** `smpi_publication_manifest_*` filters (see Extension points).
- **Code:** `src/PublicationManifest/WordPressCollector.php` (`delivery_capabilities`)

### What to know (article summary)
- **Does:** the article's "What to know" summary block, placed automatically or by shortcode. A summary with no visible text (blank lines saved as non-breaking spaces) shows nothing.
- **Switch:** `post_summary_acf_enabled` (Article Design, "What to know"). Off removes everything: the editor field, the automatic placement, the shortcodes, the contents link, the go-live checklist item and the styles.
- **Fields:** its own field group "What to Know", key `group_64a7290b61191`, field `post_summary` (`field_65ab7ba0e849b`). The key is kept because sites carry a database "Post - Header" group with that key; SMP's local group replaces it, and with the switch off it is registered empty and inactive so neither copy shows.
- **Use:** `[smp_post_summary post_id="" format="html" style=""]`, `[smp_post_acf field="post_summary"]`.
- **Code:** `src/Content/AcfFields.php` (`post_summary_group`), `src/Content/Shortcodes.php`, `src/Content/PostSummaryPlacement.php`, `src/Content/ArticleStyles.php` (`has_visible_text`)
- **HTML/CSS example:** [What to know](docs/article-design-examples.md#what-to-know).

### FAQs
- **Does:** structured article FAQs, placed automatically or by shortcode, and the FAQPage schema built from the same rows.
- **Switch:** `post_faqs_acf_enabled` (Article Design, "FAQs"). Off removes everything: the editor fields, the automatic placement, the shortcodes, the FAQPage schema, the go-live checklist item and the styles.
- **Fields:** its own field group "FAQs", key `group_smpi_post_faqs`, fields `post_faq_schema_enabled` and `post_faq_items` (keys unchanged).
- **Use:** `[smp_post_faqs post_id="" format="html|accordion|text" style=""]`, `[smp_post_acf field="post_faq_items"]`. `format="accordion"` renders structured FAQ rows as native closed `details` and `summary` elements. It does not parse or migrate FAQ text already embedded in older article content.
- **Code:** `src/Content/AcfFields.php` (`post_faqs_group`), `src/Content/Shortcodes.php`, `src/Content/PostFaqPlacement.php`, `src/StructuredData/SchemaManager.php` (`faq_rows_for_post`)
- **HTML/CSS example:** [FAQs](docs/article-design-examples.md#frequently-asked-questions).

### Publication pages
- **Does:** the Publication Pages tab lists every standard publication page in four groups (About, People, Policies, Reader). Each row shows the WordPress page it is linked to (found automatically at its usual address, at any level such as `/about/headquarters/`; Change picks another), its status, its template (View / edit, Back to default) and one button: Create page, or Apply template, which saves the page's current content as a revision first.
- **Page types (23):** About (each under HWS Base Tools' About): Ownership and Funding (optional), Executive Leadership Team, Staff, Contributors, Masthead; Powered by Scale My Publication (under Base Tools' Parent Network; the standard Scale My Publication text). Policies (default texts follow the Trust Project's trust indicators and the SPJ Code of Ethics): Policies (every policy in one list), Editorial Guidelines, Editorial Policy, DMCA Policy, and the Google News policies (Publishing Principles, Verification and Fact Checking, Corrections, Ethics, Diversity, Diversity Staffing Report, Mission and Coverage Priorities, No Bylines, Unnamed Sources, Actionable Feedback). Reader: Latest Press Releases, Submit Your Press Release, Advertise (optional). "Advertise with Us" was merged into Advertise and "Writers" into Contributors (old keys still resolve). Verified-profile and leadership/culture council pages belong to their own plugins.
- **Owned elsewhere:** the company pages any website has belong to HWS Base Tools: About, Mission Statement, Founders (old key `founder_about`), Founding Date, Headquarters, Logo, Parent Network, Team, Contact, Privacy Policy, Terms of Use, Accessibility, FAQs and RSS Feed. They are shown read-only on the tab under the source banner, linked to Base Tools' Pages tab (`PageTypes::BASE_TOOLS_OWNED`, read through `hws_base_tools_linked_page( '<key>' )`); SMP's older `about_publication` key maps to `about`. Pages SMP linked before the move are handed to Base Tools, so none is created twice. Become a Contributor and Contributor Login belong to the Contributor Network plugin (`mikeyperes/smp-contributor-network`).
- **Parents:** each page type may name a `parent` page key, from SMP or HWS Base Tools. Creating or applying a page puts it under its parent; `wp smpi-pages ensure` sets every SMP page up in one call (the linked page, else one at a usual address, else a new page) and moves each under its parent, the old address redirecting (Hexa WP Core). SMP answers Core's `hexa_site_structure_page_id` for its keys, so another plugin's page can sit under an SMP page (`Pages\PageParents`).
- **Default page set:** `PageTypes::default_set()` leaves out optional pages (Parent Network, Ownership and Funding) and pages that do not apply; those are created only on request. `PageTypes::applies()` reads Website Identity only after `init`, never at plugin boot. The admin page manager (menus and pages AJAX) is built only for a request that calls one of its actions, on `admin_init`, never at startup.
- **Smart templates:** every sentence adjusts to the facts present. `[smp_company field="x" before=" in " after="."]` prints `before`/`after` only with a value, and skips `after="."` when the value already ends in a period (no "Inc.."). `[smp_if field="<fact>"]`, `[smp_if page="<page type>"]` (a linked page, including Base Tools' Contact) and `[smp_if shortcode="<tag>"]` (registered, so a feature switched off in Base Tools, such as Team, prints nothing instead of raw shortcode text) keep their text only when it holds; `not="1"` inverts. A paragraph left empty disappears. Example: no founding city gives "founded on December 1, 2021", not "in ."; no linked Contact page drops "or use our Contact page".
- **Templates:** one file per page type in `src/Pages/templates/<type>.html`, reader-facing only, generic for every site: no publication name typed in. Names, dates, places and people come from shortcodes: `[smp_company field="name|legal_name|description|url|logo|founding_date|founding_location|founders|headquarters_city|headquarters_address|parent_name|parent_url|email|phone|mission|profiles"]` (reads HWS Base Tools' `hws_site_identity()`, falls back to the older SMP fields), `[smp_publication_page type="contact" mode="link|url|title"]`, Base Tools' `[hws_publication_founders]`, `[smpi_executive_leadership]`, `[smpi_staff]`, `[smpi_contributors]`, `[smp_latest_press_releases count="10"]`, `[smp_press_release_form]` (submission form slot; both press release pages link to Hexa PR Wire, the parent syndicator), and Base Tools' `[hws_rss_feeds]`, `[site_logo]`, `[brand_asset_gallery]`. A site's edited copy is stored in `smpi_settings[page_templates]`; `[smp_publication_page_template type=""]` prints the current template.
- **Switch:** Settings → SMP Publication Integration → Publication Pages (`options-general.php?page=smp-publication-integration&tab=pages`); links in `smpi_settings[page_assignments]`.
- **Code:** `src/Pages/PageTypes.php` (the list), `PageTemplates.php`, `PageLinker.php` (finding, linking, create/apply), `PageParents.php` (parents, setup, Core page lookup), `CompanyFacts.php`, `LatestPressReleases.php`, `SourceNotice.php`; tab `src/Admin/Dashboard/Tabs/PagesTab.php`.

### Publication profile values
- **Does:** the publication's own profile: fields, mission, founders (from HWS Base Tools), system user, assigned pages and page templates.
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

### Elementor CSS and the page cache
- **Does:** adds each Elementor CSS file's change time to its link (with `elementor_css_cache_busting` on), and whenever Elementor clears its CSS cache also clears the LiteSpeed page cache, so no cached page links a deleted CSS file and shows unstyled.
- **Code:** `src/Content/ElementorCssCacheBusting.php`

### Admin code and shortcode displays
- **Does:** every code block on SMP's tabs (HTML structure, built-in CSS, Elementor CSS samples, feature implementation notes, the Publication Pages templates, Post Fields code examples, Publishing Rules readmes, Schema Settings JSON, Schema Tests results and the Post HTML Cleanup preview) is color-coded with a copy button (Hexa WP Core 3.34.1 or newer), and every shortcode (people tabs, Shortcodes tab, Article Design examples) is shown with its copy button and each attribute's default and meaning.
- **Code:** `src/Admin/CodeViews.php` over Hexa WP Core's `WpAdminComponents\CodeDisplay` and `ShortcodeRegistry\ShortcodeBreakdown`.

### Update Center
- **Does:** the Update Center tab (Maintenance group, `tab=update-center`) shows Hexa WP Core's plugin updater with the Git Report (repository, URL, branch, GitHub version against the installed one, update button) and Core's package panel (bundled Hexa WP Core against Core on GitHub). The sidebar's GitHub version reads the same check.
- **Code:** `src/Admin/Dashboard/Tabs/UpdateCenterTab.php` over `UpdaterPanelRenderer` and `CorePackagePanelRenderer`.

### Asset loading
- **Rule:** admin CSS and JS load only in wp-admin and only on SMP's own screens (`options-general.php?page=smp-publication-integration`) or the post editor features that need them (Content Generation, Going Live checklist, featured image check). Front-end CSS and JS load only for visitors and only where the feature is used: article styles, table of contents and Elementor table-of-contents fixes on singular content (not the home page); breadcrumb styles and `public-dom.js` where breadcrumbs show or on articles; author social icon styles on articles, author pages and pages using `[smp_author_social_icons]`; staff and contributor card styles only on pages that render a grid. Nothing admin-only loads for visitors. Site-wide features (MuckRack badges in loop cards, empty-social cleanup, Elementor menu fixes) load wherever they are switched on.
- **How to check:** load the home page, an article, a category page and an SMP admin tab, and list SMP's assets: `curl -s <url> | grep -oE "<(link|script|style)[^>]*(smpi|smp-publication-integration)[^>]*>"`. The public pages must show no `assets/admin/` file and no `smpi-admin` handle; a category or home page must show no `smpi-table-of-contents-styles`, `smpi-article-style-controls` or author-listing styles; signed in, a non-SMP admin screen (Dashboard, Posts) must show no SMP asset.
- **Code:** the `wp_enqueue_scripts` / `wp_head` / `admin_enqueue_scripts` callbacks in `src/Content/*` and `src/Admin/Dashboard/DashboardController.php`.

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
- Version: `2.8.1`

## Ownership

SMP Publication owns publication-specific behavior:

- Publication identity and publication article types.
- Editorial features, article templates, summaries, FAQs, tables of contents, captions, and inline-photo treatments.
- Optional multiple authors per article (per-post switch, off by default), bylines, author displays, and MuckRack verification.
- Publication and article schema object construction.
- Blog-like custom post types: `knowledge-base` and `resources`.
- Every publication standard page on the Publication Pages tab, including Terms of Use, Privacy Policy, Brand Assets, Headquarters and FAQs (moved from HWS Base Tools in 2.5.17). Their old HWS assignments (`hws_site_page_assignment_<type>`) are still read until a page is assigned here. Only the generic Contact page stays in HWS Base Tools.
- Newsroom-only settings: Google News URL, MuckRack verification, RSS feed, podcast, coverage topics, area served (Publication Settings tab).

HWS Base Tools owns the website's basic identity (company or person basics, read with `hws_site_identity()`; see its README "Site identity"), website classification, the optional primary entity, and the generic reading-progress feature. SMP consumes a canonical Publication or Organization entity from HWS and retains legacy publication-user settings only as migration fallback. Existing reading-progress values remain stored long enough for HWS Base Tools to migrate them without a direct database rewrite.

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

## Where things live (routing for skills)

- **Founders:** HWS Base Tools → Founders only. Read with `hws_publication_founders()`; SMP shows them read-only and never stores them.
- **Company or person facts** (name, legal name, logo, founding date and city, headquarters, parent company, contact email, phone, mission, online profiles): HWS Base Tools → Website & Primary Entity → Site identity. Read with `hws_site_identity()`; SMP shows them read-only under Hexa WP Core's source banner ("Original content at HWS Base Tools. Click here to edit.") and prints them with `[smp_company]`. Never copy them into SMP.
- **Brand files, logo, database optimization, WordPress admin cleanup, Contact page:** HWS Base Tools.
- **Publication standard pages, their templates and links:** SMP → Publication Pages (`src/Pages/`).
- **Newsroom settings** (Google News, MuckRack, RSS, podcast, coverage topics, area served): SMP → Publication Settings.
- **People on the site** (executive leadership, staff, contributors, multiple authors): SMP → People group. Founders are not edited in SMP.
- **Code and shortcode displays in wp-admin:** Hexa WP Core's `CodeDisplay` and `ShortcodeBreakdown`, called only through `src/Admin/CodeViews.php`. Never hand-build a `<pre>` or attribute list.
- **Article design, post fields, publishing rules, content generation, HTML cleanup:** SMP → Articles group. **Schema:** SMP → Search & Schema. **Plugins, integrations, article cleanup, shortcodes, Core:** SMP → Maintenance.

## Dashboard

23 tabs in six groups, one file per tab under `src/Admin/Dashboard/Tabs/` (the controller `src/Admin/Dashboard/DashboardController.php` only draws the page and routes):

- **Overview:** Dashboard, Quick Start.
- **Publication:** Publication Settings, Publication Pages, Menus.
- **People:** Founders (read-only, from HWS Base Tools), Executive Leadership, Staff, Contributors, Authors.
- **Articles:** Custom Post Types, Article Design, Post Fields, Content Generation (`ContentGenerationTab.php`), Publishing Rules, Post HTML Cleanup (`PostHtmlCleanupTab.php`).
- **Search & Schema:** Schema Settings, Schema Tests.
- **Maintenance:** Article & Media Cleanup, Plugins (with Integrations and Verified Profiles), Shortcodes, Update Center, Hexa WP Core.

Removed in 2.5.18 (old links redirect): Publication Fields, Founding Date, Brand Settings, Database Optimization and WordPress Admin (owned by HWS Base Tools), Founder Profiles (into Founders), Integrations and Verified Profiles (into Plugins).

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
| Hexa WP Core bundle | 3.34.2 |

ACF Pro is not required: publication option and content field groups run on Hexa WP Core's custom fields, which use ACF when it is active and store the same data natively when it is not. Feature-specific integrations require their corresponding plugins.

## Installation

Install the repository as `wp-content/plugins/smp-publication-integration` and activate `smp-publication-integration.php`. Existing installations activated through `initialization.php` are handled by the compatibility migration.

## Development

Run every focused regression file with:

```bash
for file in tests/*.php; do php "$file" || exit 1; done
```

The suite covers navigation, article defaults, article/FAQ output, authorship, templates, colors, typography, content types, schema fallbacks, and updater configuration.

## Changelog

### 2.9.15

- Opt-in `[author_image sizes="..."]` provides display widths, intrinsic attachment dimensions and responsive candidates; existing calls and URL output stay unchanged. Bundles Hexa WP Core 3.44.2.

### 2.9.8

- `[author_categories last=" and "]` and people cards' `topics_last` join the final two sections differently ("A, B and C").

### 2.9.7

- People cards: "writing since" and the compact latest-story date use the stored date, so a site's date filters no longer change their format.

### 2.9.6

- People cards: a person without their own photo shows initials in the latest-story byline too, never a placeholder image.

### 2.9.5

- People cards: `show="since"` (writing since), `latest="compact"`, `topics_label`/`topics_separator`, and `order` to set the parts' page order.

### 2.9.4

- People cards show the verified mark at the card size (MuckRack card settings) instead of the author-page size.
- The verified mark's MuckRack link has properly quoted attributes, and the verification sentence's "learn more" link no longer breaks.

### 2.9.3

- People lists: initials replace a placeholder photo, the latest story has a byline, and `show="all"` adds an "All N stories by <first name>" link.

### 2.9.2

- People lists (Contributors, Staff, Executive Leadership): optional verified mark, "Writes about" sections, story count, latest story and initials fallback (`show="..."`, off by default), and `orderby="posts"`.

### 2.9.1

- Archive search: the box now sits on the story grid, under the lead story, instead of above the lead.

### 2.9.0

- The 11 Google News policies are rewritten as full, concrete policies built on the Trust Project's trust indicators and the SPJ Code of Ethics: how corrections, updates and clarifications are marked and how fast, when unnamed sources are allowed and how they are checked, what editors verify, how bylines work, how reader feedback is answered, and the diversity commitments. Diversity Staffing Report states the review is in progress instead of claiming a report exists.

### 2.8.1

- Apply template on a page built with Elementor now shows the template: the page leaves Elementor's builder mode, and its Elementor design stays saved. Bundles Hexa WP Core 3.44.1.

### 2.8.0

- Executive Leadership Team, Staff, Contributors and Masthead are custom-design pages: created as empty drafts and never overwritten by their template; the site redesign builds them.
- Latest Press Releases is off by default, like Advertise: an existing page stays linked, but the page setup no longer creates one.
- Bundles Hexa WP Core 3.44.0.

### 2.7.6

- Pages tab: the shortcode list uses Hexa WP Core 3.43.0's table with Copy buttons.

### 2.7.5

- Bundles Hexa WP Core 3.42.1.

### 2.7.4

- Bundles Hexa WP Core 3.42.0.

### 2.7.3

- Bundles Hexa WP Core 3.41.1.

### 2.7.2

- Page templates use HWS Base Tools' site-neutral `[hws_site]` shortcode instead of `[hws_company]`.

### 2.7.1

- Bundles Hexa WP Core 3.41.0.

### 2.7.0

- Every publication page default text rewritten: no repeated title headings, plain titles, concrete editorial policies (corrections, verification, ethics, sources, diversity), a factual Powered by Scale My Publication page, and an Ownership and Funding page linked from Policies.
- Templates use HWS Base Tools' building blocks ([hws_company], [hws_if], [hws_page_link]), so every page reads company details from Website Identity in one place.
- Pages tab: a collapsible list of every company detail shortcode with its current value, and each page shows the details it uses, with a missing-details tag.
- Bundles Hexa WP Core 3.40.1.

### 2.6.2

- Bundles Hexa WP Core 3.39.0 (the sectioned page list with a Details button per page).

### 2.6.1

- Pages tab: each page shows the page it sits under, or will go under; company pages are shown read-only as HWS Base Tools pages.

### 2.6.0

- The company pages any website has (About, Mission Statement, Founders, Founding Date, Parent Network, Team, Privacy Policy, Terms of Use, Accessibility, FAQs, RSS Feed) moved to HWS Base Tools 13.24.0; SMP shows them read-only and hands over the pages it had linked. SMP's people pages sit under About, Powered by Scale My Publication under Parent Network, and a new Policies page lists every policy. Advertise is optional. Bundles Hexa WP Core 3.36.0.

### 2.5.25

- Breadcrumbs left SMP: the bar under the header is now its own section in HWS Base Tools, off by default. SMP no longer prints, styles or places a breadcrumb bar, and its Article design tab points to the new section. The saved breadcrumb settings stay so Base Tools can carry over a site that had them on. Bundles Hexa WP Core 3.34.2.

### 2.5.24

- The publication manifest reports `article_audio` as supported only while SMP WP Text To Speech's "Listen to this article" switch is on (`smp_wp_text_to_speech_article_audio_enabled`), so Publish stops generating audio for sites that turned it off.

### 2.5.23

- "What to know" and FAQs are separate features, each with its own switch and its own field group ("What to Know" and "FAQs" replace the shared "Article Fields" group). Off removes the editor fields and every output: automatic placement, shortcodes, contents link, FAQPage schema and go-live checklist item.
- A summary made only of blank lines (non-breaking spaces) no longer draws an empty "What to know" box or contents link.

### 2.5.21

- Asset loading: front-end CSS and JS only where each feature is used (see Asset loading); admin assets were already limited to SMP's screens.
- The admin page manager is built only for its own AJAX requests, after `init`.
- Smart templates: `[smp_if]` and `before`/`after` on `[smp_company]`; templates drop sentences whose fact, page or feature is missing; no double periods.
- Headquarters and Logo moved to HWS Base Tools (read-only rows like Contact); FAQs no longer marked for Base Tools; `hws_base_tools_pages_in_use` filter.
- Update Center tab; Content Generation and Post HTML Cleanup tabs in their own files.
- Remaining plain code boxes replaced with Hexa WP Core's colour-coded display (Hexa WP Core 3.34.1).
- Removed SMP's founding-date fields, `[smpi_founding_date]`, `docs/founding-date.md`; Her Forward's old `smpi_founders`, founding values and the Become a Contributor and Founder templates deleted.
- Tests: the 20 files that stopped on "strict_types declaration must be the very first statement" parse again; the shared test fixture loads plugin classes by namespace.

### 2.5.20

- Latest Press Releases and Submit Your Press Release stay in SMP and link to the parent syndicator Hexa PR Wire (https://hexaprwire.com); the Submit page explains distribution through Hexa PR Wire and has a form slot, `[smp_press_release_form]`, which prints the form shortcode saved on that page's row in Publication Pages (option `smpi_press_release_form`; the publication-standard-pages skill sets the form up).

### 2.5.19

- Founders now live only in HWS Base Tools: SMP's Founders tab is a read-only view with an edit link; search-engine data, `[smp_company field="founders"]` and `[smp_publication_founders]` read `hws_publication_founders()`. Removed SMP's founders settings and `[smpi_founders]` copy (Base Tools keeps the alias), the Founder Profiles section, the "Founder Authors" field and the "Show founder marketing" switch.
- Founder and Founders page templates show only `[hws_publication_founders]`; the Founding Date template only the date and place.
- Every code and shortcode display uses Hexa WP Core's CodeDisplay and ShortcodeBreakdown.
- Publication Pages: Founder merged into Founders; Founding Date only for company sites (company founding date and city); Parent Network and Ownership and Funding optional and out of the default set; Powered by Scale My Publication uses the standard text; Team reads HWS Base Tools' Team read-only; Contact shown read-only from HWS Base Tools; Become a Contributor removed (moves to SMP Contributor Network).

### 2.5.18

- Publication Pages tab replaces Pages: 38 page types in About, People, Policies and Reader, each with its linked page (found automatically), status, editable template and a create/apply button that keeps the old content as a revision. Real generic templates per page type; company facts from HWS Base Tools via `[smp_company]`; `[smp_latest_press_releases]`. Advertise types merged; generic types flagged for HWS Base Tools.
- Admin cleanup: 30 tabs to 22 in six groups; Publication Settings shows only newsroom fields plus read-only company facts from HWS Base Tools; removed Publication Fields, Founding Date, Brand Settings, Database Optimization and WordPress Admin tabs; Founder Profiles merged into Founders, Integrations into Plugins. The admin controller is split into one file per tab; page types and templates moved to `src/Pages/`.

### 2.5.17

- Pages tab shows Terms of Use, Privacy Policy, Brand Assets, Headquarters and FAQs (previously hidden as HWS Base Tools pages). HWS keeps only Contact.

### 2.5.16

- Founding Date page starter template (Pages tab): `[smpi_founding_date show=date,place]` and `[smpi_founders]` only, with their own CSS.
- The Founding Date tab no longer edits the founding fields; it shows them and links to Publication Settings, where they live. Pages stay on the Pages tab.

### 2.5.15

- New Founding Date tab and `[smpi_founding_date]`: on/off, section title (default Founded), founding date, place, place link and story saved to the publication's own founding fields (also used by the structured data), live example, HTML structure and shortcode.

### 2.5.14

- Bundles Hexa WP Core 3.30.4: the archive search also narrows Elementor loops that show the page's own results without a marker class, and its filter controls are styled in every look.

### 2.5.13

- New Founders tab and `[smpi_founders]`: on/off, section title (default Founders), people from user accounts with optional titles, styles, HTML structure and shortcode.
- People lists save one change at a time, so a quick add-then-type never loses the title.

### 2.5.12

- Adds the Staff tab and `[smpi_staff]`, using the shared people renderer/editor and HWS Base Tools' existing Staff writer switch. Type-to-search add/remove updates profiles, with optional Staff Title, ordering, Cards/List/No styling examples, Staff page tools, CSS structure and all shortcode attributes.
- Staff defaults to the site's author-listing photo/article visibility settings. Existing staff, contributor and leadership shortcodes and settings remain unchanged.

### 2.5.11

- Contributors follows the site's author-listing settings by default (hide authors without a photo or without articles), overridable with `with_photo` and `with_articles`.
- People lists: names, titles, bios and the View profile link keep their own colour and spacing inside page templates that colour every link or space every heading.

### 2.5.10

- Author pages keep the author in the breadcrumb ("Home - Archives for <name>"). Including co-written stories cleared WordPress's author value, which Rank Math reads for the trail; it is now restored once the page's stories are chosen.

### 2.5.9

- Archive search runs on Hexa WP Core 3.30.2's one search (bundled): same box, now with the shared loader (the stories dim under a spinner while results load). Needs HWS Base Tools 13.17.1.

### 2.5.8

- New Contributors tab and `[smpi_contributors]`: everyone with the chosen roles (Contributor by default), plus people added from other roles, minus people removed, in a chosen order; Contributors page, styles, CSS structure and attributes `roles` and `with_articles`.
- Executive Leadership and Contributors now share one list renderer and one admin editor (same markup, classes and behavior as before).

### 2.5.7

- Executive Leadership tab: add people by typing a name or email (Hexa WP Core user search) instead of a long dropdown; each person is a row with photo, role and email, a labelled Executive Leadership Title, drag-to-reorder and icon buttons for move, profile and remove.

### 2.5.6

- New Executive Leadership tab: on/off switch that links or creates the leadership page from the default template, people picker with titles and order, Cards/List/No styling with a live example, CSS structure and the `[smpi_executive_leadership]` shortcode. User profiles get "Is part of the executive leadership team" and an Executive Leadership Title, shown only while the feature is on; the tab and profiles stay in step.

### 2.5.5

- Archive search no longer prints into category, tag and author RSS feeds, which made them invalid XML.
- Bundles Hexa WP Core 3.29.8.

### 2.5.4

- Archive search uses HWS Base Tools' new `[hws_search]` name (falls back to `[hws_listing_search]` on older Base Tools).

### 2.5.3

- Archive search also finds a loop pinned to the page's own category or tag (Include: Terms).

### 2.5.2

- Bundles Core 3.29.4 (filter lists stay on screen).

### 2.5.1

- Archive search also appears on archives the theme draws itself (no Elementor template): above the first story, with the stories refreshed in place. Bundles Core 3.29.3.

### 2.5.0

- Archive search: category, tag, author and search pages show a search box with filters above the stories, refreshed in place (`src/Content/ArchiveSearch.php`, setting `archive_search_enabled`).
- Infinite scroll with a Load more fallback moved to HWS Base Tools.
- The publication manifest no longer reads JetEngine listings. Bundles Core 3.29.2.

### 2.4.28

- `[author_categories]` orders equal counts by oldest category, so the "Writes about" list never shuffles; README and design examples show its HTML and CSS.

### 2.4.27

- `[author_image]` takes `loading="eager"` for a photo on the first screen (an author page header); it stays lazy by default.

### 2.4.26

- `smpi-auto-load-more` stays at the first batch when `window.hexaFreezeAutoLoad` is set, so page-capture checks compare first-load pages.

### 2.4.25

- Infinite scroll with a visible Load more button: the CSS class `smpi-auto-load-more` on a Loop Grid or Posts widget set to "Load on click" loads the next batch as its button comes into view.

### 2.4.24

- `smpi_archive_lead` keeps the result count, so Elementor renders the lead story instead of treating it as nothing found.

### 2.4.23

- `[author_categories]` lists the sections an author writes in most, as links, for author pages and author boxes.

### 2.4.22

- Breadcrumbs now render on author archives, search results, the page-not-found page and custom taxonomy archives, where `[smp_breadcrumbs]` printed nothing before.

### 2.4.21

- Clearing Elementor's CSS cache now also clears the LiteSpeed page cache, so no cached page keeps linking CSS files Elementor just deleted (pages showed unstyled until rebuilt).

### 2.4.20

- Related tags: `[smp_related_tags]` lists the tags used most often with the current tag or category, for archive templates.

### 2.4.19

- Archive listings: the `smpi_archive_lead` and `smpi_archive_rest` Query IDs let a category, tag, author or search template show the newest story apart from the rest, with Load more batches that fill whole rows, and `[smp_archive_count]` prints the archive's story count.

### 2.4.18

- Cards added after load only get their verified marks added; the full badge clean-up pass no longer reruns then, so it cannot remove marks that other page scripts have touched.

### 2.4.17

- The verified-mark rerun reacts only when story cards are added, not to other page changes, so other scripts changing the page no longer trigger a badge cleanup pass.

### 2.4.16

- Cards added after page load (Elementor infinite scroll and Load more) now get their MuckRack verified mark: the badge script reruns when new content arrives, ignoring its own badge nodes.

### 2.4.15

- Author photos saved as a resized copy (for example `-300x300`) now resolve to their media item, so `[author_image size="thumbnail"]` serves the real 150 px thumbnail instead of falling back to the saved larger file.

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
