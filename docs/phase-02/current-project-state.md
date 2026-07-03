# Phase 02 Current Project State

This is the single source of truth for rolling Phase 02 prompt progress. Other active docs may describe stable dependencies and ownership, but should link here for current completion/progress status.

Recorded after the Markdown-only post-Prompt-10 prompt-progress centralization cleanup. This document intentionally avoids local secrets and should be updated when later prompts change the active baseline.

## Git State

- Current branch at cleanup preflight: `main` tracking `origin/main`.
- Branch tracking state at cleanup preflight: no ahead/behind marker reported by `git status --short --branch`.
- Working tree at cleanup preflight: clean.
- Latest docs cleanup commit recorded here: `1cb158a docs: centralize prompt progress and ai development lessons`.
- Latest local `HEAD` before Prompt 10 implementation: `014c6b0 docs: update phase two prompt state, completion details for Prompts 08 and 09, and readiness notes for Prompt 10`.
- Admin management UX repair commit is present in history: `16ab33a fix: repair admin management ux after phase two resources`.
- Prompt 09 implementation commit is present in history: `22e11d0 feat: add phase two admin content management`.
- Prompt 08 implementation commit is present in history: `b15f5c1 feat: add taxonomy tags pinning settings and media foundation`.
- Prompt 07 implementation commit remains in history: `7edb82d feat: add transcription model revision`.
- Prompt 10 implementation commit is present in history: `fad6721 feat: extend phase two import export`.
- Prompt 11 public homepage/search is implemented and committed as `7ef2fa7 feat: add public content item homepage search`.
- Pre-Prompt-12 documentation pack is present in history; latest pushed pre-Prompt-12 docs state is `c1237eb docs: add pre-prompt 12 documentation and guidelines for public admin contributors`.
- Prompt 11R public frontend custom Livewire/Blade refactor is implemented and committed as `bb4b97c refactor: customize public content item discovery`.
- Prompt 11A admin relationship UX is implemented and committed locally as `1d81ec0 feat: improve admin relationship management ux`.
- Prompt 11B public contributors/transcribers discovery is implemented in this change set and ready for final quality gate/commit.
- Prompt 12 media embed/item page/parser has not started.

## Prompt Progress

| Prompt | Status | Commit / evidence | Notes |
|---|---|---|---|
| Prompt 07 transcriptions model revision | Complete | `7edb82d feat: add transcription model revision` | Prompt 07 migrations are applied locally. |
| Prompt 08 taxonomy/settings/media foundation | Complete | `b15f5c1 feat: add taxonomy tags pinning settings and media foundation` | Spatie tags/settings foundation and media metadata fields exist. |
| Prompt 09 admin content management | Complete | `22e11d0 feat: add phase two admin content management` | Admin Resources and relation-manager baseline exist. |
| Admin UX repair | Complete | `16ab33a fix: repair admin management ux after phase two resources` | Repaired ContentItem edit tab behavior and related admin workflows. |
| Prompt 10 import/export | Complete | `fad6721 feat: extend phase two import export` | Native Filament import/export baseline exists and should be preserved by later prompts. |
| Post-Prompt-10 guidance sync | Complete | `773f1c0 docs: sync prompt workflow lessons after prompt ten` | Markdown-only guidance sync; did not start Prompt 11. |
| Post-Prompt-10 prompt-progress centralization cleanup | Complete | `1cb158a docs: centralize prompt progress and ai development lessons` | Markdown-only cleanup; centralized rolling progress in this file; did not start Prompt 11. |
| Prompt 11 public homepage/search | Complete | `7ef2fa7 feat: add public content item homepage search` | Public homepage/search lists `ContentItem` cards using public visibility rules, settings, filters, routes, and homepage section foundations. |
| Pre-Prompt-12 documentation pack | Complete | `c1237eb docs: add pre-prompt 12 documentation and guidelines for public admin contributors` | Adds Prompt 11R/11A/11B sequencing before Prompt 12 and ignores local Herd remote-site config. |
| Prompt 11R public frontend custom Livewire/Blade refactor | Complete | `bb4b97c refactor: customize public content item discovery` | Public homepage/search/category/tag listing no longer uses Filament Table as the public UI; custom Livewire state and Blade components render cards, filters, pagination, and homepage sections. |
| Prompt 11A admin relationship UX | Complete | `1d81ec0 feat: improve admin relationship management ux` | Adds safe admin create/edit option modals and `ContentGroupResource` → `ContentItemsRelationManager`; Prompt 12 not started. |
| Prompt 11B public contributors/transcribers discovery | Complete in this change set | Pending final commit `feat: add public contributor discovery` | Adds `top_transcribers`, public contributor directory, previews, full contributor page, and demo seeder state; Prompt 12 not started. |
| Prompt 12 readiness sync | Pending after Prompt 11B | Pre-Prompt-12 prompt pack | Docs-only sync before Prompt 12 activation. |
| Prompt 12 media embed/item page/parser | Pending after readiness sync, not started | Active prompt/blueprint | Owns public item page, media component, and parse-only parser/viewer. |
| Prompt 13 dashboard metrics | Pending after Prompt 12 | Active prompt/blueprint | Owns editorial dashboard widgets. |
| Prompt 14 viewer/studio future plan | Future planning after Prompt 13 | Active prompt/blueprint | Documentation/planning only. |
| Prompt 15 Filament Blueprint security audit | Audit after Prompt 14 | Active prompt/blueprint | Audit-only unless fixes are explicitly approved. |

