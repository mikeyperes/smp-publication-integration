# Post opening paragraph

Choose SMP Publication > Post Opening Paragraph in Elementor's text dynamic-tag
picker. Internal name: `post_opening_paragraph`. It reads the current WordPress
post in loops and single templates, independent of site type or article policy.
Word limit defaults to 0 (unlimited); positive integers use WordPress word
trimming with an ellipsis.

Shortcode: `[post_opening_paragraph post_id="0" word_limit="0"]`.
Both attributes are optional: 0 means current post and unlimited words.

Reads post_content, never post_excerpt. Missing or password-protected posts
and empty content return empty. Supports classic blank-line paragraphs and
HTML/Gutenberg paragraphs. Skips leading headings, figures/captions, lists,
tables, code blocks and empty/media-only paragraphs. Strips inline markup and
shortcodes, including unregistered syntax. No shortcode callback, dynamic block
or content filter runs. No stored post, media, option, page or template changes.

Output is escaped plain text with no wrapper, CSS, JavaScript or assets.
Example: `<p>Opening <strong>story</strong> text.</p>` emits
`Opening story text.`. Style the containing Elementor widget or shortcode host.
Existing pages change only when an editor explicitly binds or inserts it.
