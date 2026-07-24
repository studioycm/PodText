# Media Operations UX3 Mini-task 1 Library Card Hierarchy Implementation Plan

> Execute only Mini-task 1 of
> `MEDIA-OPS-UX3-O2-PDF-CONTRACT-TARGETED-WORKSPACES` from audit
> `LS-20260724-PODTEXT-MEDIA-OPERATIONS-UX-03`. Stop before Mini-task 2 and
> Package 5.

## 1. Commands

No Artisan scaffold, migration, Composer, or npm dependency command is
authorized or required.

Use these commands sequentially:

1. establish focused baseline:
   `php artisan test --compact tests/Feature/AppOwnedMediaResourceTest.php tests/Feature/ResourceTableIconActionsTest.php`;
2. after writing the focused expectations, run the same command and verify the
   expected RED state;
3. implement the minimum table and translation changes;
4. rerun the focused feature files to GREEN;
5. run the Media gallery browser file serially:
   `php artisan test --compact tests/Browser/MediaResourceGalleryBrowserTest.php`;
6. run the focused Media regression files selected from the affected policy,
   mutation, attachment, and inventory surfaces;
7. run PHP inspections on changed PHP files when the PhpStorm MCP is available;
8. perform the final Simplifier review;
9. run final gates in the mandatory order after the final file change.

## 2. Models and persistence

No model, enum, relationship, cast, table, field, index, setting, migration,
cache, journal, or dependency changes.

The existing authorities remain:

- `App\Models\Media`;
- `App\Support\Media\MediaRecordScope`;
- `App\Support\Media\MediaReferenceFinder`;
- `App\Support\Media\MediaInventoryDiagnostics`;
- `App\Support\Media\MediaFilesystemMutationCoordinator`;
- current Media policies and safe file controllers.

## 3. Resource table changes

### Resource

- **Resource:** existing
  `App\Filament\Resources\Media\MediaResource`
- **Location:**
  `app/Filament/Resources/Media/MediaResource.php`
- **Behavior:** no route, query, form, or page change
- **Docs:** Filament 5 Resources and Tables installed documentation

### Table

- **Table:** existing
  `App\Filament\Resources\Media\Tables\MediaTable`
- **Location:**
  `app/Filament/Resources/Media/Tables/MediaTable.php`
- **Docs:**
  `packages/tables/docs/04-actions.md`,
  `packages/actions/docs/03-grouping-actions.md`
- **Config:**
  - remove this table's call to
    `App\Filament\Resources\Support\ResourceTableActions::iconOnly()`;
  - call
    `->recordActionsPosition(Filament\Tables\Enums\RecordActionsPosition::AfterContent)`;
  - set an explicit `recordUrl()` to the Media edit/details route when
    `Gate::allows('update', $record)` so card activation and the primary action
    have the same destination;
  - retain default sort, 25-only pagination, content grid, search, MIME filter,
    Needs Repair filter, selection, empty-state action, and toolbar action.

### Stable identity column

- **Column:** `card_title`
- **Component:** `Filament\Tables\Columns\TextColumn`
- **Docs:** Filament 5 table text-column documentation
- **Config:**
  - state uses first filled value from Media title, EXIF
    `original_filename`, `basename(path)`, then Media name;
  - retain title/name search authority;
  - semibold and wrapping;
  - use the same state for the preview alt text.

### Original and stored filename columns

- **Columns:** `card_original_filename`, `card_stored_filename`
- **Component:** `Filament\Tables\Columns\TextColumn`
- **Docs:** Filament 5 table text-column documentation
- **Config:**
  - preserve existing EXIF and Curator-path authorities;
  - retain stored-filename LTR isolation, copyability, and wrapping;
  - place them directly after the display identity.

### Known-reference summary column

- **Column:** `card_known_references`
- **Component:** `Filament\Tables\Columns\TextColumn`
- **Docs:** Filament 5 table text-column documentation
- **Config:**
  - count
    `App\Support\Media\MediaReferenceFinder::referencesForMedia($record)`;
  - use localized pluralization;
  - zero text is `No known references`, not `unused`;
  - non-public-disk rows use `Reference count unavailable` because the
    existing finder deliberately does not project those rows;
  - label is `Known references`;
  - add a semantic link icon and
    `data-testid="media-library-card-known-references"`;
  - do not list reference strings or query from Blade.

### Diagnostic status column

- **Column:** existing `repair_status`
- **Component:** `Filament\Tables\Columns\TextColumn`
- **Docs:** Filament 5 table text-column documentation
- **Config:**
  - healthy state remains localized `Ready` with success color;
  - diagnostic state becomes localized `Needs attention` with warning color;
  - retain a supplementary tooltip containing all localized reasons;
  - add `data-testid="media-library-card-attention-status"`.

### Primary issue column

- **Column:** `card_primary_issue`
- **Component:** `Filament\Tables\Columns\TextColumn`
- **Docs:** Filament 5 table text-column documentation
- **Config:**
  - use the first ordered reason from
    `MediaInventoryDiagnostics::reasons($record)`;
  - localize through the existing `repair_<reason>` keys;
  - return null for healthy rows;
  - show a localized below-description only when more than one reason exists,
    using the additional-reason count;
  - wrap and add
    `data-testid="media-library-card-primary-issue"`.

### Quiet file facts

- **Columns:** existing `card_file_summary`, `card_location`, `created_at`
- **Components:** `Filament\Tables\Columns\TextColumn`
- **Docs:** Filament 5 table text-column documentation
- **Config:**
  - move after known references and issue state;
  - retain MIME/extension/dimensions/size, disk/directory, and day-first
    Jerusalem date behavior;
  - retain LTR isolation for technical file summary;
  - use gray text color to reduce hierarchy without hiding facts.

## 4. Record and bulk actions

