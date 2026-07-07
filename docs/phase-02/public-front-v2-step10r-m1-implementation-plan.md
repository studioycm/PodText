# Public Front v2 Step 10R-M1 Implementation Plan

## 1. Selected Mini-Step and Dependencies

Selected mini-step: Step 10R-M1 - Multi-transcriber schema and model foundation.

Dependencies verified:

- Step 10R-A2 is complete: `d6d0bec`.
- Step 10R-B1 is complete: `34c6032`.
- Step 10R-B2 is complete: `e3c81de`.
- Step 10R-B3 is complete: `f712791`.
- Follow-up contributor grid overflow cleanup is complete: `549b331`.
- Step 10R-B4 is paused until M1-M6 are complete.
- Prompt 13 has not started.
- Step 11 has not started.
- Step 9F / 10F has not been implemented.

## 2. Current Local Repo Evidence

Preflight showed:

- Branch: `main` tracking `origin/main`.
- Working tree was clean before the required ledger/current-state correction.
- Current local `HEAD`: `549b331 refactor: remove unused contributor transcription list component from grid layout`.
- `php artisan migrate:status` showed all existing migrations as run.
- Public routes exist for `/podcasts`, `/contributors`, and `/search`.

Boost data counts:

- `transcriptions`: 8 rows.
- `transcriptions.author_id is not null`: 8 rows.
- `author_content_item`: 13 rows.

## 3. Files Inspected

- `app/Models/Transcription.php`
- `app/Models/Author.php`
- `app/Models/ContentItem.php`
- `database/factories/TranscriptionFactory.php`
- `database/factories/AuthorFactory.php`
- `database/factories/ContentItemFactory.php`
- `database/migrations/2026_06_26_041729_create_author_content_item_table.php`
- `database/migrations/2026_06_29_134855_create_transcriptions_table.php`
- `database/migrations/2026_06_29_134914_backfill_transcriptions_from_content_items_table.php`
- `tests/Feature/TranscriptionsModelTest.php`

## 4. Laravel Boost Findings

Boost was used through:

- `application_info`
- `database_schema`
- `database_query`
- `search_docs`

Findings:

- Installed versions are Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Tailwind CSS 4.3.2, PHP 8.4, and SQLite.
- `author_content_item` exists and has 13 local rows. M1 does not remove it.
- `transcriptions.author_id` is nullable, foreign-keyed to `authors.id`, and has 8 local populated rows.
- No `author_transcription` table exists.
- Laravel 13 docs support `belongsToMany()`, `withPivot()`, `orderByPivot()`, compound unique indexes, `foreignId()->constrained()->cascadeOnDelete()`, and `saveQuietly()`.

## 5. FilamentExamples MCP Findings

FilamentExamples was used with search/snippet-only access.

First batch:

- `many to many relationship form`
- `multi select relationship field`
- `relation manager attach authors`
- `belongsToMany pivot order`

Second pass:

- `Select multiple relationship categories`
- `relationship repeater pivot model`
- `saveRelationshipsUsing belongsToMany`
- `SelectFilter relationship searchable preload`

Useful patterns:

- `Select::make(...)->multiple()->relationship(...)` for M2 admin multi-transcriber fields.
- `SelectFilter::make(...)->relationship(...)->searchable()->preload()` for M2 filter conversions.
- Explicit relationship state loading/saving exists for custom fields, but M1 does not need Filament UI changes.

## 6. Old/Front-Card Leftovers Found

- `ContentItem::authors()` still exists and is still used by public card presentation code.
- `Author::contentItems()` still exists.
- `PublicContentItemCardPresenter` still has item-author-based presentation.
- These are intentionally not changed in M1. M2 removes the episode/content-item author relationship, and M4 migrates public rendering to transcription transcribers.

## 7. Current Model/Relationship Reality

- `Transcription::author()` is the single-author compatibility relation.
- `Author::transcriptions()` is a has-many relation through `transcriptions.author_id`.
- `TranscriptionFactory::forAuthor()` writes `author_id`.
- `Transcription::created()` already auto-selects the first transcription as `featured_transcription_id` when the item has no prior transcription.

## 8. Settings/Render-Context Impact

No settings or `PublicFrontRenderContext` changes in M1.

## 9. Card-Template/Rendering Impact

No card-template rendering changes in M1.

M1 only adds model helpers that later public renderers can use.

## 10. Livewire/Blade Impact

No Livewire or Blade changes in M1.

## 11. Admin/Import/Export Impact

No admin form, relation manager, importer, or exporter behavior changes in M1.

M2 will convert admin/import/export flows to transcription transcribers.

## 12. Query/Scopes/Aggregation Impact

No public query policy or aggregate changes in M1.

M3 owns public transcription policy, query scopes, and aggregate behavior.

## 13. Episode-Author Removal Impact

M1 intentionally leaves `author_content_item`, `ContentItem::authors()`, and `Author::contentItems()` in place.

The old pivot has 13 local rows. M2 must inspect and handle that data before dropping the table.

## 14. Tests To Add/Update

Update `tests/Feature/TranscriptionsModelTest.php` to cover:

- `author_transcription` table and columns exist.
- Migration backfills existing `transcriptions.author_id` rows into `author_transcription`.
- Multiple transcribers can attach to one transcription in ordered form.
- Duplicate `(author_id, transcription_id)` pivot pairs are rejected.
- `syncTranscribers()` updates the compatibility `author_id` to the first transcriber.
- `Transcription::author()` compatibility still works.
- Creating a transcription with `author_id` still creates the compatibility pivot entry.
- First transcription created for an item still becomes featured/main.
- `Transcription::transcriberNames()` returns multiple names in pivot order.

## 15. Exact Files To Change

Planned app/test files:

- `database/migrations/2026_07_08_000000_create_author_transcription_table.php`
- `app/Models/Transcription.php`
- `app/Models/Author.php`
- `tests/Feature/TranscriptionsModelTest.php`

Planned docs:

- `docs/research/public-front-v2/17-step10r-m1-multi-transcriber-schema-model-foundation-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-m1-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-m1-handoff.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/current-project-state.md`

## 16. Risks/Conflicts

- Model `saved` synchronization must not fail while tests temporarily drop/recreate `author_transcription`.
- `syncTranscribers()` must avoid recursive model events when updating `author_id`.
- Pivot ordering should be deterministic when sort orders tie.
- M1 must not create admin UI expectations that belong to M2.

## 17. Out Of Scope

- Dropping `author_content_item`.
- Removing `ContentItem::authors()` or `Author::contentItems()`.
- Updating admin forms/importers/exporters.
- Updating public cards, Livewire components, Blade, or presenter output.
- Adding public transcription policy settings.
- Adding card-template grouped parts, icons, or labels.
- Step 10R-B4, Step 9F, Step 11, or Prompt 13.

## 18. Stop Conditions

Stop before app-code changes if:

- A new contradiction appears showing B3 was not actually committed.
- The new migration cannot safely backfill from current `transcriptions.author_id`.
- Existing tests prove `author_id` cannot remain compatibility storage.
- M1 changes require dropping the legacy item-author pivot, which is M2 scope.
