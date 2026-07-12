# IMG-B Episode Images And Content Images Export Research

Date: 2026-07-12

Prompt: `prompts/pre-13-prompts/images-arc-imgb-episode-images-tb1-codex-prompt.md`

## Scope Confirmation

This run implements IMG-B only: episode-local images, D-IMG-E library retention, referenced-media delete guards, TB1 image actions, external thumbnail download enrichment, and content-images zip export.

Out of scope remains out: Composer changes, avatars, zip import packages, native CSV importer/exporter image columns, SF1/TL1 tools, Prompt 13 dashboard metrics, and rename-on-attach.

## Preflight

- `git status --short --branch` reported a clean `main...origin/main` worktree.
- Recent history includes `e70d6f3 feat: add episode workspace with single transcription lens`.
- Recent history includes `988676e feat: add image naming foundation and curator media library`.
- Current `HEAD` before implementation is `d8e0a02 docs: add IMG-B (updated) episode images prompt and update handoff`.

## Tooling Research

### Laravel Boost

Boost was available and used before code changes.

- `application_info` confirmed PHP 8.4, Laravel 13.19.0, Filament 5.6.7, Livewire 4.3.3, Horizon 5.47.2, Pest 4.7.4, and Tailwind CSS 4.3.2.
- `database_schema` confirmed `content_groups.cover_path`, `content_groups.cover_alt_text`, Curator's `curator.path`, and current `content_items.external_thumbnail_url`; `content_items.image_path` does not exist yet.
- `search_docs` was used for Filament table actions/modal forms, ImageColumn/header actions, action testing, queue dispatch, queued jobs after commits, queue routing, and authenticated download patterns.

### FilamentExamples

FilamentExamples was available and used before code changes. Access level was search/snippet only; no source/read/fetch/details tool was exposed.

Searches were decomposed across:

- table record action modal image upload/picker patterns;
- header action queued export/download notification patterns;
- dynamic image columns and table thumbnail patterns.

Useful returned patterns:

- `schedule-for-doctors` custom page/table examples: record actions with modal schemas, `fillForm()`, `action()`, and post-action notifications.
- `public-products-table` examples: table image columns plus header export actions.
- `bulk-action-modal-change-value` examples: action form schema and action callback shape.
- `generate-invoice-PDF-and-send-via-email` examples: table record download actions returning streamed/download responses.

PodText adaptation:

- Use the installed app's `MediaPickerField`/`PathCuratorPicker` rather than copying example FileUpload code.
- Keep actions translated and configured through shared helpers so both content group and content item tables use the same image action behavior.
- Keep queued content image export on the existing `imports-exports` queue.

## Current Code Findings

- `ImageFileNamer` owns app-owned media families and currently supports content group covers, header, team, about, and default images.
- `MediaPickerField` stores path strings in both Curator and FileUpload modes. Deterministic names exist only in FileUpload fallback; Curator controls upload names internally.
- `PathCuratorPicker` preserves legacy path strings without a Curator media row.
- `AppOwnedMediaFileCleaner` currently deletes a matching Curator media row for old content group covers. This conflicts with D-IMG-E and must be changed so library-registered files are kept.
- `ContentGroupObserver` calls the cleaner on cover replacement and group deletion.
- No `ContentItemObserver` exists yet.
- `PublicDefaultImageResolver::contentItemImage()` currently prefers `external_thumbnail_url`, then group cover, then default image. IMG-B must insert local `image_path` before the external thumbnail.
- `ContentItemsTable` already eager-loads `contentGroup`, `featuredTranscription.authors`, and `latestPublishedTranscription.authors`. The effective-image column can reuse `contentGroup` and scalar item fields without adding per-row queries.
- `AdminUxSettings` already includes `tb1_picker_container`, with `modal` and `slideover` values.
- `ContentItemExporter` does not include `image_path`; IMG-B keeps native CSV import/export image columns deferred.
- Curator's vendor `MediaObserver` deletes the physical file after a Curator media row is deleted. The referenced-media guard must fire during `deleting` before that vendor `deleted` cleanup.
- Curator resource delete actions use normal Filament `DeleteAction`, while the picker panel calls `$item->delete()` directly. Therefore the guard must be enforced at model-event level, with policy support as a UI hint.

## Implementation Decisions

1. Add `content_items.image_path` as a nullable string through a new migration; update model fillable and factory support.
2. Add `ImageFileNamer::CONTENT_ITEM_IMAGE` with directory `content-items/images` and include it in app-owned directory scans.
3. Generalize app-owned cleanup so D-IMG-E applies to both content group covers and content item images:
   - keep Curator-registered library files and rows on replace/clear/delete;
   - delete only app-owned, public-disk, no-Curator-row stray files;
   - keep directory-scoping, traversal guards, and cross-reference checks.
4. Add a Curator media reference guard that checks:
   - `content_groups.cover_path`;
   - `content_items.image_path`;
   - public settings asset paths from menu logos, team/about images, and default images.
5. Add a local-image field to the episode workspace media section, using `MediaPickerField` and translated helper text that states public preference order.
6. Add shared table image actions:
   - content groups choose/replace `cover_path`;
   - content items choose/replace `image_path`;
   - the action uses modal or slideover based on `admin_ux.tb1_picker_container`.
7. Add an explicit queued external-image download action for content items:
   - visible when an external thumbnail exists and no local image exists;
   - a second overwrite action is available when a local image exists;
   - accepts HTTPS only;
   - validates response size cap and raster MIME by content;
   - writes an app-owned file, registers a Curator row, updates `image_path`, and notifies the triggering user.
8. Add a content-images zip export:
   - header action on content groups list/table;
   - per-podcast record action on content groups table;
   - action form defaults to `admin_ux.media_naming_strategy` but allows per-action override;
   - job stores a zip on private `local` disk;
   - entries use `podcasts/{podcast-stem}/cover.{ext}` and `podcasts/{podcast-stem}/episodes/{episode-stem}.{ext}`;
   - missing/unreadable files are skipped and listed in the completion report;
   - delete-before-create per user is the bounded-retention mechanism.
9. Add an authenticated admin download route for generated zips.

## Test Targets

Targeted iteration only:

- existing IMG-A media tests for retention/guard regressions;
- episode workspace tests for local image field and download action;
- public default image tests for resolver preference order;
- content image export tests for zip structure, naming strategy override, skip handling, queue assignment, notification/download route, and guest blocking.

Full `php artisan test` is reserved for the final sequential gate after Pint. If it fails, fix with targeted tests and rerun the full suite until green, recording every full run.
