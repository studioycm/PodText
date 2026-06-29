# Phase 02 Feature Map

## Corrected Build Order

1. Prompt 06R: reset research, specs, guidelines, prompts, and blueprints only.
2. Prompt 07: transcriptions model revision.
3. Prompt 08: categories, Spatie tags, item pinning, settings, and media field foundation.
4. Prompt 09: admin management for transcriptions/categories/tags/pinning/settings/media fields.
5. Prompt 10: import/export for finalized Phase 02 schema.
6. Prompt 11: public homepage/search/category/tag landing pages.
7. Prompt 12: public item page, safe media player rendering, transcription tabs, timestamp parser, and viewer hide/show preferences.
8. Prompt 13: editorial dashboard metrics.
9. Prompt 14: future sync viewer and transcription studio plan only.
10. Prompt 15: Filament Blueprint security audit after implementation.

## Non-Negotiable Semantics

- Public homepage/search/category/tag listings return `ContentItem` records.
- Public result cards are never `Transcription` records.
- `Transcription` is a child model of `ContentItem`.
- `ContentItem` has many transcriptions.
- Effective/main transcription is featured published transcription, then latest published transcription, then `null`.
- Latest transcriptions means `ContentItem` records ordered by effective/main transcription `published_at`.
- Items without an effective/main published transcription are hidden from public listings.
- Pinning belongs only to `ContentItem`.
- Categories are custom hierarchical records.
- Tags use Spatie tags, scoped to type `content`, with enabled-only public visibility.
- Media fields are founded before import/export is revised.
- Prompt 14 is future planning only.

## Future Ability Names

Do not install Shield in Phase 02 planning. Use these names for future authorization planning:

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
- `run filament security audit`

## Main Blueprint Map

- Prompt 07: `blueprints/07-transcriptions-model-revision-blueprint.md`
- Prompt 08: `blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md`
- Prompt 09: `blueprints/09-admin-content-management-blueprint.md`
- Prompt 10: `blueprints/10-import-export-blueprint.md`
- Prompt 11: `blueprints/11-public-homepage-search-blueprint.md`
- Prompt 12: `blueprints/12-public-item-page-media-parser-blueprint.md`
- Prompt 13: `blueprints/13-dashboard-metrics-blueprint.md`
- Prompt 14: `blueprints/14-viewer-studio-future-plan-blueprint.md`
- Prompt 15: `blueprints/15-filament-security-audit-blueprint.md`
