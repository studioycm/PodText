# Public Front v2 Step 10R-M2 Implementation Plan

## 1. Selected Mini-Step and Dependencies

Selected mini-step: Step 10R-M2 - Remove episode authors and convert admin/import/export to transcription transcribers.

Dependencies are met:

- Step 10R-M1 is complete at `800218a`.
- `author_transcription` exists and is backfilled from `transcriptions.author_id`.
- `transcriptions.author_id` remains compatibility/primary storage.
- B4 remains paused until M1-M6 are complete.

## 2. Current Local Repo Evidence

Preflight found a clean app-code tree with only the expected M2 in-progress doc changes:

- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

Local schema data:

- `author_content_item`: 13 rows, 7 items, 4 authors.
- `author_transcription`: 8 rows.
- `transcriptions.author_id is not null`: 8 rows.

The 13 old item-author rows are legacy demo/editorial credits. They will be dropped with the old table, not migrated to transcription transcribers.

## 3. Files Inspected

- `app/Models/Author.php`
- `app/Models/ContentItem.php`
- `app/Models/Transcription.php`
- `app/Filament/Resources/Transcriptions/Schemas/TranscriptionForm.php`
- `app/Filament/Resources/Transcriptions/Tables/TranscriptionsTable.php`
- `app/Filament/Resources/ContentItems/Schemas/ContentItemForm.php`
- `app/Filament/Resources/ContentItems/Tables/ContentItemsTable.php`
- `app/Filament/Resources/ContentItems/RelationManagers/TranscriptionsRelationManager.php`
- `app/Filament/Resources/ContentGroups/RelationManagers/ContentItemsRelationManager.php`
- `app/Filament/Imports/ContentItemImporter.php`
- `app/Filament/Exports/ContentItemExporter.php`
- `app/Filament/Imports/TranscriptionImporter.php`
- `app/Filament/Exports/TranscriptionExporter.php`
- `app/Livewire/Public/ContentItemSearch.php`
- `app/Support/PublicContent/PublicContentItemQueries.php`
- `app/Support/PublicContent/PublicContributorDiscovery.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `resources/views/components/public/public-filter-panel.blade.php`
- `resources/views/components/public/content-item-row.blade.php`
- `resources/views/filament/public/pages/show-content-item.blade.php`
- `resources/views/livewire/public/content-item-transcript-viewer.blade.php`
- `app/Filament/Public/Pages/ShowContentItem.php`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/DemoHebrewContentSeeder.php`
- `resources/import-examples/content-items.csv`
- Relevant feature tests for admin resources, import/export, public search, item pages, and domain foundations.

## 4. Laravel Boost Findings

Boost was used for installed app info, schema, data queries, and version-aware docs.

Key findings:

- Laravel migrations can safely drop the legacy pivot in a new migration and recreate it in `down()`.
- Eloquent ordered `belongsToMany` relationships and pivot metadata are supported.
- Filament 5 supports multiple relationship selects, searchable/preloaded filters, and relationship-state load/save hooks.
- Filament import/export should remain inside native Importer/Exporter classes.
- Livewire URL aliases can preserve the old `author` query parameter while the internal property becomes transcriber-based.

## 5. FilamentExamples MCP Findings

Access level: search/snippet only.

Query batches:

- `multiple relationship select`
- `relation manager belongsToMany form`
- `Select multiple relationship create option`
- `table filter relationship searchable preload`
- `saveRelationshipsUsing belongsToMany`
- `importer relationship resolveUsing`
- `export multiple relationship names`
- `AttachAction relation manager pivot`

Useful patterns:

- Multiple relationship select: `Select::make(...)->multiple()->relationship(...)->searchable()->preload()`.
- Table filters: `SelectFilter::make(...)->relationship(...)->searchable()->preload()`.
- Controlled relationship state: custom form field using `loadStateFromRelationshipsUsing()` and `saveRelationshipsUsing()`.
- Import/export examples keep resolution and formatting inside Filament classes.

## 6. Old/Front-Card Leftovers Found

Public card presentation still maps `authors` from `$item->authors` and `transcriber_line` uses that data. Public row cards and item page headers also read `$item->authors`.

M2 will change these to effective transcription transcribers. Broader card-template source expansion and policy-aware public rendering remain M4/M5.

## 7. Current Model/Relationship Reality

Current before implementation:

- `ContentItem::authors()` exists through `author_content_item`.
- `Author::contentItems()` exists through `author_content_item`.
- `Transcription::author()` remains a compatibility single primary transcriber relation.
- `Transcription::authors()` is the ordered many-to-many source for transcribers.
- `Author::authoredTranscriptions()` exists.
- `Author::transcriptions()` remains the compatibility has-many through `transcriptions.author_id`.

Target after M2:

- Remove `ContentItem::authors()`.
- Remove `Author::contentItems()`.
- Drop `author_content_item`.
- Keep `Transcription::author()` and `transcriptions.author_id`.
- Use `Transcription::authors()` for multi-transcriber admin/import/export and public display touched in M2.

## 8. Settings/Render-Context Impact

No settings schema changes are planned in M2.

`PublicContentCardOptions::showAuthors` remains a legacy scalar display toggle, but M2 changes the data behind that card line from item authors to transcription transcribers. B4 will converge legacy option naming and template composition later.

## 9. Card-Template/Rendering Impact

