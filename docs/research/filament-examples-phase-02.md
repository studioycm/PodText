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
