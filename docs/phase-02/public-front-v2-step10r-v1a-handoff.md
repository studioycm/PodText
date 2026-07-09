# Public Front v2 10R-V1a Handoff

## Purpose

Step 10R-V1a adds finite default/no-image fallback settings for public images without
adding remote image fetching or new contributor image schema.

## What Was Implemented

- Added `public_content.default_images` with `global`, `content_item`, `content_group`,
  and `contributor` families.
- Added finite per-family modes:
  - `inherit`: fall through to the global fallback.
  - `custom`: use the configured public-disk image when no more specific image exists.
  - `none`: stop the fallback chain and render the existing placeholder/initials state.
- Added validator normalization for modes and `default-images/` public image paths.
- Added a settings migration to backfill the new settings group.
- Added `PublicFrontRenderContext::defaultImages()` and `PublicDefaultImageResolver`.
- Added constrained admin FileUploads on the Public Content settings display tab:
  public disk, `default-images/` directory, public visibility, image-only, JPEG/PNG/WebP,
  and 2048 KB max.
- Routed content item, content group, and contributor cards through the shared resolver.
- Routed public item, podcast, and contributor detail pages through the same resolver.
- Added contributor detail image/initials header rendering.

## Files Changed

- `app/Filament/Pages/PublicContentSettings.php`
- `app/Filament/Public/Pages/ShowContentGroup.php`
- `app/Filament/Public/Pages/ShowContentItem.php`
- `app/Filament/Public/Pages/ShowContributor.php`
- `app/Settings/PublicContentSettings.php`
- `app/Support/PublicFront/Cards/PublicContentGroupCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicContributorCardPresenter.php`
- `app/Support/PublicFront/PublicDefaultImageResolver.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/PublicFrontRenderContext.php`
- `database/settings/2026_07_09_000005_add_public_default_image_settings.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/views/components/public/contributor-card-part.blade.php`
- `resources/views/filament/public/pages/show-content-group.blade.php`
- `resources/views/filament/public/pages/show-contributor.blade.php`
- `tests/Feature/PublicDefaultImagesSettingsTest.php`
- `docs/research/public-front-v2/20-step10r-v1a-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-v1a-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-v1a-handoff.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/phase-02/current-project-state.md`

## Tests Added Or Updated

Added `tests/Feature/PublicDefaultImagesSettingsTest.php` covering:

- default image validator normalization;
- settings migration backfill;
- settings page save for no-image mode;
- content item custom/global/none modes on cards and item pages;
- explicit item thumbnail and podcast cover precedence;
- content group custom/global/none modes on cards and detail pages;
- contributor custom/global/none modes on cards and detail pages.

## Exceptions / Notes

- V1a did not add a contributor image column. Contributor fallback starts from
  configured contributor/global images until a future prompt adds owned contributor
  images.
- `none` still allows explicit own images. For content items, an item thumbnail still
  renders; if there is no item thumbnail, `none` suppresses podcast-cover/default/global
  fallbacks.
- Default images are storage-managed public paths only. No remote fetching was added.
- V1b icon-picker work and V1c custom color/palette work remain pending.

## Quality Gate

Focused checks already run:

```bash
php artisan test tests/Feature/PublicDefaultImagesSettingsTest.php
php artisan test tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php tests/Feature/PublicItemPageMediaParserTest.php tests/Feature/PublicPodcastsGroupsUxTest.php tests/Feature/PublicContributorsTopTranscribersUxTest.php tests/Feature/PublicFrontJsonSettingsArchitectureTest.php
php artisan test tests/Feature/AdminPhase02ResourcesTest.php tests/Feature/AdminResourcesTest.php
php artisan migrate
```

Final gate results:

- `vendor/bin/pint --dirty --format agent` passed.
- `php artisan test` passed: 296 tests, 2791 assertions.
- `vendor/bin/pint --test` passed.
- `vendor/bin/filacheck` passed: 0 issues.
- `npm run build` passed.
- `git diff --check` passed.

Focused check results:

- `php artisan test tests/Feature/PublicDefaultImagesSettingsTest.php` passed:
  6 tests, 84 assertions.
- Public regression suite passed: 54 tests, 747 assertions.
- Admin focused suite passed: 31 tests, 486 assertions.
- `php artisan migrate` ran
  `2026_07_09_000005_add_public_default_image_settings`.

## Commit hash

Commit message: `feat: add default image fallback settings`.

The exact commit hash is created after the final gate and lands in the final report.

## Local Front Check Report

1. Open `/admin/public-content-settings?public-content-tab=display` in Hebrew/RTL.
   Confirm the new Default images section appears after the public-front display
   configuration. Toggle each family between inherit, custom, and no image. In custom
   mode the upload field should appear, preview uploaded images, and accept JPEG/PNG/WebP
   only.
2. On `/search`, find or create an episode with no thumbnail and no podcast cover.
   With Episode cards/pages set to custom, expect the configured episode default image on
   the card. With global custom and episode inherit, expect the global image. With episode
   no image, expect the normal type-label placeholder.
3. Open `/items/{podcastSlug}/{episodeSlug}` for the same episode. Expected behavior
   matches the search card: custom/default image when configured, no image block when
   episode mode is no image and there is no explicit thumbnail.
4. Verify precedence on an episode that has an external thumbnail and a podcast cover.
   The item thumbnail should render even when episode mode is no image. If the thumbnail
   is removed, the podcast cover should render unless episode mode is no image.
5. Open `/podcasts`. For a podcast with no cover, content-group custom/global fallback
   images should render on the card; content-group no image should show the initials
   placeholder. Existing podcast covers should continue to win.
6. Open `/podcasts/{podcastSlug}` for the same podcast. The header image should match
   the podcast card fallback behavior, while the episode list below may use the episode
   family settings separately.
7. Open `/contributors` with a contributor card template that includes an image part.
   Contributor custom/global fallback images should render on cards; contributor no image
   should show initials.
8. Open `/contributors/{authorSlug}`. The contributor header should show the configured
   contributor/global image or the initials fallback. Check both light and dark mode; the
   image is neutral and initials use existing theme-safe classes.

## Next Step

Step 10R-V1b is next: Heroicon registry and shared icon picker.
