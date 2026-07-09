# Public Front v2 Step 10R-UX2 Implementation Plan

## Selected Step

Step 10R-UX2 - Effective transcription edit action on episode lists.

Dependencies satisfied: Step 10R-UX1 is complete as
`a88115f feat: standardize admin navigation tables and modals`. The v4 enhancement plan
is active, the ledger says UX2 is first pending, and Step 11 / Prompt 13 have not
started.

## Current Repo Evidence

- `git status --short --branch`: clean `main...origin/main`.
- `git log --oneline --decorate -20` includes UX1 commit `a88115f`.
- `php artisan migrate:status` reports database/settings migrations through
  `2026_07_09_000004_align_public_transcription_display_defaults` as ran.
- `docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md` is v4 and mentions
  requests 18-21 / steps AX1-AX3.
- `ContentItemsTable` and `ContentItemsRelationManager` are the two required mount
  points and already use Filament table row actions.
- `TranscriptionForm` is the existing canonical Resource form schema. UX2 will reuse its
  section layout through a component helper, omit `content_item_id`, and use an
  options-backed `transcriber_ids` select because the mounted table row record remains a
  `ContentItem`, not the edited `Transcription`.
- `Transcription::syncTranscribers()` is the existing canonical writer for ordered
  transcriber pivot rows plus `author_id` compatibility.
- The v4 ledger/sequence alignment has been applied in this run before implementation:
  AX1 after P3, AX2/AX3 after SL4, AX motion guardrails, and v4 sequence order.

## Files Inspected

- Runner and state docs required by the active prompt.
- `docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md`.
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`.
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`.
- `app/Filament/Resources/ContentItems/Tables/ContentItemsTable.php`.
- `app/Filament/Resources/ContentGroups/RelationManagers/ContentItemsRelationManager.php`.
- `app/Filament/Resources/ContentItems/RelationManagers/TranscriptionsRelationManager.php`.
- `app/Filament/Resources/Transcriptions/Schemas/TranscriptionForm.php`.
- `app/Filament/Resources/Transcriptions/TranscriptionResource.php`.
- `app/Models/ContentItem.php`.
- `app/Models/Transcription.php`.
- Admin translations and existing admin/public-front tests.

## Boost Findings

- Installed versions: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4,
  Tailwind 4.3.2, local SQLite.
- No schema migration is needed.
- Filament 5 table actions support `fillForm()`, modal `schema()`, `action()`, and
  `extraModalFooterActions()`.
- Filament action tests use `TestAction::make(...)->table($record)`,
  `mountAction()`, `assertSchemaStateSet()`, `assertMountedActionModalSee()`, and
  `callMountedAction()`.
- Custom action classes may extend `Filament\Actions\Action` and define
  `getDefaultName()` for reuse and test discovery.

## FilamentExamples Findings

Research note:
`docs/research/public-front-v2/20-step10r-ux2-mcp-research.md`.

Access was search/snippet only. Useful patterns were app-owned `Action` subclasses with
`getDefaultName()`, `fillForm()`, modal schema/action callbacks, and
`extraModalFooterActions()`, plus ordinary table row action mounting patterns.

## Resolution Tier Decision

UX2 uses this admin-edit fallback policy:

1. effective published transcription;
2. featured transcription even if unpublished;
3. latest transcription by id.

Evidence:

- Existing public resolution in `ContentItem::effectiveTranscription()` intentionally
  ignores unpublished featured transcriptions.
- UX2 must let admins edit the practical "main" transcription even when there is no
  public-effective transcription yet.
- Therefore the action reuses public effective resolution as tier 1, then falls back to
  admin-only featured/latest records without changing public visibility behavior.

The action is hidden only when the item has zero transcriptions.

## Settings / Render Context Impact

No settings keys, settings migrations, validators, render-context accessors, or public
rendering settings change in UX2.

## Admin / Public Impact

Admin:

- Episodes list gets an edit-effective-transcription row action.
- Podcast edit page's Episodes relation manager gets the same row action.
- The action modal edits the resolved `Transcription` while the row remains a
  `ContentItem`.
- The modal heading shows the resolved transcription title and status marker.
- The modal footer includes a link to the full transcription edit Resource.
- Both lists get a context column for effective transcription title/status using already
  eager-loaded transcription relations.

Public:

- No intended public behavior change.
- Public visibility and rendering tests remain regression coverage only.

## Query / Cache Impact

- No cache changes.
- The new context column must not perform relationship queries. It reads
  `featuredTranscription` and `latestPublishedTranscription` relations already loaded by
  both mount-point queries.
- The action may query when mounted/submitted to resolve and save the target
  transcription. That is admin-only interaction work, not list rendering work.

## Exact Files To Change

- Add `app/Filament/Actions/EditEffectiveTranscriptionAction.php`.
- Update `app/Filament/Resources/Transcriptions/Schemas/TranscriptionForm.php` with a
  reusable schema helper that can omit `content_item_id` and switch the transcriber
  select away from a relationship-bound field for cross-model action modals.
- Update `app/Filament/Resources/ContentItems/Tables/ContentItemsTable.php`.
- Update
  `app/Filament/Resources/ContentGroups/RelationManagers/ContentItemsRelationManager.php`.
- Update `lang/en/admin.php` and `lang/he/admin.php`.
- Update `tests/Feature/AdminPhase02ResourcesTest.php` with focused action coverage.
- Keep `tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php` green.
- Update UX2 docs, ledger, current state, and handoff after verification.

## Tests

- Action exists/visible on the Episodes resource table and podcast Episodes relation
  manager.
- Action hidden when the episode has zero transcriptions.
- All three resolution tiers:
  - effective published transcription;
  - featured unpublished transcription;
  - latest-only draft transcription by id.
- Modal form pre-fills from the resolved transcription.
- Modal heading contains resolved title and status marker.
- Save updates title, status, transcript body, and multi-transcriber selection.
- `author_transcription.sort_order` and `transcriptions.author_id` stay synchronized.
- Context column renders resolved public effective title/status without breaking the
  bounded public query-count harness.
- Existing admin suites remain green.

## Risks

- `TranscriptionForm` embeds `content_item_id` inside the identity section and normally
  uses a relationship-backed transcriber select; the helper must remove only the content
  item field for UX2, swap to an options-backed author select for the cross-model modal,
  and keep the standalone Resource unchanged.
- Filament action modal heading may render plain text rather than badge markup in tests;
  use title plus translated status marker so the visible modal content is testable and
  useful.
- The admin edit fallback policy is intentionally different from public-effective
  visibility; tests should make that distinction explicit.

## Out Of Scope

- Associate-existing transcription.
- Studio/autosave workflows.
- Public transcript behavior changes.
- Any V1/P/S/SL/AX/B/C/9F implementation.
- Installing GSAP. AX1 owns that dependency.

## Stop Conditions

- Stop if unexpected app-code dirt appears before coding.
- Stop if Filament action APIs from Boost are unavailable in the installed version.
- Stop if implementation would require public visibility rule changes or a schema
  migration.
- Stop if UX2 cannot be implemented as one shared action class mounted on both required
  surfaces.
