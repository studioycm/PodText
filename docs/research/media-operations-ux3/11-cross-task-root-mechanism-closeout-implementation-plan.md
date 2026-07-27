# Media Operations UX3 Mini-task 3A Cross-task Root-mechanism Closeout Implementation Plan

> **Status: implemented locally.** Canonical outcome and calibrated proof are
> recorded in
> `docs/phase-02/media-operations-ux3-mini3a-owner-image-choice-and-commit-handoff.md`.
> Do not re-execute this plan.

> **For agentic workers:** REQUIRED SUB-SKILL: Use
> `superpowers:subagent-driven-development` to execute this plan one task at a
> time in the existing checkout. Do not create another worktree. Do not commit
> per task; the repository's canonical two-commit ending controls.

**Goal:** Close the approved cross-task integrity holes, finish Mini-task 3A
Tasks 7–8, and prove the accepted owner-image outcome without entering any
later Media task.

**Architecture:** Return subject Settings pages to the installed Filament
native lifecycle, serialize every production `PublicContentSettings` writer
behind one Laravel atomic group lock, and merge focused edits with
`SettingsLifecycleSchema` unit-level three-way comparison. Keep Curator,
attachment, Settings, and file authorities separate.

**Tech Stack:** Laravel 13.21.1, Filament 5.7.3, Livewire 4.3.3, Spatie
Laravel Settings, Curator 5.1.2, Pest 4.7.5, Tailwind CSS 4.

## Global constraints

- Active authority:
  `LS-20260726-PODTEXT-MEDIA-OPERATIONS-UX3-M3A-CROSS-TASK-INTEGRITY-03`
  plus
  `MEDIA-OPS-UX3-M3A-RC-O2-CROSS-TASK-MECHANISM-CLOSEOUT`.
- Work only in `/Users/studioycm/Herd/PodText`.
- Preserve the staged Tasks 1–6 and operator-owned forward-document changes.
- No new worktree, dependency, migration, policy/ability, Media/file
  authority, Recheck/Retry, generic repair, O3, 3B, 3C, Mini-task 4, or
  Package 5 work.
- Use strict TDD for every behavior change: focused RED, smallest GREEN,
  refactor while green.
- Run tests serially. Diagnose whether a failure is product, test, fixture,
  environment, or stale expectation before editing production.
- Consult installed/versioned package guidance and FilamentExamples before
  changing Filament/Livewire/package behavior. Record limitations honestly.
- Use fresh implementer context for each task and a separate requirements/code
  review after it. Review the actual staged/worktree delta; do not create
  per-task commits.
- Do not stage unrelated operator-owned files.
- Any material scope, migration, dependency, authorization, or task-boundary
  drift stops for an amended Laravel Simplifier Stage 1.

## Task 1 — Restore the field-local memoization proof

**Files:**

- Modify: `tests/Feature/OwnerImageWorkspaceTest.php`
- Production file under proof:
  `app/Filament/Forms/Components/PathCuratorPicker.php`

### Steps

- [ ] Add a focused test with two owner-aware picker instances whose
  presentation closures have independent counters.
- [ ] Prove repeated reads/render consumers on one unchanged field evaluate
  that field's closure once.
- [ ] Prove another field owns a separate cache.
- [ ] Change one field state and prove only that field invalidates and
  reevaluates.
- [ ] Run the exact Pest filter.
- [ ] If it is already GREEN, classify this as restored missing proof and do
  not change production.
- [ ] If RED, inspect test construction/lifecycle first; change production
  only for a proven cache defect.
- [ ] Run the complete affected picker test files.

## Task 2 — Add the coordinated Settings writer

**Files:**

- Add:
  `app/Support/SettingsLifecycle/PublicContentSettingsWriteCoordinator.php`
