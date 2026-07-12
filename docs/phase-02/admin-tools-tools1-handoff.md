# Admin Tools TOOLS1 Handoff

Date: 2026-07-12

## Scope

Implemented `prompts/pre-13-prompts/admin-tools-tools1-codex-prompt.md` as the
single session step.

No Composer changes were made.

## Implemented

- Added the admin `Tools` / `כלים` page under `ניהול אתר`, via the central
  `AdminNavigationOrder` map at sort `335`.
- Added a tabbed Tools page structure. The first tab is a browser-local Markdown
  multi-editor backed only by Alpine/localStorage.
- Added dynamic add/remove Markdown editor rows, optional editor titles, per-editor
  markdown copy, selected copy-as-cells, and copy-all-as-cells.
- Added `SpreadsheetCellClipboard` as the testable PHP boundary for one-column
  spreadsheet clipboard payloads. The Alpine page mirrors the same quote-wrapping
  and quote-doubling algorithm.
- Added the admin `Spotify links fetcher` / `שליפת קישורי Spotify` page under
  `ניהול אתר`, via `AdminNavigationOrder` at sort `345`.
- Added mixed Spotify input parsing for episode/show URLs, Spotify URIs, and bare
  IDs, with dedupe, junk warnings, CSV upload support, and a 100-row max cap.
- Added sequential Livewire fetching for batches up to 100 rows. It uses
  `ImporterThrottle` for each row and isolates failures per row.
- Extended the existing Spotify connector/client/lookup path with show lookup and
  episode `show_id` metadata.
- Added credential-less public oEmbed reduced mode for rows where no connected
  Spotify connection is selected or credentialed fetch fails.
- Added app-owned Spotify HTML-to-Markdown conversion for paragraphs, line breaks,
  links, lists, simple emphasis, and entities.
- Added editable result rows and editable missing-show podcast rows.
- Added episodes and podcasts CSV download methods. CSV headers are derived from
  `ContentItemImporter::getColumns()` and `ContentGroupImporter::getColumns()`.
- Kept images URL-only; SF1 performs no image downloads.
- Added the missing IMG-B authorization regression: guests cannot download
  content-images export ZIP routes, and an authenticated non-owner token resolves
  to no file.
- Left the MP2 final gate gap unchanged because the kickoff message did not include
  a real MP2 suite line.
- Backfilled TS1 commit `0d17c2a` in current state and the mini-step ledger.

## Navigation Placement

- `AdminTools`: `AdminNavigationOrder::SITE_MANAGEMENT`, sort `335`, between
  Settings Backups and Importer Settings.
- `SpotifyLinksFetcher`: `AdminNavigationOrder::SITE_MANAGEMENT`, sort `345`,
  after Importer Settings.

This keeps both pages cheap for Yoni to re-place later by editing only the central
navigation map.

## Requirement Classification

- Implemented: Job 0.1 IMG-B auth test gap, Job 0.2 MP2 gap handling, TL1 Tools
  page, browser-local Markdown editor state, spreadsheet-cell clipboard payload
  boundary, SF1 input parser, CSV upload, batch cap, connection selector, oEmbed
  reduced mode, sequential fetch with throttle, show lookup, HTML-to-Markdown
  conversion, editable result rows, importer-shaped episode/podcast CSVs, per-row
  failure isolation, translations, tests, research/plan docs, current-state update,
  ledger row, WB note, and this handoff.
- Already existed: encrypted `ImportConnection` model, Spotify client-credentials
  connection baseline, `ImporterThrottle`, native `ContentGroupImporter` and
  `ContentItemImporter`, and the content-images download controller's owner-scoped
  path behavior.
- Deferred by prompt: direct send-to-importer, image downloads, WB2+ recipe
  machinery, IE-1 relation modes, server-side persistence for Markdown editors,
  and WB apply/rollback/dataset flows.
- Not applicable: Composer changes, remote push, `filacheck --fix`, raw iframe
  storage, public page changes, and direct database imports from SF1.
- Blocked: none. Live credential verification remains a manual operator check.

## Known Adaptation

The prompt refers to existing podcasts by external show ID, but
`content_groups` and `ContentGroupImporter` currently have no external-ID column.
The fetcher resolves existing podcasts by prior episode `media_metadata.show_id`
where available, then by exact content-group title. Missing shows still produce a
podcasts CSV row for the normal podcast importer.

## Files Changed

