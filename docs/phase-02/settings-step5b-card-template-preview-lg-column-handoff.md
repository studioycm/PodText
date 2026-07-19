# Step 5B Card Template Preview `lg` Column Handoff

## Status

- Mini-task: Mini 1 O1 only.
- Laravel Simplifier audit:
  `LS-20260719-STEP5B-CARD-RENDER-O1-LG-PREVIEW-SHELL-01`.
- Approved stable option:
  `STEP5B-CARD-RENDER-OVERHAUL-O1-LG-PREVIEW-SHELL`.
- Starting and observed implementation baseline:
  `d8f42da32eafe03c18862bbf7a08421b581ab0bb` on `main`, tracking
  `origin/main`, with a clean worktree.
- Implementation status: complete and verified; implementation commit pending.
- Push, publication, pull request, production action, migration, dependency,
  and local-development-database work: none.

## Contract and provenance

The operator approved the exact current Audit ID and Option ID in this task
after the read-only Stage 1 report. Stage 2 did not rely on commit `5fb3075` or
an earlier renderer/editor audit for authority.

The baseline commit reorganized admin navigation. Its changes to
`AdminNavigationOrder.php`, both admin translation files, and
`AdminPhase02ResourcesTest.php` are untouched. The final scoped diff contains
no navigation, group, sort, label, or translation change.

The required fresh option documents were created and fully consulted before
application code changed:

- `docs/research/settings-performance/33-step5b-card-template-renderer-overhaul-research.md`;
- `docs/research/settings-performance/34-step5b-card-template-renderer-overhaul-implementation-plan.md`.

Installed versions remained the source of truth: PHP 8.4, Laravel 13.19.0,
Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and Tailwind CSS 4.3.2. Laravel
Boost supplied installed-version information and responsive/Alpine/modal
guidance. FilamentExamples was searched in decomposed and refined passes; only
search/snippet access was exposed, not source/detail access.

## Outcome

The editor now has one synchronized viewport contract:

- below `lg` (`0` through `1023px`), the adjacent preview root is unmounted and
  the visible Preview header action opens the existing native Filament
  slide-over;
- at `lg` and above (`1024px+`), the action is hidden and exactly one adjacent
  preview root is mounted at logical end;
- both Alpine media-query uses now read `(min-width: 1024px)`;
- the action class is `lg:hidden`;
- the grid uses a measured `16rem`–`20rem` preview track from `lg`, then restores
  the established `20rem`–`26rem` track from `xl`.

The initial 1024px browser measurement with the old track left only 280px for
the editor. The option plan explicitly allowed a class-only `lg` refinement if
measurement proved the old track unusably compressed. The final track provides
376px to the editor and 320px to preview at 1024px, while restoring the old
416px preview track at 1280px.

When an open narrow preview crosses to `lg`, Alpine keeps `wide` false, awaits
the existing Livewire action unmount, rechecks the current media query, and only
then mounts the adjacent root. Focus from anywhere in the modal window returns
to the adjacent preview heading. Crossing below `lg` while focus is inside the
adjacent preview unmounts it and focuses the newly visible opener. A resize back
below `lg` while the unmount request is pending cannot mount a stale adjacent
root. The existing media-query listener is still removed in `destroy()`.

No responsive transition calls `refreshPreview()`, persists viewport state,
creates a second draft, or changes any renderer/sample/validation/order path.

## Requirement classification

