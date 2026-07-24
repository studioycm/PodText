# Media Operations UX3 Mini-task 2 Canonical Task Context Implementation Plan

> Execute only
> `MEDIA-OPS-UX3-M2-O2-CANONICAL-TASK-CONTEXT` from audit
> `LS-20260724-PODTEXT-MEDIA-OPERATIONS-UX3-M2-01`, under parent option
> `MEDIA-OPS-UX3-O2-PDF-CONTRACT-TARGETED-WORKSPACES`. Stop before Mini-task 3
> and Package 5.

## 1. Commands

No Artisan scaffold, migration, Composer, npm dependency, environment, or
production command is authorized or required.

The focused clean-baseline command already passed before application edits:

`php artisan test --compact tests/Feature/AppOwnedMediaResourceTest.php tests/Feature/MediaInventoryPickerReplacementTest.php`

Result: 36 tests, 473 assertions.

Continue sequentially:

1. add the focused Pest expectations and run
   `php artisan test --compact tests/Feature/MediaLibraryTaskContextTest.php tests/Feature/AppOwnedMediaResourceTest.php`;
2. verify the expected RED failures describe absent task/context behavior;
3. implement only the minimum enums, services, Resource page/table wiring,
   translations, and service registration specified below;
4. rerun the focused command to GREEN;
5. run focused Media authority/performance regressions selected from:
   `MediaInventoryPickerReplacementTest.php`,
   `MediaRelationshipPerformanceTest.php`,
   `MediaRecordScopeAndAuthorizationTest.php`,
   `MediaAttachmentConcurrencyTest.php`, and owner-image tests;
6. run the Media browser file serially:
   `php artisan test --compact tests/Browser/MediaResourceGalleryBrowserTest.php`;
7. run PhpStorm inspections on changed PHP files when a callable inspection
   tool is available;
8. perform requirements, Laravel Simplifier, security, performance, and diff
   reviews;
9. after the final file change, run the final gates in mandatory order:
   requirements sweep, `vendor/bin/pint --test`, `vendor/bin/filacheck`,
   `npm run build`, then full `php artisan test` last;
10. create the implementation commit with the handoff hash pending, then
    immediately create the docs-only handoff/ledger hash-stamp commit.

Never parallelize browser files or the full suite.

## 2. Models, enums, and persistence

### Persistence

No table, column, index, relationship, cast, setting, migration, model event,
cache, queue, journal, or dependency change.

Existing authority remains:

- `App\Models\Media`;
- `App\Models\MediaAttachment`;
- `App\Support\Media\MediaRecordScope`;
- `App\Support\Media\MediaReferenceFinder`;
- `App\Support\Media\MediaInventoryDiagnostics`;
- `App\Support\Media\PublicMediaDelivery`;
- current policies, attachment managers, admission services, and
  `MediaFilesystemMutationCoordinator`.

### Enum: Media Library task

- **Class:** `App\Enums\MediaLibraryTask`
- **Location:** `app/Enums/MediaLibraryTask.php`
- **Type:** string-backed enum
- **Cases / values:**
  - `All = 'all'`
  - `InUse = 'in_use'`
  - `NoDirectAttachment = 'no_direct_attachment'`
  - `NeedsAttention = 'needs_attention'`
  - `Recent = 'recent'`
- **Contracts:** `Filament\Support\Contracts\HasLabel`
- **Behavior:**
  - localized label through `admin.media_library.tasks.<value>`;
  - localized description through
    `admin.media_library.task_descriptions.<value>`;
  - no authorization, persistence, or mutation behavior.

### Enum: diagnostic reason

- **Class:** `App\Enums\MediaDiagnosticReason`
- **Location:** `app/Enums/MediaDiagnosticReason.php`
- **Type:** string-backed enum
- **Cases / values:**
  - `PortableIdentity = 'portable_identity'`
  - `StorageDisk = 'storage_disk'`
  - `MissingFile = 'missing_file'`
  - `AudienceDenied = 'audience_denied'`
  - `UnsanitizedSvg = 'unsanitized_svg'`
  - `Metadata = 'metadata'`
