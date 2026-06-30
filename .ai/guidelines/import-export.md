# Import Export Guideline

## Purpose

Keep data portability on Filament-native import/export classes and already-created schema fields.

## Preferred architecture

Native Filament Importer/Exporter classes with portable reference keys and failed-row behavior.

## Do

- Extend existing import/export classes.
- Import transcripts into `Transcription`.
- Use category slugs/paths and typed tag slugs.
- Import/export only fields created by Prompts 07-09.
- Export large Markdown fields disabled by default.
- Preserve formula-injection protection.
- Preserve Prompt 10's completed native Filament importer/exporter baseline when later prompts touch related models.
- Keep `transcript_file` support deferred unless a safe, documented import package structure is approved and tested.
- Keep day-first import/export presentation for dates and date-times, using `Asia/Jerusalem` for UI/export date-time presentation.

## Do not

- Do not build custom CSV controllers.
- Do not export numeric database IDs as portable identifiers.
- Do not fetch remote media during imports.
- Do not write transcript imports to the legacy item transcript field.
- Do not silently create missing categories or tags during content imports.
- Do not attach unscoped, disabled-public, or wrong-type tags unless an active spec explicitly allows it and tests cover the behavior.

## Testing rules

- Create/update imports.
- Relationship resolution.
- Failed rows.
- Export columns.
- Authorization.
- Date parsing/export formatting.
- Legacy transcript field non-regression.

## Security rules

- Validate all imported values.
- Keep failed-row download authorization.
- Escape spreadsheet formula values.
- Treat missing relationship references as row failures by default.
- Use portable identifiers only: reference keys, slugs/paths, and typed tag slugs.

## FilaCheck / FilaCheck Pro notes

- Use `Filament\Actions\ImportAction`, `ExportAction`, and `ExportBulkAction`.
- Bulk export should deselect records after completion where supported.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields should auto-generate from title/name fields in admin forms but allow manual override.
- Technical import/export fields such as reference keys, provider IDs, external IDs, metadata, and file references must have helper text, hints, or descriptions.
- Date/date-time UI and import/export presentation should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available import/export editorial metrics when useful and avoid polling unless needed.

## Related active docs

- `docs/phase-02/import-export-revision-spec.md`
- `docs/phase-02/blueprints/10-import-export-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
