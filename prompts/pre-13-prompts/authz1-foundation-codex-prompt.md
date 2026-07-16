# Codex Prompt — AUTHZ1 Foundation: Package, Schema, Catalog, and Compatibility Manifest

Prompt version: v3 — 2026-07-16. (Standing rule: if the committed prompt
differs from the version named in the kickoff, stop and ask.)

Work in the current local clone of `studioycm/PodText` on the existing branch.
Do not create a worktree, push, or mutate production.

ONE run: implement only the first reversible AUTHZ1 foundation opened by the
operator's acceptance of AUTHZ1-A. Install the exact approved Shield/Permission
dependency pair, publish and review their configuration/schema, add the
application-owned immutable literal Ability catalog and role/default-grant
manifests, constrain Shield to the Admin panel, prove legacy access remains
authoritative and unchanged, and document every current authorization surface
for later policy migration.

This is not an authority cutover. Do not begin AUTHZ1-C through AUTHZ1-I.

## Operator-authorized maintenance/Livewire exception

During v2 execution, the required five-role matrix exposed a pre-existing
maintenance boundary defect: an initial public request by Moderator,
Transcriber, or User correctly receives the maintenance 503, but a real
Livewire update using a public component snapshot obtained before maintenance
activation continues with HTTP 200. Installed Livewire 4 source shows why:
the persistent-middleware pipeline re-runs `RenderMaintenanceMode`, but discards
an ordinary non-redirect response returned by that middleware.

On 2026-07-16 the operator explicitly authorized this one scope amendment:

- change `RenderMaintenanceMode` only as needed to terminate an actual Livewire
  update with its already-rendered maintenance 503 response;
- preserve the response body, `Retry-After`, enabled/disabled behavior, current
  Super/Admin bypass, denied Moderator/Transcriber/User audience, public form
  routes, Admin routes, and every non-Livewire maintenance path;
- add real initial-page-snapshot to actual-update-endpoint tests for all five
  roles, plus disabled-mode and response/header regressions;
- research and record the maintenance-access and broader runtime effects;
- create, but do not execute in this run, the dedicated audit prompt
  `prompts/pre-13-prompts/maintenance-livewire-enforcement-audit-codex-prompt.md`
  at v1 and list it in `prompts/README.md`.

No other access change, Livewire component rewrite, update-route replacement,
global middleware change, maintenance UI change, AUTHZ1 cutover, or unrelated
fix is authorized. Treat this exception as preservation of the declared legacy
maintenance audience, not permission to alter any other authority surface.

## Approved dependency mutation

The operator explicitly approved only this exact resolution:

```text
bezhansalleh/filament-shield                 4.2.0
spatie/laravel-permission                    7.3.0
bezhansalleh/filament-plugin-essentials      1.2.1 (transitive only)
```

Use exact direct constraints for Shield 4.2.0 and Permission 7.3.0. Do not add
plugin-essentials as a direct requirement. No npm change, alternative package
version, Composer update outside the approved solve, or other dependency is
authorized. If the exact solve differs, stop before mutating manifests and
report the contradiction.

## Controlling records

Read these in full before implementation:

1. `docs/research/settings-performance/12-authz1-pre-implementation-research.md`
   — controlling AUTHZ1 contract.
2. `docs/research/settings-performance/09-arch1-drafts-authorization-research.md`
   — supporting authorization/ARCH1 boundary.
3. `docs/research/settings-performance/11-bq-decisions-and-defaults-audit.md`
   — accepted BQ decisions and audited defaults.
4. `docs/research/settings-performance/10-pending-decision-question-queue.md`,
   `docs/phase-02/current-project-state.md`, and
   `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md` — restart-safe
   queue/state records.

Report 12 wins if a supporting record is stale. `AGENTS.md`, the full lessons
file, installed-version official guidance, and current package source remain
binding. Stop if any active record contradicts this prompt or report 12 in a
way that changes scope, authority, dependency versions, or access behavior.

## Hard stop boundary

The following remain authoritative and behaviorally unchanged:

- `users.role` and its `UserRole` cast/ranks;
- `User::hasRoleAtLeast()` and `User::canAccessPanel()`;
- the current `super-admin` and `multi-transcription` Gates/macros;
- `UserResource`, `EditUser`, the existing role assignment command, and all
  current user assignments;
- Admin panel admission, Horizon authorization, the declared maintenance
  bypass audience after closing the specifically authorized persistent-
  Livewire enforcement defect,
  current Resource/Page/action access, and caller-topology authorization;
- all feature-mode, validation, conflict, reference, retention, confirmation,
  self-change, and final-Super-Admin safeguards.

Specifically prohibited in this run:

