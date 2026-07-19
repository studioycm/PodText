# Step 5B card-template strict image-order follow-up research

Date: 2026-07-19
Audit: `LS-20260719-STEP5B-CARD-UX2-FOLLOWUP-01`
Approved option: `STEP5B-CARD-UX2-FU01-STRICT-IMAGE-ORDER`

## Scope and approval boundary

This is the new follow-up research contract for FU01. It does not rewrite the
completed UX2 O1 research in document 29 and does not reopen that historical
implementation.

FU01 has one observable outcome: content-item and content-group cards must honor
the configured top-level part sequence when an image is placed after another
part. Existing row geometry remains available only when the configured image is
the leading part; an interleaved image uses one ordered stacked flow.

The approval does not authorize FU02 sample ranking, FU03/O4 path-corrected
validation closure, a production normalization, another roadmap step, a branch
or worktree, a push, or any production action.

## Verified baseline and provenance

- Checkout: `/Users/studioycm/Herd/PodText`
- Git root: `/Users/studioycm/Herd/PodText`
- Branch state at preflight: `main...origin/main [ahead 44]`
- Worktree at preflight: clean
- FU01 baseline HEAD: `4999b960188ebc1b563f135c8ce07d745a969242`
- UX2 implementation: `82e639d0fd22c06c52a70acec7c26ee9e2d8c72a`
- UX2 hash stamp: `4999b960188ebc1b563f135c8ce07d745a969242`
- The authoritative Herd checkout had not changed before FU01 started.

## Historical answer: when media-first rendering began

This behavior was not introduced by the last one or two UX revisions.

- Before configurable part rendering, the July 4 Builder-foundation card view
  rendered the image as a hard-coded block before the body.
- Commit `e3c81dec2420a23f5b5078245099da8c41395d6c` on 2026-07-07
  introduced the dynamic content-item presenter. From its first revision it
  retained ordered `parts`, split them into `media_parts` and `body_parts`, and
  rendered the complete media loop before the body loop.
- Commit `f7127914` on 2026-07-07 introduced the content-group equivalent with
  the same split and media-first view order.
- `git blame` still attributes the item split and loops to `e3c81de` and the
  group split and loops to `f712791`; later Step 5B preview and UX2 commits did
  not introduce this behavior.

The most plausible explanation for a recent observation of the image moving is
that the Builder item itself moved and renumbered, while the item/group card
view continued to hoist the image into its media loop. Contributor cards are a
separate family and already render their parts through one ordered body stream.
This explanation is an inference from the Git history; it does not claim what
was visible in the operator's past browser session.

## Current root cause

`PublicContentItemCardPresenter` and `PublicContentGroupCardPresenter` already
produce the correctly sorted top-level `parts` array. They then expose filtered
`media_parts` and `body_parts` arrays for the current views. Both views render:

1. every media part; then
2. one body wrapper containing every body part.

An image ordered third or ninth therefore still appears before all body parts.
The editor, validator, and value object can all hold the requested order while
the final item/group Blade structure discards that cross-region position.

The contributor presenter/view is not affected and must remain unchanged.

## FU01 rendering contract

### Configured sequence is authoritative

The renderer uses the already validated and sorted top-level part sequence. It
must not reinterpret raw input, modify order values, or change validator,
import, restore, backup, or focused-editor normalization behavior.

For content-item and content-group templates:

- a single leading image keeps the existing media/body rendering path;
- an image after any configured top-level part activates ordered stacked flow;
- a second image after a leading image also activates ordered stacked flow;
- hidden images and templates with no image retain current behavior;
- the interleaved path renders the presenter's existing `parts` array once, in
  order, without a second media-first pass;
- configured `rows` remains visible in the compatibility attribute, while the
  effective `data-result-layout` becomes `cards` for the stacked path;
- existing preview-disabled link behavior, image source/fallback behavior,
  image fit/radius, labels, icons, and part diagnostics remain intact.

The existing nested `part_group` presenter filters children to body parts. The
FU01 audit identified the top-level media/body split, not nested-image enablement;
changing which nested blocks render would be a separate public-rendering
behavior change and is retained for the post-FU01 audit inventory below.

### Geometry rule

The old row/card geometry remains untouched when the image is leading. When an
image is interleaved, forcing the effective card presentation to stacked/card
geometry is necessary: a two-column row grid cannot place arbitrary body blocks
before and after one media column while preserving a single configured stream.

The stacked path uses existing card density/body spacing and image presentation
classes. It adds no custom CSS, JavaScript, breakpoint emulation, or persisted
state.

### Preview and public parity

The focused preview renders the same public card components with an in-memory
draft. Fixing the shared renderer/components therefore repairs both the preview
and ordinary public item/group cards without a second preview-only implementation.
The native scoped Builder move action remains the authoritative editing action.

## Smallest safe change surface

The existing architecture can be reused without a new service or model:

- `PublicFrontCardTemplateRenderer` decides whether the configured top-level
  image is interleaved and supplies one explicit flow flag plus effective
  stacked presentation classes.
- The item and group card views retain their existing leading-image branch and
  use their already-present `parts` array for ordered stacked flow.
- Small family-specific image Blade components allow the same image markup to
  be used by the leading path and each family's part switch without duplicating
  links, fallbacks, diagnostics, or preview accessibility behavior.
- Existing presenters keep `parts`, `media_parts`, and `body_parts`; no public
  data contract needs to be removed.

Projected FU01 application surface:

- one existing renderer class;
- four existing card/card-part Blade components;
- two small family image Blade components;
- focused feature/Livewire tests;
- no migrations, dependencies, models, settings writes, or production work.

## Required FU01 evidence

