# Codex Prompt — SP2: Split the Public Content Settings Monolith (evidence-driven)

Work in the current local clone of `studioycm/PodText`.

ONE run: execute SP1's ranked fix plan items 1, 2, 4, and 5 — split the
monolith settings page into domain SettingsPages, scope validation per group,
reorganize the settings tests along the split (this IS the TS2 task), add the
stored-settings normalize command — then RE-MEASURE with the SP1 profiler and
publish before/after numbers. Standing runner rules: research note +
implementation plan docs BEFORE code, no push unless asked, no
`filacheck --fix`, fixture-owned tests, en+he translations for every new
label, NO Composer changes. The handoff is a COMMITTED MARKDOWN FILE
(`docs/phase-02/settings-performance-sp2-handoff.md`) with gate outcomes
written into it before the commit, `## Commit hash` pending, and a numbered
Local Front Check Report.

FINAL GATE ORDER (standing): requirements sweep → `vendor/bin/pint --test` →
`vendor/bin/filacheck` → `npm run build` → FULL `php artisan test` LAST
(once = once GREEN on final state; re-enter from Pint after any change;
record every run in the handoff).

## Preflight

```bash
git status --short --branch
git log --oneline -5
```

Expect SP1 `c6c9587` at or near HEAD (prompt/docs commits may sit above
it). `docs/phase-02/backlog-triage-2026-07-13.md` is Fable's backlog triage:
if Yoni already committed it, nothing to do; if it sits UNCOMMITTED in the
tree, that dirt is expected — include the file unchanged in this run's
commit; if it is missing entirely, note that in the handoff and continue.
Any OTHER unexpected app-code dirt: stop and ask.

## SP1 evidence this run is built on (do not re-derive, do verify)

From `docs/phase-02/settings-performance-sp1-handoff.md`:

- `form.total_build` ≈ 1,145–1,178 ms on EVERY request kind — initial load,
  no-op save, single-field save, and each Livewire update.
- The per-tab schema phases are TINY by comparison (all `schema.tab.*` per
  request sum to well under 100 ms; largest single: advanced 43 ms). So
  roughly 1,000 ms of every form build is spent INSIDE `form()` but OUTSIDE
  the instrumented tab boundaries.
- `save.validation.total` 126–133 ms per save (whole-config validation).
- `validator.group.import_locks` ran 63 times across a 10-request run
  (~6×/request, 127 ms accumulated) — something re-validates it repeatedly.
- Yoni's real local settings payload (37,292 bytes) FAILS current validation
  — a latent save-blocker on any machine with legacy/custom stored state.

## Job 0 — carried fixes (small, do first)

1. Backfill SP1 commit hash `c6c9587` per the standing rule: SP1 handoff
   `## Commit hash`, the mini-step ledger SP1 row, and any active doc still
   saying pending.
2. Amend the Horizon lesson in `docs/phase-02/ai-development-lessons.md`
   (the "multiple Horizon masters / APP_NAME Redis prefix" entry): before
   judging ANY extra master stale — and before any kill — identify each
   PID's owner with `ls -l /proc/<pid>/cwd`; on a multi-tenant server the
   extra masters may belong to OTHER SITES, and killing them downs those
   sites. Only a master whose cwd is this app's release path AND whose env
   shares this APP_NAME is ours. (This nuance is from the real incident:
   the two "stale" masters were two other tenant sites.)
3. Ensure `docs/phase-02/backlog-triage-2026-07-13.md` ends up committed
   per the Preflight rule (no-op if Yoni already committed it).

## Job 1 — close the evidence gap BEFORE restructuring

Attribute the missing ~1,000 ms inside `form.total_build`. Add finer
`SettingsPageProfiler` probes inside `PublicContentSettings::form()` (and
only there — vendor code stays untouched): candidate culprits to bracket
individually: total component-tree construction vs the `Tabs::make`
assembly, `withImportLockSection` wrapping overhead (and WHY import_locks
validates ~6×/request — dedupe if the fix is trivial), translation lookups,
options/default builders. Write the attribution table into the research doc.
Keep permanent probes only where they are cheap and generically useful;
remove scaffolding probes.

Decision gate: the split hypothesis holds only if the dominant cost SCALES
WITH COMPONENT COUNT (construction/config of ~hundreds of fields). If Job 1
shows a fixed per-request cost that a split would NOT reduce, STOP after
Job 2 — write findings + revised plan into the handoff, and end the run as
an evidence report (like SP1). Do not ship a large refactor the numbers do
not support.

## Job 2 — foundations (valuable regardless of the gate)

1. **Group-scoped validation**: refactor `PublicFrontConfigValidator` to
   `validateGroups(array $data, ?array $only = null): array` — `$only`
   limits normalization/validation to the named top-level groups; the
   existing `validate()` keeps whole-config behavior by delegating. Keep the
   SP1 `validator.group.*` profiler phases. All existing callers keep
   working unchanged.