- adding `HasRoles` to `User` or introducing `roles()` / `permissions()` on it;
- renaming/removing `users.role`, backfilling package assignments, or creating
  a universal `user` role;
- switching policies, Gates, panel admission, Horizon, maintenance bypass
  audience, or any writer to package authority; the narrow termination fix
  explicitly authorized above is the only maintenance exception;
- enabling additive role assignment, direct grants, role management, catalog
  synchronization, or compatibility-grant application;
- generating policies/permissions from Resource names or UI labels;
- enabling Filament strict authorization before complete policies exist;
- running Shield setup/generation/seed/super-admin commands, especially in
  production;
- running migrations, seeders, probes, or experiments against the local
  development database;
- production commands, production writes, process actions, or production data
  changes;
- AUTHZ1 analyzer/backfill/cutover/rollback/management/contraction work;
- ARCH1, SP3D, L10N, ADM, LENS, or unrelated queue work.

If installed package behavior cannot coexist with this boundary, stop before
implementation and report the exact contradiction.

## Standing workflow and coordination

- Follow the complete `AGENTS.md` session-start protocol. The operator clarified
  that provisional v1 was intentionally committed before execution, so verify
  the exact starting commit `6339e858cced21d4ba13de573802c384d0676383`
  (`docs: edit authz1 prompt`) plus a clean tree before the original v2 audit
  patch. Do
  not reset to the stale pre-prompt commit `fd39adc...`.
- Before resuming application implementation under v3, create a prompt-only
  commit containing this v3 amendment, the dedicated v1 audit prompt, and its
  `prompts/README.md` index entry. Stage no partial implementation files in that
  commit. Use `docs: authorize maintenance Livewire enforcement`, record its
  hash, then treat that commit plus the retained partial foundation worktree as
  the amended execution baseline. This operator-authorized prompt commit is in
  addition to the two canonical successful-run ending commits.
- Before repository discovery, run `command -v rg`; use `rg` / `rg --files` for
  discovery and record any fallback. The main controller itself must read the
  full session-start set, reports 09/11/12, the pending-decision queue,
  `prompts/README.md`, multiple recent completed prompt patterns, every relevant
  active `.ai/guidelines` file, every selected `SKILL.md`, and all routed skill
  references required for this work.
- This v3 file is the session's single implementation contract. The dedicated
  maintenance audit prompt created by this run is a future contract and must
  not be executed now. Immediately after the
  last prompt-audit edit, re-read this final file in full, verify the exact
  `Prompt version: v3` line, re-read the new audit prompt in full and verify its
  exact `Prompt version: v1` line, and report the amendment checklist in
  commentary before the prompt-only commit or resumed application work.
- Before application/package implementation, create both:
  `docs/research/settings-performance/13-authz1-foundation-research.md` and
  `docs/research/settings-performance/13-authz1-foundation-implementation-plan.md`.
- Use Laravel Boost `application-info` and installed-version `search-docs`
  before code. Use Boost `database-schema` before schema work, explicitly on
  the isolated `sqlite` connection; never let it default to or probe the local
  development MySQL database. Record returned versions/guidance separately
  from source inspection, tests, and controller inference.
- Use current official primary package documentation and exact tagged or
  installed vendor source. Use FilamentExamples in decomposed short query
  batches, refine from returned names/snippets/paths/classes, and inspect
  source/detail results when the tool exposes them before changing Filament
  panel/plugin code. Record examples copied, patterns avoided, PodText
  adaptations, and whether access was search-only, snippet/source, or full
  detail; if only `search_examples` exists, state that limitation.
- Activate and faithfully apply the repository-owned
  `filament-security-audit`, `laravel-best-practices`,
  `spatie-laravel-php`, `pest-testing`, `filament-forms-ux-audit`, and
  `configuring-horizon` skills. The v3 exception activates
  `livewire-development`; read it fully, use installed-version Boost Livewire
  guidance, and apply it only to the persistent-middleware boundary and real
  update-endpoint regression. Use performance or Tailwind skills only if their
  implementation triggers actually enter scope. Do not expand an audit into
  unrelated fixes.
- Keep performance claims inside the measured plane. This foundation does not
  justify browser DOM, heap, listener, TTFB, or query-budget claims.

The operator also requires coordinated read-only evidence:

- Run at least three distinct background Codex task streams:
  1. package/config/schema/compatibility/command-prohibition/reversibility;
  2. catalog grammar/groups/bilingual metadata/role metadata/default grants/
     Shield adapter boundary;
  3. current access/tests/surface disposition/no-regression/Filament
     enforcement/AUTHZ1-B hard-stop.
- Run at least two bounded read-only subagent reviews covering:
  1. codebase security plus applicable Laravel rule files;
  2. catalog/test integrity and no-regression coverage.
