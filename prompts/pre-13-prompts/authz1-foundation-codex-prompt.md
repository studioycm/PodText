# Codex Prompt — AUTHZ1 Foundation: Package, Schema, Catalog, and Compatibility Manifest

Prompt version: v1 — 2026-07-16. (Standing rule: if the committed prompt
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
- Admin panel admission, Horizon authorization, maintenance bypass behavior,
  current Resource/Page/action access, and caller-topology authorization;
- all feature-mode, validation, conflict, reference, retention, confirmation,
  self-change, and final-Super-Admin safeguards.

Specifically prohibited in this run:

- adding `HasRoles` to `User` or introducing `roles()` / `permissions()` on it;
- renaming/removing `users.role`, backfilling package assignments, or creating
  a universal `user` role;
- switching policies, Gates, panel admission, Horizon, maintenance bypass, or
  any writer to package authority;
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

- Follow the complete `AGENTS.md` session-start protocol and verify the exact
  starting commit `fd39adcafad72a5b9eae90b672f526139ab2eb1b` plus a clean tree.
- This v1 file is the session's single task contract. Re-read it in full and
  verify the `Prompt version` line before proceeding.
- Before application/package implementation, create both:
  `docs/research/settings-performance/13-authz1-foundation-research.md` and
  `docs/research/settings-performance/13-authz1-foundation-implementation-plan.md`.
- Use current official primary documentation, installed-version Laravel Boost
  guidance, and installed vendor source. Use FilamentExamples in decomposed and
  refined batches before changing Filament panel/plugin code; record honestly
  whether it exposed search snippets or source/detail access.
- Apply the repository-owned `filament-security-audit`,
  `laravel-best-practices`, directly affected `filament-forms-ux-audit`, Pest,
  and PHP/Laravel style guidance. Do not expand an audit into unrelated fixes.
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
   actual checked-in schema via SQLite `:memory:` tests.
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
- guard name, fixed to `web`;
- catalog version and deterministic hash.

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

- catalog version/hash determinism and exact literal key set;
- grammar, wildcard/brace/case/normalization collisions, duplicate keys/order,
  wrong guard, and unknown grant refusal;
- Hebrew/English group/label/description completeness and duplicate PHP
  translation keys;
- protected/reserved/delegable role metadata integrity;
- compatibility grant integrity, including Admin exclusions and empty dormant
  role grants;
- Admin-only Shield adapter registration and Public-panel exclusion;
- published config/schema defaults, table/guard/index/FK expectations, and
  reversible migration behavior in SQLite `:memory:`;
- absence of `HasRoles`, `roles()`/`permissions()`, package assignment rows,
  catalog sync/seeding, and authority switches;
- all five roles' current panel access, Super/Admin Gates, User Resource access,
  Horizon audience, maintenance bypass, and representative ordinary Admin
  Resource/Page behavior remaining unchanged;
- no-regression evidence for self/final-Super-Admin checks and protected
  multi-transcription feature/mode rules.

Tests own their fixtures. HTTP tests use `Http::preventStrayRequests()` and
committed fixtures; mail tests use `Mail::fake()`. Do not run tests in parallel.
Do not use the development database.

## Requirements sweep and final gate

Before the final gate, write the handoff's requirement classification and audit
this prompt/report 12 item by item. Classify each meaningful requirement as
Implemented, Already existed, Deferred, Not applicable, or Blocked. Verify:

- dependency diff is exact and audited;
- published config/migrations are reviewed and reversible;
- no prohibited authority/cutover/assignment behavior exists;
- catalog/role/default-grant/surface-disposition coverage is complete;
- translations and duplicate-key scan pass;
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
