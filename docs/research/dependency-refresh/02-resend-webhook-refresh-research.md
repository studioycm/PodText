# Dependency Refresh, Resend Webhook, and Fontaine Research

Date: 2026-07-24

Audit ID: `LS-20260724-PODTEXT-DEPENDENCY-RESEND-WEBHOOK-02`

Approved Option ID:
`DEP-REFRESH-WEBHOOK-O1-BUILTIN-SAFE-LOGGING`

## Approved Boundary

This run may:

- refresh Composer and npm lockfiles within the existing manifest constraints;
- replace the direct `resend/resend-php` requirement with the official
  `resend/resend-laravel` integration;
- add the Laravel Vite plugin's optional `fontaine` peer dependency;
- run `php artisan boost:update --discover` after the dependency refresh;
- reuse Resend Laravel's built-in webhook controller and Laravel events;
- reject every webhook unless `RESEND_WEBHOOK_SECRET` is configured and the
  built-in signature verifier accepts the raw request;
- synchronously log only allowlisted operational delivery metadata; and
- add tests, durable docs, and the canonical local two-commit closeout.

This run excludes migrations, database persistence, an admin event page,
engagement events, inbound email, contact/domain events, raw payload logging,
live mail or webhook calls, production actions, Packages 4 and 5, and push.

## Preflight and Provenance

- Working directory and Git root:
  `/Users/studioycm/Herd/PodText`.
- Branch: `main...origin/main [ahead 21]`.
- Starting HEAD:
  `7644ebe191c4d081e4c35b5ae86de41139214e1c`.
- The worktree was clean at Stage 2 start.
- The latest completed work is Package 3 post-acquisition UX:
  `5655222 feat: improve post-P3 media acquisition picker`, followed by its
  docs-only hash stamp.
- No prompt file was named. This is the approved ad-hoc Stage 2 contract.
- No local database, storage, cache, mail, webhook, or production probe was
  performed.

## Installed and Available Dependency Evidence

Laravel Boost reported the installed application as PHP 8.4, Laravel 13.21.1,
Filament 5.7.3, Livewire 4.3.3, Horizon 5.48.1, Boost 2.4.13, Pest 4.7.5, and
Tailwind CSS 4.3.3.

The direct Resend dependency is currently `resend/resend-php` 1.6.0.
`composer show resend/resend-laravel --all` and the official release list both
identify 1.4.0 as the current stable Laravel integration. Its Composer metadata
supports Laravel 13 and requires `resend/resend-php ^1.0.0`. Replacing the
direct SDK with `resend/resend-laravel ^1.4` therefore retains the SDK as a
transitive dependency instead of installing two competing integrations.

The bounded refresh preserves all unrelated root constraints. In particular:

- `bezhansalleh/filament-shield` remains exact-pinned at 4.2.0 even though
  4.3.0 is available; broadening that manifest constraint is not approved.
- `spatie/laravel-permission` remains exact-pinned at 7.3.0; its available
  major versions are outside the approved boundary.
- npm reports only `concurrently` 10.0.3 as newer than the allowed major.
  The current constraint resolves 9.2.4, so no major update is allowed.

The Laravel Vite plugin 3.1.3 declares optional peer
`fontaine ^0.8.0`. npm reports 0.8.0 as the current release. The installed
plugin enables optimized fallbacks by default and emits the current build
warning when Fontaine is absent. Adding `fontaine ^0.8.0` as a development
dependency preserves the existing font configuration while enabling
metric-adjusted local fallbacks. Fontaine performs build-time CSS generation
and adds no browser runtime.

Pre-refresh security baselines:

- `composer audit --locked --format=json`: no advisories or abandoned
  packages.
- `npm audit --json`: zero vulnerabilities.

The first sandboxed npm registry checks failed with
`ENOTFOUND registry.npmjs.org`. The identical read-only checks succeeded with
approved network access. This is environment/network isolation, not an
application defect.

## Official Resend Laravel Behavior

The official Laravel guide instructs applications to:

- install `resend/resend-laravel`;
- use `RESEND_API_KEY`;
- keep the existing Laravel `resend` mail transport;
- receive webhooks at `POST /resend/webhook`;
- react to package-dispatched Laravel events; and
- configure the separate `RESEND_WEBHOOK_SECRET` to verify webhook signatures.

Stable 1.4.0 source inspection adds important implementation details:

1. `ResendServiceProvider` merges `config/resend.php`, registers the mail
   transport, and registers the built-in webhook route.
2. The route is loaded directly by the package provider, not through the
   application's `web` or `api` groups. PodText therefore must not add a CSRF
   exception.
3. `WebhookController` parses the raw JSON, maps supported event names to
   package event classes, and dispatches those events synchronously.
4. `VerifyWebhookSignature` delegates to
   `Resend\WebhookSignature::verify()` using the raw body and Svix headers.
5. The controller attaches that verifier only when
   `config('resend.webhook.secret')` is truthy. With no secret, the stock route
   accepts unsigned requests. That default conflicts with the approved
   fail-closed boundary and must be guarded by PodText.
6. Package event objects expose `public array $payload` but not request
   headers. Because dispatch is synchronous, an application subscriber may
   read `svix-id` from the current request without persisting or copying the
   raw payload.
7. Stable 1.4.0 dispatches the seven approved operational events:
   `email.sent`, `email.delivered`, `email.delivery_delayed`,
   `email.bounced`, `email.complained`, `email.failed`, and
   `email.suppressed`.

