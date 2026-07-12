# TOOLS1 Implementation Plan - Admin Tools and Spotify Links Fetcher

Date: 2026-07-12

## Scope

Implement `prompts/pre-13-prompts/admin-tools-tools1-codex-prompt.md` as the
single session step.

No Composer changes. No push.

## Plan

1. Job 0 corrections:
   - Add the missing content-images download authorization regression tests.
   - Leave the MP2 gate-outcome gap unchanged because no real MP2 suite line was
     provided.
2. TL1 admin Tools page:
   - Add `App\Filament\Pages\AdminTools` under the central Site management map.
   - Add a Blade view with tabs. First tab is the Markdown editors tab; other
     tabs are not implemented, but the structure is ready.
   - Use Alpine/localStorage only for the dynamic editor list. Each editor has an
     optional translated title field and a Markdown textarea styled as an admin
     editor surface.
   - Add per-editor copy, selected-as-cells copy, and all-as-cells copy.
   - Add `App\Support\Spreadsheet\SpreadsheetCellClipboard` for one-column,
     multi-row clipboard payload quoting. Tests verify order, internal line
     breaks, quotes, and selected subsets.
3. SF1 Spotify links fetcher:
   - Add `App\Filament\Pages\SpotifyLinksFetcher` under the same Site management
     nav group as Importer Settings.
   - Page state includes paste input, optional CSV upload, entity mode,
     connection ID, batch cap, parsed links, warnings, result rows, and reduced
     mode indicators.
   - Add support classes:
     - parser for mixed paste/CSV input with dedupe and warnings;
     - CSV reader for first column or `link`/`url`/`id` column;
     - row DTO-ish arrays for editable Livewire state;
     - importer-header helper deriving header names from native importers;
     - CSV writer/factory with formula safety and day-first dates;
     - Spotify HTML-to-Markdown converter;
     - public oEmbed fallback client.
   - Extend the existing Spotify connector/client/lookup boundary with show
     lookup support.
   - Fetch sequentially inside the Livewire page using `ImporterThrottle` and
     per-row exception isolation.
   - For episode mode, include fetched show data and build a podcasts CSV for
     missing shows. Existing podcasts are matched by `media_metadata.spotify.show_id`
     or `external_id` where present.
   - For shows mode, fetch show rows and export podcasts CSV.
4. Tests:
   - Unit tests for spreadsheet clipboard payloads.
   - Unit tests for Spotify parser/dedupe/cap and HTML-to-Markdown conversion.
   - Feature/Livewire tests for Tools page rendering and admin auth.
   - Feature/Livewire tests for fetcher parsing, fake lookup/fallback, editable
     state, CSV header equality, missing-show CSV, throttle calls, per-row error
     isolation, admin auth, and bounded batch enforcement.
   - Add the IMG-B content-images download auth regression test.
5. Docs:
   - Update `docs/phase-02/current-project-state.md`.
   - Update `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md` with
     the TOOLS1 row and WB note that SF1 pulls WB7's Spotify links-list source
     forward as a standalone admin tool; WB2+ remain unchanged.
   - Add `docs/phase-02/admin-tools-tools1-handoff.md` with gate outcomes before
     commit, a `## Commit hash` section, and a numbered manual
     `## Local Front Check Report`.

## Final Gate Order

Per prompt and kickoff message:

1. Requirements sweep.
2. `vendor/bin/pint --test`.
3. `vendor/bin/filacheck`.
4. `npm run build`.
5. Full `php artisan test` last, once green on final code state.

If any file changes after the final gate starts, restart from Pint. Record every
run in the handoff before committing.

## Requirement Classification Targets

- Implemented: TL1 page, clipboard payload boundary, SF1 fetcher, parser,
  oEmbed fallback, CSV exports, show lookup, missing-show podcasts CSV, tests,
  current-state/ledger/handoff.
- Already existed: WB1 connection model/Spotify connector baseline, native
  importers, `ImporterThrottle`, IMG-B guarded route/controller behavior.
- Deferred by prompt: direct send-to-importer, image downloads, WB2+ recipe
  machinery, IE-1 relation modes, server persistence for Markdown editors.
- Not applicable: Composer changes, push, `filacheck --fix`.
- Blocked: none expected; live Spotify credential testing remains a manual local
  operator check.
