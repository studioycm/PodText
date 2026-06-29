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

## Do not

- Do not build custom CSV controllers.
- Do not export numeric database IDs as portable identifiers.
- Do not fetch remote media during imports.
- Do not write transcript imports to the legacy item transcript field.

## Testing rules

- Create/update imports.
- Relationship resolution.
- Failed rows.
- Export columns.
- Authorization.

## Security rules

- Validate all imported values.
- Keep failed-row download authorization.
- Escape spreadsheet formula values.

## FilaCheck / FilaCheck Pro notes

- Use `Filament\Actions\ImportAction`, `ExportAction`, and `ExportBulkAction`.
- Bulk export should deselect records after completion where supported.

## Related active docs

- `docs/phase-02/import-export-revision-spec.md`
- `docs/phase-02/blueprints/10-import-export-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
