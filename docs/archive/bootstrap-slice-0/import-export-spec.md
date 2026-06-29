# Import and Export Specification — Bootstrap Slice 0

## Objective

Provide administrator-only import/export for:

- authors;
- content groups, displayed by default as podcasts;
- content items, displayed by default as episodes.

Use Filament 5 native importer/exporter actions. Do not build a custom import wizard.

## Supported formats

### Import

- CSV only.
- UTF-8 input.
- Standard CSV quoting must preserve Hebrew, Hebrew diacritics, commas, Markdown, and multiline transcript cells.

### Export

- CSV.
- XLSX where supported by Filament's native Export Action.

## Execution model

Filament native imports and exports run through queued job batches and database notifications.

Required foundation:

```text
database queue connection
jobs table when absent
job_batches table
notifications table
Filament import/export support tables
Admin-panel database notifications
queue worker during local and production operation
```

No custom operations dashboard is required in Slice 0.

## Stable identity

Every `Author`, `ContentGroup`, and `ContentItem` has a unique immutable `reference_key`.

Recommended representation:

```text
ULID-compatible string
```

Importer behavior:

1. When a valid `reference_key` matches an existing record, update that record.
2. When a `reference_key` does not match, create a new record using that key.
3. When a new-row key is blank, generate a new key.
4. Never replace the reference key of an existing matched record.
5. A duplicate reference key within one import must fail affected rows.

Slug fallback may be used only as an explicit import option and must not be the default update identity.

## Import order

For relationship resolution, the expected order is:

1. Authors.
2. Content groups.
3. Content items.

The UI and example files should make this order clear.

## Author import

### Suggested headers

```csv
reference_key,name,slug,bio_markdown
```

### Rules

- `reference_key`: nullable for new records; valid unique key when supplied.
- `name`: required; maximum appropriate database length.
- `slug`: optional; generated from name when blank; unique.
- `bio_markdown`: optional; preserve multiline Markdown.

### Update matching

Use `reference_key`.

### Export columns

- reference key;
- name;
- slug;
- biography Markdown;
- created timestamp;
- updated timestamp.

## Content group import

### Suggested headers

```csv
reference_key,title,slug,group_type_label_singular,group_type_label_plural,default_item_type_label_singular,default_item_type_label_plural,description_markdown,original_language_code,status,published_at
```

### Rules

- `reference_key`: nullable for new records; valid and unique when supplied.
- `title`: required.
- `slug`: optional; generated from title when blank; globally unique for public routing.
- `group_type_label_singular`: optional; defaults to `Podcast`.
- `group_type_label_plural`: optional; defaults to `Podcasts`.
- `default_item_type_label_singular`: optional; defaults to `Episode`.
- `default_item_type_label_plural`: optional; defaults to `Episodes`.
- `description_markdown`: optional.
- `original_language_code`: optional; defaults to `he`.
- `status`: accepts only the backed values of `PublicationStatus`.
- `published_at`: nullable date-time; validate format.

### Cover handling

Cover files are not imported.

- Do not fetch a cover URL.
- Do not interpret local filesystem paths from untrusted CSV data.
- Administrators upload covers through the Resource form.
- Export may include a stored cover path only if clearly labeled as informational and disabled by default.

### Export columns

Enable by default:

- reference key;
- title;
- slug;
- type labels;
- original language code;
- status;
- publication timestamp.

Disable by default but make selectable:

- description Markdown;
- cover path;
- created timestamp;
- updated timestamp.

## Content item import

### Suggested headers

```csv
reference_key,content_group_reference_key,title,slug,type_label_singular_override,description_markdown,media_url,embed_url,duration_seconds,transcript_markdown,original_published_at,status,published_at,author_reference_keys
```

### Relationship delimiter

Use `|` between multiple author reference keys:

```text
01JAAA...|01JBBB...|01JCCC...
```

This avoids conflict with ordinary commas in names or content.

### Rules

- `reference_key`: nullable for new records; valid and unique when supplied.
- `content_group_reference_key`: required; must resolve one existing content group.
- `title`: required.
- `slug`: optional; generate when blank; unique within the parent group.
- `type_label_singular_override`: optional.
- `description_markdown`: optional.
- `media_url`: required valid URL under the field policy selected in implementation.
- `embed_url`: optional HTTPS URL that passes the approved-host rule.
- `duration_seconds`: optional non-negative integer.
- `transcript_markdown`: optional during initial drafting, but required before a record may be considered complete for publication if that rule is accepted during implementation.
- `original_published_at`: optional date-time.
- `status`: accepts only publication Enum backed values.
- `published_at`: optional date-time.
- `author_reference_keys`: optional `|`-delimited list; every supplied key must resolve.

