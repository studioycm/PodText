# Feature-First Decision and Checkpoint Queue

Date: 2026-07-17

Status: AUTHZ complexity reset proposed; operator acceptance required

Controlling plan:
`19-authz-complexity-reset-and-feature-first-master-plan.md`.

## Restart point

Read the controlling plan, current project state, and the ledger. Reports 12–18
and their handoffs are historical AUTHZ evidence, not an active remediation
queue. Do not restart at AUTHZ1-D, ARCH1, SP3D, or former Groups 16–29.

## One pending decision

Operator chooses **accept** or **revise** for the master reset. Acceptance
authorizes drafting only one bounded AUTHZ closure prompt. It does not authorize
implementation or operational commands.

## Accepted for now

- Legacy `users.role`, ranks, Gates/macros, panel/Horizon/maintenance admission,
  and Users Resource restrictions remain authoritative.
- Shield stays unregistered; `User` stays without `HasRoles`; no compatibility
  grants, package cutover, or role UI.
- A future closure prompt may only remove/unregister the three
  `authz:roles:*` commands, add focused legacy-authority regressions, and update
  minimum docs.
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

- **Now:** accept the reset; then one bounded AUTHZ closure; then select Card
  Template preview/side-panel UX on the current SP3C foundation.
- **Later:** small Public Front or settings UX slices, measured P2/P3 work,
  MAINT-LW-UX1 at its qualifying trigger, and WB2 only on concrete demand.
- **Not now:** generalized authorization or replacement architecture.

No other broad architecture question is pending.
