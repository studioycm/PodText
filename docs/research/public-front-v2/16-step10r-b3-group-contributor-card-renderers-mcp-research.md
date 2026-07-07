# Step 10R-B3 Group and Contributor Card Renderers MCP Research

## Scope

Mini-step: Step 10R-B3 - Content group and contributor card template renderers.

Goal: add controlled rendering/presentation for `content_group` and `contributor` card template families so custom templates visibly affect `/podcasts`, homepage content group sections, contributor directory cards, top-transcriber selector cards, and contributor homepage cards. Content item rendering from Step 10R-B2 is the local pattern to mirror.

## Preflight

- `git status --short --branch`: clean, `main...origin/main [ahead 2]`.
- `git log --oneline --decorate -30`: current `HEAD` is `e3c81de feat: render content item card template parts`; Step 10R-B1 and A2/A1 commits are present.
- `php artisan migrate:status`: all current Phase 02/Public Front v2 migrations are applied through `2026_07_06_000006_add_public_contributors_page_settings`.
- `php artisan route:list --path=podcasts`: public podcast index/detail routes exist.
- `php artisan route:list --path=contributors`: public contributor index/detail routes exist.
- `php artisan route:list --path=search`: public search route exists.
- Prompt 13 has not started.
- Step 11 has not started.
- Step 2 transcription publication policy remains deferred/reserved.
- Documentation note: B2 was committed as `e3c81de`, while some B2 docs still said "pending final commit"; B3 will reconcile those stable state references.

## Laravel Boost

### Tools Used

- `application_info`
- `database_schema` summary
- `database_schema` filtered to `content_groups`, `authors`, `homepage_sections`, and `settings`
- `search_docs`

### Application Versions

- PHP 8.4
- Laravel 13.18.0
- Filament 5.6.7
- Livewire 4.3.3
- Pest 4.7.4
- Tailwind CSS 4.3.2
- Laravel Boost 2.4.11
- SQLite local database

### Schema Findings

- `content_groups` has public card fields needed by B3: `title`, `slug`, type labels, description Markdown, `cover_path`, status/published fields, and `homepage_order`.
- `authors` has public contributor fields needed by B3: `name`, `slug`, and `bio_markdown`.
- `homepage_sections` has JSON `display_config` / `source_config` that already resolve card template family/key for content group and contributor homepage sections.
- `settings` stores Spatie settings payloads by `group` and `name`; no schema changes are required.

### Documentation Findings

Searches that succeeded after reducing broad queries:

- `Blade components`
- `withCount eager loading relationships`
- `Livewire URL query parameters pagination testing`
- `Pest response assertSeeInOrder assertDontSeeHtml`

Relevant installed-version guidance:

- Blade components should receive prepared data and continue using escaped `{{ }}` output for user/content text.
- Eloquent `with`, nested eager loading, and `withCount` / subquery counts prevent N+1 card rendering.
- Livewire URL and pagination behavior should remain owned by the component and not move into Blade or Alpine.
- Laravel/Pest response assertions can cover rendered HTML markers and visible order.

PodText adaptation:

- B3 should keep Livewire URL-backed state unchanged.
- B3 should reuse existing public queries, counts, and card template resolution.
- B3 should move group/contributor card preparation into presenters and keep Blade as a renderer shell.

## FilamentExamples MCP

Access level: search/snippet access only. No source/read/fetch/details tool was exposed.

### Initial Query Batches

Batch 1:

- `content group card grid`
- `contributor card component`
- `profile card view data`
- `custom card renderer`

Relevant results:

- `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`
- `v4/full-projects/github-style-user-profile-with-activity-heatmap/app/Filament/Resources/Users/Pages/ViewUser.php`
- `v4/tables/table-customized-design-viewcolumn/app/Filament/Resources/DefaultAccounts/Tables/DefaultAccountsTable.php`

Patterns to copy:

- Prepare card/profile view data in PHP classes before Blade.
- Eager-load or precompute relationships/counts before custom card views.
- Use controlled card layouts and view data instead of repeated ad hoc view queries.

Patterns to avoid:

- Do not reintroduce public Filament Tables for public card grids.
- Do not use arbitrary rendered HTML/Blade strings from settings JSON.

### Refined Query Pass

Batch 2:

- `getViewData profile card`
- `custom view column cards`
- `modifyQueryUsing eager load cards`
- `public page card grid`

Batch 3:

- `author profile cards`
- `eager load counts cards`
- `custom public page cards`
- `Livewire card grid`

Relevant results:

- Profile page examples reinforced `getViewData()` style preparation.
- Custom ViewColumn examples used `modifyQueryUsing(fn (Builder $query) => $query->with(...))` before rendering custom cells.
- Table-as-card-grid examples used Filament `contentGrid`, but PodText should keep the existing public Livewire/Blade card grids.

PodText adaptation:

- Add family-specific presenters in `App\Support\PublicFront\Cards`.
- Keep public route URLs generated by app route helpers.
- Keep all card parts constrained to finite type/source/attribute maps already normalized by the config validator.
- Preserve existing query boundaries and no public Filament Tables.

## Local Code Findings

Relevant existing B2 pattern:

- `PublicContentItemCardPresenter` prepares content item URL, image source, title, description, categories/tags, metadata, and part arrays.
- `PublicFrontCardTemplateRenderer::contentItemParts()` filters visible parts to supported finite part types.
- `resources/views/components/public/content-item-card.blade.php` loops `media_parts` and `body_parts` with `data-card-part` markers.

Current B3 gaps:

- `resources/views/components/public/content-group-card.blade.php` still computes URLs, cover URLs, excerpts, categories, counts, initials, image fit/radius, and template attributes directly in Blade.
- `resources/views/components/public/contributor-card.blade.php` still computes contributor initials, counts, bio excerpt, selected classes, and compact/full branching directly in Blade.
- Both group and contributor cards expose `data-card-template-*`, but template part order/visibility/custom text does not control actual output.
- Default content group templates include `entity_attribute`, `title`, `description`, `metadata_row`, and `action_link`.
- Default contributor templates include `title`, transcription/content item `metadata_row`, `description`, and `action_link`.

## B3 Decisions

Implement:

- Add `PublicContentGroupCardPresenter`.
- Add `PublicContributorCardPresenter`.
- Extend `PublicFrontCardTemplateRenderer` with `contentGroupPresentation()`, `contributorPresentation()`, `contentGroupParts()`, and `contributorParts()`.
- Update `content-group-card.blade.php` and `contributor-card.blade.php` to render prepared finite parts.
- Keep existing public cards broadly compatible with default templates.
- Add focused tests in the existing public front card template test file.

Do not implement:

- Step 10R-B4 legacy card-options convergence.
- Step 10R-C1 transcription-author attribution correction.
- Step 10R-C2 full semantic layout-token normalization.
- Any new contributor template key settings.
- Any public Filament Tables, forbidden models, raw classes, raw Blade paths, raw HTML, or schema changes.
