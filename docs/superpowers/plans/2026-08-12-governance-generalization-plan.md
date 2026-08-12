# Governance Model — Durable Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reconcile the governance model's contradictory text across its three
homes, amend the principles doc to match what the repo actually practises, and
add three guards so governance drift fails loudly without a peer session to
catch it.

**Architecture:** Documentation changes plus three Pest guard tests. The
principles doc stays the readable home carrying every rule with its
evidence-backed why; `.ai/rules/docs.md` gains a third rule pointing at it,
alongside the two existing rules which are unchanged. Guards live in one
`tests/Feature/GovernanceDocsGuardTest.php` because they share one concern —
governance artifacts telling the truth about themselves.

**Tech Stack:** Pest 5.1 / PHPUnit 13.3, PHP 8.4, Laravel 13. Guards read files
with `file_get_contents(base_path(...))` and shell to git via
`Illuminate\Support\Facades\Process`.

## Global Constraints

- **Scope reduced by operator decision 2026-08-12.** The concurrent-session
  machinery from the spec — path partitions, commit mutex, contribution zones,
  dormancy clocks, the two-gear protocol — is **out of scope**. The operator
  will run one session at a time or use worktrees. Only what survives without
  concurrency is built here.
- **`.ai/rules/**` changes require the operator's explicit approval of the exact
  text.** House rule; both existing entries were approved individually. Task 3
  is gated on that approval and must not be implemented without it.
- **`docs/phase-02/consolidated-open-findings.md` is never hand-edited by
  anyone.** Regenerate-wholesale only. Its two stale citations are regeneration
  input, not a task here.
- **`docs/phase-02/dashboard-governance-principles.md` belongs to the dashboard
  program**, whose session is dormant. Task 2 needs that owner's agreement or an
  operator override before it lands.
- Commit style: this repo writes multi-paragraph commit bodies explaining *why*,
  ending with `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`.
- Never `git add -A`, `-a`, or `.` — explicit pathspecs only. Never push.
- Run tests with `php artisan test --compact --filter=<name>`. The full suite
  needs the MySQL lane and takes ~370s; do not run it for a docs-only task.

## Worktrees: what they do and do not isolate

The operator will run one session at a time or use worktrees (decision
2026-08-12). Recorded here because a future reader will otherwise re-derive it.

Worktrees give each session its own working tree and its own git index, which
removes file collisions, the commit mutex, and the no-edits-while-a-suite-runs
rule. Two things live in `$HOME` rather than the repo and are shared by every
worktree:

- **The MySQL test lane** (`~/.cache/podtext-test-lane/`) — one test database on
  `127.0.0.1:3307`, so one run at a time machine-wide. Deliberate: two suites on
  one database corrupt each other. **No action needed — this is a queue, not a
  coordination protocol.** The lock refuses a second run loudly and names its
  holder.
- **The Pest 5 TIA graph** (`~/.pest/tia/<project-key>`, keyed by normalized git
  remote) — every worktree of this repo shares one graph. **No action needed —
  TIA is off, and is separately gated on a coverage driver that is not
  installed.** The standing rule that TIA must not run while sessions share a
  working tree extends to worktrees, and is what to weigh whenever enabling it
  is proposed.

Neither is a defect and neither is fixed by this plan. They are limits to know.

---

## File Structure

| File | Responsibility | Task |
|---|---|---|
| `docs/superpowers/specs/2026-08-12-governance-generalization-design.md` | The design record. Carries three wrong counts and no scope-reduction note | 1 |
| `tests/Feature/GovernanceDocsGuardTest.php` | **New.** All three guards — governance artifacts telling the truth about themselves | 4 |
| `docs/phase-02/dashboard-governance-principles.md` | The readable home for every principle | 2, 5 |
| `.ai/rules/docs.md` | Guaranteed-read path; gains a third rule pointing at the principles doc | 3 |

Task order is deliberate: Task 1 is unblocked and self-contained; Task 4's first
guard **fails against real stale text** before Task 2 fixes it, which is the TDD
cycle running against a live defect rather than a synthetic one.

---

### Task 1: Correct the spec's own counts and record the scope reduction

