# Post-P3 / Package 4 Visual UX Research

## Authority

- Audit:
  `LS-20260724-PODTEXT-MEDIA-P3-POSTP3-P4-VISUAL-UX-01`
- Approved option:
  `MEDIA-P3-POSTP3-P4-VUX-O2-NATIVE-CARD-GALLERY`
- Stage 2 approval date: 2026-07-24.
- Starting baseline: clean `main` at
  `5b737e1e0f3d355a8dbb51a6887c2e0e785ed463`, 27 commits ahead of
  `origin/main`.
- The operator amended only the topbar part of the approved option: the real
  admin topbar remains sticky. Visual-capture tooling temporarily adds a
  capture-only class/style that makes it static, then removes it. The Card
  Template Editor runtime offset remains unchanged.

This correction follows the completed Package 3, post-Package-3, Package 4 and
inline owner-picker work. Package 5, dependency work, schema changes,
production and push remain excluded.

## Visual findings

### Media Resource

- Desktop renders the full inventory as a conventional dense table rather than
  a visual media gallery.
- At a 390-pixel viewport, responsive column hiding leaves mainly the
  thumbnail and record actions. Filename, MIME/size, repair state and date lose
  useful visual priority.
- Existing search, Needs Repair, authorization, bulk selection, preview,
  download and mutation actions are correct and must remain native table
  behavior.
- The six record actions are already accessible icon-only actions through the
  bounded Package 4 Resource-table configurator.

### Owner-image action

- Replace Image is already the first/default tab and Details and Effective
  Image is already second.
- The complete schema-owned Gallery/Upload/URL/Storage picker is already
  inline; there is one action modal and no nested form or second picker modal.
- The current five-extra-large modal is cramped for the inline gallery plus
  acquisition rail.
- Filament autofocus reaches a later focusable control and can open the modal
  at a non-zero scroll position.
- The existing selected-image summary is too tall, and Curator's default
  `object-cover` class wins over the caller's contain-fit class. That existing
  block should be compacted rather than adding a second selected-image banner.
- On narrow screens, the gallery precedes the acquisition controls, forcing an
  operator to scroll through gallery cards before reaching Upload, URL or
  Storage.

### Storage source

- Storage already has search and Refresh, but search uses a 300-millisecond
  debounce and filters the already-loaded first result window in component
  state.
- Candidate discovery reads only direct children of two `media-imports`
  roots. It does not find nested images across Laravel's public disk or the
  explicitly approved `public/images` source.
- Candidate identities are already encrypted and opaque. Admission rechecks
  the configured source, containment, extension and existence and then either
  reuses, registers or copies through the existing admission boundary.
- Laravel's installed filesystem guidance confirms that the `public` disk is
  rooted at `storage/app/public`; `public/storage` is only its web symlink.

### Performance

- Picker projection can perform two existence probes per raster: one through
  public delivery selection checks and another through inventory preview
  diagnostics.
- Media Resource record-action policy evaluation can rediscover owner/settings
  references for each row even though `MediaReferenceFinder` already provides
  a bounded bulk `prime()` API.
- Storage traversal needs both a result cap and an examined-entry/directory
  cap so a search for a later match cannot become an unbounded scan.
- Detail metadata stays lazy and must not be serialized into every gallery
  tile or Resource card.

## Selected architecture

### Native Media card gallery

Keep one Filament Table and use installed Filament 5.7.3 layout primitives:

- `contentGrid()` for one mobile column, two from `md`, three from `lg` and
  four from `2xl`;
- native `ImageColumn`, `Grid`, `Split`, `Stack` and `TextColumn` components;
- one contain-fit lazy preview;
- clearly separated original/display filename and stored basename;
- compact MIME, dimensions, size, location, repair status and day-first date;
- unchanged filters, pagination, checkboxes, bulk actions, record actions and
  policy boundaries.

There is no grid/list toggle, custom media framework, custom paginator or
second state model.

### Owner-image modal

- Increase only the shared owner-image action to
  `Width::SevenExtraLarge`.