| Requirement | Classification | Evidence |
| --- | --- | --- |
| Preview is a slide-over only below `lg` and adjacent at `lg+` | Implemented | Alpine, Tailwind, and action visibility all use the exact 1024px boundary. |
| Exact 767, 768, 1023, 1024, 1279, and 1280 behavior | Implemented | Authenticated Pest Chromium and signed-in Chrome record exact `innerWidth`, opener/adjacent/modal/root counts, geometry, and overflow at all six widths. |
| No duplicate preview root during modal-to-column transition | Implemented | Mutation-record instrumentation reconstructs the running root count; 1023→1024 and rapid 1024→1023 both recorded a peak of one. |
| HE RTL and EN LTR logical-end placement | Implemented | Hebrew slide-over/column are left/logical-end; English slide-over/column are right/logical-end. |
| Focus trap, Escape/Close restore, and both resize restorations | Implemented | Native modal Tab cycles stayed inside; Escape restored the opener; narrow→wide restored the heading; wide→narrow and rapid resize-back restored the opener. |
| No stable horizontal overflow and usable 1024px geometry | Implemented | Settled document overflow is false at every exact width; final 1024px editor/preview widths are 376px/320px with non-overlapping columns. |
| Unsaved draft and warning protection survive transitions | Implemented | The exact dirty label value survived the boundary and `beforeunload` remained prevented; signed-in Chrome repeated retention and restored the original value without Save. |
| Preview remains inert | Already existed and verified | All three narrow slide-overs and wide output contain zero public-card `href`, `wire:click`, or button interactions. |
| Inline and slide-over Builder modes remain functional | Already existed and verified | The unchanged full browser cases cover live inline refresh and Apply-time slide-over refresh. Signed-in Chrome toggled both modes, restored slide-over mode, retained one preview root, and kept modal focus away from preview. |
| Restricted selector, query/state budgets, and public compatibility | Already existed and verified | Focused editor/public suites and the final full suite retain existing restricted no-query, locked-state, public-renderer, and canary coverage. No budget changed. |
| Renderer part flow, CSS source, sample ranking, validation targeting, order compatibility, modal refresh, and copy | Deferred by O1 approval | No owned file for any of these behaviors changed. They remain in the inventory below. |
| Navigation order/groups/labels | Already existed and preserved | The baseline navigation files have an empty O1 diff. No admin translation file changed. |
| Migration, dependency, permission, settings lifecycle, persistence, or production action | Not applicable | None was needed or performed. |
| Blocked requirement | Not applicable | No approved O1 requirement is blocked. |

## Files changed

Application shell:

- `app/Filament/Pages/CardTemplateEditorPage.php`;
- `resources/views/filament/pages/card-template-editor.blade.php`.

Focused regression coverage:

- `tests/Feature/CardTemplateEditorPreviewTest.php`;
- `tests/Browser/CardTemplatePreviewBrowserTest.php`.

Research, plan, state, and handoff documentation:

