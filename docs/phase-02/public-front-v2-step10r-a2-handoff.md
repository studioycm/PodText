# Public Front v2 Step 10R-A2 Handoff

## Purpose

Adopt the request-scoped `PublicFrontRenderContext` in existing public-front consumers without changing public output behavior.

## What Was Implemented

- Added raw settings-value accessors to `PublicFrontRenderContext`:
  - `settingsValues()`
  - `setting()`
  - memoized `cardOptions()`
- Extended `PublicFrontRenderContextFactory` so each context carries both normalized JSON config and legacy scalar public card settings.
- Added a narrow `SettingsSaved` listener in `AppServiceProvider` that forgets the scoped context after `PublicContentSettings` is saved.
- Routed public Livewire consumers through `PublicFrontRenderContext`:
  - `ContentItemSearch`
  - `ContentItemBrowser`
  - `ContentGroupBrowser`
  - `ContributorDirectory`
  - `ContributorContentItems`
  - `TopTranscribersSection`
  - `PublicFormModal`
- Routed public Filament page classes through `PublicFrontRenderContext`:
  - `BrowsePublicContentGroups`
  - `ShowContentGroup`
  - `BrowseContributors`
  - `ShowContributor`
  - `AboutPage`
- Routed support services through constructor-injected context:
  - `PublicMenuConfigReader`
  - `PublicAboutPageRenderer`
  - `PublicFrontCardTemplateResolver`
- Moved contributor page config reads out of Blade into page-class state.
- Kept public card Blade compatibility defaults context-backed for existing callers that do not pass options.

## Files Changed

- Runtime context/support:
  - `app/Support/PublicFront/PublicFrontRenderContext.php`
  - `app/Support/PublicFront/PublicFrontRenderContextFactory.php`
  - `app/Support/PublicContent/PublicContentCardOptions.php`
  - `app/Providers/AppServiceProvider.php`
  - `app/Support/PublicFront/Menu/PublicMenuConfigReader.php`
  - `app/Support/PublicFront/About/PublicAboutPageRenderer.php`
  - `app/Support/PublicFront/Cards/PublicFrontCardTemplateResolver.php`
- Public runtime consumers:
  - `app/Livewire/Public/*`
  - `app/Filament/Public/Pages/*`
  - `resources/views/filament/public/pages/browse-contributors.blade.php`
  - `resources/views/filament/public/pages/show-contributor.blade.php`
  - `resources/views/components/public/content-item-card.blade.php`
  - `resources/views/filament/tables/columns/public-content-item-card.blade.php`
- Tests:
  - `tests/Feature/PublicFrontRenderContextTest.php`
  - public-front regression test helpers that write settings directly
- Docs:
  - `docs/research/public-front-v2/16-step10r-a2-adopt-render-context-mcp-research.md`
  - `docs/phase-02/public-front-v2-step10r-a2-implementation-plan.md`
  - `docs/phase-02/public-front-v2-step10r-a2-handoff.md`
  - `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
  - `docs/phase-02/current-project-state.md`

## Final Public API For Later Steps

- Later public rendering code should prefer `PublicFrontRenderContext` over direct `PublicFrontConfigReader` or `PublicContentSettings` reads.
- Current context accessors available for later mini-steps:
  - normalized JSON groups: `cardTemplates()`, `displayDefaults()`, `menu()`, `aboutPage()`, `publicForms()`, `routeLabels()`, `podcastsPage()`, `contributorsPage()`, `footer()`
  - raw scalar settings: `settingsValues()`, `setting()`
  - legacy card options adapter: `cardOptions()`
- `PublicContentCardOptions::fromValues()` is available for controlled adapters where raw settings arrays are already available.

## Settings / Schema Changes

- No database schema changes.
- No settings shape changes.
- Added runtime invalidation when `PublicContentSettings::save()` emits `SettingsSaved`.
- Test helpers that bypass Spatie settings saves by writing the `settings` table directly now clear `PublicFrontRenderContext` explicitly.

## Rendering Behavior

- Public output is intended to stay unchanged.
- Card templates still mostly affect compatibility metadata and existing presentation inputs; real part rendering remains later Step 10R-B2/B3 work.
- URL-backed Livewire search/filter/sort/page state was preserved.
- No public Filament Tables were introduced.

## Tests Added / Updated

- Added `PublicFrontRenderContextTest` coverage for:
  - shared normalized context reuse across menu/about/card-template consumers;
  - settings-save invalidation of the scoped context;
  - legacy scalar card options exposed through the context;
  - contributor page settings flowing through page-class state.
- Updated public-front test settings helpers to forget the scoped context when tests write settings rows directly.

## Security / Fallback Behavior

- Existing public-front validator and safe normalized config paths remain unchanged.
- No raw CSS, Tailwind classes, Blade paths, PHP classes, HTML, iframe HTML, scripts, SQL, or unsafe URLs were introduced into settings.
- Invalid public-front config fallback remains available through the context.
- The context does not add persistent cache; it is scoped to the container lifecycle.

## Blueprint / Audit Deviations

- Admin settings form option helpers still use `PublicFrontConfigReader` in a few places because same-session custom template select behavior belongs to Step 10R-B1.
- `PublicContentCardOptions::fromSettings()` remains as a compatibility API, but public runtime defaults now use `PublicFrontRenderContext::cardOptions()`.
- Blade keeps a narrow `app(PublicFrontRenderContext::class)` default only for compatibility components where caller injection is not practical.

## Effect On Later Mini-Steps

- Step 10R-B1 can use the context-backed resolver baseline while fixing custom card template option visibility.
- Step 10R-B2/B3 can add real card-part renderers without repeating settings normalization reads.
- Step 10R-B4 can converge legacy scalar card options with the renderer using `cardOptions()` / `fromValues()`.
- Step 9F rich sections and footer work should consume `PublicFrontRenderContext` directly.

## Open Questions

- Whether to remove or further restrict `PublicContentCardOptions::fromSettings()` after Step 10R-B4 convergence.
- Whether admin settings same-session previews should force a transient unsaved context in Step 10R-B1/C future work.

## Quality Gate Summary

- `php artisan test tests/Feature/PublicFrontRenderContextTest.php`: passed, 9 tests.
- Focused public-front regression file set: passed, 113 tests.
- `vendor/bin/pint --dirty --format agent`: fixed formatting/imports only.
- `php artisan test`: passed, 228 tests.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed, 0 issues.
- `npm run build`: passed.
- `git diff --check`: pending final run after this doc update.

## Commit Hash

Pending final commit in this run: `refactor: route public front settings through render context`.
