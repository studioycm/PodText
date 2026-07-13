# Settings Performance SP1 Handoff

Date: 2026-07-13

## Scope

Executed only `prompts/pre-13-prompts/settings-performance-sp1-codex-prompt.md`.

No Composer changes were made.

SP1 adds measurement-only instrumentation for the Public Content Settings page
and completes the Job 0 carried fixes. It does not implement structural
performance fixes.

## Requirement Classification

- Implemented: env-gated settings-page profiler, dedicated daily profiling log
  channel, load/save/live-update/settings-saved/backup/payload phases, local
  measurement report, MP2 maintenance marker copy fix, deploy notes, Horizon
  lesson, IE-1 commit hash backfill, current-state update, ledger row, research
  note, implementation plan, and this handoff.
- Already existed: Public Content Settings monolith page, settings lifecycle
  backup manager, settings import/export lifecycle tests, public maintenance
  raw marker renderer, and `SettingsSaved` listener.
- Deferred by prompt: splitting settings pages, lazy tab schemas, validator
  rewrites, settings normalization refactors, persistent performance caching, and
  Composer/package changes.
- Not applicable: Prompt 13 dashboard work, importer workbench work, production
  deployment changes, remote pushes, and `filacheck --fix`.
- Blocked: none.

## Files Changed

- Instrumentation: `app/Support/Settings/SettingsPageProfiler.php`,
  `config/settings.php`, `config/logging.php`, `.env.example`.
- Settings page/lifecycle: `app/Filament/Pages/PublicContentSettings.php`,
  `app/Support/PublicFront/PublicFrontConfigValidator.php`,
  `app/Providers/AppServiceProvider.php`,
  `app/Support/SettingsLifecycle/SettingsBackupManager.php`.
- Tests: `tests/Feature/SettingsPageProfilerTest.php`.
- Docs: `docs/research/settings-performance/00-sp1-research.md`,
  `docs/research/settings-performance/00-sp1-implementation-plan.md`,
  `docs/phase-02/settings-performance-sp1-handoff.md`,
  `docs/phase-02/current-project-state.md`,
  `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`,
  `docs/phase-02/maintenance-form-mp2-handoff.md`,
  `docs/phase-02/import-relations-ie1-handoff.md`,
  `docs/phase-02/ai-development-lessons.md`.

## Tests Added Or Updated

- Added profiler disabled-by-default coverage that proves no log channel is
  resolved when profiling is off.
- Added profiler enabled coverage that exercises a real Livewire settings save
  and asserts named phase lines, payload bytes, and save request-kind propagation.
- Added marker-copy coverage proving the raw maintenance marker is rendered,
  the form field state is exactly `MaintenanceForm::MARKER`, and the Filament
  copy action writes that exact payload rather than `null`.
- During iteration, fixed the profiler wrapper around
  `PublicFrontConfigValidator` to preserve the invalid-config accumulator by
  reference; the duplicate-card-template import analysis regression covers this.

## Instrumentation Notes

- `SETTINGS_PROFILING=false` is the default and is mapped to
  `config('settings.profiling.enabled')`.
- The profiler writes to `settings_profiling`, a dedicated daily channel at
  `storage/logs/settings-profiling.log`.
- When profiling is disabled, `SettingsPageProfiler` returns before resolving
  `Log::channel()`.
- The profiler is container-scoped so request-kind context propagates through
  nested settings-page, validator, listener, and backup calls in the same request.
- Logged fields are `phase`, `milliseconds`, `request_kind`, and
  `payload_bytes` where applicable.

## Measurement Report

Measurement run id: `sp1-valid-20260713010154`.

Method:

- Temporarily saved a known-valid default settings payload for the measurement
  scenarios, because the local saved settings contained legacy/custom admin state
  that failed current settings-page validation before persist phases.
- Restored the original local settings payload after the run.
- Used `SETTINGS_PROFILING=true` and scenario markers in
  `storage/logs/settings-profiling-2026-07-13.log`.
