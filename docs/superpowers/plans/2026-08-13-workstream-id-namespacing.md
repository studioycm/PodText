# Workstream ID Namespacing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make a task/step ID unambiguous once it leaves the file that defines it, by prefixing it with a registered workstream token.

**Architecture:** One always-on guidelines file holds both the convention and an eight-row token table. Boost composes `.ai/guidelines/*.md` verbatim into `CLAUDE.md` and `AGENTS.md`, so the convention is in every agent's context rather than loaded by a path glob — which matters because IDs go wrong in commit subjects and out-of-repo briefs, where no `docs/**` rule ever loads. A four-assertion Pest test keeps the registry honest and forces a `boost:sync` when a token is added.

**Tech Stack:** Markdown, Laravel Boost 2.5+, Pest 5, PHP 8.4.

**Spec:** `docs/superpowers/specs/2026-08-13-workstream-id-namespacing-design.md`

## Global Constraints

- **Nothing is retrofitted.** No existing ID is renamed anywhere — not in docs, not in `app/`, not in `tests/`, not in history. A bare ID stays bare inside its owning file.
- **Every commit takes the mutex**: `~/.cache/podtext-coord/claim.sh acquire <label>` → `git add <explicit pathspecs>` → `git commit -F <file>` → `release`. Never `-A`, never `-a`, never `.`.
- **Compose commit messages into a FILE** and use `git commit -F <file>`. Never a heredoc, never a multi-line `-m` inside the mutex window.
- **Sequence the mutex window with `;`, never `&&`** — an aborted `&&` chain skips the release and leaves the mutex held by a live PID. Verify with `git log --oneline -1` afterwards.
- **Every pest run takes the machine-global lane**, filtered runs included — `flock()` is at `tests/Pest.php:101`, in bootstrap, before test selection. `composer boost:sync` ends in `php artisan test --filter=FilacheckAgentModeGuard`, so **the sync takes the lane too**. Announce to the arbiter before Task 1 Step 2, Task 2 Step 2, and Task 2 Step 4.
- **T23b:** no tracked-file edits while any suite is in flight, yours or another session's.
- **Do not push.** The arbiter holds push timing for the round.
- **`composer boost:sync` needs the operator's own approval**, not a peer's relay. Do not run Task 2 without it.

---

## File Structure

| File | Responsibility |
| --- | --- |
| `.ai/guidelines/workstream-ids.md` | CREATE — the convention and the token table. The single source; everything else derives from it. |
| `CLAUDE.md`, `AGENTS.md` | MODIFY, by `composer boost:sync` only — never by hand. Generated output. |
| `tests/Feature/WorkstreamRegistryGuardTest.php` | CREATE — parses the table from the source file and holds it to four invariants. |
| `~/.cache/podtext-coord/PROTOCOL.md` | NOT OURS. Task 3 sends the arbiter a delta; we do not edit it. |

---

## Task 1: The registry and its first three invariants

**Files:**
- Create: `.ai/guidelines/workstream-ids.md`
- Test: `tests/Feature/WorkstreamRegistryGuardTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `workstreamRegistryRows(): list<array{token: string, globs: list<string>}>`, a file-scope function in the test file. Task 2 calls it.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/WorkstreamRegistryGuardTest.php`:

```php
<?php

declare(strict_types=1);

/*
 * Workstream ID registry guard.
 *
 * The registry lives in .ai/guidelines/ because Boost composes that directory
 * verbatim into CLAUDE.md and AGENTS.md, making the convention always-on
 * context rather than a path-matched rule. That placement is deliberate: IDs go
 * wrong when they travel into commit subjects and briefs under ~/.cache/, and a
 * docs/** rule never loads for either.
 *
 * These assertions keep the registry INTERNALLY honest. They detect no
 * collision at the moment one is issued — in this program detection has only
 * ever happened at the human. See §1.1 of
 * docs/superpowers/specs/2026-08-13-workstream-id-namespacing-design.md.
 */

/**
 * Parse the token table out of the registry source.
 *
 * Scoped to the "## Tokens" section so a second table added later cannot
 * silently become registry rows.
 *
 * @return list<array{token: string, globs: list<string>}>
 */
function workstreamRegistryRows(): array
{
    $lines = file(base_path('.ai/guidelines/workstream-ids.md'), FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        return [];
    }

    $rows = [];
    $inTokens = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (str_starts_with($trimmed, '## ')) {
            $inTokens = $trimmed === '## Tokens';

            continue;
        }

        if (! $inTokens || ! str_starts_with($trimmed, '|')) {
            continue;
        }

        $cells = array_map(
            static fn (string $cell): string => trim(str_replace('`', '', $cell)),
            array_slice(explode('|', $trimmed), 1, -1),
        );

        if (count($cells) !== 2 || $cells[0] === 'token' || str_starts_with($cells[0], '---')) {
            continue;
        }

        $rows[] = [
            'token' => $cells[0],
            'globs' => array_map(static fn (string $g): string => trim($g), explode(',', $cells[1])),
        ];
    }

    return $rows;
}

