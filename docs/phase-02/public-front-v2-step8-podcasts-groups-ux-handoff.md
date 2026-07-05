# Public Front v2 Step 8 Podcasts and Groups UX Handoff

## Purpose

Step 8 adds canonical public podcast/group discovery while preserving the internal `ContentGroup` and `ContentItem` architecture. Public visitors can browse `/podcasts`, search and filter public groups, and open public group pages that list only public episodes/items with published transcriptions.

## What was implemented

- Canonical public podcasts index at `/podcasts`.
- Canonical public group detail route at `/podcasts/{contentGroupSlug}`.
- Custom Livewire and Blade rendering for podcast/group browsing.
- Public-only group query support through `PublicContentGroupQueries`.
- Podcast/group cards with cover/fallback, configured label, description, categories, and public episode count.
- Category toggle filters with visible category descendants.
- Search by group title, group description, visible category name, and public item title.
- Group detail headers with cover/fallback, safe Markdown description, visible categories, and public episode count.
- Group detail item list with custom Livewire search/sort, pagination, descriptions, and Step 5 content-item cards.
- JSON-first `podcasts_page` settings and admin controls on the existing public settings page.

## Final routes

- `/podcasts`: `App\Filament\Public\Pages\BrowsePublicContentGroups`
- `/podcasts/{contentGroupSlug}`: `App\Filament\Public\Pages\ShowContentGroup`
- `/groups` and `/groups/{contentGroupSlug}` are not public routes.
- Admin `admin/content-groups` routes remain unchanged.

## Final namespaces and classes

- `App\Filament\Public\Pages\BrowsePublicContentGroups`
- `App\Filament\Public\Pages\ShowContentGroup`
- `App\Livewire\Public\ContentGroupBrowser`
- `App\Livewire\Public\ContentItemBrowser`
- `App\Support\PublicFront\Groups\PublicContentGroupQueries`
- Existing `ContentGroup`, `ContentItem`, `Category`, `Author`, and `Transcription` models remain the domain model surface.

## Final public API for future prompts

Runtime config reads use:

```php
$result = app(PublicFrontConfigReader::class)->read();
$podcastsPage = $result->group('podcasts_page');
```

Group templates use:

```php
$template = app(PublicFrontCardTemplateResolver::class)->resolve(
    family: 'content_group',
    key: $podcastsPage['template_key'] ?? null,
);
```

Group page item templates use:

```php
$template = app(PublicFrontCardTemplateResolver::class)->resolve(
    family: 'content_item',
    key: $podcastsPage['item_template_key'] ?? null,
);
```

Public group queries use:

```php
$query = PublicContentGroupQueries::base();
```

## Podcasts/groups config schema

`public_content.podcasts_page`:

```json
{
  "enabled": true,
  "title": "Podcasts",
  "description": "Browse podcasts with public episodes and published transcriptions.",
  "group_label_singular": "Podcast",
  "group_label_plural": "Podcasts",
  "cards_per_page": 12,
  "category_filter_enabled": true,
  "search_enabled": true,
  "template_key": null,
  "item_template_key": null,
  "show_description": true,
  "show_categories": true,
  "show_episode_count": true,
  "group_page": {
    "show_description": true,
    "show_categories": true,
    "show_episode_descriptions": true,
    "items_per_page": 12
  }
}
```

## Public query and visibility rules

A public group requires:

- published `ContentGroup`;
- at least one `ContentItem::published()` child;
- public episode count from the same `ContentItem::published()` scope.

`ContentItem::published()` continues to require:

- published item;
- published parent group;
- at least one published child transcription with transcript Markdown.

Group detail pages use the same group query by slug, so published groups without public items return 404.

## Podcasts index behavior

The index renders a public page shell and mounts `ContentGroupBrowser`. The component keeps search and categories URL-backed, supports newest/title sort, paginates with `podcasts_page.cards_per_page`, and renders cards through `x-public.content-group-card`.

## Category/search behavior

- Search matches group title, group description, visible group category names, visible public item category names, and public item titles.
- Category toggles are visible categories only.
- Filtering includes selected visible categories and visible descendants.
- Filtering matches direct group categories and direct categories on public child items.
- Invalid category ids result in no group matches.

## Podcast/group card behavior

Cards show:

- cover image from `cover_path` or initials fallback;
- configured public singular label;
- group title;
- optional safe plain-text description excerpt;
- optional visible category chips;
- optional public episode count.

Cards expose Step 3 template compatibility attributes such as `data-card-template-family` and `data-card-template-key`. They do not render raw classes, CSS, HTML, Blade paths, or PHP class names from JSON.

## Group page behavior

Group pages show:

- back link to `/podcasts`;
- square cover image or initials fallback;
- configured public singular label;
- group title;
- public episode count;
- optional visible category links;
- optional safe Markdown group description;
- public episode/item list only.

The item list uses `ContentItemBrowser`, `PublicContentItemQueries::base()`, URL-backed search and sort, pagination from `podcasts_page.group_page.items_per_page`, and `x-public.content-item-grid` in row layout.

