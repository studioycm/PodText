# AUTHZ Complexity Reset and Feature-First Master Plan

Date: 2026-07-17

Status: accepted and complete for AUTHZ; the bounded command closure shipped in
`0be807051a3e43d08b57bd214278ce95c42bda1b`

This document supersedes active instructions that require AUTHZ1-D–I, an
AUTHZ1-to-ARCH1 cutover sequence, or ARCH1 before ordinary Card Template UX
work. Reports 12–18 remain historical evidence of what was built and audited;
they are not an active implementation queue.

## Outcome

Keep the authorization behavior that already works, make the unused migration
commands unreachable in one small closure slice, and return development to
visible product features. Do not continue building a generalized role,
permission, cutover, or rollback platform that PodText does not currently need.

## What happened and what it teaches

AUTHZ began as a small distinction between Super Admin and Admin with room for
future permissions. Research then expanded the possible future into a 135-key
catalog, five role definitions, migration analyzers, keyed evidence, backfill,
rollback, cache recovery, independent audits, MySQL rehearsal planning, and an
AUTHZ1-D–I queue. The work was careful and reversible, but the projected path
grew to roughly 17 tasks and 21 hours before delivering present product value.

The durable lesson is about stage fit, not fault. A reversible future option is
not automatically a present requirement. Once a dormant migration path needs
production-grade recovery machinery and repeated audit cycles, the product
value must be rechecked before more architecture is accepted.

## Present requirements versus future options

| Present requirement | Attractive option, not current scope |
|---|---|
| Super Admin and Admin retain their current tested distinction. | Multiple additive roles per user. |
| Existing `users.role`, rank checks, Gates/macros, panel admission, Horizon, maintenance bypass, and Users Resource restrictions remain authoritative. | Direct permission grants. |
| Shield remains dormant and exposes no role-management UI. | Role/permission management UI. |
| Unused analyzer/backfill/rollback commands cannot be operated accidentally. | Extra panels or lower-role panel access. |
| Card Template editing gets a useful preview/side-panel experience using the working storage and writer. | Dynamic catalog governance and delegated grant administration. |
| Existing settings import locks remain simple and usable. | Production backfill/cutover, rollback service, or MySQL rehearsal. |
| Product work is selected for visible value and bounded complexity. | AUTHZ-dependent ARCH1 or a generalized versioning platform. |

## Existing behavior to preserve

Do not rewrite these working surfaces:

- ROLES1 already provides the `UserRole` enum, `users.role`, Super/Admin panel
  access, `super-admin` and `multi-transcription` Gates/macros, the Users
  Resource restrictions, and server-side hidden-setting guards.
- The legacy authorization matrix is tested independently of package rows.
- Shield 4.2.0 and Permission 7.3.0 are installed, configured, and deliberately
  unregistered. `User` does not use `HasRoles`; package rows do not grant runtime
  authority.
- The frozen ability catalog and role metadata exist as dormant foundation
  evidence. No present feature needs to consume or expand them.
- SP3A, S1b, and S1c already provide settings import-lock storage, server-side
  import enforcement, visible lock surfaces, add-only behavior, and inline lock
  controls.
- SP3B/SP3C already provide focused settings pages, a Card Template library,
  one-template editing, a focused fresh-snapshot writer, reference/default
  guards, and a Builder preview canary.
- Public Front v2 has working card renderers and template-driven content item,
  group, and contributor cards. Preview UX can build on those presenters.
- WB1 is complete. WB2–WB7 are optional future work and are not prerequisites
  for the feature-first restart.

## Smallest safe AUTHZ disposition

### Keep

- Keep legacy `users.role`, rank checks, Gates/macros, and current panel/resource
  authorization authoritative.
- Keep Shield unregistered, `HasRoles` absent, compatibility grants unapplied,
  and the existing package/catalog foundation dormant.
