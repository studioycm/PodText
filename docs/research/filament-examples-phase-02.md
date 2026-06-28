# Filament Examples Research for Phase 02

This research used the configured `filament-examples` MCP server only through its search interface. No MCP token or secret value is recorded here.

## Local Project Baseline

- Current committed slice: Bootstrap Slice 0 through Prompt 05.
- Current public homepage route `/` lists `ContentGroup` records through a custom Public panel page.
- Current item page renders `ContentItem::transcript_markdown`; there is no `Transcription` model or table yet.
- Current admin panel has Filament Resources for `Author`, `ContentGroup`, and `ContentItem`.
- Current import/export already uses native Filament Importer/Exporter classes with reference-key upserts and relationship resolution.
- Current media embeds store URLs only and render through an application Blade component.

## Official Documentation Checked

- Laravel Boost version-aware docs for Filament tables, table filters, custom data tables, widgets, dashboards, forms, Alpine assets, import/export actions, and Livewire URL state.
- Spatie Laravel Tags documentation for flat tag models, typed tags, custom tag models, and translatable tag names.
- Spatie Laravel Settings documentation for typed settings classes and grouped settings migrations.
- Filament Spatie Tags plugin documentation for `SpatieTagsInput` and the requirement to scope tags by type when using multiple tag domains.
- Filament Spatie Settings plugin documentation for `SettingsPage`.

## Examples Used

### Public Table on a Livewire Page

- Example name: `Filament Table on a Public Livewire Page`.
- Files/classes observed: `app/Livewire/Products.php`, a split table class, exporter/resource examples.
- Why relevant: Phase 02 search and homepage results should be `ContentItem` records in a public shell, not admin Resources and not `Transcription` records.
- Pattern to copy: a class-based Livewire component implementing Filament table concerns, with query, columns, filters, actions, and a Blade view rendering `{{ $this->table }}`.
- Pattern to avoid: exposing admin-only Resource routes as the public frontend.
- Adaptation for PodText: build a public `ContentItem` table/listing component with published scopes, effective/main transcription joins, and URL-backed state.
- Prompt references: 11.

### Multi-Panel Hotel Booking Application

- Example name: `Multi-Panel Hotel Booking Application`.
- Files/classes observed: public custom Page using `HasTable`, `HasForms`, `HasSchemas`, and panel separation.
- Why relevant: PodText already has an admin panel and a public panel. Phase 02 should keep this separation.
- Pattern to copy: custom public Pages that compose table/form concerns without turning every fragment into a Resource.
- Pattern to avoid: mixing guest-facing routes into the authenticated admin panel.
- Adaptation for PodText: keep browse, search, category/tag landing pages, and item pages in the Public panel.
- Prompt references: 11, 12.

### Natural-Language AI Search Action

- Example name: `Natural-Language AI Search Action`.
- Files/classes observed: custom table filters, deferred filters, filter indicators, and a header action that writes table filter state.
- Why relevant: it demonstrates advanced filter state orchestration without replacing Filament tables.
- Pattern to copy: explicit filter state, indicators, and controlled filter application.
- Pattern to avoid: AI search or transcript body search as live default behavior.
- Adaptation for PodText: default search should cover item title, group title, enabled tags, and categories; transcript full-text search should be an explicit deferred action later.
- Prompt references: 11.

### Complex Relationship Filters

- Example name: `Complex Comma-Separated and Relationship Filters`.
- Files/classes observed: `FiltersLayout::AboveContent`, relationship filters, filter form columns, indicators.
- Why relevant: Phase 02 needs category/tag filters with clear desktop and mobile behavior.
- Pattern to copy: relationship filters, visible active-filter indicators, Apply/Clear behavior.
- Pattern to avoid: a dense admin-style filter wall on mobile.
- Adaptation for PodText: use desktop chips and a mobile drawer, with URL-backed search/sort/filter state where practical.
- Prompt references: 11.

### Real Estate Dependent Filters

- Example name: `Dependent Sale/Rent Price Filters Driving Columns`.
- Files/classes observed: dependent filter schema, toggle buttons, live/deferred filter updates.
- Why relevant: it shows how filter fields can react to other filter state.
- Pattern to copy: dependent filter UI where one choice changes available fields.
- Pattern to avoid: speculative complex filters before the public content search needs them.
- Adaptation for PodText: reserve dependent filter behavior for advanced fields such as provider/source/transcript-body mode.
- Prompt references: 11.

### Table Rendered as a Card Grid

- Example name: `Table Rendered as a Card Grid`.
- Files/classes observed: `contentGrid`, `Grid`, `Split`, `Stack`, image/text columns, custom pagination counts.
- Why relevant: public search results should feel like content cards while retaining table search/filter/sort/pagination behavior.
- Pattern to copy: Filament Table as a card grid with Blade/ViewColumn presentation.
- Pattern to avoid: nested cards or admin table styling on the public homepage.
- Adaptation for PodText: card content should be a `ContentItem` with group badge, effective transcription date, duration, categories, enabled tags, and pinned state.
- Prompt references: 11.

