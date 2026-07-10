# Public Front v2 Step 10R-S1a Handoff

## Status

Step 10R-S1a is complete pending final review. This run implemented settings export/import wizard core only, plus the requested S2V audit corrections.

Next mini-step: Step 10R-S1b - Import locks and add-only mode.

## Summary

- Added schema-agnostic lifecycle services for public-content settings units, labels, sections, structural types, and semantic overlays.
- Added JSON export actions to the Public Content Settings page and Settings Backups list.
- Added a hidden admin import page with a Livewire wizard that accepts either an uploaded package JSON or an existing backup row as the source.
- Added dry-run validation for checksum, schema version, watermark mismatch, scalar type mismatches, missing public-disk asset paths, and validator normalization warnings.
- Added a reusable Livewire selection table with grouped tri-state toggles, changed/added/removed/all filters, text search, current/imported previews, and outcome chips.
- Added replace-mode selected-unit import with a transactional `before_import` backup and existing public-front cache invalidation.
- Corrected S2V snapshot queue/timeout/prune/thumbnail behavior as Job 0.

## Files Changed

- Export/import UI: `app/Filament/Actions/ExportPublicSettingsAction.php`, `app/Filament/Pages/ImportPublicSettings.php`, `app/Filament/Pages/PublicContentSettings.php`, `app/Filament/Resources/SettingsBackups/Tables/SettingsBackupsTable.php`, `resources/views/filament/pages/import-public-settings.blade.php`, `app/Livewire/Admin/*`, `resources/views/livewire/admin/*`.
- Settings lifecycle engine: `SettingsLifecycleGroups`, `SettingsLifecycleSchema`, `SettingsLifecycleSelectionState`, `SettingsPackageImportAnalyzer`, `SettingsPackageImportAnalysis`, `SettingsPackageUpgradePipeline`, `PublicSettingsPackage`, `SettingsPackageDiff`, `SettingsBackupManager`.
- S2V audit corrections: `SettingsBackupSnapshotJob`, `SettingsBackupSnapshotManager`, `scripts/settings-snapshots.mjs`, `config/settings-backups.php`, `config/horizon.php`, `config/queue.php`.
- Tests: `tests/Feature/SettingsImportExportTest.php`, `tests/Feature/SettingsBackupSnapshotsTest.php`.
- Docs/translations: English/Hebrew admin translations, S1a research/plan docs, current state, ledger, S2V handoff hash backfill, this handoff.

## Tests

Focused verification passed:

- `php artisan test --compact tests/Feature/SettingsImportExportTest.php tests/Feature/SettingsBackupSnapshotsTest.php`
- Fractional headless Chromium check for `deviceScaleFactor = 800 / 1440`

Full required gate passed:

- `vendor/bin/pint --dirty --format agent`
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`
- `git diff --check`

## Implementation Notes

- S1a is replace-mode only. Persistent import locks and add-only merge semantics remain Step 10R-S1b.
- The lifecycle schema currently registers only the `public_content` settings group and derives units from current/default payload structure.
- The dry-run table preselects changed/added selectable rows and keeps type-error rows non-selectable server-side.
- Backup-as-source uses the same package JSON path as upload; the focused test asserts identical dry-run signatures.
- `before_import` snapshots fire through the existing S2V backup scheduling path when snapshots are available.

## S2V Audit Corrections

- `SettingsBackupSnapshotJob` now implements `ShouldQueueAfterCommit`; restore-created `before_restore` rows have a regression test.
- Snapshot job timeout is config-driven through `SETTINGS_BACKUP_SNAPSHOT_JOB_TIMEOUT` with default `1800`.
- Horizon supervisor timeout is env-driven through `HORIZON_SUPERVISOR_TIMEOUT` with default `1850`; Redis `retry_after` defaults to `1900`.
- Thumbnail jobs now capture the desktop 1440px layout with fractional `deviceScaleFactor`; headless Chromium accepted the fractional scale locally. The previous narrow viewport remains the script fallback.
- Retention prune defers snapshot file deletion with `DB::afterCommit()` so rolled-back transactions keep files for restored rows.

## Deploy Notes

- Set `SETTINGS_BACKUP_SNAPSHOT_JOB_TIMEOUT=1800`, `HORIZON_SUPERVISOR_TIMEOUT=1850`, and `REDIS_QUEUE_RETRY_AFTER=1900` in local `.env` when testing Redis/Horizon queues.
- Set the same `REDIS_QUEUE_RETRY_AFTER=1900` value in Forge `.env`; keep the chain ordered as job timeout `<` Horizon supervisor timeout `<` Redis retry-after.
- Continue running `npm ci` and `npx playwright install chromium --with-deps` on servers that process snapshot jobs.
- Keep `APP_URL` reachable from the queue worker, because snapshot jobs open public URLs.

## Deviations

- No Step 10R-S1b work was implemented.
- No import locks, add-only mode, workbench, P2/P3, AX, SL, B4, C2, 9F, Prompt 13, or Step 11 work was implemented.
- No custom CSV/import controllers were added; S1a stays on the S2 `PublicSettingsPackage` format.

## Commit hash

Commit: `30e413c feat: add settings export and import wizard`.

The S1a hash was backfilled during the S1b run per the standing correction rule.

## Local Front Check Report

1. Open `/admin/public-content-settings` in Hebrew RTL. Expected: the page has an `Export public settings` header action; clicking it downloads a JSON package without changing settings. Check light and dark admin themes.
2. Open `/admin/settings-backups` in Hebrew RTL. Expected: the table header has `Export public settings` and `Import public settings` actions, plus the existing backup actions. Export downloads the current JSON package.
3. Click `Import public settings`. Expected: a hidden admin page opens at `/admin/import-public-settings` with upload and backup-source choices; it is not present in the left navigation.
4. Upload a valid exported JSON package. Expected: the wizard shows the dry-run table with grouped setting units, changed/added counts, tri-state group buttons, filter dropdown, search field, current/imported previews, and outcome badges. Hebrew text remains RTL-aligned; the table remains readable in light and dark mode.
5. Choose an existing backup as source. Expected: the same dry-run rows appear as uploading the same package JSON.
6. Select only a subset of changed rows and apply. Expected: a `before_import` backup is created, only selected setting units change, unselected values stay current, and public pages reflect the imported value after cache invalidation.
7. Upload a tampered or newer-schema package. Expected: the wizard refuses it at the source step and shows the translated error without applying changes.
8. Upload a package that references a missing custom image path. Expected: the dry-run opens with a warning that names the missing path; applying still works for selected valid units.
9. Open public pages `/`, `/podcasts`, one podcast page, and one episode page after an import. Expected: no broken public rendering; Hebrew RTL and light/dark theme behavior remain unchanged except for the imported settings values.

## Next Step

Run Step 10R-S1b next, then pause for Yoni's custom importer side quest before returning to P2/P3/AX/SL/B4/C2/9F work.
