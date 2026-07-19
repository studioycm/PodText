# Step 5B Card Template Editor UX2 Handoff

## Contract and provenance

- Laravel Simplifier audit:
  `LS-20260719-STEP5B-CARD-TEMPLATE-UX2-01`.
- Approved option: `STEP5B-CARD-UX2-O1-COMPAT-MODAL`.
- Clean implementation baseline:
  `8b3b5b06cedea984ffd277fbf29d8c3f3268e3da`.
- Authoritative checkout: `/Users/studioycm/Herd/PodText`; Git root matched
  the checkout and branch remained `main`.
- Required research and consulted implementation plan:
  `docs/research/settings-performance/29-step5b-card-template-editor-ux2-research.md`
  and
  `docs/research/settings-performance/30-step5b-card-template-editor-ux2-implementation-plan.md`.
- Implementation commit: `82e639d0fd22c06c52a70acec7c26ee9e2d8c72a`.
- No branch, worktree, push, remote publication, production action, production
  probe, or production normalization occurred.

## Outcome

The focused Card Template editor now treats Builder sibling position as its
editing source of truth while retaining explicit-order compatibility at the
global validation, value-object, import, restore, backup, and lifecycle
boundaries.

Legacy explicit order is used once to establish top-level and nested sibling
position during focused hydration. Editable order inputs are absent. Preview
and save strip transport-only state, filter non-array entries, and synthesize
contiguous x10 order independently for every sibling list before the unchanged
validator/value-object path runs.

Every focused Builder row now has an escaped localized position/type heading,
compact separated summary, native inline collapse, and an icon-only native
extra-item action. That action opens an extra-small one-field move modal scoped
to its owning Builder. It clamps on the server, uses take-the-slot semantics,
preserves UUID keys, cannot cross a parent boundary, no-ops without refresh,
and invokes the existing state-updated/preview path once after a real move.

Label and icon controls are grouped compactly. Their transient switches remain
reachable, are live and non-dehydrated, conditionally hide subordinate fields,
and preserve entered label/icon values. The preview replaces visual
zoom with a transient centered 100/90/80/70/60 card-width control, so the real
card reflows without scaling its text or images. Sample, width, and icon-only
Refresh controls share one row, with short current/stale copy and a short
`Asia/Jerusalem` timestamp.

## Requirement classification

| Requirement | Classification | Evidence |
| --- | --- | --- |
| Create and consult research 29 and plan 30 before code | Implemented | Both documents were created, read back, and used before application edits. |
| Position-canonical focused top-level and nested Builders | Implemented | Hydration sorts legacy siblings deterministically and strips order; preview/save synthesize x10 order recursively. |
| Remove editable order fields | Implemented | Shared top-level/nested/Advanced schemas and the SP3C canary mirror no longer expose an order input. |
| Native scoped move action | Implemented | A native Builder extra-item action owns an extra-small modal and only mutates the owning sibling list. |
| Move clamp, take-the-slot, UUID, nesting, no-op, and one-refresh semantics | Implemented | Focused tests cover forward/backward, underflow/overflow, non-numeric validation, top/nested UUIDs, no-op, and refresh count. |
| Compact localized headings and separators | Implemented | Escaped Blade fragments render position/type headings and aria-hidden summary separators in HE/EN. |
| Native inline collapse | Implemented | Shared Builder configuration enables native collapse outside the existing slide-over preview mode. |
| Compact label/icon group and always-reachable transient switches | Implemented | Live non-dehydrated switches conditionally hide subordinate fields and preserve entered label/icon values; focused and browser coverage is green. |
| Template settings/parts organization | Implemented | Both focused editor sections are open by default and independently collapsible. |
| Transient card-width preview | Implemented | Chromium measures 60% real-card width, centering, `zoom: 1`, no transform, and unchanged font size. |
| Preserve global explicit-order compatibility | Implemented | Validator and value object are unchanged; import/restore regression coverage accepts the established explicit-order payload. |
| Preserve selector, renderer, restricted, lifecycle, and no-persistence behavior | Already existed and verified | Focused, SP3, canary, browser, import/export, and lifecycle assertions remain green. |
| O2 inline heading editing | Deferred by approved option | Not implemented. |
| O3 global explicit-order cutover | Deferred by approved option | Not implemented; the compatibility boundary remains authoritative. |
| O4 path-aware invalid-field navigation | Deferred by approved option | Not implemented. |
| Production normalization | Not applicable and excluded | It was neither run nor added to an operator procedure. |
| Migration, dependency, permission, persistence, lifecycle, production, or roadmap-selection change | Not applicable and out of scope | None was added or performed. |

