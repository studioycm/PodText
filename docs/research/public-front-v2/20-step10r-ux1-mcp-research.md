# Public Front v2 Step 10R-UX1 MCP Research

Date: 09/07/2026

## Scope

Step 10R-UX1 covers admin navigation ordering, global table/action/schema defaults,
relation-manager tabs above edit content, and scoped admin theme CSS for larger combined
relation-manager tab labels.

## Local Repository Evidence

- Preflight `git status --short --branch` reported a clean `main` branch tracking
  `origin/main`.
- Recent history includes the expected post-M6 docs commits:
  `ebfa68e`, `6e7a74c`, and the v3 runner update `06ba9e1`.
- `php artisan migrate:status` reports all database and settings migrations through
  `2026_07_09_000004_align_public_transcription_display_defaults` as ran.
- Public route preflight found `/podcasts`, `/podcasts/{contentGroupSlug}`,
  `/contributors`, `/contributors/{authorSlug}`, and `/search`.
- The central ledger still contains the v1 continuation rows. This run must amend it to
  the v3 order while implementing UX1.
- Admin resources currently registered in the local app are content groups, content
  items, transcriptions, authors, categories, content tags, public form submissions, and
  homepage sections. Admin pages are the dashboard and public content settings page.
- `EditContentItem` already combines relation-manager tabs with the content tab.
  `EditContentGroup` owns `ContentItemsRelationManager` but does not yet combine tabs.
- `resources/css/filament/admin/theme.css` is already registered through
  `AdminPanelProvider::viteTheme()`.

## Laravel Boost Findings

Tools used: `application_info`, `database_schema`, and `search_docs`.

- Boost confirmed the installed stack: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3,
  Pest 4.7.4, Tailwind 4.3.2, local SQLite.
- Boost schema summary confirmed this mini-step needs no table/schema migration.
- Filament 5 supports explicit resource navigation sorting through either
  `$navigationSort` or dynamic `getNavigationSort()`.
- Filament tables support `recordActions([...], position:
  RecordActionsPosition::BeforeColumns)` and the equivalent
  `recordActionsPosition(RecordActionsPosition::BeforeColumns)`.
- Filament actions support `modalWidth()` with `Filament\Support\Enums\Width`, including
  `Width::SevenExtraLarge`. Vendor source shows confirmation actions default to
  `Width::Medium`, so the global default should preserve compact confirmations.
- Filament schema components, including `Section`, support global `configureUsing()` and
  `columnSpanFull()`.
- Filament edit/view resource pages support `hasCombinedRelationManagerTabsWithContent()`
  and `getContentTabPosition()`. `ContentTabPosition::Before` explicitly pins the content
  tab before relation tabs.

## FilamentExamples Findings

Access level: `search_examples` snippet/search access only. No separate source/read/fetch
tool was exposed.

Query batches used:

- `navigation sort settings page resource`
- `record actions before columns`
- `action modal width section full width`
- `relation manager combined tabs`
- `Filament v4 navigationSort settings page`
- `Filament relation manager getTabComponent Tab badge`
- `Filament table reusable actions class modal schema`
- `Filament admin theme css tabs`
- `hasCombinedRelationManagerTabsWithContent ContentTabPosition`
- `relation manager tabs edit record content tab`
- `wide modalWidth SevenExtraLarge Filament Action`
- `Table configureUsing recordActionsPosition`

Relevant examples and PodText adaptation notes:

- **Account Settings Cluster Pages** showed `protected static ?int $navigationSort` on
  pages. PodText adapts this through one `AdminNavigationOrder` map and
  `getNavigationSort()` instead of scattered properties.
- **Quote Form with Custom Table Field and Product Picker Modal** showed
  `recordActions(..., position: RecordActionsPosition::BeforeColumns)` and
  full-width `Section::make(...)->columnSpanFull()` patterns. PodText adapts the table
  action position as a global admin `Table::configureUsing()` default.
- **Filament Table on a Public Livewire Page** and **Marketplace Operations Panel at
  Scale** showed table configuration classes returning `Table` objects, matching
  PodText's resource table structure. PodText tests can inspect hydrated table objects.
- **Drag-to-Resize Collapsible Sidebar** showed admin theme CSS registered with
  `viteTheme()` and scoped to Filament vendor classes. PodText adapts this only for the
  combined relation-manager tab container and records the vendor-class fragility.
- Relation-manager examples showed `getTabComponent()` with badges and icons, matching
  PodText's existing `ContentItemsRelationManager` and `TranscriptionsRelationManager`
  tab component pattern.

## Implementation Implications

- Add one app-owned admin navigation map and make every registered app admin resource/page
  consume it through `getNavigationSort()`.
- Keep the dashboard in the same map so the panel page completeness test can cover every
  registered admin page while preserving the requested content order after dashboard.
- Register global admin-only defaults for table record-action position, action modal
  width, and section column span in `AppServiceProvider`.
- Use a closure for the global action width so confirmation modals remain compact
  (`Width::Medium`) and non-confirmation action modals default to
  `Width::SevenExtraLarge`.
- Pin `EditContentItem` and `EditContentGroup` content tabs to
  `ContentTabPosition::Before`; create pages remain not applicable because relation
  managers need a persisted owner record.
- Add scoped CSS against the `relationManagerTabs` component key. This is intentionally
  narrow but depends on Filament vendor classes and should be noted in the handoff.

## Stop Conditions

- Stop if repository reality contradicts Step 10R-M6 completion or if Step 11/Prompt 13
  has already started.
- Stop if unexpected app-code dirt appears before implementation.
- Stop if Filament 5.6 APIs for global `configureUsing()`, table record-action position,
  modal width, or combined relation tabs are unavailable.
- Stop if a relation manager is requested on a create page before a record exists.
