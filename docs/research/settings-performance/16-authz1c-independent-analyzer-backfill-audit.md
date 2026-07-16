# AUTHZ1-C Independent Analyzer / Backfill Audit

Audit ID: `AUTHZ1-C-AUDIT-01`

Date: 2026-07-17

Audited HEAD: `f3cb779a7f12d48ac583cd12eeb5718a88f4ab95`

Implementation: `0147ea83947ccedb1336a71d8eecc887eb8d4e07`

Hash closeout: `628429236c138ad51fcb4b6d8311ad4726afb439`

Decision: **a separate remediation prompt is required before any AUTHZ1-D
planning**

Authority boundary: legacy `users.role`, `UserRole`, ranks, Gates, panel,
Horizon, maintenance, User Resource, and existing callers remain authoritative.

## 1. Executive conclusion

AUTHZ1-C has a strong fail-closed analyzer and a narrow query-builder writer.
The independent audit supports the implementation's byte-exact raw-role
grammar, complete ordinary issue enumeration, zero package/cache mutation on
analyzer or pre-commit refusal paths, exact same-slug five-role projection,
private immutable artifact publication, independent report reconciliation,
transaction rollback/retry control flow, dormant package authority, privacy-
safe command output, and five-role legacy runtime parity on SQLite `:memory:`.

Local acceptance is nevertheless **not supported yet**. One High integrity
finding and four Medium correctness/recovery findings contradict primary C
claims:

1. a receipt does not prove ownership of the physical role/pivot tuples it may
   later delete;
2. an absent cache entry can make the post-commit completion loop fail forever;
3. a successful cache reset and its journal are not atomic, so strict exactly-
   once reset semantics are not achieved;
4. rollback has an unrecoverable commit-to-receipt crash window; and
5. runtime schema drift checks cover column names only, not the constraints and
   indexes on which uniqueness and locking depend.

One Low privacy/configuration finding and one Low assurance finding are also
recorded. These are not AUTHZ1-D work. They require a separately reviewed C
remediation contract; this audit does not provide an implementation prompt.

The focused test file passed 26 tests / 298 assertions. The minimum adjacent
authorization suite passed 124 tests / 3,496 assertions. These green results
are valid for the paths they execute, but they do not exercise the findings
below. No MySQL command or persistent-database probe was run, and this audit
makes no MySQL row-lock, gap-lock, isolation, deadlock, or two-connection claim.

## 2. Scope and evidence discipline

### 2.1 Repository evidence

The audit read the v1 prompt, accepted research/plan, implementation handoff,
foundation research/handoff, maintenance audit, current state, mini-step
ledger, queue, every AUTHZ1-C application/command/test file, the frozen catalog
and role sources, Permission configuration/migration, and the relevant legacy
runtime sources.

The checkout matched the requested baseline:

- clean `main` at exact `f3cb779a7f12d48ac583cd12eeb5718a88f4ab95`;
- implementation `0147ea83947ccedb1336a71d8eecc887eb8d4e07` and closeout
  `628429236c138ad51fcb4b6d8311ad4726afb439` ancestral;
- foundation and audit ancestry present;
- no AUTHZ1-D–I implementation commit or application surface found;
- frozen catalog/config/migration sources unchanged from the foundation; and
- no `HasRoles`, package grant writer, policy/Gate cutover, Shield management,
  or direct-permission writer in application code.

### 2.2 Installed-source and Boost evidence

Laravel Boost reported PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire
4.3.3, Horizon 5.47.2, Pest 4.7.4, and the repository's installed package set.
Version-aware guidance confirmed Laravel transaction retries and explicit
Artisan exit codes. Exact installed source confirmed Permission 7.3.0.

Material installed-source facts:

- `PermissionRegistrar::forgetCachedPermissions()` clears in-memory state and
  returns the configured cache store's `forget()` boolean
  (`vendor/spatie/laravel-permission/src/PermissionRegistrar.php:128-133`).
