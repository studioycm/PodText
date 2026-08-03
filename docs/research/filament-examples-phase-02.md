# Phase 02 FilamentExamples Research

This file records the Phase 02 research performed through the configured `filament-examples` MCP server. No MCP token, header, license value, Composer auth value, or local machine secret is recorded.

## MCP Access Proof

- MCP search tool used: `mcp__filament_examples.search_examples`
- MCP fetch/read/detail/source tool used: none exposed as a separate tool
- Deepest available access: source-level file paths and PHP/Blade snippets returned directly by `search_examples`
- Access conclusion: source-level snippets were available through the search tool. No separate fetch tool exists in the exposed MCP surface.

## Summary Table

| Feature | Best example(s) | Use now/later | Notes |
|---|---|---|---|
| Public Filament table in Livewire | Filament Table on a Public Livewire Page | Now | Use for public `ContentItem` listing/search component |
| Public custom Page with table/form | Multi-Panel Hotel Booking Application | Now | Confirms panel separation and `Page implements HasTable` patterns |
| Complex filters | Natural-Language AI Search Action, Complex filter examples | Now | Copy filter schemas, indicators, deferred/apply behavior; AI is future only |
| Card/grid table output | Table Rendered as a Card Grid, custom ViewColumn examples | Now | Use Filament table mechanics with Blade card rendering |
| Homepage sections | Manage Dynamic Homepage Sections | Now | Use ordered visible section records, not hard-coded homepage slices |
| Tags/categories | Blog CMS With Filament Admin | Now | Copy relationship UI patterns only; PodText categories remain custom hierarchical |
| Settings | E-Shop Admin With Bootstrap Storefront | Now | Use `SettingsPage` pattern for approved Spatie Settings implementation |
| Import/export | Inventory Stock with CSV Import/Export | Now | Keep native Importer/Exporter actions and relationship resolution |
| Dashboard widgets | E-Shop widgets, Quiz leaderboard | Later | Use simple `StatsOverviewWidget` and `TableWidget` editorial warnings |
| Lens-switched dashboard + filawidgets | Multi-Widget Analytics Dashboard with Presets | Now | The direct model for Prompt 13: preset-switched `getWidgets()`, range enum, filawidgets data layer |
| Embedded Livewire/sidebar | Livewire Status Sidebar In Edit Page | Later | Useful for future studio planning; do not implement studio now |
| Alpine in custom page | Quiz Application with Custom Take-Quiz Page | Later | Use only for local viewer controls, not persisted state |

## Example: Filament Table on a Public Livewire Page

- Source: `v4/tables/public-products-table/app/Livewire/Products.php`
- MCP search tool used: `mcp__filament_examples.search_examples`
- MCP fetch/read/detail/source tool used: none exposed separately; source snippets returned by search
- MCP fetched: no; source snippets returned by search_examples
- Access level: source snippets through search_examples
- Filament version: v4 example, adaptable to installed v5 concepts already used in this app
- Files/classes inspected: `App\Livewire\Products`, `ProductsTable`, `ProductResource`, `ProductExporter`
- Dependencies: Filament tables/forms/actions, Livewire component, Eloquent model query
- Why relevant: public homepage/search should render `ContentItem` records through a public Livewire component, not admin Resources and not `Transcription` records
- Filament concepts used: `Filament\Tables\Table`, `Filament\Tables\Contracts\HasTable`, `Filament\Forms\Contracts\HasForms`, `Filament\Actions\Contracts\HasActions`, `TextColumn`, `Filter`, `TextInput`
- Pattern to copy: class-based Livewire component with `InteractsWithTable`, explicit query, searchable columns, filters, and Blade table rendering
- Pattern to avoid: exposing admin Resource URLs as public frontend
- Testing ideas: `Livewire::test()` search/filter/sort/pagination and visibility rules
- Implementation risk: table card closures can cause N+1 queries if record relationships are not eager loaded
- Use now/later: now
- Adaptation notes for PodText: query `ContentItem` with published group and effective/main published transcription; render cards through Blade/ViewColumn
- Implementation prompt references: 11
- Confidence: high

## Example: Multi-Panel Hotel Booking Application

- Source: `v4/full-projects/hotel-management-bookings/app/Filament/Booking/Pages/FindHotel.php`
- MCP search tool used: `mcp__filament_examples.search_examples`
- MCP fetch/read/detail/source tool used: none exposed separately; source snippets returned by search
- MCP fetched: no; source snippets returned by search_examples
- Access level: source snippets through search_examples
- Filament version: v4 example, adaptable to installed v5 panel/page APIs
- Files/classes inspected: `BookingPanelProvider`, `HotelPanelProvider`, `FindHotel`, `find-hotel.blade.php`
- Dependencies: Filament panels, custom Pages, forms, tables, actions
- Why relevant: PodText already uses separate Admin and Public panels; Phase 02 should keep guest public pages separate
- Filament concepts used: `Page`, `HasTable`, `HasSchemas`, `HasActions`, `InteractsWithTable`, `InteractsWithSchemas`, `Action`
- Pattern to copy: custom Page composing a form and table in the page view
- Pattern to avoid: authenticated middleware on public panel pages
- Testing ideas: guest route access and admin route protection
- Implementation risk: using `records()` arrays loses query-level pagination/search; prefer `query()` for PodText search
- Use now/later: now
- Adaptation notes for PodText: use `Page` plus Livewire table component for homepage/search/category/tag pages
- Implementation prompt references: 11, 12
- Confidence: high

## Example: Natural-Language AI Search Action

- Source: `v4/full-projects/free-form-text-search/app/Filament/Resources/Participants/Tables/ParticipantsTable.php`
- MCP search tool used: `mcp__filament_examples.search_examples`
- MCP fetch/read/detail/source tool used: none exposed separately; source snippets returned by search
- MCP fetched: no; source snippets returned by search_examples
- Access level: source snippets through search_examples
- Filament version: v4 example, adaptable to installed v5 filters/actions
- Files/classes inspected: `ParticipantsTable`, `ListParticipants`, enum filters, AI search action
- Dependencies: Filament tables/actions/forms, enum options
- Why relevant: demonstrates complex custom filters, indicators, and controlled filter state
- Filament concepts used: `SelectFilter`, `Filter`, `Indicator`, `TextInput`, `TagsInput`, `deferFilters(false)`, header `Action`
- Pattern to copy: indicators for custom filters and explicit filter state application
- Pattern to avoid: adding AI search or transcript full-text search to default live search
- Testing ideas: active filter indicators, exact result filtering, clear/apply behavior
- Implementation risk: AI and broad text filters can produce expensive queries
- Use now/later: now for filters; AI later only
- Adaptation notes for PodText: default search is item title, group title, categories, enabled tags; transcript body search is explicit/deferred
- Implementation prompt references: 11
- Confidence: high

## Example: Table Rendered as Card Grid

