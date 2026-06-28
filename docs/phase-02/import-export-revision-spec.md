# Phase 02 Import/Export Revision Spec

## Principle

Keep native Filament Importer/Exporter classes and extend the current reference-key strategy. Do not build custom CSV controllers.

## Matching Rules

- `ContentGroup`: `reference_key`, then slug.
- `ContentItem`: `reference_key`, then provider/external ID, then group plus slug.
- `Author`: `reference_key`, then slug/name.
- `Category`: slug/path.
- Tag: Spatie tag slug plus `content` type.
- `Transcription`: `reference_key`, then item plus author plus published date, with content hash fallback only if required.

## Transcripts

CSV may include inline transcript Markdown or references to `.md`/`.txt` files in an approved import package. Imports must create/update `Transcription` records, not write transcript text directly onto `ContentItem`.

## Export

Exports should use portable identifiers and avoid numeric database IDs. Include selectable columns for:

- groups
- items
- authors
- transcriptions
- categories
- tags
- media metadata
- publication state
- pinning fields

## Security

Preserve formula-injection protection and validation. Do not fetch remote covers/media during imports.

## Tests Required Later

- Create/update import for transcriptions.
- Relationship resolution by reference keys.
- Category/tag import behavior.
- Failed row behavior.
- Export column coverage and authorization.
