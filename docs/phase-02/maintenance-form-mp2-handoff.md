# MP2 Forms Page and Maintenance Form Handoff

> **Historical shipped-state notice — 2026-07-16:** This handoff remains
> authoritative for MP2. Future form-definition ownership is superseded by
> ARCH1's versioned Public Form Resources; maintenance references must migrate
> from semantic form keys during cutover. See
> `docs/research/settings-performance/07-sp3d-pre-research.md`.

Date: 2026-07-12

## Scope

Implemented `prompts/pre-13-prompts/maintenance-form-mp2-codex-prompt.md` as
the single session step.

No Composer changes were made.

## Implemented

- Added `ManagePublicForms`, a dedicated admin settings page under Site management
  for editing only `PublicContentSettings::$public_forms`.
- Removed public-form editing from the large Public Content Settings page while
  preserving the `public_forms` settings slice on unrelated saves.
- Added `PublicFormsSettingsForm` as the shared form-definition schema for the new
  page.
- Added `SettingsItemCloner`, a collection-agnostic array-item clone helper. The
  form clone action deep-copies fields, mints a unique numeric-suffix key, applies
  the translated copy suffix, and starts the clone disabled.
- Kept public form storage in settings. No `PublicFormDefinition` model/table was
  added.
- Added maintenance settings keys: `form_key`, `form_location`, and
  `form_position`, with defaults, validator normalization, lifecycle labels, and a
  settings migration.
- Added maintenance-tab controls that select enabled public forms, choose rendered
  page versus raw HTML placement, choose before/after placement for rendered pages,
  and expose the app-owned raw HTML marker:
  `<div data-podtext-maintenance-form></div>`.
- Added `MaintenancePageRenderer` and a server-rendered plain Blade form partial for
  503 maintenance responses.
- Added a dedicated plain POST route for maintenance form submissions:
  `public.maintenance-form.submit` at `POST /maintenance/form`.
- Reused `PublicFormPayloadValidator`, the existing honeypot/rate limit mechanics,
  and `PublicFormSubmission` storage through `PublicFormSubmitter`.
- Added stale-CSRF handling that returns the maintenance response with a translated
  retry message instead of a bare 419 page.
- Added raw HTML marker replacement for the first marker only, with fallback form
  append and marker-missing warning behavior.
- Added English and Hebrew labels/helpers/messages.

## Route Guard

Route: `public.maintenance-form.submit` (`POST /maintenance/form`).

Why it is safe:

- It remains in the normal web middleware stack, so CSRF still applies.
- It is not mounted inside the public panel maintenance interception path, so it can
  accept submissions while other public URLs remain 503.
- The controller aborts unless maintenance is enabled.
- The controller aborts unless the configured maintenance form resolves to an
  enabled public form definition.
- Payload validation, honeypot detection, rate limiting, IP/user-agent hashing, and
  storage reuse the same public form submission path as the existing modal.
- No Livewire update route is exempted.

## Requirement Classification

- Implemented: dedicated forms page, settings-backed storage, removal from the big
  settings page, clone action/helper, maintenance settings keys/migration/admin UI,
  rendered-page and raw-HTML form embedding, guarded POST endpoint, stale-CSRF retry
  response, translations, lifecycle import/export coverage, current-state update,
  ledger row, research note, implementation plan, deploy note, and this handoff.
- Already existed: `PublicContentSettings::$public_forms`,
  `PublicFormDefinitionRegistry`, `PublicFormModal`, `PublicFormPayloadValidator`,
  `PublicFormSchemaFactory`, `PublicFormSubmission`, `PublicFormSubmissionResource`,
  and MP1 maintenance shell/middleware/admin bypass.
- Deferred by prompt: new form field types, form emails, form uploads, moving forms
  to database tables, Livewire on the maintenance shell, and public panel work beyond
  maintenance rendering.
- Not applicable: Composer/package changes, remote pushes, `filacheck --fix`, and
  database-native form definition models.
- Blocked: none.

## Files Changed

- Admin/pages/navigation: `app/Filament/Pages/ManagePublicForms.php`,
  `app/Filament/Pages/PublicContentSettings.php`,
  `app/Filament/Support/AdminNavigationOrder.php`,
  `app/Filament/Support/PublicFormsSettingsForm.php`.
- Maintenance route/rendering: `app/Http/Controllers/MaintenanceFormSubmissionController.php`,
  `app/Http/Middleware/RenderMaintenanceMode.php`, `bootstrap/app.php`,
  `routes/web.php`, `resources/views/public/maintenance.blade.php`,
  `resources/views/public/partials/maintenance-form.blade.php`.
- Support/settings: `app/Support/Settings/SettingsItemCloner.php`,
  `app/Support/PublicFront/Forms/PublicFormSubmitter.php`,
  `app/Support/PublicFront/Maintenance/MaintenanceForm.php`,
  `app/Support/PublicFront/Maintenance/MaintenancePageRenderer.php`,
  `app/Support/PublicFront/PublicFrontConfigRegistry.php`,
  `app/Support/PublicFront/PublicFrontConfigValidator.php`,
  `database/settings/2026_07_12_000002_add_public_maintenance_form_settings.php`.
- Labels: `lang/en/admin.php`, `lang/he/admin.php`, `lang/en/public.php`,
  `lang/he/public.php`.
- Tests: `tests/Feature/PublicFormsSubmissionsTest.php`,
  `tests/Feature/PublicMaintenanceModeTest.php`,
  `tests/Feature/SettingsImportExportTest.php`.
