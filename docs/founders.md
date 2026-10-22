# Founders: HTML structure and styling

`[smpi_founders]` prints the publication's founders section, set on **Settings → SMP Publication Integration → Founders** (`options-general.php?page=smp-publication-integration&tab=founders_list`). It shares its markup and built-in look with Executive Leadership, Contributors and Staff; only the class names differ.

## Settings

- On/off switch (off: the shortcode prints nothing; people and titles are kept).
- Section title, "Founders" by default (for example "Executives"); empty for none.
- People: user accounts added by type-to-search, in a dragged order, each with an optional Founder Title. Photo, name and bio come from the user profile.
- Option `smpi_founders`: `enabled`, `heading`, `ids`, `titles`, `style`.
- The older Founder Profiles tab (Verified Profile records for structured data) is separate and unchanged.

## Markup (one item)

```html
<section class="smpi-founders-section">
  <h2 class="smpi-founders__heading">Founders</h2>
  <div class="smpi-founders smpi-founders--cards" style="--smpi-founders-columns:3">
    <article class="smpi-founder smpi-founder--{user-slug}">
      <a class="smpi-founder__photo" href="{author page}"><img class="smpi-founder__image" src="…" alt="{Name}"></a>
      <div class="smpi-founder__body">
        <h3 class="smpi-founder__name"><a href="{author page}">{Name}</a></h3>
        <p class="smpi-founder__title">{Founder Title}</p>
        <p class="smpi-founder__bio">{short bio}</p>
        <a class="smpi-founder__link" href="{author page}">View profile</a>
      </div>
    </article>
  </div>
</section>
```

## Shortcode attributes

The shared people-list attributes (`style`, `columns`, `ids`, `limit`, `hide`, `link`, `bio_words`, `photo_size`, `heading` = tag for each name), plus `section_title="Executives"` (`none` hides it) and `section_tag="h2"`.

Code: `src/Content/Founders.php`, `src/Admin/FoundersTab.php`; shared `src/Content/People/PeopleCards.php`, `src/Admin/People/PeopleEditor.php`.
