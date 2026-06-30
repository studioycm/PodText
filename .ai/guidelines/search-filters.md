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
- Reuse the same `ContentItem` public visibility query across homepage, search, category, and tag landing pages.
- Include category hierarchy/descendants and enabled `content` tags according to the active taxonomy specs.
- Let explicit user-selected sorts override default pinned-first homepage ordering where the search spec requires it.

## Do not

- Do not make transcript body search the default live search.
- Do not let disabled tags appear publicly.
- Do not lock search pages to pinned-first order when the user selected another sort.

## Testing rules

- Search field coverage.
- Filter and sort order tests.
- URL state tests.
- Disabled tag exclusion.
- Effective/main transcription visibility tests.
- Category descendant and typed-tag scoping tests.

## Security rules

- Search query must use public item scope.
- Avoid raw SQL with user input.

## FilaCheck / FilaCheck Pro notes

- Tables need searchable columns.
- Custom filters need indicators.
- Relationship filters should be searchable/preloaded where record count can grow.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.

## Cross-cutting UI rules

- Slug fields, where present in admin surfaces feeding public filters, should auto-generate from title/name fields but allow manual override.
- Technical fields must have helper text, hints, or descriptions in admin forms.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, sort labels, and date labels.
- Admin dashboard widgets should include available search/filter editorial metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/search-and-filters-spec.md`
- `docs/phase-02/blueprints/11-public-homepage-search-blueprint.md`
- `docs/research/filament-examples-phase-02.md`
