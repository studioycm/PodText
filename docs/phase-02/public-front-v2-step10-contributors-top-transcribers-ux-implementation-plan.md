# Public Front v2 Step 10 Contributors and Top Transcribers UX Implementation Plan

## Purpose

Implement the full Step 10 public contributor/transcriber UX that Step 9R intentionally deferred, while preserving the current Public Front v2 architecture and keeping Step 9F/footer-builder work out of scope.

## Preflight And Current State

- Working tree is clean on `main` tracking `origin/main`.
- Step 9R Menu/Header UX Fixes is complete.
- Step 9R Podcast Episode Grid Settings follow-up is committed and present in history.
- Prompt 13 has not started.
- Baseline checks passed before planning:
  - `php artisan test --filter=PublicContributorDiscoveryTest`
  - `php artisan test --filter=PublicStep9RMenuHeaderUxFixesTest`
  - `php artisan test --filter=PublicPodcastsGroupsUxTest`

## Current Contributor Route / Component Inventory

Routes and pages:

- `/contributors` renders `App\Filament\Public\Pages\BrowseContributors`.
- `/contributors/{authorSlug}` renders `App\Filament\Public\Pages\ShowContributor`.
- Both pages use `HidesPublicPageHeader` and custom Blade page headings.

Livewire components:

- `App\Livewire\Public\ContributorDirectory`
  - owns directory search, sort, per-page, selected contributor id, and preview search.
  - state is URL-backed for search, selected contributor, per-page, sort, and preview search.
- `App\Livewire\Public\ContributorContentItems`
  - renders full contributor page item cards.
  - currently has no search/sort controls and uses global card page size.
- `App\Livewire\Public\ContentItemSearch`
  - currently renders homepage `contributors` and `top_transcribers` sources as a static contributor-card grid.

Support classes:

- `App\Support\PublicContent\PublicContributorDiscovery`
  - already centralizes public contributor aggregate queries.
  - counts `public_transcriptions_count` and `public_content_items_count`.
- `App\Support\PublicContent\PublicContentItemQueries`
  - provides the published public `ContentItem` base query.

Blade:

- `resources/views/livewire/public/contributor-directory.blade.php`
- `resources/views/livewire/public/contributor-content-items.blade.php`
- `resources/views/components/public/contributor-card.blade.php`
- `resources/views/livewire/public/content-item-search.blade.php`
- `resources/views/components/public/content-item-grid.blade.php`

## Current Homepage `top_transcribers` Behavior

`PublicDisplaySectionQueryResolver` resolves both `contributors` and `top_transcribers` to a collection of public contributors. `content-item-search.blade.php` renders that collection as a static grid of normal contributor cards.

Limitations:

- No horizontal selector.
- No selected contributor preview below the selector.
- No 5 / 10 / 15 preview page-size controls.
- No grouped transcription names under related item cards.

## Current Contributor Directory Behavior After Step 9R

Already present:

- compact cards show contributor name and transcription count badge only;
- compact card click selects the contributor through Livewire;
- no compact direct page action;
- preview is a separate row below the list;
- preview contains the contributor page link;
- preview search is Livewire-owned;
- directory page sizes are 10 / 15 / 20;
- sorting toggles A-Z, Z-A, count down, and count up;
- preview item grid renders as cards.

Step 10 refinements:

- load settings-driven labels and page-size/sort defaults;
- normalize selection when URL state references a non-public contributor;
- show public counts in the preview;
- group and display contributor-specific transcription titles under preview item cards;
- add settings-driven preview item grid columns.

## Current Full Contributor Page Behavior

Already present:

- page displays contributor name, public transcription count, public item count, and safe bio Markdown;
- related content renders as `ContentItem` cards only;
- public item query excludes unpublished/draft/no-public-transcription content.

Step 10 refinements:

- use contributor labels from `contributors_page` config;
- add URL-backed search and sort controls for related public items;
- add semantic grid/page-size settings;
- show grouped transcription names for same-author/same-item cases;
- keep safe Markdown rendering and public `ContentItem` cards.

