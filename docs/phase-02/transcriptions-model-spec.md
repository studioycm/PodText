# Phase 02 Transcriptions Model Spec

## Goal

Move canonical transcript Markdown from `ContentItem` to child `Transcription` records while preserving item-based public listings.

## Tables and Fields

Create `transcriptions`:

- `id`
- `reference_key`, unique ULID string
- `content_item_id`, foreign key cascade delete
- `author_id`, foreign key restrict or null-on-delete decision in blueprint
- `title`, nullable string
- `language_code`, string default `he`
- `transcript_markdown`, long text
- `status`, string cast to `PublicationStatus`
- `published_at`, nullable datetime
- `word_count`, nullable integer
- `speakers`, nullable JSON
- `parsed_segments`, nullable JSON
- timestamps

Add to `content_items` after `transcriptions` exists:

- `featured_transcription_id`, nullable foreign key to `transcriptions.id`

## Relationships

- `ContentItem::transcriptions()` has many `Transcription`.
- `ContentItem::featuredTranscription()` belongs to `Transcription`.
- `ContentItem::effectiveTranscription()` must be queryable enough for listing/sorting.
- `Transcription::contentItem()` belongs to `ContentItem`.
- `Transcription::author()` belongs to `Author`.
- `Author::transcriptions()` has many `Transcription`.

## Effective/Main Transcription

Resolve in this order:

1. Featured transcription if it belongs to the item and is published.
2. Latest published transcription by `published_at` and `id`.
3. `null`.

Validation/tests:

- featured transcription must belong to the same item;
- unpublished featured transcription is not effective;
- delete/unpublish behavior safely clears the featured field or rejects the operation as defined in Prompt 07;
- public sorting by effective transcription date is tested.

## Migration Strategy

1. Create transcriptions table.
2. Backfill one transcription from each nonblank `content_items.transcript_markdown`.
3. Use first item author as transcription author when available.
4. Add `content_items.featured_transcription_id`.
5. Mark `content_items.transcript_markdown` as legacy and stop writing to it.
6. Drop the legacy column only in a later cleanup after import/export/public tests are updated.

## Security

- Markdown remains canonical.
- Public rendering continues through `SafeMarkdownRenderer`.
- Draft transcriptions are never public.
- Public listings require item, group, and effective transcription to be published.

## Blueprint

See `docs/phase-02/blueprints/07-transcriptions-model-revision-blueprint.md`.
