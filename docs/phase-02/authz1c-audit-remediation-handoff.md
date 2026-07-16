# AUTHZ1-C Audit Remediation Handoff

Date: 2026-07-17
Task: `AUTHZ1-C-REMEDIATION-EXEC-01`
Prompt: `prompts/pre-13-prompts/authz1c-audit-remediation-codex-prompt.md`
Prompt version: v1 — 2026-07-17
Status: implementation complete; independent review still required
Authority: legacy `users.role` authorization remains authoritative

## Commit hash

`0cdf8390b67d260ced54a0ca2ad58600bd475da5 fix: remediate authz1c audit findings`

## Outcome

Audit findings R-01–R-05 are remediated inside the existing AUTHZ1-C analyzer,
apply, cache, artifact, and rollback boundary. The implementation produces only
deeply validated v2 artifacts, proves physical ownership only in the current
uninterrupted post-transaction call, completes ambiguous prepared/exact-planned
restarts as non-rollback-capable `completed_unowned`, journals truthful
at-least-once permission-cache invalidation, journals rollback before deletion,
and validates a normalized runtime schema contract.

Legacy `users.role`, `UserRole`, ranks, Gates/macros, panel/Horizon/maintenance
admission, User Resource restrictions, and every existing caller remain the
runtime authority. `User` still lacks `HasRoles`; no permission grants,
cutover, management UI, or policy migration was introduced.

## Requirements disposition

### Implemented

1. **R-01 — physical tuple ownership:** v2 receipts bind actual inserted role
   IDs, the protected role-ID map, semantic assignment hashes, and physical
   tuple HMACs over role ID, typed raw model ID, and model type. Raw model IDs
   never enter artifacts. Role-ID recreation, substituted evidence, or tuple
   drift refuses before deletion.
2. **R-01 — conservative restart:** a valid prepared journal plus exact planned
   database state performs zero writes and publishes `ownership_status=unproven`,
   `rollback_capable=false`, empty owned vectors, and status
   `completed_unowned`. Its receipt cannot be rolled back. Initial
   `already_applied` without prepared evidence remains a no-receipt/no-cache
   no-op.
3. **R-02 — cache truth:** `PermissionCacheInvalidator` resolves the Permission
   7.3.0 store/key, checks presence before and after the registrar call, reports
   `deleted` or `already_absent`, and fails on store exceptions or a remaining
   key. Keyed pending/success journals make the protocol durable
   at-least-once and idempotent; receipt-backed rerun makes no call.
4. **R-03 — rollback recovery:** rollback requires the canonical stored proven
   v2 receipt, publishes keyed prepared evidence before deletion, deletes only
   exact owned pivots, validates planned state, recovers an already-planned
   state with zero deletes, refuses partial state, preserves role rows and
   pre-existing pivots, and never invalidates permission cache.
5. **R-04 — complete runtime schema contract:** the analyzer records and hashes
   deterministic driver-specific descriptors from Laravel `getColumns()`,
   `getIndexes()`, and `getForeignKeys()` for all five Permission tables plus
   users `id`/`role`. Column properties, PK, unique, secondary-index, FK,
   reference/action, users default/index, configured table, and morph drift
   block mutation with granular issues.
6. **R-05 — key validation:** APP_KEY parsing matches Laravel's installed
   non-strict `base64_decode` semantics and requires
   `Encrypter::supported($bytes, $cipher)` before HKDF. AES-128/256 CBC/GCM,
   malformed, empty, 15/17/31/33-byte, unsupported-cipher, canonical, and
   non-canonical base64 paths have direct tests with generic secret-free
   failures.
7. **R-05 — deep artifacts and boundaries:** analysis, operation, rollback
   operation, backfill receipt, and rollback receipt DTOs enforce exact keys,
   native scalar types, enum values, timestamps, ordered unique vectors,
   counts, lineage, digests, and HMACs. Invalid identity adapter types are
   distinct blockers; empty source is valid; 10 MiB payload plus publication
   newline is accepted through the size gate and one byte over refuses.
8. **V2/v1 contract and commands:** new names contain `.v2.` where derived;
   retained v1 families refuse with exit 2 and a fresh-v2 instruction. Backfill
   output includes status, receipt, ownership, rollback capability, and cache
   outcome without per-user data.
9. **Truth correction:** research 17 contains implementation evidence and the
   original AUTHZ1-C handoff has a dated correction that preserves historical
   counts while superseding its ownership, exactly-once, rollback-crash,
   schema-completeness, and exhaustive-test claims.

### Already existed and preserved

- Permission 7.3.0 schema, exact five-role metadata, 135-ability catalog,
  compatibility manifest, and reversible AUTHZ1 foundation.
- Raw query-builder source analysis, private immutable artifact directories,
  three-attempt transaction boundary, no-repair projection, exact acceptance
  fingerprints, private output, and receipt-scoped role-preserving rollback.
- Complete five-role legacy access behavior and dormant package authority.

### Deferred by contract

