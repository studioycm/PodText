# Public Front v2 10R-IP3 Handoff

## Purpose

Step 10R-IP3 implements the transcript-area UX requirements after IP1/IP2: share placement, transcript details, an optional transcript actions menu, local reading controls, fullscreen reading mode, and player visibility controls.

## What was implemented

- Added `item_page.show_transcript_actions_menu` with a default of `false`.
- Added a Spatie settings migration to backfill the new `item_page` key.
- Added an Episode page admin toggle for the transcript actions menu.
- Moved the public item share block above the media player.
- Added a page-level Alpine reading shell for fullscreen mode and player/media-column visibility.
- Added transcript font-size increase/decrease/reset controls, stored only in `localStorage`.
- Replaced the standalone transcript timestamp/speaker/copy row with an optional Blade/Alpine dropdown menu.
- Added a transcript details row above the transcript body with title, read time, word count, selected-transcription publish date, and transcriber links.
- Rendered the selected transcription publish date through the existing safe card-part shell so IP1 label/icon settings apply.

## Requirement IDs landed

Landed:

- R7: share section renders above the player.
- R8: standalone share/copy action above the transcript is absent by default.
- R9: transcript actions are consolidated into an app-owned Blade/Alpine dropdown menu.
- R10: actions menu is hidden behind `item_page.show_transcript_actions_menu`, default `false`.
- R19: transcript details row renders title, read time, word count, publish date, transcribers, and optional menu trigger.
- R20: font size increase/decrease/reset controls render and persist through browser `localStorage`.
- R21: fullscreen reading mode renders transcript and player inside a page-level full-viewport layer.
- R22: player/media column hide/show toggle renders and persists through browser `localStorage`.

Remaining:

- R1-R6 and R13 token foundation landed in IP1.
- R1 page part and R11-R18 landed in IP2.
- M6 will verify R1-R23 together before closing the arc.

## Finding IDs resolved

No F findings were resolved by IP3.

F1-F3, F7, F11-F13, and F15 remain scheduled for their owning P/B4/C2 steps.

## Files changed

