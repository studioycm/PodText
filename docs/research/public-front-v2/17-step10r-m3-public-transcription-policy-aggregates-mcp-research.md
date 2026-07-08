# Step 10R-M3 MCP Research - Public Transcription Policy and Aggregates

## Scope

Step 10R-M3 adds the public transcription policy and count/aggregate layer after the M1/M2 multi-transcriber foundation. It must keep public rendering largely unchanged while making public selection/counting policy-aware and pivot-backed.

## Laravel Boost

Access level: installed-version MCP guidance and local application/database metadata.

Tools used:

- `application_info`
- `database_schema`
- `database_query`
- `search_docs`

Findings:

- Installed stack: Laravel 13.18, Filament 5.6, Livewire 4.3, Pest 4.7, SQLite.
- `author_transcription` exists with `author_id`, `transcription_id`, `sort_order`, timestamps, unique `(author_id, transcription_id)`, and indexes.
- `transcriptions.author_id` remains nullable and populated for compatibility.
- Current local data has 8 transcriptions, 8 `author_transcription` rows, and 8 published transcriptions.
- Laravel supports constrained eager loading, `withCount`, aggregate subqueries, `whereHas`, `withExists`, and query builder subselects suitable for policy-aware public counts.
- Livewire URL state should not be renamed in M3. Existing `filterTranscriberId` still uses the URL alias `author`; this remains a compatibility alias until a later UI cleanup.
- Spatie Settings docs access was limited, but the project has established settings migration plus typed settings property patterns.

## FilamentExamples MCP

Access level: `search_examples` only. No source/detail fetch tool was available, so findings are snippet/search-level only.

Query batches:

1. `settings page JSON settings`, `public page query counts`, `eloquent aggregate counts`, `Livewire public filters`
2. `withCount dashboard cards`, `query aggregate stats widget`, `public page getViewData query`, `relation count distinct`
3. `DashboardMetrics cached service`, `getTabs badge modifyQueryUsing`, `ManageSettings form settings`, `custom page getViewData Livewire Url`

Relevant patterns:

- Settings pages keep state in typed fields and save normalized data through app-owned validation.
- Public Livewire pages can prepare query results in PHP support classes rather than Blade.
- Aggregate stats are commonly centralized in focused services instead of repeated table/card closures.
- Query badges/counts should use database-side counts/subqueries where possible.

Patterns avoided:

- Public Filament Tables were not reintroduced.
- No raw class/Blade/HTML settings are added.
- No generic CMS/settings model is introduced.

## Local Code Findings

- `PublicContentSettings` has JSON groups for public front settings, but no `transcription_policy` group yet.
- `PublicFrontConfigRegistry`, `PublicFrontConfigValidator`, and `PublicFrontRenderContext` already form the correct normalized config path.
- `PublicContentItemQueries::base()` eager-loads featured/latest published transcriptions and authors.
- `PublicContributorDiscovery` still counted public transcriptions with `transcriptions.author_id`, which is now compatibility-only.
- `PublicContentGroupQueries::base()` counts public content items but has no transcription count, latest transcription date, transcriber count, or word/read-time aggregates.
- The transcript viewer still renders all published transcriptions; broad rendering behavior is Step 10R-M4.

## M3 Implementation Notes

- Add `transcription_policy` settings with finite values:
  - `public_mode`: `featured_only` or `all_published`
  - `count_mode`: `featured_only` or `all_published`
  - `show_multiple_transcriptions_on_item_page`: boolean
- Default both modes to `featured_only`.
- Public counts and contributor discovery should use the new `author_transcription` pivot, not `transcriptions.author_id`.
- Existing public URL-backed state and card rendering should remain stable.