## Query / Counting Rules

Use `Author` as the public contributor/transcriber model.

Counting:

- Count published `Transcription` records where `transcriptions.author_id = authors.id`.
- A transcription only counts when its parent `ContentItem::published()` is public, which already includes published group, published item, and at least one published transcription.
- Authors with zero public transcriptions are excluded.
- If the same author has two published transcriptions on the same item:
  - count two public transcriptions;
  - render one `ContentItem` card;
  - show the two transcription titles in a compact list under that item card.

Sorting:

- `count_desc`, `count_asc`, `name_asc`, `name_desc` for contributor lists.
- Contributor item lists support latest/oldest transcription and title ascending/descending.

N+1 prevention:

- keep aggregate count subqueries in `PublicContributorDiscovery`;
- eager-load public contributor transcriptions on related item queries with an author-constrained relation load.

## Settings / Config Keys To Add

Add `contributors_page` to `public_content` JSON settings.

Default shape:

```json
{
  "enabled": true,
  "title": "Contributors",
  "description": "Browse public transcribers",
  "label_singular": "Contributor",
  "label_plural": "Contributors",
  "item_label_singular": "item",
  "item_label_plural": "items",
  "directory": {
    "per_page_options": [10, 15, 20],
    "default_per_page": 10,
    "default_sort": "count_desc",
    "sort_options": ["name_asc", "name_desc", "count_desc", "count_asc"],
    "preview_items_per_page": 6,
    "preview_grid_columns": 3,
    "preview_search_enabled": true
  },
  "top_transcribers": {
    "enabled": true,
    "limit": 8,
    "layout": "horizontal",
    "preview_default_page_size": 5,
    "preview_page_size_options": [5, 10, 15],
    "preview_grid_columns": 3,
    "show_full_page_link": true,
    "show_count_badge": true
  },
  "cards": {
    "compact_show_count": true,
    "compact_count_icon": "document-text",
    "preview_show_bio": true,
    "preview_show_counts": true
  },
  "page": {
    "items_per_page": 12,
    "page_size_options": [6, 12, 24],
    "default_sort": "latest_transcription",
    "sort_options": ["latest_transcription", "oldest_transcription", "title_asc", "title_desc"],
    "search_enabled": true,
    "grid_columns": 3,
    "grid_gap": "comfortable"
  }
}
```

Validation rules:

- labels: plain strings with bounded lengths;
- booleans: boolean / 0 / 1 only;
- sort/layout/icon/gap values: finite semantic tokens only;
- page-size lists: integer lists constrained to approved ranges;
- no raw CSS, Tailwind classes, PHP classes, Blade paths, SQL, unsafe HTML, or JavaScript URLs.

## Top-Transcriber Section Design

- Add `App\Livewire\Public\TopTranscribersSection`.
- It receives the section key, section heading/view-more URL, section limit, card template key, and display config.
- It reads `contributors_page.top_transcribers` from `PublicFrontConfigReader`.
- It renders:
  - a horizontal compact contributor selector;
  - selected contributor preview underneath;
  - public transcription count and optional link to the full contributor page;
  - latest grouped public items/transcriptions;
  - page-size controls 5 / 10 / 15.
- State remains Livewire-owned. Homepage top-transcriber preview does not need URL-backed state.
- If settings disable top transcribers or no public contributors exist, render a normal empty state.

## Contributor Directory / Page Design

Directory:

- keep compact card semantics;
- apply settings-driven page-size/sort options;
- show count badge from transcription count;
- show selected preview counts when enabled;
- render contributor preview item cards in a multi-column grid with grouped transcription titles.

Full contributor page:

- page title/description use settings where practical;
- item controls are URL-backed;
- search filters by item title, group title, and contributor-specific transcription title;
- sorting is finite and normalized;
- cards stay `ContentItem` cards;
- grouped transcription titles are displayed under each card.

## Card / Template / Grid Strategy

