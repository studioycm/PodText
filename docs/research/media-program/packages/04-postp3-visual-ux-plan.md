# Post-P3 / Package 4 Visual UX Correction Plan

## Approval

- Audit:
  `LS-20260724-PODTEXT-MEDIA-P3-POSTP3-P4-VISUAL-UX-01`
- Selected option:
  `MEDIA-P3-POSTP3-P4-VUX-O2-NATIVE-CARD-GALLERY`
- Stage 2 approval date: 2026-07-24.
- Starting baseline: clean `main` at
  `5b737e1e0f3d355a8dbb51a6887c2e0e785ed463`, 27 commits ahead of
  `origin/main`.
- Topbar amendment: production stays sticky; capture tooling temporarily
  makes it static through a capture-only class/style. No Card Template Editor
  runtime adjustment is required.

## Mini-task 1 — reconcile authority

Update the context helper, requirements registry, master plan, supersession
map, current state and ledger. Add this research/plan before PHP changes.

## Mini-task 2 — write failing focused tests

Add or amend isolated Pest coverage for:

1. Media Resource native content-grid/card structure and retained actions,
   filters, pagination, bulk selection and Needs Repair inventory.
2. Seven-extra-large owner action, disabled autofocus, tab order and compact
   constrained selected preview.
3. Narrow picker order with acquisition controls before Gallery.
4. 1500-millisecond Storage binding and server-backed result replacement.
5. Recursive results from nested Laravel-public and `public/images` paths,
   exclusion of unrelated public/build paths, opaque identities and descendant
   containment.
6. Candidate/result/traversal budgets.
7. One shared raster existence decision and count-independent Media Resource
   reference-query ceilings for 1, 10 and 25 records.

Use SQLite/test databases, `Storage::fake()` and a temporary fake disk for
`public/images`; do not read or write local development storage.

## Mini-task 3 — owner picker visual correction

Modify only the shared owner action and existing picker views:

- `Width::SevenExtraLarge`;
- `modalAutofocus(false)`;
- Replace first and Details second unchanged;
- compact the one existing selected-image block with Curator constrained
  contain-fit and add no duplicate selected banner;
- responsive source/gallery ordering, source first below `lg`;
- preserve one schema-owned Livewire component, one outer action modal, no
  form wrapper, no nested form/modal, focus restoration, touch, keyboard,
  RTL/LTR, busy/offline and cancellation behavior.

## Mini-task 4 — bounded Storage search/discovery

Modify `MediaPickerPanel`, `StorageImageCandidateBrowser`, `config/media.php`
and the bounded filesystem disk configuration:

- use `wire:model.live.debounce.1500ms`;
- query the server on normalized search changes;
- keep Refresh explicit;
- enumerate only the Laravel public disk and `public/images`;
- recursively traverse normalized descendants within candidate, entry and
  directory caps;
- match relative paths/source labels;
- keep encrypted tokens and fresh resolve/admission checks;
- register only safe public-disk rasters in place; copy `public/images` input;
- mutate no source file.

## Mini-task 5 — native Media Resource cards and budgets

Recompose the existing Media table with native Filament table layout columns
and `contentGrid()`:

- responsive 1/2/3/4 card grid;
- lazy contain-fit preview;
- useful filename/file metadata and repair badge;
- existing search, filters, 25-record pagination, selection and six record
  actions unchanged.

Prime `MediaReferenceFinder` once per rendered Resource page and reuse
request-local file-existence decisions between public delivery and inventory
diagnostics. Recheck the relevant direct/inherited delivery decision when the
owner detail workspace reopens. Do not load detail-only presenter state in
cards.

## Mini-task 6 — browser visual verification

Verify Hebrew RTL and English LTR at wide and 390-pixel viewports:

- card hierarchy, metadata, Needs Repair, action access and no horizontal
  overflow;
- owner modal opens at the top, fits viewport, Replace is first, Details
  second and preview is not cropped;
- acquisition controls precede Gallery on narrow screens;
- Storage 1500-millisecond search, Refresh and nested results;
- keyboard, focus, touch, busy/offline and safe action behavior.

For stitched/full-page screenshots only, temporarily install and remove the
capture topbar class/style. Runtime sticky-header behavior remains unchanged.

## Mini-task 7 — ordered closure

Create the handoff with requirement classification, files/tests/commands and
numbered manual operator checks. Run, in order:

1. requirements and drift sweep;
2. `vendor/bin/pint --test`;
3. `vendor/bin/filacheck`;
4. `npm run build`;
5. full `php artisan test` last.

After any later file change, restart from Pint. Commit the implementation and
handoff, then immediately create the docs-only implementation-hash stamp.
Do not push.

## Exclusions

- Runtime non-sticky admin topbar or Card Template Editor layout changes.
- Composer/npm/toolchain/Boost discovery changes.
- Schema, migration, settings-property or provider changes.
- Package 5 Files Discovery or physical lifecycle.
- Arbitrary `public`, `public/build`, host filesystem or environment-defined
  directory scans.
- Image editing, crop, optimization, normalization or byte mutation.
- Local-development/production data or storage actions.
- Push.
