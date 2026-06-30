# Phase 02 Feature Map

## Corrected Build Order

1. Prompt 07: transcriptions model revision.
2. Prompt 08: taxonomy, tags, pinning, settings, and media foundation.
3. Prompt 09: admin content management.
4. Prompt 10: import/export.
5. Prompt 11: public homepage/search.
6. Prompt 12: item page, media, and parser.
7. Prompt 13: dashboard metrics.
8. Prompt 14: future viewer/studio plan.
9. Prompt 15: security audit.

## Current Progress

- Prompt 07 already ran and was committed as `7edb82d feat: add transcription model revision`.
- Prompt 07 migrations are now applied locally: `transcriptions`, `content_items.featured_transcription_id`, and the backfill migration all show `Ran`.
- Prompt 08 is complete and committed as `b15f5c1 feat: add taxonomy tags pinning settings and media foundation`.
- Prompt 09 is complete and committed as `22e11d0 feat: add phase two admin content management`.
- The post-Prompt-09 admin management UX repair is complete and committed as `16ab33a fix: repair admin management ux after phase two resources`.
- The latest local `HEAD` inspected during the post-admin-repair state sync is `e671d7b feat: configure sidebar width, collapsibility, and content max width in AdminPanelProvider`.
- Prompt 10 is the next implementation prompt after this state sync is reviewed and the full baseline or required implementation quality gate is acceptable.
- Do not run Prompt 10 from this documentation sync task.

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

## Cross-Cutting Admin Form Rules

- Slug fields should auto-generate from the relevant title/name field using current Filament v5 patterns, while allowing manual override.
- Date and date-time form fields, table columns, and public displays should use Hebrew/Israel day-first formatting: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- UI date/time presentation should use `Asia/Jerusalem`; store dates using Laravel's normal date storage conventions.
- Technical/system fields such as slugs, reference keys, provider IDs, external IDs, metadata JSON, pin fields, and featured transcription selectors must include helper text, hints, or descriptions.
- Labels, helper text, hints, section headings, validation messages, and sort/date labels should use translation keys.
- Admin dashboard widgets should show editorial metrics already available from the current schema, and later prompts should extend the widgets as more schema becomes available.

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
  - Admin Resource UX includes researched relation manager patterns from `docs/research/filament-examples-admin-resource-relation-managers.md`.
  - `ContentItemResource` should add `TranscriptionsRelationManager` as the primary item-scoped transcript editing surface.
  - `EditContentItem` should use combined item details/relation manager tabs when Prompt 09 implements the admin UX.
  - Standalone Resource create/edit pages should use the researched redirect behavior, while relation manager create/edit actions stay on the owner item edit page.
- Prompt 10: `blueprints/10-import-export-blueprint.md`
- Prompt 11: `blueprints/11-public-homepage-search-blueprint.md`
  - Prompt 11 must consume `PublicContentSettings` and visible ordered `HomepageSection` records when implementing the public homepage/search UI.
- Prompt 12: `blueprints/12-public-item-page-media-parser-blueprint.md`
- Prompt 13: `blueprints/13-dashboard-metrics-blueprint.md`
- Prompt 14: `blueprints/14-viewer-studio-future-plan-blueprint.md`
- Prompt 15: `blueprints/15-filament-security-audit-blueprint.md`
