# Public Front v2 Step 10R-P1 Implementation Plan

## Selected Step

Step 10R-P1 - Validated public-front config caching.

## Dependencies

- Step 10R-UX1 complete: global admin UX defaults are already in place.
- Step 10R-UX2 complete: shared edit-effective-transcription action is complete.
- Step 10R-V1a through Step 10R-V1c complete: default images, icon tokens, custom colors,
  and podcast palette settings are included before the first validated config cache.
- Current sequence: P1 is first pending. S2 and S1 remain next and are not implemented in
  this run.

## Current Repo Evidence

- `PublicFrontRenderContext` is request-scoped and already reduces repeated reads inside
  one Laravel container lifecycle.
- `PublicFrontConfigReader::read()` still performs a settings read and full validation on
  each call when a fresh context is built.
- `fromArray()` is used for admin form normalization and should remain uncached.
- `AppServiceProvider` already listens to `SettingsSaved` for `PublicContentSettings` and
  clears scoped public-front instances.
- `config/cache.php` disables unserializing cached PHP objects, so payloads must be plain
  arrays.
- The current V1c podcast palette cache key is `public_front.podcast_palette.v1.*` and is
  content-addressed by cover path + file mtime.

## Research Summary

- Laravel cache supports persistent direct keys through `Cache::forever()` and explicit
  invalidation through `Cache::forget()`.
- Cache tags are not portable with the default database cache store.
- Spatie `SettingsSaved` fires after settings persistence and carries the settings
  instance; the existing PodText listener is the correct invalidation boundary.
- FilamentExamples returned only adjacent settings-page save/lifecycle snippets, not a
  direct cache-invalidation example. P1 therefore keeps invalidation out of the Filament
  page and inside the settings event listener.

## Implementation Plan

1. Add `PublicFrontConfigCache`.
   - Base key: `public_front.config.v1`.
   - Watermarked key: base key plus settings migration count and latest settings migration
     filename.
   - Enabled when `config('settings.cache.enabled')` is true.
   - Store normalized config and invalid-config arrays only.
   - Rebuild `PublicFrontConfigResult` from valid payloads.
   - Treat malformed payloads as corruption and recompute.
   - Expose a palette key helper for V1c palette cache naming.
2. Update `PublicFrontConfigReader`.
   - Wrap `read()` in `PublicFrontConfigCache::remember()`.
   - Keep `fromArray()` uncached.
   - Keep existing settings-unavailable fallback behavior as the fresh-read path.
3. Update `AppServiceProvider`.
   - On `SettingsSaved` for `PublicContentSettings`, forget the persistent public-front
     config cache before clearing the scoped context/policy instances.
4. Update `PublicItemPagePodcastPalette`.
   - Generate palette cache keys through `PublicFrontConfigCache::podcastPaletteKey()`.
   - Keep palette values keyed by path + mtime only; no settings watermark.

## Tests

- Warm cache skips revalidation when `settings.cache.enabled` is true.
- Saving `PublicContentSettings` invalidates the cache and makes the new value visible on
  the next read.
- Adding a settings migration file rotates the config cache key and triggers a fresh
  validated read without relying on `SettingsSaved`.
- Corrupted cache payload falls back to a fresh validated read and rewrites a valid array
  payload.
- V1c palette cache uses the shared key helper and still caches by cover path + mtime.
- `tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php` remains green.

## Files To Change

- `app/Support/PublicFront/PublicFrontConfigCache.php`
- `app/Support/PublicFront/PublicFrontConfigReader.php`
- `app/Support/PublicFront/ItemPage/PublicItemPagePodcastPalette.php`
- `app/Providers/AppServiceProvider.php`
- Focused tests under `tests/Feature/`
- P1 research/plan/handoff docs
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`

## Risks

- Tests that bypass `PublicContentSettings::save()` with direct DB writes do not fire
  `SettingsSaved`; focused P1 tests will explicitly control cache state.
- Old watermarked cache entries can remain in the cache store until normal cache pruning.
  The active key rotates safely and stale entries are not read.
- Local `.env` may leave `SETTINGS_CACHE_ENABLED` false, so tests enable
  `config('settings.cache.enabled')` explicitly for cache behavior assertions.

## Quality Gate

Run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```

Commit on green:

```text
perf: cache validated public front config
```