it('parses at least the eight seeded workstream tokens', function (): void {
    // The canary. Without a floor, a parser that returns nothing makes every
    // other assertion in this file pass vacuously over an empty set.
    expect(count(workstreamRegistryRows()))->toBeGreaterThanOrEqual(8);
});

it('issues every workstream token exactly once', function (): void {
    $tokens = array_column(workstreamRegistryRows(), 'token');

    expect(array_unique($tokens))->toHaveCount(count($tokens));
});

it('points every workstream token at a path that exists', function (): void {
    $unmatched = [];

    foreach (workstreamRegistryRows() as $row) {
        foreach ($row['globs'] as $glob) {
            // rtrim the trailing slash so a directory row globs to the
            // directory itself. glob() returns false on error, [] on no match;
            // both mean the row points at nothing.
            $matches = glob(base_path(rtrim($glob, '/')));

            if ($matches === false || $matches === []) {
                $unmatched[] = "{$row['token']} => {$glob}";
            }
        }
    }

    expect($unmatched)->toBe([]);
});
```

- [ ] **Step 2: Announce the lane to the arbiter, then run the test to verify it fails**

Message the arbiter that you are taking the lane for one filtered run, and wait for hand-over. Then:

```bash
php artisan test --compact --filter=WorkstreamRegistryGuard
```

Expected: **1 failed, 2 passed.** `file()` on a missing path emits a warning and returns `false`, so the parser returns `[]`. Only the count assertion fails, at `0 >= 8`; the duplicate-token and glob assertions both pass — vacuously, over an empty set.

That split is the point, so do not read the two greens as progress. It is a live demonstration of why the canary exists: without it, this step would report 2 passed / 0 failed against a registry that does not exist.

- [ ] **Step 3: Create the registry source**

Create `.ai/guidelines/workstream-ids.md`:

```markdown
# Workstream ID Naming

## Purpose

A task or step ID must stay unambiguous when it leaves the file that defines it.

## Format

`TOKEN-localid` — `PICKER-M2`, `FRONT-S2`, `SUITE-B4`, `AUTHZ-1c`.

`TOKEN` comes from the table below. `localid` continues that workstream's own
existing sequence; a workstream with no sequence yet starts at `1`.

## Rules

- An ID is issued once and never renamed. Kind (plan, fix, run, finding) and
  status are columns in the owning doc's table, never part of the name.
  Renaming after artifacts exist leaves two live names rather than retiring
  one — preserved logs keep the old label in their filenames.
- A bare ID is fine inside its own owning file. The moment it leaves — another
  doc, a commit subject, a code docblock, a cross-session brief, an agent
  memory file — it must carry its token.
- Nothing existing is retrofitted. An old bare ID resolves by looking up which
  token owns the file it lives in.
- Claim a new token by adding a row below, then run `composer boost:sync`. A
  token not composed into `CLAUDE.md` and `AGENTS.md` is not claimed.

## Tokens

| token | owns |
| --- | --- |
| `AUTHZ` | `docs/phase-02/authz*` |
| `CURATOR` | `docs/research/curator-g1/` |
| `FRONT` | `docs/research/public-front-v2/`, `docs/phase-02/public-front-v2-*` |
| `MEDIA` | `docs/research/media-program/` |
| `MEDIAOPS` | `docs/research/media-operations-ux3/` |
| `PICKER` | `docs/research/images-media/`, `docs/research/media-picker-*` |
| `SETPERF` | `docs/research/settings-performance/` |
| `SUITE` | `docs/research/test-suite/`, `docs/research/test-suite-rethink-notes.md` |

`ARCH1` and `AUTHZ1` are already globally unique. Leave them exactly as they
are and do not reuse those strings.

## What this does not do

