# Dependency Refresh, Resend Webhook, and Fontaine Handoff

Date: 2026-07-24

## Approved contract

- Audit ID:
  `LS-20260724-PODTEXT-DEPENDENCY-RESEND-WEBHOOK-02`
- Approved option:
  `DEP-REFRESH-WEBHOOK-O1-BUILTIN-SAFE-LOGGING`
- Approved forecast exception: five sequential mini-tasks, 7–11 engineering
  hours.
- Required scope: bounded Composer/npm refresh, replace the direct Resend PHP
  SDK with the official Laravel wrapper, install Fontaine, run Laravel Boost
  discovery, retain Resend's built-in webhook controller and Laravel events,
  fail closed without a configured signing secret, and log only allowlisted
  operational delivery metadata.
- Exclusions: migrations, database persistence, a local admin event page,
  engagement/inbound/contact/domain processing, raw payload logging, live mail
  or webhook calls, production actions, Packages 4 and 5, and push.

## Baseline and drift

- Working directory and Git root:
  `/Users/studioycm/Herd/PodText`.
- Starting branch: clean `main`, 21 commits ahead of `origin/main`.
- Starting HEAD:
  `7644ebe191c4d081e4c35b5ae86de41139214e1c`.
- Runtime: PHP 8.4, Node.js 22.23.1, npm 10.9.8.
- Initial application stack: Laravel 13.21.1, Filament 5.7.3, Livewire 4.3.3,
  Horizon 5.48.1, Boost 2.4.13, Pest 4.7.5, Tailwind CSS 4.3.3, and Laravel
  Vite plugin 3.1.3.
- No unexpected application, migration, schema, user-owned, or concurrent
  work appeared during the run.
- No material baseline, scope, security, dependency-major, migration,
  task-count, or effort drift occurred.

## Outcome

The direct `resend/resend-php` root requirement is replaced by the official
`resend/resend-laravel` 1.4.0 integration. The wrapper retains
`resend/resend-php` 1.6.0 as its transitive SDK dependency. The bounded
Composer refresh also moves `google/auth` from 1.52.0 to 1.53.0. All other
direct application versions remain unchanged; exact Shield 4.2.0 and Spatie
Permission 7.3.0 pins and out-of-range concurrently 10 remain intentionally
preserved.

Fontaine 0.8.0 is now a development dependency. It satisfies Laravel Vite
plugin 3.1.3's optional `fontaine ^0.8.0` peer, and the frontend build no
longer emits the optimized-font-fallback warning. npm's in-range refresh also
moves PostCSS from 8.5.21 to 8.5.22.

The operator-required `php artisan boost:update --discover` completed after
the package refresh. Its attributable generated changes synchronize
`AGENTS.md`, `CLAUDE.md`, the Claude/Junie Laravel guidance, the Socialite skill
discovery, and `boost.json` with the installed stack and the repository-owned
audit skills. No Filament public asset changed in this refresh.

The package's single auto-discovered `POST /resend/webhook` route and built-in
controller remain authoritative. PodText adds only:

1. a route-specific guard that returns 503 when
   `RESEND_WEBHOOK_SECRET` is missing, empty, whitespace-only, or the
   PHP-false string `0`;
2. the wrapper's built-in raw-body signature verifier after that guard; and
3. one synchronous Laravel event subscriber for the seven approved delivery
   events.

The subscriber writes one `info` record to a dedicated daily
`resend_webhook` channel with exactly four context keys:

- `event_type`;
- `svix_id`;
- `email_id`; and
- `event_created_at`.

Every value is type-checked and bounded to 255 characters. The payload,
addresses, subject, message body, tags, links, IP, failure text, signature,
API key, and signing secret never enter the logger. Opened, clicked, received,
contact, domain, and currently unsupported scheduled events are acknowledged
by the built-in controller but have no PodText subscriber or operational log.

## Route and security boundary

The wrapper adds its verifier to the controller only when its signing secret
is truthy. PodText therefore attaches the fail-closed guard to the discovered
named route from an application `booted` callback. At that callback point,
Laravel has the actual route objects but has not yet refreshed the separate
name-lookup cache, so the implementation scans the route collection for the
route object's own `resend.webhook` name instead of using `getByName()`.

