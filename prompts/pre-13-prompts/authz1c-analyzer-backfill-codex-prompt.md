# AUTHZ1-C Analyzer / Backfill Implementation Prompt

Prompt version: v1 — 2026-07-16. If this exact version is not the version named
by the operator at kickoff, stop before any command or edit and report the
mismatch.

## Task contract

Implement AUTHZ1-C only: a privacy-safe fail-closed analyzer, exact accepted-
report controlled backfill, crash-safe receipt protocol, and pre-cutover
rollback for legacy `users.role` to protected Spatie Permission role
assignments. Legacy authorization remains authoritative after completion.

The controlling contracts are, in priority order after this prompt:

1. `docs/research/settings-performance/15-authz1c-analyzer-backfill-research.md`
   audit v1;
2. `docs/research/settings-performance/15-authz1c-analyzer-backfill-implementation-plan.md`;
3. the shipped foundation catalog/config/schema/source and its tests;
4. exact installed Spatie Permission 7.3.0 source.

Do not invent behavior missing from those contracts. If they conflict with the
repository, installed source, or this prompt, stop and report before editing.

## Mandatory session preflight

Before any work, read in the repository-mandated order and in full:

1. `AGENTS.md`;
2. `docs/phase-02/ai-development-lessons.md`;
3. `docs/phase-02/current-project-state.md`;
4. the head/relevant AUTHZ rows of the mini-step ledger;
5. the newest two handoffs by git date, including the AUTHZ1 foundation and
   maintenance Livewire enforcement audit handoffs;
6. this prompt, confirming its exact v1 line;
7. both controlling AUTHZ1-C documents above;
8. reports 10–14 under `docs/research/settings-performance/` where referenced
   by the contracts;
9. relevant evergreen guidelines, current app/config/migration/tests, and exact
   installed package source.

Activate and apply `laravel-best-practices`, `filament-security-audit` for the
affected authorization boundary, and `pest-testing` for the test contract. Use
Laravel Boost application info and installed-version documentation where
available. Inspect installed source for package behavior; keep official docs,
installed source, repository evidence, and inference separate in research and
handoff.

Then verify and record:

- clean working tree and current branch;
- the operator-named `docs: plan authz1c analyzer backfill` commit is present
  and ancestral;
- foundation commits `97f8861` and `6ff1441`, audit commit `17a1352`, and the
  planning commit are ancestral in that order where applicable;
- AUTHZ1-C implementation has not started and AUTHZ1-D–I remain unstarted;
- no other task is editing the checkout;
- exact package/app versions and unchanged catalog/config/schema hashes;
- no unexpected PHP, Blade, migration, test, config, or app-code changes.

Stop and report any mismatch. Work sequentially in this checkout. Do not create
a worktree or spawn parallel repository writers.

## Baseline before implementation

Run the current sequential SQLite `:memory:` authorization baselines that cover
the package foundation/catalog and the five-role legacy Gates, panel, Horizon,
maintenance, User Resource, Author Resource, and Admin Tools behavior. Include
the existing SQLite/dev-database canaries. Stop before coding if any baseline
fails unless the failure is an explicitly accepted part of AUTHZ1-C.

Do not query, migrate, seed, copy, or otherwise probe the local development
database. Tests must own every fixture and use a dedicated SQLite `:memory:`
database. Do not access production.

## Non-negotiable acceptance outcome

The implementation is incomplete unless it proves all of the following:

- raw source is selected without Eloquent enum casts, accessors, trimming,
  normalization, aliases, or fabricated defaults;
- every unknown, corrupt, duplicate, ambiguous, guard-drifted, catalog-drifted,
  schema-drifted, or otherwise invalid value appears in one complete privacy-
  safe report and causes zero authorization DB writes and zero cache resets;
- every valid user maps to exactly one same-slug protected package role;
- no automatic baseline `user` role, extra role, permission definition,
  compatibility grant, or direct permission grant appears;
- analysis and apply are separate, and apply requires the exact accepted source
  and report fingerprints plus literal confirmation;
- apply is transactional, deterministic, idempotent, retry-safe, crash-safe,
  and safe to rerun;
- total/per-role counts, unique per-user hashes, raw-role hashes, assignment
  hashes, target fingerprints, access parity, and rerun no-op reconcile;
