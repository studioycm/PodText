# Fetcher Workspace FIX1 Research

## Scope

Prompt: `prompts/pre-13-prompts/fetcher-workspace-fix1-codex-prompt.md`

This run fixes the Spotify links fetcher import handoff, adds direct import from fetched rows, improves Spotify fill behavior in the episode workspace, applies publish-date autofill to publication forms, and clarifies trusted raw HTML editor/rendering behavior.

## Preflight

- `git status --short --branch`: clean worktree, `main...origin/main [ahead 1]`.
- Recent commits include FETCH1 implementation `524a292 feat: enrich spotify fetcher reduced mode with opengraph and previews` and this prompt commit `8c85a79 docs: fetcher direct import and workspace publishing fixes prompt`.
- `docs/phase-02/ai-development-lessons.md` was read in full before implementation planning.
- No Composer or npm dependency changes are allowed or needed.

## Installed Package Notes

Laravel Boost `application_info` reported the installed versions:

- PHP 8.4
- Laravel 13.19.0
- Filament 5.6.7
- Livewire 4.3.3
- Pest 4.7.4
- Tailwind CSS 4.3.2

Laravel Boost docs confirmed:

- Filament 5 has native `Filament\Forms\Components\CodeEditor` with `language()` and wrapping support.
- Form components support `extraInputAttributes()` / `extraAttributes()` patterns where the specific component exposes them.
- Filament action testing supports mounting/calling actions with modal schema data.
- Filament importers resolve existing records through `resolveRecord()` and can create records with provided keys.
- Laravel HTTP client tests should use `Http::preventStrayRequests()` with explicit fakes.

## FilamentExamples Research

Available tool: `search_examples` only. No source/detail reader was exposed, so results were search/snippet based.

Search batches used short topic phrases for:

- code editor / raw HTML form fields;
- textarea/input attributes;
- suffix actions that clear or fill fields;
- action modal schemas with checkboxes/options;
- importer `resolveRecord` / `firstOrNew` patterns;
- table action modal summaries and status reporting;
- `Select::live()->afterStateUpdated()` publication-like flows.

Relevant examples:

- `AI-Powered CMS With Laravel AI SDK`, `app/Filament/Actions/SuggestTitleAction.php`: modal action schema, footer/action data handling, and direct mutation of mounted action data. Useful for the workspace fetch options modal.
- `Custom Table Field With Product Picker Modal`, `app/Livewire/ListQuoteProducts.php` and `ProductsTable.php`: modal action/table patterns that update component state after a user confirms.
- `Bulk Action Updating Value via Modal Form`, `PostsTable.php`: simple action schema and action-data use.
- `QuoteForm.php`: `Select::make(...)->live()->afterStateUpdated(function (Set $set) { ... })` pattern; adapted for publish-date autofill.

## Importer Reference-Key Verification

Importers remain strict. The fix belongs on the fetcher side.

- `ContentGroupImporter::resolveRecord()` delegates to `resolveRecordByReferenceKey(ContentGroup::class)`.
- `resolveRecordByReferenceKey()` creates a new model initialized with the supplied `reference_key` when no existing record is found.
- `ContentItemImporter` has a required mapped `content_group_reference_key` column and fails unresolved group keys through `RowImportFailedException`.
- Existing fetcher CSV rows left new `reference_key` values blank and sometimes omitted podcast rows for existing groups. This made episode CSVs depend on importer-generated keys and broke the podcast-first / episode-second import workflow.

Required adaptation:

- Resolve existing podcasts before exporting/importing rows.
- Resolve existing episodes by Spotify episode ID through `media_metadata->episode_id` and by `external_id`.
- Preassign ULID reference keys for new podcasts and episodes inside the fetched-row batch.
- Keep episode rows pointing to the resolved/preassigned podcast key.
- Keep podcasts CSV first, then episodes CSV, and update UI helper text in Hebrew and English.

## Raw HTML Inventory

Admin raw HTML fields found:

- `ContentItem::embed_html`
  - Workspace form: `app/Filament/Resources/ContentItems/Schemas/EpisodeWorkspaceForm.php`
  - System item form did not expose it yet and needs a trusted raw HTML editor field.
  - Validation rule currently includes `max:65535`; prompt requires no app-level length restriction for trusted raw HTML.
  - Public render path: `resources/views/components/public/media-embed.blade.php`
  - Current render path trims `embed_html`; prompt requires byte-exact trusted raw HTML rendering.
- `PublicContentSettings` `maintenance.raw_html_override`
  - Form field: `app/Filament/Pages/PublicContentSettings.php`
  - Render path: `resources/views/public/maintenance.blade.php`
  - Config validator `trustedNullableString()` preserves the raw value without trimming or sanitizing.

Required adaptation:

- Use Filament native `CodeEditor` with HTML language for trusted raw HTML inputs.
- Force LTR editor presentation and monospace/code semantics.
- Remove app-level max length from `embed_html`.
- Preserve and render raw HTML verbatim.
- Update `.ai/guidelines/media-embeds.md` and `docs/phase-02/media-embed-spec.md` so D-EMB1 covers both `embed_html` and maintenance raw override as trusted admin fields.

## Settings Cache Rider

Findings:

- `config/settings.php` already gates Spatie settings cache with `SETTINGS_CACHE_ENABLED`, defaulting to `false`.
- `.env.example` does not yet expose the flag.
- `AppServiceProvider` listens for `Spatie\LaravelSettings\Events\SettingsSaved` for `PublicContentSettings` and forgets the derived `PublicFrontConfigCache`, request-scoped render context, and transcription policy.
- Existing tests enable `settings.cache.enabled` explicitly and verify public-front cache invalidation, but the Spatie settings container cache behavior should be covered directly for this rider.

Plan:

- Add `SETTINGS_CACHE_ENABLED=false` and `SETTINGS_CACHE_MEMO=false` to `.env.example`.
- Extend the settings cache test to prove a saved settings object invalidates the cached settings container and a fresh read sees the saved value.
- Handoff should state production can enable `SETTINGS_CACHE_ENABLED=true` after deploy, while local/test default remains false.

## HTTP Test Fixture Discipline

Existing HTTP tests:

- `tests/Feature/SpotifyFetcherFetch1Test.php` already uses `Http::preventStrayRequests()` but still has inline oEmbed/malformed response bodies.
- `tests/Feature/AdminToolsTest.php` uses `Http::fake()` without `preventStrayRequests()`.
- `tests/Feature/ContentImagesExportTest.php` uses `Http::fake()` without `preventStrayRequests()` and inline response bytes.

Required adaptation:

- Every test that fakes HTTP must call `Http::preventStrayRequests()`.
- Every HTTP response body used by tests must come from a committed fixture file.
- Add fixture-backed rich Spotify API HTML-description coverage for results row, CSV row, and workspace fill.

## Direct Import Design Findings

The direct import path should not use native Filament importers because it imports the already-fetched in-memory rows and needs per-row outcome reporting. It should use the same resolver/preassigned-key code as CSV export so both paths agree on record identity.

Semantics:

- Create missing podcasts as draft records.
- Create missing episodes as draft records linked to resolved/preassigned podcasts.
- Link episodes to existing podcasts when a show match resolves.
- Skip existing episodes and report them without updating.
- Do not download media.
- Failed rows should be isolated; a row failure must not abort the batch.

Transaction choice:

- Use a transaction per row, not one large batch transaction. This preserves already-successful imported rows when one row fails and keeps the failure model aligned with fetched-row outcome reporting.

## Front Check Focus

Manual verification should cover:

1. Spotify fetcher fetched-row table displays reference-keyed rows and direct-import outcomes.
2. Podcasts CSV helper text tells the operator to import podcasts before episodes.
3. Direct import creates draft podcasts/episodes, links existing podcasts, and skips existing episodes.
4. Episode workspace Spotify fetch opens options first, then fills only the selected fields.
5. Publish-date autofill only fills blank `published_at` when status is changed to published.
6. Raw HTML editors render as LTR code editors, and raw HTML renders verbatim.