## Compatibility boundary

No implementation change was made to:

- `PublicFrontConfigValidator`;
- `PublicFrontCardTemplate`;
- settings import, restore, normalize, backup, or import-lock services;
- focused ownership, stale-write, lifecycle, reference-scan, or permission
  contracts; or
- public renderer/presenter behavior.

The focused editor only adapts between position-canonical Builder state and the
existing explicit-order compatibility boundary. No production data rewrite is
part of this closure.

## Files changed

- `app/Filament/Pages/BuildsPublicContentSettingsSubjectSchemas.php`
- `app/Filament/Pages/CardTemplateEditorPage.php`
- `app/Support/Settings/CardTemplates/CardTemplateDraftNormalizer.php`
- `resources/views/filament/card-templates/part-heading.blade.php`
- `resources/views/filament/card-templates/part-separator.blade.php`
- `resources/views/filament/card-templates/part-summary.blade.php`
- `resources/views/filament/pages/card-template-preview.blade.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `tests/Feature/CardTemplateEditorPreviewTest.php`
- `tests/Feature/CardTemplatePreviewerTest.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `tests/Browser/CardTemplatePreviewBrowserTest.php`
- `tests/Support/SettingsSp3cCanaryPage.php`
- `docs/research/settings-performance/29-step5b-card-template-editor-ux2-research.md`
- `docs/research/settings-performance/30-step5b-card-template-editor-ux2-implementation-plan.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/settings-step5b-card-template-preview-handoff.md`
- this handoff.

## Tests added or updated

- Focused Builder schema and Livewire tests cover legacy sorting, recursive
  order stripping, native modal configuration, top/nested moves, clamping,
  validation, UUID stability, no-op refresh suppression, transient switches,
  subordinate visibility/value preservation, and x10 normalization.
- Draft-normalizer tests cover forged order/transient-key stripping,
  non-array filtering, and independent recursive x10 synthesis.
- Public Card Template builder tests retain global validator compatibility and
  verify the localized helper contract.
- SP3C canary mirror removed the obsolete order inputs and was remeasured.
- Authenticated browser tests cover real width/centering/no-scale geometry,
  compact controls/status, native row collapse, the move modal, and
  always-reachable label controls.

## Performance evidence

Component/canary measurements and browser measurements are reported in their
own planes; browser DOM/heap/timing observations are not promoted to component
budgets.

- `STEP5B_CANARY_REPORT=1` passed 23 tests / 596 assertions. Maximum ready
  preview delta was 34 elements, 0 wrappers, 0 editor controls, 0 control IDs,
  0 wire-model paths, 7,213 HTML bytes, and 7,714 serialized-state bytes. The
  existing frozen ceilings remain 41 / 0 / 0 / 0 / 0 / 8,739 / 9,299.
- Narrow slide-over component response was 1,456 elements, 9 wrappers,
  2 controls, 2 wire-model paths, 508,573 HTML bytes, 6,775 serialized-state
  bytes, and one root. Against the prior 1,304 / 8 / 466,561 / 6,868 sample,
  elements rose 11.7%, wrappers by one, HTML 9.0%, and serialized state fell by
  93 bytes; the bounded UI additions explain the movement and it remains below
  the 20% review threshold.
- `STEP5B_BROWSER_REPORT=1` passed 5 tests / 110 assertions. Wide observation:
  1,978 DOM elements, one preview root, 8 focusables, 0 public interactions,
  correct RTL, no horizontal overflow, 6 Livewire components, and an
  observational 11.9 MB heap reading. Refresh used one Livewire request,
  observed 146 ms, left zero DOM-count delta, and retained one root. Narrow
  mode added 105 DOM elements, kept focus in the modal, and retained no public
  interaction or overflow.

