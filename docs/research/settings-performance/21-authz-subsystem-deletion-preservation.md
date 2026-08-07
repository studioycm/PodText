# AUTHZ legacy-role subsystem — deletion preservation record

Date: 2026-08-07

Status: written **before** deletion, so that removing the code is not the same
as losing what it knew. Deletion itself is a separate, not-yet-executed task.

**Read this first if you are about to introduce Shield + spatie/laravel-permission
from scratch.** It is a map and a set of warnings, not a copy of the code. Git
history is the code archive; this document is the part that history does not
explain.

---

## 1. Why it is being deleted

`App\Auth\LegacyRoleBackfill` is 23 classes / ~3,800 lines with a 1,145-line
test file, and **nothing can call it**. Its three Artisan entry points were
deliberately removed by the accepted closure
(`20-authz-command-closure-implementation-plan.md`, shipped as
`0be8070`), and `tests/Unit/AuthzCommandClosureArchitectureTest.php` pins the
isolation with a Pest arch expectation.

The closure preserved it as a dormant asset. That was defensible when the only
cost was carrying the code. On 2026-08-07 a new cost appeared:

> `LegacyRoleBackfillAnalyzer.php:269` hard-codes
> `if ($contract['package_version'] !== '7.3.0')` and raises a **blocker**
> `AnalysisIssue('package_version_drift')`.

Upgrading `spatie/laravel-permission` 7.3.0 → 8.3.0 (with shield 4.2.0 → 4.3.1,
which resolves cleanly) fails **31 tests** — 9 failures + 22 errors, all
"An applicable analysis report must contain no blocker issues" — entirely
inside this dormant subsystem. Nothing reachable breaks.

**Dormant code that can still veto the dependency graph is not dormant.** That
is the deletion rationale. Operator decision 2026-08-07: delete, and reintroduce
Shield + spatie from scratch when the product actually needs roles.

## 2. What authorization actually runs today (do not delete this)

This is the live system and it is **unrelated** to the subsystem above:

| Piece | Where |
|---|---|
| Role storage | `users.role` column — `2026_07_13_010000_add_role_to_users_table.php` |
| Role type | `App\Enums\UserRole` — five roles, rank-ordered |
| Enforcement | Gates/macros, panel admission, Horizon, maintenance bypass, Users Resource restrictions |
| Policies | `app/Policies/*` — plain Laravel policies, not permission-driven |
| Shield's only use | `FilamentShield::prohibitDestructiveCommands($production)` in `AppServiceProvider` — a safety guard, **not** authorization |

`User` does **not** use `HasRoles`. All five spatie tables exist and hold
**0 rows**. Regression tests that must stay green: `LegacyAuthorizationMatrixTest`,
`AuthzPackageFoundationTest`, `PanelAuthHardeningTest`, `PublicMaintenanceModeTest`.

## 3. What was built

Three services, each with its own result/receipt/journal value objects:

**Analyzer** — `LegacyRoleBackfillAnalyzer`, `AnalysisReport`, `AnalysisUser`,
`AnalysisIssue`, `AnalysisReportValidator`, `LegacyRoleBackfillSchemaContract`.
Reads legacy `users.role`, produces a keyed, privacy-safe report of what a
backfill *would* do, and refuses to proceed on any blocker issue. The schema
contract validates the live spatie schema against a recorded descriptor.

**Applier** — `LegacyRoleBackfillApplier`, `BackfillResult`, `BackfillReceipt`,
`OperationJournal`, `PermissionCacheInvalidator`. Applies role assignments
transactionally, journals every write, emits a keyed receipt, and invalidates
the permission cache via `PermissionRegistrar::forgetCachedPermissions()`.

**Rollback** — `LegacyRoleBackfillRollback`, `RollbackResult`,
`RollbackReceipt`, `RollbackOperationJournal`. Reverses only receipt-owned
assignments, bound to actual role IDs and physical tuples, so it cannot delete
rows it did not create.

**Cross-cutting** — `PrivateArtifactRepository` (immutable versioned artifacts
on disk, refuses overwrite/symlink/tampering), `CanonicalJson` (deterministic
serialization so hashes are stable), `PrivacyHasher` (keyed hashing so reports
carry no PII), plus a five-class exception hierarchy under `BackfillException`
splitting hard failures from refusals.

