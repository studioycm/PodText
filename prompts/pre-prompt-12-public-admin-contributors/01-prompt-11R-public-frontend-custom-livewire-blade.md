# Prompt 11R: Public Homepage/Search Custom Livewire + Blade Refactor

## Goal

Refactor the public homepage/search from Filament Table rendering to a custom Livewire + Blade public discovery layer.

This is a correction/refinement after Prompt 11. Prompt 11 successfully moved public results to `ContentItem`, but the visible UI still relies on Filament Table mechanics. The desired public frontend needs full control over homepage sections, filters, sorting, card layout, and responsive styling.

## Scope

Implement only public homepage/search/category/tag listing UI refactor.

Allowed:
- custom Livewire state/query logic;
- custom Blade card grids/rows;
- custom search/filter/sort UI;
- custom homepage section rendering;
- settings-controlled card/result presentation;
- Alpine only for local UI such as mobile filter drawer/collapsible panels;
- tests and docs/current-state update.

Out of scope:
- no public item page parser/media overhaul;
- no dashboard widgets;
- no contributor/transcriber directory;
- no admin relationship selector changes;
- no import/export changes;
- no studio/sync;
- no automatic metadata extraction.

## Read first

- `AGENTS.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/ai-development-lessons.md`
- `docs/phase-02/public-panel-ux-spec.md`
- `docs/phase-02/search-and-filters-spec.md`
- `docs/phase-02/homepage-settings-spec.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/blueprints/11-public-homepage-search-blueprint.md`
- `prompts/11-phase-02-public-homepage-search.md`
- `.ai/guidelines/public-panel.md`
- `.ai/guidelines/search-filters.md`
- `.ai/guidelines/settings-dashboard.md`
- `.ai/guidelines/tooling-quality.md`

Inspect current implementation:
- `app/Filament/Public/Pages/BrowseContentGroups.php`
- `resources/views/filament/public/pages/browse-content-items.blade.php`
- `app/Livewire/Public/ContentItemSearch.php`
- `resources/views/livewire/public/content-item-search.blade.php`
- `resources/views/filament/tables/columns/public-content-item-card.blade.php`
- `app/Support/PublicContent/PublicContentCardOptions.php`
- `app/Settings/PublicContentSettings.php`
- `app/Filament/Pages/PublicContentSettings.php`
- public category/tag/search page classes and views
- existing tests, especially `tests/Feature/PublicHomepageSearchTest.php`

## Preflight

Run:
- `git status --short --branch`
- `git log --oneline --decorate -12`

Confirm:
- Prompt 11 is complete in current state.
- Prompt 12 has not started.
- Working tree is clean.

Use:
- Laravel Boost `application_info`.
- Laravel Boost `database_schema`.
- Laravel Boost `search_docs` before changing Livewire, URL state, Filament page, settings, or pagination code.
- FilamentExamples MCP public table/card/filter examples if useful, but do not keep Filament Table for the public listing.

Run baseline:
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`

Stop if baseline fails outside this prompt scope.

## Implementation requirements

### 1. Replace Filament Table public listing

The public homepage/search/category/tag listing must not render `{{ $this->table }}` as the main public UI.

Refactor `App\Livewire\Public\ContentItemSearch` to a custom Livewire component that:
- uses normal Livewire properties and methods;
- uses `WithPagination`;
- returns a paginated `ContentItem` query;
- keeps important state URL-backed with `#[Url]` or `queryString()`;
- exposes search, filters, sort, clear filters, result count, and section data;
- uses Blade loops/components for output.

Do not use:
- `HasTable`;
- `InteractsWithTable`;
- `Filament\Tables\Table`;
- `ViewColumn`;
- Filament Table filters for the public frontend.

Admin Filament tables remain allowed elsewhere.

### 2. Create reusable public Blade components

Create or refactor to normal Blade components:

- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-group-badge.blade.php`
- optional `resources/views/components/public/content-item-grid.blade.php`
- optional `resources/views/components/public/public-filter-panel.blade.php`
- optional `resources/views/components/public/public-filter-drawer.blade.php`

Move the card presentation out of:

`resources/views/filament/tables/columns/public-content-item-card.blade.php`

You may keep the old table-column view only as a compatibility wrapper if tests or existing code still need it, but the public page should use normal Blade components.

### 3. Custom homepage sections

Render homepage sections as actual sections, not one flattened Filament table query.

Supported section types:
- `latest`;
- `category`;
- `tag`;
- `content_group`.

Each visible section should render:
- heading;
- optional description/target label;
- item cards;
- empty state if no public items;
- optional “view more” link where useful.

Keep deferred:
- `curated_query`;
- `top_transcribers` until Prompt 11B.

Homepage section queries must return `ContentItem` records only and must enforce public visibility.

### 4. Public visibility query

Preserve one reusable public item query rule:
- published group;
- published item;
- effective/main published transcription exists.

Eager-load enough to avoid N+1:
- content group;
- authors;
- item categories;
- group categories;
- enabled content tags;
- featured/latest/effective transcription data as currently supported.

### 5. Settings-controlled presentation

Preserve and use `PublicContentCardOptions`.

Settings should affect actual Blade output:
- card image size;
- density;
- title size;
- show/hide group badge;
- show/hide authors;
- show/hide categories;
- show/hide tags;
- show/hide duration;
- show/hide effective date;
- show/hide description;
- description line count;
- cards per page;
- layout cards/rows.

Rules:
- Do not store raw CSS, raw Tailwind classes, or arbitrary style strings.
- Use finite semantic values and map them to classes in PHP/Blade.
- Ensure all possible Tailwind classes are statically discoverable.
- Old/missing settings rows must use safe defaults.

### 6. Search/filter/sort UI

Implement custom Blade UI for:
- search input;
- result count;
- clear filters;
- sort dropdown;
- category filter;
- enabled tag filter;
- content group filter;
- author filter;
- provider filter if existing media fields support it;
- date range filters;
- duration range filters;
- media-presence filter.

Use Alpine only for:
- mobile filter drawer;
- collapse/expand advanced filter panel;
- local UI transitions.

Do not duplicate authoritative filter state in Alpine.

### 7. Routes/pages

Keep:
- `/` homepage;
- `/search`;
- `/categories/{categorySlug}`;
- `/tags/{tagSlug}`.

They must all use the same content-item card component.

## Tests

Update or create tests to prove:

- homepage renders `ContentItem` cards through custom Blade components;
- homepage does not render the old group browser or group-card-only layout;
- homepage does not use the Filament table output as the primary UI;
- homepage sections render as separate sections;
- visible sections include latest/category/tag/content-group items;
- hidden sections do not render;
- curated query sections are ignored/deferred;
- settings affect card output;
- settings use semantic classes/data attributes, not raw DB CSS;
- card field visibility settings work;
- card layout/density/image/title settings work;
- cards per page setting is consumed;
- public visibility rules still hide draft/no-effective-transcription items;
- latest effective transcription ordering works;
- pinned-first homepage default works;
- explicit sort can override pinned-first where required;
- search by item title, group title, enabled tag name, and category name works;
- disabled tags are hidden and do not match results;
- category descendant and inherited group category filters work;
- category/tag landing pages reuse content-item cards;
- result count, clear filters, URL state, empty state, and RTL marker are present.

Keep browser tests small if used.

## Documentation update

If successful, update `docs/phase-02/current-project-state.md` before commit:
- mark Prompt 11R complete;
- keep Prompt 11A next/not started;
- record that public listing no longer uses Filament Table;
- record custom Livewire + Blade public listing components;
- record any deferred items.

Patch other docs only if stable requirements changed.

## Quality gate

Run focused tests first:
- `php artisan test --filter=PublicHomepageSearchTest`

If PHP changed:
- `vendor/bin/pint --dirty --format agent`

Then run:
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`

Do not run `vendor/bin/filacheck --fix`.

## Commit

Commit only after full gate passes:

`refactor: customize public content item discovery`

## Final report

Include:
- files changed;
- public components/views changed;
- whether Filament Table was removed from public listing;
- settings behavior;
- homepage sections behavior;
- tests added/updated;
- commands/results;
- FilaCheck summary;
- current git status;
- commit hash if committed;
- confirm Prompt 12 was not started.

End with exactly:

“Prompt 11R public frontend refactor is complete. Prompt 12 has not been started.”
