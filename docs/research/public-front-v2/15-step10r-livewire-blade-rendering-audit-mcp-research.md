# Public Front v2 Step 10R Livewire/Blade Rendering Audit MCP Research

## Scope

This research supports the Step 10R Livewire/Blade/support-class audit for public rendering, settings, card templates, card layout, and transcriber attribution.

No app-code changes were made from this research.

## Access Level

Laravel Boost was available and returned installed-version application information, database schema, and documentation summaries.

FilamentExamples MCP exposed `search_examples` only in this session. No source/read/fetch/details tool was available, so examples below are search-result names, paths, snippets, and inferred patterns only. This is search/snippet access, not deep source access.

## Laravel Boost Results

### Application Info

Boost `application_info` confirmed the installed stack:

- PHP 8.4
- Laravel 13.18.0
- Filament 5.6.7
- Livewire 4.3.3
- Pest 4.7.4
- Laravel Boost 2.4.11
- SQLite local database

### Database Schema

Boost `database_schema` confirmed:

- `transcriptions.author_id` is a nullable foreign key to `authors.id` with `on_delete set null`.
- `Transcription` is single-author in the current schema.
- `authors` has stable `reference_key`, `name`, `slug`, and `bio_markdown`.
- `author_content_item` exists as a separate item-author pivot with unique `(author_id, content_item_id)`.
- No `author_transcription` pivot exists.
- `settings` stores Spatie Settings payloads by `group` and `name`.
- No footer/rich-section builder table or dashboard metric table exists.

### Search Docs: Settings And Cache

Queries used:

- Spatie Settings cache/repository behavior
- Laravel cache
- request-scoped cache

Useful findings:

- The installed Filament SettingsPage flow fills the settings form, saves the typed settings object, and exposes lifecycle hooks including `afterSave`.
- Laravel 13 documents the `once()` helper for request-duration memoization.
- Laravel 13 documents `Cache::memo()` for per-request/job cache-store memoization.
- Laravel cache tags are not supported by every driver. Because this project uses SQLite/database locally, Step 10R should not depend on tag-based invalidation.

PodText adaptation:

- Use a request-scoped `PublicFrontRenderContext` first.
- Use persistent cache only after the render context exists and only with an explicit cache key/invalidation plan.
- Put any future persistent invalidation in the `PublicContentSettings` SettingsPage `afterSave` hook.

### Search Docs: Livewire URL State

Queries used:

- Livewire render lifecycle
- Livewire URL state
- pagination query string

Useful findings:

- Livewire 4 supports `#[Url]` for URL-backed component properties.
- URL attributes support aliases, history behavior, keeping default values, and explicit `except` values.
- Pagination is URL-aware by default unless `WithoutUrlPagination` is used.

PodText adaptation:

- Existing public components correctly use URL-backed search/filter/sort state in several places.
- Settings/render context should be request-scoped and should not be duplicated into Alpine or hidden non-authoritative state.

### Search Docs: Filament SettingsPage, Builder, Repeater, RichEditor

Queries used:

- Filament SettingsPage / Builder / Repeater
- RichEditor rendering
- Builder previews

Useful findings:

- Filament SettingsPage fields map to typed settings properties and save the settings object.
- Builder and RichEditor preview patterns exist, but previews should reuse real renderer output where possible.
- RichEditor content needs an app-owned safe rendering boundary for public output.

PodText adaptation:

- Card template admin preview should wait until the public renderer exists, then reuse it.
- Rich columns/footer should render through app-owned renderers and `SafeMarkdownRenderer`/safe RichEditor rendering, not raw Blade or raw HTML from JSON.

### Search Docs: Eloquent Relationships

Queries used:

- Eloquent relationship changes
- many-to-many pivot
- constrained eager loading

Useful findings:

- `withWhereHas`, constrained eager loading, `withCount`, `loadCount`, `belongsToMany`, `sync`, `syncWithoutDetaching`, and pivot constraints are the relevant Laravel APIs.

PodText adaptation:

- Immediate transcriber attribution fixes can use `Transcription::author()` and eager loading.
- A future `author_transcription` pivot is feasible but should be a separate schema prompt because it affects admin forms, import/export, public queries, counts, and tests.

### Search Docs: Testing APIs

Queries used:

- Pest testing APIs
- Livewire testing APIs
- query param testing

Useful findings:

- Use `Livewire::test()` with property updates and assertions for Livewire components.
- Use `withQueryParams()` for URL-state assertions.
- Use HTTP/view assertions for rendered public pages.

PodText adaptation:

- Step 10R tests should assert visible custom template output, not only `data-card-template-*`.
- Settings tests should save settings and assert a new request renders updated values without stale cache.

## FilamentExamples Batch 1: Settings And Rendering

Queries:

- `settings page cache`
- `public settings reader`
- `render context`
- `SettingsPage after save`
- `Filament settings defaults`

Result summaries:

- Account Settings Cluster Pages: settings forms use typed form state, fill from settings, and update/save through page methods.
- E-Shop Admin With Bootstrap Storefront: `ManageSettings extends SettingsPage` backed by `GeneralSettings`.
- Several results showed focused page classes preparing data before views render.

Pattern to copy:

- Keep the SettingsPage as the admin write boundary.
- Normalize public settings before renderers consume them.
- Use lifecycle hooks for post-save work.

