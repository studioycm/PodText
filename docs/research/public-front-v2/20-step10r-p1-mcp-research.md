# Public Front v2 Step 10R-P1 MCP Research

Date: 09/07/2026

## Scope

Step 10R-P1 adds a persistent cache for the validated public-front settings config behind
the existing request-scoped `PublicFrontRenderContext`.

## Local Repository Evidence

- Preflight reported a clean `main...origin/main [ahead 1]` worktree.
- Recent history includes `a846341 feat: add custom colors and theme safe podcast palette`.
- All migrations and settings migrations through
  `2026_07_09_000007_add_public_custom_color_settings` are ran locally.
- The central ledger and implementation sequence both list Step 10R-P1 as the first
  pending mini-step after UX1, UX2, and V1a through V1c.
- `PublicFrontConfigReader::read()` currently reads Spatie settings and validates on each
  call; `fromArray()` is the uncached path used by admin form normalization and ad hoc
  validation tests.
- `PublicFrontRenderContext` is already request-scoped in `AppServiceProvider`.
- `AppServiceProvider` already listens for Spatie `SettingsSaved` for
  `PublicContentSettings` and clears the scoped public-front context/policy instances.
- `config/cache.php` has `serializable_classes` disabled, so the validated config cache
  must store plain arrays rather than PHP objects.
- V1c `PublicItemPagePodcastPalette` already caches palette values by cover path and
  file mtime through a hard-coded `public_front.podcast_palette.v1.*` key.

## Laravel Boost Findings

Tools used: `application_info`, `database_schema`, and `search_docs`.

- Boost confirmed the installed stack: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3,
  Pest 4.7.4, Tailwind 4.3.2, local SQLite.
- Boost schema confirmed the local `cache` and `settings` tables exist. P1 needs no
  schema change.
- Laravel cache supports `Cache::rememberForever()`, `Cache::forever()`,
  `Cache::get()`, and `Cache::forget()`.
- Cache tags are not portable to the local database cache store, so P1 should use direct
  versioned keys and explicit invalidation rather than cache tags.
- Missing cache entries should be recomputed from the canonical settings source.
- Spatie Settings dispatches `SettingsSaved` from `Settings::save()` after persistence.
  Local vendor inspection confirmed the event exposes the saved settings instance as
  `$event->settings`.

## FilamentExamples Findings

Access level: `search_examples` snippet/search access only. No separate source/read/fetch
tool was exposed.

Initial query batch:

- `settings page cache invalidation`
- `settings page after save cache`
- `settings page save action`

Refined query batch:

- `settings saved event listener`
- `settings page cache forget`
- `settings page lifecycle save`
- `cache invalidation settings page`

Useful examples and PodText adaptation notes:

- **Account Settings Cluster Pages**
  - Snippet found: Filament page/settings-style form `mount()`, `form()`, and explicit
    save/update method.
  - Pattern to copy: keep settings-page persistence ordinary and let the persistence
    layer/event boundary handle cross-cutting effects.
  - Pattern to avoid: page-specific cache invalidation that only covers one UI path.
  - PodText adaptation: invalidate the public-front config cache through the existing
    `SettingsSaved` listener so settings page saves, future restore/import flows, and
    direct `PublicContentSettings::save()` calls use the same path.
- **Form and Table on One Custom Page**
  - Snippet found: custom page form fill/save lifecycle and notification patterns.
  - Pattern to copy: no direct cache dependency in the form schema itself.
  - PodText adaptation: P1 does not need changes to `PublicContentSettings` form fields.

No example showed a direct settings-cache invalidation implementation; P1 follows
Boost/Laravel cache docs, Spatie's local event implementation, and existing PodText
settings-event wiring.

## Implementation Implications

- Add one app-owned cache boundary for public-front config:
  `App\Support\PublicFront\PublicFrontConfigCache`.
- Use versioned base key `public_front.config.v1` plus a settings-migration watermark.
- Build the watermark from configured Spatie settings migration paths by count and latest
  settings migration filename. This avoids an extra database read on each public render
  and rotates whenever a settings migration file ships.
- Store cache payloads as arrays containing normalized config and invalid-config arrays.
- Reconstruct `PublicFrontConfigResult` and `PublicFrontInvalidConfig` from the payload
  on cache hits. Do not cache PHP objects because cache unserialization is disabled.
- Treat any malformed cache payload as corrupted: forget it, recompute, and rewrite the
  current key.
- Reuse the existing `SETTINGS_CACHE_ENABLED` setting cache knob by reading
  `config('settings.cache.enabled')`. Tests can enable it explicitly; Forge should set it
  to `true` when enabling settings/config caching.
- Extend the existing `SettingsSaved` listener for `PublicContentSettings` to forget the
  persistent config cache in addition to scoped app instances.
- Move the V1c palette cache key generation behind the same helper for naming
  consistency. Palette entries remain content-addressed by cover path + mtime and do not
  need the settings-migration watermark or `SettingsSaved` invalidation.

## Stop Conditions

- Stop if preflight finds unexpected app-code dirt.
- Stop if P1 would require a migration, public route change, public SPA mode, or AX/SL/S
  implementation.
- Stop if the v4 plan/ledger/sequence no longer list P1 as first pending.
