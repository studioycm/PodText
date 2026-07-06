# Public Front v2 Step 9R Follow-up Podcast Episode Grid Settings Plan

## Purpose

Add safe JSON-first settings for the podcast detail page episode/item grid under the existing `podcasts_page.group_page` config. This is a focused Step 9R follow-up and does not start Step 10 Contributors and Top Transcribers UX.

## Preflight

- Branch `main` was clean before implementation.
- Recent history includes Step 8, Step 9, and Step 9R commits.
- Baseline `php artisan test --filter=PublicPodcastsGroupsUxTest` passed: 10 tests, 59 assertions.
- Prompt 13 has not started.
- Step 2 transcription publication policy remains deferred/reserved.

## FilamentExamples MCP Search Subjects

Short subjects used, each 2-5 terms:

- `settings page tabs`
- `grid card controls`
- `Livewire sort filter`
- `pagination page size`
- `responsive card grid`
- `radio toggle filters`
- `section form schema`
- `public cards search`
- `contentGrid pagination options`
- `ToggleButtons filters`
- `Radio inline options`
- `Livewire URL filters`
- `public Livewire table`

## FilamentExamples MCP Findings

Only `search_examples` was exposed, so access was snippet/search access only.

Useful patterns:

- `v4/tables/table-as-grid-with-cards/app/Filament/Resources/Users/UserResource.php`
  - Relevant pattern: card-grid column settings, explicit pagination page options, filters above content.
  - PodText adaptation: use semantic grid/page-size settings but keep custom Blade/Livewire public rendering, not public Filament Tables.
- `v4/forms/large-employee-form-with-sections/.../EmployeeForm.php`
  - Relevant pattern: full-width sections with internal compact columns.
  - PodText adaptation: add nested fieldsets inside the existing Podcasts tab.
- `v4/tables/public-products-table/app/Livewire/Products.php`
  - Relevant pattern: public Livewire-owned search/filter state.
  - PodText adaptation: do not reintroduce Filament Tables; keep `ContentItemBrowser` as owner of URL-backed state.

## Proposed Settings

Extend `public_content.podcasts_page.group_page`:

```json
{
  "items_layout": "cards",
  "items_grid_columns": 3,
  "items_grid_gap": "comfortable",
  "items_per_page": 12,
  "page_size_options": [6, 12, 24, 48],
  "per_page_selector_enabled": true,
  "search_enabled": true,
  "sort_enabled": true,
  "category_filter_enabled": true,
  "default_sort": "latest_transcription",
  "sort_options": [
    "latest_transcription",
    "oldest_transcription",
    "title_asc",
    "title_desc",
    "original_newest",
    "original_oldest",
    "duration_longest",
    "duration_shortest"
  ],
  "item_density": "comfortable",
  "item_image_size": "medium",
  "item_image_fit": "cover",
  "item_image_radius": "mid_rounded",
  "item_title_size": "base",
  "show_episode_authors": true,
  "show_episode_tags": true,
  "show_episode_duration": true,
  "show_episode_effective_date": true
}
```

Existing keys remain compatible:

- `show_description`
- `show_categories`
- `show_episode_descriptions`
- `items_per_page`

## Implementation Plan

1. Add registry option helpers and defaults for podcast detail item layouts, grid columns, gaps, sort options, and page-size options.
2. Extend `PublicFrontConfigValidator` to normalize the new nested `group_page` keys and reject unknown/raw values.
3. Add a settings migration to backfill existing `podcasts_page.group_page` payloads with safe defaults.
4. Add Podcasts tab settings fields under the existing Podcast detail page fieldset, split into item grid, controls, and card display groups.
5. Extend `ContentItemBrowser` to support URL-backed per-page and category filter state, configured sort options, configured default sort, and configured layout.
6. Extend `x-public.content-item-grid` to accept semantic column/gap settings and render test markers.
7. Add translations in English and Hebrew.
8. Add focused Pest coverage to `PublicPodcastsGroupsUxTest`.
9. Update Step 9R handoff/current state docs with the follow-up.

## Out Of Scope

- No `Podcast` or `Episode` models.
- No public Filament Tables.
- No full Step 10 contributor/top-transcriber UX.
- No full footer or rich section builder.
- No Step 2 transcription publication policy.
- No Prompt 13 dashboard metrics.