Pattern to avoid:

- Do not let every public component independently read and normalize the same settings payload.
- Do not build separate preview-only settings render paths.

PodText adaptation:

- Step 10R-A should introduce `PublicFrontRenderContext` and route Livewire, page classes, menu/forms, cards, and future footer/rich sections through it.

## FilamentExamples Batch 2: Card Rendering

Queries:

- `card template builder`
- `custom card renderer`
- `card grid layout`
- `Builder block preview`
- `ViewColumn card rendering`

Result summaries:

- Table Rendered As Card Grid: examples showed `contentGrid`, disabled table row URLs, and card-style layouts.
- ViewColumn examples showed rendering custom admin table cells through views.
- Markdown/Rich Editor Preview Forms showed preview actions and `ViewEntry`-style preview patterns.

Pattern to copy:

- Use controlled view data and finite class maps for card-like rendering.
- Reuse one renderer for preview and public output if admin preview is added later.

Pattern to avoid:

- Do not reintroduce public Filament Tables just to get card grids.
- Do not let custom template JSON point to arbitrary Blade views, classes, Tailwind strings, or HTML.

PodText adaptation:

- Step 10R-B should convert the existing compatibility template data into a real finite part renderer for `content_item`, `content_group`, and `contributor` families.

## FilamentExamples Batch 3: Livewire Public Pages

Queries:

- `Livewire public cards`
- `public page grid`
- `URL state filters`
- `pagination card grid`
- `custom page layout`

Result summaries:

- Monthly Attendance Grid Tracker showed a custom Filament page using URL state and `getViewData()` to prepare data for the view.
- Form and Table on One Custom Page showed combining page state with child components, but the table pattern is admin-oriented.
- Repeated custom page results favored preparing state in PHP rather than in Blade.

Pattern to copy:

- Keep URL-backed filters in the Livewire/page class.
- Prepare public view models in the class/support layer before Blade.

Pattern to avoid:

- Do not move public browse/search back to Filament Tables.
- Do not let Blade call settings readers or resolve route/form/template JSON repeatedly.

PodText adaptation:

- Existing public components can keep their URL state, but settings-derived card/layout/page config should come from `PublicFrontRenderContext` or component-prepared DTOs.

## FilamentExamples Batch 4: Footer And Rich Sections

Queries:

- `footer builder`
- `rich columns section`
- `Builder columns`
- `form CTA section`
- `dynamic homepage sections`

Result summaries:

- Manage Dynamic Homepage Sections examples showed ordered homepage sections with constrained type/target fields and controller/page preparation.
- Large form examples showed Builder/Repeater-style section composition in admin.

Pattern to copy:

- Keep dynamic homepage sections constrained.
- Prepare section render data in PHP.
- Use semantic tokens and finite block types.

Pattern to avoid:

- Do not turn homepage/footer into a generic CMS.
- Do not store raw Tailwind, raw CSS, raw Blade, iframes, scripts, or arbitrary HTML in JSON.

PodText adaptation:

- Step 9F / 10F should wait for Step 10R-A/B/C so rich columns/footer can reuse the render context, card presentation, form CTA resolver, and safe rich rendering boundary.

## FilamentExamples Batch 5: Transcriber Relationships

Queries:

- `many to many authors`
- `relation manager attach`
- `transcription authors`
- `pivot form relation`
- `import export relationships`

Result summaries:

- CMS/blog examples showed single `author_id` relationship selects.
- Stock management examples showed relation managers, attach/detach behavior, and import/export-adjacent patterns.
- Custom quote/product field examples showed loading/saving related rows through focused component methods.

Pattern to copy:

- Keep immediate display fixes on existing relationships.
- If multi-author transcription is needed, treat it as a separate schema/admin/import/export/query/testing prompt.

Pattern to avoid:

- Do not silently reinterpret `author_content_item` as transcription authors.
- Do not retrofit many-to-many transcription authors without import/export and count-query updates.

PodText adaptation:

- Step 10R-C should use `transcriptions.author_id` for public transcriber display now.
- A future pivot should be named and migrated explicitly, then public contributor counts should switch from `transcriptions.author_id` to the pivot.

## Refined Pass

Queries:

- `ManageSettings SettingsPage GeneralSettings`
- `large employee form tabs settings page`
- `SettingsPage Repeater settings`
- `markdown rich editor preview forms`
- `Builder block previews preview view`
- `custom view column cards`
- `manage homepage sections HomeController sections`
- `custom public page URL Livewire getViewData`
- `quote products custom field relation save`

Refined findings:

- Settings examples reinforced a single admin settings page backed by typed settings classes.
- Preview examples reinforced preview-as-view-data, not preview-as-a-second-rendering-engine.
- Homepage section examples reinforced constrained section types and page/controller-side preparation.
- Relationship examples reinforced separate relationship save logic when a schema pivot exists.

## Final PodText Research Conclusions

- Request-scoped public-front context is the first architectural fix.
- Persistent settings cache is optional and should not replace a normalized context.
- `afterSave` is the right public settings invalidation hook if any persistent or derived cache is introduced.
- Existing card templates are safe as configuration data, but the renderer is still partial.
- Public item cards need prepared presentation data that includes effective transcription author(s).
- Step 9F / 10F should wait for the Step 10R context, card renderer, and attribution fixes.
