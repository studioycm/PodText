# Phase 02 Current Project State

Recorded after Prompt 08, Prompt 09, the post-Prompt-09 admin management UX repair, and the later admin panel layout commit. This document intentionally avoids local secrets and should be updated when later prompts change the active baseline.

## Git State

- Current branch: `main` tracking `origin/main`.
- Latest local `HEAD` inspected during this state sync: `e671d7b feat: configure sidebar width, collapsibility, and content max width in AdminPanelProvider`.
- Admin management UX repair commit is present in history: `16ab33a fix: repair admin management ux after phase two resources`.
- Prompt 09 implementation commit is present in history: `22e11d0 feat: add phase two admin content management`.
- Prompt 08 implementation commit is present in history: `b15f5c1 feat: add taxonomy tags pinning settings and media foundation`.
- Prompt 07 implementation commit remains in history: `7edb82d feat: add transcription model revision`.
- Starting docs-sync working tree: clean (`git status --short --branch` reported `## main...origin/main`).
- Prompt 10 has not started. It is the next implementation prompt only after this state sync is reviewed and a clean quality baseline is accepted.
- This post-admin-repair Phase 02 documentation sync is intentionally uncommitted for human review.

## Tooling State

- Laravel: 13.17.0.
- PHP: 8.4.22 from `php artisan about`; Laravel Boost reports PHP 8.4.
- Filament: 5.6.7.
- Livewire: 4.3.3.
- Laravel Boost: 2.4.10 installed and available through MCP.
- Pest: 4.7.4.
- FilaCheck: 1.2.3 installed.
- FilaCheck Pro: 1.2.7 installed.
- Spatie Laravel Tags: 4.12.0 installed.
- Filament Spatie Laravel Tags plugin: 5.6.7 installed.
- Spatie Laravel Settings: 3.9.0 installed.
- Filament Spatie Laravel Settings plugin: 5.6.7 installed.
- App locale from `php artisan about`: `he`.
- App timezone from `php artisan about`: `UTC`; Phase 02 UI requirements still require Israel/Hebrew date presentation in `Asia/Jerusalem` while storing dates with Laravel's normal conventions.

## Boost MCP Status

Laravel Boost MCP tools were exposed and usable during this state sync.

- Boost tools used: `application_info` and `database_schema`.
- Boost confirmed Laravel 13.17.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and SQLite.
- Boost schema inspection confirmed the post-Prompt-08/09 tables and fields listed below.
- Fallback shell and Artisan inspection was still run because the prompt explicitly requested it.

## Application Shape

- Database driver: SQLite.
- Public panel root: `/`.
- Admin panel root: `/admin`.
- `php artisan route:list` reported 47 routes, including admin Resources for authors, categories, content groups, content items, content tags, homepage sections, transcriptions, and `admin/public-content-settings`.
- Existing public pages remain:
  - `App\Filament\Public\Pages\BrowseContentGroups`
  - `App\Filament\Public\Pages\ShowContentGroup`
  - `App\Filament\Public\Pages\ShowContentItem`
- Existing public Livewire components remain:
  - `App\Livewire\Public\ContentGroupBrowser`
  - `App\Livewire\Public\ContentItemBrowser`

## Current Domain Schema

Current tables relevant to Phase 02 content after Prompt 08 and Prompt 09:

- `authors`
- `content_groups`
- `content_items`
- `author_content_item`
- `transcriptions`
- `categories`
- `category_content_group`
- `category_content_item`
- `tags`
- `taggables`
- `settings`
- `homepage_sections`

Prompt 07 migration status from `php artisan migrate:status` and Boost database inspection:

- `2026_06_29_134855_create_transcriptions_table`: ran.
- `2026_06_29_134914_add_featured_transcription_id_to_content_items_table`: ran.
- `2026_06_29_134914_backfill_transcriptions_from_content_items_table`: ran.

Prompt 08 migration status from `php artisan migrate:status` and Boost database inspection:

- `2026_06_30_012920_create_tag_tables`: ran.
- `2026_06_30_012921_create_settings_table`: ran.
- `2026_06_30_012923_create_categories_table`: ran.
- `2026_06_30_012931_create_homepage_sections_table`: ran.
- `2026_06_30_012932_add_prompt08_fields_to_content_items_table`: ran.
- `2026_06_30_012933_add_homepage_order_to_content_groups_table`: ran.
- `2026_06_30_012934_create_public_content_settings`: ran.

Current physical schema verified through Boost `database_schema`:

- `transcriptions` table exists.
- `content_items.featured_transcription_id` exists.
- Legacy `content_items.transcript_markdown` still exists as a legacy/backfill source and later cleanup target.
- `tags` and `taggables` exist for Spatie tags.
- `tags` includes Phase 02 editorial metadata columns: `is_enabled`, `enabled_at`, `enabled_by_id`, `created_by_id`, and `moderation_state`.
- `settings` exists for Spatie Settings.
- `homepage_sections` exists with section target fields for category, tag, and content group.

