# AUTHZ1-C Audit Remediation Research

Date: 2026-07-17
Task: `AUTHZ1-C-REMEDIATION-PLAN-01`
Research version: v1
Status: remediation planned and prompted; implementation not started
Authority boundary: legacy authorization remains authoritative

## 1. Scope and controlling finding set

This note converts independent audit items R-01 through R-05 into one closed
AUTHZ1-C remediation design. It does not enter AUTHZ1-D. The controlling audit
is `16-authz1c-independent-analyzer-backfill-audit.md`; the shipped C v1
research, plan, prompt, source, tests, and handoff remain historical evidence,
not permission to retain claims disproved by the audit.

Authorized implementation scope is limited to the current app-owned
`LegacyRoleBackfill` classes, the three existing commands, the focused C Pest
test, and restart-safe documentation. There is no migration, dependency,
configuration, role/catalog, `User`, Gate/policy, Filament, route, queue, mail,
development-database, production, or MySQL-rehearsal work.

## 2. Evidence and provenance

### 2.1 Repository evidence

- C v1 uses semantic role slugs and HMAC user identities in target
  fingerprints. Its prepared journal is protected only by recomputable
  SHA-256, and recovery accepts an exact planned state without proving which
  transaction created the physical role/pivot tuples.
- C v1 rollback resolves the current role ID by slug. A delete/recreate can
  therefore make a receipt delete a tuple with the same semantics but different
  physical identity.
- C v1 calls `PermissionRegistrar::forgetCachedPermissions()` and treats
  `false` as failure. The call precedes its durable cache-reset journal.
- C v1 rollback has no journal before its first delete.
- C v1 runtime schema evidence is sorted column names only.
- `config/app.php` uses `AES-256-CBC`. The users source is an unsigned
  auto-increment primary `id` plus indexed, non-null `varchar(32)` `role` with
  default `user`. The checked-in Permission migration defines every required
  column, primary/unique/secondary index, foreign key, reference, and cascade.
- The implementation test is green at 26 tests / 298 assertions, but the audit
  correctly identifies unexecuted ownership, cache, rollback-crash, schema,
  key-length, nested type-confusion, and boundary cases.

### 2.2 Installed-source evidence

- Installed Permission 7.3.0 clears registrar memory and returns its cache
  repository's `forget()` boolean.
- Installed Laravel Array, File, and Redis stores return `false` when a cache
  key is absent. Absence is therefore a valid invalidated state, not an error.
- Laravel 13.19.0 exposes `Schema::getColumns()`, `getIndexes()`, and
  `getForeignKeys()` with normalized column, index, reference, and delete-action
  metadata for MySQL and SQLite.
- `Illuminate\Encryption\Encrypter::supported()` is the installed source of
  truth for supported cipher/key-length pairs. It accepts AES-128/256 CBC/GCM
  with 16/32-byte keys as applicable.
- Laravel's transaction wrapper returns only after commit and retries the full
  callback for concurrency exceptions. Filesystem publication and cache
  invalidation cannot be made atomic with that database commit by current
  installed primitives.

### 2.3 Boost and official guidance

Boost confirmed PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3,
Horizon 5.47.2, and Pest 4.7.4. Installed-version guidance supports Laravel
transaction boundaries, APP_KEY/cipher length requirements, and Pest datasets
for boundary/type matrices. Installed source controls where general docs do
not define store-specific `forget()` truth.

### 2.4 Inference

- Strict exactly-once cache-call execution is impossible across the successful
  cache-call/durable-journal boundary without a shared atomic store or protocol
  not present here. Cache deletion is idempotent, so durable at-least-once
  invalidation is the truthful portable contract.
- A restart that sees prepared evidence plus an exact planned database state
  cannot prove whether C's transaction or another writer created the tuples.
  It may complete only as ownership-unproven and non-rollback-capable.
- The uninterrupted process can prove that its transaction returned after
  commit and can bind the actual role IDs and physical pivots returned by that
  callback into a keyed receipt. Losing the process before receipt publication
  intentionally loses rollback capability rather than fabricating ownership.

## 3. Frozen artifact version and integrity policy

The remediation introduces v2 schemas:

- `podtext.authz1c.analysis.v2`;
- `podtext.authz1c.operation.v2`;
- `podtext.authz1c.backfill-receipt.v2`;
- `podtext.authz1c.rollback-operation.v2`; and
- `podtext.authz1c.rollback-receipt.v2`.

Every v1 artifact is retained immutably but is non-executable. Loading a v1
report for backfill or a v1 receipt for rollback returns a contract refusal
(exit 2) stating that a fresh v2 analysis/apply is required. No in-place
upgrade, adoption, overwrite, deletion, or rollback from v1 is allowed. This is
safe because the recorded C implementation ran no operational persistent-
database command and made no production receipt claim.

