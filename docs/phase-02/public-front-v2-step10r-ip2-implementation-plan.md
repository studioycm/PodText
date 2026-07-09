# Public Front v2 Step 10R-IP2 Implementation Plan

## 1. Selected mini-step and dependency verification

Selected mini-step: Step 10R-IP2 — Episode page header and info layout rebuild.

Dependencies verified:

- M5 is complete in local history at `aa7568c`.
- IP1 is complete in local history at `9d565d7`.
- Two local review-fix commits after IP1, `f6f1722` and `88e3815`, only fix M5 card row width/stretch behavior and do not start IP2 scope.
- Ledger first pending row is Step 10R-IP2 and it has been marked `in progress` for this run.

## 2. Current local repo evidence

Current local HEAD before app-code changes: `88e3815`.

Preflight:

- `git status --short --branch`: clean except the deliberate IP2 ledger `in progress` doc edit after selection; branch is ahead of `origin/main` by 2 local review-fix commits.
- Required prior commits are present in `git log --oneline --decorate -40`: `800218a`, `e813513`, `825004c`, `af9f399`, `aaddd95`, `2a5ff96`, `aa7568c`, `9d565d7`.
- `php artisan migrate:status` confirms completed migrations are run, including the author/transcription pivot, `author_content_item` drop, transcription policy/display settings, and IP1 `item_page` settings migration.
- Public routes exist for `/items`, `/podcasts`, `/contributors`, `/search`, `/about`, and admin settings routes.

No contradiction found:

- Migrations for completed steps are present.
- The completed `author_content_item` drop migration is run.
- No M6/P/B4/C/9F/Step11/Prompt13 work is started outside the ledger.
- Step 11 and Prompt 13 are not selected.

## 3. Files inspected

- `AGENTS.md`
- `.ai/guidelines/tooling-quality.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-transcription-display-decisions.md`
- `docs/phase-02/public-front-v2-performance-efficiency-audit.md`
- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/phase-02/ai-development-lessons.md`
- M1-M4, B1-B3, A1-A2 handoffs and M4 implementation plan
- IP1 handoff/plan/research
- HF1 transcript rendering plan
- Public item page media parser blueprint
- Transcript viewer future plan
- `app/Filament/Public/Pages/ShowContentItem.php`
- `resources/views/filament/public/pages/show-content-item.blade.php`
- `app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Support/PublicFront/PublicFrontRenderContext.php`
- `app/Support/PublicContent/PublicContentItemQueries.php`
- `app/Support/PublicContent/PublicTranscriptionSelector.php`
- `app/Support/PublicFront/Cards/PublicContentItemCardPresenter.php`
- `resources/views/components/public/card-part-shell.blade.php`
- `resources/views/components/public/content-group-badge.blade.php`
- `tests/Feature/PublicItemPageMediaParserTest.php`
- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- `tests/Feature/PublicPodcastsGroupsUxTest.php`
- `tests/Feature/PublicContributorsTopTranscribersUxTest.php`
- `lang/en/public.php`
- `lang/he/public.php`
- `lang/en/admin.php`
- `lang/he/admin.php`

## 4. Laravel Boost findings

Boost tools used:

- `application_info`
- `database_schema`
- `search_docs`

Findings:

- Installed package versions are Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4.
- SQLite is the local/test database; IP2 changes do not require dialect-specific SQL.
- `content_items.published_at`, `content_items.original_published_at`, `content_items.external_thumbnail_url`, `content_groups.cover_path`, and `transcriptions.published_at` exist.
- Filament settings/repeater docs support nested array fields through SettingsPage form state.
- Eloquent docs reinforce using eager-loaded relations and avoiding fallback relationship queries under non-production lazy-loading prevention.
- Pest/Laravel docs support rendered-output HTTP assertions for public page behavior.

## 5. FilamentExamples MCP findings and access level

Access level: search/snippet only through `search_examples`; no source/detail tool was available.

Query batches:

- `public detail page`, `media sidebar page`, `SettingsPage tabs`, `settings repeater`
- `badge icon label`, `metadata row renderer`, `public card layout`, `custom page view data`
- `getViewData public page`, `nested settings repeater fields`, `metadata badge links`, `public Blade card links`

Findings:

- Use tabbed/sectioned SettingsPage forms with nested finite fields.
- Ordered repeaters should be reorderable, collapsed, and labeled by the row's selected field.
- Public detail pages should render prepared display arrays and fixed class maps.
- Persist semantic tokens, not raw classes or component names.

## 6. Exact requirement IDs owned this run

This run lands:

- R1 page part: both publication dates available on the episode page.
- R11: categories and tags above the description, as links, on the episode info line.
- R12: category/tag/podcast badges-labels-text on cards and pages are links to their pages.
- R13 render part: episode info badges consume finite icon, size, and color tokens.
- R14: no standalone episode/transcription type labels on the page.
- R15: breadcrumbs show/hide setting.
- R16: page image uses episode image, else podcast cover.
- R17: big episode title plus linked podcast identity as badge/text/hidden with finite color/icon tokens.
- R18: ordered info fields under the title with label and icon settings.

Out of this run:

- IP3 share/action menu/reading controls.
- M6 stabilization.
- P/B4/C/9F/Step11/Prompt13.

## 7. Schema/settings reality check

The existing IP1 `item_page` settings group is present as `public_content.item_page`.

`PublicContentSettings` already has an `array $item_page` property.

`PublicFrontRenderContext::itemPage()` already exposes the validated item-page config.

No schema migration is required. A Spatie settings migration is required to add the IP2 nested keys to existing settings rows.

## 8. Data mapping check

- Published on site: `content_items.published_at`.
- Originally published: `content_items.original_published_at`.
- Transcription date: selected/effective published transcription `published_at`.
- Page image: `content_items.external_thumbnail_url`, falling back to `content_groups.cover_path`.
- Dates render day-first as `d/m/Y` in `Asia/Jerusalem`.

## 9. Current rendering/query/settings gaps

- The item page does not consume `item_page` config beyond IP1 defaults.
- Breadcrumbs are always shown.
- Header metadata is hard-coded and cannot be ordered.
- Only `published_at` is shown in the page metadata today; original publication is not shown in the header.
- Categories/tags are linked but rendered in a separate taxonomy block instead of the ordered info line.
- Podcast identity is not configurable as badge/text/hidden.
- Episode image fallback to podcast cover is not presented in the header.

## 10. Exact JSON shape/defaults/validator changes

Extend `item_page` defaults:

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

Finite values:

- `podcast_identity.mode`: `badge|text|hidden`
- `podcast_identity.color`: IP1 badge color tokens
- `podcast_identity.icon`: M5 icon keys
- `podcast_identity.icon_position`: M5 icon-position tokens
- `info_fields.*.field`: `site_published_date|original_published_date|transcription_date|duration|transcribers|reading_time|word_count|transcription_count|categories|tags`
- `info_fields.*.label_mode`: IP1 label modes
- `info_fields.*.label_override`: nullable plain text, max 80
- `info_fields.*.icon`: M5 icon keys
- `info_fields.*.icon_position`: M5 icon-position tokens
- `info_fields.*.size`: IP1 size tokens
- `info_fields.*.color`: IP1 color tokens

Date fields will use the IP1 `item_page.dates.*` label/icon settings at render time so the page reflects the date settings; the ordered `info_fields` rows provide position plus size/color for those date badges.

## 11. Exact files to change

Planned app files:

- `app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Filament/Public/Pages/ShowContentItem.php`
- `resources/views/filament/public/pages/show-content-item.blade.php`
- `database/settings/2026_07_09_000001_add_public_item_page_header_settings.php`

Planned test files:

- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- `tests/Feature/PublicItemPageMediaParserTest.php`
- `tests/Feature/PublicPodcastsGroupsUxTest.php`
- `tests/Feature/PublicContributorsTopTranscribersUxTest.php`

Planned translation/docs files:

- `lang/en/admin.php`
- `lang/he/admin.php`
- `lang/en/public.php`
- `lang/he/public.php`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-ip2-handoff.md`
- this plan and the IP2 research note

