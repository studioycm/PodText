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
- Expanded editor UX closure audit:
  `LS-20260718-STEP5B-CARD-TEMPLATE-UX-01`; approved option:
  `STEP5B-CARD-UX-O1`; clean implementation baseline:
  `e31118f1c9f0fa5b2494d72fa0dc2097f6dc9d07`.
- Research and the consulted implementation plan are recorded in
  `docs/research/settings-performance/27-step5b-card-template-editor-ux-research.md`
  and
  `docs/research/settings-performance/28-step5b-card-template-editor-ux-implementation-plan.md`.
- UX2 compatibility-modal closure audit:
  `LS-20260719-STEP5B-CARD-TEMPLATE-UX2-01`; approved option:
  `STEP5B-CARD-UX2-O1-COMPAT-MODAL`; clean implementation baseline:
  `8b3b5b06cedea984ffd277fbf29d8c3f3268e3da`.
- UX2 research, its consulted implementation plan, and the dedicated handoff
  are recorded in
  `docs/research/settings-performance/29-step5b-card-template-editor-ux2-research.md`,
  `docs/research/settings-performance/30-step5b-card-template-editor-ux2-implementation-plan.md`,
  and
  `docs/phase-02/settings-step5b-card-template-editor-ux2-handoff.md`.

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

- omits the sample control from a restricted preview shell;
- returns an empty inline Select schema using the existing restricted/current
  capability state, so no preload/search/selected-label component mounts;
- returns before direct selection, `sampleOptions()`, `sampleLabel()`, or a
  selection refresh if the state becomes restricted; and
- proves that direct selection and forged schema-component lookup cannot query
  `content_items`, `content_groups`, or `authors`.

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
- Family changes retain their existing automatic refresh and sample reset. At
  this correction boundary, ordinary root fields still required explicit
  Refresh; the expanded approved closure below supersedes that boundary for
  finite rendered presentation Selects while leaving key/label identity-only.
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

## Expanded Card Template editor UX closure

The approved `STEP5B-CARD-UX-O1` closure keeps the rendered preview at the top
of its own responsive logical-end column while making the editor draft the
first section of the form column. Import-lock metadata now uses the page
subheading: a short localized label, the current lock badge, and a localized
`?` tooltip for the long explanation.

The preview shell now has a compact collapsible controls toolbar. The card
canvas remains mounted and visible when the toolbar is collapsed. Page-local
zoom uses grouped minus/reset/plus controls, 10% steps, a 50%–150% clamp, and
100% reset. It participates in the existing overflow plane and is not stored
or remembered.

Choose Sample is now an inline searchable Filament Select. It:

- preloads exactly 10 public-safe options and independently caps server search
  at 50;
- orders episodes with their own `image_path` or
  `external_thumbnail_url`, and podcasts with their own `cover_path`, before
  image-less records while keeping deterministic ordering within each tier;
- does not treat an inherited podcast cover as an episode's own image;
- keeps contributors unchanged, resolves the selected label without a
  redundant query when the current locked label is available, and retains
  three-family/transient/no-settings-persistence behavior; and
- is not constructed, rendered, queried, or accepted through direct/forged
  interactions in restricted state.

For preview rendering only, image-less episodes opt out of podcast-cover
inheritance and use the existing card missing-image fallback. Public cards and
item pages keep their current resolver behavior; no asset or default-image
setting was added.

Part editing now offers two modes:

- `inline` renders authoritative Builder state and keeps its 500ms live
  preview refresh; and
- `slide_over` uses the native cloned Builder action, opens from logical start
  at a bounded `3xl` width, uses one column on narrow and two at `lg`, and
  refreshes only after Apply.

The mode alone is remembered in browser `localStorage`. Invalid server input
normalizes to `slide_over`. No `AdminUxSettings` field, migration, setting,
backup/import ownership, or true per-user server preference was added. The
native overlay and focus trap leave the preview visible and geometrically
uncovered, but not interactive while the slide-over is open. Top-level and
nested Builders share these rules.

The finite presentation Selects `layout`, `density`, `image_size`, and
`title_size` now refresh automatically once per discrete change. `key` and
`label` remain identity-only and do not trigger preview work. Builder summaries
now use `Unlabelled` / `ללא תווית`, display the same localized source and
attribute option labels as the form, and retain escaped diagnostic raw values
for unknown legacy/corrupt tokens.

