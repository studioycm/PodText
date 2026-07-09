# Public Front v2 10R-V1c Handoff

## Purpose

Step 10R-V1c adds strict custom hex colors for existing item-page color surfaces and
turns podcast-cover sampled colors into cached, light/dark-safe palette variants.

## What Was Implemented

- Added `PublicFrontColor` for strict `#rgb`/`#rrggbb` normalization, contrast checks,
  theme-safe variants, and controlled CSS variable strings.
- Added `custom_color` beside finite color tokens for:
  - `item_page.podcast_identity`;
  - `item_page.badges.info`;
  - each `item_page.info_fields[]` row.
- Added `custom` as a finite color token only for the existing admin color surfaces.
- Added conditional Filament `ColorPicker` fields with strict hex validation and
  save-time normalization.
- Added a settings migration to backfill and remove `custom_color` keys safely.
- Updated public item-page rendering so custom and sampled colors emit CSS variables
  only from validated hex values.
- Changed podcast image palette sampling to return cached light/dark variants keyed by
  cover path and file mtime.
- Kept blank/remote cover paths on fallback palette values without remote fetches.
- Added D9 to the transcription display decisions doc for the sanctioned strict-color
  exception.

## Files Changed

- `app/Support/PublicFront/Colors/PublicFrontColor.php`
- `app/Support/PublicFront/ItemPage/PublicItemPagePodcastPalette.php`
- `app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Filament/Public/Pages/ShowContentItem.php`
- `resources/views/filament/public/pages/show-content-item.blade.php`
- `database/settings/2026_07_09_000007_add_public_custom_color_settings.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `tests/Feature/PublicFrontCustomColorsTest.php`
- Existing item-page/settings regression tests updated for the new normalized shape.
- V1c research/plan docs, central ledger, implementation sequence, current state, and
  D9 decisions note.

## Tests Added Or Updated

Added `tests/Feature/PublicFrontCustomColorsTest.php` covering:

- strict custom hex normalization and invalid fallback paths;
- settings-page custom option and revealable `ColorPicker`;
- settings-page save normalization and stale custom-color clearing;
- deterministic cover sampling with light/dark contrast assertions;
- palette cache reuse by cover path and mtime;
- remote cover paths returning fallback values without computation/fetching;
- public item-page CSS-variable rendering for custom colors.

Updated existing tests for:

- normalized `custom_color => null` shape;
- sampled podcast image colors now exposing light/dark CSS variables.

## Exceptions / Notes

- Custom color is the only sanctioned non-finite visual value and is stored only in
  `custom_color`; the sibling `color` field remains finite.
- Public rendering still never stores or renders raw Tailwind classes, CSS snippets,
  SVG, HTML, or component names from settings.
- Custom colors render as the exact normalized admin-selected hex. Theme-safe contrast
  transformation is applied to sampled podcast image colors, not arbitrary custom colors.
- Podcast palette caching uses the default cache store and no cache tags.
- No JavaScript dependency was added; AX1/GSAP remains scheduled for a later step.

## Quality Gate

Focused checks already run:

```bash
php artisan migrate
php artisan test tests/Feature/PublicFrontCustomColorsTest.php
php artisan test tests/Feature/PublicFrontJsonSettingsArchitectureTest.php tests/Feature/PublicItemPageMediaParserTest.php tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php tests/Feature/AdminPhase02ResourcesTest.php tests/Feature/AdminResourcesTest.php
vendor/bin/pint --dirty --format agent
```

Focused check results:

- `php artisan migrate` ran
  `2026_07_09_000007_add_public_custom_color_settings`.
- `tests/Feature/PublicFrontCustomColorsTest.php` passed: 7 tests, 66 assertions.
- Adjacent settings/item-page/harness/admin suite passed: 66 tests, 1029 assertions.
- `vendor/bin/pint --dirty --format agent` passed.

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
- `php artisan test` passed: 309 tests, 2931 assertions.
- `vendor/bin/pint --test` passed.
- `vendor/bin/filacheck` passed: 0 issues.
- `npm run build` passed.
- `git diff --check` passed.

## Commit hash

Commit message: `feat: add custom colors and theme safe podcast palette`.

The exact commit hash is created after the final gate and lands in the final report.

## Local Front Check Report

1. Open `/admin/public-content-settings?public-content-tab=item-page` in Hebrew/RTL.
   In the Episode page header section, change Podcast color to Custom. Expected: a
   custom color picker appears; entering `#abc` saves as `#aabbcc`.
2. In the Episode page badges section, change Info badge color to Custom. Expected: the
   info-badge custom color picker appears and validates only strict 3- or 6-digit hex.
3. In Episode page info fields, edit one field row and change Field color to Custom.
   Expected: the row-level custom color picker appears; switching back to Gray/Primary
   clears the stale custom value on save.
4. Open an episode detail URL such as `/items/{podcast-slug}/{episode-slug}` after
   setting podcast identity to Custom. Expected: the podcast identity uses the selected
   color in light and dark mode through CSS variables, with no visible RTL regression.
5. Set Podcast color to Podcast image color 1/2/3 for a podcast with a cover image and
   open an episode detail URL. Expected: the sampled color remains visible in light and
   dark mode with theme-safe contrast.
6. Check an episode whose podcast has no cover or only a remote/blank cover path.
   Expected: the public page still renders using fallback palette colors; no broken image
   fetch or loading delay appears.

## Next Step

Step 10R-P1 is next: validated public-front config caching with a versioned key and
settings-migration watermark.
