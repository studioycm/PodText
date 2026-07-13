# Fetcher Workspace FIX1 Implementation Plan

## Constraints

- Do not change Composer or npm dependencies.
- Keep native importers strict; fix fetched rows before CSV/direct import.
- Use `Http::preventStrayRequests()` and committed fixtures in every HTTP test.
- Research and plan docs must exist before code changes.
- Final gate order: requirements sweep, Pint, FilaCheck, npm build, full Pest suite last. If code changes after a green full suite, restart the gate from Pint.
- End with implementation commit, then immediate docs-only hash-backfill commit.

## Implementation Steps

1. Documentation setup and evergreen fixes
   - Backfill FETCH1 hash `524a292` in the FETCH1 handoff and ledger.
   - Strengthen `docs/phase-02/ai-development-lessons.md` usage note.
   - Add lessons for HTTP fixture discipline, fetcher/importer ownership, raw HTML trust boundaries, settings cache, and full-suite-last behavior.
   - Update media embed guideline and spec for trusted raw HTML fields.
   - Add `.env.example` settings cache flags and a direct settings-cache invalidation test.

2. Shared fetcher import identity
   - Add a small resolver/service under `App\Support\Importer\SpotifyLinks`.
   - Resolve existing episodes by Spotify episode ID in `media_metadata->episode_id` and by `external_id`.
   - Resolve existing podcasts by show ID through existing item metadata and by title fallback.
   - Preassign ULIDs for new podcasts and episodes.
   - Mutate fetcher rows and podcast rows so CSV export always has stable `reference_key` and `content_group_reference_key`.
   - Keep helper text explicit: import podcasts CSV first, then episodes CSV.

3. Direct import action
   - Add a fetcher page action `Direct import`.
   - Show a confirmation modal with counts for new podcasts, new episodes, episodes linked to existing podcasts, and existing episodes skipped.
   - On confirmation, import/link/skip using the shared resolver.
   - Use one transaction per row.
   - Store per-row outcomes on the result rows and show a notification summary.
   - Preserve URL-only media behavior and set created records to draft.

4. Workspace Spotify fetch modal
   - Replace immediate suffix action execution with an options modal.
   - Defaults: fill slug when empty, fill title prefix when empty, link matched podcast, and do not overwrite non-empty fields.
   - Resolve matched podcast through the same show-id resolver and set the podcast select only when enabled.
   - Fill slug through the existing slug-generation rules only when empty unless overwrite is selected.
   - Add a quick-clear suffix action to `title_prefix`.

5. Publication status helper
   - Add reusable support logic for status-to-published-at autofill.
   - Apply it to item workspace, item form, group form, relationship content-group option form, transcription resource, transcription relation manager, and workspace embedded transcription section.
   - Add unit coverage for the helper and feature coverage for a workspace item and a transcription form.

6. Trusted raw HTML editor
   - Add a shared trusted HTML code-editor component factory using Filament `CodeEditor` and HTML language.
   - Replace the workspace `embed_html` textarea and maintenance raw override textarea.
   - Add `embed_html` to the system item form with the same editor.
   - Remove app-level `embed_html` max-length validation.
   - Stop trimming `embed_html` in the public media component.

7. Markdown proof and HTTP fixtures
   - Add committed Spotify fixture files for oEmbed, malformed/error-ish HTML, API rich descriptions, and content-image HTTP bodies.
   - Update all HTTP tests to use `preventStrayRequests()` and fixtures.
   - Add rich `html_description` coverage for results row, CSV row, and workspace fill.
   - Ensure result descriptions are inspectable as raw Markdown in the fetcher table.

8. Verification and handoff
   - Add/update focused Pest tests while iterating.
   - Run final gate in required order.
   - Write `docs/phase-02/fetcher-workspace-fix1-handoff.md` with gate outcomes and numbered imperative Local Front Check Report.
   - Update `docs/phase-02/current-project-state.md` and ledger.
   - Commit implementation, backfill FIX1 hash into handoff/ledger, commit docs-only hash backfill.

## Requirement Classification Plan

- Implemented: stable fetcher reference keys, direct import, workspace options modal, publish-date autofill, raw HTML code editors, Markdown proof, settings cache flags/test, HTTP fixture discipline.
- Already existed: strict importers, group importer creating with supplied reference key, maintenance raw override verbatim render path, Spatie settings cache env gate in `config/settings.php`, public-front `SettingsSaved` listener.
- Not applicable: Composer/npm dependency changes, remote media downloads, importer relaxation, Workbench machinery.
- Blocked: none known before code.