## Active Known Blockers

- No active blocker is recorded for completing Prompt 11B, assuming the final quality gate remains clean.
- The `model:show` baseline issue below remains unresolved and should be avoided until investigated.

## Deferred Items

- `transcript_file` import support is deferred until an approved import package structure for referenced `.md`/`.txt` files exists.
- Curated homepage query sections are deferred until a concrete query-builder spec exists.
- Homepage result previews in admin forms remain deferred.
- Associate-existing transcription remains deferred because `Transcription` belongs to one `ContentItem`.
- A separate public volunteer/contributor profile table remains deferred; Prompt 11B uses `Author` as the public-safe contributor/transcriber entity.
- Public item page/media/parser work belongs to Prompt 12 and was not started by Prompt 11B.
- `ContentItemForm::featured_transcription_id` remains create-disabled; transcriptions are created through item-scoped relation manager/full Resource workflows.
- `TranscriptionForm::content_item_id` remains create-disabled; creating a content item inline from a transcript form is too large for a safe selector modal.
- `SpatieTagsInput` remains plugin-managed and was not replaced with custom pivot or modal behavior.
- The Add transcription table/relation-manager row action reuses the existing author selector and remains options-only because it is not a relationship-bound Resource form selector.
- Editorial dashboard widgets belong to Prompt 13.
- Viewer/studio sync planning belongs to Prompt 14; no sync/studio implementation is active yet.

## Tooling State

- Laravel: 13.18.0.
- PHP: 8.4.22 from `php artisan about`; Laravel Boost reports PHP 8.4.
- Filament: 5.6.7.
- Livewire: 4.3.3.
- Laravel Boost: 2.4.11 installed and available through MCP.
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

Laravel Boost MCP tools were exposed and usable during Prompt 10.

- Boost tools used: `application_info`, `database_schema`, and `search_docs`.
- Boost confirmed Laravel 13.17.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, and SQLite.
- Boost schema inspection confirmed the post-Prompt-08/09 tables and fields listed below.
- Boost `search_docs` was used for current Filament import/export APIs before code changes.
- FilamentExamples MCP `search_examples` returned snippet-level examples for `ImportAction`, `ExportAction`, `ExportBulkAction`, `Importer`, and `Exporter` patterns.
- Prompt 11 also used Boost `application_info`, `database_schema`, and `search_docs` before changing Livewire, Filament table/filter, URL-state, Spatie Settings, and settings-page behavior.
- Prompt 11 FilamentExamples research returned snippet/source examples for public Filament table, card, and filter patterns.
- Prompt 11R used Boost `application_info`, `database_schema`, and `search_docs` for Livewire URL state, pagination, Eloquent queries, settings, and Filament page context before changing code.
- Prompt 11R FilamentExamples research returned source snippets for public Filament table/filter examples; those snippets were used only to identify the prior table pattern to remove from the public listing.
- Prompt 11A used Boost `application_info`, detailed `database_schema`, and `search_docs` for Filament 5 `Select::relationship()`, option actions, relation managers, stable relation keys, shared forms, and `hiddenOn()` before changing code.
- Prompt 11A FilamentExamples research returned source snippets for relation-manager and selector/action patterns; access level was snippet/source through `search_examples`, not a full repository fetch.
- Prompt 11B used Boost `application_info`, `database_schema`, and `search_docs` for Livewire 4 URL attributes, `wire:model.live.debounce`, pagination, Laravel seeding, and public Filament page patterns before changing code.
- Prompt 11B FilamentExamples research returned snippet/source examples for custom multi-panel Filament Pages and Livewire-rendered page content; snippets were used as page-shell design reference, not copied wholesale.

