# Podcast AJAX inline-owner audit

Audit target: `https://podcast.michaelperes.com/` on server 236. This is a
read-only/no-deploy handoff captured 2026-08-01.

## Live surfaces checked

All returned HTTP 200:

- Episode: `/a-conversation-with-jesse-shrader/`
- Category: `/category/business/`
- Tag: `/tag/michael-peres/`
- Page: `/contact/`
- Profile: `/profile/craig-guarraci/`

## SMPI-owned executable blocks

- `smpi-breadcrumbs-inject` was emitted by
  `src/Content/Breadcrumbs.php::print_auto_inject()` at `wp_footer` priority
  18. It appeared on episode, category, tag, page, and profile targets.
- `smpi-article-markup-normalizer` was emitted by
  `src/Content/ArticleStyles.php::print_markup_fallback_script()` at
  `wp_footer` priority 48. It appeared on the episode target.

The 1.0.17 artifact removes both executable blocks. Breadcrumb markup remains
server-rendered in the exact inert ScaleMyPodcast companion contract,
`<template data-smp-ajax-companion="smpi-breadcrumbs">…</template>`. Article
content remains decorated by the existing `the_content` filter. Target article
flags are emitted as SSR body classes, and `assets/frontend/public-dom.js`
initializes on direct load and `smp:content-ready` without evaluating fetched
code.

## Anonymous Elementor HTML widgets

The repeated `hideEmptyItemsAndCheckContainer` jQuery is stored in structured
Elementor `_elementor_data`, not in Elementor Custom Code:

| Document | Template type / condition | Element | Exact HTML SHA-256 |
| --- | --- | --- | --- |
| 18252 — `Single.profile` | `single-post`; `include/singular/profile` | `8b02175` | `7d9eb572b2876c2c84752b87113fd3b7724b4ff3bac9153ea9677c708cf8c952` |
| 18252 — `Single.profile` | `single-post`; `include/singular/profile` | `037fccb` | `3f4eb6b888560de131434766e1c9259d144ff5e10b3d6c8463868913fc85f219` |
| 19298 — `Author.archive` | `archive`; `include/archive/author` | `f4eadee` | `7d9eb572b2876c2c84752b87113fd3b7724b4ff3bac9153ea9677c708cf8c952` |
| 19550 — `Episode Guest - Details` | `loop-item` | `25f2c0e` | `7d9eb572b2876c2c84752b87113fd3b7724b4ff3bac9153ea9677c708cf8c952` |
| 20001 — `Post - Episode Hosts` | `loop-item` | `2348d43` | `7d9eb572b2876c2c84752b87113fd3b7724b4ff3bac9153ea9677c708cf8c952` |

Revisions 21747, 21748, and 22141 and `_elementor_element_cache` rows also
contain copies. They are not owners and must not be edited directly. Regenerate
Elementor cache after changing an owning document.

The episode emits two copies through Loop Items 19550 and 20001. The profile
emits copies through template 18252.

### Prepared replacement/retirement contract

The replacement belongs to the podcast template/helper package, not SMPI. It
should be one external script that:

1. Initializes on `DOMContentLoaded` and `smp:content-ready`.
2. Scopes navigation work to `event.detail.root`.
3. Uses `hidden` for social items whose anchor is missing or has an empty
   `href`, and hides `.socials`/`#profile_social_icons` only when every owned
   item is empty.
4. Uses per-node markers or naturally idempotent state; it must not assign
   `$ = jQuery`, publish global helper functions, or depend on fetched inline
   execution.

Retirement must decode `_elementor_data`, locate the exact document and element
IDs above, and verify the exact HTML SHA-256 before removing each HTML widget.
Save through Elementor-compatible document APIs, then regenerate Elementor CSS
and element cache. Refuse on any ID/hash drift. Do not edit serialized JSON,
revision rows, or `_elementor_element_cache` directly.

## Elementor Custom Code record

Post 15393, `Scripts Head`, is published globally:

- Location: `elementor_head`
- Priority: `1`
- Condition: `include/general`
- Exact `_elementor_code` SHA-256:
  `c9b81c7f55fcd3abbf4eb427faee554b958e106e7c405cfaad35c823a5bd177b`

It combines a Twitter widgets loader with anonymous jQuery that sets
`target="_blank"` on `.elementor-element-d2cccb1 a`. Retire it only after:

- the target behavior is configured in the owning Elementor link control (with
  the appropriate `rel="noopener noreferrer"`) or an owned external header
  asset; and
- Twitter widgets are enqueued through a registered WordPress script handle on
  only the surfaces that contain embeds.

Because two responsibilities share this record, deleting it before both
replacements are active would be unsafe. A retirement tool must verify post ID,
status, location, condition, priority, and the exact code hash above.

## ScaleMyPodcast companion contract

SMPI emits exactly one breadcrumb `<template>` from `wp_footer`, outside the
selected content root, when breadcrumbs should render and no template when
they should not. ScaleMyPodcast owns the narrowly allowlisted companion sync:

- It accepts only the `smpi-breadcrumbs` key and a unique outside-root
  `TEMPLATE`.
- Template descendants are limited to `A`, `DIV`, `EM`, `LI`, `NAV`, `OL`,
  `P`, `SMALL`, `SPAN`, `STRONG`, and `UL`.
- Nested attributes are limited to `class`, `role`, `title`, `aria-*`, and
  `data-smpi-*`; only same-origin HTTP(S) `href` values on `A` are accepted.
- Unknown, duplicate, inside-root, or unsafe companions cause a hard-browser
  fallback.
- The old template and rendered breadcrumb are atomically replaced or removed
  before `smp:content-ready`.

`public-dom.js` then clones `template.content.firstElementChild`, marks it
`data-smp-ajax-companion-rendered="smpi-breadcrumbs"`, removes legacy/injected
rendered copies, and places the new band after the first configured visible
header. No fetched code is executed.
