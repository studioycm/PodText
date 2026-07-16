# AUTHZ1-C Analyzer / Backfill Handoff

Date: 2026-07-16
Task: `AUTHZ1-C-EXEC-01`
Prompt: `prompts/pre-13-prompts/authz1c-analyzer-backfill-codex-prompt.md`
Prompt version: v1 — 2026-07-16
Status: complete
Authority: legacy `users.role` authorization remains authoritative

## Commit hash

`0147ea83947ccedb1336a71d8eecc887eb8d4e07 feat: add authz legacy role backfill`

## Outcome

AUTHZ1-C now provides three deliberately controlled console surfaces:

- `authz:roles:analyze` reads raw `users.id` / `users.role` values and every
  Permission package target table, accumulates all problems into one
  privacy-safe immutable report, and performs no database or cache mutation;
- `authz:roles:backfill` accepts only an exact source fingerprint, exact report
  fingerprint, and literal `AUTHZ1-C` confirmation, then creates only missing
  protected `web` role rows and exact same-slug user-role pivots;
- `authz:roles:rollback` accepts only a complete canonical receipt, exact
  after-state fingerprint, and literal `ROLLBACK-AUTHZ1-C` confirmation, then
  removes only receipt-recorded inserted pivots while preserving roles and
  pre-existing assignments.

The writer uses query-builder operations rather than package models, retains
legacy authority, and leaves Permission/Shield runtime role APIs dormant.

## Requirements disposition

### Implemented

1. Raw-source analysis reads query-builder rows without Eloquent casts,
   normalization, aliases, trimming, or fallback roles.
2. Invalid identities and role types/bytes, duplicate identities, target role
   collisions/drift, wrong guards/model types, orphan/multiple/extra pivots,
   permissions, role grants, direct grants, config/team/provider/column/schema,
   catalog/manifest/package, and reporting-key drift fail closed.
3. Reports use canonical JSON, HKDF-derived domain-separated HMAC identifiers,
   non-secret key IDs, deterministic totals/vectors, target fingerprints, and
   the exact compatibility manifest plus legacy parity projection.
4. Reports, operation journals, backfill receipts, and rollback receipts are
   immutable private artifacts with validated basenames, 10 MiB limits,
   atomic publication, serialized concurrent writes, non-overwrite behavior,
   symlink refusal, schema/fingerprint checks, and private permissions.
5. Apply revalidates the accepted report independently, locks and recomputes
   the complete source/target inside `DB::transaction(..., 3)`, publishes
   prepared evidence before writes, and requires the exact planned projection
   before commit.
6. Deadlock retry re-enters the complete lock/recompute/write path. Induced
   failure rolls back all role/pivot writes and performs no cache reset.
7. Permission cache reset runs only after the changed transaction returns, or
   during exact prepared/planned crash recovery; false/exception outcomes keep
   recoverable evidence. Cache-reset and complete journals are revalidated.
8. Receipt-backed reruns are database/cache/artifact no-ops. Partial or
   different target state refuses.
9. Rollback validates the canonical receipt and complete prepared/cache/finish
   evidence, deletes only receipt tuples, requires the computed rollback
   projection, emits an immutable receipt/journal, performs no cache reset, is
   idempotent, and permits controlled re-analysis/reapply.
10. All five legacy roles retain the same Admin panel, Author/Admin Tools,
    User Resource, `super-admin`, `multi-transcription`, Horizon, maintenance,
    and direct-caller outcomes before apply, after apply, and after rollback.
11. Command output is limited to privacy-safe statuses, counts, fingerprints,
    issue totals, and artifact basenames with the contracted exit codes.

### Already existed and preserved

- Permission 7.3.0 tables, exact five-role metadata, 135-ability catalog, and
  compatibility grant manifest from the reversible AUTHZ1 foundation.
- Legacy `UserRole`, ranks, Gates/macros, panel/Horizon/maintenance admission,
  User Resource restrictions, and Laravel 13 command discovery.
- Shield remains configured but unregistered; `User` still lacks `HasRoles`.

### Deferred by contract

- The disposable two-connection production-shaped MySQL rehearsal remains a
  separately approved future production gate. SQLite proves deterministic
  application semantics, not MySQL row/gap locks, concurrent inserts,
  constraint races, isolation behavior, or multi-connection TOCTOU safety.
- Any production report, backup, backfill, rollback, cache, or process action.
- AUTHZ1-D–I authority cutover, compatibility-grant application, policy/Gate
  migration, role management UI, ARCH1, SP3D, and `MAINT-LW-UX1`.
- Artifact pruning/export or a changed retention policy.

### Not applicable

