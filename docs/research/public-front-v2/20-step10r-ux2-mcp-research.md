# Public Front v2 Step 10R-UX2 MCP Research

Date: 09/07/2026

## Scope

Step 10R-UX2 adds one reusable Filament action for editing the resolved transcription
from episode lists: the Episodes resource table and the podcast Episodes relation
manager.

## Local Repository Evidence

- Preflight `git status --short --branch` reported clean `main...origin/main`.
- Recent history includes UX1 commit `a88115f feat: standardize admin navigation tables
  and modals`; `HEAD` is `127f0c6 docs: remove v3 admin/settings enhancement plan and
  adopt updated v4 plan`.
- `php artisan migrate:status` reports all migrations through
  `2026_07_09_000004_align_public_transcription_display_defaults` as ran.
- Public route preflight found `/podcasts`, `/podcasts/{contentGroupSlug}`,
  `/contributors`, `/contributors/{authorSlug}`, and `/search`.
- The v4 plan header is present in
  `docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md`.
- Before this run's docs patch, the ledger and sequence docs were v3-shaped: SL rows
  existed, AX rows were absent, and the first pending mini-step was UX2.
- `ContentItemsTable` and `ContentItemsRelationManager` already eager-load
  `featuredTranscription.authors` and `latestPublishedTranscription.authors`, which is
  enough for a zero-query context column showing the current public effective
  transcription.
- `TranscriptionForm` is the canonical standalone transcription schema. It contains
  `content_item_id`, transcriber select, title, language, transcript body, status, and
  published date fields.
- `Transcription::syncTranscribers()` is the canonical pivot sync path and keeps
  `transcriptions.author_id` compatible with the primary transcriber.

## Laravel Boost Findings

Tools used: `application_info`, `database_schema`, and `search_docs`.

- Boost confirmed installed versions: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3,
  Pest 4.7.4, Tailwind 4.3.2, local SQLite.
- Boost schema confirmed this mini-step needs no migration. Relevant tables are
  `content_items`, `transcriptions`, `authors`, and `author_transcription`.
- Filament table record actions use normal `Action` instances with `fillForm()`,
  `schema()`, and `action()` callbacks. The row `$record` can stay a `ContentItem` while
  the action callback updates a related `Transcription`.
- Filament action modals support `extraModalFooterActions()` for additional footer
  actions. A normal `Action::make(...)->url(...)` can be used for a full-resource link.
- Filament testing docs use `Filament\Actions\Testing\TestAction::make(...)->table($record)`
  for table row actions, including visibility/existence assertions.
- Filament testing docs use `mountAction()` before modal assertions,
  `assertMountedActionModalSee()` for modal content, `assertSchemaStateSet()` for
  prefilled modal form state, and `callMountedAction()`/`callAction()` with data for
  submission.
- Filament custom action classes can extend `Filament\Actions\Action` and define
  `getDefaultName()`, making the action reusable and discoverable in tests by name.

## FilamentExamples Findings

Access level: `search_examples` snippet/search access only. No separate source/read/fetch
tool was exposed.

Initial query batch:

- `edit related record action`
- `table action modal form`
- `action fill form relationship`

Refined query batch:

- `custom Action class fillForm modal`
- `extraModalFooterActions url action`
- `relation manager record action modal`

Relevant examples and PodText adaptation notes:

- **AI-Powered CMS With Laravel AI SDK**:
  - File/class: `app/Filament/Actions/SuggestTitleAction.php`,
    `GenerateFeaturedImageAction.php`, `TranslatePostAction.php`.
  - Pattern to copy: app-owned action classes extending `Action`, `getDefaultName()`,
    `setUp()`, `fillForm()`, `schema()`, `action()`, and `extraModalFooterActions()`.
  - Pattern to avoid: AI-specific state mutation and generated-output modal workflows.
  - PodText adaptation: create `EditEffectiveTranscriptionAction` as a reusable table row
    action that resolves a related `Transcription` from the `ContentItem` row.
- **Ecommerce Admin Panel**:
  - Snippet found: table record actions with modal forms and typed Eloquent `$record`
    updates.
  - Pattern to copy: simple row-action form that updates one model and sends a
    notification.
  - Pattern to avoid: unrelated inventory/domain service logic.
  - PodText adaptation: update only the resolved transcription, then notify and refresh
    the row state.
- **Multi-Panel Hotel Booking Application**:
  - Snippet found: table record action mounted on a page/table and acting on the selected
    row.
  - Pattern to copy: ordinary `Action::make(...)->action(function ($record) { ... })`
    usage works on table rows and relation-manager style tables.
  - Pattern to avoid: array-record custom table data, since PodText uses Eloquent rows.
  - PodText adaptation: mount the same action object in both `ContentItemsTable` and
    `ContentItemsRelationManager`.

## Resolution Policy Evidence

The existing public `ContentItem::effectiveTranscription()` returns a published featured
transcription when possible, otherwise the latest published transcription. UX2 needs an
admin edit target even when public-effective data is missing, so it extends that public
resolution for admin editing only:

1. effective published transcription;
2. featured transcription, even when unpublished;
3. latest transcription by id.

The action is hidden only when `transcriptions()->exists()` is false. This keeps draft
and unpublished featured transcripts editable from the episode list without changing
public visibility rules.

## Implementation Implications

- Add one shared action class under `app/Filament/Actions`.
- Mount it in:
  - `app/Filament/Resources/ContentItems/Tables/ContentItemsTable.php`;
  - `app/Filament/Resources/ContentGroups/RelationManagers/ContentItemsRelationManager.php`.
- Extract the reusable edit schema from `TranscriptionForm` by adding a helper that can
  omit `content_item_id`.
- Fill the action form from the resolved transcription, including ordered
  `transcriber_ids`.
- Save only the transcription fields and call `syncTranscribers()` after the model save.
- Add a context column that reads already eager-loaded `featuredTranscription` /
  `latestPublishedTranscription` data and does not query in the column closure.
- Keep UX1's global modal width and section width defaults; do not redeclare them.

## Stop Conditions

- Stop if the repository is dirty before implementation.
- Stop if the v4 enhancement plan header is missing.
- Stop if the ledger/current-state docs disagree that UX2 is first pending.
- Stop if the implementation would require changing public visibility rules, adding
  migrations, enabling public SPA mode, or starting AX/SL/V/P/S/B/C/9F work.