- Source: `v4/tables/table-as-grid-with-cards`
- MCP search tool used: `mcp__filament_examples.search_examples`
- MCP fetch/read/detail/source tool used: none exposed separately; source snippets returned by search
- MCP fetched: no; source snippets returned by search_examples
- Access level: source snippets/summary through search_examples
- Filament version: v4 example, adaptable to v5 table layout
- Files/classes inspected: Resource/table snippets with `contentGrid`, `Grid`, `Split`, `Stack`
- Dependencies: Filament tables columns/layouts
- Why relevant: public search should feel like content cards while retaining table search/filter/sort
- Filament concepts used: content grid, image/text columns, record URL control, pagination page options
- Pattern to copy: use table layout primitives or `ViewColumn` to render rich cards
- Pattern to avoid: nested cards and query work inside card rendering closures
- Testing ideas: result order, cards render relationship metadata without extra queries
- Implementation risk: responsive card layouts can regress RTL/mobile if not tested
- Use now/later: now
- Adaptation notes for PodText: card displays item title, group badge, effective transcription date, author, categories, tags, duration, pin indicator
- Implementation prompt references: 11
- Confidence: medium-high

## Example: Custom-Designed Table With ViewColumn Cells

- Source: `v4/tables/table-customized-design-viewcolumn`
- MCP search tool used: `mcp__filament_examples.search_examples`
- MCP fetch/read/detail/source tool used: none exposed separately; source snippets returned by search
- MCP fetched: no; source snippets returned by search_examples
- Access level: source snippets/summary through search_examples
- Filament version: v4 example, adaptable to v5 `ViewColumn`
- Files/classes inspected: table class and custom Blade column snippets
- Dependencies: Filament tables, Blade views
- Why relevant: PodText needs custom content result cards while preserving Filament filter/sort/pagination
- Filament concepts used: `Filament\Tables\Columns\ViewColumn`, custom Blade column view
- Pattern to copy: keep query/filter logic in table class and presentation in Blade
- Pattern to avoid: embedding large HTML strings in PHP table definitions
- Testing ideas: Blade result card render tests plus Livewire table tests
- Implementation risk: table row actions/card links need accessible focus states
- Use now/later: now
- Adaptation notes for PodText: `resources/views/filament/tables/columns/public-content-item-card.blade.php`
- Implementation prompt references: 11
- Confidence: medium-high

## Example: Manage Dynamic Homepage Sections

- Source: `v4/full-projects/manage-homepage-sections`
- MCP search tool used: `mcp__filament_examples.search_examples`
- MCP fetch/read/detail/source tool used: none exposed separately; source snippets returned by search
- MCP fetched: no; source snippets returned by search_examples
- Access level: source snippets/summary through search_examples
- Filament version: v4 example, adaptable to v5 Resource/table patterns
- Files/classes inspected: `HomepageSectionResource`, section form/table snippets
- Dependencies: Filament Resource, table reorder/toggle patterns
- Why relevant: Phase 02 requires admin-managed homepage UX and custom homepage sections
- Filament concepts used: Resource, `ToggleColumn`, reorderable table, default sort, visible filter
- Pattern to copy: database-backed ordered visible sections with type, target, limit, order
- Pattern to avoid: separate pinned section as the only way to surface pinned items
- Testing ideas: visible sections render; hidden sections do not; section queries return public items only
- Implementation risk: section type logic can grow into a broad service; keep query methods focused
- Use now/later: now
- Adaptation notes for PodText: `HomepageSection` may target latest/category/tag/group, while pinning remains `ContentItem` only
- Implementation prompt references: 08, 09, 11
- Confidence: medium

## Example: Blog CMS With Filament Admin

- Source: `v4/full-projects/cms-blog-system`
- MCP search tool used: `mcp__filament_examples.search_examples`
- MCP fetch/read/detail/source tool used: none exposed separately; source snippets returned by search
- MCP fetched: no; source snippets returned by search_examples
- Access level: source snippets/summary through search_examples
- Filament version: v4 example, adaptable to v5 Resource/form patterns
- Files/classes inspected: blog post/category/tag Resource snippets
- Dependencies: Filament Resources and relationship fields
- Why relevant: shows ordinary category/tag relationship management
- Filament concepts used: `Select::make()->relationship()`, multiple relationships, category resources
- Pattern to copy: searchable/preloaded relationship selects
- Pattern to avoid: simple blog categories as a replacement for PodText hierarchy/inheritance rules
- Testing ideas: create/edit category, assign to group/item, public inheritance filter
- Implementation risk: confusing categories and Spatie tags
- Use now/later: now
- Adaptation notes for PodText: custom hierarchical categories plus Spatie typed flat tags; no duplicate custom tag pivot
- Implementation prompt references: 08, 09, 11
- Confidence: medium

## Example: E-Shop Admin With Bootstrap Storefront

- Source: `v4/full-projects/e-shop-admin-bootstrap-storefront`
- MCP search tool used: `mcp__filament_examples.search_examples`
- MCP fetch/read/detail/source tool used: none exposed separately; source snippets returned by search
- MCP fetched: no; source snippets returned by search_examples
- Access level: source snippets/summary through search_examples
- Filament version: v4 example, adaptable to v5 `SettingsPage` and widgets
- Files/classes inspected: `ManageSettings`, `GeneralSettings`, `StatsOverview`, latest-record `TableWidget`
- Dependencies: Spatie Laravel Settings, Filament widgets
- Why relevant: Phase 02 needs global homepage settings and editorial dashboard widgets
- Filament concepts used: `SettingsPage`, typed settings class, `StatsOverviewWidget`, `TableWidget`
- Pattern to copy: typed settings page and small editorial widgets
- Pattern to avoid: analytics dashboards or default polling without need
- Testing ideas: settings page save test, widget count test
- Implementation risk: settings package must be installed/approved before implementation
- Use now/later: now
- Adaptation notes for PodText: settings for item limits/layout; dashboard warnings for missing transcript/embed/category; Spatie Settings is approved for Phase 02 implementation
- Implementation prompt references: 08, 13
- Confidence: medium

## Example: Inventory Stock With CSV Import/Export

- Source: `v4/full-projects/stock-management`
- MCP search tool used: `mcp__filament_examples.search_examples`
- MCP fetch/read/detail/source tool used: none exposed separately; source snippets returned by search
- MCP fetched: no; source snippets returned by search_examples
- Access level: source snippets/summary through search_examples
- Filament version: v4 example, same native import/export concepts already used in PodText
- Files/classes inspected: `ItemImporter`, `ItemExporter`, list page actions, table bulk export
- Dependencies: Filament Importer/Exporter
- Why relevant: PodText already uses Filament-native import/export and Phase 02 must extend it
- Filament concepts used: `ImportColumn`, `ExportColumn`, `resolveRecord`, `relationship(resolveUsing:)`, `ImportAction`, `ExportAction`, `ExportBulkAction`
- Pattern to copy: native actions and relationship resolution
- Pattern to avoid: custom CSV controllers or numeric ID exports
- Testing ideas: create/update import, failed row, relationship key resolution, export column list
- Implementation risk: transcript file imports require careful package/file handling
- Use now/later: now
- Adaptation notes for PodText: transcriptions, categories, typed tags, pin fields, media metadata
- Implementation prompt references: 10
- Confidence: high

