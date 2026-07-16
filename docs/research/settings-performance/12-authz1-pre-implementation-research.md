# AUTHZ1 Pre-Implementation Research

Date: 2026-07-16

Starting commit: `86ce596 docs: record BQ decisions and audited defaults`

Status: research complete; implementation not started; no application code,
tests, migrations, package manifests, schema, production state, or
implementation prompt changed

## Contract and evidence boundary

This report consolidates the approved Groups 1–15, BQ1–BQ6, and audited
defaults. It does not reopen Groups 16–22. It defines the AUTHZ1 scope and
acceptance boundaries that must be accepted before ARCH1; it is not a
file-by-file implementation plan or an implementation prompt.

Evidence came from:

- the current checkout and its authorization tests;
- the three read-only AUTHZ1 research tasks for current surfaces, package
  compatibility, and migration/concurrency/acceptance;
- installed-version Laravel Boost guidance for Laravel 13.19.0, Filament
  5.6.7, Livewire 4.3.3, Horizon 5.47.2, and PHP 8.4;
- the repository-owned `filament-security-audit`, `laravel-best-practices`,
  and directly relevant role-management `filament-forms-ux-audit` guidance;
- current primary documentation and package source.

The exact read-only Composer solve was:

```text
composer require \
  bezhansalleh/filament-shield:4.2.0 \
  spatie/laravel-permission:7.3.0 \
  --dry-run --no-scripts --no-interaction --minimal-changes
```

It proposed three installs and no updates or removals:

```text
spatie/laravel-permission                 7.3.0
bezhansalleh/filament-plugin-essentials  1.2.1
bezhansalleh/filament-shield             4.2.0
```