### Primary details action

- **Action:** existing stable action name `edit`
- **Component:** `Filament\Actions\EditAction`
- **Docs:**
  `packages/tables/docs/04-actions.md`,
  `packages/actions/docs/01-overview.md`
- **Location:** first top-level Media record action
- **Visibility:** visible only when the existing update authorization allows it
- **Authorization:**
  `Gate::allows('update', $record)`
- **Behavior:**
  1. render as a primary `button()`;
  2. show localized `Open details`;
  3. use `Heroicon::OutlinedInformationCircle`;
  4. retain `MediaResource::getUrl('edit', ['record' => $record])`;
  5. do not claim the existing metadata page is the complete later Care
     workspace.

### More actions group

- **Action:** `More actions`
- **Component:** `Filament\Actions\ActionGroup`
- **Docs:** `packages/actions/docs/03-grouping-actions.md`
- **Location:** second top-level Media record action
- **Visibility:** Filament hides the group only when all child actions are
  hidden
- **Authorization:** each child retains its own existing authorization
- **Behavior:**
  1. render a gray icon button;
  2. use localized label and identical tooltip `More actions`;
  3. use `Heroicon::OutlinedEllipsisVertical`;
  4. retain child action names and order:
     `view`, `download`, `rename`, `swap`, `delete`;
  5. retain every existing route, trusted-record recheck, form constraint,
     confirmation, and coordinator callback.

### Permanent delete copy

- **Actions:** nested record `delete` and toolbar `deleteSelected`
- **Components:**
  `Filament\Actions\Action`,
  `Filament\Actions\BulkAction`
- **Authorization:** unchanged `delete` and `deleteAny` policy checks
- **Behavior:** change labels only to `Delete permanently` and
  `Delete selected permanently`; do not implement Trash, restore, purge, new
  confirmation logic, or changed mutation authority.

## 5. Localization

- **Locations:** `lang/en/admin.php`, `lang/he/admin.php`
- **Keys:** add localized values for:
  - `known_references`;
  - pluralized `known_reference_count` including zero;
  - `known_reference_count_unavailable`;
  - `needs_attention`;
  - pluralized `additional_issue_count`;
  - `open_details`;
  - `more_actions`;
  - `delete_permanently`;
  - `delete_selected_permanently`.
- **Rules:** Hebrew remains the primary RTL copy. Technical filenames, paths,
  MIME, and dimensions retain LTR isolation.

## 6. Authorization

No policy changes.

- `edit` remains governed by `update`;
- preview remains governed by `view`;
- download remains governed by `download` and reauthorizes a trusted current
  record;
- rename, swap, delete, and bulk delete retain their existing policy checks;
- all physical mutations remain delegated to
  `MediaFilesystemMutationCoordinator`;
- grouping an action must not change its visibility or make an ineligible
  action callable.

## 7. Widgets and later workspaces

No widget, dashboard statistic, task view, Resource page, Livewire component,
Care page, issue workbench, intake workbench, provider workspace, recovery
workspace, Files Discovery page, or Trash page.

## 8. Tests

### `tests/Feature/AppOwnedMediaResourceTest.php`

Add or update focused assertions for:

- stable identity fallback;
- localized zero and plural known-reference text;
- no `unused` claim;
- persistent primary issue and additional count;
- ordered hierarchy through technical facts;
- two top-level record actions and six stable flat actions;
- action position after content;
- existing filters, controls, pagination, query ceiling, filesystem-probe
  budget, authorization, and mutation behavior.

Use existing SQLite fixtures, fake local/public storage, and
`Http::preventStrayRequests()`.

### `tests/Feature/ResourceTableIconActionsTest.php`

- remove Media's six former top-level icon-only actions from the homogeneous
  Resource rider matrix;
- change the exact rider count from 43 to 37;
- add a dedicated Media assertion:
  - first action is the visible labelled `EditAction` button;
  - second is a gray icon-button `ActionGroup`;
  - group label equals tooltip;
  - flat group children remain `view`, `download`, `rename`, `swap`, `delete`;
  - full flat table action names remain
    `edit`, `view`, `download`, `rename`, `swap`, `delete`;
  - action position is `AfterContent`.

### `tests/Browser/MediaResourceGalleryBrowserTest.php`

In the existing Hebrew/English dataset:

- retain six-card desktop and narrow assertions;
- assert every card shows the known-reference summary and technical facts;
- assert a diagnostic card shows persistent concrete issue text;
- assert visible localized `Open details`;
- assert the localized More actions icon trigger has an accessible name;
- open one menu and assert its entries are labelled;
- retain RTL/LTR, 390×844, card containment, no horizontal overflow, and no
  JavaScript error assertions.

## 9. TDD sequence

1. Run the focused feature files on the clean baseline.
2. Patch tests only with the approved contract.
3. Run them and record the expected RED failures:
   missing copy/hierarchy/grouping/position.
4. Patch only `MediaTable` and HE/EN translations.
5. Run the focused feature files to GREEN.
6. Patch the existing browser test, run it, and make only scope-correct
   presentation fixes if needed.
7. Run the focused Media regression set.
8. Review the diff against the Simplifier objective:
   no duplicate presenter, no per-card query, no policy change, no new
   abstraction, no Mini-task 2, no Package 5.

## 10. Final verification and closeout

After the final file change:

1. requirements sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` last and serial.

If any file changes after a gate, restart at Pint. Never run the full suite or a
browser suite in parallel.

Write a committed handoff containing requirement classifications, files,
tests, every command/result, gate outcomes, assumptions, deferrals, and
numbered imperative Local Front Check steps. Update
`docs/phase-02/current-project-state.md` and the Media Program ledger row.
Commit the implementation with the handoff hash pending, then immediately
create the required docs-only hash-stamp commit. Do not push.