This convention is not enforced outside the repo. Session briefs under
`~/.cache/podtext-coord/` are ID-issuing surfaces that no guard here can reach —
the `E0-E6` collision of `0464bbb` was created in one. The only thing governing
an out-of-repo brief is the convention the agent is already carrying.
```

- [ ] **Step 4: Announce the lane, then run the test to verify it passes**

```bash
php artisan test --compact --filter=WorkstreamRegistryGuard
```

Expected: 3 passed. If "points every workstream token at a path that exists" fails, read the printed `TOKEN => glob` list — do not adjust the assertion. Verify the glob by hand with `find`, never with a shell loop that expands a glob from a variable: in zsh that silently yields zero matches (bash expands it, zsh does not without `GLOB_SUBST`), which is how `AUTHZ` nearly got dropped from this table.

- [ ] **Step 5: Commit**

Write this text to `"${TMPDIR:-/tmp}/workstream-ids-msg.txt"` with your file-writing tool — not a heredoc, which is what broke a mutex window on 2026-08-12:

```
feat(ids): register workstream tokens, so M2 stops meaning four things

Measured against HEAD: M2 is used by four unrelated workstreams, B4 spans 50
files across three programs, O1 spans 68. The workstream lived only in the
file path, so an ID was unambiguous exactly as long as it stayed in its own
file -- and IDs do not stay there. 46 references to B3 sit in app/ and tests/
docblocks, and IDs reach commit subjects too. 0464bbb was a whole commit spent
undoing two such collisions.

TOKEN-localid, issued once and never renamed: kind is a column, not part of
the name, so a finding that becomes a fix keeps its ID and no commit subject
rots. Scoped to travel, not existence -- bare IDs stay as they are inside
their owning file and nothing in the existing ~1,000 is touched.