- Every worker must be told not to edit the checkout, install packages, run
  migrations, execute mutating commands, commit, push, touch production, or
  touch local development data.
- Give each worker a narrow deliverable, evidence checklist, concise reporting
  format, and an explicit proportional time/effort box. Check status while
  continuing useful main-task work. Redirect duplication, later-slice scope,
  or non-blocking detail; interrupt a worker that still fails to converge.
- Do not wait for exhaustive research once primary evidence is sufficient.
  The main task owns synthesis and conflict resolution against report 12.
- Record material task/thread IDs, subagent scopes, findings, and limitations
  in the research note and handoff; omit process noise.

No delegated worker may edit. This primary task is the sole writer and the only
task allowed to install, stage, or commit.

## Preflight and baseline

Run and record:

```bash
git status --short --branch
git rev-parse HEAD
git log --oneline -8
composer validate --strict
composer audit --format=plain
php artisan test --compact tests/Feature/RolesGatesTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php
```

The test command must use the repository's forced SQLite `:memory:` test
environment and must run sequentially. Do not use the local development
database. If the clean baseline is red for an application reason outside this
scope, stop before dependency installation. A sandbox-only local-port failure
may be retried with the approved runner and must be disclosed.

Composer installation is gated on all of the following being true at once:
final v3 was re-read/version-verified; the controller's complete startup/
prompt-pattern/guideline/skill sweep is recorded; Boost application/version,
installed-version docs, isolated SQLite schema evidence, official primary
package docs, and FilamentExamples access level are recorded; the six original
mandatory skills and the now-triggered `livewire-development` skill were
applied; all required delegated research is sufficient; the
research note and file-specific implementation plan exist; baseline commands
are green; and the exact dry-run below is accepted. Report this gate explicitly
in commentary before installation.

Before Composer mutation, repeat the exact dry run from report 12 and verify it
proposes only the two direct packages plus plugin-essentials 1.2.1, with no
updates or removals:

```bash
composer require bezhansalleh/filament-shield:4.2.0 spatie/laravel-permission:7.3.0 --dry-run --no-scripts --no-interaction --minimal-changes
```

## Job 1 — research note and implementation plan before code

The research note must consolidate rather than repeat report 12. Include:

- current installed versions and exact primary-document/source evidence;
- exact Composer dry-run, audit, and compatibility result;
- Shield and Permission service-provider/config/migration discovery behavior;
- the exact publish tags/files/commands considered safe for this foundation;
- an explicit prohibited-command table for Shield setup/generate/seed/
  super-admin and any destructive policy/permission regeneration;
- schema review for table names, guard columns, indexes, foreign keys, cache
  configuration, teams disabled, and reversibility on SQLite tests plus a
  production-shaped MySQL review boundary without touching dev/prod data;
- Admin-only Shield registration and proof the guest Public panel is excluded;
- a complete current-surface disposition matrix mapping every surface in
  report 12 §1 to literal catalog abilities, legacy authority, future policy/
  action/service enforcement location, and non-bypassable domain preconditions;
- forms/security audit findings that materially affect this foundation,
  including the existing Curator single-vs-bulk delete discrepancy as later
  policy-migration work, not a drive-by fix;
- explicit later-slice dispositions: importer per-row create/update
  authorization is AUTHZ1-D work once policies exist; User Resource record
  action/save authorization must move from Resource `can*()` assumptions into
  the AUTHZ1-D UserPolicy/transactional boundary; and Curator's current
  reference-aware individual delete versus unconditional `deleteAny()`
  discrepancy is an AUTHZ1-D security correction with an individual-record
  bulk authorization or equivalent, not desired behavior to preserve;
- coordination task/thread/subagent IDs, material findings, disagreements,
  resolution against primary evidence/report 12, and limitations.

The implementation plan must be file-specific and include dependency mutation,
published files, catalog/manifest class APIs, translations, plugin registration,
tests, requirements sweep, rollback, handoff, and the canonical commits. It
must explicitly classify what is declarative-only in this slice and what is
deferred to AUTHZ1-C–I.

## Job 2 — exact package install and safe published foundation

1. Install only the approved exact direct packages with minimal changes and no
   scripts first; review the lockfile diff before allowing normal package
   discovery needed by Laravel. Do not authorize unrelated updates.
2. Inspect installed Shield 4.2.0 and Permission 7.3.0 source, Composer
   constraints, service providers, command signatures/help, publish tags,
   migrations, and config before publishing.
3. Publish only the package configuration and migrations required for this
   foundation, using the installed package's exact supported tags/commands.
   Review every published file. Never invoke fresh setup or policy/permission
   generation.
4. Keep Permission teams disabled and use guard `web`. Use an app-specific
   permission cache key/prefix configuration suitable for the shared
   multi-tenant server without changing production state.
