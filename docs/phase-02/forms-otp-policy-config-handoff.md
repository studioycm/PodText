# OTP-POLICY1 Handoff — OTP Policy Config and Expiry Copy

Date: 2026-07-15

## Scope

This ad-hoc run moves the three email OTP policy numbers from class constants
to env-backed Laravel config, changes the expiry default from ten to five
minutes, repairs singular/plural expiry copy, and adds an expiry hint under the
code input on the Livewire and maintenance fallback surfaces. No dependency,
database, production, mail-transport, or OTP-mechanics change is included.
Operator review also corrected the adjacent actions to logical inline-end,
which is left in Hebrew RTL and right in English LTR.

## Commit hash

`0394ab5` (`Configure OTP verification policy and copy`).

## Requirement classification

- Implemented: `FORMS_OTP_EXPIRES_MINUTES` with default `5`,
  `FORMS_OTP_MAX_ATTEMPTS` with default `5`, and
  `FORMS_OTP_RESEND_COOLDOWN_SECONDS` with default `60` under `forms.otp`.
- Implemented: `.env.example` entries and the production deploy note below.
- Implemented: all manager, mailable, Livewire, and maintenance signed-URL
  consumers read the shared typed config boundary; no public policy constants
  remain.
- Implemented: `public.forms.verification.mail.expires` uses explicit
  singular/plural choices through `trans_choice()` in English and Hebrew.
- Implemented: bilingual, config-fed expiry hints beneath both code inputs.
- Implemented after operator visual review: send-code and verify-code actions
  render at logical inline-end on the custom Livewire groups, and the
  maintenance send-code action follows the same RTL/LTR placement.
- Implemented: config-default and override behavior tests, five-minute default
  coverage, bilingual singular/plural email coverage, and both rendered hints.
- Already existed and preserved: six-digit generation, HMAC hashing, challenge
  binding, cooldown/hourly refusal, attempt increment/terminal refusal,
  verification, signed maintenance submission, and consume-on-submit flow.
- Not applicable: migrations, dependencies, live network/mail, production
  commands, Filament changes, or FilamentExamples research.
- Deferred: none within this task.
- Blocked: none.

## Files changed

- Config/deploy example: `config/forms.php`, `.env.example`.
- Policy consumers: `FormVerificationManager`, OTP mailable, maintenance
  verification controller, Livewire public form, and maintenance renderer.
- Visitor UI: OTP email Markdown, Livewire public-form Blade, maintenance-form
  Blade, and English/Hebrew public translations.
- Tests: `FormVerificationManagerTest`, `PublicFormsSubmissionsTest`, and
  `PublicMaintenanceModeTest`.
- Docs: OTP policy research/plan, current project state, mini-step ledger, and
  this handoff.

## Tests added or updated

- Config defaults and `.env.example` values are asserted together.
- Cooldown, maximum attempts, and expiry tests override `forms.otp` values and
  prove the existing mechanics honor them.
- The former ten-minute expiry assumption now asserts the config-fed window;
  the shipped default is explicitly asserted as five minutes.
- Mailable rendering proves Hebrew singular and English plural expiry forms.
- Livewire renders an English seven-minute hint and maintenance renders the
  Hebrew seven-minute hint from the same config key.
- Livewire and maintenance rendering assert normal logical flex order and an
  `inline-end` action marker; reversed flex order is rejected.

## Production deploy note

Production may omit all three new variables to accept the documented
`5`/`5`/`60` defaults, or set the three `FORMS_OTP_*` names explicitly. The
normal deployment must rebuild Laravel's config cache after env changes (for
example through its existing `php artisan config:cache` step) and recycle
long-lived application/queue workers through the existing deploy process so
they load the new cached config. No production env or process action was taken
in this session.

## Command record

- Preflight: `git status --short --branch`; `git log -n 8 --oneline` — clean
  `main` checkout before task changes.
- Laravel Boost `application_info` and `search_docs` — confirmed installed
  Laravel/Livewire/Pest versions plus config-cache and pluralization APIs.
- Initial sandboxed focused baseline — failed because Pest Browser could not
  bind a local loopback port; this was an environment restriction, not a test
  assertion failure.
- Approved loopback-port baseline:
  `PAO_DISABLE=1 php artisan test --compact tests/Feature/FormVerificationManagerTest.php tests/Feature/PublicFormsSubmissionsTest.php tests/Feature/PublicMaintenanceModeTest.php`
  — passed 35 tests / 324 assertions.
- PHP syntax checks for every changed PHP implementation/test file — passed.
- `git diff --check` — passed before focused verification.
- Changed focused suite using the same command — passed 37 tests / 331
  assertions.
- Operator-review corrected focused suite using the same command — passed 37
  tests / 338 assertions.
- `vendor/bin/pint --dirty --format agent` — passed; no formatting rewrite was
  needed.
- AST/token-level duplicate-key scan for `lang/en/public.php` and
  `lang/he/public.php` — passed with no duplicate keys.

## Final gate outcomes

- Final corrected-state requirements sweep passed: former constants are absent,
  all env/config/translation consumers are present, `git diff --check` passed,
  logical inline-end action markers and normal flex rows are present, reversed
  flex rows are absent from both surfaces, and no dependency manifest changed.
- `vendor/bin/pint --test` — passed.
- `vendor/bin/filacheck` — passed with 0 issues.
- `npm run build` — passed.
- Before the inline-end review correction, `PAO_DISABLE=1 php artisan test`
  passed 536 tests / 4,799 assertions twice (247.71 and 247.14 seconds).
- After the inline-end review correction and its assertions, the ordered final
  gate passed again: Pint, FilaCheck (0 issues), production build, and
  `PAO_DISABLE=1 php artisan test` last at 536 tests / 4,806 assertions.

## Local Front Check Report

1. Open a verification-required public form in Hebrew.
2. Enter a valid email and click the send-code action.
3. Expect the send-code action to appear on the left of the email input.
4. Expect the code field to appear with “הקוד תקף ל-5 דקות” immediately under
   the code control.
5. Expect the verify action beside the code input to appear on the left.
6. Switch the public locale to English, repeat the send step, and expect both
   actions on the right and “The
   code is valid for 5 minutes.” under the code control.
7. Open the maintenance fallback with an OTP-required form, request a code, and
   expect its send-code action on the left in Hebrew plus the same configured
   expiry beneath its code input.
8. Set a disposable environment's expiry to one minute, rebuild its config
   cache, and expect singular Hebrew and English email/hint grammar.
9. Complete each form with a correct code and expect the existing successful
   consume-on-submit behavior.

## Assumptions and deviations

- The new values are cast to integers in `config/forms.php`; no clamping was
  added because that would alter policy behavior beyond the request.
- The public manager exposes typed config accessors so every consumer uses the
  same config paths without duplicating casts or defaults.
- Tooling deviation: focused tests required approved local loopback-port access
  after the sandbox denied Pest Browser's port probe. The test run remained
  read-only outside SQLite memory/storage fakes and sent no live mail.

## Current git status

The implementation was committed as `0394ab5`. No production change was made.