Removed entry points (already gone, listed so you know what the shape was):
`authz:roles:analyze`, `authz:roles:backfill`, `authz:roles:rollback`.

## 4. What to pay attention to when you rebuild

Ordered by how expensive they were to learn.

1. **Scope discipline first.** `19-authz-complexity-reset-and-feature-first-master-plan.md`
   is the most valuable document in the chain. AUTHZ started as "Super Admin vs
   Admin" and grew into a 135-key catalog, five role definitions, migration
   analyzers, keyed evidence, backfill, rollback, cache recovery, independent
   audits and MySQL rehearsal planning — ~17 tasks and 21 hours projected
   *before any product value*. Its lesson, quoted:

   > A reversible future option is not automatically a present requirement.

   Read it before writing a line.

2. **Never hard-code a package version inside a service.** This is the specific
   thing that killed the subsystem. If you need a schema/version contract,
   accept a range, or assert it in a test where a failure is a prompt to
   re-verify rather than a runtime blocker in dormant code.

3. **Do not build rollback before you have run forward once.** The rollback
   machinery, artifact immutability, crash-window recovery and cache-failure
   recovery were all built for an operation that never executed a single time.

4. **`PermissionRegistrar::forgetCachedPermissions()` is the whole cache story.**
   Stable across spatie v6/v7/v8. It was the only spatie API the subsystem
   actually touched. Everything else was our own machinery.

5. **The empty tables are a gift.** With 0 rows in all five tables you can drop
   and re-publish spatie's migrations freely. Do that rather than preserving a
   v7-era `create_permission_tables` migration into a v8 world.

6. **Keep `FilamentShield::prohibitDestructiveCommands()`** when you remove the
   rest — it is a production safety guard that has nothing to do with roles.

## 5. Worth reviving, worth leaving

**Worth reviving if the need returns:** `CanonicalJson` (deterministic JSON for
stable hashing) and `PrivacyHasher` (keyed hashing for PII-free reports) are
small, general, and independent of authorization. Both are reusable elsewhere.

**Leave behind:** the analyzer/applier/rollback triad and their journals,
receipts and artifact repository. They encode a migration path the project has
decided not to take, and rebuilding to a known requirement will be cheaper than
adapting them to one.

## 6. The trail (stays in the repo — not deleted)

`docs/research/settings-performance/`:

| Doc | Holds |
|---|---|
| `12-authz1-pre-implementation-research.md` | original problem framing |
| `13-authz1-foundation-research.md` / `-implementation-plan.md` | role catalog, five-role definitions |
| `15-authz1c-analyzer-backfill-research.md` / `-implementation-plan.md` | analyzer + backfill design |
| `16-authz1c-independent-analyzer-backfill-audit.md` | first independent audit |
| `17-authz1c-audit-remediation-research.md` / `-implementation-plan.md` | audit remediation |
| `18-authz1c-independent-remediation-audit.md` | second independent audit |
| **`19-authz-complexity-reset-and-feature-first-master-plan.md`** | **the scope lesson — read this one** |
| `20-authz-command-closure-implementation-plan.md` | why the commands were removed; exact preservation boundary |

Reports 12–18 are historical evidence, explicitly **not** an implementation
queue (per doc 19).

## 7. Deletion inventory

When the deletion is executed, expect to touch exactly:

- `app/Auth/LegacyRoleBackfill/` — 23 files, ~3,800 lines
- `tests/Feature/AuthzLegacyRoleBackfillTest.php` — 1,145 lines
- `tests/Unit/AuthzCommandClosureArchitectureTest.php` — the arch guard becomes
  meaningless once the namespace is gone
- `composer.json` — the exact pins `bezhansalleh/filament-shield: 4.2.0` and
  `spatie/laravel-permission: 7.3.0` exist to mirror the analyzer's hard-coded
  version and can become ranges, or the packages can be removed entirely
- `database/migrations/2026_07_16_172210_create_permission_tables.php` — only if
  the packages are removed too; the tables are empty

Do **not** touch anything in §2.

Open question for whoever executes it: remove the two packages as well, or keep
them installed and dormant? Removing them is cleaner and the tables are empty;
keeping them preserves `prohibitDestructiveCommands()` without a replacement.
That is a decision, not an implementation detail.
