# Public Front v2 Step 10R MCP Research

## Purpose

Research for the Step 10R planning audit: rendering/settings architecture, card templates, public card layout, rich sections/footer, and transcriber relationship/admin patterns.

This research is planning-only. No implementation was started.

## Tool Access

Laravel Boost tools used:

- `application_info`
- `database_schema`
- `search_docs`

FilamentExamples MCP access level:

- `search_examples` only.
- No source/read/fetch/details tool was exposed in this session.
- Findings below are based on search result names, paths, snippets, and class/file references. They should be treated as pattern orientation, not copied source.

## Laravel Boost Results

### Application Info

Installed versions reported by Boost:

- PHP 8.4
- Laravel 13.18.0
- Filament 5.6.7
- Livewire 4.3.3
- Pest 4.7.4
- Tailwind CSS 4.3.2
- Laravel Boost 2.4.11
- SQLite local database

### Database Schema

Relevant schema facts:

- `transcriptions.author_id` is nullable and references `authors`; current transcription attribution is single-author.
- `author_content_item` links item-level authors to content items and is separate from transcription authors.
- `content_items.featured_transcription_id` points to a transcription.
- `settings` stores Spatie settings payloads by group/name.
- `homepage_sections` stores JSON config in `source_config`, `selection_config`, `display_config`, and `pagination_config`.

### Docs Search Topics

Queries:

- `Spatie Settings cache repository settings save refresh`
- `Laravel Cache memo remember tags forget invalidation once request cache`
- `Livewire render lifecycle URL query string pagination computed`
- `Filament SettingsPage Builder Repeater RichEditor`
- `Eloquent many to many pivot sync withCount whereHas eager loading`
- `Pest Livewire testing URL query string assert see`

Relevant docs guidance:

- Laravel cache supports `Cache::memo()` for request/job in-memory cache. This is useful for request-scoped derived data when available.
- Cache tags are not supported by all cache drivers. Avoid designing public-front invalidation around tags.
- `Cache::forget`, `remember`, and `rememberForever` are stable options for a single versioned derived-cache key if persistent app caching is later needed.
- Livewire URL attributes and pagination query-string behavior support public URL-backed state.
- Livewire component rendering should prepare data in PHP; nested looped components need stable `wire:key`.
- Filament Builder/Repeater patterns are appropriate for finite JSON structures, but public rendering still needs app-owned sanitization and class maps.
- Eloquent relationship docs support a later `author_transcription` pivot if multi-transcriber transcriptions become a product requirement.

PodText adaptation:

- Step 10R-A should begin with request-scoped context rather than persistent derived cache.
- If persistent derived cache is introduced later, use one versioned key and explicit invalidation after `PublicContentSettings` save.
- Step 10R-C can use current single `Transcription::author` relationships immediately; many-to-many transcription authors should be a separate schema prompt.

## FilamentExamples Query Batches

### Batch 1 - Settings, Rendering, Caching

Queries:

- `settings page cache`
- `SettingsPage after save`
- `Filament settings defaults`
- `render context`
- `public page settings`

Result summaries:

- Large settings forms commonly use full-width `Tabs`, `Section`, and `Fieldset` layouts.
- Search results did not expose a dedicated Filament settings-cache invalidation example.
- Public page settings examples were mostly custom page layout patterns rather than Spatie Settings specifics.

Examples/classes/snippets found:

- `v4/forms/large-employee-form-with-tabs/.../EmployeeForm.php`
  - Snippet showed `Tabs::make()->columnSpanFull()->tabs([...])`.
- `v4/forms/large-employee-form-with-sections/...`
  - Snippets showed large forms broken into full-width sections and fieldsets.

Pattern to copy:

- Keep the settings page organized into full-width tabs/sections.
- Use structured finite form controls and helper text for technical JSON settings.

Pattern to avoid:

- Do not let public views read and normalize settings ad hoc just because settings forms are large.
- Do not add raw class fields to settings.

PodText adaptation:

- Add `PublicFrontRenderContext` as the public-render boundary.
- Keep `PublicContentSettings` as storage and validation source; avoid adding settings-only public/footer models.

### Batch 2 - Card Templates and Renderers

Queries:

- `card template builder`
- `custom card renderer`
- `Builder block preview`
- `card grid layout`
- `ViewColumn card rendering`

Result summaries:

- Search results showed Filament Table cards and custom `ViewColumn` rendering patterns.
- Builder preview examples are useful for admin preview boundaries but not a substitute for safe public renderers.
- Card grid examples rely on `contentGrid`, but PodText public front intentionally uses Livewire/Blade rather than public Filament Tables.

Examples/classes/snippets found:

- `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`
  - Snippet referenced `contentGrid(['md' => 2, 'xl' => 3])`, `recordUrl(false)`, and pagination options.
- `v4/tables/table-customized-design-viewcolumn/...`
  - Snippet showed a custom view cell and query modification/eager loading.
- `v4/forms/markdown-and-rich-editor-preview-forms/...`
  - Snippet showed RichEditor/Markdown preview actions.

Pattern to copy:

- Centralize card rendering in a view/presenter rather than duplicating card markup.
- Use eager loading before rendering custom card views.
- Optional admin previews should reuse the safe renderer.

