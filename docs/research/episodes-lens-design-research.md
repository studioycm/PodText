# Episodes-Lens Design Research

Date: 2026-08-04. Session: episodes/nav mini-project DESIGN session (post-route
round chip `task_345ddb95`). Research supporting
`docs/phase-02/episodes-lens-design-spec.md`. This document records the
FilamentExamples MCP protocol run, the Boost `search-docs` API verification,
the installed-vendor verification, and the house-precedent inventory the design
builds on. Research + design only — no app code was changed.

This is a NEW research doc for the episodes-lens program.
`docs/research/filament-examples-phase-02.md` belongs to the phase-02 prompt
program and was deliberately not touched.

## Installed stack pins

- `filament/filament` **v5.7.5** (composer.lock, verified 2026-08-04).
- Livewire 4.3.4, Laravel 13.23.0 (per `dependency-pins-and-upgrades` memory;
  not re-derived here).
- Every API named in the design spec was verified against the 5.x docs via
  Boost `search-docs` AND/OR the installed vendor source at v5.7.5. The
  FilamentExamples corpus is v4-path-labelled, so nothing entered the spec on
  a corpus citation alone.

## FilamentExamples MCP protocol record

Tool exposure: the `filament-examples` MCP exposes **only `search-examples`**
(`queries[]`, `limit`); no source/read/fetch/details tool exists. Recorded per
the protocol's honesty rule — snippets returned inline are the full depth
available.

### Query batches

- **Batch 1** (limit 8): `list page tabs badge` · `table grouping group by` ·
  `select column inline edit status` · `toggle buttons filter`. Result: 8
  merged examples, 138K chars (saved to a session tool-result file).
  Coverage: the full 8-example index was read; detailed reads were the
  on-topic sections — ecommerce `ListOrders` + `OrdersTable` +
  `OrderBulkActions` + `OrderPageActions`, dynamic-checkbox `ProductsTable`,
  box-score `ManagePlayerStats` (head). The off-topic bodies (infolists,
  services, observers, sports-standings internals, homepage-sections forms)
  were skimmed by index only — recorded honestly.
- **Batch 2** (limit 3, refined): `defaultGroup group records collapsible` ·
  `SelectColumn change status table` · `filters layout above content
  collapsible` · `ActionGroup dropdown record actions`. Read in full.
- **Batch 3** (limit 3, refined): `groups Group make table records grouped by
  date` · `SelectColumn TextInputColumn editable table column` · `navigation
  group sort hide resource from navigation`. Read in full.

### Examples found and verdicts