## UX2 position-canonical compatibility-modal closure

The approved `STEP5B-CARD-UX2-O1-COMPAT-MODAL` closure supersedes the focused
editor's transient zoom control with a centered 100/90/80/70/60 card-width
control. Width changes reflow the real card container without scaling its text,
images, or controls, and reset to 100% on reload. Sample, width, and icon-only
Refresh controls share one compact row; current/stale state uses short
localized copy and a short `Asia/Jerusalem` timestamp.

The focused top-level and nested Builders now:

- consume legacy explicit sibling `order` only to establish initial position,
  remove it from focused Builder state, and synthesize contiguous x10 order for
  preview/save after filtering non-array entries;
- display a localized position badge, separator, and translated type in a
  compact escaped heading, with consistently separated compact summaries;
- use native inline Builder collapse and the native owning-Builder extra-item
  action for an icon-only move-to-position modal;
- clamp the requested position on the server and use take-the-slot semantics
  while preserving UUID keys, nested boundaries, and one refresh callback; and
- group label/icon controls compactly, keep transient switches reachable, hide
  their subordinate fields conditionally, and preserve entered label/icon
  values while the corresponding switch is off.

The global `PublicFrontConfigValidator`, `PublicFrontCardTemplate`, import,
restore, backup, and lifecycle paths retain their explicit-order compatibility.
No production normalization was run or prescribed. O2 inline heading editing,
O3 global explicit-order cutover, and O4 path-aware invalid-field navigation
remain unimplemented.

## Requirement classification

