# Prompt 10: Phase 02 Import Export

## Goal

Extend Filament-native import/export to the finalized Phase 02 schema.

## Current state assumptions

- This prompt depends on the transcriptions, taxonomy, tags, pinning, settings, media foundation, and admin management baseline from the prior prompts in the active sequence.
- Verify the admin management UX repair baseline is present before implementation.
- For current prompt progress, read `docs/phase-02/current-project-state.md`.
- `docs/phase-02/spatie-tags-and-settings-decision.md` exists and is accepted as the current Spatie tags/settings decision.
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

## Blueprint contract

The blueprint file named above is the detailed implementation contract for this prompt.

Before changing code:

1. Read the entire blueprint.
2. Summarize the blueprint sections that apply to this prompt.
3. Compare the blueprint against the current repository state.
4. If the blueprint conflicts with the active prompt, Phase 02 specs, `AGENTS.md`, or current code, stop and report the conflict before implementing.
5. If the prompt body is shorter than the blueprint, follow the blueprint details.
6. Do not omit blueprint fields, relationships, constraints, Filament components, tests, or quality checks unless the blueprint marks them optional or the current code makes them impossible.
7. In the final report, include a "Blueprint completion checklist" with:
   - implemented;
   - already existed;
   - deferred by blueprint;
   - not applicable;
   - blocked.

The import/export blueprint is the authority for import/export class names, columns, relationship resolution, failed-row behavior, native Filament actions, and tests.

- If `transcript_file` support is implemented:
  - CSV may include `transcript_file`;
  - allowed extensions are `.md` and `.txt`;
  - missing referenced files fail the row;
  - transcript file content creates/updates `Transcription` records;
  - transcript file content must never write to legacy `ContentItem` transcript fields.
- Missing categories fail the row by default.
- Missing tags fail the row by default unless a future option explicitly creates disabled content tags.
- Do not reintroduce writes to legacy `content_items.transcript_markdown`.
- The first imported transcription for an item may automatically set `featured_transcription_id` through existing model behavior; tests must account for this.
- If importing multiple transcriptions for one item, import a featured transcription reference only when provided. Otherwise the existing first-transcription default behavior applies.
- Preserve the Spatie tags decision: use Spatie's `tags` table, `taggables` pivot, and type `content`; do not create a custom `content_item_tag` pivot.
- Preserve `App\Models\ContentTag` only as the configured Spatie custom tag model for enabled/moderation fields.
- Do not implement public consumption of `PublicContentSettings` or `HomepageSection` in Prompt 10; Prompt 11 owns that work.
- Imported date fields should accept day-first `dd/mm/yyyy` and `dd/mm/yyyy HH:mm` where appropriate.
- Exported date fields should use day-first format.

## Scope

- Transcription importer/exporter.
- Category importer/exporter.
- Typed tag import/export behavior.
- Existing group/item/author import/export updates.
- Prompt 08 media fields on `ContentItem`: `embed_provider`, `media_duration_seconds`, `external_id`, `external_title`, `external_description`, `external_thumbnail_url`, `external_published_at`, `media_metadata`, and `direct_media_url`.
- Optional approved `.md`/`.txt` transcript file references.

## Transcript file and date behavior

- CSV may include `transcript_file` only if the blueprint defines the approved import package structure.
- Allowed transcript file extensions are `.md` and `.txt`.
- Missing referenced transcript files fail the row.
- Transcript file content creates or updates `Transcription` records.
- Transcript file content must never write to legacy `ContentItem` transcript fields.
- Missing categories fail the row by default.
- Missing tags fail the row by default unless a future import option explicitly creates disabled content tags.
- Existing first-transcription auto-feature behavior may set `featured_transcription_id` when creating the first imported transcription for an item.
- Import explicit featured transcription state only when the import data provides it.
- Do not use numeric IDs for portable import/export.
- Imported date fields should accept day-first `dd/mm/yyyy` where appropriate and normalize to Laravel date storage.
- Exported date fields should use `dd/mm/yyyy`; exported date-time fields should use `dd/mm/yyyy HH:mm`.

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

Commit only after the full quality gate passes. After success, update `docs/phase-02/current-project-state.md` before final commit. Patch other docs only when stable requirements changed.
