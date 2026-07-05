# Public Front v2 Step 5 Latest and Search UX Handoff

## Purpose

Public Front v2 Step 5 turns Latest into a section-driven public looper and upgrades the public search page from inline filters to a drawer-based custom Livewire and Blade experience. It builds on Step 1 JSON settings, Step 3 card template resolution, and Step 4 display section results.

## What was implemented

- Latest homepage sections now use `PublicDisplaySectionResolver` results and normalized section config.
- Latest sections support lightweight search, top previous/next controls, and bottom load-more controls depending on pagination mode.
- Search filters now open in a custom Blade/Alpine drawer while Livewire owns all filter state.
- Categories and tags are multi-select toggles, URL-backed where practical.
- Public content item cards use deterministic layout classes from a controlled renderer instead of reading arbitrary classes from JSON.
- Card image/text layout was repaired for square images, row cards, large-image cards, line clamps, and RTL-safe text columns.

## Final namespaces and classes

- `App\Livewire\Public\ContentItemSearch`
- `App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer`
- `App\Support\PublicFront\Sections\PublicDisplaySectionConfigValidator`
- `App\Support\PublicFront\Sections\PublicDisplaySectionQueryResolver`
- `resources/views/livewire/public/content-item-search.blade.php`
- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-item-grid.blade.php`
- `resources/views/components/public/public-filter-panel.blade.php`

## Final public API for future prompts

Future public homepage/search work should keep using:

```php
$sections = app(PublicDisplaySectionResolver::class)
    ->resolveMany($homepageSections);
```

For cards, resolve templates through the Step 3 resolver and ask the renderer for controlled presentation metadata:

```php
$template = app(PublicFrontCardTemplateResolver::class)->resolve(
    family: $displayConfig['template_family'] ?? 'content_item',
    key: $displayConfig['template_key'] ?? null,
    overrides: $displayConfig['template_overrides'] ?? [],
);

$presentation = app(PublicFrontCardTemplateRenderer::class)
    ->contentItemPresentation($template, fallbackLayout: 'cards');
```

Do not read raw `HomepageSection` JSON in public Blade. Public Blade should consume resolved section results and normalized config arrays.

## Latest section behavior

Latest is treated as a `latest_content_items` looper section. The resolver loads a normalized public-safe query window, then the Livewire component applies lightweight per-section search and local page/window controls.

Latest search is intentionally small in scope and searches visible latest result titles plus group titles. Heavy filter controls remain excluded from Latest.

## Search page filter drawer behavior

The search page keeps the main search field and sort selector visible. Filter controls open from an action button into a slide-over drawer.

Alpine owns only drawer visibility, escape handling, and close/open behavior. Livewire owns filter values, active filter counts, clearing filters, and query execution.

## Pagination and load-more behavior

Step 5 supports the Step 4 pagination modes:

- `none`: renders the normalized section result window without section controls.
- `simple`: shows top previous/next controls.
- `next_previous`: shows top previous/next controls.
- `load_more`: shows a bottom load-more control.

Latest page size is normalized to the range 4 to 25. Latest total query size is normalized to at least 50, with the existing upper cap preserved by the section config validator.

Infinite scroll remains deferred.

## Card layout/rendering changes

Content item cards now expose controlled layout metadata with data attributes for tests and future CSS audits. Text columns use `min-w-0`, image wrappers use `overflow-hidden`, and square images use `aspect-square` plus `object-cover`.

Large image mode forces a stacked card layout instead of row layout. This avoids image overflow into detail columns and keeps title/description clamps predictable.

## Template renderer changes

`PublicFrontCardTemplateRenderer` now includes a practical controlled content-item presentation renderer. It accepts normalized template semantics and returns safe fixed class lists and metadata for public content-item cards.

Supported controlled part types for this step are:

- `image`
- `title`
- `description`
- `group_identity`
- `transcriber_line`
- `date_read_time`
- `taxonomy`
- `metadata_row`
- `action_link`

Unsupported or invalid parts are skipped for rendering metadata and do not break public output. Step 5 does not render arbitrary Blade paths, arbitrary HTML, raw CSS, or raw Tailwind classes from JSON.

## Admin preview status

Full admin card-template live preview remains deferred as:

- Step 5B Card Template Admin Preview UX

The current implementation keeps the public renderer safe and practical without broad admin preview architecture.

## Query/visibility rules

Public content item visibility remains unchanged:

- parent `ContentGroup` must be published;
- `ContentItem` must be published;
- the item must have an effective/main published transcription.

Search filters, homepage latest sections, section loops, category filters, tag filters, and manual section results continue to use public-safe content item queries. Public tags are limited to enabled `content` tags.

## Fallback and invalid config behavior

Invalid section source config still resolves through Step 4 invalid config reporting and safe fallbacks. Latest pagination config falls back to safe normalized values.

Invalid card template parts are ignored by the controlled renderer. If a requested template key is unavailable, the Step 3 resolver fallback template is used.

## Security rules

- No public Filament Table rendering was reintroduced.
- No arbitrary Blade view path, PHP class, raw HTML, raw CSS, raw Tailwind class, raw SQL, or JavaScript URL is rendered from JSON.
- Public item queries preserve published group/item/effective transcription constraints.
- Disabled tags do not appear as public filter chips.
- Alpine does not own authoritative persisted filter state.

## Sample JSON payloads

Latest section pagination payload:

```json
{
  "source_config": {
    "source_type": "latest_content_items",
    "total_limit": 50
  },
  "display_config": {
    "template_family": "content_item",
    "template_key": "compact",
    "template_overrides": {
      "layout": "cards",
      "image_size": "square",
      "density": "comfortable"
    }
  },
  "pagination_config": {
    "mode": "load_more",
    "per_page": 8,
    "total_limit": 50
  }
}
```

Search URL payload shape:

```text
/search?q=history&sort=oldest&categories=1,3&tags=2,4
```

## Sample PHP usage

Render a section card template from a resolved section:

```php
$template = app(PublicFrontCardTemplateResolver::class)->resolve(
    family: $section->displayConfig['template_family'] ?? 'content_item',
    key: $section->displayConfig['template_key'] ?? null,
    overrides: $section->displayConfig['template_overrides'] ?? [],
);

