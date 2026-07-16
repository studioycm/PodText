# Maintenance Livewire Enforcement Effects Audit

Audit ID: `MAINT-LW-AUDIT-01`

Prompt: `maintenance-livewire-enforcement-audit-codex-prompt.md`

Prompt version: `v1 — 2026-07-16`

Audited HEAD: `6ff14416d764d94cb0ecb76d7cf8b00b261d6ca1`

AUTHZ1 implementation: `97f88617a26b0494a03efbd0238f50f11f2978d7`

Audit date: 2026-07-16

## Executive conclusion

The app-owned `RenderMaintenanceMode` correction passes its server-side
enforcement objective for valid, checksum-verified persistent Livewire update
requests. When maintenance is enabled, a denied public snapshot reaches the
persistent middleware before component construction or hydration and receives
the same maintenance response as an ordinary public request: HTTP `503`, the
exact configured `Retry-After` value in seconds, and the configured styled or
raw maintenance body. The request produces no Livewire component response,
effects, render, called method, public-form row, or verification mail from the
denied component.

The bypass remains exactly the legacy five-role contract:

- `super-admin` and `admin` bypass public maintenance;
- `moderator`, `transcriber`, `user`, and guest are denied;
- Shield definitions, Spatie roles or permissions, assignment rows, the
  `AbilityCatalog`, `RoleCatalog`, and `CompatibilityGrantManifest` cannot
  change the result.

No privilege escalation, maintenance bypass, vendor patch, custom global
Livewire update route, component rewrite, or AUTHZ1-C-or-later authority
migration was found. Admin, Horizon, queues, imports/exports, schedules, the
health route, ordinary non-Livewire routes, and the intentionally separate
plain maintenance-form routes retain their prior boundaries.

The audit found one material non-security effect: with `APP_DEBUG=false`, a
stale public Filament page suppresses Livewire's HTML error dialog and presents
Filament's generic danger notification. The configured maintenance body,
maintenance-form guidance, and `Retry-After` duration are therefore not shown
to the stale-tab user. This is a medium UX/operational finding and needs a
separate operator-approved implementation decision if remediation is desired.

The conclusion applies to structurally valid Livewire requests with valid
CSRF and snapshot checksums. The outer Livewire protocol correctly rejects
missing headers/JSON, invalid CSRF tokens, malformed payloads, and invalid
checksums before the app maintenance middleware can decide the request; those
protocol failures are not expected to return the maintenance `503`.

## Scope and method

This was an audit-only run. It made no application, package, configuration,
migration, test, translation, or frontend change. It used two bounded read-only
workers: one for security/runtime reconstruction and one for tests, UX, and
broader effects. The controller independently reconciled their evidence,
inspected official installed-version guidance and exact installed source, and
ran only the three prompt-mandated test files in the repository's forced
SQLite `:memory:` environment.

No browser session was started. A safe production-like browser state could not
be created without credentials or development-data interaction, and server
tests were not treated as browser evidence.

## Evidence discipline

### 1. Official installed-version documentation

Laravel Boost reported PHP 8.4, Laravel `13.19.0`, Livewire `4.3.3`, Filament
`5.6.7`, Pest `4.7.4`, Shield `4.2.0`, and Spatie Permission `7.3.0`.

