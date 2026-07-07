# Public Front v2 Step 10R-M1 Handoff

## Purpose

Add the multi-transcriber schema/model foundation while preserving the existing single `transcriptions.author_id` compatibility path.

## What Was Implemented

- Added the `author_transcription` pivot table.
- Backfilled `author_transcription` from existing non-null `transcriptions.author_id` rows.
- Added ordered multi-transcriber relationships and helpers on `Transcription`.
- Added the inverse ordered multi-transcriber relationship on `Author`.
- Kept `transcriptions.author_id` synchronized to the first/primary transcriber when using the new helper or when legacy creation sets `author_id`.
- Preserved first-transcription-auto-featured behavior.
- Updated contributor UX tests to match follow-up commit `549b331`, where the old contributor transcription-title list was removed from item cards to prevent overflow.

## Files Changed

- `database/migrations/2026_07_08_000000_create_author_transcription_table.php`
- `app/Models/Transcription.php`
- `app/Models/Author.php`
- `tests/Feature/TranscriptionsModelTest.php`
- `tests/Feature/PublicContributorsTopTranscribersUxTest.php`
- `docs/research/public-front-v2/17-step10r-m1-multi-transcriber-schema-model-foundation-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-m1-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-m1-handoff.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/current-project-state.md`

## Migrations / Schema

New table: `author_transcription`.

Columns:

- `id`
- `author_id`
- `transcription_id`
- `sort_order`
- `created_at`
- `updated_at`

Constraints/indexes:

- Foreign key `author_id` references `authors.id` and cascades on delete.
- Foreign key `transcription_id` references `transcriptions.id` and cascades on delete.
- Unique `(author_id, transcription_id)`.
- Indexes on `author_id`, `transcription_id`, and `(transcription_id, sort_order)`.

Local backfill result:

- 8 `transcriptions.author_id` rows existed.
- 8 `author_transcription` rows were created after `php artisan migrate`.

## Final Model Relationships

`Transcription`:

- `author()` remains the compatibility primary transcriber relation through `transcriptions.author_id`.
- `authors()` is the ordered many-to-many transcriber relation through `author_transcription`.
- `syncTranscribers(iterable $authors)` syncs ordered transcribers and updates `author_id` to the first transcriber.
- `primaryTranscriber()` returns the first ordered pivot author, falling back to `author()`.
- `primaryAuthor()` aliases `primaryTranscriber()`.
- `transcriberNames()` returns ordered transcriber names, falling back to the compatibility author.

`Author`:

- `transcriptions()` remains the legacy compatibility has-many relation through `transcriptions.author_id`.
- `authoredTranscriptions()` is the ordered many-to-many relation through `author_transcription`.

## Removed Relationships / Tables

None in M1.

`author_content_item`, `ContentItem::authors()`, and `Author::contentItems()` intentionally remain for Step 10R-M2.

## Admin Behavior

No admin UI behavior changed in M1.

M2 owns transcription multi-select fields, relation-manager conversion, item-author removal from resources, and filters.

## Import / Export Behavior

No import/export behavior changed in M1.

M2 owns multi-transcriber import/export columns and legacy item-author import/export removal.

## Public Query / Policy Behavior

No public query or transcription policy behavior changed in M1.

M3 owns public transcription selection/count policy and aggregate query helpers.

## Card / Template / Rendering Behavior

No card-template rendering behavior changed in M1.

The only public-facing test adjustment records the already-committed B3 follow-up: contributor item cards no longer render the overflow transcription-title list removed in `549b331`.

## Settings / Schema Changes

No Spatie Settings or public JSON settings changed in M1.

## Tests Added / Updated

Updated `tests/Feature/TranscriptionsModelTest.php` to cover:

- Pivot table schema.
- Migration backfill from existing `transcriptions.author_id`.
- Multiple ordered transcribers on one transcription.
- Duplicate pivot prevention.
- Compatibility `author_id` synchronization.
- Existing `Transcription::author()` compatibility.
- First-transcription-auto-featured behavior.
- Ordered `transcriberNames()`.

Updated `tests/Feature/PublicContributorsTopTranscribersUxTest.php` to assert removed overflow transcription-title snippets stay absent.

## Security / Fallback Behavior

- No public exposure of `User` records.
- No new public rendering path.
- No raw classes, HTML, CSS, Blade paths, or unsafe JSON settings.
- `transcriptions.author_id` remains a compatibility fallback while future steps migrate admin/public behavior to the many-to-many relationship.

## Effect On Later Mini-Steps

- M2 can now replace episode authors with transcription transcribers using `Transcription::authors()`.
- M3 can build public transcription policy/counts on top of the many-to-many relationship.
- M4 can render item/group/contributor transcribers from selected/effective transcriptions.
- M5 can add grouped card parts, labels, and icons without revisiting schema.
- B4 remains paused until M1-M6 are complete.

## Open Questions

- M2 must inspect and handle the 13 local `author_content_item` rows before dropping the old pivot.
- M2 must decide how admin/import/export surfaces name the compatibility `author_id` versus the new ordered transcribers field.
- A later cleanup may remove `transcriptions.author_id`, but not during M1-M6 unless explicitly approved.

## Quality Gate Summary

Commands run:

- `php artisan migrate` - passed; created and backfilled `author_transcription`.
- `php artisan test tests/Feature/TranscriptionsModelTest.php` - passed, 10 tests / 38 assertions.
- `php artisan test tests/Feature/PublicContributorsTopTranscribersUxTest.php` - passed, 6 tests / 101 assertions.
- `vendor/bin/pint --dirty --format agent` - first run fixed import ordering, final run passed.
- `php artisan test` - passed, 241 tests / 2021 assertions.
- `vendor/bin/pint --test` - passed.
- `vendor/bin/filacheck` - passed, 0 issues.
- `npm run build` - passed.
- `git diff --check` - passed.

## Commit Hash

This commit: `feat: add multi-transcriber relationship foundation`.

The final report for this run records the exact Git hash because the hash cannot be embedded in its own committed file without becoming stale.
