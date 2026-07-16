# AUTHZ1 Foundation Research

Date: 2026-07-16
Status: foundation implementation evidence complete; legacy authority retained
Contract: `prompts/pre-13-prompts/authz1-foundation-codex-prompt.md` v3

## Decision summary

AUTHZ1 may add the approved package schema and an application-owned catalog,
role metadata, and compatibility-grant manifest without changing authority.
`users.role`, `UserRole`, rank helpers, current Gates/macros, panel admission,
Horizon, maintenance bypass, User Resource behavior, policies, callers, and
assignments remain authoritative. No `HasRoles`, package assignment backfill,
catalog synchronization, policy generation, management UI, direct grants, or
AUTHZ1-C through AUTHZ1-I work belongs in this foundation.

The provisional prompt was intentionally committed at
`6339e858cced21d4ba13de573802c384d0676383`. The kickoff's older
`fd39adc...`/uncommitted-prompt assumption was stale; the operator explicitly
resolved it in favor of the committed v1 baseline. The original audit advanced
that contract to v2. A real persistent-Livewire regression then exposed a
pre-existing maintenance enforcement defect. The operator authorized only
that correction plus a separate audit contract, and the resulting v3
foundation prompt, v1 future audit prompt, and prompt index were committed
before resumed implementation as `d084d9b`.

## Evidence categories

### Installed-version guidance

Laravel Boost `application-info` reported PHP 8.4, Laravel 13.19.0, Filament
5.6.7, Horizon 5.47.2, Livewire 4.3.3, Boost 2.4.11, Pest 4.7.4, Pint 1.29.3,
and Tailwind CSS 4.3.2. The exact installed authorization packages are Shield
4.2.0, Permission 7.3.0, and transitive-only Plugin Essentials 1.2.1.

Boost `search-docs` returned installed-version guidance for:

- Laravel package discovery and tagged publishing;
- Laravel 13 Gate interception;
- Horizon's `viewHorizon` dashboard gate;
- Filament panel/resource/page authorization and direct-route behavior;
- Pest datasets and Laravel SQLite testing.

After the operator's v3 exception, Boost `search-docs` also returned Livewire
4 guidance that persistent middleware re-applies authorization middleware to
updates when authorization changes after initial page load, plus Laravel 13
response-exception guidance. This supports enforcing maintenance at the
persistent-middleware boundary rather than changing a component or update
route.

Boost `database_connections` reported default `mysql` and an available
`sqlite` connection. `database-schema` was called explicitly with
`database=sqlite` and `summary=true`; it returned an empty SQLite schema. No
default connection or development MySQL schema/query tool was used.

### Official primary documentation and tagged source

