# Codex Prompt — FETCH1: Spotify Fetcher Reduced-Mode Upgrade (OpenGraph tier, previews, Markdown)

Work in the current local clone of `studioycm/PodText`.

ONE run: make the credential-less ("reduced") mode of the Spotify links
fetcher actually useful — add a plain-HTTP OpenGraph tier and merge it with
oEmbed, fix the missing thumbnail, add an image preview column, and wire
description→Markdown conversion through the table, the importer CSV, and the
episode workspace fill. NO Playwright and NO headless browser — that
approach was evaluated and REJECTED (ToS/fragility/weight); this run is
plain HTTPS GET + HTML parsing only. Standing runner rules: research note +
implementation plan docs BEFORE code, no push unless asked, no
`filacheck --fix`, fixture-owned tests (NO live network in tests), en+he
translations for every new label, NO Composer changes (parse with native
`DOMDocument`/`DOMXPath` + `libxml_use_internal_errors(true)`; do not add a
crawler package). The handoff is a COMMITTED MARKDOWN FILE
(`docs/phase-02/spotify-fetcher-fetch1-handoff.md`) with gate outcomes
written into it before the commit, `## Commit hash` pending, and a numbered
Local Front Check Report.

FINAL GATE ORDER (standing): requirements sweep → `vendor/bin/pint --test` →
`vendor/bin/filacheck` → `npm run build` → FULL `php artisan test` LAST
(once = once GREEN on final state; re-enter from Pint after any change;
record every run in the handoff).

## Preflight

```bash
git status --short --branch
git log --oneline -4
```

Expect SP2's commit at or near HEAD (prompt/docs commits may sit above
it). Clean tree otherwise; stop on unexpected app-code dirt.

## Context (verify in code, then build)

The fetcher shipped in TOOLS1 (`a6d6408`): `App\Filament\Pages\
SpotifyLinksFetcher` + `app/Support/Importer/SpotifyLinks/*`
(`SpotifyLinkParser`, `SpotifyOEmbedClient`, `SpotifyHtmlToMarkdown`,
`ImporterCsvBuilder`, `SpotifyEntityMode`). The API tier (credentials set)
works well per Yoni — do not regress it. Yoni's field report on reduced
mode: "not fetching most of the data like description, podcast, display a
preview image." The episode workspace (`EpisodeWorkspaceForm`) has a
Spotify fetch-into-form action that must also benefit.

## Job 0 — carried fixes (small, do first)

1. Backfill SP2's commit hash per the standing rule (SP2 handoff
   `## Commit hash`, ledger row, any active doc saying pending).
2. **MP2 maintenance-form fallback styling** (production-reported): when the
   maintenance raw HTML lacks the marker div, the form falls back to
   appending after the raw block and "looks not so good." Wrap the fallback
   placement in a styled container consistent with the maintenance shell
   (spacing, max-width, RTL direction, same typography scale), so the
   appended position looks intentional. Marker-based placement is untouched.
   Test: fallback renders inside the styled container; marker path
   unchanged.

## Job 1 — research + bug reproduction (docs before code)

Research/plan docs first (`docs/research/spotify-fetcher/00-fetch1-research.md`,
`00-fetch1-implementation-plan.md`). In research: map the current reduced-mode
data flow field-by-field, and REPRODUCE the missing-thumbnail bug — oEmbed
returns `thumbnail_url`, yet reduced-mode rows show no preview image;
root-cause it (mapping vs render) and record the finding. Confirm what
`open.spotify.com` episode/show pages expose in static HTML: `og:*` metas,
`music:*` metas (duration/release date), and `application/ld+json`
structured data — capture ONE sanitized, trimmed (head-only) fixture per
entity kind for tests; strip any volatile tokens from fixtures.

## Job 2 — `SpotifyOpenGraphClient` (the new tier)

New client beside `SpotifyOEmbedClient`, same discipline:

- Plain HTTPS GET of the canonical `open.spotify.com/episode/{id}` or
  `/show/{id}` URL (from `SpotifyLinkParser`), descriptive User-Agent,
  ~8 s timeout, redirects limited to the same host, HTTPS only.
