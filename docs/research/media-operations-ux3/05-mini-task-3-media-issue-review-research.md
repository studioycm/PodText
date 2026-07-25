# Media Operations UX3 Mini-task 3 Media Issue Review Research

## Approved contract

- Laravel Simplifier audit:
  `LS-20260725-PODTEXT-MEDIA-OPERATIONS-UX3-M3-01`
- Approved option:
  `MEDIA-OPS-UX3-M3-O1-ROUTE-FIRST-ISSUE-REVIEW-NO-RECHECK`
- Binding direction: dedicated Media Issue Review surface
- Approved implementation slice: Media Operations UX3 Mini-task 3 only
- Checkout at Stage 2 entry:
  `main` at `cc634cb02a922c9bf165bb5951ae32c5654fd564`,
  33 commits ahead of `origin/main`
- Required predecessors:
  - Mini-task 1:
    `0e42ea47d2813141fa8583fc36532c3a85250c33`
  - Mini-task 2:
    `ce97b3d9350db966073b1fc224046d4a25cbfa68`

The operator's approval is the Laravel Simplifier Stage 2 authority for this
bounded slice. The first new reason-specific Media repair mutation, Recheck or
Retry, Package 5, Mini-task 4, migrations, dependencies, production, and push
remain outside this authority.

## Stage 2 baseline and ownership

At approval recheck, tracked and staged diffs were empty. The only worktree
entries were the six pre-existing operator-owned untracked
`ux-design-thinking` paths and symlinks under `.agents`, `.ai`, `.claude`, and
`.junie`. They are outside this delivery and must remain untouched.

The installed runtime reports:

- PHP 8.4;
- Laravel 13.21.1;
- Filament 5.7.3;
- Livewire 4.3.3;
- Pest 4.7.5;
- Tailwind CSS 4.3.3.

The unchanged Git baseline and installed package state do not introduce a
migration, dependency, persistence, or authority drift from the approved
audit.

## Binding product outcome

Mini-task 3 must let an authorized Media operator:

1. see stable Media identity and a concise, truthful issue indication on the
   existing Media details page;
2. follow an explicit **Review issues** route to a dedicated Resolve surface;
3. understand every current issue's cause, consequence, observed technical
   facts, bounded known impact, and evidence limits;
4. follow only existing authorized owner and file routes;
5. understand that an owner route may replace an owner's presentation without
   repairing the broken Media record;
6. see an explicit blocker because this slice adds no Media-record repair
   authority;
7. close to the current Media details page, continue to the next issue in the
   same task cohort, or return to the exact originating Media Library
   task/filter/search/sort/page/card context.

The existing Media details form remains **Describe**. The dedicated Issue
Review page is **Resolve**. Saving title, alternative text, caption, or
description must never be represented as repairing a stored-file diagnostic.

## Protected Media authorities

| Authority | Mini-task 3 treatment |
|---|---|
| All Media is complete Curator inventory | Preserve `MediaRecordScope::inventoryQuery()` |
| Needs Attention is diagnostic | Reuse the exact six-reason `MediaInventoryDiagnostics` result |
| `media_attachments.media_id` is owner authority | Use only canonical attachment rows for owner routes |
| Curator `path` is file-location authority | Show it as technical evidence; never infer ownership from it |
| No Known Direct Attachment is not unused | Repeat the evidence limitation on Issue Review |
| Gallery selection is mutation-free | Not touched |
| Upload/URL/Storage admission is immediately permanent | Not touched |
| Existing view/download policies authorize file routes | Reuse them; do not add an ability |
| Owner Resource update policy authorizes owner routes | Reuse it; do not add an ability |
| Physical mutations remain coordinator-owned | No new mutation calls |
| Package 5 owns future lifecycle controls | No discovery, move, Trash, restore, purge, or lifecycle controls |

## Current implementation evidence

### Media Library and return context

Mini-task 2 already provides:

- five finite task tabs;
- exact MIME and diagnostic-reason filters;
- title/name search;
- deterministic `created_at` plus Media-key sorting;
- URL-backed pagination;
- one exact, allowlisted version-1 `from[...]` context;
- a server-derived focus key for the initial Library-to-details transition;
- explicit Back and Cancel URLs reconstructed from Resource state.

`MediaLibraryContext::fromInput()` intentionally replaces a submitted focus
with the current record key. That remains correct for a normal
Library-to-details transition and is how the origin focus first becomes
server-derived.

Issue Review needs to preserve that already normalized origin across **Next
issue** and a later Close-to-details transition. Passing the focus back as an
ordinary query value would weaken the Mini-task 2 invariant. The bounded
solution is a Laravel-encrypted, request-only continuation token generated
from the locked normalized context. The token:

- contains only the same exact eight-key version-1 state;
- is decrypted and fully revalidated before use;
- fails back to All/page 1/current-record focus when absent, malformed, or
  forged;
