# AUTHZ1-C Independent Analyzer / Backfill Audit Handoff

Task ID: `AUTHZ1-C-AUDIT-01`

Date: 2026-07-17

Audited HEAD: `f3cb779a7f12d48ac583cd12eeb5718a88f4ab95`

Implementation audited: `0147ea83947ccedb1336a71d8eecc887eb8d4e07`

Closeout audited: `628429236c138ad51fcb4b6d8311ad4726afb439`

Decision: **a separate remediation prompt is required before any AUTHZ1-D
planning**

## Outcome

The independent audit found one High, four Medium, and two Low findings. The
focused and adjacent SQLite `:memory:` tests are green, and the ordinary
fail-closed analyzer, exact five-role projection, legacy runtime parity,
artifact privacy, and refusal behavior have meaningful local evidence.

Local AUTHZ1-C acceptance is not supported because the current receipt does
not prove ownership of the physical role/pivot tuples rollback may delete, an
absent permission-cache key can trap post-commit completion, strict exactly-
once cache reset evidence has a crash gap, rollback has a commit-to-receipt
crash gap, and operational schema validation omits constraints and indexes.

Controlling report:
`docs/research/settings-performance/16-authz1c-independent-analyzer-backfill-audit.md`.

## Finding summary

| ID | Severity | Finding | Disposition |
| --- | --- | --- | --- |
| H-01 | High | Rollback ownership is semantic, not bound to the physical role/pivot tuples that the receipt may delete. | Remediation required before AUTHZ1-D planning. |
| M-01 | Medium | A real cache store returns `false` for an absent key, which can make changed-state completion fail forever. | Remediation required. |
| M-02 | Medium | Cache invalidation success and durable cache journal publication are not atomic, so strict exactly-once reset is not achieved. | Remediation or an explicitly amended at-least-once contract is required. |
| M-03 | Medium | A crash after rollback commit but before rollback receipt publication is not recoverable by rerun. | Remediation required. |
| M-04 | Medium | Runtime schema drift validates column names but not types, indexes, keys, or foreign-key behavior required by the writer/locks. | Remediation required. |
| L-01 | Low | Nonempty but cipher-invalid/short APP_KEY material is accepted as the privacy HMAC source. | Tighten validation in remediation. |
| L-02 | Low | The original handoff's proof claim is broader than the direct focused-test matrix. | Correct the evidence claim and add focused cases. |

The audit report contains an independent remediation plan. It is a finding-
level specification only, not an implementation or remediation prompt.

## Requirement classification

### Supported locally

- Byte-exact native-string raw-role validation and ordinary complete issue
  enumeration.
- Zero authorization database writes and zero permission-cache reset on the
  tested analyzer and pre-commit refusal paths.
- Exactly one protected same-slug role assignment per valid user in the
  ordinary valid workflow, with no default user role, extra role, grant, or
  direct permission.
- Independent report reconciliation, private non-overwriting artifact paths,
  HMAC user identities, symlink refusal, and deterministic semantic
  fingerprints in the tested matrix.
- Retry callback recomputation and transaction rollback control flow on
  SQLite.
- Five-role legacy runtime parity, continued legacy authority, no `HasRoles`,
  no package grant writer, and no authority cutover.
- Static refusal output, generic unexpected-error output, and explicit exit
  codes without raw role/user leakage in the inspected commands.

### Remediation required

- Physical receipt ownership and role-ID drift protection: H-01.
- Real absent-cache completion behavior: M-01.
- Truthful crash-consistent cache completion semantics: M-02.
- Crash-recoverable rollback publication: M-03.
- Constraint/index/type-complete runtime schema contract: M-04.
- Cipher-valid privacy-key validation and deeper artifact DTO tests: L-01 and
  L-02.

### Deferred by authority boundary

- Disposable two-connection MySQL rehearsal.
- InnoDB row/gap/next-key lock proof, production isolation, real concurrent
  insertion/source-mutation blocking, and MySQL deadlock retries.
- Production cache-store and filesystem failure rehearsal.

### Not applicable to this audit

- Application, test, config, migration, package, translation, or frontend
  fixes.
- Operational analyzer/backfill/rollback execution.
- AUTHZ1-D–I, authority cutover, compatibility grants, management UI, ARCH1,
  SP3D, or MAINT-LW-UX1 implementation.

## Files changed

- `docs/research/settings-performance/16-authz1c-independent-analyzer-backfill-audit.md`
- `docs/phase-02/authz1c-independent-analyzer-backfill-audit-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/settings-performance/10-pending-decision-question-queue.md`

