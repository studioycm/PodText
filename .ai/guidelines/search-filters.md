# Search and Filters Guideline

## Purpose

Keep public search/filter/sort behavior explicit, URL-aware, and scoped to public `ContentItem` records.

## Preferred architecture

Filament Table inside a public Livewire component, rendered as item cards or rows.

## Do

- Default search: item title, group title, enabled tags, categories.
- Use explicit filters for category, tag, group, author, date ranges, duration, and provider.
- Add active indicators for custom filters.
- Persist important state in URL.
- Implement all required sort modes.

## Do not

- Do not make transcript body search the default live search.
- Do not let disabled tags appear publicly.
- Do not lock search pages to pinned-first order when the user selected another sort.

## Testing rules

- Search field coverage.
- Filter and sort order tests.
- URL state tests.
- Disabled tag exclusion.

## Security rules

- Search query must use public item scope.
- Avoid raw SQL with user input.

## FilaCheck / FilaCheck Pro notes

- Tables need searchable columns.
- Custom filters need indicators.
- Relationship filters should be searchable/preloaded where record count can grow.

## Related active docs

- `docs/phase-02/search-and-filters-spec.md`
- `docs/phase-02/blueprints/11-public-homepage-search-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
