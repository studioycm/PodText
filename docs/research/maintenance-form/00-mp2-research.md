# MP2 Maintenance Form Research

Date: 2026-07-12

## Scope

This research supports `prompts/pre-13-prompts/maintenance-form-mp2-codex-prompt.md`.
The run is limited to MP2: a dedicated admin forms page backed by
`PublicContentSettings::$public_forms`, plus a plain POST maintenance-page form that
reuses the existing public-form submission pipeline.

## Local Preflight

- `git status --short --branch` reported `## main...origin/main [ahead 3]` with no
  working-tree dirt.
- Recent history includes NAV1 as `e59705b feat: restructure admin navigation groups and defer badges`.
- The MP2 prompt commit is present at `HEAD` as `a76a1a3 docs: prompt for forms management page and maintenance form embedding`.
- `rg` is not installed in this local environment, so repository inspection used
  `find`, `grep`, and focused file reads.

## Boost Findings

Tools used: `application_info`, `database_schema`, and `search_docs`.

- Boost confirmed PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3,
  Pest 4.7.4, Tailwind CSS 4.3.2, and no Composer/package changes are required.
- The database schema has `public_form_submissions` only; there is no
  `public_form_definitions` table. This matches D-MP2-A: form definitions stay in
  `public_content.public_forms`.
- Filament 5 Repeater item actions support `extraItemActions()` with access to
  `$arguments['item']`, `getRawItemState()`, `getState()`, and `state($state)`.
  MP2 can use this for a custom clone action that mutates a repeater collection.
- Filament SettingsPage save/fill hooks use `mutateFormDataBeforeFill()` and
  `mutateFormDataBeforeSave()`. Reusing a SettingsPage keeps Spatie Settings saves,
  `SettingsSaved`, cache clearing, system backups, and import-lock behavior.
- Boost did not return useful Spatie SettingsPage package docs, so the installed
  `vendor/filament/spatie-laravel-settings-plugin/src/Pages/SettingsPage.php` was
  inspected. It fills from `app(static::getSettings())->toArray()`, calls
  `fill($data)`, then `save()`.
- Laravel's public panel stack already includes `PreventRequestForgery`, so the
  maintenance POST route should be exempt only from maintenance interception, not
  from CSRF protection.

## FilamentExamples Findings

Access level: `search_examples` snippet/search access only. No source/read/fetch
detail tool was exposed.

Query batches used:

- `Filament SettingsPage custom settings form repeater actions`
- `Filament Repeater clone item action unique key disabled copy`
- `Filament settings page header actions import export`
- `Filament form field hint action copy clipboard`
- `Filament progressive disclosure visible fields settings page`
- refined pass: `Filament custom page manage settings collection builder fields`
- refined pass: `Filament Builder blocks form fields nested repeater settings`
- refined pass: `Filament settings page save only one settings array property`
- refined pass: `Filament action inside repeater item clone disabled duplicate`
- refined pass: `Filament warning hint missing marker settings page`

Relevant examples and PodText adaptation notes:

- Custom settings/profile pages showed the established custom Page pattern:
  state lives in a `data` array, form schemas own validation, and save actions submit
  the form. PodText already uses Filament's Spatie SettingsPage, so MP2 should extend
  that instead of a manual custom page.
- Multi-panel provider snippets confirmed that discovered pages are normal navigation
  surfaces. PodText adapts this through `AdminNavigationOrder` and the existing
  `UsesAdminNavigationOrder` trait.
- Repeater/action examples matched Boost docs: custom item actions can mutate the
  repeater state. MP2 should use this for a generic settings collection cloner, not
  Filament's built-in clone action, because the clone must mint a unique key, add a
  translated copy suffix, and disable the new form.
- Search did not expose a complete source example for copy-to-clipboard hint actions
  or raw HTML marker warnings. MP2 should use Filament schema actions where practical
  and keep warning behavior testable through labels/helper text and maintenance
  rendering.

## Existing Public Form Pipeline

- Definitions live in `PublicContentSettings::$public_forms` under
  `public_forms.definitions`.
- `PublicFrontConfigRegistry::defaults()` sets `public_forms` to
  `['definitions' => []]`.