`composer audit --format=plain` reported no known security advisories. No
Composer file was changed. Shield 4.2.0 permits Filament 4/5, Illuminate
11.28/12/13, and Permission 6/7; Permission 7.3.0 permits Illuminate 12/13 and
PHP 8.3+, so this pair resolves on the installed graph. Shield 4.2 added
Laravel 13 support. See the [Shield 4.2 constraints](https://github.com/bezhanSalleh/filament-shield/blob/4.2.0/composer.json),
[Permission 7.3 constraints](https://github.com/spatie/laravel-permission/blob/7.3.0/composer.json),
and [Shield releases](https://github.com/bezhanSalleh/filament-shield/releases).

## 1. verified current-state findings

### 1.1 Current authority and exact five-role access matrix

`App\Enums\UserRole` owns five fixed slugs and numeric rank inheritance.
`User::hasRoleAtLeast()` and `User::canAccessPanel()` make `admin` and
`super-admin` the only panel roles. `AppServiceProvider` defines the
rank-backed `super-admin` and mode-plus-rank `multi-transcription` Gates and
UI-hiding macros. `HorizonServiceProvider` currently derives `viewHorizon`
from panel access. No Spatie Permission or Shield package is installed.

| Current surface | super-admin | admin | moderator | transcriber | user |
|---|---:|---:|---:|---:|---:|
| Enter the `admin` panel | Yes | Yes | No | No | No |
| Dashboard, profile, Account/Filament Info widgets | Yes | Yes | No | No | No |
| Ordinary Resources and CRUD | Yes | Yes | No | No | No |
| Native content imports and exports | Yes | Yes | No | No | No |
| Ordinary settings pages and writes | Yes | Yes | No | No | No |
| Settings export/import/backups/restore/locks/snapshots | Yes | Yes | No | No | No |
| Importer connections, credentials, OAuth, tests, and probes | Yes | Yes | No | No | No |
| Admin tools, Spotify fetch, and Spotify direct import | Yes | Yes | No | No | No |
| Public Form submissions, payload/PII, and status actions | Yes | Yes | No | No | No |
| Curator media management | Yes | Yes | No | No | No |
| Users list/edit/single-role assignment | Yes | No | No | No | No |
| User creation or deletion | No | No | No | No | No |
| Change global transcription mode | Yes | No | No | No | No |
| Admin-level multiple-transcription actions in `multi` mode | Yes | Yes | No | No | No |
| Protected multiple-transcription settings/templates in `multi` mode | Yes | No | No | No | No |
| Horizon dashboard | Yes | Yes | No | No | No |
| Bypass public maintenance mode | Yes | Yes | No | No | No |
| Guest-facing public pages | Same public rules | Same public rules | Same public rules | Same public rules | Same public rules |
| Artisan mutation commands | Shell authority, not application role | Shell authority, not application role | Shell authority, not application role | Shell authority, not application role | Shell authority, not application role |

The legacy migration gave existing accounts `admin`, while the `UserFactory`
also defaults to `admin`. Tests that do not declare a role therefore exercise
Admin, not a neutral user.

### 1.2 Gates, policies, panel, Horizon, and maintenance

- `app/Enums/UserRole.php` owns the slugs, bilingual labels, colors, ranks,
  comparison, and option lists.
- `app/Models/User.php` casts `users.role`, implements rank comparison, and
  admits only Admin-or-higher to panel ID `admin`.
- `app/Providers/AppServiceProvider.php` registers only the application Gates
  `super-admin` and `multi-transcription`; its schema/action macros hide UI but
  do not authorize execution.
- `app/Providers/Filament/AdminPanelProvider.php` discovers every application
  Resource and Page, registers Curator and the built-in widgets, and does not
  enable Filament strict authorization.
- `app/Policies/CuratorMediaPolicy.php` is the sole conventional application
  policy. It allows panel users to view/create/update, blocks referenced media
  on individual deletion, but unconditionally allows `deleteAny`. Because
  Curator exposes bulk delete, the individual reference guard and bulk policy
  are inconsistent and must be resolved in AUTHZ1; this research does not fix
  it.
- `app/Providers/HorizonServiceProvider.php` makes both existing panel roles
  Horizon viewers. The target must preserve that initial audience through the
  separate `system.horizon.view` key, not through panel admission. Horizon's
  production dashboard is explicitly governed by `viewHorizon` in the
  [official Horizon authorization contract](https://laravel.com/docs/13.x/horizon#dashboard-authorization).
- `app/Http/Middleware/RenderMaintenanceMode.php` lets any current Admin-panel
  user bypass public maintenance. The target therefore needs the independent
  compatibility grant `public.maintenance.bypass` for Admin and Super Admin.

### 1.3 Resources and their actions

The following Resources have no owned model policy or role-specific Resource
restriction; panel admission currently grants Admin and Super Admin the whole
surface:

| Resource family | Current operations requiring an AUTHZ1 disposition |
|---|---|
| Authors | list/create/edit/delete; native import/export/bulk export/bulk delete |
| Categories | list/create/edit/delete; native import/export/bulk export/bulk delete |
| Content Groups | list/create/edit/delete; native import/export; child-item relation create/edit/open/delete |
| Content Items | list/classic/workspace create/edit/delete; native import/export; direct transcription actions; relation-manager actions |
| Content Tags | list/create/edit/delete/bulk delete |
| Homepage Sections | list/create/edit/delete/bulk delete |
| Transcriptions | list/create/edit/delete; native import/export; set-featured; history and multiple-row affordances |
| Settings Backups | list; custom export/import/lock/create/download/compare/report/restore/snapshot/delete actions; Resource create is disabled |
| Public Form Submissions | list/edit payload; mark reviewed/archive/reopen/bulk archive; Resource create is disabled |
| Curator Media | list/create/update/single delete/bulk delete/download and picker operations |

`UserResource` is the exception: `canViewAny`, `canView`, and `canEdit` call the
`super-admin` Gate, while create and delete are disabled. Its role select
offers every fixed role. `EditUser` checks self-demotion and final-Super-Admin
demotion before saving, but its count-before-save check is not transactional
and is race-prone. It is a Page/Resource override, not a reusable `UserPolicy`.

Public Form payload/PII view and status mutation currently share ordinary
Admin access. AUTHZ1 must separate `forms.submissions.view`,
`forms.submissions.status-update`, `forms.submissions.pii-view`, and
`forms.submissions.pii-export`; routine deletion remains absent under BQ4.

### 1.4 Pages, widgets, custom actions, and settings writers

The focused About, Contributor, Display, Episode Page, Homepage,
Menu/Header, Podcast, Maintenance, and Public Forms settings pages inherit
Admin-or-higher access and recheck it at save. The legacy settings URL checks
the same rank before redirecting. Card Templates checks Admin access at page,
editor, and focused-writer boundaries; protected multiple-transcription parts
add Super Admin plus `multi` mode. `AdminUxSettings` hides the transcription
mode from Admin and restores the stored value on forged writes.

The following Pages/actions currently rely only on panel admission unless a
stronger rule is named above:

- Dashboard and built-in Account/Filament Info widgets; there are no
  application-owned admin Widget classes.
- Admin Tools and Spotify Links Fetcher, including direct import.
- Importer Settings create/test/OAuth/edit/delete connection actions.
- Public settings import and import-lock pages/components.
- settings package export, backup create/download/compare/restore/delete,
  snapshot file/retry/zip, and inline lock actions.
- native import/export actions and queued importer execution.
- content image download/overwrite/remove actions and effective-transcription
  edit/open actions.
- Public Form submission status row/bulk actions.

`SettingsBackupManager::import()`/`restore()`, `SettingsImportLocks::save()`,
snapshot managers, Spotify direct-import services, and importer connection
services treat a User as attribution or rely on their caller topology; they do
not provide a reusable authorization boundary themselves. The two custom
settings lifecycle Livewire components also rely on being mounted inside the
authenticated panel. Every future caller—UI, job, command, import, restore, or
migration—must reach the owned authorization/mutation boundary rather than
calling these service writes as if attribution implied permission.

The role-management form must remain usable in Hebrew RTL and English. The
five seeded role choices are bounded and may use a native/preloaded control;
the potentially growing catalog needs grouped bilingual labels, concise
descriptions for sensitive abilities, search/filtering when measured as
necessary, and server-side validation that ignores no hidden or stale state.

### 1.5 Commands, imports/restores, Workbench, and alternate entry points

Current console commands run as trusted shell operations, not as an
application actor:

- `users:assign-role` directly replaces one enum role and can bypass the UI's
  last-Super-Admin/self-demotion checks.
- `settings:normalize-public-content` can apply settings writes.
- `importer:probe-formats` reads remote documents and writes local findings.
- the Curator registration and content-tag repair commands mutate records.

The native Author, Category, Content Group, Content Item, and Transcription
importers are launched by Admin/Super tables and have no independent model
policy matrix. Filament imports do not supply a per-record policy boundary by
default; queued execution therefore needs explicit owned authorization and a
fresh actor. Settings restore/import and Spotify direct import are separate
bulk writers with the same requirement.

Workbench connection management exposes encrypted-at-rest Google service
account/refresh-token and Spotify client credentials to both current panel
roles. Its OAuth routes use Filament authentication, and the controller writes
returned tokens without a finer ability. AUTHZ1 must separately disposition
connection view, credential management, connection test, OAuth, remote probe,
Spotify fetch, and direct import.

### 1.6 Verified package behavior and required adaptation

- Shield 4.2 can generate policies/permissions for Resources and expose Page,
  Widget, and custom permission groups, but Pages/Widgets need the package's
  enforcement traits. Shield does not assign roles to users. Its Role Resource
  is an adapter for approved bundles; it is not the catalog authority. See the
  [Shield documentation](https://filamentphp.com/plugins/bezhansalleh-shield).
- Shield commands can regenerate policies and are described as potentially
  destructive. Production setup/generation/publish/seed/super-admin commands
  must be prohibited; unrestricted `shield:generate --all` and fresh setup are
  not production synchronization mechanisms.
- Filament standard Resource CRUD can use policies, but custom actions, Pages,
  Livewire methods, endpoints, imports, and business services still need
  explicit authorization. Filament rechecks Resource/Page/Widget authorization
  on Livewire requests, while panel admission runs through authentication
  middleware. See [Filament security](https://filamentphp.com/docs/5.x/advanced/security).
- Spatie supports additive roles and direct permissions. Application checks
  must use Laravel `Gate`, policies, `can()`, `authorize()`, and `@can` so the
  Super Admin interception is honored. Direct `hasPermissionTo()` calls are
  not a competing application authorization path. `Gate::allowIf()` and
  `denyIf()` also bypass Gate before/after hooks. See [Spatie Super Admin](https://spatie.be/docs/laravel-permission/v8/basic-usage/super-admin)
  and [Laravel Gate interception](https://laravel.com/docs/13.x/authorization#intercepting-gate-checks).
- Spatie forbids conflicting `role`/`roles` properties, methods, relations, or
  fields on the authorization model. The current `users.role` column/cast is a
  hard cutover constraint; `HasRoles` cannot safely be added while that name is
  active. See [Spatie prerequisites](https://spatie.be/docs/laravel-permission/v8/prerequisites).
- PodText uses the `web` guard. Every catalog permission and role must use
  `guard_name=web`; Shield is registered only in panel `admin`, never the guest
  public panel.

### 1.7 Existing tests and evidence gaps

Current positive evidence includes:

- `tests/Feature/RolesGatesTest.php`: all five roles for panel admission;
  Admin/Super Gates in single/multi mode; User Resource denial/access;
  role-command behavior; self/final-Super-Admin UI protection; forged protected
  settings and Card Template writes.
- `tests/Feature/SettingsSp3bTest.php` and SP3C tests: guest/User/Admin/Super
  access, save reauthorization, protected writer behavior, and unchanged state
  on denial.
- `tests/Feature/PanelAuthHardeningTest.php`: guest versus factory-default
  Admin Horizon behavior.
- `tests/Feature/PublicMaintenanceModeTest.php`: factory-default Admin bypass.
- Workbench, Admin Tools, settings lifecycle, import/export, and backup tests:
  successful Admin paths and their domain guards.

AUTHZ1 must add a complete role/ability × surface × operation matrix,
denial-with-zero-state-change evidence for alternate writers, all five roles
for Horizon and maintenance, direct URL/Livewire action tests, protected-role
concurrency, catalog drift/unknown-key tests, cache/worker evidence, and a
Curator bulk-delete regression. SQLite cannot prove `FOR UPDATE`; the
last-Super-Admin race needs disposable MySQL with two genuine connections.

## 2. proposed AUTHZ1 scope slices

### 2.1 Owned catalog contract

PodText deployed code is the sole Ability-definition authority. Each entry
contains:

- a literal stable key;
- `group` and deterministic ordering;
- `label_key` and `description_key`, both present in Hebrew and English;
- `sensitive`, `delegable`, and optional `protected_domain` metadata;
- the `web` guard and an immutable catalog version/hash;
- compatibility-default grant membership by protected seeded role.

Keys use lower-case literal
`<domain>.<subject>.<action>` with each segment matching
`[a-z0-9]+(?:-[a-z0-9]+)*`. Wildcards are disabled. A rename is an explicit
migration, never a generator side effect. Brace notation below is documentation
shorthand for separately declared literal keys; it is not a runtime wildcard.

| Catalog group | Literal key families required by AUTHZ1 |
|---|---|
| Panel/system | `panel.admin.access`; `system.horizon.view`; `public.maintenance.bypass`; `dashboard.admin.view` |
| Editorial records | `content.authors.{view,create,update,delete,import,export}`; the same action set for `categories`, `groups`, `items`, and `transcriptions`; `content.tags.{view,create,update,delete}`; `homepage.sections.{view,create,update,delete,reorder}` |
| Transcription policy | `content.transcriptions.{history-view,multiple-manage,featured-manage}`; the `multi` feature-mode precondition remains separate |
| Media | `media.library.{view,create,update,delete,download}`; reference integrity remains a non-bypassable precondition |
| Public Form submissions | `forms.submissions.{view,status-update,pii-view,pii-export}`; no routine delete key before approved policy |
| Settings subjects | `settings.subjects.{view,update}`; sensitive field/cluster abilities for `settings.security-policy.update` and `settings.trusted-html.update` where the final surface inventory requires them |
| Current Template/Form settings | `settings.card-templates.{view,create,update,delete,protected-manage}`; `settings.public-forms.{view,create,update,delete}` |
| Settings lifecycle | `settings.packages.{export,import,restore}`; `settings.backups.{view,create,delete,download,compare}`; `settings.snapshots.{view,retry,download}`; `settings.import-locks.manage` |
| Workbench/tools | `workbench.connections.{view,create,update,delete,credentials-manage,test,oauth}`; `workbench.spotify.{fetch,direct-import}`; `workbench.probes.run`; `tools.admin.use` |
| Users and security | `users.accounts.{view,update}`; `users.roles.{assign,assign-delegable}`; `security.roles.{view,manage}`; `security.direct-grants.manage`; `security.catalog.sync` |
| ARCH1-reserved Template lifecycle | `templates.parents.{view,create,update,archive,restore}`; `templates.drafts.{own-update,other-view,adopt,discard}`; `templates.revisions.{checkpoint,view,compare,publish}`; `templates.defaults.manage`; `templates.protected.{view,export,activate}`; `templates.portability.{import,export}` |
| ARCH1-reserved Form lifecycle | `forms.definitions.{view,create,update,archive,restore}`; `forms.drafts.{own-update,other-view,adopt,discard}`; `forms.revisions.{checkpoint,view,compare,publish,revoke}`; `forms.availability.manage`; `forms.portability.{import,export}` |

The implementation research may split an action only where the current surface
inventory or an ARCH1 security boundary proves a distinct business decision;
it must not collapse the named sensitive keys. It may not invent abilities
from UI labels or Shield-generated names.

### 2.2 Protected role metadata and compatibility-first grants

`protected` means the deployed role identity cannot be renamed/deleted;
`reserved` means ordinary delegation cannot target or modify it; `delegable`
controls the strict delegated-assignment path. These are independent from the
role's current grant set.

| Seeded role | protected | reserved | delegable | Compatibility-first grants |
|---|---:|---:|---:|---|
| `super-admin` | Yes | Yes | No | Every AUTHZ1 deployed ability, plus Gate interception; domain invariants still apply |
| `admin` | Yes | Yes | No | Every currently Admin-accessible operation, including explicit panel, Horizon, maintenance bypass, settings lifecycle, Workbench, submission/PII view and status mutation, and imports; excludes current Super-only surfaces and does not gain the new PII-export ability merely from migration |
| `moderator` | Yes | No | Yes | No panel/admin ability solely from the role name |
| `transcriber` | Yes | No | Yes | No panel/admin ability solely from the role name |
| `user` | Yes | No | Yes | No panel/admin ability solely from the role name |

Migration maps each user to exactly the one legacy role. It does not add a
universal `user` role. Future default-grant changes are explicit catalog
changes with review; ordinary synchronization does not silently grant them.

### 2.3 Multiple roles, direct grants, and strict assignment

Effective authorization is the additive union of role grants and exceptional
direct grants; rank and explicit-denial layers do not survive cutover. Direct
grants are Super-Admin-only in v1 and pass through the same transaction as
their audit record. The owned audit record includes actor or explicit system
provenance, target, permission, grant/revoke operation, reason, `granted_at`,
optional advisory `review_at`, revoker/revocation reason/time, channel,
release/correlation identifier, and exact before/after sets. Review dates do
not expire grants automatically.

A delegated actor may assign only when, inside the locked transaction:

- the actor has `users.roles.assign-delegable`;
- actor and target differ;
- the target holds no reserved role;
- every role already exists, is non-reserved and delegable, and contains only
  catalog-known permissions;
- each role's complete grant set is a subset of the actor's freshly loaded
  effective permissions;
- no role definition, direct grant, catalog entry, reserved role, or
  Super-Admin membership changes.

AUTHZ1 v1 refuses all self role/direct-grant changes, including by Super Admin;
another Super Admin must act. Bootstrap/backfill uses explicit system
provenance, never a fabricated human grantor.

### 2.4 Single transactional mutation boundary and concurrency

Every UI, Artisan, role-bundle edit, direct grant, user deactivation/deletion,
import/restore, migration, and synchronization path uses one owned mutation
boundary:

1. validate all input/catalog/guard metadata before transaction;
2. begin a database transaction with bounded deadlock retry;
3. lock the canonical protected `super-admin` role row with `lockForUpdate()`
   as the v1 authorization-write mutex;
4. lock actor and target users, then affected roles, in deterministic order;
5. freshly load actor and target roles/direct grants/effective permissions;
6. re-evaluate delegation, reserved-target, self-change, and domain rules;
7. compute exact before/after state;
8. for protected membership changes, count distinct users holding the actual
   `super-admin` role and require the post-state to remain at least one;
9. apply package mutations and append audit records atomically;
10. after commit, invalidate supported caches and discard loaded relations.

Laravel recommends transactions around pessimistic locks and supports bounded
deadlock retries. See [pessimistic locking](https://laravel.com/docs/13.x/queries#pessimistic-locking)
and [deadlock handling](https://laravel.com/docs/13.x/database#handling-deadlocks).

The command boundary replaces ambiguous singular replacement with explicit
semantics:

```text
users:roles add TARGET ROLE... --actor=ACTOR --reason=TEXT
users:roles remove TARGET ROLE... --actor=ACTOR --reason=TEXT
users:roles replace TARGET ROLE... --actor=ACTOR --reason=TEXT --confirm=TARGET
```

- `add` is exact set union; `remove` exact set subtraction; `replace` makes the
  supplied roles the final set and authorizes every addition/removal.
- `--dry-run` resolves actor/target, prints before/after and every blocker, and
  mutates no assignment, audit, or cache state.
- unknown/duplicate/retired/wrong-guard/nondelegable roles or unknown
  permissions fail the whole operation; no role is created implicitly.
- no-op add/remove reports `unchanged`; replace, empty replace, and protected
  removal require exact noninteractive confirmation.
- human actions require actor and reason; system mode requires explicit
  provenance/correlation data.

Direct grants use separate `grant` and `revoke` operations with actor, target,
permission, and reason; there is no bulk direct-permission replace in v1.

### 2.5 Permission synchronization, cache, and workers

Catalog synchronization preflights the complete deployed catalog and database
state, then creates/updates only catalog-known Permission records and owned
metadata. Unknown database keys, malformed/colliding keys, wrong guards,
missing bilingual metadata, or incomplete reserved-role metadata fail before
mutation and are reported together. Sync never silently deletes, grants,
quarantines, or activates unknown keys. Compatibility grants are their own
explicit migration operation.

Spatie package APIs invalidate normal permission caches; controlled direct
backfill needs an explicit `PermissionRegistrar::forgetCachedPermissions()` or
`permission:cache-reset` after commit. User assignments also require loaded
relations to be discarded. See [Spatie cache guidance](https://spatie.be/docs/laravel-permission/v8/advanced-usage/cache).

Permission 7.3 keeps an in-process permission collection and has no
Horizon-specific reset listener. Jobs that authorize store actor IDs, load a
fresh User at `handle()`, and authorize then; no serialized User or memoized
decision is authority. Web and Horizon use the intended shared store with a
PodText-specific cache prefix on the multi-tenant server. Deployed catalog,
code, or role-permission-definition changes require cache reset and recycling
of verified PodText workers. Normal Horizon deployment uses graceful
`horizon:terminate`; see [Horizon deployment](https://laravel.com/docs/13.x/horizon#deploying-horizon).

### 2.6 Lossless expand/backfill/verify/cutover/rollback/contraction

**Expansion:** after exact dependency approval, publish/review the package
schema and deploy the owned catalog/seeded roles without `HasRoles`. Keep
`users.role`, ranks, current Gates, panel admission, and assignments as the
sole authority.

**Raw analysis and backfill:** inspect raw `users.role` values, not enum casts.
Fail before mutation on null/blank/malformed/unknown/case-ambiguous values,
wrong guards, duplicate/colliding roles or permissions, unknown database keys,
or incomplete protected metadata. Backfill exactly one `web` role per user,
idempotently. Verify per-user mapping, total/per-role counts, absence of direct
grants, catalog hash, exact compatibility matrix, and a no-op rerun.

**Coordinated cutover:** because `users.role` conflicts with `HasRoles`, this is
not a zero-downtime schema flip. During an approved authorization/traffic-write
pause: drain the relevant paths; identify and stop/recycle only PodText-owned
workers; rerun raw parity; rename `users.role` to read-only
`users.legacy_role`; activate the `HasRoles` release and policy/Gate authority;
reset permission cache; reload the activated PHP-FPM release; recycle owned
Horizon; run all-role canaries; then reopen traffic. MySQL DDL/metadata-lock
behavior must be rehearsed on production-shaped MySQL.

**Rollback observation:** freeze additive roles/direct grants through the first
accepted observation release. While every user still maps to one legacy value,
the accepted rollback can reverse the authority switch and column rename or
activate the rehearsed legacy-compatible rollback release. There is no dual
write.

**Management enablement:** only after the rollback stop may multiple roles and
direct grants become writable. From then on, rollback targets an AUTHZ-aware
normalized release; projecting multiple roles/direct grants into one legacy
column is lossy and prohibited.

**Contraction:** only after soak, parity, backup/restore drill, cache/worker and
production acceptance, remove `legacy_role`, rank helpers, old Gates/macros,
and rollback compatibility in a later release. Cutover and contraction never
share a deployment.

### 2.7 Required implementation slices and operator/Fable stops

| Slice | Scope boundary | Required stop |
|---|---|---|
| AUTHZ1-A dependency/catalog | exact packages; immutable keys/HE+EN metadata; role metadata/default grants; complete surface disposition | Operator/Fable accept exact catalog, matrix, and dependency resolution before package mutation |
| AUTHZ1-B expansion | package schema/catalog/roles present; legacy authority unchanged; no `HasRoles` | Prove expansion rollback and zero access change |
| AUTHZ1-C analyzer/backfill | fail-closed raw report; exact one-role mapping; idempotent backfill; unknown-key refusal | Operator/Fable inspect parity and zero-partial-write artifacts |
| AUTHZ1-D policies/mutation UI | all Resources/Pages/widgets/actions/writers/commands/imports/restores/Workbench/Horizon; strict delegation; direct-grant audit; bilingual grouped role UX | Accept complete allow/deny/no-mutation matrix before authority cutover |
| AUTHZ1-E concurrency/cache | MySQL protected-role races; transaction rollback; catalog/cache/long-worker behavior | Accept two-connection race, cache, and worker evidence |
| AUTHZ1-F cutover/observation | coordinated rename/`HasRoles`; all-role direct-URL/Livewire canaries; FPM/Horizon observation | Per-action production approval and explicit operator/Fable go/no-go |
| AUTHZ1-G rollback | reverse cutover while additive/direct mutation is frozen | Accept rehearsed rollback before enabling management |
| AUTHZ1-H management enable | multiple roles/direct grants and audited add/remove/replace become writable | Accept normalized-authority-only rollback boundary |
| AUTHZ1-I contraction | remove legacy authority after soak and recovery proof | Separate operator/Fable approval; AUTHZ1 accepted before ARCH1 begins |

## 3. acceptance evidence required for each slice

### AUTHZ1-A

- clean Composer dry-run and audit on the current graph;
- reviewed vendor constraints/config/migrations and prohibited production
  Shield commands;
- catalog schema validation, immutable hash, literal-key lint, HE/EN key
  completeness, duplicate/collision/guard checks;
- every current surface in section 1 mapped to one or more abilities or an
  explicit non-authorization domain precondition;
- compatibility grant diff for all five protected roles;
- role-management UX review in Hebrew RTL and English, including sensitive
  descriptions and strict server validation.

### AUTHZ1-B

- package tables/indexes/FKs are independently reversible on disposable MySQL;
- no `HasRoles`, rank, Gate, panel, Horizon, or current assignment behavior
  changes;
- empty database/catalog expansion rollback is rehearsed;
- strict authorization is not enabled while policies remain incomplete.

### AUTHZ1-C

- raw-source report includes counts, hashes, every invalid value, guard drift,
  unknown permission, protected-role metadata, and intended mapping without
  printing private identities unnecessarily;
- corrupt/unknown fixtures cause zero writes;
- every user maps to exactly the legacy role and no automatic `user`/direct
  grant appears;
- total and per-role counts, per-user hashes, access matrix, and rerun no-op
  reconcile;
- permission cache reset is explicit after controlled backfill.

### AUTHZ1-D

- five-role plus exceptional multi-role/direct-grant tests for panel,
  Horizon, maintenance, policy/Gate, Resources, Pages, relation managers,
  widgets, custom actions, settings writers, commands, imports/restores,
  backup/snapshot endpoints, Workbench/OAuth/credentials, Spotify direct
  import, Curator, and Public Form PII/status paths;
- every denial also proves no model, file, credential, job, audit, cache, or
  settings state changed;
- Super Admin Gate interception works through `can()`/policies while feature
  mode, validation, conflict, lock, transition, reference, retention,
  confirmation, self-change, and final-Super-Admin rules still refuse;
- Shield cannot create/delete catalog definitions or overwrite owned policies;
- add/remove/replace/direct-grant semantics and append-only audit atomicity are
  proven for UI and Artisan/system provenance.

### AUTHZ1-E

- disposable MySQL, two connections, deterministic protected-role lock order;
- simultaneous remove/replace/deactivate/delete attempts leave at least one
  distinct Super Admin and create no orphan/partial audit;
- deadlock retry is bounded and failures roll back assignment plus audit;
- unknown-key sync fails with no partial rows/grants;
- package cache and loaded relations invalidate after commit;
- long-running worker test proves a fresh actor/catalog after reset/recycle.

### AUTHZ1-F and AUTHZ1-G

- pre-cutover backup, raw report, exact private intended-Super-Admin check,
  final hashes, write pause, MySQL rename timing, release topology, process
  ownership, cache prefix/store, FPM, and Horizon checklist;
- Super Admin/Admin can enter the panel; Moderator/Transcriber/User cannot;
  every current Admin/Super exception and direct URL behaves identically;
- `panel.admin.access`, `system.horizon.view`, and
  `public.maintenance.bypass` are independently demonstrated;
- an already-mounted Livewire component loses access on the next request after
  demotion; no stale worker or PHP process retains authority;
- rollback restores the exact legacy matrix while additive/direct mutation is
  frozen, followed by a clean forward re-cutover.

### AUTHZ1-H and AUTHZ1-I

- multiple roles, direct grants, strict delegation, self-refusal, protected
  targets, and audit/review metadata pass end-to-end;
- operators acknowledge that rollback is normalized-authority-only after this
  point;
- soak shows no catalog drift, cache staleness, authorization errors, lost
  assignments, or worker mismatch;
- final backup/restore and forward-recovery drill is accepted before legacy
  column/code removal;
- AUTHZ1 final acceptance is recorded before ARCH1 implementation begins.

## 4. risks and rollback boundaries

### Current security and migration risks

- Curator bulk delete can bypass the individual referenced-media response.
- Policy-free Resources and non-strict Filament authorization make panel
  admission the current security perimeter; giving lower roles panel access
  before the full matrix would expose broad CRUD, settings, PII, backups,
  imports, credentials, and tools.
- `users:assign-role` bypasses UI self/final-Super-Admin safeguards.
- the current final-Super-Admin count is race-prone.
- settings/Workbench/import service classes authorize by caller topology, not
  reusable execution boundaries.
- mixed old/new FPM or Horizon code cannot safely overlap the `role` rename;
  this cutover needs a controlled maintenance boundary.
- a legacy-only rollback stops being lossless immediately after additive roles
  or direct grants are enabled.

### Super Admin boundary

The `Gate::before()` interception grants authorization only. The following are
evaluated before or independently of the bypass and remain mandatory for every
actor: feature mode (including single transcription mode), validation,
authorization-input freshness, optimistic conflicts, database/import locks,
state transitions, reference integrity, retention rules, confirmation,
self-change restrictions, final-Super-Admin protection, and migration/catalog
consistency. No UI-hidden state or direct Spatie method substitutes for these
checks.

### ARCH1 risks

- ARCH1 Template/Form parent, per-user draft, revision, publish, defaults,
  protected revision, availability, portability, PII, and adoption policies
  depend on stable literal ability keys; renaming after ARCH1 would orphan
  grants and invalidate acceptance evidence.
- AUTHZ1 must reserve the named Template/Form namespaces without prematurely
  implementing ARCH1 models or workflows.
- ARCH1 imports/restores and after-commit coordinator must consume the owned
  actor/mutation boundary rather than create another permission/cache path.
- BQ4 PII separation and no routine delete must already be expressible before
  Public Forms move to versioned Resources.

### SP3D and lifecycle risks

- `UserFactory` currently implies Admin. SP3D fixtures must create catalog and
  named role/permission states deterministically, own their data, and reset
  Permission Registrar state between tests.
- all three browser harnesses need explicit login/session states, direct URL
  refusal, demotion/ejection on the next Livewire request, stale-cache recovery,
  and no development-database use.
- role/permission policies may add queries and serialized state. Component,
  query, response, DOM, heap, listener, navigation, and TTFB evidence must be
  recalibrated after AUTHZ1 and ARCH1; pre-policy budgets are not preserved by
  assertion.
- the Shield Role Resource/large catalog is a separate management surface; its
  grouped/searchable UX and payload/DOM cost must not be confused with ordinary
  settings-page SP3D budgets.
- current settings lifecycle demotion tests update `users.role` directly; they
  must move to the owned mutation boundary and prove mounted-state rechecks.

### Rollback boundaries

- Expansion rollback: safe before `HasRoles` because legacy authority is
  untouched.
- Backfill rollback: safe because target assignments are not authoritative;
  the raw report and backup remain controlling.
- Cutover rollback: safe only during the one-role/no-direct-grant frozen
  observation window and only through the rehearsed coordinated release.
- Post-management rollback: normalized AUTHZ-aware code only; never collapse
  multiple roles/direct grants to `legacy_role`.
- Post-contraction recovery: forward migration or backup restoration; a
  destructive `down()` must not be represented as lossless.

## 5. dependency and production approvals still required

- Exact approval to install Shield 4.2.0, Permission 7.3.0, and the resolved
  plugin-essentials dependency. Permission 8 is not compatible with Shield
  4.2's `^6|^7` constraint.
- Approval to publish and review package config/migrations and to register
  Shield only on panel `admin`.
- Separate approval for every migration, raw production report, backfill,
  catalog/default-grant sync, authority cutover, rollback, management-enable,
  and contraction action.
- Read-only production inventory, backup confirmation, private intended-role
  verification, Redis/store/prefix review, deployment topology review, and
  production-shaped MySQL rehearsal before mutation.
- Per-action production approval for traffic/write pause, cache/config
  mutation, PHP-FPM reload, `horizon:terminate`, queue restart, or any process
  action. Before process action, verify `/proc/<pid>/cwd` and environment prove
  the process belongs to PodText's active release and `APP_NAME`.
- Operator/Fable acceptance at each AUTHZ1 slice. No ARCH1 implementation,
  implementation prompt, or SP3D calibration begins until AUTHZ1 final
  acceptance.
- No package, database, production, process, prompt, or push action is approved
  by this research report itself.

## 6. only genuinely unresolved operator questions, if evidence reveals any

None. The evidence exposed implementation and production approval stops, not a
new product/architecture question. Groups 1–15, BQ1–BQ6, the audited defaults,
multiple additive roles, exceptional audited direct grants, strict delegation,
the owned catalog, compatibility-first grants, separate panel/Horizon access,
and the AUTHZ1-before-ARCH1 sequence remain settled.
