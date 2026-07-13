# Fetcher Workspace FIX1 Handoff

Date: 2026-07-13

## Scope

Executed only `prompts/pre-13-prompts/fetcher-workspace-fix1-codex-prompt.md`.

No Composer or npm dependency changes, no importer relaxation, no live-network
tests, no Playwright/browser automation, and no push.

## Commit hash

Pending implementation commit.

## Requirement Classification

- Implemented: fetcher CSV preassigned reference keys, existing podcast/episode
  resolution, podcast-first helper text, direct import from fetched rows, row
  outcomes and edit links, workspace Spotify options modal, matched-podcast
  linking, slug/prefix controls, shared publish-date autofill, trusted LTR HTML
  code editor fields, raw HTML verbatim persistence/rendering, rich Spotify HTML
  description-to-Markdown proof, settings cache `.env.example` flags, settings
  save invalidation coverage, and HTTP fixture discipline.
- Already existed: strict native Filament importers, `ContentGroupImporter`
  creation with supplied `reference_key`, Spatie settings cache
  `SETTINGS_CACHE_ENABLED` support in `config/settings.php`, the
  `SettingsSaved` public-front cache listener, maintenance raw override routing,
  URL-only media storage, and the standalone Spotify links fetcher page.
- Deferred by prompt: per-row cherry-picking for direct import, Workbench WB2+
  machinery, remote media downloads, importer package ZIP flows, analytics,
  browser automation, and any Composer/npm package changes.
- Not applicable: public result-card behavior, custom CSV controllers, publishing
  imported records, and relaxing failed-row behavior for missing relationship
  references.
- Blocked: none.

## Files Changed

- Fetcher/direct import:
  `app/Filament/Pages/SpotifyLinksFetcher.php`,
  `app/Support/Importer/SpotifyLinks/SpotifyLinksImportResolver.php`,
  `app/Support/Importer/SpotifyLinks/SpotifyLinksDirectImporter.php`,
  `app/Support/Importer/SpotifyLinks/SpotifyLinksImportSummary.php`,
  `resources/views/filament/pages/spotify-links-fetcher.blade.php`.
- Workspace/forms:
  `app/Filament/Resources/ContentItems/Schemas/EpisodeWorkspaceForm.php`,
  `app/Filament/Resources/ContentItems/Schemas/ContentItemForm.php`,
  `app/Filament/Resources/ContentGroups/Schemas/ContentGroupForm.php`,
  `app/Filament/Resources/Transcriptions/Schemas/TranscriptionForm.php`,
  `app/Filament/Resources/ContentItems/RelationManagers/TranscriptionsRelationManager.php`,
  `app/Filament/Resources/Support/RelationshipOptionForms.php`,
  `app/Filament/Forms/Components/PublicationStatusSelect.php`,
  `app/Support/Publication/PublicationDateAutofill.php`,
  `app/Filament/Forms/Components/TrustedHtmlCodeEditor.php`,
  `app/Filament/Pages/PublicContentSettings.php`.
- Raw HTML/media/settings:
  `app/Support/Media/ContentItemMediaRules.php`,
  `resources/views/components/public/media-embed.blade.php`,
  `.env.example`.
- Translations:
  `lang/en/admin.php`, `lang/he/admin.php`.
- Tests and fixtures:
  `tests/TestCase.php`,
  `tests/Feature/FetcherWorkspaceFix1Test.php`,
  `tests/Unit/PublicationDateAutofillTest.php`,
  `tests/Feature/EpisodeWorkspaceTest.php`,
  `tests/Feature/SpotifyFetcherFetch1Test.php`,
  `tests/Feature/AdminToolsTest.php`,
  `tests/Feature/ContentImagesExportTest.php`,
  `tests/Feature/PublicMaintenanceModeTest.php`,
  `tests/Feature/PublicFrontConfigCacheTest.php`,
  `tests/Fixtures/spotify-fetcher/*`,
  `tests/Fixtures/content-images/*`.
- Docs:
  `.ai/guidelines/media-embeds.md`,
  `docs/phase-02/ai-development-lessons.md`,
  `docs/phase-02/current-project-state.md`,
  `docs/phase-02/media-embed-spec.md`,
  `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`,
  `docs/phase-02/spotify-fetcher-fetch1-handoff.md`,
  `docs/research/fetcher-workspace/00-fix1-research.md`,
  `docs/research/fetcher-workspace/00-fix1-implementation-plan.md`,
  `docs/phase-02/fetcher-workspace-fix1-handoff.md`.

## Tests Added Or Updated

- Added fetcher-to-native-importer round-trip coverage proving podcast CSV then
  episode CSV imports cleanly, second import updates instead of duplicating, and
  existing groups/episodes reuse real reference keys.
- Added direct import coverage for creating draft podcasts/episodes, linking an
  existing podcast, skipping an existing episode, reduced-mode sparse rows, row
  failure isolation, and guest/admin page access.
- Added unit coverage for publish-date autofill and feature coverage for the
  workspace item form plus a transcription form.
- Updated workspace Spotify tests for modal defaults, option paths,
  matched-podcast fill, slug/prefix behavior, prefix clear, raw HTML editor
  rendering, and byte-exact `embed_html` save.
- Updated Spotify fetcher tests for committed HTTP fixtures, stray-request
  prevention, and rich HTML description conversion to Markdown in result, CSV,
  and workspace fill surfaces.
- Updated admin tools and content-image HTTP tests to use
  `Http::preventStrayRequests()` and committed fixtures.
- Added settings cache invalidation coverage for a saved Spatie settings object.

## Local Front Check Report

