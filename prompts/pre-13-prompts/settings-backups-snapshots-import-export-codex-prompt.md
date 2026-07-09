# Codex Prompt — Steps 10R-S2 → 10R-S2V → 10R-S1: Settings Backups, Visual Snapshots, Import/Export

Work in the current local clone of `studioycm/PodText`.

This prompt covers THREE mini-steps run one per run, in order: S2 (backup versions +
restore), S2V (visual snapshots, NEW step), S1 (import/export package). All standing
runner rules apply: one implementation step per run, full quality gate incl.
`git diff --check`, no push unless asked, no `filacheck --fix`, no `model:show`,
fixture-owned tests, en+he translations on all new admin strings, RTL-safe UI, handoffs
end with `## Commit hash` and `## Local Front Check Report` sections.

## Preflight every run

```bash
git status --short --branch
git log --oneline --decorate -20
php artisan migrate:status
```

Confirm clean tree and that the ledger's first pending step matches the run (S2, then
S2V, then S1). FIRST RUN ONLY: insert a `Step 10R-S2V - Backup visual snapshots` row
into the ledger between S2 and S1 (S1 has NO dependency on S2V; Yoni may run S1 before
S2V by selecting it explicitly), and append the S2V section + the research refinements
below to `docs/phase-02/public-front-v2-admin-settings-enhancement-plan.md`.

## Research-verified facts binding all three steps (from Yoni/Fable planning)

1. `PublicContentSettings` holds 34 properties: 23 scalars + 11 array groups
   (`card_templates`, `menu_config`, `about_page`, `public_forms`, `route_labels`,
   `display_defaults`, `default_images`, `transcription_policy`, `item_page`,
   `podcasts_page`, `contributors_page`). Packages/backups snapshot the FULL Spatie
   group via `getPropertiesInGroup('public_content')` — scalars included.
2. Backups are VERBATIM snapshots (no normalization on capture). Validator
   normalization of registry-known groups happens at IMPORT/RESTORE apply time only,
   apply-with-warnings style.
3. Reuse P1's public API: `PublicFrontConfigCache::settingsMigrationWatermark()` for
   the package watermark and `::forget()` where explicit invalidation is needed.
4. Apply path for restore/import: hydrate `app(PublicContentSettings::class)`, assign
   properties, `save()` inside `DB::transaction()`. The existing `SettingsSaved`
   listener then invalidates caches AND triggers the S2 system backup; the post-apply
   auto-backup echo is absorbed by payload-hash dedupe. Do NOT bypass the settings
   class with raw repository writes.
5. Package validation policy: checksum mismatch → REFUSE; package `schema_version`
   newer than supported → REFUSE; settings-migration watermark mismatch → WARN only
   (cross-environment imports legitimately differ).
6. Uploaded files: settings carry public-disk paths (logos, default images, about/team
   images). v1 packages are PATHS-ONLY; the dry-run report includes a
   "missing files on this environment" warning list. Media packaging is deferred.
7. Every new page/resource must be added to `AdminNavigationOrder` (the UX1
   completeness test enforces this). UX1 global modal/section defaults apply.

---

## Run 1 — Step 10R-S2: Settings backup versions and restore

### Schema

`settings_backup_versions`: id; scope (string, default `public_content`); label
(nullable); payload_json (longText); checksum (string, sha256 over canonical
sorted-key JSON of the payload); payload_hash (string, indexed — dedupe key over the
same canonical JSON); source enum-ish string `manual|before_import|before_restore|system`;
created_by_user_id (nullable FK, admin-only visibility, never exposed publicly);
timestamps. MySQL+SQLite compatible.

### PublicSettingsPackage serializer (shared with S1 — build it HERE)

`app/Support/SettingsLifecycle/PublicSettingsPackage.php` (namespace per conventions):
`fromCurrentSettings()`, `fromArray()`, `toArray()`/`toJson()`, `checksumValid()`.
Package shape: `schema_version` (int, start 1), `generated_at`, `app_version`,
`settings_group`, `settings_migration_watermark`, `payload` (all 34 properties),
`checksum`. Canonical JSON = sorted keys, unescaped unicode.

