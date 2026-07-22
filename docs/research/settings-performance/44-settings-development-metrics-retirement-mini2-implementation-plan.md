# Settings Development Metrics Retirement Mini-task 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use
> `superpowers:executing-plans` to implement this plan sequentially in the
> current checkout. Repository rules prohibit parallel repository writers and
> worktrees for this task.

**Goal:** Retire the remaining test-only SP3C/Step 5B measurement harness and
active report routing while preserving all functional Card Template, security,
query, browser, Filament 5.7.1, and Curator G1/LMTC behavior.

**Architecture:** Delete the isolated synthetic canary graph and narrow mixed
tests to their functional assertions. Move consumed phase-named DOM hooks to
durable Card Template names through a temporary two-phase declaration bridge,
then remove every old runtime/test hook. Preserve historical evidence while
superseding only active instructions.

**Tech Stack:** PHP 8.4, Laravel 13.21.1, Filament 5.7.1, Livewire 4.3.3,
Curator 5.1.2, Pest 4.7.5, Blade, Alpine.js.

## Global constraints

- Audit: `LS-20260722-SETTINGS-DEV-METRICS-M2-01`.
- Approved option: `SETTINGS-METRICS-M2-O1-BOUNDED-FULL-RETIREMENT`.
- Scope: Mini-task 2 only; stop before any later mini-task.
- Baseline: clean `main` at
  `ab37bd0838214bf55612390f7e501ac7e8eef4df`.
- No dependency, migration, schema, local-development database/storage/cache,
  production, deployment, worker, worktree, or push action.
- Do not weaken functional assertions, deterministic query counts, security
  regressions, browser interactions, or `performance.now()` wait deadlines.
- Do not change Curator/media production files or neighboring Curator
  translations.
- Final verification and browser/full-suite commands are serial.

---

### Task 1: Lock the baseline and functional coverage

**Files:**

- Read: `tests/Feature/SettingsSp3aTest.php`
- Read: `tests/Feature/SettingsSp3bTest.php`
- Read: `tests/Feature/SettingsSp3cTest.php`
- Read: `tests/Feature/CardTemplatePreviewerTest.php`
- Read: `tests/Feature/CardTemplateEditorPreviewTest.php`
- Read: `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- Read: `tests/Browser/CardTemplatePreviewBrowserTest.php`

**Produces:** Fresh baseline evidence for all retained functional tests.

- [ ] Confirm clean status and exact HEAD `ab37bd0`; stop on overlapping drift.
- [ ] Record declaration/consumer inventory for the ten production selectors,
  five report switches, eight report blocks, eight canary files, and bilingual
  canary translation subtrees.
- [ ] Run the SP3A/SP3B/SP3C functional files serially.
- [ ] Run Card Template previewer/editor/Builder feature files serially.
- [ ] Run the complete Card Template browser file serially. If the known macOS
  browser sandbox error occurs, retry the identical permitted command rather
  than changing application code.
- [ ] Recheck the worktree after baseline tests.

Commands:

```bash
php artisan test --compact tests/Feature/SettingsSp3aTest.php tests/Feature/SettingsSp3bTest.php tests/Feature/SettingsSp3cTest.php
php artisan test --compact tests/Feature/CardTemplatePreviewerTest.php tests/Feature/CardTemplateEditorPreviewTest.php tests/Feature/PublicFrontCardTemplateBuilderTest.php
php artisan test --compact tests/Browser/CardTemplatePreviewBrowserTest.php
git status --short --branch
```

Expected: all retained functional suites pass; tests use only test-owned
fixtures and the local development database/storage remain untouched.

### Task 2: Establish the durable selector contract before production removal

**Files:**

- Modify: `tests/Feature/CardTemplateEditorPreviewTest.php`
- Modify: `tests/Feature/SettingsSp3cTest.php`
- Modify: `tests/Browser/CardTemplatePreviewBrowserTest.php`
- Modify: `app/Filament/Pages/CardTemplateEditorPage.php`
- Modify: `resources/views/filament/pages/card-template-editor.blade.php`
- Modify: `resources/views/filament/card-templates/part-heading.blade.php`
- Modify: `resources/views/filament/card-templates/part-summary.blade.php`

**Produces:** All functional consumers use `data-card-template-*`; declarations
temporarily expose both old and new names until the new contract is green.

- [ ] Change the existing position-badge feature assertion first:

```php
->assertSeeHtml('data-card-template-part-position-badge')
```

- [ ] Run that focused test and confirm expected RED because production still
  emits only `data-sp3c-part-position-badge`.
- [ ] Add each new selector beside its old declaration, without changing
  schema, state, validation, visibility, or order.
- [ ] Move PHP/Blade JavaScript and all feature/browser consumers to the six
  durable selector names from research note 43.
- [ ] Run the focused position/summary feature tests and complete browser file;
  require GREEN before old declarations are removed.
- [ ] Search the diff to confirm no browser assertion or bounded deadline was
  removed during the consumer migration.

### Task 3: Remove the synthetic canary and report graph

**Files:**

- Delete: the eight explicit canary files listed in research note 43.
- Modify: `tests/Feature/CardTemplateEditorPreviewTest.php`
- Modify: `tests/Feature/SettingsSp3cTest.php`
- Modify: `tests/Browser/CardTemplatePreviewBrowserTest.php`
- Modify: `lang/en/admin.php`
- Modify: `lang/he/admin.php`

**Produces:** No executable canary class, fixture, helper, report switch, or
canary translation remains.

- [ ] Delete the eight files individually; use no wildcard deletion.
- [ ] Delete `SettingsSp3cCanaryMeasurement` imports and mixed-test report
  branches. Keep the exact preview-root assertion, deterministic query
  assertions, retired-flag security behavior, and ordinary stored save.
- [ ] Delete all eight report conditionals and only fields/accumulators whose
  consumers were exclusively report output.
- [ ] In the browser test, remove no `expect()` and no bounded
  `performance.now()` wait condition. Retain ResizeObserver unexpected-error
  filtering/assertion.
- [ ] Delete only the `admin.settings_sp3c.canary` subtree from both languages.
- [ ] Independently inspect the language diff; functional settings and Curator
  translations must be unchanged.

### Task 4: Remove old selector declarations and unused markers

**Files:**

- Modify: `app/Filament/Pages/CardTemplateEditorPage.php`
- Modify: `resources/views/filament/pages/card-template-editor.blade.php`
- Modify: `resources/views/filament/pages/card-template-settings.blade.php`
- Modify: `resources/views/filament/card-templates/part-heading.blade.php`
- Modify: `resources/views/filament/card-templates/part-separator.blade.php`
- Modify: `resources/views/filament/card-templates/part-summary.blade.php`

**Produces:** Durable selector names only; unused phase markers absent.

- [ ] Confirm every consumer already uses `data-card-template-*`.
- [ ] Remove the six old declarations and four unused attributes listed in
  research note 43 while keeping surrounding markup/UI unchanged.
- [ ] Run an executable-scope search that returns no `data-sp3c-*` occurrence
  in `app`, `resources`, or `tests`.
- [ ] Re-run SP3C/editor-preview feature files and the complete browser file.
- [ ] Confirm `unmountAction(false)` and all browser wait deadlines remain.

### Task 5: Supersede active routing without rewriting history

**Files:**

- Create: `docs/phase-02/settings-development-metrics-retirement-mini2-handoff.md`
- Modify: `docs/phase-02/current-project-state.md`
- Modify: `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- Modify: `docs/research/settings-performance/10-pending-decision-question-queue.md`
- Modify: `docs/research/settings-performance/19-authz-complexity-reset-and-feature-first-master-plan.md`
- Modify: `docs/phase-02/feature-map.md`
- Modify: `prompts/README.md`
- Modify: active Public Front routing headers identified in research note 43.
- Modify: only supersession banners in SP3A/SP3B/SP3C handoffs and reports 07/08.

