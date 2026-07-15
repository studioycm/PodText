# Global Search

Filament global search can query every globally searchable resource. Treat it as a cross-resource query fan-out.

## Resource Scope

Flag:

- Many resources enabled for global search by default when only a few are useful.
- Resources with broad searchable attributes on large tables.
- Resources with relation attributes in global search without clear indexes or constraints.

Recommended fixes:

- Disable global search for resources that users do not need to search globally.
- Use opt-in global search at the panel level when the app has many resources.
- Keep searchable attributes narrow and intentional.

## Query Shape

Filament global search uses the resource query, applies searchable attribute constraints, calls `modifyGlobalSearchQuery()`, limits results, and then maps records into search results.

Flag:

- Relation search that creates costly `whereHas` queries.
- Missing eager loads for data used in result title, details, URL, or actions.
- High result limits without a user need.
- Case-insensitive or split-term search on large unindexed text columns.

Recommended fixes:

- Use `getGlobalSearchEloquentQuery()` or `modifyGlobalSearchQuery()` to eager load and constrain results.
- Lower `getGlobalSearchResultsLimit()` where appropriate.
- Add indexes only after checking schema and database behavior.
- Avoid relation search unless the relation column is important and performant.

## Result Rendering

The result mapping step runs per result.

Flag:

- Database queries in `getGlobalSearchResultTitle()`, `getGlobalSearchResultDetails()`, `getGlobalSearchResultUrl()`, or `getGlobalSearchResultActions()`.
- Per-result authorization or action construction that queries repeatedly.

Recommended fixes:

- Eager load everything result rendering needs.
- Use already-loaded attributes and relationships.
- Keep result details short and cheap.

## Global Search Findings

Report whether the issue is fan-out across resources, a single expensive resource query, relation search, or per-result rendering work.
