# Codex Prompt — SP1: Settings Page Performance Instrumentation (+ marker fix)

Work in the current local clone of `studioycm/PodText`.

ONE run: instrument the Public Content Settings page, MEASURE it, report a
ranked fix plan — plus small carried fixes. NO structural performance fixes in
this run: the deliverable is evidence. Standing runner rules: research note +
implementation plan docs BEFORE code, no push unless asked, no
`filacheck --fix`, fixture-owned tests, en+he translations where UI text is
touched, NO Composer changes. The handoff is a COMMITTED MARKDOWN FILE
(`docs/phase-02/settings-performance-sp1-handoff.md`) with gate outcomes
written into it before the commit, `## Commit hash`, and manual front checks.
Backfill IE-1's commit hash `6f1cea7` per the standing rule.

FINAL GATE ORDER (standing): requirements sweep → pint → filacheck →
npm run build → FULL `php artisan test` LAST (once = once GREEN on final
state; re-enter from Pint after changes; record every run).

## Preflight

```bash
git status --short --branch
git log --oneline -4
```

Clean tree; IE-1 `6f1cea7` expected at or near HEAD.

## Job 0 — carried fixes (small, do first)

1. **MP2 marker-copy bug (production-reported)**: on the maintenance settings
   form, the copyable raw-html marker snippet renders EMPTY and its
   copy-to-clipboard action copies the literal string "null". It must display
   `MaintenanceForm::MARKER` and copy exactly that constant. Find the broken
   state wiring (likely a Placeholder/action reading unset form state instead
   of the constant), fix, and add tests: the rendered maintenance settings
   section contains the marker string; the copy action payload equals
   `MaintenanceForm::MARKER`.
2. **Deploy notes** (tonight's infra lessons, into the maintenance/deploy
   notes doc): (a) after PHP version upgrades, re-apply
   `memory_limit = 512M` in the new fpm php.ini (distro default is 128M);
   (b) zero-downtime sites must either use `$realpath_root` in the nginx
   fastcgi params or keep the post-activation FPM reload in the deploy
   script; (c) `storage` must be a configured Shared Path on zero-downtime
   sites.
3. **Lesson** (ai-development-lessons): multiple Horizon masters on one
   server sharing an APP_NAME share the same Redis prefix and queues — stale
   masters from old releases/renames silently process live jobs with old
   code; after deploy-topology changes, verify exactly one master with
   `ps aux`.

## Job 1 — the profiler (env-gated, production-safe)

Build a lightweight `SettingsPageProfiler` boundary (no new dependencies):

- Activated ONLY by `SETTINGS_PROFILING=true` (config-mapped; default off —
  zero overhead and zero log writes when off; assert that in a test).
- Writes ONE structured line per phase to a dedicated daily log channel
  (mirror the `import_export` channel pattern), fields: phase, milliseconds,
  request kind (initial load | livewire update | save), payload bytes where
  relevant.
- Phases to wrap (via the page class + schema builders, not vendor hacks):
  settings read/hydrate; registry defaults build; EACH top-level tab/section
  schema build (name them); total form() build; Livewire update round-trip
  total; on save — total validation, PER-GROUP validator time (instrument
  `PublicFrontConfigValidator` group boundaries), mutate/normalize, settings
  save/persist, `SettingsSaved` listener time (backup creation vs snapshot
  scheduling separately).
- Also record the Livewire component payload size (bytes of the state
  snapshot) on load and after one update — the transfer weight matters in
  production.

## Job 2 — measure and report (local, with the flag on)

Run and record: three initial page loads (cold + warm); one no-op save; one
single-field change save; one live() interaction on a heavy tab. Produce in
the handoff a `## Settings Performance Report`:

- a phase table (phase → ms, per scenario);
- top 3 cost centers with the numbers;
- Livewire payload sizes;
- a RANKED fix plan with expected savings per item, judged against the
  evidence — the known candidates to confirm or refute: (a) split the
  monolith page into domain SettingsPages following the MP2
  `ManagePublicForms` precedent, with per-page scoped validation; (b) scope
  the validator to changed groups on save; (c) cache/registry-default reuse;
  (d) tab-level lazy schema building if Filament 5.6 supports it natively
  (cite); (e) anything the numbers surface that we did not predict.
- Note explicitly which fixes would ALSO cut the 110s-class test costs (the
  TS2 file) — the same monolith is behind both.

NO fix implementation beyond Job 0. The fix plan becomes SP2's contract after
Yoni + Fable review.

## Tests

Profiler off by default writes nothing (log fake); on-flag writes the phase
lines (log fake, assert phase names present); marker fix tests (Job 0.1);
existing suite green. Full gate per header order.

## Docs and handoff

Ledger row `SP1 - Settings performance instrumentation`;
`current-project-state.md`; research/plan docs before code
(`docs/research/settings-performance/00-sp1-*.md`); handoff per header rules
with the performance report and manual checks (enable SETTINGS_PROFILING
locally → load the settings page → the profile log shows named phases; turn
it off → no log lines; the maintenance marker snippet now shows the div and
copies it exactly).

Commit: `perf: instrument settings page and fix maintenance marker copy`

End with exactly:

```text
Settings performance SP1 is complete. Waiting for Yoni review before continuing.
```
