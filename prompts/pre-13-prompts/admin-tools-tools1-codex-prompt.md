# Codex Prompt — TOOLS1: Admin Tools Page (Markdown Multi-Editor) + Spotify Links Fetcher

Work in the current local clone of `studioycm/PodText`.

ONE merged implementation run (the TL1 + SF1 scope). Standing runner rules:
research note + implementation plan docs BEFORE code, no push unless asked,
no `filacheck --fix`, fixture-owned tests, en+he translations, RTL-safe UI,
NO Composer changes. The handoff is a COMMITTED MARKDOWN FILE
(`docs/phase-02/admin-tools-tools1-handoff.md`) whose gate outcomes are
written into the file AFTER the gate passes and BEFORE the commit, ending
with `## Commit hash` and a numbered MANUAL `## Local Front Check Report`.
Backfill TS1's commit hash `0d17c2a` per the standing rule.

FINAL GATE ORDER (standing): requirements sweep → `vendor/bin/pint --test` →
`vendor/bin/filacheck` → `npm run build` → FULL `php artisan test` LAST
(once = once GREEN on final state; any later change re-enters from Pint;
record every run; ~8 quiet minutes per run — never interrupt/parallelize).

## Preflight

```bash
git status --short --branch
git log --oneline -4
```

Clean tree; TS1 `0d17c2a` expected at or near HEAD.

## What exists (verified — build on it, do not rebuild)

- `App\Support\Media\EpisodeSpotifyLookup` (EP1): normalizes Spotify episode
  URL/URI/ID input, selects an enabled Spotify `ImportConnection`, returns
  normalized form-fill fields; `SpotifyHttpClient` already fetches
  title/show/duration/release/thumbnail and EP1 expanded description support.
- WB1's `ImporterThrottle` and the Spotify client-credentials connection.
- Native Filament importers (`ContentGroupImporter`, `ContentItemImporter`)
  with their column sets — the sanctioned import path (SF1 PRODUCES CSVs for
  them; it never imports directly).
- NAV1's central `AdminNavigationOrder` map — NAV1 explicitly deferred tools
  placement to this run.

## Job 0 — carried corrections

1. IMG-B audit gap: add the missing test — a GUEST requesting the
   content-images export download route is blocked (403/redirect), an
   authenticated non-owner token mismatch yields no file.
2. MP2 gate-outcome backfill: if the kickoff message provides the MP2 final
   suite line, stamp it into `docs/phase-02/maintenance-form-mp2-handoff.md`
   replacing the recorded gap; if not provided, leave the gap line as is.

## Job 1 — TL1: the tools page (markdown multi-editor)

- New admin page "כלים" / "Tools" with a TABS layout (first tab: markdown
  editors; structure ready for future tabs). Navigation: default under
  ניהול אתר via the central map — record the placement in the handoff for
  Yoni's cheap re-placement.
- Markdown editors tab: a dynamic list of MarkdownEditor instances — add
  another / remove, each with a translated title field (optional label per
  editor). NO server persistence: state lives in browser localStorage only
  (Alpine), surviving reloads on the same browser; a translated hint says so.
- Copy actions (Alpine clipboard):
  - per-editor "copy markdown";
  - multi-select checkboxes + "copy selected as cells";
  - "copy all as cells".
  "As cells" = ONE clipboard payload that pastes into Excel/Google Sheets as
  N ROWS in ONE COLUMN (one row per editor, in order): rows separated by
  newlines, each cell quoted per spreadsheet clipboard rules so INTERNAL line
  breaks stay inside one cell (double-quote wrapping with quote-doubling).
  Build the payload in ONE testable boundary (a small JS function mirrored by
  a PHP helper used in tests, or a server helper Alpine calls — pick the
  simplest testable shape and record it).
- Tests: payload formatting (N editors → N rows one column; internal newlines
  and quotes survive; selected-subset order preserved); page renders with
  translated tabs; RTL-safe markers where practical.

