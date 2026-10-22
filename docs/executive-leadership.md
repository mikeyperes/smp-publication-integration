# Executive Leadership: HTML structure and styling

`[smpi_executive_leadership]` prints the publication's Executive Leadership Team, set on **Settings → SMP Publication Integration → Executive Leadership** (`options-general.php?page=smp-publication-integration&tab=executive_leadership`) or on each user's profile. This page is the reference for building and styling it in Elementor or an HTML draft.

## Data

- User meta `smpi_executive_leadership` (`1` = on the team) and `smpi_executive_leadership_title` (title shown only on the leadership list). The profile fields are the field group `group_smpi_executive_leadership`, registered only while the feature is on; the title shows once the toggle is on.
- Option `smpi_executive_leadership`: `enabled`, `style` (`cards|list|none`) and `order` (user IDs). People turned on from a profile join the end of the order.
- Page: the `executive_team` page assignment. Turning the feature on links the assigned page, or an existing page at `about/executive-leadership-team`, `executive-leadership-team`, `leadership-team`, `executive-team` or `leadership`, or creates **Executive Leadership Team** with the default template (a short line and the shortcode).
- Photo: the author photo SMP uses everywhere (explicit author image, then avatar). Bio: the profile's Biographical Info.

## Markup

```html
<div class="smpi-leadership smpi-leadership--cards" style="--smpi-leadership-columns:3">
  <article class="smpi-leader smpi-leader--{user-slug}">
    <a class="smpi-leader__photo" href="{author page}" tabindex="-1" aria-hidden="true">
      <img class="smpi-leader__image" src="…" alt="{Name}" loading="lazy" decoding="async">
    </a>
    <div class="smpi-leader__body">
      <h3 class="smpi-leader__name"><a href="{author page}">{Name}</a></h3>
      <p class="smpi-leader__title">{Executive Leadership Title}</p>
      <p class="smpi-leader__bio">{short bio}</p>
      <a class="smpi-leader__link" href="{author page}">View profile</a>
    </div>
  </article>
</div>
```

- `smpi-leadership--cards|list|none` follows the style; `none` prints the same markup with no CSS.
- `smpi-leader--{user-slug}` targets one person.
- `hide="photo,title,bio,link"` removes those elements; an empty title or bio is left out.
- `link="none"` prints the photo in a `<span class="smpi-leader__photo">` and the name without a link.
- `heading` changes the name's tag (`h2|h3|h4|p`).

## Shortcode attributes

- `style="cards|list|none"` (default: the tab's style)
- `columns="3"` (1 to 6, Cards only; at most 2 under 900px, 1 under 560px)
- `ids="12,7"` only these users, in this order
- `limit="4"`
- `hide="photo,title,bio,link"`
- `link="profile|none"`
- `bio_words="30"` (0 hides the bio)
- `photo_size="medium"` (thumbnail, medium, medium_large, large)
- `heading="h3"`

## Built-in CSS

Printed once per page as `<style id="smpi-leadership-css">` (the tab shows the current copy under **Built-in CSS**). It inherits the site's fonts and colours; columns come from `--smpi-leadership-columns`. Every rule is one class, so site CSS or an Elementor widget's Custom CSS (`selector .smpi-leader__title{…}`) overrides it.

Code: `src/Content/ExecutiveLeadership.php` (data, fields, shortcode, page) and `src/Admin/ExecutiveLeadershipTab.php` (tab).
