# AUTHZ1-C Independent Remediation Audit

Audit ID: `AUTHZ1-C-REMEDIATION-AUDIT-01`

Date: 2026-07-17

Audited HEAD: `de68d29187f1d8445c065109aa13101e8d5ed707`

Remediation implementation: `0cdf8390b67d260ced54a0ca2ad58600bd475da5`

Decision: **separate follow-up required**

Authority boundary: legacy `users.role`, `UserRole`, ranks, Gates, panel,
Horizon, maintenance, User Resource, and existing callers remain authoritative.

## 1. Executive conclusion

The remediation materially closes the original R-01 through R-05 findings.
Local source and SQLite `:memory:` evidence supports physical role/pivot
ownership, conservative `completed_unowned` restart completion, rollback
prohibition for unowned receipts, truthful durable at-least-once permission
cache invalidation, prepared/recoverable rollback, complete normalized SQLite
schema descriptors, Laravel-compatible APP_KEY/cipher validation, deep keyed
v2 DTO validation, immutable v1 refusal, private artifact boundaries, safe
command output, and unchanged five-role legacy authority.

Local acceptance is still not supported as a complete claim. Three gaps
remain:

1. a coherent keyed replacement of a `completed_unowned` receipt and its
   complete journal can reclassify externally created pivots as proven and
   rollback-capable;
2. rollback accepts keyed, individually valid pending/cache/complete operation
   journals after comparing only selected fields, rather than exact transition
   lineage against the prepared journal and canonical receipt; and
3. the public source-row analysis adapter distinguishes integer `1` from
   string `"1"` for HMAC identity but aliases both through the same internal
   target-lookup key, so the later row silently replaces the earlier one.

The first is a High destructive-ownership contradiction to the explicit
receipt/journal substitution and external-projection refusal contract. The
second is a Medium evidence-integrity contradiction to exact transition
lineage; by itself it does not widen the delete set because physical tuple
checks remain. The third is a Low adapter-boundary correctness contradiction;
normal database ID hydration is homogeneous and apply reanalysis remains fail
closed, but the documented native-int-or-string adapter contract is broader
than the code.

The focused remediation suite passed 56 tests / 555 assertions. The minimum
adjacent authorization suite passed 124 tests / 3,496 assertions. Those tests
do not coherently replace an unowned keyed receipt and complete journal,
substitute keyed pending/cache/complete lineage fields, or mix integer/string
identities with the same textual value. No MySQL, persistent database,
operational AUTHZ command, production action, or implementation work was
performed.

## 2. Scope and evidence discipline

### 2.1 Repository and contract evidence

The checkout matched the requested baseline:

- clean `main` at exact
  `de68d29187f1d8445c065109aa13101e8d5ed707`;
- remediation implementation
  `0cdf8390b67d260ced54a0ca2ad58600bd475da5` ancestral;
- original AUTHZ1-C implementation, closeout, independent audit, remediation
  planning, implementation, and hash closeout ancestral;
- AUTHZ1-D–I unstarted and legacy authority active; and
- no pre-existing repository change at audit start.

The audit read the remediation prompt/research/plan/handoff, original C
contracts/handoff, audit 16 and its handoff, every remediation-changed source
and test path, related unchanged DTO/helpers, migrations, Permission config,
catalog/role/manifest/foundation sources, legacy runtime authority sources,
current state, ledger, queue, and prompt index.

### 2.2 Installed-source and Boost evidence

Laravel Boost reported PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire
4.3.3, Horizon 5.47.2, Pest 4.7.4, and the installed package set. Its
installed-version guidance covered Laravel transaction retry/commit behavior,
schema inspection, encryption key support, cache behavior, and Pest datasets.
The development database was not queried.

Exact installed source confirmed:

- Permission 7.3 resolves `default`, named, and missing configured cache stores
  exactly as `PermissionCacheInvalidator` does, and
  `forgetCachedPermissions()` clears registrar memory before forgetting the
  configured key;
