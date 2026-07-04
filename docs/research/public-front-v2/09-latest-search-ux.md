# Public Front v2 Research: Latest and Search UX

## Purpose

Plan latest-section and search-page UX refinements with settings-backed card/layout behavior.

## Topic Scope

Latest as looper, card deterministic layout, search filters drawer/slide-over, active filter counts, URL state, and mobile/desktop behavior.

## Exact Search Terms Used

- Boost: "Livewire URL attribute query string pagination resetPage"
- Boost: "Tailwind CSS min-w-0 flex overflow line clamp grid card layout"
- Boost: "Tailwind CSS line clamp aspect ratio object cover responsive grid"
- FilamentExamples MCP: "Filament filter drawer slide over search filters"
- FilamentExamples MCP: "Filament custom filter panel active filter count"
- FilamentExamples MCP: "Filament load more pagination Livewire cards"
- FilamentExamples MCP: "Filament table as grid with cards filters"

## Boost Docs Used

- Livewire URL and pagination docs.
- Filament Action modal/slide-over docs for filter drawer inspiration.
- Tailwind layout docs for deterministic card sizing and overflow prevention.

## FilamentExamples MCP Examples Found

- `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`: cards with filters above content, content grid, image/text layout.
- `v4/full-projects/hotel-management-bookings/app/Filament/Booking/Pages/FindHotel.php`: search form plus result table.
- No exact public drawer/filter-chips example found.

## Actual Files, Classes, and Snippets Observed

- Local: `resources/views/livewire/public/content-item-search.blade.php` has search, sort, clear, filter panel, homepage sections, and custom card grids.
- Local: `app/Livewire/Public/ContentItemSearch.php` has URL-backed search/filter/sort properties and homepage-section rendering.
- Local: `resources/views/components/public/content-item-card.blade.php` needs stronger layout rules for large image variants.

## GitHub/Source Files Inspected

- LaravelDaily public-form and menu demo sources were inspected for public layout/action patterns, not search-specific implementation.

## Pattern To Copy

- Keep search and sort visible.
- Keep important state URL-backed.
- Use a dedicated filter action/button with active count.
- Use `min-w-0`, fixed tracks, aspect ratio, line-clamp, and stacked layout thresholds.

## Pattern To Avoid

- Do not bring back a public Filament Table for listings.
- Do not make transcript body the default live search.
- Do not store raw layout classes in settings.

## PodText Adaptation Notes

Prompt 11R replaced Filament Table listing with custom Livewire/Blade. Preserve that and improve the current component.

## JSON-First Settings Recommendation

Latest looper settings should include:

- heading/body
- total query size minimum 50
- page size integer 4-25
- pagination mode: next_previous, load_more, numbered
- card family/template key
- card font sizes and line clamps
- podcast display mode and separator

Search settings should include filter panel mode, visible filters, and default sort labels.

## Model/Table Considered

Rejected: no model required. Latest is a looper/section config.

## Recommended Model/Schema Options

No schema beyond the looper/homepage JSON config proposed in topic 3.

## Recommended Filament Patterns

SettingsPage fields for latest/search display defaults. Use Action modal/slide-over patterns only as admin/public UX inspiration.

## Public Livewire/Blade Implications

Latest section:

- heading row
- lightweight search
- next/previous controls on top
- load more at bottom
- no heavy filters

Latest item card:

- square cropped image
- transcriber name with icon
- date/read time row pushed to sides
- title/episode text with clamps and tooltip
- description visibility and clamp settings
- group display modes

Search page:

- filter drawer/slide-over opened by action button
- category toggle buttons
- tag chips/buttons
- active filter count badge
- clear all

## Tests

- Latest total query size minimum clamps to 50.
- Page size clamps to 4-25.
- Card layout mode emits expected safe classes/markup.
- Search filter count updates for category/tag/group/author/date/provider/duration filters.
- URL state remains stable across search/sort/filter changes.

## Security Notes

Search must use `PublicContentItemQueries` and never raw SQL with user input. Tooltips and descriptions must be escaped/sanitized.

## Open Questions

- Should search filters use a custom drawer or Filament Action modal/slide-over in public pages?
- Should latest search search only item/group title or also categories/tags?
- Should numbered pagination be available in v1 or only next/previous plus load more?