## Example: Livewire Status Sidebar In Edit Page

- Source: `v4/forms/livewire-component-in-editform-sidebar`
- MCP search tool used: `mcp__filament_examples.search_examples`
- MCP fetch/read/detail/source tool used: none exposed separately; source snippets returned by search
- MCP fetched: no; source snippets returned by search_examples
- Access level: source snippets through search_examples
- Filament version: v4 example, adaptable to v5 schema components
- Files/classes inspected: `EditTicket`, `TicketSidebar`, ticket sidebar Blade view
- Dependencies: Filament Resource edit page, schema `Livewire` component, Livewire events
- Why relevant: future transcription studio may need a Livewire sidebar/editor surface
- Filament concepts used: `Filament\Schemas\Components\Livewire`, `Grid`, `Group`, `#[On]`, computed Livewire properties
- Pattern to copy: isolated Livewire component embedded in a Filament page when interactivity is justified
- Pattern to avoid: adding studio workflows during public browsing/search implementation
- Testing ideas: component event refresh and authorization tests when studio is built
- Implementation risk: editor state/autosave requires failure handling not planned for Phase 02 implementation
- Use now/later: later
- Adaptation notes for PodText: use only in Prompt 14 planning for future studio
- Implementation prompt references: 14
- Confidence: high

## Example: Quiz Application With Custom Take-Quiz Page

- Source: `v4/full-projects/quiz-application/app/Filament/Pages/TakeQuiz.php`
- MCP search tool used: `mcp__filament_examples.search_examples`
- MCP fetch/read/detail/source tool used: none exposed separately; source snippets returned by search
- MCP fetched: no; source snippets returned by search_examples
- Access level: source snippets through search_examples
- Filament version: v4 example, adaptable to v5 Pages/Livewire/Alpine usage
- Files/classes inspected: `TakeQuiz`, `take-quiz.blade.php`, nested `Repeater` form snippets
- Dependencies: Filament Page, Livewire state, Alpine local timer, Repeaters
- Why relevant: demonstrates local Alpine behavior and custom public page flows
- Filament concepts used: custom Page route, `#[Locked]`, `#[Computed]`, Alpine `x-data`, `Repeater`
- Pattern to copy: Alpine for local UI-only behavior
- Pattern to avoid: using Alpine to own persisted transcript/editor state
- Testing ideas: public page state and Blade rendering tests
- Implementation risk: timers/player sync are easy to overbuild before direct media support exists
- Use now/later: later for viewer/studio
- Adaptation notes for PodText: Prompt 12 may use Alpine for show/hide speakers/timestamps only
- Implementation prompt references: 12, 14
- Confidence: medium

## Example: Multi-Widget Analytics Dashboard with Presets

Recorded during Prompt 13 phase 2. Phase 1 ran its MCP passes during the design
stage but never wrote them here, which the tooling guideline requires; this
section and the next close that gap.

- Source: `v4/full-projects/filawidgets-dashboard/` — `app/Filament/Pages/Dashboard.php`, `app/Filament/Widgets/RevenuePulseWidget.php`, `RevenueByRegionWidget.php`, `DailyRevenueWidget.php`, `RevenueGoalWidget.php`, `FulfillmentRateWidget.php`, `app/Enums/DashboardDateRange.php`, `app/Enums/DashboardPreset.php`
- MCP search tool used: `mcp__filament-examples__search-examples`
- MCP fetch/read/detail/source tool used: none exposed separately; source snippets returned by search
- MCP fetched: no; full class bodies returned by `search-examples`
- Access level: source snippets through `search-examples`
- Filament version: v4 example, adapted to installed Filament v5
- Query batches run: pass 1 — `stats overview widget chart`, `custom blade widget`, `widget page filters`, `heatmap calendar widget`, `activity timeline widget` (limit 8); pass 2 — `widget interacts with page filters`, `table widget resource url action`, `blade widget section heading badge` (limit 2, returned overlapping projects and no new dashboard material)
- Files/classes inspected: the eight files listed above, read in full
- Dependencies: `laraveldaily/filawidgets`, `HasFiltersForm`, `ToggleButtons`, page-level `$filters`
- Why relevant: this is the same shape as PodText's lens dashboard — a preset enum switching `getWidgets()`, a range enum feeding every widget, and package widgets reading a shared range filter
- Filament concepts used: `Dashboard implements HasFiltersForm`, grouped `ToggleButtons`, `getWidgetsForPreset()` `match`, `getColumns()`, `SparklineTableWidget`, `BreakdownWidget`, `HeatmapCalendarWidget`, `ProgressWidget`, `CompletionRateWidget`, `SparklineSeries::daily()`, `BreakdownItemData`
- Pattern to copy: preset-enum-switched `getWidgets()`; a range enum with `currentPeriod()`/`previousPeriod()` feeding both the value and its previous-period delta; `BreakdownItemData` built by merging current and previous key sets so a transcriber who published last period but not this one still appears
- Pattern to avoid: (1) the example's `DashboardDateRange` computes periods on the server default timezone — PodText needs Jerusalem walls, which is why `App\Enums\DashboardRange` is custom; (2) `SparklineSeries::daily()` groups with raw `DATE(column)`, i.e. database-timezone days, so PodText buckets in PHP instead and stays correct on both MySQL and the SQLite test database and across DST; (3) the example's widgets query models directly, which would break PodText's one-number-one-source rule
- Testing ideas: assert the widget set per preset; assert period boundaries; assert previous-period deltas
- Implementation risk: extending package widget classes couples the board to package view internals — PodText keeps the data layer and writes its own Blade views instead
- Use now/later: now
- Adaptation notes for PodText: `Dashboard::getWidgetsForLens()` mirrors `getWidgetsForPreset()`; every number routes through `App\Support\Dashboard\EditorialMetrics`; day bucketing uses `DashboardRange::dayKeys()` on Jerusalem walls
- Implementation prompt references: 13
- Confidence: high

## Example: Segmented Button Filter for Chart Widget

- Source: `v4/full-projects/chart-filter-buttons/app/Filament/Widgets/EngagementRateChart.php`, `app/Enums/TrafficSource.php`, `resources/views/filament/widgets/engagement-rate-chart.blade.php`
- MCP search tool used: `mcp__filament-examples__search-examples`
- MCP fetch/read/detail/source tool used: none exposed separately
- MCP fetched: no; source snippets returned by search
- Access level: source snippets through `search-examples`
- Filament version: v4 example, adapted to installed Filament v5
- Files/classes inspected: the three files listed above
- Dependencies: widget-local public state, an enum of filter values, a custom widget Blade view
- Why relevant: the activity stream's type chips and the heatmap's day selection are widget-local state, not page filters
- Filament concepts used: custom widget `$view`, public widget properties, `wire:click` handlers on a Blade chip row
- Pattern to copy: widget-local public state driven by `wire:click`, with the widget's own Blade view owning the chip row
- Pattern to avoid: pushing every chip into page filters — only the legend's status chip belongs in `pageFilters` (H6); chip and day state stay widget-local
- Testing ideas: `Livewire::test(Widget::class)->call('selectType', ...)->assertSet(...)`; cross-widget wiring via `->dispatch(...)`/`assertDispatched(...)`
- Implementation risk: widget-local state is not URL-persisted, so a page reload loses the chip selection — accepted for phase 2
- Use now/later: now
- Adaptation notes for PodText: `ActivityStreamWidget::$type`/`$day` and `PublicationHeatmapWidget::$selectedDay`; the heatmap dispatches `dashboard-day-selected` and the stream listens with `#[On]`
- Implementation prompt references: 13
- Confidence: high

