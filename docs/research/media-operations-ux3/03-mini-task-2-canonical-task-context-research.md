# Media Operations UX3 Mini-task 2 Canonical Task Context Research

## Approved contract

- Parent audit: `LS-20260724-PODTEXT-MEDIA-OPERATIONS-UX-03`
- Parent option:
  `MEDIA-OPS-UX3-O2-PDF-CONTRACT-TARGETED-WORKSPACES`
- Mini-task audit:
  `LS-20260724-PODTEXT-MEDIA-OPERATIONS-UX3-M2-01`
- Approved Mini-task option:
  `MEDIA-OPS-UX3-M2-O2-CANONICAL-TASK-CONTEXT`
- Binding design contract: `PODTEXT-MEDIA-UX-CONTRACT-20260724-CORRECTED`
- Approved implementation slice: Mini-task 2 only
- Baseline: clean `main` at
  `69aa0ce3f4983be54a3d25124cf43ef3ee21b5d6`, 31 commits ahead of
  `origin/main`

The operator approval is the Laravel Simplifier Stage 2 authority for this
bounded slice. Mini-task 1 is the immediate predecessor. Mini-task 3, a complete
Care/fix/results workspace, Package 5, migrations, dependencies, production,
and push remain outside this authority.

## Binding Mini-task semantics

Mini-task 2 adds five native Media Library task views:

1. **All Media** (`all`) — every permanent Curator row;
2. **In Use** (`in_use`) — at least one current known use authority:
   - a canonical `media_attachments.media_id` reference;
   - a public-disk legacy `ContentGroup.cover_path` or
     `ContentItem.image_path` reference;
   - a current settings identity match by Media path or reference key;
3. **No Known Direct Attachment** (`no_direct_attachment`) — no canonical
   `media_attachments` row;
4. **Needs Attention** (`needs_attention`) — the exact union of the six
   current `MediaInventoryDiagnostics` reasons;
5. **Recent (30 days)** (`recent`) — `created_at` is inside the inclusive
   rolling interval from request time minus 30 days through request time.

The task sets intentionally overlap. A Media row referenced only through a
legacy owner path or settings can be both **In Use** and **No Known Direct
Attachment**. The latter must never be called unused, orphaned, safe to delete,
or equivalent Hebrew wording.

Settings identity is itself a known configuration use. It remains a known use
when the referenced Media is not publicly deliverable; the same row then also
belongs to Needs Attention through its delivery diagnostics. This prevents a
broken configured use from disappearing from the operational cohort.

## Mini-task boundary

This slice must deliver:

- the five native task tabs and stable URL keys;
- at most two bounded numeric badges, only for All Media and No Known Direct
  Attachment, and only if measured list-page budgets remain green;
- exact reason filtering for:
  `portable_identity`, `storage_disk`, `missing_file`, `audience_denied`,
  `unsanitized_svg`, and `metadata`;
- retained MIME filtering, search, pagination, selection, and visible
  created-date sorting;
- visible task meaning, honest empty states, removable filter indicators, and
  one-click reset;
- one allowlisted canonical return context from Library to Edit and back;
- explicit Back and Cancel destinations that reconstruct the Media Resource
  index instead of accepting a raw URL or browser history;
- originating-record focus restoration using a server-derived Media key;
- unchanged Save behavior: successful metadata Save remains on Edit;
- Hebrew RTL and English LTR copy and browser proof;
- measured separation between database counts, database predicates,
  full-inventory diagnostics, page-bounded file probes, and browser behavior.

It must not add:

- any Fix, Repair, Recheck, result, retry, next-issue, or Care action;
- a new Resource page or custom workbench;
- owner detachment, association repair, metadata backfill, visibility repair,
  file replacement, or other new mutation;
- Files Discovery, Trash, restore, purge, move, lifecycle state, or Package 5
  placeholder controls;
- a migration, index, cache, queue, dependency, or durable task snapshot.

## Package 1-4 authorities

| Authority | Mini-task 2 treatment |
|---|---|
| Every Curator row is visible in All Media | Preserved through the existing Resource inventory query |
| Needs Repair/Attention is diagnostic | Preserved and corrected to the exact existing six-reason union |
| `media_attachments.media_id` is direct owner authority | Used for In Use and No Direct predicates; never replaced by presentation strings |
| Curator `path` is file-location authority | Used only for current legacy/settings compatibility evidence |
| Gallery selection is mutation-free | Not touched |
| Upload/URL/Storage admission is immediately permanent | Not touched |
| Owner cancellation does not delete admitted Media | Not touched |
| Policies and `MediaFilesystemMutationCoordinator` own physical mutation | Not touched |
| Shared referenced bytes remain protected | Not touched |
| Safe delivery and fresh mutation checks remain authoritative | Task snapshots never become mutation authority |

