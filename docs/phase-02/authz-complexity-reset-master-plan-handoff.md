# AUTHZ Complexity Reset Master Plan Handoff

Date: 2026-07-17

## Outcome

The active roadmap is reset from generalized AUTHZ migration work to a
feature-first sequence. Legacy `users.role`/Gates remain authoritative, Shield
remains dormant, AUTHZ1-D–I and AUTHZ-to-ARCH1 coupling are stopped, and the
three unused `authz:roles:*` commands are assigned to one future bounded closure
slice rather than another hardening cycle.

Controlling plan:
`docs/research/settings-performance/19-authz-complexity-reset-and-feature-first-master-plan.md`.

## Requirement classification

### Implemented in this docs reset

- Factual overrun analysis and durable stage-fit/complexity lessons.
- Present-versus-future requirement split.
- Inventory of working authorization, settings-lock, Card Template, Public
  Front, and WB foundations.
- Done-for-now disposition for AUTHZ and H-01/M-01/L-01.
- BQ1/BQ2 and AUTHZ-to-ARCH1 sequencing reset.
- Now/Later/Not now roadmap and two-task/four-hour stop rule.
- One next action and exact operator acceptance decision.
- Alignment of active state, ledger, queue, prompt index, feature map, and
  Public Front routing notices.

### Already existed and preserved

- ROLES1 Super Admin/Admin behavior and legacy authorization tests.
- Dormant Shield/Permission foundation, unregistered Shield plugin, and absent
  `HasRoles`.
- SP3A/S1b/S1c settings import locks and SP3B/SP3C template editing foundation.
- Historical reports and handoffs 12–18; their bodies were not changed.

### Deferred / not current

- AUTHZ1-D–I, multiple roles, direct grants, role-management UI, extra panels,
  catalog governance, package cutover, production backfill/rollback/MySQL
  rehearsal, ARCH1, SP3D, SP4, LOG1, and broad WB2–WB7 construction.
- Any implementation. No PHP, Blade, JS/CSS, test, migration, config,
  dependency, translation, prompt contract, or database change occurred.

## Files changed

- `docs/research/settings-performance/19-authz-complexity-reset-and-feature-first-master-plan.md`
- `docs/phase-02/authz-complexity-reset-master-plan-handoff.md`
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/research/settings-performance/10-pending-decision-question-queue.md`
- `docs/phase-02/feature-map.md`
- `prompts/README.md`

All are Markdown. Historical reports/handoffs 12–18 and older plans,
blueprints, indexes, and shipped handoffs were left unchanged.

## Tests

None added or run. This is documentation/research/planning only.

## Checks

- `git diff --check` — PASS on the final unstaged Markdown state.
- Markdown-only changed-path audit — PASS; exactly the eight files above.
- `git status --short` — PASS before staging; only the eight authorized
  Markdown paths were changed or added.

Pint, FilaCheck, npm build, and the PHP test suite are not applicable because no
implementation file changes.

## Single recommended next step

Operator accepts or revises the master reset. Acceptance authorizes drafting,
not executing, one closure prompt capped at removing/unregistering the three
`authz:roles:*` commands plus focused legacy-authority regression tests and
minimum docs.

## Unresolved decision

Operator decision: **accept** or **revise** the reset and its two-task/four-hour
complexity budget.

## Safety confirmation

- No operational AUTHZ command ran.
- No local, test-persistent, development, or production database was accessed.
- No implementation, dependency, translation, or prompt-contract change was
  made.
- No branch, worktree, subagent, parallel task, push, AUTHZ1-D–I, ARCH1, or
  SP3D work occurred.

## Commit

This handoff belongs to the single docs-only commit with subject
`docs: simplify authz and reset roadmap`. The exact commit hash is reported in
the task final; no second hash-stamp commit is authorized.
