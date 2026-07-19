# Step 5B Card Template Renderer Overhaul Research

Audit ID: `LS-20260719-STEP5B-CARD-RENDER-O1-LG-PREVIEW-SHELL-01`

Approved option: `STEP5B-CARD-RENDER-OVERHAUL-O1-LG-PREVIEW-SHELL`

## Purpose and authority

This document records the Stage 2 research contract for Mini 1 O1 only. The
operator approved the exact audit and option above in the same task after the
read-only Stage 1 report. This document does not authorize any later renderer,
sample, validation, order-compatibility, interaction, or copy-cleanup option.

The complete seven-option program is retained below so later findings are not
silently discarded. Each later option still requires its own fresh clean-HEAD
Laravel Simplifier audit, direct operator approval, new research/plan pair,
implementation, browser evidence, review, gate, and two-commit closeout.

## Baseline and provenance

- Authoritative checkout and Git root: `/Users/studioycm/Herd/PodText`.
- Branch: `main`.
- Authorized and observed Stage 2 HEAD:
  `d8f42da32eafe03c18862bbf7a08421b581ab0bb`.
- Stage 2 preflight worktree: clean.
- Current tracking state: `main...origin/main`; `origin/main` advanced externally
  to the same commit during Stage 1. Local HEAD and the worktree never drifted.
- Baseline commit `d8f42da` reorganized admin navigation. O1 does not need to
  touch `AdminNavigationOrder.php`, `lang/en/admin.php`, `lang/he/admin.php`, or
  `AdminPhase02ResourcesTest.php`; its navigation groups, order, and labels must
  remain unchanged.
- Completed Step 5B preview, UX2, and FU01 work remains historical context:
  `c75d0f2`, `d889d4f`, `82e639d`, and `2d02825`. None independently authorizes
  this option.

## Installed-version and research evidence

- PHP 8.4; Laravel 13.19.0; Filament 5.6.7; Livewire 4.3.3; Pest 4.7.4;
  Tailwind CSS 4.3.2.
- Tailwind's installed-version documentation defines `md` at 768px, `lg` at
  1024px, and `xl` at 1280px. The O1 contract uses the viewport `lg` boundary,
  not a preview-card container query.
- Livewire ships Alpine; no JavaScript dependency or duplicate Alpine bundle is
  required.
- Filament's native action slide-over remains the narrow preview mechanism and
  provides the modal focus trap. O1 preserves the action rather than creating
  an app-owned dialog.
- FilamentExamples was queried in two decomposed passes for responsive custom
  pages, adjacent preview grids, header actions, slide-overs, and focus. Access
  was search/snippet only; no source/read/detail tool was exposed. The useful
  neighboring example was a native slide-over content preview. No exact
  synchronized viewport-shell example displaced the installed application
  pattern.

## Current shell contract

The current shell has one breakpoint duplicated across three coupled surfaces:

1. `card-template-editor.blade.php` initializes and listens to
   `matchMedia('(min-width: 1280px)')`.
2. The page grid uses
   `xl:grid-cols-[minmax(0,1fr)_minmax(20rem,26rem)]`.
3. `CardTemplateEditorPage` hides the Preview header action with `xl:hidden`.

The adjacent preview is inside Alpine `<template x-if="wide">`; the narrow
preview is the same partial rendered through a native Filament action
slide-over. Editor content remains first in DOM order and preview second, so
normal CSS grid placement already puts preview at logical end in LTR and RTL
without physical left/right utilities.

Stage 1 authenticated browser evidence against the unchanged baseline showed:

| CSS viewport | Current result |
| ---: | --- |
| 767 | Preview opener and slide-over |
| 768 | Preview opener and slide-over |
| 1023 | Preview opener and slide-over |
| 1024 | Preview opener and slide-over |
| 1279 | Preview opener and slide-over |
| 1280 | One adjacent preview column |

Hebrew RTL placed the slide-over and wide column at logical end. Stable states
had no horizontal document overflow and at most one active preview root.
Opening the slide-over focused the preview heading; tab focus stayed trapped;
Escape restored the opener. The renderer emitted link-shaped preview elements
without `href`, so the public card remained inert.

## O1 correctness findings

### One synchronized `lg` boundary

All three breakpoint consumers must change together. A partial change could
hide the opener before the adjacent root mounts, render both surfaces, or make
the CSS grid disagree with Alpine state.

### Narrow-to-wide root race