- contains no URL, route name, host, path, referrer, ability, or mutation
  input;
- creates no durable state or new access authority.

The normal `fromInput()` behavior remains unchanged.

### Media details is descriptive

`EditMedia`:

- is authorized through the existing Media `update` policy;
- sends only `alt`, `title`, `caption`, and `description` to the Livewire
  form;
- saves only those same four fields;
- remains on Edit after a successful Save;
- already owns explicit Back and Cancel destinations.

`MediaForm` currently renders a flat descriptive metadata section. A
schema-inserted, read-only Blade summary can establish the approved hierarchy
without replacing the edit page:

1. stable identity and safe preview/file availability;
2. concise primary issue plus additional-issue count;
3. explicit Review issues link when issues exist;
4. clearly labelled descriptive fields and explicit non-repair copy.

No technical Curator attribute becomes writable.

### Diagnostic facts

`MediaInventoryDiagnostics::reasons()` is the current source of truth and emits
these stable reasons, in order:

1. `portable_identity`;
2. `storage_disk`;
3. `missing_file`;
4. `audience_denied`;
5. `unsanitized_svg`;
6. `metadata`.

The `metadata` reason is technical. It evaluates stored-file facts through
`MediaRecordScope::allowsForBackfill()` after removing delivery and portable
identity concerns. Relevant observed facts include stored path/name, MIME,
extension, byte size, width, and height. The descriptive fields edited by
`EditMedia` are not inputs to this diagnostic.

The Review presenter should project the existing reason result, not copy or
reimplement its decision logic. Reason cards may explain the current
diagnostic and show observed facts, but must acknowledge when the diagnostic
does not identify one independently repairable field.

### File routes

`AdminMediaFileController` already:

- resolves complete inventory through `MediaRecordScope`;
- reauthorizes `view` or `download`;
- verifies the configured disk and current file existence;
- blocks unsafe inline SVG through `PublicMediaDelivery`;
- streams with restrictive headers.

Issue Review may link to:

- **View file** only when a safe inline preview URL currently exists;
- **Download file** only when the configured file currently exists and the
  actor has the existing download ability.

These links inspect or save current bytes. They are never labelled repair
actions and do not imply that the Media record was repaired.

### Owner routes and bounded impact

Canonical direct owner evidence comes only from
`media_attachments.media_id`. Current supported pairs are:

- `content_group` plus `cover` → `ContentGroupResource` Edit;
- `content_item` plus `primary_image` → `ContentItemResource` Workspace.

The presenter can resolve these with one attachment query and at most one
owner query per supported type. It must:

- omit a route when the owner no longer exists;
- omit a route when the current actor cannot edit that owner Resource;
- count unsupported or unresolved attachment rows as an evidence limitation;
- open owner routes in a new tab so the Issue Review context remains in place.

For a missing file, an owner route may let the operator choose a different
owner image and restore that owner's presentation. It does not recreate the
missing bytes or repair the broken Media record.

`MediaReferenceFinder` already recognizes public legacy owner paths and the
bounded public settings families. A focused non-attachment projection can
reuse its existing settings/path readers to show compatibility/configuration
evidence separately from canonical owners. These strings are context evidence,
not attachment authority, owner route authority, usage completeness, or
deletion safety.

### Next issue

`MediaLibraryTaskQuery` already owns every task predicate and exact diagnostic
reason predicate. The smallest coherent continuation reuses that service to
find one next issue:

1. start from complete inventory;
2. apply the originating task;
3. apply MIME, diagnostic reason, and the Media Library's split-term and
   quoted-phrase title/name search semantics from the normalized origin
   context;
4. require an issue when the originating task/reason does not already do so;
5. use the normalized explicit sort, or default newest-first;
6. compare `(created_at, id)` after the current record in that direction;
7. return one row and never wrap.

The original page number is irrelevant to queue selection but remains
unchanged for the exact Library return URL. This is a request-time,
non-mutating query, not a durable task snapshot.

## Approved route semantics

| Reason | Current action semantics |
|---|---|
| `portable_identity` | Honest blocker; no repair route |
| `storage_disk` | Honest blocker; no repair route |
| `missing_file` | Canonical owner routes when authorized; they may repair owner presentation only |
| `audience_denied` | Honest blocker; no repair route |
| `unsanitized_svg` | Honest blocker; safe Download may remain available, but is not repair |
| `metadata` | Honest blocker; descriptive Save is explicitly not repair |

There is no generic **Fix**, reason-specific Media mutation, **Recheck**,
**Retry**, Files Discovery, move, Trash, restore, purge, or Package 5
placeholder.

## Installed-version research

### Laravel Boost

Version-scoped documentation and installed source confirmed:

- a record-backed custom Resource page uses
  `Filament\Resources\Pages\Concerns\InteractsWithRecord`;