- `PublicFrontConfigValidator::normalizePublicForms()` accepts the wrapped
  `definitions` array and the legacy list shape, validates duplicate form keys,
  normalizes Filament Builder field items, and disables enabled forms that have no
  fields.
- `PublicFormDefinitionRegistry` owns safe field types, display modes, validation
  semantics, and rate-limit defaults.
- `PublicFormSchemaFactory::fields()` maps a normalized definition to renderable field
  metadata: text-like fields, textarea, select, checkbox-with-options, checkbox-as-
  boolean, and toggle.
- `PublicFormPayloadValidator` builds Laravel validation rules from the same
  definitions, restricts select/checkbox options, applies email/phone/url semantics,
  rejects non-HTTP URL schemes, and returns only configured sanitized payload keys.
- `PublicFormSubmission` stores the form key, name snapshot, sanitized payload,
  source URL, IP/user-agent hashes, status, and metadata. It clears the submissions
  navigation badge cache on save/delete.

## Existing Anti-Spam Mechanics

`App\Livewire\Public\PublicFormModal` currently provides the anti-spam behavior MP2
must reuse:

- A hidden `honeypot` field rejects bot-style filled submissions before validation or
  storage.
- Rate limiting uses `RateLimiter::tooManyAttempts()` and `RateLimiter::hit()` with a
  key based on form key plus a hashed request IP/user-agent fingerprint.
- Per-form limits come from `definition['settings']['rate_limit_attempts']` and
  `definition['settings']['rate_limit_decay_seconds']`, defaulting to 5 attempts and
  600 seconds through `PublicFormDefinitionRegistry::rateLimitDefaults()`.
- The stored payload still goes through `PublicFormPayloadValidator`; the Livewire
  component does not own a parallel validation or persistence path.

MP2 should centralize this in a reusable submitter/action so the Livewire modal and
plain maintenance POST share honeypot, throttle, validation, hashing, source URL,
and metadata semantics.

## Plain HTML Field Mapping

The maintenance form can render normalized schema fields without Livewire:

- `text`, `email`, `phone`, and `url` map to `<input>` with type `text`, `email`,
  `tel`, and `url`.
- `textarea` maps to `<textarea>`.
- `select` maps to `<select>` with configured option values/labels.
- `checkbox` with options maps to repeated checkbox inputs using `name="data[key][]"`.
- `checkbox` without options and `toggle` map to a single checkbox with value `1`.
- Required markers, help text, preserved old input, and field errors should be rendered
  in the server Blade partial.
- The hidden honeypot input must use a stable non-data name and stay visually hidden.

## Maintenance Mode Findings

- `RenderMaintenanceMode` currently returns a 503 view for every public-panel request
  when `maintenance.enabled` is true and the current user cannot access the admin
  panel.
- Admin users bypass maintenance through Filament's admin panel access check.
- `resources/views/public/maintenance.blade.php` either renders raw HTML override
  verbatim or a standalone Hebrew RTL shell with title and rich content/fallback body.
- MP2 needs one named POST route that bypasses only `RenderMaintenanceMode` so the
  request reaches the controller while the rest of public routes keep returning 503.
- The endpoint must still independently verify maintenance is enabled and an enabled
  form key is configured before accepting a submission.

## Implementation Implications

- Add a dedicated `ManagePublicForms` SettingsPage that saves only
  `public_forms.definitions` while preserving all other `PublicContentSettings` data.
- Move the existing form-builder schema out of `PublicContentSettings` page or share it
  through a helper, then remove the Forms tab from the big settings page.
- Add a collection-agnostic `SettingsItemCloner` that deep-copies an item, mints a
  unique key with a numeric suffix, applies a translated name suffix, and lets callers
  override cloned fields such as `enabled`.
- Add maintenance settings keys:
  `form_key`, `form_location`, and `form_position`, with defaults and validation.
- Add a single app-owned marker constant for raw HTML placement:
  `<div data-podtext-maintenance-form></div>`.
- Render the form in the existing 503 response without Livewire, replacing only the
  first marker occurrence in raw HTML mode and falling back after raw content when the
  marker is missing.
- Add one named POST route, guarded by maintenance/form configuration and CSRF, using
  the same submitter as the public modal.
- Add TokenMismatch rendering for this route so stale CSRF returns the maintenance
  page with a translated retry message.
