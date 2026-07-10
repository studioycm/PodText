# Codex Prompt — Step 10R-S1d: Import Result Report + MP1 Hardening

Work in the current local clone of `studioycm/PodText`.

ONE mini-step: S1d — full transparency for settings imports (locks visibility, what
was and wasn't applied, and why) plus the MP1 pre-implementation review items that
could not be folded mid-run. Standing runner rules apply: research note +
implementation plan docs BEFORE code, full sequential quality gate incl.
`git diff --check`, no push unless asked, no `filacheck --fix`, fixture-owned tests,
en+he translations, RTL-safe UI, tests are SQLite `:memory:` by construction (canary
stays), handoff ends with `## Commit hash` (backfill MP1's hash from git log per the
standing rule) and `## Local Front Check Report`. First docs job: insert the
`Step 10R-S1d - Import result report and MP1 hardening` ledger row before the WB-gate
note.

## Job 1 — MP1 hardening (audit first, implement what is missing)

Audit the shipped MP1 commit against these six review items; implement any that are
missing, and record in the handoff which were already covered:

1. Unrelated-save regression test: with maintenance content stored, saving the
   settings page WITHOUT touching the maintenance tab preserves `rich_html` and
   `raw_html_override` byte-identical (the S1c import_locks save-wipe failure class:
   the save mutator rehydrates defaults for absent/hidden fields).
2. The maintenance middleware is registered on the public panel with
   `isPersistent: true` so Livewire component requests from open guest tabs also
   receive the 503.
3. The middleware runs after session/auth in the panel stack; the admin-bypass test
   covers a Livewire interaction, not only a page load.
4. Add a `sensitive` semantic to the lifecycle overlay, tag `maintenance.enabled`,
   and make the import analyzer never PRESELECT sensitive units (they stay selectable,
   opt-in only) — an imported package must not flip production into maintenance by
   default. Test it.
5. Assert `maintenance.rich_html` persists as an HTML string (not TipTap JSON) after
   a settings-page save.
6. The 503 is returned via `response()->view(view, data, 503)` with the `Retry-After`
   header — never `abort(503)`; the standalone shell declares `lang="he" dir="rtl"`,
   charset, and viewport.

## Job 2 — Import result report and locks visibility (Yoni request)

### Structured result (server truth)

- `SettingsBackupManager::import()` returns a small `SettingsImportReport` value
  object instead of a bare applied-paths array: mode, source label, generated-at,
  `before_import` backup id, and per-outcome path groups — applied, skipped_locked
  (with the lock that blocked each), skipped_exists (add-only), skipped_unchanged,
  error rows (path + translated reason), plus the analysis warnings (watermark /
  missing files / normalization). All labels resolve through the lifecycle schema —
  no enumerated paths, no literal counts.

### Persistence (reviewable after the fact)

- Add a nullable `import_report` json column to `settings_backup_versions`
  (MySQL+SQLite migration). The report is stored on the `before_import` backup row
  created by that import — the backup is the natural anchor of every import.
- `SettingsBackupResource` gains an "Import report" row action, visible only when a
  report is present, rendering the grouped report read-only (UX1 modal defaults).

### Wizard UX (progressive disclosure)

- DRY-RUN step summary chips above the table, all derived: selected count, added,
  changed, locked-excluded (expandable list of the locked units + a link to the lock
  manager), error count, and skip-exists count when mode is add_only. Add a `locked`
  option to the selection-table filter.
- COMPLETION step renders the full report (grouped sections per outcome with unit
  labels and reasons) instead of the bare applied count; include a link to the
  `before_import` backup row.

### Tests

One mixed-import scenario proves the report end to end: a package containing a locked
unit + a scalar type error + an applied change + (under add_only) a skip-exists map —
assert each lands in the right report group with translated labels; report persisted
on the before_import backup row; resource action visible/renders only with a report;
dry-run chips match derived counts; `locked` filter works; guest blocked from the
report action; existing import/lock/maintenance regressions stay green; bounded
public harness; full sequential gate.

## Out of scope

Importer Workbench (its journal is separate and richer); new lock granularity;
report retention beyond the backup row's own lifecycle (deleted with the backup).

## Docs and handoff

Ledger S1d row, current-state, enhancement-plan note (report anchored on
before_import backups; sensitive-units semantic), research + plan docs BEFORE code,
handoff with `## Commit hash` (backfill MP1's hash) and `## Local Front Check Report`
(numbered: run an import with one lock set and one bad row → dry-run chips show
locked/error counts → apply → completion report groups applied/locked/error → open
the backups list → Import report action on the before_import row shows the same →
Hebrew RTL + light/dark).

Commit: `feat: add import result report and maintenance hardening`

End with exactly:

```text
Public Front v2 mini-step S1d is complete. Waiting for Yoni review before continuing.
```

S1d ADDENDUM — verified middleware facts + panel audit + slow tests - changes and precede above if there is collision:

Job 1 refinement (verified against Filament docs): register the maintenance middleware
as a SEPARATE ->middleware([...], isPersistent: true) call on the public panel,
leaving the default stack untouched — it appends after StartSession/AuthenticateSession
so auth()->user() works. Do not prepend into the default array.

Job 3 — panel middleware/auth hardening (from Fable's provider audit):
a. Define the viewHorizon gate in HorizonServiceProvider: authorize users who can
   access the admin panel — production /horizon is currently local-only. Test it.
b. Record as a standing rule (ai-development-lessons + ledger guardrail):
   User::canAccessPanel() currently admits EVERY authenticated user to the admin
   panel; before any non-admin account type is ever introduced, an is_admin gate must
   land first.
c. Handoff deploy note: set SESSION_SECURE_COOKIE=true in the production env.
d. Write a short research note documenting both panels' middleware stacks and the
   persistent-middleware behavior for future runs.

Job 4 — test-suite performance investigation (report before optimizing):
a. Run php artisan test --profile and record the 10 slowest tests; identify the
   dominant cost (suspects: the Browser suite booting Chromium, repeated rendering of
   the large settings page, per-test settings writes/validator runs).
b. Apply only SAFE quick wins now (e.g., excluding an empty/accidental Browser suite
   from the default run, obvious per-test duplicated setup) and REPORT the rest as
   options with measured numbers — including whether php artisan test --parallel is
   safe here (it is one command, so it does not violate the sequential rule; each
   paratest process gets its own sqlite :memory:) — for Yoni to choose in a later run.