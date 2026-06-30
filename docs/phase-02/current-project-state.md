# Phase 02 Current Project State

Recorded after Prompt 07 was run, committed, and the local Prompt 07 migrations were applied manually. This document intentionally avoids local secrets and should be updated when later prompts change the active baseline.

## Git State

- Current branch: `main` tracking `origin/main`.
- Latest commit inspected during this post-migration sync: `dd60315 docs: add Spatie Laravel and Technical Debt Manager skill guidelines for PHP/Laravel-focused workflows`.
- Prompt 07 implementation commit remains in history: `7edb82d feat: add transcription model revision`.
- Starting docs-sync working tree: clean (`git status --short --branch` reported `## main...origin/main`).
- Prompt 07 state: committed and locally migrated. Prompt 08 has not been run.
- This post-migration Phase 02 documentation sync is intentionally uncommitted for human review.

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

Laravel Boost MCP tools were exposed and usable during this post-migration sync.

- Boost tools used: `application_info`, `database_schema`, and `database_query`.
- Boost `search_docs` was not needed because no syntax or package-behavior uncertainty came up.
- Fallback shell and Artisan inspection was still run because the prompt explicitly requested it.

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

Current tables relevant to content after the local Prompt 07 migrations were applied:

- `authors`
- `content_groups`
- `content_items`
- `author_content_item`
- `transcriptions`

Prompt 07 migration status from `php artisan migrate:status` and Boost database inspection:

- `2026_06_29_134855_create_transcriptions_table`: ran.
- `2026_06_29_134914_add_featured_transcription_id_to_content_items_table`: ran.
- `2026_06_29_134914_backfill_transcriptions_from_content_items_table`: ran.

Current physical schema verified through Boost `database_schema`:

- `transcriptions` table exists.
- `content_items.featured_transcription_id` exists and references `transcriptions.id` with `onDelete set null`.
- Legacy `content_items.transcript_markdown` still exists as a legacy/backfill source and later cleanup target.

Prompt 07 code/migration state:

- `App\Models\Transcription` exists.
- `ContentItem::transcriptions()` exists.
- `ContentItem::featuredTranscription()` exists.
- `ContentItem::latestPublishedTranscription()` exists.
- `ContentItem::effectiveTranscription()` exists.
- `Author::transcriptions()` exists.
- `Transcription::contentItem()` and `Transcription::author()` exist.
- New writes to legacy `content_items.transcript_markdown` are deprecated/blocked in application code: the field was removed from `ContentItem::$fillable`, admin item form transcript editing, item import columns, item export columns, and normal factory defaults.

Prompt 08 state:

- Prompt 08 has not run yet.
- Categories, Spatie tags/taggables, homepage sections/settings, item pinning fields, media metadata foundation fields, and dashboard widgets are still absent from the application schema/code unless they appear only in planning/spec documentation.
- Schema/search inspection found no implemented `Category` or `HomepageSection` model, category pivots, pin fields, Spatie tag tables, settings tables/classes, or Prompt 08 media metadata columns.

Local database reset note:

- `migrate:status` shows all migrations, including Prompt 07 migrations, in batch 1. That strongly suggests the local database was rebuilt with `migrate:fresh --seed` or an equivalent reset path, but this documentation sync did not observe the exact manual command.
- Current local counts from Boost `database_query`: 1 user, 3 content groups, 4 content items, and 3 transcriptions.
- If older local admin users or manually entered rows existed before the reset, they may need to be recreated or re-entered. The current database now reflects the resulting migrated/seeded state.

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
- Focused test result during this sync: `php artisan test --filter=TranscriptionsModelTest` passed, 5 tests and 19 assertions.
- Focused test result during this sync: `php artisan test --filter=PublicTranscriptionVisibilityTest` passed, 6 tests and 14 assertions.

## Form and Locale Issues Discovered by User

- Slug fields should be auto-generated from relevant title/name fields using current Filament v5 patterns, while allowing a manual override.
- Dates/date-times and similar UI output/input should use Israel/Hebrew locale behavior and day-first `dd/mm/yyyy` display; date-time UI should use `dd/mm/yyyy HH:mm`.
- UI date/time presentation should use `Asia/Jerusalem`; storage should continue to use Laravel's normal date storage conventions.
- Technical/system fields such as slug, reference keys, provider IDs, external IDs, metadata JSON, pin fields, and featured transcription selectors need hints/help text/descriptions.
- Existing and already-available editorial metrics should be surfaced as admin dashboard widgets, with later prompts extending those widgets as more schema becomes available.

## Baseline Issue To Record

`php artisan model:show App\Models\ContentItem` and `php artisan model:show App\Models\ContentGroup` previously failed with a class redeclare fatal. This documentation sync did not retest or fix that application issue. Future implementation prompts should avoid relying on `model:show` until the cause is investigated.