### Follow-up pass: how the filawidgets example names its keys

Re-queried during Prompt 13 phase 2 (`filawidgets operations pulse queue widget`,
`filawidgets growth signals expansion target`). The MCP exposes eight files of
that project; the other ten widget classes the example's `Dashboard` imports are
not returned, so the searchable surface is exhausted.

What the example and the installed package show about key naming:

- Every widget subclass sets a **distinct `protected ?string $widgetLabel`**
  (`'Revenue Pulse'`, `'Revenue by Region'`, `'Daily Revenue'`, `'Revenue Goal'`,
  `'Fulfillment Rate'`). That is the package's per-widget identity.
- `LaravelDaily\FilaWidgets\Widgets\Concerns\InteractsWithWidgetConfiguration`
  also exposes `protected ?string $widgetCacheKey`, passed to
  `WidgetDataCache::remember(key: ...)`.
- `WidgetDataCache::key()` falls back to `implode(':', [$widget, class_basename($resolver)])`
  plus a sha1 of filters + options when no explicit key is set. Two widgets
  sharing a resolver and the same filters would therefore share a cache entry
  unless each sets a distinct `$widgetCacheKey`.
- The example's `filtersForm()` uses `ToggleButtons::make('preset')` and
  `make('range')` with **no** explicit `->key()` — it relies on distinct field
  names. PodText sets explicit keys anyway, because its dashboard renders its
  own filters schema alongside widget-owned schemas.

Applicability to PodText: **none of the package's key mechanisms are in play**,
because phase 2 extends no filawidgets widget class and imports nothing from
`LaravelDaily\FilaWidgets`. The equivalent surface in PodText is
`EditorialMetrics`'s own cache key, already namespaced per podcast scope
(`dashboard:editorial-metrics:v2:{id|all}`); the other metric methods are
uncached, so there is no second key surface to collide.

### Follow-up pass: chart rendering, formatting and trend colour

Run before the Prompt 13 chart-polish and formatter work, after filawidgets was
removed. Two sources: the FilamentExamples MCP, and the package's own source
read from the MIT-licensed release still in composer's cache
(`LaravelDaily/FilaWidgets` @ `59bc019`). MIT means its techniques may be
adapted directly with attribution rather than reimplemented blind.

**Queries run:** `svg chart in blade widget`, `enum with label and color
contract`, `alpine tooltip hover widget` (limit 1); earlier passes covered the
filawidgets dashboard project itself.

#### Finding 1 — filawidgets ships no JavaScript at all

Five Blade views, 784 lines, **zero** `.js`, `.css`, `x-data`, `x-tooltip` or
`x-on:`. Its charts are static server-rendered SVG and CSS bars. There is no
"package-grade interactivity" to acquire — the premise that it had some was
wrong, and PodText's own SVG partial already uses the same `<polyline>`
technique (and adds `vector-effect="non-scaling-stroke"`, which the package
lacks).

#### Finding 2 — three real improvements to copy (option A)

- **min/max normalisation.** The package maps `y` over `range = max - min` with
  a 2px padding inset. PodText normalises against the peak only, so a series
  like `[8, 9, 10]` renders almost flat when it should show a clear slope. This
  is the single biggest visual win available.
- **Trend-coloured stroke.** `WidgetMetricCalculator::comparison()` returns a
  `trend` of `up`/`down`/`neutral`, and the view maps it to stroke classes, with
  per-threshold-colour variants. `SeriesRow::delta()` already gives PodText the
  trend; only the colour map is missing.
- **Dashed-border empty state** and `x-filament::link` with
  `heroicon-m-arrow-top-right-on-square` for doorways — panel-native styling we
  currently hand-roll with plain `<a>`.

#### Finding 3 — `Illuminate\Support\Number`, not `number_format()`

`WidgetValueFormatter` wraps Laravel's `Number` helper, which is locale-aware —
relevant for a Hebrew panel where PodText currently calls `number_format()`
directly in Blade. `formatSignedPercentage()` shows the sign convention worth
centralising (`Number::format(abs($v), maxPrecision: 1)` plus an explicit sign).

#### Finding 4 — "group other" bucketing

`WidgetMetricCalculator::breakdown()` supports `limit` + `groupOther`, rolling
the tail into a single "Other" row. PodText's `podcastHealth()`/
`transcriberBoard()` just `take($limit)` and silently drop the tail — which the
tooling guideline's "no silent caps" rule argues against. Worth adopting.

#### Finding 5 — Filament already bundles Chart.js (for option B/C later)

`vendor/filament/widgets/dist/components/chart.js`, ~280 KB, lazy-loaded via
`x-load` / `x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"`,
with `wire:ignore` on the wrapper and `x-ref` colour probe elements
(`backgroundColorElement`, `borderColorElement`, `gridColorElement`,
`textColorElement`) so CSS-derived theme colours reach Chart.js. Source:
`v4/full-projects/dashboard-visitor-analytics`. Genuinely interactive charts
therefore cost **no new dependency** — but a `ChartWidget` is a different base
class from our own-Blade-views widgets, and Chart.js assumes LTR. Recorded for
when a chart earns it; not adopted now.

- Use now/later: findings 2–4 now; finding 5 later
- Implementation prompt references: 13
- Confidence: high (primary source read directly)

## M2 Research: Nested Livewire in Action Modals — Keying Patterns (2026-08-01)

Recorded while researching M2 (media picker panel dead after action-modal
reopen: a stable `->key()` on a `Filament\Schemas\Components\Livewire` child
stays in the parent memo across Filament partial renders, so on reopen
Livewire emits a snapshot-less stub and partials.js grafts a dead clone).
Goal: how do real FilamentExamples projects nest Livewire components in
modals/action modals, and how do they key them.

- MCP search tool used: `mcp__filament-examples__search-examples`
- MCP fetch/read/detail/source tool used: none exposed as a separate tool;
  full file bodies are returned directly by search