## Prompt 07 Implementation Notes

- `ContentItem::transcriptions()`, `ContentItem::featuredTranscription()`, `ContentItem::latestPublishedTranscription()`, and `ContentItem::effectiveTranscription()` exist.
- `Transcription::contentItem()` and `Transcription::author()` exist.
- `Author::transcriptions()` exists.
- `ContentItem::published()` requires a published parent group, a published item, and at least one published child transcription.
- Public item/group pages load and render effective/main transcription content instead of directly rendering legacy item transcript content.
- Featured transcription ownership is validated so a featured transcription must belong to the same `ContentItem`.
- Public effective transcription resolution ignores unpublished featured transcriptions and falls back to the latest published transcription.
- New writes to legacy `content_items.transcript_markdown` are deprecated/blocked in normal application paths.

## Prompt 08 Implementation Notes

- Prompt 08 is implemented and committed.
- Categories are implemented as custom hierarchical records.
- Spatie tags are implemented through the standard `tags` table and `taggables` pivot, scoped to type `content` in admin item forms.
- `App\Models\ContentTag` remains only as the configured Spatie custom tag model for enabled/moderation metadata on the normal Spatie `tags` table.
- Item pinning fields and content group homepage ordering fields exist.
- Prompt 08 media metadata foundation fields exist on `content_items`.
- `App\Settings\PublicContentSettings` works in the admin settings page and persists rows in the `settings` table.
- Public pages do not consume `PublicContentSettings` yet; Prompt 11 owns that public homepage/search consumption.

## Prompt 09 and Admin Repair Notes

- Prompt 09 is implemented and committed.
- The post-Prompt-09 admin management UX repair is implemented and committed as `16ab33a`.
- `EditContentItem` uses `getContentTabLabel(): ?string` for the item details tab label.
- `EditContentItem` no longer overrides `getContentTabComponent()` only to change the label, preserving real form fields in the item details tab.
- ContentItem edit renders the item details tab, core item form fields, and the transcriptions tab.
- ContentItem create redirects to the edit page for the created item and notifies admins to add a transcription from the transcriptions tab.
- `ContentItemsTable` has an Add transcription row action.
- Associate-existing transcription was deferred because `Transcription` belongs to one `ContentItem`; associating an existing transcription would move it from another item rather than copy it.
- The first transcription created for an item is automatically set as `featured_transcription_id`.
- The set-featured action is exposed only when the item has more than one transcription.
- Draft transcriptions remain publicly ineffective even if selected as featured.
- `content_items.transcript_markdown` remains out of item forms and relation-manager writes.

## Homepage and Settings Notes

- `HomepageSectionResource` is treated as homepage content configuration: records define which content slices appear on the homepage.
- `HomepageSectionForm` is type-driven:
  - `latest` does not require a category, tag, or content group target.
  - `category` requires `category_id`.
  - `tag` requires `tag_id`.
  - `content_group` requires `content_group_id`.
  - `curated_query` remains deferred.
- Homepage settings and homepage sections have separate responsibilities:
  - `PublicContentSettings` stores global defaults, limits, and layout choices.
  - `HomepageSection` records configure ordered content slices.
  - Item pinning is separate and affects item ordering where public queries support it.
- Prompt 11 must read `PublicContentSettings` and visible ordered `HomepageSection` records when building the public homepage/search UI.

## Browser Regression Tests

- Pest browser testing is present.
- `tests/Browser/AdminContentItemBrowserTest.php` visits a ContentItem edit page in a real browser.
- The browser test asserts the item details tab label, title field, slug field, content group field, status field, media URL field, and transcriptions tab are visible.
- This test protects the `getContentTabLabel()` repair from regressing into an empty details tab.

## Prompt 10 Readiness Notes

- Prompt 10 has not started.
- Prompt 10 must preserve the Spatie tags decision: use `tags`/`taggables`, keep tag inputs scoped to type `content`, and do not create a custom `content_item_tag` pivot.
- Prompt 10 must preserve `ContentTag` only as the configured Spatie custom tag model for enabled/moderation fields.
- Prompt 10 must not reintroduce writes to legacy `content_items.transcript_markdown`.
- Prompt 10 import tests must account for first-transcription auto-feature behavior.
- Prompt 10 must not implement public consumption of `PublicContentSettings` or `HomepageSection`; Prompt 11 owns that work.

## Baseline Issue To Record

`php artisan model:show App\Models\ContentItem` and `php artisan model:show App\Models\ContentGroup` previously failed with a class redeclare fatal. This documentation sync did not retest or fix that application issue. Future implementation prompts should avoid relying on `model:show` until the cause is investigated.
