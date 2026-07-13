# FETCH1 Spotify Fetcher Implementation Plan

Date: 2026-07-13

## Scope Guard

Execute only `prompts/pre-13-prompts/spotify-fetcher-fetch1-codex-prompt.md`.

Constraints:

- No Playwright and no headless browser.
- No Composer changes.
- Tests use committed fixtures and `Http::fake()`, never live network.
- OpenGraph tier uses plain HTTPS GET plus native `DOMDocument`/`DOMXPath`.
- No image downloads or media writes.
- No `vendor/bin/filacheck --fix`.
- No push.

Final gate order:

1. Requirements sweep.
2. `vendor/bin/pint --test`.
3. `vendor/bin/filacheck`.
4. `npm run build`.
5. Full `php artisan test` last, once green on final state.

## Job 0 Carried Fixes

1. SP2 hash backfill: already present in SP2 handoff, current state, and
   central ledger. Record as already existed in FETCH1 handoff.
2. Maintenance fallback styling:
   - Keep marker replacement unchanged.
   - When the raw HTML marker is missing, append the hidden marker-warning div
     and wrap the rendered form in a visible styled fallback container.
   - Container should be RTL-aware, centered, constrained, spaced, and use the
     same maintenance CSS variables and typography scale as the maintenance
     shell.
   - Tests: marker path has no fallback container; missing-marker path wraps
     the form in the styled container.

## Job 1 Docs And Fixtures

Status before app code:

- Research doc created with reduced-mode field map, thumbnail bug root cause,
  Boost notes, FilamentExamples notes, and live static-HTML observations.
- Implementation plan created before app-code edits.

Fixture work:

- Add `tests/Fixtures/spotify-fetcher/episode-head.html`.
- Add `tests/Fixtures/spotify-fetcher/show-head.html`.
- Fixtures are sanitized and head-only, with representative `og:*`,
  `music:*`, and LD-JSON fields.

## Job 2 OpenGraph Client

Add `App\Support\Importer\SpotifyLinks\SpotifyOpenGraphClient`.

Behavior:

- Accept only canonical HTTPS `open.spotify.com/episode/{id}` or
  `open.spotify.com/show/{id}` URLs.
- Use a descriptive user agent, text/html accept header, connect timeout, and
  about eight-second total timeout.
- Disable automatic redirects and follow at most three redirects manually,
  accepting only HTTPS redirects that remain on `open.spotify.com`.
- Cache each URL result using a short app cache entry, including null failures
  through a sentinel wrapper.
- Throttle uncached external fetches through `ImporterThrottle`.
- Return `null` on HTTP failures, unsafe redirects, malformed HTML, or malformed
  JSON.
- Parse static HTML only:
  - `og:title`
  - `og:description`
  - `og:image`
  - `og:url`
  - `music:duration`
  - `music:release_date`
  - `application/ld+json`: `name`, `description`, `datePublished`,
    `partOfSeries.name`, `duration`, and image where present.

Tests:

- Episode fixture extraction.
- Show fixture extraction.
- Malformed HTML/JSON tolerance.
- Cache hit avoids second request.
- HTTP failure returns null.
- Unsafe redirect returns null.

## Job 3 Reduced-Mode Merge

Update reduced mode to fetch oEmbed and OG for each row and merge per field:

| Field | Precedence |
|---|---|
| title | LD/OG title, else oEmbed title, else ID |
| description | LD-JSON description, else `og:description`, else blank |
| image | `og:image`, else oEmbed `thumbnail_url` |
| embed | oEmbed HTML/src-derived URL only |
| podcast/show name | LD `partOfSeries.name`, else episode OG description heuristic, else show title |
| release date | LD `datePublished`, else `music:release_date` |
| duration | LD duration, else `music:duration` |

API mode:

- Keep the existing credentialed lookup path.
- Add `source = api` and translated source label.
- Keep existing fallback behavior when API fails; if fallback reduced fetch also
  fails, the row remains an error.

Reduced mode:

- Add `source = reduced` and translated source label.
- Existing status remains `reduced`.
- Generate podcast rows from reduced show rows and from episode rows when a
  show name/ID can be resolved.

CSV:

- Keep headers derived from native Filament importers.
- Both modes emit the same importer columns with blanks where unknown.
- Add source information only inside `media_metadata`, not as a new importer
  column.

Tests:

- Merge precedence per field.
- oEmbed-only thumbnail fallback.
- CSV header parity and blank unknown fields.
- Source labels in API and reduced rows.

## Job 4 Results Preview

The existing fetcher uses a custom Blade table, so implement the preview there.

- Add a preview column near the thumbnail URL.
- Render `<img>` directly from `external_thumbnail_url` when filled.
- Use fixed width/height, object-cover, rounded border, and alt text from row
  title.
- Gracefully show a translated placeholder when blank.
- Do not download or store the image.

Tests:

- Reduced row with only oEmbed thumbnail renders an `<img>` preview.
- API row image URL renders the same preview path.

## Job 5 Description Markdown

Use the existing `SpotifyHtmlToMarkdown` class only:

- Add a plain-text normalization method to the same class for OG/LD
  descriptions.
- API HTML descriptions convert to Markdown before display/output.
- API plain-description fallback uses plain-text normalization.
- OG/LD descriptions use plain-text normalization only.
- `EpisodeSpotifyLookup::lookup()` returns `description_markdown`, so the
  episode workspace fill action writes Markdown into blank
  `description_markdown` fields.

Tests:

- HTML conversion still preserves paragraphs/line breaks.
- Plain-text normalization preserves line jumps and paragraph gaps.
- CSV description cell contains Markdown.
- Episode workspace fetch fills `description_markdown` from lookup data.

## Documentation And Handoff

Before final commit:

- Add central ledger row:
  `FETCH1 - Spotify fetcher reduced-mode upgrade`.
- Update `docs/phase-02/current-project-state.md`.
- Create committed handoff:
  `docs/phase-02/spotify-fetcher-fetch1-handoff.md`.
- Handoff includes:
  - gate outcomes before the handoff commit;
  - `## Commit hash` pending before the final commit;
  - numbered Local Front Check Report;
  - requirement classification;
  - files changed, tests, assumptions, deferred issues, and current git status.

Commit sequence:

1. Commit handoff markdown before final commit.
2. Commit final implementation as
   `feat: enrich spotify fetcher reduced mode with opengraph and previews`.
