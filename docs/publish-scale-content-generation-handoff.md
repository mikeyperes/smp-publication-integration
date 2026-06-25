# Publish Scale Content Generation Handoff

Target plugin: `smp-publication-integration`

Live example: `/home/mashviral/public_html/wp-content/plugins/smp-publication-integration`

Feature tab: `Content Generation`

## Goal

SMP publication sites need one-click generators for native excerpts, `post_summary`, and structured FAQ rows. WordPress should not own the writing rules. WordPress sends post context to publish.scalemypublication.com and stores the response.

## WordPress endpoint behavior

The SMP plugin calls:

`POST /api/smp-content-generation/v1/generate`

Headers:

- `Content-Type: application/json`
- `Accept: application/json`
- `X-SMP-Content-Key: <key>`
- `X-SMP-TTS-Key: <same key fallback compatibility>`

Payload includes:

- `target`: `excerpt`, `summary`, or `faqs`
- `site_url`
- `post_id`
- `post_type`
- `title`
- `permalink`
- `excerpt`
- `content_html`
- `content_text`
- `post_summary`
- `faqs`
- `rules`: labels that point to the existing Publish Scale custom excerpt, post summary, and FAQ generation rules

## Expected responses

Excerpt:

```json
{"excerpt":"Short custom excerpt text."}
```

Summary:

```json
{"summary":"HTML or plain text post summary."}
```

FAQs:

```json
{"faqs":[{"question":"Question text?","answer":"Answer text."}]}
```

The plugin also accepts a nested `data` object, for example:

```json
{"data":{"faqs":[{"question":"Question text?","answer":"Answer text."}]}}
```

## Reporting portal requirements

Create a reporting portal similar to the text-to-speech portal. Track every request by:

- site URL
- post ID
- post permalink
- target type
- request timestamp
- response timestamp
- HTTP status
- model or rule profile used
- generated character counts
- generated FAQ row count
- success or failure
- error message when failed

## WordPress UI requirements already implemented

- Separate SMP settings tab for Content Generation
- Separate SMP API key stored through Hexa Credential Vault
- API base and timeout settings
- Editor buttons for excerpt, summary, and FAQs
- Creating state, activity log, green success, red error
- Storage into native excerpt, `post_summary`, `post_faq_items`, and `post_faq_schema_enabled`

## Do not duplicate rules

The publish-side service must reuse the same source of truth already used for Publish Scale article generation rules for excerpts, summaries, and FAQs.