- permission cache reset occurs exactly once only after a successful changed
  backfill commit, or during exact post-commit crash recovery; never during
  analysis, refusal, transaction rollback, ordinary no-op, or rollback;
- legacy `users.role`, `UserRole`, ranks, Gates, panel/Horizon/maintenance,
  User Resource, and callers remain authoritative after C;
- a receipt-scoped rollback before cutover removes only assignments inserted by
  that receipt, preserves roles/pre-existing data, and performs no cache reset;
- no authority cutover, policy/UI work, management enablement, direct grants,
  catalog mutation/sync, ARCH1, SP3D, or AUTHZ1-D–I enters scope.

## Exact implementation surface

Follow the plan's exact classes, paths, command signatures, report/receipt
schemas, issue grammar, key derivation, filesystem rules, transaction order,
locking/retry protocol, crash journal, cache order, rollback, exit codes, and
test matrix.

Create only:

- three commands:
  `authz:roles:analyze`, `authz:roles:backfill`, and
  `authz:roles:rollback`;
- focused app-owned immutable DTO/canonicalization/hashing/artifact/analyzer/
  validator/apply/rollback classes under
  `app/Auth/LegacyRoleBackfill/`;
- `tests/Feature/AuthzLegacyRoleBackfillTest.php` and only genuinely necessary
  shared test fixture changes;
- implementation evidence updates and the required handoff/state rows.

Do not add or change a migration, dependency, npm package, package config,
catalog definition, role enum, User trait/cast/accessor, Gate, policy, Filament
Resource/page/form/table, panel provider, Horizon rule, maintenance middleware,
route, navigation, translation, queue/job, mail path, or production command
guard. If a necessary change would cross this list, stop for operator approval.

### App-owned package-table boundary

Do not add `HasRoles` and do not use package Role/Permission models for writes.
Use configured table and column names plus query-builder inserts inside the
controlled transaction. Derive model type with `(new User())->getMorphClass()`.
Teams must remain false and the schema must contain no team columns. Create only
missing exact protected `web` role definitions and missing exact
`model_has_roles` rows. Never create/update/delete permissions or
`role_has_permissions`, and never write `model_has_permissions`.

### Analysis boundary

Read `users.id` and `users.role` raw, ordered by ID, within a consistent
non-mutating transaction. A role is valid only if its native string bytes equal
one exact `UserRole::value`. Enumerate every problem without early return.
Report HMAC identifiers and canonical role slugs only; never emit IDs, names,
emails, raw invalid values, APP_KEY material, SQL bindings, or report details
to ordinary logs.

Any nonempty permission/grant/direct-grant table is a blocker. Existing roles
and assignments must be an exact clean subset of the planned same-slug state.
Unknown/wrong-guard/colliding roles, foreign/orphan/multiple/extra pivots,
wrong model type, team/config/schema drift, and static catalog/manifest drift
are blockers. Missing exact roles/assignments are planned work.

Publish immutable local-private artifacts only beneath
`storage/app/private/authorization/authz1-c/`. Validate basename, size, schema,
HMAC/report fingerprint, permissions, non-overwrite, and atomic publication.
Retain artifacts; do not prune or expose them via a route.

### Apply, TOCTOU, and crash boundary

Require `report`, `--accept-source`, `--accept-report`, and
`--confirm=AUTHZ1-C`. Revalidate static/report integrity before mutation. In a
three-attempt database transaction, acquire deterministic user/role/package-
table full-scan locks including empty insertion ranges, recompute raw source
and full target under lock, and accept only the report's exact before state or
an exact prepared-journal planned state. On MySQL require `REPEATABLE READ` or
`SERIALIZABLE`; refuse weaker/unknown isolation. SQLite is test-only and every
other driver is unsupported in C.
Publish the prepared operation journal before the first DB insert. Insert
without upsert/ignore, recompute the exact planned state, and throw on any
mismatch so Laravel rolls back.

Only after commit, call
`Spatie\Permission\PermissionRegistrar::forgetCachedPermissions()` and require
success before receipt/complete journal publication. A normal completed rerun
is a true DB/cache/artifact no-op. A crash after DB commit may recover only when
the prepared journal and exact planned state agree; recovery makes no DB write,
resets cache once, and completes the receipt. Every partial/different state
refuses. Never claim a committed DB transaction was rolled back because later
cache/artifact completion failed.

