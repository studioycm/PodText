# Public Front v2 Step 10R-A1 Implementation Plan

## Purpose

Add the foundation for a request-scoped public-front render context while preserving current public output behavior.

## Selected Mini-Step

Step 10R-A1 - PublicFrontRenderContext foundation.

## Current Code Reality

- `PublicFrontConfigReader` already knows how to read and normalize `PublicContentSettings`.
- No single request-scoped public render context exists.
- Public consumers still read `PublicFrontConfigReader`, `PublicContentSettings`, and `PublicContentCardOptions` directly.
- `AppServiceProvider::register()` is empty and can safely register a scoped binding.

## Implementation Tasks

1. Add `App\Support\PublicFront\PublicFrontRenderContext`.
   - Hold one normalized `PublicFrontConfigResult`.
   - Expose `result()`, `config()`, `group()`, `invalidConfig()`, `invalidConfigArray()`, and `hasInvalidConfig()`.
   - Expose typed group accessors: `cardTemplates()`, `displayDefaults()`, `menu()`, `aboutPage()`, `publicForms()`, `routeLabels()`, `podcastsPage()`, `contributorsPage()`, and future-safe `footer()`.

2. Add `App\Support\PublicFront\PublicFrontRenderContextFactory`.
   - Build the context from `PublicFrontConfigReader::read()`.
   - Accept an optional `PublicContentSettings` instance for tests/future save-boundary use.

3. Register a request-scoped binding in `App\Providers\AppServiceProvider`.
   - Bind `PublicFrontRenderContext::class` with `$this->app->scoped(...)`.
   - Do not add persistent cache in A1.

4. Add focused Pest tests.
   - Context resolves normalized groups and a future-safe empty footer fallback.
   - Resolving the context twice in one container lifecycle returns the same instance and reads once.
   - Invalid config still falls back through the existing validator.
   - Saved settings are visible in a refreshed context.

5. Update A1 docs after implementation.
   - Ledger
   - Current project state
   - Handoff

## Explicit Non-Goals

- Do not move public consumers to the context in A1.
- Do not implement cache invalidation hooks.
- Do not implement card-template visual rendering.
- Do not implement transcriber attribution changes.
- Do not implement Step 9F / 10F, Step 11, or Prompt 13.

## Verification

Focused:

```bash
php artisan test tests/Feature/PublicFrontRenderContextTest.php
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