**Produces:** Historical results remain intact; no active instruction can
reactivate the retired harness.

- [ ] Add a concise historical-only/do-not-execute notice to the five audited
  historical entry points without altering measurements, commands, results,
  hashes, or body text.
- [ ] Update current state, ledger, pending queue, feature map, master plan,
  prompt index, and active Public Front headers to point to this Mini-task 2
  closeout.
- [ ] Write the handoff with requirement classifications, files/tests, every
  command and result, gate outcomes, assumptions/deferred work, and numbered
  imperative Local Front Check steps. Leave `## Commit hash` pending.
- [ ] Run `git diff --check` and inspect documentation diffs separately.

### Task 6: Focused preservation verification

**Files:** No new files.

**Produces:** Fresh evidence for retained functional, browser, and Curator
contracts on the final implementation state.

- [ ] Run SP3A/SP3B/SP3C functional files.
- [ ] Run previewer/editor/Builder feature files.
- [ ] Run the complete Card Template browser file serially.
- [ ] Run the five Curator G1/LMTC preservation files serially.
- [ ] Run requirements and scope sweeps: deleted symbols absent from executable
  code; old selectors absent; no removed browser assertion/deadline; exact
  language subtree diff; no Curator production/dependency/migration diff;
  `unmountAction(false)` present.
- [ ] Update the handoff with every result. Any documentation edit after this
  point requires restarting the final gate at Pint.

### Task 7: Canonical final gate and two-commit closeout

**Files:** Final approved diff only.

**Produces:** Canonical implementation commit and immediate docs-only hash
stamp; clean local status; no push.

- [ ] Complete a final requirement-by-requirement sweep.
- [ ] Run `vendor/bin/pint --test`.
- [ ] Run `vendor/bin/filacheck`.
- [ ] Run `npm run build`.
- [ ] Run full `php artisan test` last, serial, and uninterrupted.
- [ ] If any file changes, restart at Pint and repeat the remaining ordered
  gate on the new final state.
- [ ] Run PhpStorm inspections on edited PHP files and resolve only in-scope
  findings.
- [ ] Commit implementation, tests, research, active docs, and handoff with an
  imperative approved-prefix subject.
- [ ] Immediately replace the pending hash in the handoff and ledger with the
  implementation commit SHA, then create `docs: backfill settings metrics
  mini2 hash`.
- [ ] Confirm clean `git status --short --branch`; do not push; stop.

## Self-review

- Scope coverage: every approved canary, report, translation, selector,
  historical-status, preservation, and closeout requirement maps to a task.
- Placeholder scan: no `TBD`, `TODO`, unspecified implementation, or generic
  test step remains. The handoff hash is intentionally pending until the
  implementation commit exists, as required by the repository protocol.
- Type/name consistency: all six durable selectors exactly match research note
  43, including the collision-safe `data-card-template-editor-parts`; report
  switch names and canary paths match the live inventory.
- Complexity: one bounded deletion/rename outcome, no new class, abstraction,
  migration, dependency, or separately shippable task.