5. Keep the migrations independently reversible. Do not edit third-party
   migration semantics casually; document MySQL index/FK review and prove the
   actual checked-in schema via a package-only SQLite `:memory:` up/down/up
   test. Invoke only the newly published package migration objects, assert the
   package tables/columns/indexes/FKs after both `up()` passes and their absence
   after reverse-order `down()`. Never use `migrate:rollback`, `migrate:fresh`,
   `migrate:refresh`, database-wide rollback helpers, or another broad rollback
   command for this proof.
6. Do not run the migrations against the local development database. Migration
   behavior belongs to tests only.

If Composer scripts are needed only to complete normal Laravel package
discovery after the reviewed no-scripts install, run the narrow supported
command and record why. No package command may mutate the local database.

## Job 3 — application-owned immutable Ability catalog

Create an application-owned catalog under an existing-consistent authorization
namespace. Shield-generated identifiers, Resource class names, UI labels, and
database rows are adapters/consumers, never the authority.

Each Ability entry must expose immutable data for:

- literal stable key;
- catalog group and deterministic group/entry order;
- `label_key` and `description_key`;
- `sensitive`, `delegable`, and optional `protected_domain` metadata;
- guard name, fixed to `web`.

Catalog version and deterministic hash belong to the canonical catalog
envelope, not to individual Ability entries. Entries contain exactly the ten
canonical fields fixed below.

Grammar is exactly lower-case literal `<domain>.<subject>.<action>`, with every
segment matching `[a-z0-9]+(?:-[a-z0-9]+)*`. Wildcards and brace shorthand are
invalid runtime keys. Duplicate keys, normalized/case collisions, duplicate
ordering, wrong guards, unknown role grants, and missing translations fail
closed in validation/tests.

Declare every literal key required by report 12 §2.1, including the named
ARCH1-reserved Template/Form namespaces, without implementing those workflows.
Do not collapse sensitive distinctions such as panel/Horizon/maintenance,
submission view/status/PII, Workbench credentials/OAuth/test/probe/import, or
Template protected/portability operations.

Add complete Hebrew and English group labels plus per-ability labels and
descriptions. Descriptions must make sensitive/admin/security effects clear;
all translation keys must exist in both locales and pass the repository's
duplicate-key check.

### Golden catalog vector and canonical hash

The catalog expectation is independently frozen here, not derived from the
production catalog class. It contains exactly these 135 ordered literal keys;
every line is a runtime key and brace/wildcard expansion is forbidden:

```text
# panel-system
panel.admin.access
system.horizon.view
public.maintenance.bypass
dashboard.admin.view
# editorial-records
content.authors.view
content.authors.create
content.authors.update
content.authors.delete
content.authors.import
content.authors.export
content.categories.view
content.categories.create
content.categories.update
content.categories.delete
content.categories.import
content.categories.export
content.groups.view
content.groups.create
content.groups.update
content.groups.delete
content.groups.import
content.groups.export
content.items.view
content.items.create
content.items.update
content.items.delete
content.items.import
content.items.export
content.transcriptions.view
content.transcriptions.create
content.transcriptions.update
content.transcriptions.delete
content.transcriptions.import
content.transcriptions.export
content.tags.view
content.tags.create
content.tags.update
content.tags.delete
homepage.sections.view
homepage.sections.create
homepage.sections.update
homepage.sections.delete
homepage.sections.reorder
# transcription-policy
content.transcriptions.history-view
content.transcriptions.multiple-manage
content.transcriptions.featured-manage
# media
media.library.view
media.library.create
media.library.update
media.library.delete
media.library.download
# public-form-submissions
forms.submissions.view
forms.submissions.status-update
forms.submissions.pii-view
forms.submissions.pii-export
# settings-subjects
settings.subjects.view
settings.subjects.update
settings.security-policy.update
settings.trusted-html.update
# current-template-form-settings
settings.card-templates.view
settings.card-templates.create
settings.card-templates.update
settings.card-templates.delete
settings.card-templates.protected-manage
settings.public-forms.view
settings.public-forms.create
settings.public-forms.update
settings.public-forms.delete
# settings-lifecycle
settings.packages.export
settings.packages.import
settings.packages.restore
settings.backups.view
settings.backups.create
settings.backups.delete
settings.backups.download
settings.backups.compare
settings.snapshots.view
settings.snapshots.retry
settings.snapshots.download
settings.import-locks.manage
# workbench-tools
workbench.connections.view
workbench.connections.create
workbench.connections.update
workbench.connections.delete
workbench.connections.credentials-manage
workbench.connections.test
workbench.connections.oauth
workbench.spotify.fetch
workbench.spotify.direct-import
workbench.probes.run
tools.admin.use
# users-security
users.accounts.view
users.accounts.update
users.roles.assign
users.roles.assign-delegable
security.roles.view
security.roles.manage
security.direct-grants.manage
security.catalog.sync
# template-lifecycle
templates.parents.view
templates.parents.create
templates.parents.update
templates.parents.archive
templates.parents.restore
templates.drafts.own-update
templates.drafts.other-view
templates.drafts.adopt
templates.drafts.discard
templates.revisions.checkpoint
templates.revisions.view
templates.revisions.compare
templates.revisions.publish
templates.defaults.manage
templates.protected.view
templates.protected.export
templates.protected.activate
templates.portability.import
templates.portability.export
# form-lifecycle
forms.definitions.view
forms.definitions.create
forms.definitions.update
forms.definitions.archive
forms.definitions.restore
forms.drafts.own-update
forms.drafts.other-view
forms.drafts.adopt
forms.drafts.discard
forms.revisions.checkpoint
forms.revisions.view
forms.revisions.compare
forms.revisions.publish
forms.revisions.revoke
forms.availability.manage
forms.portability.import
forms.portability.export
```