## 12. Tests to add/update

Focused tests:

- Settings defaults include new IP2 keys.
- Settings migration deep-merges the new IP2 keys into existing `item_page` rows.
- Invalid breadcrumb, podcast identity, info field, icon, size, color, label, and raw class values normalize safely.
- Settings page can save the new fields.
- Public item page hides breadcrumbs when configured.
- Public item page displays both site/original publication dates and transcription date through the IP1 date settings.
- Info-field repeater order is respected.
- Podcast identity badge/text links to the podcast page.
- Episode image uses item thumbnail first and group cover fallback.
- Categories and tags are links in the info line above the description.
- Link audit covers item cards, group cards, contributor item grids, episode page, and podcast/group page.
- Bounded query-count harness remains green.

Full gate remains required after focused tests.

## 13. Translation keys to add/update

Add/update both `lang/en` and `lang/he`:

- Admin section labels/helpers for episode header and info fields.
- Admin fields/helpers for breadcrumbs, podcast identity mode/color/icon/icon position, info field field/label/icon/size/color.
- Admin option labels for podcast identity modes and info field keys.
- Public labels for info fields where no existing translation key is specific enough.

## 14. Performance implications

- The page must use `PublicContentItemQueries::base()` eager-loaded relations.
- The Blade view should not call relationship queries for categories, tags, content group, or transcribers.
- No cache/P1 work is included.
- No queued work is introduced.
- The bounded query-count harness must stay within its existing limit.

## 15. Out-of-scope list

- IP3 share section movement, transcript actions menu, fullscreen, font controls, and player visibility controls.
- Stored transcript segment rendering and P3 economy work.
- Card-options convergence in B4.
- Grid semantic tokens in C2.
- Homepage rich columns/footer in 9F.
- Seeders/demo data in Step 11.
- Dashboard metrics in Prompt 13.
- New models, public Filament Tables, or user exposure.

## 16. Stop conditions

Stop before app-code changes if:

- The ledger no longer has IP2 as the selected in-progress mini-step.
- New app-code dirt appears that is unrelated to IP2.
- A required migration/setting from IP1 or earlier disappears.
- `item_page` settings cannot be extended without replacing existing IP1 structure.
- Any implementation path requires raw CSS/classes/HTML/SVG/component names in JSON.
- Any implementation path requires starting IP3, B4, 9F, Step 11, or Prompt 13.