- Laravel Array, File, and Redis stores return `false` when the requested key is
  absent (`ArrayStore.php:197-205`, `FileStore.php:325-335`, and
  `RedisStore.php:286-288`).
- package Role/Permission model events reset the permission cache through
  `RefreshesPermissionCache`; AUTHZ1-C correctly avoids those models.
- Laravel's transaction wrapper retries concurrency exceptions by rerunning the
  callback and commits before returning its callback result
  (`ManagesTransactions.php:26-75`).

### 2.3 Executed SQLite evidence

The following commands ran sequentially under the repository canaries that
require `APP_ENV=testing`, SQLite, and database `:memory:`:

| Command | Result |
| --- | --- |
| `php artisan test --compact tests/Feature/AuthzLegacyRoleBackfillTest.php` | PASS — 26 tests / 298 assertions |
| `php artisan test --compact tests/Feature/AuthzFoundationCatalogTest.php tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php` | PASS — 124 tests / 3,496 assertions |

The focused test's file-level setup calls `Http::preventStrayRequests()` and
`Mail::fake()`, asserts SQLite `:memory:`, and owns its private artifact root.
No live HTTP, mail, development database, or production resource was used.

### 2.4 Deferred evidence

The separately approved disposable two-connection MySQL rehearsal remains a
future production gate. SQLite and source inspection do not prove:

- `FOR UPDATE` coverage of empty insertion ranges;
- actual InnoDB row/next-key/gap locks on every scan/index;
- concurrent role insertion or source mutation blocking;
- real deadlock victim/retry behavior across two connections;
- current transaction isolation under production connection topology; or
- production cache-store and filesystem failure behavior.

## 3. Findings

### H-01 — Rollback ownership is semantic, not receipt-physical

Severity: **High integrity / destructive rollback safety**

Requirement affected: receipt-scoped rollback must delete only assignments
inserted by that receipt and must refuse role-ID drift, receipt substitution,
or an externally produced exact target.

Evidence:

- `LegacyRoleBackfillApplier.php:85-94` accepts any exact planned state when a
  prepared journal exists and classifies it as recovery; it cannot prove that
  the committed tuples were written by its transaction rather than by another
  actor after a prepared-before rollback/crash.
- `LegacyRoleBackfillApplier.php:234-270` derives receipt ownership from the
  semantic report diff before mutation. `loadPreparedJournal()` at lines
  274-289 verifies report/source/target fingerprints but does not recompute the
  inserted role/assignment set from the accepted report.
- operation journals use an unkeyed recomputable SHA-256 only
  (`LegacyRoleBackfillApplier.php:344-367`). Private permissions reduce the
  attacker set, but a same-service-account writer can replace a journal and
  recompute that checksum.
- analyzer target vectors contain canonical role slugs and semantic assignment
  hashes, not role row IDs (`LegacyRoleBackfillAnalyzer.php:438-442`,
  `486-513`). Delete/recreate of a role plus exact pivot recreation therefore
  preserves the reported target fingerprint.
- rollback resolves the **current** role ID by slug and deletes that current
  tuple (`LegacyRoleBackfillRollback.php:126-169`).

Failure/exploit path:

1. C publishes `prepared`, then its transaction rolls back or the process dies
   before owning the target rows.
2. Another authorized process produces the exact five-role planned state, or a
   protected role/pivot is later deleted and recreated with a different role
   ID but identical semantic state.
3. C recovery accepts the semantic planned state and emits a receipt claiming
   the report diff as inserted by C.
4. Pre-cutover rollback resolves current rows and deletes them even though the
   receipt cannot prove it inserted those physical tuples.

Disposition: **confirmed source finding; not covered by the focused tests**.
The existing mixed-preexisting rollback test proves ordinary semantic
preservation only; it does not replace role IDs, substitute an exact external
projection, or tamper with inserted-set journal fields.

### M-01 — An absent permission-cache key can trap completion forever

Severity: **Medium operational/recovery correctness**

Requirement affected: a changed committed backfill must complete its one
post-commit cache reset and publish a recoverable receipt.

Evidence:

- after the transaction commits, the applier treats a `false` return from
  `forgetCachedPermissions()` as failure
  (`LegacyRoleBackfillApplier.php:125-139`).
- installed Permission 7.3.0 simply returns the cache store's `forget()` result.
- installed Array/File/Redis stores return `false` when the key is already
  absent. AUTHZ1-C deliberately keeps package authority dormant, so an absent
  permission cache before first operational use is a normal state, not proof
  of failed invalidation.
- the focused test at `AuthzLegacyRoleBackfillTest.php:529-543` mocks `false`
  once and `true` on a later invocation; it does not exercise a real store
  whose absent key continues returning `false`.

Failure path:

1. the DB projection commits successfully while the permission cache key is
   absent;
2. cache `forget()` returns `false`; no cache-reset journal or receipt is
   published;
3. every rerun sees exact planned state, calls the same absent-key `forget()`,
   receives `false`, and fails again.

The database is changed but completion is not recoverable under the claimed
protocol. Legacy authority limits immediate runtime impact, but no C receipt
exists and later planning cannot safely rely on completion.

Disposition: **confirmed installed-source finding**.

### M-02 — Successful cache reset and durable evidence have an exactly-once gap

Severity: **Medium crash-consistency correctness**

Requirement affected: cache reset must occur exactly once after changed commit
or exact recovery.

Evidence: the cache call occurs at `LegacyRoleBackfillApplier.php:131`; only
after it returns does the code publish `cache_reset` at lines 135-139. These are
different systems and no durable pending/completion token atomically spans
them.

Failure path:

1. cache deletion succeeds;
2. the process crashes, filesystem publication fails, or the host is lost
   before `cache_reset` is durably published;
3. rerun finds only `prepared` plus exact planned DB state and calls cache reset
   again.

Cache deletion is operationally idempotent, but the implementation cannot
truthfully claim one invocation or one externally observable reset. The
existing cache false/exception tests fail **inside** the call; they do not
exercise the success-to-journal crash window.

Disposition: **confirmed source finding**. A remediation decision must either
provide a store-specific atomic/idempotent protocol or explicitly replace the
strict exactly-once claim with durable at-least-once idempotent invalidation.

### M-03 — Rollback commit can become permanently unrecoverable

Severity: **Medium crash-consistency / idempotency correctness**

Requirement affected: rollback must be receipt-scoped, idempotent, and safely
rerunnable.

Evidence:

- rollback deletes and commits inside the transaction at
  `LegacyRoleBackfillRollback.php:73-90`;
- the rollback receipt and journal are first published after commit at lines
  92-112; there is no pre-delete rollback journal;
- without a rollback receipt, a rerun requires current target to equal the
  original backfill after-state at lines 77-82. The already-rolled-back state
  therefore refuses.

Failure path: process loss after DB commit and before rollback receipt
publication leaves the assignments deleted but no artifact that permits the
idempotent branch. An exact rerun refuses rather than recovering or proving the
rollback target.

Disposition: **confirmed source finding; not covered by tests**. The current
idempotency test begins only after receipt publication succeeds.

### M-04 — Runtime schema validation omits required constraints and indexes

Severity: **Medium fail-closed / concurrency-assumption correctness**

Requirement affected: schema drift must block before mutation, and the
transaction/locking protocol must rely on a verified schema.

Evidence:

- `LegacyRoleBackfillAnalyzer::staticContract()` records only sorted column
  names from `Schema::getColumnListing()`
  (`LegacyRoleBackfillAnalyzer.php:58-94`).
- validation compares only expected column-name lists and configured key names
  (`LegacyRoleBackfillAnalyzer.php:280-303`).
- the package migration's primary keys, unique `(name, guard_name)` indexes,
  model lookup indexes, foreign keys, cascade behavior, column types,
  nullability, and auto-increment properties are absent from the report's
  schema hash.
- foundation tests prove the checked-in migration on isolated SQLite, but an
  operational analyzer does not revalidate those properties on its target.