- Keep reports 12–18 and their handoffs as historical evidence.
- Keep the package migration and dormant support code unless removal is proven
  simpler than leaving it unreachable. Do not start dependency removal here.

### Withhold

The only runtime callers of the legacy-role migration services are the three
auto-discovered commands:

- `authz:roles:analyze`
- `authz:roles:backfill`
- `authz:roles:rollback`

The accepted closure deleted those three auto-discovered command classes and
asserted that they are absent from the Artisan command surface. It did not
repair, redesign, or replace their retained migration machinery.

### Audit findings H-01, M-01, and L-01

| Finding | Done-for-now disposition |
|---|---|
| H-01 keyed evidence can fabricate rollback ownership | Eliminate the reachable rollback surface. Accept the dormant support code only inside a non-operational boundary with no registered command or application caller. |
| M-01 rollback does not exact-compare complete apply lineage | Eliminate the reachable rollback surface. Do not harden unused rollback lineage. |
| L-01 mixed integer/string IDs alias in adapter lookup | Eliminate the reachable analyze/backfill command surface. Accept only the existing homogeneous database-ID assumption inside unreachable support code. |

If a real production migration is requested later, these findings reopen and a
new threat model is required. They do not justify more work while the migration
surface is deliberately unavailable.

## BQ1/BQ2 and AUTHZ-to-ARCH1 reset

BQ1 and BQ2 remain useful records of one possible future authorization model,
not current product requirements. The following are deferred/not-current:

- generalized multiple roles;
- direct grants;
- role-management UI and delegated role assignment;
- additional panels;
- dynamic ability-catalog governance;
- compatibility-grant application and package-authority cutover;
- production backfill, rollback, contraction, or MySQL rehearsal;
- any requirement that ARCH1 wait on AUTHZ1-D–I;
- any requirement that Card Template preview/slide-over UX wait on ARCH1; and
- speculative future-role or future-panel design.

ARCH1 and SP3D remain optional research directions. They require a concrete
current product problem, a fresh cost estimate, and operator approval. Existing
Card Template/Public Form storage stays authoritative until such a decision;
ordinary UX work must preserve its writer, lifecycle, locks, backups, and tests.

## AUTHZ done-for-now boundary

AUTHZ is done for now when all of the following are true:

- current Super Admin/Admin behavior remains green and authoritative;
- no role-management UI or lower-role panel access exists;
- no package authority cutover, compatibility grants, or `HasRoles` adoption
  occurs;
- Shield stays unregistered;
- the analyzer/backfill/rollback commands are absent from the runtime command
  surface;
- no application route, job, UI, or command calls the dormant migration
  services;
- H-01/M-01/L-01 are documented as outside the narrowed, non-operational threat
  boundary; and
- AUTHZ1-D–I, production migration machinery, and recursive independent audits
  are closed as active work.

## Roadmap reset

| Horizon | Work |
|---|---|
| Now | AUTHZ is done for now. Run the versioned Step 5B feature-first controller: first create a thorough docs-only specification prompt in one mini-task, then run that prompt in a separate mini-task to prepare the small Card Template preview/side-panel UX specification. |
| Later | Small Public Front improvements selected one at a time; measured P2/P3 work; `MAINT-LW-UX1` before its qualifying public Livewire trigger; `WB-PROBE-HF1` plus the private Google probe if Workbench resumes; LENS review packs; conditional SP3 browser evidence; production settings/cache/mail verification; an optional suggestions-only project simplification audit; dashboard or other visible features at operator choice. |
| Not now | AUTHZ1-D–I, multiple-role/direct-grant governance, role UI, extra panels, package cutover, production backfill/rollback/MySQL rehearsal, ARCH1 migration, SP3D calibration, SP4/LOG1, broad WB2–WB7 construction, or a large replacement architecture. |

The existing import-lock implementation is an asset, not a new architecture
project. Card Template preview should render normalized unsaved editor state in
a slide-over or adjacent panel through existing controlled presenters; it should
not introduce versioned aggregates, autosave collaboration, new permissions, or
a storage migration.

