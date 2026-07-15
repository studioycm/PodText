# Widgets, Dashboards, and Navigation

## Widget Polling

Filament widgets can poll automatically. Polling is useful only when the data must update live.

Flag:

- `StatsOverviewWidget` using default or aggressive polling.
- Dashboard widgets that run aggregate queries on every poll.
- Multiple widgets repeating the same aggregate queries.

Recommended fixes:

- Disable polling or lengthen the interval unless live updates are required.
- Cache aggregate results with `Cache::remember()` and clear them when relevant records change.
- Share expensive aggregate data across widgets where the codebase has an established service or query object pattern.

## Stats and Aggregate Queries

Stats are often rendered on dashboard entry and can be expensive.

Flag:

- Multiple `count()`, `sum()`, `avg()`, or date-range aggregate calls per widget render.
- Aggregates missing matching indexes for status, tenant, date, or ownership constraints.
- Stats that duplicate navigation badges or table summaries.

Recommended fixes:

- Cache stats for a short, explicit TTL.
- Add indexes after checking schema and query predicates.
- Precompute counters only when cache invalidation is not enough.

## Navigation Badges

`getNavigationBadge()` can run on every panel page load.

Flag:

- Uncached `count()`, `where()->count()`, or other queries inside `getNavigationBadge()`.
- Badge logic that loads models or relationships.
- Multiple resources computing similar badge counts independently.

Recommended fixes:

- Wrap badge values in `Cache::remember()`.
- Use stable, unique cache keys per tenant, user, panel, and filter scope where relevant.
- Describe invalidation, such as clearing the key after create/update/delete of affected records.

## Panel Boot and Visibility

Panel and navigation setup should not run heavy work for every request.

Flag:

- Expensive queries in panel providers, navigation registration, `canView()`, widget visibility, or resource visibility.
- Authorization checks that load many models during navigation rendering.

Recommended fixes:

- Move heavy logic to cached booleans or cheaper policy checks.
- Avoid work during panel boot that only a specific page needs.

## Widget Findings

A good finding identifies the refresh path: page load, widget poll, dashboard render, or navigation render. Include cache scope and invalidation expectations when recommending caching.