- No migration, dependency, npm package, config, model trait/cast, Gate,
  policy, Filament Resource/Page/form/table, route, translation, queue, mail,
  browser UI, or production command-guard change was required.

### Blocked

- None for the local AUTHZ1-C implementation contract. Production approval is
  intentionally unavailable until the separately approved MySQL rehearsal.

## Files changed

### Application

- `app/Auth/LegacyRoleBackfill/AnalysisIssue.php`
- `app/Auth/LegacyRoleBackfill/AnalysisReport.php`
- `app/Auth/LegacyRoleBackfill/AnalysisReportValidator.php`
- `app/Auth/LegacyRoleBackfill/AnalysisUser.php`
- `app/Auth/LegacyRoleBackfill/ArtifactException.php`
- `app/Auth/LegacyRoleBackfill/BackfillException.php`
- `app/Auth/LegacyRoleBackfill/BackfillReceipt.php`
- `app/Auth/LegacyRoleBackfill/BackfillRefusalException.php`
- `app/Auth/LegacyRoleBackfill/BackfillResult.php`
- `app/Auth/LegacyRoleBackfill/CanonicalJson.php`
- `app/Auth/LegacyRoleBackfill/LegacyRoleBackfillAnalyzer.php`
- `app/Auth/LegacyRoleBackfill/LegacyRoleBackfillApplier.php`
- `app/Auth/LegacyRoleBackfill/LegacyRoleBackfillRollback.php`
- `app/Auth/LegacyRoleBackfill/PrivacyHasher.php`
- `app/Auth/LegacyRoleBackfill/PrivacyKeyException.php`
- `app/Auth/LegacyRoleBackfill/PrivateArtifactRepository.php`
- `app/Auth/LegacyRoleBackfill/RollbackReceipt.php`
- `app/Auth/LegacyRoleBackfill/RollbackResult.php`
- `app/Console/Commands/AuthzAnalyzeLegacyRolesCommand.php`
- `app/Console/Commands/AuthzBackfillLegacyRolesCommand.php`
- `app/Console/Commands/AuthzRollbackLegacyRolesCommand.php`

### Tests and documentation

