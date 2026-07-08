# Public Front v2 Step 10R-M4 Handoff

## Purpose

Step 10R-M4 makes public rendering consume the multi-transcriber and public transcription policy foundation added in M1-M3.

The goal was to keep cards compact, show only selected/effective transcription data on cards, expose aggregate/count attributes safely to templates, and make item pages show per-transcription transcriber context when multiple public transcriptions are enabled.

## What Was Implemented

- Public content-item cards now render transcription-backed transcribers from the selected/effective transcription.
- Card templates can render finite M4 attributes:
  - `content_item.transcribers`
  - `content_item.transcription_count`
  - `content_item.reading_time`
  - `content_item.effective_transcription_title`
  - `transcription.transcribers`
  - `transcription.reading_time`
  - `content_group.public_episode_count`
  - `content_group.transcription_count`
  - `content_group.total_reading_time`
  - `content_group.latest_transcription_date`
  - `content_group.transcriber_count`
  - `contributor.transcription_count`
  - `contributor.public_item_count`
- Contributor-context episode cards select the contributor-specific transcription when loaded, so contributor pages/previews can show a transcription title/transcribers that differs from the global effective transcription.
- Item page header renders effective transcription transcribers plus optional transcription-count metadata.
- Transcript viewer tabs show each transcription's ordered transcriber names.
- Podcast/group cards and podcast detail header render public episode count, total reading time, transcription count, distinct transcriber count, and latest transcription date where configured.
- Public item/group card presentation is prepared once per grid instead of resolving presenters inside each Blade card.

## Files Changed

- Settings/admin: `app/Filament/Pages/PublicContentSettings.php`, `database/settings/2026_07_08_000008_add_public_transcription_display_settings.php`
- Public pages/Livewire: `app/Filament/Public/Pages/ShowContentItem.php`, `app/Livewire/Public/ContentItemBrowser.php`, `app/Livewire/Public/ContentItemTranscriptViewer.php`, `app/Livewire/Public/ContributorContentItems.php`, `app/Livewire/Public/ContributorDirectory.php`, `app/Livewire/Public/TopTranscribersSection.php`
- Presenters/config: `app/Support/PublicContent/PublicContentCardOptions.php`, `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`, `app/Support/PublicFront/Cards/PublicContentGroupCardPresenter.php`, `app/Support/PublicFront/Cards/PublicContributorCardPresenter.php`, `app/Support/PublicFront/Cards/PublicFrontCardTemplateRegistry.php`, `app/Support/PublicFront/PublicFrontConfigRegistry.php`, `app/Support/PublicFront/PublicFrontConfigValidator.php`, `app/Support/PublicFront/Sections/PublicDisplaySectionResolver.php`
- Performance/eager loading: `app/Models/Transcription.php`, `app/Providers/AppServiceProvider.php`, admin item table/relation-manager eager loads
- Views/translations/tests/docs: public card/grid/page/viewer Blade files, `lang/en/*`, `lang/he/*`, focused public/admin tests, current-state/ledger/decision/performance/research docs

## Migrations And Schema

- Added Spatie Settings migration `2026_07_08_000008_add_public_transcription_display_settings.php`.
- No database table migration was added in M4.
- New finite settings keys:
  - `display_defaults.transcription_display`
  - `podcasts_page.group_page.transcription_display`
  - `contributors_page.directory.transcription_display`
  - `contributors_page.top_transcribers.transcription_display`
  - `contributors_page.page.transcription_display`
- Allowed values:
  - `effective_only`
  - `effective_plus_count`
- Default: `effective_plus_count`.

## Model Relationships

- No model relationship was added or removed in M4.
- `Transcription::authors()` remains the public transcriber source.
- `transcriptions.author_id` remains compatibility/primary storage.
- `ContentItem::authors()` and `Author::contentItems()` remain removed from M2.

## Removed Relationships Or Tables

- None in M4.
- `author_content_item` was already dropped in M2 and remains absent.

## Admin Behavior

- Public settings page includes the new `transcription_display` selects for global defaults, podcast detail item cards, contributor directory previews, top transcriber previews, and contributor pages.
- Admin content item table/relation-manager queries now eager-load `contentGroup` to satisfy non-production lazy-loading prevention.
- No import/export behavior changed in M4.

## Public Query And Policy Behavior