- Disable that action's modal autofocus.
- Keep Replace first and Details second.
- Make the one existing selected-image summary compact and pass Curator's
  supported constrained/contain flag rather than fighting its default class.
- Explicitly recheck direct/inherited file availability when details reopen so
  a request-local probe cache cannot preserve stale fallback evidence.
- Use responsive CSS ordering so acquisition controls appear before Gallery
  below the `lg` breakpoint and the existing desktop rail remains on the
  logical side.

### Storage search and traversal

- Make `storageSearch` a server request after 1500 milliseconds and pass the
  normalized search to the candidate browser.
- Refresh reruns the same server-backed search.
- Browse only two code-allowlisted sources:
  - the complete Laravel `public` disk (`storage/app/public`), registered in
    place for safe rasters;
  - `public/images`, copied through admission because it is outside the
    Laravel public disk.
- Traverse subdirectories breadth-first within bounded candidate, examined
  entry and directory limits.
- Match against relative path and translated source label.
- Resolve only normalized descendants of the selected configured source.
- Never browse `public/build`, arbitrary public roots, environment-provided
  roots or the host filesystem.

This is a bounded acquisition browser, not Package 5 Files Discovery. It adds
no inventory state, journal or lifecycle operation.

### Probe and query budgets

- Reuse one request-local filesystem-existence decision between
  `PublicMediaDelivery` and `MediaInventoryDiagnostics`.
- Prime Media reference discovery once for the current Resource page before
  record-action policy evaluation.
- Add count-independent query ceilings for 1, 10 and 25 Resource cards and
  linear one-per-record raster existence-probe proof for picker/card
  projection.
- Keep preview images lazy and do not preload source bytes.

## Capture-only topbar rule

The application keeps its normal sticky admin topbar. Before a full-page
visual capture, the browser harness:

1. adds a dedicated capture class to the document root;
2. injects a scoped rule that changes only `.fi-topbar-ctn` positioning;
3. takes the screenshot; and
4. removes the class/style.

This avoids a repeated sticky header in stitched screenshots without changing
operator UX or the Card Template Editor runtime layout.

## Deferred deep UX evidence

The final visual pass also exposed broader workflow questions that this option
does not authorize: two confirmation concepts inside Change Image, maintenance
actions mixed into a selection task, a verbose six-action Media card, and weak
preview/identity/impact context on Media create/edit/mutation surfaces. These
findings belong to a fresh post-Package-4 deep Media UX Stage 1 audit with new
IDs. They are recorded in the implementation handoff and are not silently
included here.

## Installed-version research record

- Laravel Boost `application-info`: PHP 8.4, Laravel 13.21.1, Filament 5.7.3,
  Livewire 4.3.3, Boost 2.4.13, Pest 4.7.5 and Tailwind 4.3.3.
- Laravel Boost Filament docs: installed-version source guidance for
  `contentGrid()`, `Grid`/`Split`/`Stack`, `ImageColumn`,
  `modalAutofocus(false)` and modal attributes.
- Laravel Boost Livewire docs: installed-version guidance for
  `wire:model.live.debounce.1500ms` and keeping computed/server data out of
  serialized public state.
- Laravel Boost Laravel docs: installed-version guidance for the Laravel
  public disk and recursive filesystem enumeration.
- FilamentExamples: two short-query/refinement passes. Search returned the
  complete source of “Table Rendered as a Card Grid,” including
  `contentGrid()`, native layout columns and `recordUrl(false)`. No separate
  source/detail reader exists; other results were search summaries or full
  snippets embedded in search responses.
- Installed Filament 5.7.3 source: full source proof for table content grids,
  record-action placement, image sizing/existence behavior, action modal width
  and autofocus.
- Repository form UX/performance skills: applied to tabs, focus, mobile order,
  Livewire state, query shape and filesystem work.

## Boundaries

No migration, settings property, provider, Composer/npm dependency, Boost
update, file mutation, arbitrary directory scan, Package 5 feature, live/local
data action, production action or push is needed. Existing authorization,
safe preview/download, D01, diagnostics, acquisition permanence and pending
owner attachment contracts remain unchanged.
