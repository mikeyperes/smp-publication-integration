# Contributors: HTML structure and styling

`[smpi_contributors]` prints the publication's contributors, managed on **Settings → SMP Publication Integration → Contributors** (`options-general.php?page=smp-publication-integration&tab=contributors`). The markup and built-in look are shared with Executive Leadership ([executive-leadership.md](executive-leadership.md)); only the class names differ, so each list can be styled on its own.

## Who is on the list

- Everyone with one of the chosen roles (Contributor by default; any roles can be ticked on the tab).
- Plus people added from other roles (shown with an "Added" badge on the tab).
- Minus role members removed from the list (their role is unchanged; "removed from the list" on the tab puts them back).
- Order: the order set on the tab, then anyone new by name.
- Stored in the option `smpi_contributors`: `roles`, `include`, `exclude`, `order`, `style`.
- Page: the `contributors` page assignment, or an existing page at `about/executive-leadership-team/meet-our-contributors`, `about/meet-our-contributors`, `meet-our-contributors`, `contributors` or `about/contributors`. The tab can create a Contributors page with the default template or apply the template to the existing page (kept as a revision).

## Markup

```html
<div class="smpi-contributors smpi-contributors--cards" style="--smpi-contributors-columns:3">
  <article class="smpi-contributor smpi-contributor--{user-slug}">
    <a class="smpi-contributor__photo" href="{author page}" tabindex="-1" aria-hidden="true">
      <img class="smpi-contributor__image" src="…" alt="{Name}" loading="lazy" decoding="async">
    </a>
    <div class="smpi-contributor__body">
      <h3 class="smpi-contributor__name"><a href="{author page}">{Name}</a></h3>
      <p class="smpi-contributor__bio">{short bio}</p>
      <a class="smpi-contributor__link" href="{author page}">View profile</a>
    </div>
  </article>
</div>
```

## Shortcode attributes

All of Executive Leadership's (`style`, `columns`, `ids`, `limit`, `hide`, `link`, `bio_words`, `photo_size`, `heading`), plus:

- `roles="contributor,author"` uses these roles for this one list instead of the tab's roles (added and removed people still apply).
- `with_photo="1|0"` leaves out people without their own profile photo, or shows everyone. Default: the Publication setting "hide authors without a featured image" (`author_listing_hide_without_featured_image`).
- `with_articles="1|0"` leaves out people with no published articles, or shows everyone. Default: the Publication setting "hide authors without articles" (`author_listing_hide_without_articles`).

- `show="badge,topics,count,latest,initials"` switches on extra parts (all off by default, on Staff and Executive Leadership too): `badge` the MuckRack verified mark after the name, `topics` a "Writes about" line with the sections the person writes in most (`topics="3"` how many, `topics_label` its label), `count` the number of published stories, `latest` the newest story (photo, section, headline, date), `initials` the person's initials in place of a missing photo (a placeholder image never counts as a photo), `all` an "All 61 stories by Julie" link to the author page. The latest story carries a byline (avatar, name, the verified mark when `badge` is on, date). New markup: `__topics`, `__topics-label`, `__count`, `__latest`, `__latest-label`, `__latest-photo`, `__latest-body`, `__latest-section`, `__latest-title`, `__latest-date`, `__latest-byline`, `__latest-avatar`, `__latest-author`, `__all`, `__initials`.
- `orderby="posts"` orders the list by published stories, most first.
- `show="since"` adds "writing since <Month Year>" (the first published story) to the count line; the count is `<b class="__count-number">N</b> stories`.
- `latest="compact"` shows the latest story as one line (label, headline, date) instead of the full block.
- `topics_label=""` drops the label; `topics_separator=" · "` sets the separator between sections, and `topics_last=" and "` the one before the last.
- `order="topics,name,count,latest"` sets the page order of the text parts (name, title, topics, count, bio, latest, link, all); anything left out follows in that default order, and the photo always comes first.

## Built-in CSS

Printed once per page as `<style id="smpi-contributors-css">`; columns come from `--smpi-contributors-columns`. Same rules as Executive Leadership with `smpi-contributors` / `smpi-contributor` class names.

Code: `src/Content/Contributors.php`, `src/Admin/ContributorsTab.php`; shared pieces `src/Content/People/PeopleCards.php` (markup, CSS, page helpers) and `src/Admin/People/PeopleEditor.php` (tab layout and behavior).

## Head layout (`layout="head"`)

```html
<article class="smpi-contributor smpi-contributor--{nicename}">
  <div class="smpi-contributor__head">
    <a class="smpi-contributor__photo" href="{author page}"><img class="smpi-contributor__image" src="…" alt="{Name}"></a>
    <div class="smpi-contributor__id">
      <h2 class="smpi-contributor__name"><a href="{author page}">{Name}</a>{verified mark}</h2>
      <p class="smpi-contributor__title">{role}</p>
      <p class="smpi-contributor__topics">…</p>
      <p class="smpi-contributor__count">…</p>
    </div>
  </div>
  <div class="smpi-contributor__latest smpi-contributor__latest--compact">
    <span class="smpi-contributor__latest-label">Latest story</span>
    <h3 class="smpi-contributor__latest-title"><a href="{story}">{Title}</a></h3>
    <span class="smpi-contributor__latest-meta"><a class="smpi-contributor__latest-section" href="{section}">{Section}</a><span class="smpi-contributor__latest-sep" aria-hidden="true"></span><time class="smpi-contributor__latest-date">{date}</time></span>
  </div>
  <p class="smpi-contributor__all"><a href="{author page}">All {n} stories by {First}</a></p>
</article>
```

## Row stacks and search

Use `layout="rows" orderby="newest_story" per_page="10" pagination="load_more" hide="bio,link"` on the list shortcode. Rows show photo, name with an existing verified mark, role, latest published story category and title, and its date. People without stories follow those with stories; equal dates preserve the saved list order. Ordering happens before `limit`.

`per_page` enables HWS Base Tools' people search and pagination. Rows default to ten per page; `pagination` accepts `load_more`, `numbered` or `infinite`. `search="auto"` shows the search form only when the complete eligible list exceeds ten people; `search="no"` hides the form while retaining paging. Filtering or paging never changes that threshold. Without Base Tools the full list remains available.

SMP supplies the exact list after its photo/article filters, plus Byline writers (published article authors), Contributors (saved list including additions/exclusions) and Staff (saved list). Filters intersect this list, never expose unrelated accounts, and match display names or public slugs only. Multiple selected groups use OR. `[hws_search profile="people" directory="people"]` renders the union; directory may also be `byline`, `contributor`, or `staff`.

HTML: `.smpi-contributors--row-layout / .smpi-staff--row-layout / .smpi-leadership--row-layout` identifies row markup (use the actual block prefix); each article has `__head` (photo and `__id` with name/role) and `__latest` (label, title and `__latest-meta` with category/date). `style="none"` emits the same markup without built-in CSS. The search owns `.hls-wrap`, `.hls-results`, `.hls-list`, `.hls-more` and `.hls-pages`; render scoped custom CSS against those wrappers. Assets appear only in shortcode output.
