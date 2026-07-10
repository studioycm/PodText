# Public Front v2 Step 10R-S1b Handoff

## Status

Step 10R-S1b is complete pending final review. This was the last settings-arc run in
this prompt family. The Importer Workbench gate is now open; the main 10R queue
pauses for Yoni's custom importer side quest before returning to P2/P3/AX/SL/B4/C2/9F.

## Summary

- Added persistent `import_locks` settings with validator normalization against the
  schema-derived unit vocabulary.
- Added a hidden Settings Import Locks admin page launched from the Settings Backups
  table header.
- Reused the S1a selection-table component in lock mode with row/group toggles,
  locked/unlocked filters, save, unlock-all, and Lock all front texts preset.
- Refined schema segmentation so `route_labels` are route-key units and
  `card_templates` are card-family units, despite their list-based storage.
- Added hard server-side lock enforcement to imports; locked units are visible in the
  wizard but cannot be selected or applied.
- Added add-only import mode with current-wins map merging, fill-only-empty scalar/list
  behavior, and accurate outcome chips.
- Completed S1a audit corrections: overlay drift default, import applied-path count,
  upload MIME validation, dot-segment schema guard, and S1a hash backfill.

## Files Changed

- Settings/storage: `PublicContentSettings`, `PublicFrontConfigRegistry`,
  `PublicFrontConfigValidator`, and `database/settings/2026_07_10_000000_add_public_import_locks_setting.php`.
- Lifecycle/import engine: `SettingsImportMode`, `SettingsImportLocks`,
  `SettingsImportMergeEngine`, `SettingsLifecycleOverlay`, `SettingsLifecycleSchema`,
  `SettingsPackageImportAnalyzer`, and `SettingsBackupManager`.
- Admin UI: `SettingsBackupsTable`, `ManageSettingsImportLocks`,
  `SettingsImportLocksManager`, `SettingsImportWizard`,
  `SettingsLifecycleSelectionTable`, and the related Blade views.
- Tests/translations/docs: `SettingsImportExportTest`, English/Hebrew admin
  translations, S1b research/plan docs, ledger, current state, S1a handoff hash
  backfill, and this handoff.

## Tests

Focused verification passed before the full gate:

- `php artisan migrate`
- `php artisan test --compact tests/Feature/SettingsImportExportTest.php`
- `php artisan test --compact tests/Feature/SettingsBackupsTest.php tests/Feature/SettingsBackupSnapshotsTest.php`

Full gate is run after this handoff is written:

- `vendor/bin/pint --dirty --format agent`
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`
- `git diff --check`

## Implementation Notes

- `import_locks` is intentionally excluded from lifecycle units, so it cannot be
  imported through the selective wizard. Full restore still ignores locks and restores
  `import_locks` verbatim from the backup package.
- `route_labels.{route}` and `card_templates.{family}` are schema-owned virtual unit
  paths. The schema reads/writes them back to the existing list-based settings shape.
- Card-template family unit values are keyed by template key for merge purposes, so
  add-only can add new templates while preserving existing colliding template keys.
- Lock filtering happens before merge mode logic; locks beat both replace and add-only.
- The wizard completion count now uses the `SettingsBackupManager::import()` return
  value after server-side allowed-path and lock filtering.

## Deviations

- No S1a, S2V, P2/P3, AX, SL, B4, C2, 9F, Prompt 13, Step 11, or Importer Workbench
  implementation was added beyond the explicitly requested S1a audit corrections.
- FilamentExamples access was search/snippet only; no source/detail fetch tool was
  available in this session.

## Commit hash

Commit message: `feat: add settings import locks and add-only mode`.

The actual commit hash lands in the final report after the commit is created.

## Local Front Check Report

1. Open `/admin/settings-backups` in Hebrew RTL. Expected: the table header shows
   `Import settings` and the new `Import locks` action next to backup creation/export;
   the table still renders thumbnails and existing backup row actions in light and
   dark mode.
2. Click `Import locks`. Expected: `/admin/settings-import-locks` opens as a hidden
   admin page, not as a navigation item. The page shows grouped setting units with
   lock checkboxes, locked/unlocked filters, and readable RTL table layout.
3. On `/admin/settings-import-locks`, click `Lock all front texts`, then `Save locks`.
   Expected: a success message reports the locked count; rows such as menu logo text,
   route labels, public forms, page labels, and card-template families show locked
   badges. Toggle light/dark; badges and table contrast remain readable.
4. Click `Unlock all`, then `Save locks`. Expected: all units become unlocked and the
   save message reports zero locked units.
5. Create or choose a backup package, then open `/admin/import-public-settings`.
   Expected: the dry-run step has an `Import mode` selector with `Replace` and
   `Add only`; changing it recomputes outcome badges without re-uploading.
6. With one or more locks saved, load an import package that changes a locked unit.
   Expected: locked rows remain visible, show a lock marker/badge, are unchecked and
   disabled, and the summary reports excluded locked units.
7. Apply an import with locked rows plus unlocked rows selected. Expected: only
   unlocked selected units change; the completion count reflects the actually applied
   rows, not the forced/locked selection count.
8. Test add-only with route labels or card templates. Expected: new route labels or
   new card-template keys are added, but existing labels/templates with the same key
   stay unchanged.
9. Restore a full backup from `/admin/settings-backups`. Expected: restore ignores
   import locks and restores the backup's settings, including the saved lock list,
   exactly as before S1b.
