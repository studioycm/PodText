# Phase 02 Current Project State

Recorded during Prompt 06R reset planning and patched during the evergreen context cleanup. This document intentionally avoids local absolute paths and secrets.

## Git State

- Current branch: `master`.
- Latest committed baseline: `e8ed0e0 docs: plan phase two public content work`.
- Pre-existing uncommitted user changes were present before this reset task, including updated `AGENTS.md`, dependency files, installed skills, and the reset prompt.
- This task must leave changes uncommitted for human review.
- Root `AGENTS.md` is now evergreen repository guidance, not Bootstrap Slice 0 or Phase 02-only context.
- Historical Bootstrap Slice 0, Phase 1, and superseded Phase 02 prompts/docs have been moved under archive directories.
- Active `.ai/guidelines/` files use durable domain names instead of `phase-02-*` filenames.

## Tooling State

- Laravel: 13.17.0.
- PHP: 8.4.22.
- Filament: 5.6.7.
- Livewire: 4.3.3 and explicitly required in `composer.json`.
- Laravel Boost: 2.4.10 installed. MCP tools were exposed and usable during Prompt 06S verification.
- Filament Blueprint: 2.2.0 installed and Blueprint planning guidance is available in `vendor/filament/blueprint/resources/markdown/planning/`.
- FilaCheck: 1.2.3 installed.
- FilaCheck Pro: 1.2.7 installed.
- `vendor/bin/filacheck --detailed` baseline: `pass`, `issues: 0`.

## Boost MCP Status

Laravel Boost MCP tools were exposed and usable during Prompt 06S verification. `application_info`, `database_schema`, and `search_docs` succeeded. Shell and Artisan inspection were still run because the prompt explicitly requested them.

## Application Shape

- Database driver: SQLite.
- Public panel root: `/`.
- Admin panel root: `/admin`.
- Existing public pages:
  - `App\Filament\Public\Pages\BrowseContentGroups`
  - `App\Filament\Public\Pages\ShowContentGroup`
  - `App\Filament\Public\Pages\ShowContentItem`
- Existing public Livewire components:
  - `App\Livewire\Public\ContentGroupBrowser`
  - `App\Livewire\Public\ContentItemBrowser`

## Current Domain Schema

Current tables relevant to content:

- `authors`
- `content_groups`
- `content_items`
- `author_content_item`

Current `content_items` transcript and media fields:

- `media_url`
- `embed_url`
- `duration_seconds`
- `transcript_markdown`

Not currently present:

- `transcriptions`
- `categories`
- Spatie `tags` / `taggables`
- homepage section/settings tables
- item pinning fields
- provider metadata fields
- parser/segment fields
- dashboard widgets

## Current Implementation Notes

- `ContentItem::published()` checks item state, item `published_at`, and published parent group.
- Public root currently browses `ContentGroup` records, not item search results.
- Public group pages list published `ContentItem` records for one group.
- Public item page renders the legacy `ContentItem::transcript_markdown`.
- Admin Resources are split into Resource, `Schemas`, `Tables`, and Pages classes.
- Import/export already uses native Filament Importer/Exporter classes, reference-key upsert, relationship resolution, failed rows, and spreadsheet formula escaping.
- Media embeds are URL-only and rendered by an app-owned Blade component.

## Baseline Issue To Record

`php artisan model:show App\Models\ContentItem` and `php artisan model:show App\Models\ContentGroup` failed with a class redeclare fatal. This reset task does not fix application code. Future implementation prompts should avoid relying on `model:show` until the cause is investigated.

## Prompt 06S AI Context Alignment Audit

- Verified after evergreen cleanup that active root docs contain only `docs/README.md`; active implementation planning is under `docs/phase-02/`, `docs/research/`, and `docs/planning/`.
- Verified active prompts now contain only the corrected Phase 02 sequence `06` through `15` plus `prompts/README.md`; one-time Prompt 06S was moved to `prompts/archive/phase-02-superseded/`.
- Verified active `.ai/guidelines/` files use durable names and required durable sections.
- Verified `AGENTS.md` is evergreen and does not contain an active Bootstrap Slice 0 objective or Phase 02 implementation scope.
- Retried Laravel Boost MCP during Prompt 06S: `application_info`, `database_schema`, and `search_docs` were exposed and succeeded.
- Verified FilamentExamples MCP access during Prompt 06S: `search_examples` returned source snippets and file paths; no separate fetch/read/detail/source tool is exposed.
- Verified Composer/package state through shell output: Laravel Boost 2.4.10, Filament 5.6.7, Filament Blueprint 2.2.0, Livewire 4.3.3, FilaCheck 1.2.3, and FilaCheck Pro 1.2.7 are installed.
