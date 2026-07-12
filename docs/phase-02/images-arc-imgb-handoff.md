# Images Arc IMG-B Handoff

Date: 2026-07-12

## Scope

Implemented `prompts/pre-13-prompts/images-arc-imgb-episode-images-tb1-codex-prompt.md` as the single session step.

No Composer changes were made.

## Implemented

- D-IMG-A v2: `admin_ux.media_naming_strategy` now controls image egress/download filenames only. Storage filenames remain uploader/media-library owned.
- D-IMG-E: replacing, clearing, or deleting owning records keeps Curator-registered media files. Cleanup applies only to app-owned no-row strays with no remaining references.
- Referenced-media delete guard: Curator media referenced by `content_groups.cover_path`, `content_items.image_path`, or public settings image paths is blocked at policy/observer boundaries.
- Local episode images: `content_items.image_path`, `ImageFileNamer::CONTENT_ITEM_IMAGE`, workspace image picker, factory/model support, and content-item observer cleanup.
- Public resolver order: local episode image, external thumbnail, group cover when allowed, then configured defaults/fallback. External thumbnail source labels now report `item_external`.
- External image download: queued `imports-exports` job with HTTPS-only, size cap, raster MIME/content validation, Curator row registration, overwrite action, and database notifications.
- TB1 actions: podcast cover picker and episode image picker actions on table surfaces, modal/slideover container support via `tb1_picker_container`, and effective-image thumbnail columns.
- Content-images ZIP export: podcast-list header action plus per-podcast action, per-action egress naming override, queued ZIP build, private local storage, delete-before-create per-user retention, skip reporting, and notification download action.
- IMG-A retrospective handoff and EP1 commit-hash backfill.

## Deferred

- CSV/native import/export columns for `content_items.image_path` remain deferred.
- Zip import packages and manifest-based media imports remain deferred.
- EXIF stripping remains deferred until an approved re-encoding step exists.
- Category images and bulk media ingestion remain future work.

## Tooling Notes

- Laravel Boost was available and used before code changes for `application_info`, schema inspection, and version-aware documentation. It reported PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Horizon 5.47.2, and Pest 4.7.4.
- Boost schema confirmed `content_items.image_path` did not exist before this run and confirmed the existing content/media/settings storage surfaces.
- FilamentExamples MCP was used in decomposed and refined search batches for table image actions, action modals, image columns, queued exports, and download notification patterns. Access was search/snippet only; no source/read/fetch/detail tool was exposed.

## Tests Added Or Updated

- `tests/Feature/ContentImagesExportTest.php`: ZIP naming/skip handling, header and record export queue dispatch, export-ready notification, external image success, and non-HTTPS/oversized/non-raster rejection.
- `tests/Feature/ImageMediaCuratorTest.php`: D-IMG-E retention, referenced media delete blocking, legacy no-row path preservation, and content item image stray cleanup.
- `tests/Feature/PublicDefaultImagesSettingsTest.php`: local item image precedence and truthful external source labels.
- `tests/Feature/EpisodeWorkspaceTest.php`: TB1 image action visibility and external download queue dispatch.
- `tests/Feature/PublicItemPageMediaParserTest.php`: updated item-page external thumbnail source label.
- `tests/Unit/ImageFileNamerTest.php`: selected egress strategy for export filenames.

## Targeted Verification

- `php artisan test --compact tests/Feature/ContentImagesExportTest.php --filter='content image|external'` passed: 5 tests, 26 assertions.
- `php artisan test --compact tests/Feature/ImageMediaCuratorTest.php --filter='unused app-owned cover|library registered|blocks deleting|preserves legacy|unregistered local episode image'` failed once on legacy path preservation, then passed after the picker sentinel fix: 5 tests, 15 assertions.
- `php artisan test --compact tests/Feature/PublicDefaultImagesSettingsTest.php --filter='local item images'` passed: 1 test, 23 assertions.
- `php artisan test --compact tests/Feature/EpisodeWorkspaceTest.php --filter='TB1 image actions'` passed: 1 test, 15 assertions.
- `php artisan test --compact tests/Unit/ImageFileNamerTest.php --filter='egress strategy'` passed: 1 test, 3 assertions.
- `php artisan test --compact tests/Feature/PublicItemPageMediaParserTest.php --filter='configured item page header image'` passed after updating the stale source-label assertion: 1 test, 25 assertions.
- `php artisan test --compact tests/Feature/ContentImagesExportTest.php --filter='non-HTTPS oversized|external item images|content image'` passed after adding the oversized case: 5 tests, 27 assertions.

