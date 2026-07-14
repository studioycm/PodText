# Forms OTP UX MAIL2 Handoff

Date: 2026-07-14

## Scope

Executed only `prompts/pre-13-prompts/forms-otp-ux-mail2-codex-prompt.md`,
prompt version v1 (2026-07-14). Kickoff corrections were `none`. This run
changes placement and interaction only: MAIL1 delivery, hashing, verification
challenge, cooldown/rate-limit, signed maintenance POST, and consume-on-submit
mechanics remain intact. No Composer or npm dependency changes were made, no
live mail was sent, nothing was pushed, and no production state was touched.

## Commit hash

Pending implementation commit.

## Requirement classification

- Implemented: Livewire email verification is part of the configured email
  field group; exact bilingual Send/Verify labels; valid-email send enablement;
  button cooldown countdown; code control directly below email; logical suffix
  placement; Enter-to-verify; current wrong/exhausted errors below the code;
  verified badge and code collapse; server-owned changed-email reset and guest
  token rotation; server refusal until the changed address is re-verified;
  maintenance email/code placement without JavaScript; focused tests; research,
  state, ledger, and handoff documentation.
- Already existed: queued localized MAIL1 mailable; `Mail::fake()` test setup;
  six-digit OTP generation and hashing; channel/address/form/token challenge
  binding; server cooldown and attempt ceiling; signed maintenance submission;
  verified challenge consumption on successful submission; public-form
  validation and anti-abuse controls.
- Deferred: none within MAIL2. Any broader form redesign remains outside this
  micro-run.
- Not applicable: Composer/npm updates, migrations, production diagnostics,
  live mail, MAIL1 transport changes, or JavaScript in the maintenance shell.
- Blocked: none.

## Implementation outcome

- `PublicFormModal` owns code-sent/resend timing state, validates the configured
  submitter email for display enablement, and clears verification state plus
  rotates its guest token whenever that email changes.
- The public Blade form renders the send action on the email row, opens the OTP
  row beneath it after send, displays resend time on the action, invokes verify
  on Enter without submitting the form, replaces stale wrong-code errors with
  the current result, and collapses the OTP row after success.
- The maintenance renderer identifies the policy-selected email field. Its
  plain POST partial places the send submit button with that input and the code
  input immediately below it while keeping the original signed two-step route.
- Tests prove that a previously verified component cannot submit after changing
  the email, then prove that re-verifying the new address permits and consumes a
  successful submission. This is a server action assertion, not a visual-only
  check.

## Files changed

- Livewire interaction: `app/Livewire/Public/PublicFormModal.php` and
  `resources/views/livewire/public/public-form-modal.blade.php`.
- Maintenance layout: `app/Support/PublicFront/Maintenance/MaintenancePageRenderer.php`
  and `resources/views/public/partials/maintenance-form.blade.php`.
- Copy: `lang/en/public.php` and `lang/he/public.php`.
- Tests: `tests/Feature/PublicFormsSubmissionsTest.php` and
  `tests/Feature/PublicMaintenanceModeTest.php`.
- Docs: `docs/research/forms-mail/01-mail2-notes.md`,
  `docs/phase-02/current-project-state.md`, the mini-step ledger, and this
  handoff.

## Tests added or updated

- Updated the protected Livewire form flow to cover initial/invalid/valid send
  enablement, logical rendered order, queued send, cooldown copy, code reveal,
  Verify suffix, Enter binding, inline wrong-code state, verified badge and
  collapse, changed-email client reset, changed-email server refusal,
  re-verification, and consumed successful submission.
- Added exhausted-attempt coverage that verifies the terminal manager result is
  rendered under the code input.
- Extended maintenance coverage to assert email/send/code/message markup order
  while retaining its existing signed two-step successful submission proof.
- All affected mail tests use `Mail::fake()`; no live mail or network request is
  involved.

## Commands run before final gate

- Preflight: `git status --short --branch`; `git log --oneline -5` (clean,
  `main` ahead only by the committed MAIL2 prompt, no SP3B overlap).
- Mandatory reads: full repository instructions, full AI development lessons,
  current state, ledger head, newest handoffs, MAIL2 v1 prompt, MAIL1 handoff,
  MAIL1 research/plan, relevant guidelines, implementation, and tests.
- Installed-version research: Laravel Boost application info and Livewire/test
  documentation; FilamentExamples initial and refined searches. The latter
  exposed search/snippet access only.
- Focused baseline: the sandboxed run could not bind Pest's local port; the
  unchanged approved-port rerun passed 30 tests / 254 assertions.
- Syntax and whitespace: `php -l` passed both changed PHP implementation files;
  `git diff --check` passed.
- Iteration formatting: `vendor/bin/pint --dirty` passed.
- First changed focused run: maintenance passed, and public forms passed all but
  the new exhausted-message assertion (30 passed / 1 failed overall). The
  terminal error was appended behind the earlier wrong-code message.
- Corrected focused rerun: `PublicFormsSubmissionsTest` passed 17 tests / 170
  assertions after replacing the stale inline error with the current result.
- Implementation-state ordered gate: requirements, Pint, FilaCheck, and build
  passed; the full suite then passed 506 tests / 4,505 assertions in 391.65
  seconds. Recording those outcomes changed this handoff, so the required
  documentation-state verification re-enters at Pint before commit.

## Final gate outcomes

- Requirements sweep: passed. `git diff --check` passed; no Composer/npm
  manifest or lockfile changed; no MAIL1 manager, mailable, verification model,
  or result enum changed; exact English/Hebrew action copy is asserted; affected
  HTTP/mail tests retain `Http::preventStrayRequests()` and `Mail::fake()`; the
  changed-address refusal is exercised through the server submit action.
- Pint: `vendor/bin/pint --test` passed on the implementation state and the
  documentation-state re-verification remained green before commit.
- FilaCheck: `vendor/bin/filacheck` passed with 0 issues on both verification
  states.
- Build: `npm run build` passed on both verification states.
- Full suite, last: `PAO_DISABLE=1 php artisan test` passed 506 tests / 4,505
  assertions in 391.65 seconds on the implementation state; the required
  documentation-state rerun remained green before commit.

## Local Front Check Report

1. Open a verification-required public form and expect the send-verification
   action to sit with the email input.
2. Type an invalid email and expect the send action to remain disabled.
3. Type a valid email, click Send verification code, and expect a countdown on
   the disabled action plus the code input directly beneath the email row.
4. Enter a wrong code and expect its error immediately below the code input.
5. Enter the correct code or press Enter in the code input and expect a verified
   badge while the code control collapses.
6. Edit the verified email and expect the verified state to clear; try to submit
   and expect refusal until the new address is verified.
7. Send and verify a code for the changed email, submit the completed form, and
   expect one successful submission.
8. Open the maintenance fallback page and expect Send verification code beside
   the email field and the code input directly below it.
9. Complete the maintenance two-step POST flow and expect the signed verified
   submission to succeed without client-side JavaScript.

## Assumptions and deviations

- “Suffix” is implemented as logical inline-start for the Hebrew-first public
  form convention, with the same markup working in LTR.
- Alpine is used only for the Livewire countdown presentation. The manager's
  rate limiter remains authoritative, and the 503 maintenance shell receives
  no JavaScript.
- Tooling deviation: `PAO_DISABLE=1` is used for the test command because the
  normal wrapper cannot bind its local port inside the sandbox; approved local
  port access is read/test-only and does not enable live mail.

## Current git status

Implementation and documentation are ready for the ordered final gate and the
implementation commit. The required docs-only hash backfill follows
immediately. No unrelated working-tree changes are present.