### Rollback boundary

Require exact receipt, `--accept-after`, and
`--confirm=ROLLBACK-AUTHZ1-C`. Lock/recompute and transactionally delete only
receipt-inserted pivot tuples. Keep all role rows, pre-existing assignments,
source roles, permissions/grants, and direct-grant tables unchanged. Write an
immutable rollback receipt after commit. Do not reset cache. Exact rerun is a
receipt-backed no-op; drift refuses.

## Required Pest evidence

Implement every numbered test category in section 10 of the plan. In
particular, test raw invalid types before enum conversion, multiple simultaneous
problems in one complete report, privacy-negative scanning, exact zero-write
and cache-spy assertions, guard/model/team/schema/config drift, path and artifact
attacks, report/source tampering, mixed pre-existing exact rows, rollback on
induced failure, deadlock retry recomputation, post-commit cache failure and
crash recovery, idempotent no-op, receipt-scoped rollback/reapply, and complete
five-role legacy access parity.

Every HTTP-touching test calls `Http::preventStrayRequests()`. Every mail-
touching test calls `Mail::fake()`. Tests create their own users/schema/artifact
root and prove SQLite `:memory:` before helpers. Do not use live network/mail or
the development database.

SQLite proves logical transaction/uniqueness/rollback behavior only. Do not
claim it proves MySQL locking, gap locks, deadlocks, or two-connection races.
Record the disposable production-shaped MySQL rehearsal as a later separately
approved gate before any production authorization; do not run it now.

## Research, handoff, and restart-safe docs

Before code, preserve the existing audit v1 and plan as controlling contracts.
During implementation, append implementation evidence to the research note
without weakening its accepted decisions.

Create `docs/phase-02/authz1c-analyzer-backfill-handoff.md` as a committed
handoff containing:

- every requirement classified as Implemented, Already existed, Deferred, Not
  applicable, or Blocked;
- exact files changed and tests added/updated;
- every command run, including failures and reruns, with result;
- separate repository, installed-source, Boost/official-doc, SQLite, inference,
  and deferred-MySQL evidence;
- analyzer/apply/rollback, privacy, TOCTOU, retry, crash, cache, and retention
  behavior;
- FilaCheck/build/full-suite outcomes and deviations;
- rollback limitations and production approval boundary;
- numbered imperative Local Front Check steps (`open`, `run`, `inspect`,
  `expect`), not a self-report;
- `## Commit hash` with `pending` until the implementation commit exists.

Update only the minimum restart-safe rows in current state, ledger, decision
queue, and prompt index. Record C implemented only after gates/commit; record
legacy authority active and AUTHZ1-D hard-stopped. Preserve `MAINT-LW-UX1` as
an independent deferred task due before the first later public Livewire
navigation/polling/lazy/deferred/stream/upload expansion or AUTHZ1 final
acceptance, whichever comes first. Do not implement or prompt it here.

## Requirements sweep and canonical final gate

Before the final gate, inspect the final diff and produce a line-by-line
requirements sweep against this prompt, research, and plan. No requirement may
be silently skipped. Resolve or explicitly block every item before proceeding.

On the final code state, run sequentially and exactly:

1. `vendor/bin/pint --test`
2. `vendor/bin/filacheck`
3. `npm run build`
4. full `php artisan test` last

“Once” means once green on the final tracked state. After any file change,
including docs/handoff edits, re-enter from Pint. Never parallelize or interrupt
the full suite. Do not run `vendor/bin/filacheck --fix`.

## Canonical commits and terminal stop

After the final sequence is green:

1. commit code, tests, research/state docs, and handoff with pending hash as
   `feat: add authz legacy role backfill`;
2. immediately replace pending with the implementation hash in the handoff and
   ledger and commit only that Markdown as
   `docs: backfill authz1c hash`;
3. verify clean status and report both hashes. Do not push.

If any contradiction, baseline, privacy invariant, transaction/cache behavior,
installed-source fact, or final gate blocks completion, do not create the
canonical success commits. Report the exact blocker and leave AUTHZ1-C
unimplemented.

Hard stop after AUTHZ1-C. Do not begin AUTHZ1-D, AUTHZ1-E–I, authority cutover,
compatibility grants, policy/UI management, ARCH1, SP3D, production reporting/
backfill/cache/process work, or `MAINT-LW-UX1`.