- Add or modify focused tests:
  `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- Modify:
  `app/Support/Settings/CardTemplates/CardTemplateFocusedWriter.php`
  `app/Support/SettingsLifecycle/SettingsImportLocks.php`
  `app/Support/SettingsLifecycle/SettingsBackupManager.php`
  `app/Console/Commands/NormalizePublicContentSettings.php`
  `app/Console/Commands/BackfillSettingsMediaReferenceKeys.php`
  `app/Support/Media/CuratorMediaAssetConverter.php`
  `app/Support/Media/MediaFilesystemMutationCoordinator.php`
  `app/Support/Media/LegacyMediaReferenceSwitcher.php`

### Steps

- [ ] Add RED tests proving the coordinator owns one stable group lock,
  acquires it before a fresh read, releases it after exceptions, and refuses
  concurrent entry while a lock is held.
- [ ] Implement bounded `withinLock()` and `transaction()` APIs using
  `Cache::lock()->block()`.
- [ ] Keep database transactions inside the group lock.
- [ ] Add RED regression tests for disjoint card-template/import-lock and
  backup/page-shaped writes.
- [ ] Move each writer's fresh read, mutation, and save inside the coordinator.
- [ ] Preserve existing row locks and media transactions for their original
  domain invariants.
- [ ] Avoid nested lock acquisition by splitting public coordinated entry
  points from private already-locked methods.
- [ ] For normalization apply, recompute the candidate after lock acquisition.
- [ ] For legacy Media paths, acquire the group lock before plan revalidation
  whenever settings snapshots are involved.
- [ ] Rerun focused writer, backup/import, normalization, backfill,
  conversion, and transition tests serially.

## Task 3 — Restore the native Filament SettingsPage lifecycle

**Files:**

- Modify:
  `app/Filament/Pages/PublicContentSettingsSubjectPage.php`
  `app/Filament/Pages/BuildsPublicContentSettingsSubjectSchemas.php`
- Add:
  `app/Support/SettingsLifecycle/SettingsSubjectBaseline.php`
  `app/Support/SettingsLifecycle/SettingsSubjectThreeWayMerger.php`
- Modify focused tests:
  `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
  `tests/Feature/PublicMenuHeaderUxFixesTest.php`
  `tests/Feature/PublicDefaultImagesSettingsTest.php`
  `tests/Feature/PublicAboutPageContentTeamTest.php`
  `tests/Feature/PublicFrontCardTemplateBuilderTest.php`

The exact helper class split may be reduced if one cohesive merger class is
clearer. Do not add a broad generic settings service.

### Steps

- [ ] Add RED tests proving native fill/save hooks execute once and
  authorization still returns 403.
- [ ] Add RED three-way tests:
  - [ ] local unchanged + fresh changed keeps fresh;
  - [ ] local changed + fresh unchanged keeps local;
  - [ ] both changed to the same value succeeds;
  - [ ] same unit changed differently halts with zero write;
  - [ ] disjoint units merge.
- [ ] Add RED tests proving whole-list units conflict as whole units.
- [ ] Replace copied `mount()`, `fillForm()`, and complete `save()` logic with
  native hooks and a thin coordinator-wrapped `save()`.
- [ ] Capture opened raw unit hashes before native normalization/fill.
- [ ] Capture opened editable unit hashes after fill.
- [ ] Build the union of opened/local/fresh paths through
  `SettingsLifecycleSchema`.
- [ ] Perform the exact unit merge in `mutateFormDataBeforeSave()` while the
  group lock is held.
- [ ] Refresh baselines and owner-image snapshot in `afterSave()`.
- [ ] Keep errors localized and identify the exact conflicting unit.
- [ ] Rerun every subject Settings page test file serially.

## Task 4 — Reject invalid changed units without damaging legacy data

**Files:**

- Modify:
  `app/Support/SettingsLifecycle/SettingsSubjectThreeWayMerger.php`
  `app/Filament/Pages/BuildsPublicContentSettingsSubjectSchemas.php`