- Queued snapshot jobs were faked during the measurement so the report measures
  scheduling overhead rather than executing snapshots.

### Initial Loads

| Scenario | `form.total_build` | `settings.read_hydrate` | Largest tab/section phases | Payload |
|---|---:|---:|---|---:|
| Cold load | 1157.348 ms | 7.444 ms | `schema.tab.about` 10.189 ms; `schema.tab.homepage` 5.798 ms; `schema.tab.advanced` 5.605 ms | 7,864 bytes |
| Warm load 1 | 1145.763 ms | 2.917 ms | `schema.tab.advanced` 5.140 ms; `schema.tab.about` 4.158 ms; `schema.tab.podcasts` 0.873 ms | 7,864 bytes |
| Warm load 2 | 1149.524 ms | 2.837 ms | `schema.tab.item_page` 20.725 ms; `schema.section.public-front-item-page-info-fields` 19.835 ms; `schema.tab.advanced` 5.134 ms | 7,864 bytes |

### Save And Live Update

| Scenario | Total | Main measured phases | Payloads |
|---|---:|---|---|
| No-op save | `save.total` 185.749 ms | validation 133.230 ms; persist 13.883 ms; listener 8.763 ms; backup creation 8.390 ms; mutate/normalize 3.344 ms; snapshot scheduling 2.450 ms | load 7,864; validation 7,864; mutate 7,686; persist 7,976; save total 7,864 |
| Single-field save | `save.total` 173.114 ms | validation 126.195 ms; persist 12.082 ms; listener 7.796 ms; backup creation 7.431 ms; mutate/normalize 3.089 ms; snapshot scheduling 2.809 ms | load 7,864; update 7,864; validation 7,864; mutate 7,686; persist 7,976; save total 7,864 |
| Heavy tab live update | `livewire_update.total` 22.816 ms | form build max 1178.396 ms; import-lock validator group total 21.668 ms; item-page schema total 12.265 ms; advanced tab schema total 10.849 ms | load 7,864; update 8,031 |

### Global Top Cost Centers

| Rank | Phase | Total across run | Max single line | Count |
|---:|---|---:|---:|---:|
| 1 | `form.total_build` | 11554.905 ms | 1178.396 ms | 10 |
| 2 | `save.total` | 358.863 ms | 185.749 ms | 2 |
| 3 | `save.validation.total` | 259.425 ms | 133.230 ms | 2 |
| 4 | `validator.group.import_locks` | 127.288 ms | 3.595 ms | 63 |
| 5 | `schema.tab.advanced` | 110.727 ms | 43.295 ms | 10 |
| 6 | `schema.tab.about` | 48.422 ms | 10.189 ms | 10 |
| 7 | `schema.tab.maintenance` | 41.343 ms | 31.136 ms | 10 |
| 8 | `schema.tab.item_page` | 41.165 ms | 20.725 ms | 10 |

Top three findings:

1. `form.total_build` dominates every request. Each Livewire test scenario
   rebuilds the entire monolith form, even for saves and field updates.
2. `save.validation.total` is the main save-path cost after form build, at
   roughly 126-133 ms on the valid local payload.
3. `validator.group.import_locks` is low per call but repeats heavily; across
   the measured run it accumulated 127.288 ms.

Payload findings:

- Valid default load/update payloads are about 7.7-7.9 KB.
- The measured heavy-tab update with one probe card template was 8,031 bytes.
- The first local measurement against Yoni's custom saved settings produced a
  37,292-byte payload but failed current validation before persist phases; that
  supports treating legacy/custom settings payload shape as a separate cleanup
  concern before comparing production-like save timings.

## Ranked Follow-up Plan

1. Split or lazily construct the Public Content Settings monolith so unrelated
   tabs are not rebuilt for every mount, save, and Livewire field update.