- Keep `x-public.contributor-card` for compact contributor cards.
- Keep `x-public.content-item-grid` for `ContentItem` cards.
- Extend `content-item-grid` with semantic column/gap inputs already used by the podcast grid follow-up.
- Add a small contributor-transcription list partial/component if needed for grouped transcription titles.
- Do not read raw classes from JSON.
- Do not render public Filament Tables.

## Integration With Step 9F Future Section/Footer Plan

Step 10 should inform Step 9F but not implement it.

After implementation:

- update `docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md`;
- note that top-transcriber preview grids need reusable semantic grid controls;
- keep footer/rich section builder after Step 10 and before Step 11 if approved;
- do not add footer schema/classes here.

## Exact Files To Change

Expected app files:

- `app/Settings/PublicContentSettings.php`
- `app/Support/PublicFront/PublicFrontConfigRegistry.php`
- `app/Support/PublicFront/PublicFrontConfigValidator.php`
- `app/Filament/Pages/PublicContentSettings.php`
- `app/Support/PublicContent/PublicContributorDiscovery.php`
- `app/Livewire/Public/ContributorDirectory.php`
- `app/Livewire/Public/ContributorContentItems.php`
- `app/Livewire/Public/TopTranscribersSection.php`
- `resources/views/livewire/public/contributor-directory.blade.php`
- `resources/views/livewire/public/contributor-content-items.blade.php`
- `resources/views/livewire/public/top-transcribers-section.blade.php`
- `resources/views/livewire/public/content-item-search.blade.php`
- `resources/views/components/public/content-item-grid.blade.php`
- `resources/views/components/public/contributor-transcription-list.blade.php`
- `resources/views/filament/public/pages/browse-contributors.blade.php`
- `resources/views/filament/public/pages/show-contributor.blade.php`
- `lang/en/admin.php`
- `lang/he/admin.php`
- `lang/en/public.php`
- `lang/he/public.php`
- a settings migration under `database/settings`

Expected docs/tests:

- `docs/research/public-front-v2/14-step10-contributors-top-transcribers-mcp-research.md`
- `docs/phase-02/public-front-v2-step10-contributors-top-transcribers-ux-implementation-plan.md`
- `docs/phase-02/public-front-v2-step10-contributors-top-transcribers-ux-handoff.md`
- `docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md`
- `docs/phase-02/current-project-state.md`
- `tests/Feature/PublicContributorsTopTranscribersUxTest.php`
- `tests/Feature/PublicContributorDiscoveryTest.php` if existing assertions need adjustment.

## Tests To Add / Update

Add focused coverage in `PublicContributorsTopTranscribersUxTest`:

- contributor directory compact cards show only name + count badge;
- compact cards do not include direct page action links;
- selecting a contributor opens preview;
- preview contains the contributor page link;
- preview search filters related public items;
- page-size options 10 / 15 / 20 work;
- sort toggles work;
- preview item grid is multi-column by default;
- top-transcribers homepage section renders horizontal selector/list;
- selecting top transcriber renders preview under selector;
- top-transcriber preview supports page sizes 5 / 10 / 15;
- counts include only published transcriptions attached to public items;
- authors with no public transcriptions are hidden;
- duplicate same-author/same-item transcriptions count twice but render one item card;
- full contributor page excludes draft/unpublished/no-effective-transcription content;
- contributor settings normalize and affect labels/layout/page sizes;
- no `ContributorProfile`, `VolunteerProfile`, public `User` exposure, `Podcast`, `Episode`, `PublicFooter`, `FooterSection`, `PublicMenu`, or `PublicMenuItem` models are introduced;
- no public Filament Table markup is introduced.

Regression filters listed in the prompt will be run after focused tests.

## Out Of Scope

- Step 9F / 10F Footer + Rich Section Builder implementation.
- Step 11 seed/demo data cleanup.
- Prompt 13 dashboard metrics.
- Prompt 14 / Prompt 15.
- Step 2 transcription publication policy.
- Generic CMS/page management.
- ContributorProfile / VolunteerProfile / public User exposure.
- Public form uploads or notifications.
- New Podcast/Episode models.
- Public Filament Tables.
