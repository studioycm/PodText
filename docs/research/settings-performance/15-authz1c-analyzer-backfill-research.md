# AUTHZ1-C Analyzer / Backfill Research

Date: 2026-07-16
Task: AUTHZ1-C-PLAN-01
Audit version: v1
Status: implementation and canonical final gate complete; commit pending
Authority boundary: legacy authorization remains authoritative after AUTHZ1-C

## 1. Accepted planning boundary

The operator/Fable accepted planning AUTHZ1-C after reviewing the completed
maintenance Livewire enforcement audit. This acceptance authorizes this
research, the paired implementation plan, one versioned implementation prompt,
restart-safe Markdown updates, and one docs-only commit. It does not authorize
application, test, config, migration, dependency, database, production, push,
AUTHZ1-D–I, ARCH1, or SP3D work.

AUTHZ1-C is a fail-closed projection of the existing `users.role` authority
into protected package role assignments. It is not an authority cutover. After
C, `users.role`, `UserRole`, rank comparisons, current Gates, panel admission,
Horizon admission, maintenance bypass, User Resource restrictions, and every
existing caller remain the only runtime authority.

## 2. Evidence and provenance

### 2.1 Repository evidence

- `app/Models/User.php` casts `role` to `UserRole`; its convenience checks can
  fall back to `UserRole::User`. An analyzer using this model could therefore
  hide corrupt source and fabricate a valid default. AUTHZ1-C must read
  `users.id` and `users.role` through the query builder.
- `app/Enums/UserRole.php` owns the only five byte-exact legacy values, in
  order: `super_admin`, `admin`, `moderator`, `transcriber`, `user`.
- `app/Auth/RoleCatalog.php` defines those same five protected `web` roles.
  `app/Auth/CompatibilityGrantManifest.php` is declarative only: Super has all
  135 catalog abilities, Admin has 89, and the other three roles have none.
- `app/Auth/AbilityCatalog.php` is frozen at version
  `AUTHZ1-2026-07-16` and SHA-256
  `fb46f5ef0228c2017e049b13a6f18eb72183a85b89249385828bf5295b9193c7`.
- The shipped foundation deliberately added neither `HasRoles` nor package
  assignments, grants, policies, sync, or management routes.
- The package migration uses `model_id` and `model_type`; with teams disabled,
  `model_has_roles` has no `team_id`. Its primary key is
  `(role_id, model_id, model_type)`.

### 2.2 Installed-source evidence

The exact installed package is `spatie/laravel-permission` 7.3.0.

- `Spatie\Permission\Traits\HasRoles::roles()` creates a polymorphic many-to-
  many relation, and `assignRole()` attaches through that relation. Nothing in
  the package schema requires the assigned model to use `HasRoles`.
- `Spatie\Permission\Models\Role::users()` derives the related model from the
  configured guard provider. This is unnecessary for C and is less explicit
  than an app-owned writer.
- Role and Permission model save/delete events use
  `RefreshesPermissionCache`. Creating package roles through the Role model
  would reset the permission cache before the surrounding application
  transaction commits.
- `Spatie\Permission\PermissionRegistrar::forgetCachedPermissions()` clears
  both the registrar's in-memory collection and the configured cache entry.
- `config/permission.php` has guard registration disabled, teams disabled,
  `model_morph_key = model_id`, `team_foreign_key = team_id`, a 24-hour cache,
  and cache key `podtext.permission.cache`.

Therefore C can and should populate the package tables without adding
`HasRoles`. The safest boundary is an app-owned query-builder writer that uses
the configured table/column names, resolves role IDs inside the transaction,
and writes only `roles` plus `model_has_roles`. It must never instantiate a
package Role/Permission model during mutation. Runtime role APIs remain absent.

### 2.3 Version-aware framework guidance

Laravel Boost reported PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire
4.3.3, Horizon 5.47.2, and Pest 4.7.4. Its installed-version documentation
confirmed that `DB::transaction()` rolls back on exceptions and accepts a
deadlock retry count, Artisan commands can return explicit failure codes, and
the local filesystem disk is rooted in private application storage. Relevant
framework references are:

- [Laravel database transactions](https://laravel.com/docs/13.x/database#database-transactions)
- [Laravel deadlock retries](https://laravel.com/docs/13.x/database#handling-deadlocks)
- [Laravel Artisan commands](https://laravel.com/docs/13.x/artisan)
- [Laravel filesystem](https://laravel.com/docs/13.x/filesystem)
- [Laravel database testing](https://laravel.com/docs/13.x/database-testing)

### 2.4 Inference, explicitly separated

- SQLite accepts the same transaction and uniqueness contract but its
  `FOR UPDATE` behavior cannot prove MySQL row/gap locking, deadlock retry, or
  two-connection TOCTOU behavior.
- Permission 7.3.0 caches permission/role definitions, not the authoritative
  legacy role field. Even so, the accepted contract requires one explicit
  reset after a successful C commit that made database changes.
- No morph map is currently registered. The implementation must still use
  `(new User())->getMorphClass()` rather than freeze `App\Models\User`, so a
  later morph-map change is detected as source/target drift.

There is no blocking contradiction. The apparent tension between “no catalog
sync” and the need for assignment targets is resolved narrowly: C may create
only missing protected role rows from `RoleCatalog`; it may not create or
update permissions or `role_has_permissions` grants. The compatibility
manifest remains a projected parity oracle for later slices, not active
package authority.

## 3. Frozen raw-source grammar

The analyzer selects raw `users.id` and `users.role`, ordered by primary key,
without Eloquent, enum casts, accessors, defaults, trimming, case folding,
Unicode normalization, aliases, or fallback values.

A source role is valid only when its database value is a native string whose
bytes equal exactly one `UserRole::value`. All other representations are
invalid, including `NULL`, empty/whitespace strings, padded values, case
variants, invalid UTF-8, numeric/boolean values returned by a test adapter,
unknown strings, and duplicate source identities. A valid user maps to exactly
the same protected role slug and to no other role.

Analysis enumerates every source and target problem before deciding readiness.
No first-error exit is allowed. Invalid source never reaches an enum cast.

## 4. Target-state grammar and refusal conditions

The analyzer reads the configured Permission tables directly and validates:

1. role rows are a unique subset of the five exact protected `web` role names;
2. no role name has a wrong guard, case collision, duplicate, or unknown value;
3. the permissions table and `role_has_permissions` are empty, because catalog
   materialization and compatibility-grant application are outside C;
4. `model_has_permissions` is empty; any direct grant is a blocker;
5. existing `model_has_roles` rows are a subset of the one expected assignment
   per current user, with the exact resolved role, exact current user morph
   type, and no foreign, orphaned, duplicate, multiple, or extra assignment;
6. guard provider/model, configured table names, pivot keys, teams setting, and
   actual schema match the frozen report metadata; team mode or any unexpected
   team column is a blocker;
7. `RoleCatalog`, `AbilityCatalog`, `CompatibilityGrantManifest`, `UserRole`,
   guard, model type, and package/config hashes match the report contract.

Unknown/corrupt/duplicate/ambiguous source, guard drift, catalog drift,
premature permissions/grants, direct grants, foreign model assignments,
or schema/config drift produce a complete privacy-safe blocked report and
exactly zero authorization database writes and zero cache resets. The report
artifact itself is the one permitted analysis output.

## 5. Privacy-safe canonical evidence

### 5.1 Key derivation and identifiers

Derive a 32-byte reporting key from the decoded `APP_KEY` bytes with
`hash_hkdf('sha256', ..., 32, 'podtext:authz1-c:report:v1')`. Refuse malformed
or missing key material. Never log or serialize it. Store a non-secret key ID,
`sha256(derived_key)`, so APP_KEY rotation invalidates an old report.

Use domain-separated HMAC-SHA-256 values:

- `user_hash = HMAC(key, 'user\0' + canonical_typed_primary_key)`;
- `raw_role_hash = HMAC(key, 'raw-role\0' + raw_length + raw_bytes)`;
- source, target, and assignment fingerprints are HMACs over canonical,
  sorted, length-aware JSON vectors.

The report may expose a valid canonical role slug, issue code, counts, package
version, catalog hashes, and HMAC identifiers. It must never expose a user ID,
name, email, raw invalid role value, APP_KEY, cache data, or SQL bindings.

### 5.2 Report schema

Use schema `podtext.authz1c.analysis.v1`. Canonical JSON has sorted object keys,
stable array ordering, unescaped slashes/Unicode, and exception-on-error. It
contains:

- generation metadata and evidence versions/hashes;
- exact connection driver (not credentials), guard, model type, teams/schema
  contract, table/column names, and key ID;
- `ready`, `blocked`, or `already_applied` status;
- total and per-role source counts;
- target-before and target-planned semantic counts/fingerprints;
- one privacy-safe row per user with user/raw-role hashes, valid role or null,
  existing/planned assignment hashes, validity, and sorted issue codes;
- a complete sorted issue list and issue-code totals;
- projected access-parity matrix/hash and legacy-authority declaration;
- source, assignment, target-before, target-planned, and report fingerprints.

The `report_fingerprint` is SHA-256 over canonical report content excluding
that field. It is safe because sensitive identifiers are already HMACs. Apply
requires both the exact source fingerprint and report fingerprint typed as
options; a path alone is insufficient.

## 6. Artifact storage and retention

Use only the configured local disk under
`storage/app/private/authorization/authz1-c/` with `reports/`, `operations/`,
and `receipts/` children. The command accepts only a validated basename, never
an absolute path or traversal. Default names use UTC timestamp plus ULID.
Existing destinations are never overwritten. A repository-level filesystem
lock serializes artifact publication; write temporary content in the same
directory, flush it, atomically rename it, and use private file/directory
permissions where supported.

Artifacts have no web route and report paths are not placed in ordinary logs.
AUTHZ1-C performs no pruning. Retain reports, operation journals, and receipts
through AUTHZ1 final acceptance; any export, deletion, or changed retention is
a separately approved operation.

## 7. Analyzer/apply protocol

### 7.1 Analyze

`authz:roles:analyze` opens a consistent read transaction, reads raw source and
all package target tables without locks or model events, validates every row,
and publishes exactly one immutable report. It never writes database/cache
state. Exit 0 means `ready` or `already_applied`, 2 means a complete blocked
report, and 1 means an operational/artifact failure.

### 7.2 Controlled apply

`authz:roles:backfill` accepts the report path, exact accepted source/report
fingerprints, and literal confirmation. Before mutation it validates the
artifact and current static contract. Inside `DB::transaction(..., 3)` it:

1. locks complete users, roles, permissions, grant, direct-grant, and role-
   assignment scans in deterministic primary-key order, including empty-range
   gaps on MySQL;
2. recomputes raw source and full target evidence under those locks;
3. refuses unless source exactly equals the accepted report and target equals
   either `target_before` or the complete `target_planned` crash-recovery state;
4. publishes a `prepared` operation journal before the first database write;
5. inserts only missing exact protected `web` roles through the query builder;
6. resolves their IDs and inserts only missing exact user-role pivot rows;
7. re-analyzes and requires exact planned counts/fingerprints and one role per
   user before allowing commit.

No upsert, insert-ignore, destructive repair, delete, partial acceptance, or
automatic baseline `user` role is allowed. A constraint race/deadlock is
retried only through the three transaction attempts; every retry recomputes
state. Any final mismatch throws and rolls back the whole transaction.

The production driver is MySQL. Apply records and requires MySQL
`REPEATABLE READ` or `SERIALIZABLE` isolation for the full locked scans; it
refuses a weaker or unknown isolation. SQLite is allowed only by the test
contract. Other drivers are unsupported in C. The later two-connection MySQL
rehearsal must confirm the actual empty-table/gap-lock behavior before any
production approval.

After a successful commit that changed database rows, and only then, call
`PermissionRegistrar::forgetCachedPermissions()` once. After that succeeds,
atomically write the receipt and mark the operation complete. A normal
receipt-backed rerun is a database/cache/artifact no-op.

If the process crashes after commit but before cache reset/receipt, the
prepared journal plus an exact `target_planned` recomputation is the only
accepted recovery state: rerun performs no database writes, resets the cache
once, and completes the receipt. `target_before` resumes normally. Any partial
or different state refuses. If cache reset fails, report operational failure;
the committed database state and prepared journal permit the same recovery.

### 7.3 Rollback before cutover

`authz:roles:rollback` requires the exact successful receipt, its after-state
fingerprint, and a literal rollback confirmation. It locks and recomputes the
same state, then transactionally deletes only assignment rows recorded as
inserted by that receipt. It preserves pre-existing assignments and all role
rows, never touches permissions/grants/source roles, and writes a rollback
receipt after commit. Drift refuses with zero database writes. It does not
reset permission cache: C has not made package roles authoritative, role
definition rows remain, and the accepted cache rule forbids rollback resets.
Re-analysis can then prove the source and remaining target state before a
controlled reapply.

## 8. Reconciliation and parity

The report and receipt reconcile:

- source total equals the sum of exact five per-role totals;
- every valid user has exactly one semantic planned assignment;
- distinct user hashes equal source total;
- assignment hashes and target semantic fingerprints agree before/after;
- no direct permission, extra role, extra assignment, or baseline role exists;
- rerun returns exact no-op with unchanged counts/fingerprints;
- the five-role legacy access matrix remains byte-for-byte equivalent before
  and after C for Admin panel, Horizon, maintenance, Gates, User Resource, and
  direct callers;
- the projected package compatibility matrix equals the immutable manifest,
  while tests also prove it is not consulted by runtime authority.

## 9. Test and rehearsal boundary

Implementation tests use Pest, fixtures owned by the test file, a dedicated
SQLite `:memory:` database, and a test-owned private artifact directory. They
cover every raw grammar class, complete multi-error reports, privacy scanning,
target/schema/config/guard/model drift, zero-write/cache assertions,
transaction rollback, exact assignment, idempotency, source/report tampering,
crash recovery, cache timing/failure, rollback/reapply, and the five-role parity
matrix. SQLite tests must label row-lock/concurrency claims as unproved.

Before any future production backfill approval, run a separately approved,
disposable, production-shaped MySQL rehearsal with two connections. It must
prove concurrent source mutation, concurrent applies, role-name insertion
races, gap/row locks, deadlock retry, post-commit crash recovery, and rollback.
It must not use the local development database. That rehearsal and every
production report, backup, backfill, cache, or process action require their own
per-action operator approval.

## 10. Maintenance follow-up

`MAINT-LW-UX1` is a named, independent future mini-task for the medium stale-tab
maintenance UX issue and the focused missing regression coverage in report 14.
It must run before either (a) any later public Livewire navigation, polling,
lazy/deferred loading, streaming, or upload expansion, or (b) AUTHZ1 final
acceptance, whichever occurs first. It is not a prerequisite for AUTHZ1-C, is
not coupled to its implementation, and has no implementation prompt here.

## 11. Implementation evidence

AUTHZ1-C was implemented from the accepted v1 prompt without changing the
foundation schema, package configuration, catalog, `User`, `UserRole`, Gates,
policies, Filament UI, routes, translations, dependencies, or runtime authority.
The implementation lives under `app/Auth/LegacyRoleBackfill/` with three
discovered Artisan commands and the focused Pest contract at
`tests/Feature/AuthzLegacyRoleBackfillTest.php`.

The settled focused evidence is 26 tests / 298 assertions. The combined
foundation, package, legacy matrix, panel, maintenance, and AUTHZ1-C regression
is 150 tests / 3,794 assertions. It proves privacy-safe raw analysis, static and
target drift refusal, independently validated accepted reports, exact
query-builder projection, transaction rollback and retry recomputation,
post-commit cache failure recovery, immutable journal/receipt reconciliation,
true completed no-op, receipt-scoped rollback/reapply, and unchanged five-role
legacy authority.

The canonical gate passed Pint, FilaCheck with zero issues, the production asset
build, and the full suite at 711 tests / 8,828 assertions. The first sandboxed
full-suite attempt could not launch Chromium; the identical escalated command
passed and the final documented state was confirmed through the full sequence.

This is application-plane SQLite `:memory:` evidence only. The separately
approved disposable two-connection MySQL rehearsal remains required before any
production approval and must not be replaced by a development-database probe.
The detailed disposition and command record is in
`docs/phase-02/authz1c-analyzer-backfill-handoff.md`.
