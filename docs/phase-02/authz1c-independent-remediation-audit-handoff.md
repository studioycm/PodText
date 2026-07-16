# AUTHZ1-C Independent Remediation Audit Handoff

Task ID: `AUTHZ1-C-REMEDIATION-AUDIT-01`

Date: 2026-07-17

Audited HEAD: `de68d29187f1d8445c065109aa13101e8d5ed707`

Remediation implementation audited:
`0cdf8390b67d260ced54a0ca2ad58600bd475da5`

Decision: **separate follow-up required**

## Outcome

The remediation substantially closes R-01–R-05, and both required SQLite
`:memory:` suites are green. One High coherent ownership-substitution gap, one
Medium exact-lineage gap, and one Low mixed-identity adapter gap remain, so
complete local remediation acceptance is not supported yet.

Controlling report:
`docs/research/settings-performance/18-authz1c-independent-remediation-audit.md`.

Legacy `users.role`, ranks, Gates/macros, panel/Horizon/maintenance admission,
User Resource restrictions, and existing callers remain authoritative.
AUTHZ1-D–I remain blocked and unstarted.

## Finding summary

| ID | Severity | Finding | Disposition |
| --- | --- | --- | --- |
| H-01 | High | A coherent keyed replacement of `completed_unowned` receipt/complete evidence can fabricate proven ownership and make rollback delete externally created planned pivots. | Restore an ownership trust anchor or explicitly narrow and accept the threat model before AUTHZ1-D–I. |
| M-01 | Medium | Rollback compares selected pending/cache/complete fields instead of exact transition lineage from prepared evidence and the canonical receipt. | Separate follow-up required before AUTHZ1-D–I. |
| L-01 | Low | Integer and string IDs with the same textual value receive distinct HMACs but alias in the public source adapter's internal target lookup. | Narrow or make the adapter contract type-safe in the separate follow-up. |

M-01 by itself does not widen an authentic receipt's physical delete set:
rollback separately requires exact current state, protected role-ID identity,
current user-HMAC resolution, physical tuple HMAC, and exact tuple existence.
H-01 shows those checks can validate ownership fabricated coherently across a
substituted receipt/complete pair. L-01 was not shown on the operational
homogeneous database-ID path, and fresh locked reanalysis remains fail closed
before apply.

## Requirement classification

### Supported locally

- Keyed v2 exact/deep DTO shapes, immutable v1 refusal, private publication,
  family domain separation, and the inclusive 10 MiB boundary.
- Exact prepared recomputation from the validated report.
- Actual role/pivot physical ownership, role-ID recreation refusal, canonical
  receipt behavior on the authentic ordinary path, and preservation of pre-
  existing rows.
- `completed_unowned` semantics and rollback prohibition.
- Permission 7.3-compatible store resolution, truthful present/absent outcomes,
  pending/success evidence, durable at-least-once retry, and receipt-backed
  no-repeat behavior.
- Rollback prepared-before-delete, recovered zero-delete completion, partial-
  state refusal, role preservation, and no rollback cache reset.
- Complete required SQLite schema descriptors and pure MySQL expected
  descriptors for columns, properties, indexes, foreign keys, references, and
  actions.
- Laravel 13.19-compatible APP_KEY/cipher validation.
- Static/generic command failures, five-role legacy parity, dormant package
  authority, no `HasRoles`, and no grants/cutover.

### Separate follow-up required

- Durable ownership proof under the contract's coherent keyed receipt/journal
  substitution boundary, or an explicitly narrowed and accepted threat model:
  H-01.
- Exact pending/cache/complete lineage validation on the rollback entry path:
  M-01.
- Type-safe or explicitly narrowed native int/string source-adapter identity
  lookup: L-01.

### Deferred by authority boundary

- Disposable two-connection MySQL rehearsal.
- InnoDB row/gap/next-key/empty-range locks, deployed isolation, real deadlock
  retries, and multi-connection TOCTOU evidence.
- Production cache-store and artifact-filesystem race/failure rehearsal.

### Not applicable to this audit

- Application, test, config, migration, dependency, translation, or frontend
  changes.
- An implementation prompt or follow-up implementation.
- Operational analyze/backfill/rollback execution.
- AUTHZ1-D–I, grants/cutover, management UI, ARCH1, SP3D, or MAINT-LW-UX1
  implementation.

## Files changed

- `docs/research/settings-performance/18-authz1c-independent-remediation-audit.md`
- `docs/phase-02/authz1c-independent-remediation-audit-handoff.md`
- `docs/research/settings-performance/17-authz1c-audit-remediation-research.md`
- `docs/research/settings-performance/17-authz1c-audit-remediation-implementation-plan.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/settings-performance/10-pending-decision-question-queue.md`
- `prompts/README.md`

No application, test, config, migration, dependency, translation, or frontend
file changed.

## Tests added or updated

None. This task authorized an independent audit and Markdown-only corrections.

## Commands and results