The resulting middleware order is:

1. `App\Http\Middleware\RequireResendWebhookSecret`;
2. `Resend\Laravel\Http\Middleware\VerifyWebhookSignature`;
3. `Resend\Laravel\Http\Controllers\WebhookController@handleWebhook`.

The route stays outside the `web` middleware group. No CSRF exception,
duplicate provider, route, controller, signature implementation, IP allowlist,
rate limiter, queue, cache, or persistence layer was added.

Missing/invalid/stale/malformed signatures are rejected before dispatch or
logging. A deliberately malformed bare `v1` signature is covered by the real
Laravel integration test and returns 403: Laravel converts the SDK's
undefined-offset warning to an `ErrorException`, which the wrapper converts to
access denied. The literal secret `0` is also rejected before dispatch because
the package treats that string as false and would otherwise omit its verifier.
No broad `Throwable` catch was added to application code.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Durable research and implementation plan before code | Implemented | Research and the exact five-mini-task plan were written before dependency/application changes. |
| Bounded Composer refresh | Implemented | Wrapper 1.4.0 added, SDK retained transitively, and only in-range `google/auth` 1.53.0 moved. |
| Bounded npm refresh | Implemented | Fontaine 0.8.0 added and PostCSS moved to 8.5.22; no major constraint changed. |
| Replace direct SDK with Laravel wrapper | Implemented | `resend/resend-laravel ^1.4` is the only direct Resend package. |
| Install Fontaine and remove build advisory | Implemented | `fontaine ^0.8.0` is installed and the build warning is absent. |
| Run Boost discovery after updates | Implemented | Discovery completed and attributable generated guidance was retained. |
| Use built-in webhook controller and events | Implemented | One package route/controller handles verified payloads; PodText subscribes to package events. |
| Fail closed without signing secret | Implemented | Null, empty, whitespace-only, and PHP-false `0` secrets return 503 before dispatch. |
| Reject invalid signatures | Implemented | Missing, invalid, stale, and malformed signatures return 403 with no operational log. |
| Log only operational delivery metadata | Implemented | The log context is exactly the approved four-key allowlist. |
| Process seven delivery events | Implemented | Sent, delivered, delayed, bounced, complained, failed, and suppressed events log synchronously. |
| Engagement/inbound/contact/domain processing | Deferred / excluded | PodText registers no listeners or logging for those package events. |
| Raw payload or PII logging | Not applicable / prohibited | Marker-based negative assertions prove the subscriber does not pass them to its logger. |
| Migrations or database persistence | Not applicable / excluded | No migration, model, table, or persistence path changed. |
| Admin webhook event page | Deferred / excluded | No Filament page, Resource, widget, or navigation item was added. |
| Live mail/webhook calls | Not applicable / prohibited | Tests use local fixtures/signatures; transport resolution sends no message. |
| Package 4 or Package 5 | Deferred / excluded | Neither package entered the diff. |
| Production actions or push | Not authorized | None performed. |
| Canonical local closeout | Implemented | Implementation and immediate docs-only hash-stamp commits are recorded below. |

## Files changed

### Dependency and environment examples

- `composer.json`
- `composer.lock`
- `package.json`
- `package-lock.json`
- `.env.example`
- `config/services.php`

### Webhook application boundary

- `app/Http/Middleware/RequireResendWebhookSecret.php`
- `app/Listeners/ResendWebhookEventSubscriber.php`
- `app/Providers/AppServiceProvider.php`
- `config/logging.php`

### Tests and fixture

- `tests/Feature/ResendWebhookIntegrationTest.php`
- `tests/Fixtures/resend/email-delivered.json`

### Laravel Boost discovery output

- `AGENTS.md`
- `CLAUDE.md`
- `boost.json`
- `.claude/skills/laravel-best-practices/SKILL.md`
- `.claude/skills/laravel-best-practices/rules/style.md`
- `.claude/skills/socialite-development/SKILL.md`
- `.junie/skills/laravel-best-practices/SKILL.md`
- `.junie/skills/laravel-best-practices/rules/style.md`
- `.junie/skills/socialite-development/SKILL.md`