- Docs: `docs/research/maintenance-form/00-mp2-research.md`,
  `docs/phase-02/maintenance-form-mp2-implementation-plan.md`,
  `docs/phase-02/maintenance-form-mp2-handoff.md`,
  `docs/phase-02/current-project-state.md`,
  `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`,
  `docs/phase-02/admin-navigation-nav1-handoff.md`,
  `docs/phase-02/public-front-v2-step10r-mp1-handoff.md`.

## Tests Added Or Updated

- Public form settings tests now save definitions through `ManagePublicForms`.
- Added preservation coverage so unrelated Public Content Settings saves do not wipe
  `public_forms`.
- Added clone-helper coverage for unique keys, disabled clones, and deep-copied
  fields.
- Added maintenance rendered-page form rendering, successful guest submission,
  invalid payload errors, raw marker insertion, missing-marker fallback, disabled
  endpoint guards, and stale-CSRF retry rendering.
- Added lifecycle export/import coverage for `public_forms.definitions` and the new
  maintenance form keys.

## Commands Run

- Preflight: `git status --short --branch`; `git log --oneline -8`.
- Syntax: `php -l` on new/edited PHP entry points including
  `MaintenanceFormSubmissionController`, `MaintenancePageRenderer`,
  `PublicContentSettings`, `PublicFormsSettingsForm`, and `bootstrap/app.php`.
- Targeted iteration:
  `php artisan test tests/Feature/PublicFormsSubmissionsTest.php tests/Feature/PublicMaintenanceModeTest.php tests/Feature/SettingsImportExportTest.php`
  failed first, then passed after focused fixes.
- Targeted confirmation:
  `php artisan test tests/Feature/PublicMaintenanceModeTest.php tests/Feature/SettingsImportExportTest.php`
  passed: 42 tests, 402 assertions.
- Final gate outcomes: gate outcomes were reported only in the MP2 session chat.
  The TS1 kickoff message did not provide the exact suite line, so IE-1 closes
  this as a documented historical gap rather than inventing unrecoverable MP2
  test/assertion counts.

## Tooling Notes

- Laravel Boost was available. Used `application_info`, `database_schema`, and
  version-aware `search_docs` for Filament settings pages/repeaters/actions,
  Laravel CSRF/rate limiting, and installed package context.
- FilamentExamples MCP exposed search-only access in this session. Searches covered
  SettingsPage/custom settings forms, repeater item actions, schema actions, builder
  blocks, and missing-marker/progressive disclosure patterns. No source/detail tool
  was available, so this handoff records the access level as search-only.

## Deploy Notes

- No new environment variables are required.
- Forge deploy scripts must run `npx playwright install chromium` after the npm
  install/build step so Playwright browser binaries stay aligned after package version
  bumps. This was also added to the MP1 maintenance deploy notes.
- After PHP version upgrades, re-apply `memory_limit = 512M` in the new FPM
  `php.ini`; the distro default can return to `128M`.
- Zero-downtime sites must either pass `$realpath_root` in nginx FastCGI params or
  keep the post-activation FPM reload in the deploy script so workers do not serve
  stale release paths.
- `storage` must be configured as a Shared Path on zero-downtime sites.

## Commit hash

Previous run NAV1: `e59705b feat: restructure admin navigation groups and defer badges`.

Final MP2 commit hash: `465967f feat: add forms management page and maintenance form embedding`.

## Local Front Check Report

1. Open the new Forms page under `ניהול אתר` and create a public form with a name,
   key, enabled toggle, and at least one required email/text field.
2. Open Public Content Settings -> Maintenance and confirm the created enabled form
   appears in the maintenance form select.
3. Enable maintenance, choose rendered page placement, choose before content, and
   visit `/` as a guest: confirm the form appears above the title and the response is
   still 503.
4. Submit valid input as a guest: confirm the maintenance page returns with the
   translated thank-you state, a `PublicFormSubmission` row is created, and the
   `רשומות טפסים` badge rises.
5. Change rendered page placement to after content and confirm the form appears below
   the maintenance body.
6. Switch to raw HTML placement, copy the marker via the copy icon, paste it into the
   raw override, and confirm the form renders exactly at that marker position.
7. Remove the marker from the raw override and confirm the form falls back after the
   raw HTML and the marker-missing warning is visible in the settings UI.
8. Submit wrong input and confirm validation errors stay on the maintenance page with
   the submitted values preserved.
9. Confirm `/`, `/search`, `/podcasts`, and an episode URL still return the maintenance
   503 response while `POST /maintenance/form` handles the form.
10. Disable maintenance and confirm the form endpoint returns 404.
11. Confirm the forms editor is gone from the large Public Content Settings page.
12. Check Hebrew RTL, light mode, and dark mode for the new Forms page, maintenance
   settings controls, rendered maintenance form, and raw-HTML fallback.

## Assumptions

- The new Forms page belongs under the NAV1 Site management group by default so Yoni
  can re-place it cheaply later if needed.
- The maintenance form route may exist globally, but accepting submissions only while
  maintenance is enabled and an enabled form is configured keeps it safe.
- MP2 final gate output was not written into this committed handoff before the MP2
  commit; IE-1 closes that as documented by preserving the historical gap instead
  of inventing missing numbers.

## Deferred Issues

- Public form file uploads and notification emails remain deferred.
- Card-template clone reuse of `SettingsItemCloner` is intentionally not wired in this
  run.
- Manual browser checks above remain operator checks for Yoni/local review.
