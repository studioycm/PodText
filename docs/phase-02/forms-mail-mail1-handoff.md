# Forms Mail MAIL1 Handoff

Date: 2026-07-13

## Scope

Executed only `prompts/pre-13-prompts/forms-mail-mail1-codex-prompt.md`
version v1.

Added the Resend-first mail foundation, email OTP verification for public form
submissions, maintenance-form OTP enforcement, public-form hardening, and the
four FIX1 v2 carry-forward items. No npm changes were made. The only Composer
package added was `resend/resend-php`.

## Commit hash

Pending until implementation commit.

## Requirement Classification

- Implemented: Resend mail transport dependency/config, `.env.example` mail
  examples, queued localized OTP mailable, channel-extensible verification
  manager, `form_verification_codes`, submission verification metadata,
  per-form and global email verification settings, Livewire OTP flow,
  maintenance plain-POST OTP flow, server-side refusal of unverified protected
  submissions, public-form size/length/email hardening, named maintenance form
  throttles, admin verified badge, import failure cause summaries, public-site
  admin nav link, workspace Spotify field moved first, and tiered Spotify
  podcast recognition.
- Already existed: Laravel 13 native Resend mailer config shape, Filament
  failed-row CSV download authorization and `validation_error` output, strict
  native importers, public form honeypot/rate-limit baseline, maintenance
  plain-POST form rendering, and workspace Spotify lookup/fill foundation.
- Deferred by prompt: phone/SMS/WhatsApp verification drivers, generic
  notification emails beyond OTP, remote media fetching, import package ZIP
  flows, analytics/logging dashboards, and any npm package work.
- Not applicable: custom CSV controllers, live mail/network tests, Horizon
  config changes, and Prompt 13 dashboard widgets.
- Blocked: none.

## Files Changed

- Mail and verification:
  `composer.json`, `composer.lock`, `.env.example`, `config/mail.php`,
  `config/services.php`, `app/Mail/PublicFormEmailVerificationCodeMail.php`,
  `resources/views/mail/public-form-email-verification-code.blade.php`,
  `app/Enums/FormVerificationChannel.php`,
  `app/Enums/FormVerificationResult.php`,
  `app/Enums/PublicFormEmailVerificationMode.php`,
  `app/Models/FormVerificationCode.php`,
  `app/Support/Forms/Verification/*`,
  `database/migrations/2026_07_13_000000_create_form_verification_codes_table.php`,
  `database/migrations/2026_07_13_000001_add_verification_fields_to_public_form_submissions_table.php`,
  `database/settings/2026_07_13_000000_add_public_form_email_verification_settings.php`.
- Public forms and maintenance:
  `app/Livewire/Public/PublicFormModal.php`,
  `app/Support/PublicFront/Forms/*`,
  `app/Support/PublicFront/PublicFrontConfigRegistry.php`,
  `app/Support/PublicFront/PublicFrontConfigValidator.php`,
  `app/Support/PublicFront/Maintenance/MaintenancePageRenderer.php`,
  `app/Http/Controllers/MaintenanceFormSubmissionController.php`,
  `app/Http/Controllers/MaintenanceFormVerificationCodeController.php`,
  `resources/views/livewire/public/public-form-modal.blade.php`,
  `resources/views/public/partials/maintenance-form.blade.php`,
  `routes/web.php`.
- Admin/import/workspace:
  `app/Filament/Imports/Concerns/ConfiguresContentImports.php`,
  `app/Filament/Pages/ManagePublicForms.php`,
  `app/Filament/Support/AdminNavigationOrder.php`,
  `app/Filament/Support/PublicFormsSettingsForm.php`,
  `app/Filament/Resources/ContentItems/Schemas/EpisodeWorkspaceForm.php`,
  `app/Filament/Resources/PublicFormSubmissions/*`,
  `app/Providers/AppServiceProvider.php`,
  `app/Providers/Filament/AdminPanelProvider.php`,
  `app/Support/Importer/SpotifyLinks/*`.
- Translations/tests/docs:
  `lang/en/admin.php`, `lang/en/public.php`, `lang/he/admin.php`,
  `lang/he/public.php`, targeted feature tests, research/plan docs, this
  handoff, current state, ledger, and AI lessons.

## Tests Added Or Updated

- Added `tests/Feature/FormVerificationManagerTest.php` for send, previous-code
  invalidation, happy verification, max attempts, expiry, cooldown, and
  single-use consume behavior.
- Updated public-form tests for per-form OTP, global force flag, server refusal,
  oversized payloads, length limits, localized queued mailable assertions, and
  admin verified badge rendering.
- Updated maintenance tests for the two-step plain POST OTP flow.
- Updated import/export tests for failed-row validation cause output and
  completion notification cause summaries.
- Updated admin navigation tests for the last public-site link.
- Updated workspace tests for show-ID, exact-title, and close-title Spotify
  recognition tiers.

## Deploy Notes

- Production mail option A: set `MAIL_MAILER=resend`,
  `RESEND_KEY=<production API key>`, `MAIL_FROM_ADDRESS`, and
  `MAIL_FROM_NAME="${APP_NAME}"`.
- Verify `podtext.co.il` in Resend before enabling production sends. Publish the
  SPF/DKIM DNS records Resend gives for the domain and wait until Resend marks
  the domain verified.