- Modify:
  `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
  `tests/Feature/PublicMenuHeaderUxFixesTest.php`
  `tests/Feature/PublicAboutPageContentTeamTest.php`

### Steps

- [ ] Add a RED test with one invalid untouched stored unit and one valid
  changed unit; prove the invalid raw unit survives exactly.
- [ ] Add a RED test where the operator changes an invalid nested unit; prove
  Save halts and no Settings property changes.
- [ ] Map `PublicFrontInvalidConfig` semantic paths to lifecycle units.
- [ ] Apply validator-normalized values only for locally changed valid units.
- [ ] Remove any subject override that consumes `.config()` and silently drops
  invalid rows before the central guard.
- [ ] Prove unrelated invalid warnings do not block a valid edit.
- [ ] Rerun validator, public-form, route-label, About, and menu tests.

## Task 5 — Fix Settings Media replacement, clear, and truth

**Files:**

- Modify:
  `app/Filament/Pages/BuildsPublicContentSettingsSubjectSchemas.php`
  `app/Support/Media/SettingsOwnerImagePresenter.php`
  `app/Support/Media/SettingsOwnerImageSnapshot.php` only if its value contract
  needs an explicit field
- Modify:
  `tests/Feature/PublicMenuHeaderUxFixesTest.php`
  `tests/Feature/PublicDefaultImagesSettingsTest.php`
  `tests/Feature/PublicAboutPageContentTeamTest.php`

### Steps

- [ ] Add RED tests for valid replacement of a broken stored reference/path in
  menu logo, default image, About image block, and team profile.
- [ ] Add RED tests for explicit clear of the same broken stored identities.
- [ ] Reorder media normalization around the incoming operator intent.
- [ ] Preserve unchanged unresolved legacy paths without pretending they are
  healthy.
- [ ] Add RED presentation proof that broken configured identity differs from
  absent and changes the snapshot fingerprint.
- [ ] Project `direct_state=broken` with safe configured evidence and no
  unauthorized preview/details URL.
- [ ] Rerun all Settings owner-image and public-default resolution tests.

## Task 6 — Apply official upload restrictions and proven nested identity rules

**Files:**

- Modify:
  `app/Filament/Pages/PublicContentSettingsSubjectPage.php`
  `app/Filament/Pages/BuildsPublicContentSettingsSubjectSchemas.php`
- Modify:
  `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
  `tests/Feature/PublicMenuHeaderUxFixesTest.php`
  `tests/Feature/PublicAboutPageContentTeamTest.php`

### Steps

- [ ] Add a RED test that a forged or hidden upload state path is rejected on
  a subject Settings page.
- [ ] Add Filament's installed
  `RestrictsFileUploadsToSchemaComponents` concern to the shared parent.
- [ ] Add RED clone tests for route labels and proven public-form stable keys.
- [ ] Use native `distinct()`,
  `disableOptionsWhenSelectedInSiblingRepeaterItems()`, and clone-action
  customization where they fit the installed APIs.
- [ ] Regenerate/clear only identities that the validator and renderer treat
  as stable unique keys.
- [ ] Do not change menu-item or item-info identity rules without new proof.
- [ ] Rerun the focused form-integrity suites.

## Task 7 — Measure a real Livewire Settings page

**Files:**

- Modify:
  `tests/Feature/PublicAboutPageContentTeamTest.php`
  or the narrowest existing subject-page performance test file
- Modify production only if the new measurement is RED:
  `app/Support/Media/SettingsOwnerImagePresenter.php`
  `app/Filament/Pages/BuildsPublicContentSettingsSubjectSchemas.php`

### Steps

- [ ] Add a real authenticated Livewire subject-page mount/render measurement
  with small and larger nested owner-image fixtures.
- [ ] Filter setup/auth noise precisely; measure the intended query plane.
- [ ] Run the proof.
- [ ] If GREEN, record no production optimization.
- [ ] If RED, identify the exact repeated query source before changing code,
  then add the smallest request-scoped batch/memoization correction.
- [ ] Rerun both projection-level and page-level query proofs.

## Task 8 — Complete original Task 7 localization and responsive styling

**Files:**

- Modify:
  `lang/he/admin.php`
  `lang/en/admin.php`
  `resources/css/filament/admin/theme.css`
- Modify:
  `tests/Feature/OwnerImageWorkspaceTest.php`
  `tests/Feature/AppOwnedMediaPickerTest.php`

### Steps

- [ ] Inventory every user-facing owner-image label, state, error, hint,
  action, and permanence sentence in both locales.
