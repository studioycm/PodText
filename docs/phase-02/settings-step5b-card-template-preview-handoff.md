# Step 5B Card Template Preview UX Handoff

## Contract

- Accepted specification:
  `docs/research/settings-performance/21-step5b-card-template-preview-ux-specification.md`
  v1.
- Laravel Simplifier audit: `LS-20260717-STEP5B-01`.
- Approved option: `STEP5B-O1-FOCUSED-PREVIEW`.
- Clean implementation baseline:
  `a137123cba22ba1325ef491959fecbc1b53d5706`.
- Approved interpretations: the sample selector is an isolated preview-shell
  action control, and preview composition/refresh does not read the configured
  Card Template list or settings-backed render context.
- No migration, dependency, persistence, permission, lifecycle, ownership, or
  generalized preview-platform change was authorized or added.

## Outcome

Create and Edit Card Template pages now preview the current unsaved single
`data` draft. At `xl` and wider, one independently scrolling preview is mounted
at logical end. Below `xl`, the adjacent DOM is unmounted and the existing
server preview result opens in a native read-only Filament slide-over.

Preview establishment and explicit refresh:

- share the writer's extracted Builder-transport cleanup and normalize exactly
  one candidate through the existing validator/value object;
- construct an in-memory registry-default render context and a preview-local
  transcription policy, selector, aggregates, image resolver, and renderer;
- select one deterministic published item, group, or contributor, or use a
  transient server-searched selection capped at 50 results;
- reuse the current presenters and public card/part Blade components;
- store only locked scalar status/sample fields and app-rendered HTML in
  Livewire state, never an Eloquent model or relation graph; and
- do not call the focused writer, reference scanner, settings save/lifecycle,
  backup, cache invalidation, or configured Card Template resolver/list.

Public card components accept an explicit `previewMode`. It preserves visible
content while removing `href`, `target`, `wire:click`, button submission, and
keyboard tab stops. Ordinary public calls retain their existing defaults.

## Restricted sample-selector closure

The later Laravel Simplifier audit `LS-20260717-STEP5B-CLOSURE-01` confirmed a
restricted-shell contract gap, not a protected-data disclosure. A protected
template's preview already stopped before rendering protected parts, but its
retained valid family could still offer the transient public-safe sample
selector.

The approved `STEP5B-CLOSURE-O1` repair now:

- omits Choose sample from a restricted preview shell;
- hides and disables the Filament action using the existing restricted/current
  capability state, so a forged `mountAction()` stops before its schema mounts;
- returns before `sampleOptions()`, `sampleLabel()`, or a selection refresh if
  the state becomes restricted; and
- proves that mount, a forged action request, and forged schema-component
  lookup cannot query `content_items`, `content_groups`, or `authors`.

This preserves the authorized selector's existing public-safe semantics,
three-family support, transient state, and 50-result cap. It does not add a
permission, alter public sample eligibility/order, or expose protected parts.

## Requirement classification

