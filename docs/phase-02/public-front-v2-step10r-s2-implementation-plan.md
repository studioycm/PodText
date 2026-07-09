# Public Front v2 Step 10R-S2 Implementation Plan

## Selected Step

Step 10R-S2 - Settings backup versions and restore flow.

## Dependencies

- Step 10R-P1 is complete and provides the public-front config cache helper.
- The ledger's first pending mini-step is S2.
- S2V and S1 remain unimplemented in this run. S2V receives only the first-run docs
  insertion plus the `settings_backups` settings group needed later.

## Current Repo Evidence

- `PublicContentSettings` is the single Spatie settings class for public content.
- `PublicFrontConfigRegistry` and `PublicFrontConfigValidator` own finite JSON group
  defaults and normalization.
- `AppServiceProvider` already listens for `SettingsSaved` on `PublicContentSettings`.
- `AdminNavigationOrder` is the central completeness gate for all admin resources and
  pages.
- UX1 global modal width and section defaults are active; S2 actions do not need local
  width overrides.

## Research Summary

- Laravel `DB::transaction()` is the restore boundary.
- Laravel `response()->streamDownload()` is appropriate for JSON backup downloads.
- Filament 5 table header and record actions support modal schemas, confirmation
  actions, and read-only modal actions.
- FilamentExamples showed working patterns for settings page actions, custom modal
  actions, `TextEntry`-based read-only modal content, and confirmed record actions.
- Installed Spatie source confirms `SettingsSaved` fires after `Settings::save()` and
  `getPropertiesInGroup()` returns decoded group payloads.

## Implementation Plan

1. Add schema and settings migrations.
   - Create `settings_backup_versions` with scope, label, payload JSON, checksum,
     payload hash, source, optional creator, and timestamps.
   - Add `settings_backups` to the `public_content` settings group with defaults for
     thumbnail width, formats, and themes.
2. Add finite settings support.
   - Add `settings_backups` to `PublicContentSettings`.
   - Add registry defaults, schema mapping, finite vocabularies, and validator
     normalization.
3. Add backup lifecycle support.
   - Add `SettingsBackupSource` enum for `manual`, `before_import`, `before_restore`,
     and `system`.
   - Add `SettingsBackupVersion` model.
   - Add `PublicSettingsPackage` for verbatim package capture, canonical payload JSON,
     checksum validation, and JSON export.
   - Add `SettingsPackageDiff` for grouped dot-path added/removed/changed summaries.
   - Add `SettingsBackupManager` for manual/system/before-restore backups, retention,
     restore, and cache invalidation.
4. Wire system backups.
   - Extend the existing `SettingsSaved` listener for `PublicContentSettings` to create
     a deduped system backup and preserve the P1 cache invalidation behavior.
5. Add admin UI.
   - Add `SettingsBackupResource` with only the list page.
   - Table columns: created_at day-first in Asia/Jerusalem, source badge, label,
     shortened payload hash, and JSON package size.
   - Header action: create backup with an optional label.
   - Row actions: stream JSON download, compare current settings to the backup, restore
     with confirmation and diff summary, and delete with confirmation.
6. Add tests.
   - Manual and system backup creation.
   - System hash dedupe and retention prune.
   - Package download shape/checksum.
   - Diff changed scalar and nested setting keys.
   - Restore round-trip with before_restore backup and cache invalidation.
   - Guest/admin access and navigation completeness.
   - Public bounded harness remains green.
7. Docs and handoff.
   - Insert the S2V ledger row and enhancement-plan section.
   - Mark S2 complete in the ledger/current state and set next step to S1 unless Yoni
     explicitly selects S2V.
   - Add the S2 handoff with commit hash and local front check sections.

## Files To Change

- `database/migrations/*_create_settings_backup_versions_table.php`
- `database/settings/*_add_public_settings_backup_settings.php`
- `app/Models/SettingsBackupVersion.php`
- `app/Enums/SettingsBackupSource.php`
- `app/Support/SettingsLifecycle/*`
- `app/Filament/Resources/SettingsBackups/*`
- `app/Settings/PublicContentSettings.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Providers/AppServiceProvider.php`
- `app/Filament/Support/AdminNavigationOrder.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `tests/Feature/SettingsBackupsTest.php`
- `tests/Feature/AdminPhase02ResourcesTest.php`
- S2 research/plan/handoff/current-state/ledger/enhancement-plan docs

## Risks

- Spatie settings rows are not created by normal Laravel migrations; S2 needs a settings
  migration and app instance cache clearing in tests after settings changes.
- Restore triggers `SettingsSaved`; system backup dedupe must prevent duplicate backup
  rows for the restored payload.
- The backup table may not exist during deploy before migrations; system backup creation
  should no-op until the table exists.
- S2V thumbnail/gallery checks belong to the later S2V step and should not leak into S2
  code or tests.

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
feat: add settings backup versions and restore
```
