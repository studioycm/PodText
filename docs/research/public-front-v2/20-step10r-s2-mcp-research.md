# Public Front v2 Step 10R-S2 MCP Research

Date: 09/07/2026

## Scope

Step 10R-S2 adds public content settings backup versions, package serialization,
automatic system backups on settings saves, retention pruning, compare/download/restore
admin actions, and the settings_backups JSON group needed by the later S2V step.

## Local Repository Evidence

- Preflight `git status --short --branch` reported clean `main...origin/main`.
- Recent history has `7f15bdc feat: add settings backup, snapshots, and import/export functionality`;
  that commit only added the new runner prompt.
- `php artisan migrate:status` reports all application and settings migrations through
  `2026_07_09_000007_add_public_custom_color_settings` as ran.
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md` lists S2 as the first
  pending mini-step after completed P1.
- `PublicContentSettings` currently has 23 scalar settings and 11 array groups. S2 adds
  `settings_backups`, so new packages produced after this migration include 35
  properties.
- `AppServiceProvider` already listens for `Spatie\LaravelSettings\Events\SettingsSaved`
  on `PublicContentSettings` and invalidates the P1 public-front cache boundary.
- `PublicFrontConfigCache::settingsMigrationWatermark()` and `forget()` are available
  and should be reused by the package serializer and restore path.

## Laravel Boost Findings

Tools used: `application_info`, `database_schema`, and `search_docs`.

- Boost confirmed installed versions: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3,
  Pest 4.7.4, Tailwind 4.3.2, local SQLite.
- Boost schema confirmed there is no existing `settings_backup_versions` table.
- Laravel docs confirm `DB::transaction()` commits on success and rolls back on
  exception.
- Laravel docs confirm `response()->streamDownload()` streams generated content as a
  downloadable response without writing a temporary file.
- Filament docs confirm table `headerActions()` and `recordActions()` can use custom
  `Action` objects with modal `schema()`, `action()`, `requiresConfirmation()`,
  `modalSubmitAction(false)`, and `modalCancelActionLabel()`.
- Filament docs confirm action schemas can use read-only `TextEntry` components inside
  modal-style view actions.
- Boost did not return useful Spatie Settings docs for `getPropertiesInGroup()` or
  `SettingsSaved`, so the installed vendor source was inspected directly.

## Installed Spatie Settings Evidence

- `Spatie\LaravelSettings\Settings::save()` persists through the mapper, refreshes the
  settings instance, then dispatches `SettingsSaved`.
- `SettingsSaved` carries the saved `Settings` instance.
- `DatabaseSettingsRepository::getPropertiesInGroup()` reads all settings rows in the
  group and returns decoded payload values keyed by setting name.
- This supports verbatim backup capture from the repository and transactional restore
  through the `PublicContentSettings` class.

## FilamentExamples Findings

Access level: `search_examples` snippet/search access only. No source/read/fetch tool
was exposed.

Initial query batch:

- `settings page header actions`
- `import wizard upload preview`
- `resource gallery image column`

Second query batch:

- `table action modal form`
- `resource record action confirmation`
- `download action json file`
- `compare modal infolist`

Refined query batch:

- `extra modal footer actions action class`
- `header action modal form table`
- `action modalSubmitAction false TextEntry`

Relevant examples and PodText adaptation notes:

- **Multi-Step Invoice Creation Wizard**
  - File/class: `app/Filament/Pages/ManageSettings.php`.
  - Pattern to copy: settings page actions and notifications remain thin UI wrappers
    around persistence.
  - Pattern to avoid: ad-hoc settings model storage; PodText must use Spatie settings.
  - PodText adaptation: S2 keeps backup creation/restore in service classes and exposes
    them through table actions.
- **AI-Powered CMS With Laravel AI SDK**
  - File/class: `app/Filament/Actions/SuggestTitleAction.php`,
    `GenerateFeaturedImageAction.php`.
  - Pattern to copy: custom `Action` setup, modal schemas, extra footer actions, and
    stateful action callbacks with notifications.
  - Pattern to avoid: AI-specific long-running action state.
  - PodText adaptation: use simple header/row actions for create, compare, download,
    and restore.
- **Repair Salon CRM with Two Panels**
  - File/class: `app/Filament/Resources/Orders/Tables/OrdersTable.php`,
    `OrderInfolist.php`.
  - Pattern to copy: record actions with `requiresConfirmation()` and infolist
    `TextEntry` components for readable modal summaries.
  - Pattern to avoid: unrelated order domain status mutations.
  - PodText adaptation: restore confirmation shows a compact diff summary, while the
    compare action shows grouped dot-path details.
- **Checkbox Matrix For Many-To-Many Sync**
  - File/class: `app/Filament/Resources/Groups/Tables/GroupsTable.php`.
  - Pattern to copy: custom row actions can mount dynamic modal schemas from a record.
  - Pattern to avoid: wide slide-over override unless needed; UX1 global modal defaults
    already apply in PodText.
  - PodText adaptation: compare modal schema is computed from the selected backup row.

## Implementation Implications

- Store the full JSON package in `payload_json`; compute `checksum` and `payload_hash`
  from canonical sorted-key JSON of the package payload.
- Keep backup capture verbatim via `getPropertiesInGroup(PublicContentSettings::group())`.
- Validate/normalize only when applying settings back to `PublicContentSettings`.
- Add a focused package serializer and backup manager under
  `App\Support\SettingsLifecycle`.
- Add `SettingsBackupResource` to admin navigation near Public Content Settings.
- Use automatic system backup creation in the existing `SettingsSaved` listener after
  settings persistence and before/with cache invalidation.
- Guard system backup creation for pre-migration deployments by no-oping when the
  backup table is absent.
- S2V remains documentation-only in this run except for the `settings_backups` settings
  group and migration required by S2 package shape.

## Stop Conditions

- Stop if the repository is dirty before implementation.
- Stop if S2 is not the first pending ledger step.
- Stop if the implementation requires S2V visual snapshots, S1 import/export, or
  importer workbench code.
- Stop if backup restore would need raw repository writes instead of
  `PublicContentSettings::save()`.
