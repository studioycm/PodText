# Phase 02 Feature Map

Phase 02 expands the Slice 0 content loop into a stronger public content experience while preserving sequential implementation. This document is a map, not implementation.

## Build Order

1. Transcription domain revision.
2. Taxonomy, tags, pinning, and settings foundation.
3. Admin content management updates.
4. Import/export revision.
5. Public homepage and search.
6. Media embed and item page refinement.
7. Dashboard metrics.
8. Viewer/studio future planning.

## Core Invariants

- Public browse, homepage, and search results are `ContentItem` records.
- A `Transcription` is a child record of a `ContentItem`.
- Pinning belongs only to `ContentItem`.
- "Latest transcriptions" means `ContentItem` records ordered by the effective/main published transcription `published_at`.
- A public item is visible only when the item, its group, and its effective/main transcription are published.
- No feature should introduce `Podcast` or `Episode` classes or tables.
- No Shield, roles, volunteer workflows, analytics, comments, request flows, or studio implementation belong in the Phase 02 implementation prompts unless explicitly moved from future planning.

## Future Ability Names

Document these names for future Shield work without installing Shield now:

- `manage content groups`
- `manage content items`
- `manage transcriptions`
- `feature transcription`
- `pin content items`
- `manage categories`
- `manage content tags`
- `manage media metadata`
- `manage homepage settings`
- `view editorial dashboard`

## Prompt Ownership

- Prompt 07 owns the data model move from `content_items.transcript_markdown` to child `transcriptions`.
- Prompt 08 owns categories, tags, pinning fields, and settings.
- Prompt 09 owns admin Resources and management screens for the new fields/models.
- Prompt 10 owns import/export updates.
- Prompt 11 owns public homepage/search.
- Prompt 12 owns item page/media embed refinement.
- Prompt 13 owns dashboard widgets.
- Prompt 14 remains planning-only for viewer/studio future work.
