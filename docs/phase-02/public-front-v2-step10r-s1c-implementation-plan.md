# Public Front v2 Step 10R-S1c Implementation Plan

## Selected Step

Step 10R-S1c - Inline import locks on the settings page.

UX3 is complete as `0f3aed6`. S1c runs after S1b/HF2/UX3 and keeps the Importer
Workbench gate open. MP1 and the Importer Workbench are explicitly out of scope.

## Implementation Plan

1. Finish Job 0 corrections.
   - Union the front-text preset with manually selected locks.
   - Preserve selected import rows when switching import modes.
   - Keep first duplicate card-template family/key entries and warn on duplicates.
   - Record local MySQL runtime and SQLite `:memory:` test safety.
   - Skip snapshot scheduling for locks-only system backups while still creating the
     system backup row.

2. Add inline lock controls.
   - Add Public Content Settings header action linking to `/admin/settings-import-locks`.
   - Add section header lock actions for schema sections mapped to lifecycle groups.
   - Add field hint lock actions where the field state path resolves to exactly one
     lifecycle unit.
   - For deep fields, show translated copy explaining which containing unit is covered.
   - Memoize lock reads per request and persist toggles through `SettingsImportLocks`.

3. Preserve D29 import-only behavior.
   - Normal settings saves must preserve current `import_locks`.
   - Locked fields must remain editable.
   - Lock state affects import dry-runs and server-side apply filtering only.

4. Harden test safety.
   - Keep forced test env in `phpunit.xml`.
   - Add Pest bootstrap overrides for exported shell DB vars.
   - Add a base TestCase canary that aborts before migrations unless the app is testing
     with SQLite `:memory:`.

5. Tests.
   - Add focused Pest coverage for preset union, mode-switch preservation, duplicate
     templates, inline section/field toggles, D29 editable-while-locked behavior,
     header/RTL smoke, locks-only backups without snapshots, recapture on done rows,
     and file-level storage fakes.
   - Run duplicate-key scans for both `lang/en/admin.php` and `lang/he/admin.php`.

6. Docs and finalization.
   - Update current state, ledger, D29 decision, AI lessons, and S1c handoff.
   - Run the full sequential gate.
   - Commit locally as `feat: add inline import locks on settings page`.
   - Do not push.

## Out Of Scope

- Read-only or edit-blocking lock behavior.
- Lock UI outside Public Content Settings.
- New lock granularity.
- Importer Workbench, MP1, P2/P3, AX, SL, B4, C2, 9F, Step 11, and Prompt 13.

## Commit hash

Previous completed mini-step UX3: `0f3aed6`.

S1c commit message: `feat: add inline import locks on settings page`.

Final S1c commit hash is reported in the chat final because this document is part of
that commit.

## Local Front Check Report

1. Lock a field inline and confirm it is locked in the manager and excluded in the import
   wizard dry-run.
2. Lock a section inline and confirm all units in that lifecycle group are locked.
3. Edit and save a locked field to confirm D29 import-only behavior.
4. Save only lock changes and confirm a system backup row exists with no snapshot rows.
5. Recapture a done thumbnail from the snapshot gallery.
6. Check Hebrew RTL settings rendering and light/dark-safe translated lock controls.
