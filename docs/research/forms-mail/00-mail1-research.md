# MAIL1 Research - Mail Foundation and Email OTP Forms

Date: 2026-07-13

Prompt: `prompts/pre-13-prompts/forms-mail-mail1-codex-prompt.md` v1.

## Preflight

- `git status --short --branch`: clean `main...origin/main [ahead 1]`.
- `git log --oneline -12`: `0f4b2e9 docs: add forms mail MAIL1 prompt` at
  HEAD, with expected FIX1 commits `700de7f` and `c66ca3f` directly behind it.
- No MAIL1 research, plan, handoff, or code had started.
- `docs/phase-02/ai-development-lessons.md` was read in full.
- Prompt version matches the kickoff version: v1, dated 2026-07-13.

## Tool Research

### Laravel Boost

Boost was available and used before implementation.

- `application_info`: PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire
  4.3.3, Pest 4.7.4, Tailwind CSS 4.3.2.
- `database_schema` summary confirmed the existing `public_form_submissions`,
  `imports`, `failed_import_rows`, `content_groups`, and `content_items`
  shapes.
- `search_docs` confirmed Laravel 13 mail support:
  - install `resend/resend-php`;
  - configure a `resend` mailer and `services.resend.key`;
  - default mailer can be switched with `MAIL_MAILER`;
  - Markdown mailables use the `Content(markdown: ...)` API;
  - named rate limiters are defined with `RateLimiter::for()` and attached with
    `throttle:{name}`;
  - `email:rfc` avoids DNS lookup validation.

### FilamentExamples

FilamentExamples exposed `search_examples` only. No source/detail fetch tool was
available.

Relevant examples and adaptations:

- `v4/tables/public-products-table/.../ProductExporter.php`:
  `getCompletedNotificationBody()` appends failed-row counts. PodText will
  extend its existing shared importer completion body with grouped failure
  causes.
- `v4/full-projects/schedule-for-doctors/.../ManageDoctorSchedule.php`:
  Filament page actions with schema, `Notification::make()`, enum icons, and
  table badges. PodText will keep the same patterns for form actions and badges.
- `v4/full-projects/hotel-management-bookings/.../PanelProvider.php` and page
  snippets: current Filament 5 panel/page registration patterns. PodText will
  use installed local Filament APIs for custom navigation item registration.
- `v4/full-projects/multi-language-with-switcher/.../PostForm.php`: translated
  form labels and max-length constraints. PodText will keep all new admin/public
  labels in `lang/en` and `lang/he`.

Patterns not copied:

- Examples with ad-hoc inline English labels; PodText requires translation keys.
- Examples that query relationships inside table closures without eager
  loading; PodText table changes should stay cheap.

## Current Code Findings

### Mail Configuration

- `config/mail.php` already has a `resend` mailer entry.
- `config/services.php` already has `resend.key` reading `RESEND_API_KEY`.
- `resend/resend-php` is not installed. Laravel 13 suggests it, but Composer
  does not currently require it.
- `.env.example` currently contains only app key, Curator, and settings-cache
  entries. It does not document mail, Resend, SMTP, or from-address keys.

Implementation impact:

- Add exactly `resend/resend-php` and no other direct Composer package.
- Keep local default `MAIL_MAILER=log`.
- Support prompt-preferred `RESEND_KEY` while retaining Laravel-doc
  `RESEND_API_KEY` compatibility if practical.

### Public Form Storage and Validation

- Form definitions are stored in `PublicContentSettings::$public_forms`, not a
  table.
- `PublicFrontConfigValidator` currently accepts only
  `public_forms.definitions`.
- Per-form settings currently contain `rate_limit_attempts` and
  `rate_limit_decay_seconds`.
- `PublicFormPayloadValidator` already:
  - limits string fields by `max_length` with defaults of 255 for short inputs
    and 5000 for textareas;
  - validates email with `email:rfc`;
  - restricts select and checkbox values;
  - rejects non-http(s) URLs.
- There is no submitter-email verification setting, no global force flag, and no
  verification metadata columns on submissions.

Implementation impact:

- Add `public_forms.require_email_verification` with a default of `false`.
- Add a per-form `settings.submitter_email_verification` option with values
  `off` and `email_otp`.
- Treat global force as active only when the form has an email submitter field.
- Preserve settings-backed forms; do not add a `PublicFormDefinition` table.

