# Step 5B Card Template Ordered Flow Foundation Handoff

## Status

Implementation, canonical final gate, implementation commit
`f56ef36983a8813a1ebe18b1087864c626a4c8f5`, and docs-only hash-stamp commit
`27f38aeaebc8ab2ff4279abd2a905efdce82b495` are complete.

- Audit: `LS-20260719-STEP5B-CARD-RENDER-O2-ORDERED-FLOW-01`
- Option: `STEP5B-CARD-RENDER-OVERHAUL-O2-ORDERED-FLOW-FOUNDATION`
- Starting HEAD: `14285b34ea1a4b19471a96cc1f5e07990fd9633a`
- Branch: `main`
- Push: forbidden and not requested

## Contract and provenance

The operator directly approved the current Audit ID and exact Option ID in this
task. Research 35 and plan 36 define the implementation contract. O1's 1024px
shell, focus sequencing, single-root behavior, preview safety, and regression
coverage remain binding. The `d8f42da` navigation order, grouping, labels, and
tests remain outside this option.

## Outcome

The actual presenter-produced part sequence is now authoritative per item and
group card. Base presentation retains only static tokens. After record/display
gates remove blank or unavailable parts, the renderer derives the effective
flow, geometry, exact diagnostics, media/body projections, and ordered runs
from that one final sequence.

- Exactly one leading media part plus body may retain configured row geometry.
- Body-only, media-only, interleaved, and repeated-media results use card
  geometry without an invented media track.
- Every top-level repeated image renders exactly once in configured order.
  Each ordered media occurrence is a full-bleed run; contiguous body parts
  share one density-padded run.
- Group row geometry belongs to the article. No whole-card anchor is added;
  existing image, title, taxonomy, and action links remain independent.
- Configured `data-card-template-*` attributes remain unchanged while
  `data-result-layout`, `data-card-part-flow`, and
  `data-card-renderer-parts` report actual output, including repeated types.
- The public and admin themes now scan only
  `app/Support/PublicFront/Cards/**/*`, compiling the existing finite row
  utilities after the no-media correction was green.

## Requirement classification

| Requirement | Classification | Result |
|---|---|---|
| Actual filtered order is authoritative | Implemented | Item/group presenters finalize after producing actual parts. |
| Select geometry only after actual visibility is known | Implemented | Per-card pure finalization derives five finite flows. |
| Retain leading row only for real leading image plus body | Implemented | `media-leading` alone may keep rows; large-image/card configuration still wins. |
| Interleaved image uses ordered full-bleed flow | Implemented | Media/body runs preserve exact order and ordered media sits outside padded body wrappers. |
| No-image rows have no phantom column/gap/outer padding | Implemented | `body-only` reports effective cards and browser style is flex, `gap: normal`, `padding: 0px`, no grid. |
| Reconcile parts/projections/flags/diagnostics | Implemented | `parts`, `media_parts`, `body_parts`, `part_runs`, flow/layout flags, and actual diagnostics share one source. |
| Pin multiple-image behavior | Implemented | All top-level occurrences render once; two or more media parts always select `ordered-stack`. |
| Correct group row/link contract | Implemented | Geometry moved to article; unused `link` key and whole-card-link contract removed. |
| Add missing Tailwind sources after server fix | Implemented | Server tests were green before the two narrow source declarations were added. |
| Preview/public item/group parity | Implemented | Shared presenters/components; browser and feature coverage exercise both surfaces/families. |
| Leading/interleaved/missing/default, rows/cards, HE/EN, narrow/wide | Implemented | Feature plus signed-in Chromium matrix covers the approved cases and widths. |
| O1 1024px shell/focus/single-root preservation | Already existed; verified | Full browser file retains O1 focus, dirty state, one-root and transition assertions. |
| Public visibility, preview inertness, escaping, query safety | Already existed; verified | No query/scope/security path changed; focused and browser regressions are green. |
| `d8f42da` navigation order/groups/labels/tests | Already existed; verified | Navigation and translation files were untouched; navigation regression passed. |
| Nested images and contributor redesign | Deferred | Nested images remain omitted; contributor renderer is untouched. |
| Migrations, dependencies, permissions, lifecycle, production | Not applicable | None were required or changed. |
| Blocked requirements | Blocked | None. |

## Files changed

- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRenderer.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContentGroupCardPresenter.php`
- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/css/filament/admin/theme.css`
- `resources/css/filament/public/theme.css`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `tests/Feature/CardTemplatePreviewerTest.php`
- `tests/Feature/PublicDefaultImagesSettingsTest.php`
- `tests/Feature/PublicLatestSearchUxTest.php`
- `tests/Browser/CardTemplatePreviewBrowserTest.php`
- research 35, plan 36, this handoff, current project state, the mini-step
  ledger, and the cumulative Step 5B preview handoff.

## Tests added or updated

- Renderer/presenter finalization matrix for empty, body-only, media-only,
  media-leading, ordered, repeated, and globally hidden image cases.
- Public item/group no-media rows with nested-image omission and exact actual
  diagnostics.
- Per-record heterogeneous item finalization and exact-once repeated top-level
  item/group image output.
- Preview body-only and repeated-image behavior, inertness, and query-neutral
  variants without lazy loading.
- Existing default/fallback item/group tests now prove those visible image
  payloads classify as media-leading.
- The existing latest-layout regression now expects actual renderer diagnostics
  rather than configured but hidden transcriber/taxonomy types.
- Browser fixtures render item/group leading, body-only, ordered/multiple, and
  card-mode templates with loaded public default images and preview fallback.

## Browser evidence

Fixture-backed Chromium ran with one browser owner. The initial sandboxed launch
failed at macOS `bootstrap_check_in ... Permission denied (1100)`; the identical
command was retried with the permitted runner as required.

Public item and group matrix, in both Hebrew/RTL and English/LTR:

| Width | Leading row columns | Body-only article | Ordered/repeated media | Overflow |
|---:|---:|---|---|---|
| 767 | 1 | flex; no grid; normal gap; 0px outer padding | 2 exact full-bleed images | none |
| 768 | 2 | same full-width body contract | same exact ordered flow | none |
| 1024 | 2 | same full-width body contract | same exact ordered flow | none |
| 1280 | 2 | same full-width body contract | same exact ordered flow | none |

At every width and direction, item/group leading cards reported
`media-leading`, loaded the test-owned `content_item_default` /
`content_group_default` fixture, body-only cards contained zero image parts,
ordered cards reported `custom_text,image,title,image`, card-mode templates
computed as flex/cards, no nested anchors existed, and public cards retained
three valid per-part links.

Authenticated preview covered item and group in HE/RTL and EN/LTR at 767, 768,
1024, and 1280px. Leading, body-only, ordered/repeated, and configured card
variants each retained one preview root. The 767px leading row computed one
column; 768px and wider computed two. Body-only variants had no grid, row gap,
outer padding, or image. Ordered variants reported
`custom_text,image,title,image`, rendered two full-bleed fallback occurrences,
and remained inert. At 767/768 the preview was one native modal root; at
1024/1280 it was one adjacent root. All variants had no horizontal overflow or
unexpected JavaScript errors.

The isolated complete O1+O2 browser file passed 10 tests / 1,689 assertions.
O1 retained
767/768/1023 native slide-over focus and Escape restoration; 1024/1279/1280
adjacent logical-end geometry; peak one root during slow and rapid transitions;
dirty-value and `beforeunload` preservation; one Livewire transition request;
and no settled overflow. Known ResizeObserver delivery messages were counted
and filtered exactly as in O1; unexpected browser errors were empty.

## Measurement planes and limitations

- Server/component plane: the settled focused set passed 102 tests / 1,705
  assertions. Item and group leading, body-only, media-only, hidden-image, and
  ordered preview variants each had an explicit zero-query delta from their
  same-family baseline and passed with lazy loading prevention enabled.
  Restricted selectors and the frozen SP3C canaries passed unchanged.
- Browser plane: DOM order, computed display/grid/gap/padding, element
  rectangles, loaded image dimensions, links, focus, root counts, overflow,
  requests, smoke, and JavaScript errors were observed in Chromium.
- Compiled-theme plane: Vite built both panel themes after the narrow sources
  were added. Generated assets remain ignored and were not committed.
- Listener enumeration remains unavailable in the installed runner. No proxy
  listener count or browser TTFB/heap conclusion was fabricated.
- The browser default-image fixtures copy the tracked logo into the public test
  disk and delete both files in `afterEach`; no live HTTP is used.

## Independent review

- Architecture/compatibility/security review: no findings. It confirmed pure
  per-record finalization, correct run construction, actual diagnostics, group
  link safety, nested-media exclusion, finite theme scanning, O1 single-root
  preservation, and untouched navigation/translations.