Failure path: an otherwise column-identical target with a dropped unique/pivot
primary index or foreign key is accepted. That changes duplicate protection,
role/pivot identity, cascade behavior, and potentially the indexes used by the
locking scans. Post-write semantic checks catch some realized duplicates, but
they do not make the initial schema contract exact or prove the expected
concurrent locking behavior.

Disposition: **confirmed source finding**. The MySQL rehearsal cannot replace
runtime schema validation; both are required for their separate evidence
planes.

### L-01 — Malformed or low-entropy APP_KEY material is accepted

Severity: **Low conditional privacy/configuration**

Requirement affected: malformed reporting key material must refuse.

Evidence: `PrivacyHasher.php:11-35` rejects missing/empty material and invalid
base64 syntax, but accepts any nonempty decoded length and any nonempty literal
string. It does not validate the decoded key against Laravel's configured
cipher key length.

Failure path: a syntactically valid but one-byte base64 key, or another invalid
short literal, produces a valid 32-byte HKDF output. Reports then use HMACs
whose effective secrecy is bounded by the weak source material rather than
failing closed.

Disposition: **conditional source finding**. The current tracked repository
does not expose or prove a weak deployed APP_KEY, and no secret was read.

### L-02 — The focused test claim is broader than its direct matrix

Severity: **Low assurance/documentation debt**

Requirement affected: exact test evidence and honest classification.

The 26-test file is green and substantial, but direct cases are absent for:

- role-ID replacement/recreation and external exact planned-state adoption;
- tampered prepared `inserted_roles` / `inserted_assignments` with a recomputed
  journal checksum;
- a real configured cache store with an absent permission key;
- process loss after successful cache reset but before cache journal;
- process loss after rollback commit but before rollback receipt;
- same-column schema with missing/changed indexes or foreign keys;
- malformed but nonempty short APP_KEY material;
- configured table-name drift, morph-map drift, and several nested artifact
  type-confusion cases; and
- empty-source and report-size boundary behavior.

The original handoff says every listed drift and crash/cache behavior is
proven. The executed test counts should remain recorded, but their evidence
must not be extended to these unexecuted planes.

Disposition: **confirmed assurance gap**.

## 4. Requirements verdict

| Objective | Verdict | Evidence boundary |
| --- | --- | --- |
| Byte-exact raw-role fail-closed behavior | Supported locally | Query builder reads, native-string exact enum values, invalid raw role tests; exotic test-adapter identity/type cases remain L-02. |
| Complete ordinary issue enumeration | Supported locally | Multi-source/target report tests and source scan; schema-contract completeness fails M-04. |
| Zero auth writes/cache resets on invalid/refused state | Supported for tested pre-commit paths | Analyzer has no cache/writer dependency; transaction exceptions roll back; no production probe. |
| Exactly one same-slug role per valid user, no defaults/extras/grants | Supported for ordinary apply | Query-builder inserts and focused assertions pass. |
| Independent report/artifact validation and privacy | Mostly supported | Report reconciliation, HMAC IDs, private non-overwrite/symlink checks pass; L-01 and journal ownership portion of H-01 remain. |
| Deterministic source/target fingerprints | Supported semantically | Stable source/target tests pass; physical role identity is intentionally omitted and becomes H-01 for rollback ownership. |
| Transaction/lock/retry/TOCTOU | Partially supported | SQLite control flow and retry recomputation pass; M-04 remains; MySQL lock behavior is deferred. |
| Prepared/cache/complete recovery | Not accepted | M-01 and M-02. |
| Exactly-once post-commit cache semantics | Not satisfied | M-02; M-01 can also prevent any durable completion. |
| Receipt-scoped rollback/idempotency/reapply | Not accepted | H-01 and M-03; ordinary focused path passes. |
| Legacy runtime parity / no `HasRoles` or cutover | Supported | Source absence checks and 124-test adjacent suite pass. |
| Command output/exit-code safety | Supported locally | Known refusals use static messages/exit 2; unexpected throwables use generic exit-1 output; no sensitive row output found. |
| Honest SQLite/MySQL boundary | Supported in this audit | No MySQL proof is claimed or executed. |

