# Repo governance model — generalizing the dashboard model beyond one program

**Status:** DESIGN, operator-approved scope, not implemented. Planning round 2026-08-12.
**Supersedes in scope:** the dashboard-only scope ruling of 2026-08-03.
**Provenance:** written 2026-08-12 by the governance-planning session, from
`docs/phase-02/dashboard-governance-principles.md` (the model), `.ai/rules/docs.md`
(the two rules the test-suite-rethink round earned), the R→T→S→U round's record,
and `~/.cache/podtext-coord/PROTOCOL.md` (the live concurrent-session protocol,
outside the repo).

---

## 1. Why now

The 2026-08-03 ruling scoped the governance model to the dashboard program and
deferred wider adoption as "its own future thinking and plan". Two things have
since made the deferral ripe.

**It transferred.** The test-suite-rethink program (R→T→S→U, closed 2026-08-12)
ran three concurrent sessions in one shared tree with zero collisions, and every
cross-program contribution arrived through the report channel rather than by
writing to an owned file. A different program, unmodified rules.

**It leaked, unevenly, and left a contradiction.** The model is already binding
outside the dashboard program — but through a second home, with different text:

- `.ai/rules/docs.md:12` §"Living cross-session ledgers" is scoped `docs/**` —
  repo-global — names three ledgers from three programs, and **replaced**
  principle 3 (`one-owner-registry`) by operator decision 2026-08-11.
- `docs/phase-02/pest5-upgrade-implementation-plan.md:29` — a different
  program's plan restates the custodian/evidence-gated protocol as its own
  operating rule.
- But `docs/phase-02/dashboard-governance-principles.md:32-36` still states
  `one-owner-registry` flatly, with no supersession note; and
  `docs/phase-02/consolidated-open-findings.md:199` still tells readers the
  cause-pattern ledger has "one owner", while `:146` still instructs
  "do not write it there directly".

`a07474a` struck the retired rule in the patterns ledger. Its siblings in two
other documents never learned. **That is the governance gap in one sentence,
with commit evidence** — and it is the strongest argument for this round's
existence.

## 2. Decisions taken (operator, 2026-08-12)

| # | Decision | Chosen |
|---|---|---|
| D1 | Scope | **Two-axis**: an always-on core binding all docs work; a heavier custody protocol binding only named artifacts, escalating with concurrency |
| D2 | How the core binds | **Prose home + one pointer rule** — the governance doc carries every principle with its evidence-backed why; `.ai/rules/docs.md` gains ONE rule pointing at it |
| D3 | Consolidated register ownership | **Split** — this model owns *how* it is written; the Documentation Architecture audit owns *what* it consolidates |
| D4 | Doc home | **Rename** to a scope-honest name, fixing citations so no dangling reference exists |

Constraint discovered after D2 was taken: **changes to `.ai/rules/**` are
operator decisions, not edits** (house rule; both current entries were approved
individually). So D2's pointer rule needs its exact text approved separately.

## 3. Artifact classes — enumerated, with a test that keeps the list honest

The current rule binds "…and their kind". That is unenforceable: every session
decides for itself what *kind* means. Replace it with an enumerated table plus
an admission test, so the list can grow without a ruling each time.

- **T0 — ordinary doc.** Default. Anyone edits. No custodian, no re-audit.
  The core still applies, because the core applies everywhere.
- **T1 — living ledger.** Custodian header, evidence-gated writes, contribution
  zone.
- **T2 — generated register.** A hand-edit is itself a defect. Regenerate
  wholesale, harvest SHA pinned, refreshed at round close by the closing
  session. One member today: `consolidated-open-findings.md`.

**Admission test for T1 — four clauses, all required:**

(a) multiple sessions write it over its life ·
(b) it carries status claims others act on ·
(c) it has no natural close date ·
(d) **its value depends on curation** — entries must be deduped, merged, and
reconciled against each other.

Clause (d) is load-bearing. Without it the test sweeps in
`docs/phase-02/current-project-state.md` (3,085 lines, multi-session,
status-bearing, no close date) — and appointing a single custodian there would
break a documented, working workflow, since `.ai/guidelines/tooling-quality.md`
requires *every* implementation prompt to update it. With (d) it lands T0 correctly:
that file is chronological **accumulation**, not curated **synthesis**. Nobody
reconciles prompt 9's entry against prompt 13's.

The three named ledgers fail (d) in the other direction — the patterns ledger
dedupes sightings into patterns, the triage list reconciles status, the register
synthesizes across documents.

> **Custodianship exists to protect synthesis, not accumulation.**

