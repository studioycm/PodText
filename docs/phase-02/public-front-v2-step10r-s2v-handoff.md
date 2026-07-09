# Public Front v2 Step 10R-S2V Handoff

## Status

Step 10R-S2V is complete pending final review. This run implemented backup visual snapshots only, plus the required S2 prune correction and first-run S1a/S1b documentation amendments.

Next mini-step: Step 10R-S1a - Settings export and import wizard core.

## Summary

- Added `settings_backup_snapshots` as the private snapshot metadata table.
- Added a finite snapshot manifest for home, search, podcasts, first podcast, first episode, contributors, and first contributor public screens.
- Added queued snapshot processing through `SettingsBackupSnapshotJob` and `scripts/settings-snapshots.mjs`.
- Added private admin-only file streaming, failed-shot retry, and download-all zip routes.
- Added the settings-backups table home-thumbnail column and a Snapshots slide-over gallery with screen tabs, theme toggles, scrollable full-page image previews, download links, and retry controls.
- Corrected S2 retention pruning so only `source = system` rows are auto-pruned; manual, before-import, and before-restore backups are retained until explicit delete.
- Added snapshot file cleanup for both explicit backup delete and retention prune.
- Moved `playwright` from devDependencies to dependencies for runtime snapshot capture.

## Files Changed

- Backup snapshot engine: `app/Models/SettingsBackupSnapshot.php`, `app/Support/SettingsLifecycle/SettingsBackupSnapshotManifest.php`, `app/Support/SettingsLifecycle/SettingsBackupSnapshotManager.php`, `app/Jobs/SettingsBackupSnapshotJob.php`, `scripts/settings-snapshots.mjs`.
- Backup integration: `app/Support/SettingsLifecycle/SettingsBackupManager.php`, `app/Models/SettingsBackupVersion.php`, `app/Filament/Resources/SettingsBackups/Tables/SettingsBackupsTable.php`, `resources/views/filament/settings-backups/snapshots-gallery.blade.php`.
- Routes/controllers: `routes/web.php`, snapshot file/retry/zip controllers.
- Data/config/dependencies: `database/migrations/2026_07_09_000010_create_settings_backup_snapshots_table.php`, `config/settings-backups.php`, `package.json`, `package-lock.json`.
- Tests: `tests/Feature/SettingsBackupsTest.php`, `tests/Feature/SettingsBackupSnapshotsTest.php`.
- Docs/translations: admin translations in English and Hebrew, S2V research/plan docs, current state, ledger, sequence doc, S2 handoff hash backfill, S1a/S1b plan/ledger split, old-prompt supersede notes.

## Tests

Focused verification passed:

- `php artisan test tests/Feature/SettingsBackupsTest.php tests/Feature/SettingsBackupSnapshotsTest.php`
- `php artisan test tests/Feature/AdminPhase02ResourcesTest.php tests/Feature/AdminResourcesTest.php tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php`

Full required gate passed:

- `vendor/bin/pint --dirty --format agent`
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`
- `git diff --check`

## Implementation Notes

- System backups get only two thumbnail rows: `home` and `podcasts`, PNG only, using the first configured snapshot theme.
- Manual, before-import, and before-restore backups get those thumbnails plus full snapshot rows for all resolved manifest screens across the selected themes and formats.
- Dynamic manifest targets are skipped when no public podcast, episode, or contributor exists.
- Snapshot processing is per row. A failed browser/process capture marks only that snapshot failed and does not fail the backup row.
- HTML snapshots are reference captures only; assets stay remote.
- Snapshot files are stored on the private `local` disk under `settings-backups/{backup_id}/` and are streamed through authenticated admin routes.

## Deploy Notes

- Forge deploy must run `npm ci`; Playwright is now a production dependency.
- Run `npx playwright install chromium --with-deps` once per server.
- The queue worker user must be able to execute Chromium.
- `APP_URL` must point to a URL the queue worker can reach for the public site.
- Keep Horizon/queue workers running because snapshot capture is queued.

## Deviations

- No S1a or S1b import/export functionality was implemented.
- No GSAP/AX work was implemented.
- Thumbnail capture uses a viewport width equal to the configured maximum width so it does not require an extra image-resize dependency.

## Commit hash

Commit message: `feat: add backup visual snapshots`.

The actual commit hash lands in the final report after the commit is created.

## Local Front Check Report

1. Open `/admin/settings-backups` while signed in. Expected: the table shows a new `Home thumbnail` column before the date column; old backup rows without snapshots may show no image, while new processed backups show a private thumbnail image. Check in Hebrew RTL and both light/dark admin themes.
2. Click `Create backup`. Expected: the modal includes `Snapshot formats` checkboxes for PNG/PDF/HTML and `Snapshot themes` toggles for light/dark, prefilled from `settings_backups`. Submit a labeled manual backup.
3. After the queue processes, reopen `/admin/settings-backups`. Expected: the new backup row is present, the thumbnail is visible, and the Actions menu includes `Snapshots`.
4. Click Actions -> `Snapshots`. Expected: a slide-over opens with screen tabs, light/dark theme switches when captured, status chips, a scrollable image preview for PNG rows, per-shot download links, and `Download all snapshots`.
5. For a failed snapshot row, click `Retry snapshot`. Expected: the row returns to pending and the snapshot job is queued again; other snapshots remain unchanged.
6. Open public screens `/`, `/search`, `/podcasts`, one podcast page, one episode page, `/contributors`, and one contributor page. Expected: no visible public UX change from this step; these screens are only read by the snapshot worker. Verify Hebrew RTL content remains correctly aligned in light and dark public themes.
7. Delete a backup from `/admin/settings-backups`. Expected: the row disappears and its private files under `storage/app/private/settings-backups/{id}` are removed.

## Next Step

Run Step 10R-S1a next. After S1b, pause for the custom importer side quest before returning to P2/P3/AX/SL/B4/C2/9F work.