| Requirement | Classification | Evidence |
| --- | --- | --- |
| Create/Edit use the current unsaved single draft | Implemented | Locked preview state is derived from `data`; Edit refresh coverage changes unsaved `title_size`; Create family-change coverage uses the unsaved draft. |
| Zero preview writer/settings-list/lifecycle/reference side effects | Implemented | Focused mocks/event assertions reject writer, scanner, configured Card Template settings, save events, and preview-local settings resolution. Existing capability hydration remains the authoritative security gate. |
| Shared Builder transport cleanup | Implemented | `CardTemplateDraftNormalizer` owns the former writer cleanup and is used by writer and previewer. |
| Exactly one normalized candidate; no saved fallback | Implemented | Invalid-draft tests reject malformed drafts and leave preview HTML empty. |
| Existing value object/renderer/presenters/public components | Implemented | All three families use `PublicFrontCardTemplate`, existing presenters, renderer, and card/part views. |
| Deterministic public-safe sample and bounded selector | Implemented | Family queries use existing public scopes/aggregates, deterministic ordering, one-record fetches, and server search capped at 50. |
| Eager-loaded/constant query plane | Implemented | Three identical family query runs are constant and lazy-loading prevention remains green. |
| Family-specific empty/error/restricted/loading/stale states | Implemented | HE/EN copy and focused component coverage exercise no-sample, invalid, restricted, current/stale, and modal response states. |
| Family change is the only automatic refresh | Implemented | The live family select clears sample identity and refreshes once; other fields use explicit Refresh. Opening a current slide-over does not recompute preview. |
| Responsive adjacent/slide-over single mount | Implemented | Authenticated Chromium verifies one active root at 1440 and 1024 CSS px and no duplicate root after both resize directions. |
| HE/EN, RTL/LTR, logical end, independent scroll | Implemented | Authenticated Chromium verifies Hebrew RTL and English LTR; CSS uses logical layout and both editor/preview scroll containers remain independent. |
| Keyboard, focus trap, Escape, resize restoration | Implemented | Native slide-over trap plus explicit heading/open-button focus targets are covered in Chromium. |
| Inert public interactions | Implemented | Feature and browser assertions find no public-card `href`, `wire:click`, buttons, or other public interactions in either preview mode. |
| Protected state and restricted selector boundary remain absent | Implemented | Restricted interaction coverage proves Choose sample is hidden/disabled, forged action/schema requests issue no item/group/author sample query, and the protected sentinel remains absent from HTML and serialized draft state. |
| One draft/no model graph/no added editor controls | Implemented | Preview-aware canary adds zero wrappers, editor controls, or wire-model paths; preview state contains compact scalars/presented HTML only. |
| Three-run component delta and frozen SP3C preservation | Implemented | Three identical unselected/selected/nested runs pass without changing the SP3C ceilings. |
| Real-browser DOM/network/listener/heap/timing evidence | Implemented with runner limitation | Chromium records DOM, roots, focusables, one refresh request, heap observation, and timings. The runner exposes Livewire component count but not listener enumeration; no listener value was fabricated. |
| Existing writer/public/settings regressions | Already existed and verified | Focused writer/public/SP3C suites and the full suite remain green. |
| Saved preview preferences, autosave, revisions, collaboration, synthetic/persisted samples | Deferred by specification | No persistence or generalized preview architecture was added. |
| New permission, ARCH1/AUTHZ/SP3D, migration, dependency | Not applicable/out of scope | None was needed. |

## Performance evidence

The unchanged pre-candidate SP3C baseline ran three times and retained its
frozen values. The Step 5B preview-aware canary then ran unselected,
top-selected, and nested-selected states for all three families three times.

- Maximum ready-preview delta observed before adopting the budget: 34 DOM
  elements, 0 field wrappers, 0 editor controls, 0 wire models, 7,282 HTML
  bytes, and 7,749 serialized-state bytes.
- New Step 5B maximum-plus-20-percent budgets: 41 elements, 0 wrappers, 0
  controls, 0 wire models, 8,739 HTML bytes, and 9,299 serialized-state bytes.
- Separate narrow slide-over component response: 1,304 elements, 8 wrappers,
  2 controls, 2 wire models, 466,561 HTML bytes, 6,868 serialized-state bytes,
  and one server preview root.

Authenticated Chromium observation at 1440 × 900:

- 1,676 page DOM elements;
- one active preview root and two preview-shell focusables;
- zero interactive elements inside the rendered public card;
- one explicit Livewire refresh request, approximately 129 ms in the recorded
  run, zero DOM-element delta, and one preview root after refresh;
- `usedJSHeapSize` was supported and read as approximately 10.6 MB before the
  refresh; the observed refresh delta was zero at the runtime's granularity;
- six Livewire components were observable, but listener enumeration was not
  exposed by the runner.

At 1024 × 800, opening the slide-over added 69 observed DOM elements, retained
one active preview root, focused the preview heading inside the native trap,
and retained zero public-card interactions. Escape and the resize-to-wide path
both restored focus to the appropriate preview control/heading.