- [ ] Add exact Hebrew and English parity tests.
- [ ] Assert no raw or dotted translation key renders.
- [ ] Add CSS scoped under `.podtext-owner-image-modal`.
- [ ] Implement semantic 390-pixel full-screen behavior, one scroll region,
  sticky safe actions, RTL/LTR parity, touch targets, and no horizontal
  overflow.
- [ ] Keep generic picker styling unchanged outside the scoped class.
- [ ] Run the focused feature tests and `npm run build`.

## Task 9 — Complete original Task 8 browser and visual proof

**Files:**

- Modify the existing owner-image browser/spec files discovered in the
  implementation checkout.
- Store screenshots and review PDF only under the approved Codex
  visualization workspace, never in the repository.

### Steps

- [ ] Replace stale tab/details expectations with the accepted canonical
  owner-choice workflow.
- [ ] Run browser scenarios serially for:
  - [ ] podcast dedicated modal;
  - [ ] episode dedicated modal;
  - [ ] classic and workspace forms;
  - [ ] relation-manager create/edit;
  - [ ] menu/default/About Settings.
- [ ] Cover Hebrew RTL and English LTR at desktop and 390 pixels.
- [ ] Prove direct/shown/pending truth, one real Save, All Media without
  Current folder, new-tab Details continuity, cancel/permanent admission,
  stale-review second commit, focus return, Escape, one scroll region,
  keyboard/touch operation, and no clipping/overflow.
- [ ] Record console, page, network, and accessibility failures with causal
  classification.
- [ ] Produce implementation screenshots and a polished
  current-versus-implemented PDF outside the repository.
- [ ] Stop and report if browser infrastructure fails independently of app
  behavior; rerun the permitted suite rather than patching app code.

## Task 10 — Cross-task requirements review

**Files:**

- Review every changed application, test, translation, CSS, and documentation
  file.
- Update:
  `docs/research/media-operations-ux3/07-program-reconciliation-and-finding-coverage.md`
  and the exact active state/ledger/handoff files required by the prompt.

### Steps

- [ ] Run a requirements sweep against research `08`, plan `09`, O2 research
  `10`, this plan, the accepted visual direction, and the exact audit option.
- [ ] Classify every meaningful requirement:
  Implemented / Already existed / Deferred / Not applicable / Blocked.
- [ ] Confirm no 3B, 3C, Mini-task 4, Package 5, generic repair, or
  Recheck/Retry work entered the diff.
- [ ] Confirm every production Settings writer in the inventory is coordinated
  or explicitly prove it read-only.
- [ ] Run separate requirements and code-quality reviews.
- [ ] Fix only in-scope findings; after any change rerun affected focused
  tests.

## Task 11 — Final gates and canonical closeout

**Files:**

- Add the Mini-task 3A handoff named according to repository convention.
- Update bounded current-state/ledger/status references only where the active
  prompt requires them.

### Steps

- [ ] Write the handoff with:
  - [ ] requirement classifications;
  - [ ] files changed;
  - [ ] tests added/updated;
  - [ ] every command and result, including failures;
  - [ ] package/tool evidence and limitations;
  - [ ] gate outcomes;
  - [ ] numbered imperative Local Front Check steps;
  - [ ] implementation screenshots/PDF paths;
  - [ ] `## Commit hash` set to pending.
- [ ] Run `git diff --check`.
- [ ] Run the mandatory final gates on the final code state, serially:
  - [ ] requirements/diff sweep;
  - [ ] `vendor/bin/pint --test`;
  - [ ] `vendor/bin/filacheck`;
  - [ ] `npm run build`;
  - [ ] full `php artisan test` last.
- [ ] After any file change, restart at Pint.
- [ ] Stage only approved Mini-task 3A/O2 files plus required closeout docs.
- [ ] Create one implementation commit with an allowed imperative prefix.
- [ ] Immediately stamp that implementation hash into the handoff and ledger.
- [ ] Run documentation checks required for the stamp.
- [ ] Create `docs: backfill media operations UX3 mini-task 3A hash`.
- [ ] Do not push.
- [ ] Report current Git status and stop for operator outcome review. Do not
  begin Mini-task 3B.
