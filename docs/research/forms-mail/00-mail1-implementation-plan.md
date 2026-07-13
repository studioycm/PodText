# MAIL1 Implementation Plan - Mail Foundation and Email OTP Forms

Date: 2026-07-13

## Constraints

- Execute only MAIL1 prompt v1.
- Composer: add exactly `resend/resend-php`; no other direct package additions.
- No npm dependency changes.
- Tests must use `Mail::fake()` and `Http::preventStrayRequests()`.
- Final gate order: requirements sweep, `vendor/bin/pint --test`,
  `vendor/bin/filacheck`, `npm run build`, full `php artisan test` last.
- Implementation commit first, then docs-only hash-backfill commit.
- No push.

## Steps

1. Mail foundation
   - Run `composer require resend/resend-php`.
   - Update `config/services.php` to prefer `RESEND_KEY` with
     `RESEND_API_KEY` compatibility.
   - Expand `.env.example` with local log mailer defaults, Resend API mailer
     option, generic SMTP fallback notes for Resend SMTP and Brevo SMTP, and
     from-address/name keys.
   - Add queued Markdown OTP mailable and he/en mail text.

2. Verification model and service
   - Add `FormVerificationChannel` and `FormVerificationResult` enums.
   - Add `form_verification_codes` migration and model.
   - Add channel interface plus email channel driver.
   - Add `FormVerificationManager` with `send()`, `verify()`, and `consume()`:
     invalidate prior active codes, hash codes, 10-minute expiry, 5 attempts,
     60-second resend cooldown, hourly caps by address and IP, and single-use
     consumption.
   - Register named rate limiters in `AppServiceProvider`.

3. Public form config
   - Add registry helpers for verification modes.
   - Normalize `public_forms.require_email_verification`.
   - Normalize per-form `settings.submitter_email_verification`.
   - Add a settings migration for the global flag and per-form setting default.
   - Add global and per-form controls to `ManagePublicForms` via
     `PublicFormsSettingsForm`, with helper text and translations.

4. Submission authority
   - Extend `PublicFormSubmitter` to determine whether verification is required,
     locate the configured submitter email field, refuse unverified submissions,
     consume verified codes, and store verification channel/verified timestamp
     on `PublicFormSubmission`.
   - Add `verification_channel` and `verification_verified_at` columns/casts.
   - Add admin table/form badge/details for verified status.

5. Livewire flow
   - Add send-code and verify-code actions to `PublicFormModal`.
   - Render code controls only when required and an email is present.
   - Disable submit in the UI until verified, while keeping server refusal as
     the authority.
   - Keep success reset behavior and no unverified path.

6. Maintenance plain POST flow
   - Add a send-code POST route with honeypot and throttle middleware.
   - Add controller to send the code and render/redirect back with a signed
     token.
   - Extend the maintenance form partial with OTP code input and token fields.
   - Final submit verifies and consumes the code through the same manager.
   - Preserve existing CSRF retry behavior and 404 guards.

7. Job 0 carry items
   - Add import failure-cause summary helper to `ConfiguresContentImports`.
   - Add tests proving failed-row CSV output contains the actual cause and the
     notification body summarizes it.
   - Add central public-homepage navigation item and tests for last/new-tab URL.
   - Move workspace Spotify input/fetch action to the top of the workspace form.
   - Add tiered podcast recognition and tests for show ID, exact normalized
     title, and close-name unchecked suggestion.
   - Append the prompt-version race lesson to
     `docs/phase-02/ai-development-lessons.md`.

8. Hardening
   - Add named route throttle middleware to public form submission routes.
   - Add whole-payload size guard in `PublicFormPayloadValidator`.
   - Add tests for throttles and length/oversized payload behavior.
   - Ensure edited HTTP-touching tests call `Http::preventStrayRequests()`.

9. Docs and state
   - Update current state and ledger row:
     `MAIL1 - Mail foundation and email OTP form verification`.
   - Add handoff at `docs/phase-02/forms-mail-mail1-handoff.md` with gate
     outcomes, requirement classification, assumptions, deferred issues, git
     status, and manual Local Front Check Report.
   - Include deploy notes for production `MAIL_MAILER=resend`, `RESEND_KEY`,
     `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, Resend domain SPF/DKIM verification
     for `podtext.co.il`, and local log-mailer workflow.

## Targeted Tests Before Final Gate

- New unit/feature OTP manager test file.
- `tests/Feature/PublicFormsSubmissionsTest.php`.
- `tests/Feature/PublicMaintenanceModeTest.php`.
- `tests/Feature/Phase02ImportExportTest.php`.
- `tests/Feature/AdminPhase02ResourcesTest.php`.
- `tests/Feature/EpisodeWorkspaceTest.php`.

The full suite runs only after the final gate reaches `php artisan test`.