The registry is one always-on guidelines file rather than a docs/** rule,
because an ID goes wrong where a path glob cannot see it: commit subjects,
app/ docblocks, briefs under ~/.cache/. The guard's count assertion is a
canary -- without it the other two pass vacuously over an empty parse, which
this commit's own red-to-green cycle demonstrated.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
```

Then, sequenced with `;` so the release always runs:

```bash
~/.cache/podtext-coord/claim.sh acquire workstream-ids ; git add .ai/guidelines/workstream-ids.md tests/Feature/WorkstreamRegistryGuardTest.php ; git commit -F "${TMPDIR:-/tmp}/workstream-ids-msg.txt" ; ~/.cache/podtext-coord/claim.sh release workstream-ids ; git log --oneline -1
```

---

## Task 2: Compose it into the agent files, and pin that it stays composed

**Requires the operator's own approval**, not the arbiter's and not the TIA session's. Both peers declined to grant it. Do not begin without it.

**Files:**
- Modify: `tests/Feature/WorkstreamRegistryGuardTest.php` (append one test)
- Modify: `CLAUDE.md`, `AGENTS.md` — by `composer boost:sync` only

**Interfaces:**
- Consumes: `workstreamRegistryRows()` from Task 1.
- Produces: nothing further.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/WorkstreamRegistryGuardTest.php`:

```php
it('has been composed into the generated agent files', function (): void {
    // CLAUDE.md and AGENTS.md are generated: Boost composes .ai/guidelines/*.md
    // into both verbatim. A token added to the source but never synced is
    // invisible to every agent, so the registry would fail silently. Fix by
    // running `composer boost:sync` — never by hand-editing either file, which
    // the next regeneration would discard.
    $rows = workstreamRegistryRows();

    expect($rows)->not->toBeEmpty();

    foreach (['CLAUDE.md', 'AGENTS.md'] as $generated) {
        $contents = (string) file_get_contents(base_path($generated));

        $missing = [];

        foreach ($rows as $row) {
            if (! str_contains($contents, "| `{$row['token']}` |")) {
                $missing[] = "{$generated}: {$row['token']}";
            }
        }

        expect($missing)->toBe([]);
    }
});
```

- [ ] **Step 2: Announce the lane, then run the test to verify it fails**

```bash
php artisan test --compact --filter=WorkstreamRegistryGuard
```

Expected: 3 passed, 1 failed — the new test prints `["CLAUDE.md: AUTHZ", "CLAUDE.md: CURATOR", …]`, because the source exists but nothing has composed it yet.

- [ ] **Step 3: Sync, then read the diff before staging anything**

This takes the lane — its last step is `php artisan test --filter=FilacheckAgentModeGuard`. Announce first.

```bash
composer boost:sync
```

Then, and this is the gate rather than a formality — the repo's own `CLAUDE.md` says the sync is safe *because a human runs it and reads the diff*:

```bash
git diff --stat CLAUDE.md AGENTS.md
```

Expected: both files changed, insertions only, roughly 45 lines each. **Stop and report if the diff touches anything other than the new `=== .ai/workstream-ids rules ===` block** — an unrelated section moving means Boost adopted or dropped something else, which is a decision, not a refresh.

- [ ] **Step 4: Announce the lane, then run the test to verify it passes**

```bash
php artisan test --compact --filter=WorkstreamRegistryGuard
```

Expected: 4 passed.

- [ ] **Step 5: Commit**

Write this text to `"${TMPDIR:-/tmp}/workstream-ids-msg2.txt"` with your file-writing tool:

```
build(boost): compose the token registry into the generated agent files

CLAUDE.md and AGENTS.md are regenerated output, never hand-edited -- Boost
composes every .ai/guidelines/*.md into both verbatim, which is why the
registry survives regeneration by BEING its source rather than despite it.

The fourth guard assertion pins that the composition actually happened. A
token added to the source but never synced is invisible to every agent, so the
registry would fail silently -- the worst failure mode for a thing whose only
job is being seen. Claiming a token now costs a boost:sync, which is the right
friction: a token nobody can see is not claimed.

Diff read before staging, per the repo's own rule that the sync is safe
because a human reads it.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
```

Then:

```bash
~/.cache/podtext-coord/claim.sh acquire workstream-ids ; git add tests/Feature/WorkstreamRegistryGuardTest.php CLAUDE.md AGENTS.md ; git commit -F "${TMPDIR:-/tmp}/workstream-ids-msg2.txt" ; ~/.cache/podtext-coord/claim.sh release workstream-ids ; git log --oneline -1
```

---

## Task 3: Hand the arbiter the `PROTOCOL.md` §3 delta

Not an edit. `PROTOCOL.md` is outside the repo and is the arbiter's instrument; one file with two authors is the collision this whole design exists to prevent.

**§2c already exists** and covers more than the spec's §7 asked for: the `N at <sha>` rule, the three-sessions-three-counts instance (6/7/8, all correct when taken), the gate chain `2,029 → 2,032 → 2,035`, the frozen-window exception placed *inside* the rule's sentence, and a corollary about relaying unverified identifiers. Do not duplicate any of it.

- [ ] **Step 1: Send the arbiter exactly these three clauses, as a delta**

1. **A brief never hands a session a bare expected count.** It says *expect green*, or a number written `N at <sha>` when the brief covers a frozen measurement window. This is the clause §2c does not yet carry: §2c governs how a count is *written*, and this governs whether a brief hands one forward at all. The failure originated in briefs — `tia-session-brief.md:21` and `ef-session-brief.md:42`.
2. **A count differing from a peer's is expected, not a signal.** Do not investigate it and do not report it. Drift is the normal consequence of parallel work; §2c proves it with three correct-but-different counts.
3. **A count already written in a doc keeps its date and stays put.** Going out of date is not a defect and needs no reconciling sweep — the response to the `2,029 → 2,035` drift was to write the number into two more docs, which is the loop.

- [ ] **Step 2: Record the arbiter's decision in the spec**

Whatever it places or declines, append one line to §7 of the spec naming the outcome. A decision that lives only in a chat message did not happen.

---

## Leftovers to report, not to fix silently

- **`8b834f9`'s subject overstates.** It reads "move the ID registry to `.ai/guidelines`" while its diff only revised the design doc to say it should live there. History is not being rewritten: three sessions share this tree, the commit sits behind two others, and per this design's own rule a rename after artifacts exist leaves two live names. State it in the final report instead.
- **No enforcement outside the repo.** Stated in the guidelines file itself and in spec §1.1.
- **The guard detects no collision at issue time.** It keeps the registry internally consistent, nothing more.
- **Fourteen research subdirectories hold no token.** Deliberate — tokens are issued on demand, and a completeness sweep would be retrofit through the back door.

## Final report must include

The four assertions passing; what `git diff CLAUDE.md AGENTS.md` showed; gate numbers written as `N at <sha>`; and the leftovers above. In this program "nothing left" gets verified, not accepted.