| Example (corpus path) | Pattern found | Verdict | PodText adaptation notes |
|---|---|---|---|
| `v4/full-projects/ecommerce-admin-panel/.../Pages/ListOrders.php` | `getTabs()` returning key-named `Tab::make()` entries mixing enum-status scopes (`where('status', …)`) with derived-state scopes (`fulfillmentQueue()` model scope) | **Copy** (shape) | The episodes quick-scope set mixes raw status (draft/published) with derived visibility states (blocked-from-public) exactly this way; PodText routes the queries through a query service like `MediaLibraryTaskQuery`, not inline closures |
| `v4/full-projects/ecommerce-admin-panel/.../Tables/OrdersTable.php` | Date-range `Filter::make()->schema([DatePicker from/until])->query(...)`; amount-range filter; `defaultSort('created_at','desc')`; `toggleable(isToggledHiddenByDefault:)` tail columns | **Copy** (range-filter + defaultSort shapes) | The episodes list today has NO `defaultSort` — adopting one is a design item. Range filters must add `indicateUsing`/`Indicator::removeField` (house rule: custom filters need indicators) |
| same file, `shipping_country` SelectFilter | `->options(fn () => Order::query()->distinct()->pluck(...))` full-table distinct per render | **Reject** | Identical anti-pattern to the registered `embed_provider` watch item (`ContentItemsTable.php:162-169`, defect ledger research-watch row). The design must not copy it into new filters; the existing site is queued for repair in the spec |
| `v4/full-projects/ecommerce-admin-panel/.../Actions/OrderBulkActions.php` | Action-class returning `array` of `BulkAction`s; service-backed state transitions; skipped-count honesty in notifications (`"{n} skipped"`) | **Copy** | Matches the house `ContentImageActions` shape. The skipped-count notification is the model for bulk status changes over mixed-eligibility selections |
| `v4/full-projects/ecommerce-admin-panel/.../Actions/OrderPageActions.php` | Status-transition record actions guarded by `visible(fn => $record->status->isX())` | **Copy** (guard shape) | For publish/unpublish quick actions: visibility from record state, `requiresConfirmation` on the consequential direction |
| `v4/full-projects/repair-salon-crm/.../Tables/OrdersTable.php` | `ActionGroup::make([...])` wrapping ViewAction/EditAction/quick-modal-status-action/cancel with per-record `hidden()` | **Copy** (grouping shape) | The row-action overflow dropdown for the episodes list; note house `ResourceTableActions::iconOnly` operates on **ungrouped** actions only (`modifyUngroupedRecordActionsUsing`), so grouped actions keep labels inside the dropdown — desirable |
| `v4/full-projects/repair-salon-crm/.../Schemas/OrderForm.php` | `ToggleButtons::make('status')->inline()->options(Enum::class)` in a form | **Copy** (component reference) | Confirms enum-fed ToggleButtons — already house-proven by the Dashboard lens row |
| same file, customer Select | `getSearchResultsUsing()` + `getOptionLabelUsing()` capped server search | **Copy** | The house-sanctioned non-relationship select shape (`IconSelect` precedent; decorative-cap countermeasure) |
| `v4/tables/table-with-complex-filters/.../ProductsTable.php` | `FiltersLayout::AboveContent` + `filtersFormColumns(4)` + per-filter `columnSpan()` + `indicateUsing()`; debounced text filters | **Copy** (layout mechanics) | The candidate layout if filters move above the table; the `->preload()` on its SelectFilters is **rejected** (house default is inverted per Q7 — searchable-without-preload) |
| `v4/tables/table-as-grid-with-cards/.../UserResource.php` | `FiltersLayout::AboveContent` with schema-based filters; `User::pluck` options | **Partial** | Layout confirmed again; the unbounded `pluck` options are **rejected** (decorative-cap family) |
| `v4/tables/dynamic-table-columns-with-database-update/.../ProductsTable.php` | `CheckboxColumn` with `state()`/`updateStateUsing()` writing pivots; columns generated per DB row | **Reject** (dynamic per-row columns), **copy** (updateStateUsing mechanics reference) | Dynamic column generation does not fit; the inline-write mechanics inform the inline-edit column section |
| `v4/full-projects/box-score-form/.../Pages/ManagePlayerStats.php` | `TextInputColumn` on a custom `InteractsWithTable` page | **Reference only** | House already has the stronger precedent (`MediaTable:97-105` policy-gated `TextInputColumn`) |
| `v4/full-projects/log-table-and-global-search-queries/.../SearchLogsTable.php` | `->defaultGroup('resource')` (string form) over an aggregate query | **Reference only** | Only grouping usage in the corpus; the design needs `Tables\Grouping\Group` objects — verified via docs/vendor below |
| `v4/tables/sports-standings-tables/...` | Multiple per-group Livewire tables on one page | **Reject** | A table-per-podcast page contradicts the single-lens goal and would multiply query cost; grouping inside ONE table is the right mechanism |

### Not found in the corpus (three passes)

No example surfaced for: `SelectColumn` (by name), `Tables\Grouping\Group`
objects with `collapsible()`, list-tab **badges**, navigation
hiding/`shouldRegisterNavigation`, ToggleButtons used as a table-filter
control. These were verified via Boost docs + vendor source instead (below);
recorded per the protocol.

## Boost `search-docs` verification (Filament 5.x docs)

Two batches, 10 queries total, scoped to `filament/filament`. Confirmed:

