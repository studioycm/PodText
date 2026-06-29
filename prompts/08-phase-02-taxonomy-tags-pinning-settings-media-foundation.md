# Prompt 08: Phase 02 Taxonomy, Tags, Pinning, Settings, and Media Foundation

## Goal

Implement categories, Spatie content tags, item pinning, settings, homepage sections, and media metadata foundation.

## Current state assumptions

- Prompt 07 is complete and committed.
- Spatie Tags, the Filament Spatie Tags plugin, Spatie Settings, and the Filament Spatie Settings plugin are approved for this implementation prompt. If absent, this prompt owns adding them; do not ask for package approval again.

## Required preflight before implementation

Before installing packages or adding schema, verify and report:

- `App\Models\Transcription` exists and the `transcriptions` table migration exists.
- `ContentItem` effective/main transcription API exists.
- `content_items.featured_transcription_id` behavior is implemented or documented as missing.
- Public visibility now requires an effective/main published transcription.
- Prompt 07 tests pass in the current checkout.

If Prompt 07 is incomplete, its migrations are missing, or its tests fail, stop and report instead of implementing Prompt 08.

## Docs to read

- `AGENTS.md`
- `docs/phase-02/taxonomy-tags-spec.md`
- `docs/phase-02/homepage-settings-spec.md`
- `docs/phase-02/media-embed-spec.md`
- `docs/phase-02/tooling-and-quality-gates.md`

## Blueprint and guidelines

- `docs/phase-02/blueprints/08-taxonomy-tags-pinning-settings-media-foundation-blueprint.md`
- `.ai/guidelines/taxonomy-tags.md`
- `.ai/guidelines/settings-dashboard.md`
- `.ai/guidelines/media-embeds.md`
- `.ai/guidelines/tooling-quality.md`

## Scope

- Custom hierarchical categories.
- Spatie typed content tags.
- `ContentItem` pin fields only.
- Spatie Settings foundation.
- Homepage section model.
- Media metadata fields on `ContentItem`.

## Form and locale requirements

- Category slug fields must auto-generate from category name while allowing manual override.
- Homepage section slug fields must auto-generate from section name/title while allowing manual override.
- Any new model with slug/reference key fields must provide helper text.
- All date/date-time fields introduced in Prompt 08 must be displayed and edited with Israel/Hebrew locale expectations and day-first formatting.
- Use `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times unless the docs/blueprint define another day-first Israeli format.
- Pin fields such as `pinned_at` and `pinned_until` should use `Asia/Jerusalem` for UI display/input while storing dates normally through Laravel.
- Technical/system fields should be grouped under an "Advanced" or "Technical details" section where practical, with helper text.

## Out of scope

No admin Resource polish, import/export, public homepage/search UI, item page parser/media rendering, dashboards, or studio.

## Package/tool assumptions

Use Boost docs when available. Install only the approved Spatie Tags/Settings packages required by this prompt if they are absent; do not install unrelated packages.

## Implementation plan

1. Write failing domain tests.
2. Add migrations/models/factories.
3. Add relationships/scopes.
4. Add settings classes/migrations using the approved settings packages.
5. Add media field validation helpers where needed.

## Acceptance criteria

Schema foundation exists for Prompt 09 admin management and Prompt 10 import/export.

## Required tests

Category hierarchy, inheritance, descendant filtering, item pin scopes/order/expiration, settings defaults, tag scoping/enabled visibility, media field validation.

## Required quality gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Final report format

Report files changed, tests added, commands/results, assumptions, deferred issues, package decisions, and FilaCheck output.

## Commit behavior

Commit only after the full quality gate passes.