Resend documents webhook delivery as at-least-once and out of order. The
`svix-id` header is the delivery correlation identifier. This option does not
add persistence, so replayed deliveries may create repeated log lines. That is
acceptable for an operational log and is preferable to introducing an
unapproved migration or cache-based authority.

## Fail-Closed Route Design

PodText will keep the built-in controller, built-in signature middleware, and
built-in events. It will not add a second webhook controller.

The smallest stable-1.4-compatible guard keeps normal Composer discovery:

1. allow `Resend\Laravel\ResendServiceProvider` to register its own client,
   mail transport, configuration, route, and controller;
2. add one route-specific PodText middleware that returns a non-success
   response when the configured webhook secret is absent or blank; and
3. from `AppServiceProvider`, use an application `booted` callback to append
   that middleware to the discovered `resend.webhook` route after every
   provider has booted. With a secret present, the guard passes through and
   the built-in controller still attaches and executes Resend's verifier.

Installed Laravel 13 source confirms that application `booted` callbacks run
after every provider's `boot()` method. Route middleware is deduplicated when
gathered, so the same mutation remains safe with a route cache. Laravel's
route-name lookup cache is refreshed by a later application `booted` callback,
so PodText must scan the route collection for the route's own name at this
point instead of calling `getByName()`. This avoids a global middleware,
duplicate provider registration, copied vendor route, or custom controller.
The route-name contract is covered by tests so a future package route change
cannot pass the dependency gate unnoticed.

The endpoint remains outside the `web` group. No CSRF exception, IP allowlist,
or application rate limiter is added. Signature verification is the security
boundary, and Resend retries/bursts must not be blocked by an unrelated
throttle.

## Event Logging Design

A single synchronous Laravel event subscriber will subscribe only to the seven
approved operational package events. It will write one structured `info`
record to a dedicated daily `resend_webhook` channel.

The context allowlist is exact:

- `event_type`: an application-owned constant derived from the event class;
- `svix_id`: the current request's `svix-id` header;
- `email_id`: `data.email_id` from the verified event payload; and
- `event_created_at`: the payload's top-level `created_at`.

Values will be accepted only as bounded strings. The logger will not receive
the payload array or any recipient, sender, subject, body, tags, URL, failure
message, signature, API key, or webhook secret.

The channel follows the existing `import_export` pattern:

- daily file at `storage/logs/resend-webhook.log`;
- `info` default level; and
- 14-day retention through the shared `LOG_DAILY_DAYS` setting.

Logging stays synchronous. A 200 response means the listener completed. Log
write failures are not swallowed, allowing Resend's documented non-200 retry
behavior to preserve operational evidence.

## Configuration Compatibility

The wrapper's canonical outbound key is `RESEND_API_KEY`. PodText will make
that the documented and preferred key while retaining `RESEND_KEY` as a
temporary fallback in `config/services.php`. The webhook secret is separate:

- `RESEND_API_KEY`: outbound API/mail transport authentication;
- `RESEND_WEBHOOK_SECRET`: inbound webhook signature verification.

Tracked files will contain key names and empty examples only. The local
`.env` will not be read, printed, edited, or committed.

## TDD and Verification Evidence Required

After the Laravel wrapper is installed, webhook tests must fail against the
stock fail-open behavior before production webhook code is added. Committed
fixtures and local HMAC generation will exercise the real raw-body signature
path without network access.

Coverage must prove:

- a missing or blank secret fails closed;
- missing, invalid, stale, and malformed signatures are rejected;
- a valid signature reaches the built-in controller and event dispatcher;
- all seven operational events log;
- opened, clicked, received/inbound, contact, and domain events do not log;
- the log record contains exactly the four allowlisted keys;
- marker values in recipient, sender, subject, body, tags, failure details,
  raw payload, and signature never enter the log context;
- the dedicated channel has the intended path, driver, level, and retention;
- the endpoint is registered once, outside the `web` middleware group, with
  the package controller;
- the existing Resend mail transport still resolves; and
- no test performs live HTTP, mail, or database work.

Every HTTP-touching test will call `Http::preventStrayRequests()`. The transport
compatibility canary resolves the real wrapper transport but does not construct
or send a message; existing mail-delivery tests continue to use `Mail::fake()`.

## Tool Research and Limitations

Laravel Boost version-aware documentation confirmed Laravel 13 package
discovery, provider registration, route middleware, event subscriber
registration, daily log retention, and event testing patterns.

Official primary sources inspected:

- <https://resend.com/docs/send-with-laravel>
- <https://resend.com/docs/webhooks/introduction>
- <https://github.com/resend/resend-laravel/releases/tag/v1.4.0>
- stable 1.4.0 provider, route, controller, middleware, event, config, and
  Composer source files from the official GitHub repository;
- <https://github.com/unjs/fontaine>
- installed `laravel-vite-plugin` 3.1.3 package metadata and source.

The PhpStorm review skill was loaded, but tool discovery currently exposes no
PhpStorm inspection MCP method in this task, including no `get_inspections`.
The tool will be retried after dependency/Boost discovery. If it remains
unavailable, the handoff must record the limitation and the compensating
Pint, FilaCheck, focused tests, Composer validation/audits, npm audit/build,
and full-suite evidence. This tooling issue does not expand application scope.

## Drift Stop Conditions

Return to Stage 1 before continuing if the refresh introduces an unrelated
major version, migration, new runtime service, additional direct integration
package, production requirement, raw/PII logging need, persistence need,
security-boundary change, more than five mini-tasks, or an effort forecast
above the approved 7–11 hours.