- its route declares `{record}` in `Resource::getPages()`;
- `resolveRecord()` retains the Resource's route-binding and inventory query;
- a custom page must explicitly apply record authorization on mount and
  hydration, following `ViewRecord`;
- custom Resource Blade views use `<x-filament-panels::page>`;
- a schema `View` component inserts a Blade view and supports `viewData()`;
- the schema Blade receives the current `$record`;
- custom read-only pages that inherit schema interaction should use
  `RestrictsFileUploadsToSchemaComponents`;
- Livewire `#[Locked]` properties are appropriate for record/navigation state
  that must not be client-overwritten;
- Eloquent grouped predicates and deterministic secondary-key ordering support
  the bounded next-row query;
- Pest browser tests support 390-pixel resizing, keyboard interaction, smoke,
  console, JavaScript, and accessibility assertions.

Relevant documentation:

- <https://filamentphp.com/docs/5.x/resources/custom-pages#using-a-resource-record>
- <https://filamentphp.com/docs/5.x/schemas/custom-components#inserting-a-blade-view-into-a-schema>
- <https://filamentphp.com/docs/5.x/resources/editing-records#using-a-custom-blade-view>
- <https://filamentphp.com/docs/5.x/advanced/security#restricting-livewire-file-uploads-to-schema-components>
- <https://livewire.laravel.com/docs/4.x/locked>
- <https://laravel.com/docs/13.x/eloquent>
- <https://pestphp.com/docs/browser-testing>

### FilamentExamples

The configured MCP exposes search results and code snippets but no separate
source/detail reader. Two multi-query passes were used.

| Example | Evidence | Pattern used | PodText adaptation |
|---|---|---|---|
| GitHub-Style Profile View Page | custom `ViewRecord`, `getViewData()`, responsive custom Blade | prepare view data in the page class; use responsive sections | PodText uses a read-only presenter and no queries in Blade |
| Editable Box Score Stats Table | custom record Resource route with `InteractsWithRecord` | resolve `{record}` inside a Resource page | PodText adds explicit view authorization and no mutation methods |
| Teacher Payouts Attendance | Resource custom page and Resource-generated URLs | keep navigation within Resource URL helpers | PodText reconstructs only allowlisted origin state |
| User Profile Section | `<x-filament::section>` page composition | native sections and responsive flow | PodText uses semantic headings, RTL/LTR-safe grids, and bounded actions |
| Quiz custom page | Livewire locked record and custom page view | lock route-backed component state | PodText locks the normalized origin context and exposes no submit action |

No example is authority over installed source, current policies, or the binding
R4 product decision.

## Filament forms, performance, security, and UX findings

- The details summary is read-only and outside the editable state path.
- Labels, helper copy, causes, consequences, blockers, evidence limits, and
  actions require both Hebrew and English translations.
- Visible sections need semantic headings; icon-only primary actions are not
  appropriate.
- Owner and file links should remain real anchors for keyboard and assistive
  technology behavior.
- Technical identifiers and paths render LTR even inside Hebrew RTL.
- The review layout must collapse to one column at 390 pixels without
  horizontal overflow.
- The page performs no polling, eager client hydration, durable cache, or
  background work.
- Owner lookup is bounded by supported owner types, not attachment count.
- Storage decisions reuse the existing request-scoped
  `PublicMediaDelivery` cache.
- Absence of known evidence is never worded as unused or deletion-safe.

## Chosen implementation design

Add one focused read-only presenter and one Resource page:

- `App\Support\Media\MediaIssueReviewPresenter`
  - projects stable identity, safe file links, exact reasons, observed facts,
    canonical owners, non-attachment evidence, and evidence limitations;
  - authorizes every emitted route through existing policies;
  - performs no write and exposes no mutation callback.
- `App\Filament\Resources\Media\Pages\ReviewMediaIssues`
  - resolves inventory through the Resource;
  - authorizes existing Media view access on mount and hydration;
  - locks the normalized origin context;
  - emits Close, Next issue, and exact Library return URLs;
  - provides one prepared view-data array to Blade.

Extend, without replacing:

- `MediaForm` with the details summary and explicit Describe copy;
- `EditMedia` with Issue Review URL generation and encrypted continuation
  restoration for Review-generated details URLs;
- `MediaLibraryContext` with same-shape encrypted continuation methods;
- `MediaLibraryTaskQuery` with deterministic `nextIssue()`;
- `MediaReferenceFinder` with non-attachment evidence projection;
- `MediaResource` with `/{record}/review-issues`.

## Explicit deferrals

- every reason-specific Media-record repair mutation;
- Recheck and Retry;
- generic Fix;
- Package 5 discovery/lifecycle controls;
- owner-route return integration beyond opening in a new tab;
- migrations, indexes, dependencies, caches, queues, or task snapshots;
- broader refactoring of owner presentation or diagnostic architecture;
- Mini-task 4.