- **Contracts:** `Filament\Support\Contracts\HasLabel`
- **Behavior:** labels reuse the existing
  `admin.media_library.repair_<value>` translations.

## 3. Query and context services

### Exact diagnostic snapshot

- **Class:** existing `App\Support\Media\MediaInventoryDiagnostics`
- **Location:** `app/Support/Media/MediaInventoryDiagnostics.php`
- **Behavior:**
  1. emit enum values while retaining the public `array<int, string>` reason
     contract;
  2. add a request-memoized map of matching Media IDs for every exact reason;
  3. build the map with
     `MediaRecordScope::inventoryQuery()->select([...])->lazyById(250)`;
  4. select exactly:
     `id`, `disk`, `directory`, `visibility`, `name`, `path`, `width`,
     `height`, `size`, `type`, `ext`, `reference_key`, and `updated_at`;
  5. add `applyReasonFilter(Builder $query, ?MediaDiagnosticReason $reason)`:
     selected reason uses its ID list; null uses the unique union;
  6. use `whereIntegerInRaw()` for nonempty IDs and an always-false predicate
     for an empty set;
  7. make existing `applyNeedsRepairFilter()` delegate to the exact null-reason
     path so no stale incomplete diagnostic authority remains;
  8. keep `selectionBlockedReason()` and fresh mutation checks unchanged.

The snapshot is allowed only after explicit Needs Attention or reason
selection.

Performance acceptance is measured at 1, 10, 25, and 251 records. Normal task
requests perform zero diagnostic-snapshot storage decisions. An explicit
diagnostic snapshot performs at most one existence decision per inventory row
and one SVG content read per SVG row, with two inventory chunks at 251 rows.
The raw ID predicate may contain at most one integer per matching inventory row;
record both the matching-ID count and the conservative serialized forecast
`21 * matching IDs` bytes before closeout. A durable or materialized result is
outside this option.

### Settings identity candidates

- **Class:** existing `App\Support\Media\MediaReferenceFinder`
- **Location:** `app/Support/Media/MediaReferenceFinder.php`
- **Method:** `settingsIdentityCandidates(): array`
- **Return:**
  `array{paths: list<string>, reference_keys: list<string>}`
- **Behavior:**
  1. read only the existing `menu_config`, `about_page`, and `default_images`
     payloads;
  2. extract menu light/dark path and reference key;
  3. extract About block and nested block path/reference key;
  4. extract team profile path/reference key;
  5. extract every default-image family path/reference key;
  6. normalize paths through the existing safe normalizer;
  7. lowercase nonblank reference keys for case-insensitive query matching;
  8. return unique values only;
  9. cache decoded payloads for the request;
  10. expose bounded invalidation for that cache and any primed projections,
      called from the mutation coordinator's existing settings-cache
      invalidation boundary after a legacy transition rewrites settings;
  11. do not return translated reference strings or owner labels from this
      method.

### Media Library task query

- **Class:** `App\Support\Media\MediaLibraryTaskQuery`
- **Location:** `app/Support/Media/MediaLibraryTaskQuery.php`
- **Registration:** request-scoped in
  `App\Providers\AppServiceProvider::register()`
- **Dependencies:**
  `MediaRecordScope`, `MediaInventoryDiagnostics`, `MediaReferenceFinder`
- **Docs:**
  <https://laravel.com/docs/13.x/eloquent-relationships#querying-relationship-absence>
- **Methods / behavior:**
  - `apply(Builder $query, MediaLibraryTask $task): Builder`
    - All: unchanged supplied Builder;
    - In Use: one grouped union:
      - `whereHas('attachments')` on any disk;
      - settings candidate path or case-insensitive reference-key match on any
        disk;
      - public-disk `ContentGroup.cover_path` or
        `ContentItem.image_path` subquery match;
    - No Direct Attachment: `whereDoesntHave('attachments')`;
    - Needs Attention:
      `MediaInventoryDiagnostics::applyReasonFilter($query, null)`;
    - Recent: inclusive `whereBetween(created_at,
      [requestNow - 30 days, requestNow])`.
  - `applyReason(Builder $query, ?string $value): Builder`
    - blank returns unchanged;
    - exact enum applies its snapshot subset;
    - nonblank invalid value produces no rows.
  - `counts(): array{all: int, no_direct_attachment: int}`
    - use exactly two aggregate queries;
    - memoize the pair for the request;
    - do not read settings or storage and do not expose any other count.

