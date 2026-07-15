# OTP Policy Config Research and Implementation Plan

Date: 2026-07-15

Task: ad-hoc OTP verification policy config and copy fixes.

## Preflight and installed-version research

- The working tree was clean on `main` at preflight. The latest completed mail
  work is MAIL2 (`650b631`) and its docs hash backfill (`e076e95`).
- Laravel Boost reported Laravel 13.19.0, Livewire 4.3.3, Pest 4.7.4, and
  Tailwind CSS 4.3.2.
- Laravel's installed-version documentation confirms that environment reads
  belong in config files, application code should consume `config()`, and
  pluralized copy should use `trans_choice()` with explicit choice intervals.
- No Filament files are in scope, so FilamentExamples research is not
  applicable.

## Current behavior

- `FormVerificationManager` owns three public policy constants: a 10-minute
  expiry, five attempts, and a 60-second resend cooldown.
- The manager, Livewire component, maintenance signed URL, and OTP mailable
  consume those constants directly.
- The OTP email uses a non-pluralized translation, which produces incorrect
  Hebrew for one minute.
- Neither the Livewire code input nor the maintenance fallback code input shows
  the expiry before submission.

## Constraints

- Preserve code generation and hashing, challenge binding, rate-limit refusal,
  verification, and consume-on-submit mechanics.
- Add no dependencies and perform no production action.
- Keep every visitor-facing string in both `lang/he/public.php` and
  `lang/en/public.php`.
- Tests continue to fake mail and prevent stray HTTP requests.

## Operator review correction

- Both custom Blade input/action groups used `row-reverse`, which placed the
  action at inline-start: right in Hebrew and left in English.
- The input precedes its action in the DOM. A normal flex row follows the
  document direction, placing the action at logical inline-end: left in Hebrew
  RTL and right in English LTR. This applies to the send-code and verify-code
  groups without changing verification behavior or copy.

## Implementation plan

1. Add `config/forms.php` with integer OTP policy values backed by
   `FORMS_OTP_EXPIRES_MINUTES`, `FORMS_OTP_MAX_ATTEMPTS`, and
   `FORMS_OTP_RESEND_COOLDOWN_SECONDS`; document defaults in `.env.example`.
2. Replace every public constant consumer with the corresponding config value,
   using five minutes as the new default while preserving all control flow.
3. Convert the mail expiry line to explicit singular/plural translations and
   call it with `trans_choice()`.
4. Add a bilingual code-expiry hint under the OTP input on the Livewire and
   maintenance surfaces, with both receiving the same configured minute count.
5. Extend Pest coverage for config defaults/overrides, the five-minute expiry,
   attempt and cooldown overrides, singular/plural email copy, and both hints.
6. Record the production env/config-cache expectation in the handoff, update
   current state and the ledger, then run the ordered final gate.
7. Correct both custom Blade input/action groups to logical inline-end and add
   rendered placement assertions for the Livewire and maintenance surfaces.
