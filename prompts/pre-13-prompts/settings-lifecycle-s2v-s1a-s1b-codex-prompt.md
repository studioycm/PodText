# Codex Prompt — Steps 10R-S2V → 10R-S1a → 10R-S1b: Backup Snapshots, Settings Export/Import Wizard, Import Locks and Modes

Work in the current local clone of `studioycm/PodText`.

This prompt covers THREE mini-steps run one per run, in order: S2V (backup visual
snapshots + S2 audit corrections), S1a (settings export + import wizard core), S1b
(import locks + add-only mode). It SUPERSEDES Run 2 and Run 3 of
`prompts/pre-13-prompts/settings-backups-snapshots-import-export-codex-prompt.md`
(that file's Run 1 = Step 10R-S2 is complete as `f694c49`). S1 as a single step no
longer exists; it is replaced by S1a + S1b.

All standing runner rules apply: one implementation step per run, full quality gate
incl. `git diff --check`, no push unless asked, no `filacheck --fix`, no `model:show`,
fixture-owned tests, en+he translations on all new admin strings, RTL-safe UI, handoffs
end with `## Commit hash` and `## Local Front Check Report` sections.

STANDING CORRECTION RULE (new, applies to every run from now on): as part of each
run's docs job, backfill the PREVIOUS run's commit hash into that run's handoff
`## Commit hash` section and into any ledger/current-state rows that still say
"this commit" for it. The S2V run backfills S2's hash `f694c49`.

## Preflight every run

```bash
git status --short --branch
git log --oneline --decorate -20
php artisan migrate:status
```

Confirm clean tree and that the ledger's first pending step matches the run. FIRST RUN
OF THIS FILE ONLY (docs amendment before implementing S2V):

1. In `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`, replace the
   single `Step 10R-S1` pending row with TWO rows: `Step 10R-S1a - Settings export and
   import wizard core` and `Step 10R-S1b - Import locks and add-only mode` (S1b depends
   on S1a). Keep the existing note that S1a/S1b have no dependency on S2V and may be
   selected explicitly before it. Move the WB-gate note: the Importer Workbench track
   opens after S1b (was: after S1).
2. Mirror the same S1a/S1b split in
   `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md` and in the
   v4 sequence table + S1 section of
   `docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md`. Replace the S1
   section body with the S1a/S1b scopes and the binding decisions D21-D28 below
   (record them as numbered decisions in the plan doc's decision list).
3. Append a one-line supersede note under the `## Run 2` and `## Run 3` headings of
   `prompts/pre-13-prompts/settings-backups-snapshots-import-export-codex-prompt.md`
   pointing to this file, so the stale blocks cannot be run by mistake.

## Facts already shipped by S2 (`f694c49`) — reuse, do not rebuild

- `settings_backup_versions` table + `SettingsBackupVersion` model + `SettingsBackupSource`
  enum + `SettingsBackupResource` (list-only) + `SettingsBackupManager`
  (manual/system/before-restore creation, any-existing-hash dedupe, retention prune,
  compare, transactional restore through `PublicContentSettings::save()`).
- `PublicSettingsPackage` verbatim serializer (schema_version 1, canonical sorted-key
  JSON, checksum, payload hash) and `SettingsPackageDiff` (generic dot-path flatten).
- `settings_backups` group inside `public_content` (thumbnail_max_width 400|600|800,
  snapshot_formats png|pdf|html, snapshot_themes light|dark) — registry + validator +
  settings migration all landed. S2V ships NO new settings migration.
- Validation policy: checksum mismatch → REFUSE; package schema_version newer than
  supported → REFUSE; settings-migration watermark mismatch → WARN only. Packages are
  paths-only for uploaded files (missing-files warning list at dry-run; no media
  packaging).
- Apply path: hydrate `app(PublicContentSettings::class)`, assign, `save()` inside
  `DB::transaction()`; the `SettingsSaved` listener invalidates the P1 cache and
  creates the system backup; the echo is absorbed by hash dedupe. Never write the
  Spatie repository directly.

## Binding design decisions D21-D28 (Yoni-approved) — settings-lifecycle abstraction

The settings structure WILL change/improve soon. All lifecycle machinery must be
structure-agnostic: it may know how to WALK a settings payload, never what is in it.

- **D21 — one schema boundary.** New `SettingsLifecycleSchema` service (namespace
  `App\Support\SettingsLifecycle`) is the ONLY component that knows the settings
  shape. It exposes: managed groups; selectable units (dot-paths); structural type per
  path (bool/int/string/list/map) DERIVED from registry defaults + current payload;
  expected scalar PHP types via reflection on the settings class; label translation
  key per path by naming convention (`admin.settings_paths.<path>` style) with
  graceful fallback to the raw path. Selection UI, locks, presets, modes, diff
  presentation, and dry-run type checks consume ONLY this service. No other class or
  Blade view may enumerate a group name or path string.
- **D22 — segmentation is a policy, not a hard depth.** Default rule: a scalar
  property is its own unit; an array group's units are its first-level keys. Per-path
  overrides live in a small declarative overlay co-located with the lifecycle code
  (NOT scattered in features), so a future group (e.g. `display_templates`) can
  declare finer units (per-template-name) with zero UI changes.
- **D23 — semantic overlay + drift guard.** The overlay also declares semantics that
  cannot be derived from value shape: which paths are front-facing free text (the
  Hebrew copy: titles, labels, route labels, about texts, empty-state messages,
  type-label overrides), which are asset paths. One drift test asserts every overlay
  path exists in the merged defaults, so a settings restructure fails loudly in one
  test instead of silently breaking five features.
- **D24 — persistent import locks.** `import_locks` group inside `public_content`
  (`{"locked_paths": []}`), path vocabulary validated against the schema service
  (unknown/stale paths dropped with a warning). Locks are HARD in the wizard: locked
  rows are greyed, lock-iconed, forced-unselected, with an excluded-count summary; to
  import a locked path the user must unlock it in the lock manager first — there is no
  in-wizard override. The `import_locks` group itself is NEVER importable
  (hard-excluded from the selection tree). Restore from S2 backups IGNORES locks
  (backups are trusted recovery points) and restores the locks themselves verbatim.
- **D25 — import modes.** Global per-run mode `replace | add_only`, chosen on the
  dry-run step; outcome chips recompute live. `add_only`: associative maps merge as a
  recursive union where CURRENT WINS on conflicts (new keys added, existing keys
  untouched — the "import new templates safely" case); lists and scalars apply only
  when the current value is empty (null/''/[]). Lock beats mode. Every selected row
  shows its resolution chip: `replace` / `add (new)` / `skip (exists — add-only)` /
  `skip (locked)` / `skip (unchanged)`.
- **D26 — backups are import sources.** Wizard step 1 offers upload OR picking an
  existing backup row (its payload_json IS a package) — giving selective restore
  (restore only chosen units from a backup, optionally add-only) for free.
- **D27 — packages survive restructures.** `PublicSettingsPackage` gains a payload
  upgrade pipeline keyed by `schema_version` (v1 = identity, structured like
  migrations). A future settings restructure ships one upgrader (old paths → new
  paths) and bumps the supported version so every existing backup/export stays
  restorable/importable; locked paths ride the same upgrader because `import_locks`
  lives inside the payload.
- **D28 — group-parametric lifecycle.** One registration point (e.g.
  `SettingsLifecycleGroups`) maps group name → settings class + defaults provider +
  overlay. v1 registers only `public_content`. Manager/package/wizard/lock services
  take the group from it — no direct `PublicContentSettings::class` references
  sprinkled through UI code. (The S2 `scope` column already anticipates this.)
- **No literal counts anywhere** (code OR tests). Round-trip and toggle-count tests
  derive expectations from the schema service / registry defaults. Adding, removing,
  or renaming a setting must not require touching lifecycle code or lifecycle tests,
  except the overlay + its drift test when semantic classification changes.

---

## Run 1 — Step 10R-S2V: Backup visual snapshots

### Job 0 — S2 audit corrections (small, do first)

1. Retention prune must delete ONLY `source = system` rows. Manual, before_import,
   and before_restore backups are never pruned automatically; they are removed only by
   the explicit Delete action. Update `SettingsBackupManager::prune()` + the retention
   test.
2. Backfill `f694c49` per the standing correction rule (S2 handoff + ledger +
   current-state rows).

### Engine

- Move `playwright` from devDependencies to dependencies. App-owned node script
  `scripts/settings-snapshots.mjs`: args = JSON job file (targets: url, screen_key,
  theme, formats, full|thumb, max_width, out paths). Uses Chromium; sets the public
  theme the same way the site's theme selector persists it (inspect the header
  implementation — likely localStorage/cookie/documentElement attribute — replicate,
  do not hack CSS); full-page PNG via fullPage screenshot; PDF via page.pdf(); HTML via
  page.content() saved as-is (reference-only, assets stay remote). Thumbnails:
  viewport render scaled to `settings_backups.thumbnail_max_width`, image format only.
- Laravel side: `SettingsBackupSnapshotJob` (queued, Horizon): builds the manifest,
  invokes the script per target via `Process`, sequential + small sleep, writes
  per-shot rows; per-shot retry action; snapshot failures NEVER fail or block the
  backup row.

### Manifest (finite, v1)

Screens: `home /`, `search /search`, `podcasts /podcasts`, `podcast` (first published
group by homepage order), `episode` (first public item), `contributors /contributors`,
`contributor` (first public author). Resolved sample URLs stored on each snapshot row.
Themes from `settings_backups.snapshot_themes`. Desktop viewport 1440px only. Base URL
= `config('app.url')`.

### Policy

- EVERY created backup row (all sources, system included) gets the two THUMBNAILS:
  `home` + `podcasts`, image format only, max width per setting (hash dedupe already
  bounds row volume).
- FULL sets (all 7 screens × selected themes × selected formats) run for `manual`,
  `before_import`, `before_restore` sources; the manual-backup modal gains format
  checkboxes (png/pdf/html) + theme toggles prefilled from `settings_backups`; system
  backups take no full set.

### Schema + storage + UI

`settings_backup_snapshots`: id, backup_id FK cascade, screen_key, theme, viewport,
kind (`thumbnail|full`), format, resolved_url, path, status (`pending|done|failed`),
error (nullable), timestamps. Files under private disk `settings-backups/{backup_id}/`,
streamed downloads only.

IMPLEMENTATION TRAP — file deletion: deleting a backup must also delete its snapshot
FILES. The DB FK cascade removes snapshot ROWS but not files, and bulk query deletes
(as used by `prune()`) fire NO Eloquent model events. File cleanup must work for BOTH
the single-record Delete action and retention pruning — collect paths first or convert
prune to event-firing deletes. Cover both paths with tests.

Resource table gains the home thumbnail as an image column (visual row identity).
"Snapshots" row action opens a gallery (page or slide-over): screen tabs, theme
switcher, tall full-page image inside a scrollable max-height container, per-shot
download, download-all zip, PDF/HTML links when captured, per-shot retry on failed.

### Deploy notes (handoff)

Forge: `npm ci` includes playwright; run `npx playwright install chromium --with-deps`
once per server; the queue worker user must be able to execute Chromium; `APP_URL`
must serve the public site from the worker.

### Tests

Job creates expected pending rows from the manifest with resolved URLs (Process faked —
no real browser in CI); thumbnail-only for system source, full set for manual;
per-shot failure marks that row failed without failing others or the backup; gallery
renders rows + scroll container markers; image column on the table; snapshot files
removed on single delete AND on retention prune; prune deletes only system rows
(Job 0); script file exists and is invoked with the expected JSON contract (assert
command line via Process fake); nav/harness/full gate green.

Commit: `feat: add backup visual snapshots`

---

## Run 2 — Step 10R-S1a: Settings export and import wizard core

### Schema boundary first (D21/D22/D23/D27/D28)

- `SettingsLifecycleGroups` registration point (public_content only) and
  `SettingsLifecycleSchema` service per D21/D22: units, structural types, scalar
  reflection types, label-key convention with fallback. Include the overlay skeleton
  (semantic map + per-path segmentation overrides; declare the front-text and
  asset-path semantics for the CURRENT structure) + the drift test per D23.
- `PublicSettingsPackage`: add the schema_version-keyed payload upgrade pipeline
  (v1 = identity) per D27; `fromArray()` runs the pipeline before validation.
- Refactor `SettingsPackageDiff` labels/grouping presentation to consume the schema
  service (engine stays generic).

### Export

Header action on the Public Content Settings page AND the backups list: streams
`PublicSettingsPackage::fromCurrentSettings()` as a JSON download (filename with date
+ app name). Reuse the existing streamed-download pattern from S2.

### Import wizard (dedicated admin page, launched from a backups-list header action)

A dedicated Filament page (custom Livewire content is fine and preferred for the
selection table), hidden from the nav (launched via the header action; if the UX1
nav-completeness test enumerates pages, add an explicit documented exemption).
Progressive disclosure throughout; UX1 width/section defaults.

1. SOURCE step: upload a package JSON (private temp disk, validated size/type) OR pick
   an existing backup row (D26 — its payload_json is the package; no upload).
2. VALIDATE: run the upgrade pipeline, then checksum REFUSE / newer schema_version
   REFUSE / watermark mismatch WARN banner. Plus scalar TYPE-CHECK via the schema
   service's reflection types: mismatched paths become non-selectable error rows in
   the dry-run (warned, never applied) instead of a mid-apply TypeError.
3. DRY-RUN + SELECTION step: one shared, reusable selection-table Livewire component
   (S1b reuses it for the lock manager — build it standalone):
   - Sections per group with tri-state group toggle buttons (all/some/none) +
     changed/added counts; rows per unit with current → imported preview and outcome
     chip; scalars grouped under a derived pseudo-group.
   - Filter bar: changed / added / removed / all + text search. DEFAULT VIEW: changes
     only, all changed units pre-selected. Unchanged rows hidden until expanded.
   - Missing-files warning list (paths in payload absent on the public disk) and
     normalization warnings (validator dry pass on lifecycle groups).
   - Everything labeled via the schema service; RTL-safe; en+he.
4. CONFIRM + APPLY: `before_import` backup via the S2 manager (if S2V has landed its
   snapshot policy fires automatically; S1a must NOT depend on S2V), then transactional
   apply of ONLY the selected units: per group, take the CURRENT group value, replace
   the selected unit subtrees with imported values, run the validator on lifecycle
   groups, assign + `save()` (replace mode only in S1a); selected scalars assigned
   after type-check. Applied-with-warnings summary at the end; caches invalidated via
   the existing listener.

### Tests

Derived-count losslessness: export → import round-trip restores every unit (expected
set DERIVED from the schema service, no literals); partial selection applies only
selected units (unselected keep current values, verified on both a scalar and a nested
group key); tri-state group toggle semantics; checksum tamper refused; newer
schema_version refused; watermark mismatch imports with warning; scalar type mismatch
becomes an error row and never applies; missing-file warning; backup-as-source path
produces an identical dry-run to uploading the same package; upgrade pipeline identity
pass covered; before_import backup created; cache invalidated (fresh public read
reflects an imported value); guest/unauthorized blocked; drift test green; bounded
public harness + full gate.

Commit: `feat: add settings export and import wizard`

---

## Run 3 — Step 10R-S1b: Import locks and add-only mode

### Locks (D24)

- `import_locks` group inside `public_content`: registry defaults
  (`{"locked_paths": []}`), validator normalization against the schema service's unit
  vocabulary (unknown paths dropped with warning), settings migration.
- Lock manager: header action on the backups list (next to Import) opening the SAME
  selection-table component in lock mode — rows show lock toggles at group ("section")
  and unit ("field") level; includes the "Lock all front texts" preset button driven
  by the D23 overlay semantics, plus "Unlock all". Persists to `import_locks`.
- Wizard integration: locked rows greyed + lock icon + forced-unselected + excluded
  count in the summary; `import_locks` itself never appears in the tree; restore
  remains lock-ignoring (test it).

### Add-only mode (D25)

- Mode selector (`replace | add_only`) on the dry-run step, default replace; outcome
  chips recompute live per D25 semantics.
- Merge engine as a small pure class: recursive current-wins union for associative
  maps; fill-only-if-empty for lists and scalars; lock beats mode; validator pass
  after merge. Property-based clarity over cleverness — this class is the one the
  workbench (D-WB14) will later align with.

### Tests

Lock persistence round-trip; preset locks exactly the overlay's front-text paths
(derived, no literals); locked unit excluded from apply even when "selected" is forced
in the request payload (server-side enforcement, not just UI); `import_locks` never
importable; restore ignores locks and restores lock values verbatim; add_only adds a
new map key while keeping an existing colliding key untouched (template scenario);
add_only fills an empty scalar and skips a filled one; lock-beats-mode; outcome chips
per resolution; drift test still green; harness + full gate.

Commit: `feat: add settings import locks and add-only mode`

---

## Research directives per run (house process)

Boost `search_docs` before coding: queued jobs + `Process` (S2V), Filament custom
pages/wizards/header actions/streamed downloads/image columns/slide-overs, FileUpload
to private disk, Livewire component state for the selection table, Spatie settings
save/transactions (S1a/S1b), PHP reflection on typed properties. FilamentExamples MCP
short batches + refined pass per run (S2V: gallery/image column; S1a: import wizard
upload+preview, settings page header actions; S1b: matrix/toggle table patterns).
Research note `docs/research/public-front-v2/20-step10r-<id>-mcp-research.md` and an
implementation plan doc BEFORE code, every run.

## Docs each run

Ledger row + current-state + handoff with the standard sections, `## Commit hash`,
`## Local Front Check Report` (numbered admin clicks incl. Hebrew RTL + light/dark),
and the standing hash-backfill correction. Run 1 also executes the FIRST-RUN docs
amendment (S1a/S1b split + D21-D28 + supersede notes). After S1b: the ledger notes the
WB gate is OPEN (Importer Workbench WB1 has its own prompt file) and the main queue
resumes at P2 when Yoni chooses.

End each run with exactly:

```text
Public Front v2 mini-step <MINI_STEP_ID> is complete. Waiting for Yoni review before continuing.
```