The comment headers are not serialized. The contiguous groups above have exact
IDs/orders/counts: `panel-system` 1/4, `editorial-records` 2/39,
`transcription-policy` 3/3, `media` 4/5,
`public-form-submissions` 5/4, `settings-subjects` 6/4,
`current-template-form-settings` 7/9, `settings-lifecycle` 8/12,
`workbench-tools` 9/11, `users-security` 10/8,
`template-lifecycle` 11/19, and `form-lifecycle` 12/17. Entry order is
one-based within its group.

Canonical full entries use this field insertion order and no other fields:
`key`, `group`, `group_order`, `entry_order`, `label_key`,
`description_key`, `sensitive`, `delegable`, `protected_domain`, `guard`.
`label_key` is exactly `authz.abilities.<literal-key>.label` and
`description_key` is exactly `authz.abilities.<literal-key>.description`.
`guard` is always `web`. `sensitive` is true when the action (the third
segment) is not exactly `view`, and also for these exact view keys:
`system.horizon.view`, `users.accounts.view`, `security.roles.view`, and
`templates.protected.view`. `delegable` is exactly the inverse of `sensitive`
in this foundation fixture.

`protected_domain` is `null` except for this exact map:

```text
content.transcriptions.multiple-manage = transcription-feature-mode
media.library.delete = media-reference-integrity
settings.card-templates.protected-manage = protected-card-template
users.roles.assign = protected-role-integrity
users.roles.assign-delegable = protected-role-integrity
security.roles.manage = protected-role-integrity
security.direct-grants.manage = direct-grant-audit
security.catalog.sync = catalog-integrity
templates.protected.activate = protected-template
```

Canonical serialization is PHP `json_encode()` of the insertion-ordered
envelope `['version' => 'AUTHZ1-2026-07-16', 'entries' => $entries]` using
only `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR`,
without whitespace, sorting, pretty printing, or omission of nulls. The
independently computed expected SHA-256 is exactly:

```text
fb46f5ef0228c2017e049b13a6f18eb72183a85b89249385828bf5295b9193c7
```

The committed test fixture must repeat the literal vector and full-entry
expectations independently; it must not import, reflect, map, or compute its
expected values from the production catalog implementation.

## Job 4 — role metadata and compatibility-first grant manifest

Create declarative application-owned metadata for exactly these protected
seeded role identities:

| Role | protected | reserved | delegable |
|---|---:|---:|---:|
| `super-admin` | yes | yes | no |
| `admin` | yes | yes | no |
| `moderator` | yes | no | yes |
| `transcriber` | yes | no | yes |
| `user` | yes | no | yes |

The compatibility manifest must encode report 12 §2.2 exactly:

- Super Admin receives every deployed catalog ability declaratively and will
  retain future Gate interception, while domain invariants remain separate.
- Admin receives every operation it can perform today, including explicit
  panel/Horizon/maintenance access, ordinary CRUD/import/export, settings and
  lifecycle operations, Workbench/tools, media, and current submission PII
  view/status behavior; it excludes current Super-only surfaces and does not
  receive the newly introduced PII-export ability merely from migration.
- Moderator, Transcriber, and User receive no panel/admin ability solely from
  their role names.
- No universal `user` role is added, no assignment changes, and no direct grant
  is created.

Freeze an independent five-role grant snapshot. Super Admin's expected grant
fixture is the full 135-literal vector above. Moderator, Transcriber, and User
each have an exact empty fixture. Admin has exactly 89 allowed and 46 denied
keys. Its allowed fixture is:

