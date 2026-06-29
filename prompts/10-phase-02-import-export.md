# Prompt 10: Phase 02 Import Export

## Goal

Extend Filament-native import/export to the finalized Phase 02 schema.

## Current state assumptions

- Prompts 07, 08, and 09 are complete and committed.
- Transcriptions, categories, typed tags, pinning, and media metadata fields exist.
- Prompt 10 may only import/export fields created by Prompts 07-09. Do not add or depend on fields planned for Prompt 11, 12, 13, or 14.

## Docs to read

- `AGENTS.md`
- `docs/phase-02/import-export-revision-spec.md`
- `docs/phase-02/tooling-and-quality-gates.md`

## Blueprint and guidelines

- `docs/phase-02/blueprints/10-import-export-blueprint.md`
- `.ai/guidelines/import-export.md`
- `.ai/guidelines/tooling-quality.md`

## Scope

- Transcription importer/exporter.
- Category importer/exporter.
- Typed tag import/export behavior.
- Existing group/item/author import/export updates.
- Prompt 08 media fields on `ContentItem`: `embed_provider`, `media_duration_seconds`, `external_id`, `external_title`, `external_description`, `external_thumbnail_url`, `external_published_at`, `media_metadata`, and `direct_media_url`.
- Optional approved `.md`/`.txt` transcript file references.

## Out of scope

No custom CSV controllers, retry dashboards, remote media fetching, public UI, dashboards, studio, parser/viewer-only fields, or fields that are not created by Prompts 07-09.

## Package/tool assumptions

Use Boost docs when available and FilamentExamples MCP import/export examples. Do not install packages.

## Implementation plan

1. Write failing import/export tests.
2. Add new Importer/Exporter classes.
3. Extend existing ContentItem/ContentGroup import/export columns.
4. Validate relationship resolution and failed rows.
5. Verify formula-injection protection.

## Acceptance criteria

All Phase 02 content schema can be imported/exported with portable identifiers and native Filament actions.

## Required tests

Create/update imports, relationship resolution, failed rows, transcript import, category/tag import, export columns, bulk export, and authorization.

## Required quality gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Final report format

Report files changed, tests added, commands/results, assumptions, deferred issues, and FilaCheck output.

## Commit behavior

Commit only after the full quality gate passes.
