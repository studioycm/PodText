# Public Front v2 10R-S2 Handoff

## Purpose

Step 10R-S2 adds admin-only public content settings backup versions and restore. It
creates the safety net that Step 10R-S1 import/export will reuse.

## What Was Implemented

- Added `settings_backup_versions` with scope, label, full package JSON, checksum,
  payload hash, source, optional creator, and timestamps.
- Added `settings_backups` to the `public_content` settings group for S2V defaults:
  thumbnail width, snapshot formats, and snapshot themes.
- Added `PublicSettingsPackage` for verbatim full-group settings capture with canonical
  sorted-key checksum/hash.
- Added `SettingsBackupManager` for manual backups, automatic system backups on
  `PublicContentSettings::save()`, retention pruning, compare, and restore.
- Restore creates a `before_restore` backup, applies through `PublicContentSettings`,
  runs inside a DB transaction, and invalidates the P1 public-front cache boundary.
- Added system backup dedupe by payload hash; this also absorbs restore echo writes when
  the restored payload already exists as a backup.
- Added `SettingsBackupResource` as a list-only admin resource near Public Settings.
- Added header action "Create backup" and row actions Download, Compare, Restore, and
  Delete.
- Added first-run docs amendments: S2V row in the ledger and S2V section in the v4
  enhancement plan.

## Files Changed

- `config/settings-backups.php`
- `database/migrations/2026_07_09_000008_create_settings_backup_versions_table.php`
- `database/settings/2026_07_09_000009_add_public_settings_backup_settings.php`
- `app/Enums/SettingsBackupSource.php`
- `app/Models/SettingsBackupVersion.php`
- `app/Support/SettingsLifecycle/PublicSettingsPackage.php`
- `app/Support/SettingsLifecycle/SettingsBackupManager.php`
- `app/Support/SettingsLifecycle/SettingsPackageDiff.php`
- `app/Filament/Resources/SettingsBackups/*`
- `app/Settings/PublicContentSettings.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Providers/AppServiceProvider.php`
- `app/Filament/Support/AdminNavigationOrder.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `tests/Feature/SettingsBackupsTest.php`
- `tests/Feature/AdminPhase02ResourcesTest.php`
- S2 research, plan, handoff, ledger, sequence, current-state, and enhancement-plan docs

## Tests Added Or Updated

Added `tests/Feature/SettingsBackupsTest.php` covering:

- manual backup creation through the real Filament table action;
- automatic system backup creation on settings save;
- identical system-backup dedupe;
- retention pruning on SQLite-compatible query logic;
- streamed JSON download and checksum-valid package shape;
- guest access protection for the backup resource;
- scalar and nested settings diff output;
- restore round trip with before-restore backup and public config cache invalidation.

Updated `tests/Feature/AdminPhase02ResourcesTest.php` so the new resource is included in
the central admin navigation map.

## Exceptions / Notes

- S2V visual snapshots were not implemented. Only the S2V ledger row, enhancement-plan
  section, and `settings_backups` defaults/migration were added as required by this
  first S2 run.
- S1 import/export was not implemented.
- Backup capture is verbatim. Validator normalization is used on restore/apply only.
- The package payload now includes 35 properties after S2 because `settings_backups` is
  added to the previous 34-property `public_content` group.
- `SETTINGS_BACKUPS_RETENTION` can override the default retention of 25.

## Quality Gate

Final gate commands:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```

Final gate results:

- `vendor/bin/pint --dirty --format agent`: passed.
- `php artisan test`: passed, 319 tests / 3003 assertions.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed, 0 issues.
- `npm run build`: passed.
- `git diff --check`: passed.

## Commit hash

Commit message: `feat: add settings backup versions and restore`.

Commit hash: `f694c49`.

## Local Front Check Report

1. Open `/admin/settings-backups` while logged in as an admin. Expected: a new Hebrew/RTL
   Settings Backups list opens from the admin navigation near Public Settings, with
   day-first created dates, source badges, label, short payload hash, and package size.
2. Click "Create backup", enter a Hebrew label, and submit. Expected: a success
   notification appears and a new row is visible in light and dark mode without layout
   overflow.
3. On that row, click Download. Expected: the browser downloads a JSON package whose
   filename starts with `public-content-settings-backup-`; the JSON includes
   `schema_version`, `settings_group`, `payload`, and `checksum`.
4. Change a visible setting in `/admin/public-content-settings`, such as Podcasts page
   title, save, then return to `/admin/settings-backups` and click Compare on the older
   backup. Expected: the modal shows a summary plus dot-path changes, including the
   changed settings group key, in Hebrew/RTL with readable line breaks.
5. Click Restore on the older backup and confirm. Expected: the settings return to the
   backup values, a `before_restore` backup row is created, and public pages reflect the
   restored settings after reload because the P1 config cache was invalidated.
6. Verify S2-only scope: no thumbnail column, snapshot gallery, export action, or import
   wizard appears yet. Those are S2V/S1 work.

## Next Step

Step 10R-S2V is now the default next pending step. Yoni may explicitly select Step
10R-S1 next because S1 does not depend on S2V.