The service modifies the passed Builder and never starts a replacement query
for row results.

### Canonical return context

- **Class:** `App\Support\Media\MediaLibraryContext`
- **Location:** `app/Support/Media/MediaLibraryContext.php`
- **Registration:** normal container autowiring; it contains no durable state
- **Dependency:** `CuratorImageUploadPolicy`
- **Constants:** context version `1`, search max `200`, page max `10_000`
- **Methods / behavior:**
  1. capture list task, MIME/reason filters, search, sort, page, and the actual
     record key;
  2. emit edit parameters only under `from[...]`;
  3. parse an incoming `from` array and normalize it as one unit;
  4. require exact task/MIME/reason/sort allowlists;
  5. accept search only when scalar, no control characters, and at most 200
     Unicode characters after trim;
  6. accept page only as a decimal integer from 1 through 10,000;
  7. always replace focus with the actual current Media key;
  8. any malformed context returns the All/page-1/default-sort state;
  9. reconstruct only native index parameters:
     `tab`, known `filters`, `search`, `sort`, `page`, and `focus`;
  10. never accept or output a raw URL, route, scheme, host, path, referrer, or
      browser-history instruction.

## 4. Resource and table changes

### Resource

- **Resource:** existing
  `App\Filament\Resources\Media\MediaResource`
- **Location:** `app/Filament/Resources/Media/MediaResource.php`
- **Behavior:** no route, model, authorization, base inventory query, form, or
  page registration change.

### List page and native tabs

- **Page:** existing
  `App\Filament\Resources\Media\Pages\ListMedia`
- **Location:** `app/Filament/Resources/Media/Pages/ListMedia.php`
- **Component:** `Filament\Schemas\Components\Tabs\Tab`
- **Docs:**
  <https://filamentphp.com/docs/5.x/resources/listing-records#adding-tabs-to-the-table>
- **Config / behavior:**
  1. add `getTabs()` in enum order;
  2. every tab uses `modifyQueryUsing()` and the shared task query;
  3. All and No Direct Attachment may call `badge()` from the shared memoized
     count pair only if the measured list-page ceiling remains green;
  4. badge acceptance is exactly two aggregate queries and no settings or
     storage reads; omit both badges if that budget or the full page budget
     fails, and use `deferBadge()` only if a measured separate request is
     preferable and remains within the same two-query aggregate ceiling;
  5. normalize invalid active task to All after `parent::mount()`;
  6. default active task is All;
  7. expose a URL-backed `focus` scalar, bounded/validated before use;
  8. expose the active localized task description;
  9. expose one method used by both record URL and Edit action URL to capture
     canonical context;
  10. expose exact reason application and active-constraint checks;
  11. preserve current-page `MediaReferenceFinder::prime()`;
  12. add a gray header Reset view action linking to the clean Resource index
      before the existing Upload action.

### Table task description

- **Table:** existing
  `App\Filament\Resources\Media\Tables\MediaTable`
- **Location:** `app/Filament/Resources/Media/Tables/MediaTable.php`
- **Component:** `Filament\Tables\Table`
- **Docs:** <https://filamentphp.com/docs/5.x/tables/overview>
- **Config:**
  - default sort `created_at`, direction `desc`;
  - localized default sort option says Added, newest first;
  - dynamic table description uses the selected task description;
  - dynamic empty heading and description distinguish a truly empty inventory
    from a constrained no-match state;
  - retain fixed 25-row pagination, content grid, selection, toolbar bulk
    action, and current record actions.

### MIME filter

- **Filter:** `type`
- **Component:** `Filament\Tables\Filters\SelectFilter`
- **Docs:** <https://filamentphp.com/docs/5.x/tables/filters/select>
- **Config:**
  - retain localized MIME label;
  - options remain
    `CuratorImageUploadPolicy::globalMimeTypes()`;
  - native control because the bounded set has four options;
  - custom query accepts only the same allowlist;
  - invalid nonblank values return no rows;
  - retain native removable indicator.