| API | Docs evidence (5.x) |
|---|---|
| `getTabs()` / `getDefaultActiveTab()` on ListRecords; tab keys "persisted in the URL's query string"; label defaults from key | Resources → Listing Records |
| `Tab::modifyQueryUsing()` | Resources → Listing Records |
| Table grouping: `->groups([Group::make('…')->collapsible()])`, `collapsedGroupsByDefault()`, `groupingSettingsHidden()`, `groupingDirectionSettingHidden()`, `groupRecordsTriggerAction()`, `selectGroupsOnly()` | Tables → Grouping; Tables → Actions |
| Column manager (v5): `toggleable()`, `reorderableColumns()`, `deferColumnManager(false)`, `ColumnManagerLayout::Modal`, `columnManagerTriggerAction()`, `columnManagerResetActionPosition()`, `persistColumnsInSession(false)`, `columnManagerColumns(n)` | Tables → Columns → Overview |
| `SelectColumn` + `optionsRelationship(name:, titleAttribute:, modifyQueryUsing:)`; lifecycle `beforeStateUpdated`/`afterStateUpdated` | Tables → Columns → Select |
| **SelectColumn security**: "does not automatically check Laravel Model Policies … use the `disabled()` method to conditionally prevent editing" | Tables → Columns → Select → Security (also verbatim as a source comment, below) |
| `ToggleColumn` | Tables → Columns → Toggle |
| Filters: deferred-by-default ("By default, filter changes are deferred… `deferFilters(false)`" to go live), `filtersApplyAction()` | Tables → Filters → Overview |
| `FiltersLayout::AboveContent` / `::Modal` (+ slideOver via trigger-action API) | Tables → Filters → Layout |
| Filter indicators: `indicator()`, `indicateUsing()` returning `Indicator` objects with `removeField()`; no-indicator filters are excluded from the active-count badge | Tables → Filters → Custom |
| `ActionGroup::make([...])` in `recordActions`, nestable | Tables → Actions; Actions → Grouping Actions |
| `modifyUngroupedRecordActionsUsing()` via `Table::configureUsing` | Tables → Actions → Global record action settings |
| `shouldRegisterNavigation` (property + method) — **docs warn it only hides the link; access control stays with authorization** | Navigation → Overview |
| Cluster sub-nav: `SubNavigationPosition::Top`, `$shouldRegisterSubNavigation` | Navigation → Clusters |
| Schema `Tab` badge deferral `deferBadge()` (requires closure badge + keyed Tabs) | Schemas → Tabs |
| Relation-manager badge deferral `$isBadgeDeferred` | Resources → Managing Relationships |
| Summaries incl. group summaries and `summaries(pageCondition:, allTableCondition:)` | Tables → Summaries |

## Installed-vendor verification (v5.7.5 source)

| Fact | Evidence |
|---|---|
| `FiltersLayout` cases: `AboveContent`, `AboveContentCollapsible`, `BelowContent`, `BeforeContent`, `AfterContent`, `BeforeContentCollapsible`, `AfterContentCollapsible`, `Dropdown`, `Modal`, `Hidden` | `vendor/filament/tables/src/Enums/FiltersLayout.php` |
| `filtersFormColumns(int\|array\|Closure\|null)` | `tables/src/Table/Concerns/HasFilters.php:117` |
| **Deferred filters default ON**: `protected bool\|Closure $hasDeferredFilters = true` | `tables/src/Table/Concerns/HasFilters.php` |
| Filter session persistence default OFF: `$persistsFiltersInSession = false` | same file |
| `Group` methods: `collapsible()`, `date()`, `getTitleFromRecordUsing()`, `orderQueryUsing()`, `titlePrefixedWithLabel()` | `tables/src/Grouping/Group.php:69-163` |
| `groups(array\|Closure)`, `defaultGroup(string\|Group\|Closure\|null)`, `groupsOnly()`, `collapsedGroupsByDefault()`, `groupingSettingsHidden()` | `tables/src/Table/Concerns/CanGroupRecords.php:62-100` |
| `SelectColumn` source carries the security comment verbatim ("saves directly without checking Laravel Model Policies. Use `disabled()`…"); concerns: `CanDisableOptions`, `CanBeValidated`, `CanUpdateState`, `HasEnum`, `HasOptions`; `$isNative = true`, `$areOptionsPreloaded = false` | `tables/src/Columns/SelectColumn.php:40-60` |
| Column `disabled(bool\|Closure)` | `tables/src/Columns/Concerns/CanBeDisabled.php:13` |
| `ListRecords` `#[Url]` bindings: `reordering`, `filters`, `grouping`, `search`, `sort`, `tab` (→ `$activeTab`) — bare keys by design | `filament/src/Resources/Pages/ListRecords.php:33-55` |
| Schema `Tab` composes `HasBadge`, `HasBadgeTooltip`, `HasIcon`, `HasIconPosition`, `HasLabel` | `schemas/src/Components/Tabs/Tab.php:24-30` |
| `RecordActionsPosition` cases: `AfterCells`, `AfterColumns`, `AfterContent`, `BeforeCells`, `BeforeColumns` | `tables/src/Enums/RecordActionsPosition.php` |
| `NavigationGroup::collapsed()` and `collapsible()` | `filament/src/Navigation/NavigationGroup.php:48,57` |

## House precedent inventory (what the design reuses instead of inventing)

- **Enum-backed list tabs with narrowing and batched badge counts** —
  `ListMedia::getTabs()` builds tabs from `MediaLibraryTask::cases()` with
  `modifyQueryUsing` through `MediaLibraryTaskQuery`, badges from one
  `counts()` pass, `updatedActiveTab()` narrows via `tryFrom ?? All`
  (`app/Filament/Resources/Media/Pages/ListMedia.php:137-171`). This is the
  raw-state-narrowed, service-computed quick-scope pattern the spec adopts.