```text
panel.admin.access
system.horizon.view
public.maintenance.bypass
dashboard.admin.view
content.authors.view
content.authors.create
content.authors.update
content.authors.delete
content.authors.import
content.authors.export
content.categories.view
content.categories.create
content.categories.update
content.categories.delete
content.categories.import
content.categories.export
content.groups.view
content.groups.create
content.groups.update
content.groups.delete
content.groups.import
content.groups.export
content.items.view
content.items.create
content.items.update
content.items.delete
content.items.import
content.items.export
content.transcriptions.view
content.transcriptions.create
content.transcriptions.update
content.transcriptions.delete
content.transcriptions.import
content.transcriptions.export
content.tags.view
content.tags.create
content.tags.update
content.tags.delete
homepage.sections.view
homepage.sections.create
homepage.sections.update
homepage.sections.delete
homepage.sections.reorder
content.transcriptions.history-view
content.transcriptions.multiple-manage
content.transcriptions.featured-manage
media.library.view
media.library.create
media.library.update
media.library.delete
media.library.download
forms.submissions.view
forms.submissions.status-update
forms.submissions.pii-view
settings.subjects.view
settings.subjects.update
settings.security-policy.update
settings.trusted-html.update
settings.card-templates.view
settings.card-templates.create
settings.card-templates.update
settings.card-templates.delete
settings.public-forms.view
settings.public-forms.create
settings.public-forms.update
settings.public-forms.delete
settings.packages.export
settings.packages.import
settings.packages.restore
settings.backups.view
settings.backups.create
settings.backups.delete
settings.backups.download
settings.backups.compare
settings.snapshots.view
settings.snapshots.retry
settings.snapshots.download
settings.import-locks.manage
workbench.connections.view
workbench.connections.create
workbench.connections.update
workbench.connections.delete
workbench.connections.credentials-manage
workbench.connections.test
workbench.connections.oauth
workbench.spotify.fetch
workbench.spotify.direct-import
workbench.probes.run
tools.admin.use
```

Its denied fixture is:

```text
forms.submissions.pii-export
settings.card-templates.protected-manage
users.accounts.view
users.accounts.update
users.roles.assign
users.roles.assign-delegable
security.roles.view
security.roles.manage
security.direct-grants.manage
security.catalog.sync
templates.parents.view
templates.parents.create
templates.parents.update
templates.parents.archive
templates.parents.restore
templates.drafts.own-update
templates.drafts.other-view
templates.drafts.adopt
templates.drafts.discard
templates.revisions.checkpoint
templates.revisions.view
templates.revisions.compare
templates.revisions.publish
templates.defaults.manage
templates.protected.view
templates.protected.export
templates.protected.activate
templates.portability.import
templates.portability.export
forms.definitions.view
forms.definitions.create
forms.definitions.update
forms.definitions.archive
forms.definitions.restore
forms.drafts.own-update
forms.drafts.other-view
forms.drafts.adopt
forms.drafts.discard
forms.revisions.checkpoint
forms.revisions.view
forms.revisions.compare
forms.revisions.publish
forms.revisions.revoke
forms.availability.manage
forms.portability.import
forms.portability.export
```

Tests must hold these as independent literal arrays. Do not compute Admin's
allow fixture as the complement of deny, compute deny from allow, or derive
either from the production manifest.

Validation must prove all roles are unique/known, metadata is complete, grants
reference literal catalog keys only, every granted key uses `web`, and the
current five-role access behavior is unchanged. This run may define a future
sync adapter contract, but it must not synchronize/seed package tables or make
the manifest authoritative.

## Job 5 — Shield constrained to Admin as a non-authoritative adapter

- Register/configure Shield only for panel ID `admin`. The guest Public panel
  must not include Shield's plugin, pages, resources, middleware, or navigation.
- Do not expose an additive role/direct-grant management Resource in this slice.
  If Shield requires plugin registration for configuration, disable/hide its
  management navigation and routes until AUTHZ1-D/H expressly enables them.
- Disable Shield as the source for policies/permission identifiers. Do not run
  generation or write generated policies.
- Do not change `User` or any current policy/Gate. Do not call direct Spatie
  permission APIs in application authorization.
- Add source/config tests proving the adapter is Admin-only, management is not
  exposed, destructive commands were not wired into deploy/runtime paths, and
  the owned catalog remains the definition authority.

If Shield 4.2.0 cannot be registered safely without `HasRoles`, policy
generation, or management exposure, do not force registration. Record the
installed-source contradiction and stop before changing access behavior.

## Job 6 — tests and unchanged legacy behavior

Use Pest and follow existing test organization. At minimum add/update tests for:

- catalog version/hash determinism, exact independent 135-literal key fixture,
  canonical full-entry serialization, and the exact expected hash above;