The current listener assigns `wide = true` before awaiting
`$wire.unmountAction()`. When a preview slide-over is open, Alpine may mount the
adjacent root before Filament removes the teleported modal root. A settled
root-count assertion cannot detect this peak duplicate or its duplicate heading
ID.

O1 must keep `wide` false while the action unmounts, verify that the media query
still matches, then mount the adjacent root. Browser coverage must observe the
peak root count through the transition, not only its final state.

### Focus restoration in both directions

The current narrow-to-wide check only looks inside the preview section. Focus
on surrounding modal chrome can be missed. O1 must detect focus within the
containing `[aria-modal="true"]` and restore the adjacent preview heading after
the modal is gone.

Stage 1 also reproduced the inverse gap: when focus is on the adjacent preview
heading, resizing below the wide boundary removes that subtree and leaves focus
on `body`. O1 must restore the newly visible Preview opener when focus was
inside the adjacent preview.

Rapid resize-back while `unmountAction()` is pending must recheck the current
media query. It must not mount a stale wide root; if modal focus was displaced,
it must restore the narrow opener.

### 1024px geometry risk

The existing wide track reserves 20rem to 26rem plus a 1.5rem gap. First apply
the existing track at `lg` and measure the authenticated 1024px layout. If that
measured state makes the editor unusably narrow or creates shell overflow, O1
may use a narrower finite `lg` preview track and restore the existing 20rem to
26rem track at `xl`. This remains a class-only adjustment in the same Blade
file. Renderer geometry, Builder slide-over width, and modal semantics are not
part of the adjustment.

## O1 bounded implementation contract

O1 may change only the responsive preview shell and its focused regression
coverage:

- change both media-query uses from 1280px to 1024px;
- change the page grid from `xl`-only to `lg`-and-up adjacency;
- change only the Preview action's responsive class from `xl:hidden` to
  `lg:hidden`;
- sequence action unmount, current-query verification, adjacent mounting, and
  focus restoration without duplicate roots;
- preserve listener removal on Alpine destruction;
- prove exact behavior at 767, 768, 1023, 1024, 1279, and 1280;
- prove Hebrew RTL and English LTR logical-end placement;
- preserve focus trap, Escape/Close/resize restoration, no overflow, unsaved
  state, preview inertness, transient controls, and both Builder modes; and
- preserve the existing public renderer and preview/public parity unchanged.

No new PHP class, JavaScript module, CSS source, translation key, persistent
property, query, migration, model, dependency, permission, or lifecycle hook is
needed.

## Performance and security invariants

- `wide`, the `MediaQueryList`, and its listener remain Alpine-local state.
- Do not add a Livewire property, persist a breakpoint preference, duplicate the
  draft, or call `refreshPreview()` during responsive transitions.
- Modal open/close may use the existing Livewire action request; resizing does
  not authorize new preview, sample, or settings queries.
- Do not infer query counts from browser request counts. Server/component/query
  and hydrated browser planes remain separately reported.
- Restricted users must retain the existing zero-render/zero-query selector
  behavior and forged-call guards.
- Public preview HTML must retain inert link output and the same sanitizer,
  presenter, and renderer path.
- Unsaved draft state and `beforeunload` protection remain authoritative across
  root switches; no save is performed during browser acceptance.
- No live HTTP, mail, local-development database probe, or production action is
  permitted.

## Deep renderer inventory retained for later options

O1 does not change any of these findings:

1. Content-item and content-group presenters still expose `parts`,
   `media_parts`, and `body_parts`; their views retain media/body branches.
2. FU01 added an ordered top-level stacked path for interleaved images while
   preserving the leading-image media/body geometry.
3. Effective geometry is selected before actual absent/filtered part output is
   fully known, leaving no-image row and phantom-column questions for O2.
4. Content-group row geometry has an adjacent link-contract gap that O2 may
   include only after its fresh audit.
5. Renderer-emitted finite row classes under `app/Support/PublicFront` are not
   currently included by the public/admin Tailwind source declarations. O2 must
   fix content-aware no-image geometry before compiling those classes so it
   does not create a new regression.
6. Automatic sample selection, ten-option preload, and capped search do not yet
   share full effective-image ranking semantics.
7. Structured validator paths are flattened onto `data.key` instead of being
   mapped to current top-level/nested Builder UUID paths.
8. `updatedInteractsWithSchemas()` and the owning Builder
   `afterStateUpdated()` both call `refreshPreview()`; FU05 owns the service-call
   count closure.
9. Focused hydration mirrors effective-order range/fallback logic inline rather
   than using a pinned shared helper with complete boundary coverage.