- Laravel 13.19 parses `base64:` APP_KEY material non-strictly and
  `Encrypter::supported()` enforces the configured cipher's exact byte length;
- Laravel transaction retries rerun the callback and return only after commit;
  and
- Laravel's MySQL/SQLite schema builders and processors expose the normalized
  column, index, and foreign-key properties consumed by the schema contract.

### 2.3 Executed SQLite evidence

| Command | Result |
| --- | --- |
| `php artisan test --compact tests/Feature/AuthzLegacyRoleBackfillTest.php` | PASS — 56 tests / 555 assertions |
| `php artisan test --compact tests/Feature/AuthzFoundationCatalogTest.php tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php` | PASS — 124 tests / 3,496 assertions |

The suites ran sequentially. The focused test owns its private artifact root,
requires testing mode with SQLite `:memory:`, calls
`Http::preventStrayRequests()`, and uses `Mail::fake()`. No live HTTP, mail,
development database, or production resource was used.

### 2.4 Deferred evidence

The separately gated disposable two-connection MySQL rehearsal remains future
work. SQLite and source inspection do not prove:

- InnoDB row, next-key, gap, or empty-range locking;
- real concurrent source/role/pivot insert blocking;
- deployed transaction isolation and multi-connection TOCTOU behavior;
- real MySQL deadlock victim/retry behavior;
- MySQL runtime normalization against the deployed schema; or
- production cache-store/filesystem races and failure behavior.

## 3. Findings

### H-01 — Coherent keyed substitution can fabricate rollback ownership

Severity: **High destructive rollback integrity**

Requirement affected: role/pivot ownership proof, receipt/journal substitution
refusal, external exact-projection refusal, and the rule that only an
uninterrupted successful writer may publish proven rollback capability.

Evidence:

- after an ambiguous prepared/exact-planned restart, the canonical v2 receipt
  correctly records `ownership_status=unproven`, `rollback_capable=false`, and
  empty owned/protected vectors;
- `BackfillReceipt` deep validation permits a coherent proven receipt whose
  owned assignments are a subset of the prepared semantic plan, whose complete
  protected role map uses current physical role IDs, and whose physical tuple
  HMACs reconcile. It cannot distinguish whether the current process or an
  external writer created those planned tuples;
- rollback's canonical-receipt check reloads the canonical path and compares it
  with the receipt the command just loaded. If the same-service-account writer
  replaced that file and recomputed its MAC/fingerprint, both objects are the
  same substituted artifact (`LegacyRoleBackfillRollback.php:145-150`); and
- replacing the corresponding complete journal with matching keyed ownership
  vectors satisfies the selected comparisons at
  `LegacyRoleBackfillRollback.php:152-180`. Original prepared, pending, and
  cache journals can remain unchanged because they contain no physical
  ownership claim.

Failure path:

1. C publishes prepared evidence, another writer creates the exact planned
   state, and C correctly publishes `completed_unowned` evidence.
2. A same-service-account artifact writer replaces the canonical receipt with
   an exact-shape, MAC-valid proven receipt. The semantic plan remains
   unchanged, but its owned assignments identify the externally created
   planned pivots and its protected map/physical tuple HMACs use current IDs.
3. The writer replaces the complete journal with an exact-shape, MAC-valid
   journal matching the forged receipt. Pending/cache evidence need not change.
4. Rollback accepts the substituted evidence and deletes those external
   pivots. The physical checks prove only that the tuples currently match the
   substituted receipt, not that the uninterrupted C transaction owned them.

The direct focused test named `rejects a substituted keyed receipt before
rollback mutation` increments one owned `role_id` without updating the
protected role map; `BackfillReceipt::fromArray()` rejects that internally
incoherent DTO. It does not exercise a coherent substituted receipt plus
matching complete journal on the rollback entry path.

Disposition: **separate follow-up required before AUTHZ1-D–I**. The follow-up
must either restore a durable ownership trust anchor outside the replaceable
artifact/key boundary or explicitly narrow and independently accept the threat
model. This audit does not authorize or provide implementation work.