- `app/Filament/Pages/PublicContentSettings.php`
- `app/Livewire/Public/ContentItemTranscriptViewer.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `database/settings/2026_07_09_000003_add_public_item_page_transcript_actions_setting.php`
- `resources/views/filament/public/pages/show-content-item.blade.php`
- `resources/views/livewire/public/content-item-transcript-viewer.blade.php`
- `lang/en/admin.php`
- `lang/en/public.php`
- `lang/he/admin.php`
- `lang/he/public.php`
- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
- `tests/Feature/PublicItemPageMediaParserTest.php`
- `docs/research/public-front-v2/18-step10r-ip3-mcp-research.md`
- `docs/phase-02/public-front-v2-step10r-ip3-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10r-ip3-handoff.md`
- `docs/phase-02/current-project-state.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`

## Migrations/settings/schema changes

- Added settings migration `2026_07_09_000003_add_public_item_page_transcript_actions_setting`.
- No database table schema changed.
- Local `php artisan migrate` ran the new settings migration successfully.

## Settings keys and defaults

Extended `item_page`:

```json
{
  "show_transcript_actions_menu": false
}
```

Browser reading preferences are not stored in settings. The public viewer uses localStorage keys:

- `podtext.transcript.fontStep`
- `podtext.itemPage.playerHidden`
- existing `podtext.showTimestamps`
- existing `podtext.showSpeakers`

## Registry/validator/render-context changes

- `PublicFrontConfigRegistry::defaults()` includes `item_page.show_transcript_actions_menu`.
- `PublicFrontConfigValidator::normalizeItemPage()` allows and normalizes the key as a boolean.
- Invalid values fall back to `false` and report `item_page.show_transcript_actions_menu`.
- `PublicFrontRenderContext::itemPage()` is unchanged and returns the extended normalized group.

## Public rendering behavior

- The share block renders above `<x-public.media-embed>`.
- The transcript details row renders above tabs/body.
- Timestamp, speaker, copy-link, font-size, fullscreen, and player controls render only inside the optional actions menu.
- The actions menu is absent by default.
- When the menu is enabled, controls dispatch Alpine/browser events and do not write to Livewire/server state.
- The selected transcription publish date uses IP1 `item_page.dates.transcription_date` label/icon settings and `dd/mm/yyyy` `Asia/Jerusalem` formatting.
- Fullscreen mode is layout-only; no player/transcript sync was added.

## Admin behavior

- The Episode page tab now includes a collapsible Transcript controls section.
- The section contains `item_page.show_transcript_actions_menu`.
- The toggle defaults to off.

## Query/performance behavior

- No new queries were added to card/listing surfaces.
- `ContentItemTranscriptViewer` still resolves public transcriptions through the existing computed selector.
- The details row uses the already-selected transcription and eager-loaded authors.
- Public rendering remains synchronous.
- No cache/P1 work, derived transcript/P3 work, or queued work was added.

## Translation keys added/updated

Added in `lang/en/admin.php` and `lang/he/admin.php`:

- `admin.sections.public_front_item_page_transcript_controls`
- `admin.descriptions.public_front_item_page_transcript_controls`
- `admin.fields.item_page_show_transcript_actions_menu`
- `admin.helpers.item_page_show_transcript_actions_menu`

Added in `lang/en/public.php` and `lang/he/public.php`:

- `public.viewer.actions`
- `public.viewer.decrease_font`
- `public.viewer.fullscreen`
- `public.viewer.hide_player`
- `public.viewer.increase_font`
- `public.viewer.reset_font`
- `public.viewer.show_player`

## Tests added/updated/renamed

Updated:

- `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php`
  - settings migration backfill for the IP3 key;
  - unsafe boolean normalization;
  - settings-page save path;
  - translation-key coverage.
- `tests/Feature/PublicItemPageMediaParserTest.php`
  - share-before-player order;
  - transcript details row;
  - default-hidden actions menu;
  - enabled actions menu and reading controls;
  - localStorage/event markers;
  - fallback transcript control assertions.

No tests were renamed.

## Tests changed because old assertions intentionally moved/changed

- The previous timestamp/speaker/copy transcript buttons no longer render as a standalone row above the transcript.
- Tests now assert those controls are absent by default and present only inside the enabled actions menu.
- Share remains on the page but now renders above the media player.

## Security/fallback behavior

- No raw HTML, classes, SVG, component names, or icon class strings are stored in settings.
- The new setting is boolean-normalized through the public-front validator.
- Transcript publish date icon rendering uses finite M5 icon resolver keys.
- Labels render as escaped Blade text.
- Markdown transcript rendering remains on the HF1 safe renderer path.
- Browser preferences are local-only and do not affect public visibility or server-side selection.

## Bounded query-count harness result

Focused run passed:

```bash
php artisan test tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php
```

Result: 7 passed, 64 assertions.

## Impact on later mini-steps

- M6 should verify R1-R23 and mark C1 final status.
- P3 still owns derived transcript segments/render economy.
- B4 must preserve M5/IP1/IP2/IP3 card/page behavior.
- Prompt 14 future viewer/studio work remains out of scope.

## Open issues / follow-up decisions

- Full visual/browser review should verify Alpine fullscreen and player hide/show behavior in Hebrew RTL, light mode, and dark mode.
- The actions menu default is intentionally hidden; enable it in admin to review the controls.

## Quality gate summary

Passed:

```bash
vendor/bin/pint --dirty --format agent
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
git diff --check
```

- `php artisan test`: 284 passed, 2614 assertions.
- `vendor/bin/filacheck`: passed, 0 issues.
- `npm run build`: passed.
- `git diff --check`: passed.

## Commit hash

This commit: `feat: add transcript reading controls and actions menu`. The concrete hash is recorded in the final run report because amending the commit changes the self-reference.