- Test/performance/browser review: its initial findings were all resolved. The
  cumulative O1 handoff now records O2; item/group render paths directly cover
  consecutive images, media-only, globally hidden media, and heterogeneous
  result sets; preview query tests cover both families with explicit zero
  deltas; and authenticated browser coverage spans HE/EN at
  767/768/1024/1280. The independent follow-up found no remaining bounded
  code/test coverage issue.

## Deferred inventory

- legacy UX2 O2 inline-header editing;
- UX2 O3 global explicit-order cutover;
- production normalization and all production actions;
- nested `part_group` images and nested-media redesign;
- contributor image-field invention or renderer redesign;
- persistence for preview width, sample, or Builder display mode;
- FU02 sample-ranking parity;
- FU03/O4 validation-path correction;
- FU04 order-compatibility closure;
- FU05 duplicate-refresh/move-modal interaction closure;
- FU06 copy cleanup;
- migrations, dependencies, permission redesign, lifecycle changes, generalized
  renderer-platform work, and every other roadmap step.

## Commands and results

### Preflight and research

- `git status --short --branch` — clean; `main` ahead of `origin/main` by two.
- `git rev-parse HEAD` — exact expected O1 closeout `14285b34...`.
- Laravel Boost `application_info` — Laravel 13.19.0, Filament 5.6.7,
  Livewire 4.3.3, Pest 4.7.4, Tailwind CSS 4.3.2.
- Laravel Boost `search_docs` — confirmed Tailwind/Filament custom-theme
  `@source` requirements and Pest/Livewire browser guidance.
- FilamentExamples two-pass `search_examples` — search/snippet access only;
  custom themes and custom page Blade patterns reviewed; no source/detail tool
  was exposed.

### Focused implementation and iteration

- First pre-Tailwind renderer run,
  `php artisan test --compact tests/Feature/PublicFrontCardTemplateBuilderTest.php`:
  failed 1 of 33 after 32 passed / 382 assertions because one older test still
  expected deduplicated renderer types. The assertion was corrected to the
  approved exact repeated sequence; retry passed 33 / 408.
- Pre-Tailwind preview/editor run: passed 25 tests / 307 assertions. Only after
  these server geometry checks were green were the theme sources edited.
- First post-source `npm run build`: passed with Vite 8.1.0.
- Added preview/body-only/repeated coverage: previewer passed 13 / 67.
- Default/fallback media-flow coverage: passed 6 / 104.
- Nested-image/body-only renderer retry: passed 33 / 410; global-hidden image
  addition retry passed 33 / 412.
- Query-neutral preview additions: passed 14 / 68. Editor preview regression
  passed 14 / 251.
- Frozen canary plus navigation regression: passed 33 / 841.
- Iteration `vendor/bin/pint --test`: passed.
- Iteration `vendor/bin/filacheck --dirty`: passed with 0 issues.
- Browser fixture iteration first failed all four new datasets before browser
  assertions because the fixture used nonexistent `position` instead of
  `sort_order`; the test-only fixture was corrected.
- The next sandboxed browser run hit the known Chromium bootstrap denial. The
  identical permitted retry reached the app and failed the new assertions:
  custom homepage templates were hidden by stale scoped test services and the
  interaction selector counted preview controls (0 passed / 28 assertions).
- Permitted diagnostic iterations then exposed, in sequence: default-template
  fixture cache (two failures / 10 assertions twice), stale scoped resolver
  state (two failures / 2 assertions twice), default-image fixture fallback
  from an invalid/unsupported temporary path (two failures / 24, 28, then four
  runs of 8 assertions), and the already-classified ResizeObserver message
  after 190 assertions. Test-only cache reset, path, selector, and error
  classification were corrected; no application workaround was added.
- Settled public O2 browser run passed 2 / 356; after adding loaded-image
  fixtures and checks it passed 2 / 372. Authenticated preview O2 passed 2 / 56.
- Expanded authenticated preview matrix passed 2 / 1,056.
- Added direct media-only/hidden/heterogeneous/consecutive-image renderer
  coverage passed 35 / 440; expanded item/group zero-query preview coverage
  passed 14 / 69.
