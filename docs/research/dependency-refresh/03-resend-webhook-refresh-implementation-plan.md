# Dependency Refresh, Resend Webhook, and Fontaine Implementation Plan

Date: 2026-07-24

Audit ID: `LS-20260724-PODTEXT-DEPENDENCY-RESEND-WEBHOOK-02`

Approved Option ID:
`DEP-REFRESH-WEBHOOK-O1-BUILTIN-SAFE-LOGGING`

Forecast exception accepted by the operator: five mini-tasks, 7–11 hours.

## Constraints

- Execute the five mini-tasks sequentially in the current checkout.
- Preserve the starting clean baseline at
  `7644ebe191c4d081e4c35b5ae86de41139214e1c`.
- Keep unrelated root dependency constraints unchanged.
- Use the official stable `resend/resend-laravel ^1.4` package and retain
  `resend/resend-php` only as its transitive dependency.
- Use Resend's built-in webhook controller, signature verifier, and Laravel
  events. Do not add a duplicate controller.
- Require a nonblank `RESEND_WEBHOOK_SECRET` and a valid raw-body signature.
- Log only the four approved operational metadata fields.
- Do not read or edit the local `.env`.
- Do not use the local development database.
- No migrations, admin event page, payload persistence, engagement/inbound/
  contact/domain processing, raw payload logging, live calls, production
  actions, Packages 4 or 5, worktree, push, or `filacheck --fix`.
- Use TDD for the webhook behavior.
- Final gate order is requirements sweep, Pint, FilaCheck, Vite build, then
  the full Laravel test suite last. After any later file change, restart at
  Pint.
- Finish with an implementation commit followed immediately by a docs-only
  commit that stamps the implementation hash into the handoff and ledger.

## Mini-task 1 — Durable Research and Executable Plan

1. Complete repository, Git, dependency, official-source, Boost, security,
   logging, and testing research.
2. Record the exact approved boundary and drift stop conditions in:
   - `docs/research/dependency-refresh/02-resend-webhook-refresh-research.md`;
   - this implementation plan.
3. Run `git diff --check` and inspect status before dependency changes.

Expected result: the architecture and five-task execution contract exist on
disk before application or dependency code changes.

## Mini-task 2 — Bounded Dependency Refresh and Boost Discovery

1. Remove the direct root requirement for `resend/resend-php` without removing
   it transitively.
2. Add `resend/resend-laravel ^1.4` as the sole direct Resend integration.
3. Add `fontaine ^0.8.0` as a development dependency.
4. Refresh Composer and npm lockfiles within the resulting root constraints.
   Do not broaden exact-pinned or major-version constraints.
5. Allow normal Composer discovery/update scripts, then inspect every changed
   manifest, lock entry, published asset, and generated file. Reconcile only
   attributable dependency output.
6. Run:
   - `composer validate --strict`;
   - `composer audit --locked`;
   - `npm audit`;
   - `npm run build` to prove the Fontaine warning is gone.
7. Run the operator-required `php artisan boost:update --discover`.
8. Inspect all Boost-generated changes. Keep only current, safe, attributable
   guidance and disclose any tooling deviation.
9. Confirm installed versions with Composer, npm, and Laravel Boost.

Expected result: the official Laravel wrapper and Fontaine are installed, the
bounded dependency graph is current, the build warning is gone, and no
unrelated major or manifest drift occurred.

## Mini-task 3 — Fail-Closed Webhook TDD and Implementation

### Red

1. Add a committed Resend webhook fixture containing operational fields and
   deliberately unique PII/raw-payload marker values.
2. Add focused Pest coverage under
   `tests/Feature/ResendWebhookIntegrationTest.php`.
3. Generate valid Svix-compatible test signatures locally over the exact raw
   body. Make no external requests.
4. First prove the stock package route violates PodText's boundary by accepting
   an unsigned request when the secret is absent.
5. Add the remaining expected tests for missing/invalid/stale/malformed
   signatures, event allowlisting, exact log context, route shape, log channel,
   and mail transport compatibility.
6. Run the focused file and record the expected failure.

### Green

1. Add `App\Http\Middleware\RequireResendWebhookSecret` to reject a missing or
   blank configured secret before the built-in controller can run.
2. Keep the official provider auto-discovered. From `AppServiceProvider`, add
   an application `booted` callback that appends the guard only to the
   discovered `resend.webhook` route after all providers register their routes.
   Do not copy or manually register the package provider, route, or controller.
3. Add `App\Listeners\ResendWebhookEventSubscriber` with an explicit mapping
   for the seven approved operational events.
4. Register the subscriber once through `AppServiceProvider`.
5. Add the `resend_webhook` daily logging channel.
6. Prefer and document `RESEND_API_KEY`, preserve `RESEND_KEY` as a fallback,
   and add an empty `RESEND_WEBHOOK_SECRET` example.
7. Run the focused test file until green, then run nearby mail/public-form
   regressions without live mail, HTTP, or database probes.

### Refactor

1. Remove duplication while keeping the event allowlist and log context
   visible.
2. Keep all extracted values type-checked and bounded.
3. Do not catch logging failures or add persistence, queues, throttles, CSRF
   exceptions, IP allowlists, or new controllers.
4. Re-run focused tests after every refactor.

Expected result: unsigned or invalid requests fail closed; valid requests use
the vendor controller/verifier/events; only approved delivery evidence is
logged.

## Mini-task 4 — Focused Review and Inspection

1. Run route and event inventory commands to confirm one webhook endpoint, the
   package controller, required middleware, and subscriber registrations.
2. Run focused Resend/mail/form tests, Pint on changed PHP, and
   `vendor/bin/filacheck --dirty` if applicable.
3. Perform the Laravel Simplifier Stage 2 review:
   - remove unnecessary abstractions and comments;
   - verify no duplicated package behavior;
   - verify narrow configuration and logging boundaries;
   - verify no hidden outbound or persistence side effects.
4. Retry the PhpStorm inspection MCP against every changed PHP file at
   `WARNING` or `WEAK_WARNING`, fix applicable findings, and rerun it. If the
   MCP remains unavailable, record that precise tooling limitation.
5. Run `composer validate`, Composer audit, npm audit, `git diff --check`, and
   inspect the full diff for secret, generated-file, and scope leakage.

Expected result: focused behavior is green, the implementation is minimal, and
all review findings are resolved or explicitly documented.

## Mini-task 5 — Canonical Verification, Handoff, and Local Closeout

1. Perform a requirement-by-requirement sweep and classify every approved and
   excluded item.
2. Update:
   - `docs/phase-02/current-project-state.md`;
   - `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`;
   - a new committed dependency/Resend handoff with requirement
     classification, files, tests, every command/result, gate outcomes,
     deploy key names, assumptions, deferred issues, and numbered imperative
     Local Front Check steps.
3. Run the canonical final gates in this exact order on the final code state:
   - `vendor/bin/pint --test`;
   - `vendor/bin/filacheck`;
   - `npm run build`;
   - full `php artisan test` last.
4. If any file changes after a gate begins, restart at Pint.
5. Stage only attributable files and run staged diff/secret/whitespace checks.
6. Commit code, tests, dependency files, research, state, ledger, and handoff
   with the handoff commit hash still marked pending.
7. Immediately replace the pending value in the handoff and ledger with the
   implementation commit hash and make the docs-only
   `docs: backfill dependency resend webhook hash` commit.
8. Confirm a clean `main`, report ahead/behind, and do not push.

Expected result: all gates are honestly recorded, the canonical two-commit
ending is complete, and the checkout is clean and local-only.
