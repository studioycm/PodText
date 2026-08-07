# AUTHZ legacy-role subsystem — why it is dormant, and what is worth reading in it

Date: 2026-08-07 (rewritten same day — see §8)

Status: **kept, not deleted.** Deletion was proposed and rejected. Nothing in
`App\Auth\LegacyRoleBackfill` is scheduled for removal.

**Read this if** you have just found 3,800 lines of unreferenced machinery under
`app/Auth/` and are working out whether it matters to you. Short answer: almost
certainly not — it cannot be called — but four of its 23 classes contain
technique worth borrowing, and one has an active consumer.

---

## 1. Why it is dormant

Three Artisan entry points (`authz:roles:analyze`, `:backfill`, `:rollback`)
were deliberately removed by the accepted closure
(`20-authz-command-closure-implementation-plan.md`, shipped as `0be8070`).
`tests/Unit/AuthzCommandClosureArchitectureTest.php` pins the isolation:

```php
arch('keeps the dormant legacy-role migration boundary isolated')
    ->expect('App\Auth\LegacyRoleBackfill')
    ->toOnlyBeUsedIn('App\Auth\LegacyRoleBackfill');
```

So it is sealed, not merely unused. Adding a caller fails the suite.

## 2. Why keeping it is now defensible

It was **not** defensible earlier on 2026-08-07. `LegacyRoleBackfillAnalyzer`
hard-coded `$contract['package_version'] !== '7.3.0'` as a **blocking** issue,
so dormant code was vetoing a live dependency upgrade: spatie 8 failed 31 tests
that nothing reachable depended on.

That is exactly what `19-authz-complexity-reset-and-feature-first-master-plan.md`
warns against — a future option charging rent on present work:

> A reversible future option is not automatically a present requirement.

Fixed in `a0cd9aa` by validating a half-open range `[7.0.0, 9.0.0)` instead of a
pinned string. The subsystem is now genuinely inert: it cannot be called, and it
cannot block a dependency. Keeping it costs nothing but **attention** — which is
what this document exists to reduce.

If a third kind of cost appears, revisit the deletion decision.

## 3. What authorization actually runs (unrelated to any of the above)

| Piece | Where |
|---|---|
| Role storage | `users.role` — `2026_07_13_010000_add_role_to_users_table.php` |
| Role type | `App\Enums\UserRole`, five rank-ordered roles |
| Enforcement | Gates/macros, panel admission, Horizon, maintenance bypass, Users Resource |
| Policies | `app/Policies/*`, plain Laravel, not permission-driven |
| Shield's only use | `FilamentShield::prohibitDestructiveCommands()` — a safety guard, not authorization |

`User` does **not** use `HasRoles`. All five spatie tables exist with **0 rows**,
on spatie 8.3.0 / shield 4.3.1. Regressions that must stay green:
`LegacyAuthorizationMatrixTest`, `AuthzPackageFoundationTest`,
`PanelAuthHardeningTest`, `PublicMaintenanceModeTest`.

## 4. The 23 classes

**The triad — 19 files, ~3,090 lines.** Analyzer/Applier/Rollback plus the value
objects that exist only to serve them: `AnalysisReport`,
`AnalysisReportValidator`, `AnalysisUser`, `AnalysisIssue`, `OperationJournal`,
`RollbackOperationJournal`, `BackfillReceipt`, `RollbackReceipt`,
`BackfillResult`, `RollbackResult`, `PermissionCacheInvalidator`, and five
exceptions. This encodes a migration the project decided not to run.

**Four utilities**, with their real extraction cost:

| Class | Lines | Also needs | Status |
|---|---|---|---|
| `CanonicalJson` | 33 | nothing | free to move |
| `PrivacyHasher` | 96 | `CanonicalJson`, `PrivacyKeyException` (5) | nearly free |
| `LegacyRoleBackfillSchemaContract` | 273 | `AnalysisIssue` (86), `BackfillRefusalException`, `CanonicalJson` | cheap; **has a consumer** |
| `PrivateArtifactRepository` | 304 | 5 triad value objects (~1,100 lines) | **not** cheap |

