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