## Current implementation evidence

### Complete inventory and native table state

`MediaResource::getEloquentQuery()` already starts from
`MediaRecordScope::inventoryQuery()` and retains every Curator row while
projecting `storage_identity_count`. The task layer must modify the supplied
Builder rather than start a narrower query, so this projection and inventory
contract survive.

Filament 5.7.3 `ListRecords` already exposes URL-backed native state:

- `filters`;
- `search`;
- `sort`;
- `tab`;
- the normal Livewire pagination `page`.

`HasTabs` applies `Tab::modifyQueryUsing()` to the table query and resets the
page when a task changes. Unknown string tab keys are a trap: installed source
leaves the query unmodified and can render an unselected tab. `ListMedia` must
therefore normalize every non-allowlisted tab to `all`.

The existing content-grid layout renders a native sort selector whenever a
layout column is sortable. The existing `created_at` column can therefore
remain the sole sort authority, with a clearer added-date label and default
newest-first copy.

### Current task and diagnostic gap

The existing generic `needs_repair` filter calls
`MediaInventoryDiagnostics::applyNeedsRepairFilter()`. That method is not the
exact union of `reasons()`:

- it includes rows outside the narrower managed scope;
- it adds configured-disk rows whose files are missing;
- it does not add an otherwise managed, existing SVG whose bytes produce the
  `unsanitized_svg` reason.

It also performs an implicit inventory-wide file-existence scan whenever the
filter is selected. Mini-task 2 replaces that UI filter with an explicit
reason select and makes the whole scan an explicit, request-scoped consequence
only of selecting Needs Attention or a reason.

The exact snapshot can remain inside the already request-scoped
`MediaInventoryDiagnostics`. A lazy 250-row traversal selects only the columns
used by `reasons()`, records matching IDs for every recognized reason, and
memoizes them for the request. Existing `PublicMediaDelivery` request caches
then prevent the selected page from probing the same file or SVG bytes again.

Normal All, In Use, No Direct Attachment, and Recent requests must not invoke
this inventory snapshot. Mini-task 1’s existing file decisions for the
currently rendered maximum of 25 cards remain intentional and unchanged.

### Direct attachment predicates

`Media::attachments()` is the canonical relation. The committed
`media_attachments_media_role_index` begins with `media_id`, supporting:

- `whereHas('attachments')` for direct use;
- `whereDoesntHave('attachments')` for no direct attachment;
- a bounded aggregate count for the No Direct badge.

No Direct is not the inverse of In Use. It says only that no canonical
attachment is known.

### Legacy and settings identity predicates

Current compatibility evidence lives in `MediaReferenceFinder`:

- public-disk `ContentGroup.cover_path`;
- public-disk `ContentItem.image_path`;
- `menu_config`, `about_page`, and `default_images` settings payloads;
- settings path and case-insensitive reference-key matches.

The current card finder returns translated strings for presentation and primes
them for only the current page. A whole-library task must not infer authority
from those localized strings. The finder needs one typed, read-only extraction
method returning normalized unique settings paths and lowercase reference
keys. The In Use query can then combine those bounded candidates with set-based
attachment and legacy-path predicates.

There are no indexes on `content_groups.cover_path`,
`content_items.image_path`, or `curator.created_at`. The approved option
therefore does not count In Use or Recent and does not add an index. Any future
index needs measured evidence and a fresh migration audit.

## Installed-version research

### Laravel Boost

Installed-version information confirmed:

- PHP 8.4;
- Laravel 13.21.1;
- Filament 5.7.3;
- Livewire 4.3.3;
- Pest 4.7.5;
- Tailwind CSS 4.3.3.

Version-scoped documentation and installed source confirmed:

- native Resource tabs use
  `Filament\Schemas\Components\Tabs\Tab::modifyQueryUsing()`;
- tab badges use `badge()` and expensive badges may use `deferBadge()`;
- native `SelectFilter` values use `filters[name][value]`, generate removable
  indicators, and reset to null;
- content-grid tables render a native sort column and direction selector for
  sortable layout columns;
