# Publish Scale Content Generation

Target plugin: `smp-publication-integration` (2.3.0+). Feature tab: `Content Generation`.

WordPress never owns the writing rules. Publish (publish.scalemypublication.com)
writes each excerpt, summary and FAQ set as a background **AI job** and decides
which AI connection answers it: the ChatGPT/Codex subscription (default) or the
provider API. Switch on Publish with
`php artisan ai:jobs mode --mode=api|subscription --purpose=smp.content`.

## Contract

All calls are `POST` under `/api/smp-content-generation/v1`, JSON, with the
site's key in `X-SMP-Content-Key`. A site only ever sees its own jobs.

- `/status` checks the key.
- `/generate` `{target, site_url, post_id, title, permalink, content_text}`
  answers `202` at once with `{job_id, target, status, mode, attempts, data, error}`.
  `target` is `excerpt`, `summary` or `faqs`. Articles under 300 characters are
  refused with `422` and a message.
- `/jobs` `{job_ids: [...]}` (up to 20) returns the same shape for each job.
  `status` is `pending`, `running`, `completed` or `failed`. When completed,
  `data` holds `{excerpt}`, `{summary}` (an HTML `<ul>`) or `{faqs: [{question, answer}]}`.
  When failed, `error` says why (for example the quality review's last reason).

When a job finishes, Publish pings the site URL registered with the key:
`POST /wp-json/smpi/v1/generation-jobs/notify {post_id, job_id}`. The ping
carries no content; the site reads the result through `/jobs` with its key.

## WordPress side

- `Content\Generation\PublishContentClient`: the only HTTP client to Publish.
- `Content\Generation\GeneratedValueStore`: where each value lives (native
  excerpt, `post_summary`, `post_faq_items` + FAQ schema on) and the one place
  that reads, checks and saves it.
- `Content\Generation\GenerationJobs`: the lifecycle. Each target's job is
  stored in its own post meta row (`_smpi_generation_job_<target>`), so a
  screen opened later still knows it is working. A working job is settled by
  Publish's ping, the editor's poll (every 4 s while something is working) or
  the background `smpi_generation_jobs_poll` event, whichever comes first. A job
  still working after 15 minutes is given up.
- `assets/admin/content-generation.js`: `window.smpiGeneration`, the one editor
  client used by the inline field buttons and the Going Live checklist. It
  fills a field in when its job finishes.
- `do_action( "smpi_go_live_process", $post_id )` starts every empty target
  (unless the post is marked "Do not process this page") and returns at once.
