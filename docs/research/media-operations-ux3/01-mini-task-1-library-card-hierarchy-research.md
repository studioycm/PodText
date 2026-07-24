# Media Operations UX3 Mini-task 1 Library Card Hierarchy Research

## Approved contract

- Audit: `LS-20260724-PODTEXT-MEDIA-OPERATIONS-UX-03`
- Option: `MEDIA-OPS-UX3-O2-PDF-CONTRACT-TARGETED-WORKSPACES`
- Binding design contract: `PODTEXT-MEDIA-UX-CONTRACT-20260724-CORRECTED`
- Approved implementation slice: Mini-task 1 only
- Baseline: `main` at
  `6d8f7f73742448a7671fb5b8f238bf01ebf6b5ad`, clean, 29 commits ahead of
  `origin/main`

The operator approval is the Stage 2 authority. The corrected PDF and its ideal
Media Operations home sketch are binding design evidence, but they do not
authorize later workspaces or Package 5 implementation.

## Mini-task boundary

Mini-task 1 changes the existing Media Library card and its record-action
hierarchy only.

It must deliver:

1. an image-first card;
2. a stable human identity using title, then original filename, then stored
   filename;
3. a bounded known-reference summary that never claims an unattached row is
   unused;
4. a persistent `Needs attention` state with the concrete primary diagnostic
   reason and the count of additional reasons;
5. quiet technical file facts after the usage and issue information;
6. one visible `Open details` entry and one quiet, accessible `More actions`
   menu;
7. the precise label `Delete permanently` for the current irreversible delete;
8. unchanged All Media inventory completeness, Needs Repair filter behavior,
   pagination, selection, upload, authorization, and physical mutation
   authorities.

It must not add task views, URL-backed return context, a complete Care page,
reason-specific fixes, structured repair results, provider decisions, batch
workflow redesign, migrations, dependencies, or any Package 5 route or
operation.

## Binding Package 1-4 authorities

| Authority | Mini-task 1 treatment |
|---|---|
| Every Curator row remains visible in All Media | Preserved; the base `MediaRecordScope::inventoryQuery()` and existing filters are unchanged |
| Needs Repair is diagnostic | Preserved; the filter and `MediaInventoryDiagnostics` remain authoritative |
| `media_attachments.media_id` is owner authority | Preserved; the card reads the existing reference projection and performs no attachment write |
| Curator `path` is file-location authority | Preserved; stored filename and file facts continue to derive from the current Media row |
| Gallery selection is mutation-free | Not touched |
| Upload, URL, and Storage admission is immediately permanent | Not touched |
| Owner form cancellation does not delete admitted Media | Not touched |
| Safe preview/download routes remain authoritative | Existing routes remain the menu destinations |
| Policies and `MediaFilesystemMutationCoordinator` govern file mutation | Existing action authorization and callbacks remain unchanged |
| Referenced shared-byte mutation remains blocked | Preserved; no policy or coordinator change |

## Current implementation evidence

### Inventory and bounded reference work

`MediaResource::getEloquentQuery()` already uses the complete inventory query
and projects only the existing storage-identity peer count. `ListMedia` primes
`MediaReferenceFinder` once for the records on the current page. That prime
performs bounded attachment, legacy-path, and relevant settings lookups for the
page instead of one lookup per card.

`MediaReferenceFinder::referencesForMedia()` returns unique translated
reference strings. It is suitable for a compact count labelled **known
references** for public-disk rows. Its existing safety contract deliberately
returns no reference result for non-public-disk rows, so those cards must say
that the count is unavailable rather than misreporting zero. It is not a typed
usage/Care model: it does not categorize direct owners, effective fallbacks,
settings slots, or owner destinations. Mini-task 1 therefore must not label
this number `owners`, `direct owners`, or `unused`.

### Diagnostics and filesystem work

`MediaInventoryDiagnostics::reasons()` provides a stable ordered reason list:

1. portable identity;
2. storage disk or missing physical file;
3. public-audience denial;
4. unsafe inline SVG;
5. legacy or nonstandard metadata.

The service memoizes each Media decision for the request. The first reason can
therefore be presented as the concrete primary issue and the remaining length
as an additional-issue count without adding a second diagnostic authority.
Preview and diagnostic rendering continue to share the existing request-local
file-existence decision.

### Current card gap

The present card renders title/name, original filename, stored filename, file
facts, location, a generic Ready/Needs Repair badge, and date. The concrete
reason is tooltip-only. It has no usage summary. Six record actions have equal
visual weight. Because Filament resolves a Resource record link from an action
named `view` before one named `edit`, the current card body opens the raw safe
preview route rather than Media details.

The project-wide table configuration also places record actions before columns.
For card layouts, Filament adds `order-first`, which puts actions before the
image even though the PHP column declaration is image-first.

## Installed-version research

### Laravel Boost

`application-info` returned installed-version guidance for:

- PHP 8.4;
- Laravel 13.21.1;
- Filament 5.7.3;
- Livewire 4.3.3;
- Pest 4.7.5;
- Tailwind CSS 4.3.3.

Version-scoped documentation confirmed:

- record actions support URL actions and authorization callbacks;
- `Filament\Actions\ActionGroup` is the supported table-action dropdown;
- action-group triggers support `label()`, `icon()`, `color()`, `button()`,
  and the normal trigger-button APIs;