> ### ✅ ALREADY DONE — DO NOT EXECUTE
>
> Landed 2026-08-12 in **`96c94ed`**, before this plan was committed. It ran as
> an authorized correction to a published spec — a wrong count and an
> unenumerated membership in an already-committed document — not as execution of
> this plan, which is planning-only.
>
> The steps below are kept as the record of what was done and how it was
> verified. **An agentic worker starting from this plan should begin at Task 4**
> (the guards), since Tasks 2, 3 and 5 each carry a gate that is not yours to
> open.
>
> Two things landed beyond what these steps describe, both discovered while
> executing them: §0.1, recording that this spec binds its analysis to an
> unversioned file outside the repo (`unarchived-binding`, alias P12), and the
> replacement of the `governs-itself` ordinals with an extensible table, because
> a running count of "instances of a rule being broken" is itself a number that
> rots.

The spec states that one test reads **three** docs files. It reads **four** —
the missed one is `.ai/guidelines/tooling-quality.md` at `:121`. The error came
from a detection command ending in `head -3`, which silently capped the count.
That is `silent-cap` (alias P6 in the patterns ledger) occurring inside a
measurement used to correct someone else's miscount.

**Files:**
- Modify: `docs/superpowers/specs/2026-08-12-governance-generalization-design.md`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing consumed by later tasks. Self-contained.

- [ ] **Step 1: Verify the real count before changing anything**

```bash
sed -n '117,122p' tests/Feature/PublicStep9RMenuHeaderUxFixesTest.php
```

Expected: four `file_get_contents(base_path(...))` calls at lines 118–121,
reading — in order — `docs/research/public-front-v2/13-step9r-menu-header-ux-fixes-mcp-research.md`,
`docs/phase-02/public-front-v2-step9r-verification-and-fixes-plan.md`,
`docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md`, and
`.ai/guidelines/tooling-quality.md`.

Do not proceed on the number written in this plan. Verify it. A corrected count
still needs verifying — that is the whole lesson of this task.

- [ ] **Step 2: Fix §5.1 item 3**

Replace:

```
   runtime — `tests/Feature/PublicStep9RMenuHeaderUxFixesTest.php:118-120`,
   reading three files, two of them under `docs/phase-02/`. Additionally, a Pest
```

with:

```
   runtime — `tests/Feature/PublicStep9RMenuHeaderUxFixesTest.php:118-121`,
   reading four files, two of them under `docs/phase-02/` and one
   (`.ai/guidelines/tooling-quality.md`) outside `docs/` entirely — which is
   exactly the kind of file a session edits while believing docs are inert.
   Additionally, a Pest
```

- [ ] **Step 3: Fix both §8 sites**

Replace:

```
`.md`, two of them under `docs/phase-02/`"*. Measured at HEAD: **one** test file
reads **three** docs files, two of them under `docs/phase-02/` — the last clause
exact, the first off by 4×, apparently by counting files-read as test-files.
```

with:

```
`.md`, two of them under `docs/phase-02/`"*. Measured at HEAD: **one** test file
reads **four** files, two of them under `docs/phase-02/` — the last clause
exact, the first off by 4×, apparently by counting files-read as test-files.
```

Then replace:

```
test input" when no test reads one. The rule's *conclusion* survives — three real
docs are read by a real test — but a protocol written hours earlier, by a careful
```

with:

```
test input" when no test reads one. The rule's *conclusion* survives — four real
files are read by a real test — but a protocol written hours earlier, by a careful
```

- [ ] **Step 4: Append the correction record to §8**

Add immediately after the paragraph ending *"a count you can only cite is
not."*:

```markdown
**And this document got it wrong too, in a new way.** The figure above was
first written as *three* files. It came from a detection command ending in
`head -3` — the instrument silently capped the count, and the cap left no trace
in the output. That is `silent-cap` (alias P6) firing inside the measurement
used to correct someone else's uncountable number. The full chain ran three
deep — "four test files" (wrong), "one test file, three docs files" (wrong,
truncated), "four files" (verified) — and in every link the corrector was not
the writer. The lesson is sharper than *enumerate rather than count*: **an
enumeration is only as trustworthy as its instrument's cap.** `head`,
`--maxdepth`, PHPStan's agent-formatter truncation and MCP result limits all
return lists that look complete. Enumerate, then verify the enumeration was
not truncated.
```