### Behavior

- Automatic `system` backup on every `PublicContentSettings` save via the existing
  listener: skip when payload_hash equals the latest backup's hash; retention keep
  last N (config `settings-backups.retention`, default 25) with prune-on-create.
- New `settings_backups` array group INSIDE `public_content` (registry defaults +
  validator + settings migration + render-context accessor is NOT needed publicly —
  admin-only reads are fine): `{ "thumbnail_max_width": 800, "snapshot_formats":
  ["png"], "snapshot_themes": ["light","dark"] }` with finite vocabularies
  (`thumbnail_max_width`: 400|600|800; formats: png|pdf|html; themes: light|dark).
  Consumed by S2V; added now so S2V ships no second migration.
- `SettingsBackupResource` (read-only: create/edit/delete-record disabled except a
  delete with confirmation if trivial): table columns created_at (day-first,
  Asia/Jerusalem), source badge, label, payload_hash short, size. Header action
  "Create backup" (label input). Row actions: Download (streams the package JSON),
  Compare (backup ↔ CURRENT settings: grouped dot-path diff — added/removed/changed
  per top-level property, drillable list; v1 scope is backup↔current only),
  Restore (confirmation modal shows the diff summary; creates `before_restore` backup;
  applies per fact #4; success notification).
- Add the Resource to `AdminNavigationOrder` near Settings.

### Tests

Backup creation manual + system; hash dedupe skips identical consecutive payloads;
retention prune; download package validates checksum; compare diff lists a changed
scalar AND a changed nested group key; restore round-trip (change → backup → change →
restore → original values back) with cache invalidated (assert via
`PublicFrontConfigCacheTest` helpers/pattern) and `before_restore` row created;
unauthorized/guest access absent; nav completeness test green; bounded public harness
green; full gate.

Commit: `feat: add settings backup versions and restore`

---

## Run 2 — Step 10R-S2V: Backup visual snapshots (NEW)

Decisions (Yoni): Playwright reuse (already in package.json), desktop viewport only in
v1 (mobile later), system backups get THUMBNAILS ONLY, full sets are policy-gated.

### Engine

- Move `playwright` from devDependencies to dependencies. App-owned node script
  `scripts/settings-snapshots.mjs`: args = JSON job file (targets: url, screen_key,
  theme, formats, full|thumb, max_width, out paths). Uses Chromium; sets the public
  theme the same way the site's theme selector persists it (inspect the header
  implementation; likely localStorage/cookie/documentElement attribute — replicate,
  do not hack CSS); full-page PNG via screenshot fullPage; PDF via page.pdf(); HTML
  via page.content() saved as-is (documented as reference-only, assets stay remote).
  Thumbnails: viewport-width render scaled to `thumbnail_max_width` (default from the
  `settings_backups` group), PNG/JPEG only.
- Laravel side: `SettingsBackupSnapshotJob` (queued, Horizon): builds the manifest,
  invokes the script per target via `Process`, sequential + small sleep, writes
  per-shot rows; per-shot retry action; failures NEVER fail or block the backup row.

### Manifest (finite, v1)

Screens: `home /`, `search /search`, `podcasts /podcasts`, `podcast` (first published
group by homepage order), `episode` (first public item), `contributors /contributors`,
`contributor` (first public author). Resolved sample slugs/urls are stored on each
snapshot row. Themes from `settings_backups.snapshot_themes`. Desktop viewport 1440px.
Base URL = `config('app.url')`.

### Policy

- EVERY created backup row (all sources, system included) gets the two THUMBNAILS:
  `home` + `podcasts`, image format only, max width per setting (R: quick visual aid —
  dedupe already bounds volume).
- FULL sets (all 7 screens × selected themes × selected formats) run for `manual`,
  `before_import`, `before_restore` sources by default; the manual-backup modal gets
  format checkboxes (png/pdf/html) + theme toggle prefilled from `settings_backups`;
  system backups take no full set.

### Schema + storage + UI