- exact invalid fixtures, including uppercase `Panel.admin.access`, wildcard
  `panel.*.access`, brace shorthand `content.authors.{view,create}`, underscore
  `panel_admin_access`, empty segment `panel..access`, a duplicate
  `panel.admin.access`, the normalized/case pair `panel.admin.access` plus
  `Panel.Admin.Access`, two entries sharing the same group/entry order, guard
  `api`, grant `system.unknown.view`, an unknown role `owner`, duplicate
  `admin` metadata, and a manifest missing `user`; each must fail closed with
  no partial accepted result;
- exact Hebrew/English group/label/description key-set parity; every required
  key must satisfy locale-specific existence with `Lang::hasForLocale()` in
  both `he` and `en`, trimmed values must be nonempty, a returned value must
  differ from the lookup key, and fallback-disabled locale lookups must prove
  neither locale is borrowing the other. Run the existing token-level duplicate
  PHP-key scan as a separate check;
- protected/reserved/delegable role metadata integrity;
- compatibility grant integrity against independent literal fixtures: Super is
  all 135, Admin is exactly the 89 allow/46 deny snapshot, and Moderator,
  Transcriber, and User are exactly empty;
- Admin-only Shield adapter registration and Public-panel exclusion;
- published config/schema defaults, table/guard/index/FK expectations, and
  package-only reversible SQLite `:memory:` up/down/up behavior without broad
  rollback commands;
- absence of `HasRoles`, `roles()`/`permissions()`, package assignment rows,
  catalog sync/seeding, and authority switches;
- all five roles' current panel access, Super/Admin Gates, User Resource access,
  Horizon audience, maintenance bypass, and representative ordinary Admin
  Resource/Page behavior remaining unchanged;
- no-regression evidence for self/final-Super-Admin checks and protected
  multi-transcription feature/mode rules.

Drive the legacy-surface proof with a shared exact five-role dataset, in enum
order `super-admin`, `admin`, `moderator`, `transcriber`, `user`, and cover each
surface independently:

- Admin panel: allow Super/Admin; deny the other three.
- Horizon HTTP/direct audience: allow Super/Admin; deny the other three.
- Maintenance bypass: allow Super/Admin and deny the other three in both the
  initial HTTP request and the persistent Livewire update path.
- User Resource: allow only Super; deny Admin and the other three for index and
  edit direct URLs, List/Edit Livewire mounts, and the existing record-action/
  save path. Denials must prove the target role and persisted state are
  unchanged. This is regression evidence only; the UserPolicy/action/save
  migration remains explicitly deferred to AUTHZ1-D.
- Legacy `super-admin` Gate: allow only Super.
- Legacy `multi-transcription` Gate: in single mode deny every role for both
  Admin and Super minimums; in multi mode allow Super/Admin for Admin minimum,
  allow only Super for Super minimum, and deny Moderator/Transcriber/User.
- Representative ordinary Admin Resource: use Author Resource list/direct URL
  and Livewire page; allow Super/Admin and deny the other three.
- Representative ordinary Admin Page: use Admin Tools direct URL and Livewire
  page; allow Super/Admin and deny the other three.

Package-table rows, including deliberately inserted additive role/permission
rows in isolated tests where safe, must not change any expected result in this
dataset. Every denied mutation path must assert zero state change.

Tests own their fixtures. HTTP tests use `Http::preventStrayRequests()` and
committed fixtures; mail tests use `Mail::fake()`. Do not run tests in parallel.
Do not use the development database.

## Job 6A — narrow maintenance/Livewire enforcement correction

Research before editing:

- use Boost installed-version Livewire 4 and Laravel 13 documentation for
  persistent middleware, authorization changes after initial page load,
  request termination, and response exceptions;
- inspect installed `Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware`,
  `Livewire\Drawer\Utils::applyMiddleware()`, Laravel's
  `HttpResponseException`, the Public panel middleware registration, and
  `RenderMaintenanceMode` itself;
- record official guidance, installed source, executed test evidence, and
  controller inference separately;
- obtain one bounded read-only security review and one bounded read-only test/
  effect review of the proposed correction before applying it.

Implementation is limited to making `RenderMaintenanceMode` throw/terminate
with its already-built maintenance response when it is executing inside an
actual Livewire update request whose original public route carries the
persistent middleware. Do not replace Livewire's update route, add global
middleware, modify Livewire vendor source, change the maintenance renderer,
change the five-role decision, or alter any component.

The real regression must:

1. load a public page with maintenance disabled and capture its actual child
   component snapshot;
2. enable maintenance;
3. POST that snapshot to the actual installed Livewire update endpoint with
   the real Livewire request header;
4. prove Super/Admin still receive a normal 200 component update;
5. prove Moderator/Transcriber/User receive the exact 503 maintenance body and
   `Retry-After` header, with no component effects or state mutation;
