# Public Front v2 Step 10R-S1a MCP Research

Date: 10/07/2026

## Scope

Step 10R-S1a adds the settings export and import wizard core only. This run also
applies the requested S2V audit corrections around after-commit snapshot dispatch,
snapshot timeout configuration, thumbnail capture fidelity, prune file deletion, and
hash backfills.

## Local Repository Evidence

- Preflight reported a clean `main...origin/main` tree.
- Recent history includes `86d21cb feat: add backup visual snapshots` and
  `f694c49 feat: add settings backup versions and restore`.
- The ledger's first pending row is `Step 10R-S1a - Settings export and import wizard
  core`.
- `PublicSettingsPackage`, `SettingsPackageDiff`, and `SettingsBackupManager` already
  own backup serialization, compare, restore, and cache invalidation.
- `SettingsBackupSnapshotJob` currently implements `ShouldQueue`, has a fixed
  `$timeout = 300`, and can be dispatched from inside restore transactions.
- `SettingsBackupManager::prune()` currently deletes snapshot files before bulk
  deleting rows.
- The settings backups table already uses `response()->streamDownload()` for backup
  row downloads.

## Laravel Boost Findings

Tools used: `application_info`, `database_schema`, and `search_docs`.

- Boost confirmed Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Horizon 5.47.2,
  Pest 4.7.4, Tailwind 4.3.2, PHP 8.4, and SQLite.
- Boost schema confirmed the current settings tables, backup tables, snapshot tables,
  jobs table, and cached settings infrastructure.
- Filament docs confirm page-level `getHeaderActions()` and table `headerActions()`
  are the correct extension points for export/import launch actions.
- Filament docs and examples confirm hidden custom pages can render bespoke Livewire
  content while staying out of the central navigation by returning
  `shouldRegisterNavigation() = false`.
- Laravel response docs confirm `response()->streamDownload()` is appropriate for
  emitting JSON packages without writing temporary files.
- Livewire docs confirm uploaded files are validated with normal Laravel file rules,
  tested with `UploadedFile::fake()`, and downloads can return standard Laravel
  responses.
- Laravel queue docs confirm job timeout must be less than the queue connection
  `retry_after`; this run aligns `snapshot_job_timeout < horizon supervisor timeout <
  REDIS_QUEUE_RETRY_AFTER`.
- Laravel transaction docs confirm `DB::afterCommit()` is the correct boundary for
  file cleanup that must not run if a transaction rolls back.
- Laravel Process docs confirm `Process::fake()` and `assertRanTimes()` for snapshot
  process contract tests.

## FilamentExamples Findings

Access level: `search_examples` snippet/search access only. No source/read/fetch tool
was exposed.

Initial S1a query batch:

- `import wizard upload preview`
- `settings page header actions`
- `file upload private disk preview`

Second S1a query batch:

- `custom page wizard`
- `table header action stream download`
- `Livewire selection table toggles`

Refined S1a query batch:

- `streamed download header action`
- `download action response stream`
- `settings export action`

Relevant examples and PodText adaptation notes:

- **Profile Page with Multiple Child Records**
  - File/class: `v4/full-projects/user-profile-section-with-multiple-records/app/Filament/Pages/EditProfile.php`.
  - Pattern to copy: custom Filament page view with page actions and stateful child
    record editing.
  - Pattern to avoid: storing wizard state as Eloquent child records.
  - PodText adaptation: use a hidden Filament page that renders an import Livewire
    component, keeping package source/dry-run/apply state in the component.
- **Multi-Step Invoice Creation Wizard**
  - File/class: `v4/forms/wizard-invoice-form/app/Filament/Resources/Invoices/Pages/CreateInvoice.php`.
  - Pattern to copy: progressive steps and review-before-submit flow.
  - Pattern to avoid: resource create wizard APIs tied to an Eloquent record create
    flow.
  - PodText adaptation: render source, dry-run, and confirmation sections as custom
    Livewire content because the wizard applies selected settings units, not a single
    model.
- **Doctor Availability and Blocked-Time Scheduling**
  - File/class: `v4/full-projects/schedule-for-doctors/app/Filament/Pages/ManageDoctorSchedule.php`.
  - Pattern to copy: hidden/custom management page, URL state, table-like interactive
    selection, and header action usage.
  - Pattern to avoid: broad page-specific business logic mixed directly into Blade.
  - PodText adaptation: keep import comparison/apply logic in support services and
    keep Blade focused on controls and table rendering.
- **Generate Invoice PDF and Email It**
  - File/class: `v4/full-projects/generate-invoice-PDF-and-send-via-email/app/Filament/Resources/Invoices/Tables/InvoicesTable.php`.
  - Pattern to copy: action returns a Laravel download response.
  - Pattern to avoid: public storage URLs for private artifacts.
  - PodText adaptation: export JSON through a streamed admin action and keep backup
    snapshot files private.

## Implementation Implications

- Add `SettingsLifecycleGroups` and `SettingsLifecycleSchema` as the only shape-aware
  lifecycle boundary. UI and import services consume derived unit paths and labels
  from this service.
- Add a small declarative overlay for semantics and future segmentation overrides;
  the drift test must assert overlay paths exist in merged defaults.
- Add a package upgrade pipeline to `PublicSettingsPackage::fromArray()`. Version 1 is
  identity, but the structure allows future path migrations.
- Keep `SettingsPackageDiff` generic while using schema labels/groups for presentation.
- Add one export action helper and mount it on both `PublicContentSettings` and the
  settings backups list header.
- Add one hidden Filament page for the import wizard, launched from the backups list
  header action.
- Add one reusable Livewire selection-table component plus pure selection-table helper
  logic for S1b reuse.
- Apply imports through `SettingsBackupManager::applySelectedImportedPayload()` so
  replace mode uses the same settings save/cache invalidation route as restore.
- Create `before_import` backups before applying selected units; snapshot scheduling
  remains optional and behind the S2V manager.

## Stop Conditions

- Do not implement S1b import locks or add-only mode.
- Do not make `import_locks` settings or migrations in this run.
- Do not add custom CSV import controllers; this step is JSON settings packages only.
- Do not push.
