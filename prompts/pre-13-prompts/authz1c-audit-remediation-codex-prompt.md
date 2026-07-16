# AUTHZ1-C Audit Remediation Implementation Prompt

Prompt version: v1 — 2026-07-17. If the operator kickoff does not name this
exact file and version, stop before any command or edit and report the mismatch.

## Task contract

Remediate only AUTHZ1-C independent audit items R-01 through R-05. Preserve the
existing analyzer/projection boundary and legacy runtime authority. Do not
enter AUTHZ1-D.

Controlling contracts, in order after this prompt:

1. `docs/research/settings-performance/17-authz1c-audit-remediation-research.md`
   v1;
2. `docs/research/settings-performance/17-authz1c-audit-remediation-implementation-plan.md`;
3. `docs/research/settings-performance/16-authz1c-independent-analyzer-backfill-audit.md`;
4. shipped AUTHZ1-C v1 source/tests and original contracts as historical
   baseline; and
5. exact installed Laravel 13.19.0 and Permission 7.3.0 source.

The planning documents make every architectural choice. Do not invent a
different cache, ownership, schema, artifact-upgrade, rollback, or command
protocol. Stop on a contradiction.

## Mandatory exact preflight

Before work, read in full and in repository-mandated order:

1. `AGENTS.md`;
2. `docs/phase-02/ai-development-lessons.md`;
3. `docs/phase-02/current-project-state.md`;
4. the head and AUTHZ rows of the mini-step ledger;
5. the newest two handoffs, including the independent audit handoff;
6. this exact v1 prompt;
7. the two controlling `17-authz1c-audit-remediation-*` documents;
8. audit report 16, C v1 prompt/research/plan/handoff, queue, and prompt index;
9. every current AUTHZ1-C app/command/test file, Permission migration/config,
   users source migrations/model/enum, catalogs, and relevant legacy runtime;
10. exact installed Permission registrar/cache source, Laravel cache stores,
    Encrypter, schema builders/grammars/processors, and transaction source; and
11. `laravel-best-practices`, `filament-security-audit`, and `pest-testing`.

Use Boost application information and installed-version docs. Keep repository,
installed source, Boost/official guidance, SQLite evidence, and inference
separate in the handoff.

Require:

- clean `main` at the exact operator-named planning commit with subject
  `docs: plan authz1c audit remediation`;
- implementation `0147ea83947ccedb1336a71d8eecc887eb8d4e07`, closeout
  `628429236c138ad51fcb4b6d8311ad4726afb439`, and independent audit
  `34b6478422e33ab5f73227760c30b4b89387246c` ancestral;
- the operator-named planning commit exact hash matches checkout history;
- AUTHZ1-D–I unstarted and legacy authority active;
- no unexpected PHP/Blade/migration/test/config/app dirt; and
- no other repository writer.

Stop on mismatch. Work sequentially in the saved checkout. Do not create a
worktree or subagent.

## Baseline before implementation

Run sequentially:

```bash
php artisan test --compact tests/Feature/AuthzLegacyRoleBackfillTest.php
php artisan test --compact tests/Feature/AuthzFoundationCatalogTest.php tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php
```

The repository canaries must prove `APP_ENV=testing`, SQLite, and `:memory:`.
Stop on any baseline failure.

Never run `authz:roles:analyze`, `authz:roles:backfill`, or
`authz:roles:rollback` operationally. Do not query/migrate/seed/probe the local
development database. Do not run MySQL or access production.

## Non-negotiable outcome

The implementation is incomplete unless it proves:

- exact recomputed, deep-validated, HMAC-bound v2 prepared evidence;
- an uninterrupted successful writer binds actual role IDs and physical pivot
  identities into a rollback-capable receipt without exposing raw user IDs;
- restarted exact-planned state never becomes receipt-owned and completes only
  as explicit `completed_unowned` / non-rollback-capable evidence;
- role-ID recreation, receipt/journal substitution, external exact projection,
  and physical tuple drift refuse destructive rollback;
- permission cache completion distinguishes `deleted`, `already_absent`, and
  store error and documents durable at-least-once idempotent invalidation;
- no strict exactly-once cache-call claim remains;
- rollback publishes prepared evidence before deletes and recovers exact
  commit-to-receipt planned state without another delete or cache reset;
- runtime schema validation includes exact column type/length/unsigned family,
  nullability, default where contracted, auto-increment, PK/unique/secondary
  indexes, FKs/references/delete actions, and users ID/role source schema;
- APP_KEY parsing matches the installed Laravel provider exactly, and the
  resulting bytes are valid for the configured Laravel cipher before HKDF;
- report/journal/backfill-receipt/rollback-receipt DTO validation is deep and
  boundary/type-confusion tests are direct;
- v1 artifacts remain immutable and are refused with exit 2; no automatic
  upgrade/adoption/rollback occurs;
- the original C handoff's overbroad claims receive an append-only correction;
  and
- legacy enum/ranks/Gates/panel/Horizon/maintenance/User Resource/callers stay
  authoritative, `User` lacks `HasRoles`, and no grants/cutover/UI/policy work
  appears.

## Exact scope

Follow the plan's exact added/modified classes, v2 schemas, filename separation,
deep shapes, physical tuple grammar, schema normalization, cache outcomes,
state transitions, retry/recovery branches, commands, tests, docs, and commits.

Permitted implementation files are current `app/Auth/LegacyRoleBackfill/*`,
the three existing AUTHZ commands, and
`tests/Feature/AuthzLegacyRoleBackfillTest.php`. Permitted docs are the
controlling research evidence, original handoff correction, new remediation
handoff, and minimal state/ledger/queue/prompt-index updates.