- [ ] **Step 5: Record the scope reduction in the header**

Replace the `**Status:**` line with:

```markdown
**Status:** DESIGN. **Scope reduced by operator decision 2026-08-12** — see
"Scope reduction" below. Not implemented; the implementation plan is
`docs/superpowers/plans/2026-08-12-governance-generalization-plan.md`.
```

Then add immediately before `## 1. Why now`:

```markdown
## 0. Scope reduction (operator decision, 2026-08-12)

The operator will run **one session at a time, or use worktrees**, rather than
concurrent sessions in a shared tree. Everything in this document that exists to
make concurrent shared-tree work safe is therefore **not being built**: the
two-gear protocol (§5), the shared-resource register as a governance mechanism
(§5.1), the contribution zone (§6), and the dormancy clock (§7).

What survives, and is what the plan implements: the core principles (§4), the
reconciliation of the contradictory `one-owner` text (§1), and the guards (§8) —
which matter *more* without concurrency, not less. Nearly every correction this
round came from a peer session catching something on evidence; remove the peers
and a guard that fails loudly is the only remaining check.

**A caution on worktrees, verified 2026-08-12.** They isolate the working tree
and the git index. They do **not** isolate the MySQL test lane (lock and
fingerprint are machine-global at `~/.cache/podtext-test-lane/`, made so
deliberately by `89a2ee1`/`810f6f2` to close the cross-worktree gap) nor the
Pest 5 TIA graph (`~/.pest/tia/<project-key>`, keyed by normalized git remote —
every worktree of this repo shares one graph; mechanism confirmed `50dc246`).
A large net win, not a complete one.

The rest of this document is kept unchanged as the design record and the
evidence base. Sections marked out of scope above were not wrong — they were
answering a question the operator has decided not to have.
```

- [ ] **Step 6: Sweep the rest of the document for status claims this changed**

Per `.ai/rules/docs.md` §"Editing a doc's status claim re-verifies its
siblings", re-read the whole spec and check every present-tense status claim
against the new scope. Expected findings: §11 "Out of scope" and §12 "Decisions
still needed" both need a line noting D-rename is now gated and the guard set is
fixed at three. Fix in place; do not rewrite history.

- [ ] **Step 7: Commit**

```bash
git add docs/superpowers/specs/2026-08-12-governance-generalization-design.md
git commit -m "docs(governance): correct the spec's own capped count and record the scope reduction"
```

---

### Task 2: Amend the principles doc to match what the repo practises

**Blocked on:** the dashboard program's owner (session "Dashboard UX
route-plan 1", dormant) or an explicit operator override. Do not land without
one.

**Files:**
- Modify: `docs/phase-02/dashboard-governance-principles.md`

**Interfaces:**
- Consumes: nothing.
- Produces: the retired-slug state that Task 4's guard G1 asserts. G1 expects
  `one-owner-registry` to appear in this file **only** on lines that also contain
  `SUPERSEDED`, and nowhere else in any active doc.

- [ ] **Step 1: Replace the scope ruling**

Replace lines 8–12 (the `**Scope (operator ruling, 2026-08-03)...**` paragraph)
with:

```markdown
**Scope (operator rulings, 2026-08-03 and 2026-08-12).** These principles bind
**all documentation work in this repo**, not the dashboard program alone. The
2026-08-03 dashboard-only ruling is superseded: the model transferred unmodified
to the test-suite-rethink program (R→T→S→U, closed 2026-08-12), and had already
leaked repo-wide through `.ai/rules/docs.md` without this file being updated to
match. The heavier custody machinery designed alongside this widening —
partitions, commit mutex, contribution zones, dormancy clocks — was
**deliberately not adopted** (operator decision 2026-08-12): sessions run one at
a time or in worktrees, so it would solve a problem this repo has chosen not to
have. Design record and evidence:
`docs/superpowers/specs/2026-08-12-governance-generalization-design.md`.
```

- [ ] **Step 2: Retire principle 3 in place, struck not deleted**

Replace principle 3 with:

```markdown
3. **`one-owner-registry`** — **SUPERSEDED 2026-08-11**, replaced by the
   rolling-custodian rule whose home is `.ai/rules/docs.md` §"Living
   cross-session ledgers". Ownership follows freshest knowledge, not creation:
   whoever last performs a sanctioned merge is the current custodian.
   ~~Original text: every registry (ledger, checklist, principles doc) has
   exactly one curator; everyone else contributes through report sections, never
   by editing the file.~~ Struck rather than deleted per `provenance-stated`.
   *Why it changed:* one-owner-forever left `defect-cause-patterns.md` stale for
   a week under a dormant creator-owner.
```

**The slug and the `SUPERSEDED` marker must be on the same line.** Guard G1
checks line-by-line; splitting them across lines makes the guard fail on the
very fix it exists to verify. Do not reflow this paragraph without re-running
the guard.

- [ ] **Step 3: Add the three new principles**

Append after principle 7:

```markdown
8. **`sibling-status-sweep`** — editing a doc's status claim re-verifies that
   doc's other present-tense status claims against HEAD.
   *Why:* proven 3× on 2026-08-11 (a gitignore proposal echoed at three sites;
   a retired pin-chain claim; the register's own stale copy). Full rule text and
   the still-true / falsified / unverifiable disposition live in
   `.ai/rules/docs.md`.
9. **`authority-not-relayed`** — a peer session's report carries information,
   never authority; an operator narrowing is lifted only by the operator.
   *Why:* two near-misses in the 2026-08 rounds where a session acted on a
   reasonable-sounding derivation of someone else's approval. Already operating
   unprompted at `505f043`, which declined to delete a worthless test because
   the approval in hand was a peer's relay.
10. **`governs-itself`** — a governance rule binds the document that states it.
    Check the document you wrote a rule in against that rule before committing.
    *Why:* four instances by 2026-08-12 — this file stating `one-owner-registry`
    after its own model retired it; the concurrent-session protocol carrying an
    unenumerable count hours after being written; `defect-cause-patterns.md`
    leaving its custodian line unstamped at `6e23af0`; and the same ledger
    registering its own `one-home` violation (`0df05ee`).
11. **`control-preconditions`** — when a discipline is accepted as a control,
    state what it depends on. *"Safe provided everyone obeys a rule"* is weaker
    than *"safe"*, and weaker still when enforceability rests on an incidental
    property of the current system. Corollary: **a discipline is not a
    mechanism; a guard is.**
    *Why:* measured 2026-08-12 — the no-commits-during-a-suite rule silently
    depended on runs being slow; a ~1s Pest 5 TIA replay reaches the same
    unserialised state and nobody perceives it as a suite in flight.
```

- [ ] **Step 4: Update the change protocol to name its guard**

Replace the `## Change protocol` body with:

```markdown
Amendments follow these principles' own rules: evidence-backed why, registered
at decision time, slugs per `names-carry-family`, and — per `governs-itself` —
checked against the principles they amend before committing.

Pinned by `tests/Feature/GovernanceDocsGuardTest.php`: retired slugs may not be
restated as current, cited paths must exist, and custodian lines must cite real
commits.
```

- [ ] **Step 5: Sweep this file's own status claims**

Per `sibling-status-sweep` — and per `governs-itself`, which this file now
states — re-read the whole file and re-verify every present-tense claim against
HEAD before committing.

- [ ] **Step 6: Commit**

```bash
git add docs/phase-02/dashboard-governance-principles.md
git commit -m "docs(governance): retire one-owner-registry at its source and widen scope past the dashboard"
```

---

### Task 3: Add the pointer rule to `.ai/rules/docs.md`

**✅ TEXT APPROVED by the operator 2026-08-12** — the exact wording in Step 1 is
approved as drafted. Implement it verbatim. Changing so much as a clause
re-opens the gate, because what was approved is the text, not the intent: both
existing entries in this file were approved individually, and that is a house
rule rather than a formality.

This task is otherwise unblocked and can run alongside Task 4.

Per the operator's 2026-08-12 decision the two existing rules are **kept
unchanged** — this is added as a third, not a replacement.