No application, test, config, migration, dependency, package, translation, or
frontend file changed.

## Tests added or updated

None. This task authorized an independent audit and Markdown updates only.

## Commands and results

### Preflight and inspection

- `git status --short --branch` — PASS; clean `main`, initially ahead 16.
- `git rev-parse HEAD` — PASS; exact expected
  `f3cb779a7f12d48ac583cd12eeb5718a88f4ab95`.
- Ancestry checks for `0147ea83947ccedb1336a71d8eecc887eb8d4e07`
  and `628429236c138ad51fcb4b6d8311ad4726afb439` — PASS.
- Source, installed-package, migration, prompt, research, plan, handoff, state,
  ledger, queue, foundation, and maintenance-audit reads — completed.
- Laravel Boost application/version inspection and installed-version Laravel
  transaction/console guidance — completed.
- `php -r` Composer installed-version probe — FAILED with a local PHP parse
  error caused by an over-escaped namespace in the read-only one-liner; no
  repository or database state changed.
- `composer show spatie/laravel-permission` — PASS; confirmed installed 7.3.0
  without changing dependencies.

### SQLite `:memory:` tests

- `php artisan test --compact tests/Feature/AuthzLegacyRoleBackfillTest.php` —
  PASS, 26 tests / 298 assertions.
- `php artisan test --compact tests/Feature/AuthzFoundationCatalogTest.php tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php`
  — PASS, 124 tests / 3,496 assertions.

The tests ran sequentially. The repository canaries required testing mode,
SQLite, and database `:memory:`. The focused suite prevents stray HTTP and
fakes mail. No operational AUTHZ1 command or persistent database was used.

### Documentation checks

- `git diff --check` and `git diff --cached --check` — PASS after the final
  Markdown state.
- `git status --short` — PASS; exactly five authorized Markdown paths staged.

FilaCheck, Pint, npm build, and the full application suite were not applicable
to this Markdown-only audit; no implementation file changed.

## SQLite / MySQL evidence boundary

SQLite and exact source inspection support the local control-flow and semantic
claims recorded above. They do not prove MySQL locking of empty ranges,
InnoDB index/gap behavior, production isolation, two-connection races,
deadlocks, cache-store behavior, or production filesystem behavior. No MySQL
proof is claimed. The future MySQL rehearsal remains separately approved and
must not be treated as a substitute for the required C remediation.

## Assumptions

- The exact requested HEAD and ancestral commits define the implementation
  under audit.
- A same-service-account artifact writer is inside the relevant integrity
  threat boundary because journals use only an unkeyed recomputable checksum.
- Permission cache absence is a normal state while package authorization is
  intentionally dormant.

## Deferred issues and next action

1. Review the audit report and the seven finding dispositions.
2. Author and approve a separate AUTHZ1-C remediation prompt; do not treat this
   audit report as executable authority.
3. Keep AUTHZ1-D–I stopped until that remediation is implemented and accepted.
4. Keep the disposable MySQL rehearsal as a separate future gate.
5. Preserve `MAINT-LW-UX1` at its existing independent deadline: before the
   first later public Livewire expansion or AUTHZ1 final acceptance, whichever
   comes first.

## Local review steps

1. Open the audit report and verify each finding's source lines, failure path,
   disposition, and remediation section.
2. Open this handoff and expect decision 2 with one High, four Medium, and two
   Low findings.
3. Open current state, the mini-step ledger, and the checkpoint queue and
   expect AUTHZ1-D–I to remain stopped pending a separate C remediation prompt.
4. Confirm the MAINT-LW-UX1 timing is unchanged and remains independent.
5. Run `git show --stat --oneline HEAD` after handoff and expect Markdown-only
   paths.
6. Run `git status --short --branch` and expect a clean checkout after the
   audit commit.

## Commit hash

This handoff belongs to the single authorized docs commit with subject
`docs: audit authz1c analyzer backfill`. Its exact hash is returned in the task
final; a second hash-stamp commit is outside this audit's one-commit authority.

## Explicit safety confirmation

- No implementation, test, config, migration, dependency, package,
  translation, or frontend change occurred.
- No operational analyzer/backfill/rollback command ran.
- No local development database, persistent SQLite database, MySQL database,
  or production system was accessed or mutated.
- No cache reset, process action, authority cutover, grant, direct permission,
  `HasRoles`, management UI, AUTHZ1-D–I, ARCH1, SP3D, or MAINT-LW-UX1 work
  occurred.
- No push occurred.