These browser numbers are recorded observations, not new pass/fail ceilings.

## Files changed

- Preview/editor PHP:
  `app/Filament/Pages/CardTemplateEditorPage.php`,
  `app/Filament/Pages/CreateCardTemplate.php`,
  `app/Filament/Pages/EditCardTemplate.php`.
- Focused support:
  `app/Support/Settings/CardTemplates/CardTemplateDraftNormalizer.php`,
  `app/Support/Settings/CardTemplates/CardTemplatePreviewer.php`, and
  `app/Support/Settings/CardTemplates/CardTemplateFocusedWriter.php`.
- Preview-local query seams:
  `app/Support/PublicContent/PublicContentItemQueries.php`,
  `app/Support/PublicContent/PublicContributorDiscovery.php`,
  `app/Support/PublicContent/PublicTranscriptionPolicy.php`, and
  `app/Support/PublicFront/Groups/PublicContentGroupQueries.php`.
- Views:
  `resources/views/filament/pages/card-template-editor.blade.php`,
  `resources/views/filament/pages/card-template-preview.blade.php`, and the
  item/group/contributor public card and card-part components.
- Localization: `lang/he/admin.php`, `lang/en/admin.php`.
- Tests:
  `tests/Feature/CardTemplatePreviewerTest.php`,
  `tests/Feature/CardTemplateEditorPreviewTest.php`,
  `tests/Feature/SettingsSp3cCanaryTest.php`, and
  `tests/Browser/CardTemplatePreviewBrowserTest.php`.
- Planning/state:
  `docs/research/settings-performance/22-step5b-card-template-preview-implementation-plan.md`,
  this handoff, current project state, and the mini-step ledger.
- Restricted-selector closure:
  `docs/research/settings-performance/23-step5b-restricted-preview-closure-research.md`,
  `docs/research/settings-performance/24-step5b-restricted-preview-closure-implementation-plan.md`,
  `docs/research/settings-performance/10-pending-decision-question-queue.md`,
  `app/Filament/Pages/CardTemplateEditorPage.php`,
  `resources/views/filament/pages/card-template-preview.blade.php`, and
  `tests/Feature/CardTemplateEditorPreviewTest.php`.

## Tests added or updated

- Added preview normalization, zero-settings-render-context, all-family
  rendering, deterministic/forged sample, 50-result cap, invalid fallback,
  constant-query, and lazy-loading tests.
- Added editor unsaved refresh, locked-state, current-modal no-recompute,
  family-change, empty/invalid, and protected-state tests.
- Added the separate three-run Step 5B component delta and cap to the existing
  SP3C canary without changing frozen ceilings.
- Added authenticated Chromium coverage for responsive roots, native
  slide-over focus/trap/Escape/restoration, scroll, inert cards, refresh
  network/DOM/heap timing, dirty `beforeunload`, and HE/EN directions.
- Added restricted interaction coverage for selector absence/disablement,
  forged action/schema requests, all three sample-query tables, protected
  sentinel absence, authorized search/selected-label/selection/refresh for all
  three families, and the 50-result selector bound.

## Commands and results

### Preflight and research

- `git status --short --branch`, recent history, commit verification, and
  session-doc reads: clean `main` baseline at the accepted commit.
- Laravel Boost installed-version/application inspection and version-aware
  Laravel/Filament/Livewire/Pest docs: completed.
- FilamentExamples two-pass searches: search/snippet access only; relevant
  slide-over/read-only action/custom-page patterns recorded in the plan.
- SP3C unchanged baseline report: passed 1 test / 21 assertions with three
  identical samples and the existing frozen ceilings unchanged.

### Focused implementation checks

- Initial `CardTemplatePreviewerTest`: failed 1 of 7 because the contributor
  fixture lacked the pivot-backed transcriber relation; fixture ownership was
  corrected with `syncTranscribers`, then 7 tests / 38 assertions passed.