- **Policy-gated inline-edit column** — `MediaTable`'s `TextInputColumn::make('title')
  ->rules([…])->disabled(fn (Media $r): bool => ! Gate::allows('update', $r))`
  (`app/Filament/Resources/Media/Tables/MediaTable.php:97-105`). The only
  inline-edit column in the app; the sanctioned authorization shape given the
  vendor's no-policy-check contract.
- **Lens ToggleButtons with door-narrowed writes** — the Dashboard's grouped
  `ToggleButtons` lens row + `FILTER_KEYS` allowlist +
  `DashboardLens::fromFilter` (`app/Filament/Pages/Dashboard.php:33-118`).
- **Global admin-table defaults** (`app/Providers/AppServiceProvider.php`):
  `RecordActionsPosition::BeforeColumns` for all admin tables;
  `queryStringIdentifier` auto-namespacing for every non-`ListRecords` table
  (ListRecords keeps Filament's bare `#[Url]` keys deliberately — static
  bindings an identifier cannot rename); Q7 preload inversion —
  `Select`/`SelectFilter` global default `preload(false)->optionsLimit(50)`,
  bounded sets opt in per-site; action modal width defaults
  (confirmation → `Medium`, other → `SevenExtraLarge`).
- **Icon-only ungrouped record actions** — `ResourceTableActions::iconOnly`
  enforces a semantic icon per ungrouped action and converts to
  icon-button+tooltip (`app/Filament/Resources/Support/ResourceTableActions.php`).
  Operates via `modifyUngroupedRecordActionsUsing`, so `ActionGroup` members
  are untouched (they render labelled inside the dropdown).
- **Central navigation map + structural guard** — `AdminNavigationOrder::ITEMS`
  + `panelNavigation(NavigationBuilder)` with panel-context pinning;
  `AdminPhase02ResourcesTest` ("orders every registered admin navigation
  resource and page through the central map") asserts order, groups, labels
  and no untracked navigation surfaces. Any navigation change lands in the map
  and re-pins that test.
- **Workspace/classic action naming convention** (NAV1): workspace actions are
  the defaults («עריכה», «פרק חדש»), classic Filament CRUD actions carry
  «(מערכת)».
- **defaultSort precedents** — `MediaTable` (`created_at desc` +
  `defaultSortOptionLabel`), `PublicFormSubmissionsTable`
  (`submitted_at desc`), `SettingsBackupsTable` (`id desc`). The three content
  tables (episodes, podcasts, transcriptions) have none.
- **First-in-house features** (no precedent anywhere in `app/`): table
  `->groups()` grouping, `reorderableColumns()`/column-manager customization,
  `filtersLayout()`, `SelectColumn`/`ToggleColumn`, record-action
  `ActionGroup`. Each enters via this design with its Boost/vendor
  verification above.

## Model/contract facts pinned for the design

- **Public visibility** (`ContentItem::scopePublished`,
  `app/Models/ContentItem.php:277-288`): status `Published` AND
  (`published_at` null **or** ≤ now) AND group published AND a published
  transcription exists. Corollaries: a null `published_at` publishes
  immediately; a future `published_at` is scheduling; flipping status alone
  is a complete publish lever (no date automation exists — `booted()` only
  mints `reference_key`, uniquifies slug, defaults status to Draft, and
  validates featured-transcription ownership, lines 379-411).
- **Single-transcription lens** (LENS1): in `single` mode (current default)
  the standalone Transcriptions resource is scoped to one current row per
  episode; history is a super-admin-only filter. The episode ontology labels
  ride `TranscriptionModeLabel`.
- **Pinning**: `is_pinned` + `pinned_at`/`pinned_until` window + `pin_order`
  (`isCurrentlyPinned()`, `scopeCurrentlyPinned`, `scopeOrderedForPins`).
- **Effective-transcription ordering hooks** exist for sorting by transcript
  recency (`scopeOrderByEffectiveTranscriptionPublishedAt`, lines 350-377) —
  correlated subselects, usable as a table sort.
- Local scale evidence (dev DB, indicative only): 6 podcasts, 10 episodes,
  18 transcriptions, 0 drafts, 3 users. Production is the filling era
  (dashboard program evidence); design targets tens→low hundreds of episodes,
  single-digit→tens of podcasts, 2-3 admin users.
