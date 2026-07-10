# Public Front v2 Step 10R-S1b Implementation Plan

## Selected Step

Step 10R-S1b - Import locks and add-only mode.

## Dependencies

- Step 10R-S2 is complete as `f694c49`.
- Step 10R-S2V is complete as `86d21cb`.
- Step 10R-S1a is complete as `30e413c`.
- Step 10R-S1b is the first pending ledger row.

## Job 0 Corrections

1. Fix the overlay drift test to call `data_get($payload, $path, '__missing__')`
   and add a meta-assertion that a bogus path returns `__missing__`.
2. Change `SettingsBackupManager::import()` to return the actually applied paths
   after server-side allowed-path and lock filtering. The wizard completion summary
   uses that count.
3. Add `mimetypes:application/json,text/plain` to import upload validation.
4. Add a schema guard and test that every derived unit path segment is dot-free.
5. Refine lifecycle segmentation before locks ship:
   - `route_labels` units are route-key units.
   - `card_templates` units are card-family units.
   - The previous whole-unit overrides are removed.
6. Backfill the S1a commit hash `30e413c` in active docs.

## Schema And Lock Boundary

- Add `import_locks` to `PublicContentSettings`, registry defaults, registry keys,
  validator normalization, and a settings migration.
- Keep `import_locks` excluded from lifecycle units so the wizard cannot import or
  lock the lock configuration itself.
- Add a focused lock service that reads, normalizes, and saves locked unit paths using
  `SettingsLifecycleSchema`.
- Add schema-owned unit value helpers for normal paths and virtual paths:
  - `route_labels.{route_key}` maps to one route-label repeater item.
  - `card_templates.{family}` maps to templates of that family, keyed by template key
    for deterministic add-only merging.
- Add a schema method that resolves each overlay semantic path up to exactly one
  lockable unit. The "Lock all front texts" preset consumes only that method.

## Lock Manager UI

- Add a hidden Filament page for import locks, launched from a Settings Backups table
  header action next to Import.
- Reuse the shared selection-table partial in lock mode. Rows show current setting
  previews and lock checkboxes; group toggles lock/unlock all selectable units in the
  group.
- Add preset actions:
  - Lock all front texts.
  - Unlock all.
  - Save locks.

## Import Wizard Changes

- Add a mode selector on the dry-run step: `replace` and `add_only`, default
  `replace`.
- Re-run analysis when the mode changes so outcome chips recompute live.
- Locked rows are visible but forced unselected, non-selectable, and marked with a
  lock outcome. The summary shows how many rows were excluded by locks.
- Server-side import enforces locks even if selected paths are forced into the
  request payload.
- Restore continues ignoring locks and restores the package payload verbatim,
  including `import_locks`.

## Add-Only Merge

Add a small pure merge class for unit values:

- `replace` returns the imported unit value.
- `add_only` recursively unions associative maps with current values winning.
- Lists and scalars are applied only when the current value is empty.
- `card_templates.{family}` unit values are keyed maps, so add-only adds new template
  keys and keeps existing colliding keys untouched.
- Lock filtering happens before merge, so locks beat both modes.

## Tests

- Lock persistence round-trip from the lock manager component.
- Lock all front texts derives locks from overlay semantics and each front-text path
  maps to exactly one lockable unit.
- Locked units are excluded from import even when force-selected server-side.
- `import_locks` never appears as an importable/lockable unit.
- Restore ignores locks and restores lock values verbatim.
- Add-only adds new card-template keys but preserves existing colliding keys.
- Add-only fills empty scalar/list values and skips filled ones.
- Lock beats add-only.
- Analyzer outcome chips cover replace, add_new, skip_exists, skip_locked,
  skip_unchanged, remove, and error.
- Overlay drift, upload mimetype validation, import applied-path count, route/card
  segmentation, and dot-segment guard regressions.

## Docs And Handoff

- Update the ledger S1b row as complete and note that the Importer Workbench gate is
  open after this settings-arc run.
- Update current project state with S1b completion and the next pause for the custom
  importer side quest.
- Create `docs/phase-02/public-front-v2-step10r-s1b-handoff.md` with standard
  sections plus `## Commit hash` and `## Local Front Check Report`.

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
feat: add settings import locks and add-only mode
```