- Initial `SettingsSp3cTest`: 10 passed, 2 failed, 18 errors because a Blade
  directive inside an Alpine attribute did not compile; the modal/non-modal
  focus buttons were separated, `php artisan view:cache` passed, and the retry
  passed 30 tests / 284 assertions.
- `CardTemplateEditorPreviewTest`: passed after adding modal response coverage.
- Preview-aware canary report: passed three identical runs and adopted the
  maximum-plus-20-percent Step 5B budgets above.
- Browser test in the sandbox: failed because Chromium could not register its
  macOS rendezvous port. External reruns exposed and fixed the slide-over view
  data boundary and explicit focus restoration. Final focused browser run:
  2 tests / 28 assertions passed, including long-content overflow and
  technical-key direction checks.
- `vendor/bin/pint --dirty`: formatted five changed PHP/test files.
- Focused settled regression: 52 tests / 752 assertions passed.
- Public card/editor regression: 28 tests / 358 assertions passed.
- Public transcription policy/preview regression: 25 tests / 199 assertions
  passed.
- Final long-content/technical-key browser rerun: 2 tests / 28 assertions
  passed.

### Restricted selector closure checks

- Stage 2 preflight confirmed clean `main` at
  `2861c320bbeb1091e57b436623241feea039f64a`, both required Step 5B commits,
  the scoped source baseline, and the exact approved audit/option IDs.
- Laravel Boost installed-version and action/select/Livewire testing research:
  completed. FilamentExamples completed two search/snippet passes; no
  source/detail endpoint was available.
- `php artisan test --compact tests/Feature/CardTemplateEditorPreviewTest.php`:
  passed 6 tests / 94 assertions.
- `php artisan test --compact tests/Feature/CardTemplatePreviewerTest.php`:
  passed 8 tests / 42 assertions.
- `php artisan test --compact tests/Feature/SettingsSp3cCanaryTest.php`:
  passed 10 tests / 388 assertions; frozen SP3C ceilings remain unchanged.
- Sandboxed `php artisan test --compact tests/Browser/CardTemplatePreviewBrowserTest.php`:
  failed 2 browser cases before assertions because Chromium was denied its
  macOS rendezvous port. No files changed. The permitted external retry of the
  same command passed 2 tests / 28 assertions.

### Ordered final gate

Requirements sweep passed before the gate.

#### Restricted selector closure requirements sweep

- Implemented: restricted preview shells do not render or offer Choose sample;
  the page action is also disabled before Filament can mount its schema.
- Implemented: search, selected-label, and selection callbacks return before
  preview sample services in a restricted/current-capability state; Refresh
  keeps its existing restricted-safe branch.
- Implemented: focused restricted interaction coverage proves no
  `content_items`, `content_groups`, or `authors` sample query after the
  initial shell mount, including forged action/schema attempts.
- Implemented: authorized item, group, and contributor selectors retain their
  real search, selected-label, selection, refresh, transient state, and
  50-result behavior.
- Implemented: protected parts remain absent from HTML/editor state; the
  Step 5B browser and SP3C canary preservation suites remain unchanged.
- Implemented: the handoff wording and pending-decision queue are synchronized;
  the ledger/current state retain the rule that no next implementation is
  automatically selected.
- Not applicable: persistence, migration, dependency, capability architecture,
  sample eligibility/order changes, public-card redesign, and all excluded
  roadmap work.

1. `vendor/bin/pint --test`: passed.
2. `vendor/bin/filacheck`: passed with 0 issues.
3. `npm run build`: passed with Vite 8.1.0.
4. Initial sandboxed `php artisan test`: application tests passed, while all
   10 browser cases failed at Chromium launch because the sandbox denied the
   rendezvous port; 745 tests and 9,373 assertions passed before those 10
   environment failures.
5. No files changed; external retry of `php artisan test`: passed 755 tests /
   9,473 assertions in 343.413 seconds.

The canonical post-handoff final gate repeated the requirements sweep and the
same ordered commands on the final documented tree: Pint passed, FilaCheck
reported 0 issues, Vite built successfully, and the external full suite passed
755 tests / 9,476 assertions.