## Commands and results

### Preflight, research, and baseline

- `git status --short --branch`, Git-root, recent-commit, and worktree checks:
  clean `main...origin/main [ahead 42]`, authoritative Herd checkout, audited
  HEAD `8b3b5b06cedea984ffd277fbf29d8c3f3268e3da`, no UX2 implementation started.
- Required repository, lesson, state, ledger, recent-handoff, audit, relevant
  code/test, package-source, and skill inspections completed before editing.
- Installed-version inspection recorded Laravel 13.19, Filament 5.6.7,
  Livewire 4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2.
- Laravel Boost version-aware searches covered Builder actions/modal/state,
  collapse, live toggles, conditional fields, and raw state.
- FilamentExamples used two decomposed search passes and exposed search/snippet
  evidence only; installed Filament 5 source was decisive for the native
  extra-item action implementation.
- `php artisan test --compact --filter=SettingsSp3`: passed 71 tests / 945
  assertions before implementation.
- A static `php artisan tinker --execute=...` registry inspection produced only
  a PsySH history permission warning and no application output. It did not
  query or mutate the development database; source inspection replaced it.

### Iteration and focused verification

- `vendor/bin/pint --dirty`: first useful run corrected import/indentation
  style; every later iteration passed.
- Initial `CardTemplateEditorPreviewTest`: passed 11 tests / 154 assertions.
- Initial updated `CardTemplatePreviewerTest`: 10 passed and 1 stale expected
  order assertion failed; the test was corrected to the approved x10 contract.
- First expanded editor run: 11 passed with 2 test-harness errors
  (`Text::getName()` and an over-strict previewer mock); harness assertions were
  corrected without changing the production contract.
- Next expanded editor run: 12 passed with 1 nested-action test path error; the
  duplicated `form` path segment was corrected.
- Settled expanded editor run: passed 13 tests / 208 assertions.
- First Public Front builder run: 24 passed with 1 stale fixture failure after
  transient switches were introduced; the fixture was corrected.
- Settled Public Front builder run: passed 25 tests / 322 assertions.
- Sandboxed `php artisan test --compact --filter=CardTemplate`: 49 application
  tests passed; 5 browser cases failed because Chromium bootstrap/rendezvous was
  denied by the macOS sandbox. No application change was made for that runner
  failure.
- Permitted retry of the same target: 52 passed and 2 browser assertions failed
  because the test compared top edges instead of centers and queried a
  checkbox while Filament renders the toggle as a button. Those assertions
  were corrected.
- Permitted focused browser file: passed 5 tests / 110 assertions.
- Post-change `php artisan test --compact --filter=SettingsSp3`: passed 71 tests
  / 945 assertions.
- `env STEP5B_CANARY_REPORT=1 php artisan test --compact
  tests/Feature/SettingsSp3cCanaryTest.php
  tests/Feature/CardTemplateEditorPreviewTest.php`: passed 23 tests / 596
  assertions.
- `env STEP5B_BROWSER_REPORT=1 php artisan test --compact
  tests/Browser/CardTemplatePreviewBrowserTest.php`: passed 5 tests / 110
  assertions with the permitted Chromium runner.
- `vendor/bin/filacheck --dirty`: passed with 0 issues.
- Final focused feature trio before documentation closeout: passed 49 tests /
  607 assertions.
- `php artisan test --compact tests/Feature/SettingsImportExportTest.php
  tests/Feature/SettingsSp3cTest.php`: passed 59 tests / 566 assertions.

### Ordered final gate

- Requirements sweep passed: the diff remains inside O1; global validator,
  value-object, import, restore, backup, and lifecycle compatibility files are
  unchanged; no editable part-order input remains; HE/EN touched keys are
  paired; and no O2/O3/O4, cross-Builder endpoint, migration, dependency,
  persistence, production action, or next-step selection is present.
