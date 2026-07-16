# AUTHZ1-C Analyzer / Backfill Implementation Plan

Date: 2026-07-16
Contract: AUTHZ1-C implementation prompt v1
Controlling research: `15-authz1c-analyzer-backfill-research.md` audit v1
Status: planned and prompted; implementation not started
Boundary: protected package role projection only; legacy authority remains active

## 1. Mandatory preflight and hard stops

1. Read the repository session-start files and the v1 implementation prompt in
   full. Stop if its version line is not exactly v1.
2. Require the planning commit named by the operator, clean expected checkout,
   completed AUTHZ1 foundation/audit ancestry, and no AUTHZ1-C implementation.
3. Read this plan and the paired research note as controlling contracts. Read
   the exact current app/package/config/migration/test source again; installed
   source wins over remembered package behavior.
4. Run the prompt's sequential SQLite `:memory:` legacy authorization baseline.
   Stop before coding on a baseline failure or contract contradiction.
5. Do not add dependencies, use the development database, touch production,
   push, create a worktree, enable Shield, add `HasRoles`, or start AUTHZ1-D.

## 2. Scaffold commands

Run only after preflight, with no interaction:

```bash
php artisan make:command AuthzAnalyzeLegacyRolesCommand --command=authz:roles:analyze --no-interaction
php artisan make:command AuthzBackfillLegacyRolesCommand --command=authz:roles:backfill --no-interaction
php artisan make:command AuthzRollbackLegacyRolesCommand --command=authz:roles:rollback --no-interaction
php artisan make:test --pest AuthzLegacyRoleBackfillTest --no-interaction
```

Do not generate a model, migration, policy, seeder, Resource, or package setup
artifact. Create the focused app-owned support classes below directly.