### Restricted selector closure final gate

The documented closure requirements sweep above passed before this sequence.

1. Initial `vendor/bin/pint --test`: failed only on
   `CardTemplateEditorPreviewTest.php` `braces_position`. The multiline helper
   signature was corrected manually; no behavior changed.
2. `php artisan test --compact tests/Feature/CardTemplateEditorPreviewTest.php`:
   passed 6 tests / 94 assertions after that formatting-only correction.
3. Restarted `vendor/bin/pint --test`: passed.
4. `vendor/bin/filacheck`: passed with 0 issues.
5. `npm run build`: passed with Vite 8.1.0.
6. `php artisan test`: passed as the full-suite final command. The local runner
   did not emit an aggregate summary after completion; the successful exit is
   the recorded result.

## Assumptions, limitations, and deferrals

- The approved zero-preview-settings-read interpretation applies to preview
  composition and the configured Card Template/render-context path. Existing
  editor mount/hydration capability enforcement remains authoritative and may
  resolve the existing Admin UX mode setting.
- Browser listener enumeration is unsupported by the installed Pest/Playwright
  runner. Livewire component count was recorded; no listener proxy was used.
- Heap readings use Chromium's optional `performance.memory` plane and are not
  heap snapshots.
- Browser observations are not substituted for component HTML/state/query
  metrics or promoted to numeric ceilings.
- The closure relies on the existing restricted/current-capability state; it
  deliberately adds no general permission or preview-authorization system.
- The audit found no protected-data confidentiality exposure. The repair is
  authorization-contract enforcement, unnecessary public-sample query
  suppression, and test/handoff correction.
- No live network, mail, development database, migration, dependency install,
  production action, push, or remote publication occurred.

## Local Front Check Report

1. Open Admin > Settings > Card Templates and edit a content-item template.
   Expect the existing import-lock badge, Builder, Save/Cancel behavior, and a
   preview at logical end on a window at least 1280 CSS pixels wide.
2. Change the label, title size, and one Builder part without saving. Expect
   “Changes not yet previewed” while the last preview remains visible.
3. Click Refresh preview. Expect the unsaved changes to render, the freshness
   status and Jerusalem day-first timestamp to update, and no template save or
   settings notification to occur.
4. Click Choose sample, search for another published episode, and select it.
   Expect no more than 50 server results and the selected sample to remain
   transient after leaving the editor.
5. Change the family. Expect the old sample to clear and one automatic preview
   refresh to render the matching podcast or contributor card.
6. Narrow the window below 1280 CSS pixels. Expect the adjacent preview to
   disappear and the Preview header action to appear.
7. Open Preview, press Tab repeatedly, and then press Escape. Expect focus to
   stay inside the slide-over while open and return to the Preview action when
   it closes; expect unsaved editor changes to remain.
8. Reopen Preview and widen the window past 1280 CSS pixels. Expect one
   adjacent preview, no duplicate slide-over DOM, and focus on the adjacent
   preview heading.
9. Try every visible card title, image, category/tag, contributor, and action
   affordance inside the preview. Expect none to navigate, submit, or activate
   Livewire behavior.
10. Repeat the wide/narrow flow in Hebrew and English. Expect Hebrew RTL,
    English LTR, preview placement at logical end, independent editor/preview
    scrolling, wrapped long sample labels, and no horizontal page overflow.
11. Edit a protected template as an actor without its current capability. At
    both wide layout and in the narrow Preview slide-over, expect a restricted
    preview with no Choose sample action or searchable sample control. Expect
    no protected part values in page source and unchanged existing Save
    protection.
12. Make an unsaved edit and use Back or close the tab. Expect the existing
    unsaved-changes warning to remain authoritative.

## Commit hash

`c75d0f2b2d476c58d12c16610ea97ba4088c5e79`

## Restricted selector closure commit hash

Pending implementation commit; the immediate docs-only backfill will stamp
the full hash here and in the ledger.
