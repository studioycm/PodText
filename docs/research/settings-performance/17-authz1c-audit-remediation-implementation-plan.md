# AUTHZ1-C Audit Remediation Implementation Plan

Date: 2026-07-17
Contract: AUTHZ1-C audit remediation prompt v1
Controlling research: `17-authz1c-audit-remediation-research.md` v1
Controlling audit: `16-authz1c-independent-analyzer-backfill-audit.md`
Status: planned and prompted; implementation not started
Boundary: remediate R-01 through R-05 only; legacy authority remains active

## 1. Mandatory preflight and baselines

1. Confirm the operator names the exact v1 remediation prompt and the exact
   planning commit produced by `AUTHZ1-C-REMEDIATION-PLAN-01`.
2. Read the repository session-start files, prompt, audit/report handoff, this
   plan/research, C v1 contracts/handoff, current source/tests, state, ledger,
   queue, installed Permission/Laravel/cache/schema source, and relevant skill
   instructions in full.
3. Require clean `main`, expected planning commit at HEAD or ancestral, C
   implementation/closeout/audit commits ancestral, and no AUTHZ1-D–I start.
4. Use Boost application/version information and installed-version guidance;
   installed source controls exact cache, schema, and encryption behavior.
5. Run sequentially:

   ```bash
   php artisan test --compact tests/Feature/AuthzLegacyRoleBackfillTest.php
   php artisan test --compact tests/Feature/AuthzFoundationCatalogTest.php tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php
   ```

6. Stop on dirt, ancestry/version mismatch, baseline failure, schema/source
   contradiction, another writer, or later-slice evidence.

Do not run any `authz:roles:*` operational command. Do not access the local
development database, MySQL, production, live cache, or live filesystem
artifacts. Do not add dependencies, migrations, config keys, worktrees, or
subagents, and do not push.

## 2. Exact implementation surface

Modify only as required:

- current files under `app/Auth/LegacyRoleBackfill/`;
- the three existing `app/Console/Commands/Authz*LegacyRolesCommand.php`;
- `tests/Feature/AuthzLegacyRoleBackfillTest.php`;
- C remediation research/evidence, original-C correction, new remediation
  handoff, and minimal current state/ledger/queue/prompt index rows.

Add these focused classes under `app/Auth/LegacyRoleBackfill/`:

- `LegacyRoleBackfillSchemaContract.php` — explicit SQLite/MySQL expected
  schema descriptors plus runtime normalization;
- `ArtifactVersionException.php` — safe contract refusal for v1 artifacts;
- `OperationJournal.php` — deep-validated keyed v2 apply/cache/complete DTO;
- `RollbackOperationJournal.php` — deep-validated keyed v2 rollback DTO;
- `PermissionCacheInvalidator.php` — configured-store truthful invalidation;
- `PermissionCacheInvalidationOutcome.php` — backed enum `deleted` and
  `already_absent`.

Do not add a broad service, model, migration, policy, provider binding, job,
event, route, translation, or UI class. Constructor autowiring is sufficient.

## 3. Artifact v2 and compatibility implementation

### 3.1 Schema constants and refusal

Change the four current DTO/repository paths to write only v2 schemas from the
research note. `PrivateArtifactRepository` detects the exact v1 schema strings
before ordinary shape parsing and throws `ArtifactVersionException` with a
static non-sensitive fresh-v2-analysis instruction. Commands classify it as
refusal exit 2. Unknown schemas and malformed JSON remain operational artifact
failures exit 1.

Never overwrite, rename, upgrade, delete, or adopt a v1 artifact. Keep existing
directory layout and basename grammar. Include `.v2.` in newly derived
operation/receipt filenames so a retained v1 file cannot collide.

### 3.2 Keyed artifact integrity

Extend `PrivacyHasher` with domain-specific artifact-MAC and physical-tuple
methods. `BackfillReceipt::create/fromArray`,
`RollbackReceipt::create/fromArray`, `OperationJournal`, and
`RollbackOperationJournal` receive `PrivacyHasher` explicitly and use
HMAC-SHA-256 over canonical payload without the MAC field. The field name is
`artifact_mac`; it must be lowercase 64-hex and compared with `hash_equals`.

`PrivateArtifactRepository` injects `PrivacyHasher`, accepts an optional local
root second, and is the only filesystem-to-DTO construction boundary. It
returns typed journals/receipts, not unvalidated arrays. Preserve atomic
same-directory write/flush/rename, lock, private modes, symlink refusal,
non-overwrite, no prune, and 10 MiB ceiling.

### 3.3 Deep validators

Use small private assertion helpers on the owning DTOs; share only genuinely
identical scalar checks through `CanonicalJson` or one narrow internal helper.
Validate exact object keys and:

- status/state/ownership/cache/completion enum membership;
- native integer counts/IDs (`>= 0`, role IDs `> 0`), booleans, nullability,
  strings, timestamps, lowercase 64-hex values, and canonical role/model data;
- list versus object shape and deterministic sort/uniqueness;
- report users, issues, totals, parity matrix, schema descriptors, physical
  tuples, planned/owned deltas, and receipt/journal count reconciliation; and