- research 33 and implementation plan 34;
- this handoff;
- `docs/phase-02/current-project-state.md`;
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`;
- `docs/phase-02/settings-step5b-card-template-preview-handoff.md`.

No renderer, presenter, public card, preview partial, JavaScript module, CSS
source, translation, navigation, migration, dependency, settings, model,
authorization, query, or lifecycle file changed.

## Tests added or updated

`CardTemplateEditorPreviewTest` now asserts:

- exactly two 1024px media-query occurrences and no 1280px media query;
- the finite `lg` and restored `xl` grid tracks;
- the Preview action's `lg:hidden` class and retained native slide-over.

The existing responsive browser case now proves:

- the exact six-width matrix;
- one logical-end, non-overlapping adjacent root and hidden opener at `lg+`;
- no root while closed and one logical-end modal root at all three narrow widths;
- trapped focus, Escape restore, inert public output, and settled no-overflow;
- mutation-record peak root count during 1023→1024;
- one expected unmount request with no preview-refresh request;
- 1024→1023 focus restore and a rapid resize-back guard;
- exact dirty-value retention plus `beforeunload` protection; and
- a repeated boundary cycle without stale roots.

The English case now opens the 1023px LTR slide-over and verifies the 1024px
LTR adjacent shell. Existing transient control, Builder slide-over, inline
Builder, and native move cases remain in the same file.

## Automated browser evidence

Final Hebrew exact-width measurements:

| Width | Surface | Root / adjacent / modal | Editor / preview width | Stable overflow | Logical end |
| ---: | --- | --- | --- | --- | --- |
| 767 | closed opener; native slide-over when opened | closed `0/0/0`; open `1/0/1` | 735px / not mounted | false | true when open |
| 768 | closed opener; native slide-over when opened | closed `0/0/0`; open `1/0/1` | 720px / not mounted | false | true when open |
| 1023 | closed opener; native slide-over when opened | closed `0/0/0`; open `1/0/1` | 975px / not mounted | false | true when open |
| 1024 | adjacent | `1/1/0` | 376px / 320px | false | true |
| 1279 | adjacent | `1/1/0` | 631px / 320px | false | true |
| 1280 | adjacent with restored `xl` track | `1/1/0` | 536px / 416px | false | true |

At every narrow width, focus began in the modal, four Tab moves remained in the
native trap, Escape removed the modal root and restored the opener, and the
rendered public-card plane contained zero active interactions.

For an open 1023px modal crossing to 1024px:

- peak preview roots: 1;
- final roots/adjacent/modal: `1/1/0`;
- Livewire requests: 1 existing action-unmount request;
- focus: adjacent preview heading;
- dirty label: retained exactly;
- `beforeunload`: still prevented;
- stable horizontal overflow: false.

For 1024px back to 1023px, and for the rapid 1024→1023 resize while unmount was
pending, final roots were `0/0/0`, focus was on the visible opener, peak roots
remained one, and the dirty value remained exact.

Synthetic viewport cycling produced three instances of Chromium's exact
`ResizeObserver loop completed with undelivered notifications.` message from
the Filament body/sidebar observer. The test records that count, removes only
that exact known message from Pest's accumulator, and fails on every unexpected
message. It must not be described as a literal zero-message run.

## Signed-in Chrome and public-output evidence

The existing signed-in Hebrew editor at
`/admin/settings/card-templates/edit/content_item/default_content_item` was
reloaded after the Vite build and inspected at all six exact widths. Its counts,
geometry, directions, and widths matched the automated table.

- The 1023px native slide-over settled at logical end with one root, focus on
  the preview heading, four subsequent focus targets inside the modal, zero
  public interactions, and no stable document overflow. A probe taken before
  the 300ms entrance transition completed saw transient animation overflow;
  after 450ms both HTML and body scroll widths equaled 1023px.
- Escape removed the modal and restored the visible opener.
- Open-modal 1023→1024 produced one adjacent root, no modal root, heading focus,
  logical-end non-overlapping columns, 376px/320px widths, and no overflow.
- Focused-adjacent 1024→1023 removed the root and restored the opener.
- An unsaved label survived 1023→1024 exactly; the original Hebrew label was
  restored and Save was never used.
- Existing Builder slide-over mode opened at logical start and trapped focus.
  At constrained 1024px its existing 3xl overlay covered 96px of the preview
  plane, but the preview remained mounted and inert; O1 does not change Builder
  modal width or behavior. Inline mode was exercised, then the prior
  slide-over mode was restored without applying or saving a part edit.
- The public homepage rendered 14 articles, including eight content-item Card
  Template outputs, with RTL direction, no broken images, and no horizontal
  overflow.
- Editor and public Chrome diagnostic logs contained no errors or warnings.

The temporary responsive override was reset before browser control was
released. No browser save, settings notification, production action, or
database probe occurred.

## Measurement planes and limitations

- Pest Chromium measured hydrated/teleported browser DOM, CSS geometry, focus,
  exact viewport state, fetch-observed Livewire request count, mutation-record
  root peaks, optional `performance.memory`, and console error accumulation.
- Signed-in Chrome measured visible DOM/accessibility state, computed
  rectangles, focus, public output, and captured console diagnostics.
- Feature tests own SQLite fixtures and retain the server/component query,
  restricted, locked-state, and public compatibility planes.
- Browser request counts are not SQL query counts. DOM counts are not listener,
  heap-snapshot, TTFB, or network-waterfall measurements.
- Listener enumeration is not exposed by the installed runner; repeated
  boundary-cycle behavior was measured instead, and no listener count was
  fabricated.
- Transition completion still depends on the existing Livewire action request
  succeeding. That is the pre-existing modal network-failure domain, not a new
  renderer, query, or persistence risk.

## Independent review

Two independent read-only post-implementation reviews were completed without
tests, browser ownership, edits, staging, or commits.

1. Architecture/history/scope review: no actionable finding. It confirmed the
   exact synchronized boundary, awaited unmount/current-query sequencing,
   conditional focus restoration, permitted measured track refinement, and
   empty renderer/navigation/translation/dependency/migration/security diffs.
2. Tests/performance/security review: no high- or medium-severity finding. Its
   one low test-hardening finding noted that a current-count MutationObserver
   could miss a same-delivery add/remove. The test was changed to reconstruct
   the running count from every mutation record and the focused case returned
   green with a peak of one. The review also required honest classification of
   the exact ResizeObserver artifact above.

## Complete seven-option program inventory

Only O1 is implemented in this run:

1. `STEP5B-CARD-RENDER-OVERHAUL-O1-LG-PREVIEW-SHELL` — implemented now.
2. `STEP5B-CARD-RENDER-OVERHAUL-O2-ORDERED-FLOW-FOUNDATION` — deferred; select
   geometry after visible parts, reconcile ordered/media/body flow, close only
   bounded group/link geometry, then add missing renderer Tailwind source.
3. `STEP5B-CARD-UX2-FU02-SAMPLE-RANKING-PARITY` — deferred; align automatic,
   preload, and search effective-image ranking including configured defaults.
4. `STEP5B-CARD-UX2-FU03-PATH-CORRECTED-CLOSURE` — deferred; target actual
   UUID-keyed top-level/nested invalid fields. This is an internal bug, not a
   GitHub issue.
5. `STEP5B-CARD-UX2-FU04-ORDER-COMPAT-CLOSURE` — deferred; pin shared
   effective-order semantics without a global cutover.
6. `STEP5B-CARD-UX2-FU05-INTERACTION-CLOSURE` — deferred; close duplicate
   refresh calls and native move-modal interaction evidence.
7. `STEP5B-CARD-UX2-FU06-COPY-CLEANUP` — deferred; correct live-renderer helper
   copy and remove only proven-dead order copy.

Every later option requires a fresh clean-HEAD Simplifier audit and direct
operator approval in its own task.

## Full deferred renderer and editor inventory

O1 does not change any of these known findings:

1. Item/group presenters and views retain their `parts`, `media_parts`, and
   `body_parts` splits.
2. FU01 retains ordered top-level stacked rendering for interleaved images and
   established leading-image geometry.
3. Geometry is still chosen before absent/filtered output is fully known.
4. Content-group row geometry retains its adjacent link-contract gap.
5. Renderer-emitted finite row classes still lack public/admin Tailwind source
   coverage; O2 must first fix content-aware geometry.
6. Automatic, ten-option preload, and capped search sample ranking are not yet
   fully aligned.
7. Structured validation paths are still flattened onto `data.key` instead of
   current UUID-keyed Builder paths.
8. `updatedInteractsWithSchemas()` and the owning Builder
   `afterStateUpdated()` retain duplicate `refreshPreview()` hooks.
9. Focused hydration still mirrors effective-order fallback logic inline.
10. Nested `part_group` images remain allowed by Builder but omitted by the
    presenters' body-only child filter; enabling them remains excluded.
11. Existing family/global missing/default-image settings remain the approved
    source for preview image availability; no contributor image field exists.
12. Wrapper padding and row-gap limitations remain part of O2's geometry work.
13. Old HE/EN helper copy still describes future renderers; FU06 owns cleanup.

## Program-wide exclusions retained

- legacy UX2 O2 inline-header editing;
- UX2 O3 global explicit-order cutover;
- production normalization or production actions;
- nested image enablement or nested-media redesign;
- contributor image-field invention;
- persistence for preview width, sample, or Builder mode;
- migrations, dependencies, permission redesign, settings lifecycle changes,
  generalized renderer platform work, or another roadmap step; and
- branch/worktree creation, push, publication, or pull request work.

## Commands and results

### Preflight, instructions, and research

- `pwd`, `git rev-parse --show-toplevel`, branch/HEAD/upstream checks,
  `git status --short --branch`, recent `git log`, and scoped baseline diffs:
  passed; cwd/Git root were `/Users/studioycm/Herd/PodText`, branch `main`, HEAD
  `d8f42da`, and worktree clean. No baseline drift was found.
- Full required repository docs, newest handoffs, renderer/editor research,
  relevant source/tests/history, all selected skill packages, and required
  references were read. No conflict or stop condition was found.
- Laravel Boost installed-version/doc searches and the required multi-pass
  FilamentExamples protocol completed. FilamentExamples remained
  search/snippet-only.
- Research 33 and plan 34 were created, read back in bounded chunks, and
  `git diff --check` passed before code.

### Focused implementation and iteration

- PHP syntax checks over the changed PHP/Pest files: passed on every run.
- `vendor/bin/pint --dirty --format agent`: passed on every iteration.
- First focused `CardTemplateEditorPreviewTest`: 14 tests / 250 assertions
  passed. After final grid expectations: 14 / 251 passed.
- First sandboxed responsive browser command: failed before assertions because
  Chromium was denied its macOS Mach-port rendezvous bootstrap. No app change
  was made for this infrastructure failure.
- Identical permitted retry before rebuilding assets: reached the app and
  failed 11 assertions because the new `lg` class was absent from the stale
  Vite bundle.
- Iteration `npm run build`: passed with Vite 8.1.0.
- Next responsive browser run completed 133 functional assertions but the final
  no-JavaScript-errors assertion reported two exact ResizeObserver transition
  messages. Unexpected-message enforcement and exact artifact classification
  were added; the retry passed 1 test / 135 assertions.
- Focused English LTR browser case: passed 1 / 26.
- The measured 280px editor width activated the approved finite `lg` track
  refinement. `npm run build` passed; feature retry passed 14 / 251; final HE
  responsive case passed 1 / 147; final EN case passed 1 / 26.
- `CardTemplatePreviewerTest` plus `PublicFrontCardTemplateBuilderTest`: passed
  40 / 394.
- `vendor/bin/filacheck --dirty`: passed with 0 issues.
- Full `CardTemplatePreviewBrowserTest`: passed 6 / 261.
- Reviewer-requested mutation-record instrumentation: Pint and PHP syntax
  passed; focused HE responsive case passed 1 / 147 with both peak counts one.

### Final ordered gate

The final documented-tree requirements sweep and canonical gate results are
recorded here before the implementation commit:

1. Requirements sweep: passed. `git diff --check`, changed-file inspection,
   exact responsive-marker inspection, full seven-option/deferred-inventory
   inspection, and scoped baseline diffs confirmed the approved O1 only. The
   navigation order, navigation tests, both admin translations, renderer,
   preview partial, public-card components, sample/query services, validator,
   order compatibility, settings lifecycle, migrations, and dependencies have
   an empty O1 diff.
2. `vendor/bin/pint --test`: passed.
3. `vendor/bin/filacheck`: passed with 0 issues.
4. `npm run build`: passed with Vite 8.1.0.
5. Full serial `php artisan test` last: the sandboxed command reached 763
   passing tests, then Chromium failed to register its macOS Mach-port
   rendezvous with `bootstrap_check_in ... Permission denied`; 14 browser tests
   were reported failed/risky after the browser closed. No app change was made.
   The identical command was immediately retried with the permitted runner and
   passed 777 tests / 9,953 assertions in 365.829 seconds. Neither run was
   parallelized or interrupted.

After this measured result section was written, the required final documented-
tree restart repeated `vendor/bin/pint --test`, `vendor/bin/filacheck`,
`npm run build`, and the full serial permitted `php artisan test` last. The
repeat passed Pint, FilaCheck with 0 issues, Vite 8.1.0, and 777 tests / 9,953
assertions. This statement was verified before the implementation commit; any
different result would require a handoff correction and another restart.

## Numbered Local Front Check

1. Open Admin > System Management > Card Templates and edit a content-item
   template. Do not save during this check.
2. Set the CSS viewport to 767px, 768px, and 1023px in turn. Expect a visible
   Preview action, no adjacent preview, and no horizontal document overflow.
3. Open Preview at each narrow width. Expect one logical-end native slide-over,
   one preview root, no adjacent root, an inert rendered card, and focus inside
   the modal.
4. Press Tab repeatedly, then press Escape. Expect focus to remain trapped while
   open and return to the visible Preview action after close.
5. At 1023px, open Preview and focus its heading, then resize to 1024px. Expect
   the slide-over to disappear before one adjacent preview appears, with focus
   on the adjacent heading and no duplicate heading/root.
6. Focus the adjacent heading at 1024px, then resize to 1023px. Expect no preview
   root until reopened and focus on the visible Preview action.
7. Set 1024px, 1279px, and 1280px in turn. Expect one adjacent logical-end
   preview, a hidden opener, non-overlapping columns, and no horizontal overflow.
   At 1024px expect approximately 376px editor and 320px preview widths; at
   1280px expect the wider established preview track.
8. Repeat steps 2–7 in Hebrew and English. Expect preview at left/logical-end in
   Hebrew RTL and right/logical-end in English LTR.
9. Change the template label without saving, cross 1023→1024→1023, and expect
   the exact draft value to remain. Attempt to leave and expect the existing
   unsaved-changes warning; cancel navigation and restore the label.
10. Open a Builder part in slide-over mode. Expect the unchanged logical-start
    native panel, trapped focus, one preview root, and no interactive preview
    content behind the overlay. Close without Apply.
11. Select inline Builder mode, inspect one part, then restore the original
    Builder mode. Expect no settings save and one preview root throughout.
12. Open the public homepage. Expect normal interactive public content-item
    cards, no broken images, no horizontal overflow, and no renderer-order or
    geometry change attributable to O1.
13. Repeat with a restricted editor account. Expect no preview sample selector,
    no protected state exposure, and unchanged Save authorization.
14. Inspect browser console and network activity. Expect no unexpected errors;
    resizing a closed shell must not issue a Livewire request, while resizing an
    open preview to wide may issue only the existing action-unmount request.

## Assumptions and current state

- The existing native Filament modal owns focus trapping and close behavior.
- The existing preview result remains the only rendered draft; O1 adds no
  authoritative state in Alpine.
- The local development database was not queried or changed. Automated tests
  owned all fixtures.
- No live HTTP or mail occurred in tests.
- No later option is selected automatically.

## Implementation commit hash

`pending`