| Requirement | Classification | Evidence |
| --- | --- | --- |
| Create/Edit use the current unsaved single draft | Implemented | Locked preview state is derived from `data`; Edit refresh coverage changes unsaved `title_size`; Create family-change coverage uses the unsaved draft. |
| Zero preview writer/settings-list/lifecycle/reference side effects | Implemented | Focused mocks/event assertions reject writer, scanner, configured Card Template settings, save events, and preview-local settings resolution. Existing capability hydration remains the authoritative security gate. |
| Shared Builder transport cleanup | Implemented | `CardTemplateDraftNormalizer` owns the former writer cleanup and is used by writer and previewer. |
| Exactly one normalized candidate; no saved fallback | Implemented | Invalid-draft tests reject malformed drafts and leave preview HTML empty. |
| Existing value object/renderer/presenters/public components | Implemented | All three families use `PublicFrontCardTemplate`, existing presenters, renderer, and card/part views. |
| Deterministic public-safe sample and bounded selector | Implemented | Family queries use existing public scopes/aggregates. Automatic sample selection keeps its prior deterministic order; the inline selector uses image-first tiers, exactly 10 preloaded options, and an independent 50-result server-search cap. |
| Eager-loaded/constant query plane | Implemented | Three identical family query runs are constant and lazy-loading prevention remains green. |
| Family-specific empty/error/restricted/loading/stale states | Implemented | HE/EN copy and focused component coverage exercise no-sample, invalid, restricted, current/stale, and modal response states. |
| Family, presentation-field, and template-part automatic refresh boundaries | Implemented | Family clears sample identity; `layout`, `density`, `image_size`, and `title_size` refresh once per discrete change; inline `data.parts...` uses the Builder's 500ms binding; accepted slide-over/structural mutations refresh after Apply. `key` and `label` do not trigger preview work. |
| Automatic refresh has no settings persistence/lifecycle effect | Implemented | Focused writer/scanner mocks and the `SettingsSaved` event fake remain untouched while direct and structural part updates refresh the preview. |
| Wide preview clears the sticky Filament topbar | Implemented | The aside uses a 5.5rem top offset and matching viewport-height bound; authenticated Chromium verifies its top edge remains at or below the topbar bottom after scrolling. |
| Cancel is translated and placed beside Save | Implemented | English `Cancel` and Hebrew `ביטול` keys are covered in the form-action order; Chromium verifies the settings URL action is in the form and absent from the header. Delete remains a header action. |
| Compact header metadata and draft-first editor column | Implemented | Import-lock label/badge/help are rendered in the Filament page subheading; the dedicated section is removed, and Chromium verifies the draft is the first editor section in both logical layouts. |
| Collapsible preview toolbar and transient bounded zoom | Implemented | The toolbar alone collapses; authenticated Chromium verifies the canvas remains visible, 10% controls clamp at 50%/150%, reset to 100%, stay inside the scroll plane, and reset on reload. |
| Inline sample Select, 10 preload, 50 search, and image-first order | Implemented | Focused tests independently assert both caps and two-query bounded planes, all-family search/label/selection, item/group own-image semantics, contributor continuity, transient reload, and no settings payload change. |
| Preview missing-image behavior | Implemented | Preview-only presenter/resolver input disables inherited podcast cover for image-less episodes; the existing `fallback` card treatment renders. Ordinary public resolver defaults remain unchanged and existing image suites are green. |
| Remembered Builder display mode | Implemented | Browser `localStorage` remembers only `inline`/`slide_over`; server validation normalizes forged values, no settings event fires, and reload acceptance restores inline mode without changing the settings payload. |
| Logical-start native Builder slide-over | Implemented | Top-level and nested edit actions use native logical-start `3xl` slide-overs with sticky chrome and responsive one/two-column schemas. Browser geometry proves the panel does not cover the logical-end preview; overlay/focus evidence distinguishes visible from non-interactive. |
| Native Apply-time versus inline live timing | Implemented | Browser evidence proves cloned slide-over edits do not alter preview before Apply and do so in one request after Apply; authoritative inline input refreshes live in one debounced request without opening a modal. No custom mounted-action bridge exists. |
| Localized Builder summaries and legacy diagnostics | Implemented | Formatter tests cover HE/EN no-prefix fallback, registry source/attribute labels, escaped unknown raw values, and nested/top-level preview continuity. |
| Responsive adjacent/slide-over single mount | Implemented | Authenticated Chromium verifies one active root at 1440 and 1024 CSS px and no duplicate root after both resize directions. |
| HE/EN, RTL/LTR, logical end, independent scroll | Implemented | Authenticated Chromium verifies Hebrew RTL and English LTR; CSS uses logical layout and both editor/preview scroll containers remain independent. |
| Keyboard, focus trap, Escape, resize restoration | Implemented | Native slide-over trap plus explicit heading/open-button focus targets are covered in Chromium. |
| Inert public interactions | Implemented | Feature and browser assertions find no public-card `href`, `wire:click`, buttons, or other public interactions in either preview mode. |
| Protected state and restricted selector boundary remain absent | Implemented | Restricted interaction coverage proves the inline Select schema has no components, forged direct/schema requests issue no item/group/author sample query, and the protected sentinel remains absent from HTML and serialized draft/control state. |
| One draft/no model graph/no added editor controls | Implemented | Preview-aware canary adds zero wrappers, editor controls, or wire-model paths; preview state contains compact scalars/presented HTML only. |
| Three-run component delta and frozen SP3C preservation | Implemented | Three identical unselected/selected/nested runs pass without changing the SP3C ceilings. |
| Real-browser DOM/network/listener/heap/timing evidence | Implemented with runner limitation | Chromium records DOM, roots, focusables, one refresh request, heap observation, and timings. The runner exposes Livewire component count but not listener enumeration; no listener value was fabricated. |
| Existing writer/public/settings regressions | Already existed and verified | Focused writer/public/SP3C suites and the full suite remain green. |
| Saved zoom/sample preferences, autosave, revisions, collaboration, synthetic/persisted samples | Deferred by specification | Zoom and sample remain page-transient; no server persistence or generalized preview architecture was added. |
| Server-persisted Builder display preference or live-in-slide-over bridge | Deferred by approved option | Only browser-local mode memory was added. `AdminUxSettings`, per-user persistence, and mounted-action draft synchronization remain excluded. |
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

The expanded UX closure preserves the existing synthetic Step 5B/SP3C
component ceilings. The actual inline content-group Select performs two
bounded queries for its 10-option preload and two bounded queries for a
50-result server search; current selected-label resolution performs zero
queries by reusing the locked current label. Restricted render/direct/forged
paths perform zero item/group/author selector queries. Presentation Selects
issue one Livewire preview refresh per discrete change, and inline Builder text
uses its existing 500ms debounce.

