# Public Front v2 IP1 Handoff

## Purpose

Step 10R-IP1 creates the episode-page settings foundation for date display, adds finite info-badge tokens, and exposes the missing content-item card attribute for the on-site publish date.

## What was implemented

- Added `public_content.item_page` as an extensible JSON settings group.
- Added defaults and validator normalization for site/original/transcription date settings.
- Added a dedicated Episode page tab to Public Content Settings.
- Moved the existing scalar `item_page_layout` field into the Episode page tab while keeping it as a compatibility scalar.
- Added finite info-badge size/color tokens and fixed PHP class maps in `PublicItemPageRegistry`.
- Added `PublicFrontRenderContext::itemPage()`.
- Added a Spatie settings migration to create/backfill `public_content.item_page`.
- Added `content_item.site_published_date` to the card-template registry/presenter.
- Added `content_item.original_published_date` as a compatibility alias for the existing `content_item.original_published_at`.
- Added rendered-output coverage proving a custom content-item card template can show both publication dates with labels/icons.

## Requirement IDs landed

Landed:

- R1 data/attributes part: cards can render `site_published_date`; original and effective date attributes remain available.
- R2: `item_page.dates.display` with `site|original|both`.
- R3: long/short/hidden label mode plus optional plain-text label overrides.
- R4: per-date finite icon and icon-position settings.
- R5: transcription-date enabled flag plus label/icon settings.
- R6: Episode page settings tab.
- R13 token part: finite info-badge size/color vocabulary and fixed class maps.
- R23: extensible `item_page` JSON root.

Remaining:

- R1 page placement/rendering part remains IP2.
- R7-R10 and R19-R22 remain IP3.
- R11-R18 remain IP2.

## Finding IDs resolved

No F findings were resolved by IP1.

F1-F3, F7, F11-F13, and F15 remain scheduled for their owning P/B4/C2 steps.

## Files changed

- `app/Filament/Pages/PublicContentSettings.php`
- `app/Settings/PublicContentSettings.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`
- `app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Support/PublicFront/PublicFrontRenderContext.php`
- `database/settings/2026_07_09_000000_add_public_item_page_settings.php`
- `lang/en/admin.php`
- `lang/en/public.php`
- `lang/he/admin.php`
- `lang/he/public.php`
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- `tests/Feature/PublicFrontRenderContextTest.php`
- `docs/phase-02/public-front-v2-step10r-ip1-implementation-plan.md`
- `docs/research/public-front-v2/18-step10r-ip1-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-ip1-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`

## Migrations/settings/schema changes

- Added settings migration `2026_07_09_000000_add_public_item_page_settings`.
- No database table schema changed.
- Local migration status shows the new settings migration as run.

## Settings keys and defaults

New setting group:

```json
{
  "item_page": {
    "dates": {
      "display": "both",
      "site_published": {
        "label_mode": "long",
        "label_override": null,
        "icon": "calendar",
        "icon_position": "inline_before"
      },
      "original_published": {
        "label_mode": "short",
        "label_override": null,
        "icon": "calendar",
        "icon_position": "inline_before"
      },
      "transcription_date": {
        "enabled": true,
        "label_mode": "short",
        "label_override": null,
        "icon": "document",
        "icon_position": "inline_before"
      }
    },
    "badges": {
      "info": {
        "size": "sm",
        "color": "gray"
      }
    }
  }
}
```

Legacy scalar retained:

- `item_page_layout`: unchanged storage and behavior; moved to the Episode page settings tab.

## Registry/validator/render-context changes

- `PublicFrontConfigRegistry::settingsKeys()` and `schema()` now include `item_page`.
- `PublicFrontConfigRegistry::defaults()` now includes `item_page`.
- `PublicFrontConfigValidator` normalizes `item_page.dates` and `item_page.badges`.
- `PublicItemPageRegistry` owns finite option vocabularies and fixed info-badge class maps.
- `PublicFrontRenderContext::itemPage()` returns the normalized group.
- `PublicFrontCardTemplateRegistry` now exposes `content_item.site_published_date` and `content_item.original_published_date`.

## Public rendering behavior

- Content item cards can render:
  - `content_item.site_published_date` from `content_items.published_at`.
  - `content_item.original_published_at` from `content_items.original_published_at`.
  - `content_item.original_published_date` as an alias for `original_published_at`.
- Dates are formatted `dd/mm/yyyy` in `Asia/Jerusalem`.
- No public episode page header/info-line rendering was rebuilt in IP1; IP2 owns that placement.

## Admin behavior

- Public Content Settings now has an Episode page tab.
- The tab includes:
  - compatibility layout select;
  - date display mode;
  - site/original/transcription date fieldsets;
  - transcription date enabled toggle;
  - label mode, label override, icon, and icon-position fields;
  - info-badge size/color token controls.
- All new admin labels/helpers are translated in English and Hebrew.

## Query/performance behavior