2. Move settings normalization/validation toward smaller group-scoped units so
   save validation does not require full-form state and repeated import-lock work.
3. Review advanced/card-template and item-page schema construction for expensive
   option/default builders once the page-level split has reduced noise.
4. Add a dedicated TS2 task to separate suite test cost from real UI cost:
   settings-page Livewire tests currently amplify full form build cost.
5. Clean or migrate legacy/custom local settings payload shapes before using
   local payload size as production evidence.

## TS2 / Test-Cost Overlap

The profiler confirms TS1's earlier finding that settings-page tests are costly
because component mount/save/update flows rebuild the same large schema multiple
times. TS2 should prefer smaller workflow tests and direct unit coverage for pure
normalization where behavior does not require a full Filament page.

## Job 0 Fixes

- MP2 marker copy: `maintenance_form_marker` now hydrates to
  `MaintenanceForm::MARKER`, remains read-only, and is not dehydrated.
- Deploy notes: the MP2 handoff now records the PHP FPM `memory_limit = 512M`,
  nginx `$realpath_root` or FPM reload, and zero-downtime `storage` shared-path
  requirements.
- Horizon lesson: `ai-development-lessons.md` now records the multiple-master
  `APP_NAME`/Redis-prefix risk and the `ps aux` verification step.
- IE-1 hash: active state docs now record
  `6f1cea7 feat: add relation import modes and tag export scope`.

## Commands Run

- Preflight: `git status --short --branch`; `git log --oneline -8`.
- Context/research: Laravel Boost `application_info`; Laravel Boost
  `search-docs`; FilamentExamples `search_examples`; local source/vendor
  inspection with `grep`, `find`, `sed`, and `git show`.
- Syntax:
  `php -l app/Filament/Pages/PublicContentSettings.php`;
  `php -l app/Support/Settings/SettingsPageProfiler.php`;
  `php -l app/Support/PublicFront/PublicFrontConfigValidator.php`;
  `php -l app/Providers/AppServiceProvider.php`;
  `php -l app/Support/SettingsLifecycle/SettingsBackupManager.php`;
  `php -l tests/Feature/SettingsPageProfilerTest.php`.
- Targeted iteration:
  `php artisan test --compact tests/Feature/SettingsPageProfilerTest.php`
  initially failed on the marker render assertion, then passed 3 tests,
  21 assertions; after request-kind coverage, one run failed because the first
  validator line was initial-load, then passed 3 tests, 22 assertions.
- Targeted regression:
  `php artisan test --compact tests/Feature/PublicMaintenanceModeTest.php tests/Feature/SettingsImportExportTest.php`
  failed 1 existing import/export assertion after the first validator wrapper;
  root cause was the profiler arrow closure capturing `$invalidConfig` by value.
- Targeted fix confirmation:
  `php artisan test --compact tests/Feature/SettingsImportExportTest.php --filter='keeps the first duplicate card template during analysis and import'`
  passed 1 test, 6 assertions.
- Targeted confirmation:
  `php artisan test --compact tests/Feature/SettingsPageProfilerTest.php tests/Feature/PublicMaintenanceModeTest.php tests/Feature/SettingsImportExportTest.php`
  passed 45 tests, 424 assertions.
- Formatter iteration:
  first final-gate `vendor/bin/pint --test` failed on
  `app/Filament/Pages/PublicContentSettings.php` for `array_indentation`,
  `unary_operator_spaces`, and `not_operator_with_successor_space`.
- Formatter fix:
  `vendor/bin/pint app/Filament/Pages/PublicContentSettings.php app/Support/PublicFront/PublicFrontConfigValidator.php app/Support/Settings/SettingsPageProfiler.php app/Providers/AppServiceProvider.php app/Support/SettingsLifecycle/SettingsBackupManager.php tests/Feature/SettingsPageProfilerTest.php`
  fixed only `app/Filament/Pages/PublicContentSettings.php`.
