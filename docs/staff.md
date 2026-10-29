# Staff: HTML structure and styling

`[smpi_staff]` prints the publication's staff. Manage it on **Settings → SMP Publication Integration → Staff** (`options-general.php?page=smp-publication-integration&tab=staff`). It uses the same renderer and admin editor as Contributors and Executive Leadership, with its own CSS classes.

## People and profiles

- Everyone whose existing HWS Base Tools **Staff writer** switch is on (`staff_writer` user meta). No WordPress role restrictions and no second membership list.
- Adding someone through type-to-search turns that switch on; removing them turns it off. Profile changes are read directly on every tab/list load.
- Optional **Staff Title** on the tab (`smpi_staff_title` user meta), shown under the name on this list only. Removal keeps the title for a later return.
- Drag to order, or use Move up/Move down. Option `smpi_staff` stores `order` and `style`. Newly marked people follow the saved order alphabetically.
- Photos use SMP's author photo resolver. Bios use Biographical Info. By default, people without their own photo or published articles follow the site's author-listing visibility settings, just like Contributors. The admin people editor always shows every staff member.
- `[staff_grid]` remains unchanged.

## Staff page

The tab finds the `staff` page assignment or an existing page at `about/executive-leadership-team/meet-the-staff`, `about/meet-the-staff`, `meet-the-staff`, `staff`, or `about/staff`. It links the page, reports whether it has the list, and can create **Meet The Staff** or apply the default template to an existing page. Applying the template saves the previous content as a WordPress revision. For an Elementor design, add the shortcode to a native Shortcode widget instead to retain the layout.

Default template: a short introduction using the publication name, then `[smpi_staff columns="4"]`.

## Markup

```html
<div class="smpi-staff smpi-staff--cards" style="--smpi-staff-columns:4">
  <article class="smpi-staff-member smpi-staff-member--{user-slug}">
    <a class="smpi-staff-member__photo" href="{author page}" tabindex="-1" aria-hidden="true">
      <img class="smpi-staff-member__image" src="…" alt="{Name}" loading="lazy" decoding="async">
    </a>
    <div class="smpi-staff-member__body">
      <h3 class="smpi-staff-member__name"><a href="{author page}">{Name}</a></h3>
      <p class="smpi-staff-member__title">{Staff Title}</p>
      <p class="smpi-staff-member__bio">{short bio}</p>
      <a class="smpi-staff-member__link" href="{author page}">View profile</a>
    </div>
  </article>
</div>
```

`--cards`, `--list`, or `--none` indicates the selected style. `smpi-staff-member--{user-slug}` targets one person. Empty titles and bios are omitted. `hide` removes the selected parts. `link="none"` makes the photo a span and the name plain text.

## Full shortcode attribute list

- `style="cards|list|none"`: default is the tab's style, initially Cards.
- `columns="3"`: 1 to 6, Cards only. Use `4` for four across. At most two below 900px and one below 560px.
- `ids="12,7"`: explicit users in this order instead of the membership list; photo/article filters still apply.
- `limit="4"`: maximum people after filtering; default `0` shows all.
- `hide="photo,title,bio,link"`: comma-separated parts to omit, default empty.
- `link="profile|none"`: default `profile`, linking photos, names and View profile to the author page.
- `bio_words="30"`: bio length, `0` omits it.
- `photo_size="medium"`: registered WordPress image size, for example thumbnail, medium, medium_large or large.
- `heading="h3"`: h2, h3, h4 or p for names.
- `with_photo="1|0"`: require an explicit profile photo, or show everyone. Default: Publication's hide authors without a featured image setting.
- `with_articles="1|0"`: require published articles, or show everyone. Default: Publication's hide authors without articles setting.

## Built-in CSS

Printed once per page as `<style id="smpi-staff-css">`. The tab shows the exact CSS and a live example for Cards, List and No styling. Cards use `--smpi-staff-columns`; List shows one person per row with a circular photo. No styling prints the same markup without CSS. Fonts and colours inherit the site; name links inherit heading colour. Classes are isolated from Contributors and Executive Leadership. For Elementor overrides, use the Shortcode widget's Custom CSS, for example `selector .smpi-staff-member__title { opacity: .8; }`.

Code: `src/Content/Staff.php`, `src/Admin/StaffTab.php`, shared `src/Content/People/PeopleCards.php` and `src/Admin/People/PeopleEditor.php`.

## Row stacks and search

Use `layout="rows" orderby="newest_story" per_page="10" pagination="load_more" hide="bio,link"` on the list shortcode. Rows show photo, name with an existing verified mark, role, latest published story category and title, and its date. People without stories follow those with stories; equal dates preserve the saved list order. Ordering happens before `limit`.

`per_page` enables HWS Base Tools' people search and pagination. Rows default to ten per page; `pagination` accepts `load_more`, `numbered` or `infinite`. `search="auto"` shows the search form only when the complete eligible list exceeds ten people; `search="no"` hides the form while retaining paging. Filtering or paging never changes that threshold. Without Base Tools the full list remains available.

SMP supplies the exact list after its photo/article filters, plus Byline writers (published article authors), Contributors (saved list including additions/exclusions) and Staff (saved list). Filters intersect this list, never expose unrelated accounts, and match display names or public slugs only. Multiple selected groups use OR. `[hws_search profile="people" directory="people"]` renders the union; directory may also be `byline`, `contributor`, or `staff`.

HTML: `.smpi-contributors--row-layout / .smpi-staff--row-layout / .smpi-leadership--row-layout` identifies row markup (use the actual block prefix); each article has `__head` (photo and `__id` with name/role) and `__latest` (label, title and `__latest-meta` with category/date). `style="none"` emits the same markup without built-in CSS. The search owns `.hls-wrap`, `.hls-results`, `.hls-list`, `.hls-more` and `.hls-pages`; render scoped custom CSS against those wrappers. Assets appear only in shortcode output.