### Diagnostic reason filter

- **Filter:** `reason`
- **Component:** `Filament\Tables\Filters\SelectFilter`
- **Docs:** <https://filamentphp.com/docs/5.x/tables/filters/select>
- **Config:**
  - replaces the generic `needs_repair` toggle;
  - localized label `Attention reason`;
  - options are `App\Enums\MediaDiagnosticReason`;
  - native bounded six-option control;
  - query injects `ListMedia $livewire` and delegates exact reason application;
  - native removable localized indicator;
  - composes as an intersection with task, MIME, search, and sort.

### Added-date sort column

- **Column:** existing `created_at`
- **Component:** `Filament\Tables\Columns\TextColumn`
- **Docs:** <https://filamentphp.com/docs/5.x/tables/columns/text>
- **Config:**
  - localized label `Added`;
  - retain `dateTime('d/m/Y H:i', 'Asia/Jerusalem')`;
  - retain `sortable()`;
  - native content-grid sort selector exposes Added plus ascending/descending;
  - default is newest first and explicit URL values are
    `created_at:asc|created_at:desc`.

### Canonical details URLs

- **Targets:** table `recordUrl()` and existing record `EditAction`
- **Components:**
  `Filament\Tables\Table`,
  `Filament\Actions\EditAction`
- **Behavior:**
  - inject `Media $record` and `ListMedia $livewire`;
  - delegate both URLs to the same List page method;
  - preserve existing update authorization and details label;
  - include only canonical namespaced context parameters.

### Focus attributes

- **Component:** `Filament\Tables\Table::extraRecordLinkAttributes()`
- **Config:**
- deterministic ID `media-record-<integer-key>`;
- `autofocus` only for the validated returned focus record;
- no user-provided HTML, JavaScript, or arbitrary attribute value;
- return URL fragment uses the same server-derived ID.

Browser proof must establish the actual `document.activeElement`, not merely
the presence of a fragment or `autofocus`. It must also cover the case where
the originating record no longer matches the retained view: the constraints
remain intact and focus falls back without an error or filter weakening.

### Empty-state actions

- **Action:** `resetMediaView`
- **Component:** `Filament\Actions\Action`
- **Docs:** <https://filamentphp.com/docs/5.x/tables/empty-state>
- **Location:** first empty-state action only when constraints are active
- **Icon:** `Filament\Support\Icons\Heroicon::OutlinedArrowPath`
- **Color:** gray
- **Authorization:** same authenticated Resource access; no mutation
- **Behavior:** link to clean `MediaResource::getUrl('index')`.

- **Action:** existing `create`
- **Component:** `Filament\Actions\Action`
- **Behavior:** retain upload destination and current `create` authorization;
  it is not presented as the primary remedy for a constrained no-match state.

## 5. Edit Back, Cancel, and Save

### Validated context property

- **Page:** existing
  `App\Filament\Resources\Media\Pages\EditMedia`
- **Location:** `app/Filament/Resources/Media/Pages/EditMedia.php`
- **Property:** locked validated context array using
  `Livewire\Attributes\Locked`
- **Mount behavior:**
  1. call `parent::mount($record)`;
  2. parse only `request()->query('from')`;
  3. replace focus with `$this->getRecord()->getKey()`;
  4. store the normalized array.

### Back action

- **Action:** `backToMediaLibrary`
- **Component:** `Filament\Actions\Action`
- **Docs:** <https://filamentphp.com/docs/5.x/actions/overview>
- **Location:** Edit page header
- **Icon:** direction-neutral
  `Filament\Support\Icons\Heroicon::OutlinedPhoto`
- **Color:** gray
- **Visibility:** every authorized Edit page
- **Authorization:** inherited Edit page access
- **Behavior:** link to the canonical reconstructed Resource index URL and
  record fragment.

### Cancel action

