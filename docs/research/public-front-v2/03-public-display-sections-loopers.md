# Public Front v2 Research: Public Display Sections and Loopers

## Purpose

Plan a generalized homepage section and looper/query-display system without creating generic display models by default.

## Topic Scope

Homepage sections, latest/top/manual/query-generated displays, source config, manual include/exclude, pagination/load-more, section rendering, and safe query construction.

## Exact Search Terms Used

- Boost: "Filament table reorderable rows reorderRecordsTriggerAction default sort"
- Boost: "Filament table bulk select all matching records select all pages bulk action selected records"
- Boost: "Filament table ViewColumn custom Blade card layout"
- Boost: "Filament QueryBuilder filter constraints safe stored query configuration"
- FilamentExamples MCP: "Filament homepage sections dynamic sections reorderable table"
- FilamentExamples MCP: "Filament query builder filter sections saved configuration"
- FilamentExamples MCP: "Filament table bulk select all matching select filtered records"
- FilamentExamples MCP: "Filament load more pagination Livewire cards"

## Boost Docs Used

- Filament table docs for bulk selection, select current page, custom data, actions, and record URLs.
- Filament QueryBuilder docs for constraint pickers; useful as an admin UX reference, but raw serialized constraints should not be stored blindly.
- Livewire pagination docs for `WithPagination`, URL query strings, `resetPage()`, `nextPage()`, and `previousPage()`.

## FilamentExamples MCP Examples Found

- `v4/full-projects/hotel-management-bookings/app/Filament/Booking/Pages/FindHotel.php`: custom page with form state and records table after a search.
- `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/Pages/ManagePlayerStats.php`: custom resource page with computed table records.
- `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`: card grid table with filters above content.

## Actual Files, Classes, and Snippets Observed

- Local: `app/Models/HomepageSection.php` has type, category, tag, content group, limit, sort order, and visibility.
- Local: `app/Enums/HomepageSectionType.php` has `latest`, `category`, `tag`, `content_group`, `top_transcribers`, `curated_query`.
- Local: `app/Livewire/Public/ContentItemSearch.php` renders homepage sections and uses URL-backed state.
- Local: `app/Support/PublicContent/PublicContentItemQueries.php` centralizes public item visibility.

## GitHub/Source Files Inspected

- LaravelDaily menu demo layout showed a public Blade shell rendering configured menu output; useful only as high-level public layout inspiration.

## Pattern To Copy

- Keep a central query resolver for public content visibility.
- Store semantic source and display options, then resolve through a registry.
- Use admin table actions for selecting/deselecting visible or filtered records when practical.
- Preserve existing `HomepageSection` typed fields for current behavior and add JSON only where needed.

## Pattern To Avoid

- Do not store raw SQL or arbitrary query strings.
- Do not create `PublicDisplaySection` or `PublicLooper` tables by default.
- Do not let loopers bypass public visibility constraints.

## PodText Adaptation Notes

`HomepageSectionType::CuratedQuery` already exists but is not public-renderable. This is the natural bridge to looper JSON, but implementation should still start by extending `HomepageSection` rather than introducing a generic model.

## JSON-First Settings Recommendation

Add section-specific JSON on `HomepageSection` later:

- `source_config`: source type, category/tag/group/author keys, filter presets, latest/top/manual source options.
- `selection_config`: manual includes/excludes and saved filtered selections using portable keys.
- `display_config`: heading/body, card family/template, layout, link to full page, hide/show link.
- `pagination_config`: mode, page size, total limit, load more behavior.

Site-wide defaults remain in settings JSON.

## Model/Table Considered

Rejected: `PublicDisplaySection` and `PublicLooper`. Existing `HomepageSection` already models ordered homepage slices. A future generic model is a user decision only if non-homepage displays become numerous and unmaintainable.

## Recommended Model/Schema Options

Optional migration later: add nullable JSON columns to `homepage_sections`. Keep existing columns for backward compatibility and simple filters.

## Recommended Filament Patterns

- Extend `HomepageSectionResource` form with Builder/Repeater sections that appear based on `type`.
- Use `Select` fields for allowed source types and sort modes.
- Use a custom selection page or modal if multi-select-by-table becomes too cramped in a settings form.
- Use table bulk actions to materialize filtered selections into portable keys.

## Public Livewire/Blade Implications

Public loopers should be class-based Livewire components that consume normalized source/display config and keep search/pagination state URL-backed where it matters. Homepage latest can use next/previous and load more without heavy filters.

## Tests

- Existing homepage section behavior remains unchanged.
- Source config cannot expose draft items, disabled tags, or unpublished groups.
- Manual include/exclude respects public visibility.
- Latest section respects min total query size 50 and page-size range 4-25.
- Pagination modes render expected controls and preserve state where required.

## Security Notes

All query options are registry keys. Admin-selected filters should be converted into known predicates, not stored SQL. Manual selections should use slugs/reference keys where portable.

## Open Questions

- Should all display sections be homepage-only in v1?
- Should manual selections store numeric IDs, reference keys, slugs, or a mixed resolved snapshot?
- Should infinite scroll be explicitly deferred until after load-more and next/previous are stable?