- A runner-control mistake launched three full browser sessions before the
  preceding PTY sessions had fully exited, contrary to the serial-suite rule.
  Those overlapping sessions interfered with their shared test-owned image
  fixtures/focus timing and failed 9 of 10 at 1,482, 1,520, and 1,520
  assertions. All sessions were allowed to finish; no application change was
  made in response. The one subsequent isolated full browser run passed
  10 / 1,689.
- Settled focused PHP regression passed 102 / 1,705. Although it was started
  during the runner-control mistake above, it completed green; the final gate
  remains strictly serial.
- First canonical full suite attempt failed 1 of 790 after 789 passed / 11,493
  assertions. `PublicLatestSearchUxTest` still expected configured but hidden
  `transcriber_line` and `taxonomy` types in `data-card-renderer-parts`; the
  rendered value correctly contained only the six actual visible parts. The
  narrow expectation was updated and its file passed 8 / 69. The canonical
  gate restarts from Pint after this test and documentation change.

### Final ordered gate

First final-tree attempt:

1. Requirements sweep — passed: `git diff --check` was clean; the changed-file
   inventory matched O2; O1 editor shell files and both admin translation files
   had no diff from `14285b34...`.
2. `vendor/bin/pint --test` — passed.
3. `vendor/bin/filacheck` — passed with 0 issues.
4. `npm run build` — passed with Vite 8.1.0; both panel theme assets compiled.
5. `php artisan test` — failed 1 of 790 after 789 passed / 11,493 assertions
   on the stale `PublicLatestSearchUxTest` diagnostic expectation described
   above.

Restart after the narrow test/documentation correction:

1. Requirements sweep — passed with the same scope and preservation checks.
2. `vendor/bin/pint --test` — passed.
3. `vendor/bin/filacheck` — passed with 0 issues.
4. `npm run build` — passed with Vite 8.1.0.
5. `php artisan test` — passed 790 tests / 11,500 assertions in 409.041s.

Documentation-record restart, required because the green result above was
added to this handoff: the same requirements sweep, Pint, FilaCheck, Vite build,
and full serial suite were run in the same order and passed; the full suite
again passed 790 tests / 11,500 assertions. No file changed afterward before
the implementation commit.

Post-hash-stamp restart, required because the implementation hash below and
the ledger stamp changed after the green gate: the same requirements sweep,
Pint, FilaCheck, Vite build, and full serial suite were run in the same order
and passed; the full suite again passed 790 tests / 11,500 assertions. No file
changed afterward.

## Numbered Local Front Check

1. Open the Card Template editor for an item row template at 1280px in Hebrew.
   Expect one adjacent preview at logical end and one card root.
2. Move the image after a visible text part. Expect the preview to switch to
   ordered stacked flow and keep text, image, and title in exact logical order.
3. Add a second top-level image after the title. Expect two full-width image
   occurrences, each rendered once, with the body content between them.
4. Hide or remove every top-level image from a row template. Expect a full-width
   body card with no blank media column, row gap, or outer row padding.
5. Restore one image as the first part with a visible title after it. At 767px
   expect one stacked column; at 768px and wider expect two row columns.
6. Repeat steps 2–5 for a content-group template. Expect image/title/action
   links to remain individually clickable and no whole-card link wrapper.
7. Switch between Hebrew and English. Expect the same logical DOM order, RTL/LTR
   direction, full-bleed media, and no horizontal overflow.
8. Choose an image-less preview sample. Expect the established fallback
   placeholder to count as visible media; public saved output may instead use
   its configured default or inherited group image.
9. Compare configured row diagnostics to a body-only result. Expect configured
   layout `rows`, effective layout `cards`, flow `body-only`, and exact actual
   renderer types.
10. At 1023px open Preview, tab within it, and press Escape. Expect focus to
    return to the trigger. Resize to 1024px and back; expect no duplicate root
    and retention of an unsaved value.
11. Inspect browser console and network while repeating the checks. Expect no
    unexpected JavaScript errors, smoke failures, or live external image/API
    calls from the test-owned cases.
12. Open the admin sidebar. Expect the existing `d8f42da` group order and labels
    unchanged.

## Assumptions and current state

- Preview missing-image fallback and public default/group-cover resolution remain
  intentionally distinct; parity means shared flow/geometry for each payload.
- Top-level repeated image blocks remain compatible and render exactly once each.
- Nested images remain omitted.
- No local development database probe is permitted.

## Implementation commit hash

`f56ef36983a8813a1ebe18b1087864c626a4c8f5`
