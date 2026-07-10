# Public Front v2 Step 10R-S1d Implementation Plan

## Scope

Implement only Step 10R-S1d: settings import result reporting plus MP1 maintenance,
panel, Horizon, and test-performance hardening. Do not start the Importer Workbench.

## Commit Baseline

- Previous completed S1c commit: `389cb0f feat: add inline import locks on settings page`.
- Previous completed MP1 commit: `8458a5d feat: add maintenance mode page and settings`.
- Target S1d commit: `feat: add import result report and maintenance hardening`.

## Implementation Steps

1. Complete MP1 hardening.
   - Move `RenderMaintenanceMode` out of the public panel default middleware array and
     attach it as its own persistent middleware call.
   - Preserve maintenance content fields through unrelated settings saves.
   - Add a Livewire-interaction admin-bypass regression while maintenance is enabled.
   - Verify the maintenance response shell metadata and existing 503/`Retry-After`
     behavior.
   - Assert `maintenance.rich_html` remains an HTML string after settings-page save.

2. Add sensitive lifecycle semantics.
   - Add `sensitive` to `maintenance.enabled`, `maintenance.title`,
     `maintenance.rich_html`, and `maintenance.raw_html_override`.
   - Keep the maintenance content fields tagged as `front_text`.
   - Update import analysis so sensitive rows remain selectable but are never
     preselected.
   - Cover sensitive default-selection behavior with a focused test.

3. Add import report server truth and persistence.
   - Add a nullable JSON `settings_backup_versions.import_report` migration and model
     cast/fillable update.
   - Add `SettingsImportReport` as the import return value.
   - Build grouped outcomes from lifecycle-analysis rows and applied paths:
     applied, skipped locked, skipped exists, skipped unchanged, errors, and warnings.
   - Store the report on the `before_import` backup row inside the import flow.

4. Add report UI.
   - Add a visible-when-present "Import report" action on `SettingsBackupResource`.
   - Add dry-run summary chips for selected, added, changed, locked excluded, errors,
     and add-only skip-exists.
   - Add a locked filter to the selection table.
   - Render the completion step from the structured report with a link back to the
     before-import backup list row.

5. Add panel/Horizon auth hardening.
   - Define `viewHorizon` in `HorizonServiceProvider` through the admin panel access
     contract.
   - Test an admin-capable user passes and a guest is denied.
   - Record the `User::canAccessPanel()` standing guardrail in the ledger and AI
     lessons.
   - Record the production deploy note that `SESSION_SECURE_COOKIE=true` should be set.

6. Tests.
   - Add the mixed import scenario covering locked, bad scalar, applied change, and
     add-only skip-exists groups.
   - Assert report persistence, backup Resource action visibility/rendering, dry-run
     chips, locked filter, and guest blocking.
   - Keep existing settings import/export, locks, backups, snapshots, and maintenance
     regressions green.

7. Docs and finalization.
   - Update ledger, current state, enhancement-plan decisions, AI lessons, and S1d
     handoff.
   - Record performance findings and no unsafe default-run optimization.
   - Run the full sequential gate:
     `vendor/bin/pint --dirty --format agent`, `php artisan test`,
     `vendor/bin/pint --test`, `vendor/bin/filacheck`, `npm run build`,
     `git diff --check`.
   - Commit locally as `feat: add import result report and maintenance hardening`.
   - Do not push.

## Out Of Scope

- Importer Workbench.
- New lock granularity.
- New retention rules for import reports beyond backup-row lifecycle.
- Public browsing throttling changes.
- Creating non-admin account types or an `is_admin` column.
- P2, P3, AX, SL, B4, C2, 9F, Step 11, and Prompt 13.

## Commit hash

Previous completed MP1 commit: `8458a5d feat: add maintenance mode page and settings`.

S1d commit message: `feat: add import result report and maintenance hardening`.

Final S1d commit hash is reported in the handoff because this document is part of that
commit.

## Local Front Check Report

1. Run an import with one lock set and one bad row: dry-run chips show locked and
   error counts.
2. Apply the import: completion report groups applied, locked, skipped, and error rows.
3. Open the backups list: the before-import backup row exposes the Import report
   action.
4. Open Import report: the modal shows the same grouped report as completion.
5. Confirm a sensitive maintenance unit is deselected by default but can still be
   manually selected.
6. Enable maintenance and interact as a logged-in admin on a Livewire public surface:
   the real public component remains reachable.
7. Confirm Hebrew RTL and light/dark rendering on the maintenance shell and report UI.
