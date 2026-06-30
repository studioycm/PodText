# Phase 02 Import/Export Revision Spec

## Principle

Keep native Filament Importer/Exporter classes. Do not build custom CSV controllers.

## Dependency

Prompt 10 must run after:

- transcriptions model revision;
- category/tag schema;
- pinning fields;
- settings/media field foundation;
- admin management fields.

Prompt 10 preflight must confirm Prompts 08 and 09 are committed, the admin UX repair commit `16ab33a` is present, `docs/phase-02/spatie-tags-and-settings-decision.md` exists, and Prompt 10 has not already started.

## Current Baseline After Prompt 10

Prompt 10 is complete and committed as `fad6721 feat: extend phase two import export`.

- Native Filament importers/exporters are the authoritative import/export path.
- `TranscriptionImporter` and `TranscriptionExporter` exist; inline `transcript_markdown` import writes to `Transcription`, never to legacy `content_items.transcript_markdown`.
- `transcript_file` package support remains deferred because no approved import package structure is specified.
- Category import/export uses slug/path hierarchy.
- Content tag import/export uses Spatie `type = content` tags and fails missing, disabled-public, or wrong-type references by default.
- Content item import/export covers category paths, typed tag slugs, pin fields, media metadata, and featured transcription reference keys.
- Content group import/export covers category paths and homepage ordering where implemented.
- Later prompts should preserve this baseline unless their active blueprint explicitly requires a compatible change.

## Matching Rules

- `ContentGroup`: `reference_key`, then slug.
- `ContentItem`: `reference_key`, then provider/external ID, then group plus slug.
- `Author`: `reference_key`, then slug/name.
- `Transcription`: `reference_key`, then item key plus author key plus published date, with content hash fallback only if required.
- Category: slug/path.
- Tag: Spatie tag slug plus `content` type.

Missing category or tag references must create failed rows by default. Do not silently create categories, create tags, or attach unknown tag types during item imports unless a later prompt explicitly adds an admin-only create-if-missing option with tests.

## Transcript Files

- CSV may include a `transcript_file` column only if the blueprint defines the approved import package structure for locating those files.
- Allowed transcript file extensions are `.md` and `.txt`.
- Missing referenced transcript files fail the row.
- Imported transcript file content creates or updates `Transcription` records.
- Imported transcript file content must never write to legacy `ContentItem::transcript_markdown`.
- `transcript_markdown` CSV content, if supported, also creates or updates `Transcription` records and never writes to the legacy item field.
- The first imported transcription for an item may automatically set `content_items.featured_transcription_id` through existing model behavior; tests must account for this.
- When importing multiple transcriptions for one item, import `featured_transcription_id` or a featured transcription reference only when explicitly provided. Otherwise the existing first-transcription default behavior applies.

## Tags and Settings Boundaries

- Preserve the Spatie tags decision: use Spatie's `tags` table, `taggables` pivot, and type `content`.
- Do not create a custom `content_item_tag` pivot.
- Preserve `App\Models\ContentTag` only as the configured Spatie custom tag model for enabled/moderation fields on the normal Spatie `tags` table.
- Do not implement public consumption of `PublicContentSettings` or `HomepageSection` in Prompt 10; Prompt 11 owns that work.

## Date Handling

- Imported date fields should accept day-first `dd/mm/yyyy` where appropriate and normalize to Laravel date storage.
- Imported date-time fields should accept day-first `dd/mm/yyyy HH:mm` where appropriate.
- Exported date fields should use `dd/mm/yyyy`.
- Exported date-time fields should use `dd/mm/yyyy HH:mm`.
- UI/import/export timezone presentation should use `Asia/Jerusalem` unless a field is explicitly documented as timezone-neutral.

## Security

- Continue formula-injection protection.
- Continue failed-row output.
- Treat missing categories, missing tags, disabled public tags, and wrong tag types as validation failures with failed-row output.
- Missing categories fail the row by default.
- Missing tags fail the row by default unless a future import option explicitly creates disabled content tags with tests.
- Do not fetch remote covers/media.
- Use portable identifiers, not numeric IDs.

## Blueprint

See `docs/phase-02/blueprints/10-import-export-blueprint.md`.