- [Livewire 4 security](https://livewire.laravel.com/docs/4.x/security)
  documents persistent middleware as the mechanism for reapplying original
  route authorization to later Livewire requests.
- [Livewire 4 installation](https://livewire.laravel.com/docs/4.x/installation)
  documents the app-key-derived Livewire endpoint form.
- [Filament 5 panel configuration](https://github.com/filamentphp/filament/blob/5.x/docs/05-panel-configuration.md)
  states that panel middleware runs on later Livewire requests only when the
  persistent flag is enabled.
- [Filament 5 security](https://github.com/filamentphp/filament/blob/5.x/docs/09-advanced/06-security.md)
  describes current-state authorization on later Filament Livewire requests.
- [Laravel 13 error handling](https://github.com/laravel/docs/blob/13.x/errors.md)
  distinguishes exception reporting from exception rendering.
- [Laravel 13 HTTP tests](https://github.com/laravel/docs/blob/13.x/http-tests.md)
  establishes the response status, header, content, and JSON assertion plane
  used by the feature tests.
- Livewire's installed-version documentation describes request bundling,
  isolated lazy/deferred loading by default, optional deferred bundling,
  polling, navigation, and two-stage uploads. These mechanisms were not
  assumed to be present in PodText without source discovery.

The documentation establishes intended package behavior. It does not by
itself prove PodText's middleware order, role contract, response body, or
mutation boundary.

### 2. Exact installed/tagged source

The installed source proves the runtime mechanics:

- `vendor/livewire/livewire/src/Mechanisms/HandleRequests/EndpointResolver.php`
  derives `/livewire-{hash}/update` from the app key.
- `HandleRequests.php` registers that route with `web` and
  `RequireLivewireHeaders`, requires a non-empty structured component array,
  and processes bundled components sequentially.
- `RequireLivewireHeaders.php` requires both `X-Livewire` and JSON.
- `PersistentMiddleware.php` writes the initial request path and method into
  snapshot memo, reconstructs a fake request for that path/method on update,
  matches its route, and retains only registered persistent middleware.
- `HandleComponents.php` verifies the checksum and triggers
  `snapshot-verified` before component construction, property hydration,
  updates, method calls, render, dehydrate, and effects.
- Livewire's `Utils::applyMiddleware()` only converts a redirect response into
  an abort; an ordinary non-redirect middleware response is otherwise returned
  to a persistent-middleware caller that does not use it. Throwing the embedded
  response is therefore the narrow mechanism that makes the maintenance
  termination effective.
- Filament `Panel/Concerns/HasMiddleware.php` turns
  `middleware(..., isPersistent: true)` into
  `Livewire::addPersistentMiddleware(...)`.
- Laravel's `HttpResponseException` carries the response. Laravel's exception
  handler returns that response and lists the exception among internal
  non-reportable exceptions.
- The Livewire client sends the update with JSON and `X-Livewire`; a non-2xx
  response enters the error path and never enters success parsing, component
  response merging, DOM morphing, or effect processing. The client does not
  inspect `Retry-After` and has no automatic HTTP-error retry.
- Filament's non-debug page bootstrapping registers default error
  notifications. Its request interceptor calls `preventDefault()` and shows a
  generic danger notification, suppressing Livewire's default HTML dialog.
- Without that Filament interceptor, Livewire writes the response body into a
  `#livewire-error` dialog iframe. The iframe has no `title`, the dialog has no
  explicit accessible label, and the implementation focuses then blurs the
  dialog.
- Laravel's routing pipeline catches exceptions inside a middleware slice and
  converts them to responses. The embedded `503` therefore returns through the
  outer `web` middleware tails; `StartSession` can store its current URL, save
  the session, and attach its cookie normally.

The AUTHZ1 commit contains no tracked vendor change, installed package metadata
matches `composer.lock`, and the application does not call `setUpdateRoute()`.
A fresh upstream archive was not downloaded for a byte-for-byte vendor-tree
comparison.

### 3. Committed application source and configuration

The narrow application path is:

- `app/Providers/Filament/PublicPanelProvider.php:68-81` attaches the public
  web/session/CSRF stack and registers only `RenderMaintenanceMode` through the
  app's persistent panel call.
- `app/Http/Middleware/RenderMaintenanceMode.php:24-45` reads current
  maintenance configuration, preserves the disabled and bypass paths, builds
  the maintenance response, and throws it only for a request carrying
  `X-Livewire`.
- `RenderMaintenanceMode.php:48-59` resolves the admin panel auth guard and
  delegates bypass solely to the existing `FilamentUser::canAccessPanel()`.
- `app/Models/User.php:37-46` implements that decision with the legacy enum
  threshold `UserRole::Admin`.
- `app/Enums/UserRole.php:10-45` preserves the order `super-admin`, `admin`,
  `moderator`, `transcriber`, `user`.
- `MaintenancePageRenderer.php:25-61` returns the styled/raw view with status
  `503` and string `Retry-After`; allowed configured hours are `1`, `6`, `12`,
  `24`, and `48`.
- `AdminPanelProvider.php` does not carry `RenderMaintenanceMode`.
- `HorizonServiceProvider.php:31-39` uses the same legacy
  `canAccessPanel(admin)` contract, independently of public maintenance.
- `routes/web.php:13-19` keeps the maintenance form submit and send-code
  controllers as ordinary throttled routes, not public Filament routes.
- `config/permission.php` disables the package Gate registration; `User` has no
  `HasRoles`; neither panel registers Shield or its role resource; the package
  catalogs are validation data, not runtime authorizers.
- `PublicFrontConfigReader.php:24-35` predates AUTHZ1 and deliberately falls
  back to registry defaults if settings loading fails; the default maintenance
  state is disabled. This is a conditional fail-open operational residual, not
  a change introduced by the Livewire correction. When settings caching is
  enabled, that fallback result can be cached until an explicit invalidation.

Repository discovery found no public `wire:poll`, `wire:navigate`, Livewire
lazy/deferred component, `wire:stream`, or file-upload component. Public form
configuration rejects file fields. Existing uploads are Admin-only and use a
distinct signed Livewire upload route.

### 4. Executed isolated tests and local observations

The controller executed, sequentially:

| Command | Result |
| --- | --- |
| `php artisan test --compact tests/Feature/PublicMaintenanceModeTest.php` | PASS — 44 tests, 444 assertions |
| `php artisan test --compact tests/Feature/PanelAuthHardeningTest.php` | PASS — 11 tests, 42 assertions |
| `php artisan test --compact tests/Feature/AuthzPackageFoundationTest.php` | PASS — 4 tests, 91 assertions |

`tests/Pest.php`, `tests/TestCase.php`, and `phpunit.xml` independently force
`APP_ENV=testing`, SQLite `:memory:`, array cache/session, and the synchronous
queue. `TestCase` aborts if the effective database is not that safe test
database. The maintenance test calls `Http::preventStrayRequests()` and
`Mail::fake()`.

Directly executed coverage proves:

- guest initial public maintenance status, header, body, Hebrew/RTL shell, and
  public-content suppression;
- the exact five-role initial and stale-update matrix under both no package
  definitions and additive package definitions;
- allowed JSON/effects for Super/Admin and maintenance-disabled roles;
- exact denied `503`, `Retry-After: 21600`, `text/html`, maintenance body,
  absence of component/effects JSON, unchanged relevant row timestamp, no
  public-form submission, and no queued mail;
- byte-identical styled response-body parity between an ordinary request and a
  denied update; outer middleware may still add normal session/cookie headers;
- raw override and styled initial responses, plain maintenance-form view,
  validation, OTP mail, successful submission, disabled routes, stale-CSRF
  maintenance response, and cache invalidation;
- Admin initial availability, Horizon HTTP/Gate role and guest boundaries, and
  complete package dormancy/assignment isolation.

No browser, assistive-technology, application-log, browser-console,
web-server access-log, or performance observation was made.

### 5. Auditor inference and residual uncertainty

- A same-route public bundle is denied when its first non-skipped public
  snapshot runs persistent middleware; no public component in that route key
  executes.
- A mixed-route crafted bundle is not transaction-atomic. An allowed component
  ordered before a denied public component can finish its server-side action
  before the later component throws. Current public pages produce same-route
  components; no reachable privilege escalation was demonstrated.
- Maintenance activation cannot recall an update that already passed its
  middleware decision. That narrow in-flight race is inherent without an
  explicit transaction barrier.
- With maintenance restored to disabled, the same previously denied valid
  snapshot should be accepted because the checksum remains valid and the
  current middleware decision now calls `$next`; this exact retry was not
  directly tested.
- The client source predicts retained local input, cleared loading state, no
  automatic action replay, and no successful DOM morph after `503`. Browser
  confirmation is still required.
- Session mutation beyond normal web middleware behavior was not directly
  asserted. Installed routing-pipeline source shows the rendered `503` returns
  through the session middleware tail rather than escaping the route stack.
- If no valid public-front config cache exists and settings loading fails, the
  reader returns defaults with maintenance disabled. This pre-existing
  fail-open path matters only if maintenance is relied on as incident
  containment while the settings repository is unavailable.

## Complete causal request trace

1. A public Filament route is matched under the `public` panel. Its configured
   stack includes cookies, session, authenticated-session validation, shared
   errors, CSRF, bindings, Filament serving middleware, and finally the
   app-owned maintenance middleware.
2. On an allowed initial render, Livewire's `dehydrate` hook copies the real
   request `path()` and HTTP method into each component snapshot's signed memo.
3. A later browser interaction posts a JSON component payload to the
   app-key-derived `/livewire-{hash}/update` route with `X-Livewire`. The route
   retains the `web` group and the header/JSON guard.
4. CSRF and request-shape checks run before component processing. Livewire then
   decodes one component payload and verifies its snapshot checksum.
5. `snapshot-verified` fires before component construction. Persistent
   middleware reads signed `memo.path` and `memo.method`, clones the current
   request while replacing its URI/method, rematches the original public route,
   gathers its middleware, and filters to the registered persistent set.
6. Because `RenderMaintenanceMode` is persistent only on the public panel, it
   is selected for the reconstructed public route and is absent for Admin,
   Horizon, maintenance-form, health, console, queue, and other routes.
7. The middleware reads the current cached/validated maintenance group. When
   disabled, it calls `$next`. When enabled, it resolves the current user from
   the admin panel guard and calls the existing `canAccessPanel(admin)` method.
8. Super/Admin call `$next`. Guest/Moderator/Transcriber/User calculate
   `max(1, retry_after_hours) * 3600`, build the same maintenance renderer
   response used by ordinary public HTTP, and throw
   `HttpResponseException($response)` because the update has `X-Livewire`.
   An ordinary returned `503` would not terminate this persistent-middleware
   caller; the embedded-response throw is the required narrow correction.
9. The throw occurs before new component construction, hydration, property
   updates, lifecycle hooks, action calls, render, dehydrate, and effects. It
   also aborts remaining sequential bundle processing at that point.
10. Laravel's routing pipeline catches the exception, renders the embedded
    response, and does not exception-log `HttpResponseException`. The response
    then returns through outer route middleware, including normal session and
    cookie tails. The network response is HTML `503` with exact `Retry-After`;
    it contains no successful Livewire JSON payload.
11. Livewire's browser client enters its non-2xx error branch and skips all
    success/effect work. On a production-like Filament page, Filament replaces
    the default HTML dialog with a generic danger notification. No client
    automatic retry honors `Retry-After`.

## Exact access matrix

Legend: **T** directly exercised by the three audit test commands; **E** proven
by another committed test inspected in this audit; **S** exact source proof;
**U** not directly observed in a browser.

### Public initial and persistent requests

| Actor | Maintenance disabled: initial / fresh update | Enabled: initial public HTTP | Enabled: stale pre-activation update | After restore: exact denied snapshot retry | Authority basis |
| --- | --- | --- | --- | --- | --- |
| `super-admin` | `200` live / `200` JSON effects (T) | `200` live bypass (T) | `200` JSON effects (T) | `200` expected (S; exact retry untested) | enum rank 500 ≥ Admin |
| `admin` | `200` live / `200` JSON effects (T) | `200` live bypass (T) | `200` JSON effects (T) | `200` expected (S; exact retry untested) | enum rank 400 ≥ Admin |
| `moderator` | `200` live / `200` JSON effects (T) | `503` maintenance (T) | `503` HTML, exact header/body, no effects/mutation (T) | `200` expected (S; exact retry untested) | enum rank 300 < Admin |
| `transcriber` | `200` live / `200` JSON effects (T) | `503` maintenance (T) | `503` HTML, exact header/body, no effects/mutation (T) | `200` expected (S; exact retry untested) | enum rank 200 < Admin |
| `user` | `200` live / `200` JSON effects (T) | `503` maintenance (T) | `503` HTML, exact header/body, no effects/mutation (T) | `200` expected (S; exact retry untested) | enum rank 100 < Admin |
| guest | `200` live (T) / `200` expected update (S) | `503` maintenance (T) | `503` expected before component work (S; no real guest stale test) | `200` expected (S; exact retry untested) | no guard user; bypass false |

For every tested denied stale role update, `Retry-After` is exactly `21600` for
the configured six hours, the body is byte-identical to the ordinary styled
maintenance response, content type contains `text/html`, no `components` or
`effects` JSON appears, relevant persisted state remains unchanged, and no mail
is queued.

### Adjacent surfaces while public maintenance is enabled

| Actor | Admin initial / real Livewire | Horizon HTTP / direct Gate | Maintenance form view | Direct send-code / submit | Raw/styled public response |
| --- | --- | --- | --- | --- | --- |
| `super-admin` | `200` / `200` effects (E, S) | `200` / allow (T) | bypasses public maintenance view (T, S) | role-neutral ordinary routes if called (S) | live public page, not maintenance body (T) |
| `admin` | `200` / `200` effects (E, S) | `200` / allow (T) | bypasses public maintenance view (T, S) | role-neutral ordinary routes if called (S) | live public page, not maintenance body (T) |
| `moderator` | `403` / `403` (E, S) | `403` / deny (T) | configured maintenance form in `503` page (T for guest; S for role) | `503` maintenance response; intentional row/mail only after valid flow (T guest, S role) | `503` configured raw or styled body (T styled, S raw update) |
| `transcriber` | `403` / `403` (E, S) | `403` / deny (T) | same as Moderator (S) | same as Moderator (S) | same as Moderator (T/S) |
| `user` | `403` / `403` (E, S) | `403` / deny (T) | same as Moderator (S) | same as Moderator (S) | same as Moderator (T/S) |
| guest | login redirect / no legitimate snapshot (E, S) | `403` / deny (T) | configured maintenance form in `503` page (T) | view/send-code/submit, validation, OTP, mutation, mail boundaries directly tested (T) | initial raw and styled `503` directly tested; raw stale update source-proven (T/S) |

When maintenance or the configured maintenance form is disabled, both plain
form POST routes return `404` (T). These ordinary POST routes intentionally can
persist a valid submission and queue OTP mail during maintenance; they are the
maintenance page's allowed communication channel, not a Livewire enforcement
bypass.

The access results were repeated with and without additive Shield/Permission
definition rows. No model assignment row was created. Package rows,
permissions, roles, assignments, and catalog manifests therefore cannot alter
any current matrix result.

## Broader-effects matrix

| Plane | Classification | Audit result |
| --- | --- | --- |
| Single stale snapshot after activation | Directly tested | Five roles covered; denied roles receive exact HTML `503` before effects or mutations. |
| Guest stale update | Source-proven; direct gap | No guard user means bypass false; a real guest stale request is not in the current test. |
| Deactivation/recovery | Source-proven/partly tested | Fresh snapshots in disabled mode work for five roles; an explicit enabled-to-disabled transition and exact same denied snapshot retry are untested. |
| Activation in-flight race | Inferred residual | A request that passed the check just before activation can finish afterward. |
| Same-route multi-component bundle | Installed-source proven | First processed public component runs middleware and aborts before component work; later payloads are not processed. Reactive-child skipping requires an earlier parent render and does not create a first-component public bypass. |
| Mixed-route crafted bundle | Installed-source residual | Earlier authorized non-maintained component work can commit before a later public component throws. No current public exploit path was shown. |
| Lazy/deferred | Not applicable currently; source-proven if added | No public usage. Such component updates use the update endpoint and current route middleware. |
| Polling | Not applicable currently; conditional source finding | No public `wire:poll`. If added, `503` does not itself pause polling and may repeat notification/access-log noise. |
| Public form modal | Directly tested | Denied stale send-code/submit produces no row or mail. |
| File uploads | Not applicable to public UI | Public forms reject files. Existing Admin signed upload flow is outside public maintenance. |
| Navigation | Not applicable currently | No public panel SPA or `wire:navigate`; ordinary HTTP maintenance is directly tested. |
| Streaming | Not applicable currently | No public `wire:stream` use found. |
| CSRF | Source-proven/partly tested | Update endpoint retains web CSRF; invalid Livewire CSRF is `419` before maintenance. Plain maintenance-form stale CSRF is converted to maintenance `503` without mutation. |
| Session/authentication | Source-proven/partly tested | Current session auth drives legacy bypass. Routing-pipeline rendering returns the `503` through normal session/cookie tails; detailed cookie deltas are untested. |
| Locale/RTL | Directly tested for styled shell | Hebrew `lang`, RTL `dir`, charset, viewport, and translated fallback pass. Raw override remains trusted operator HTML. |
| Cache invalidation | Directly tested | Saving the toggle invalidates config cache and the next request observes enabled maintenance. |
| Settings unavailable | Source-proven pre-existing residual | With no usable cached config, a settings-loading failure returns defaults where maintenance is disabled. |
| Public form verification mail | Directly tested | Denied Livewire queues nothing; allowed plain OTP flow queues only through the intended route. |
| Response semantics | Directly tested/source-proven | `503`, HTML, exact `Retry-After`, configured body, no Livewire success payload/effects. |
| Laravel exception logging | Installed-source proven | `HttpResponseException` is not exception-reported. |
| Browser/application/access logs | Unobserved | No log reads were used; access `503` records and console noise require manual observation. |
| Production client UX | Installed-source proven; browser unobserved | Generic Filament danger notification hides configured maintenance body and retry duration. |
| Debug client UX | Installed-source proven; browser unobserved | Livewire HTML error dialog iframe contains the maintenance response. |
| Unsaved local state/loading | Source inference; browser unobserved | No merge/morph or replay; local input should remain and loading should clear. |
| Focus/accessibility | Source concern; unobserved | Production notification needs AT verification; fallback dialog/iframe lacks explicit accessible naming. |
| Admin panel | Existing direct tests/source-proven | Separate route middleware; exact five-role perimeter unchanged. |
| Horizon | Directly tested/source-proven | Exact five-role plus guest HTTP/Gate matrix unchanged. |
| Queues/workers | Source-proven | Request middleware is not installed on queue execution. No queue process was touched. |
| Imports/exports/scheduled tasks | Source-proven | No shared global maintenance middleware or authority change. |
| APIs | Not applicable | No API route file is registered in `bootstrap/app.php`. |
| Ordinary non-Livewire routes | Directly tested/source-proven | Public initial maintenance and plain maintenance-form contracts preserved; unrelated routes do not carry the middleware. |
| Performance | Deferred | No timing, browser, query-count, memory, or load measurement was taken; no performance claim is made. |

## Findings ranked by severity

### Critical, high, or medium security findings

None supported by the audited evidence.

### M-UX-1 — Production stale tabs hide the maintenance contract

Severity: **Medium UX/operational; not a server authorization bypass**.

Evidence: Filament emits its default error-notification configuration only in
non-debug page output. On a `503`, its installed request interceptor calls
`preventDefault()` and sends the generic translated error notification. This
prevents Livewire's default maintenance-HTML dialog. The server response still
contains the exact maintenance body and `Retry-After`, but the user sees
neither.

Preconditions:

1. `APP_DEBUG=false`;
2. the user loaded a public Filament page while maintenance was disabled;
3. maintenance becomes enabled;
4. a denied user triggers a Livewire interaction.

Impact: confusing stale content, invisible maintenance messaging and form
guidance, no communicated retry duration, and weaker operational clarity.

### L-SEC-1 — Mixed-route bundles are not request-atomic

Severity: **Low security/integrity residual; no demonstrated current exploit**.

Evidence: installed Livewire source processes component payloads sequentially.
The middleware decision occurs per reconstructed route key. An allowed
component ordered first can complete before a later denied public component
throws.

Exploit preconditions:

1. the caller possesses valid signed/checksummed snapshots for more than one
   route under the same session;
2. the first snapshot belongs to a route not subject to public maintenance;
3. the caller remains authorized for a state-changing method on that component;
4. the denied public snapshot is ordered later in a crafted or unusual bundle.

Impact: the final response can be `503` even though an earlier, independently
authorized mutation committed. The denied public component still does not
execute, and current public pages did not expose such a mixed-route path.

### L-OPS-1 — Activation is not a transaction barrier

Severity: **Low operational/integrity residual**.

Preconditions: a valid public update passes the maintenance check immediately
before the operator activates maintenance and completes afterward.

Impact: that already-admitted request can finish. Requests evaluated after
activation are denied. Cache invalidation is directly tested, but it cannot
recall an in-flight request.

### L-OPS-2 — Settings unavailability falls back to maintenance disabled

Severity: **Low conditional security/operational residual; pre-existing**.

Evidence: `PublicFrontConfigReader` catches settings-loading failures and
returns `PublicFrontConfigRegistry::defaults()`, whose maintenance value is
disabled. This behavior existed before AUTHZ1 and is shared by ordinary and
Livewire public requests. With settings cache enabled, the fallback result can
remain cached until settings save or another explicit cache invalidation.

Exploit/precondition set: there is no usable valid cached config; settings
loading fails; the remainder of the public request stays serviceable; and an
operator is relying on maintenance as an incident-containment boundary. An
attacker would additionally need to cause or reliably time that failure to
weaponize it.

Impact: normally public content may be served despite the operator's intended
maintenance state. This is not a new bypass created by the correction and does
not expose content that is otherwise private.

### L-A11Y-1 — Error presentation needs browser and AT verification

Severity: **Low accessibility assurance risk**.

Evidence: the debug fallback dialog iframe lacks a title and explicit dialog
labelling and is focused then blurred. The non-debug Filament notification is
source-proven but was not rendered with keyboard or assistive technology.

Preconditions: a stale denied interaction and either debug fallback UI or an
assistive-technology dependency on the production notification.

Impact: uncertain announcement, focus placement, dismissal, and recovery.

### L-ASSURANCE-1 — Direct coverage gaps remain

Severity: **Low assurance debt; not evidence of bypass**.

Missing direct checks are: real guest stale update; retrying the exact denied
snapshot after deactivation; same-route and mixed-route bundles; raw-override
stale response parity; maintenance-enabled Admin Livewire and Horizon in the
same test; session-cookie/save behavior; production notification/debug dialog;
unsaved state, focus, screen reader, access logs, browser console, and measured
performance.

### Informational findings

- Laravel intentionally does not exception-report `HttpResponseException`;
  operators must use HTTP/access metrics if they need denied-update counts.
- The Livewire client ignores `Retry-After` and does not automatically retry a
  failed action.
- No current public polling exists. If introduced, ordinary `503` responses do
  not automatically suspend its interval.
- Additive package definitions and empty assignment tables cannot affect the
  maintenance decision. This was both directly tested and source-proven.

## Limitations and residual uncertainty

1. No safe local browser/AT observation was performed.
2. No application, browser-console, access-log, or production telemetry was
   read.
3. No timing or load measurements were taken.
4. The test suite did not directly execute the coverage gaps enumerated in
   L-ASSURANCE-1, and this audit was not authorized to add tests.
5. Mixed-route bundle and in-flight activation findings are installed-source
   analyses, not reproduced exploit demonstrations.
6. Raw maintenance override is intentionally trusted operator content; its
   accessibility, locale, script, and UX qualities are outside the correction.
7. Package documentation and source describe current installed versions only;
   future Livewire/Filament upgrades require re-audit.

## Numbered recommendations

1. Accept the server-side AUTHZ1 correction as enforcing the declared current
   role and response contract; do not couple it to Shield, Spatie assignments,
   or later ability catalogs.
2. Commission a separate operator-approved implementation prompt to decide how
   production stale tabs should surface the maintenance state, configured
   message, and retry duration without weakening the `503` termination.
3. In that separate prompt, add direct regression tests for guest stale
   updates, exact-snapshot deactivation recovery, same-route bundles, raw
   override parity, session/cookie semantics, and settings-unavailable policy.
4. Do not add a mixed-route preflight or transaction wrapper until a concrete
   reachable state-changing bundle is demonstrated; then decide explicitly
   whether request-level atomicity is required.
5. Add maintenance-transition coverage whenever public polling,
   lazy/deferred components, navigation, streaming, or uploads are introduced.
6. Perform the manual production-like browser and accessibility checks in the
   handoff before accepting any future UX remediation.
7. Monitor HTTP `503` access metrics rather than Laravel exception logs if
   operational counts are required.
8. Decide in a separate prompt whether settings-unavailable behavior should
   remain fail-open or gain an explicitly designed emergency fail-closed mode.
9. Keep AUTHZ1-C and later authority slices unstarted until their own accepted
   prompt; this audit creates no authority-migration permission.

## Final authority-isolation confirmation

Current maintenance outcomes are functions only of current maintenance
configuration, the current admin-guard user, and the legacy
`User::canAccessPanel(admin)` enum threshold. `User` does not use `HasRoles`,
Spatie's permission Gate integration is disabled, Shield is not registered on
either panel, package mutation commands are guarded, and catalog/manifest
classes are validation-only. Therefore no package permission definition,
package role, role-permission relation, model assignment row, ability catalog
entry, compatibility grant, or later AUTHZ1 slice can alter the audited result
in the committed state.