## 5. Separate remediation plan

This is a remediation specification, not an implementation prompt.

### R-01 — Bind receipts to physical tuple ownership

1. Recompute and exact-compare the entire prepared journal from the accepted
   report, including operation ID, inserted roles, inserted assignments, and
   all field types/order; do not merely compare its report fingerprints.
2. Record and validate the concrete protected role IDs used by the committed
   transaction and the exact physical pivot tuple identity needed for rollback
   while keeping raw user IDs out of artifacts (for example, keyed tuple
   evidence plus role ID and HMAC user identity).
3. Refuse recovery if exact planned state exists but physical ownership cannot
   be proven. If prepared-before and externally-completed states are
   indistinguishable, do not issue a rollback-capable ownership receipt.
4. Add role-ID delete/recreate, external exact projection, prepared-field
   substitution, receipt substitution, and pre-existing tuple preservation
   tests.

### R-02 — Define a truthful cache completion protocol

1. Wrap Permission cache invalidation in an app-owned result that distinguishes
   `deleted`, `already_absent`, and actual store error; treat confirmed absence
   as a successful invalidated state.
2. Test the real configured cache repository semantics with an absent key and
   repeated recovery, not only a mock that changes `false` to `true`.
3. Resolve the cache-call-to-journal crash window explicitly. Either implement
   a store-specific atomic/idempotency token or amend the contract to durable
   at-least-once idempotent invalidation. Do not retain an unprovable strict
   exactly-once statement.
4. Add success-then-publication-failure and repeated absent-key recovery tests.

### R-03 — Add rollback prepared/recovery evidence

1. Publish and independently validate an immutable rollback-prepared journal
   before the first delete.
2. Define exact accepted rollback-before and rollback-planned states.
3. After a commit-to-receipt crash, accept only exact rollback-planned state,
   perform no additional DB delete, publish the rollback receipt/journal, and
   return recovered.
4. Keep partial/different state fail-closed and cache-reset-free.

### R-04 — Expand the operational schema contract

1. Normalize and fingerprint column types/nullability/auto-increment,
   primary/unique/secondary indexes, foreign keys, referenced columns, and
   delete behavior for every Permission table.
2. Include the `users` source table's required ID/role columns and primary-key
   properties.
3. Refuse same-column schemas missing any required constraint or index.
4. Keep SQLite normalization distinct from MySQL evidence; exercise the final
   MySQL-normalized contract only in the separately approved disposable
   rehearsal.

### R-05 — Tighten key and DTO validation

1. Decode APP_KEY using Laravel-compatible semantics and require a key length
   supported by the configured cipher before HKDF.
2. Deep-validate every report, journal, backfill receipt, and rollback receipt
   field with exact scalar/list/object types and exact counts.
3. Add malformed nested values, numeric-string count substitution, invalid
   identity adapter types, empty source, and artifact/report size-boundary
   tests.

## 6. Decision and sequencing

Selected outcome: **2. A separate remediation prompt is required before any
AUTHZ1-D planning.**

The remediation prompt must be separately authored, reviewed, and approved.
This audit does not authorize or provide that prompt, application changes,
tests, migrations, dependencies, operational commands, or MySQL rehearsal.

`MAINT-LW-UX1` keeps its existing independent deadline: before the first later
public Livewire navigation/polling/lazy/deferred/stream/upload expansion or
AUTHZ1 final acceptance, whichever comes first. The AUTHZ1-C remediation does
not move it earlier and AUTHZ1-D remains hard-stopped.

## 7. Explicit safety confirmation

- No operational analyze/backfill/rollback command ran against any persistent
  database.
- No local development database or production system was queried or mutated.
- No MySQL rehearsal, cache reset, process action, dependency change, push,
  authority cutover, compatibility grant, `HasRoles`, policy/UI management,
  AUTHZ1-D–I, ARCH1, SP3D, or MAINT-LW-UX1 implementation occurred.
- No application, test, config, migration, package, translation, or frontend
  file was changed by this audit.