10. Nested `part_group` images are allowed by the Builder but omitted by the
    item/group presenters' body-only child filter. Enabling them would expose
    new public content and remains excluded.
11. Existing family/global missing/default-image settings can provide preview
    images; FU02 should reuse them instead of inventing image fields.
12. Wrapper padding and row-gap behavior remain part of O2's content-aware
    geometry question.
13. Some HE/EN helper text still describes future renderers although rendering
    is live; FU06 owns that copy inventory and cleanup.

## Complete seven-option program inventory

| Sequence | Stable option | Reserved outcome | Status in this document |
| ---: | --- | --- | --- |
| 1 | `STEP5B-CARD-RENDER-OVERHAUL-O1-LG-PREVIEW-SHELL` | Move adjacent preview boundary to `lg`, synchronize render/teleport/focus state, and prove the exact viewport matrix. | Approved and authorized now |
| 2 | `STEP5B-CARD-RENDER-OVERHAUL-O2-ORDERED-FLOW-FOUNDATION` | Select geometry after visible parts, reconcile ordered/media/body flow, close bounded group/link geometry, then add missing renderer Tailwind source. | Deferred; fresh audit required |
| 3 | `STEP5B-CARD-UX2-FU02-SAMPLE-RANKING-PARITY` | Align automatic, preload, and search ranking across own, inherited, configured-default, and no-image cases. | Deferred; fresh audit required |
| 4 | `STEP5B-CARD-UX2-FU03-PATH-CORRECTED-CLOSURE` | Preserve structured issues and target actual UUID-keyed top-level/nested invalid fields. | Deferred; fresh audit required; internal bug, not GitHub issue |
| 5 | `STEP5B-CARD-UX2-FU04-ORDER-COMPAT-CLOSURE` | Pin effective-order semantics and import/public/focused-save compatibility without a global cutover. | Deferred; fresh audit required |
| 6 | `STEP5B-CARD-UX2-FU05-INTERACTION-CLOSURE` | Close duplicate refresh calls and native move-modal keyboard/focus/service-count evidence. | Deferred; fresh audit required |
| 7 | `STEP5B-CARD-UX2-FU06-COPY-CLEANUP` | Correct live-renderer helper copy and remove only proven-dead order copy while preserving navigation text. | Deferred; fresh audit required |

## Program-wide exclusions

Unless a later fresh audit and direct approval changes them, exclude:

- legacy UX2 O2 inline-header editing or a recursive/public mover;
- UX2 O3 global explicit-order cutover;
- production normalization, instructions prescribing it, or any production
  action;
- nested image enablement or nested-media redesign;
- contributor image-field invention;
- persistence for preview width, selected sample, or Builder display mode;
- migrations, dependencies, permission redesign, settings lifecycle changes,
  generalized renderer platform work, or another roadmap step; and
- branch/worktree creation, push, publication, or pull request work.

## O1 acceptance evidence

Stage 2 must collect and record:

1. Static/component evidence that Alpine, grid, and Preview action all use the
   exact 1024px/`lg` boundary.
2. Browser evidence at 767, 768, and 1023: no adjacent root while closed, a
   visible opener, exactly one modal root when open, trapped focus, inert card,
   no horizontal overflow, and focus restoration on Close/Escape.
3. Browser evidence at 1024, 1279, and 1280: exactly one adjacent root, no modal
   root, hidden opener, usable non-overlapping editor/preview columns, correct
   logical-end placement, and no horizontal overflow.
4. A 1023-to-1024 open-modal transition with peak root count at most one and
   focus restored to the adjacent heading.
5. A 1024-to-1023 focused-adjacent transition with focus restored to the
   visible opener.
6. Hebrew RTL and English LTR evidence at the new boundary.
7. Unsaved value and `beforeunload` protection retained through a boundary
   transition with no settings save.
8. Existing inline and slide-over Builder behavior, including logical-start
   modal geometry, Apply-time versus live refresh, preview visibility/inertness,
   and one preview root.
9. Existing restricted selector, public inertness, public-renderer parity, and
   query/state canaries remain green without changed budgets.
10. Authenticated browser and public-output smoke evidence with console/network
    observations reported only in the planes actually measured.

## Stop conditions

Return to a fresh Stage 1 audit before continuing if O1 requires a renderer,
presenter, sample query, validator, order-compatibility, refresh-hook, copy,
translation, authorization, persistence, schema, dependency, lifecycle, or
production change; a second bounded implementation task; or material effort
beyond the approved 5–8 hour forecast.