- exact transition lineage between prepared, cache pending, cache invalidated,
  receipt, complete, rollback prepared, rollback receipt, and rollback complete.

`AnalysisReportValidator` independently recomputes source, target, schema,
manifest, and status evidence. No consumer may cast an unvalidated numeric
string or rely on PHP truthiness.

## 4. Privacy key correction

In `PrivacyHasher`:

1. read the passed key or `config('app.key')` and `config('app.cipher')`;
2. parse a `base64:` value with the same non-strict `base64_decode` semantics
   used by the installed `EncryptionServiceProvider::parseKey()`, otherwise
   use literal bytes exactly; do not invent a stricter or different parser;
3. require the parsed value to be a string, require a native nonempty cipher
   string, and
   `Illuminate\Encryption\Encrypter::supported($bytes, $cipher)`;
4. only then derive the 32-byte HKDF key; and
5. keep every exception generic and secret-free.

Test all four installed supported cipher/length pairs, canonical and
non-canonical base64 cases for parity with the installed provider, malformed
base64 that does not produce a supported key, empty, 15/17/31/33-byte
boundaries, unsupported cipher, and a valid default AES-256-CBC key. Do not
inspect a real environment secret.

## 5. Exact schema contract implementation

`LegacyRoleBackfillSchemaContract` exposes:

- `inspect(): array` for the current supported driver;
- `expected(string $driver): array` for explicit SQLite/MySQL descriptors; and
- `issues(array $actual): list<AnalysisIssue>` or an exact comparison used by
  the analyzer.

Use the current connection's schema builder methods, not the dev database and
not raw information-schema SQL. Normalize names to their configured exact
table/column values but compare them to the frozen non-team contract. Sort
columns by name, indexes by semantic kind/ordered columns, and foreign keys by
local/reference columns. Normalize case/action spelling; do not make generated
SQLite FK names authoritative.

Expected tables and constraints are exactly the checked-in migrations:

- `roles` and `permissions`: auto-increment unsigned-bigint/integer `id`
  primary; non-null `name`/`guard_name`; nullable timestamps; unique ordered
  `(name, guard_name)`;
- `model_has_roles`: unsigned/integer `role_id` and `model_id`, non-null
  `model_type`; primary `(role_id, model_id, model_type)`; secondary
  `(model_id, model_type)`; FK `role_id -> roles.id` with cascade delete;
- `model_has_permissions`: the permission-equivalent contract;
- `role_has_permissions`: primary `(permission_id, role_id)` and both exact
  cascade FKs; and
- `users`: required auto-increment primary `id`, non-null `varchar(32)` role
  with default `user`, and secondary role index.

Include type length/unsigned family, nullability, default where specified, and
auto-increment for every required column. Extra/missing columns remain drift.
Add stable granular issue codes for column property, primary, unique,
secondary-index, and foreign-key/reference/action drift; all issues are
enumerated in one report. Add full normalized schema to `connection.schema`
and its hash to evidence. `AnalysisReportValidator` exact-compares it.

Tests mutate only their owned SQLite schema. Recreate affected tables from the
owned migration contract to test missing/changed indexes and FKs; restore in
`finally` or isolated dataset cases. MySQL descriptors receive pure unit-level
expected-shape assertions only; no MySQL connection is opened.

## 6. Apply ownership and cache protocol

### 6.1 Prepared evidence

Replace array journals with `OperationJournal`. `preparedFromReport()` computes
the semantic delta and deterministic operation ID. Loading prepared evidence
recomputes every deterministic field from the accepted report and exact-
compares canonical bytes, permitting only the validated stored timestamp/MAC.

The transaction continues to use `DB::transaction(..., 3)`, complete
deterministic locks, and full locked recomputation. It inserts without
upsert/ignore and retains current no-repair behavior.

### 6.2 Physical transaction result

Have insertion methods return the actual database identities used. Before the
callback returns, create a typed in-memory transaction result containing:

- inserted roles `{role, role_id}`;
- all resolved protected roles `{role, role_id}`;
- inserted assignments `{assignment_hash, user_hash, role, role_id,
  model_type, physical_tuple_hash}`; and
- after report/fingerprints and changed flag.

Re-read inserted roles and pivots and recompute these values under lock before
allowing commit. Do not serialize a raw model ID.

### 6.3 Proven and unproven completion

When the transaction returned after writes in the current call, continue with
`ownership_status=proven`, `rollback_capable=true`. When apply begins with an
existing valid prepared journal and exact planned target, perform zero DB
writes and use `ownership_status=unproven`, `rollback_capable=false`, empty
owned vectors, and status `completed_unowned`. Preserve the report-derived
semantic delta in separate `planned_*` fields.

Do not treat an exact planned state as receipt-owned, even if its role IDs match
a prior attempt. Role IDs and physical tuple hashes are ownership assertions
only in the uninterrupted post-commit transaction result.

### 6.4 Cache invalidation

Replace direct registrar use in the applier with `PermissionCacheInvalidator`.
It resolves the exact configured Permission store/key through `CacheManager`,
checks presence, calls the registrar, checks presence again, and returns:

- `deleted`: key was present and is now absent;
- `already_absent`: key is confirmed absent after the call, including a false
  store return; or
- throws `BackfillException`: store inspection/call error or key remains.

Publish `cache_invalidation_pending` before the call and `cache_invalidated`
after success. A pending-without-success rerun repeats invalidation. Add a
nullable test-only post-invalidation hook after the successful call and before
success publication to exercise the crash window. Production construction
passes no hook.

Receipt fields state
`cache_semantics=at_least_once_idempotent`, outcome, and completion true. A
complete receipt/complete-journal rerun is a DB/cache/artifact no-op. Command
output includes status, receipt, ownership, rollback capability, and cache
outcome without per-user evidence. Success/no-op/completed-unowned exit 0;
contract/drift/version refusal exit 2; operational/store/artifact failure exit
1.

## 7. Rollback protocol

Reject any receipt unless it is canonical stored v2 evidence with
`ownership_status=proven` and `rollback_capable=true`. Validate all apply
journal lineage and exact physical tuples first.

Compute rollback planned state from the current exact backfill after-state less
only the receipt's owned semantic assignment hashes. Publish a
`RollbackOperationJournal` prepared state before the first delete. Inside
`DB::transaction(..., 3)`:

1. lock/recompute the full state;
2. exact before -> resolve each user hash, require recorded role ID/slug/guard,
   recompute physical tuple HMAC, delete the exact tuple, and validate planned;
3. exact planned plus prepared journal -> recovery with zero deletes; and
4. partial/different -> refusal before mutation.

Return `rolled_back` after a current-call delete commit or `recovered` for the
planned-state path. Publish keyed rollback receipt then keyed complete journal.
Receipt-backed rerun is `no_op`. Add a nullable test-only post-rollback-commit
hook before receipt publication. Never call the cache invalidator.

## 8. Test changes

Keep `tests/Feature/AuthzLegacyRoleBackfillTest.php` as the only test file.
Preserve file-level SQLite `:memory:` canary, private temp root,
`Http::preventStrayRequests()`, and `Mail::fake()`.

Add exact cases for every item in research section 9. At minimum, name direct
tests for:

- HMAC-prepared field substitution, full prepared recomputation, role ID
  recreation, external exact planned projection, proven/unproven receipt
  difference, nonrollback refusal, receipt substitution, and pre-existing
  physical tuple preservation;
- real Array store key present, key absent, repeatedly absent recovery,
  false-with-key-still-present/store exception, and successful invalidation
  followed by publication failure/retry;
- rollback prepared journal, post-commit hook failure, recovered zero-delete,
  partial state, repeated completion, and zero cache calls;
- all schema descriptor/index/FK drift classes and users source contract;
- cipher/key datasets, all nested report/journal/receipt type-confusion cases,
  empty source, invalid identity adapter types, table-name/morph-map drift, and
  size at/over boundary;
- updated command output/exit codes, zero mutation/cache on refusal, legacy
  five-role runtime parity, no `HasRoles`, no grants/cutover.

Correct or replace v1 tests whose expected `false` cache result, exactly-once
claim, planned-state recovery ownership, unkeyed checksum recomputation, or
rollback protocol is intentionally superseded. Do not delete unrelated tests.

## 9. Documentation and handoff

Create `docs/phase-02/authz1c-audit-remediation-handoff.md` with:

- R-01–R-05 and every prompt requirement classified;
- exact files/tests/commands, including failures and reruns;
- separate repository, installed-source, Boost/docs, SQLite, inference, and
  deferred-MySQL evidence;
- proven/unproven receipt, at-least-once cache, rollback recovery, schema,
  v1 refusal, privacy, and command behavior;
- gate results and deviations;
- numbered imperative Local Front Check steps; and
- `## Commit hash` set to `pending` before the implementation commit.

Append a clearly dated audit/remediation correction to the original C handoff;
do not alter its historical test/command record. Append implementation evidence
to the new research note. Update minimal state, ledger, queue, and prompt index.
Record remediation implemented only after gates. Keep AUTHZ1-D blocked pending
review and the separately approved MySQL/production sequence.

## 10. Requirements sweep, final gate, and commits

Before gates, sweep every R-01–R-05 item, this plan, and prompt line-by-line.
Classify each as Implemented, Already existed, Deferred, Not applicable, or
Blocked in the handoff.

On the final tracked state, run sequentially:

1. `vendor/bin/pint --test`
2. `vendor/bin/filacheck`
3. `npm run build`
4. full `php artisan test` last

After any tracked change, re-enter at Pint. Never parallelize or interrupt the
full suite. Never run `vendor/bin/filacheck --fix`.

Then:

1. commit implementation/tests/docs/handoff with pending hash as
   `fix: remediate authz1c audit findings`;
2. immediately stamp that hash into the remediation handoff and ledger and
   commit only those Markdown changes as
   `docs: backfill authz1c remediation hash`;
3. verify clean status and do not push.

Hard stop. Do not run operational commands or MySQL rehearsal and do not begin
AUTHZ1-D–I, authority cutover, grants, `HasRoles`, UI/policies, ARCH1, SP3D, or
MAINT-LW-UX1 implementation.
