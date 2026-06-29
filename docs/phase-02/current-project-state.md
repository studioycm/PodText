# Phase 02 Current Project State

Recorded after Prompt 07 was run and committed. This document intentionally avoids local secrets and should be updated when later prompts change the active baseline.

## Git State

- Current branch: `master`.
- Latest commit inspected after Prompt 07: `7edb82d feat: add transcription model revision`.
- Starting docs-sync working tree: clean (`git status --short --branch` reported `## master`).
- Prompt 07 state: committed. Prompt 08 has not been run.
- This post-Prompt-07 documentation sync is intentionally uncommitted for human review.

## Tooling State

- Laravel: 13.17.0.
- PHP: 8.4.22.
- Filament: 5.6.7.
- Livewire: 4.3.3.
- Laravel Boost: 2.4.10 installed.
- Filament Blueprint: 2.2.0 installed.
- FilaCheck: 1.2.3 installed.
- FilaCheck Pro: 1.2.7 installed.
- App locale from `php artisan about`: `he`.
- App timezone from `php artisan about`: `UTC`; Phase 02 UI requirements still require Israel/Hebrew date presentation in `Asia/Jerusalem` while storing dates with Laravel's normal conventions.

## Boost MCP Status

Laravel Boost MCP tools were exposed and usable during Prompt 06S verification and this post-Prompt-07 sync. `application_info`, `database_schema`, and `search_docs` succeeded during this sync. Shell and Artisan inspection were also run because the prompt explicitly requested them.

## Application Shape

- Database driver: SQLite.
- Public panel root: `/`.
- Admin panel root: `/admin`.
- Existing public pages:
  - `App\Filament\Public\Pages\BrowseContentGroups`
  - `App\Filament\Public\Pages\ShowContentGroup`
  - `App\Filament\Public\Pages\ShowContentItem`
- Existing public Livewire components:
  - `App\Livewire\Public\ContentGroupBrowser`
  - `App\Livewire\Public\ContentItemBrowser`

## Current Domain Schema

Current tables relevant to content before running the new local migrations:

- `authors`
- `content_groups`
- `content_items`
- `author_content_item`

Prompt 07 added committed migrations that are pending in the inspected local database:

- `2026_06_29_134855_create_transcriptions_table`
- `2026_06_29_134914_add_featured_transcription_id_to_content_items_table`
- `2026_06_29_134914_backfill_transcriptions_from_content_items_table`

Prompt 07 code/migration state:

- `App\Models\Transcription` exists.
- A `transcriptions` table migration exists with `reference_key`, `content_item_id`, nullable `author_id`, title/language/status/published fields, canonical `transcript_markdown`, parser output JSON fields, and indexes.
- A `content_items.featured_transcription_id` migration exists with a nullable FK to `transcriptions.id` and `nullOnDelete`.
- The inspected local SQLite database has not applied those three Prompt 07 migrations yet, so the physical `transcriptions` table and `featured_transcription_id` column are pending locally.
- Legacy `content_items.transcript_markdown` still exists in the original schema as the backfill source and cleanup target for a later prompt.
- New writes to legacy `content_items.transcript_markdown` are deprecated/blocked in application code: the field was removed from `ContentItem::$fillable`, admin item form transcript editing, item import columns, item export columns, and normal factory defaults.

Not currently present until later prompts:

- categories
- Spatie `tags` / `taggables`
- homepage section/settings tables
- item pinning fields
- provider metadata fields
- dashboard widgets

## Prompt 07 Implementation Notes

- `ContentItem::transcriptions()`, `ContentItem::featuredTranscription()`, `ContentItem::latestPublishedTranscription()`, and `ContentItem::effectiveTranscription()` exist.
- `Transcription::contentItem()` and `Transcription::author()` exist.
- `Author::transcriptions()` exists.
- `ContentItem::published()` now requires a published parent group, a published item, and at least one published child transcription.
- Public item/group pages now load and render effective/main transcription content instead of directly rendering legacy item transcript content.
- Public item sorting by effective/main transcription `published_at` is implemented through query scopes and covered by Prompt 07 tests.
- Featured transcription ownership is validated in model saving logic so a featured transcription must belong to the same `ContentItem`.
- Public effective transcription resolution ignores unpublished featured transcriptions and falls back to the latest published transcription.
- Delete behavior uses the nullable FK with `nullOnDelete`; stricter clear-or-reject behavior on unpublish/delete should remain documented and tested in follow-up prompts if needed.
- The backfill migration creates one child `Transcription` for each item with nonblank legacy transcript Markdown, uses the first item author when available, copies status/title/published date, and sets `featured_transcription_id` if it is empty. Its `down()` migration intentionally does not move child transcript data back into the legacy item field.

## Prompt 07 Tests Observed

- `tests/Feature/TranscriptionsModelTest.php` covers relationships/casts, immutable reference keys, backfill, effective ordering, and same-item featured validation.
- `tests/Feature/PublicTranscriptionVisibilityTest.php` covers public hiding without effective transcription, public item 404 behavior, safe Markdown rendering from `Transcription`, effective sorting, and unpublished featured fallback behavior.
- Existing public/admin/import/domain tests were updated to use child transcriptions and stop expecting legacy transcript import/export behavior.

## Form and Locale Issues Discovered by User

- Slug fields should be auto-generated from relevant title/name fields using current Filament v5 patterns, while allowing a manual override.
- Dates/date-times and similar UI output/input should use Israel/Hebrew locale behavior and day-first `dd/mm/yyyy` display; date-time UI should use `dd/mm/yyyy HH:mm`.
- UI date/time presentation should use `Asia/Jerusalem`; storage should continue to use Laravel's normal date storage conventions.
- Technical/system fields such as slug, reference keys, provider IDs, external IDs, metadata JSON, pin fields, and featured transcription selectors need hints/help text/descriptions.
- Existing and already-available editorial metrics should be surfaced as admin dashboard widgets, with later prompts extending those widgets as more schema becomes available.

## Baseline Issue To Record

`php artisan model:show App\Models\ContentItem` and `php artisan model:show App\Models\ContentGroup` previously failed with a class redeclare fatal. This documentation sync did not retest or fix that application issue. Future implementation prompts should avoid relying on `model:show` until the cause is investigated.
