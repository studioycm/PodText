# Tables, Resources, and Relation Managers

## Query Shape

Check table queries before column callbacks. Filament tables render many records at once, so per-row work multiplies quickly.

- Look for relationships accessed inside `formatStateUsing()`, `state()`, `url()`, `color()`, `icon()`, `description()`, visibility callbacks, row actions, and authorization callbacks.
- Prefer relationship columns such as `TextColumn::make('author.name')` when Filament can resolve the relation directly.
- Use `modifyQueryUsing()` on the table or resource page to eager load relationships with `with()`, add counts with `withCount()`, or add existence checks with `withExists()`.
- Use `withAggregate()` or select subqueries for sortable derived values instead of querying inside callbacks.
- Do not recommend joins by default for Eloquent resources unless the existing codebase already uses that pattern and the relationship semantics are clear.

## N+1 Hotspots

Flag database calls in any per-record callback. Common risky calls include:

- `Model::find()`, `first()`, `where()`, `query()`, `count()`, `exists()`, `pluck()`, `get()`, `all()`
- Relationship property access that is not eager loaded.
- `Gate::allows()` or policy checks that query per row.
- URL generation or labels that load related models per row.

Recommended fixes:

- Move query work into `modifyQueryUsing()`.
- Add eager loads to the table query, not inside the callback.
- Replace per-record counts with `withCount()`.
- Cache small lookup maps once per request with `once()` when eager loading is not a good fit.

## Search, Sort, and Filter Performance

Use `database_schema` before recommending indexes.

- Searchable text columns should map to indexed or acceptably small columns where possible.
- Default sorts and frequently used `orderBy()` columns should be indexed.
- Filters on status, type, boolean, tenant, date, and ownership columns should have matching indexes when tables are large.
- Relationship searches can produce `whereHas` queries. Check relationship depth and dataset size before recommending broad relation search.
- Avoid making every text column searchable. Prefer the fields users actually search.

## Large Table Surface Area

Flag tables that render too much at once:

- More than about 10 visible columns, especially with callbacks, images, badges, icons, or relationship state.
- Missing filters on obvious status/type/boolean/date columns.
- Text columns present but no useful searchable columns.
- Summarizers on large unfiltered tables when the aggregate is expensive.
- Bulk actions or exports that use unbounded queries without explicit query shaping.

Potential fixes:

- Add filters that reduce result sets before expensive columns or summarizers are used.
- Make low-priority columns toggleable or hidden by default.
- Add or tune pagination instead of increasing page size casually.
- Use export action query hooks to select columns and eager load exactly what the export needs.

## Custom Data and Deferred Loading

For tables backed by `records()` rather than Eloquent, Filament does not apply
search, filtering, sorting, or pagination automatically. Verify that the
callback consumes the injected state and performs bounded work. An unpaginated
custom-data table is acceptable only when the source is demonstrably bounded.

Use `deferLoading()` only when initial table loading is measured as expensive
and the deferred request improves the intended interaction. Count the extra
request and aggregate bytes; do not report initial TTFB alone.

## Table Findings

A strong finding includes:

- The callback or table method that causes repeated work.
- The query or relation that repeats.
- The likely multiplier, such as rows per page or global search result limit.
- The exact place to move the work, usually `modifyQueryUsing()` or the model query.
