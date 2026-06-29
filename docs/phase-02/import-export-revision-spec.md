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

## Matching Rules

- `ContentGroup`: `reference_key`, then slug.
- `ContentItem`: `reference_key`, then provider/external ID, then group plus slug.
- `Author`: `reference_key`, then slug/name.
- `Transcription`: `reference_key`, then item key plus author key plus published date, with content hash fallback only if required.
- Category: slug/path.
- Tag: Spatie tag slug plus `content` type.

Missing category or tag references must create failed rows by default. Do not silently create categories, create tags, or attach unknown tag types during item imports unless a later prompt explicitly adds an admin-only create-if-missing option with tests.

## Transcript Files

Imports may support approved `.md`/`.txt` transcript file references. Imported transcript content creates or updates `Transcription` records. It must not write to legacy `ContentItem::transcript_markdown`.

## Security

- Continue formula-injection protection.
- Continue failed-row output.
- Treat missing categories, missing tags, disabled public tags, and wrong tag types as validation failures with failed-row output.
- Do not fetch remote covers/media.
- Use portable identifiers, not numeric IDs.

## Blueprint

See `docs/phase-02/blueprints/10-import-export-blueprint.md`.