- Parse ONLY static HTML: `og:title`, `og:description`, `og:image`,
  `og:url`; `music:duration` / `music:release_date` when present; and
  best-effort `application/ld+json` (episode/show name, description,
  `datePublished`, `partOfSeries.name`, duration) — every field nullable,
  parser never throws on malformed JSON/HTML.
- Per-URL caching and throttling mirroring `SpotifyOEmbedClient`'s
  pattern; graceful null result on any HTTP/parse failure (a failed OG
  fetch must never fail the row — the row degrades to oEmbed-only).
- Note in helper text / docs: Spotify truncates `og:description` (~200
  chars); LD-JSON description is usually fuller — prefer it when present.
- Tests on committed fixtures via `Http::fake()`: field extraction per
  entity kind, malformed-HTML tolerance, cache hit avoids second request,
  failure returns null.

## Job 3 — tier merge (per FIELD, not per row)

Reduced mode fetches BOTH oEmbed and OG (each cached/throttled) and merges:

- title: OG/LD-JSON, else oEmbed;
- description: LD-JSON, else `og:description` (oEmbed has none);
- image: `og:image`, else oEmbed `thumbnail_url`;
- embed html: oEmbed only (unchanged);
- podcast/show name: LD-JSON `partOfSeries.name`, else existing heuristics;
- release date / duration: LD-JSON or `music:*` when present, else blank.

API mode (credentials) keeps its current richer path unchanged. Add a
row-level source label column, translation keys he+en (e.g. API /
ללא הרשאות), so Yoni can see which tier produced each row. The importer CSV
(`ImporterCsvBuilder`) emits the SAME columns in both modes with blanks
where unknown — blank cells mean "unchanged" downstream per the standing
import convention. Tests: merge precedence per field; CSV parity between
modes; source labels.

## Job 4 — image preview in the results table

`ImageColumn` (or the app's existing pattern) rendering the merged image
URL directly as an admin-side preview — display only, NO downloading and NO
writing media to storage (imports never fetch remote media; a preview `img`
in the admin tool is presentation). Graceful blank when no image; alt text
from the row title; sensible fixed size. This also closes the Job 1
thumbnail bug — assert a reduced-mode row with only oEmbed data now shows
its thumbnail. Apply the same preview to the API tier's image URL.

## Job 5 — description → Markdown, everywhere descriptions surface

Use the existing `SpotifyHtmlToMarkdown` (TOOLS1) — do not write a second
converter:

1. API-tier HTML descriptions → Markdown before display/output.
2. OG/LD-JSON plain-text descriptions → paragraph normalization only
   (preserve line jumps; never invent markup).
3. Surfaces: (a) results table shows a truncated plain-text preview of the
   Markdown (full text via the table's existing tooltip/expand conventions);
   (b) importer CSV description column carries the Markdown; (c) the episode
   workspace Spotify fetch-fill writes the Markdown into the description/
   markdown field it fills — line breaks and paragraphs preserved end to end.
   Tests: conversion preserves paragraphs/line breaks; CSV cell content;
   workspace fill state contains the converted Markdown.

## Tests

Fixture-based client tests; merge precedence; CSV parity + blanks; preview
column render (incl. the regression for the oEmbed-only thumbnail); Markdown
conversion + workspace fill; maintenance fallback container; existing
fetcher/API-tier tests stay green. Full gate per header order.

## Docs and handoff

Ledger row `FETCH1 - Spotify fetcher reduced-mode upgrade`;
`current-project-state.md`; the two research/plan docs BEFORE code; handoff
per header rules with a Local Front Check Report (numbered: fetch a known
episode link WITHOUT credentials → row shows title, description, podcast
name, preview image, source label; same WITH credentials → API label and
richer data; export CSV → description is Markdown with line breaks; episode
workspace fetch → description field filled with Markdown; maintenance page
without marker → form appears in the styled container).

Commit: `feat: enrich spotify fetcher reduced mode with opengraph and previews`

End with exactly:

```text
Spotify fetcher FETCH1 is complete. Waiting for Yoni review before continuing.
```