### Preflight and inspection

- `git status --short --branch` — PASS; clean `main`, initially ahead 20.
- `git rev-parse HEAD` — PASS; exact expected
  `de68d29187f1d8445c065109aa13101e8d5ed707`.
- Ancestry check for `0cdf8390b67d260ced54a0ca2ad58600bd475da5` —
  PASS.
- Required prompt/version, research, plan, handoffs, state, ledger, queue,
  source, tests, migrations, config, vendor Laravel/Permission/cache/schema,
  catalog, and legacy-authority reads — completed.
- Laravel Boost application/version inspection and installed-version
  transaction/schema/encryption/cache/Pest guidance — completed; no database
  schema query was used.
- A read-only `rg` exception/output scan initially named three command files
  incorrectly and exited 2 for those paths; the corrected scan used the actual
  `Authz*LegacyRolesCommand.php` paths and completed. No state changed.
- The first exact-path `git add` attempt failed because the sandbox denied
  creation of `.git/index.lock`; the same exact eight-path staging command was
  approved and then succeeded. No broader path was staged.

### SQLite `:memory:` tests

- `php artisan test --compact tests/Feature/AuthzLegacyRoleBackfillTest.php` —
  PASS, 56 tests / 555 assertions.
- `php artisan test --compact tests/Feature/AuthzFoundationCatalogTest.php tests/Feature/AuthzPackageFoundationTest.php tests/Feature/LegacyAuthorizationMatrixTest.php tests/Feature/PanelAuthHardeningTest.php tests/Feature/PublicMaintenanceModeTest.php`
  — PASS, 124 tests / 3,496 assertions.

The tests ran sequentially. Repository canaries enforce testing mode, SQLite,
and `:memory:`. The focused suite prevents stray HTTP, fakes mail, and owns its
private artifacts. No operational AUTHZ command or persistent database ran.

### Documentation checks

- `git diff --check` — PASS on the final unstaged Markdown state.
- `git diff --cached --check` — PASS after exact-path staging.
- authorized staged-path audit — PASS; exactly the eight Markdown paths in
  this handoff's file list are staged.
- `git status --short` — PASS before commit; exactly those eight paths are
  staged and there are no unstaged changes.

Pint, FilaCheck, npm build, and the full application suite are not applicable
to this Markdown-only audit; no implementation file changed.

## SQLite / MySQL evidence boundary

SQLite and exact source inspection support the local findings and supported
claims above. They do not prove deployed MySQL schema normalization, InnoDB
locking, transaction isolation, two-connection races, deadlock behavior,
production cache behavior, or production filesystem behavior. No MySQL proof
is claimed or executed. The disposable rehearsal remains separately gated.

## Assumptions

- Exact requested HEAD and ancestry define the remediation under audit.
- A same-service-account artifact writer remains inside the integrity threat
  boundary used by audit 16; repository file modes permit the owner to replace
  artifacts, and the service account can access the application key needed to
  create valid replacement MACs.
- Operational database IDs are hydrated homogeneously; the mixed native-type
  alias is confined to the public source-row adapter unless future callers
  widen that boundary.

## Deferred issues and next action

1. Resolve or explicitly narrow H-01, M-01, and L-01 in a separately authorized
   follow-up; this audit does not provide an implementation prompt.
2. Keep AUTHZ1-D–I stopped until that follow-up is independently accepted.
3. Keep the disposable MySQL rehearsal as a separate future gate.
4. Preserve `MAINT-LW-UX1` at its existing independent deadline: before the
   first later public Livewire expansion or AUTHZ1 final acceptance, whichever
   comes first.

## Local Front Check Report

1. Open the audit report and verify the decision reads **separate follow-up
   required** with exactly H-01, M-01, and L-01.
2. Open the remediation research and implementation plan and expect their
   headers to record completed implementation/closeout and this audit outcome
   while preserving the historical planning body.
3. Open current state, the ledger, queue, and prompt index and expect
   AUTHZ1-D–I to remain blocked and legacy authority to remain active.
4. Confirm `MAINT-LW-UX1` retains its existing independent deadline.
5. Run `git show --stat --oneline HEAD` after commit and expect only the eight
   authorized Markdown paths listed above.
6. Run `git status --short --branch` and expect a clean checkout.

## Commit hash

This handoff belongs to the single authorized docs commit with subject
`docs: audit authz1c remediation`. Its exact hash is returned in the task
final; a second hash-stamp commit is outside this audit's one-commit authority.

## Explicit safety confirmation

- No implementation, test, config, migration, dependency, translation, or
  frontend change occurred.
- No operational analyzer/backfill/rollback command ran.
- No local development database, persistent SQLite database, MySQL database,
  or production system was accessed or mutated.
- No cache reset, process action, authority cutover, grant, direct permission,
  `HasRoles`, management UI, AUTHZ1-D–I, ARCH1, SP3D, or MAINT-LW-UX1 work
  occurred.
- No push occurred.