Docs: [Artisan commands](https://laravel.com/docs/13.x/artisan),
[transactions](https://laravel.com/docs/13.x/database#database-transactions),
[filesystem](https://laravel.com/docs/13.x/filesystem), and
[database testing](https://laravel.com/docs/13.x/database-testing).

## 3. No schema, model-trait, or UI changes

- Migration: none. Use only the shipped Permission tables.
- `App\Models\User`: unchanged; do not add `HasRoles`, `roles()`, permission
  traits, casts, observers, accessors, or authority behavior.
- `App\Enums\UserRole`: unchanged and authoritative.
- Filament Resources, forms, tables, policies, Gates, panel providers, Horizon,
  middleware, maintenance, navigation, translations, and UI: unchanged.
- Package config and catalog/manifest definitions: unchanged.

## 4. Canonical evidence objects

Create under `app/Auth/LegacyRoleBackfill/`:

### `CanonicalJson.php`

Final utility with deterministic recursive object-key sorting and stable list
ordering supplied by callers. Encode with `JSON_THROW_ON_ERROR`,
`JSON_UNESCAPED_SLASHES`, and `JSON_UNESCAPED_UNICODE`. Preserve type and byte
length in raw-source vectors; never normalize source strings.

### `PrivacyHasher.php`

Final service that decodes Laravel `APP_KEY` (`base64:` or literal), derives
the 32-byte HKDF-SHA-256 key with info
`podtext:authz1-c:report:v1`, exposes only the key ID, and creates domain-
separated HMAC-SHA-256 user, raw-role, source, target, and assignment hashes.
Throw a dedicated exception for absent/malformed key material. No secret may
appear in exceptions, output, logs, or artifacts.

### `AnalysisIssue.php`, `AnalysisUser.php`, `AnalysisReport.php`

Final readonly DTOs with explicit `toArray()` methods. Issue codes are stable
lowercase snake-case constants. The report schema and fields are exactly those
in research sections 3–5. It owns `contentForFingerprint()` and computes the
plain SHA-256 report fingerprint only after every HMAC field is present.

### `BackfillReceipt.php` and `RollbackReceipt.php`

Final readonly DTOs. A backfill receipt records operation/report/source hashes,
before/planned/after target hashes, inserted protected role names, inserted
semantic assignment hashes, counts, cache reset completion, and timestamps.
It contains no raw identity. A rollback receipt records the accepted backfill
receipt, exact deleted assignment hashes, before/after hashes, and counts.

## 5. Immutable private artifact repository

Create `PrivateArtifactRepository.php`.

- Root: local disk path `authorization/authz1-c` beneath private storage.
- Children: `reports`, `operations`, `receipts`.
- Accepted names: basename matching
  `\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}\.json\z`; reject separators, `..`,
  absolute paths, symlinks, and existing destinations.
- Default: UTC `Ymd\THis\Z` plus ULID and type suffix.
- Serialize publication with one repository lock file, write/flush a temporary
  sibling, rename atomically, and apply directory 0700/file 0600 where
  supported. Never overwrite or prune.
- Load with size ceiling 10 MiB, JSON exception handling, schema validation,
  fingerprint recomputation, and constant-time comparison.
- Operation states are `prepared`, `cache_reset`, `complete`, or `rolled_back`.
  State transitions create immutable versioned journal records; never rewrite
  the accepted report or receipt.

Tests point the local disk at a test-owned temporary directory created before
helpers and removed after each test. No fixture uses application storage or the
development database.

## 6. Analyzer

Create `LegacyRoleBackfillAnalyzer.php` with injected database connection,
config, `PrivacyHasher`, and catalogs. Its public method returns an
`AnalysisReport` and performs no filesystem, cache, log, or database mutation.

Inside one non-locking `DB::transaction()`:

1. validate frozen static configuration/schema metadata;
2. query raw `users.id` and `users.role` through `DB::table('users')`, ordered
   by ID; never hydrate `User` or invoke `UserRole::from/tryFrom` before raw
   grammar validation;
3. scan configured roles, permissions, pivots, grants, and direct grants in
   deterministic semantic order;
4. accumulate every source/target/static issue without early return;
5. compute source totals, exact five per-role totals, privacy-safe per-user
   rows, semantic existing/planned assignment vectors, target fingerprints,
   immutable manifest parity matrix/hash, and status.

Refusal conditions and exact accepted target grammar are research sections 3–4.
Missing protected role rows and missing expected assignments are planned work,
not blockers. Any premature permission/grant row, direct grant, unknown role,
wrong guard/model type, extra/multiple/orphan assignment, config/schema/team
drift, or invalid source is a blocker.

Create `AnalysisReportValidator.php` to revalidate loaded artifacts independently
of the analyzer and compare every static hash/config/schema field before apply.

## 7. App-owned transactional writer

Create `LegacyRoleBackfillApplier.php`. It must not use package Role/Permission
models or `HasRoles`.

Inputs: validated report plus exact accepted source and report fingerprints.
Use `hash_equals` for fingerprint options. Reject blocked reports and changed
key/catalog/config/schema metadata before opening a mutation transaction.

Use `DB::transaction($callback, 3)`. In each attempt, in this order:

1. require driver `mysql` with transaction isolation `REPEATABLE READ` or
   `SERIALIZABLE`, except the explicitly detected `sqlite` test contract;
2. lock a complete users scan ordered by ID, including its insertion gap;
3. lock complete roles, permissions, role-grant, direct-grant, and role-
   assignment scans in deterministic primary-key order, including empty-table
   ranges; do not restrict locks to the expected five rows;
4. recompute a full report from those locked rows without starting a nested
   transaction;
5. accept only exact report source plus exact target-before, or prepared-journal
   crash recovery at exact target-planned; otherwise throw before writes;
6. atomically publish the immutable prepared journal before first DB mutation;
7. insert missing five-role definitions with query-builder `insert`, exact
   `name`, `guard_name = web`, and timestamps; never upsert/ignore/update;
8. resolve protected role IDs by exact name/guard and insert missing
   `model_has_roles` rows with configured role key, `model_id`, and
   `(new User())->getMorphClass()`; never assign a default/extra/direct grant;
9. recompute under lock and require exact planned semantic fingerprint,
   source total, per-role totals, one assignment per user, zero permissions,
   zero grants, and zero extras before returning from the callback.

An exception rolls back everything. Do not catch inside the callback except to
add non-sensitive context and rethrow. A deadlock/constraint retry repeats the
entire lock/recompute path. Do not use a manual transaction, upsert, or nested
cache operation.

After the transaction commits:

- if no DB row changed and a complete matching receipt exists, return no-op;
- if the prepared journal proves a committed target-planned recovery, perform
  no DB write and continue recovery;
- for a changed/recovery operation only, call the injected
  `Spatie\Permission\PermissionRegistrar::forgetCachedPermissions()` exactly
  once and require `true`;
- publish `cache_reset`, then the immutable receipt, then `complete` journal;
- if cache/artifact completion fails, return operational failure and leave the
  prepared evidence for an exact rerun; never fake rollback after DB commit.

## 8. Controlled rollback service

Create `LegacyRoleBackfillRollback.php`.

Validate an exact complete backfill receipt, exact after-state fingerprint,
source/static contract, and literal confirmation. In a three-attempt
transaction, lock/recompute in the same order and require the receipt's exact
after state. Delete by the explicit tuple set only those `model_has_roles` rows
whose semantic hashes appear in `inserted_assignments`. Preserve every role
row, pre-existing assignment, source role, permission/grant table, and direct
grant table. Require the computed rollback target before commit.

After commit, publish rollback receipt/journal state. Do not call permission
cache reset during rollback. A repeated exact rollback is a receipt-backed
no-op. Any drift, missing/extra tuple, or partial state refuses without DB
mutation.

## 9. Console commands

### `AuthzAnalyzeLegacyRolesCommand`

Signature:

```text
authz:roles:analyze {--report= : Optional private report basename}
```

Behavior: run analyzer, publish report, print only status, counts,
fingerprints, issue-code totals, and basename. Never print per-user rows or raw
source. Exit 0 for ready/already-applied, 2 for blocked, 1 operational failure.

### `AuthzBackfillLegacyRolesCommand`

Signature:

```text
authz:roles:backfill
{report : Private report basename}
{--accept-source= : Exact source fingerprint}
{--accept-report= : Exact report fingerprint}
{--confirm= : Must equal AUTHZ1-C}
```

No `--force`, repair, default-role, production, connection, or cache option.
Print privacy-safe result/fingerprints and receipt basename only. Exit 0 for
applied/recovered/no-op, 2 for contract/drift refusal, 1 for operational or
post-commit completion failure.

### `AuthzRollbackLegacyRolesCommand`

Signature:

```text
authz:roles:rollback
{receipt : Private receipt basename}
{--accept-after= : Exact successful after-state fingerprint}
{--confirm= : Must equal ROLLBACK-AUTHZ1-C}
```

No role deletion, repair, production, connection, or cache option. Same
privacy-safe output and exit-code convention.

All three commands are registered through Laravel 13 command discovery only.
They do not prompt interactively, dispatch jobs/events, send mail, or acquire
process/service controls.

## 10. Pest test contract

Create `tests/Feature/AuthzLegacyRoleBackfillTest.php`; use file-scoped Pest
fixtures/helpers and the repository's shared exact five-role dataset. Every
database test creates a dedicated SQLite `:memory:` connection, runs only the
users and published Permission schema it owns, and restores configuration in
`finally`. Add an explicit canary asserting the driver/database are
`sqlite`/`:memory:` before helper execution.

Test at minimum:

1. valid raw five-role rows produce deterministic totals, per-role counts,
   user/raw/assignment hashes, projected access hash, and ready report;
2. null/blank/whitespace/padded/case/unknown/invalid-UTF8/non-string adapter and
   duplicate identity fixtures report every issue without enum cast/default;
3. one run with multiple source/target faults proves complete issue collection,
   privacy scan, zero changed DB rows, zero cache calls, and only one report;
4. wrong guard, name collision, unknown/duplicate role, permission row,
   `role_has_permissions`, direct grant, foreign/orphan/multiple/extra pivot,
   model type, teams, column, provider, schema, catalog, and APP_KEY drift each
   refuse;
5. apply creates exactly the five missing protected roles as needed and exactly
   one legacy-equivalent pivot per user, never universal `user`, extra role,
   permission, grant, or direct grant;
6. missing roles mixed with valid pre-existing exact assignments remain
   deterministic; query-builder path emits no package model events;
7. tampered report, wrong source/report option, source drift, target drift,
   stale key ID, path traversal, symlink, overwrite, malformed/oversized JSON,
   and concurrent artifact name refuse;
8. induced insert/final-validation exception rolls back roles and pivots and
   never resets cache; deadlock retry recomputes rather than reusing state;
9. cache registrar spy proves zero calls for analysis/refusal/rollback and one
   call only after a changed transaction commits; cache false/exception leaves
   recoverable prepared journal;
10. prepared-before state resumes; prepared-planned state recovers with zero DB
    writes plus one cache reset/receipt; any partial state refuses;
11. normal second apply is exact DB/cache/artifact no-op with matching totals
    and fingerprints;
12. rollback deletes only receipt-inserted pivots, preserves pre-existing
    pivots/all role rows, is idempotent, and supports re-analysis/reapply;
13. all five legacy roles retain identical Admin panel, Author/Admin Tools,
    User Resource, `super-admin`, `multi-transcription`, Horizon, maintenance,
    and direct-caller results before/after apply and after rollback;
14. User still lacks `HasRoles` and package assignments remain dormant runtime
    data; no AUTHZ1-D policy/Gate/management behavior is introduced.

Use `Http::preventStrayRequests()` in every HTTP-touching test and `Mail::fake()`
in every mail-touching test. Do not claim MySQL locking from SQLite. Record the
separately approved disposable two-connection MySQL rehearsal as a future
production gate, not an implementation test or local-dev probe.

## 11. Requirements, documentation, and final gate

Before gates, update the research note with implementation evidence and create
`docs/phase-02/authz1c-analyzer-backfill-handoff.md`. The committed handoff must
classify every requirement as Implemented, Already existed, Deferred, Not
applicable, or Blocked; list files/tests; record every command including
failures; distinguish SQLite evidence from future MySQL rehearsal; describe
rollback/crash/cache limits; and include numbered imperative Local Front Check
steps. Update only the minimum current-state, ledger, prompt index, and decision
queue rows. Do not mark authority cutover or AUTHZ1-D started.

Run the final sequence exactly:

1. requirements sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` last.

After any tracked file change, re-enter from Pint. Never parallelize or
interrupt the full suite. Never run `filacheck --fix`.

## 12. Canonical completion and hard stop

After all gates are green on final code:

1. commit application/tests/docs/handoff with `## Commit hash` pending using
   `feat: add authz legacy role backfill`;
2. immediately stamp that implementation hash into handoff and ledger in a
   docs-only commit `docs: backfill authz1c hash`;
3. verify clean status and do not push.

Stop completely after AUTHZ1-C. Do not add `HasRoles`, apply compatibility
grants, cut over policies/Gates, enable package/Shield management, authorize a
production action, or begin AUTHZ1-D, ARCH1, SP3D, or `MAINT-LW-UX1`.