Pattern to avoid:

- Do not move public cards to public Filament Tables.
- Do not store arbitrary Blade view names, classes, or raw HTML in the card template JSON.

PodText adaptation:

- Replace compatibility-only card metadata with family-specific safe part presenters.
- `PublicContentCardOptions` should become a legacy adapter or be folded into the card renderer context.

### Batch 3 - Livewire/Public Rendering

Queries:

- `Livewire public cards`
- `public page grid`
- `URL state filters`
- `pagination card grid`
- `custom page layout`

Result summaries:

- Custom page examples show state prepared in PHP and rendered by Blade.
- URL-backed state and pagination patterns align with the current public search/podcast/contributor components.
- Several examples reinforce stable keyed nested Livewire components and explicit render data.

Examples/classes/snippets found:

- `v4/full-projects/student-or-user-attendance/app/Filament/Pages/Attendance.php`
  - Snippet referenced Livewire `#[Url]` state and page view data.
- `v4/full-projects/create-form-and-table-on-the-same-page/...`
  - Snippets showed custom page composition.
- `v4/forms/quote-form-with-custom-table-field-and-product-picker-modal/...`
  - Snippets showed nested Livewire components with explicit keys and relationship state handling.

Pattern to copy:

- Keep public state URL-backed where useful.
- Prepare render arrays in PHP before Blade.
- Use stable keys for repeated Livewire/card regions.

Pattern to avoid:

- Do not duplicate persistent state in Alpine.
- Do not introduce generic page builders for a small number of constrained public sections.

PodText adaptation:

- `PublicFrontRenderContext` should be injected into Livewire render paths.
- Contributor context should be passed explicitly to card presenters.

### Batch 4 - Rich Sections and Footer

Queries:

- `footer builder`
- `rich columns section`
- `Builder columns`
- `form CTA section`
- `dynamic homepage sections`

Result summaries:

- The most relevant result was a full project managing homepage sections with a normal resource and constrained section types.
- Builder/Repeater results support finite column/block structures.
- Search did not expose a footer-specific app-owned renderer example.

Examples/classes/snippets found:

- `v4/full-projects/manage-homepage-sections/...`
  - Snippets referenced `HomepageSectionResource`, section type/source controls, visible order, and limits.
- Builder examples for columns/blocks appeared in form search results but only as snippets.

Pattern to copy:

- Keep visible ordered homepage sections as data records.
- Use finite section/source/block types.
- Let controllers/renderers map JSON to app-owned output.

Pattern to avoid:

- Do not turn rich sections into generic CMS pages.
- Do not create `FooterSection` or `PublicFooter` models.

PodText adaptation:

- Step 9F / 10F should wait for Step 10R render context and card renderer fixes.
- `rich_columns` and `footer_config` should share safe block renderers and fixed class maps.

### Batch 5 - Transcriber Relationships and Admin

Queries:

- `many to many authors`
- `relation manager attach`
- `transcription authors`
- `pivot form relation`
- `import export relationships`

Result summaries:

- Many-to-many examples showed `AttachAction` and pivot-like relation manager patterns.
- Import/export examples showed resolving relationships by a portable column such as name.
- These patterns are relevant only if PodText later adds an `author_transcription` pivot.

Examples/classes/snippets found:

- `v4/full-projects/box-score-form/.../TeamsRelationManager.php`
  - Snippet referenced `AttachAction` and relationship counts.
- `v4/full-projects/stock-management/.../ItemImporter.php`
  - Snippet referenced `ImportColumn::relationship(resolveUsing: 'name')`.

Pattern to copy:

- Use native Filament relationship actions/selects if a pivot is introduced.
- Keep import/export relationship resolution portable and failure-aware.

Pattern to avoid:

- Do not change transcription attribution schema just to fix current display.
- Do not silently attach unresolved authors during import.

PodText adaptation:

- Immediate Step 10R-C should use `transcriptions.author_id`.
- A future multi-transcriber prompt must update schema, admin forms, import/export, public counts, and tests together.

## Refined Pass

Queries:

- `large settings tabs columnSpanFull`
- `SettingsPage Repeater JSON settings`
- `homepage section form visible order`
- `markdown rich editor preview action`
- `table as grid cards paginationPageOptions`
- `custom page Livewire Url state`
- `relation manager AttachAction pivot`
- `belongsToMany Select relationship multiple`
- `import relationship resolveUsing`
- `dashboard cache invalidation observer`

Refined findings:

- Full-width tab/section layouts are the clearest settings-page pattern.
- RichEditor/Markdown preview examples are useful only for admin preview UX; public output still needs PodText sanitizers.
- `AttachAction` and import relationship examples reinforce the later pivot plan.
- Cache invalidation examples were generic observer/service patterns, not a direct Spatie SettingsPage pattern.

## Research Conclusions

- Step 10R-A should create request-scoped settings/render context first.
- Step 10R-B should make templates render real finite parts across item, group, and contributor cards.
- Step 10R-C should use current transcription author relationships for display and defer multi-author schema work.
- Step 9F / 10F should wait until the settings and renderer boundaries are stable.
