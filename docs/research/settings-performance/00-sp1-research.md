# SP1 Settings Performance Research

Date: 2026-07-13

## Prompt Scope

Run `prompts/pre-13-prompts/settings-performance-sp1-codex-prompt.md` as the
single session step.

Deliver evidence only for settings-page performance, plus Job 0 carried fixes.
Do not implement structural performance fixes. No Composer changes.

## Preflight

- `git status --short --branch` reported `## main...origin/main [ahead 1]`.
- Recent commits include `5508af4 docs: add SP1 settings profiling prompt`,
  `6f1cea7 feat: add relation import modes and tag export scope`, and prior
  TOOLS1/TS1 commits.
- No unexpected dirty PHP, Blade, migration, test, config, or app-code changes
  existed before implementation.
- IE-1 commit `6f1cea7` is present at HEAD-2 and needs backfilling into active
  docs that still say pending.
- Prompt 13 has not started in the active mini-step ledger.

## Installed Versions

Laravel Boost `application_info` was available and reported:

- PHP 8.4
- Laravel 13.19.0
- Filament 5.6.7
- Livewire 4.3.3
- Horizon 5.47.2
- Pest 4.7.4
- Tailwind CSS 4.3.2

## Boost Research

Boost `search_docs` was used before code changes for:

- Filament 5 SettingsPage/forms/tabs/actions.
- Laravel 13 logging custom daily channels and `Log::channel()`.
- Livewire 4 testing/lifecycle context.
- Filament 5 schema action testing.

Relevant result:

- Laravel logging supports dedicated configured channels and `Log::channel()`.
- Filament Tabs docs show normal tab schemas, but the docs pass did not expose a
  native lazy tab-schema API for Filament 5.6.
- Filament action testing docs support `TestAction::make(...)->schemaComponent(...)`
  for component actions.

## FilamentExamples Research

FilamentExamples MCP was available with `search_examples` only. No source/read/
detail fetch tool was exposed.

Queries covered:

- settings pages with tabs/forms/actions;
- custom SettingsPage form save boundaries;
- placeholder/copy action patterns;
- custom page schema/state patterns.

Useful examples:

- `v4/forms/edit-profile-custom-forms/app/Filament/Pages/EditProfile.php`
  showed explicit page form methods, page actions, and `fill()`/`getState()` save
  flow. Pattern copied: keep page-owned schema/action boundaries explicit.
  Pattern avoided: splitting the monolith into multiple forms/pages in SP1,
  because structural fixes are out of scope.
- `v4/full-projects/hotel-management-bookings/app/Filament/Hotel/Pages/MyHotel.php`
  showed normal custom page save handling. Pattern copied: page-level save
  instrumentation is acceptable when it preserves the same flow.
- Search snippets did not expose a native copyable marker workaround, so local
  Filament source was inspected.

## Local Vendor Findings

- `vendor/filament/spatie-laravel-settings-plugin/src/Pages/SettingsPage.php`
  implements `save()` as:
  `beforeValidate` -> `$this->form->getState()` -> `afterValidate` ->
  `mutateFormDataBeforeSave()` -> `beforeSave` -> settings `fill()`/`save()` ->
  `afterSave` -> transaction commit -> notification/redirect.
- `vendor/filament/forms/src/Components/TextInput.php` implements `copyable()` by
  adding `TextInput\Actions\CopyAction`.
- `vendor/filament/forms/src/Components/TextInput/Actions/CopyAction.php` writes
  the field state to `window.navigator.clipboard.writeText(...)`.
- Therefore the MP2 marker-copy bug is consistent with the marker input relying
  on an unhydrated/dehydrated-false form state. The fix should hydrate the field
  to `MaintenanceForm::MARKER` and test that exact state/payload.

## Application Findings

- `App\Filament\Pages\PublicContentSettings` is the monolith settings page.
- `PublicContentSettings::form()` builds one vertical Tabs component with top-level
  tabs for homepage, display, item page, podcasts, contributors, menu, about,
  maintenance, and advanced settings.
- Every top-level section is passed through `withImportLockSection(Section $section,
  string $group, string $key)`, which is the clean page-owned instrumentation
  boundary for section schema build timings.
- `mutateFormDataBeforeFill()` reads/hydrates config through
  `PublicFrontConfigReader::fromArray($data)->config()` and builder normalization.
- `mutateFormDataBeforeSave()` normalizes upload/menu/maintenance state, preserves
  hidden public forms and maintenance fields, then runs
  `PublicFrontConfigValidator::validate($data)`.
- `PublicFrontConfigValidator::validate()` uses one top-level loop and a `match`
  for each settings group: `card_templates`, `menu_config`, `about_page`,
  `public_forms`, `route_labels`, `display_defaults`, `default_images`,
  `transcription_policy`, `item_page`, `podcasts_page`, `contributors_page`,
  `settings_backups`, `import_locks`, and `maintenance`.
- `AppServiceProvider` listens for `SettingsSaved` and, for
  `PublicContentSettings`, clears the public-front config cache, calls
  `SettingsBackupManager::createSystem()`, and forgets render-context/policy
  instances.
- `SettingsBackupManager::create()` calls
  `SettingsBackupSnapshotManager::scheduleForBackup(...)` when snapshots should
  be scheduled.

## Instrumentation Shape

- Add `App\Support\Settings\SettingsPageProfiler` as a no-dependency boundary.
- Map `SETTINGS_PROFILING` to `config('settings.profiling.enabled')`; default off.
- Add a `settings_profiling` daily log channel mirroring `import_export`.
- When disabled, the profiler must return before work and before any log-channel
  resolution. A test should prove no log write happens while disabled.
- When enabled, write one structured `info` line per phase with at least:
  `phase`, `milliseconds`, `request_kind`, and `payload_bytes` when relevant.
- Use page-owned/schema-owned/app-owned boundaries only. Do not modify vendor code.

## Measurement Plan

Measure locally with `SETTINGS_PROFILING=true` equivalent config:

1. Three initial page loads: cold plus two warm loads.
2. One no-op save.
3. One single-field change save.
4. One live interaction on a heavy tab.

Record phase tables, top three cost centers, Livewire payload sizes, and a ranked
fix plan in `docs/phase-02/settings-performance-sp1-handoff.md`.

## Manual Checks Required

- Enable profiling locally, load the settings page, and confirm named phases are
  logged.
- Turn profiling off and confirm no profiler lines are written.
- Confirm the maintenance marker snippet displays
  `<div data-podtext-maintenance-form></div>` and the copy action copies the same
  constant.