The classification is a property of the artifact, not of the program that made
it. That is precisely why the model transferred to the test-suite program
unmodified, and it is the whole basis for generalizing it.

### 3.1 The consolidated register — split ownership (D3)

`docs/phase-02/consolidated-open-findings.md` is the sole T2 member and is also
the committed seed of the unstarted Documentation Architecture and Consolidation
Audit. Ownership splits cleanly along a line that already exists in its header:

- **This model owns *how* it is written.** Regenerate wholesale — a hand-edit is
  itself a defect, by anyone, including the round's arbiter. Harvest SHA pinned
  in the header. Refreshed at round close by the session closing the round, never
  by the auditor, whose independence is why the record came out clean.
- **The audit owns *what* it consolidates.** Sources, tiering, the
  pointer-not-restatement form, and whether the register should continue to exist
  in this shape at all.

The audit therefore **inherits a governed artifact rather than re-deciding
governance**, and may re-classify it without re-deriving the rules. Nothing here
blocks the audit, and the audit starting does not invalidate anything here.

This split is not theoretical: the register currently carries two stale lines
(`:199`, `:146`) telling readers the retired `one-owner` rule still applies. Under
this split they are **regeneration input, not a docs fix** — the arbiter
considered hand-fixing them during this round and refused, on the grounds that a
rule's author does not get an exception to it. Nothing operative depends on those
lines, because the live operating guidance reaches sessions through
`.ai/rules/docs.md` and the concurrent-session protocol instead.

## 4. The core — always on, everywhere, solo included

Carried unchanged from the dashboard model: `readable-binding`,
`pinned-promise`, `register-at-the-moment`, `names-carry-family`,
`provenance-stated`, `verify-dont-trust`.

**Retired:** `one-owner-registry` — struck, not deleted, with a supersession
pointer to `.ai/rules/docs.md` §"Living cross-session ledgers", per the model's
own provenance discipline.

**Adopted into the principles:** `sibling-status-sweep` — editing a doc's status
claim re-verifies that doc's other present-tense status claims. It exists today
as a rule with no home among the principles; giving it a home with its why makes
the two documents one model instead of two.

**New — `authority-not-relayed`:** a peer session's report carries information,
never authority; an operator narrowing is lifted only by the operator.
*Why:* both expensive near-misses in the case-study data were this, two prior
incidents are already on record, and `PROTOCOL.md` closes with the same lesson
independently — *"twice a session that acted on a reasonable-sounding
derivation was wrong."*

The principle is already operating, unprompted, in this repo's commit log.
`505f043` converts a test but refuses to delete a worthless neighbouring one,
and says why in the message: *"deleting a test needs the operator's explicit
approval here and what exists so far is a peer session's relay of that
approval … a deletion being obviously harmless is an argument about the
deletion, not about who authorises it."* A session declining to inherit
authority sideways, on the same day, in a tracked artifact. Naming the
principle does not create the behaviour — it stops each session having to
re-derive it.

**New — `governs-itself`:** a governance rule binds the document that states it.
Every governance artifact is classified under this scheme like any other, carries
the same custodian discipline, and is subject to the same guards. When you write
a rule, check the document you wrote it in against it before you commit.

*Why:* four instances, three of them during the round that designed this section.

| Artifact | Rule it states | How it broke that rule |
|---|---|---|
| `dashboard-governance-principles.md` | `one-owner-registry` | Still states the rule its own model retired (2026-08-11 → unfixed) |
| `~/.cache/podtext-coord/PROTOCOL.md` §4 | enumerate rather than cite counts | Carried an unenumerable count ("four test files"; actually one), hours after being written, in the document that enforces the discipline |
| `defect-cause-patterns.md` | custodian stamped at each merge | Went unstamped at `6e23af0`, the first merge after the rule was adopted |
| `defect-cause-patterns.md` | `one-home` | Registered its own `one-home` violation (`0df05ee`) |

The pattern is not carelessness — the second instance was written by the round's
arbiter, under active collision pressure, and self-corrected within the hour. It
is that **prose describing a discipline does not participate in the discipline**.
Only a guard does. This principle is why §8 exists, and §8 is what makes this
principle more than an observation.

**New — `control-preconditions`:** when a discipline is accepted as a control,
state what it depends on. *"Safe provided everyone obeys a rule"* is a materially
weaker claim than *"safe"*, and weaker still when the rule's enforceability rests
on an incidental property of the current system. Corollary: **a discipline is not
a mechanism.** A guard is.

