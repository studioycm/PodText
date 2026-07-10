# Public Front v2 Step 10R-S1a Implementation Plan

## Selected Step

Step 10R-S1a - Settings export and import wizard core.

## Dependencies

- Step 10R-S2 is complete as `f694c49`.
- Step 10R-S2V is complete as `86d21cb`.
- Step 10R-S1a is the first pending ledger row.
- S1b is explicitly out of scope.

## Current Repo Evidence

- `PublicSettingsPackage` serializes the current `public_content` settings payload with
  schema version 1, checksum, payload hash, and migration watermark.
- `SettingsBackupManager` already creates backups, restores packages transactionally,
  validates checksum/schema/scope for restore, saves through `PublicContentSettings`,
  and forgets the public-front cache/render context.
- `SettingsBackupsTable` owns backup create/download/compare/restore/delete actions.
- `PublicContentSettings` is a Filament `SettingsPage` and has no export header action
  yet.
- The admin panel discovers `app/Filament/Pages`; hidden pages can opt out of
  navigation and therefore do not need an `AdminNavigationOrder` entry.
- S2V snapshot dispatch currently needs the audit corrections listed below.

## Job 0 Audit Corrections

1. Make `SettingsBackupSnapshotJob` implement `ShouldQueueAfterCommit` and configure
   its timeout from `settings-backups.snapshot_job_timeout` with a default of 1800.
2. Align the queue timeout chain:
   - `settings-backups.snapshot_job_timeout` default 1800.
   - Horizon supervisor timeout from `HORIZON_SUPERVISOR_TIMEOUT`, default 1850.
   - Redis `retry_after` from `REDIS_QUEUE_RETRY_AFTER`, default 1900.
3. Change thumbnail snapshot JSON contracts to render the desktop layout at 1440px
   with `deviceScaleFactor = thumbnail_max_width / 1440`; the Playwright script retries
   the prior narrow-viewport mode if fractional scale capture fails.
4. In `SettingsBackupManager::prune()`, delete pruned rows first and defer private
   snapshot directory deletion with `DB::afterCommit()`.
5. Backfill prior hashes in S2V handoff, ledger, and current-state rows.

## Schema Boundary

Add `App\Support\SettingsLifecycle` classes:

- `SettingsLifecycleGroups`: one registration point for `public_content`, mapping the
  group to the settings class, defaults provider, validator, and overlay.
- `SettingsLifecycleOverlay`: semantic declarations and future segmentation override
  storage.
- `SettingsLifecycleSchema`: derives managed groups, selectable unit paths, structural
  type (`bool`, `int`, `string`, `list`, `map`, `null`), expected scalar PHP types via
  reflection, label keys, labels with fallback, and semantic groups.
- `SettingsLifecycleUnit`: small value object for schema-derived UI/apply rows.

Default segmentation:

- Scalar top-level setting properties are individual units.
- Array setting groups expose first-level keys as units.
- The lifecycle code may know how to walk payloads, but only the schema service and
  overlay may know current paths.

## Package/Diff Changes

- `PublicSettingsPackage::fromArray()` runs a schema-version keyed upgrade pipeline
  before returning the package. Version 1 is identity.
- `SettingsPackageDiff` continues to compare flattened payloads but presents group and
  path labels through `SettingsLifecycleSchema`.

## Export

- Add one export action helper mounted in:
  - `app/Filament/Pages/PublicContentSettings.php`
  - `app/Filament/Resources/SettingsBackups/Tables/SettingsBackupsTable.php`
- The action streams `PublicSettingsPackage::fromCurrentSettings()->toJson()` with a
  date/app-name filename and `application/json; charset=UTF-8`.

## Import Wizard

Mount points:

- Hidden Filament page: `App\Filament\Pages\ImportPublicSettings`, route slug
  `/admin/import-public-settings`, `shouldRegisterNavigation() = false`.
- Backups list header action: `importSettings` links to that page.
- Custom Livewire component: `App\Livewire\Admin\SettingsImportWizard`.
- Reusable selection-table component: `App\Livewire\Admin\SettingsLifecycleSelectionTable`
  plus `SettingsLifecycleSelectionState` helper for tri-state semantics.

Flow:

1. Source: upload a JSON package or choose an existing backup row.
2. Validate: parse package, run upgrade pipeline, refuse checksum mismatch, refuse
   newer schema version, warn on migration watermark mismatch.
3. Dry-run: build schema-derived rows with current/imported previews, outcome chips,
   changed/added/removed/unchanged states, scalar type errors, missing-file warnings,
   normalization warnings, and default changed-row selection.
4. Apply: create a `before_import` backup, replace only selected units into the current
   payload, validate lifecycle groups, save through `PublicContentSettings`, and show
   an applied-with-warnings summary.

## Tests

- Schema service derives units from defaults/current payload without literal counts.
- Overlay drift test proves every semantic path exists in merged defaults.
- Export/import round trip restores every schema-derived unit.
- Partial selection applies only selected scalar and nested group units.
- Selection-table tri-state group toggles return all/some/none states.
- Checksum tampering is refused.
- Newer schema version is refused.
- Watermark mismatch imports with warning.
- Scalar type mismatch becomes a non-selectable error row and is never applied.
- Missing public-disk asset paths produce warnings.
- Backup-as-source dry run matches uploaded package dry run.
- Upgrade pipeline identity pass is covered.
- `before_import` backup is created.
- Public-front cache is invalidated through the existing save listener.
- Guest/unauthorized access to the import page redirects to admin login.
- S2V audit tests cover after-commit job interface, restore-flow before-restore
  snapshot dispatch, timeout config, thumbnail JSON contract, and prune file deletion
  after commit.

## Files To Change

- Settings lifecycle support classes under `app/Support/SettingsLifecycle`.
- `PublicSettingsPackage`, `SettingsPackageDiff`, and `SettingsBackupManager`.
- `SettingsBackupSnapshotJob`, `SettingsBackupSnapshotManager`, `config/settings-backups.php`,
  `config/horizon.php`, `config/queue.php`, and `scripts/settings-snapshots.mjs`.
- `PublicContentSettings`, `SettingsBackupsTable`, hidden import page, Livewire wizard,
  reusable selection table, and Blade views.
- Admin translations in English and Hebrew.
- Focused settings lifecycle/import tests plus S2V correction tests.
- S1a research, plan, ledger, current-state, and handoff docs.

## Risks

- Large settings payloads can make the dry-run table dense. Keep the default filter on
  changed rows and derive labels from schema fallbacks to avoid brittle hand-written
  translation coverage.
- The hidden import page is discovered by Filament but excluded from navigation; tests
  must assert that behavior remains intentional.
- `before_import` backup creation fires snapshot scheduling when S2V is present. The
  queue-after-commit correction keeps workers from seeing uncommitted rows.
- Add-only and import locks must remain out of scope so S1b can layer them on the same
  schema and selection-table boundaries.

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
feat: add settings export and import wizard
```