### Relationship resolution

- Resolve `content_group_reference_key` against `ContentGroup.reference_key`.
- Resolve all `author_reference_keys` against `Author.reference_key`.
- If any supplied relationship cannot resolve, fail the row.
- Do not silently create authors or groups from the item importer.
- Sync the item-author relationship to the supplied keys on update when the column is mapped.
- When the authors column is not mapped, preserve existing authors.
- When the mapped authors cell is intentionally blank, define and test whether it clears authors; the UI should communicate this behavior.

### Transcript handling

- Preserve Markdown exactly except for line-ending normalization if required.
- Support quoted multiline CSV cells.
- Do not sanitize or transform the stored Markdown during import.
- Sanitize only when rendering publicly.
- Apply a configurable maximum size to prevent accidental oversized rows.

### Export columns

Enable by default:

- reference key;
- parent group reference key;
- title;
- slug;
- effective/override type information;
- media URL;
- embed URL;
- duration seconds;
- original publication timestamp;
- status;
- publication timestamp;
- author reference keys.

Disable by default but make selectable:

- description Markdown;
- transcript Markdown;
- created timestamp;
- updated timestamp.

The transcript should be disabled by default because it can make exports very large.

## Import options

Each importer should expose only useful, explicit options.

Recommended options:

```text
Mode:
- create and update (default)
- create only
- update only

Blank mapped fields on update:
- preserve existing value (safe default)
- overwrite with null/empty value
```

Do not add an option that bypasses validation.

## Row failures

Use Filament's standard failed-row behavior.

A failed-row file should include clear errors for:

- invalid status;
- invalid or duplicate reference key;
- unresolved group;
- unresolved author;
- duplicate slug;
- invalid URL;
- unapproved embed host;
- invalid date;
- negative duration;
- transcript exceeding the configured limit.

Valid rows should continue importing when other rows fail.

## Example CSV downloads

Enable Filament's example CSV behavior for each importer.

The examples should:

- include exact supported headers;
- use valid Enum values;
- show UTF-8 Hebrew content;
- show a multiline Markdown field;
- show item relationship keys;
- show multiple author keys separated by `|`.

Do not put real user data into example files.

## Notifications

Enable Admin-panel database notifications for import/export completion.

Completion notification should report:

- total rows processed;
- successful rows;
- failed rows;
- availability of the failed-row file;
- export file availability.

Do not add email notification delivery in Slice 0.

## Authorization

Only authenticated administrators may:

- trigger an import;
- trigger an export;
- view import/export completion notifications;
- download generated files;
- download failed-row files.

Do not expose import/export actions in the Public panel.

## Security requirements

- Use Filament's current per-record authorization hooks.
- Limit maximum import rows.
- Limit maximum upload size.
- Validate MIME type and extension according to current Filament guidance.
- Prevent CSV formula injection in exported and failed-row data.
- Do not export credentials, internal tokens, filesystem secrets, or arbitrary HTML.
- Treat Markdown as untrusted content.
- Do not perform network requests from importer row processing.
- Use a private or controlled storage disk for generated import/export files as appropriate to the current Filament implementation.
- Ensure one administrator cannot download another administrator's export if the framework's default policy requires explicit ownership checks.

## Queue behavior for Slice 0

Use conservative queue settings:

- dedicated queue name such as `imports-exports` when straightforward;
- finite attempts;
- explicit timeout appropriate for CSV batches;
- reasonable backoff;
- chunk size small enough for transcript rows;
- no infinite retries.

Do not build custom rerun/replay controls yet. Native failed-row re-import is sufficient for Slice 0.

## Required tests

### Authors

- imports a new author;
- updates an existing author by reference key;
- generates a reference key when omitted;
- rejects duplicate or invalid keys;
- exports expected columns.

### Content groups

- imports defaults for blank type labels;
- updates an existing group;
- rejects invalid publication status;
- preserves Hebrew Markdown;
- exports expected columns.

### Content items

- imports a new item and resolves its parent group;
- attaches multiple authors by reference key;
- updates an existing item by reference key;
- preserves authors when the authors column is not mapped;
- rejects unresolved relationships;
- rejects unapproved embed URLs;
- preserves multiline transcript Markdown;
- excludes transcript from default export selection;
- exports relationship reference keys.

### Security and authorization

- guest cannot import or export;
- unauthenticated admin routes redirect or deny;
- non-owner export access is denied when applicable;
- dangerous spreadsheet-leading values are safely exported according to Filament's security handling.