### Reconciled Boost guideline sources

- `.ai/guidelines/search-filters.md`
- `.ai/guidelines/taxonomy-tags.md`

### Durable documentation

- `docs/research/dependency-refresh/02-resend-webhook-refresh-research.md`
- `docs/research/dependency-refresh/03-resend-webhook-refresh-implementation-plan.md`
- this handoff
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## Tests added

`tests/Feature/ResendWebhookIntegrationTest.php` provides 27 fixture-backed
tests / 88 assertions covering:

- null, empty, whitespace-only, and PHP-false `0` secret fail-closed behavior;
- the single package route, built-in controller, app guard, built-in verifier,
  and absence of the `web` group;
- missing, invalid, malformed, and stale signatures;
- an exact raw-body valid HMAC signature and built-in event dispatch;
- exact four-key logging for all seven approved delivery event classes;
- no logging for opened, clicked, received, contact, domain, or scheduled
  events;
- sentinel sender, recipient, subject, body, tag, URL, IP, failure, signature,
  and secret values never reaching the operational log;
- log-write failure returning 500 so Resend can retry rather than losing the
  only approved evidence;
- the dedicated daily log path, level, and retention;
- separate outbound API and inbound signing key examples; and
- blank canonical-key fallback to the legacy outbound key and real wrapper
  mail-transport resolution without constructing or sending mail.

Each test calls `Http::preventStrayRequests()`. Signatures are generated
locally over committed fixture bytes. No test uses the development database,
live mail, or a live webhook.

## Commands and results

Read-only orientation covered cwd/Git-root/status/log/HEAD, the mandatory
lessons/state/ledger/handoffs, manifests/locks, installed source, adjacent
application/tests, and exact Stage 2 approval. Secret values and the local
`.env` were never read or printed.

