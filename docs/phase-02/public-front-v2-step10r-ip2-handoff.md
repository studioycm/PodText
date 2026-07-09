# Public Front v2 IP2 Handoff

## Purpose

Step 10R-IP2 rebuilds the public episode page header and info line so the page consumes the `item_page` settings foundation added in IP1.

## What was implemented

- Extended `item_page` with breadcrumb, podcast identity, and ordered info-field settings.
- Added a Spatie settings migration to backfill those keys into existing `public_content.item_page` rows.
- Added Episode page admin controls for breadcrumbs, podcast identity, and ordered metadata fields.
- Rebuilt the public episode page header with:
  - optional breadcrumbs;
  - episode thumbnail with podcast cover fallback;
  - large episode title;
  - linked podcast identity as badge/text/hidden;
  - wrapping ordered info fields under the title.
- Rendered page dates from IP1 date settings, including label overrides, icon keys, icon position, and `dd/mm/yyyy` `Asia/Jerusalem` formatting.
- Moved category/tag links into the header info line above the description.
- Added site-wide link audit assertions for homepage item cards, group cards, contributor item grids, episode page, and podcast detail page.

## Requirement IDs landed

Landed:

- R1 page part: episode page can display site/original publication dates.
- R11: categories and tags are links in the info line above the description.
- R12: category, tag, and podcast/group labels on cards/pages are link-audited.
- R13 render part: episode info badges use finite icon, size, and color tokens.
- R14: no standalone episode/transcription type label is rendered on the episode page.
- R15: breadcrumbs are controlled by `item_page.show_breadcrumbs`.
- R16: page image uses episode thumbnail, else podcast cover.
- R17: podcast identity is badge/text/hidden, finite icon/color, and links to podcast.
- R18: ordered info fields render under the title.

Remaining:

- R7-R10 and R19-R22 remain IP3.
- M6 will verify R1-R23 after IP3.

## Finding IDs resolved

No F findings were resolved by IP2.

F1-F3, F7, F11-F13, and F15 remain scheduled for their owning P/B4/C2 steps.

## Files changed