- First `vendor/bin/pint --test`: passed.
- First `vendor/bin/filacheck`: passed with 0 issues.
- First `npm run build`: passed with Vite 8.1.0.
- First full `php artisan test`, last and serial with the permitted external
  Chromium runner: passed 771 tests / 9,765 assertions in 357.927 seconds.
- After recording these results, the ordered gate restarted from Pint on the
  documented final tree: `vendor/bin/pint --test` passed;
  `vendor/bin/filacheck` passed with 0 issues; `npm run build` passed with Vite
  8.1.0; and the full serial `php artisan test` passed last with 771 tests /
  9,765 assertions. Neither full suite was parallelized or interrupted.

## Assumptions, limitations, and deferrals

- The approved compatibility boundary means position is canonical only inside
  the focused editor. Explicit order remains authoritative at global ingress
  and value-object boundaries.
- Native modal movement is intentionally scoped to one owning Builder. O2's
  inline heading edit, O3's global cutover, O4's navigation system, and a
  cross-Builder move system are not hidden in this implementation.
- Browser heap and timing are observational. The runner does not expose a
  reliable listener enumeration plane, so no listener value was fabricated.
- No dependency, migration, permission, persistence, lifecycle, production,
  remote, branch, worktree, or next-roadmap-step change was needed.

## Local Front Check Report

1. Open Admin > Settings > Card Templates and edit a content-item template.
   Expect Template settings and Template parts to be open initially and to
   collapse independently without losing the draft.
2. Inspect top-level and nested part rows in English and Hebrew. Expect an
   escaped position badge, translated type, compact separators, and localized
   summary values.
3. Collapse and reopen an inline part with its native row control. Expect the
   draft and preview state to remain intact.
4. Open a top-level row's icon-only move action. Expect one compact numeric
   position field in an extra-small modal.
5. Move the row forward and backward. Expect take-the-slot sibling order,
   stable row identity, and one preview refresh after each real move.
6. Enter zero, a negative value, and a value above the sibling count. Expect
   server clamping to the first or last position.
7. Submit the row's current position. Expect no movement and no preview refresh.
8. Repeat the move on a nested row. Expect only that parent block's children to
   reorder; expect no move across the top-level/nested boundary.
9. Drag a sibling to the same target position. Expect the same sibling order as
   the modal move and contiguous x10 order only at preview/save compatibility
   projection.
10. Toggle Show label off. Expect its switch to remain reachable, subordinate
    label controls to hide, the entered label to remain, and position to return
    as inline-before when re-enabled.
11. Repeat with Show icon. Expect the same reachability, conditional hiding,
    entered-icon preservation, and inline-before re-enable position.
12. Set preview card width to 60%. Expect the real card to occupy approximately
    60% of the plane, remain centered, reflow normally, and keep unchanged text
    and image scale.
13. Reload the editor. Expect width to reset to 100% and sample selection to
    reset; neither value should be persisted to settings.
14. Inspect the compact control row. Expect Sample, Width, and icon-only
    Refresh together, followed by short current/stale text and a short
    day-first Jerusalem timestamp.
15. Exercise inline and slide-over part modes. Expect existing debounce versus
    Apply-time refresh behavior, focus trapping, and logical-start geometry to
    remain unchanged.
16. Open a protected template without its current capability. Expect no sample
    selector, protected value, public-safe sample query, or changed Save guard.
17. Import or restore a valid explicit-order payload only through automated
    tests. Expect the unchanged compatibility path to accept it; this UX2
    closure contains no production normalization procedure.
18. Save a valid focused draft. Expect each sibling list to persist contiguous
    x10 order without a settings notification or lifecycle effect during mere
    preview/move interactions.
19. Make an unsaved edit and navigate away. Expect the existing unsaved-change
    warning to remain authoritative.

## Current Git status

Before the implementation commit, `main...origin/main [ahead 42]` contains only
the O1 implementation, tests, required research/plan, and requested closeout
documentation listed above; no unrelated pre-existing change is present.

## Commit hash

`82e639d0fd22c06c52a70acec7c26ee9e2d8c72a`
