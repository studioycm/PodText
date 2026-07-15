# Public Front v2 Step 6 Public Forms and Submissions Handoff

> **Historical shipped-state notice — 2026-07-16:** This handoff remains
> authoritative for the current feature. ARCH1 now requires Public Form
> definitions/revisions as Resources and exact submission revision binding;
> bounded verification policy remains settings. See
> `docs/research/settings-performance/07-sp3d-pre-research.md`.

## Purpose

Public Front v2 Step 6 adds safe configurable public forms and durable public form submission records. Form definitions remain JSON-first settings under the Step 1 public-front config architecture, while submissions are transactional database records for admin review.

## What was implemented

- Added normalized `public_forms.definitions` support to the public-front config registry and validator.
- Added safe registry-backed public form field types, display modes, validation semantics, and rate-limit defaults.
- Added `PublicFormSubmission` model, enum, migration, factory, and Filament Resource.
- Added a public Livewire/Blade form modal/slide-over component.
- Added v1 honeypot and Laravel-native rate limiting.
- Extended the public content settings page with a JSON-first Filament form builder for definitions.
- Added focused tests for config validation, public submission behavior, admin review actions, security rules, and deferred features.

## Final migrations and schema

Migration:

- `database/settings/2026_07_05_000001_normalize_public_forms_setting.php`
- `database/migrations/2026_07_05_000000_create_public_form_submissions_table.php`

Table:

- `public_form_submissions`

Columns:

- `id`
- `form_key`
- `form_name_snapshot`
- `payload` JSON
- `status`
- `submitted_at`
- `source_url`
- `submitter_ip_hash`
- `user_agent_hash`
- `metadata` JSON
- timestamps

No `public_form_definitions` table was created.

## Final namespaces and classes

- `App\Enums\PublicFormFieldType`
- `App\Enums\PublicFormSubmissionStatus`
- `App\Models\PublicFormSubmission`
- `App\Livewire\Public\PublicFormModal`
- `App\Support\PublicFront\Forms\PublicFormDefinitionRegistry`
- `App\Support\PublicFront\Forms\PublicFormPayloadValidator`
- `App\Support\PublicFront\Forms\PublicFormSchemaFactory`
- `App\Support\PublicFront\Forms\PublicFormSubmissionPresenter`
- `App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource`
- `resources/views/livewire/public/public-form-modal.blade.php`

## Final public API for future prompts

Read forms only through the Step 1 config reader:

```php
$result = app(PublicFrontConfigReader::class)->read();
$publicForms = $result->group('public_forms');
$definitions = $publicForms['definitions'] ?? [];
```

Render a configured public form:

```blade
<livewire:public.public-form-modal
    form-key="request_transcription"
    display-mode="modal"
/>
```

Open a mounted form from future menu/header UI without moving form state into Alpine:

```js
window.dispatchEvent(new CustomEvent('open-public-form', {
  detail: { formKey: 'request_transcription' },
}));
```

Alpine may only own open/close state. Livewire owns form data, validation, submission, success/error messages, honeypot, and rate-limiting behavior.

## Public form definition JSON schema

Canonical settings shape:

```json
{
  "public_forms": {
    "definitions": [
      {
        "key": "request_transcription",
        "name": "Request transcription",
        "heading": "Request a transcription",
        "description": "Send a public request.",
        "submit_label": "Send request",
        "success_message": "Request received.",
        "enabled": true,
        "display_mode_default": "modal",
        "settings": {
          "rate_limit_attempts": 5,
          "rate_limit_decay_seconds": 600
        },
        "fields": []
      }
    ]
  }
}
```

The validator also accepts the older Step 1 list placeholder shape for `public_forms` and normalizes it to `public_forms.definitions`.

## Supported field types

Supported v1 field types:

- `text`
- `email`
- `phone`
- `textarea`
- `select`
- `checkbox`
- `toggle`
- `url`

Supported field config:

- `key`
- `type`
- `label`
- `placeholder`
- `help_text`
- `required`
- `options`
- `min_length`
- `max_length`
- `validation_semantics`

Supported validation semantics:

- `none`
- `email`
- `phone`
- `url`

File uploads are not accepted in v1.

## Runtime validation behavior

The Livewire component resolves the form by key from normalized config. Missing or disabled forms do not render and cannot submit.

Validation rules are generated from configured fields only:

- required fields use Laravel validation before storage;
- email fields use Laravel email validation;
- URL fields require a valid HTTP or HTTPS URL and reject `javascript:`;
- select values must match configured option values;
- checkbox option arrays must contain configured values only;
- unknown payload fields are ignored.

Submitted string values are escaped before JSON storage.

## Submission storage behavior

Valid submissions create `PublicFormSubmission` records with:

- form key and form name snapshot;
- sanitized configured-field payload only;
- default status `new`;
- submission timestamp;
- source URL when available;
- HMAC hashes for submitter IP and user agent when available;
- display-mode metadata.

Raw IP addresses and raw user agents are not stored.

## Admin Resource behavior

`PublicFormSubmissionResource` lists submissions in the admin panel. It supports:

- searching form key and form name snapshot;
- filtering by status;
- safe payload summaries;
- edit page with read-only payload/details and editable status;
- record actions to mark reviewed, archive, and reopen;
- archive bulk action.

Admin access remains authenticated through the existing Filament admin panel.

## Honeypot and rate limiting behavior

