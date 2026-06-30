# Phase 02 Transcriptions Model Spec

## Status

Prompt 07 was run and committed as `7edb82d feat: add transcription model revision`. This file is now a post-Prompt-07 baseline plus remaining requirements document.

Post-migration sync verification found the local database has now applied all three Prompt 07 migrations:

- `2026_06_29_134855_create_transcriptions_table` ran and the physical `transcriptions` table exists.
- `2026_06_29_134914_add_featured_transcription_id_to_content_items_table` ran and `content_items.featured_transcription_id` exists.
- `2026_06_29_134914_backfill_transcriptions_from_content_items_table` ran.
- Legacy `content_items.transcript_markdown` still exists only as a legacy/backfill source and later cleanup target.

`migrate:status` shows all migrations in batch 1, which strongly suggests the local database was rebuilt with `migrate:fresh --seed` or an equivalent reset path. The exact manual command was not observed. If that reset path was used, the backfill migration ran during the fresh migration sequence but may not have backfilled older local user-entered rows because the previous local database contents were reset first.

## Implemented Baseline From Prompt 07

- `App\Models\Transcription` exists and stores canonical Markdown transcript content.
- A committed migration creates `transcriptions` with:
  - `reference_key`, unique ULID string;
  - `content_item_id`, foreign key cascade delete;
  - nullable `author_id`, null on delete;
  - nullable `title`;
  - `language_code`, default `he`;
  - `transcript_markdown`;
  - `status`, cast to `PublicationStatus`;
  - nullable `published_at`;
  - nullable `word_count`;
  - nullable JSON `speakers`;
  - nullable JSON `parsed_segments`;
  - timestamps and query indexes.
- A committed migration adds nullable `content_items.featured_transcription_id` with `nullOnDelete`.
- A committed backfill migration creates one `Transcription` from each nonblank legacy `content_items.transcript_markdown`, copies item title/status/published date, uses the first item author when available, and features that transcription when the item has no featured transcription.
- `ContentItem::transcriptions()`, `ContentItem::featuredTranscription()`, `ContentItem::latestPublishedTranscription()`, and `ContentItem::effectiveTranscription()` exist.
- `Transcription::contentItem()` and `Transcription::author()` exist.
- `Author::transcriptions()` exists.
- Public item/group pages now use effective/main transcription content.
- Public item visibility now requires a published group, a published item, and at least one published child transcription.
- Public latest/search sorting by effective/main transcription `published_at` is queryable and covered by Prompt 07 tests.
- New writes to legacy `content_items.transcript_markdown` are deprecated/blocked in normal code paths: model fillable, admin form, import/export columns, and factories no longer write new canonical transcript content there.

## Effective/Main Transcription Rules

Resolve in this order:

1. Featured transcription if it belongs to the item and is published.
2. Latest published transcription by `published_at` and `id`.
3. `null`.

Implemented safeguards:

- `featured_transcription_id` must reference a transcription belonging to the same `ContentItem`.
- Only a published featured transcription can be effective publicly.
- If the featured transcription is unpublished, public effective resolution ignores it and falls back to the latest published transcription.
- If the featured transcription is deleted, the nullable FK is configured with `nullOnDelete`.

Remaining follow-up requirements:

- If future admin actions unpublish or delete a featured transcription, they should either clear `featured_transcription_id` or reject safely with a clear validation message.
- Follow-up tests should cover explicit admin unpublish/delete behavior after Prompt 09 admin management exists.
- Public latest/search sorting by effective/main transcription `published_at` must remain queryable and tested as Prompt 11 replaces the current public listing UI.

## Legacy Transcript Field Policy

- `content_items.transcript_markdown` remains only as a legacy/backfill source.
- New canonical transcript writes must go to `Transcription::transcript_markdown`.
- Imports and exports must not write transcript content to the legacy field.
- The legacy field should only be dropped in a later cleanup after imports, exports, public pages, and tests no longer rely on it.
- Any prompt that touches item transcript behavior must verify it did not reintroduce new writes to the legacy field.

## Security

- Markdown remains canonical.
- Public rendering continues through `SafeMarkdownRenderer`.
- Draft transcriptions are never public.
- Public listings require item, group, and effective/main transcription publication rules.
- Featured transcription ownership and publication state must be validated before admin feature actions become available.

## Blueprint

See `docs/phase-02/blueprints/07-transcriptions-model-revision-blueprint.md`.