Final authenticated Chromium acceptance at 1440 × 900 passed five cases / 98
assertions. It observed one preview root, no page-level horizontal overflow,
logical-end preview placement in HE/RTL and EN/LTR, a scroll-contained 50%–150%
zoom plane, transient sample/zoom reload reset, a logical-start non-overlapping
Builder slide-over, a visible but overlay-blocked preview, focus inside the
slide-over, no preview update before Apply, one Apply request, and one debounced
inline update request. These remain browser observations rather than new
numeric production ceilings.

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
- Expanded editor UX closure:
  research notes 27–28; `CardTemplateEditorPage` and its shared Builder schema
  trait; the previewer, part-summary formatter, content-item presenter, and
  default-image resolver; the editor/preview, import-lock metadata,
  Builder-mode, and part-summary views; both admin locale files; focused
  editor/previewer and browser tests; this handoff; current state; and the
  mini-step ledger.

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
- Added expanded closure coverage for discrete presentation refresh versus
  identity-only staleness, browser-only Builder mode validation/memory,
  top-level/nested Builder preview switching and action configuration,
  one/two-column schemas, native Apply timing, inline live timing, localized
  summaries with escaped unknown diagnostics, own-image ordering, exact
  10-option preload versus 50-result search, query counts, preview-only missing
  image fallback, restricted empty-schema/direct/forged paths, and complete
  HE/EN browser geometry/zoom/select/overlay evidence.

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

### Expanded editor UX closure checks

- Stage 2 preflight confirmed clean `main` at
  `e31118f1c9f0fa5b2494d72fa0dc2097f6dc9d07`, all three prior Step 5B
  implementation commits, unchanged scoped source, and exact approval for
  `LS-20260718-STEP5B-CARD-TEMPLATE-UX-01` /
  `STEP5B-CARD-UX-O1`.
- Laravel Boost installed-version research covered custom Select preload/search/
  selected-label callbacks, Builder `blockPreviews()`/`editAction()`, native
  slide-overs, responsive columns, page subheadings, and Livewire/Alpine state.
  FilamentExamples completed the required direct and refined search/snippet
  passes; no full-source/detail endpoint was available.
- Research note 27 and implementation plan 28 were created, read back, and
  consulted before application code was changed.
- Initial combined editor/previewer focused run: 11 tests passed and five
  failed, with one reported render error. All failures were stale modal/
  explicit-refresh expectations after the intentional inline Select and
  presentation-refresh changes. Updated interaction tests then passed 22 tests
  / 192 assertions.
- Related SP3C, canary, public-image, and public-card regression run: passed 70
  tests / 1,091 assertions.
- Initial sandboxed browser run: all three then-existing cases failed at
  Chromium launch because macOS denied the rendezvous port. No application
  files changed for that environmental failure.
- Permitted external browser iteration exposed and corrected one Alpine
  expression, focus restoration across the nested preview Alpine scope, test
  timing around Livewire morphs, and the missing action-schema column override.
  Intermediate browser results included 1/5, 3/5, and focused 1/2 passes before
  the corrected focused zoom case passed 1 test / 22 assertions.
- Final focused browser file before closeout: passed 5 tests / 98 assertions.
- Combined focused implementation regression after browser completion passed
  91 of 92 tests / 1,290 assertions; the single failure documented the actual
  two-query preload plane rather than the provisional one-query expectation.
  The corrected exact query-count case passed 1 test / 6 assertions.
- A closeout-focused command named two nonexistent public regression files and
  stopped before running tests. The command was corrected to the repository's
  owned test filenames; no application state changed.
- Corrected consolidated closeout regression passed 62 tests / 1,010
  assertions across the editor, previewer, SP3C canary, public default-image,
  and public card-template suites.
- The first staged `git diff --cached --check` exposed Markdown hard-break
  trailing spaces in the two new research documents that the earlier
  untracked-file diff check could not inspect. Those six markers were removed;
  no content or application behavior changed.
- `vendor/bin/pint --dirty`: formatted the previewer only; no behavior changed.
- `git diff --check` and PHP syntax checks over all affected PHP/locale files:
  passed during iteration.

### Ordered final gate

Requirements sweep passed before the gate.

#### Restricted selector closure requirements sweep

- Implemented: restricted preview shells do not render or offer sample choice;
  the then-current action was disabled before schema mount. The expanded
  closure preserves this with an empty inline Select schema and direct guards.
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
- Deferred at that correction boundary: ordinary root fields remained
  explicit-refresh inputs. The later expanded closure now refreshes the four
  finite rendered presentation Selects, without generalized text-per-keystroke
  preview or identity-field refresh.
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

### Expanded editor UX closure final gate

