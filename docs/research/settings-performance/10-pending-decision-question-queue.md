# Feature-First Decision and Checkpoint Queue

Date: 2026-07-17

Status: AUTHZ complexity reset accepted; bounded v1 closure prompt drafted,
implementation not authorized

Controlling plan:
`19-authz-complexity-reset-and-feature-first-master-plan.md`.

## Restart point

Read the controlling plan, current project state, and the ledger. Reports 12–18
and their handoffs are historical AUTHZ evidence, not an active remediation
queue. Do not restart at AUTHZ1-D, ARCH1, SP3D, or former Groups 16–29.

## One pending decision

Operator chooses whether to explicitly start
`prompts/pre-13-prompts/authz-command-closure-codex-prompt.md` v1 from the exact
clean planning commit. The accepted reset authorized drafting only; it did not
authorize implementation or operational commands.

## Accepted for now

- Legacy `users.role`, ranks, Gates/macros, panel/Horizon/maintenance admission,
  and Users Resource restrictions remain authoritative.
- Shield stays unregistered; `User` stays without `HasRoles`; no compatibility
  grants, package cutover, or role UI.
- Plan 20 and the v1 closure prompt may only remove the three auto-discovered
  `authz:roles:*` command classes, add focused closure/legacy-authority
  regressions, and update minimum docs.
- H-01/M-01/L-01 are outside the narrowed non-operational threat boundary once
  those commands are unreachable. They reopen if migration capability returns.
- Existing settings import locks and Card Template storage/writer remain in
  place. Card Template preview/side-panel UX does not depend on ARCH1.
- A small slice is capped at two logical tasks and four estimated engineering
  hours; exceeding either requires operator reapproval.

## Deferred / not current

- BQ1 multiple roles and direct grants.
- BQ2 catalog governance, role bundles, delegated assignment, and role UI.
- AUTHZ1-D–I, production backfill/cutover/rollback, MySQL rehearsal, extra
  panels, and speculative role/panel design.
- ARCH1, SP3D, SP4, LOG1, and any sequencing dependency from AUTHZ into them.
- WB2–WB7 until a concrete near-term importer need is selected.

## Roadmap pointer

- **Now:** explicitly start or leave pending the one bounded AUTHZ closure; after
  closure, select Card Template preview/side-panel UX on the current SP3C
  foundation.
- **Later:** small Public Front or settings UX slices, measured P2/P3 work,
  MAINT-LW-UX1 at its qualifying trigger, and WB2 only on concrete demand.
- **Not now:** generalized authorization or replacement architecture.

No other broad architecture question is pending.
