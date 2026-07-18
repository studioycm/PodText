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

## Template-parts auto-refresh and editor UX correction

Laravel Simplifier audit `LS-20260718-STEP5B-PARTS-AUTOREFRESH-01` and approved
option `STEP5B-PARTS-AUTOREFRESH-O1` add the smallest post-closeout correction
for the editor behavior observed by the operator.

- The top-level `parts` Builder now owns `live(debounce: 500)` state binding.
  Direct `data.parts...` updates refresh the existing transient preview after
  capability enforcement without saving settings.
- Builder edit/add/delete/clone/reorder mutations use the Builder's existing
  state-updated callback to refresh. Because block previews edit their cloned
  action schema in a modal, those modal inputs remain deferred until the
  operator accepts the part edit; the accepted edit then refreshes the preview
  automatically in one Livewire request.
- Family changes retain their existing automatic refresh and sample reset.
  Ordinary root fields such as label, layout, density, image size, and title
  size remain explicit-refresh inputs by design.
- The wide preview now clears the sticky 4rem Filament topbar with a 5.5rem
  logical viewport offset and matching maximum height. Its internal preview
  header remains sticky at `top-0` inside the preview scroll box.
- Cancel now has English and Hebrew strings and is rendered beside Save in the
  form actions. Preview and Delete remain header actions.

The correction retains the restricted selector boundary, protected-part
absence, all three preview families, transient sample identity, deterministic
public-safe sample behavior, and the 50-result selector-search cap. It adds no
permission, persistence, dependency, migration, or generalized preview
infrastructure.

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
| Family and template-part automatic refresh boundaries | Implemented | The live family select clears sample identity and refreshes once. Direct `data.parts...` updates use the Builder's 500ms binding, while accepted Builder modal/structural mutations refresh through one Builder callback. Ordinary root fields remain explicit-refresh inputs, and opening a current slide-over does not recompute preview. |
| Automatic refresh has no settings persistence/lifecycle effect | Implemented | Focused writer/scanner mocks and the `SettingsSaved` event fake remain untouched while direct and structural part updates refresh the preview. |
| Wide preview clears the sticky Filament topbar | Implemented | The aside uses a 5.5rem top offset and matching viewport-height bound; authenticated Chromium verifies its top edge remains at or below the topbar bottom after scrolling. |
| Cancel is translated and placed beside Save | Implemented | English `Cancel` and Hebrew `ביטול` keys are covered in the form-action order; Chromium verifies the settings URL action is in the form and absent from the header. Delete remains a header action. |
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
| Automatic preview for ordinary root fields | Deferred by approved correction boundary | Label, layout, density, image size, and title size retain explicit Refresh. Only family and accepted Builder part changes auto-refresh. |
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
- Template-parts/editor UX correction:
  `docs/research/settings-performance/25-step5b-parts-autorefresh-research.md`,
  `docs/research/settings-performance/26-step5b-parts-autorefresh-implementation-plan.md`,
  `app/Filament/Pages/CardTemplateEditorPage.php`,
  `resources/views/filament/pages/card-template-editor.blade.php`,
  `lang/en/admin.php`, `lang/he/admin.php`,
  `tests/Feature/CardTemplateEditorPreviewTest.php`, and
  `tests/Browser/CardTemplatePreviewBrowserTest.php`.

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
- Added direct part-state and actual Builder delete-action regressions proving
  automatic refresh, retained sample identity/current status, a 500ms Builder
  binding, an unchanged deferred root field, and no settings writer/scanner or
  `SettingsSaved` event.
- Added bilingual form-action placement coverage and authenticated Chromium
  coverage for the actual Builder modal-save refresh, one Livewire request,
  one preview root, sticky topbar clearance, translated Cancel placement, and
  no JavaScript errors.

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

### Template-parts auto-refresh correction checks