- **Action:** existing stable name `cancel`
- **Component:** `Filament\Actions\Action`
- **Location:** Edit form footer
- **Color:** gray
- **Visibility / authorization:** inherited Edit page behavior
- **Behavior:**
  - use the same canonical return URL as Back;
  - do not use `previousUrl`, `document.referrer`, history back, a raw URL, or
    a JavaScript click handler.

### Save

- **Action:** inherited `save`
- **Component:** `Filament\Actions\Action`
- **Behavior:** unchanged; update only the current metadata whitelist, show the
  existing notification, and remain on Edit without a list redirect.

## 6. Localization

- **Locations:** `lang/en/admin.php`, `lang/he/admin.php`
- **Add matching keys for:**
  - task labels and task descriptions;
  - `Attention reason`;
  - `Added`;
  - default newest sort copy;
  - Reset view;
  - Back to Media Library;
  - true empty inventory and constrained no-match headings/descriptions.
- **Copy rules:**
  - Hebrew is primary and RTL;
  - Recent explicitly says rolling previous 30 days;
  - No Direct explicitly warns that settings, legacy, inherited, or effective
    use may remain and deletion safety is not implied;
  - Needs Attention explains that it is a diagnostic view and names the
    currently selectable diagnostic reasons;
  - avoid “unused”, “orphaned”, “safe to delete”, and false repair wording;
  - filenames, MIME, paths, dimensions, and reference keys retain LTR
    isolation.

## 7. Authorization and safety

No policy or Gate change.

- list access remains admin-only;
- card/details URLs remain governed by `update`;
- raw view/download and all existing mutations retain their current policy and
  fresh trusted-record behavior;
- task membership does not grant action eligibility;
- settings identity does not become attachment authority;
- diagnostic snapshot does not become mutation evidence;
- bulk delete remains unchanged and rechecks current policy/coordinator
  constraints;
- no external destination or raw return input is accepted.

## 8. Widgets and later workspaces

No widget, dashboard statistic, custom Page, new Livewire component, Care page,
repair action, result component, provider workspace, recovery workspace,
Files Discovery, Trash, restore, purge, or Package 5 control.

## 9. Tests

### `tests/Feature/MediaLibraryTaskContextTest.php`

Create focused tests using `RefreshDatabase`, fake local/public storage,
`Http::preventStrayRequests()`, current Filament panel setup, and uniquely
named fixture helpers.

#### Task query authority

- All includes managed, private, malformed, missing, and duplicate-path rows.
- The supplied Resource Builder retains `storage_identity_count`.
- In Use includes:
  - attached public row;
  - attached non-public row;
  - public podcast legacy cover;
  - public episode legacy image;
  - menu/About/team/default settings path and reference-key identities;
  - non-public settings identity as a broken known use;
  - duplicate public rows sharing a referenced legacy path.
- In Use excludes:
  - fully unreferenced row;
  - non-public compatibility-only legacy path.
- No Direct excludes only rows with canonical attachments.
- Legacy-only, settings-only, and unreferenced rows remain in No Direct.
- Explicitly assert overlap between In Use and No Direct.

#### Counts and performance

- if badges pass measurement, exact All and No Direct badge values;
- if badges do not pass, both are omitted and the measured reason is recorded;
- count result exposes only those two keys when invoked;
- repeated count access in one request executes no additional aggregates;
- counts do not read settings or storage;
- at 1, 10, 25, and 251 rows, badge aggregates are at most two and In Use is
  one settings payload query plus one result query, independent of fixture
  count;
- no full diagnostic scan occurs for normal tasks without a reason;
- explicit Needs/reason performs one lazy diagnostic snapshot: one inventory
  query through 250 rows and two through 251 rows, one existence decision per
  candidate, and at most one content read per SVG candidate;
- displayed-card file decisions reuse the snapshot’s full cache key and add no
  repeated existence or SVG reads for records already scanned;
- normal list rendering retains the existing 25-card filesystem ceiling;
- record the matching-ID count and conservative `21 * count` raw-list byte
  forecast; no unbounded hidden second list is permitted.

#### Diagnostics

