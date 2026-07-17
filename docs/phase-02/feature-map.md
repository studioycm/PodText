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

## Progress Pointer

For current prompt completion/progress state, see `docs/phase-02/current-project-state.md`.

## Feature-First Restart

The controlling reset is
`docs/research/settings-performance/19-authz-complexity-reset-and-feature-first-master-plan.md`.
The bounded AUTHZ command-surface closure is complete in `0be8070`; no further
AUTHZ audit or remediation is pending. The selected next work is docs-only Step
5B planning through
`prompts/pre-13-prompts/step5b-feature-first-controller-codex-prompt.md` v1.
It first delegates a thorough specification-prompt mini-task, then runs that
prompt in a separate mini-task to prepare Card Template preview beside the
editor on wide screens and in a slide-over on narrower screens, using the
existing SP3C storage/writer and controlled public card presenters.

The specification returns for operator review. A later PHP implementation, if
selected, begins with its own Laravel Simplifier Stage 1 audit and ID-bound
approval; the current controller does not run that audit or write application
code.

AUTHZ1-D–I, multiple roles, direct grants, role UI, extra panels, package
cutover, ARCH1, and SP3D are deferred/not-current. They do not block ordinary
settings, import-lock, Card Template, or Public Front feature work.

The surviving and deferred tracking registers are maintained in
`docs/research/settings-performance/10-pending-decision-question-queue.md`.
They preserve P2/P3, `MAINT-LW-UX1`, `WB-PROBE-HF1`, the Google probe, LENS
review packs, production settings/cache/mail checks, conditional SP3 browser
evidence, and the existing optional Public Front queue without making any of
them automatic next work.

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
- Import/export uses native Filament importers/exporters with portable reference keys, category paths, and typed content tag slugs.
- Transcript imports write to `Transcription` records, not legacy `content_items.transcript_markdown`.
- `transcript_file` imports are deferred until an approved import package structure is specified; inline `transcript_markdown` import is supported.
- Prompt 14 is future planning only.

## Cross-Cutting Admin Form Rules

- Slug fields should auto-generate from the relevant title/name field using current Filament v5 patterns, while allowing manual override.
- Date and date-time form fields, table columns, and public displays should use Hebrew/Israel day-first formatting: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- UI date/time presentation should use `Asia/Jerusalem`; store dates using Laravel's normal date storage conventions.
- Technical/system fields such as slugs, reference keys, provider IDs, external IDs, metadata JSON, pin fields, and featured transcription selectors must include helper text, hints, or descriptions.
- Labels, helper text, hints, section headings, validation messages, and sort/date labels should use translation keys.
- Admin dashboard widgets should show editorial metrics already available from the current schema, and later prompts should extend the widgets as more schema becomes available.

## Authorization Status

Shield/Permission are installed but dormant. Legacy `users.role`, ranks, and
Gates remain authoritative. Do not expand the dormant ability catalog or plan
future role/panel governance until a concrete current feature requires it and
the operator approves a fresh complexity estimate.

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
  - Prompt 11 must preserve Prompt 10 native import/export behavior unless the Prompt 11 blueprint explicitly requires a related change.
- Prompt 12: `blueprints/12-public-item-page-media-parser-blueprint.md`
- Prompt 13: `blueprints/13-dashboard-metrics-blueprint.md`
- Prompt 14: `blueprints/14-viewer-studio-future-plan-blueprint.md`
- Prompt 15: `blueprints/15-filament-security-audit-blueprint.md`