Analysis keeps a plain SHA-256 report fingerprint after sensitive identity
fields have already been HMACed. Every v2 operation journal and receipt uses a
domain-separated HMAC-SHA-256 artifact MAC from `PrivacyHasher`; a same-file
recomputed plain checksum is no longer sufficient. Exact schema, exact key
set, exact scalar/list/object type, finite enum value, lowercase 64-hex digest,
count/vector reconciliation, deterministic ordering, and timestamp grammar are
validated before any field is consumed.

## 4. R-01: physical ownership and exact prepared evidence

### 4.1 Prepared journal

The v2 prepared journal is derived only from the independently validated v2
report. It contains the deterministic operation ID, report/source/before/
planned fingerprints, exact semantic planned-insert role list, exact semantic
planned-insert assignment list, preparation timestamp, and keyed artifact MAC.
On load, the implementation recomputes the whole expected payload from the
accepted report, reuses only the validated stored timestamp, and exact-compares
canonical bytes. Field substitution, reordered lists, extra fields, numeric
strings, or a recomputed unkeyed digest refuse.

### 4.2 Rollback-capable normal completion

Inside the successful transaction, the writer captures:

- every inserted role as exact canonical slug plus actual role ID;
- every inserted pivot as semantic assignment hash, HMAC user identity,
  canonical slug, actual role ID, exact model type, and a domain-separated
  `physical_tuple_hash` over the actual role ID plus typed raw model ID and
  model type; and
- the exact resolved protected role slug-to-ID map used by all assignments.

Raw model IDs never enter artifacts. After `DB::transaction()` returns, the
same uninterrupted call may publish a receipt with
`ownership_status=proven` and `rollback_capable=true`. Its keyed MAC binds the
physical evidence, semantic deltas, counts, cache outcome, and fingerprints.
Rollback requires the recorded role ID still to own the same exact `web` slug,
resolves current users by HMAC, recomputes the physical tuple hash, and deletes
only that exact tuple. Role-ID recreation, receipt substitution, or any
physical mismatch refuses before a delete.

### 4.3 Restarted exact-planned completion

If a rerun finds a valid prepared journal and exact planned state without a v2
receipt, ownership is indistinguishable. It performs no database write and must
not copy the planned semantic diff into an owned set. After truthful cache
completion it publishes `ownership_status=unproven`,
`rollback_capable=false`, empty owned role/pivot vectors, and status
`completed_unowned`. The semantic planned delta remains separately recorded as
non-ownership evidence. The command exits 0 but prints
`rollback_capable: no`; `authz:roles:rollback` refuses this receipt with exit 2.
Partial/different state still refuses.

An initially `already_applied` v2 report with no prepared journal remains a
true no-op without a receipt or cache call.

## 5. R-02: truthful cache protocol

Add an app-owned `PermissionCacheInvalidator` and
`PermissionCacheInvalidationOutcome` (`deleted`, `already_absent`). Resolve the
same configured cache repository/key as Permission 7.3.0. For a changed or
restarted exact-planned completion:

1. publish keyed `cache_invalidation_pending` evidence;
2. inspect key presence, call `forgetCachedPermissions()` so registrar memory
   is also cleared, and inspect presence again;
3. return `deleted` when a present key is gone, or `already_absent` when the key
   is confirmed absent even if `forget()` returned false;
4. treat an exception, an unreadable store, or a key still present as an
   operational/store error; and
5. publish keyed `cache_invalidated` evidence with the truthful outcome.

If the process dies after successful invalidation but before durable success
evidence, rerun invalidates again. The contract is explicitly **durable
at-least-once idempotent invalidation**, not exactly-once invocation. A complete
receipt-backed rerun performs no cache call. A store error leaves pending
evidence and exact rerun recovery available.

## 6. R-03: rollback prepared and recovery protocol

A rollback-capable v2 backfill receipt deterministically defines a rollback
before state and rollback planned state (all roles preserved, only its proven
owned pivots removed). Before the first delete, publish a keyed immutable
rollback-prepared journal containing the backfill receipt fingerprint, exact
physical tuple set, source/before/planned fingerprints, counts, and timestamp.

Within the three-attempt locked transaction:

- exact rollback-before state deletes the receipt's physical tuples and must
  recompute exact rollback-planned state before commit;
- exact rollback-planned state plus the valid journal is recovery: no delete is
  repeated; and
- any partial/different state refuses.

After commit or recovery, publish the v2 rollback receipt and complete journal.
A crash after commit but before either artifact is recovered on rerun from the
prepared journal and exact planned state. The receipt records
`completion=deleted` or `completion=recovered_planned`. Rollback never resets
permission cache.

## 7. R-04: runtime schema contract

Add `LegacyRoleBackfillSchemaContract` with explicit driver-specific expected
logical descriptors sourced from the checked-in migrations. At runtime,
normalize Laravel `getColumns()`, `getIndexes()`, and `getForeignKeys()` output
for the five Permission tables and the required users source surface.