- Stage 2 preflight confirmed clean `main` at
  `911f707ae691f0ee49ab4e640b579b1ffd077fa8`, the original and restricted
  closure Step 5B commits, unchanged scoped source, and exact audit/option IDs.
- Laravel Boost `application_info` plus version-aware Filament/Livewire/Pest/
  Tailwind searches completed. FilamentExamples completed two refined
  search/snippet batches; no full-source/detail endpoint was available.
- Initial
  `php artisan test --compact tests/Feature/CardTemplateEditorPreviewTest.php`:
  failed before tests because a `void` arrow callback returned the
  `refreshPreview()` expression. Replaced it with an explicit `void` closure.
- Retry of that command: passed 6 tests / 94 assertions.
- After adding regressions, the same command failed with 7 tests passing and
  one error because this SettingsPage does not expose
  `getCachedFormActions()`. The test now uses its public `getFormActions()`
  contract; retry passed 8 tests / 120 assertions.
- Initial sandboxed
  `php artisan test --compact tests/Browser/CardTemplatePreviewBrowserTest.php`:
  all 3 cases failed before assertions because Chromium was denied its macOS
  rendezvous port.
- Permitted external retries exposed test-only assumptions in sequence: the
  unbuilt sticky utility (2 passed / 1 failed, 31 assertions), an excessive
  scroll endpoint (2 / 1, 31), an overbroad header selector (2 / 1, 33), the
  Builder action modal's intentionally deferred `wire:model` binding (2 / 1,
  38), and a hidden-modal selector which counted 11 requests and did not
  observe the preview result (2 / 1, 39; single-test retry 0 / 1, 11).
- Iteration `npm run build`: passed with Vite 8.1.0 and made the new Tailwind
  utility available to Chromium.
- The browser test now targets the open Filament modal and submits its real
  action form. Single-test retry passed 1 test / 16 assertions; full focused
  file retry passed 3 tests / 44 assertions.
- `git diff --check`: passed.
- Focused `vendor/bin/pint` over the changed PHP/test/locale files: passed.
- Final focused
  `php artisan test --compact tests/Feature/CardTemplateEditorPreviewTest.php`:
  passed 8 tests / 120 assertions.
- `php artisan test --compact tests/Feature/CardTemplatePreviewerTest.php`:
  passed 8 tests / 42 assertions.
- `php artisan test --compact tests/Feature/SettingsSp3cCanaryTest.php`:
  passed 10 tests / 388 assertions; frozen SP3C ceilings are unchanged.

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

### Template-parts auto-refresh correction final gate

The requirements sweep passed before this sequence:

- Implemented: direct `data.parts...` updates and accepted Builder modal/
  structural mutations refresh through the existing transient preview without
  calling the settings writer, reference scanner, or settings lifecycle.
- Implemented: the Builder owns a 500ms live modifier; its cloned block-preview
  edit modal remains deferred until its real Save action, which refreshes the
  authoritative Builder draft in one Livewire request.
- Implemented: family/sample behavior, all three preview families, authorized
  selector search/label/selection, the 50-result cap, restricted query
  suppression, and protected sentinel absence remain unchanged.
- Implemented: the wide preview clears the Filament topbar and retains one
  independently scrolling preview root.
- Implemented: English and Hebrew Cancel labels render beside Save; Delete and
  Preview remain header actions.
- Deferred by the approved boundary: ordinary root fields remain explicit-
  refresh inputs. No generalized per-keystroke preview was added.
- Not applicable/out of scope: permissions, persistence, migrations, models,
  dependencies, sample semantics, public-card redesign, generalized preview
  infrastructure, production/database actions, and another roadmap item.
- Documentation synchronized: the handoff, current state, active pending queue,
  and ledger record completion while retaining that no next implementation is
  automatically selected.

1. Requirements/scope sweep using `git diff --check`, scoped changed-file and
   implementation-marker inspection, translation inspection, and roadmap-rule
   inspection: passed.
