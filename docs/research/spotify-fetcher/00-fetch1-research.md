# FETCH1 Spotify Fetcher Research

Date: 2026-07-13

## Prompt Scope

Run only `prompts/pre-13-prompts/spotify-fetcher-fetch1-codex-prompt.md`.

No Playwright, no headless browser, no Composer changes, no live-network tests,
and no push. The implementation must use plain HTTPS GET plus native
`DOMDocument`/`DOMXPath` for OpenGraph parsing.

## Preflight

- `git status --short --branch` reported `## main...origin/main`.
- Recent commits at kickoff:
  - `5ecc646 docs: backfill settings performance sp2 hash`
  - `fb3f515 perf: optimize public settings lock hints`
  - `b2deb95 docs: add backlog triage, settings performance sp2 and fetcher prompts for phase 2`
  - `c6c9587 perf: instrument settings page and fix maintenance marker copy`
- Working tree was clean before implementation.
- SP2 commit hash backfill already exists in
  `docs/phase-02/settings-performance-sp2-handoff.md`,
  `docs/phase-02/current-project-state.md`, and
  `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`.

## Installed Versions

Laravel Boost `application_info` was available and reported:

- PHP 8.4
- Laravel 13.19.0
- Filament 5.6.7
- Livewire 4.3.3
- Pest 4.7.4
- Tailwind CSS 4.3.2

## Boost Research

Boost `search_docs` was used before code changes for:

- Laravel 13 HTTP client timeouts, fakes, request assertions, and preventing
  stray requests.
- Laravel 13 cache memoization/remember/locks.
- Filament 5 table `TextColumn` badges/tooltips/limits and image column usage.

Useful findings:

- Laravel HTTP tests support `Http::fake()`, `Http::preventStrayRequests()`,
  `Http::assertSent()`, and `Http::assertSentCount()`, which fits the fixture
  owned test requirement.
- Laravel HTTP client supports explicit `timeout()` and `connectTimeout()`.
- Cache APIs support short per-URL response caching without new dependencies.
- Filament 5 supports badge text columns and image columns; the current fetcher
  uses a custom Blade table, so the implementation will mirror the app's
  existing table markup instead of forcing a table abstraction rewrite.

## FilamentExamples Research

FilamentExamples MCP was available with `search_examples` only. No source/read
detail tool was exposed, so this is search/snippet research only.

Initial topic batch:

- `ImageColumn table preview`
- `custom page table image column`
- `table action form fill state`
- `Filament table image column`

Refined topic batch:

- `Filament page table records array`
- `custom page table action`
- `table column tooltip description`
- `Filament action modal table results`

Relevant examples and adaptations:

| Example | Path/class | Useful pattern | Avoided pattern | PodText adaptation |
|---|---|---|---|---|
| Doctor Availability and Blocked-Time Scheduling | `v4/full-projects/schedule-for-doctors/app/Filament/Pages/ManageDoctorSchedule.php` | Custom page with `InteractsWithTable`, page-owned records, header actions, and table reset after actions. | Replacing the existing simple Blade table in this prompt. | Confirms array/page-owned state is a normal Filament custom-page pattern, but the current fetcher can remain Blade-rendered for a narrow change. |
| Editable Box Score Stats Table | `v4/full-projects/box-score-form/app/Filament/Resources/Tournaments/Pages/ManagePlayerStats.php` | `records(fn () => $this->getDataArray())` for array-backed table rows. | Inline database updates in columns. | Useful if the fetcher later moves to a real Filament table; not needed for FETCH1. |
| Custom-Designed Table with ViewColumn Cells | `v4/tables/table-customized-design-viewcolumn/app/Filament/Resources/Accounts/Tables/AccountsTable.php` | Use a custom cell view when built-in columns are too restrictive. | Custom CSS-heavy table rebuild. | Supports keeping preview markup owned by the fetcher Blade table. |
| Default Accounts Table | `v4/tables/table-customized-design-viewcolumn/app/Filament/Resources/DefaultAccounts/Tables/DefaultAccountsTable.php` | `ImageColumn::make(...)->imageSize(...)->square()/circular()` for compact previews. | Storage-disk image assumptions. | PodText preview should render the external merged image URL directly and never download it. |

## Current Reduced-Mode Data Flow

Input parsing:

1. `SpotifyLinksFetcher::fetch()` calls `parseLinks()`.
2. `SpotifyLinkParser` normalizes URLs, Spotify URIs, bare IDs, and CSV tokens
   into canonical `https://open.spotify.com/{episode|show}/{id}` URLs.
3. Without a connected Spotify `ImportConnection`, the page sets
   `usedReducedMode = true` and calls `reducedRow()` for every parsed item.

Reduced row creation:

| Field | Current source |
|---|---|
| `title` | oEmbed `title`, else Spotify ID |
| `external_thumbnail_url` | oEmbed `thumbnail_url` mapped to `thumbnail` by `SpotifyOEmbedClient` |
| `description_markdown` | Always blank |
| `external_description` | Always blank |
| `show_name` | Show rows use oEmbed title; episode rows blank |
| `show_id` | Show rows use ID; episode rows blank |
| `duration_seconds` | Always blank |
| `release_date` | Always blank |
| `embed_url` | Always blank |
| `media_url` | Parsed canonical Spotify URL |
| `status` / `status_label` | `reduced` / translated reduced label |

CSV export:

- Episode rows pass through `contentItemImportRow()`, whose headers are derived
  from `ContentItemImporter::getColumns()`.
- Podcast rows pass through `contentGroupImportRow()`, whose headers are derived
  from `ContentGroupImporter::getColumns()`.
- Blank cells are already preserved for unknown fields and match the standing
  import convention that blank update cells mean unchanged downstream.

Episode workspace:

- `EpisodeWorkspaceForm::fetchSpotifyEpisode()` calls
  `EpisodeSpotifyLookup::lookup()` and fills only blank fields.
- The lookup currently returns `external_description` and `media_metadata`
  `html_description`, but does not return `description_markdown`.
- Therefore the workspace action cannot currently fill the Markdown description
  field from Spotify API HTML descriptions.

## Reduced Thumbnail Bug Reproduction

The reduced-mode thumbnail bug is render-side, not mapping-side.

Observed in code:

1. `SpotifyOEmbedClient::fetch()` maps Spotify oEmbed `thumbnail_url` to the
   returned `thumbnail` key.
2. `SpotifyLinksFetcher::reducedRow()` stores that value in
   `rows.*.external_thumbnail_url`.
3. `resources/views/filament/pages/spotify-links-fetcher.blade.php` renders
   that field as an editable URL `<input>`.
4. There is no `<img>` preview in the row, so Yoni sees no preview image even
   when oEmbed returned a thumbnail URL.

Regression boundary to add:

- Fake oEmbed returns only `thumbnail_url` and `title`.
- Reduced fetch row keeps `external_thumbnail_url`.
- The results table must render an admin-side preview `<img>` using that URL,
  with no download/storage side effect.

## Static Spotify HTML Findings

Plain `curl` with redirects limited to three and a descriptive user agent was
used for research only. Tests will not use live network.

Sample episode URL:
`https://open.spotify.com/episode/5wpLryBEOWyxaMPUUSAQqQ`

Static fields observed:

- `meta name="description"`: full-ish page description.
- `meta name="music:duration"`: numeric seconds, e.g. `3108`.
- `meta name="music:release_date"`: ISO date-time, e.g.
  `2017-03-28T10:00:00Z`.
- `meta property="og:title"`: episode title.
- `meta property="og:description"`: short/truncated display description,
  e.g. show/type text.
- `meta property="og:url"`: canonical episode URL.
- `meta property="og:image"`: CDN image URL.
- `script type="application/ld+json"`: JSON object with `name`,
  `description`, `datePublished`, URL, and a large volatile eligible-region
  payload. The sampled episode object did not include `partOfSeries`.

Sample show URL:
`https://open.spotify.com/show/4c9ZKaFtEKweSYOlYvxfvp`

Static fields observed:

- `meta property="og:title"`: show title.
- `meta property="og:description"`: show description.
- `meta property="og:url"`: canonical show URL.
- `meta property="og:image"`: CDN image URL.
- `meta name="description"`: page description.
- `script type="application/ld+json"`: `PodcastSeries` object with `name`,
  `description`, `publisher`, `author.name`, `image`, and `inLanguage`.

Fixture plan:

- Commit one sanitized head-only episode fixture and one sanitized head-only
  show fixture under `tests/Fixtures/spotify-fetcher/`.
- Strip country allowlists, autoplay entrypoints, and any volatile tokens.
- Keep only representative `og:*`, `music:*`, and LD-JSON fields needed for
  deterministic parser tests.

## Implementation Notes From Research

- Reduced mode should fetch both oEmbed and OpenGraph/LD JSON, then merge per
  field rather than choosing one source per row.
- OpenGraph failure must return `null` and degrade to oEmbed-only data.
- oEmbed `thumbnail_url` should remain the fallback image when OG image is
  missing.
- LD-JSON descriptions are usually fuller than `og:description`; prefer
  LD-JSON when present, then OG description.
- API mode should preserve the existing richer data path, while normalizing
  HTML descriptions to Markdown consistently and adding a source label.
- The preview image is display-only admin presentation and must not fetch or
  store remote media server-side.