*Why:* measured during this round by the TIA session. The repo's T23b rule — no
commits while a suite is in flight — appeared to close the remaining hole around
Pest 5's unserialised TIA cache. It does not: the hole is reachable by a
**one-second** replay run, which nobody recognises as "a suite in flight". T23b's
coverage silently depended on runs being slow, which is precisely the property
the feature under evaluation exists to destroy. The control was never wrong; its
unstated precondition expired.

**Solo-day cost of the entire core:** one sentence when you find something, one
sentence when you defer something, and re-running a number before you repeat it.
There is no solo exemption because there is nothing to exempt. That is the
answer to "minimum viable form".

## 5. The custody protocol — T1/T2 only, two gears, a maintained resource register

The measured cost of the model is coordination. So gear it to concurrency, not
to program membership.

**Quiet gear** (no other session live in this tree): write directly. Gate =
evidence attached (commit + file/symbol) + narrow pathspec + an `unaudited`
marker on the custodian line. No re-audit — there is no independent session, and
inventing one is theatre. The marker is the debt record; the next session to
touch the file clears it.

**Concurrent gear** (≥1 other session live in the same tree): full protocol —
contribution zone for non-custodians, independent re-audit of the diff, custodian
line stamped at merge.

The trigger must be *checkable* or it becomes a judgement call that always
resolves to "quiet". Concrete check: list running sessions sharing this `cwd`.

### 5.1 Shared resources are a maintained register, not a one-time list

`PROTOCOL.md` names three, each earned from a real collision. A fourth is named
here, found while writing this document:

1. **Content** — path partition, plus the contribution zone below.
2. **The git index** — disjoint files still share one index; `git add` stages
   whole content and a commit takes whatever is staged. This already swallowed
   one session's in-flight work in this repo. Needs a serialized add+commit
   window.
3. **The working tree as test input** — no tracked-file edits while any suite
   runs. Markdown genuinely *is* test input here, so "docs-only is inert" is
   false in this repo. Measured at HEAD: exactly one test reads project docs at
   runtime — `tests/Feature/PublicStep9RMenuHeaderUxFixesTest.php:118-120`,
   reading three files, two of them under `docs/phase-02/`. Additionally, a Pest
   5 TIA recording run is graph-sensitive to *any* new file, whether or not a
   test reads it.

4. **Git HEAD itself** — and this one is *outside any lock's reach*. Per
   `3330fe7`'s message, a Pest 5 TIA recording run stamps `setRecordedAtSha()`
   with HEAD read at the **end** of the run, so a commit landing mid-run becomes
   that run's recorded baseline while its results still describe pre-commit code
   — and `structuralFingerprintShifted()` cannot detect the swap, because the
   fingerprint hashes `composer.lock`/`composer.json` and other config, never app
   source. No mutual exclusion fixes this: the two writers are *a test run* and
   *a commit*, and the lane lock serialises runs against runs only. It is the
   clearest case in the repo of a shared resource whose protection must be a
   stated rule rather than a mechanism — which is precisely `control-preconditions`
   with no mechanism available to fall back on.

**But enumerating them once is not the work.** `PROTOCOL.md` §3 describes the
machine-global lane lock by its stated remit — one pest run at a time — and is
silent on the fact that it is also the only thing serializing Pest 5's TIA
cache, which does a read-modify-write on a shared graph file with no mutual
exclusion of its own. A protocol written hours ago already carries an undeclared
invariant.

So the register is **maintained**, and the model gains a self-test: *when a
session discovers it depends on a shared resource, does that resource's
declaration already name the dependency?* If not, the discovery is a registrable
finding, not a private note.

## 6. The contribution channel when nobody is listening

"Contribute by report, not by writing" assumes a live recipient. Asynchronously
there is none, and the failure is already on the books: `silent-vendor-surface`
sits at `consolidated-open-findings.md:146` as *"Awaiting orchestrator merge …
do not write it there directly"* — a finding parked on a handshake that never
came.

**Design: an append-only contribution zone at the tail of each T1 ledger.**
Any session appends a dated, evidenced entry. Nobody edits or reorders existing
entries — including the custodian, who *moves* an entry into the curated body
when merging.

This does not reintroduce `two-writer-channel`: the file gains exactly one
two-writer region and it is append-only, so writes cannot conflict semantically
— the same reason an append-only log is safe where a mutable record is not. The
curated body keeps its single writer.

**Against the measured costs:** the handshake becomes optional for the common
case. A contributor with evidence writes it down and moves on — no round-trip,
no dormant-owner wait, no chat-only finding. The wake-message/deadline/transfer
machinery survives as the escalation, not the only path.

**Cost:** the file grows a tail that must actually get merged. Mitigation:
merging the zone is part of the round-close duty that already exists.

## 7. Custodianship must be derivable, not transcribed