- Query batches run: pass 1 (limit 8) — `livewire component in modal`,
  `livewire component action modal`, `nested livewire component`;
  `livewire schema component`, `media picker`, `modal wire:key`;
  `livewire key modal`, `custom action modal content`, `modalContent
  livewire`; `construction projects` (zero results). Pass 2 (limit 3) —
  `construction`, `project management`, `kanban board drag drop`;
  `ViewAction modal livewire component`, `Livewire make lazy`, `schema
  component key`; `image gallery`, `comments section`, `chat messages`.
  Later batches only re-returned the same three Livewire-hosting projects,
  so the searchable surface for this topic is exhausted.

Headline: exactly one project in the searchable corpus renders a Livewire
component inside an action modal, and it sets **no key at all**. No example
anywhere calls `->key()` on `Filament\Schemas\Components\Livewire`. The only
deliberate key in the corpus is a **data-dependent** `key(...)` on a Blade
`@livewire()` mount, composed so the key changes whenever the child must be
remounted. A static stable key on a modal-nested Livewire child — the M2
arrangement — has zero precedent in the corpus.

### Example: Product Picker Livewire Table Inside an Action Slide-Over

- Source: `v4/forms/quote-form-with-custom-table-field-and-product-picker-modal` — `app/Livewire/ListQuoteProducts.php`, `app/Livewire/ProductPickerTable.php`, `app/Filament/Tables/ProductsTable.php`, `app/Filament/Forms/Components/QuoteProductsField.php`, `resources/views/filament/forms/components/quote-products-field.blade.php`
- MCP search tool used: `mcp__filament-examples__search-examples`
- MCP fetch/read/detail/source tool used: none exposed; full sources via search
- Access level: source files through `search-examples`
- Filament version: v4 example; same schema `Livewire` component API as installed v5
- Files/classes inspected: the five files above, read in full
- Dependencies: Filament actions/tables/schemas, Livewire events, `#[Modelable]`/`#[Locked]` attributes
- Why relevant: the only corpus example of `Livewire::make(...)` inside `Action->schema()` in a modal/slide-over — structurally the same shape as PodText's media picker panel
- Pattern found (action modal, no key):

```php
Action::make('selectProducts')
    ->slideOver()
    ->modalSubmitAction(false)
    ->schema([
        Livewire::make(ProductPickerTable::class, [
            'vehicleData' => $this->vehicleData,
        ]),
    ]),
```

- Pattern found (the project's only explicit key — data-dependent, on the
  Blade `@livewire()` mount of the wrapper component; the key changes when
  the data that must invalidate the child changes):

```blade
@livewire(\App\Livewire\ListQuoteProducts::class, [
    'wire:model' => $getStatePath(),
    'vehicleData' => $vehicleData,
    'isReadOnly' => $isDisabled(),
], key($getId() . '-' . ($vehicleData['vehicle_model_id'] ?? 'no-model')))
```

- Pattern found (partial-rendering escape hatch — after mutating state the
  child table renders, the parent forces a full render instead of trusting
  Filament partial rendering):

```php
#[On('addProductToTable')]
public function addProductToTable(int $id, string $name, int $quantity): void
{
    $this->state[] = ['id' => $id, 'name' => $name, 'quantity' => $quantity];
    $this->resetTable();
    app(PartialsComponentHook::class)->forceRender($this);
}
```

- Filament concepts used: `Filament\Schemas\Components\Livewire`, `slideOver()`, `modalSubmitAction(false)`, `#[Modelable]`, `#[Locked]` child input, child-to-parent `dispatch()` plus `#[On]`, `Filament\Support\Livewire\Partials\PartialsComponentHook::forceRender()`
- Pattern to copy: pass data into the child as mount data and mark it `#[Locked]`; send the selection back with a Livewire event, not shared state; when a parent state change must reach DOM that partial rendering would skip, call `PartialsComponentHook::forceRender($this)`; when a child must be remounted on data change, derive the mount key from that data
- Pattern to avoid: treating the keyless modal nesting as proven reopen-safe — the example hosts the action on a nested Livewire component (its own lifecycle, remounted with its host), nothing in the corpus demonstrates a page-level action-modal reopen cycle, so it may simply never hit M2's reopen path
- Testing ideas: reopen cycle assertions on the mounted child (`wire:snapshot` present on second mount), event round-trip from child to parent
- Implementation risk: `forceRender()` opts the whole component out of partial rendering for that request — measure before adopting wholesale
- Use now/later: now (M2 fix reference)
- Adaptation notes for PodText: the corpus's two working stances are "no explicit key" and "key varies with the data that must invalidate the child". A per-mount token key (changing on every modal mount) is the per-mount generalisation of the observed data-dependent key and consistent with corpus practice; a fixed string key is not observed anywhere
- Implementation prompt references: M2 media-picker fix
- Confidence: high for what the corpus does; medium for reopen behaviour (never exercised in the example)

### Example: Ticket Sidebar — keyless long-lived Livewire refreshed by events

- Source: `v4/forms/livewire-component-in-editform-sidebar` — `EditTicket::content()`, `TicketSidebar`
- MCP search tool used: `mcp__filament-examples__search-examples`
- Access level: source files through `search-examples`
- Pattern found: `Livewire::make('ticket-sidebar')->data(fn (): array => ['record' => $this->getRecord()])` in persistent page content, **no key**; parent `afterSave()` dispatches `ticket-sidebar-refresh`, the child refreshes itself via `#[On]`
- Why relevant: the corpus's stance for children that are never unmounted — keep them keyless and push updates through events rather than re-render/remount
- Adaptation notes for PodText: stable identity plus event refresh is the pattern for permanently mounted children; a modal child is not that, so this pattern argues against carrying a stable identity across modal mounts
- Confidence: high

### Alternatives the corpus prefers over nesting Livewire in modals

- Schema-native modal content: `ViewEntry::make(...)->view(...)` inside a
  `slideOver()` action with data captured at schema build time
  (`v4/forms/markdown-and-rich-editor-preview-forms`); plain form-component
  schemas everywhere else — the AI CMS actions
  (`v4/full-projects/laravel-ai-sdk-cms`) keep rich multi-step modals alive
  by mutating `$livewire->mountedActions[$index]['data'][...]` and calling
  `$action->halt()`, never by nesting a component.
- Render-hook singleton: one page-level Livewire component registered at
  `PanelsRenderHook::BODY_START` and driven from actions with
  `->dispatchTo(...)` (`v4/full-projects/global-search-actions-clipboard`) —
  the modal never owns the component, so modal lifecycle cannot kill it.

### Honest gaps

- `construction projects` (operator-named) returned zero results; the
  variants `construction`, `project management`, and `kanban board drag
  drop` surfaced only `kanban-board`, `table-reorderable-position`, and
  `restaurant-menu`, none containing modal-nested Livewire. The named
  example either is not in this MCP's corpus or carries a different name
  beyond query reach.
- No media-library/gallery picker example exists in the corpus; the `media
  picker` query resolves to the product-picker project above.
- Only `search-examples` is exposed; no separate fetch/read/details tool,
  recorded per protocol.

## Admin Table URL-Key Research: Query-String Namespacing (2026-08-02)