6. prove the same update remains 200 for all roles when maintenance is disabled;
7. preserve the existing initial HTTP, guest/Admin-route, raw override, styled
   maintenance, public maintenance form, cache invalidation, and Hebrew/RTL
   coverage.

The research note and handoff must explicitly audit effects on stale snapshots,
multi-component/bundled updates, lazy/polling requests, public form submissions,
CSRF/session middleware, Admin panel Livewire, Horizon, status/body/header
semantics, exception reporting/log noise, and browser error UX. Classify each as
tested, source-proven, inferred, deferred to the dedicated audit prompt, or not
applicable. Do not claim browser behavior without a browser observation.

Create the separate v1 audit prompt named in the operator exception. It must be
an audit/research contract, not an implementation authorization: Markdown-only
repository outputs, isolated SQLite tests, optional local browser observation,
no application/package/config/migration changes, no production/dev-database
access, and a hard stop requiring a new accepted prompt for any remediation.

## Requirements sweep and final gate

Before the final gate, write the handoff's requirement classification and audit
this prompt/report 12 item by item. Classify each meaningful requirement as
Implemented, Already existed, Deferred, Not applicable, or Blocked. Verify:

- dependency diff is exact and audited;
- published config/migrations are reviewed and reversible;
- no prohibited authority/cutover/assignment behavior exists;
- catalog/role/default-grant/surface-disposition coverage is complete;
- translations and duplicate-key scan pass;
- the exact v3 prompt and future v1 maintenance-audit prompt reread/version
  checks, the prompt-only authorization commit, `command -v rg`, controller-owned
  prompt-pattern/guideline/skill sweep, Boost `application-info`, Boost
  installed-version `search-docs`, isolated-SQLite `database-schema`, official
  primary docs/tagged source, installed vendor source, FilamentExamples query
  refinement and access-level report, each of the six original mandatory
  skills, and `livewire-development` has concrete recorded proof rather than a
  generic claim;
- docs guidance, installed/tagged source inspection, executed tests, and
  controller inference are recorded as distinct evidence categories;
- the independent 135/full-entry/hash fixture, five-role metadata/grants,
  every required five-role surface dataset, package-only up/down/up proof, and
  all three AUTHZ1-D dispositions are present and classified;
- the maintenance correction is limited to the authorized persistent-Livewire
  termination boundary; the real snapshot/update regression is green for all
  five roles and disabled mode; all required effect-audit planes have an honest
  tested/source/inference/deferred classification;
- `git diff --check` passes and no secret/local config is present.

FINAL GATE ORDER, exactly:

1. requirements sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` last.

Never run `vendor/bin/filacheck --fix`. Never interrupt or parallelize the full
suite. Record every run, including failures. After any file change, re-enter
the final gate from Pint; a green suite belongs only to the final file state.

## Docs, handoff, state, and canonical completion

Create `docs/phase-02/authz1-foundation-handoff.md`. Before committing it must
contain:

- requirement classification;
- exact dependency and published-file review;
- files changed and tests added/updated;
- every command and result, including failures and deviations;
- research/tool access level and material delegated evidence with task/thread/
  subagent IDs and limitations;
- the operator-authorized v3 maintenance exception, exact causal source path,
  before/after five-role results, and maintenance effect-audit classification;
- gate outcomes;
- no-production/no-dev-database/no-push confirmation;
- assumptions, deferrals, blockers, and rollback boundary;
- a numbered Local Front Check Report in imperative operator voice. Include
  direct checks that legacy Admin/Super access is unchanged, dormant roles are
  still rejected from Admin, Shield management is absent, and no role/direct
  grant assignment UI is exposed.

Update these restart-safe records to one next point:

- `docs/phase-02/current-project-state.md`;
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`;
- `docs/research/settings-performance/10-pending-decision-question-queue.md`.

The next point must say this reversible foundation is complete while legacy
authority remains active, and that AUTHZ1-C analyzer/backfill is not started
without a new accepted implementation slice. Do not mark AUTHZ1 complete and
do not begin ARCH1/SP3D.

On successful completion only:

1. commit dependency/config/schema/catalog/tests/research/plan/state/handoff
   with `## Commit hash` pending using:
   `feat: add authz package and catalog foundation`;
2. immediately stamp that implementation hash into the handoff and ledger in
   a docs-only commit:
   `docs: backfill authz1 foundation hash`;
3. verify the tree is clean and do not push.

If a contradiction, baseline, package solve, installed behavior, or final gate
blocks completion, do not create the canonical success commits. Report the
exact blocker, retained evidence, and working-tree state.

After both successful commits, end with exactly:

```text
AUTHZ1 foundation is complete. Waiting for operator/Fable review before AUTHZ1-C.
```