The rolling-custodian rule has been due exactly once since its recording step
existed, and that once it was skipped:

- `a07474a` **introduced** the custodian line in `defect-cause-patterns.md`,
  naming the register-1.9 settings-backup retention session.
- `6e23af0` — *"merge dead-guard-revival; note governance evidence from another
  program"*, the most recent merge — **did not touch it**
  (verified with `git log -L 28,29:docs/research/defect-cause-patterns.md`).

n=1, so this is a mechanism, not yet a pattern. The mechanism: the rule defines
the custodian as *whoever last merged*, then separately asks someone to write
that down. When the write is skipped, the file's stated custodian and the rule's
actual custodian diverge silently, and **no reader can tell**.

An earlier draft of this design proposed "transfer is automatic on merge, no
handshake". That is withdrawn: it optimizes away a round-trip and buys a higher
rate of silent divergence.

**Resolution.** `one-home` applied to governance itself: a name hand-copied into
prose is a second home for a fact git already owns, and it drifted within a day.
But a reader must see it without running git (`readable-binding`), so the header
line stays — as a *cache*, citing its merge commit — and:

> **A merge that does not stamp the custodian line is not a sanctioned merge.**

Definitional, not aspirational. Enforced by §8.

**Dormancy** needs a number or it is unenforceable. Proposed: no merge in
**7 days** *and* an unmerged contribution-zone entry older than **3 days**.
(The founding incident was a week.) With the contribution zone in place,
escalation is needed only when a *curation decision* is blocked — not when a
contribution is.

## 8. Governance rules get guards, same as code

The dashboard model's own `pinned-promise` says a behaviour claim in prose names
the test that pins it, or it is not a promise. **That principle was never
applied to the governance artifacts themselves**, and the result is exactly what
the theory predicts: three sites of stale `one-owner` text, a custodian line
that rotted in a day, and a register still carrying instructions nobody should
follow.

The need is not theoretical, and the evidence arrived during this very round.
`PROTOCOL.md` §4 justifies its most absolute rule with *"four test files read
`.md`, two of them under `docs/phase-02/`"*. Measured at HEAD: **one** test file
reads **three** docs files, two of them under `docs/phase-02/` — the last clause
exact, the first off by 4×, apparently by counting files-read as test-files.
Thirteen of the fifteen `.md` mentions in `tests/` are docblock citations, two of
them on a governed ledger, which is how a reader arrives at "a governed ledger is
test input" when no test reads one. The rule's *conclusion* survives — three real
docs are read by a real test — but a protocol written hours earlier, by a careful
author, under active collision pressure, already carried a number that could be
cited and not enumerated. This is the register's own corollary applied to
governance: **a count you can list is a count you can falsify; a count you can
only cite is not.**

Prose written by careful people rots this fast. Docs are already test input here,
so guarding is cheap. Proposed guards:

- **Custodian freshness** — for each T1 ledger, the commit cited in its custodian
  line is the most recent commit touching that file. Catches the `6e23af0` case
  automatically.
- **No orphaned supersession** — no doc states a rule that `.ai/rules/docs.md`
  records as replaced. Would have caught all three stale one-owner sites the day
  the rule changed.
- **Class-list integrity** — every artifact in the T1/T2 table exists, and every
  file carrying a custodian header appears in the table. Per `governs-itself`,
  the governance doc is itself in the table (T1) and is not exempt.

Prose advises; only a test fails. `tests/Feature/FilacheckAgentModeGuardTest.php`
is this repo's worked example of the same move.

### 8.1 What each control here depends on

`control-preconditions` applied to this design, before anyone else has to find it
the hard way. Every control below is a discipline until a guard makes it a
mechanism; this table is the honest statement of what each one is currently
resting on.

| Control | Silently depends on | Fails when |
|---|---|---|
| Quiet gear skips re-audit (§5) | The liveness check being accurate *and still true during the write* | It is a point-in-time read; a session starting mid-write is invisible to it |
| Contribution zone is append-only (§6) | Contributors appending rather than editing | Nothing enforces it but discipline — needs a guard, or it is a two-writer region after all |
| Custodian stamped at merge (§7) | Someone remembering, at the moment of merge | Already failed once (`6e23af0`). §8's freshness guard is what converts it |
| Path partition (§5.1) | An arbiter existing and being asked | Solo and asynchronous rounds have no arbiter — the partition silently becomes self-assignment |
| Commit mutex (§5.1) | *Every* session using it | One session that does not participate voids the guarantee for all the others, with no signal |
| T23b (§5.1) | Suite runs being long enough to notice | **Measured false:** a ~1s TIA replay is a suite in flight that nobody perceives |
| Enumerable counts (§8) | The writer enumerating rather than estimating | Failed in `PROTOCOL.md` hours after it was written, by the person enforcing it |

