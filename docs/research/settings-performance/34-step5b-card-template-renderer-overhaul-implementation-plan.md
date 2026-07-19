# Step 5B Card Template Renderer Overhaul O1 Implementation Plan

Audit ID: `LS-20260719-STEP5B-CARD-RENDER-O1-LG-PREVIEW-SHELL-01`

Approved option: `STEP5B-CARD-RENDER-OVERHAUL-O1-LG-PREVIEW-SHELL`

## Objective

Implement Mini 1 O1 only: below 1024px the focused Card Template preview is a
native Filament slide-over opened on demand; at 1024px and above it is one
adjacent logical-end column. Synchronize Alpine, Tailwind, action visibility,
teleport/unmount sequencing, focus restoration, and exact-width tests without
changing renderer output or any later program option.

Research 33 is the controlling evidence inventory. Completed documents 29–32
remain historical and must not be rewritten.

## Commands

No generator, migration, package, normalization, or database-probe command is
required. Use `apply_patch` for the two existing application/test surfaces and
the requested documentation.

Run test commands sequentially. Never use `vendor/bin/filacheck --fix`.

## Models, schema, resources, authorization, and widgets

- Models and schema: unchanged; no migration.
- Filament Resources: unchanged.
- Custom page: update the existing `App\Filament\Pages\CardTemplateEditorPage`
  Preview header action only.
- Authorization: preserve the existing page/action/preview capability checks.
- Widgets: not applicable.
- Dependencies: none.

## Application changes

### 1. Synchronize the Preview action breakpoint

File: `app/Filament/Pages/CardTemplateEditorPage.php`

Action: `Filament\Actions\Action::make('previewPanel')`

Docs: https://filamentphp.com/docs/5.x/actions/overview

Location: existing page header actions.

Visibility and authorization: keep the existing action registration and page
authorization; change only its responsive extra-attribute class from
`xl:hidden` to `lg:hidden`.

Behavior: the opener remains present and visible below 1024px, hidden at and
above 1024px, and continues to mount the same native slide-over content.

### 2. Synchronize Alpine and Tailwind at 1024px

File: `resources/views/filament/pages/card-template-editor.blade.php`

Tailwind docs: https://tailwindcss.com/docs/responsive-design

Config:

- initialize and retain one `MediaQueryList` for
  `(min-width: 1024px)`;
- use an `lg:grid-cols-[minmax(0,1fr)_minmax(20rem,26rem)]` adjacent layout;
- keep the existing sticky/max-height/overflow preview shell;
- retain editor-first and preview-second DOM order for logical placement; and
- preserve listener cleanup in `destroy()`.

Transition behavior:

1. When moving below `lg`, record whether focus is inside the adjacent preview,
   set `wide` false, then on Alpine's next tick focus the visible Preview opener
   only when restoration is required.
2. When moving to `lg` with no preview modal, set `wide` true directly.
3. When moving to `lg` with the preview modal open, determine whether focus is
   inside its containing `[aria-modal="true"]`; keep `wide` false; await the
   existing `$wire.unmountAction()`; recheck `media.matches`; mount the adjacent
   root only if the viewport is still wide.
4. After a successful wide mount, restore the adjacent preview heading only
   when focus came from the modal.
5. If the viewport returned narrow while unmount was pending, do not mount a
   stale adjacent root; restore the narrow opener when displaced modal focus
   requires it.
6. Do not call `refreshPreview()`, persist state, or create another Livewire
   property during any transition.

If measured 1024px geometry is unusably compressed, change the same grid class
to a smaller finite `lg` preview track and add the existing 20rem–26rem track
back at `xl`. Do not change Builder modal width or renderer geometry.

### 3. Keep preview rendering untouched

Do not change:

- `card-template-preview.blade.php` unless a browser-proven shell-only focus
  marker is strictly required;
- `PublicFrontCardTemplateRenderer`, presenters, or public card components;
- renderer CSS sources;
- sample selection/ranking;
- validation targeting;
- order compatibility;
- preview refresh hooks or modal Apply-time behavior; or
- HE/EN copy or navigation translations.

## Focused tests

### Feature/Livewire

File: `tests/Feature/CardTemplateEditorPreviewTest.php`

- Assert the cached `previewPanel` action retains its native slide-over and now
  has `lg:hidden` rather than `xl:hidden`.
- Assert rendered page shell source includes the exact 1024px media query and
  `lg` grid class without the old 1280px/`xl` shell boundary.
- Preserve existing one-root component response, locked state, no-write,
  restricted no-query, authorized query-limit, and canary assertions.

### Authenticated browser

File: `tests/Browser/CardTemplatePreviewBrowserTest.php`

Update the existing responsive test rather than creating an overlapping suite.
Use exact CSS viewport widths:

- 767, 768, 1023: opener visible, adjacent absent, closed root count zero;
  opening yields one modal/root, trapped focus, inert card, no overflow, and
  Escape/Close restoration.
- 1024, 1279, 1280: opener computed hidden, modal absent, one adjacent/root,
  usable editor/preview widths, logical-end geometry, and no overflow.

Add transition assertions:

- Observe DOM mutations while resizing an open preview from 1023 to 1024;
  maximum simultaneous preview roots must be at most one, final modal count
  zero, final adjacent count one, and focus on the adjacent heading.
- Resize from 1024 to 1023 while focus is in the adjacent preview; final root
  count is zero until opened and focus returns to the opener.
- Repeat a boundary cycle to expose stale listener/root leakage.

Locale and state:

- Hebrew RTL and English LTR must both prove logical-end geometry at 1024px or
  another exact required wide width.