- [Shield documentation](https://filamentphp.com/plugins/bezhansalleh-shield)
  confirms 4.x supports Filament 4/5, documents `filament-shield-config`, the
  Role Resource/plugin, `HasRoles` setup expectation, and destructive-command
  prohibition.
- [Shield 4.2.0 composer source](https://github.com/bezhanSalleh/filament-shield/blob/4.2.0/composer.json)
  accepts Illuminate 13, Filament 5, Permission 7, and Plugin Essentials 1.x.
- [Shield 4.2.0 config source](https://github.com/bezhanSalleh/filament-shield/blob/4.2.0/config/filament-shield.php)
  is the pre-install configuration reference.
- [Permission 7.3.0 composer source](https://github.com/spatie/laravel-permission/blob/7.3.0/composer.json),
  [configuration](https://github.com/spatie/laravel-permission/blob/7.3.0/config/permission.php),
  and [migration stub](https://github.com/spatie/laravel-permission/blob/7.3.0/database/migrations/create_permission_tables.php.stub)
  are the exact package references.
- [Spatie v7 prerequisites](https://spatie.be/docs/laravel-permission/v7/prerequisites)
  make the existing `users.role`/`HasRoles` collision explicit.
- [Spatie cache guidance](https://spatie.be/docs/laravel-permission/v7/advanced-usage/cache)
  requires an app-specific shared-cache boundary.
- [Laravel 13 package publishing](https://laravel.com/docs/13.x/packages#publishing-file-groups),
  [authorization](https://laravel.com/docs/13.x/authorization#intercepting-gate-checks),
  and [Horizon dashboard authorization](https://laravel.com/docs/13.x/horizon#dashboard-authorization)
  support the package/Gate/Horizon conclusions.

Installed source was subsequently inspected. Shield 4.2.0 always registers its
Role Resource when its plugin is registered, and the documented setup assumes
`HasRoles`; therefore the safe supported foundation is installed/configured
but unregistered in both panels. Shield's supported destructive-command helper
does not cover every mutating command, and `shield:seeder` is Prohibitable but
does not check its prohibited state, so the application-owned production guard
covers those gaps without changing development behavior. Permission's Gate
hook remains disabled. Tagged-source guidance does not stand in for these
installed-source findings.

### FilamentExamples protocol

Only `search_examples` is exposed; there is no separate read/fetch/detail
tool. The search results include example names, exact paths/classes, and source
snippets, so access is recorded as search plus returned source snippets, not
full detail access.

Initial short batches covered panel-scoped plugins, disabled Resource
navigation, Resource route authorization, and custom Page authorization. The
refined batches used returned multi-panel and authorization patterns:

| Example/source | Pattern copied | Pattern avoided / PodText adaptation |
|---|---|---|
| Multi-Panel Hotel Booking and Investor/Broker panel providers | Register/discover panel-specific components in the intended provider only | Do not infer authorization merely from discovery topology |
| Quiz `ViewResult::mount()` | Direct routes still need explicit authorization at the Page/Resource boundary | No policy/action cutover in this slice |
| `shouldRegisterNavigation = false` Page examples | Navigation visibility is presentation only | Never treat hidden navigation as route denial |
| Spatie Roles with Filament Policies | Policies are the eventual Filament enforcement location | Avoid `HasRoles`, `hasRole()`, assignment observers, and direct Spatie checks while legacy authority is active |

No form schema is created or changed. The forms UX skill therefore acts as a
negative-scope check: Shield's role form/management surface must remain absent,
and bilingual grouped role-management UX remains later AUTHZ1-D/H work.

### Executed tests and controller inference

The pre-install baseline is green:

- `composer validate --strict`: passed;
- `composer audit --format=plain`: no security advisories; Composer could not
  write its user cache under the sandbox and proceeded without cache;
- the sequential Roles/Gates, panel/Horizon, and maintenance baseline passed
  26 tests / 243 assertions in 24.722 seconds on forced SQLite `:memory:`;
- the exact Composer dry-run resolved three installs, zero updates, and zero
  removals: Shield 4.2.0, Permission 7.3.0, and transitive Plugin Essentials
  1.2.1. It reported no security advisories and persisted no manifest diff.

A follow-up `composer show` check was invoked with two positional package names,
which Composer rejects as an invalid version constraint; it exited nonzero and
made no change. This command-shape failure is retained for the handoff.

Current-code inspection and delegated findings remain inference, not executed
new-feature verification: current broad Admin/Super access derives
from `User::canAccessPanel()`, Horizon and maintenance reuse that decision, the
User Resource uses `can*()` plus the `super-admin` Gate, and current importers
have no independent per-row policy boundary.

### Maintenance/Livewire causal evidence

Executed regression evidence is distinct from source inspection. A real public
page was loaded with maintenance disabled, the rendered
`public.content-item-browser` child snapshot was extracted, maintenance was
enabled, and that snapshot was posted with `X-Livewire` to the installed update
URI. Super/Admin returned 200 as declared; Moderator/Transcriber/User also
returned 200 instead of the initial-request 503. The focused run therefore
failed exactly three denied-role cases and proved the issue is not a synthetic
Livewire mount artifact.

Installed Livewire source reconstructs the original route from the snapshot
and runs registered persistent middleware. Its middleware utility returns an
ordinary 503 response from `RenderMaintenanceMode`, but the persistent wrapper
does not forward that return value; the utility terminates only redirects.
Laravel's `HttpResponseException` carries an exact response through exception
rendering. Controller inference, pending the required independent reviews, is
that throwing this exception only after the middleware has built a denied
maintenance response and only for an actual Livewire update preserves the
body, status, `Retry-After`, role decision, and every initial HTTP path.

The correction must not claim unobserved browser behavior. Stale-snapshot and
ordinary single-component server behavior are directly testable here;
bundled/multi-component, lazy/polling, browser error UX, log noise, and wider
client-state effects require source classification now and the dedicated v1
audit after foundation completion.

## Package and schema findings

The exact approved direct requirements are:

```text
bezhansalleh/filament-shield:4.2.0
spatie/laravel-permission:7.3.0
```

`bezhansalleh/filament-plugin-essentials:1.2.1` resolved only transitively. The
pre-install dry-run, strict validation, and audit are green. A future install
solve with any other version, update, removal, or package remains a hard stop.

Permission's published migration is expected to create `permissions`, `roles`,
`model_has_permissions`, `model_has_roles`, and `role_has_permissions`.
Permission/role names are unique per `guard_name`; package pivots have compound
keys and indexes; package-table foreign keys cascade; polymorphic model pivots
intentionally do not reference `users`. Teams stay disabled. Shield owns no
separate authorization tables.

The checked-in migration will be proven only on a dedicated SQLite `:memory:`
connection by invoking that published package migration object `up()`, then
`down()`, then `up()` again. No broad rollback command and no development or
production database is permitted. MySQL compatibility is source/DDL review in
this slice, not a live probe.

Permission's cache key must be PodText-specific. Shared-server isolation should
use the existing app cache-prefix convention plus a package key such as
`podtext.permission.cache`; no environment or production state changes are
needed here.

## Shield boundary and prohibited commands

Shield is an adapter/consumer, never the catalog or authorization authority.
Installed source must decide whether a plugin can be present without exposing
the Role Resource route or requiring `HasRoles`. Merely disabling navigation is
insufficient. If source shows registration always registers management routes,
the safe foundation outcome is Shield installed/configured but unregistered on
every panel, with tests proving both Admin/Public management routes are absent.
Do not create an unused constants-only adapter class as a substitute for real
integration. Exact source/config review must also neutralize any package-owned
Super Gate interception, panel-user behavior, RolePolicy registration,
generation, or management default. This is not a request to force a plugin
into the Public panel.

| Command | Foundation/production disposition |
|---|---|
| `shield:setup` | Prohibited; `--fresh`/`--force` can replace schema/data |
| `shield:install` | Prohibited; interactive setup/generation is outside scope |
| `shield:generate` | Prohibited; owned literal catalog is authority |
| `shield:super-admin` | Prohibited; assignments remain legacy-only |
| `shield:seeder` | Prohibited; can export users/password hashes and mutate grants |
| `shield:publish` | Prohibited in this slice; Role Resource must remain absent |
| `shield:translation` | Not required; owned HE/EN catalog metadata is used |

The installed package's supported facade-level production prohibition will be
used if it does not mutate state during application boot.

## Owned catalog and manifest decisions

Prompt v2 independently freezes 135 ordered keys across 12 groups, the complete
entry field order/metadata rules, version `AUTHZ1-2026-07-16`, and expected
SHA-256
`fb46f5ef0228c2017e049b13a6f18eb72183a85b89249385828bf5295b9193c7`.
An independent parsing check confirmed 135/135 unique keys and the same hash.

Role metadata is exact: Super Admin and Admin are protected/reserved and not
delegable; Moderator, Transcriber, and User are protected, non-reserved, and
delegable. The declarative grant snapshot is Super 135, Admin 89 allowed/46
denied, and exact empty arrays for the three dormant roles. Nothing is written
to package tables and no manifest is consulted by current authorization.

Validation must reject malformed/case/wildcard/brace/underscore/empty-segment
keys, normalized collisions, duplicate keys/order, wrong guards, unknown
grants, and incomplete/unknown/duplicate roles. HE and EN must have the exact
same group/label/description key set, locale-specific presence, trimmed
nonempty values, no lookup-key return, no fallback borrowing, and no duplicate
PHP array keys.

## Current-surface disposition

| Surface | Literal abilities | Legacy authority now | Later enforcement / invariant |
|---|---|---|---|
| Admin panel and dashboard | `panel.admin.access`, `dashboard.admin.view` | `UserRole` rank and `canAccessPanel()` | AUTHZ1-D panel/Page checks; no early lower-role admission |
| Ordinary Resources and relation managers | Matching `content.*`, `homepage.sections.*`, `media.library.*` | Admin panel perimeter; Curator policy only where present | Model policies plus action authorization; validation/reference constraints remain separate |
| ContentItem/Transcription workspaces | content item/transcription abilities plus `history-view`, `multiple-manage`, `featured-manage` | panel rank, `multi-transcription` Gate, feature mode | Policy/action boundary; multi mode remains non-bypassable |
| Native imports/exports | each Resource `.import`/`.export` | launching Admin table/panel | AUTHZ1-D per-row actor-aware create/update authorization and queued reauthorization |
| Public Form submissions | submission view/status/PII keys | Admin panel/current Resource actions | AUTHZ1-D policy/action split; routine delete remains absent |
| Settings subjects | subject/security/trusted-HTML keys | Page access/save checks and protected writers | Page plus mutation service; field/cluster protection remains separate |
| Card Template/Public Form settings | current settings keys | existing Page/Resource/custom writers | AUTHZ1-D policies/actions; protected-template invariant stays separate |
| Settings package/backup/snapshot/locks | settings lifecycle keys | current pages/services/caller topology | reusable service authorization for UI/job/command/import/restore callers |
| Workbench/Spotify/Admin Tools | workbench/tool keys | panel perimeter and current domain guards | route/action plus fresh-actor service authorization; credential/provider validation remains separate |
| Users Resource | users/security keys | `super-admin` Gate, Resource `can*()`, EditUser safeguards | AUTHZ1-D UserPolicy plus one transactional mutation boundary; self/final-Super invariant remains separate |
| Horizon | `system.horizon.view` | `canAccessPanel()` | later independent Gate; current five-role result must not change now |
| Maintenance bypass | `public.maintenance.bypass` | `canAccessPanel()` | later independent Gate; initial and persistent Livewire paths must agree |
| Curator media delete | `media.library.delete` | reference-aware `delete()`, unconditional `deleteAny()` | AUTHZ1-D correction must authorize each bulk record or equivalent; reference integrity is non-bypassable |
| Public panel | no Admin/Shield management ability | guest Public panel topology/public scopes | Shield plugin/pages/resources/middleware/navigation remain absent |

## Required later-slice dispositions

1. Importer per-row create/update authorization is deferred to AUTHZ1-D, when
   meaningful policies and an actor-aware queued boundary exist.
2. User Resource record action/save authorization is deferred to AUTHZ1-D and
   must move from Resource `can*()` assumptions to a UserPolicy plus the single
   transactional role-mutation boundary.
3. Curator's individual-versus-bulk delete discrepancy is an AUTHZ1-D security
   correction, not desired compatibility behavior. Bulk delete must authorize
   individual records or use an equivalent reference-aware boundary.

## Mandatory skill application

- `filament-security-audit`: applied search-anchored A1/A2/A3/A4/D4 checks.
  A1 found Curator's reference-aware single delete versus unconditional bulk
  decision; A2 is a future importer boundary; A3 found User Resource `can*()`
  migration sensitivity; A4 found no inline editable columns; D4 confirmed
  panel admission is rank-restricted rather than unconditional.
- `laravel-best-practices`: keeps authorization server-side, legacy Gates and
  invariants unchanged, migrations isolated/reversible, configuration out of
  environment mutation, and tests on SQLite `:memory:` rather than dev MySQL.
- `spatie-laravel-php`: the planned PHP uses explicit types, focused final
  classes, conventional namespaces, and clear array shapes. Its generic advice
  against migration `down()` methods yields to the higher-priority repository
  reversibility contract.
- `pest-testing`: coverage uses named shared datasets, independent fixtures,
  real direct-URL/Livewire behavior, precise fail-closed exceptions, and zero
  state-change assertions rather than class-existence checks.
- `filament-forms-ux-audit`: no form is created or changed; its material result
  is to keep Shield role/direct-grant management forms absent and leave grouped
  bilingual/RTL role UX to AUTHZ1-D/H.
- `configuring-horizon`: current `viewHorizon` derives from panel admission;
  this slice freezes its exact five-role audience and does not change Horizon
  supervisors, queues, dashboard topology, snapshots, or worker processes.

`livewire-development` was activated by the operator-authorized v3 exception.
Its persistent-middleware and testing guidance is applied only to the real
update-endpoint maintenance boundary; no component state architecture or
frontend behavior is being redesigned. Performance and Tailwind skills remain
untriggered because no performance claim or UI styling change entered scope.

## Delegated evidence and worker register

All workers were evidence-only and prohibited from edits, installs, database
access, mutations, staging, commits, production, and later AUTHZ1 scope.

| ID | Type | Status | Scope / material result | Stop decision |
|---|---|---|---|---|
| `019f68fe-3446-7d30-9e96-765136abcc9a` | RSCH | complete | exact package/schema/reversibility; versions compatible, `HasRoles` conflict confirmed | evidence sufficient |
| `019f68fe-3947-7310-9495-7b3b46cc056f` | RSCH | complete | 135-key catalog/role/adapter audit; missing golden hash/snapshot identified | evidence sufficient |
| `019f68fe-362e-75f2-8d8a-daf22bb739b4` | RSCH | complete | current surfaces/no-regression; Curator and future importer/User Resource gaps | evidence sufficient |
| `AUTHZ1-CTRL-01-AUDT-01` | AUDT | AMBER complete | security/Laravel/Horizon review; five-role and later-disposition gaps confirmed | incorporated into v2 |
| `AUTHZ1-CTRL-01-TEST-01` | TEST | AMBER complete | independent 135 count/key hash, Admin 89/46, invalid/translation/schema datasets | incorporated into v2 |
| `AUTHZ1-CTRL-01-CTX-01` | CTX | AMBER complete | stale baseline/state contradiction resolved; prompt omissions compressed | incorporated into v2 |
| `AUTHZ1-CTRL-01-PLAN-01` | PLAN | AMBER complete | file contract aligned; envelope/entry wording and Shield boundary corrections identified | corrected before install |
| `AUTHZ1-CTRL-01-MON-01` | MON | AMBER complete | concurrency/scope valid; required PLAN/CTX checkpoint enforced | checkpoint complete |
| `AUTHZ1-CTRL-01-CTX-02` | CTX | AMBER complete | baseline/dry-run evidence compressed; exact install/source gates retained | checkpoint complete |
| `AUTHZ1-CTRL-01-AUDT-02` | AUDT | RED complete | confirmed the persistent middleware 503 is discarded and the observed denied-role 200 is real behavior | operator approved narrow v3 exception |
| `AUTHZ1-CTRL-01-AUDT-03` | AUDT | GREEN complete | one-file response-exception boundary preserves response/role checks; header spoofing cannot grant access | apply after test review |
| `AUTHZ1-CTRL-01-TEST-02` | TEST | AMBER complete | required byte-exact body/header, disabled-mode, absent-effects, and form-side-effect assertions; no design contradiction | incorporate before middleware test run |
| `AUTHZ1-CTRL-01-MON-02` | MON | GREEN complete | v3 scope, concurrency, retained failures, and remaining sequence were compliant | continue foundation only |
| `AUTHZ1-CTRL-01-CTX-03` | CTX | AMBER complete | compressed causal/test/inference/limitation evidence without raw data | defer unobserved client planes to audit v1 |

The PLAN review found and the controller resolved one prompt wording conflict:
version/hash are catalog-envelope fields, while each Ability entry has exactly
the ten canonical fields. Its remaining Shield-default questions are the
mandatory installed-source checkpoint, not a present disagreement. Report 12
and exact primary source control; worker claims are corroborating evidence. The
full-entry hash was not provided by a worker and was independently defined,
computed, and rechecked by the controller before installation.

## Current implementation gates

The original pre-install gate passed before the exact approved Composer solve.
The v3 prompt-only commit is complete. `AUDT-03` and `TEST-02` found no design
contradiction. The correction remains limited to the single app-owned
middleware plus focused tests, with byte-exact body/header, disabled-mode,
absent-effects, and denied form-side-effect assertions. The five-role legacy-
surface matrix is complete; only restart-safe records and the canonical final
gate/commit sequence remain.

## Implemented foundation evidence

The exact Composer mutation installed only direct Shield 4.2.0 and Permission
7.3.0 plus transitive Plugin Essentials 1.2.1, with zero unrelated updates or
removals. Current strict validation is green with intentional exact-pin
warnings; Composer audit reports no advisories. The published Shield config,
Permission config, and one Permission migration were reviewed. The package-
only SQLite test invokes that migration object `up()`, `down()`, and `up()` and
asserts tables, columns, indexes, and foreign keys without a broad rollback.

Shield is deliberately not registered as a panel plugin because installed
4.2.0 source always registers its role-management Resource and documented setup
assumes `HasRoles`. Both panels exclude the plugin/Resource; package Super Gate,
policy generation, Permission Gate hook, teams, events, wildcards, management,
and discovery are disabled. Production package mutation commands are replaced
by app-owned refusal commands. No package assignment, sync, seed, generated
policy, or direct-grant writer exists.

The application-owned catalog contains the exact 135 ordered literals and the
canonical SHA-256
`fb46f5ef0228c2017e049b13a6f18eb72183a85b89249385828bf5295b9193c7`.
Independent fixtures freeze full entries, invalid cases, exact five-role
metadata, Super 135, Admin 89 allow/46 deny, and three empty dormant grants.
HE/EN group, label, and description metadata passes exact parity, locale-
specific existence, nonempty/no-key-return/no-fallback, and duplicate-key
checks.

Targeted final-state evidence before the canonical gate:

- catalog and package/role batches: 41 tests / 2,782 assertions green;
- shared five-role panel/Gate/Author/Admin Tools/User Resource matrix, with and
  without package definitions and empty assignment pivots: 50 / 372 green;
- expanded maintenance matrix: 44 / 444 green;
- initial maintenance correction run: 29 tests, 25 green and four assertion
  failures because unsupported 2/3-hour values normalized to 24 hours;
- corrected supported-six-hour maintenance run: 29 / 266 green;
- first legacy matrix run: 20 green and 30 extractor errors because Filament 5
  snapshots use PHP class names; exact installed names were then used, with no
  production behavior change.

## Maintenance effects classification

| Plane | Classification | Evidence boundary |
|---|---|---|
| Initial HTTP and stale single-component update | directly tested | exact five roles, enabled/disabled, byte-equal body, 503, `Retry-After`, absent effects |
| Public form component side effects | directly tested | denied stale `PublicFormModal` update creates no submission and queues no mail |
| Persistent route reconstruction and exception response | installed-source proven | middleware runs before hydration/update/call; embedded response is rendered and not reported |
| Ordinary same-route bundled/multi-component updates | source-proven with limitation | first denied route aborts the request; mixed-route ordering was not observed |
| Lazy/polling/streaming | repository/source inference | no public lazy/polling/`wire:stream` use found; dedicated audit retains the edge plane |
| CSRF/session/auth middleware | source-proven, partly tested | actual update URI/web stack used; feature tests do not prove browser CSRF transport |
| Admin Livewire and Horizon | tested/source-proven | real Admin page updates retain five-role perimeter; Horizon matrix remains separate and green |
| Status/body/header/log noise | tested/source-proven | exact response parity; Laravel internally excludes `HttpResponseException` from reporting |
| Browser error UX, focus, accessibility, unsaved client state | deferred | no browser observation was made; audit v1 owns this plane |