### M-01 — Rollback does not exact-compare complete apply lineage

Severity: **Medium integrity / assurance**

Requirement affected: the remediation plan requires exact transition lineage
between prepared, cache pending, cache invalidated, receipt, and complete
artifacts, and the handoff claims that all DTO lineage is enforced.

Evidence:

- apply receipt-backed reruns correctly rebuild and canonical-byte-compare the
  pending and cache journals from prepared evidence, then rebuild and exact-
  compare the complete journal
  (`LegacyRoleBackfillApplier.php:420-471`);
- rollback instead loads the same four keyed journals and compares the pending
  journal only by state and `operation_id`, the cache journal only by state,
  `operation_id`, and `cache_outcome`, and the complete journal only by state,
  `operation_id`, receipt name, ownership/rollback flags, and owned vectors
  (`LegacyRoleBackfillRollback.php:152-180`); and
- omitted rollback comparisons include report/source/target fingerprints,
  planned roles/assignments, preparation/transition timestamps, and—on the
  complete journal—cache outcome. All can remain individually schema-valid and
  MAC-valid while disagreeing with the canonical prepared/receipt lineage.

Failure path:

1. A keyed-substitution test actor replaces a pending, cache, or complete
   journal with an exact-shape v2 journal, recomputes its operation-journal MAC,
   and preserves only the fields rollback compares.
2. `OperationJournal::fromArray()` accepts the artifact because its own shape,
   state, types, ordering, and MAC are valid.
3. Direct rollback lineage validation accepts it even though the full
   transition does not derive from the stored prepared journal and receipt.

Apart from H-01's coherent ownership substitution, this selected-field gap is
bounded: with an authentic proven receipt, rollback still validates exact
current state, protected role IDs, current user HMACs, physical tuple HMACs,
and every tuple before deletion. M-01 independently contradicts evidence
integrity and the exact-lineage acceptance claim.

Test boundary: the focused suite tests full prepared recomputation, nested
type confusion, receipt substitution, and ordinary rollback recovery. It does
not substitute a keyed, exact-shape pending/cache/complete journal while
preserving the selected fields rollback currently compares.

Disposition: **separate follow-up required before AUTHZ1-D–I**. This audit
does not authorize or provide implementation work.

### L-01 — Mixed native identity types alias in target lookup

Severity: **Low adapter-boundary correctness / assurance**

Requirement affected: R-05 states that the raw source adapter accepts native
integer or string IDs while invalid identities cannot collide silently in the
internal map.

Evidence:

- `scanSource()` correctly includes the native type in identity canonicalization
  and `PrivacyHasher::userHash()`, so integer `1` and string `"1"` receive
  distinct identity/HMAC values
  (`LegacyRoleBackfillAnalyzer.php:307-328` and
  `PrivacyHasher.php:41-47`); but
- accepted IDs are stored for target resolution under
  `$internal[(string) $id]`, so integer `1` and string `"1"` both use key
  `"1"` and the later row replaces the earlier without a blocker
  (`LegacyRoleBackfillAnalyzer.php:359-365`); and
- target pivot lookup also stringifies `model_id` before resolving that map
  (`LegacyRoleBackfillAnalyzer.php:458-463`).

This can associate a target row with the wrong typed HMAC/role in an
`analyzeSourceRows()` call containing the same textual ID in both native
types. It is not a demonstrated production mutation path: the operational
query receives one homogeneous database key representation, and apply performs
fresh locked operational analysis/source-fingerprint reconciliation before a
write.

Test boundary: invalid arrays, objects, floats, booleans, and nulls are tested
as distinct blockers. The focused suite does not cover the accepted native
pair `1` and `"1"` together.

Disposition: **separate follow-up required** to narrow or make the public
adapter's identity-key contract type-safe. This audit does not authorize a
test or source change.

## 4. R-01–R-05 verification matrix