- Admin pages/navigation: `app/Filament/Pages/AdminTools.php`,
  `app/Filament/Pages/SpotifyLinksFetcher.php`,
  `app/Filament/Support/AdminNavigationOrder.php`,
  `resources/views/filament/pages/admin-tools.blade.php`,
  `resources/views/filament/pages/spotify-links-fetcher.blade.php`.
- Spotify/support boundaries: `app/Support/Media/EpisodeSpotifyLookup.php`,
  `app/Support/Importer/Contracts/SpotifyClient.php`,
  `app/Support/Importer/Spotify/SpotifyConnector.php`,
  `app/Support/Importer/Spotify/SpotifyHttpClient.php`,
  `app/Support/Importer/SpotifyLinks/*`,
  `app/Support/Spreadsheet/SpreadsheetCellClipboard.php`.
- Labels: `lang/en/admin.php`, `lang/he/admin.php`.
- Tests: `tests/Feature/AdminToolsTest.php`,
  `tests/Feature/AdminPhase02ResourcesTest.php`,
  `tests/Feature/ContentImagesExportTest.php`,
  `tests/Feature/ImporterWorkbenchConnectionsTest.php`.
- Docs: `docs/research/admin-tools/00-tools1-research.md`,
  `docs/research/admin-tools/01-tools1-implementation-plan.md`,
  `docs/phase-02/current-project-state.md`,
  `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`,
  `docs/phase-02/admin-tools-tools1-handoff.md`.

## Tests Added Or Updated

- Added `AdminToolsTest` coverage for spreadsheet-cell payload quoting, Spotify
  parser/dedupe/cap/CSV input, HTML-to-Markdown conversion, admin page rendering,
  guest blocking, fake credentialed fetches, missing-show podcasts CSV rows,
  editable state export, importer-derived CSV headers, oEmbed reduced mode,
  throttle calls, per-row error isolation, and max batch enforcement.
- Updated `ContentImagesExportTest` with the missing guest/non-owner ZIP download
  authorization regression.
- Updated central navigation coverage for `AdminTools` and `SpotifyLinksFetcher`.
- Updated the importer-workbench fake Spotify client for the new `fetchShow()`
  interface method.

## Commands Run

- Preflight: `git status --short --branch`; `git log --oneline -8`.
- Research/tools: Laravel Boost `application_info`, `database_schema`, and
  version-aware `search_docs`; FilamentExamples `search_examples` in focused and
  refined batches.
- Syntax: `php -l` on new/edited PHP pages, support classes, translations, and test
  files passed.
- Targeted run 1:
  `php artisan test --compact tests/Feature/AdminToolsTest.php tests/Feature/ContentImagesExportTest.php --filter='formats markdown|parses spotify|converts spotify|renders tools|fetches spotify|oembed|isolates per row|maximum fetch batch|blocks guests'`
  failed: 9 tests, 3 passed, 1 failure, 5 errors. Fixed page mount array handling
  and non-breaking-space Markdown normalization.
- Targeted run 2:
  `php artisan test --compact tests/Feature/AdminToolsTest.php tests/Feature/ContentImagesExportTest.php --filter='formats markdown|parses spotify|converts spotify|renders tools|fetches spotify|oembed|isolates per row|maximum fetch batch|blocks guests'`
  passed: 9 tests, 51 assertions.
- Targeted navigation:
  `php artisan test --compact tests/Feature/AdminPhase02ResourcesTest.php --filter='orders every registered admin navigation resource and page through the central map'`
  passed: 1 test, 43 assertions.
- Targeted importer connection:
  `php artisan test --compact tests/Feature/ImporterWorkbenchConnectionsTest.php --filter='fake spotify client|renders importer settings'`
  passed: 2 tests, 39 assertions.
- Targeted TOOLS1 file:
  `php artisan test --compact tests/Feature/AdminToolsTest.php`
  passed: 8 tests, 46 assertions.
- Formatting iteration:
  `vendor/bin/pint --dirty --format agent`
  fixed formatting in `AdminNavigationOrder.php`,
  `AdminPhase02ResourcesTest.php`, and `AdminToolsTest.php`.
- Post-format targeted confirmation:
  `php artisan test --compact tests/Feature/AdminToolsTest.php`
  passed: 8 tests, 46 assertions.
- Post-format navigation confirmation:
  `php artisan test --compact tests/Feature/AdminPhase02ResourcesTest.php --filter='orders every registered admin navigation resource and page through the central map'`
  passed: 1 test, 43 assertions.
