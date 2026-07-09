# Public Front v2 Step 10R-S2V MCP Research

Date: 09/07/2026

## Scope

Step 10R-S2V adds private visual snapshots to settings backup rows. It also applies
the S2 audit corrections: retention pruning affects only system backups, and S2's
commit hash is backfilled into the previous handoff/current docs.

## Local Repository Evidence

- Preflight reported a clean `main...origin/main` tree.
- Recent history includes `f694c49 feat: add settings backup versions and restore`.
- The ledger's first pending row is `Step 10R-S2V - Backup visual snapshots`.
- `settings_backup_versions` exists and `settings_backup_snapshots` does not yet exist.
- S2 currently prunes by scope only; S2V must constrain prune to `source = system`.
- Public theme selection persists to `localStorage['podtext-theme']` and toggles the
  root `dark` class from `resources/views/livewire/public/public-header.blade.php`.
- `settings_backups` already exists in the `public_content` group with finite
  thumbnail width, snapshot format, and snapshot theme settings.
- `playwright` is currently in `devDependencies`; S2V must move it to runtime
  dependencies for queue-worker execution.

## Laravel Boost Findings

Tools used: `application_info`, `database_schema`, and `search_docs`.

- Boost confirmed Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Horizon 5.47.2,
  Pest 4.7.4, Tailwind 4.3.2, PHP 8.4, and SQLite.
- Boost schema confirmed `settings_backup_versions` exists and no snapshot table is
  present before S2V.
- Laravel docs confirm queued jobs can be tested with `Queue::fake()` and job logic can
  be tested directly by invoking `handle()`.
- Horizon docs confirm job-level timeouts should stay below worker retry/timeout
  settings. S2V keeps the snapshot job bounded and sequential.
- Laravel Process docs confirm `Process::fake()`, `preventStrayProcesses()`,
  `assertRan()`, `assertRanTimes()`, timeouts, and array command support through the
  installed `PendingProcess` source.
- Laravel filesystem docs confirm private/local storage downloads, fake disks, file
  assertions, and directory deletion patterns.
- Filament docs confirm table `headerActions()`, `recordActions()`, modal schemas,
  `modalSubmitAction(false)`, and slide-over/modal content patterns for custom gallery
  actions.

## FilamentExamples Findings

Access level: `search_examples` snippet/search access only. No source/read/fetch tool
was exposed.

Initial S2V query batch:

- `gallery image column`
- `table image column`
- `record action gallery`
- `slide over image gallery`

Second S2V query batch:

- `table action modal content image`
- `record action slide over view`
- `download action table row`
- `resource image gallery`

Refined S2V query batch:

- `retry failed row action`
- `download zip action`
- `image preview modal action`
- `table action extra modal footer`

Relevant examples and PodText adaptation notes:

- **Table Rendered as a Card Grid**
  - File/class: `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`.
  - Pattern to copy: `ImageColumn` usage with explicit image dimensions and local cell
    attributes.
  - Pattern to avoid: turning the backups table into a card grid. S2V needs a normal
    admin list with one thumbnail identity column.
  - PodText adaptation: add a small home thumbnail column sourced from a private
    streamed route rather than public disk URLs.
- **Custom-Designed Table with ViewColumn Cells**
  - File/class: `v4/tables/table-customized-design-viewcolumn/app/Filament/Resources/Accounts/Tables/AccountsTable.php`.
  - Pattern to copy: custom view columns can render richer table cells when the plain
    column API is not enough.
  - Pattern to avoid: inline CSS-heavy custom tables.
  - PodText adaptation: prefer Filament `ImageColumn` for the row thumbnail and Blade
    only for the gallery modal body.
- **AI-Powered CMS With Laravel AI SDK**
  - File/class: `app/Filament/Actions/GenerateFeaturedImageAction.php`.
  - Pattern to copy: action modal placeholders can render image previews from storage.
  - Pattern to avoid: long-running UI state inside the modal. PodText runs browser work
    in queued jobs.
  - PodText adaptation: "Snapshots" is read-only gallery content with download/retry
    actions, while capture happens in `SettingsBackupSnapshotJob`.
- **Checkbox Matrix For Many-To-Many Sync**
  - File/class: `app/Filament/Resources/Groups/Tables/GroupsTable.php`.
  - Pattern to copy: `Action::make(...)->slideOver()` with dynamic record-scoped schema.
  - Pattern to avoid: unrelated relationship-sync state.
  - PodText adaptation: the gallery action uses a record-scoped Blade view with tabs,
    theme grouping, retry controls, and download links.

## Implementation Implications

- Add a `settings_backup_snapshots` child table with private file paths and finite
  status/kind/format/theme/screen values.
- Keep backup creation fast. Every backup creates pending snapshot rows and dispatches
  a queued job; snapshot failures are recorded per row and never block the backup row.
- Build one `SettingsBackupSnapshotManifest` service to resolve the finite public
  targets from `config('app.url')`, public page classes, and current public data.
- Build one `SettingsBackupSnapshotManager` service to create rows, dispatch jobs,
  stream downloads, retry failed shots, and delete private files before backup row
  deletion/pruning.
- `SettingsBackupManager::prune()` must collect system rows and delete through the
  snapshot manager so private files are removed even when rows are bulk-pruned.
- The Playwright script receives JSON job files and writes to absolute output paths.
  Laravel tests fake `Process` and inspect the JSON contract; CI does not launch a real
  browser.
- The gallery UI can be a modal/slide-over Blade view for S2V. It must expose stable
  test markers for gallery, screen tabs, theme switches, and scrollable full-image
  containers.

## Stop Conditions

- Stop if the tree is dirty before implementation.
- Stop if S2V is not the first pending ledger step.
- Do not implement S1a, S1b, import/export wizard, import locks, or add-only mode.
- Do not make snapshots public; all snapshot files stay on the private local disk and
  are streamed through authenticated admin actions/routes.