- `tests/Feature/AuthzLegacyRoleBackfillTest.php`
- `docs/research/settings-performance/15-authz1c-analyzer-backfill-research.md`
- `docs/research/settings-performance/10-pending-decision-question-queue.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `prompts/README.md`
- this handoff

## Tests added

`tests/Feature/AuthzLegacyRoleBackfillTest.php` contains 26 focused Pest tests
covering deterministic five-role analysis, the raw invalid-value grammar,
multi-fault privacy reports, every package target row class, config/schema/key
drift, exact and mixed pre-existing apply, artifact attacks and concurrent
publication, independently recomputed report tampering, TOCTOU drift,
transaction rollback, deadlock retry recomputation, cache false/exception
recovery, prepared-before/planned crash paths, journal tampering, no-op,
receipt-scoped rollback/reapply, the five-role runtime matrix, command
contracts, and the dormant package boundary.

Every test uses SQLite `:memory:`, owns its users and temporary private artifact
root, calls `Http::preventStrayRequests()`, and uses `Mail::fake()`.

## Commands and results

### Preflight, source, and framework evidence

- `git status --short --branch`, `git rev-parse HEAD`, ancestry checks, and
  recent-log inspection: passed; clean `main`, exact starting HEAD
  `1716d0c6142e5f1d35d9baab33b6cb78d91af47e`, required ancestors present.
- Mandatory full Markdown reads plus targeted `rg`/`sed` source inspection:
  passed; prompt version matched exactly and no contract contradiction appeared.
- Laravel Boost application information and version-aware transaction,
  deadlock, Artisan, filesystem, and database-testing searches: passed.
- Installed source inspection: Permission 7.3.0 cache reset returns the cache
  forget boolean; package model events reset cache, so the implementation uses
  query-builder writes; teams and package Gate registration remain disabled.
- Baseline command over the five required authorization files: passed twice on
  SQLite `:memory:`; settled result 124 tests / 3,496 assertions.

### Scaffolding and iteration

- The three exact `php artisan make:command ... --no-interaction` commands and
  `php artisan make:test --pest AuthzLegacyRoleBackfillTest --no-interaction`:
  passed.
- PHP syntax scan over new PHP files: passed.
- Iterative `vendor/bin/pint --dirty` runs: passed; formatting fixes were
  applied where reported.
- Focused Pest iteration failures were retained as evidence: privacy numeric-ID
  scan plus missing Filament imports; two multi-transcription expectations;
  one Admin expectation; command receipt basename/absence; canonical table
  comparison; and an unprimed cache-forget false result. Each was corrected
  before the next run.
- First settled focused implementation: 18 tests / 211 assertions, passed.
- Expanded pre-hardening suite: 21 tests / 251 assertions, passed.
- Required deadlock test initially failed because the Pest outer transaction
  intentionally converts nested concurrency errors to `DeadlockException`;
  the test now exits/restores only its test-owned outer SQLite transaction so
  Laravel's three-attempt top-level retry path is exercised. No wrapper or
  production behavior was changed.
- Final focused pre-documentation run: 26 tests / 298 assertions, passed.
- Combined authorization regression:
  `php artisan test --compact tests/Feature/AuthzFoundationCatalogTest.php tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php tests/Feature/AuthzLegacyRoleBackfillTest.php`
  passed 150 tests / 3,794 assertions.
- Repeated `git diff --check`: passed.

### Canonical final gate

- The first requirements-sweep expression failed because it compared the whole
  prompt-version line to only its required version prefix; the committed prompt
  continues that line with explanatory text. The corrected anchored prefix
  check plus scope, forbidden-surface, syntax, and `git diff --check` checks
  passed. This was a diagnostic command correction, not a contract mismatch.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed with 0 issues.
- `npm run build`: passed with Vite 8.1.0.
- The first sandboxed full `php artisan test` run reached 703 passing tests / 8
  browser failures because Chromium was denied macOS Mach bootstrap registration
  (`Permission denied (1100)`). No AUTHZ1-C failure was reported.
- The same full `php artisan test` command was rerun with the required sandbox
  escalation and passed 711 tests / 8,828 assertions in 335.939 seconds.
- Final post-documentation clean-state confirmation: requirements sweep passed;
  Pint passed; FilaCheck passed with 0 issues; build passed; full suite passed
  711 tests / 8,828 assertions last.

No full suite was interrupted or parallelized. The only tooling deviation was
the disclosed escalation needed to let the repository's existing browser tests
launch Chromium after the sandbox denial.

## Evidence planes and limitations

- SQLite `:memory:` proves raw grammar, deterministic reconciliation,
  transaction rollback/retry control flow, exact query-builder projection,
  cache ordering/recovery, idempotency, receipt rollback, and legacy parity.
- SQLite does not prove MySQL `FOR UPDATE` row/gap locks, empty-range insertion
  locks, real two-connection deadlocks, isolation enforcement, or concurrent
  role/source races. No MySQL claim is made.
- The implementation explicitly refuses non-test SQLite apply/rollback,
  unsupported drivers, and MySQL isolation weaker than or different from
  `REPEATABLE READ` / `SERIALIZABLE`.
- No development-database, production, live mail, live network, process,
  cache, or operational command probe was run.

## Security audit notes

The Filament security checklist was applied to the affected authorization
boundary. No Filament surface changed. Negative scans confirmed no package
Role/Permission model mutation, `HasRoles`, role/permission helper mutation,
grant/direct-grant write, logging/dumping, route exposure, or authority cutover.
Artifacts contain canonical role/ability names, counts, HMAC identifiers, and
non-secret hashes only; tests scan out user IDs, names, emails, raw corrupt
roles, and key material.

## Local Front Check Report

1. Open the existing Admin panel as a `super_admin`, click Users, Authors, and
   Admin Tools, and expect the same access as before AUTHZ1-C.
2. Open the existing Admin panel as an `admin`, click Authors and Admin Tools,
   and expect access; navigate to Users and expect denial.
3. Open the existing Admin panel as `moderator`, `transcriber`, and `user`, and
   expect Admin panel admission to remain denied.
4. Open `/horizon` as each role and expect only `super_admin` and `admin` to be
   admitted, exactly as before.
5. Enable the existing maintenance setting only in a disposable local test
   setup, open the public site as each role, and expect only `super_admin` and
   `admin` to retain the existing bypass.
6. Run `php artisan test --compact tests/Feature/AuthzLegacyRoleBackfillTest.php`
   and expect 26 passing tests; do not run AUTHZ1-C operational commands
   against the development or production database for this visual check.

## Explicit boundary confirmation

- No push was performed.
- No production read/write/report/backfill/cache/process action was performed.
- No local development database was queried or mutated.
- No dependency, npm package, worktree, subagent, or `filacheck --fix` was used.
- No `HasRoles`, authority cutover, compatibility grant, direct grant, policy,
  management UI, AUTHZ1-D–I, ARCH1, SP3D, MAINT-LW-UX1, or later slice was
  started.
- The maintenance follow-up remains preserved and independent at its original
  deadline: before the first later public Livewire expansion or AUTHZ1 final
  acceptance, whichever comes first.