`PrivateArtifactRepository` is a *typed* repository over the triad's payloads.
Any claim that it is authorization-free is wrong — check its imports.

## 5. `LegacyRoleBackfillSchemaContract` — the one with an active consumer

A driver-aware schema differ. `inspect()` reads live schema via
`getSchemaBuilder()` and normalizes it deterministically; `expected($driver)`
builds the expected descriptor **parameterized by driver**; `issues()` diffs into
typed drift codes.

`expected()` is a written-down, test-backed catalogue of where SQLite and MySQL
disagree — `driver-lenient-fallback` enumerated instead of discovered one bug at
a time:

| Property | sqlite | mysql |
|---|---|---|
| integer `unsigned` | false | true |
| string `length` | null (not reported) | real (255, 32) |
| index `type` | null | `btree` |
| FK `on_update` | `no action` | `restrict` |
| `role_has_permissions` index on `role_id` | absent | present |

`normalizeColumn()` adds more: type-name spellings differ per driver
(`varchar`/`char`/`string` → string, `timestamp`/`datetime` → datetime), and
string defaults come back quoted.

`AuthzLegacyRoleBackfillTest.php:745` asserts the live SQLite descriptor equals
`expected('sqlite')` **and** that `expected('mysql')` carries the MySQL
properties — a MySQL assertion running on SQLite, i.e. a hand-rolled stand-in
for the test lane in `docs/phase-02/mysql-test-lane-spec.md`. Once that lane
exists, run `inspect()` against MySQL and compare to `expected('mysql')` for
real; the class then doubles as a canary that the lane is production-shaped
rather than Herd-default-shaped.

Caveat: it covers only the five spatie tables plus `id`/`role` on `users`. It is
a pattern to generalise, not a finished tool. Handed to the
"Database standardization across environments" session on 2026-08-07.

## 6. The technique worth borrowing from `PrivateArtifactRepository`

The class is coupled to the triad, but its two private methods are ~40 lines of
reusable recipe for immutable on-disk JSON:

**Publish** — `flock(LOCK_EX)` for the whole operation → refuse if
`file_exists() || is_link()` (immutability *and* symlink-swap defence) → size cap
(10 MiB) → write temp → `fflush` → **atomic `rename`**, so a reader never sees a
partial file.

**Load** — reject `is_link()` → size-check *before and after* reading →
`JSON_THROW_ON_ERROR` → validate payload shape. Names constrained to
`/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}\.json\z/D`: no traversal, no leading dot,
bounded length.

**Open question for whoever owns settings lifecycle.**
`app/Support/SettingsLifecycle/SettingsBackupSnapshotManager.php:104` writes with
a plain `Storage::disk('local')->put(...)` — no atomicity, no locking, no size
bound. That may be entirely fine: it appears to write a job-input file consumed
by a process it launches itself, so a partial write may be structurally
impossible. Not asserted as a defect — worth one look by someone who knows that
path. If it turns out a truncated snapshot can ever be read back, the recipe
above is the fix.

Do **not** generalise the class into `App\Support\Artifacts\*` speculatively.
Building a generic store for a caller that might appear is the same move that
produced a rollback service for a migration that never ran once.

## 7. The trail (all still in the repo)

`docs/research/settings-performance/`: `12` pre-implementation research;
`13` foundation research + plan; `15` analyzer/backfill research + plan;
`16` independent audit; `17` audit remediation research + plan; `18` independent
remediation audit; **`19` the complexity-reset master plan — read this one**;
`20` command closure. Reports 12–18 are historical evidence, explicitly not an
implementation queue.

## 8. Revision note

First written the same day as a pre-deletion record, when removal was the plan.
The operator declined to delete code they had not read, then the version-range
fix removed the only concrete cost of keeping it. Re-pointed from "what you are
about to lose" to "why this is here and what to read in it." The deletion
inventory was dropped; `git log` has it if deletion is ever reconsidered.