2. **Stored-settings normalize command**: `php artisan
   settings:normalize-public-content`. Default is DRY-RUN: load the stored
   payload, run defaults-merge + full validation, print a per-group report
   of unknown keys dropped, invalid values reset, missing keys filled —
   and write nothing. `--apply` first creates a system backup via
   `SettingsBackupManager::createSystem()`, then persists the normalized
   payload, then prints what changed. Helper text in command description.
   Tests: seeded legacy-shaped payload → dry-run reports and does not write;
   `--apply` writes normalized payload and creates the backup. This is the
   documented cleanup for SP1's 37 KB failing local payload; note in the
   handoff that Yoni should run it locally (dry-run first) and on
   production after deploy.

## Job 3 — the split (gated by Job 1)

Canary first: build ONE smallest domain page end-to-end (maintenance or
about), measure its `form.total_build`. If it is not dramatically below the
monolith (expect < 300 ms), stop per the Job 1 gate. Otherwise proceed:

- **Mapping table first** (in the implementation plan doc): every current
  top-level tab → exactly one new page; every validator group → exactly ONE
  owning page (single-owner rule — no group editable from two pages).
  Groups that already have dedicated pages stay there and are NOT
  duplicated: `public_forms` → `ManagePublicForms`, `import_locks` →
  `ManageSettingsImportLocks`, plus existing backup/import pages. Default is
  one page per current tab (homepage, display, item page, podcasts,
  contributors, menu, about, maintenance, advanced); merging two tiny tabs
  into one page is allowed only if the merged page's measured build stays
  under target.
- **Storage does not change**: all pages read/write the single
  `PublicContentSettings` Spatie settings class. No group renames, no data
  migration.
- **Page save contract** (the critical invariant): on save, start from the
  CURRENT stored settings payload, overlay ONLY this page's validated
  groups (via `validateGroups`), persist the merged whole. Sibling groups
  survive byte-identical — including invalid legacy content (normalize is
  the separate cleanup tool). The monolith's existing hidden-field
  preservation (public forms, maintenance) is the precedent. Explicit Pest
  coverage: edit+save page A → page B's groups unchanged; a legacy-invalid
  sibling group does not block page A's save.
- **Import-lock sections**: `withImportLockSection` wrapping keeps working
  on every page that renders lockable sections.
- **Profiler portability**: move the SP1 page-level instrumentation
  boundaries into a shared trait/concern applied to every domain page, so
  each page reports `form.total_build`, save phases, and payload bytes under
  its own page name. `SettingsPageProfiler` API unchanged, still off by
  default.
- **`SettingsSaved` listener** (cache clear + system backup + snapshot
  scheduling) keeps firing per page save — behavior unchanged, covered by
  existing tests you relocate.
- **Old page**: remove the monolith `PublicContentSettings` page class; its
  old slug/URL redirects to the most-used new page (homepage settings) so
  bookmarks survive. The maintenance marker field and its exact-payload test
  move with the maintenance page.
- **Navigation**: all domain pages under the existing ניהול אתר group,
  ordered editorial-first (homepage/display first, advanced last), distinct
  enum icons, he+en translation keys for every label/title. Technical
  fields keep helper text per cross-cutting rules.
- **Card-template clone rider** (MP2 rider, cheapest now): on the page that
  owns `card_templates`, wire the existing `SettingsItemCloner` as a clone
  action per template entry (cloned name gets a suffix; duplicate-key
  regression stays green). Tests: clone action produces an independent
  template entry.

## Job 4 — TS2: settings tests reorganized along the split

Split the monolith settings test files along the new pages; prefer direct
unit coverage of `validateGroups`/normalization where behavior does not need
a mounted Filament page; keep every regression (duplicate card template,
marker exact payload, backup/listener behavior) — relocated, not deleted.
No test deletions without approval — moving/reshaping with equal-or-stronger
assertions is the intent here; list every relocation in the handoff. Record
settings-area suite time before/after (targeted `php artisan test --compact`
timings are fine for the area table; the full suite runs once at the gate).

## Job 5 — re-measure and report

With `SETTINGS_PROFILING=true` locally: for EACH new page — cold + warm
load, no-op save, single-field save; plus one live() interaction on the
heaviest page. Handoff gets a `## Settings Performance Report`: per-page
phase table; BEFORE/AFTER table vs SP1's numbers (monolith 1,150 ms build /
126–133 ms validation); payload bytes per page; settings-area test time
delta; honest misses with follow-ups if any target is missed.

Targets (evidence over promises — report actuals): per-page
`form.total_build` < 400 ms worst page, < 250 ms typical; save validation
< 40 ms typical page; Livewire update round-trip visibly interactive on the
heaviest page.

## Tests

Cross-page preservation (both directions + legacy-invalid sibling);
`validateGroups` scoping; normalize command dry-run/apply/backup; canary
page load/save; clone rider; relocated regressions; profiler still silent
when disabled. Full gate per header order.

## Docs and handoff

Ledger row `SP2 - Settings split and scoped validation (TS2)`;
`current-project-state.md`; research/plan docs BEFORE code
(`docs/research/settings-performance/01-sp2-research.md`,
`01-sp2-implementation-plan.md` — plan includes the mapping table and the
Job 1 attribution table); handoff per header rules incl. Local Front Check
Report (numbered manual steps: open each new page, save a field, verify
sibling pages unchanged, run the normalize dry-run, confirm nav group order,
marker copy still exact).

Commit: `perf: split public content settings into domain pages`

End with exactly:

```text
Settings performance SP2 is complete. Waiting for Yoni review before continuing.
```