| Requirement | Verdict | Evidence boundary |
| --- | --- | --- |
| R-01 keyed v2 prepared evidence | Supported locally | Prepared journal is recomputed from the validated report and canonical-byte-compared; keyed field substitution refuses. |
| R-01 physical ownership/substitution refusal | Not accepted | Ordinary actual role IDs, protected role map, typed-user physical tuple HMAC, role recreation refusal, and canonical receipt behavior are supported, but H-01 permits coherent keyed unowned-to-proven receipt/complete substitution and destructive rollback of an external exact projection. |
| R-01 `completed_unowned`/rollback prohibition | Supported locally | Exact-planned restart after prepared evidence records no owned vectors, is non-rollback-capable, and rollback refuses; true initial already-applied remains a no-receipt no-op. |
| R-02 truthful durable cache invalidation | Supported locally | Store resolution matches Permission 7.3; presence is checked before/after; absent is successful; pending precedes the call; crash-before-success publication repeats idempotently; receipt-backed rerun avoids another call. Production-store races remain unproven. |
| R-03 rollback prepared/recovery | Mostly supported | Ordinary canonical receipt, prepared-before-delete, exact before/planned states, recovered zero-delete path, partial-state refusal, immutable publication, and no cache reset are present and tested. H-01 and M-01 limit substituted ownership/lineage acceptance. |
| R-04 complete logical schema descriptors | Supported for SQLite and pure MySQL expectations | All required tables, users ID/role, column properties, PK/unique/secondary indexes, FKs, references/actions, configured names, and morph type are normalized and hashed. SQLite drift tests pass; deployed MySQL remains deferred. |
| R-05 Laravel-compatible key validation | Supported locally | Parsing and cipher length match installed Laravel; all four cipher pairs and malformed/boundary cases are tested with generic errors. |
| R-05 exact/deep v2 DTOs and immutable v1 refusal | Mostly supported | Exact keys/types/order/counts/enums/timestamps/digests/MACs, v1 family refusal, size boundary, and private immutable publication are supported. H-01 is coherent cross-artifact ownership substitution, M-01 is incomplete transition lineage, and L-01 is adapter lookup identity. |
| Command safety and legacy parity | Supported locally | Explicit confirmations/fingerprints and static/generic errors avoid row/secret leakage; adjacent suite is green; `User` lacks `HasRoles`; package Gate registration remains off; no grants or cutover exist. |
| Honest SQLite/MySQL boundary | Supported | No MySQL execution or concurrency proof is claimed. |

## 5. Negative-search conclusions

- No raw APP_KEY, user ID, raw role, password, token, or artifact MAC material
  is printed by the three AUTHZ commands.
- No cross-family MAC reuse was found: operation, rollback-operation,
  backfill-receipt, and rollback-receipt domains are distinct. M-01 is an
  incomplete same-family transition comparison after valid MAC verification.
- No v1 adoption, upgrade, overwrite, rename, or collision with `.v2.` derived
  names was found.
- No numeric-string coercion was found in DTO counts/IDs; DTO validators require
  native integers and exact list/object shapes.
- No rollback of `completed_unowned` evidence, no role-row deletion, no cache
  invalidation during rollback, and no pre-existing pivot deletion were found.
- No artifact-size off-by-one was found: 10 MiB payload content is admitted
  through the size gate with one publication newline, and an extra byte
  refuses.
- No exception path was found that emits nested exception details; known
  failures use static field/contract messages and unexpected throwables use
  generic command messages.

## 6. Decision and sequencing

Selected outcome: **separate follow-up required**.

H-01, M-01, and L-01 must be resolved or explicitly narrowed and independently
accepted before AUTHZ1-D–I. This report is an audit record, not an
implementation prompt, and authorizes no application/test/config/migration or
operational work.

The disposable two-connection MySQL rehearsal remains a separate future gate.
`MAINT-LW-UX1` keeps its existing independent deadline: before the first later
public Livewire navigation/polling/lazy/deferred/stream/upload expansion or
AUTHZ1 final acceptance, whichever comes first.
