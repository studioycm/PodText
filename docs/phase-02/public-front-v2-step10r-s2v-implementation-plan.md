# Public Front v2 Step 10R-S2V Implementation Plan

## Selected Step

Step 10R-S2V - Backup visual snapshots.

## Dependencies

- Step 10R-S2 is complete as `f694c49`.
- The ledger's first pending mini-step is S2V.
- S1a and S1b are not implemented in this run.
- This run includes the first-run lifecycle docs amendments that split S1 into S1a/S1b
  and record decisions D21-D28.

## Current Repo Evidence

- `SettingsBackupManager` already creates manual, system, and before-restore backups.
- `SettingsBackupVersion` stores the full settings package and has no snapshot
  relation yet.
- The backups table is list-only and already owns create, download, compare, restore,
  and delete actions.
- `settings_backups` settings already expose `thumbnail_max_width`, `snapshot_formats`,
  and `snapshot_themes`.
- Public theme persistence uses `localStorage['podtext-theme']` plus the root `dark`
  class.
- The private local disk points at `storage/app/private`, which is appropriate for
  non-public snapshot files.

## Research Summary

- Laravel Boost confirmed current package versions and Process/Queue/Filesystem APIs.
- Laravel Process supports array commands and can be faked/asserted in tests.
- Laravel storage supports private local file writes, fake disk assertions, download
  responses, and directory deletion.
- Filament table actions support modal/slide-over read-only content and row actions.
- FilamentExamples returned search/snippet examples for `ImageColumn`, custom table
  view cells, dynamic record actions, and image preview modal placeholders; no deeper
  fetch/read access was exposed.

## Implementation Plan

1. Apply S2 audit corrections.
   - Change retention pruning so only `source = system` rows are pruned.
   - Ensure pruned backup snapshot files are deleted before rows are deleted.
   - Backfill S2 commit `f694c49` into the S2 handoff, ledger, and current-state rows.
2. Apply first-run docs amendments.
   - Split `Step 10R-S1` into `Step 10R-S1a` and `Step 10R-S1b` in the ledger,
     sequence, and enhancement plan.
   - Add D21-D28 lifecycle decisions to the enhancement plan.
   - Add supersede notes under Run 2 and Run 3 in the old settings prompt file.
3. Add snapshot schema and model.
   - Create `settings_backup_snapshots` with backup FK cascade, finite screen/theme/
     viewport/kind/format/status fields, resolved URL, private path, nullable error,
     and timestamps.
   - Add `SettingsBackupSnapshot` model and relationship methods on
     `SettingsBackupVersion`.
4. Add snapshot services.
   - `SettingsBackupSnapshotManifest` resolves finite public screens from `APP_URL`
     and current published podcast/episode/contributor samples.
   - `SettingsBackupSnapshotManager` creates pending rows per policy, dispatches the
     queued job, streams single downloads and zip downloads, retries failed shots, and
     deletes stored files.
5. Add queued snapshot job and Node script.
   - `SettingsBackupSnapshotJob` processes snapshot rows sequentially with small
     sleeps, invokes `node scripts/settings-snapshots.mjs <job.json>` through
     Laravel Process, and marks each row done/failed independently.
   - `scripts/settings-snapshots.mjs` launches Chromium, sets the public theme through
     the same localStorage key, captures PNG/PDF/HTML outputs, and writes absolute
     paths from the JSON contract.
   - Move `playwright` from `devDependencies` to `dependencies`.
6. Update backup UI.
   - Manual backup modal gains format checkboxes and theme toggles prefilled from
     `settings_backups`.
   - Table gains the home thumbnail image column.
   - Row actions gain "Snapshots" gallery, per-shot download/retry, and download-all zip.
   - Delete action routes through the snapshot manager so files are removed.
7. Add tests.
   - S2 prune correction: only system rows pruned, manual/before_import/before_restore
     retained.
   - Snapshot manifest creates pending rows from fixtures and resolved URLs.
   - System backup creates thumbnail-only rows; manual backup creates full configured rows.
   - Process-faked job invokes the script with the expected JSON contract.
   - Per-shot process failure marks that row failed and continues.
   - Gallery renders stable markers and scroll-container markers.
   - Image column/action presence is asserted through the backups table.
   - Snapshot files are removed on single delete and retention prune.
8. Docs and handoff.
   - Mark S2V complete in the ledger and current state; next step becomes S1a.
   - Add S2V handoff with commit-hash and local front check sections plus Forge notes.

## Files To Change

- `package.json`
- `package-lock.json`
- `scripts/settings-snapshots.mjs`
- `database/migrations/*_create_settings_backup_snapshots_table.php`
- `app/Models/SettingsBackupSnapshot.php`
- `app/Models/SettingsBackupVersion.php`
- `app/Jobs/SettingsBackupSnapshotJob.php`
- `app/Support/SettingsLifecycle/SettingsBackupManager.php`
- `app/Support/SettingsLifecycle/SettingsBackupSnapshotManifest.php`
- `app/Support/SettingsLifecycle/SettingsBackupSnapshotManager.php`
- `app/Filament/Resources/SettingsBackups/Tables/SettingsBackupsTable.php`
- `resources/views/filament/settings-backups/snapshots-gallery.blade.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `tests/Feature/SettingsBackupsTest.php`
- `tests/Feature/SettingsBackupSnapshotsTest.php`
- S2V research, plan, handoff, ledger, sequence, current-state, enhancement-plan, and
  old-prompt supersede docs

## Risks

- Queue workers may lack Chromium on first deploy. The handoff must include the Forge
  `npx playwright install chromium --with-deps` setup note.
- Snapshot files are private. Filament image preview needs authenticated streamed URLs
  or route-backed access; do not expose raw storage paths publicly.
- Eloquent cascade deletes do not delete files. Both explicit delete and prune must
  delete private files through a service before removing rows.
- Public sample pages may have no podcast/episode/contributor records in sparse
  environments. The manifest should skip sample-only targets that cannot be resolved
  while still capturing fixed routes.
- Process-faked tests must avoid launching a browser in CI while still proving the
  JSON contract and row state transitions.

## Quality Gate

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```

Commit on green:

```text
feat: add backup visual snapshots
```