### Custom-Designed Table With ViewColumn Cells

- Example name: `Custom-Designed Table with ViewColumn Cells`.
- Files/classes observed: `ViewColumn::make()->view(...)`, custom Blade cells, relationship-aware query tuning.
- Why relevant: PodText can keep Filament table mechanics while rendering content-specific rows/cards.
- Pattern to copy: custom Blade view for result cards/rows and explicit relationship eager loading.
- Pattern to avoid: placing all presentation HTML in table column closures.
- Adaptation for PodText: extract item cards, badges, media links, and transcript metadata to Blade components.
- Prompt references: 11, 12.

### Manage Dynamic Homepage Sections

- Example name: `Manage Dynamic Homepage Sections`.
- Files/classes observed: `HomepageSectionResource`, form/table classes, visible toggles, reorderable order column, limit fields.
- Why relevant: Phase 02 needs admin-managed homepage UX and display settings.
- Pattern to copy: normal database records for ordered, visible homepage sections.
- Pattern to avoid: hard-coded homepage sections in a Blade file.
- Adaptation for PodText: sections may include latest items, category/tag slices, and group slices, but pinned items remain item-level ordering inside the combined list rather than a separate pinned section.
- Prompt references: 08, 11.

### Blog CMS With Filament Admin

- Example name: `Blog CMS With Filament Admin`.
- Files/classes observed: Post form relationships to category and tags, simple category resource.
- Why relevant: provides ordinary Filament relationship field patterns.
- Pattern to copy: relationship fields for categories/tags and simple admin management tables.
- Pattern to avoid: using simple blog categories as the final taxonomy model without hierarchy and inheritance.
- Adaptation for PodText: categories are custom hierarchical models; tags use Spatie tags with a content type and enabled-only public visibility.
- Prompt references: 08, 09, 11.

### E-Shop Admin With Bootstrap Storefront

- Example name: `E-Shop Admin With Bootstrap Storefront`.
- Files/classes observed: `ManageSettings` extending `SettingsPage`, typed `GeneralSettings`, `StatsOverviewWidget`, and `TableWidget`.
- Why relevant: Phase 02 needs global settings and lightweight dashboard metrics.
- Pattern to copy: typed settings class behind a Filament Settings page; simple stats/list widgets.
- Pattern to avoid: analytics dashboards or polling-heavy operational views.
- Adaptation for PodText: use Spatie Settings for site/homepage/public item-page settings; use dashboard widgets for counts and editorial warnings.
- Prompt references: 08, 13.

### Inventory Stock With CSV Import/Export

- Example name: `Inventory Stock with CSV Import/Export`.
- Files/classes observed: native `Importer`, `Exporter`, `ImportAction`, `ExportAction`, and `ExportBulkAction`.
- Why relevant: PodText already uses these APIs and Phase 02 expands the schema.
- Pattern to copy: native Filament import/export, relationship resolution, completed notifications, and bulk export actions.
- Pattern to avoid: replacing Filament import/export with custom CSV controllers.
- Adaptation for PodText: extend existing importers/exporters for categories, tags, transcriptions, metadata, and transcript `.md`/`.txt` file references.
- Prompt references: 10.

### Livewire Status Sidebar In Edit Page

- Example name: `Livewire Status Sidebar In Edit Page`.
- Files/classes observed: EditRecord page composition with a Livewire sidebar, event refresh, computed values, and sticky layout.
- Why relevant: future transcription studio work may need editor sidebars and live state.
- Pattern to copy: isolated Livewire components embedded into Filament pages when interactivity is justified.
- Pattern to avoid: building a studio during Phase 02 public browsing work.
- Adaptation for PodText: plan studio components later after transcription model, public item page, parser, and viewer are stable.
- Prompt references: 14.

## Research Conclusions

1. Public listing/search should use a dedicated public `ContentItem` listing component, likely a Filament Table rendered as cards or rows.
2. `Transcription` should become a child model, but public cards remain `ContentItem` cards.
3. Pinning belongs only to `ContentItem` and should affect item lists, not individual transcripts.
4. The effective/main transcription must be explicit enough to support latest sorting and item page defaults.
5. Categories should be custom and hierarchical; Spatie tags should remain flat and scoped by type.
6. Homepage sections and global settings are separate concerns: ordered/visible sections as content records, global limits/layout options as settings.
7. Media embed work should extend the current URL-only security model, not accept raw iframe HTML.
8. Import/export should remain Filament-native and should expand the existing reference-key strategy.
9. Dashboard work should stay editorial and lightweight.
10. Studio work remains future planning only.