- Independent review/acceptance of this remediation before AUTHZ1-D.
- Disposable two-connection MySQL rehearsal. The pure MySQL descriptor does not
  prove MySQL row/gap locks, empty-range locks, concurrent inserts, isolation,
  deadlock behavior, or multi-connection TOCTOU safety.
- Any production report, backup, apply, rollback, cache, process, or deployment
  action; any local-development-database rehearsal.
- AUTHZ1-D–I, compatibility grants, authority cutover, `HasRoles`, policy/Gate
  migration, role UI, ARCH1, SP3D, and MAINT-LW-UX1 implementation.

### Not applicable

- No migration, dependency, Composer/npm file, config, model, enum, provider,
  route, middleware, Gate, policy, Filament surface, translation, job, queue,
  mail, or environment file required a change.
- No browser/visual UI change required a screenshot or visual regression.

### Blocked

- None for the local R-01–R-05 implementation. AUTHZ1-D remains intentionally
  blocked on independent remediation review; MySQL remains a separate boundary.

## Files changed

### Application and commands

- `app/Auth/LegacyRoleBackfill/AnalysisIssue.php`
- `app/Auth/LegacyRoleBackfill/AnalysisReport.php`
- `app/Auth/LegacyRoleBackfill/AnalysisReportValidator.php`
- `app/Auth/LegacyRoleBackfill/ArtifactVersionException.php`
- `app/Auth/LegacyRoleBackfill/BackfillReceipt.php`
- `app/Auth/LegacyRoleBackfill/BackfillRefusalException.php`
- `app/Auth/LegacyRoleBackfill/BackfillResult.php`
- `app/Auth/LegacyRoleBackfill/LegacyRoleBackfillAnalyzer.php`
- `app/Auth/LegacyRoleBackfill/LegacyRoleBackfillApplier.php`
- `app/Auth/LegacyRoleBackfill/LegacyRoleBackfillRollback.php`
- `app/Auth/LegacyRoleBackfill/LegacyRoleBackfillSchemaContract.php`
- `app/Auth/LegacyRoleBackfill/OperationJournal.php`
- `app/Auth/LegacyRoleBackfill/PermissionCacheInvalidationOutcome.php`
- `app/Auth/LegacyRoleBackfill/PermissionCacheInvalidator.php`
- `app/Auth/LegacyRoleBackfill/PrivacyHasher.php`
- `app/Auth/LegacyRoleBackfill/PrivateArtifactRepository.php`
- `app/Auth/LegacyRoleBackfill/RollbackOperationJournal.php`
- `app/Auth/LegacyRoleBackfill/RollbackReceipt.php`
- `app/Console/Commands/AuthzAnalyzeLegacyRolesCommand.php`
- `app/Console/Commands/AuthzBackfillLegacyRolesCommand.php`

### Test and documentation

- `tests/Feature/AuthzLegacyRoleBackfillTest.php`
- `docs/research/settings-performance/17-authz1c-audit-remediation-research.md`
- `docs/phase-02/authz1c-analyzer-backfill-handoff.md`
- `docs/phase-02/authz1c-audit-remediation-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/settings-performance/10-pending-decision-question-queue.md`
- `prompts/README.md`

## Tests added and updated

`tests/Feature/AuthzLegacyRoleBackfillTest.php` is the sole affected test file.
It now contains 56 Pest cases / 555 assertions, including named cipher/key
datasets and direct R-01–R-05 regressions for physical ownership, external
projection, prepared recomputation, present/absent/error cache states,
success-before-publication retry, rollback crash recovery/partial refusal,
schema properties/indexes/FKs/users source, deep nested type confusion, v1
refusal, empty source, invalid identities, size boundaries, exact command
output, dormant package authority, and unchanged five-role runtime parity.

Every test owns SQLite `:memory:` fixtures and its private temporary artifact
root. File-level setup calls `Http::preventStrayRequests()` and `Mail::fake()`.

## Commands and results

### Preflight, mandatory reads, and source evidence

- `git status --short --branch`, exact HEAD/ancestry/recent-log checks: passed
  from clean `main` at `721532a048cb601a9e02c855c0aced6c5f5d5993`;
  AUTHZ1-C/audit ancestors present and D–I unstarted.
- Mandatory repository, lessons, state, ledger, handoff, prompt, research,
  plan, audit, source, migration, installed-package, and skill reads: passed;
  prompt version matched exactly.
- Laravel Boost application info: Laravel 13.19.0, PHP 8.4, Pest 4.7.4; version-
  aware encryption/cache/schema/transaction docs returned. Installed Laravel
  and Permission source checks confirmed the controlling parser/cache behavior.
- Negative `rg` scans: no `HasRoles`, grant helper, authority cutover, or later
  AUTHZ implementation entered the changed application boundary.

### Baselines and iteration

- Baseline `php artisan test --compact tests/Feature/AuthzLegacyRoleBackfillTest.php`:
  passed 26 tests / 298 assertions.
- Baseline adjacent authorization regression command over the five required
  files: passed 124 tests / 3,496 assertions.
- PHP syntax scan over affected application/command files: passed.
- First focused implementation run: 23 passed with two expected old-contract
  failures and one old `cache_reset` journal error; tests were corrected to the
  v2 absent-cache and `completed_unowned` contract.
