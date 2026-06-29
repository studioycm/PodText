# Prompt 10 Blueprint: Import Export

## Commands

- `php artisan make:test --pest Phase02ImportExportTest --no-interaction`

## Native Filament Classes

Create or modify:

- `App\Filament\Imports\TranscriptionImporter`
- `App\Filament\Exports\TranscriptionExporter`
- `App\Filament\Imports\CategoryImporter`
- `App\Filament\Exports\CategoryExporter`
- extend `ContentItemImporter`
- extend `ContentItemExporter`
- extend `ContentGroupImporter`
- extend `ContentGroupExporter`

## Import Columns

Transcription importer:

- `reference_key`: nullable ULID, unique ignore current.
- `content_item_reference_key`: required, resolves item.
- `author_reference_key`: required, resolves author.
- `title`: nullable max 255.
- `language_code`: required max 10 default he.
- `transcript_markdown`: nullable unless transcript file path is blank.
- `transcript_file`: nullable `.md` or `.txt` approved import package reference.
- `status`: nullable enum.
- `published_at`: nullable date.

Category importer:

- `name`, `slug`, `parent_slug`, `is_visible`, `sort_order`, `description_markdown`.

Content item importer additions:

- pin fields.
- media metadata fields.
- category slugs.
- content tag slugs.
- featured transcription reference key after transcription rows exist.
- missing category or content tag references fail the row by default; do not silently create them.

## Export Columns

Use `Filament\Actions\Exports\ExportColumn`. Disable large Markdown fields by default.

Portable identifiers only:

- no numeric IDs;
- reference keys;
- slugs/path for categories;
- typed tag slugs.

## Actions

Use:

- `Filament\Actions\ImportAction`
- `Filament\Actions\ExportAction`
- `Filament\Actions\ExportBulkAction`

Bulk exports should use `->deselectRecordsAfterCompletion()` where available.

## Security

- Keep spreadsheet formula escaping.
- Keep failed-row output.
- Missing categories, missing tags, disabled public tags, and wrong tag types must become validation failures with failed rows.
- Do not fetch remote media/covers.
- Validate `.md`/`.txt` transcript file paths and size if file package support is implemented.

## Tests

- Create/update transcription imports.
- Relationship resolution.
- Failed rows.
- Category import.
- Typed tag import.
- Missing category/tag failed-row behavior.
- Content item import with pin/media/category/tag fields.
- Export columns.
- Bulk export.
- Authorization.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Prompt 06S Section Alignment

This alignment block preserves the implementation scope above while exposing the exact headings required by the active AI-context prompt.

## Goal

Implement only the prompt-specific objective described in this blueprint title and body.

## Dependencies

Complete prior prompts in sequence and read `AGENTS.md`, relevant specs, durable guidelines, and this blueprint before implementation.

## Models and migrations

Use the model and schema notes above. If this prompt is documentation-only, do not create migrations.

## Relationships and casts

Use the relationship, cast, and enum notes above; keep public visibility rules queryable and tested.

## Indexes and constraints

Add indexes, unique constraints, and foreign keys only for fields created in this prompt and queries described above.

## Filament Resources / Pages / Relation Managers / Actions

Use Filament 5 Resources, Pages, Actions, Importers, Exporters, or Widgets only where this prompt scope requires them.

## Public UI / Livewire / Blade where relevant

Use public Filament Pages, class-based Livewire, Blade components, and local Alpine only where this prompt scope requires public UI.

## Forms / tables / filters / actions

Use full Filament component namespaces, searchable relationship selects, useful filters, indicators, and Resource URL helpers.

## Import/export where relevant

Use native Filament import/export only for schema fields created by earlier prompts; never build custom CSV controllers.

## Settings/widgets where relevant

Use approved Spatie Settings for global options and simple editorial widgets only where this prompt scope requires them.

## Out of scope

Do not implement work assigned to later prompts, install unrelated packages, run migrations in planning tasks, or add speculative infrastructure.