Research for the app-wide closure of the "admin table components claim bare
URL query-string keys" family (generalising the Dashboard `blockersQueue`
fix from commit `894870e`). Protocol: pass 1 ran five short queries at
`limit: 8` ("table widget dashboard", "query string identifier", "multiple
tables on page", "custom page with table", "settings page table"); pass 2 ran
two refined batches at `limit: 3` ("queryStringIdentifier",
"WithoutUrlPagination", "dashboard multiple table widgets", "configureUsing
table", "table widget pagination", "persist filters in session"). The corpus
is small and result overlap between queries is heavy.

### Headline corpus verdicts

- No example demonstrates `queryStringIdentifier()`, a global
  `Table::configureUsing()` default, or Livewire's `WithoutUrlPagination` —
  all three had to be justified from official Filament/Livewire docs and
  vendor source, not examples.
- The corpus's de-facto convention for widgets and secondary tables is to
  sidestep URL state entirely: `->paginated(false)` plus a query-level
  `->limit(N)` and `->defaultSort()`.
- The only multi-table-per-screen pattern is one child Livewire component per
  table, each unpaginated (`sports-standings-tables`); no example shows two
  paginated Filament tables coexisting on one screen.

### Example: Teacher Payouts dashboard TableWidget

- Source: `v4/full-projects/teachers-payouts` — `TeacherScheduleWidget`
- MCP search tool used: `mcp__filament-examples__search-examples`
- Access level: snippets through `search-examples` only
- Pattern found: `extends TableWidget`, `->paginated(false)`, query
  `->limit(10)`, `canView()` gate, `columnSpan 'full'`
- Pattern to copy: unpaginated capped widgets where paging is not a job
- Pattern to avoid: `->searchable()` columns on a 10-row unpaginated widget
- Adaptation notes for PodText: the blockers queue is a real paged work queue
  (10/25 page sizes), so the corpus's unpaginated stance does not fit it;
  namespacing was kept instead
- Confidence: high for what the corpus does

### Example: Sports Group Standings multi-table screen

- Source: `v4/tables/sports-standings-tables` — `GroupsOverview` page +
  `GroupGamesOverview` child component per group
- Pattern found: one `InteractsWithTable` child component per table, mounted
  with `:key`, every table `->paginated(false)`
- Adaptation notes for PodText: collision is avoided by killing pagination,
  not by namespacing; gives no answer when pagination is required
- Confidence: high

### Example: Doctor Schedules page-level #[Url] beside a table

- Source: `v4/full-projects/schedule-for-doctors` — `ManageDoctorSchedule`
- Pattern found: `#[Url] public ?int $selectedDoctorId` page state coexisting
  with a table on one component; table `->paginated(false)`
- Adaptation notes for PodText: precedent that page params and table params
  share one query string — exactly the surface the namespacing convention
  protects
- Confidence: high

### Supporting examples

- `box-score-form` `ManagePlayerStats` and
  `create-form-and-table-on-the-same-page` `Category`: baseline
  `Page implements HasTable` + `InteractsWithTable` stacks that leave table
  URL params untouched (single table per screen).
- `filawidgets-dashboard` `Dashboard`: `HasFiltersForm` + widgets reading
  `$this->filters` — the shared-dashboard-filter pattern PodText already
  uses; no table URL state involved.
- `free-form-text-search` `ListParticipants`: canonical programmatic
  `tableFilters` mutation sequence (`fill` + `updatedTableFilters()`),
  confirming the property family behind the URL params.

### Honest gaps

- Only `search-examples` is exposed by the MCP; no source/read/fetch/details
  tool, recorded per protocol. Pass 1 returned 134KB for 5 queries at
  limit 8; pass 2 used limit 3 for that reason.
- The corpus never exercises the collision this task closes (two paginated
  table components on one screen), so the chosen mechanism rests on
  Filament 5.7.5 vendor source (`SupportPagination`,
  `InteractsWithRelationshipTable`, static `#[Url]` on `ListRecords`) and the
  official 5.x/4.x docs sections "Preventing query string conflicts with the
  pagination page" (tables overview), the custom-data
  `LengthAwarePaginator` `pageName` caveat, and Livewire "Multiple
  paginators" / `WithoutUrlPagination`.

## Public Form Modal Duplicate-Mount Research: One Dialog Per Open Event (2026-08-02)

Research for deduping duplicate `PublicFormModal` mounts that all hear one
`open-public-form` window event. Protocol: pass 1 ran four short queries at
`limit: 8` ("modal open close alpine", "public form modal livewire", "window
event listener component", "dispatch browser event button") and returned
Custom Table Field With Product Picker Modal; Action Buttons in Global Search
Results; Livewire Status Sidebar In Edit Page; Bulk Action Updating Value via
Modal Form; AI-Powered CMS With Laravel AI SDK; Doctor Availability and
Blocked-Time Scheduling; Fill Form Field Using OpenAI API; Drag-and-Drop
Kanban Board Page. Pass 2 refined ("alpine x-data blade view", "custom
javascript event blade", "guest public page livewire", "overlay dialog alpine
show") and returned Google Maps Markers on a Custom Page; GitHub-Style
Profile View Page with Heatmap; School Weekly Timetable Calendar Page;
Drag-and-Drop Kanban Board Page; Monthly Attendance Grid Tracker; Register
Form Password Strength Meter; Profile Page with Multiple Child Records;
Custom-Designed Table with ViewColumn Cells.

### Headline corpus verdicts

- No example mounts the same Livewire modal component more than once on a
  page or coordinates several listeners answering one window `CustomEvent`;
  the duplicate-mount dedupe decision has no corpus precedent.
- Closest adjacent pattern: the Google Maps custom page guards duplicate
  global side effects with an idempotency flag (`mapsScriptLoaded` plus a
  single global callback). The adopted fix reuses that shape as a per-event
  claim marker (`$event.publicFormClaimed`) instead of a long-lived global.
- Access level: only `search-examples` (snippets) was available; no
  source/read/fetch tool.
- Boost `search-docs` (livewire/livewire 4.x events/actions): the supported
  contract is `x-on:<event>.window` with `$event.detail`; neither Livewire
  nor Alpine offers a single-consumer event mechanism, so the claim lives in
  the app-owned listener.
- Adaptation notes for PodText: the fix stays inside the owned public modal
  Blade listener (Alpine-only local UI behavior per the public-panel
  guideline). A page-level mount registry/render hook was rejected because
  the three mounting parents re-render in independent Livewire requests, so
  request-scoped registries decide inconsistently across partial re-renders
  (the M2 stale-child class).

## F1/F2 Localization Home + F3 Group-Other Research (2026-08-03)

Research for the F-block: the `UiFormats` localization home beside
`UiTimezone` (dates day-first, numbers via `Illuminate\Support\Number`), its
statement-scanned anti-drift guard, and the F3 "group other" tail roll-up in
`EditorialMetrics` breakdowns. Protocol: pass 1 ran four short queries at
`limit: 8` ("dashboard stats overview widget", "table column date time
format", "custom widget blade view data", "number formatting in table
column") and returned Star Rating Column with ViewColumn and Modal;
Investment Holdings Table with Styled Columns; Custom-Designed Table with
ViewColumn Cells; Sports Group Standings Tables; Complex Orders Table with
Query Builder Filters; Google-Analytics-Style Dashboard Widgets; Dynamic
Checkbox Columns from Database Rows; Gantt-Style Fleet Availability Widget.
Pass 1's 73 KB result was inspected via a structure map (all example
names/paths) plus full snippet reads of the four surface-relevant examples
(VisitorsPerCountry widget + view, orders StatsOverview, wealthfolio
Performance column + HoldingsTable); the other snippets were not read in
full. Pass 2 refined at `limit: 3` ("top list widget with other row",
"locale aware date display", "breakdown percentage bar widget") and returned
the Google-Analytics dashboard again, Multi-Widget Analytics Dashboard with
Presets (`filawidgets-dashboard`), and Drag-to-Resize Collapsible Sidebar.

### Headline corpus verdicts

- **The F3 concept has corpus precedent in the package PodText removed.**
  `filawidgets-dashboard`'s `RevenueByRegionWidget` sets
  `protected ?int $itemLimit = 4` with `protected bool $groupOther = true` —
  filawidgets' BreakdownWidget owns tail roll-up at the widget/presentation
  layer. PodText adopted then removed that package (plan decision 14), and
  the in-house rewrite dropped the group-other capability, leaving the bare
  `take($limit)` cap F3 now closes. Adaptation: the roll-up moves into
  `EditorialMetrics` (data layer), because PodText widgets must not compute;
  `BreakdownRow::meta` carries the rolled-up row count and aggregates.
- **The corpus demonstrates the defect patterns, not the cure.**
  `VisitorsPerCountry` truncates with `limit(10)` and no tail signal (the
  exact silent-cap shape), polls every 20s, and queries models from the
  widget — all three against PodText board contracts. The orders
  `StatsOverview` inlines `number_format()` with no locale routing — the
  pattern F1/F2 replace. No example owns date/number formats in one home;
  the `UiFormats` shape extends PodText's own `UiTimezone` idiom instead.
- **Filament-native per-column number/locale formatting exists** for future
  table-column numbers: wealthfolio's `HoldingsTable` uses
  `TextColumn::numeric()` and `->money(fn ($record) => $record->market_locale->value)`,
  and Boost docs confirm `->numeric(locale: ...)`. PodText's five number
  sites are all in owned Blade views, so they route through
  `UiFormats::number()`; `->numeric()` remains the right lever if a numeric
  TextColumn appears later.
- Boost `search-docs` confirmed `->dateTime($format, timezone: ...)` stays
  the supported column signature (the F2 change swaps only the literal for
  `UiFormats::dateTime()`), and surfaced `FilamentTimezone::set()` as a
  panel-wide default — noted, not adopted: per-site `UiTimezone::name()` is
  the established, guarded idiom here.
- Access level: only `search-examples` (snippets) was available; no
  source/read/fetch tool.

## Board 3 · Intake Lens Research (2026-08-03, phase-3 re-plan session)

Research for the from-scratch phase-3 plan
(`docs/phase-02/dashboard-metrics-phase-3-plan.md`): the intake work queue,
the Spotify connection card, the media findings bars, and the chip/source
filters. Protocol: pass 1 ran two batches at `limit: 8` — batch A
("dashboard table widget", "stats overview widget", "custom widget blade
view", "widget page filters"), batch B ("failed import rows", "import action
csv", "navigation badge count", "toggle buttons enum filter"); pass 2
refined at `limit: 8` against the gaps pass 1 exposed ("connection test
status", "empty state", "download csv action", "inbox pending widget").
Each batch overflowed the MCP result limit and was saved to a file; every
file was read to 100% (156K + 191K + 164K chars — 24 distinct examples,
182 file blocks) via dedicated read-only digest agents, with per-file
coverage statements recorded. Access level: only `search-examples`
(snippets) was available; no source/read/fetch/details tool.

### Headline corpus verdicts

- **The corpus has no connection-test / API-health card precedent.** Greps
  across all three payloads for connection/health/ping/tested_at returned
  no real hits. The closest analogues: `laravel-ai-sdk-cms`'s
  `SuggestTitleAction` (run external call in a modal, echo result inline,
  `$action->halt()` to keep it visible), `teachers-payouts`'
  `TeacherPayoutsTable` (boolean status column + `…_at` timestamp stamped
  by the action that changed the state), and `AiConfig::isProviderConfigured()`
  (key-presence check filtering provider options). The Spotify card
  therefore stands on PodText's own precedent — `ImporterSettings`' test
  action persisting `status` + `last_tested_at` through
  `ImportConnection::markTested()` — with `ImportConnectionStatus`'s
  existing `HasLabel`+`HasColor` contract styling the badge.
- **Queue-widget shape.** Best references: `teachers-payouts`
  `TeacherScheduleWidget` (`TableWidget` + `canView()` gate + `limit(10)` +
  `paginated(false)`), `material-theme` `LatestOrders`
  (`defaultPaginationPageOption(5)` + per-row `Action->url()`),
  `laravel-ai-sdk-cms` `RecentAiActivityWidget` (badge column + `->since()`).
  All are single-model tables; none unions two models. PodText's intake
  queue spans `PublicFormSubmission` + Filament `Import`, so it follows the
  board's own `ActivityStreamWidget` custom-`Widget` pattern (typed rows
  merged in the metrics service, chips as widget-local Livewire state)
  rather than forcing a cross-model `TableWidget`.
- **The cache+invalidate twin.** `ecommerce-admin-panel`'s
  `DashboardMetrics` (`Cache::remember`/`Cache::forget`, `CACHE_KEY`/
  `CACHE_SECONDS` consts) plus `OrderObserver` forgetting caches on model
  events is structurally identical to PodText's `EditorialMetrics` +
  `EditorialMetricsCacheObserver`. Confirms the phase-3 choice: extend the
  observer registration to the intake models rather than inventing
  per-widget caches. Same example's `Stat->url(Resource::getUrl('index',
  ['tab' => 'fulfillment_queue']))` is the count-to-prefiltered-queue
  doorway idiom the media findings bars use (`tab` + `filters`).
- **Empty states.** The only real `emptyStateHeading()`/`emptyStateDescription()`
  usage is `filter-or-search-only-table` (with a caveat its own copy shows:
  one undifferentiated string for two different reasons of emptiness).
  `teachers-payouts`' attendance Blade demonstrates the better concept —
  two distinct empty branches with distinct icon/heading/description —
  but implements it by copying `fi-ta-empty-state-*` internal classes
  (pattern to reject; PodText widgets keep their own owned empty-state
  markup, as the stream/gap views already do). Phase 3 adopts the
  two-reason concept: the queue's "nothing to handle" empty state is
  distinct from its "this source produces no queue rows" state.
- **Failed-rows CSV.** No corpus example touches `FailedImportRow` or the
  failure-CSV download at all (grep-verified zero hits across payloads);
  `stock-management` stops at `getCompletedNotificationBody()` +
  `getFailedRowsCount()`. The load-bearing citation is vendor code, not the
  corpus: `ImportAction.php:317` builds
  `URL::signedRoute('filament.imports.failed-rows.download',
  ['authGuard' => …, 'import' => …], absolute: false)`, and
  `DownloadImportFailureCsv` honours a `view` policy on the `Import` model
  before falling back to owner-only — which is why the plan adds
  `ImportPolicy::view` for admins.
- **Chip filters.** No `ToggleButtons->options(Enum::class)` exists in the
  corpus; the composable parts are `HomesTable`'s `ToggleButtons->live()
  ->grouped()` and the widespread `SelectFilter->options(Enum::class)`.
  PodText's command bar keeps its established `options(X::options())`
  value=>label array idiom instead of `options(Enum::class)` — the filter
  state is URL-bound and session-persisted, and an `EnumStateCast` there
  would repeat the E5/P9 state-type mismatch. `chart-filter-buttons`'
  segmented-control Blade (wire:click + @class active pill) matches the
  chip row the stream widget already ships; no change of idiom needed.

### Patterns to reject, recorded

- `MessageResource::getNavigationBadge()` (internal-messaging-inbox):
  hydrates every topic+message and counts in PHP per render — the shape
  PodText's cached `PublicFormSubmission` badge already avoids.
- `TopicsTable->recordClasses()` recomputing a whole-table aggregate inside
  the per-row closure, with `'!=='` passed to Eloquent `where()` as an
  operator (silently wrong).
- `VisitorsPerCountry` (dashboard-visitor-analytics): `rendering()`
  re-query per Livewire round-trip, `wire:poll.20s`, and a `limit(10)`
  with no tail signal — three board-contract violations in one widget
  (polling ban, silent cap P6, widget-owned queries).
- `FleetAvailability`'s Blade-side `$cellColors`/`$cellTitles` maps keyed
  by status string — hand-written colour maps beside typed state (P1/P2);
  enum `barClass()`/`chipClass()` is the PodText replacement.
- `orders-table-complex` `OrderStatus::getColor()` returning CSS class
  names while not implementing `HasColor` (and `filawidgets-dashboard`'s
  base classes presented as core Filament — `getRangeFilter()`,
  `$widgetLabel` are package API, not Filament).
- v3-era leftovers flagged by the digests: unused `use Filament\Tables;`
  imports, `getActions()` on a custom page (never called in v4/v5 —
  invisible action), `->reactive()` instead of `->live()`, raw string
  modal widths, copying `fi-*` internal class names into app Blade.

### Version idioms

All 24 examples are v4-namespaced; zero `Filament\Tables\Actions`,
`Filament\Forms\Form`, `->actions([`/`->bulkActions([` hits. Confirmed
idioms match the installed 5.7.x surface PodText already uses:
`Filament\Actions\*`, `->recordActions()`, `Filament\Schemas\Schema`,
schema-namespace layout components, non-static widget `$heading`/`$view`
with static `$sort`, `Heroicon` enum icons, and `ListRecords` tab binding
via `#[Url(as: 'tab')]` (verified in the installed vendor sources, which is
what makes `['tab' => …, 'filters' => …]` the gallery doorway shape).

## A Block · Sparkline, Doorways, Empty States, Reason Doorway (2026-08-03)

Protocol run for the A-block session (A1 normalisation, A2 trend stroke,
A3 empty states + panel-native doorways, A4 reason-bar doorway). Two query
batches against `search-examples` — batch 1 (`limit: 8`): "widget empty
state", "custom dashboard widget blade view", "table select filter default
value", "livewire dispatch event between widgets"; batch 2 (`limit: 3`,
refined): "empty state custom widget", "svg sparkline blade", "link to
filtered table url", "badge link blade component view". Batch 1 overflowed
the tool-result budget (~150 KB) and was read from the saved dump. Only
`search-examples` is exposed — no fetch/read/details tool, so per-file
inspection beyond returned snippets was not possible.

### Informing patterns

- `v4/full-projects/chart-filter-buttons/resources/views/filament/widgets/engagement-rate-chart.blade.php`
  — segmented chip buttons inside a widget section driving server state via
  `wire:click` — the same interaction family as the A4 reason bars.
  Confirmed the server-driven idiom; PodText differs in routing the click
  through a validated public method that dispatches to a *different*
  widget, because the receiver (the blockers queue) is not the emitter.
- `v4/full-projects/school-timetable-calendar/app/Filament/Resources/Users/Tables/UsersTable.php`
  — `Action::url(fn () => Page::getUrl([...]))` param-carrying doorways.
  Fed the A4 design evaluation: URL params reach only surfaces with a
  `#[Url]` binding to receive them (`ListRecords`), which widgets lack.
- `v4/full-projects/github-style-user-profile-with-activity-heatmap/resources/views/filament/resources/users/pages/view-user.blade.php`
  — bordered stat-tile idiom (`rounded-xl border … dark:border-white/10`)
  matching PodText's card grid; its inline `number_format()` and
  `<x-heroicon-o-*>` component tags rejected (`UiFormats` + `Heroicon`
  enum icons are the house contracts).
- `v4/tables/table-customized-design-viewcolumn` — chip/pill UI extracted
  into `@props` Blade partials, the same shape as the new
  `partials/empty-state.blade.php`; its hex-literal colour system rejected
  (P1).

### Rejected outright

- `v4/full-projects/dashboard-visitor-analytics/.../visitors-per-country.blade.php`
  — `wire:poll.20s`, inline `style="background: rgb(...)"` literals, and a
  nested `@livewire()` chart: three board-contract violations in one view.
- Corpus-wide: no dashed empty-state idiom, no inline-SVG sparkline, and
  zero `x-filament::link` usage (hand-rolled anchors throughout). The
  panel-native doorway idiom therefore came from the installed vendor
  component itself (`vendor/filament/support/resources/views/components/link.blade.php`
  — verified props: `tag`, `icon`, `size`, `color`, `badge`, and the
  `wire:click`-aware loading indicator) plus PodText's own precedent in
  `public-form-target-warnings.blade.php`.

### Boost + vendor verifications for the same block

Boost `search-docs` (2026-08-03): Livewire 4 `#[On]`/`dispatch()`;
Filament 5 blade `button`/`icon-button`/`link` components (`tag`
attribute); `SelectFilter::default()`; filters overview. Verified against
installed vendor sources, not assumed: table filters are deferred by
default (`Tables\Table\Concerns\HasFilters::$hasDeferredFilters = true`),
so a programmatic filter write must end in `applyTableFilters()`;
the canonical mutation path writes through the filter form's field state
(`Tables\Concerns\HasFilters::removeTableFilter()`); and
`getIdentifiedTableQueryStringPropertyNameFor()` has exactly one consumer
— pagination's page name — so `blockersQueueWidgetFilters` names a
property, not a URL binding, and no query string hydrates a widget's
table filters.