The public form component renders an invisible honeypot field. Filled honeypot submissions are rejected before validation and do not create records.

Laravel `RateLimiter` limits submissions by form key and an HMAC request fingerprint derived from IP and user agent. Defaults are 5 attempts per 600 seconds, configurable per form through safe integer settings.

Validation failures count against the rate limit so repeated invalid automated attempts are throttled.

## Public rendering behavior

Public rendering is custom Livewire plus Blade. Labels, placeholders, help text, options, descriptions, and success messages are rendered as escaped Blade text.

The component supports `modal` and `slide_over` display modes. Future menu/header prompts can mount the component and open it through the `open-public-form` browser event.

Public form state is separate from Step 5 search/filter drawer state.

## Fallback and invalid config behavior

Invalid definitions, fields, field options, display modes, validation semantics, unknown keys, and unsafe strings are reported through `invalidConfigArray()` and normalized to safe defaults or skipped.

Enabled definitions with no valid fields are forced disabled.

Unsafe config values are not rendered publicly.

## Security rules

Rejected or ignored values include:

- raw CSS;
- raw Tailwind classes;
- raw SQL-looking strings;
- arbitrary PHP classes;
- arbitrary validation classes;
- arbitrary Blade paths;
- unsafe HTML;
- iframe HTML;
- JavaScript URLs;
- unknown field types;
- unknown validation semantics;
- unknown display modes.

No notification emails are sent in v1.

## Sample JSON payloads

Contact/request form:

```json
{
  "key": "request_transcription",
  "name": "Request transcription",
  "heading": "Request a transcription",
  "description": "Send a link and contact details.",
  "submit_label": "Send request",
  "success_message": "Request received.",
  "enabled": true,
  "display_mode_default": "slide_over",
  "fields": [
    {
      "key": "name",
      "type": "text",
      "label": "Name",
      "required": true,
      "max_length": 80
    },
    {
      "key": "email",
      "type": "email",
      "label": "Email",
      "required": true
    },
    {
      "key": "source_url",
      "type": "url",
      "label": "Source URL",
      "required": false
    },
    {
      "key": "topic",
      "type": "select",
      "label": "Topic",
      "required": true,
      "options": [
        { "value": "podcast", "label": "Podcast" },
        { "value": "lecture", "label": "Lecture" }
      ]
    }
  ],
  "settings": {
    "rate_limit_attempts": 5,
    "rate_limit_decay_seconds": 600
  }
}
```

Stored submission payload:

```json
{
  "name": "Submitter",
  "email": "submitter@example.com",
  "source_url": "https://example.com/source",
  "topic": "podcast"
}
```

## Sample PHP usage

Read invalid config for admin/debug surfaces:

```php
$result = app(PublicFrontConfigReader::class)->read();

if ($result->hasInvalidConfig()) {
    $invalidConfig = $result->invalidConfigArray();
}
```

Resolve submissions by status:

```php
$newSubmissions = PublicFormSubmission::query()
    ->status(PublicFormSubmissionStatus::New)
    ->latest('submitted_at')
    ->get();
```

## Blueprint deviations

- No broad public preview route was added. Tests mount the Livewire component directly, and future menu/header prompts can mount it where needed.
- The `public_forms` placeholder list shape from Step 1 remains accepted for compatibility but normalizes to the canonical `public_forms.definitions` shape.

## Impact on later prompts

- Step 7 About Page Content and Team Builder can use public form definitions for contact/volunteer calls to action without creating settings-only models.
- Step 8 Podcasts and Groups UX should mount or trigger forms only through the Livewire component/API if group pages need request actions.
- Step 9 Public Menu and Header should wire menu/header form actions to `PublicFormModal` and the `open-public-form` event; it should not store form state in Alpine.
- Step 10 Contributors and Top Transcribers UX can reuse the same form component for contributor-related calls to action if needed.
- Step 11 Seeders, Demo Data, Assets, and Cleanup can seed `public_forms.definitions` JSON and sample `PublicFormSubmission` records, but should not seed file-upload fields or email notification settings.
- Step 2 / Reserved Transcription Publication Policy remains deferred; Step 6 does not change effective/main transcription publication behavior.
- Prompt 13 Dashboard Metrics has not started; later metrics can count `PublicFormSubmission` statuses after Public Front v2 review.

## Open issues / follow-up decisions

- Public menu/header integration is deferred to Step 9.
- Email notifications are deferred.
- Public form file uploads are deferred.
- CAPTCHA package integration is deferred.
- Admin form preview beyond the settings builder is deferred.

## Tests and quality gate summary

Added:

- `tests/Feature/PublicFormsSubmissionsTest.php`

Updated:

- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`

Focused tests run during implementation:

- `php artisan test --filter=PublicFormsSubmissionsTest`
- `php artisan test --filter=PublicFrontJsonSettingsArchitectureTest`
- `php artisan test --filter=PublicLatestSearchUxTest`
- `php artisan test --filter=PublicHomepageSearchTest`
- `php artisan test --filter=PublicDisplaySectionsLoopersTest`
- `php artisan test --filter=PublicFrontCardTemplateBuilderTest`
- `php artisan test --filter=PublicContributorDiscoveryTest`
- `php artisan test --filter=PublicItemPageMediaParserTest`

Final full gate before commit:

- `php artisan test`: passed, 178 tests and 1397 assertions.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed, 0 issues.
- `npm run build`: passed.
