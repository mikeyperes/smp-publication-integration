# FAQ ACF and AI update handoff

SMP article FAQs now use structured repeater rows instead of relying on loose WYSIWYG text for schema.

Field group: `Post - Header`

Field: `post_faq_items`

Each row:

- `question`: plain text question.
- `answer`: WYSIWYG answer, stored as sanitized HTML.
- `enabled_for_schema`: true or false toggle. If false, the row can display but will not enter FAQPage JSON LD.

AI update rules:

- Never overwrite all FAQ rows unless the caller explicitly asks for full replacement.
- Read existing rows first.
- Match existing questions case insensitively before adding duplicates.
- Keep answers factual and article specific.
- Keep row order as the intended public order.
- Set `enabled_for_schema` to true only when the answer is complete and safe for public structured data.
- Use legacy `post_faqs` only as a fallback display field. Do not use it for reliable schema generation.

Validation sequence:

1. Save or update `post_faq_items` rows.
2. Load `/wp-json/smpi/v1/schema?post_id=ID`.
3. Confirm `FAQPage` appears in `types`.
4. Confirm `FAQPage.mainEntity` count matches enabled FAQ rows.
5. Confirm disabled rows are excluded.
6. Run the live article through validator.schema.org.