**Files:**
- Modify: `.ai/rules/docs.md`

**Interfaces:**
- Consumes: the principles doc from Task 2 must exist at the cited path.
- Produces: the `docs/**` binding that guard G3 checks for path validity.

- [ ] **Step 1: Append the third rule**

Append to `.ai/rules/docs.md`:

```markdown
## Governance principles bind all docs work
Before editing any file under `docs/**`, read
`docs/phase-02/dashboard-governance-principles.md` — it carries every governance
principle with the evidence that earned it, and it binds all documentation work
in this repo, not the dashboard program alone (operator ruling 2026-08-12,
superseding the 2026-08-03 dashboard-only scope). The two rules above are the
operative detail for living ledgers and status edits; the principles doc is the
why, plus the rules that have no other home. Amending a principle is an operator
decision, like amending this file.
```

- [ ] **Step 2: Verify the index still routes correctly**

```bash
cat .ai/rules/index.md
```

Expected: the `docs/**` → `.ai/rules/docs.md` row is present and unchanged. No
edit needed — the new rule lives in an already-routed file.

- [ ] **Step 3: Commit**

```bash
git add .ai/rules/docs.md
git commit -m "docs(rules): point docs work at the governance principles"
```

---

### Task 4: Build the three guards

Three checks, one file, one concern: governance artifacts telling the truth
about themselves. Guard G1 is written **before** Task 2 runs so it fails against
real stale text.

**Files:**
- Create: `tests/Feature/GovernanceDocsGuardTest.php`

**Interfaces:**
- Consumes: `docs/phase-02/dashboard-governance-principles.md` (Task 2 state),
  `.ai/rules/docs.md` (Task 3 state).
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the three failing tests**

Create `tests/Feature/GovernanceDocsGuardTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * Governance artifacts must tell the truth about themselves.
 *
 * Every failure these guards catch has already happened in this repo:
 * a retired rule left standing as current in two documents after being struck
 * in a third; a custodian line naming a session that was no longer the
 * custodian; and — the failure this file exists to prevent next — a rename
 * leaving a citation pointing at a path that no longer exists.
 *
 * Evidence and rationale: docs/superpowers/specs/2026-08-12-governance-generalization-design.md
 */

use Illuminate\Support\Facades\Process;

/**
 * The one file where a principle is BINDING rather than merely cited.
 *
 * Scope matters here. An earlier draft of this guard scanned every active doc
 * and produced six false positives against one true positive: the retired slug
 * legitimately appears in analysis, in history, and in a list of principles a
 * ledger runs under. Mentioning a retired rule is healthy; STATING it as
 * currently binding is the defect. Per `readable-binding`, a rule binds only
 * where its text can be read as a rule — which is this file, and no other.
 * A guard with a 6:1 false-positive rate gets skipped within a week, and a
 * skipped guard is worse than none.
 */
const GOVERNANCE_PRINCIPLES_DOC = 'docs/phase-02/dashboard-governance-principles.md';

/**
 * Retired principle slugs, mapped to the marker that makes a mention legitimate.
 * Inside the principles doc a retired slug may appear ONLY on a line that also
 * carries its marker.
 *
 * @var array<string, string>
 */
const RETIRED_GOVERNANCE_SLUGS = [
    'one-owner-registry' => 'SUPERSEDED',
];

/**
 * Living ledgers that carry a "Custodian at last merge:" header line.
 *
 * @var list<string>
 */
const CUSTODIED_LEDGERS = [
    'docs/research/defect-cause-patterns.md',
];