- Post-format IMG-B auth confirmation:
  `php artisan test --compact tests/Feature/ContentImagesExportTest.php --filter='blocks guests'`
  passed: 1 test, 5 assertions.
- Final gate:
  - Requirements sweep passed: `git diff --check` clean; `git status --short`
    showed only TOOLS1/app/test/doc changes; no Composer, package-lock, or tracked
    build artifact changes.
  - `vendor/bin/pint --test` passed.
  - `vendor/bin/filacheck` passed with 0 issues.
  - `npm run build` passed.
  - `php artisan test` passed once on the final code state: 440 tests, 3,946
    assertions, 485.055s.

## Tooling Notes

- Laravel Boost was available and used before code changes. It reported PHP 8.4,
  Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Horizon 5.47.2, Pest 4.7.4,
  and Tailwind 4.3.2.
- FilamentExamples MCP exposed search/snippet access only. Searches covered custom
  pages, schema/table state, editable rows, downloads, CSV uploads, tabs, and
  navigation placement. No source/read/detail tool was available.

## Assumptions

- Enabled Spotify connections mean provider `spotify` with status `connected`.
- Sequential Livewire fetching is sufficient and simpler than a queue/polling table
  for the explicit 100-row cap.
- The Tools page localStorage key is browser-specific and intentionally does not
  sync across users/devices.
- The browser clipboard/localStorage behavior is validated through manual operator
  checks; the shared payload formatting boundary is covered by Pest.

## Deferred Issues

- Group-level external Spotify IDs remain a future schema/importer decision.
- Direct import handoff remains out of scope; SF1 downloads CSVs only.
- WB2+ run/step/dataset/recipe machinery remains pending.
- Image downloads remain IMG/WB7 scope, not SF1.

## Current Git Status Before Commit

```text
 M app/Filament/Support/AdminNavigationOrder.php
 M app/Support/Importer/Contracts/SpotifyClient.php
 M app/Support/Importer/Spotify/SpotifyConnector.php
 M app/Support/Importer/Spotify/SpotifyHttpClient.php
 M app/Support/Media/EpisodeSpotifyLookup.php
 M docs/phase-02/current-project-state.md
 M docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md
 M lang/en/admin.php
 M lang/he/admin.php
 M tests/Feature/AdminPhase02ResourcesTest.php
 M tests/Feature/ContentImagesExportTest.php
 M tests/Feature/ImporterWorkbenchConnectionsTest.php
?? app/Filament/Pages/AdminTools.php
?? app/Filament/Pages/SpotifyLinksFetcher.php
?? app/Support/Importer/SpotifyLinks/
?? app/Support/Spreadsheet/
?? docs/phase-02/admin-tools-tools1-handoff.md
?? docs/research/admin-tools/
?? resources/views/filament/pages/admin-tools.blade.php
?? resources/views/filament/pages/spotify-links-fetcher.blade.php
?? tests/Feature/AdminToolsTest.php
```

## Commit hash

Final TOOLS1 commit hash: `a6d6408 feat: add admin tools page and spotify links fetcher`.

## Local Front Check Report

1. MANUAL: Open `כלים` in the admin sidebar under `ניהול אתר`.
2. MANUAL: Add three Markdown editors.
3. MANUAL: Copy one editor and paste into a plain text target.
4. MANUAL: Copy all as cells and paste into Google Sheets; confirm three rows in
   one column and internal line breaks stay inside their cells.
5. MANUAL: Reload the page and confirm the editor content survived in the same
   browser.
6. MANUAL: Open the Spotify fetcher under `ניהול אתר`.
7. MANUAL: Paste a mixed list of five episode links plus junk; confirm junk is
   warned and five episode links are parsed.
8. MANUAL: Fetch with a connected Spotify connection; confirm the editable table
   fills, show names appear as title prefixes, and reduced mode is not shown.
9. MANUAL: Edit a title, download the episodes CSV, import it through the normal
   episodes importer, and confirm the episodes appear.
10. MANUAL: Download the podcasts CSV when missing shows were fetched and import it
    through the normal podcasts importer before retrying dependent episode rows.
11. MANUAL: Switch to shows mode, fetch a show link, and confirm the podcasts CSV
    contains the fetched show row.
12. MANUAL: Clear the Spotify connection and fetch again; confirm reduced mode is
    labeled and rows include only title/thumbnail-level metadata.
13. MANUAL: Check Hebrew RTL, light mode, and dark mode for both pages.