### Public Form Submission Paths

- Livewire path: `PublicFormModal::submit()` calls `PublicFormSubmitter`.
- Maintenance fallback path: `POST /maintenance/form` calls
  `MaintenanceFormSubmissionController`, then `PublicFormSubmitter`.
- `PublicFormSubmitter` already enforces honeypot and per-form/request
  fingerprint rate limiting through `RateLimiter`.
- There is no server-side verification refusal path today.

Implementation impact:

- `PublicFormSubmitter` must be the server authority for required-but-unverified
  submissions.
- Livewire and plain POST can collect/send/verify codes, but client state must
  not be trusted.
- Add named route rate limiters for public form submissions and send-code routes
  without removing existing per-definition rate-limit behavior.

### Maintenance Plain POST Fallback

- The maintenance form partial posts directly to
  `public.maintenance-form.submit`.
- It renders field errors under raw field keys, not `data.*`.
- CSRF retry handling is custom in `bootstrap/app.php`.
- There is no separate send-code route or signed verification token today.

Implementation impact:

- Add `POST /maintenance/form/send-code`, still guarded by maintenance state,
  honeypot, CSRF, and throttling.
- Redirect/render back with a signed token after sending the code.
- Final plain POST must verify the submitted code through the same manager and
  then consume the verification at submission.

### Import Failure Surfaces

- Filament vendor `DownloadImportFailureCsv` keeps failed-row authorization in
  place and appends one `error` column from `failed_import_rows.validation_error`.
- Vendor `ImportCsv` stores `RowImportFailedException` messages directly and
  flattens `ValidationException` messages before storage.
- PodText importers already throw explicit messages for relationship failures
  such as unresolved content groups, categories, tags, disabled tags, and
  duplicate reference keys.
- Shared PodText importer notification body only reports successful row count,
  failed row count, and skipped disabled tags. It does not summarize distinct
  failed-row causes.

Implementation impact:

- Preserve vendor failed-row download authorization.
- Test the download output for actual failure text.
- Add grouped failure-cause summaries to `ConfiguresContentImports`.

### Admin Navigation

- Navigation order is centralized in `AdminNavigationOrder`.
- The admin panel uses `AdminNavigationOrder::panelNavigationGroups()`.
- Installed Filament 5 supports `Panel::navigationItems()` and
  `NavigationItem::url($url, shouldOpenInNewTab: true)`.
- Public homepage is `BrowseContentGroups`, route
  `filament.public.pages.home`.

Implementation impact:

- Add a central custom item for the public homepage link, sorted after all
  existing entries.
- Use `BrowseContentGroups::getUrl(panel: 'public')`, a Heroicon enum, new-tab
  behavior, and he/en labels.

### Workspace Spotify Fetch

- `EpisodeWorkspaceForm` currently places `spotify_episode` inside the media
  section after description, image, and media URL.
- `SpotifyLinksImportResolver::resolveGroup()` links by Spotify show ID via
  existing item metadata, then by exact case-sensitive title.
- Workspace modal currently shows only a matched podcast name and defaults the
  link checkbox to `true`.

Implementation impact:

- Move the Spotify input/fetch action to the first form section.
- Add resolver support for show-ID, normalized exact-title, and close-title
  suggestions.
- Defaults:
  - show-ID match: checked and can auto-link when submitted;
  - exact normalized title match: checked in the modal;
  - close title suggestion: shown unchecked;
  - no below-tier auto-linking without the option being selected.

## Forms Hardening Gap Enumeration

Found gaps:

- Missing server-side verification refusal and consume semantics.
- Missing named route rate limiters for public form submissions and code sends.
- Missing hourly OTP caps by address and IP.
- Missing oversized payload tests; individual fields have max lengths, but there
  is no explicit whole-payload guard.
- Missing first-class verification metadata on `public_form_submissions`.

Already acceptable:

- Strict email validation is already `email:rfc`.
- Field-level max lengths are already available and normalized through config.
- Public form field keys and options are semantic-key restricted.
- Honeypot protects both Livewire and maintenance POST submission surfaces.
- Public URL validation rejects `javascript:` and non-http(s) schemes.

Bigger deferred items:

- CAPTCHA/package integration remains out of scope.
- Phone/SMS/WhatsApp verification driver remains design-only until a later
  prompt approves provider and package choices.
- File uploads remain out of scope.