## Job 2 — SF1: Spotify links fetcher

A new admin page under the SAME nav group as Importer Settings (default
ניהול אתר per NAV1 — record it):

- INPUT: a paste box accepting a mixed list (auto-split on commas, newlines,
  whitespace; recognizes open.spotify.com episode/show URLs, spotify: URIs,
  bare IDs; dedupes; ignores junk with a per-line warning) AND a CSV upload
  alternative (first column or a column named link/url/id). Entity mode
  select: episodes | shows; in episodes mode, missing podcasts (shows not yet
  existing as content groups by external id) get their show data fetched too.
- Batch cap: default 25, user-raisable to 100 MAX (translated helper text).
- CONNECTION: select an enabled Spotify `ImportConnection` (WB1). If none
  exists or fetch is chosen without credentials, run the CREDENTIAL-LESS
  fallback via Spotify's public oEmbed endpoint
  (https://open.spotify.com/oembed?url=...) which yields title + thumbnail
  only — clearly labeled as reduced mode in the UI and in each row.
- FETCH: queued or chunked-live (research the cheapest reliable shape for up
  to 100 lookups with `ImporterThrottle`; a queued job with progressive table
  updates is acceptable, so is sequential chunking in Livewire — cite the
  choice). Reuse/extend `EpisodeSpotifyLookup` (add show lookup to the same
  service; NO composer changes). `html_description` converts to Markdown
  preserving line breaks, links, and lists — a minimal app-owned converter
  for the subset Spotify emits, unit-tested; no new dependencies.
- RESULTS: an editable table (Livewire) — title, title_prefix (from show
  name), description (markdown), duration, release date, external id,
  thumbnail URL, show name/id, per-row status (fetched / reduced / error with
  translated reason). Rows are editable inline before export. Images stay
  URL-only (`external_thumbnail_url`) — no downloads here.
- EXPORT: "download episodes CSV" (+ "download podcasts CSV" when missing
  shows were fetched) shaped EXACTLY to the existing importers' columns —
  derive the header set programmatically from the importer classes and
  assert equality in a test (never hardcode a drifting list). reference_key
  stays empty (importer upsert creates new records); day-first date fields
  formatted per the import contract.
- Rate limiting + per-row failure isolation: one bad link never fails the
  batch.
- Tests: parsing/dedupe/cap; mixed junk warnings; fetch fill with a faked
  lookup service; oEmbed fallback labeled reduced; html→markdown conversion
  cases (breaks, links, lists, entities); editable state round-trip; CSV
  header sets match the importers programmatically; missing-show podcasts
  CSV; throttle respected; per-row error isolation; guest blocked from the
  pages; bounded batch enforcement.

## Out of scope

Direct "send to importer" (v1 is CSV download only — Yoni 6b); image
downloads (IMG-B owns that); WB2+ recipe machinery; IE-1 relation modes;
Composer changes; server-side persistence for the markdown editors.

## Docs and handoff

Research + plan docs BEFORE code (`docs/research/admin-tools/00-tools1-*.md`);
ledger row `TOOLS1 - Admin tools page and spotify links fetcher`;
`current-project-state.md`; the WB track note updated (SF1 pulls WB7's
links-list source forward as a tool; WB2+ unchanged); handoff per header
rules with manual front checks (open כלים → add three markdown editors →
copy one → copy all → paste into Google Sheets → three rows one column with
line breaks intact; reload → content survived; open the fetcher → paste a
mixed list of 5 episode links + junk → junk warned, 5 parsed → fetch with
the connection → table fills → edit a title → download episodes CSV → import
it through the normal episodes import → episodes appear; fetch a show link
in shows mode; try without credentials → reduced mode labeled; Hebrew RTL +
light/dark).

Commit: `feat: add admin tools page and spotify links fetcher`

End with exactly:

```text
Admin tools TOOLS1 is complete. Waiting for Yoni review before continuing.
```