`settings_backup_snapshots`: id, backup_id FK cascade, screen_key, theme, viewport,
kind (`thumbnail|full`), format, resolved_url, path, status (`pending|done|failed`),
error (nullable), timestamps. Files under private disk `settings-backups/{backup_id}/`,
streamed downloads, deleted with the backup (retention prune cascades files).
Resource table gains the home thumbnail as an image column (visual row identity).
"Snapshots" row action opens a gallery (page or slide-over): screen tabs, theme
switcher, tall full-page image inside a scrollable max-height container, per-shot
download, download-all zip, PDF/HTML links when captured, per-shot retry on failed.

### Deploy notes (handoff)

Forge: `npm ci` includes playwright; run `npx playwright install chromium --with-deps`
once per server; ensure the queue worker user can execute it; APP_URL must serve the
public site from the worker.

### Tests

Job creates expected pending rows from the manifest with resolved URLs (Process faked —
no real browser in CI); thumbnail-only for system source, full set for manual;
per-shot failure marks the row failed without failing others or the backup; gallery
renders rows + scroll container markers; image column on the table; storage pruned with
backup deletion; script file exists + is invoked with the expected JSON contract
(assert command line via Process fake); nav/harness/full gate green.

Commit: `feat: add backup visual snapshots`

---

## Run 3 — Step 10R-S1: Settings import/export package

### Export

Header action on the Public Content Settings page (and/or the backup Resource):
streams `PublicSettingsPackage::fromCurrentSettings()` as a JSON download
(filename includes date + app name).

### Import wizard (header action, UX1 wide-modal defaults; progressive disclosure)

1. Upload (private temp disk) → parse → validate per fact #5 (checksum REFUSE /
   schema_version newer REFUSE / watermark mismatch WARN).
2. DRY-RUN report: grouped dot-path diff vs current (added/removed/changed per
   property), normalization warnings (run registry-known groups through the validator
   and surface what would be normalized away), missing-files list (paths in the
   payload that do not exist on the public disk), and PER-GROUP APPLY TOGGLES —
   one toggle per array group (11) plus ONE toggle for the scalars bundle; all default
   on; unchanged groups shown as no-op.
3. Confirm → `before_import` backup (S2, with its snapshots policy) → transactional
   apply of ONLY the selected groups/bundle via the settings instance save (fact #4)
   → surface applied-with-warnings summary → caches invalidated via the listener.

### Tests

Export→import round-trip is lossless for all 34 properties; partial apply changes only
selected groups (unselected keep current values); tampered checksum refused; newer
schema_version refused; watermark mismatch imports with a warning; normalization
warning surfaced for an intentionally-invalid token in the package; missing-file path
warned; before_import backup + snapshots policy triggered; cache invalidated (fresh
public read reflects imported value); guest/unauthorized blocked; harness + full gate.

Commit: `feat: add settings import export package`

---

## Research directives per run (house process)

Boost `search_docs` before coding: Spatie settings repository/getPropertiesInGroup/
save events, DB transactions, Filament Resource custom actions + streamed download
responses + image columns + slide-overs, FileUpload to private disk, Laravel `Process`
+ queued jobs, config vs settings. FilamentExamples MCP short batches + refined pass
(settings page header actions; import wizard upload+preview; resource gallery/image
column patterns); research note `docs/research/public-front-v2/20-step10r-<id>-mcp-research.md`;
implementation plan doc before code each run.

## Docs each run

Ledger row (+ S2V insertion on run 1), enhancement plan doc S2V section (run 1),
`current-project-state.md`, handoff with the standard sections + `## Commit hash` +
`## Local Front Check Report` (numbered admin clicks: create backup with label →
see thumbnails appear on the row after the queue runs → open gallery and scroll a
full-page shot in both themes → compare → restore → export file → import it back with
one group toggled off → verify only selected groups changed; Hebrew RTL + light/dark).
After S1: the ledger notes the WB gate is OPEN (Importer Workbench WB1 has its own
prompt) and the main queue resumes at P2 when Yoni chooses.

End each run with exactly:

```text
Public Front v2 mini-step <MINI_STEP_ID> is complete. Waiting for Yoni review before continuing.
```