The fingerprint and report include, in deterministic order:

- columns: name, normalized full type/length/unsigned family, nullable, default
  where contract-relevant, and auto-increment;
- indexes: primary/unique/secondary kind, ordered columns, and index type where
  exposed;
- foreign keys: local columns, referenced table/columns, and normalized update/
  delete action; and
- users: `id` and `role` descriptors, `id` primary-key/auto-increment contract,
  and the role secondary index.

For Permission tables the expected primary keys, unique `(name, guard_name)`
indexes, model lookup indexes, pivot foreign keys, references, and cascade
delete actions must all match. Constraint names are recorded when stable but
semantic identity is comparison-authoritative so SQLite's absent/generated FK
names do not create false drift. SQLite and MySQL expected descriptors are
separate; other drivers remain unsupported. Same-column missing/changed PK,
unique/secondary index, FK, reference, delete action, type, nullability, or
auto-increment produces complete schema issue evidence and blocks mutation.

The future disposable two-connection MySQL rehearsal must exercise the MySQL-
normalized descriptor and actual lock behavior. It remains separate because
runtime schema validation does not prove concurrency, and rehearsal does not
replace runtime validation.

## 8. R-05: key, DTO, and boundary validation

`PrivacyHasher` parses `base64:` with the same non-strict `base64_decode`
semantics as the installed `EncryptionServiceProvider::parseKey()` or uses
literal bytes, reads `config('app.cipher')`, requires the parsed key to be a
string, and requires `Encrypter::supported($decodedBytes, $cipher)` before
HKDF. This deliberately preserves Laravel parsing compatibility rather than
inventing stricter key bytes. Missing, unsupported-cipher, malformed values
that do not yield a supported key, empty, short, and long keys refuse without
revealing key material.

DTO validation is deep rather than top-level-only. It validates every report
user/issue/target/access row, schema descriptor, journal transition, physical
assignment, cache outcome, receipt count, boolean, digest, timestamp, enum,
and ordered list. Integers must be native non-negative integers; numeric strings
and floats refuse. Empty source is valid when schema/target evidence is valid:
it plans the five protected roles and zero pivots. Raw source adapter identity
accepts only native integer or string IDs; arrays, objects, floats, booleans,
and null are privacy-safe blocker rows and cannot collide silently in the
internal map.

The 10 MiB ceiling remains inclusive for read content and exclusive after the
publication newline is accounted for. Tests cover an artifact at the accepted
boundary reaching JSON/shape validation and one byte over refusing at the size
gate.

## 9. Required evidence matrix

The remediation test expands the focused Pest file, using named datasets where
useful, to cover:

1. exact prepared recomputation, HMAC substitution refusal, receipt
   substitution, role-ID delete/recreate, external exact projection, and
   preservation of pre-existing physical pivots;
2. proven normal receipt versus `completed_unowned`, non-rollback receipt
   refusal, and unchanged true no-op;
3. real configured Array-store present/absent outcomes, persistent absence,
   store false-with-key-present/error, success-before-journal failure, and
   at-least-once recovery;
4. rollback prepared-before, commit-to-receipt recovery with zero repeated
   delete, idempotent completion, and partial-state refusal;
5. each missing/changed column property, PK, unique index, secondary index,
   FK, reference, and delete action on SQLite, plus exact users source schema;
6. AES-128/256 CBC/GCM supported lengths and malformed/short/long/unsupported
   key refusal;
7. nested report/journal/receipt type confusion, unknown/extra/missing fields,
   numeric-string counts, ordering, timestamp/digest/enum errors, empty source,
   invalid identity adapter types, configured table/morph drift, and inclusive/
   over-limit artifact boundaries;
8. command output/status/exit behavior, zero write/cache on refusal, and the
   complete five-role legacy parity/dormant-package matrix.

All HTTP-touching tests retain `Http::preventStrayRequests()` and all
mail-touching tests retain `Mail::fake()`. Tests own SQLite `:memory:` schema and
private artifacts. No test claims MySQL locks, production cache behavior, or a
process/filesystem atomicity guarantee.

## 10. Documentation correction and sequencing

Implementation adds a correction section to the original C handoff rather than
rewriting historical command results. It explicitly supersedes its broad
physical ownership, strict exactly-once cache, rollback crash recovery, schema,
and exhaustive-test claims. A new remediation handoff records every command,
R-01–R-05 classification, evidence plane, failure, gate, and numbered operator
steps.

AUTHZ1-D–I remain blocked until remediation implementation, independent review,
and the separately gated MySQL rehearsal/production approval sequence. Legacy
authority, no `HasRoles`, no grants/cutover/UI/policies, and MAINT-LW-UX1's
existing independent deadline are unchanged.
