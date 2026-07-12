# Codex Prompt — TS1: Test-Suite Performance (from the NAV1 timing report)

Work in the current local clone of `studioycm/PodText`.

ONE implementation run executing the NAV1 Suite Timing Report's ranked
proposal (in `docs/phase-02/admin-navigation-nav1-handoff.md`). Standing
runner rules: research/plan note BEFORE code (short — the NAV1 report IS the
research; write only an implementation plan doc), no push unless asked, no
`filacheck --fix`, NO Composer changes, en+he untouched (no UI work). The
handoff is a COMMITTED MARKDOWN FILE
(`docs/phase-02/test-suite-performance-ts1-handoff.md`) whose gate outcomes
are written into the file AFTER the gate passes and BEFORE the commit.

FINAL GATE ORDER: requirements sweep → `vendor/bin/pint --test` →
`vendor/bin/filacheck` → `npm run build` → FULL `php artisan test --profile`
LAST (once = once GREEN on final state; record every run; the profile output
is the after-measurement — if the artisan runner emits JSON without the
profile list, run `vendor/bin/pest --profile` once, sequentially, to recover
it, as NAV1 did).

## Preflight

```bash
git status --short --branch
git log --oneline -4
```

Clean tree; MP2's commit `465967f` expected at or near HEAD.

## Job 0 — corrections from the Fable audits (do first)

1. **Dashboard returns to the sidebar** (Yoni veto of NAV1's assumption):
   visible again as the FIRST item, ungrouped, before `פרק חדש`; wire through
   the central `AdminNavigationOrder` map; update the central-map test.
2. **Insert NAV1's missing ledger row** in the mini-step ledger:
   `NAV1 - Admin navigation restructure and suite timing diagnosis`,
   complete, commit `e59705b` (it was required by the NAV1 prompt and never
   inserted).
3. **Backfill MP2's record**: stamp MP2's commit hash `465967f` wherever the
   MP2 handoff/ledger reference it by message only, and write MP2's final
   gate outcomes into `docs/phase-02/maintenance-form-mp2-handoff.md` — the
   exact numbers are provided in the kickoff message; if they are not, record
   "gate outcomes reported only in the session chat" as an explicit gap line.
4. **Lesson refinement** in `docs/phase-02/ai-development-lessons.md`: gate
   outcomes are written into the handoff FILE after the gate passes and
   before the commit; "recorded in the session final" is chat, not a record,
   and does not satisfy the handoff rule.
5. Minor: deduplicate the two identical maintenance-419 closure bodies in
   `bootstrap/app.php` into one shared helper (behavior identical; covered by
   the existing maintenance CSRF tests).

## Job 1 — the fix (NAV1 proposal items 1, 3, 4; item 2 deferred)

BEFORE-measurement is already recorded (NAV1: 423 tests, ~485-489s; top 3
settings-save tests = 220s). Implement:

1. **Suppress backup-snapshot side effects in settings tests that are not
   testing backups/snapshots** (proposal #1, highest savings): the evidenced
   mechanism is `SettingsSaved` → system backup → snapshot scheduling → sync
   queue possibly running the snapshot job (node script) inside tests. Choose
   the cleanest lever with evidence — `Queue::fake()` at file-level setup for
   the hot settings test files, or a test-env guard the snapshot scheduler
   respects — WITHOUT weakening the tests that DO cover backups/snapshots
   (they keep their real paths). Every touched test file keeps proving what
   it proved: assertions unchanged, only side-effect cost removed. Tests that
   assert backup rows exist must keep creating backups (fake only the
   snapshot/queue layer there).
2. **Shared fixture/setup helper** for the settings-page test files
   (proposal #3) where repeated default-payload construction dominates —
   only if it stays a bounded, mechanical extraction.
3. **Split the 110s test** (`PublicFrontJsonSettingsArchitectureTest::it
   saves sanitized public front config...`, proposal #4) into focused saves
   per subsystem ONLY if the split preserves every existing assertion;
   otherwise leave it and record why.
4. Proposal #2 (migrating normalization assertions to validator unit tests)
   is DEFERRED — record it as the remaining TS2 candidate.
5. `--parallel` stays parked: after the changes, RE-EVALUATE on evidence and
   write the recommendation (do not enable it in the gate this run).

## Measurement and honesty rules

- No test deletions; no assertion removals; `.ai` testing rules hold.
- The handoff's `## Suite Timing Report` records before (NAV1 numbers) vs
  after (this run's final --profile), the new top-10 list, and per-change
  attribution where visible.
- If a change does not measurably help, revert it and say so.

## Docs and handoff

Implementation plan doc before code
(`docs/research/test-suite/00-ts1-implementation-plan.md` or similar); ledger
row `TS1 - Test suite performance` plus the Job 0 rows; `current-project-state.md`;
handoff with Job 0 outcomes, the before/after timing report, `## Commit hash`,
and a short front-check list (run `php artisan test` locally and compare wall
time; open /admin and confirm the Dashboard is back as the first sidebar
item).

Commit: `perf: cut settings test suite cost and restore dashboard navigation`

End with exactly:

```text
Test suite performance TS1 is complete. Waiting for Yoni review before continuing.
```