$presentation = app(PublicFrontCardTemplateRenderer::class)
    ->contentItemPresentation($template);
```

## Blueprint deviations

- Full admin card-template live preview was not implemented because it requires broader admin preview architecture. It is explicitly deferred as Step 5B.
- A full generic part-by-part renderer for every Step 3 family and part type was not implemented. Step 5 ships the practical controlled content-item renderer needed by Latest and Search.

## Impact on later prompts

- Step 6 Public Forms and Submissions should keep public forms separate from search/filter drawer state and continue using custom Livewire/Blade public UI.
- Step 7 About Page Content and Team Builder can reuse the controlled renderer pattern if team/member cards need semantic template rendering.
- Step 8 Podcasts and Groups UX should reuse the section resolver and avoid raw homepage section JSON in Blade.
- Step 9 Public Menu and Header should preserve URL-backed search state and not move authoritative search filters into Alpine.
- Step 10 Contributors and Top Transcribers UX can follow the same drawer/toggle/card renderer conventions for contributor filters if needed.
- Step 11 Seeders, Demo Data, Assets, and Cleanup should seed latest/search section JSON using normalized values in this handoff.
- Step 2 / Reserved Transcription Publication Policy remains deferred; Step 5 does not change effective/main transcription visibility rules.
- Prompt 13 Dashboard Metrics has not started. Metrics should treat Step 5 as public UX behavior, not as analytics/search logging.

## Open issues / follow-up decisions

- Step 5B Card Template Admin Preview UX remains open.
- Infinite scroll remains deferred.
- Transcript body search remains deferred and is not part of lightweight Latest search.
- Curated query schema remains deferred.

## Tests and quality gate summary

Added `tests/Feature/PublicLatestSearchUxTest.php` and updated the public homepage search card layout expectation for large-image stacking.

Focused tests run during implementation:

- `php artisan test --filter=PublicLatestSearchUxTest`
- `php artisan test --filter=PublicHomepageSearchTest`
- `php artisan test --filter=PublicDisplaySectionsLoopersTest`
- `php artisan test --filter=PublicFrontCardTemplateBuilderTest`
- `php artisan test --filter=PublicContributorDiscoveryTest`
- `php artisan test --filter=PublicItemPageMediaParserTest`

Final full gate before commit:

- `php artisan test`: passed, 167 tests and 1319 assertions.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed, 0 issues.
- `npm run build`: passed.
