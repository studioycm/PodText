# Public Front v2 Step 10R-A2 Implementation Plan

## Purpose

Adopt the request-scoped `PublicFrontRenderContext` in existing public consumers while preserving current public output behavior.

## Selected Mini-Step

Step 10R-A2 - Adopt render context in public consumers.

## Current Code Reality

- Step 10R-A1 added `PublicFrontRenderContext`, `PublicFrontRenderContextFactory`, and a scoped container binding.
- Public consumers still read `PublicFrontConfigReader`, `PublicContentSettings`, and `PublicContentCardOptions::fromSettings()` directly.
- Card templates still mostly affect compatibility metadata and limited content-item presentation. Real part rendering belongs to later B mini-steps.
- URL-backed Livewire public state already exists and must not change.

## Implementation Tasks

1. Extend `PublicFrontRenderContext` for legacy card options.
   - Accept an optional `PublicContentSettings` instance from the factory.
   - Add memoized `cardOptions(): PublicContentCardOptions`.
   - Keep existing result/group accessors unchanged.

2. Add scoped context invalidation after settings saves.
   - Listen for `Spatie\LaravelSettings\Events\SettingsSaved` in `AppServiceProvider::boot()`.
   - If the saved settings object is `PublicContentSettings`, forget `PublicFrontRenderContext::class`.
   - Do not add persistent cache.

3. Move public Livewire components to the context.
   - `ContentItemSearch`
   - `ContentItemBrowser`
   - `ContentGroupBrowser`
   - `ContributorDirectory`
   - `ContributorContentItems`
   - `TopTranscribersSection`
   - `PublicFormModal`
   - Preserve all URL-backed properties and query behavior.

4. Move public page classes and Blade reads to the context.
   - `BrowsePublicContentGroups`
   - `ShowContentGroup`
   - `BrowseContributors`
   - `ShowContributor`
   - `AboutPage`
   - `browse-contributors.blade.php`
   - `show-contributor.blade.php`

5. Move support services to constructor-injected context where lifecycle supports it.
   - `PublicMenuConfigReader`
   - `PublicAboutPageRenderer`
   - `PublicFrontCardTemplateResolver`
   - Leave section query resolution unchanged because it does not read settings directly.

6. Add focused tests.
   - Multiple public consumers in one container lifecycle use one normalized context/read.
   - Saving `PublicContentSettings` invalidates the scoped context and updated values are visible.
   - Public page output reflects updated settings after save.
   - Existing public URL-state tests continue to pass.

## Explicit Non-Goals

- Do not implement real card part rendering.
- Do not fix card-template select/options UX.
- Do not change public transcriber attribution.
- Do not add footer/rich sections.
- Do not introduce persistent cache.
- Do not create forbidden models or public Filament Tables.
- Do not start Step 11 or Prompt 13.

## Verification

Focused:

```bash
php artisan test tests/Feature/PublicFrontRenderContextTest.php
php artisan test --filter=PublicHomepageSearchTest
php artisan test --filter=PublicPodcastsGroupsUxTest
php artisan test --filter=PublicContributorsTopTranscribersUxTest
php artisan test --filter=PublicFormsSubmissionsTest
php artisan test --filter=PublicAboutPageContentTeamTest
php artisan test --filter=PublicMenuHeaderUxFixesTest
```

Required final gate:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```