## Template integration

- `/podcasts` resolves `content_group` templates through `podcasts_page.template_key`.
- `/podcasts/{slug}` resolves `content_item` templates through `podcasts_page.item_template_key`.
- Missing or invalid template keys fall back through the existing Step 3 resolver.
- Step 5 controlled content-item presentation metadata remains the renderer for group-page episode rows.

## Admin settings UI behavior

The existing `App\Filament\Pages\PublicContentSettings` page now includes a Podcasts page section for:

- enable/disable;
- title and description;
- public singular/plural labels;
- cards per page;
- category filter and search toggles;
- content-group and content-item template selectors;
- card visibility toggles;
- detail-page visibility and pagination toggles.

Helper text states that public labels do not rename `ContentGroup` or `ContentItem`.

## Route/backward-compatibility decision

The active Step 8 prompt made `/podcasts` authoritative. The older blueprint mentioned keeping `/groups` unless route changes were approved; this prompt is that approval. No `/groups` public redirect was added. The old `/groups/{contentGroupSlug}` public detail route is absent and returns 404.

## Fallback and invalid config behavior

- Missing `podcasts_page` settings fall back to registry defaults.
- Unknown `podcasts_page` keys are reported as invalid config and ignored.
- Invalid booleans, page sizes, labels, and semantic template keys are normalized or rejected by `PublicFrontConfigValidator`.
- Disabled `podcasts_page.enabled` causes both `/podcasts` and `/podcasts/{slug}` to return 404.
- Missing template keys fall back to default family templates.

## Security rules

- Draft groups are never listed or resolved.
- Groups without public items are never listed or resolved.
- Draft/future items and items without published transcriptions are not counted or listed.
- Group descriptions render through the safe public Markdown component.
- Cards display text through escaped Blade output.
- Public pages continue to avoid public Filament Table markup.

## Sample JSON payloads

Minimal label override:

```json
{
  "podcasts_page": {
    "title": "Shows",
    "group_label_singular": "Show",
    "group_label_plural": "Shows"
  }
}
```

Template selection:

```json
{
  "podcasts_page": {
    "template_key": "homepage_group_row",
    "item_template_key": "episode_row_compact"
  }
}
```

## Sample PHP usage

```php
$config = app(PublicFrontConfigReader::class)
    ->read()
    ->group('podcasts_page');

$groups = PublicContentGroupQueries::base()
    ->tap(fn ($query) => PublicContentGroupQueries::applySearch($query, request('q', '')))
    ->paginate($config['cards_per_page'] ?? 12);
```

## Blueprint deviations

- Route path uses `/podcasts`, not `/groups`, because the active Step 8 prompt explicitly made `/podcasts` canonical.
- The implementation did not add a `PublicGroupDisplayConfig` value object or `GroupPageLayout` enum; existing normalized settings arrays and card-template support classes were sufficient and matched Step 1/3 patterns.
- Search includes public item titles in addition to group title/description/category matching.

## Impact on later prompts

- Step 9 Public Menu and Header should link to the new `podcasts` route key, which now resolves to `/podcasts`.
- Step 10 Contributors and Top Transcribers UX should keep contributor item cards as `ContentItem` records and can link group badges to `/podcasts/{contentGroupSlug}`.
- Step 11 Seeders, Demo Data, Assets, and Cleanup can seed podcast cover paths and visible categories without creating `Podcast` or `Episode` models.
- Step 2 / Reserved Transcription Publication Policy remains deferred; Step 8 keeps the current effective/published transcription behavior.
- Prompt 13 Dashboard Metrics has not started; future widgets can count public groups through `PublicContentGroupQueries::base()` if useful.

## Open issues / follow-up decisions

- No `/groups` compatibility redirect exists. Add one only if a later prompt explicitly approves public route backward compatibility.
- Demo cover image imports and seeder cleanup remain Step 11 work.
- Public menu/header exposure remains Step 9 work.
- Step 2 transcription publication policy remains deferred/reserved.

## Tests and quality gate summary

Added `tests/Feature/PublicPodcastsGroupsUxTest.php`.

Focused and public regression tests passed before the full gate:

- `php artisan test --filter=PublicPodcastsGroupsUxTest`
- `php artisan test --filter=PublicAboutPageContentTeamTest`
- `php artisan test --filter=PublicFormsSubmissionsTest`
- `php artisan test --filter=PublicLatestSearchUxTest`
- `php artisan test --filter=PublicHomepageSearchTest`
- `php artisan test --filter=PublicDisplaySectionsLoopersTest`
- `php artisan test --filter=PublicFrontCardTemplateBuilderTest`
- `php artisan test --filter=PublicContributorDiscoveryTest`
- `php artisan test --filter=PublicItemPageMediaParserTest`
- `php artisan test --filter=PublicPanelTest`
- `php artisan test --filter=PublicTranscriptionVisibilityTest`

Full quality gate passed:

- `php artisan test`: 195 tests passed, 1549 assertions.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed with 0 issues.
- `npm run build`: passed.
