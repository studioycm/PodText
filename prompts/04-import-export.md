# Codex Prompt 04 — Native Import and Export

## Goal

Add administrator-only native Filament import/export for Authors, ContentGroups, and ContentItems.

## Required context

Read completely:

- `AGENTS.md`
- `docs/import-export-spec.md`
- Phase 4 in `docs/project-phases.md`

Inspect the actual models, Resource Tables, queue configuration, database notifications, Enum values, validation rules, and relationships before generating code.

Use Laravel Boost and current Filament 5 Import/Export documentation for exact APIs.

## Constraints

- Current checkout only; no worktrees.
- Use native Filament `ImportAction`, `ExportAction`, and `ExportBulkAction` where appropriate.
- Do not build a custom import screen.
- Do not perform remote network requests.
- Do not import covers or media files.
- Do not add a custom operations dashboard, audit log, retry UI, or notification channel.
- Do not bypass authorization or row validation.

## Implement

### 1. AuthorImporter and AuthorExporter

- Generate/configure native classes.
- Support create/update by `reference_key`.
- Generate a key for a new row when blank.
- Preserve existing key on update.
- Import name, slug, and biography Markdown.
- Add example CSV.
- Add Resource import/export actions.

### 2. ContentGroupImporter and ContentGroupExporter

- Support fields in the specification.
- Apply default display labels.
- Validate PublicationStatus using Enum values.
- Validate dates and language code.
- Do not fetch cover URLs.
- Configure sensible default export columns.
- Add example CSV.

### 3. ContentItemImporter and ContentItemExporter

- Resolve parent by `content_group_reference_key`.
- Resolve Authors from `author_reference_keys` separated by `|`.
- Fail rows with unresolved supplied relationships.
- Preserve current authors when the author column is not mapped.
- Define/test behavior for an intentionally mapped blank authors cell.
- Preserve multiline Markdown.
- Validate media and embed URLs consistently with Admin forms.
- Keep transcript disabled in default export column selection but selectable.
- Export group and author reference keys.
- Add example CSV.

### 4. Import modes and blank behavior

Implement only options that can be clearly explained and tested:

- create and update;
- create only;
- update only;
- safe blank-field behavior.

If Filament's current API makes one option disproportionately complex, keep the simpler safe behavior and document the limitation rather than creating custom infrastructure.

### 5. Limits, chunking, and queue

- Configure a safe maximum row count.
- Configure upload validation.
- Choose a chunk size suitable for long transcript rows.
- Use a finite retry/timeout policy supported by current Filament importer/exporter jobs.
- Use the existing database queue and completion notifications.
- Keep generated files appropriately protected.

### 6. Security

- Restrict all actions to authenticated administrators.
- Confirm failed-row/export downloads are owner-authorized according to current Filament behavior.
- Use current Filament formula-injection protection.
- Do not export secrets or raw embed HTML.
- Keep transcript export optional.

## Tests

Implement the test matrix in `docs/import-export-spec.md`.

At minimum:

- each model imports a new record;
- each model updates by reference key;
- omitted new key is generated;
- group defaults apply;
- item group relationship resolves;
- multiple authors resolve;
- unresolved relationships fail;
- invalid status fails;
- invalid embed fails;
- multiline Hebrew Markdown survives;
- valid rows continue when another row fails;
- expected export columns exist;
- transcript is not selected by default where testable;
- guest/public access is denied;
- export/failed-row ownership is protected.

## Manual round trip

With the queue worker running:

1. download all example CSV files;
2. import Authors;
3. import ContentGroups;
4. import ContentItems;
5. inspect completion notifications and failed rows;
6. open imported published content publicly;
7. export all three record types;
8. safely modify one field in each export;
9. re-import using reference keys;
10. verify updates and relationships.

## Completion checks

Run and report:

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

Also report the exact queue-worker command used and whether all queued imports/exports completed.

## Final report

Report importer/exporter classes, actions, formats, columns, matching rules, relationship behavior, limits, queue settings, tests, results, and known limitations.

Do not start Prompt 05.
