# Article design examples

Use these examples to see the shortcode output and which CSS changes each
part. Fixture text and IDs illustrate the structure; runtime IDs, content,
state attributes and plugin scripts come from the renderer. Do not paste this
HTML into a live Elementor HTML widget.

The common [Elementor plugin-element contract](https://github.com/mikeyperes/hws-agent-operations/blob/main/skills/elementor-build-guidelines/SKILL.md#plugin-rendered-elements)
owns widget placement and styling location. The CSS below uses Elementor's
`selector` placeholder. Sample values are editable examples; the publication's
design guide owns the final sizes and colours.

## Preview routes and skin choice

On an installed site, open **SMP Publication > Features > Article design**:
`/wp-admin/admin.php?page=smp-publication-integration&tab=features#smpi-feature-group-article-design`.

- **Article summary block:** preview `sum00` through `sum05`.
- **Article table of contents:** preview `toc00` through `toc04`.
- **Article FAQ block:** preview `faq00` through `faq04`.

These previews use the frontend renderers and [ArticleStyles](../src/Content/ArticleStyles.php).
Choose a named skin when its plugin controls own the design. The examples below
use `unstyled` so Elementor owns the appearance. For summary and FAQ,
`style="none"` removes the wrapper/title and is not a substitute for `unstyled`.
No data means no feature output. Explicit template placement uses manual
summary/FAQ placement and automatic Contents insertion off.

## What to know

Shortcode: `[smp_post_summary style="unstyled"]`.

Source: [render_post_summary](../src/Content/Shortcodes.php),
[wrap_post_summary](../src/Content/ArticleStyles.php) and
[rich-text class decoration](../src/Content/TemplateMarkup.php).

Representative HTML from the summary field:

```html
<aside class="smpi-template smpi-template--article-summary smpi-post-summary smpi-unstyled"
       data-smpi-skin="unstyled">
  <h2 class="smpi-template-title smpi-post-summary-title">What to know</h2>
  <div class="smpi-template-content smpi-post-summary-content">
    <ul class="smpi-template-list smpi-post-summary-list">
      <li class="smpi-template-item smpi-post-summary-item">A supplied summary item.</li>
      <li class="smpi-template-item smpi-post-summary-item">Another supplied item with <strong>emphasis</strong>.</li>
    </ul>
  </div>
</aside>
```

Example CSS:

```css
selector .smpi-post-summary {
  padding: 18px 20px;
  border: 1px solid #ddd;
  color: #333;
}
selector .smpi-post-summary-title {
  margin: 0 0 12px;
  font-family: inherit;
  font-size: 11px;
  font-weight: 700;
  line-height: 1.3;
}
selector .smpi-post-summary-content {
  font-size: 12.5px;
  line-height: 1.55;
}
selector .smpi-post-summary-content :is(p, li, a, strong, b, em, span) {
  font-size: inherit;
  line-height: inherit;
}
selector .smpi-post-summary-list { margin: 0; padding-left: 20px; }
selector .smpi-post-summary-item + .smpi-post-summary-item { margin-top: 8px; }
selector .smpi-post-summary-item::marker { color: #b42318; }
```

Edit the root for frame/background; `-title` for the label; `-content` for
reading size; `-item` for item spacing; `::marker` for marker colour. Supplied
summary paragraphs also belong to `-content`; do not force all summaries into
a fixed number of items.

## In this article

Shortcode: `[smp_table_of_contents style="unstyled" title="In this article" levels="h2" open="desktop" markers="leading-zero"]`.

Source: [TableOfContents](../src/Content/TableOfContents.php). The real shortcode
also emits responsive-open and heading-anchor scripts; keep them. The following
shows a desktop-open list after the plugin marks the first section current:

```html
<details class="smpi-template smpi-template--toc smpi-table-of-contents smpi-unstyled smpi-toc-collapsible"
         data-smpi-skin="unstyled" data-smpi-open="desktop"
         data-smpi-markers="leading-zero" open>
  <summary class="smpi-template-title smpi-toc-label">
    <span class="smpi-template-text smpi-toc-label-text">In this article</span>
    <span class="smpi-toc-caret" aria-hidden="true"></span>
  </summary>
  <ol class="smpi-template-content smpi-template-list smpi-toc-list smpi-toc-has-active">
    <li class="smpi-template-item smpi-toc-item smpi-toc-level-2 smpi-toc-item--active">
      <span class="smpi-toc-ordinal" aria-hidden="true">01</span>
      <a class="smpi-template-link smpi-toc-link smpi-toc-link--active"
         href="#example-section" aria-current="location">Example section</a>
    </li>
  </ol>
</details>
```

Example CSS:

```css
selector .smpi-table-of-contents { padding: 16px; border: 1px solid #ddd; }
selector .smpi-toc-label { font-size: 11px; font-weight: 700; margin-bottom: 12px; }
selector .smpi-toc-list { list-style: none; margin: 0; padding: 0; }
selector .smpi-toc-item { display: flex; align-items: flex-start; gap: 10px; }
selector .smpi-toc-ordinal { flex: 0 0 2ch; padding-top: 6px; font-size: 12px; line-height: 1.4; font-variant-numeric: tabular-nums; }
selector .smpi-toc-link {
  flex: 1; min-width: 0; padding: 6px 8px;
  border-left: 2px solid transparent;
  color: #616161; font-size: 12px; line-height: 1.4; text-decoration: none;
}
selector .smpi-toc-link:hover { color: #111; }
selector .smpi-toc-link:focus-visible { outline: 2px solid #b42318; outline-offset: 2px; }
selector .smpi-toc-link--active {
  color: #b42318; border-left-color: currentColor;
  text-shadow: .25px 0 currentColor;
}
@media (max-width: 1024px) {
  selector .smpi-toc-link { min-height: 44px; box-sizing: border-box; }
}
```

Edit `-label` for the disclosure heading; `-link` for text/padding; `-ordinal`
for numbers; `--active` for the reading marker. The border already occupies
space at rest, and paint-only emphasis avoids a font-weight wrapping change.
The plugin owns tracking; do not hard-code the first link as active. Keep
sticky layout on the containing Elementor container and preserve page scroll
chaining rather than adding an inner scroll trap.

## Frequently asked questions

Shortcode: `[smp_post_faqs format="accordion" style="unstyled"]`.

Source: [render_post_faqs](../src/Content/Shortcodes.php),
[wrap_post_faqs](../src/Content/ArticleStyles.php) and
[shared FAQ/schema data](../src/Content/Schema.php).

Representative HTML from one structured FAQ row:

```html
<section class="smpi-template smpi-template--article-faqs smpi-post-faqs smpi-unstyled"
         data-smpi-skin="unstyled">
  <h2 class="smpi-template-title smpi-post-faqs-title">Frequently asked questions</h2>
  <div class="smpi-template-content smpi-post-faqs-content">
    <div class="smpi-template-list smpi-post-faq-list smpi-post-faq-accordion">
      <details class="smpi-template-item smpi-post-faq-item smpi-post-faq-accordion-item">
        <summary class="smpi-template-title smpi-post-faq-question smpi-post-faq-accordion-question">
          <span class="smpi-template-text smpi-post-faq-question-text">A supplied question?</span>
        </summary>
        <div class="smpi-template-content smpi-post-faq-answer smpi-post-faq-accordion-answer">
          <p class="smpi-template-text smpi-post-faq-text">The supplied answer.</p>
        </div>
      </details>
    </div>
  </div>
</section>
```

Example CSS:

```css
selector .smpi-post-faqs-title { margin: 0 0 16px; }
selector .smpi-post-faq-item { border-top: 1px solid #ddd; }
selector .smpi-post-faq-question {
  display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;
  box-sizing: border-box; min-height: 44px; margin: 0; padding: 13px 0;
  list-style: none; cursor: pointer;
  font-size: 14px; font-weight: 600; line-height: 1.4;
}
selector .smpi-post-faq-question::-webkit-details-marker { display: none; }
selector .smpi-post-faq-question-text { font-size: inherit; line-height: inherit; }
selector .smpi-post-faq-question::after { content: "+"; flex: none; color: #b42318; }
selector .smpi-post-faq-item[open] > .smpi-post-faq-question::after { content: "−"; }
selector .smpi-post-faq-question:focus-visible { outline: 2px solid #b42318; outline-offset: 2px; }
selector .smpi-post-faq-answer { padding: 8px 0 16px; font-size: 13px; line-height: 1.6; }
selector .smpi-post-faq-answer :is(p, li, a, strong, b, em, span) { font-size: inherit; line-height: inherit; }
selector .smpi-post-faq-answer p { margin: 0 0 8px; }
```

Edit `-faqs-title` for the group heading; `-question` for row typography and
padding; `-answer` for answer typography; `[open]` for expanded decoration.
The native disclosure remains functional without replacement JavaScript.
`format="html"` instead renders expanded pairs. FAQ schema uses the same
structured source; this CSS changes neither content nor schema.

## Writes about

Shortcode: `[author_categories limit="4" separator=", "]` in an Elementor
Shortcode widget on the author archive or an author box. Put the "Writes
about" label in its own Heading widget.

Source: [render_categories](../src/Content/AuthorShortcodes.php).

The list is the categories of the author's published posts (newest 200),
counted per post, most posts first; equal counts keep the oldest category
first. The default category (Uncategorized) is skipped and tags are never
used. `limit` is 1 to 12. The author is the current archive author, or the
post's author elsewhere; `user_id`, `post_id` and `author_index` pick one
explicitly. No categories means no output.

Representative HTML:

```html
<span class="smpi-author-categories">
  <a class="smpi-author-category" href="https://example.com/category/productivity/">Productivity</a>,
  <a class="smpi-author-category" href="https://example.com/category/careers/">Careers</a>
</span>
```

Elementor custom CSS for the Shortcode widget:

```css
selector .smpi-author-categories {
  font-size: 15px;
  line-height: 1.5;
}
selector .smpi-author-category {
  color: inherit;
  text-decoration: underline;
  text-underline-offset: 3px;
}
selector .smpi-author-category:hover { text-decoration-thickness: 2px; }
```

Edit the root for size and the separator colour; `-category` for link colour
and underline. Background and spacing belong to the widget's own controls.

## Listen

Audio is owned by the companion plugin, not this renderer. See its
[player example, HTML and CSS](https://github.com/mikeyperes/smp-wp-text-to-speech/blob/main/docs/player-design-examples.md#listen-player).