it('does not state a retired principle as currently binding', function (): void {
    $offenders = [];

    $lines = file(base_path(GOVERNANCE_PRINCIPLES_DOC), FILE_IGNORE_NEW_LINES);

    foreach ($lines as $index => $line) {
        foreach (RETIRED_GOVERNANCE_SLUGS as $slug => $marker) {
            if (str_contains($line, $slug) && ! str_contains($line, $marker)) {
                $offenders[] = GOVERNANCE_PRINCIPLES_DOC.':'.($index + 1)
                    ." states '{$slug}' without '{$marker}'";
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('cites only paths that exist', function (): void {
    $sources = [
        'docs/phase-02/dashboard-governance-principles.md',
        '.ai/rules/docs.md',
    ];

    $missing = [];

    foreach ($sources as $source) {
        $body = file_get_contents(base_path($source));

        preg_match_all('#`((?:docs|tests|app|\.ai)/[A-Za-z0-9._/-]+\.(?:md|php))`#', $body, $matches);

        foreach (array_unique($matches[1]) as $cited) {
            if (! file_exists(base_path($cited))) {
                $missing[] = "{$source} cites missing path {$cited}";
            }
        }
    }

    expect($missing)->toBe([]);
});

it('records a custodian whose cited commit really touched the ledger', function (): void {
    $problems = [];

    foreach (CUSTODIED_LEDGERS as $ledger) {
        $body = file_get_contents(base_path($ledger));

        expect($body)->toContain('Custodian at last merge:');

        // Bound the search to the custodian paragraph. An unbounded /s regex
        // would happily reach forward and adopt a SHA from elsewhere in a
        // 1,500-line file, so a MISSING custodian SHA would silently pass by
        // borrowing someone else's.
        $paragraphs = preg_split('/\n\s*\n/', $body);
        $block = null;

        foreach ($paragraphs as $paragraph) {
            if (str_contains($paragraph, 'Custodian at last merge:')) {
                $block = $paragraph;

                break;
            }
        }

        if ($block === null || preg_match('/`([0-9a-f]{7,40})`/', $block, $match) !== 1) {
            $problems[] = "{$ledger} has a custodian block citing no commit SHA";

            continue;
        }

        $sha = $match[1];

        $touched = Process::path(base_path())
            ->run("git log --format=%H -1 {$sha} -- {$ledger}");

        if (! $touched->successful() || trim($touched->output()) === '') {
            $problems[] = "{$ledger} cites {$sha}, which is not a commit that touched it";
        }
    }

    expect($problems)->toBe([]);
});
```

- [ ] **Step 2: Run the guards and confirm G1 fails on real stale text**

```bash
php artisan test --compact --filter=GovernanceDocsGuardTest
```

Expected **before Task 2 lands**: the retired-principle test FAILS with exactly
one offender — `docs/phase-02/dashboard-governance-principles.md:32 states
'one-owner-registry' without 'SUPERSEDED'`. The other two tests PASS.

If it passes here, stop — either Task 2 already ran, or the detection is wrong
and the guard is worthless. Check which before continuing.

If it reports **more than one** offender, the scope narrowing has been undone —
re-read the `GOVERNANCE_PRINCIPLES_DOC` docblock before "fixing" the extra
findings. They are almost certainly legitimate mentions in analysis or history.

- [ ] **Step 3: Run Task 2, then re-run the guards**

```bash
php artisan test --compact --filter=GovernanceDocsGuardTest
```

Expected: all three PASS.

- [ ] **Step 4: Mutation-check each guard**

A guard that cannot fail is not a guard. For each, break it, watch it fail,
restore it:

1. Add the line `12. **\`one-owner-registry\`** — reinstated.` to
   `docs/phase-02/dashboard-governance-principles.md`. Re-run — the
   retired-principle test must FAIL naming that line number. Remove the line.
   (Adding it to any *other* doc must NOT fail the guard — that is the scope
   narrowing working, and is worth confirming once.)
2. Add `` `docs/does-not-exist.md` `` to `.ai/rules/docs.md`. Re-run —
   cited-paths test must FAIL. Remove it.
3. Change the SHA in `defect-cause-patterns.md`'s custodian line to
   `0000000`. Re-run — custodian test must FAIL. Restore the real SHA with
   `git checkout -- docs/research/defect-cause-patterns.md`.

Record all three observed failure messages in the commit body.

- [ ] **Step 5: Format and lint**

```bash
vendor/bin/pint --dirty --format agent
composer filacheck -- --dirty
```

Expected: pint reports the file formatted; filacheck reports no violations.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/GovernanceDocsGuardTest.php
git commit -m "test(governance): pin retired slugs, cited paths and custodian commits"
```

---

### Task 5: Rename the principles doc to a scope-honest name

**GATED — do not start until `docs/phase-02/consolidated-open-findings.md` is
being regenerated in the same round.** Operator decision 2026-08-12. The
register cites the old path twice and cannot be hand-edited; renaming outside a
regeneration window publishes a dangling pointer into a governed artifact.

**Files:**
- Rename: `docs/phase-02/dashboard-governance-principles.md` →
  `docs/phase-02/governance-principles.md`
- Modify: `docs/phase-02/current-project-state.md`,
  `docs/phase-02/dashboard-widget-principles.md`,
  `docs/phase-02/dashboard-metrics-phase-2R-handoff.md`, `.ai/rules/docs.md`
- Modify (by report to its custodian, not directly):
  `docs/research/defect-cause-patterns.md`

**Interfaces:**
- Consumes: Tasks 2, 3 and 4 complete; the register regeneration in flight.
- Produces: the new path, which guard G3 will then check.

- [ ] **Step 1: Confirm the gate is actually open**

```bash
git log --oneline -3 -- docs/phase-02/consolidated-open-findings.md
```

Expected: a regeneration commit in this round, or a session actively producing
one. If neither, STOP — the gate is closed and this task does not run.

- [ ] **Step 2: Re-measure the citation set**

```bash
grep -rn "dashboard-governance-principles" docs/ .ai/ prompts/
```

Expected at time of writing: 6 occurrences across 5 files. **Do not trust that
number** — re-run it and work from the output. It was 6 on 2026-08-12 and every
task above may have changed it.

- [ ] **Step 3: Rename**

```bash
git mv docs/phase-02/dashboard-governance-principles.md docs/phase-02/governance-principles.md
```

- [ ] **Step 4: Update every citation the previous step listed, except the register**

Edit each file from Step 2's output, replacing
`dashboard-governance-principles.md` with `governance-principles.md`. Skip
`docs/phase-02/consolidated-open-findings.md` — its citations are corrected by
the regeneration, not by hand. Skip `docs/research/defect-cause-patterns.md` —
send its custodian the one-line change instead of editing it.

- [ ] **Step 5: Run the guards**

```bash
php artisan test --compact --filter=GovernanceDocsGuardTest
```

Expected: all three PASS. The cited-paths guard is what proves no dangling
reference survived — this is the task it was written for.

- [ ] **Step 6: Commit**

```bash
git add docs/phase-02/governance-principles.md docs/phase-02/dashboard-governance-principles.md docs/phase-02/current-project-state.md docs/phase-02/dashboard-widget-principles.md docs/phase-02/dashboard-metrics-phase-2R-handoff.md .ai/rules/docs.md
git commit -m "docs(governance): rename the principles doc to match its scope"
```

---

## What this plan deliberately does not do

- **No contribution zones, path partitions, commit mutex, dormancy clocks, or
  two-gear protocol.** Out of scope by operator decision 2026-08-12.
- **No edit to `consolidated-open-findings.md`.** Its two stale citations and
  its `:199`/`:146` one-owner text are regeneration input.
- **No guard for the two silently-failing controls** identified in spec §8.1
  (concurrency liveness checks and the commit mutex). Both belong to the dropped
  concurrent machinery.
- **No T1/T2 class table.** The spec proposed one; without the custody protocol
  it would be a list with nothing reading it. `CUSTODIED_LEDGERS` in the guard is
  the only membership list that now has a consumer.

## Routed by report, not implemented here

Two items belong to files this plan does not own. Send them; do not edit.

1. **`docs/research/defect-cause-patterns.md:48`** lists `one-owner-registry`
   among the principles that ledger runs under. That citation went stale on
   2026-08-11 and the file's own header (lines 14–26) already explains the
   supersession at length, so the list contradicts the header three dozen lines
   above it. One-line fix, but the file is custodian-governed — route it to the
   custodian with the evidence rather than editing. Deliberately **not** guarded:
   see the `GOVERNANCE_PRINCIPLES_DOC` docblock for why citations are not
   bindings.
2. **The `silent-cap` sighting from Task 1** — a `head -3` silently truncating a
   census, inside a measurement used to correct someone else's miscount. It is a
   new sighting of an existing catalogued pattern (alias P6) and belongs in the
   ledger's entry for it. Same routing.
