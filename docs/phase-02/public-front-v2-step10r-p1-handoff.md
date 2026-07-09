# Public Front v2 10R-P1 Handoff

## Purpose

Step 10R-P1 caches the validated public-front config behind the existing
`PublicFrontRenderContext` boundary, with a versioned settings-migration-aware key and
safe invalidation on public settings saves.

## What Was Implemented

- Added `PublicFrontConfigCache` as the single app-owned cache boundary for normalized
  public-front config.
- Added the versioned config base key `public_front.config.v1`.
- Added a settings-migration watermark made from configured settings migration paths,
  migration count, and latest migration filename.
- Cached plain array payloads only, then rebuilt `PublicFrontConfigResult` and
  `PublicFrontInvalidConfig` from valid payloads.
- Treated malformed cache payloads as corruption: forget, recompute, and rewrite a valid
  payload.
- Routed canonical no-argument `PublicFrontConfigReader::read()` calls through the cache
  when `config('settings.cache.enabled')` is true.
- Kept explicit settings-instance reads and `fromArray()` uncached for admin form
  normalization, direct tests, and tooling.
- Extended the existing `SettingsSaved` listener for `PublicContentSettings` to forget the
  persistent public-front config cache and scoped context instances.
- Centralized V1c podcast palette cache key generation through `PublicFrontConfigCache`
  while keeping palette entries content-addressed by cover path + file mtime.

## Files Changed

- `app/Support/PublicFront/PublicFrontConfigCache.php`
- `app/Support/PublicFront/PublicFrontConfigReader.php`
- `app/Support/PublicFront/PublicFrontRenderContextFactory.php`
- `app/Support/PublicFront/ItemPage/PublicItemPagePodcastPalette.php`
- `app/Providers/AppServiceProvider.php`
- `tests/Feature/PublicFrontConfigCacheTest.php`
- `docs/research/public-front-v2/20-step10r-p1-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-p1-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-p1-handoff.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/phase-02/current-project-state.md`

## Tests Added Or Updated

Added `tests/Feature/PublicFrontConfigCacheTest.php` covering:

- warm validated-config cache skips revalidation;
- saved public settings invalidate the cache and become visible on the next read;
- settings-migration watermark rotation produces a new key and fresh validation;
- corrupted cache payloads fall back to fresh validation and are rewritten;
- V1c podcast palette entries use the shared key helper and stay path/mtime addressed.

Focused regression suites run before the full gate:

```bash
php artisan test tests/Feature/PublicFrontConfigCacheTest.php
php artisan test tests/Feature/PublicFrontConfigCacheTest.php tests/Feature/PublicFrontRenderContextTest.php tests/Feature/PublicFrontCustomColorsTest.php tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php
```

## Forge Environment Checklist

1. Set `SETTINGS_CACHE_ENABLED=true` when the public-front validated config cache should
   be active in Forge. This also enables the existing Spatie Settings cache knob.
2. Prefer `CACHE_STORE=redis` in production if Redis is provisioned; otherwise the
   database cache store works and does not require cache tags.
3. When using Redis, verify the app has a cache connection configured, for example
   `REDIS_CACHE_CONNECTION=cache` if the environment separates default and cache Redis
   connections.
4. Run the normal deployment config-cache step after changing cache env values.
5. Settings migrations rotate the public-front config cache key automatically; no manual
   public-front cache clear is required solely because a new settings migration shipped.

## Exceptions / Notes

- Local `.env` may leave `SETTINGS_CACHE_ENABLED=false`; P1 tests enable
  `config('settings.cache.enabled')` explicitly for behavior coverage.
- Old watermarked config cache entries can remain in the cache store until normal cache
  pruning. They are no longer read after the watermark changes.
- Palette cache entries are not invalidated by `SettingsSaved` because they are
  content-addressed by local cover path and mtime.
- No migration, public route, public SPA mode, AX, SL, S2, or S1 work was added.

## Quality Gate

Final gate commands:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```

Final gate results:

- `vendor/bin/pint --dirty --format agent` passed.
- `php artisan test` passed: 314 tests, 2961 assertions.
- `vendor/bin/pint --test` passed.
- `vendor/bin/filacheck` passed: 0 issues.
- `npm run build` passed.
- `git diff --check` passed.

## Commit hash

Commit message: `perf: cache validated public front config`.

The exact commit hash is created after the final gate and lands in the final report.

## Local Front Check Report

1. Open `/admin/public-content-settings?public-content-tab=podcasts` in Hebrew/RTL. Edit a
   visible Podcasts page label, save, then reload. Expected: the save succeeds and the
   admin value remains visible with no layout change in light or dark mode.
2. Open `/podcasts` in a fresh browser tab after the save. Expected: the updated label is
   visible immediately after reload, proving the saved settings invalidated the config
   cache.
3. Open `/admin/public-content-settings?public-content-tab=item-page`, change an existing
   item-page visual setting such as podcast identity color, and save. Expected: no new UI
   appears in P1, and the existing Hebrew/RTL form behavior is unchanged.
4. Open an episode detail page such as `/items/{podcast-slug}/{episode-slug}` in light and
   dark mode. Expected: the item-page setting saved in the previous step is reflected
   after reload, with no stale config and no visible flash or broken layout.
5. Open a podcast/episode whose podcast has a local cover image. Expected: sampled palette
   colors still render as before; the cache remains keyed to the cover path and file
   mtime, not to settings saves.

## Next Step

Step 10R-S2 is next: settings backup versions and restore flow. Step 10R-S1 follows S2,
then the branch should pause for Yoni's custom importer side quest before returning to
P2/P3/AX/SL/B4/C2/9F work.