- Production mail option B remains generic SMTP: set `MAIL_MAILER=smtp`,
  `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and
  `MAIL_ENCRYPTION`. Resend SMTP uses host `smtp.resend.com`, username
  `resend`, and password equal to the Resend API key. Brevo SMTP can use the
  same generic SMTP keys.
- Local/default workflow stays `MAIL_MAILER=log`; OTP messages appear in
  `storage/logs/laravel.log` when the queue/log mailer is run locally.
- OTP mail is queued onto the existing `default` queue. No Horizon config change
  is required by MAIL1.

## Local Front Check Report

1. Set local mail to the log mailer and make sure the default queue is running
   or configured for local processing.
2. In admin, open Public forms, enable email OTP on one form with an email
   field, save, and open that form publicly.
3. Enter the email and required form data, request a code, copy the OTP from
   `storage/logs/laravel.log`, verify it, and submit successfully.
4. Repeat the same public form and try submitting without verification; expect a
   refusal and no stored submission.
5. Turn on the global public-forms email verification flag, leave a form's
   per-form setting off, and confirm that the form now still requires OTP.
6. Enable maintenance mode with a public form, request a code through the plain
   maintenance form, copy the log-mailer OTP, submit with the signed action, and
   confirm the submission stores verification metadata.
7. Import a CSV with one broken content-tag row and confirm the failed-row CSV
   and completion notification show the actual missing tag cause.
8. Open the admin sidebar and confirm the Site Management group ends with the
   public-site link, opens a new tab, and points to the public homepage.
9. Open the episode workspace and confirm the Spotify link/fetch field is the
   first form entry.
10. Fetch an episode from an existing show ID and confirm the modal links the
    podcast by default.
11. Fetch an episode whose show title exactly matches an existing podcast after
    case/whitespace normalization and confirm the modal offers it pre-checked.
12. Fetch an episode whose show title is only close to an existing podcast and
    confirm the suggestion appears unchecked.

## Commands Run

- Preflight:
  `git status --short --branch`; `git log --oneline -5`; full read of
  `docs/phase-02/ai-development-lessons.md`.
- Research/tools:
  Laravel Boost `application_info`, `database_schema`, and `search_docs`;
  FilamentExamples `search_examples` in short query batches plus refined
  passes; local source inspection with `find`, `grep`, and `sed`.
- Dependency:
  `composer require resend/resend-php --no-interaction`.
- Syntax:
  `(git diff --name-only -- '*.php'; git ls-files --others --exclude-standard -- '*.php') | sort -u | xargs -n 1 php -l` passed.
- Targeted tests:
  `PAO_DISABLE=1 vendor/bin/pest tests/Feature/FormVerificationManagerTest.php --colors=never`
  passed 4 tests, 19 assertions.
  `PAO_DISABLE=1 vendor/bin/pest tests/Feature/PublicFormsSubmissionsTest.php tests/Feature/PublicMaintenanceModeTest.php tests/Feature/Phase02ImportExportTest.php tests/Feature/AdminPhase02ResourcesTest.php tests/Feature/EpisodeWorkspaceTest.php --colors=never`
  initially failed one locale-specific import notification assertion; after the
  assertion fix, `PAO_DISABLE=1 vendor/bin/pest tests/Feature/Phase02ImportExportTest.php --colors=never`
  passed 14 tests, 123 assertions.
  A later full-suite pass exposed one settings import/export expectation and one
  Spotify close-title auto-link regression; after fixes,
  `PAO_DISABLE=1 vendor/bin/pest tests/Feature/AdminToolsTest.php tests/Feature/SettingsImportExportTest.php tests/Feature/EpisodeWorkspaceTest.php --colors=never`
  passed 49 tests, 442 assertions.
- Final gate:
  `vendor/bin/pint --test` passed.
  `vendor/bin/filacheck` passed with 0 issues.
  `npm run build` passed.
  `PAO_DISABLE=1 php artisan test` passed 478 tests, 4,228 assertions in
  360.18s.
  Earlier gate attempts caught and fixed Pint formatting, a FilaCheck enum-label
  issue, and the two full-suite regressions noted above; the final gate was then
  restarted from Pint and the full suite remained the last command.

## Tooling Notes

- Laravel Boost was available and used before code changes. It reported PHP 8.4,
  Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and Tailwind CSS
  4.3.2.
- Boost docs confirmed Laravel 13 Resend mailer configuration, Markdown
  mailables, named rate limiters, and `email:rfc`.
- FilamentExamples exposed `search_examples` only. No source/read/detail tool
  was exposed, so research notes and this handoff describe access as
  search/snippet access only.
- Test commands used `PAO_DISABLE=1` after Laravel Pao's agent stdout filter
  crashed silently at shutdown in this environment. Application behavior was not
  changed for that tooling issue.

## Assumptions

- Resend is the preferred provider for production OTP mail, with SMTP fallback
  documented but not selected by default.
- The maintenance send-code handler returns the maintenance form response with a
  signed submit action and preserved entered data, rather than dropping user data
  through a browser redirect.
- Public forms without an email field remain exempt from email OTP, even when
  the global flag is enabled.

## Deferred Issues

- Phone/SMS/WhatsApp verification requires a future channel driver and provider
  decision.
- Per-form non-OTP notification emails are not part of MAIL1.
- Further import UI polish can build on the distinct failure-cause summaries,
  but native Filament failed-row authorization/download behavior remains intact.
