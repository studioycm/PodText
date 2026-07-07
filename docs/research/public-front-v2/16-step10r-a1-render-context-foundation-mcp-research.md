# Step 10R-A1 Render Context Foundation MCP Research

## Scope

Mini-step: Step 10R-A1 - PublicFrontRenderContext foundation.

Goal: add a request-scoped public-front settings/render context that normalizes `PublicContentSettings` once per request and exposes typed group accessors without moving public consumers yet.

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
- SQLite local database

### Database Notes

The `settings` table stores Spatie settings as one row per group/name pair:

- `group`
- `name`
- `payload`
- unique index: `settings_group_name_unique`

No schema change is needed for A1.

### Documentation Findings

Search queries:

- `service container scoped binding request lifecycle`
- `request scoped singleton service provider`
- `once helper request memoization`
- `settings cache repository clear cache save`
- `SettingsPage save settings lifecycle after save`
- `Spatie settings default values cache`
- `Pest assert same object container binding`
- `Livewire URL query parameter state testing`
- `Pest RefreshDatabase Laravel`

Relevant findings:

- Laravel 13 supports `scoped()` container bindings for values that should resolve only once per request/job lifecycle and be flushed on the next lifecycle.
- Service providers are the correct place to register these bindings.
- `once()` and `Cache::memo()` exist, but A1 does not need a separate persistent cache because the requested foundation can be satisfied by a scoped binding.
- Filament settings pages remain the write boundary for settings. The available docs show `SettingsPage` as the page class that fills and saves a Spatie settings object, but did not provide a specific after-save cache invalidation hook pattern.
- Pest is already configured in the repository for feature tests through `tests/Pest.php`; existing project tests use `RefreshDatabase` and explicit Spatie `SettingsContainer::clearCache()` helpers.

## FilamentExamples MCP

Access level: search/snippet access only. No source/read/details tool was exposed.

### Initial Query Batches

Batch 1 - settings lifecycle:

- `SettingsPage after save`
- `settings page cache`
- `Filament settings defaults`

Relevant results:

- `v4/full-projects/eshop-with-front-page/app/Filament/Pages/ManageSettings.php`
- `v4/full-projects/eshop-with-front-page/app/Settings/GeneralSettings.php`
- `v4/full-projects/clusters-with-profile-settings/...`

Pattern to copy:

- Keep settings pages as bounded form/write surfaces.
- Keep settings data in typed settings classes.

Pattern to avoid:

- Do not add a settings model or generic CMS model just to prepare public view data.

Batch 2 - public/settings preparation:

- `public settings reader`
- `render context`
- `public page settings`

Relevant results:

- Mostly custom Filament pages and settings examples.
- `Monthly Attendance Grid Tracker` showed page classes preparing view data in PHP before rendering.

Pattern to copy:

- Prepare view data in PHP classes/services rather than in Blade.

Pattern to avoid:

- Do not add broad page-level table/UI constructs for public rendering.

### Refined Query Pass

Queries:

- `ManageSettings extends SettingsPage GeneralSettings`
- `custom page getViewData Url attribute`
- `service class settings page`

Relevant results:

- `ManageSettings extends SettingsPage` with `protected static string $settings`.
- Custom Filament pages using PHP methods and URL state to prepare render data.

PodText adaptation:

- A1 should not alter the existing settings page behavior.
- Add a request-scoped render context as a support service, then let later mini-steps move consumers.

## Local Code Findings

Relevant current classes:

- `App\Support\PublicFront\PublicFrontConfigReader`
- `App\Support\PublicFront\PublicFrontConfigResult`
- `App\Support\PublicFront\PublicFrontConfigRegistry`
- `App\Support\PublicFront\PublicFrontConfigValidator`
- `App\Settings\PublicContentSettings`
- `App\Providers\AppServiceProvider`

Current behavior:

- `PublicFrontConfigReader::read()` reads Spatie settings, normalizes through the validator, and returns `PublicFrontConfigResult`.
- The registry currently exposes `card_templates`, `menu_config`, `about_page`, `public_forms`, `route_labels`, `display_defaults`, `podcasts_page`, and `contributors_page`.
- `footer_config` does not exist yet, so A1 should expose `footer()` as an empty/fallback group for future Step 9F work.
- `AppServiceProvider::register()` is currently empty, making it the safest place to register the scoped context.

## A1 Decision

Implement:

- `App\Support\PublicFront\PublicFrontRenderContext`
- `App\Support\PublicFront\PublicFrontRenderContextFactory`
- `AppServiceProvider::register()` scoped binding
- focused Pest coverage for group accessors, scoped reuse, invalid fallback, and saved settings visibility in a refreshed context

Do not implement:

- persistent cache
- settings-page invalidation hook
- consumer migration
- card-template visual rendering
- footer/rich-section behavior
