# Step 10R-A2 Adopt Render Context MCP Research

## Scope

Mini-step: Step 10R-A2 - Adopt render context in public consumers.

Goal: move public settings consumers from direct `PublicFrontConfigReader`, `PublicContentSettings`, and repeated settings-helper reads to `PublicFrontRenderContext` while keeping public output and URL-backed state unchanged.

## Laravel Boost

### Tools Used

- `application_info`
- `database_schema` summary
- `database_schema` filtered to `settings`
- `search_docs`

### Application Versions

- PHP 8.4
- Laravel 13.18.0
- Filament 5.6.7
- Livewire 4.3.3
- Pest 4.7.4
- Laravel Boost 2.4.11
- Tailwind CSS 4.3.2
- SQLite local database

### Database Notes

The `settings` table stores Spatie settings as one row per group/name pair with a unique `settings_group_name_unique` index on `group` and `name`.

No schema change is needed for A2.

### Documentation Findings

Search queries:

- `service container scoped binding lifecycle`
- `Livewire render dependency injection mount container`
- `SettingsPage afterSave settings save lifecycle`
- `Pest Livewire component testing query string`

Relevant findings:

- Laravel 13 scoped bindings resolve once per request/job lifecycle and are flushed between lifecycles.
- Service-provider `register()` should bind container services only; event listeners belong in `boot()`.
- Livewire 4 tests support `withQueryParams()` for URL-backed component state and property assertions.
- Filament SettingsPage/Spatie settings save lifecycle is compatible with a post-save invalidation boundary.

Local vendor confirmation:

- Spatie Settings emits `Spatie\LaravelSettings\Events\SettingsSaved` after `Settings::save()`.
- Laravel's container can forget scoped instances, and a single scoped instance can be forgotten with `forgetInstance()`.

PodText adaptation:

- Keep request-scoped context as the runtime cache.
- Do not add persistent derived cache in A2.
- Add a narrow `SettingsSaved` listener for `PublicContentSettings` that forgets `PublicFrontRenderContext`, so admin saves and same-process tests do not reuse stale settings.
- Use context helper access inside Livewire components because constructor injection is not a stable fit for current class-based Livewire component lifecycle and several config reads happen outside `render()`.
- Use constructor injection in support services where the lifecycle supports it.

## FilamentExamples MCP

Access level: search/snippet access only. No source/read/fetch/details tool was exposed.

### Initial Query Batches

Batch 1 - settings and render context:

- `SettingsPage after save`
- `public settings reader`
- `render context`
- `custom page settings data`
- `SettingsPage defaults`

Relevant results:

- Account settings cluster pages showed settings/admin pages filling typed form state and saving through page methods.
- GitHub-style profile page showed page classes preparing view data in `getViewData()`.
- Settings results did not expose a dedicated cache-invalidation pattern.

Pattern to copy:

- Keep admin settings as the write boundary.
- Prepare view data in PHP classes/services before Blade renders.

Pattern to avoid:

- Do not add settings-only models or a separate public CMS layer.
- Do not let Blade repeatedly resolve settings readers.

Batch 2 - Livewire public pages:

- `Livewire public cards`
- `custom page Livewire Url state`
- `public page grid`
- `pagination card grid`
- `custom page getViewData`

Relevant results:

- Monthly Attendance Grid Tracker showed Livewire `#[Url]` state and PHP-side `getViewData()` preparation.
- Kanban/custom page examples reinforced state in page classes and view data arrays.

Pattern to copy:

- Preserve URL-backed state in Livewire/page classes.
- Prepare config and render arrays in PHP before Blade.

Pattern to avoid:

- Do not move public browse/search back to Filament Tables.

Batch 3 - menu/forms/cards:

- `public form modal Livewire settings`
- `menu builder settings page`
- `public header render hook`
- `card template resolver`
- `custom Blade card renderer`

Relevant results:

- Returned settings/custom page examples and nested Livewire examples.
- No PodText-like menu/settings reader source was exposed.

Pattern to copy:

- Keep menu/form/card logic in focused support services.
- Let public Livewire components delegate to those services.

Pattern to avoid:

- Do not create a second preview-only rendering path or raw Blade/class settings.

### Refined Query Pass

Queries:

- `custom page getViewData Url attribute`
- `ManageSettings SettingsPage GeneralSettings`
- `SettingsPage Repeater settings`
- `table as grid cards contentGrid`
- `custom view column cards`

Refined findings:

- Examples reinforced PHP-side view data preparation, typed settings/admin page boundaries, and custom-card rendering through controlled views.
- Public Filament Table card-grid examples were treated as admin/reference patterns only; PodText public pages must remain custom Livewire/Blade.

## Local Code Findings

Direct public settings reads before A2:

- `ContentItemSearch`
- `ContentItemBrowser`
- `ContentGroupBrowser`
- `ContributorDirectory`
- `ContributorContentItems`
- `TopTranscribersSection`
- `PublicFormModal`
- `BrowsePublicContentGroups`
- `ShowContentGroup`
- `BrowseContributors`
- `ShowContributor`
- `AboutPage`
- `PublicMenuConfigReader`
- `PublicAboutPageRenderer`
- `PublicFrontCardTemplateResolver`
- contributor public Blade pages

Admin forms still need persisted option reads for later Step 10R-B, so A2 should not force unsaved admin form state into the runtime context.

Legacy scalar card options currently read `PublicContentSettings` directly through `PublicContentCardOptions::fromSettings()`. A2 should route public consumers through a memoized `PublicFrontRenderContext::cardOptions()` accessor to preserve behavior without repeated direct settings reads.

## A2 Decision

Implement:

- Context-owned memoized card options.
- Scoped-context invalidation on `PublicContentSettings` save.
- Livewire/page/support consumers routed through `PublicFrontRenderContext`.
- Contributor page Blade views receive config from page classes instead of resolving readers.
- Focused tests for context reuse across consumers and settings-save refresh.

Do not implement:

- Card-template visual part rendering.
- Custom template select fixes.
- Transcriber attribution changes.
- Footer/rich sections.
- Step 11 seeders.
- Prompt 13 dashboard metrics.
