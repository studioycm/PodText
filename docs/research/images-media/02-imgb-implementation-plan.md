# IMG-B Implementation Plan

Date: 2026-07-12

Prompt: `prompts/pre-13-prompts/images-arc-imgb-episode-images-tb1-codex-prompt.md`

## Guardrails

- Execute IMG-B as the only session step.
- No Composer changes.
- Do not run other prompts or unrelated cleanup.
- Do not run `vendor/bin/filacheck --fix`.
- Keep native CSV import/export image-path changes deferred.
- Keep storage filenames technically controlled by the storage/picker path. `admin_ux.media_naming_strategy` controls egress/download names only for this run.
- Final handoff must be committed as `docs/phase-02/images-arc-imgb-handoff.md`, end with `## Commit hash`, and include a numbered `## Local Front Check Report` containing manual operator steps.

## Implementation Sequence

1. Documentation before code.
   - Create this research/plan pair.
   - Later update `docs/phase-02/images-media-track-plan.md`, `docs/phase-02/current-project-state.md`, and `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`.
   - Create retrospective `docs/phase-02/images-arc-imga-handoff.md`.
   - Backfill EP1 commit hash in EP1 handoff/current state/ledger references.
   - Add the three durable AI-development lessons required by the prompt.

2. Schema and model foundation.
   - Add migration for nullable `content_items.image_path`.
   - Add `image_path` to `ContentItem` fillable.
   - Add factory support for `image_path`.
   - Add `ImageFileNamer::CONTENT_ITEM_IMAGE` and include `content-items/images` in `appOwnedDirectories()`.

3. Retention and delete guards.
   - Refactor `AppOwnedMediaFileCleaner` to delete only public-disk app-owned files that have no Curator media row and are not referenced elsewhere.
   - Keep Curator-registered library files and rows on replace, clear, and owner-record delete.
   - Add `ContentItemObserver` for `image_path` cleanup.
   - Register an app observer/listener for Curator `Media` deleting events.
   - Add a media reference finder that reports translated surfaces for `cover_path`, `image_path`, and settings assets.
   - Add a policy as a UI guard where Filament honors model authorization, while model-event enforcement remains the direct-delete backstop.

4. Admin image UI.
   - Add local episode image field to `EpisodeWorkspaceForm` media section.
   - Add shared table record image action helper for `cover_path` and `image_path`.
   - Add the group cover action to the content groups table.
   - Add the episode local-image action to the content items table and content-group relation manager.
   - Add an effective-image `ImageColumn` to the content items list and relation manager, using `PublicDefaultImageResolver` and existing eager-loaded relationships.

5. Resolver and public preference order.
   - Update `PublicDefaultImageResolver::contentItemImage()` to prefer local `image_path`, then `external_thumbnail_url`, then group cover when mode allows, then defaults.
   - Keep source labels truthful: local item image uses source `item`, external thumbnail uses source `item_external`, group cover uses `group`.

6. External thumbnail download enrichment.
   - Add `DownloadExternalContentItemImage` queued job on `imports-exports`.
   - Fetch only HTTPS URLs, with explicit timeout and no remote fetch in native CSV import paths.
   - Enforce `ImageUploadRules::MAX_KILOBYTES` and raster MIME validation from response content.
   - Store to `content-items/images`, register a Curator row, update `image_path`, and send database/Filament notification to the user.
   - Add table action on content item surfaces; when a local image exists, expose an overwrite confirmation action.

7. Content images zip export.
   - Add `ContentImagesExportManager` or focused support class to build zips and route download responses.
   - Add `ExportContentImagesZip` queued job on `imports-exports`.
   - Add authenticated route/controller for `admin/content-images-exports/{token}`.
   - Add content group header action and cheap record-scoped action.
   - Delete previous zip files for the requesting user before creating a new export as the bounded-retention strategy.

8. Tests.
   - Update/add Pest tests for the real workflows listed in the research note.
   - Iterate with targeted commands only:
     - `php artisan test --compact <file> --filter='...'`
   - Do not run the full suite until the final gate after Pint.

9. Final sequential quality gate.
   - `vendor/bin/pint --test`
   - `php artisan test`
   - If full suite fails, fix with targeted tests, then rerun full suite until green.
   - `vendor/bin/filacheck`
   - `npm run build`
   - `git diff --check`
   - `git status --short`

10. Handoff and commit.
   - Create `docs/phase-02/images-arc-imgb-handoff.md` with files changed, tests, commands, FilaCheck result, assumptions, deferred issues, current git status, numbered manual local front check steps, and final commit hash section.
   - Commit as `feat: add episode images, media guards, and content images export`.

## Requirement Classification Plan

- Implemented: D-IMG-E cleanup semantics, referenced-media guard, local episode image, resolver preference, TB1 image actions, external-image download, content-images export.
- Deferred by prompt: avatars, zip import packages, SF1/TL1 tools, native CSV image import/export columns, rename-on-attach.
- Not applicable: Composer changes.
- Blocked: none known at plan time.