1. A leading item image in a rows template retains effective rows geometry.
2. A leading group image retains its existing geometry and media-first path.
3. Moving an item image between configured body parts renders it between those
   parts in preview and public output.
4. Moving a group image between configured body parts renders it between those
   parts in public output.
5. Interleaved rows templates report stacked/card effective layout while
   retaining the configured rows compatibility attribute.
6. Image URL/fallback, fit, radius, source markers, preview-disabled links, and
   top-level part order diagnostics remain present.
7. Contributor rendering remains unchanged.
8. No validator, import, restore, backup, normalizer, editor move action,
   database schema, dependency, or production behavior changes.

## Complete post-FU01 audit inventory

Selecting FU01 does not reject or erase the other audit findings. The following
items remain explicit candidates for a fresh read-only Laravel Simplifier audit
after FU01 is committed and the new baseline is known.

### FU02: sample ranking and visible evidence

- Automatic/initial preview selection and the visible selector must be checked
  end to end for own-image-first ordering.
- Cover inherited from a content group must not count as the content item's own
  image.
- The browser/UI matrix still needs real evidence for own local image, own
  external image, inherited-only image, and no image.
- The reported low-severity symptom is that sample search still does not put
  episodes with an actual own image first.
- Query bounds, public visibility, restricted no-query behavior, 10-option
  preload, and 50-result search limits must remain intact.

### FU03: O4 path-corrected validation closure

- O4 is an internal Step 5B validation bug, not GitHub issue 10 and not a
  request to query GitHub.
- Validator issues currently carry field paths, but the focused editor flattens
  generic failures onto `data.key`; invalid-field navigation can therefore jump
  to the key/slug input instead of the actual part field.
- The follow-up must map validator paths through UUID-keyed top-level and nested
  Builder state and target the concrete field without weakening save validation.
- Order-field removal only made one former error case moot; it did not solve
  general path-aware error targeting.

### UX2 O1 corrections and evidence gaps to re-audit

- Extract or otherwise pin a single effective-order computation matching the
  validator's `0..1000` integer range and `(index + 1) * 10` fallback; add
  non-numeric, negative, over-1000, duplicate, and missing-order coverage. The
  current hydration condition mirrors the range inline but the named helper and
  full boundary regression are absent.
- Guard and prove exactly one refresh across the named pair:
  `CardTemplateEditorPage::updatedInteractsWithSchemas()` and the focused
  Builder `afterStateUpdated()` callback. Both call `refreshPreview()`.
- Preserve the approved move-modal details: autofocus, select the current value
  for type-to-replace, Enter submits, and Escape cancels through native modal
  behavior. The current implementation includes autofocus and an on-focus
  selection hook, but browser evidence remains incomplete.
- Add the named import/edit compatibility regression in
  `tests/Feature/SettingsImportExportTest.php`: import a template whose array
  order contradicts explicit order, prove public rendering follows explicit
  order, then edit/save through the focused editor and prove contiguous x10.
- Keep preview width explicitly transient. Tailwind `md:` responds to the
  browser viewport, not the narrower percentage card plane; true mobile
  breakpoint emulation would require a future iframe-based preview.
- Correct the remaining current-renderer helper copy. At this FU01 baseline two
  English strings still say `future renderer(s)`; one touched string was fixed
  during UX2.
- Treat old line-number anchors as cosmetic drift and refresh references rather
  than using stale line numbers as behavioral evidence.
- Keep conditional collapsibility aligned with inline versus slide-over display
  mode.
- Do not implement the rejected O2 recursive/public mover. Owning-Builder native
  action scope remains the safe boundary. The earlier whole-header click wrapper
  idea also conflicted with native collapse behavior.
- Do not implement the rejected O3 always-position-derived global cutover. The
  public reader validates on every read, so that change would reinterpret
  stored/imported data immediately. The current compatibility model remains
  authoritative until deliberate focused-editor save.
- Follow the project-authored tooling rule: run `vendor/bin/filacheck` and never
  run `vendor/bin/filacheck --fix` without explicit approval.

### Newly pinned adjacent renderer question

- Nested `part_group` image blocks are allowed by the shared Builder but are
  currently removed by the item/group presenters' body-only child filter. FU01
  does not silently enable them. Include that behavior in the fresh post-FU01
  audit so the operator can choose whether nested images should render and what
  geometry they should use.

## Sequencing answer for FU02 and FU03

FU02 and FU03 can run sequentially immediately after FU01 closure, but not as
part of this approved Stage 2 run. The safe sequence is:

1. finish FU01, its gates, handoff, and two-commit hash closeout;
2. run a fresh read-only Simplifier audit on the FU01 baseline covering this
   complete inventory, with stable FU02/FU03 option IDs and updated forecasts;
3. receive explicit approval for the selected new option or options;
4. create new research/implementation-plan documents for that approved scope;
5. implement and verify those options sequentially.

Pre-writing implementation plans for FU02/FU03 before the new audit would make
them baseline-stale and would not satisfy the repository's Stage 2 approval
gate. A combined audit may offer a combined option only if it remains one
bounded outcome; otherwise FU02 and FU03 should remain separate tasks.

## Explicit exclusions for FU01

- FU02 implementation or selector-query changes.
- FU03/O4 implementation or validation-path changes.
- Nested-image enablement.
- Effective-order/editor refresh/modal/import-compatibility evidence repairs.
- O2 inline/global mover or O3 global order cutover.
- Production normalization, production instructions prescribing it, or any
  other production action.
- Migration, dependency, model, authorization, lifecycle, import/restore/backup
  behavior change.
- Branch/worktree creation, push, remote publication, or another roadmap step.
