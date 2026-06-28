# Phase 02 Transcriptions Model Spec

## Intent

Move transcript content out of `ContentItem` and into child `Transcription` records while keeping public listings item-based.

## Model

Create `App\Models\Transcription` with:

- `id`
- `reference_key`
- `content_item_id`
- `author_id`
- `title` or `label`
- `language_code`, default `he`
- `transcript_markdown`
- `status`, cast to `PublicationStatus`
- `published_at`
- optional derived fields: `word_count`, `duration_seconds`, `speakers_json`, `segments_json`
- timestamps

`ContentItem` has many `Transcription` records and may have a nullable `featured_transcription_id`.

## Effective/Main Transcription

Resolve an item's effective/main transcription in this order:

1. The item's `featured_transcription_id`, if it points to a published transcription.
2. The latest published transcription for the item by `published_at`.
3. `null`.

Public latest ordering uses this effective/main transcription date. Items without an effective/main published transcription are excluded from public latest/search results.

## Migration Path

1. Create `transcriptions` with foreign keys to `content_items` and `authors`.
2. Backfill one transcription for each item that currently has `transcript_markdown`.
3. Use the first item author as the initial transcription author where available; otherwise require remediation in tests/seed data.
4. Add nullable `content_items.featured_transcription_id` after the `transcriptions` table exists.
5. Mark `content_items.transcript_markdown` deprecated during the migration phase.
6. Drop the legacy column in a later cleanup only after imports, exports, public pages, and tests no longer read it.

## Public Rules

- Draft transcriptions are never publicly visible.
- Public item pages default to the effective/main transcription.
- Other published transcriptions may be shown as tabs or a selector.
- Markdown remains canonical. Parsed speakers/timestamps are derived.
- Public rendering must continue through the centralized safe Markdown renderer.

## Admin Rules

- Admins can create, edit, publish, draft, and feature transcriptions.
- A transcription belongs to one author in Phase 02.
- Future multi-contributor transcription credits are deferred.

## Tests Required Later

- Relationships and casts.
- Backfill migration behavior.
- Effective/main transcription resolution.
- Public exclusion when no published transcription exists.
- Draft transcription hidden from item page tabs.
- Markdown XSS regression after moving content to `Transcription`.