- Dirty an existing editor field without saving, cross the boundary, and prove
  its current value and `beforeunload` protection remain.
- Retain the existing inline Builder live-refresh and native slide-over
  Apply-time tests. Exercise the new wide shell at 1024px where practical; do
  not change Builder persistence or refresh semantics.

Performance/security:

- Do not change existing canary budgets.
- Browser request counts are browser-plane evidence only; restricted SQL/query
  guarantees remain feature/component assertions.
- Keep preview public interactions at zero by asserting rendered link-shaped
  elements remain without `href` or other active actions.

## Focused verification sequence

Run sequentially and record every result:

1. `git diff --check` after research 33 and plan 34 are created and reread.
2. `php artisan test --compact tests/Feature/CardTemplateEditorPreviewTest.php`
3. Focused responsive browser test filter.
4. Full `tests/Browser/CardTemplatePreviewBrowserTest.php` using the permitted
   Chromium runner if the macOS sandbox reports the known bootstrap failure.
5. Existing `tests/Feature/CardTemplatePreviewerTest.php` and relevant public
   Card Template renderer regression unchanged.
6. `vendor/bin/pint --dirty --format agent` after PHP/test edits.
7. `vendor/bin/filacheck --dirty` for iteration only; never `--fix`.

Browser and test suites must never overlap.

## Authenticated browser acceptance

After focused automated coverage is green, use one browser owner to inspect the
real signed-in editor and public output. Record:

- exact viewport, locale, and document direction;
- opener/modal/adjacent/root counts;
- logical geometry, usable widths, and document/shell overflow;
- focus trap plus Escape/Close and both resize restorations;
- unsaved-state retention and absence of settings save;
- inline and slide-over Builder observations;
- console errors/warnings and observed Livewire request behavior; and
- the precise browser measurement plane, without claiming listener, heap,
  query, TTFB, or network-waterfall data that was not measured.

Restore any temporary viewport override and do not save the editor.

## Independent review

After implementation and focused verification, request at least two independent
read-only reviews:

1. architecture/history/scope review, including no renderer or navigation diff;
2. tests/performance/security review, including boundary race, focus, dirty
   state, restricted/public compatibility, and measurement-plane discipline.

Resolve or explicitly classify every finding before the final gate. Reviewers
must not edit, run overlapping suites, stage, commit, branch, use production,
or own the browser simultaneously.

## Documentation and requirement classification

Create
`docs/phase-02/settings-step5b-card-template-preview-lg-column-handoff.md` and
update:

- `docs/phase-02/current-project-state.md`;
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`;
- `docs/phase-02/settings-step5b-card-template-preview-handoff.md`.

The option handoff must include contract/provenance, Implemented / Already
existed / Deferred / Not applicable / Blocked classifications, files, tests,
every command and result, browser evidence, assumptions/limits, complete
seven-option and deep-renderer deferrals, numbered imperative Local Front Check
steps, final status, and a pending implementation hash.

## Ordered final gate

On the final documented tree:

1. requirements sweep against research 33, this plan, the audit, O1 acceptance,
   review findings, exclusions, navigation preservation, and seven-option
   inventory;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full serial `php artisan test` last.

After any file change following a green gate, restart at Pint and finish with a
new full serial suite. Never parallelize or interrupt the full suite.

## Canonical closeout

1. Stage only the approved implementation, tests, research/plan, handoffs, and
   requested state documents.
2. Confirm staged diff and secret/navigation/exclusion checks.
3. Commit with an imperative `fix:` subject while the option handoff hash is
   pending.
4. Immediately stamp that implementation hash into the option handoff and
   ledger only.
5. Commit the docs-only stamp as
   `docs: backfill Step5B O1 preview shell hash`.
6. End clean on `main`, do not push, and report both hashes.

## Full seven-option and deferred inventory

Only O1 is authorized. Preserve these later stable options unchanged:

1. `STEP5B-CARD-RENDER-OVERHAUL-O1-LG-PREVIEW-SHELL` — current option.
2. `STEP5B-CARD-RENDER-OVERHAUL-O2-ORDERED-FLOW-FOUNDATION` — visible-part
   geometry, ordered/media/body reconciliation, bounded group/link repair, then
   renderer Tailwind source.
3. `STEP5B-CARD-UX2-FU02-SAMPLE-RANKING-PARITY` — effective-image automatic,
   preload, and search parity including configured defaults.
4. `STEP5B-CARD-UX2-FU03-PATH-CORRECTED-CLOSURE` — structured UUID-aware
   validation targeting; internal bug, not a GitHub issue.
5. `STEP5B-CARD-UX2-FU04-ORDER-COMPAT-CLOSURE` — effective-order helper and
   import/public/focused-save compatibility.
6. `STEP5B-CARD-UX2-FU05-INTERACTION-CLOSURE` — duplicate service refresh and
   native move-modal keyboard/focus closure.
7. `STEP5B-CARD-UX2-FU06-COPY-CLEANUP` — live-renderer HE/EN helper copy and
   proven-dead order copy only.

Also retain every deep-renderer finding from research 33: media/body splits,
FU01 ordered stack, pre-filter geometry, group link gap, missing Tailwind source,
ranking gaps, flattened validation paths, duplicate refresh hooks, inline
effective-order logic, nested image omission, existing default images, wrapper
padding, and old helper copy.

## Stop conditions

Stop for a new Stage 1 audit and approval if implementation needs any unapproved
later option, another bounded task, a translation/navigation change, migration,
dependency, persistence, authorization, lifecycle, production action, renderer
or public-output change, or material effort beyond the approved forecast.