M2 does not add new card-template part types or row/icon/label behavior.

M2 will update content-item card presenter data so the existing `transcriber_line` renders effective transcription transcribers. M4/M5 own the broader source/attribute expansion and grouped part rendering.

## 10. Livewire/Blade Impact

Planned changes:

- Rename internal public search state from `filterAuthorId` to a transcriber-focused property while preserving the `author` URL alias.
- Filter public items through published transcriptions and their `authors` pivot.
- Rename option data passed to the filter drawer.
- Update the filter label to “Transcriber”.
- Update row/item page/transcript viewer rendering to use transcription transcribers.

## 11. Admin/Import/Export Impact

Planned admin changes:

- Add a reusable transcriber select helper for transcription forms.
- Transcription create/edit form uses multiple transcribers and saves through `syncTranscribers()`.
- Content item transcriptions relation manager uses the same multi-transcriber field.
- Content item table add-transcription action accepts multiple transcribers and calls `syncTranscribers()` after creation.
- Content item form removes item authors.
- Content item tables and relation manager remove item-author columns/filters and optionally show effective transcription transcribers.
- Transcription table columns/filters use pivot transcribers.

Planned import/export changes:

- Remove `author_reference_keys` from content item import/export and sample CSV.
- Keep `author_reference_key` on transcription import/export as legacy primary compatibility.
- Add `primary_transcriber_reference_key`, `transcriber_reference_keys`, and `transcriber_names` to transcription import/export.
- Import multi-transcriber reference keys/names and call `syncTranscribers()`.
- Export ordered transcriber keys/names with spreadsheet formula escaping on names.

## 12. Query/Scopes/Aggregation Impact

M2 will update public search filtering and eager loads needed to avoid broken item-author reads.

Full public policy, aggregate counts, and contributor count pivot conversion are M3 scope. M2 can leave `PublicContributorDiscovery` count subqueries on `transcriptions.author_id` because the compatibility field is synchronized to the primary transcriber.

## 13. Episode-Author Removal Impact

M2 will remove the old episode author concept from app code.

Data handling:

- The 13 `author_content_item` rows will be dropped with the old pivot.
- They are not silently reclassified as transcribers.
- Transcriber data remains sourced from `author_transcription`, already backfilled from `transcriptions.author_id`.

## 14. Tests to Add/Update

Focused coverage:

- `author_content_item` table no longer exists after migrations.
- `ContentItem::authors()` is absent.
- `Author::contentItems()` is absent.
- Transcription resource form saves multiple transcribers and keeps `author_id` synchronized.
- Content item transcriptions relation manager saves multiple transcribers and keeps `author_id` synchronized.
- Content item admin no longer exposes an item-author form field.
- Content item import/export no longer include item-author columns.
- Transcription import accepts multiple transcriber keys/names and legacy `author_reference_key`.
- Transcription export includes primary and ordered transcriber keys/names.
- Public search transcriber filter uses transcription transcribers.
- Public item cards/pages do not render old item authors.

## 15. Exact Files to Change

Expected files:

- New migration under `database/migrations/`.
- `app/Models/Author.php`
- `app/Models/ContentItem.php`
- `app/Filament/Resources/Support/RelationshipOptionForms.php`
- `app/Filament/Resources/Transcriptions/Schemas/TranscriptionForm.php`
- `app/Filament/Resources/Transcriptions/Tables/TranscriptionsTable.php`
- `app/Filament/Resources/ContentItems/Schemas/ContentItemForm.php`
- `app/Filament/Resources/ContentItems/Tables/ContentItemsTable.php`
- `app/Filament/Resources/ContentItems/RelationManagers/TranscriptionsRelationManager.php`
- `app/Filament/Resources/ContentGroups/RelationManagers/ContentItemsRelationManager.php`
- `app/Filament/Imports/ContentItemImporter.php`
- `app/Filament/Exports/ContentItemExporter.php`
- `app/Filament/Imports/TranscriptionImporter.php`
- `app/Filament/Exports/TranscriptionExporter.php`
- `app/Livewire/Public/ContentItemSearch.php`
- `app/Support/PublicContent/PublicContentItemQueries.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Filament/Public/Pages/ShowContentItem.php`
- Public Blade files that still read `$item->authors`.
- Seeders and import examples.
- English/Hebrew translations.
- Focused feature tests.
- M2 research/plan/handoff/current-state/ledger docs.

## 16. Risks/Conflicts

- Large legacy tests may fail until all item-author assertions are updated.
- Filament action field names must match Livewire test state paths.
- Keeping public query parameter `author` avoids external URL churn, but internal labels should say transcriber.
- M3 will replace compatibility single-author count logic with policy/pivot aggregation; M2 must avoid overbuilding that now.

## 17. Out of Scope

- Public transcription policy service and aggregate modes.
- `featured_only` versus `all_published` settings.
- Card-template grouped parts, labels, and icons.
- Removing `transcriptions.author_id`.
- B4 card-options convergence.
- Step 2 publication workflow.

## 18. Stop Conditions

Stop before coding if:

- Migration cannot drop `author_content_item` safely in the current schema.
- Filament cannot save the multi-transcriber selector while keeping `author_id` synchronized.
- Tests show public rendering still depends on `ContentItem::authors()` after the relation is removed.
- Unexpected app-code dirt appears outside the M2 scope.
