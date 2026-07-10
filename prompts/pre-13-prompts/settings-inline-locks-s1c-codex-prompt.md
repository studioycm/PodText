# Codex Prompt — Step 10R-S1c: Inline Import Locks on the Settings Page

Work in the current local clone of `studioycm/PodText`.

ONE mini-step: S1c — surface the existing import-locks mechanism inline on the Public
Content Settings page, plus the S1b/HF2 audit corrections as Job 0. This is an
approved settings-arc coda; the Importer Workbench gate stays OPEN and WB1 runs after
this at Yoni's choice. All standing runner rules apply: full quality gate incl.
`git diff --check`, no push unless asked, no `filacheck --fix`, no `model:show`,
fixture-owned tests, en+he translations, RTL-safe UI, handoff ends with
`## Commit hash` (backfill previous run's hash from git log per the standing rule)
and `## Local Front Check Report`.

## Preflight

```bash
git status --short --branch
git log --oneline --decorate -10
php artisan migrate:status
```

Confirm clean tree; HF2 is complete; the ledger has no pending step before S1c (FIRST
JOB: insert the `Step 10R-S1c - Inline import locks on settings page` row after S1b /
HF2 and before the WB-gate note). NOTE: the local environment now runs MySQL — see
Job 0 item 4.

## Binding semantics (Yoni decision, records as D29)

- **D29 — locks are import-only, everywhere.** The inline lock affordances on the
  settings page toggle the SAME `import_locks` store via `SettingsImportLocks`; they
  NEVER make form fields read-only and never block saving the settings form. Helper
  text must say so explicitly (protected from import — editing unaffected). D24 is
  unchanged.

## Job 0 — S1b/HF2 audit corrections

1. **Preset must union, not replace**: `lockAllFrontTexts()` currently overwrites the
   selection, silently dropping manually-added locks on the next save. Change to a
   union with the current selection; test: manual lock + preset + save keeps both.
2. **Mode switch must preserve user selection work**: `updatedImportMode()` re-analysis
   currently resets `selectedPaths` to defaults. Preserve the user's selection by
   intersecting the previous `selectedPaths` with the new analysis' selectable paths
   (fall back to defaults only when there was no prior dry-run). Test: deselect a row,
   switch mode and back, deselection survives where still selectable.
3. **Card-template duplicate keys**: the validator never enforces family+key
   uniqueness, but the schema's family units key templates by `key` — a duplicate
   family+key pair silently collapses (last wins) on ANY apply touching that family.
   Add validator normalization: duplicate family+key template entries are dropped
   (keep the first) with a `duplicate_template_key` invalid-config warning. Test with
   a payload containing an intentional duplicate.
4. **Docs: local environment now runs MySQL.** Update `current-project-state.md`
   (Boost/application-shape/git-state mentions of local SQLite) and any active doc
   that states the local dev DB is SQLite: local runtime DB is now MySQL 8
   (Herd-managed, `127.0.0.1:3306`, database `podtext`) for production parity after
   the HF2 incident; the TEST suite intentionally remains SQLite `:memory:` per
   `phpunit.xml`; migration-bearing steps are now verified against local MySQL before
   push. Do not write credentials anywhere.
5. **Locks-only saves must not schedule snapshots**: every lock toggle saves
   `PublicContentSettings`, which creates a (deduped) system backup AND queues two
   Playwright thumbnails — inline toggles would multiply browser work for changes with
   zero visual effect. Keep creating the system backup row, but skip snapshot
   scheduling when the saved payload differs from the previous backup's payload ONLY
   in the `import_locks` group (compare canonical JSON of payload-minus-import_locks).
   Implement in the backup/snapshot boundary, not in UI code. Tests: locks-only save →
   backup row without snapshot rows; a save that also changes another group still
   schedules thumbnails.
6. Backfill the HF2 commit hash (from git log) into the HF2 handoff and
   ledger/current-state rows per the standing correction rule.

## S1c — inline locks on the Public Content Settings page

Research first (Boost `search_docs` + FilamentExamples short batches): Filament 5.6
Section header actions / suffix-hint actions on fields inside a SettingsPage schema,
Livewire-updating hint state, and action mounting from schema components. If Section
header actions are unavailable in the installed version, fall back to a lock badge in
the section description plus a section-level toggle exposed through a page header
action reusing the existing lock-manager component — record whichever mechanism is
used in the handoff.

- **Per-section lock toggle**: each settings Section that maps to one lifecycle group
  gets a header lock action showing current state (locked / partially locked /
  unlocked via the tri-state service) and toggling ALL units of that group through
  `SettingsImportLocks` (reuse `SettingsLifecycleSelectionState::toggleGroup`
  semantics).
- **Per-field lock indicator + toggle**: fields whose statePath resolves (via
  `SettingsLifecycleSchema::unitPathsForSemanticPath`) to exactly one unit get a hint
  lock icon + hint action toggling that unit's lock. Fields deeper than their unit
  (e.g. the date label overrides inside `item_page.dates`) must SAY which unit the
  lock actually covers in the hint tooltip/helper text (translation key, en+he).
  Fields that resolve to zero or multiple units get NO inline affordance.
- **Page header**: add a "Manage import locks" header action linking to
  `/admin/settings-import-locks`, next to the existing export action.
- Lock state reads once per request (memoized service read); toggles persist
  immediately through `SettingsImportLocks::save()` (which now skips snapshot
  scheduling per Job 0.5) and refresh the visible state without a full page reload.
- Progressive disclosure: no extra chrome for unlocked fields beyond the small hint
  icon; locked fields/sections show a clear lock badge. RTL-safe, light/dark safe.

## Explicit out of scope

Read-only/edit-blocking behavior (rejected — D29); lock UI on any page other than the
Public Content Settings page; new lock granularity; Importer Workbench; P2/P3/AX/SL.

## Tests

Job 0 items each as listed; plus: representative section toggle locks/unlocks all its
group units and persists via the service; representative deep field resolves to its
containing unit and toggles it; a locked field still saves normally through the
settings form (import-only semantics — the critical D29 test); the settings page
renders with locks present in Hebrew RTL; header link action present; nav completeness
green; bounded public harness green; full gate.

## Docs and handoff

Ledger S1c row; `current-project-state.md` (S1c complete + MySQL note from Job 0.4);
enhancement-plan D29 note in the decisions list; handoff with standard sections +
`## Commit hash` + `## Local Front Check Report` (numbered checks: settings page shows
lock hints; lock a field inline → appears locked in the lock manager and excluded in
the import wizard dry-run; lock a section → all its rows locked in the manager;
locked field still editable and saves; locks-only save produces a backup row with NO
snapshot rows; Hebrew RTL + light/dark). After S1c the ledger notes WB1 is next at
Yoni's choice.

Commit: `feat: add inline import locks on settings page`

End with exactly:

```text
Public Front v2 mini-step S1c is complete. Waiting for Yoni review before continuing.
```