- fixture coverage includes all six reason values;
- Needs Attention equals the unique union;
- exact reason selection returns every and only row containing that reason;
- an otherwise managed malicious SVG appears under `unsanitized_svg`;
- multi-reason rows appear once;
- empty result creates a valid zero-row Builder;
- MIME and reason intersect.
- task, MIME, reason, search, sort, and page compose in one Resource test;
- removing one native indicator preserves task/search/sort, resets pagination
  as Filament specifies, and does not weaken the remaining constraint;

#### Recent and sort

- freeze request time;
- include exactly request time minus 30 days;
- exclude one second before;
- include request time;
- exclude one second in the future;
- assert newest/oldest `created_at` sorting and deterministic key fallback.

#### Canonical context

- round-trip exact valid task, MIME, reason, search, sort, page, and actual
  focus;
- direct edit without context returns All/page 1/default newest;
- reject unknown task/reason/MIME, array values, unknown filters, invalid sort,
  page zero/negative/decimal/scientific/overflow, search bound-plus-one,
  controls, forged focus, and malformed context version/shape;
- unknown query keys and `return`, `return_url`, `redirect`, protocol-relative,
  external, `javascript:`, `data:`, encoded-host, and malicious Referer values
  never propagate;
- direct native list abuse (`tab[]=x`, `sort[]=x`, `search[]=x`,
  `filters[type][value][]=x`, and `focus[]=1`) never produces a 500 and
  normalizes to the canonical All/default state;
- Back and Cancel share the same reconstructed local URL;
- Save updates allowed metadata and asserts no redirect.

### `tests/Feature/AppOwnedMediaResourceTest.php`

Update existing Resource integration assertions:

- replace generic `needs_repair` filter expectations with the five task tabs
  and exact reason filter;
- assert only All and No Direct tabs have badges if the measured badge path is
  accepted, otherwise assert no task badges;
- assert task descriptions and constrained empty copy;
- assert MIME/reason filters and removable indicators;
- assert record body and visible Edit action have identical context URLs;
- preserve card hierarchy, action names, 25-row pagination, selection,
  authorization, query ceiling, and page-bounded probes.

### `tests/Browser/MediaResourceGalleryBrowserTest.php`

Extend the serial real-browser matrix for Hebrew RTL and English LTR:

- five native task tabs and selected semantics;
- keyboard navigation between native task tabs;
- visible Recent 30-day meaning;
- No Direct warning without deletion claim;
- Needs Attention plus exact reason and MIME indicators;
- visible Added newest/oldest native sort;
- constrained empty state and keyboard-reachable Reset;
- bilingual filtered-empty state with task + MIME + reason + search + sort
  composed, then native indicator removal/reset behavior;
- page/context URL updates;
- Open details then explicit Back restores task/filter/search/sort/page and
  actual keyboard focus to the originating native record link;
- Cancel uses the same destination and restores actual focus;
- when the origin no longer matches, Back/Cancel retain the constrained view,
  do not error, and use a safe focus fallback;
- filters, sort, Back, and Cancel expose meaningful accessible names;
- the direction-neutral Back icon remains correct in Hebrew RTL and English
  LTR;
- Save remains on Edit;
- 1280×900 and 390×844 retain readable controls, one narrow card column, no
  horizontal overflow, and no JavaScript errors.

### Focused regression files

Run applicable existing coverage for:

- complete inventory and selection eligibility;
- exact delivery/SVG boundaries;
- attachment authority and query shape;
- mutation authorization, stale checks, and shared-byte blocks;
- owner picker mutation-free selection and cancellation;
- immediate acquisition permanence.

## 10. Final documentation and commits

Create:

- `docs/phase-02/media-operations-ux3-mini2-canonical-task-context-handoff.md`;

Update:

- `docs/phase-02/current-project-state.md`;
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`.

The handoff must include:

- starting provenance and drift result;
- requirement classification;
- task semantics and overlap;
- exact context security contract;
- performance planes and measured results;
- files/tests changed;
- every command and result;
- mandatory gate results;
- numbered imperative Local Front Check steps;
- explicit Mini-task 3 and Package 5 stop.

Commit the implementation with an imperative allowed-prefix subject. Then
immediately stamp that implementation hash into the handoff and ledger in a
docs-only `docs: backfill media operations ux3 mini2 hash` commit. Do not push.
