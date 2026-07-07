# Step 10R-M1 MCP Research - Multi-Transcriber Schema and Model Foundation

## Mini-Step

Step 10R-M1 - Multi-transcriber schema and model foundation.

## Access Level

- Laravel Boost MCP: available. Used `application_info`, `database_schema`, `database_query`, and `search_docs`.
- FilamentExamples MCP: available through `search_examples` only. Access level was search/snippet only; no source/read/fetch/details tool was exposed.

## Laravel Boost Findings

### Application Info

Boost reported:

- PHP 8.4
- Laravel 13.18.0
- Filament 5.6.7
- Livewire 4.3.3
- Pest 4.7.4
- Tailwind CSS 4.3.2
- SQLite database

### Schema Findings

Boost schema inspection confirmed:

- `author_content_item` exists with `id`, `author_id`, and `content_item_id`.
- `author_content_item` has a unique index on `(author_id, content_item_id)` and cascade delete foreign keys.
- `transcriptions.author_id` is nullable and foreign-keyed to `authors.id` with null-on-delete behavior.
- `transcriptions` has no multi-author pivot yet.
- `authors` has stable `reference_key`, `name`, `slug`, and optional `bio_markdown`.

Boost `database_query` returned the current local data counts:

- `transcriptions`: 8 rows.
- `transcriptions` with non-null `author_id`: 8 rows.
- `author_content_item`: 13 rows.

M1 will backfill the new transcription pivot from the 8 `transcriptions.author_id` rows. The 13 legacy item-author pivot rows are not removed in M1; removal and data handling belong to M2.

### Documentation Findings

Boost `search_docs` was run for:

- `Laravel 13 belongsToMany pivot table withPivot orderByPivot sync unique pivot`
- `Laravel 13 migration create pivot table foreignId constrained cascadeOnDelete unique index timestamps`
- `Laravel 13 Eloquent model events saved sync relationship saveQuietly`
- `Pest Laravel test migration schema hasTable assert database pivot unique`

Useful installed-version guidance:

- Use `belongsToMany()` with `withPivot()` and `orderByPivot()` for ordered pivot data.
- Use `foreignId()->constrained()->cascadeOnDelete()` for pivot foreign keys.
- Use compound unique indexes to prevent duplicate pivot pairs.
- Use `saveQuietly()` when synchronizing compatibility columns from relationship helpers to avoid recursive model events.
- Laravel 13 supports transactional many-to-many methods such as `syncOrFail`, but M1 can use normal `sync` because failures should surface during tests.

## FilamentExamples MCP Findings

### First Query Batch

Queries:

- `many to many relationship form`
- `multi select relationship field`
- `relation manager attach authors`
- `belongsToMany pivot order`

Relevant snippet patterns:

- `v4/full-projects/classifieds-front-page-bootstrap/.../CompanyForm.php` showed `Select::make('categories')->multiple()->relationship('categories', 'name')`.
- `v4/forms/form-custom-fields/.../CustomerForm.php` showed relationship repeaters and a custom pivot model pattern.
- `v4/forms/quote-form-with-custom-table-field-and-product-picker-modal/.../QuoteProductsField.php` showed explicit relationship state loading/saving when relationship state needs controlled persistence.

### Refined Query Batch

Queries:

- `Select multiple relationship categories`
- `relationship repeater pivot model`
- `saveRelationshipsUsing belongsToMany`
- `SelectFilter relationship searchable preload`

Relevant snippet patterns:

- `Select::make(...)->multiple()->relationship(...)` is the expected simple Filament 5 relationship field shape for M2 admin UI.
- `SelectFilter::make(...)->relationship(...)->searchable()->preload()` remains the expected table filter shape when M2 converts filters from item authors to transcription transcribers.
- Relationship repeaters are available for nested relationship editing, but M1 does not require admin UI changes.

## PodText Source Findings

Inspected files:

- `app/Models/Transcription.php`
- `app/Models/Author.php`
- `app/Models/ContentItem.php`
- `database/factories/TranscriptionFactory.php`
- `database/factories/ContentItemFactory.php`
- `database/migrations/2026_06_26_041729_create_author_content_item_table.php`
- `database/migrations/2026_06_29_134855_create_transcriptions_table.php`
- `database/migrations/2026_06_29_134914_backfill_transcriptions_from_content_items_table.php`
- `tests/Feature/TranscriptionsModelTest.php`

Current model reality:

- `Transcription::author()` is the single compatibility relation.
- `TranscriptionFactory::forAuthor()` writes `author_id`.
- `Author::transcriptions()` is a `hasMany` through `transcriptions.author_id`.
- `ContentItem::authors()` and `Author::contentItems()` still exist and remain M2 removal scope.
- `Transcription::created()` already preserves first-transcription-auto-featured behavior by setting `content_items.featured_transcription_id` when appropriate.

## M1 Implementation Implications

- Add `author_transcription` as a new pivot table without changing or dropping `author_content_item`.
- Backfill from `transcriptions.author_id` during the migration.
- Add ordered `Transcription::authors()` and `Author::authoredTranscriptions()` relationships.
- Keep `Transcription::author()` and `Author::transcriptions()` for compatibility.
- Add helper methods on `Transcription` to synchronize ordered transcribers and maintain `author_id` as the first/primary compatibility transcriber.
- Tests should cover schema, backfill, duplicates, ordering, compatibility, helper output, and the existing first-featured behavior.

## Patterns To Avoid

- Do not migrate public rendering to the new relationship in M1.
- Do not remove `author_content_item` in M1.
- Do not remove `transcriptions.author_id`.
- Do not add roles unless current code needs them.
- Do not write MCP tokens or headers into tracked files.
