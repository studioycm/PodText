# MP2 Maintenance Form Implementation Plan

Date: 2026-07-12

## Goal

Implement `prompts/pre-13-prompts/maintenance-form-mp2-codex-prompt.md` as the only
step in this session. No Composer changes, no push, no `filacheck --fix`.

## Code Plan

1. Extract/share form-definition admin schema.
   - Create a focused support object for public form admin fields so
     `ManagePublicForms` can own the editor and the big Public Content Settings page
     no longer exposes it.
   - Keep builder storage compatible with
     `PublicFrontConfigValidator::normalizePublicForms()`.

2. Add the dedicated forms settings page.
   - `App\Filament\Pages\ManagePublicForms` extends Filament's SettingsPage with
     `PublicContentSettings` as the backing settings class.
   - Fill only the `public_forms` state from the current settings.
   - Save by merging the current full settings payload with validated/normalized
     `public_forms`, preserving unrelated fields and triggering the existing
     `SettingsSaved` pipeline.
   - Register under NAV1's `ניהול אתר` group in `AdminNavigationOrder`.
   - Add the generic `SettingsItemCloner` and wire it to the forms repeater clone
     action; cloned forms are disabled, receive a unique semantic key, and preserve
     original field arrays.

3. Extend maintenance settings.
   - Add defaults, validator rules, options, translations, lifecycle labels, and a
     settings migration for `form_key`, `form_location`, and `form_position`.
   - Add maintenance-tab UI controls in the existing big settings page with progressive
     disclosure, enabled-form select, marker snippet text, copy action, and warning
     helper text when raw HTML mode has no marker.

4. Reuse form submission behavior.
   - Add a small submitter/action that contains the shared honeypot, rate limiting,
     payload validation, hashing, source URL, metadata, and persistence behavior.
   - Refactor `PublicFormModal` to call it.
   - Use the same submitter from the maintenance POST controller.

5. Render the maintenance form.
   - Add a maintenance-form renderer/partial that receives definition, schema fields,
     old input, errors, status messages, and the endpoint URL.
   - For `rendered_page`, place the form before or after the normal title/content.
   - For `raw_html`, replace the first marker in `raw_html_override`; if no marker
     exists, render raw HTML unchanged followed by the form and a warning hint.
   - Keep no-form-configured output byte-compatible with MP1 as much as practical.

6. Add the POST endpoint.
   - Add one named route, tentatively `public.maintenance-form.submit`.
   - Teach `RenderMaintenanceMode` to let that route through.
   - Controller rejects maintenance-off or no-form-configured requests, returns the
     maintenance page with errors for invalid payload, and returns a thank-you state on
     success.
   - Register TokenMismatch handling for this route to return the maintenance page
     with a translated retry message instead of a bare 419.

7. Docs and handoff.
   - Update `current-project-state.md`.
   - Backfill NAV1's final hash in the NAV1 handoff.
   - Add the Forge `npx playwright install chromium` deploy note to the MP2 handoff
     and the existing maintenance deploy notes.
   - Create `docs/phase-02/maintenance-form-mp2-handoff.md` with commit hash and a
     numbered manual Local Front Check Report.

## Targeted Tests While Iterating

- `tests/Feature/PublicFormsSubmissionsTest.php`
  - dedicated forms page render/save
  - clone behavior
  - big settings page no longer exposes forms editor
  - registry/modal pick up page changes
  - unrelated settings saves do not wipe forms
- `tests/Feature/PublicMaintenanceModeTest.php`
  - settings keys
  - rendered before/after
  - raw marker replacement once
  - raw missing marker fallback/warning
  - valid guest submission creates `PublicFormSubmission`
  - endpoint rejection when disabled/unconfigured
  - invalid payload, honeypot, rate limit, thank-you, and stale CSRF rendering
- `tests/Feature/SettingsImportExportTest.php`
  - `public_forms` and new maintenance keys round-trip through packages

## Final Gate Order

Per the prompt, after the final code state:

1. Requirements sweep against the prompt's job/test lists.
2. `vendor/bin/pint --test`
3. `vendor/bin/filacheck`
4. `npm run build`
5. Full `php artisan test` last, exactly once green on the final code state.

Any code/doc change after a gate command restarts the final gate from Pint.
