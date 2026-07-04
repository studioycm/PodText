# Public Front v2 Step 4 Display Sections and Loopers Handoff

## Purpose

Add the foundation for homepage display sections and generalized public loopers/query displays without starting Step 5 Latest/Search UX. This step extends existing homepage section records with semantic JSON config and routes public homepage rendering through a typed resolver layer.

## What was implemented

- Extended `HomepageSection` with four JSON config columns and safe array helpers.
- Added `App\Support\PublicFront\Sections` support classes for config normalization, invalid-config reporting, source query resolution, and view-ready section results.
- Updated `ContentItemSearch` homepage section rendering to use the new resolver while preserving Prompt 11R custom Livewire + Blade output.
- Updated `HomepageSectionForm` with semantic source, selection, display/template, and pagination fields.
- Added focused Step 4 tests covering public visibility, config normalization, admin save behavior, template integration, and backward compatibility.

## Final migrations and schema

Migration:

```text
database/migrations/2026_07_04_221810_add_public_front_config_to_homepage_sections_table.php
```

New nullable JSON columns on `homepage_sections`:

- `source_config`
- `selection_config`
- `display_config`
- `pagination_config`

The migration is reversible and `php artisan migrate` was run locally.

## Final namespaces and classes

New namespace:

```text
App\Support\PublicFront\Sections
```

Classes:

- `PublicDisplaySectionRegistry`
- `PublicDisplaySectionConfigValidator`
- `PublicDisplaySectionConfigResult`
- `PublicDisplaySectionResolver`
- `PublicDisplaySectionQueryResolver`
- `PublicDisplaySectionResult`

Updated existing classes:

- `App\Models\HomepageSection`
- `App\Livewire\Public\ContentItemSearch`
- `App\Filament\Resources\HomepageSections\Schemas\HomepageSectionForm`

## Final public API for future prompts

Runtime section resolution:

```php
$result = app(PublicDisplaySectionResolver::class)->resolve($homepageSection);
$sections = app(PublicDisplaySectionResolver::class)->resolveMany($homepageSections);
```

Config validation:

```php
$result = app(PublicDisplaySectionConfigValidator::class)->validate($homepageSection);
$normalized = $result->config();
$invalid = $result->invalidConfigArray();
```

Step 3 template integration remains:

```php
$template = app(PublicFrontCardTemplateResolver::class)->resolve(
    family: $displayConfig['template_family'] ?? 'content_item',
    key: $displayConfig['template_key'] ?? null,
    overrides: $displayConfig['template_overrides'] ?? [],
);
```

## HomepageSection JSON schema

`source_config` chooses the source and source-specific options.

`selection_config` adds include/exclude ID constraints.

`display_config` controls safe presentation semantics and card template selection.

`pagination_config` stores normalized looper pagination hints for this and later prompts.

Existing typed fields remain active for backward compatibility:

- `type`
- `category_id`
- `tag_id`
- `content_group_id`
- `limit`
- `sort_order`
- `is_visible`

## Source types

Implemented:

- `latest_content_items`
- `category_content_items`
- `tag_content_items`
- `content_group_items`
- `manual_content_items`
- `content_groups`
- `categories`
- `contributors`
- `top_transcribers`

Deferred:

- `curated_query`, because there is no approved safe query-builder schema yet.

## Selection config

Shape:

```json
{
  "include_ids": [1, 2, 3],
  "exclude_ids": [4, 5]
}
```

IDs are runtime database IDs for Step 4. Portable import/export identifiers are deferred until a later portability requirement exists. Public rendering still rechecks public visibility after include/exclude selection.

## Display config

Shape:

```json
{
  "template_key": "compact_episode",
  "template_family": "content_item",
  "template_overrides": {
    "layout": "rows",
    "density": "compact",
    "image_size": "small",
    "title_size": "lg"
  },
  "heading": "Latest",
  "show_heading": true,
  "show_view_all_link": true,
  "view_all_route_key": "search"
}
```

Only semantic values are accepted. Blade paths, Tailwind classes, raw CSS, HTML, JavaScript URLs, SQL-looking strings, and arbitrary PHP classes are rejected.

## Pagination config

Shape:

```json
{
  "mode": "load_more",
  "per_page": 6,
  "page_size_options": [6, 12, 18],
  "total_limit": 50
}
```

Supported modes:

- `none`
- `simple`
- `load_more`
- `next_previous`

Infinite scroll is deferred. Step 4 stores/normalizes pagination config but does not redesign Latest/Search UX.

## Template integration

Sections resolve card templates through `PublicFrontCardTemplateResolver`. Public cards keep the compatibility path and expose `data-card-template-*` attributes through `PublicFrontCardTemplateRenderer::compatibilityAttributes()`.

Step 4 applies semantic overrides for layout, density, image size, and title size. It does not implement full part-by-part visual rendering or live template preview.

## Admin settings/resource changes

`HomepageSectionForm` now includes semantic sections for:

- source config;
- manual include/exclude selection;
- display config and card template key/family;
- pagination config.

Legacy type-driven category/tag/content-group fields remain. Manual selection v1 is explicit item include/exclude multi-selects; advanced select-all-filtered behavior is deferred.

## Query/visibility rules

Content item sources use the shared public content item query path and require:

- published content group;
- published content item;
- published effective/main transcription availability.

Category sources support descendants and inherited group categories. Tag sources require enabled `content` tags. Content group sources require published groups. Manual sources require selected IDs and still filter through public visibility. Contributor/top-transcriber sources reuse existing public contributor discovery/counting rules.

## Fallback and invalid config behavior

Invalid public section config never throws during rendering.

- Unknown source types are reported and skipped.
- Unknown sort/pagination/template family values are reported and defaulted where safe.
- Missing template keys fall back through the Step 3 resolver to family defaults.
- Unsafe strings are reported and dropped.
- Empty JSON config on existing sections falls back to legacy typed fields.

## Security rules

Rejected or ignored:

- raw CSS;
- raw Tailwind class strings;
- raw SQL;
- arbitrary PHP class names;
- arbitrary Blade paths;
- unsafe HTML and iframe/script HTML;
- JavaScript URLs;
- unknown source/sort/pagination/template family values;
- invalid template override values.

Public queries never expose draft/unpublished records.

## Sample JSON payloads

Latest content items:

```json
{
  "source_config": {
    "source_type": "latest_content_items",
    "sort": "latest_transcription",
    "direction": "desc",
    "total_limit": 50
  },
  "pagination_config": {
    "mode": "none",
    "per_page": 6,
    "total_limit": 50
  }
}
```

Manual compact rows:

```json
{
  "source_config": {
    "source_type": "manual_content_items"
  },
  "selection_config": {
    "include_ids": [1, 2, 3],
    "exclude_ids": [2]
  },
  "display_config": {
    "template_family": "content_item",
    "template_key": "compact_episode",
    "template_overrides": {
      "layout": "rows",
      "density": "compact",
      "image_size": "small",
      "title_size": "lg"
    }
  }
}
```

## Sample PHP usage

```php
$sections = HomepageSection::query()
    ->visible()
    ->ordered()
    ->get();

$viewSections = app(PublicDisplaySectionResolver::class)
    ->resolveMany($sections);
```

## Blueprint deviations

- Advanced manual selection controls such as select-all-filtered were deferred as allowed by the prompt.
- `content_groups` and `categories` sources use simple safe public cards/lists rather than a new full visual renderer.
- Pagination modes are normalized and stored, but Step 4 does not implement new Latest/Search pagination UX.
- Route key `podcasts` currently maps to the existing public content-groups root until Step 8 Podcasts and Groups UX owns the final page behavior.

## Impact on later prompts

Step 5 Latest and Search UX should consume `PublicDisplaySectionResolver`, `PublicDisplaySectionResult`, and normalized pagination config instead of reading raw `HomepageSection` JSON.

Step 6 Public Forms and Submissions can use the Step 1 public-front config architecture, but Step 4 does not add forms sources.

Step 7 About Page Content and Team Builder should continue using Step 1 config and may reuse section display/template semantics if about sections need cards.

Step 8 Podcasts and Groups UX can build on the `content_groups` source and replace the temporary `podcasts` route-key mapping with final podcast/group behavior.

Step 9 Public Menu and Header can link to known route keys without depending on raw section URLs.

Step 10 Contributors and Top Transcribers UX can refine `contributors` and `top_transcribers` display without changing the public counting source.

Step 11 Seeders, Demo Data, Assets, and Cleanup can seed JSON configs on `HomepageSection` records. The PodText logo at `public/images/podtext-logo.jpg` remains preserved.

Step 2 / Reserved Transcription Publication Policy remains deferred. Step 4 keeps current featured/effective transcription behavior.

Prompt 13 Dashboard Metrics has not started and remains after Public Front v2 readiness unless explicitly chosen sooner.

## Open issues / follow-up decisions

- Define safe `curated_query` schema before enabling curated query sources.
- Decide whether section config should later use portable identifiers for import/export.
- Implement actual `simple`, `load_more`, and `next_previous` UX in Step 5.
- Implement full visual card part renderer and live template preview in the later public UX/card work.
- Decide final podcasts route/page behavior in Step 8.

## Tests and quality gate summary

Added:

```text
tests/Feature/PublicDisplaySectionsLoopersTest.php
```

Updated:

```text
tests/Feature/PublicHomepageSearchTest.php
```

Focused tests run during implementation:

```bash
php artisan test --filter=PublicDisplaySectionsLoopersTest
php artisan test --filter=PublicHomepageSearchTest
php artisan test --filter=PublicContributorDiscoveryTest
php artisan test --filter=PublicItemPageMediaParserTest
php artisan test --filter=PublicFrontCardTemplateBuilderTest
```

Final full-gate results should be checked in the final report.