The requirements sweep passed before this sequence:

- Implemented: preview stays first in its own logical-end responsive column;
  the draft is the first editor section; compact import-lock metadata is in the
  page header with localized badge/help; HE/RTL and EN/LTR geometry are covered.
- Implemented: only the compact toolbar collapses; the canvas stays visible;
  transient zoom uses 10% steps, 50%–150% bounds, 100% reset, and an intact
  browser scroll plane.
- Implemented: the inline searchable Select has exactly 10 initial options,
  independently capped 50-result search, item/group own-image-first order,
  unchanged contributor semantics, selected-label resolution, transient state,
  all-family operation, and no settings persistence.
- Implemented: image-less episode preview uses the existing missing-image
  fallback without inherited podcast cover; ordinary public image behavior is
  unchanged.
- Implemented: browser-local Builder mode memory; authoritative inline live
  refresh; native logical-start non-overlapping slide-over; visible but
  non-interactive overlay state; Apply-time refresh; responsive one/two-column
  top-level and nested schemas; no custom live action-state bridge.
- Implemented: finite rendered presentation fields auto-refresh; `key` and
  `label` remain non-rendered/identity-only; no unnecessary text-per-keystroke
  query path was added.
- Implemented: unlabelled summaries lose the Part prefix, source/attribute
  tokens use form option labels, and escaped unknown diagnostics remain visible.
- Preserved: protected values are absent from HTML/serialized state; restricted
  state neither renders nor queries the selector through preload/search/label/
  direct/forged paths; all preview interactions avoid writers, settings events,
  lifecycle, backup, cache, reference scans, and persistence.
- Preserved: Step 5B/SP3C canaries and public card/image regressions; no
  permission, migration, dependency, model/settings ownership, generalized
  preview platform, production/local-development-database action, or next
  roadmap selection.
- Documentation synchronized: research/plan, handoff, current state, ledger,
  and pending-decision queue all record the expanded closure and retain no
  automatically selected next step.

1. `vendor/bin/pint --test`: passed.
2. `vendor/bin/filacheck`: passed with 0 issues.
3. `npm run build`: passed with Vite 8.1.0.
4. `php artisan test`: passed as the full-suite final command using the
   permitted external runner for bundled Chromium: 768 tests / 9,668
   assertions in 356.261 seconds. The suite was not parallelized or
   interrupted.
5. After stamping the measured full-suite evidence above, the documentation
   change restarted the final gate: `vendor/bin/pint --test` passed.
6. Restarted `vendor/bin/filacheck`: passed with 0 issues.
7. Restarted `npm run build`: passed with Vite 8.1.0.
8. Restarted `php artisan test`: passed as the full-suite final command using
   the permitted external runner for bundled Chromium: 768 tests / 9,668
   assertions. The suite was not parallelized or interrupted.
9. After the staged Markdown whitespace correction, the ordered gate restarted
   again: `vendor/bin/pint --test` passed.
10. Second restarted `vendor/bin/filacheck`: passed with 0 issues.
11. Second restarted `npm run build`: passed with Vite 8.1.0.
12. Second restarted `php artisan test`: passed as the full-suite final command
    using the permitted external runner for bundled Chromium: 768 tests / 9,668
    assertions. The suite was not parallelized or interrupted.

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
  and therefore remain deferred while the slide-over is open. Applying that
  edit updates the authoritative Builder state and automatically refreshes the
  preview. Inline mode edits authoritative state live. No second preview draft
  or mounted-action synchronization bridge exists.
- `layout`, `density`, `image_size`, and `title_size` are finite Selects and now
  refresh once per discrete change. `key` and `label` remain identity-only and
  intentionally do not refresh preview.
- Builder display-mode memory is browser-local to that browser/profile, not a
  true user preference shared across browsers. Zoom/sample remain page-local.
- No live network, mail, development database, migration, dependency install,
  production action, push, or remote publication occurred.

## Local Front Check Report

1. Open Admin > Settings > Card Templates and edit a content-item template.
   Expect the template draft to be the first editor section and the preview to
   be the first surface in its own wide-screen column.
2. Inspect the page header. Expect a short localized family import-lock label,
   its current badge, and a `?` help control; hover or focus the control and
   expect the long localized import-only explanation.
3. Repeat at 1440 CSS pixels in English and Hebrew. Expect the preview at
   logical end: right in English and left in Hebrew, with no horizontal page
   overflow.
