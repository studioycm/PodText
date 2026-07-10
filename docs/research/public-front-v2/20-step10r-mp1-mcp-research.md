# Public Front v2 Step 10R-MP1 MCP Research

## Scope

Step 10R-MP1 adds a settings-controlled public maintenance / coming-soon mode. The
Importer Workbench is not part of this run.

## Preflight

- Working tree was clean after the active prompt file was committed in
  `9267597`.
- Previous completed S1c hash backfilled from git log:
  `389cb0f feat: add inline import locks on settings page`.
- `php artisan migrate --no-interaction` ran against local MySQL and reported nothing
  to migrate.
- Tests remain SQLite `:memory:` by `phpunit.xml`, `tests/Pest.php`, and the
  `TestCase` canary.

## Boost Research

- Boost `application_info` reported PHP 8.4, Laravel 13.18.0, Filament 5.6.7,
  Livewire 4.3.3, Pest 4.7.4, Tailwind CSS 4.3.2, and MySQL as the runtime database.
- Boost `search_docs` found Filament 5 RichEditor docs: `RichEditor::make()` stores
  HTML by default, can render through `RichContentRenderer`, supports merge tags and
  custom blocks, and warns that raw RichEditor HTML must be sanitized unless the
  application deliberately trusts that surface.
- Boost `search_docs` found Filament panel middleware docs: `Panel::middleware()`
  attaches middleware to all panel routes and can be persistent. MP1 should attach one
  middleware to the public panel only, not admin routes.
- Boost `search_docs` found Laravel 13 middleware/response docs: middleware can return
  a response before the request reaches the route; responses can carry headers such as
  `Retry-After`.
- Boost `database_schema(summary, filter=settings)` confirmed the local MySQL
  `settings` table stores Spatie settings payloads as JSON, with existing backup
  tables separate.

## FilamentExamples Research

- Query batch 1: `Filament RichEditor settings page`, `RichEditor disable
  attachments Filament`, `Filament settings page tabs section visibility`.
- Query batch 2: `Filament RichEditor custom blocks merge tags`, `Filament form
  section warning toggle visible`, `Filament settings page RichEditor textarea`.
- Access level: search/snippet only.
- Useful examples:
  - `v4/full-projects/eshop-with-front-page/app/Filament/Pages/ManageSettings.php`
    shows a Spatie `SettingsPage` with simple fields and follows the same broad shape
    as PodText's Public Content Settings page.
  - `v4/forms/edit-profile-custom-forms/app/Filament/Pages/EditProfile.php` and
    related custom-page examples confirm Filament 5 schema components, sections, form
    state paths, and action submission patterns in custom pages.
  - Multi-panel provider snippets confirm `Panel::middleware([...])` is the right
    panel-local attachment point and that admin and public panels can have distinct
    middleware stacks.
- Pattern to copy: keep the settings UI inside the existing `SettingsPage` schema and
  use panel middleware rather than global middleware.
- Pattern to avoid: no FileUpload/media-library attachment path for the maintenance
  RichEditor in v1.

## External Reference Anchors

- Filament 5 RichEditor official docs:
  https://filamentphp.com/docs/5.x/forms/rich-editor
- LaravelDaily / Povilas Korop overview of the v4 RichEditor improvements:
  https://laraveldaily.com/post/filament-v4-beta-new-features
- MP1 conclusion: custom blocks and merge tags are useful future options, but v1 should
  ship basic HTML editing only. Use `RichEditor::fileAttachments(false)` and a separate
  advanced raw HTML textarea.

## Codebase Findings

- Public panel routes are registered in `app/Providers/Filament/PublicPanelProvider.php`
  with `path('')`; admin routes are in `AdminPanelProvider` with `path('admin')`.
- Snapshot/zip/retry admin routes live in `routes/web.php` and already require
  Filament `Authenticate`; they are not public panel routes and must not be intercepted.
- `App\Models\User::canAccessPanel()` returns true for the admin panel, so admin bypass
  should reuse the same contract through the admin panel instance.
- `PublicFrontConfigReader` and `PublicFrontConfigCache` are the P1 cache boundary.
  Adding a `maintenance` settings group to the registry lets middleware read through
  the same cached validated config path.
- `PublicFrontConfigValidator` sanitizes normal public rich-content arrays. MP1 must
  add a deliberate maintenance normalizer that passes `title`, `rich_html`, and
  `raw_html_override` through as nullable strings without sanitizer checks.
- `PublicSettingsPackage::fromCurrentSettings()` exports the whole Spatie settings
  group, so import/export round-trip coverage mostly needs the registry/validator and
  lifecycle schema to know the new group.

## Implementation Notes

- Add `maintenance` to `PublicContentSettings`, registry keys/defaults/schema,
  validator normalization, render context accessor, settings migration, lifecycle units,
  settings page tab, translations, public-panel middleware, and standalone Blade view.
- Middleware response must be `503` with `Retry-After` seconds equal to
  `retry_after_hours * 3600`.
- The view renders `raw_html_override` verbatim when filled; otherwise it renders
  `rich_html` verbatim in a minimal shell with translated fallback content.
- Known consequence for handoff: backup snapshots are guest requests, so while
  maintenance is enabled they capture the maintenance page.

