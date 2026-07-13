# SP1 Settings Performance Implementation Plan

Date: 2026-07-13

## Scope Guard

Implement only:

- Job 0 carried fixes.
- Env-gated profiling instrumentation.
- Local measurement and committed handoff report.

Do not implement structural performance fixes such as splitting settings pages,
lazy-building tabs, scoped validators, or cache reuse beyond instrumentation.
No Composer changes.

## Job 0

1. Fix the maintenance marker copy field on `PublicContentSettings`.
   - Hydrate `maintenance_form_marker` to `MaintenanceForm::MARKER`.
   - Keep the field read-only and not dehydrated.
   - Add focused Pest coverage that the rendered settings page contains the marker
     string and the field/copy payload resolves to exactly `MaintenanceForm::MARKER`.
2. Add deploy notes to the active maintenance/deploy-note docs:
   - after PHP upgrades, re-apply `memory_limit = 512M` in the new FPM php.ini;
   - zero-downtime sites must use `$realpath_root` in nginx fastcgi params or keep
     the post-activation FPM reload;
   - `storage` must be a configured Shared Path on zero-downtime sites.
3. Add the Horizon master/APP_NAME Redis-prefix lesson to
   `docs/phase-02/ai-development-lessons.md`.
4. Backfill IE-1 commit hash `6f1cea7` into active docs still saying pending.

## Profiler

Add `App\Support\Settings\SettingsPageProfiler`:

- `isEnabled(): bool` reads `config('settings.profiling.enabled', false)`.
- `measure(string $phase, string $requestKind, callable $callback, ?int $payloadBytes = null): mixed`
  returns the callback result and logs only when enabled.
- `record(...)` writes `Log::channel('settings_profiling')->info(...)`.
- `payloadBytes(mixed $payload): int` JSON-encodes with `JSON_THROW_ON_ERROR`,
  `JSON_UNESCAPED_UNICODE`, and `JSON_UNESCAPED_SLASHES`, returning byte length.

Config:

- Add `settings.profiling.enabled` mapped from `SETTINGS_PROFILING`, default false.
- Add a dedicated `settings_profiling` daily channel in `config/logging.php`.
- Add `SETTINGS_PROFILING=false` to `.env.example`.

## Instrumentation Boundaries

`PublicContentSettings`:

- `fillForm()` override mirrors installed SettingsPage behavior and measures:
  settings read/hydrate and load payload size.
- `form()` measures total form build and records load payload size after the schema
  is assembled.
- `withImportLockSection()` records top-level section build timings by section key.
- Top-level tabs are recorded by wrapping tab schema arrays at the tab boundary.
- `updatedInteractsWithSchemas()` records Livewire update total and post-update
  payload size.
- `save()` override mirrors installed SettingsPage behavior and measures:
  total validation (`form->getState()`), mutate/normalize,
  settings save/persist, and total save round trip.
- `mutateFormDataBeforeSave()` records the full validator call as part of mutate
  details while leaving the validator to record per-group timings.

`PublicFrontConfigValidator`:

- Wrap registry defaults build.
- Wrap each top-level group normalization in `SettingsPageProfiler::measure()`
  with phases like `validator.group.card_templates`.

`AppServiceProvider` / lifecycle managers:

- Wrap the `SettingsSaved` listener with a total listener phase.
- Record backup creation separately around `SettingsBackupManager::createSystem()`.
- Record snapshot scheduling around `SettingsBackupSnapshotManager::scheduleForBackup()`.
- Keep cache forget and instance forget behavior unchanged.

## Tests

Add focused tests, likely in a new `tests/Feature/SettingsPageProfilerTest.php`
or near existing settings tests:

- Disabled by default: `Log::fake()` plus a representative `record()`/page action
  proves no `settings_profiling` log writes.
- Enabled: `Log::fake()` and Livewire mount/save prove named phases are written.
- Marker fix: rendered settings section contains `MaintenanceForm::MARKER`; the
  marker field state/copy payload equals `MaintenanceForm::MARKER`.

Use targeted tests while iterating. Full suite runs only as final gate, last, once
green on final code state.

## Measurement Report

Create `docs/phase-02/settings-performance-sp1-handoff.md` with:

- requirement sweep;
- files changed;
- tests added/updated;
- every command run and result;
- FilaCheck result;
- settings performance report with phase table, top 3 cost centers, payload sizes,
  ranked fix plan, and TS2/test-cost overlap;
- manual front-check steps;
- current git status before commit;
- `## Commit hash`.

## Final Gate Order

After all code, tests, docs, and handoff contents are stable:

1. Requirements sweep.
2. `vendor/bin/pint --test`.
3. `vendor/bin/filacheck`.
4. `npm run build`.
5. Full `php artisan test` last.

If any file changes after a green full suite, re-enter from Pint and record every
run in the handoff.