## Application Shape

- Database driver: SQLite.
- Public panel root: `/`.
- Admin panel root: `/admin`.
- `php artisan route:list --path=contributors` reports the public contributor directory and contributor detail routes.
- Existing public pages remain:
  - `App\Filament\Public\Pages\BrowseContentGroups`
  - `App\Filament\Public\Pages\SearchContentItems`
  - `App\Filament\Public\Pages\BrowseCategoryContentItems`
  - `App\Filament\Public\Pages\BrowseTagContentItems`
  - `App\Filament\Public\Pages\BrowseContributors`
  - `App\Filament\Public\Pages\ShowContributor`
  - `App\Filament\Public\Pages\ShowContentGroup`
  - `App\Filament\Public\Pages\ShowContentItem`
- Existing public Livewire components remain:
  - `App\Livewire\Public\ContentGroupBrowser`
  - `App\Livewire\Public\ContentItemBrowser`
- Prompt 11 public homepage/search component:
  - `App\Livewire\Public\ContentItemSearch`
- Prompt 11B public contributor components:
  - `App\Livewire\Public\ContributorDirectory`
  - `App\Livewire\Public\ContributorContentItems`
- Prompt 11R public Blade components:
  - `resources/views/components/public/contributor-card.blade.php`
  - `resources/views/components/public/content-item-card.blade.php`
  - `resources/views/components/public/content-group-badge.blade.php`
  - `resources/views/components/public/content-item-grid.blade.php`
  - `resources/views/components/public/public-filter-panel.blade.php`
- Prompt 11 public card option mapper:
  - `App\Support\PublicContent\PublicContentCardOptions`
- Prompt 11B public query helpers:
  - `App\Support\PublicContent\PublicContentItemQueries`
  - `App\Support\PublicContent\PublicContributorDiscovery`
- Prompt 11A admin helper:
  - `App\Filament\Resources\Support\RelationshipOptionForms`
- Prompt 11A admin relation manager:
  - `App\Filament\Resources\ContentGroups\RelationManagers\ContentItemsRelationManager`

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
- `2026_07_02_000000_add_public_content_card_settings`: added by Prompt 11.

Local data reset note:

- Previous `migrate:status` output showed all migrations in batch 1, which strongly suggests the local database was rebuilt with `migrate:fresh --seed` or an equivalent reset path.
- The exact manual reset command was not observed.

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
- Public homepage/search pages now consume `PublicContentSettings`.

## Prompt 11 Public Homepage/Search Notes

- Prompt 11 is implemented.
- The public root `/` keeps the existing `BrowseContentGroups` root page class as a compatibility shell but renders `ContentItemSearch`; the homepage result unit is now `ContentItem`/episode cards, not `ContentGroup`/podcast cards.
- New public routes/pages exist for `/search`, `/categories/{categorySlug}`, and `/tags/{tagSlug}`.
- Public item listing visibility requires a published parent group, a published item, and at least one effective/main published transcription.
- Prompt 11R replaced the public Filament Table listing with custom Livewire state, `WithPagination`, URL-backed properties, and Blade-rendered card grids/rows.
- The reusable public item card view is now `resources/views/components/public/content-item-card.blade.php`; `resources/views/filament/tables/columns/public-content-item-card.blade.php` remains only as a compatibility wrapper.
- Public listing output no longer renders `{{ $this->table }}` or public Filament table markup as the primary UI.
- Public group badges are rendered through `resources/views/components/public/content-group-badge.blade.php`, including cover-image and title/initial fallback behavior.
- Card display is controlled by safe semantic Spatie settings, not raw CSS or Tailwind classes from the database.
- Prompt 11 card settings cover image size, density, title size, group badge visibility, authors/categories/tags/date/duration/description visibility, description line count, and cards per page.
- Semantic values are mapped in PHP through `PublicContentCardOptions`; Tailwind source scanning includes that support namespace.
- Public filters include custom Blade search, category with descendant and inherited group matching, enabled content tag, content group, author, provider, effective/original date ranges, duration, and media-presence controls.
- Sort options include latest/oldest transcription, title A-Z/Z-A, duration shortest/longest, and original newest/oldest.
- Homepage default ordering keeps valid pinned items first unless an explicit sort is selected.
- Visible ordered `HomepageSection` records now render as separate homepage sections for `latest`, `category`, `tag`, and `content_group`, each using `ContentItem` records and the shared card component.
- Prompt 11B adds `top_transcribers` homepage sections, rendered as public `Author` contributor cards ranked by published transcriptions on public content items.
- Curated homepage query sections remain deferred by the blueprint/spec.
- Transcript body search remains deferred and is not part of default live search.
- Public item page media/parser overhaul remains deferred to Prompt 12.