- `emptyStateHeading()`, `emptyStateDescription()`, and
  `emptyStateActions()` accept closures;
- action and table URL callbacks can inject `$livewire` and `$record`;
- Eloquent `whereHas()`, `whereDoesntHave()`, subqueries, and aggregate counts
  provide the required database predicates.

The two approved badge aggregates are cheap, indexed or primary-table counts
and will be measured synchronously. `deferBadge()` is therefore retained as an
available fallback, not introduced by default.

Relevant documentation:

- <https://filamentphp.com/docs/5.x/resources/listing-records#adding-tabs-to-the-table>
- <https://filamentphp.com/docs/5.x/tables/filters/select>
- <https://filamentphp.com/docs/5.x/tables/layout>
- <https://filamentphp.com/docs/5.x/tables/empty-state>
- <https://filamentphp.com/docs/5.x/actions/overview>
- <https://laravel.com/docs/13.x/eloquent-relationships#querying-relationship-absence>

### FilamentExamples

The configured MCP exposes search results and code snippets but no separate
source/detail reader. Two multi-query passes were used.

| Example | Evidence | Pattern used | Adaptation |
|---|---|---|---|
| Complex Orders Table | `ListOrders::getTabs()` snippet | native `Tab::modifyQueryUsing()` and badges | PodText exposes badges only for the two approved cheap cohorts |
| Natural-Language AI Search Participants Table | filter and indicator source snippet | canonical `tableFilters` shape and removable `Indicator` behavior | PodText keeps only MIME and exact diagnostic reason |
| Table Rendered as a Card Grid | native content-grid snippet | retain the existing native grid and controls | no custom card/list shell |
| Complex Filters examples | `SelectFilter` and query snippets | filters compose through Builder predicates | invalid values are rejected by PodText allowlists |

No example was treated as authority over installed source or the binding Media
contract.

### Filament Blueprint and audit checklists

The installed Blueprint planning overview, table, action, testing, and final
checklist guidance were applied. Full namespaces, locations, authorization,
behavior, test cases, and documentation links are specified in the companion
implementation plan.

The repository Filament forms UX checklist requires clear labels, descriptions,
empty states, preserved entered state, RTL/LTR review, and no accidental
mutation. The performance checklist requires separate database, filesystem,
Livewire, and browser claims; no wall-clock or DOM claim is inferred from query
counts.

## Chosen implementation design

### Finite task and reason values

Add backed enums:

- `App\Enums\MediaLibraryTask`;
- `App\Enums\MediaDiagnosticReason`.

Both provide localized Filament labels. Task descriptions explain cohort
meaning. The enums are the shared allowlists for tabs, query application,
filters, and return context.

### Task query service

Add request-scoped `App\Support\Media\MediaLibraryTaskQuery`.

It:

- applies a selected task to the Builder supplied by the Resource;
- returns that Builder unchanged for All;
- applies a set-based known-use predicate for In Use;
- uses `whereDoesntHave('attachments')` for No Direct Attachment;
- delegates Needs Attention and reason filtering to the exact request-local
  diagnostic snapshot;
- applies one request-stable inclusive 30-day `created_at` interval for Recent;
- memoizes exactly two aggregate counts: All and No Direct Attachment.

It never classifies selection eligibility, authorizes actions, or mutates
Media.

### Canonical safe navigation context

Add request-scoped `App\Support\Media\MediaLibraryContext`.

The only edit-query payload is a namespaced `from[...]` structure with a
version and these values:

- task: the five enum values;
- MIME: null or a current `CuratorImageUploadPolicy::globalMimeTypes()` value;
- reason: null or one of the six reason values;
- search: a trimmed scalar of at most 200 Unicode characters with no control
  characters;
- sort: null, `created_at:asc`, or `created_at:desc`;
- page: decimal integer from 1 through 10,000;
- focus: always overwritten with the actual clicked/current Media key.

Any missing, non-array, structurally invalid, unknown, overlong, or
out-of-range context becomes canonical All Media, page 1, default newest sort,
with only the actual current record as focus.

The service reconstructs native index parameters and never accepts or
propagates:

- a raw return URL;
- a scheme, host, or path;
- `previousUrl`;
- `Referer`;
- browser history;
- unknown filters or query parameters.

Both the card body and visible Edit action obtain their URL from one
`ListMedia` method, preventing context drift.