Do not change a migration, dependency, `composer.lock`, npm file, config file,
catalog/manifest, `User`, `UserRole`, Gate, policy, Filament surface, provider,
route, middleware, Horizon rule, translation, job/queue, mail, or environment
file. Do not add `HasRoles`, permissions, grants, direct grants, Shield
registration, or management UI. Stop for operator direction if the exact plan
cannot be implemented inside this list.

## Frozen ownership protocol

Prepared v2 evidence contains only the exact report-derived semantic delta and
is fully recomputed on load. Inside the locked three-attempt transaction,
capture and verify actual inserted role IDs, the resolved protected role-ID map,
and inserted physical pivots. Physical tuple HMAC includes actual role ID,
typed raw model ID, and model type; artifacts contain HMAC user identity, never
raw model ID.

Only the current uninterrupted call after `DB::transaction()` returns may set
`ownership_status=proven` and `rollback_capable=true`. A rerun with prepared
evidence and exact planned DB state performs no DB write and publishes only
`ownership_status=unproven`, `rollback_capable=false`, empty owned vectors, and
status `completed_unowned`. It may retain the semantic planned delta in
separate non-ownership fields. Rollback refuses that receipt.

An initial `already_applied` report without prepared evidence remains a true
no-receipt/no-cache no-op. Partial/different state refuses.

## Frozen cache protocol

Use the plan's `PermissionCacheInvalidator` against the exact configured
Permission store/key. Publish keyed pending evidence before invalidation.
Clear registrar memory through Permission 7.3.0, then classify confirmed absent
state as `deleted` or `already_absent`; a false store return with a confirmed
absent key is success. Exception, unreadable state, or a remaining key is an
operational error.

Publish keyed success evidence afterward. If success occurs before journal
publication and the process fails, rerun invalidates again. This is expressly
at-least-once and idempotent. Receipt-backed completed rerun makes no call.

## Frozen rollback protocol

Accept only canonical stored v2 receipts with proven ownership and rollback
capability. Require recorded role ID still to own the exact slug/guard, resolve
the current user by HMAC, and recompute the physical tuple HMAC.

Publish keyed rollback-prepared evidence before the first delete. Exact before
state deletes only those tuples and validates planned state. Exact planned
state plus prepared evidence is recovery with zero deletes. Partial/different
state refuses. Publish rollback receipt/complete evidence after commit or
recovery. Never delete role rows and never reset cache.

## Required Pest evidence

Implement every test in plan section 8. Directly cover all R-01–R-05 cases,
including:

- prepared-field HMAC substitution and full recomputation;
- role delete/recreate, external exact projection, proven versus unproven
  receipts, nonrollback refusal, receipt substitution, and pre-existing tuples;
- real configured Array-store present/absent/repeated-absence semantics,
  store error, and success-before-journal retry;
- rollback post-commit/pre-receipt crash and zero-repeat recovery;
- same-column schemas missing/changing every required index/FK/property and
  users source schema;
- all supported cipher lengths and malformed/short/long keys;
- malformed nested values, numeric-string counts, invalid identity adapter
  types, configured table/morph drift, empty source, and at/over size boundary;
- exact command status/output/exit behavior and zero mutation/cache on refusal;
  and
- unchanged five-role legacy parity and dormant package authority.

Use named Pest datasets where they clarify matrices. Every HTTP-touching test
calls `Http::preventStrayRequests()` and every mail-touching test uses
`Mail::fake()`. Tests own fixtures, SQLite `:memory:` schema, cache state, and
private artifacts. Do not claim MySQL or production evidence.

## Documentation and truth correction

Append remediation implementation evidence to research 17. Create
`docs/phase-02/authz1c-audit-remediation-handoff.md` with every requirement
classified, all files/tests/commands/failures, evidence planes, cache and
ownership truth, rollback limits, v1 refusal, gates, assumptions/deferrals,
and numbered imperative Local Front Check steps. Set `## Commit hash` to
`pending` before the implementation commit.

Append a dated correction to the original C handoff. Preserve its historical
command counts but explicitly supersede the physical ownership, exactly-once
cache, rollback-crash, schema-completeness, and exhaustive-test claims.

Update only minimal current state, ledger, queue, and prompt index rows.
AUTHZ1-D remains blocked pending remediation review; the disposable MySQL
rehearsal remains separate; MAINT-LW-UX1 timing is unchanged.

## Requirements sweep and canonical final gate

Before gates, inspect the complete final diff and classify every prompt,
research, plan, and R-01–R-05 requirement. No silent skip.

On the final tracked state, run sequentially and exactly:

1. `vendor/bin/pint --test`
2. `vendor/bin/filacheck`
3. `npm run build`
4. full `php artisan test` last

After any file change, re-enter from Pint. Never parallelize or interrupt the
full suite. Do not run `vendor/bin/filacheck --fix`.

## Canonical completion and hard stop

After green gates:

1. commit implementation/tests/docs/handoff with pending hash as
   `fix: remediate authz1c audit findings`;
2. immediately stamp the implementation hash into the remediation handoff and
   ledger and commit only those Markdown files as
   `docs: backfill authz1c remediation hash`;
3. verify clean status and report both hashes; do not push.

Hard stop. Do not run AUTHZ operational commands, local dev DB, MySQL rehearsal,
production, dependencies, push, AUTHZ1-D–I, grants/cutover, `HasRoles`, UI/
policies, ARCH1, SP3D, or MAINT-LW-UX1 implementation.