## Prompt 11B Public Contributor Discovery Notes

- Prompt 11B is implemented in this change set.
- Contributor/transcriber discovery uses `Author` as the public-safe contributor model. No `User` records are exposed publicly.
- New public routes exist:
  - `/contributors`
  - `/contributors/{authorSlug}`
- `ContributorDirectory` provides URL-backed live search with `#[Url(as: 'q', except: '')]`, paginates contributors, and stores selected preview contributor state in the URL as `contributor`.
- Contributor directory cards show public transcription counts and distinct public content item counts.
- Selecting a contributor card loads a live preview of related public `ContentItem` records through published transcriptions.
- Full contributor pages show the contributor name, safe-rendered public bio Markdown, counts, and paginated public `ContentItem` cards.
- Public contributor visibility/counting requires a published transcription by the author whose content item is public under existing public item rules: published group, published item, and effective/main published transcription.
- Contributor-related content item cards are still `ContentItem` records, never public `Transcription` cards.
- `DemoHebrewContentSeeder` remains idempotent and now creates a visible `top-transcribers` homepage section with stable slug `top-transcribers`.
- Public contributor profile records beyond `Author` remain deferred to a future contributor-profile prompt if needed.

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

## Prompt 11A Admin Relationship UX Notes

- Prompt 11A is implemented and committed locally as `1d81ec0 feat: improve admin relationship management ux`.
- Relationship selector policy:
  - Simple singular selectors get create and edit option modals.
  - Many-to-many selectors get create option modals only because installed Filament 5 does not expose edit-option actions for multiple selects.
  - Complex selectors stay create-disabled and use relation managers or full Resource pages.
- Shared modal schemas live in `App\Filament\Resources\Support\RelationshipOptionForms`.
- Create/edit option modals were added to these singular selectors:
  - `ContentItemForm::content_group_id`
  - `CategoryForm::parent_id`
  - `TranscriptionForm::author_id`
  - `TranscriptionsRelationManager::author_id`
  - `HomepageSectionForm::category_id`
  - `HomepageSectionForm::content_group_id`
- Create-only option modals were added to these medium/multiple selectors:
  - `ContentItemForm::authors`
  - `ContentItemForm::categories`
  - `ContentGroupForm::categories`
  - `HomepageSectionForm::tag_id`
- Intentionally unchanged complex selectors:
  - `ContentItemForm::featured_transcription_id`: create/edit transcriptions through the item transcriptions relation manager or full `TranscriptionResource`.
  - `TranscriptionForm::content_item_id`: creating content items inline from a transcript form is too large for a safe selector modal.
  - `SpatieTagsInput::make('tags')`: plugin-managed tag entry remains intact; no custom tag pivot or replacement selector was introduced.
  - Add transcription row action author selector: action data is not a relationship-bound Resource form selector, so it remains options-only while the action itself is reused.
- `ContentGroupResource` now registers `ContentItemsRelationManager` with stable relation key `contentItems`.
- `ContentItemsRelationManager` manages the owner group's `contentItems` relation, lists only current-group items, creates items through the owner context without submitting `content_group_id`, edits items in a modal, exposes delete actions consistently with existing admin conventions, links to the full `ContentItemResource` edit page, and reuses the existing Add transcription action.
- `ContentItemForm::content_group_id` is hidden on `ContentItemsRelationManager`; the owner relationship supplies the group.
- Prompt 11A did not start public contributors/transcribers discovery, public item pages, media embeds, parser work, import/export changes, or permissions work.
- Prompt 11B later implemented public contributors/transcribers discovery while leaving public item pages, media embeds, parser work, import/export changes, and permissions work untouched.

## Prompt 10 Import/Export Notes

- Prompt 10 is implemented.
- Native Filament importers/exporters now include:
  - `App\Filament\Imports\TranscriptionImporter`
  - `App\Filament\Exports\TranscriptionExporter`
  - `App\Filament\Imports\CategoryImporter`
  - `App\Filament\Exports\CategoryExporter`
- Existing importers/exporters were extended:
  - `ContentItemImporter` and `ContentItemExporter`
  - `ContentGroupImporter` and `ContentGroupExporter`
  - existing `AuthorExporter` date output was aligned to day-first date-time formatting.