- Post-format targeted confirmation:
  `php artisan test --compact tests/Feature/SettingsPageProfilerTest.php tests/Feature/PublicMaintenanceModeTest.php tests/Feature/SettingsImportExportTest.php`
  passed 45 tests, 424 assertions.
- Measurement:
  `SETTINGS_PROFILING=true php artisan tinker --execute='...'`
  run `sp1-20260713005858` completed, but local custom settings failed save
  validation before persist phases.
- Measurement:
  `SETTINGS_PROFILING=true php artisan tinker --execute='...'`
  run `sp1-valid-20260713010154` completed with valid default settings, full
  save phases, and original local settings restored afterward.

## Final Gate

Run 1:

1. Requirements sweep: `git diff --check` passed; `git status --short` showed
   only SP1 expected files; Composer/package diff was empty; IE-1 pending-hash
   grep showed only SP1's own pending commit placeholders.
2. `vendor/bin/pint --test` failed on
   `app/Filament/Pages/PublicContentSettings.php`.

Formatter fix was applied, then targeted tests were rerun as recorded above.

Run 2:

1. Requirements sweep: `git diff --check` passed; `git status --short` showed
   only SP1 expected files; Composer/package diff was empty; IE-1 pending-hash
   grep showed only SP1's own pending commit placeholders.
2. `vendor/bin/pint --test` passed.
3. `vendor/bin/filacheck` passed with 0 issues.
4. `npm run build` passed.
5. `php artisan test` passed: 447 tests, 3,993 assertions, 502.901s.

This handoff section was edited after Run 2 to record the gate. Per prompt, a
final re-entry from Pint is required after this documentation edit; the final
session report records that re-entry result.

## Local Front Check Report

1. Set `SETTINGS_PROFILING=true` locally and clear config cache.
2. Open the admin Public Content Settings page and confirm
   `storage/logs/settings-profiling-YYYY-MM-DD.log` contains
   `settings.read_hydrate`, `form.total_build`, and `payload.load`.
3. Change one field and save; confirm the log contains `save.validation.total`,
   `save.mutate_normalize`, `save.settings_persist`, and
   `settings_saved.listener.total`.
4. Turn profiling off and confirm loading/saving the page no longer writes
   settings profiling lines.
5. Open the Maintenance tab with raw HTML form placement configured and confirm
   the marker field shows `<div data-podtext-maintenance-form></div>`.
6. Click the marker copy action and confirm the clipboard value is exactly
   `<div data-podtext-maintenance-form></div>`.

## Assumptions

- Measurement uses local Livewire test harness scenarios, not a browser timing
  trace.
- Snapshot jobs were faked during the measurement to avoid executing snapshot
  work while still timing scheduling.
- The local custom saved settings payload is useful payload-size evidence but not
  a valid save timing baseline because it fails current settings-page validation.

## Deferred Issues

- Structural performance fixes are deferred until Yoni reviews this report.
- Legacy/custom settings payload cleanup is deferred.
- Prompt 13 dashboard metrics remains not started.

## Current Git Status

Before final commit:

```text
 M .env.example
 M app/Filament/Pages/PublicContentSettings.php
 M app/Providers/AppServiceProvider.php
 M app/Support/PublicFront/PublicFrontConfigValidator.php
 M app/Support/SettingsLifecycle/SettingsBackupManager.php
 M config/logging.php
 M config/settings.php
 M docs/phase-02/ai-development-lessons.md
 M docs/phase-02/current-project-state.md
 M docs/phase-02/import-relations-ie1-handoff.md
 M docs/phase-02/maintenance-form-mp2-handoff.md
 M docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md
?? app/Support/Settings/SettingsPageProfiler.php
?? docs/phase-02/settings-performance-sp1-handoff.md
?? docs/research/settings-performance/
?? tests/Feature/SettingsPageProfilerTest.php
```

## Commit Hash

`c6c9587 perf: instrument settings page and fix maintenance marker copy`.
