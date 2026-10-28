# Author image markup

`[author_image]` accepts `size="thumbnail"`, `output="html"`,
`class="smpi-author-image"`, `loading="lazy"`, `sizes=""`, and the common
`user_id`, `post_id`, `author_index` selectors (default `0`).

## Responsive build

An Elementor Shortcode widget or another shortcode-aware template supplies its
own measured CSS breakpoints and image box widths, for example:

```text
[author_image size="medium_large" loading="eager" sizes="(max-width: 767px) 124px, (max-width: 1024px) 165px, (max-width: 1366px) 229px, 282px"]
```

These breakpoints are illustrative: the build must match its actual layout.
The image keeps its existing class, source, alt text, loading and decoding.
A nonempty `sizes` adds that escaped attribute. When the resolved photo matches
a WordPress media attachment, it also adds the selected file's intrinsic width
and height and WordPress's available `srcset`. The browser chooses a candidate
using the display width and device pixel density. Dimensions reserve the image's
aspect ratio; the build still controls its displayed size with CSS.

Representative additions for a square 768-pixel file:

```html
sizes="(max-width: 767px) 124px, (max-width: 1024px) 165px, (max-width: 1366px) 229px, 282px" width="768" height="768"
```

No remote file is fetched to guess dimensions. Photos without matching local
attachment metadata receive `sizes` only. Omitting `sizes` (or supplying an
empty value) preserves the previous HTML exactly. `output="url"` always returns
only the existing URL, even when `sizes` is supplied.

Renderer: `src/Content/AuthorShortcodes.php::render_image()`.
