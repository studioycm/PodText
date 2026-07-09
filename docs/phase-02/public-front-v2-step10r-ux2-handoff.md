# Public Front v2 10R-UX2 Handoff

## Purpose

Step 10R-UX2 adds a shared admin action for editing the resolved transcription directly
from episode list surfaces, without changing public transcription visibility.

## What Was Implemented

- Added `EditEffectiveTranscriptionAction` as one reusable Filament table action.
- Mounted the action on the Episodes resource table and the podcast Episodes relation
  manager.
- Implemented the documented admin fallback policy:
  - effective published transcription;
  - current featured transcription even if unpublished;
  - latest transcription by id.
- Hid the action only when the episode has zero transcriptions.
- Reused the existing `TranscriptionForm` schema layout without `content_item_id`.
- Swapped the transcriber field to an options-backed select inside the cross-model modal
  because the mounted row remains a `ContentItem`.
- Saved edited transcription data through `Transcription::syncTranscribers()` so pivot
  order and `transcriptions.author_id` compatibility stay synchronized.
- Added a modal footer action linking to the full transcription edit Resource.
- Added an optional context column on both episode list surfaces showing resolved
  transcription title/status from already eager-loaded relations.
- Amended the Step 10R ledger and sequence docs to the v4 order with AX1-AX3 scheduled.

## Files Changed

- `app/Filament/Actions/EditEffectiveTranscriptionAction.php`
- `app/Filament/Resources/ContentItems/Tables/ContentItemsTable.php`
- `app/Filament/Resources/ContentGroups/RelationManagers/ContentItemsRelationManager.php`
- `app/Filament/Resources/Transcriptions/Schemas/TranscriptionForm.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `tests/Feature/AdminPhase02ResourcesTest.php`
- `docs/research/public-front-v2/20-step10r-ux2-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-ux2-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-ux2-handoff.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/phase-02/current-project-state.md`

## Tests Added Or Updated

Updated `tests/Feature/AdminPhase02ResourcesTest.php` with:

- action visibility on both episode list surfaces;
- hidden action when an episode has zero transcriptions;
- effective-published, featured-unpublished, and latest-only draft fallback fixtures;
- modal title/status assertions;
- modal save assertions for title, status, body, transcriber IDs, pivot order, and
  `author_id` compatibility;
- context-column state assertion.

`tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php` remains the bounded public
query-count harness for public rendering regressions.

## Exceptions / Notes

- UX2 intentionally does not install `gsap`; AX1 owns that dependency.
- The admin fallback policy is broader than public visibility on purpose. Unpublished
  featured/latest transcriptions can be edited by admins without becoming public.
- The action verifies the current `featured_transcription_id` before using a loaded
  featured relation, so stale model relations do not mask the latest-only fallback.
- Public pages have no intended visual change in this step.

## Quality Gate

Focused tests:

```bash
php artisan test tests/Feature/AdminPhase02ResourcesTest.php --filter="effective transcription edit action|fallback tiers"
php artisan test tests/Feature/AdminPhase02ResourcesTest.php
php artisan test tests/Feature/AdminResourcesTest.php
php artisan test tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php
```

Results:

- focused UX2 action tests: 2 passed, 51 assertions;
- `AdminPhase02ResourcesTest`: 19 passed, 335 assertions;
- `AdminResourcesTest`: 12 passed, 151 assertions;
- `PublicFrontMultiTranscriptionRenderingTest`: 7 passed, 64 assertions.

Final gate:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```

Results:

- `vendor/bin/pint --dirty --format agent`: passed;
- `php artisan test`: 290 passed, 2704 assertions;
- `vendor/bin/pint --test`: passed;
- `vendor/bin/filacheck`: passed, 0 issues;
- `npm run build`: passed;
- `git diff --check`: passed.

## Commit hash

Commit message: `feat: add effective transcription edit action to episode lists`.

The exact commit hash is created after the final gate and lands in the final report.

## Local Front Check Report

1. Open `/admin/content-items` in Hebrew/RTL. Confirm rows with at least one
   transcription show the new edit-transcription action before the row columns and a
   transcription context badge with title/status. Rows with zero transcriptions should
   not show the action.
2. On `/admin/content-items`, click the edit-transcription action for an episode whose
   featured transcription is published. The modal heading should include that
   transcription title and the published status marker. Edit title/status/body and
   transcribers, save, and confirm the row context updates after refresh.
3. On `/admin/content-items`, click the same action for an episode with only an
   unpublished featured transcription. The modal should still open, show the draft status
   marker, and save the selected transcription rather than creating a new one.
4. Open `/admin/content-groups/{record}/edit`, choose the Episodes relation tab, and
   repeat the edit action from the podcast-scoped episode list. Expected behavior matches
   the full Episodes resource table.
5. From either modal, click the footer link to the full Resource. It should navigate to
   `/admin/transcriptions/{record}/edit` for the same resolved transcription.
6. Toggle the admin panel light/dark theme and re-open the modal. The UX1 global wide
   modal and full-width sections should remain intact, and the new action should not add
   custom theme-specific styling.
7. Public screens such as `/podcasts`, `/search`, and a public episode page should show
   no visible UX2 change. Public content remains Hebrew/RTL where content is Hebrew, and
   draft/unpublished transcriptions remain hidden publicly.

## Next Step

Step 10R-V1a is next: default/no-image fallback settings.