1. Open the admin Spotify links fetcher without Spotify credentials, paste a
   Spotify episode link, click fetch, and expect populated episode rows with
   reference keys plus a podcast CSV row.
2. Click the podcast CSV download, import it through the admin content-group
   importer, then click the episode CSV download and import it through the
   admin content-item importer; expect both imports to finish cleanly.
3. Re-import the same podcast CSV and episode CSV in the same order, and expect
   updates/no duplicates rather than new podcast or episode records.
4. Fetch a fresh Spotify link, click Direct import, review the summary counts,
   confirm, and expect draft podcast/episode records with linked group and row
   outcomes in the results table.
5. Fetch or paste a Spotify episode that belongs to an existing podcast, open
   the episode workspace Spotify suffix action, keep "link matched podcast" on,
   confirm, and expect the podcast select to be set.
6. In the same workspace modal, turn slug fill off, confirm, and expect an empty
   manual slug field to stay empty; repeat with slug fill on and expect the slug
   to fill from the title.
7. Enter a title prefix in the workspace, click the clear suffix action, and
   expect the prefix field to empty without changing the title.
8. Set an item or transcription status to published while `published_at` is
   empty, and expect the publication date to fill automatically.
9. Set an item or transcription status to published when `published_at` already
   has a value, and expect that existing value to remain unchanged.
10. Edit item `embed_html` and maintenance raw HTML override in the new editor,
    confirm the editor is LTR/monospace, save a full HTML/script/iframe sample,
    and expect the saved/rendered HTML to stay verbatim.
11. Fetch an episode whose Spotify description includes links, bold text, and
    paragraphs, expand/view the description cell, and expect raw Markdown syntax
    such as `**bold**` and `[link](https://...)` to be visible.

## Commands Run

- Preflight:
  `git status --short --branch`; `git log --oneline -5`; full read of
  `docs/phase-02/ai-development-lessons.md`.
- Research/tools:
  Laravel Boost `application_info`; Laravel Boost `search_docs`;
  FilamentExamples `search_examples` across short query batches; local source
  inspection with `find`, `grep`, `sed`, `nl`, and `git`.
- Syntax:
  `php -l` on changed fetcher/support/form/test files passed.
- Targeted tests:
  `php artisan test --compact tests/Unit/PublicationDateAutofillTest.php tests/Feature/FetcherWorkspaceFix1Test.php`
  passed 5 tests, 31 assertions;
  `php artisan test --compact tests/Feature/EpisodeWorkspaceTest.php tests/Feature/SpotifyFetcherFetch1Test.php tests/Feature/AdminToolsTest.php tests/Feature/ContentImagesExportTest.php tests/Feature/PublicMaintenanceModeTest.php tests/Feature/PublicFrontConfigCacheTest.php`
  passed 52 tests, 394 assertions.
- Requirements sweep:
  `git diff --check` passed; dependency diff for Composer/npm lock/package
  files was empty; every `Http::fake()` occurrence was paired with
  `Http::preventStrayRequests()`; edited translation files had no duplicate
  literal keys; `git status --short --branch` showed only expected FIX1
  app/test/doc/fixture changes.
- Final gate:
  initial `vendor/bin/pint --test` failed on formatting/import-order issues;
  `vendor/bin/pint` applied mechanical formatting fixes; restarted
  `vendor/bin/pint --test` passed; initial `vendor/bin/filacheck` failed on a
  deprecated `Placeholder` in the new workspace modal; replaced it with
  `TextEntry::make()->state(...)` and restarted the gate from Pint;
  final `vendor/bin/pint --test` passed; final `vendor/bin/filacheck` passed
  with 0 issues; `npm run build` passed; full `php artisan test` passed 468
  tests, 4,152 assertions, 342.346 seconds.

## Tooling Notes

- Laravel Boost was available and used before code changes. It reported PHP 8.4,
  Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and Tailwind CSS
  4.3.2.
- FilamentExamples MCP exposed `search_examples` only. No source/read/detail
  tool was available, so research notes distinguish search-only snippets from
  source-level guidance.
- The direct import path uses a transaction per row. That matches the fetched
  table's per-row outcome model and keeps a failed row from aborting already
  imported podcast/episode rows.

## Settings Cache Rider

`SETTINGS_CACHE_ENABLED=false` and `SETTINGS_CACHE_MEMO=false` are now in
`.env.example` for local safety. Production may enable
`SETTINGS_CACHE_ENABLED=true` after deploy if persistent Spatie settings caching
is desired; the save path is covered by the existing `SettingsSaved` listener
and by a test proving a saved settings object invalidates the cached read.

## Assumptions

- Existing podcast matching by Spotify show ID depends on prior items carrying
  `media_metadata->show_id`; title fallback remains a secondary compatibility
  path.
- Direct import v1 intentionally creates/links/skips only. It does not update
  existing episodes, because the prompt required skipped existing episodes for
  this action.
- `ContentItemImporter` has no `title_prefix` mapping. CSV import stays limited
  to the existing strict importer schema; direct import fills `title_prefix`
  when fetch data has it.
- The shared test bootstrap now forces SQLite in-memory, array cache/session,
  and sync queue after application refresh so local `.env` settings cannot make
  tests touch MySQL/Redis or enqueue Filament database notifications.

## Deferred Issues

- Manual browser review remains for Yoni; this run did not use Playwright or a
  browser.
- Direct import row cherry-picking, importer-workbench source machinery, and
  media downloads remain future scope.
- Prompt 13 remains not started.

## Current Git Status Before Commits

Before the implementation commit, `git status --short --branch` showed
`main...origin/main [ahead 1]` with expected FIX1 app/test/doc/fixture changes
only; no Composer, lockfile, package, push, or remote changes.