2. `vendor/bin/pint --test`: passed.
3. `vendor/bin/filacheck`: passed with 0 issues.
4. `npm run build`: passed with Vite 8.1.0.
5. `php artisan test`: passed 760 tests / 9,573 assertions in 350.988 seconds.
   The full command used the permitted external runner because the sandboxed
   Chromium launch is known to fail on its macOS rendezvous port; the suite was
   not parallelized or interrupted.

After this result section was written, the requirements sweep and the same
canonical Pint → FilaCheck → build → full-suite sequence were repeated on the
final documented implementation tree. The repeated results remained green;
the full-suite result is the final result reported for the committed tree.

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
- Builder block-preview inputs are edited in Filament's cloned action schema
  and therefore remain deferred while the modal is open. Accepting that edit
  updates the authoritative Builder state and automatically refreshes the
  preview. The change does not attempt a second preview draft inside the modal.
- Root template fields other than family remain explicit-refresh inputs. This
  correction is intentionally limited to template parts.
- No live network, mail, development database, migration, dependency install,
  production action, push, or remote publication occurred.

## Local Front Check Report

1. Open Admin > Settings > Card Templates and edit a content-item template.
   Expect Delete and Preview in the page header, and expect translated Save and
   Cancel actions together below the form.
2. At a window at least 1280 CSS pixels wide, scroll the editor. Expect the
   preview to remain sticky below the Filament topbar, never beneath it, with
   its own internal scrolling.
3. Open a Builder part, change a visible setting such as custom text, and
   accept the part edit. Expect the adjacent preview to update automatically
   without clicking Refresh and without saving the template.
4. Add, clone, reorder, and delete a part. After each accepted Builder action,
   expect the preview to update automatically and keep the selected sample.
5. Change the root template label or title size. Expect “Changes not yet
   previewed” while the last preview remains visible; these root fields remain
   explicit-refresh inputs.
6. Click Refresh preview. Expect the root-field changes to render, the
   freshness status and Jerusalem day-first timestamp to update, and no
   template save or settings notification to occur.
7. Click Choose sample, search for another published episode, and select it.
   Expect no more than 50 server results and the selected sample to remain
   transient after leaving the editor.
8. Change the family. Expect the old sample to clear and one automatic preview
   refresh to render the matching podcast or contributor card.
9. Narrow the window below 1280 CSS pixels. Expect the adjacent preview to
   disappear and the Preview header action to appear.
10. Open Preview, press Tab repeatedly, and then press Escape. Expect focus to
   stay inside the slide-over while open and return to the Preview action when
   it closes; expect unsaved editor changes to remain.
11. Reopen Preview and widen the window past 1280 CSS pixels. Expect one
   adjacent preview, no duplicate slide-over DOM, and focus on the adjacent
   preview heading.
12. Try every visible card title, image, category/tag, contributor, and action
   affordance inside the preview. Expect none to navigate, submit, or activate
   Livewire behavior.
13. Repeat the wide/narrow flow in Hebrew and English. Expect Hebrew RTL,
    English LTR, preview placement at logical end, independent editor/preview
    scrolling, translated Cancel text instead of `admin.actions.cancel`,
    wrapped long sample labels, and no horizontal page overflow.
14. Edit a protected template as an actor without its current capability. At
    both wide layout and in the narrow Preview slide-over, expect a restricted
    preview with no Choose sample action or searchable sample control. Expect
    no protected part values in page source and unchanged existing Save
    protection.
15. Make an unsaved edit and use Back or close the tab. Expect the existing
    unsaved-changes warning to remain authoritative.

## Commit hash

`c75d0f2b2d476c58d12c16610ea97ba4088c5e79`

## Restricted selector closure commit hash

`69813dbd4002ed8e7c3e42e640f7d48085e275da`

## Template-parts auto-refresh correction commit hash

`cdf0e89789c0c987abc0f577ea74d6c8303afa4b`