- Transcription imports create/update `Transcription` child records and never write to legacy `content_items.transcript_markdown`.
- First imported transcription auto-feature behavior remains the existing model behavior and is covered by tests.
- `transcript_file` import support is deferred because the active blueprint/spec does not define an approved import package structure for locating referenced `.md`/`.txt` files. Inline `transcript_markdown` import is supported.
- Category import/export uses portable category paths such as `parent/child` and preserves hierarchy, visibility, sort order, and Markdown description.
- Content item and content group imports attach existing categories by path; missing category paths fail the row.
- Content item imports attach existing enabled Spatie content tags by slug/name using type `content`; missing tags, wrong-type tags, and disabled content tags fail the row.
- Prompt 10 preserves the Spatie tag decision: normal `tags` table, normal `taggables` pivot, `type = content`, and no custom `content_item_tag` pivot.
- Content item import/export now covers pin fields, media metadata fields, category paths, content tag slugs, and `featured_transcription_reference_key`.
- Content group import/export now covers category paths and `homepage_order`.
- Exporters use portable identifiers only: reference keys, category paths, and typed tag slugs. Numeric database IDs are not exported as portable identifiers.
- Exported date-times use `dd/mm/yyyy HH:mm` in `Asia/Jerusalem`; imported day-first date-times are normalized to Laravel storage.
- Exported user/content text is formula-escaped where exporter APIs expose formatting. Failed import rows continue through native Filament failed-row behavior.
- Native `ImportAction`, `ExportAction`, and `ExportBulkAction` are registered for content groups, content items, categories, and transcriptions. Existing author import/export compatibility remains.
- Prompt 10 did not implement public homepage/search, public item page/parser work, dashboard widgets, or studio/sync work.
- Prompt 11 later implemented public homepage/search while preserving Prompt 10 import/export behavior.

## Homepage and Settings Notes

- `HomepageSectionResource` is treated as homepage content configuration: records define which content slices appear on the homepage.
- `HomepageSectionForm` is type-driven:
  - `latest` does not require a category, tag, or content group target.
  - `category` requires `category_id`.
  - `tag` requires `tag_id`.
  - `content_group` requires `content_group_id`.
  - `top_transcribers` does not require a category, tag, or content group target.
  - `curated_query` remains deferred.
- Homepage settings and homepage sections have separate responsibilities:
  - `PublicContentSettings` stores global defaults, limits, and layout choices.
  - `HomepageSection` records configure ordered content slices.
  - Item pinning is separate and affects item ordering where public queries support it.
- Prompt 11 reads `PublicContentSettings` and visible ordered `HomepageSection` records when building the public homepage/search UI.

## Browser Regression Tests

- Pest browser testing is present.
- `tests/Browser/AdminContentItemBrowserTest.php` visits a ContentItem edit page in a real browser.
- The browser test asserts the item details tab label, title field, slug field, content group field, status field, media URL field, and transcriptions tab are visible.
- This test protects the `getContentTabLabel()` repair from regressing into an empty details tab.

## Prompt 12 Readiness Notes

- Prompt 10 is complete.
- Prompt 11 is complete.
- Prompt 11R is complete and committed as `bb4b97c refactor: customize public content item discovery`.
- Prompt 11A is complete and committed as `1d81ec0 feat: improve admin relationship management ux`.
- Prompt 11B is complete in this change set and ready for final commit.
- Prompt 12 readiness sync and Prompt 12 activation remain pending after Prompt 11B.
- Prompt 12 has not started.
- Prompt 12 must preserve the Prompt 10 import/export behavior and Prompt 11/11R content-item homepage/search behavior plus Prompt 11B contributor discovery routes and `top_transcribers` sections.
- Prompt 12 owns public item page media/parser work and must not add dashboard widgets or studio/sync behavior.

## Post-Prompt-10 Guidance Sync Notes

- Active prompt workflow guidance now records the requirement to run preflight, read the blueprint/spec stack, stop on conflicts, and classify blueprint completion in final reports.
- Successful implementation prompts must update relevant active Markdown state files before the final commit, not only code and tests.
- Prompt 11 started from the Prompt 10 import/export baseline and did not modify import/export behavior.
- This guidance sync changed Markdown only and did not start Prompt 11; Prompt 11 was implemented later.

## Baseline Issue To Record

`php artisan model:show App\Models\ContentItem` and `php artisan model:show App\Models\ContentGroup` previously failed with a class redeclare fatal. This documentation sync did not retest or fix that application issue. Future implementation prompts should avoid relying on `model:show` until the cause is investigated.
