# Dashboard Governance Principles

How the dashboard program's work is recorded, owned, named, and verified.
Co-created by the operator and the route orchestrator on 2026-08-03,
consolidating rulings made during the 2026-08-03 orchestrated route into one
readable home.

**Scope (operator ruling, 2026-08-03): dashboard program only.** These
principles bind dashboard-program work. Any wider adoption — other programs
or repo-global governance — is deliberately deferred future work requiring
its own thinking and plan (registered in the cause-pattern ledger's deferred
register).

The general rules live here; the cause-pattern ledger
(`docs/research/defect-cause-patterns.md`) keeps only its ledger-specific
mechanics and cites this document.

## The principles

1. **`readable-binding`** — a rule binds only where its text can be read:
   binding sources live in the repo, never by reference to an artifact, a
   PDF, or a chat.
   *Why:* the `unarchived-binding` pattern — the dossier principles and the
   media design principles both bound work while existing nowhere readable;
   both had to be recovered (transcript search, PDF extraction) and
   archived (2026-08-03).
2. **`pinned-promise`** — a behavior claim in prose (docblock, spec line)
   names the test that pins it, or it is not a promise.
   *Why:* the `unpinned-promise` pattern — the reason-bar doorway promise
   rotted unnoticed because no test asserted the URL's shape (fixed
   `c36f6c4`).
3. **`one-owner-registry`** — every registry (ledger, checklist, principles
   doc) has exactly one curator; everyone else contributes through report
   sections, never by editing the file.
   *Why:* operator ruling 2026-08-03 — two writers on one ledger would be
   the `one-home` pattern itself.
4. **`register-at-the-moment`** — findings register at discovery; deferral
   decisions (postponed sweeps, parked work, unfiled reports, drafts held
   for reconciliation) register at decision time, in the rightful doc,
   stating what, why, and what unblocks it. A finding or deferral that
   exists only in a conversation does not exist.
   *Why:* operator directive 2026-08-03 — chat-only decisions were how
   earlier rounds lost work items.
5. **`names-carry-family`** — no bare letter+digit id families, ever again:
   canonical ids are kebab slugs; other numbering families carry a distinct
   prefix; historical aliases are marked as aliases.
   *Why:* operator directive 2026-08-03 — bare `P<n>` meant five different
   things across this repo before the rename (`27c5f3b`).
6. **`provenance-stated`** — recovered, derived, or extracted text states
   its source and date where it lands.
   *Why:* the recovery practice that closed `unarchived-binding`
   (`22d44a6`, `7923738`) — provenance is what makes an archived copy
   authoritative rather than apocryphal.
7. **`verify-dont-trust`** — a delegate's gate claim is stamped only after
   the curator's own identical run; report numbers are hypotheses until
   reproduced.
   *Why:* operator-approved 2026-08-03; practiced across the route — every
   block's claimed gate (F: 1571/19,430; A: 1587/19,563; B1: 1594/19,614)
   was reproduced exactly by a direct run before its checklist stamp.

## Change protocol

Amendments follow these principles' own rules: evidence-backed why,
registered at decision time, slugs per `names-carry-family`, and this file
has one curator.