- `ShowContentItem` now uses `PublicContentItemQueries::base()` so item pages receive the same public transcription relations and aggregate values as listing surfaces.
- Cards follow D1: they do not render full multi-transcription lists.
- Under `all_published`, cards can show an optional count badge only when the template asks for `content_item.transcription_count`, the item has more than one public transcription, and `transcription_display` is `effective_plus_count`.
- Item-page multi-transcription tabs still depend on `transcription_policy.show_multiple_transcriptions_on_item_page`.
- Group aggregate values from M3 are now consumed by public rendering.

## Card Template And Rendering Behavior

- Content item presenter internal data now uses transcriber-accurate naming.
- Compatibility mapping keeps legacy `transcription.author_name` template parts working.
- Group and contributor template families gained aggregate aliases listed above.
- M4 intentionally does not implement labels, icons, or nested group parts; that remains Step 10R-M5.

## Performance Backlog Delta

- F3: M4 resolved repeated published-transcription list resolution with `#[Computed]`; P3 still owns parsed segment/render economy.
- F4: resolved with `TopTranscribersSection` computed contributors.
- F5: resolved by guarding no-op compatibility pivot sync; focused test verifies no `author_transcription` queries.
- F6: resolved with relation-first/memoized section target lookup.
- F8: resolved by enabling `Model::preventLazyLoading(! app()->isProduction())` and fixing exposed eager-load gaps.
- F10: resolved by rendering group aggregate attributes.
- F11: partially resolved by hoisting item/group card presentation and renaming the internal item-card data key; B4 owns options convergence.
- F14: resolved in the ledger.
- F15: scheduled for P2; M4 consumes aggregates where rendered, P2 should make unconsumed subselects opt-in.

Local evaluation query-count snapshot, tests do not depend on this data:

| Route | Pre-M4 baseline | After M4 snapshot |
|---|---:|---:|
| `/` | 24 | 24 |
| `/search` | 38 | 22 |
| `/podcasts` | not recorded | 10 |
| `/contributors` | 8-16 | 7 |
| `/podcasts/technology-in-hebrew` | 20 | 20 |
| `/items/technology-in-hebrew/ai-tools-for-editors` | 48 | 19 |
| `/contributors/yonatan-cohen` | 18 | 18 |

## Tests Added Or Updated

- Added `tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php`.
- Updated public item page/search/settings/admin tests for M4 defaults, tab behavior, and transcriber marker naming.
- Query-count harness covers homepage sections, `/search`, podcast detail, contributors, and item page using fixture-owned data.
- Tests explicitly set policy modes and do not rely on local seeded data or local settings.

## Security And Fallback Behavior

- No raw HTML, Blade path, CSS class, SVG, script, or unsafe URL is accepted from JSON settings.
- New settings are finite-token normalized by `PublicFrontConfigValidator`.
- Draft and future-dated transcriptions remain hidden in M4 tests.
- Lazy loading is prevented outside production so future public rendering gaps fail during development/test.

## Blueprint/Audit Deviations

- Added a settings migration for `transcription_display` because full tests proved registry defaults alone were insufficient for existing settings rows.
- Added admin item table/relation-manager eager loads because global lazy-loading prevention exposed an existing admin query gap.
- Labels/icons/grouped rows were not partially implemented, per D8.

## Effect On Later Mini-Steps

- Step 10R-M5 can build labels/icons/`part_group` on top of the expanded finite attribute map.
- Step 10R-M6 should mark C1 superseded unless a narrow cleanup remains.
- Step 10R-P1/P2/P3 are now inserted in the ledger after M6 and before B4.
- Step 10R-B4 must preserve transcription-backed transcriber display while converging card options.

## Open Questions

- Whether Yoni wants `effective_only` to be default for any card surface after manual review.
- Whether P2 should split aggregate subselect opt-in by page type or by explicit card-template attribute detection.
- Whether M5 should include full nested admin Builder UX for `part_group` now or ship storage/rendering first with limited admin UX.

## Quality Gate Summary

- `php artisan migrate` passed and applied the M4 settings migration.
- Focused public multi-transcription/media/search tests passed.
- Focused card/podcast/contributor/display-section suites passed.
- Focused settings/admin defaults tests passed.
- Full `php artisan test` passed: 254 tests, 2114 assertions.
- `vendor/bin/pint --dirty --format agent` passed after formatting.
- `vendor/bin/pint --test` passed.
- `vendor/bin/filacheck` passed with 0 issues.
- `npm run build` passed.
- `git diff --check` passed.

## Commit Hash

- This mini-step commit: `feat: render public transcribers and transcription aggregates`.
- The final commit hash is reported in the run output because this handoff is part of the commit and cannot self-reference its final hash without changing it.
