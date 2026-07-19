# Step 5B Card Template Ordered Flow Foundation Research

Date: 2026-07-19

Audit: `LS-20260719-STEP5B-CARD-RENDER-O2-ORDERED-FLOW-01`

Option: `STEP5B-CARD-RENDER-OVERHAUL-O2-ORDERED-FLOW-FOUNDATION`

## Purpose and authority

This document records the approved O2 research contract. The operator approved
the exact audit and option in the same task after the mandatory read-only Stage
1 audit. It authorizes only the ordered-flow renderer foundation described
below. It does not authorize another Step 5B option or another roadmap step.

## Baseline and provenance

- Checkout: `/Users/studioycm/Herd/PodText`
- Branch: `main`
- Approved and observed starting HEAD:
  `14285b34ea1a4b19471a96cc1f5e07990fd9633a`
- Required O1 implementation:
  `215340d3b155232bba0dcf4644f9c360917d9d22`
- Required O1 hash stamp:
  `14285b34ea1a4b19471a96cc1f5e07990fd9633a`
- Starting relation: clean, ahead of `origin/main` by two, behind by zero.
- The `d8f42da` navigation contract remains outside this renderer change.

The renderer's explicit order history is retained. Validation and the card
template value object sort parts by normalized `order`; O2 does not change the
Builder, draft normalizer, validator, persistence, import, restore, or backup
contract.

## Installed-version and research evidence

Laravel Boost reported:

- PHP 8.4
- Laravel 13.19.0
- Filament 5.6.7
- Livewire 4.3.3
- Pest 4.7.4
- Tailwind CSS 4.3.2

Version-aware Tailwind and Filament documentation confirms that custom theme
files must explicitly register every application directory containing complete
Tailwind utility strings with `@source`, then rebuild the theme. Both PodText
panel themes currently omit `app/Support/PublicFront/Cards`.

FilamentExamples was queried in two passes with short custom-theme, preview,
Builder, and responsive-page searches. The useful examples confirmed the
existing custom-theme `@source` pattern and keeping custom page rendering in
Blade. Only `search_examples` was exposed; no independent source/detail fetch
tool was available. No external example changes the PodText-specific shared
presenter and public-card architecture.

## Current defect

`PublicFrontCardTemplateRenderer` currently selects geometry from configured,
family-filtered parts before either presenter removes record-specific blank or
disabled parts. The presenters then create the actual `parts`, `media_parts`,
and `body_parts` arrays, too late to correct the chosen article geometry.

Consequences:

- a configured row can keep a phantom media track, row gap, and outer padding
  after all media is removed;
- `presentMany()` can reuse one geometry for records with different actual
  visible parts;
- ordered images are rendered inside the padded body wrapper rather than full
  bleed;
- renderer diagnostics describe configured controlled types instead of the
  exact actual sequence;
- group row geometry lives in an unused `presentation['link']` contract and is
  never applied to the rendered article; and
- the required row utility appears in markup but is absent from the current
  public/admin compiled themes.

## Actual visible-part semantics

The final presenter-produced top-level `parts` array is authoritative. An
actual visible part is a non-null presenter result after:

1. explicit-order sorting;
2. template `visible` filtering;
3. family allowlisting;
4. global `image_size` suppression;
5. record data and display-option gates;
6. blank-value suppression; and
7. the existing body-only child filter for `part_group`.

A presented image part is visible media even when its resolved URL is null,
because the current image component visibly renders the established fallback
placeholder. A default image also counts as visible media. A true no-media card
has no presented top-level image part because the image block is absent,
invisible, or globally hidden.

Nested `part_group` images remain omitted and cannot affect geometry.

## Finite flow contract

The effective flow is determined per presented card:

- `media-leading`: exactly one actual media part at index zero and at least one
  actual body part. A configured row may use the existing two-column row
  geometry. Existing large-image forcing still selects card geometry.
- `body-only`: zero actual media parts and at least one body part. A configured
  `rows` template keeps `data-card-template-layout="rows"` but reports effective
  `data-result-layout="cards"` and `data-card-part-flow="body-only"`. It renders
  one full-width density-padded body region with no row grid, row gap, or outer
  row padding.
- `media-only`: one leading media part and no body. It uses full-bleed card
  geometry rather than an empty row.
- `ordered-stack`: any media part after index zero or more than one actual media
  part. It reports effective cards and renders the exact actual sequence.
- `empty`: no actual parts. The article remains structurally safe and does not
  invent new content.

