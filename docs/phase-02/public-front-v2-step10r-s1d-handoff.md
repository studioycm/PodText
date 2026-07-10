# Public Front v2 Step 10R-S1d Handoff

## Scope

Step 10R-S1d is complete pending the final local commit. It added structured settings
import reports and finished the MP1 maintenance, panel, Horizon, and performance audit
items. The Importer Workbench was not started.

## What Changed

- Settings imports now return a `SettingsImportReport` value object with mode, source
  label, generated time, before-import backup id, warnings, and grouped outcome rows.
- `settings_backup_versions.import_report` stores the report on the before-import
  backup row created by the import.
- The import wizard shows dry-run summary chips, a locked-row filter, an expandable
  locked-excluded list with a lock-manager link, and a grouped completion report.
- Settings Backups gained a visible-when-present Import report row action that renders
  the persisted grouped report read-only.
- Sensitive lifecycle semantics now cover `maintenance.enabled`,
  `maintenance.title`, `maintenance.rich_html`, and
  `maintenance.raw_html_override`. The three content fields still keep `front_text`;
  `sensitive` is additive. Sensitive units are selectable but never preselected.
- Maintenance settings preserve stored trusted HTML/raw override values during
  unrelated settings saves, including hidden/absent-field save paths.
- The public maintenance middleware is registered as its own persistent public-panel
  middleware call, leaving the default Filament stack untouched.
- `viewHorizon` is now defined through the admin panel access contract.

## MP1 Hardening Audit

1. Unrelated-save preservation was missing and is implemented. Stored
   `maintenance.rich_html` and `maintenance.raw_html_override` are preserved
   byte-identical during unrelated settings-page saves.
2. Persistent middleware was missing and is implemented. `RenderMaintenanceMode` was
   moved out of the default middleware array and attached with
   `isPersistent: true`.
3. Admin bypass now has Livewire-interaction coverage while maintenance mode is
   enabled.
4. Sensitive lifecycle semantics are implemented for all four maintenance fields.
   Imported maintenance content remains opt-in only because sensitive rows are
   deselected by default while still selectable.
5. `maintenance.rich_html` has a regression assertion proving it persists as an HTML
   string after a settings-page save.
6. The 503 response construction was already correct: `response()->view(..., 503)`
   with `Retry-After`. S1d added shell metadata assertions for `lang="he"`,
   `dir="rtl"`, charset, and viewport.

## Panel Audit

- Verified correct: both panels keep the Filament install-default middleware stack in
  the expected order: EncryptCookies, AddQueuedCookiesToResponse, StartSession,
  AuthenticateSession, ShareErrorsFromSession, PreventRequestForgery,
  SubstituteBindings, then Filament internals.
- Verified correct: the public panel has no `authMiddleware()` because it is the guest
  panel; the admin panel gates through Filament Authenticate.
- Accepted and documented: public browsing routes are not app-throttled. Filament
  login has built-in rate limiting, public browsing throttling belongs at the
  server/nginx layer, and a public-panel throttle middleware is the extension point if
  app-level throttling is ever required.
- Verified behavior: default panel middleware runs on first page load only;
  `isPersistent: true` re-applies middleware on Livewire requests. The maintenance
  middleware relies on being the only persistent public-panel middleware.
- Deploy note: set `SESSION_SECURE_COOKIE=true` in the production environment.
- Guardrail: `User::canAccessPanel()` currently admits every authenticated user to the
  admin panel. This is acceptable only while the only account is Yoni's; an `is_admin`
  gate must land before any non-admin account type exists.

## Test Performance

`php artisan test --profile` passed but emitted only the compact JSON summary.
`vendor/bin/pest --profile --compact --colors=never` then passed 368 tests / 3447
assertions in 447.854 seconds and produced the slow list recorded in the research
note.

Dominant cost is repeated full render/save cycles through the large Public Content
Settings page plus settings writes and validators. The Browser suite is not empty, so
excluding it from the default run was not a safe S1d quick win. `php artisan test
--parallel` appears safe as one command because each Paratest process receives its own
SQLite `:memory:` database, but adopting it should be a later Yoni-approved choice.

## Verification

- `php artisan migrate --no-interaction`
- `php artisan test --profile`
- `vendor/bin/pest --profile --compact --colors=never`
- `php -l` on dirty PHP files
- `php artisan test tests/Feature/PanelAuthHardeningTest.php`
- `php artisan test tests/Feature/PublicMaintenanceModeTest.php`
- `php artisan test tests/Feature/SettingsImportExportTest.php`
- `php artisan test tests/Feature/SettingsBackupsTest.php`
- Duplicate-key scan for edited `lang/en/admin.php` and `lang/he/admin.php`
- Final sequential gate for this handoff: `vendor/bin/pint --dirty --format agent`,
  `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/filacheck`,
  `npm run build`, `git diff --check`

## Commit hash

Previous completed MP1 commit: `8458a5d feat: add maintenance mode page and settings`.

S1d commit message: `feat: add import result report and maintenance hardening`.

The final S1d commit hash is reported after the local commit because this handoff is
part of that commit.

## Local Front Check Report

1. Lock one import unit, prepare one bad scalar row, and run a settings import dry run:
   summary chips show locked and error counts.
2. Apply the import: the completion report groups applied, skipped locked, skipped
   exists, skipped unchanged, and error rows with translated labels/reasons.
3. Open Settings Backups: the before-import row exposes the Import report action, and
   rows without reports do not.
4. Open Import report: the modal shows the same grouped report as the completion step.
5. Confirm sensitive maintenance units are deselected by default while remaining
   manually selectable.
6. Enable maintenance and interact as a logged-in admin on a Livewire public surface:
   the admin bypass still reaches the component.
7. Confirm the standalone maintenance shell remains Hebrew RTL and declares charset
   and viewport metadata.
8. Confirm light/dark report and maintenance surfaces remain readable.
