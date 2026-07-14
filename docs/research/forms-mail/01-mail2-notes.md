# MAIL2 Research and Implementation Plan - Inline Email Verification UX

Date: 2026-07-14

Prompt: `prompts/pre-13-prompts/forms-otp-ux-mail2-codex-prompt.md` v1
(2026-07-14). Kickoff corrections: none.

## Preflight and baseline

- `git status --short --branch` was clean on `main`, ahead of `origin/main`
  only by the committed MAIL2 prompt.
- `git log --oneline -5` confirmed MAIL2 follows the completed SP3A hash
  backfill and does not overlap an active SP3B change.
- The prompt version matches the kickoff. The AI development lessons were read
  in full.
- The focused MAIL1 baseline passed 30 tests / 254 assertions for
  `PublicFormsSubmissionsTest` and `PublicMaintenanceModeTest`. The first
  sandboxed attempt could not bind Pest's local port; the unchanged command
  passed with approved port access.

## Installed-version research

Laravel Boost reported PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire
4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2.

Boost documentation confirmed:

- Livewire 4 array update hooks receive the changed array key through
  `updatedData(mixed $value, ?string $key)`.
- `wire:model.live.debounce.*` is the installed API for immediate validity and
  reset feedback while typing.
- non-submit actions should use `wire:loading.attr="disabled"` with a scoped
  `wire:target`.
- `assertSeeInOrder()` is available for rendered placement assertions.

FilamentExamples was searched in short OTP/input-action/loading batches and a
refined `auth-sms-otp` pass. The server exposed search/snippet access only, not
a source/read/detail tool. The useful neighboring pattern was keeping OTP state
and its verify action adjacent while clearing verification when the address
changes. PodText will not copy the example's persistence or notification
mechanics; the existing `FormVerificationManager` remains authoritative.

## Current behavior and constraints

- `PublicFormModal` already sends, verifies, and consumes OTP challenges via
  the MAIL1 manager. Server challenges are bound to channel, normalized email
  address, form key, and guest token.
- Livewire currently renders one detached verification panel after every form
  field. Its verified boolean does not reset visually when `data.email`
  changes, although `PublicFormSubmitter` correctly refuses the changed address
  because no verified challenge exists for it.
- The maintenance fallback currently renders the code field after the entire
  fields collection and the send-code button in the final actions row.
- Existing tests already use `Mail::fake()` at file setup. No live mail or HTTP
  is needed, and no Composer/npm change is allowed.

## Implementation plan

1. Livewire state and authority
   - Add server-owned code-sent and resend-available-at display state.
   - Use `updatedData()` to clear code, status, errors, verified state, and the
     client cooldown when the configured submitter email field changes.
   - Keep `PublicFormSubmitter` and `FormVerificationManager` unchanged as the
     server authority; prove a verified old address cannot submit a changed
     address.
2. Livewire field-group UX
   - Resolve the configured verification email field in `render()`.
   - Make only that email input live/debounced and render an adjacent logical
     start suffix action, disabled until the email is valid.
   - After send, render the OTP input immediately below the email row with an
     adjacent Verify suffix action; Enter invokes verify without submitting the
     outer form.
   - Put wrong/exhausted-attempt errors under the OTP control, collapse the OTP
     control after success, and show a verified badge in the same group.
   - Show the 60-second resend countdown on the send button with Alpine-local
     presentation while MAIL1's rate limiter remains authoritative.
3. Maintenance fallback layout
   - Move the send-code submit control into the configured email field group.
   - Move the existing verification-code input directly below that email row.
   - Preserve the signed two-step POST flow and add no JavaScript.
4. Copy and tests
   - Set the exact English/Hebrew action labels requested by MAIL2 and add a
     bilingual resend-countdown label.
   - Extend Livewire tests for invalid/valid send state, inline order, cooldown,
     OTP visibility, inline errors, verified collapse/badge, Enter binding,
     changed-address client reset, server refusal, re-verification, and
     consume-on-submit.
   - Extend maintenance tests with rendered marker ordering while preserving
     the existing two-step success assertion.
5. Documentation and gate
   - Update current state and the mini-step ledger, add the MAIL2 handoff with
     requirement classification and manual operator steps, then run the final
     ordered gate. Commit implementation first and immediately follow it with
     the docs-only hash backfill commit.