- a table may keep an ungrouped primary action beside a grouped action menu;
- `modifyUngroupedRecordActionsUsing()` affects only the ungrouped primary
  action.

Relevant installed documentation:

- `packages/tables/docs/04-actions.md`
- `packages/actions/docs/03-grouping-actions.md`
- `docs/10-testing/05-testing-actions.md`

### FilamentExamples

The configured MCP exposes search snippets only; it has no source-detail tool.
Two query passes were performed with multiple short phrases.

| Example | Evidence returned | Pattern used | Pattern rejected or adapted |
|---|---|---|---|
| Table Rendered as a Card Grid | Full search snippet for `UserResource` | Native `Grid`, `Stack`, `contentGrid()`, explicit details destination | Its rendered HTML button inside a `TextColumn` is unnecessary; PodText can use native record actions |
| Repair Salon CRM `OrdersTable` | Full search snippet | `ActionGroup` safely groups heterogeneous record actions | PodText keeps one details action outside the group so the primary job remains visible |
| Complex Orders Table | Full search snippet | Native action URLs and table configuration | Its task tabs are later-scope and are not copied |
| Tournament `view_stats` relation action | Full search snippet | Resource URL callback for a focused destination | PodText reuses the existing edit/details route and does not add a page in this slice |

### Installed Filament source

Installed source confirms:

- `ActionGroup` defaults to an icon-button trigger and exposes
  `getFlatActions()`;
- an icon-button trigger receives its accessible name from the localized
  label;
- a tooltip can supplement that accessible name;
- group children render as labelled dropdown items;
- Enter and Space activate the group trigger;
- table action groups are teleported, preventing clipping by the card's
  `overflow-hidden`;
- `RecordActionsPosition::AfterContent` is supported and selects the suitable
  bottom-end dropdown placement.

`ResourceTableActions::iconOnly()` cannot remain on this table because it would
hide the required visible label on the primary details action. Other Resources
continue using that rider unchanged.

## Chosen implementation

Use the existing `MediaTable`, `MediaReferenceFinder`, and
`MediaInventoryDiagnostics`; do not add a parallel projector or persistence
model.

The card order will be:

1. safe preview;
2. stable display identity, original filename, stored filename;
3. localized known-reference summary;
4. Ready/Needs attention badge, then persistent primary issue and additional
   count;
5. MIME/extension/dimensions/size, location, and created date;
6. record actions after card content.

The record actions will be:

- the card body explicitly linked to the existing edit/details route when the
  current user may update the record;
- the existing `edit` action, relabelled `Open details`, rendered as a visible
  primary button, and still authorized by `update`;
- one gray icon-button `ActionGroup` labelled and tooled `More actions`;
- unchanged nested `view`, `download`, `rename`, `swap`, and `delete` action
  names, authorization, safe routes, confirmation/form behavior, and
  coordinator callbacks;
- record and bulk delete labels changed to state permanence explicitly.

The existing edit page remains a transitional details/Care entry. A complete
Care page, typed usages, impact explanation, explicit blockers, fixes, recheck,
results, and recovery are later mini-tasks and are not represented as complete.

## Performance contract

- Keep the 25-card page limit.
- Keep current-page `MediaReferenceFinder::prime()` behavior.
- Do not add per-card queries.
- Keep Media reference-query evidence at or below the current 20-query ceiling
  for 1, 10, and 25 cards.
- Keep one request-local filesystem existence decision per rendered raster.
- Do not add whole-library task counts or filesystem scans.
- Browser claims remain limited to rendered layout, direction, accessible
  controls, and overflow; database query and storage-probe claims remain
  separate measured planes.

## Security and authorization contract

- Do not change any policy.
- Keep per-record action authorization.
- Keep fresh trusted-record resolution before download.
- Keep all physical mutations inside `MediaFilesystemMutationCoordinator`.
- Keep replacement MIME, purpose, size, and temporary-upload validation.
- Do not expose raw storage paths as URLs.
- Do not present hidden/ineligible mutation actions as newly available.

## Verification design

Focused Pest coverage must prove:

- All Media and Needs Repair query behavior did not change;
- title → original filename → stored filename identity fallback;
- zero references says `No known references`, never `unused`;
- non-public rows say the reference count is unavailable rather than
  misreporting zero;
- nonzero references use localized cardinality;
- primary reason is persistent and additional reasons are counted;
- technical facts follow usage and issue content;
- top-level actions are the visible `edit` details button plus one accessible
  action group;
- all six stable flat action names remain available;
- record actions render after content;
- mutation authorization tests still pass;
- the existing reference query and filesystem-probe budgets still pass.

The existing Media gallery browser story must prove Hebrew RTL and English LTR
at desktop and 390×844, visible localized details text, an accessible More
actions trigger, labelled dropdown items, one-column narrow layout, and no
horizontal overflow or JavaScript errors.

## Drift triggers

Return to a fresh Simplifier Stage 1 before adding:

- a new Resource page or Livewire workspace;
- typed owner/effective-usage projections beyond the bounded reference count;
- task tabs, task counts, URL/return state, or global filesystem health counts;
- changed policy or referenced shared-byte mutation;
- persistence, migration, cache, queue, dependency, or provider changes;
- Package 5 Files Discovery, move, lifecycle, Trash, restore, purge, or recovery
  authority.