- No public query changes.
- Card date rendering uses already-loaded model attributes.
- Settings validation remains uncached until P1 resolves F1.
- The bounded query-count harness remains green.

## Translation keys added/updated

Added in `lang/en/admin.php` and `lang/he/admin.php`:

- `admin.tabs.public_content_settings.item_page`
- `admin.sections.public_front_item_page_layout`
- `admin.sections.public_front_item_page_dates`
- `admin.sections.public_front_item_page_badges`
- `admin.sections.item_page_site_published_date`
- `admin.sections.item_page_original_published_date`
- `admin.sections.item_page_transcription_date`
- `admin.descriptions.public_front_item_page_layout`
- `admin.descriptions.public_front_item_page_dates`
- `admin.descriptions.public_front_item_page_badges`
- `admin.fields.item_page_dates_display`
- `admin.fields.item_page_date_label_mode`
- `admin.fields.item_page_date_label_override`
- `admin.fields.item_page_date_icon`
- `admin.fields.item_page_date_icon_position`
- `admin.fields.item_page_transcription_date_enabled`
- `admin.fields.item_page_info_badge_size`
- `admin.fields.item_page_info_badge_color`
- matching `admin.helpers.*`
- `admin.item_page_date_displays.*`
- `admin.item_page_label_modes.*`
- `admin.item_page_badge_sizes.*`
- `admin.item_page_badge_colors.*`
- `admin.card_template_attributes.content_item.site_published_date`
- `admin.card_template_attributes.content_item.original_published_date`

Added in `lang/en/public.php` and `lang/he/public.php`:

- `public.dates.site_published_long`
- `public.dates.site_published_short`
- `public.dates.original_published_long`
- `public.dates.original_published_short`
- `public.dates.transcription_date_long`
- `public.dates.transcription_date_short`

## Tests added/updated/renamed

Updated:

- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
  - default merge coverage for `item_page`;
  - settings migration/backfill coverage;
  - invalid token normalization coverage;
  - settings-page save coverage;
  - translation-key coverage.
- `tests/Feature/PublicFrontRenderContextTest.php`
  - `itemPage()` accessor coverage.
- `tests/Feature/PublicFrontCardTemplateBuilderTest.php`
  - custom card template rendering both site and original publication dates with labels/icons.

No tests were renamed.

## Tests changed because old assertions intentionally moved/changed

No legacy assertions were intentionally removed.

The Episode page `item_page_layout` field moved from the General/display settings tab to the Episode page tab, but tests still set the same scalar state path.

## Security/fallback behavior

- Settings JSON stores finite tokens only.
- Label overrides are plain text, length-bounded, and rejected if unsafe.
- Icons use the M5 finite icon registry; raw SVG/component/class strings are rejected.
- Badge size/color values are semantic tokens mapped through PHP support code.
- Invalid nested keys are reported through existing invalid-config reporting.

## Bounded query-count harness result

`php artisan test tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php` passed: 7 tests, 64 assertions.

## Impact on later mini-steps

- IP2 can consume `PublicFrontRenderContext::itemPage()`, the date settings, and `PublicItemPageRegistry` badge maps to rebuild the episode header/info line.
- IP3 can extend the same `item_page` root for transcript action menu and reading controls.
- B4 must preserve `site_published_date` and `original_published_date` when converging card options.
- P1 should include `item_page` in the cached validated public-front config.

## Open issues / follow-up decisions

- IP2 still needs to place site/original/transcription dates on the public episode page.
- IP2 still needs taxonomy link audit, breadcrumbs, page image fallback, podcast identity, and ordered info fields.
- IP3 still owns share movement, transcript actions menu, fullscreen, font size, and player visibility controls.
- No default card template was changed; the new dates render through custom card templates.

## Quality gate summary

Focused gates passed:

- `php artisan test tests/Feature/PublicFrontJsonSettingsArchitectureTest.php` - 9 tests, 118 assertions
- `php artisan test tests/Feature/PublicFrontRenderContextTest.php` - 9 tests, 44 assertions
- `php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php` - 24 tests, 311 assertions
- `php artisan test tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php` - 7 tests, 64 assertions
- `php artisan test tests/Feature/PublicPodcastsGroupsUxTest.php tests/Feature/PublicContributorsTopTranscribersUxTest.php` - 19 tests, 200 assertions
- `php artisan test tests/Feature/PublicItemPageMediaParserTest.php` - 11 tests, 106 assertions
- `php artisan test --filter=PublicTranscriptRenderingTest` - 2 tests, 12 assertions
- `php artisan test tests/Feature/TaxonomyTagsPinningSettingsTest.php --filter="loads public content settings defaults"` - 1 test, 38 assertions

Final full gate:

- `vendor/bin/pint --dirty --format agent` - passed
- `php artisan test` - 277 tests, 2357 assertions
- `vendor/bin/pint --test` - passed
- `vendor/bin/filacheck` - passed with 0 issues
- `npm run build` - passed
- `git diff --check` - passed

## Commit hash

This commit: `feat: add episode page settings and publication dates`
