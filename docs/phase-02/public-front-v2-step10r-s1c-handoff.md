# Public Front v2 Step 10R-S1c Handoff

## Status

Step 10R-S1c implementation is complete.

The Importer Workbench and MP1 were not started.

## Scope Completed

- Added inline import-lock section actions and field hint actions to Public Content
  Settings.
- Added the Public Content Settings header action linking to `/admin/settings-import-locks`.
- Added translated lock copy that states imports are protected while editing is
  unaffected.
- Deep field lock hints now resolve to the containing lifecycle unit and say which unit
  the control covers.
- Preserved D29: locked settings fields remain editable and normal settings saves keep
  existing lock state.
- Fixed S1b audit items: front-text preset union, import-mode selection preservation,
  duplicate card-template first-wins normalization, and duplicate-template warnings.
- Fixed locks-only backup behavior so system backup rows are still created while
  snapshots are skipped when only `import_locks` changed.
- Made the snapshot gallery retry action available on done rows as recapture.
- Added test-run canary protection for SQLite `:memory:` and testing environment.
- Moved `Storage::fake('local')` to file-level setup in the three requested test files.
- Confirmed duplicate-key scans pass for both admin language files.

## Tests Added Or Updated

- `tests/Feature/SettingsImportExportTest.php`
  - preset union
  - import-mode selection preservation
  - duplicate template handling
  - inline section and deep-field toggles
  - D29 editable-while-locked save behavior
  - header action and Hebrew RTL smoke
- `tests/Feature/SettingsBackupSnapshotsTest.php`
  - locks-only backup row without snapshots
  - recapture action on done snapshot rows
- `tests/Feature/SettingsBackupsTest.php`
  - file-level local storage fake retained for safety.
- `tests/Pest.php` and `tests/TestCase.php`
  - safe testing environment enforcement.

## Commands Run

- `php -l` on every dirty PHP file - passed.
- `php artisan test tests/Feature/SettingsImportExportTest.php` - passed.
- `php artisan test tests/Feature/SettingsBackupSnapshotsTest.php` - passed.
- `php artisan test tests/Feature/SettingsBackupsTest.php` - passed.
- Duplicate-key scan for `lang/en/admin.php` and `lang/he/admin.php` - passed.
- Full sequential quality gate is run before the S1c commit and reported in the chat
  final.

## Requirement Classification

- Implemented: every S1c inline-lock requirement, all Job 0 audit corrections, D29,
  header manager action, locks-only snapshot skip, file-level storage fakes, recapture
  on done rows, duplicate-key scan, and test canary guard.
- Already existed and preserved: existing lock manager page, import dry-run lock
  enforcement, add-only mode, settings backup package infrastructure, and S2V snapshot
  capture pipeline.
- Deferred by prompt: Importer Workbench, MP1, P2/P3, AX, SL, B4, C2, 9F, Step 11, and
  Prompt 13.
- Blocked: none.

## Commit hash

Previous completed mini-step UX3: `0f3aed6`.

S1c commit message: `feat: add inline import locks on settings page`.

Final S1c commit hash is reported in the chat final because this document is part of
that commit.

## Local Front Check Report

1. Lock a field inline: Public Content Settings exposes an inline lock action for
   `homepage_item_limit`; the lock persists in `SettingsImportLocks`, appears in the
   manager, and import analysis marks that row locked/non-selectable.
2. Lock a section inline: the homepage settings section action toggles all scalar-group
   lifecycle units using `SettingsLifecycleSelectionState`.
3. Confirm import-only semantics: a locked `homepage_item_limit` remains editable and
   saves normally; the lock remains after the settings save.
4. Confirm locks-only backup behavior: saving only `import_locks` creates a system
   backup row with zero snapshot rows, while a later visual setting change schedules
   snapshots.
5. Recapture a done thumbnail: the snapshot gallery renders the done-row action as
   `Recapture snapshot`.
6. Check Hebrew RTL and light/dark: the Hebrew settings page renders `dir="rtl"` with
   translated lock actions; lock UI uses Filament icon/color tokens rather than custom
   theme-specific CSS.