For ordered flow, each media part is an individual full-bleed run. Consecutive
body parts share one density-padded body run. This preserves exact logical DOM
order in both RTL and LTR without physical left/right rules.

## Multiple-image contract

The current Builder and validator permit repeated top-level image blocks. O2
therefore preserves every actual top-level image occurrence exactly once and in
configured order. It does not deduplicate, reject, or silently select a primary
image.

Two or more actual image parts always force `ordered-stack`, including two
consecutive leading images. Each occurrence reuses the family's existing
resolved image payload. Distinct source-aware image assets would be a separate
media-semantics change and are excluded.

## Diagnostics contract

- `data-card-template-*` remains configured-state compatibility evidence.
- `data-result-layout` reports actual effective geometry.
- `data-card-part-flow` reports the finite flow above.
- `data-card-renderer-parts` reports the exact actual top-level rendered type
  sequence, retaining repeated types.
- `parts`, `media_parts`, and `body_parts` remain derived from the same actual
  source array.
- ordered `part_runs` are derived from that same array and are a render-only
  projection, not another authoritative state source.

## Group row and link contract

Group cards already contain individual image, title, taxonomy, and action links.
Adding a whole-card anchor would create nested interactive content. O2 instead
applies content-aware geometry to the article/container, preserves individual
public links and preview inertness, and removes the unused `link` presentation
shape.

## Preview and public parity

The preview already renders the shared public presenter/components from an
unsaved in-memory draft. O2 changes the shared path rather than creating a
preview-only renderer.

The established image-source distinction remains intentional: preview-only
item rendering disables inherited group covers and can show the fallback
placeholder, while ordinary public rendering retains saved default-image and
group-cover behavior. Parity here means the same actual-part classification,
flow, ordering, geometry, diagnostics, and link-mode rules for the payload each
surface presents.

## Performance and security invariants

- Geometry finalization is a pure linear array operation and adds zero queries.
- No query is added to Blade.
- No public property, control, `wire:model`, polling, island, or cache is added.
- Existing preview query counts, restricted-state zero-query behavior, and
  frozen Livewire DOM/HTML/state ceilings remain unchanged.
- Existing public visibility scopes and escaped Blade output remain unchanged.
- Preview links remain non-navigable and public per-part links remain valid.
- No nested media becomes public.

## Tailwind source sequencing

The current themes do not scan the renderer's finite class strings. The narrow
source declaration is `../../../../app/Support/PublicFront/Cards/**/*` in both
panel theme files.

This source must be added only after the body-only/content-aware geometry fix is
implemented and covered by focused tests. Compiling the current row classes
first is forbidden because it would activate the phantom-column regression.

## Browser baseline

The Stage 1 authenticated HE/RTL browser review at 1470px observed the row
utility in markup but only one computed `348px` grid column. A 382px preview
article rendered a 348px placeholder followed by a 348px body with a vertical
16px gap. This is browser-plane evidence that the missing theme source currently
masks the server-side row defect.

The same review confirmed preview placeholders and public default/group images,
no horizontal overflow, and correct preview-inert/public-link behavior. It did
not establish group, multiple-image, true body-only, EN/LTR, narrow-width,
keyboard, console, or network acceptance; Stage 2 must cover those planes.

## Required verification

Focused tests must cover item and group leading, body-only, media-only,
interleaved, multiple-image, nested-image omission, heterogeneous
`presentMany()`, exact diagnostics, group non-anchor geometry, query constancy,
restricted preview behavior, and existing navigation/O1 regressions.

Browser acceptance must cover 767, 768, 1024, and 1280px in HE/RTL and EN/LTR,
preview and public item/group cards, row/card modes, leading/default/fallback,
body-only, interleaved, multiple images, overflow, focus, console, network, and
public/preview link behavior.

## Deferred inventory

O2 does not implement legacy inline-header UX, global explicit-order cutover,
production normalization, nested media, contributor images, preview preference
persistence, sample ranking parity, validation-path correction, order
compatibility closure, duplicate-refresh/move-modal closure, copy cleanup,
migrations, dependencies, permission redesign, settings lifecycle changes,
general renderer-platform work, or another roadmap step.

## Stop conditions

Stop for an amended audit and new approval if implementation requires a schema,
dependency, migration, new class/subsystem, changed public security boundary,
new persistence, nested-media behavior, contributor changes, materially larger
task count or effort, or any unexpected overlapping worktree change.