- `app/Filament/Pages/PublicContentSettings.php`
- `app/Filament/Public/Pages/ShowContentItem.php`
- `app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `database/settings/2026_07_09_000001_add_public_item_page_header_settings.php`
- `resources/views/filament/public/pages/show-content-item.blade.php`
- `lang/en/admin.php`
- `lang/en/public.php`
- `lang/he/admin.php`
- `lang/he/public.php`
- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- `tests/Feature/PublicItemPageMediaParserTest.php`
- `tests/Feature/PublicHomepageSearchTest.php`
- `tests/Feature/PublicPodcastsGroupsUxTest.php`
- `tests/Feature/PublicContributorsTopTranscribersUxTest.php`
- `docs/phase-02/public-front-v2-step10r-ip2-implementation-plan.md`
- `docs/research/public-front-v2/18-step10r-ip2-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-ip2-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`

## Migrations/settings/schema changes

- Added settings migration `2026_07_09_000001_add_public_item_page_header_settings`.
- No database table schema changed.
- Local `php artisan migrate` ran the new settings migration successfully.

## Settings keys and defaults

Extended `item_page`:

```json
{
  "show_breadcrumbs": true,
  "podcast_identity": {
    "mode": "badge",
    "color": "primary",
    "icon": "podcast",
    "icon_position": "inline_before"
  },
  "info_fields": [
    { "field": "site_published_date", "label_mode": "long", "label_override": null, "icon": "calendar", "icon_position": "inline_before", "size": "sm", "color": "gray" },
    { "field": "original_published_date", "label_mode": "short", "label_override": null, "icon": "calendar", "icon_position": "inline_before", "size": "sm", "color": "gray" },
    { "field": "transcription_date", "label_mode": "short", "label_override": null, "icon": "document", "icon_position": "inline_before", "size": "sm", "color": "gray" },
    { "field": "duration", "label_mode": "hidden", "label_override": null, "icon": "clock", "icon_position": "inline_before", "size": "sm", "color": "gray" },
    { "field": "transcribers", "label_mode": "hidden", "label_override": null, "icon": "users", "icon_position": "inline_before", "size": "sm", "color": "gray" },
    { "field": "categories", "label_mode": "hidden", "label_override": null, "icon": "folder", "icon_position": "inline_before", "size": "sm", "color": "gray" },
    { "field": "tags", "label_mode": "hidden", "label_override": null, "icon": "tag", "icon_position": "inline_before", "size": "sm", "color": "gray" },
    { "field": "transcription_count", "label_mode": "hidden", "label_override": null, "icon": "document", "icon_position": "inline_before", "size": "sm", "color": "gray" }
  ]
}
```

IP1 `item_page.dates` and `item_page.badges` remain in the same group. Date fields render from `item_page.dates.*` label/icon settings; `info_fields` controls order and badge size/color.

## Registry/validator/render-context changes

- `PublicItemPageRegistry` now owns:
  - podcast identity modes;
  - info field keys/options;
  - default info field rows.
- `PublicFrontConfigRegistry::defaults()` includes the new keys.
- `PublicFrontConfigValidator` normalizes:
  - `show_breadcrumbs`;
  - `podcast_identity`;
  - `info_fields`.
- `PublicFrontRenderContext::itemPage()` is unchanged from IP1 and now returns the extended normalized group.

## Public rendering behavior

- The episode page header is settings-driven.
- Breadcrumbs hide when `item_page.show_breadcrumbs` is false.
- Podcast identity always links to the podcast detail page when visible.
- Info fields skip empty values.
- Category/tag/transcriber values render as links.
- Date fields render only when allowed by `item_page.dates.display` and transcription-date `enabled`.
- Share block remains below the player; IP3 owns moving it above the player.
- Transcript actions remain unchanged; IP3 owns the actions menu and reading controls.

## Admin behavior

- The Episode page tab now includes:
  - breadcrumbs toggle;
  - podcast identity mode/color/icon/icon-position controls;
  - ordered Repeater for info fields.
- Repeater rows use finite field keys, label modes, icon keys, icon positions, size tokens, and color tokens.
- No raw classes, HTML, SVG, or component names are accepted.

## Query/performance behavior

- The page uses the existing `PublicContentItemQueries::base()` eager-loaded relations.
- No queued work was added.
- No cache/P1 work was added.
- The bounded query-count harness remains green.

## Translation keys added/updated

Added in `lang/en/admin.php` and `lang/he/admin.php`:

- `admin.sections.public_front_item_page_header`
- `admin.sections.public_front_item_page_info_fields`
- `admin.descriptions.public_front_item_page_header`
- `admin.descriptions.public_front_item_page_info_fields`
- `admin.fields.item_page_show_breadcrumbs`
- `admin.fields.item_page_podcast_identity_mode`
- `admin.fields.item_page_podcast_identity_color`
- `admin.fields.item_page_podcast_identity_icon`
- `admin.fields.item_page_podcast_identity_icon_position`
- `admin.fields.item_page_info_fields`
- `admin.fields.item_page_info_field_*`
- matching `admin.helpers.*`
- `admin.item_page_info_fields.*`
- `admin.item_page_podcast_identity_modes.*`

Added in `lang/en/public.php` and `lang/he/public.php`:

- `public.item_page.info_fields.*_long`
- `public.item_page.info_fields.*_short`

## Tests added/updated/renamed

Updated:

- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
  - settings migration backfill for IP2 keys;
  - unsafe-token normalization;
  - settings-page save path;
  - translation-key coverage.
- `tests/Feature/PublicItemPageMediaParserTest.php`
  - configured header/image/podcast identity/info line rendering;
  - date setting consumption;
  - group-cover image fallback.
- `tests/Feature/PublicHomepageSearchTest.php`
  - homepage group-badge link audit.
- `tests/Feature/PublicPodcastsGroupsUxTest.php`
  - group-card, group category, and episode-card link audit.
- `tests/Feature/PublicContributorsTopTranscribersUxTest.php`
  - contributor item grid item/podcast link audit.

No tests were renamed.

## Tests changed because old assertions intentionally moved/changed

- Episode page taxonomy is now rendered in the configured info line rather than a separate taxonomy section.
- The public item page duration assertion was adjusted to the localized `public.labels.duration_value` output and zero-padded duration string.
- No tests were deleted.

## Security/fallback behavior

- Labels are escaped Blade text.
- Icons are finite registry keys resolved by the app-owned icon resolver.
- Settings validator rejects/normalizes unsafe raw strings, raw classes, invalid icon keys, invalid fields, and malformed nested rows.
- The settings migration converts nested object payloads to arrays before merging so existing values are preserved.
- Public pages still resolve only public groups/items/transcriptions through existing query constraints.

## Bounded query-count harness result

Passed:

```bash
php artisan test tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php
```

Result: 7 tests passed, 64 assertions.

## Impact on later mini-steps

- IP3 can use the extended `item_page` group for transcript actions/menu/player toggles without reshaping settings.
- M6 should verify the IP2 header/info-line behavior when closing R1-R23.
- B4/C2 should preserve the page link audit and avoid breaking M5/IP1/IP2 label/icon/date behavior.

## Open issues / follow-up decisions

- IP3 still owns share movement, transcript details row, actions menu, font controls, fullscreen, and player visibility toggle.
- No C1 cleanup was started; M6 decides final C1 status.
- No performance F-status changed.

## Quality gate summary

Passed:

```bash
php artisan migrate
php artisan test tests/Feature/PublicFrontJsonSettingsArchitectureTest.php tests/Feature/PublicItemPageMediaParserTest.php tests/Feature/PublicHomepageSearchTest.php tests/Feature/PublicPodcastsGroupsUxTest.php tests/Feature/PublicContributorsTopTranscribersUxTest.php
php artisan test tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php
php artisan test tests/Feature/PublicFrontCardTemplateBuilderTest.php tests/Feature/PublicPodcastsGroupsUxTest.php tests/Feature/PublicContributorsTopTranscribersUxTest.php tests/Feature/PublicItemPageMediaParserTest.php
php artisan test --filter=PublicTranscriptRenderingTest
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```

Note: the first attempted combined focused command with `--filter=PublicTranscriptRenderingTest` over specific unrelated files returned "No tests found"; the corrected unfiltered focused files and standalone transcript filter both passed.

## Commit hash

This commit: `feat: rebuild public episode page header and info line`.
