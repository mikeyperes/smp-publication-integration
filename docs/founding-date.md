# Founding Date: HTML structure and styling

`[smpi_founding_date]` prints when and where the publication was founded, with its founding story, set on **Settings → SMP Publication Integration → Founding Date** (`options-general.php?page=smp-publication-integration&tab=founding_date`).

## Settings

- On/off switch (off: the shortcode prints nothing; the details are kept).
- Section title, "Founded" by default; empty for none. Option `smpi_founding_date_section`: `enabled`, `heading`.
- Date, place, place link and story are the publication's own fields, edited on Publication Settings (the tab only shows them): `smpi_founding_date` (a full date, `Y-m-d`), `smpi_founding_location`, `smpi_founding_location_url`, `smpi_founding_date_extended`. The structured data's `foundingDate` and `foundingLocation` read the same fields.

## Markup

```html
<section class="smpi-founding smpi-founding--default">
  <h2 class="smpi-founding__heading">Founded</h2>
  <dl class="smpi-founding__facts">
    <div class="smpi-founding__row smpi-founding__row--date">
      <dt class="smpi-founding__label">Founded</dt>
      <dd class="smpi-founding__value"><time datetime="2019-04-12">April 12, 2019</time></dd>
    </div>
    <div class="smpi-founding__row smpi-founding__row--place">
      <dt class="smpi-founding__label">Founded in</dt>
      <dd class="smpi-founding__value"><a href="{place link}">New York, New York</a></dd>
    </div>
  </dl>
  <div class="smpi-founding__story"><p>{founding story}</p></div>
</section>
```

`style="none"` drops the `smpi-founding--default` class and the built-in CSS, so the page's own CSS styles the same markup.

## Shortcode attributes

- `show="date,place,story"`: which parts, in any combination.
- `date_format="F j, Y"` (default): how the date is written; `F Y` for month and year, `Y` for the year only.
- `section_title="Our story"` (`none` hides it), `section_tag="h2"` (h2, h3, h4 or p).
- `style="none"`: no built-in CSS.

Code: `src/Content/FoundingDate.php`, `src/Admin/FoundingDateTab.php`.