4. Collapse the preview controls. Expect the sample/width/refresh toolbar to
   collapse while the rendered card canvas remains visible.
5. Reopen the controls and select 60% card width. Expect the real card box to
   reflow to approximately 60%, stay centered, and retain unchanged text and
   image scale; restore 100% and expect full preview-plane width.
6. Reload the editor. Expect card width to return to 100%; it must not be
   remembered or written to settings.
7. Open the inline sample Select. Expect 10 initial public-safe options; search
   for a published episode, podcast, and contributor and expect server results
   capped at 50 with the selected label resolved.
8. Compare image-bearing and image-less episode/podcast results. Expect records
   with their own image first. Do not count a podcast cover inherited by an
   episode as that episode's own image.
9. Select an image-less episode whose podcast has a cover. Expect the existing
   missing-image placeholder in preview, not the inherited cover or a blank
   image area. Reload and expect the sample choice to reset transiently.
10. Change layout, density, image size, and title size. Expect one automatic
    preview refresh after each discrete Select change, without saving settings.
11. Change only template key or label. Expect the preview to become stale and
    remain unchanged until another rendered field changes or Refresh is used.
12. Choose inline Part editing mode. Edit custom text directly and expect the
    authoritative preview to refresh after the existing debounce without an
    Apply action.
13. Reload the page. Expect inline mode to be remembered in this browser only;
    inspect settings in another browser/profile if desired and expect no
    server-side user/default preference.
14. Choose slide-over Part editing mode and open a top-level and nested part.
    Expect each large panel to enter from logical start—left in English and
    right in Hebrew—without covering the preview column.
15. While the slide-over is open, expect the preview to remain visible behind
    the native overlay but not interactive, and expect keyboard focus to remain
    trapped inside the panel.
16. Change a slide-over field without applying it. Expect the preview not to
    change. Apply the edit and expect one refresh with the new value; cancel a
    later edit and expect the authoritative draft to remain unchanged.
17. Expand the Template settings and Template parts sections. Expect both to be
    open initially and independently collapsible without losing draft state.
18. Inspect Builder rows in English and Hebrew. Expect a position badge,
    translated type, escaped compact summary separators, and native inline
    collapse; legacy unknown tokens should remain escaped diagnostics.
19. Open a top-level row's icon-only move action. Enter a valid, zero, negative,
    and over-limit position in turn. Expect owning-sibling take-the-slot moves,
    server clamping, stable UUID identity, and no cross-parent movement.
20. Repeat the move action for a nested row, then drag a sibling to the same
    target position. Expect equivalent sibling order and contiguous x10 order
    only when the draft is previewed or saved.
21. Toggle Show label and Show icon off and on. Expect both switches to remain
    reachable, their subordinate fields to hide while off, entered label/icon
    values to remain, and position to return as inline-before when re-enabled.
22. Add, clone, reorder, and delete parts in both display modes. Expect nested
    state, validation, sample identity, and automatic refresh timing to remain
    correct.
23. Narrow below 1280 CSS pixels. Expect the adjacent preview to unmount and
    the Preview header action to open exactly one focus-trapped preview
    slide-over; Escape should restore focus to the trigger.
24. Edit a protected template without its current capability. Expect no sample
    Select, no sample preload/search, no protected part values in HTML or
    Livewire state, and unchanged Save protection.
25. Exercise preview width, sample, Refresh, and Builder mode controls, then
    verify no settings notification, backup, import-lock, reference-scan,
    cache-invalidation, or persisted settings change occurred.
26. In automated tests, exercise a valid explicit-order import and restore.
    Expect the unchanged global compatibility path to accept it. Do not run or
    prescribe production normalization for this closure.
27. Make an unsaved draft edit and use Back or close the tab. Expect the
    existing unsaved-changes warning to remain authoritative.

## Commit hash

`c75d0f2b2d476c58d12c16610ea97ba4088c5e79`

## Restricted selector closure commit hash

`69813dbd4002ed8e7c3e42e640f7d48085e275da`

## Template-parts auto-refresh correction commit hash

`cdf0e89789c0c987abc0f577ea74d6c8303afa4b`

## Expanded editor UX closure commit hash

`d889d4f6fca521616e148890502b038a113dff9c`

## UX2 position-canonical editor closure commit hash

`PENDING`