- Focused iterations passed 26/299, then 53/519, then exposed the initially
  final `BackfillRefusalException` inheritance error when v1 refusal loaded;
  making the refusal base extensible fixed the dedicated typed version error.
- Expanded focused run initially passed 54 with two deep-reconciliation test
  errors; the receipt substitution expectation and no-cache refusal mock were
  corrected. Settled focused result: 56 tests / 555 assertions, passed.
- Post-implementation adjacent authorization regression: passed 124 tests /
  3,496 assertions.
- Initial `vendor/bin/pint --test`: failed on three formatting/import-order
  files. Scoped `vendor/bin/pint ...` formatted them successfully.
- Iterative `vendor/bin/filacheck --dirty`: passed with 0 issues.
- Repeated `git diff --check`: passed.

### Canonical final gate

- Requirements sweep: passed; every prompt/research/plan item and R-01–R-05 is
  classified above, and forbidden-surface scans are clean.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed with 0 issues.
- `npm run build`: passed.
- The first sandboxed full `php artisan test` reached 733 passing application
  tests and eight browser failures because Chromium was denied macOS Mach
  bootstrap registration (`Permission denied (1100)`). No AUTHZ1-C test
  failed. The same command was rerun with the required sandbox escalation and
  passed 741 tests / 9,085 assertions.
- Final post-record confirmation on the tracked documentation state:
  `vendor/bin/pint --test`, `vendor/bin/filacheck`, `npm run build`, and full
  `php artisan test` last all passed; the full suite passed 741 tests / 9,085
  assertions.

No full suite was interrupted or parallelized. The only tooling deviation was
the disclosed escalation required for the existing browser tests after the
sandbox denial. No command touched the local development database, MySQL,
production, live network, or live mail.

## Evidence planes and remaining MySQL boundary

- SQLite `:memory:` proves deterministic raw-source/schema reconciliation,
  v2 DTO/MAC validation, transaction rollback/retry control flow, exact role/
  pivot projection, physical evidence capture, cache journal state machines,
  rollback prepared/recovery behavior, and legacy runtime parity.
- Array cache tests prove the configured in-process store's present, absent,
  remaining-key, exception, and repeat-after-crash semantics. They do not claim
  production Redis/database cache transport behavior.
- Pure expected-shape tests prove what the normalized MySQL schema descriptor
  must contain. No MySQL connection was opened. Row/gap locking, empty-range
  locking, two-connection races, isolation enforcement, deadlocks, and
  concurrent constraints remain the disposable rehearsal boundary.
- Filesystem tests prove the local private-mode, symlink, immutable-name,
  concurrent publication, HMAC, and 10 MiB code paths; they do not claim every
  production filesystem failure mode.

## Assumptions and deviations

- The monitoring task remained read-only as stated by the operator; this task
  was the sole repository writer.
- The security-audit skill's optional delegation pattern was not used because
  the exact prompt forbade subagents. Its review checklist was applied
  sequentially to the affected boundary.
- Formatting used scoped Pint after the required `--test` failure. No
  FilaCheck auto-fix was used.
- No unresolved implementation deviation remains.

## Local Front Check Report

1. Open the existing Admin panel as `super_admin`, navigate to Users, Authors,
   and Admin Tools, and expect the same access as before remediation.
2. Open the Admin panel as `admin`, navigate to Authors and Admin Tools, and
   expect access; navigate to Users and expect denial.
3. Open the Admin panel as `moderator`, `transcriber`, and `user`, and expect
   Admin panel admission to remain denied.
4. Open `/horizon` as each legacy role and expect only `super_admin` and
   `admin` to be admitted.
5. Enable the existing maintenance setting only in a disposable local test
   setup, open the public site as each role, and expect only `super_admin` and
   `admin` to retain the existing bypass.
6. Run `php artisan test --compact tests/Feature/AuthzLegacyRoleBackfillTest.php`
   and expect 56 passing tests; do not run any AUTHZ operational command for
   this check.
7. Inspect the generated test artifacts only inside the Pest-owned temporary
   root and expect v2 names, keyed journals, and no raw user IDs; do not inspect
   or mutate development/production artifact storage.

## Explicit safety confirmation

- No push was performed.
- No production command, SSH, Forge, process, cache, report, apply, rollback,
  migration, or file action was performed.
- No local development database was queried or mutated; no operational AUTHZ
  command was run outside its SQLite `:memory:` Pest coverage.
- No MySQL connection or rehearsal was opened.
- No dependency, Composer/npm file, worktree, subagent, `filacheck --fix`, or
  secret-bearing file was used.
- No `HasRoles`, permission/grant write, authority cutover, compatibility
  grant application, policy/Gate migration, role UI, AUTHZ1-D–I, ARCH1, SP3D,
  or MAINT-LW-UX1 implementation was started.
- AUTHZ1-D remains blocked pending independent remediation review. The
  disposable MySQL rehearsal remains separately gated, and MAINT-LW-UX1 keeps
  its prior independent deadline.
