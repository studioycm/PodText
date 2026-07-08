# Step 10R-M4 MCP Research - Public Rendering, Aggregates, and Performance

## Scope

Selected mini-step: Step 10R-M4 - Public rendering, card templates, Livewire, Blade, and aggregate attributes.

This research covers public multi-transcription rendering, card-template source expansion, Livewire memoization, eager loading under lazy-loading prevention, query-count testing, and the M4-owned performance backlog.

## Laravel Boost Access

Access level: installed-version application/package guidance and database inspection.

Tools used:

- `application_info`
- `database_schema`
- `database_query`
- `search_docs`

Findings:

- Installed stack is Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Tailwind 4.3.2, SQLite locally.
- `author_transcription` exists and M1/M2/M3 migrations are applied locally.
- `transcriptions.author_id` remains for compatibility and existing seeded rows have pivot-backed transcribers.
- Laravel supports `Model::preventLazyLoading(! $this->app->isProduction())` from a service provider boot method.
- Livewire `#[Computed]` memoizes a computed property for the request lifecycle and should be consumed as a property from the component/view.
- Pest/Laravel support database query-count assertions, but for this step a `DB::listen` upper-bound harness is less brittle for public page rendering.
- Eloquent relationship and aggregate work should prefer Builder-composed eager loads/subselects so SQLite and MySQL both execute the queries.

Database evidence from local-only evaluation data:

- 24 rows in `author_transcription`.
- 16 transcriptions total.
- 16 transcriptions with compatibility `author_id`.
- 8 content items with a featured transcription.

These values are local evaluation data only. Tests must create their own fixtures.

## Boost Search Docs Queries

- `Livewire computed property memoize public component URL state`
- `Laravel preventLazyLoading service provider production`
- `Eloquent constrained eager loading aggregate addSelect subquery Laravel`
- `Pest expectsDatabaseQueryCount database query count Laravel`
- `Filament SettingsPage nested array Builder Repeater settings`
- `Spatie settings cache settings saved event`

Usable guidance:

- Use `#[Computed]` for request-local memoization in `TopTranscribersSection` and `ContentItemTranscriptViewer`.
- Enable lazy-loading prevention outside production and then fix suite-exposed eager-load gaps.
- Keep settings/config values normalized through existing registry/validator paths before public rendering.
- Keep query-count assertions bounded rather than exact for rendered pages with Livewire/settings queries.

## FilamentExamples MCP Access

Access level: search/snippet access only. No source/fetch/detail tool was available.

First-pass query batches:

- `Livewire public cards`
- `URL state filters`
- `public page grid`
- `custom page layout`
- `settings page nested arrays`
- `builder repeater settings`
- `custom page view data`
- `livewire computed page`
- `card grid presenter`

Refined query batches:

- `getViewData aggregate stats`
- `relationship eager load view page`
- `modifyQueryUsing eager load`
- `select multiple relationship form`
- `repeater json settings`
- `public page custom blade`
- `Livewire custom Filament page`
- `URL query state page`
- `getViewData public page`

Relevant snippets/patterns:

- GitHub-style profile page snippets used `getViewData()`/derived view data rather than pushing aggregate formatting into Blade.
- Quiz result/page snippets loaded required relationships in `mount()` before rendering custom views.
- Leaderboard/stat snippets used aggregate query values prepared before Blade.
- Monthly attendance snippets used `#[Url]` and page-level data preparation for Livewire state.
- Account settings snippets showed nested settings state paths and array-backed form state.

PodText adaptation:

- Prepare card view-models once per grid/presenter layer, then render arrays in Blade.
- Keep nested `transcription_display` settings normalized by the app-owned config validator.
- Avoid adding raw classes/HTML to JSON settings.

## Code Inspection Summary

Inspected areas:

- Public Livewire components for search, group detail items, contributor pages, top transcribers, and transcript viewer.
- Public Blade cards/grids and item/group/contributor pages.
- Card presenters, template registry, template renderer, and config resolver/validator.
- Public item/group/contributor query helpers and transcription selector/aggregates.
- Transcription model boot hooks and service provider bindings.

Key findings:

- Content-item cards still use an internal `authors` data key for transcription-backed transcribers.
- Card templates do not yet expose all M4 attributes such as `content_item.transcribers`, `content_item.transcription_count`, `content_group.total_reading_time`, or `contributor.public_item_count`.
- `ContentItemTranscriptViewer::publishedTranscriptions()` runs during mount normalization and render, and the viewer always lists all published transcriptions.
- `TopTranscribersSection::contributors()` is queried during mount normalization and render.
- `Transcription::saved` always attempts compatibility pivot sync.
- `PublicDisplaySectionResolver` re-finds section targets even when the section relation was eager-loaded.
- `ShowContentItem` uses a direct item query and needs M3 aggregate subselects for header/count rendering.
- `PublicContentItemQueries::base()` currently adds aggregate subselects to every listing; M4 will consume them where possible and leave opt-in pruning to P2.

## Resulting Implementation Direction

- Apply display decisions D1-D8 without implementing labels/icons/group rows, which remain M5.
- Add finite `transcription_display` settings tokens to card surfaces and normalize them.
- Render item card transcribers from the selected/effective transcription, with contributor-context cards selecting contributor-specific transcriptions.
- Render count badges only as finite template metadata parts and only when policy/count settings call for them.
- Add group/contributor aggregate attributes to card-template registry and presenters.
- Memoize top transcribers and transcript viewer transcription lists with Livewire computed properties.
- Enable lazy-load prevention outside production and fix public rendering eager-load gaps exposed by tests.
- Add a query-count regression harness with fixtures owned by tests.
