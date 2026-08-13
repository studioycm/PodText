# Workstream-namespaced task IDs

**Status:** approved by the operator 2026-08-13. Not yet implemented.
**Constraint the operator set:** keep it simple. This is a naming convention plus
one table and one small test — not a mechanism.

## 1. The problem

Task/step IDs in this program are single letters plus a digit: `M2`, `B4`, `O1`,
`S2`. The workstream they belong to exists only in the **file path**, never in
the ID. So an ID is unambiguous exactly as long as it stays inside its own file.

Measured 2026-08-13 against HEAD:

| ID | distinct workstreams using it | example collision |
| --- | --- | --- |
| `M2` | 4 | media-picker action-modals race vs `step10r-m2-remove-episode-authors` |
| `B4` | 50 files | test-suite rethink vs step10r card renderers vs settings-performance |
| `O1` | 68 files | media-program packages vs media-operations-ux3 |
| `P1` `S2` `A1` `R1` `C1` `G1` `UX2` | 20–43 files each | 5+ unrelated programs |

This is not a hypothetical. The commit immediately before this spec, `0464bbb`,
exists solely to undo two collisions: `E0-E6` collided with the operator's road
letters (where `E` is the larastan session, and the operator asked whether the
session had wandered into `E`'s work), and the findings labels `F1-F8` collided
*both* with road letter `F` and with `F<number>`, already the findings-ledger
numbering in `open-findings-triage.md`. A reader hitting `F2` had two plausible
wrong places to look it up. A whole commit was spent renaming.

Two facts constrain any fix:

- **IDs already leak out of docs into code and history.** 46 references to `B3`
  in `app/` and `tests/` docblocks, 29 to `O2`, 26 to `R4`; and into commit
  subjects (`F13`, `A18`, `DP9`).
- **So a rename sweep is impossible.** Commit subjects cannot be rewritten, and
  renaming the code docblocks would break their link to the docs and commits
  that used the old name.

## 2. Format

    TOKEN-localid

`PICKER-M2` · `FRONT-S2` · `SUITE-B4` · `AUTHZ-1c`

`TOKEN` is an uppercase word naming the workstream, drawn from the registry in
§4. `localid` is whatever letter+digit that workstream already uses, unchanged —
nobody relearns their own habits. A new ID continues that workstream's own
existing sequence; a workstream with no sequence yet starts at `1`.

**An ID is issued once and never renamed.** Kind (`plan` / `fix` / `run` /
`finding`) and status are columns in the owning doc's table, never part of the
name. This is what makes it safe to write an ID into a commit subject or a code
docblock: a finding that later becomes a fix keeps its ID, so no prior reference
ever rots.

## 3. The travel rule — no retrofit

The convention is scoped to **travel**, not to existence:

> A bare ID is fine inside its own owning file. The moment it leaves — another
> doc, a commit subject, a code docblock, a cross-session brief, an agent memory
> file — it must carry its token.

So `M2` stays `M2` throughout `media-picker-m2-cross-session-brief.md`, and the
same reference in a commit subject or memory index is `PICKER-M2`. Nothing in
the existing ~1,000 IDs is edited. The registry's `owns` column *is* the legacy
map: an old bare ID resolves by looking up which token owns the file it lives in.

## 4. The registry

**One file — `.ai/guidelines/workstream-ids.md`** — holding both the convention
text of §2/§3 and the token table below.

Location decided by the operator 2026-08-13, and the reasoning is worth keeping
because it looks wrong at first glance. `CLAUDE.md` and `AGENTS.md` are
*generated*: Boost composes every `.ai/guidelines/*.md` into both, verbatim
(verified 2026-08-13 — the 111-line body of `tooling-quality.md` appears intact
in each). So a guidelines file **survives regeneration by being its source**,
and reaches Claude, Codex and Junie alike. The overwrite risk applies to
hand-editing the generated files, which nothing here does.

Always-on rather than glob-matched, because **the collision happens where a path
glob cannot see it**: an ID goes wrong when it travels into a commit subject, an
`app/` docblock, or a brief outside the repo. A `docs/**` rule never loads for
any of those. `.ai/rules/` was rejected for that reason, not for cost.

Two columns: `token` and `owns` (one or more comma-separated globs, relative to
repo root).

Tokens are **issued on demand**, not census-collected. Adding a row is how you
claim a token; a workstream with no travelling IDs needs no row. The seed below
covers exactly the workstreams with a proven collision in §1.

| token | owns |
| --- | --- |
| `FRONT` | `docs/research/public-front-v2/`, `docs/phase-02/public-front-v2-*` |
| `SUITE` | `docs/research/test-suite/`, `docs/research/test-suite-rethink-notes.md` |
| `SETPERF` | `docs/research/settings-performance/` |
| `MEDIA` | `docs/research/media-program/` |
| `MEDIAOPS` | `docs/research/media-operations-ux3/` |
| `PICKER` | `docs/research/images-media/`, `docs/research/media-picker-*` |
| `CURATOR` | `docs/research/curator-g1/` |
| `AUTHZ` | `docs/phase-02/authz*` |

`ARCH1` and `AUTHZ1` are already globally unique and get no special handling —
leave them exactly as they are, and do not reuse those strings for anything else.
(`ARCH1` has no owning directory of its own; it lives inside settings-performance.
Giving it a row would have forced a special case into the guard test, so it does
not get one.)

**Allocation does not reintroduce the race this program already has.** A new
token is one registry row under the commit mutex, and new workstreams are rare.
Local IDs *within* a workstream are allocated by whoever owns that workstream's
doc, which `PROTOCOL.md` §1's path partition already serializes.

## 5. The guard test

`tests/Feature/WorkstreamRegistryGuardTest.php` — parses the table out of
`.ai/guidelines/workstream-ids.md` (the source, never the generated files) and
asserts four things:

1. **at least 8 rows parse** — the canary. Without it, a broken parser makes
   assertions 2 and 3 pass vacuously over an empty set.
2. **no duplicate token.**
3. **every glob in every row matches at least one existing path.**
4. **the table is present in `CLAUDE.md` and `AGENTS.md`** — i.e. someone ran
   `composer boost:sync` after editing the source.

No special cases, because every seeded row owns a real path.

Assertion 4 earns its place: without it, adding a token to the source and
forgetting to sync leaves the new token invisible to every agent, which defeats
the registry silently. It does mean claiming a token costs a `boost:sync` — the
right friction, since a token nobody can see is not claimed. This mirrors
`FilacheckAgentModeGuardTest`, which `boost:sync` already runs.

Deliberately *not* asserted: that new docs avoid bare IDs. That check has no
reliable signal against a docs tree legitimately full of in-file bare IDs, and a
guard that cries wolf gets disabled. Revisit only if the registry proves itself
first.

## 6. Deliberately not built

- No rename of existing IDs, in docs, code, or history.
- No kind (`FIX`/`PLN`/`RUN`) inside the ID — it would make the ID mutable.
- No completeness sweep forcing all 22 research subdirectories into the registry.
  That would be retrofit through the back door.
- No linter for bare IDs.

## 7. Companion decision — the test-count chatter

Raised in the same session, resolved with **no code**.

The chatter had a specific source: session briefs hand out a count as a
pre-registered expectation — `tia-session-brief.md:21` says *"expect exactly
2,029 tests / 21,037 assertions"*, and `ef-session-brief.md:42` repeats it with
the hedge *"unless C or D changed the count"*. A session then measures, finds a
different number, and investigates a non-problem: another session added tests.
The hedge shows the author already knew it would drift.

Commit `0464bbb` records the cost in its own words: the gate count "moved twice
inside one session (2,029 -> 2,032 -> 2,035) and a peer already quoted the
intermediate figure as current." The response at the time was to write the count
into two more docs. That is the loop this rule closes.

The counts split by purpose. Only the first was ever a problem:

| use | needs an exact count? | status |
| --- | --- | --- |
| one session assuring another it broke nothing | **no** — green/red + sha is the whole signal | this was the chatter |
| TIA / speed science (`2,025 / 21,024`, `366.7s`, `12.9×` replay) | **yes** | already correct: deliberate runs, one session, tree frozen by T23b, logged in one doc |
| gate proof in `current-project-state.md` | no, but harmless | already correct: a dated record nobody reconciles |

Two rules, both in `~/.cache/podtext-coord/PROTOCOL.md` §3, zero build cost:

1. **A brief never hands a session an expected count.** It says *expect green*.
   Count drift between sessions is the normal result of parallel work, not a
   signal — there is nothing to investigate and nothing to report. One stated
   exception: inside a frozen measurement window (T23b holding, single session)
   an exact count is a valid control, and the brief must label it as a
   frozen-window control so nobody carries it outside.
2. **A count in a doc is a dated measurement, not a live fact.** It keeps its
   date and stays put. A doc's count going out of date is not a defect.

**What this gives up:** no way to ask "is the suite green right now" without
running it. Nothing is actually lost — a count never answered that question
either. `cat ~/.cache/podtext-test-lane/*.lock` already reports who ran the suite
last, with what argv and when, which is the only coordination question that was
real.

An automated record was designed and rejected as over-built for the problem. For
the record of why there is no cheap middle: the existing
`register_shutdown_function` in `tests/Pest.php` cannot reach the exit code or
the counts without the PHPUnit event system, so the only options were rule-only
or a full event subscriber plus a `<extensions>` block.

## 8. Build order and coordination

1. `.ai/guidelines/workstream-ids.md` — the convention plus the eight seed rows.
2. `composer boost:sync`, then read `git diff CLAUDE.md AGENTS.md` before staging.
3. `tests/Feature/WorkstreamRegistryGuardTest.php` (`php artisan make:test --pest`).
4. Two paragraphs into `PROTOCOL.md` §3 (the §7 rules).

Moving the registry out of `docs/phase-02/` into `.ai/guidelines/` **removed** a
grant rather than adding one: `.ai/rules/**` is the arbiter's under `PROTOCOL.md`
§1 and is no longer touched. Step 4 still needs one — `PROTOCOL.md` lives outside
the repo but is the arbiter's instrument.

Two coordination facts specific to step 2:

- It rewrites `CLAUDE.md` and `AGENTS.md`, tracked files that every session
  reads. That is a shared-file mutation, so it wants the arbiter's awareness even
  though neither file is named in the partition table.
- `.ai/guidelines/` is **test input**: `PROTOCOL.md` §4 records that
  `PublicStep9RMenuHeaderUxFixesTest` reads `.ai/guidelines/tooling-quality.md`
  at runtime. Adding a sibling file does not affect that test, but T23b binds
  regardless — do not run step 1 or 2 while any suite is in flight.

Everything takes the commit mutex, because the git index is shared.