`EditMedia` stores only the validated context in a Livewire `#[Locked]`
property. One method reconstructs the local Resource index URL. Both a header
Back action and the form Cancel action use it. Save behavior remains inherited
and stays on Edit.

The return URL includes a fragment built only from the actual integer Media
key. The table adds the same deterministic ID to the native record link and
applies `autofocus` only when its validated `focus` value matches the rendered
record. Browser verification, rather than the fragment alone, determines
whether keyboard focus is restored.

### Task meaning and empty states

The native table header description explains the active task. In particular:

- No Direct says settings, legacy, inherited, or other effective use may
  remain and this view is not deletion proof;
- Needs Attention says the view is diagnostic and explains the exact reasons
  currently visible;
- Recent explicitly says the previous rolling 30 days by added date.

One Reset view action returns to the clean Media index and therefore clears
task, filters, search, sort, page, and focus together. A constrained empty
state says no records match the current view and offers Reset first. A truly
empty All Media state retains Upload.

## Performance contract

- Keep the 25-record page limit.
- Keep current-page `MediaReferenceFinder::prime()` behavior.
- Keep existing page-bounded preview/status decisions.
- Add no per-card query.
- Measure exactly two request-memoized aggregate badge queries.
- Add no badge/count for In Use, Needs Attention, a reason, or Recent.
- Keep settings extraction bounded to the three known payload rows and cache
  it for the request.
- Invalidate that request-local settings payload cache at the existing
  coordinator boundary whenever legacy transition rewrites settings; primed
  reference projections must be cleared with it.
- Use set-based database predicates for In Use and No Direct.
- Run a lazy full-inventory diagnostic snapshot only when Needs Attention or a
  reason is explicitly active.
- Select every field used by the diagnostic and public-delivery cache keys so
  explicit scan decisions are reused by rendered cards.
- Do not persist or serialize the diagnostic snapshot across requests.
- Do not serialize selected records into return context; bulk selection is
  intentionally excluded.
- Treat the unindexed Recent and legacy-path queries as known residual risk,
  not authority for an unapproved migration.

## Security and authorization contract

- No policy changes.
- No new mutation action.
- Admin Resource authorization remains unchanged.
- Task membership is evidence, not permission or mutation eligibility.
- Settings and legacy matches are usage evidence, not owner-write authority.
- The diagnostic snapshot is not accepted by mutation paths; fresh current
  record and filesystem checks remain required there.
- Every context input is allowlisted and bounded.
- Focus derives from the actual record.
- Back and Cancel only target `MediaResource::getUrl('index', ...)`.
- User-controlled schemes, hosts, paths, and raw redirects are never rendered.

## Verification design

Focused Pest coverage must prove:

- exact task keys, labels, descriptions, and invalid-tab fallback;
- All preserves every inventory row and Resource projections;
- In Use includes direct attachments on any disk, public legacy owner paths,
  and settings path/reference identities;
- non-public settings identity remains visible as In Use and diagnostic;
- No Direct is exactly attachment absence and overlaps In Use;
- badge values and two-query/request memoization;
- exact inclusive 30-day boundary and future exclusion;
- exact six-reason union, exact reason subsets, and unsafe-SVG inclusion;
- no full diagnostic scan for normal tasks without a reason;
- one diagnostic scan and request-cache reuse for explicit Needs/reason;
- MIME, reason, task, search, sort, and pagination composition;
- native removable indicators and Reset behavior;
- accurate empty-state and non-destructive wording;
- exact canonical edit and return parameters;
- malicious/malformed context fallback;
- no raw URL/referrer propagation;
- Back and Cancel share the canonical destination;
- Save remains on Edit;
- focus is server-derived and restored in the browser;
- Hebrew/English task/filter/action copy and narrow/desktop browser behavior;
- existing Mini-task 1 hierarchy, action, query, probe, policy, and mutation
  regressions remain green.

## Drift triggers

Return to a fresh Laravel Simplifier Stage 1 before:

- starting Mini-task 3 or adding any Fix/Repair/Recheck/result action;
- adding a custom Care/issue page or workbench;
- adding owner/effective-use destinations beyond this bounded task evidence;
- adding an index, migration, materialized count, cache, queue, or durable
  snapshot;
- changing policy, attachment, acquisition, delivery, or mutation authority;
- adding Files Discovery, lifecycle state, Trash, restore, purge, move, or any
  Package 5 control;
- touching local-development data/storage, production, dependencies, or push.