Two honest consequences, and the first one is worse than it first looked.

**Of the seven controls above, §8's guards convert exactly ONE** — custodian
freshness. The other two guards protect different things (stale rule text, table
integrity) and map to no row here. So six of seven controls in this design remain
pure discipline, including both that fail silently. An earlier revision of this
paragraph claimed "three guards convert three rows" — an unenumerated count,
written inside the section arguing against unenumerated counts, caught only by
listing the rows against the guards. `governs-itself` fired on this document
within minutes of the principle being written into it; the count is corrected
above and the error left recorded here, because a design that hid it would be
making the case for its own guards while demonstrating why prose cannot be
trusted to carry them.

Second, the two rows that fail *silently* — the liveness check and the commit
mutex — are the ones to distrust first, because neither produces a signal when
its precondition lapses. Closing those is the obvious candidate for guard work
beyond the three proposed, and is deliberately not scoped here.

## 9. What this costs — priced honestly

**Bought, from the case-study record:** sessions correcting each other on
evidence, repeatedly · two voluntary self-reports of rule breaches · a stale-copy
defect caught because the auditor stayed read-only · zero collisions across three
concurrent sessions in one tree.

**Paid, from the same record:** real coordination overhead · several message
round-trips per handoff · one dormant-owner wait · two cases where relayed
authority nearly caused wrong action.

**What this design changes about the price:**

| Cost | Mechanism | Effect |
|---|---|---|
| Round-trips per handoff | Contribution zone (§6) | Removed for the common case; handshake becomes escalation |
| Dormant-owner wait | Contribution zone + dormancy clock (§7) | Contributions no longer block on a person |
| Relayed authority | `authority-not-relayed` (§4) | Named principle instead of a lesson each session re-learns |
| Solo-day tax | Two-gear protocol (§5) | Quiet gear has no re-audit; core is ~free |
| Rule rot | Guards (§8) | Converted from prose discipline to test failure |

**New costs this design adds:** a maintained shared-resource register (§5.1);
three guard tests to write and keep green (§8); a contribution tail per T1
ledger that must actually be merged (§6).

## 10. Migration — the model's first customer is itself

Of the 5 files citing the path (6 occurrences), **two are governed artifacts**,
so the rename cannot be a unilateral sweep. It must run through the protocol it
establishes.

| Citing file | Class | Path for its citation fix |
|---|---|---|
| `consolidated-open-findings.md` ×2 | T2 | Cannot be hand-edited — its own header makes that a defect. Lands at next regeneration |
| `defect-cause-patterns.md` ×1 | T1 | Contribution-zone entry, or evidence-gated write + re-audit |
| `current-project-state.md` ×1 | T0 (per §3) | Plain edit |
| `dashboard-widget-principles.md` ×1 | T0 | Plain edit |
| `dashboard-metrics-phase-2R-handoff.md` ×1 | T0 | Plain edit |

**The rename is blocked on a dormant owner.** `dashboard-governance-principles.md`
belongs to the dashboard program (Dashboard UX route-plan 1), which is not
running; the round's arbiter declined to grant what is not its to grant. That is
the exact cost the case-study data priced, reproduced inside the round designed
to fix it — and under this design it resolves without a handshake, via §6.

**Sequencing fork (open — see §12).** `git mv` breaks the register's two
citations immediately, and fixing them by hand is a defect by that file's own
terms. A dangling pointer in a governed artifact is worse than a stale status
claim.

## 11. Out of scope

- Rewriting the register's content, tiering, or pointer-not-restatement form —
  that belongs to the Documentation Architecture and Consolidation Audit (D3).
- Any change to code, tests, or the suite, beyond the three guard tests in §8.
- Retro-classifying archived docs (`docs/archive/**`). Archived material is not
  active instruction and gains nothing from custodianship.
- The dashboard program's own open work. This round governs *how* artifacts are
  written, not what any program still owes.

## 12. Decisions still needed

1. **Rename sequencing** — gate the `git mv` on the next register regeneration
   so no dangling window exists (recommended), or rename now and carry a
   known-stale pointer for one cycle.
2. **Exact text of the `.ai/rules/docs.md` pointer rule** — an operator decision
   by house rule, not an edit.
3. **Routing of the undeclared-invariant finding** (§5.1, the lane lock silently
   serializing Pest 5's TIA graph) to the patterns ledger's custodian as a
   candidate pattern. It has a code home in `TestLaneContract`'s docblock; it has
   no pattern-level home.