| Command / check | Result |
|---|---|
| Mandatory preflight and Simplifier Stage 2 approval match | PASS; clean `main`, exact Audit/Option/five-task forecast and exclusions, no drift. |
| Laravel Boost `application_info` and version-aware documentation searches | PASS; installed stack and Laravel provider/middleware/events/logging/testing behavior confirmed. |
| Official Resend, wrapper v1.4.0, SDK, Laravel Vite, and Fontaine source/release research | PASS; built-in route/controller/events, fail-open missing-secret condition, wrapper dependency graph, and Fontaine peer contract confirmed. |
| `composer outdated --direct --format=json` | PASS; only excluded exact-pin/major opportunities found. |
| Initial sandboxed `npm outdated --json` | Infrastructure FAIL after DNS isolation: `ENOTFOUND registry.npmjs.org`; no npm log could be written outside the workspace. |
| Escalated `npm outdated --json` | PASS; only concurrently 10 is outside the manifest. |
| `npm view fontaine ...` | PASS; 0.8.0 is current and compatible. |
| `composer show resend/resend-laravel --all` | PASS; stable 1.4.0 supports Illuminate 13 and requires the SDK transitively. |
| Baseline `composer audit --locked --format=json` | PASS; no advisory or abandoned package. |
| Baseline `npm audit --json` | PASS; zero vulnerabilities. |
| `composer remove resend/resend-php --no-update --no-interaction` | PASS; direct SDK requirement removed without changing the lock. |
| `composer require resend/resend-laravel:^1.4 --no-update --no-interaction` | PASS; wrapper requirement added without resolving yet. |
| `npm install --save-dev fontaine@^0.8.0` | PASS; Fontaine graph added, zero vulnerabilities. |
| `composer update --with-all-dependencies --no-interaction` | PASS; wrapper 1.4.0 installed, SDK 1.6.0 retained transitively, `google/auth` moved to 1.53.0, discovery scripts completed. |
| `npm update` | PASS; PostCSS moved to 8.5.22 within constraints. |
| `npm prune` | PASS; dependency tree pruned. |
| `npm ci` | PASS; exact lock reinstall completed with zero vulnerabilities. |
| `npm ls --depth=0` | Completed; npm 10 on this Darwin platform still reports five optional WASM helper packages as extraneous. They were present in the starting lock and reproduce after `npm ci`; there is no invalid peer or attributable manifest drift. |
| `php artisan boost:update --discover --no-interaction` | PASS; generated guidance and skill discovery synchronized. |
| Initial multi-package `composer show --locked ...` | Command-shape FAIL: Composer rejected multiple positional package names. |
| `composer show --locked --direct --format=json` and corrected installed-package checks | PASS; direct wrapper and unchanged core versions confirmed. |
| `composer validate --strict` | `composer.json` is valid; strict mode exits 1 only for the repository's pre-existing exact Shield 4.2.0 and Permission 7.3.0 pins, both excluded from this run. |
| Post-refresh Composer audit | PASS outside network isolation; no advisory or abandoned package. |
| Post-refresh npm audit | PASS outside network isolation; zero vulnerabilities. |
| Initial sandboxed `npm run build` | Infrastructure FAIL: Bunny font DNS was unavailable. |
| Identical escalated `npm run build` | PASS; Fontaine warning absent. |
| First focused TDD RED | Expected RED: 23 tests, 0 passed, 13 failures, 10 test-construction/environment errors. Test-only warning/expectation issues were corrected before application code. |
| Corrected focused TDD RED outside the browser sandbox | Expected RED: 23 tests, 12 passed, 10 failed, 1 error; failures exposed stock missing-secret acceptance and absent app logging/configuration. |
| First GREEN attempt | 21 of 23 passed; two route-guard assertions exposed Laravel's not-yet-refreshed name lookup inside the application `booted` callback. |
| Read-only route boot probe | Initial probe omitted autoload and failed; corrected probe reported `lookup=missing`, `scan=1`, confirming collection scan as the narrow fix. |
| Focused GREEN before final review | PASS: 23 tests / 79 assertions. |
| `php artisan test tests/Feature/FormVerificationManagerTest.php tests/Feature/PublicFormsSubmissionsTest.php tests/Feature/PublicMaintenanceModeTest.php` | PASS outside the browser sandbox: 68 tests / 649 assertions. |
| `vendor/bin/pint --dirty` | Fixed test-only strict operator spacing; later focused rerun stayed green. |
| Sandboxed focused rerun | Infrastructure FAIL: Pest browser helper could not bind a local port (`Operation not permitted`). |
| Identical focused rerun outside the sandbox | PASS before final blank-secret expansion: 23 / 79. |
| Independent security review secret-`0` regression | Expected RED: three dataset cases passed and the `0` case returned 200 instead of 503, proving the verifier-omission edge. |
| Final focused test after secret/fallback/route/log-failure corrections | PASS after formatting: 27 tests / 88 assertions. |
| `php artisan route:list --name=resend.webhook -vv` | PASS; exactly one route with app guard then built-in verifier/controller. |
| `php artisan event:list --event=Resend\\Laravel\\Events\\EmailDelivered` | PASS; one subscriber registration at `record`. |
| Initial wildcard event-list filter | Command-shape limitation: Artisan returned no match; exact class filter succeeded. |
| `vendor/bin/filacheck --dirty` | PASS with 0 issues. |
| PhpStorm inspection MCP | Initial root-tool discovery did not expose `get_inspections`; `codex mcp list` showed PhpStorm enabled and its local SSE endpoint responded 200 outside the sandbox. A read-only review agent then discovered the MCP router and completed inspections. Final result: middleware, subscriber, both configs, and the test file have 0 problems at the recorded thresholds; `AppServiceProvider` has only one pre-existing unrelated WEAK_WARNING at line 178. |
| Final review `composer validate --strict` | Valid with only the same intentional exact-pin warnings. |
| Final review Composer audit | Initial sandbox DNS/cache attempt failed; identical escalated audit PASS with no advisory/abandoned package. |
| Final review npm audit | Initial sandbox DNS attempt failed; identical escalated audit PASS with zero vulnerabilities. |
| `git diff --check` review passes | PASS; no whitespace error. |
| First complete ordered gate on completion docs | PASS after the final security/review corrections: Pint; FilaCheck with 0 issues; Vite without the Fontaine warning; full suite last, serial, and outside the macOS browser sandbox at 1,107 tests / 14,253 assertions. |
| Final exact-documentation-state ordered gate | PASS: Pint; FilaCheck with 0 issues; Vite without the Fontaine warning; full suite last, serial, and outside the macOS browser sandbox at 1,107 tests / 14,253 assertions. |

