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

## Built-in CSS

Printed once per page as `<style id="smpi-contributors-css">`; columns come from `--smpi-contributors-columns`. Same rules as Executive Leadership with `smpi-contributors` / `smpi-contributor` class names.

Code: `src/Content/Contributors.php`, `src/Admin/ContributorsTab.php`; shared pieces `src/Content/People/PeopleCards.php` (markup, CSS, page helpers) and `src/Admin/People/PeopleEditor.php` (tab layout and behavior).