The complete surviving-work and deferred-work tracking registers live in
`10-pending-decision-question-queue.md`. That register preserves non-AUTHZ
checkpoints without restoring their old automatic sequencing.

## Laravel Simplifier closure record

The required Stage 1 audit ran as `LS-20260717-AUTHZ-01`; the operator then
approved Option `AUTHZ-CLOSE-O1-DELETE-3` in a new message. The bounded closure
shipped and passed its canonical gate. No further AUTHZ Simplifier audit,
remediation cycle, or approval is pending.

The two-stage rule still applies to a later Step 5B **implementation**, but not
to the docs-only specification now selected. After the specification is
reviewed, any PHP implementation starts with a fresh read-only Stage 1 audit
for that exact scope and stops for ID-bound operator approval.

A separate project-wide simplification-opportunities audit may be selected
later. It is read-only and suggestions-only: findings must be classified as
required now, safety/correctness, optional robustness, or future-only; none may
become implementation scope without review, analysis, a bounded estimate, and
fresh operator approval. It does not block the AUTHZ exit or Step 5B.

## Complexity budget and stop rule

Before accepting any future architecture or “small” feature, publish the likely
task count, elapsed-effort range, dependencies, and audit burden.

- Default small-slice budget: one implementation prompt, at most two logical
  tasks, and at most four estimated engineering hours.
- If the forecast exceeds either two tasks or four hours, stop and obtain fresh
  operator approval before planning more detail.
- Prefer reversible choices that fit the current stage and preserve working
  behavior.
- Permit one audit/remediation cycle, then recheck product value. A second
  remediation or independent-audit loop requires explicit operator reapproval.
- Disable or remove unused destructive surfaces before hardening hypothetical
  operations.
- Do not design speculative roles, panels, delegation, migrations, or
  collaboration systems without a present feature that needs them.
- If a requested UX improvement begins to require new persistence architecture,
  split the UX from the architecture and ship the bounded UX first when safe.

## One next action

Run `prompts/pre-13-prompts/step5b-feature-first-controller-codex-prompt.md`
v1. The controller has exactly two sequential docs-only mini-tasks:

1. open a task that writes and commits one thorough v1 prompt for preparing the
   Step 5B Card Template Preview UX specification; then
2. after that task leaves a clean checkout, open a separate task that executes
   the new prompt and prepares the specification.

Neither task may change application code, start a Simplifier implementation
audit, or absorb ARCH1, SP3D, `MAINT-LW-UX1`, persistence, autosave, revisions,
permissions, or generalized preview architecture. The controller stops after
the specification and returns it for operator review.

## Acceptance checklist

- [x] Reports and handoffs 12–18 remain unchanged as historical evidence.
- [x] Legacy authorization remains the active authority.
- [x] Shield remains dormant; no role UI or package cutover is planned.
- [x] The closure withheld the three runtime commands with focused regressions
  and minimum docs.
- [x] H-01/M-01/L-01 are accepted only inside the narrowed non-operational
  boundary and reopen if migration capability returns.
- [x] BQ1/BQ2 are future options, not current requirements.
- [x] AUTHZ1-D–I and AUTHZ-to-ARCH1 sequencing are no longer active.
- [x] Card Template preview/side-panel UX may proceed on current storage.
- [x] The two-task/four-hour complexity stop rule is accepted.
- [x] The AUTHZ closure passed its approved Simplifier audit and canonical gate.
- [x] Step 5B begins with a docs-only specification, not implementation.

## Decision record and next authorization

The operator accepted and completed the bounded AUTHZ exit, then selected the
Step 5B specification as the next work. The current authorization is docs-only:
run the v1 controller, produce the specification, and stop for review. It does
not authorize Step 5B PHP implementation, operational AUTHZ commands, database
work, dependency changes, or any AUTHZ1-D–I/ARCH1/SP3D work.