## Review findings and limitations

- The Laravel Simplifier review found no duplicate provider, route, controller,
  verifier, persistence, queue, or speculative abstraction.
- Independent security review found and corrected the package-truthiness edge
  for a literal `0` secret, the blank canonical-key legacy fallback, the
  one-route regression gap, and missing log-failure retry proof. It also
  confirmed the malformed bare-`v1` signature returns 403 in PodText's real
  Laravel runtime.
- PhpStorm inspection completed through the configured MCP router after the
  root discovery limitation. The subscriber's one typed-constant weak warning
  was corrected; final application/config files have no new inspection
  problem, and the test file has no WARNING-or-higher problem.
- Two Boost-discovered relationship rules initially contradicted the
  repository's bounded/no-preload standard. Their canonical `.ai/guidelines`
  sources were corrected and Boost discovery reran, leaving `AGENTS.md` and
  `CLAUDE.md` consistent.
- Composer's strict validation warnings are not new defects. Broadening the two
  exact root pins would violate the approved dependency boundary.
- npm's five optional Darwin WASM `extraneous` entries are reproducible from
  the starting lock after a clean `npm ci`; they are not introduced by
  Fontaine and do not represent invalid application dependencies.

## Deployment notes and deferred work

No deployment or production action was performed. A future authorized
deployment must:

1. set `MAIL_MAILER=resend`;
2. set the outbound `RESEND_API_KEY`;
3. set a separate nonblank `RESEND_WEBHOOK_SECRET`;
4. rebuild the normal Laravel configuration cache through the deployment
   process;
5. configure the Resend dashboard endpoint as `/resend/webhook`; and
6. subscribe only to the seven supported operational email events unless a
   separately audited wrapper upgrade expands the contract.

`RESEND_KEY` remains a compatibility fallback only when the canonical key is
absent or blank. New deployments should use `RESEND_API_KEY`.

Database event history, deduplication, an admin page, engagement analytics,
inbound email, contact/domain events, `email.scheduled` support, raw payload
retention, and production verification remain separate future decisions.
Package 4 and Package 5 remain separately gated.

## Local Front Check Report

These are manual operator steps, not claims that live Resend traffic was used:

1. Open the Resend dashboard for a non-production test account and create a
   webhook pointing to the disposable environment's `/resend/webhook` path.
2. Select only sent, delivered, delivery delayed, bounced, complained, failed,
   and suppressed email events; leave opened, clicked, received, contact, and
   domain events unselected.
3. Temporarily leave `RESEND_WEBHOOK_SECRET` absent in that disposable
   environment; send a test delivery and expect the endpoint to return 503
   without a delivery-log entry.
4. Set the dashboard-provided signing secret as
   `RESEND_WEBHOOK_SECRET`, rebuild configuration through the normal deployment
   process, and replay the test event; expect a successful response.
5. Open only the dedicated `resend-webhook` log; expect one structured line
   containing event type, Svix delivery ID, Resend email ID, and event
   timestamp.
6. Inspect that line; expect no sender, recipient, subject, body, tags, URL,
   IP, failure detail, signature, API key, signing secret, or raw JSON.
7. Send or replay an invalidly signed request; expect 403 and no operational
   delivery-log entry.
8. Trigger an ordinary queued OTP mail in a disposable environment configured
   with `MAIL_MAILER=resend` and `RESEND_API_KEY`; expect the existing localized
   mail flow to complete through the wrapper transport.
9. Build frontend assets; expect the build to finish without the optional
   Fontaine optimized-fallback warning.

Do not run these checks against local development or production without a new
exact environment-action approval.

## No-environment-mutation statement

No migration, local-development database/storage/cache probe, live mail,
live webhook, production, deployment, worker/process, branch, worktree, or
push action occurred. Composer/npm changed only the approved local dependency
graph. Composer's normal package discovery scripts and Laravel Boost discovery
updated attributable local generated guidance; no production system was
touched.

## Commit hash

Pending
