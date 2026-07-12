# TOOLS1 Research - Admin Tools and Spotify Links Fetcher

Date: 2026-07-12

## Prompt

Active prompt: `prompts/pre-13-prompts/admin-tools-tools1-codex-prompt.md`.

Scope is one merged TL1 + SF1 implementation run. No Composer changes are allowed.

## Preflight

- `git status --short --branch`: clean tree on `main`, ahead of `origin/main` by
  one commit.
- `git log --oneline -8`: current prompt commit `f0a29d6` is at `HEAD`; TS1
  `0d17c2a` is directly behind it, satisfying the expected prior prompt check.
- MP2 final-gate suite line was not provided in the kickoff message; the literal
  placeholder is not usable, so the MP2 recorded gap stays unchanged for Job 0.2.

## Boost Findings

Laravel Boost was available.

- `application_info` reported PHP 8.4, Laravel 13.19.0, Filament 5.6.7,
  Livewire 4.3.3, Horizon 5.47.2, Pest 4.7.4, Tailwind 4.3.2, and Boost 2.4.11.
- `database_schema` summary confirmed the relevant tables:
  `import_connections`, `content_groups`, `content_items`, `imports`,
  `exports`, `notifications`, and existing settings/public-form tables.
- `search_docs` was used for Filament custom pages/forms/actions, Livewire file
  upload/state testing, Pest/Livewire assertions, and Filament import/export CSV
  considerations. Useful installed-version guidance:
  - Filament custom pages can implement `HasSchemas`, `HasTable`, and
    `HasActions`, render a Blade page view, and return a download response from a
    page method.
  - Livewire/Filament component state is stored in public properties and can be
    tested through Livewire component calls and state assertions.
  - CSV output must continue to account for spreadsheet formula injection risk.

## FilamentExamples Findings

FilamentExamples MCP was available with search/snippet access only. No
source/read/fetch/detail tool was exposed.

Search batches:

- `Filament custom page tabs form schema MarkdownEditor Alpine clipboard`
- `Filament custom page Livewire table editable rows CSV download action`
- `Filament FileUpload csv custom page import parse first column`
- `Filament navigation sort custom page group icon Heroicon`
- refined: `Filament custom settings page tabs schema markdown editor repeater`
- refined: `Filament table records array inline editable text inputs Livewire custom page`
- refined: `Filament action CSV download from custom page no Excel package`
- refined: `Filament Livewire page FileUpload CSV parse table results`

Relevant examples and PodText adaptation:

- Multi-panel hotel custom page examples:
  - Pattern to copy: `Page` classes with `InteractsWithSchemas`, `public ?array
    $data`, `mount()` filling schema state, and simple Blade page views.
  - Pattern to avoid: hardcoded English labels and unscoped page placement.
  - PodText adaptation: pages use `UsesAdminNavigationOrder`, translation keys,
    and the existing admin panel discovery.
- FindHotel custom page with table records:
  - Pattern to copy: a custom page owning transient state and rendering a table
    after a page action populates records.
  - Pattern to avoid: direct relationship access without explicit loading when
    rows come from database records.
  - PodText adaptation: SF1 rows are component-owned arrays, so inline Blade
    editing is cheaper than forcing fake Eloquent table rows.
- Custom page download report example:
  - Pattern to copy: page method returning a download response.
  - Pattern to avoid: adding `maatwebsite/excel`; TOOLS1 has no Composer changes.
  - PodText adaptation: use native streamed CSV responses built from importer
    column metadata.
- Editable box-score stats table example:
  - Pattern to copy: Livewire-backed inline inputs inside a tabular admin page.
  - Pattern to avoid: saving row edits to database; TOOLS1 results are transient.
  - PodText adaptation: update only the page's `rows` array, then export CSV.

## Existing PodText Findings

- `App\Support\Media\EpisodeSpotifyLookup` already normalizes Spotify episode URL,
  URI, and ID values and maps Spotify API data into content-item form fields.
  It only supports episodes today.
- `SpotifyHttpClient` already fetches episode title, show name, duration,
  release date, thumbnail, description, and `html_description`.
- `SpotifyConnector` and `SpotifyClient` only expose `fetchEpisode()` and
  `ping()`. SF1 should extend these boundaries with show lookup instead of
  adding a parallel client.
- `ImportConnection` already stores encrypted Spotify client credentials and can
  be selected by provider/status.
- `ImporterThrottle` provides a shared throttling hook. It reads the Google
  throttle config today, but the operation key is generic and can be reused for
  Spotify fetches.
- `ContentGroupImporter::getColumns()` and `ContentItemImporter::getColumns()`
  are the sanctioned header sources. SF1 CSV headers should derive from these
  classes programmatically.
- Native importers use portable identifiers. For SF1, `reference_key` remains
  empty and `content_group_reference_key` should resolve existing podcasts by
  matched external show ID when possible.
- `AdminNavigationOrder` currently places `ImporterSettings` in
  `site_management` at sort `340`. TOOLS1 pages should use the same group and be
  near the importer settings page.
- `ContentImagesExportDownloadController` already aborts guests and uses the
  manager's user-scoped path. The missing IMG-B audit gap is test coverage, not
  an implementation gap.
- `routes/web.php` protects the content-images download route with Filament
  `Authenticate`; guests should redirect to `/admin/login`, and authenticated
  wrong-owner/token access should return no file.

## Decisions

- TL1 clipboard payload boundary: add a small PHP helper for spreadsheet-cell
  quoting and mirror the same deterministic logic in the Alpine page. Pest tests
  exercise the PHP helper; the Blade page uses an inline Alpine function with the
  same algorithm for browser-only localStorage state.
- TL1 persistence: no server persistence. Markdown editors live only in
  `localStorage` under a PodText-specific key.
- SF1 execution: use sequential Livewire page execution for up to 100 lookups,
  with `ImporterThrottle` before each row. A queued job/progressive polling stack
  would add persistence and operational surface that the prompt does not require
  for a 100-row cap.
- SF1 fallback: use Spotify public oEmbed only when no enabled Spotify
  connection is selected/available or credentialed fetch fails before a row can
  return useful data. Reduced rows are clearly labeled in UI state and CSV data.
- SF1 HTML conversion: add a minimal app-owned HTML-to-Markdown converter for the
  Spotify subset: line breaks, paragraphs, links, ordered/unordered lists,
  entities, and plain text. No dependency is added.
- SF1 CSV output: use streamed CSV responses with formula-injection protection.
  Header sets are derived from importer column names and tested for equality.

## Risks

- Spotify oEmbed is network-backed; tests must fake HTTP and avoid real network.
- Importer `content_group_reference_key` is required. SF1 can only populate it
  when an existing `ContentGroup` matches the show external ID. Missing shows get
  a podcasts CSV and episode rows keep the group reference blank for user review.
- Full browser clipboard/localStorage behavior remains an operator front check,
  while payload formatting is covered through the shared PHP helper.