## Final Verification

- `vendor/bin/pint --test` failed once on formatting in four files; `vendor/bin/pint ...` applied formatting; all later `vendor/bin/pint --test` runs passed.
- Full test run 1: `php artisan test` failed with 420 passed / 421 total because `PublicItemPageMediaParserTest` still expected external thumbnails to report source `item`.
- Full test run 2: `php artisan test` passed with 421 tests, 3,804 assertions.
- FilaCheck run 1: `vendor/bin/filacheck` failed because the episode table had 11 always-visible columns after adding the effective image. `pin_order` was made hidden-by-default while keeping effective image visible.
- Full test run 3: `php artisan test` passed with 421 tests, 3,804 assertions after the FilaCheck fix.
- Full test run 4: `php artisan test` passed with 421 tests, 3,805 assertions after adding the explicit oversized-download rejection test.
- Final `vendor/bin/filacheck` passed with 0 issues.
- Final `npm run build` passed.

## Requirement Classification

- Implemented: D-IMG-A v2 egress naming, D-IMG-E retention, referenced-media delete blocking, local episode image, queued external download, TB1 image actions, effective image column, and export-only content-images ZIP.
- Already existed: Curator library baseline, group cover path storage, public default-image resolver boundary, and `imports-exports` queue/Horizon baseline.
- Deferred by prompt: CSV image columns, zip import packages, EXIF stripping, category images, WB7 bulk ingestion, and SF/TL tools.
- Not applicable: Composer/dependency changes.
- Blocked: none.

## Query Impact

Episode table queries already eager-load `contentGroup`, `featuredTranscription.authors`, and `latestPublishedTranscription.authors`; the effective-image column calls `PublicDefaultImageResolver` against those loaded relationships and does not add per-row database queries.

## Local Front Check Report

1. In the episode workspace, choose a local episode image and save; open the public item/card surface and confirm it prefers the local image over the Spotify/external thumbnail.
2. Clear that local episode image and save; confirm the external thumbnail wins again and the previously selected local file remains in the Curator library.
3. From the podcasts table, replace a podcast cover with the table image action; confirm the old Curator-registered cover remains on disk and in the library.
4. In the Curator UI, try deleting a media row still referenced by a podcast cover, episode image, menu logo, team/about image, or default image; confirm deletion is blocked with a translated warning naming the referencing surface.
5. Delete an unreferenced Curator media row and confirm deletion is allowed.
6. Run the external image download action on an episode with only an external thumbnail; confirm a local `content-items/images` file appears, a Curator row is registered, and the episode uses it.
7. Run "Download content images" from the podcasts list; confirm a ZIP arrives with `podcasts/{name}/cover.{ext}` and nested `podcasts/{name}/episodes/{episode}.{ext}` entries.
8. Run the content-images export again with a different naming strategy selected in the modal; confirm the ZIP entry stems change to match the selected strategy.
9. Flip `tb1_picker_container` between modal and slide-over; confirm podcast and episode table image picker actions use the selected container.
10. Confirm the episodes table effective thumbnail matches the public resolver result for local image, external image, group cover, and default fallback cases.
11. Repeat the key image picker and export flows in Hebrew RTL, light mode, and dark mode.

## Commit hash

IMG-B was committed as `8c590ab58f1b4b4b89ec85b7c0541d95a41cde90 feat: add episode images, media guards, and content images export`.
