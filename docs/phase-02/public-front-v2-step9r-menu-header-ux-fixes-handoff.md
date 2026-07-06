# Public Front v2 Step 9R Menu/Header UX Fixes Handoff

## Purpose

Step 9R repairs remaining public menu/header and UX issues after Step 9, verifies Step 8 and Step 9 implementation against their plans, improves MCP research discipline, and splits future footer/rich-section-builder work out of this prompt.

## MCP research protocol added

- Added durable FilamentExamples MCP guidance to `AGENTS.md` and `.ai/guidelines/tooling-quality.md`.
- Added the same process expectation to `docs/phase-02/public-front-v2-agent-usage-index.md`, `docs/phase-02/tooling-and-quality-gates.md`, and `docs/phase-02/ai-development-lessons.md`.
- Recorded Step 9R searches in `docs/research/public-front-v2/13-step9r-menu-header-ux-fixes-mcp-research.md`.
- Research was run as focused batches plus a refined second pass. Only `search_examples` was exposed; no source/read/fetch details tool was available.

## Step 8 verification summary

Step 8 `/podcasts` and group detail behavior is present and preserved. The verification matrix confirmed canonical `/podcasts`, `/podcasts/{contentGroupSlug}`, public group queries, podcast settings, group cards/detail browsers, no `Podcast`/`Episode` models, and no public Filament Tables.

## Step 9 verification summary

Step 9 settings tabs, About/team card support, H1-H6 Markdown classes, contributor compact list/preview foundations, homepage section headers, `content_block` support, and JSON-powered menu/header exist. Step 9R repaired the partial items: root query homepage chrome, preview grid layout, logo/search/alignment/theme settings, and image styling.

## Step 10 overlap decision

Step 9R only repairs contributor directory behavior already touched by Step 9: compact cards, Livewire-owned preview, preview search, sort/page-size controls, preview link placement, and preview related item grid layout.

Full top-transcribers homepage redesign, horizontal contributor selectors, top-transcriber previews, contributor preview item pagination inside top-transcriber sections, and broader contributor-page UX remain Step 10.

## Section/footer future plan

Full footer manager and rich section-builder work is deferred. The plan lives at `docs/phase-02/public-front-v2-step9f-section-footer-builder-plan.md`.

Recommended sequence: Step 9R, Step 10, then Step 9F/10F Footer + Rich Section Builder foundation before Step 11 seeders, then Prompt 13.

## Discovery chrome/root query fix

`ContentItemSearch` now keeps `context === 'home'` in homepage section mode even when root query parameters exist. `/search` still renders discovery chrome, search, filters, and sort controls.

## Page title/chrome fix

Public Filament page classes with custom public H1s now use `App\Filament\Public\Pages\Concerns\HidesPublicPageHeader`, which returns an empty page header view. This suppresses redundant fixed Filament page titles without removing meaningful public page headings.

## Logo settings behavior

`menu_config.logo` supports storage-managed `header/` light/dark logo paths, alt text, display mode, and semantic size. If no configured logo is valid, the public header falls back to `public/images/podtext-logo.jpg`.

SVG is allowed only as a validated storage-managed image path, not as raw inline SVG or arbitrary HTML.

## Theme selector modes

`menu_config.theme_selector.mode` still controls available theme options:

- `light_dark`
- `light_dark_system`

`menu_config.theme_selector.display_mode` controls rendering:

- `text`
- `text_icon`
- `icon`
- `trigger_icon_menu`

The selector stores preference in local storage and toggles the document dark class. It does not introduce a package.

## Heading typography fix

`SafeMarkdownRenderer::publicContentClasses()` already provides explicit H1-H6 public heading classes. Step 9R adds focused coverage for H1-H6 and keeps About Markdown/RichEditor rendering on the existing safe renderer path.

## Image styling settings

Added safe semantic image settings for:

- homepage/search item cards: fit, radius, group badge mode, combined-title separator, duplicate-thumbnail behavior;
- display defaults: fit and radius;
- podcast/group cards: fit and radius;
- About team cards: fit and radius;
- About image blocks: fit and radius.

All values map through fixed PHP/Blade class maps. JSON does not store raw Tailwind classes.

## Contributor directory fixes

The contributor preview related item list now renders with `layout="cards"` and exposes `data-test="contributor-preview-items-grid"`. Selection, preview search, sorting, and pagination state remain Livewire-owned.

## Item image fallback and group badge behavior

Item cards resolve image sources in this order:

1. item `external_thumbnail_url`;
2. parent group `cover_path`;
3. text fallback.

Group badges support `name_only`, `thumbnail_name`, and `combined_title`. Thumbnail mode suppresses duplicate group thumbnails when the same group cover is already the main card image unless explicitly allowed.

## Podcast detail episode grid settings follow-up

The Step 9R follow-up adds focused podcast detail episode/item grid settings under `public_content.podcasts_page.group_page`.

The public group detail item list remains `App\Livewire\Public\ContentItemBrowser` plus Blade components. No public Filament Table was introduced.

New behavior:

- episode layout can be `cards` or `rows`;
- card grid can use 1-4 desktop columns and semantic gap tokens;
- search, category filter, sort, and page-size controls can be enabled/disabled independently;
- sort options are constrained to known public item sorts;
- default sort is normalized against allowed sort options;
- public page-size selector uses configured page-size options;
- episode cards can override density, image size/fit/radius, title size, and metadata visibility.

Category filters are scoped to visible categories connected to public items inside the selected podcast or to the current group.

## Header global search

`menu_config.search` controls a lightweight header search form:

- `enabled`
- `placeholder`
- `route_key`
- `query_param`

The form submits a normal GET request to the known route, usually `/search?q=...`. Livewire search state remains owned by the target page.

## Menu alignment behavior

`menu_config.items_alignment` supports `start`, `center`, and `end`. The header maps those tokens to fixed flex classes and exposes `data-menu-alignment` for tests.

## Final namespaces/classes changed

- `App\Filament\Pages\PublicContentSettings`
- `App\Filament\Public\Pages\Concerns\HidesPublicPageHeader`
- public Filament page classes under `App\Filament\Public\Pages`
- `App\Livewire\Public\ContentItemSearch`
- `App\Livewire\Public\PublicHeader`
- `App\Settings\PublicContentSettings`
- `App\Support\PublicContent\PublicContentCardOptions`
- `App\Support\PublicFront\Menu\PublicMenuConfigReader`
- `App\Support\PublicFront\Menu\PublicMenuRenderer`
- `App\Support\PublicFront\PublicFrontConfigRegistry`
- `App\Support\PublicFront\PublicFrontConfigValidator`

## Final public API for future prompts

Runtime config remains:

```php
$result = app(PublicFrontConfigReader::class)->read();
$menuConfig = $result->group('menu_config');
```

Header rendering should continue through:

```blade
<livewire:public.public-header />
```

Public form actions continue to dispatch:

```js
window.dispatchEvent(new CustomEvent('open-public-form', {
  detail: { formKey: 'request_transcription' },
}));
```

## Settings/JSON schema changes

`menu_config` now includes:

- `items_alignment`
- `logo.light_path`
- `logo.dark_path`
- `logo.alt_text`
- `logo.display_mode`
- `logo.size`
- `search.enabled`
- `search.placeholder`
- `search.route_key`
- `search.query_param`
- `theme_selector.display_mode`

Scalar public card settings added:

- `homepage_card_image_fit`
- `homepage_card_image_radius`
- `homepage_group_badge_mode`
- `homepage_group_title_separator`
- `homepage_group_badge_duplicate_thumbnail`

JSON display settings added:

- `display_defaults.image_fit`
- `display_defaults.image_radius`
- `podcasts_page.image_fit`
- `podcasts_page.image_radius`
- `podcasts_page.group_page.items_layout`
- `podcasts_page.group_page.items_grid_columns`
- `podcasts_page.group_page.items_grid_gap`
- `podcasts_page.group_page.page_size_options`
- `podcasts_page.group_page.per_page_selector_enabled`
- `podcasts_page.group_page.search_enabled`
- `podcasts_page.group_page.sort_enabled`
- `podcasts_page.group_page.category_filter_enabled`
- `podcasts_page.group_page.default_sort`
- `podcasts_page.group_page.sort_options`
- `podcasts_page.group_page.item_density`
- `podcasts_page.group_page.item_image_size`
- `podcasts_page.group_page.item_image_fit`
- `podcasts_page.group_page.item_image_radius`
- `podcasts_page.group_page.item_title_size`
- `podcasts_page.group_page.show_episode_authors`
- `podcasts_page.group_page.show_episode_tags`
- `podcasts_page.group_page.show_episode_duration`
- `podcasts_page.group_page.show_episode_effective_date`
- `about_page.settings.team_card.image_fit`
- `about_page.settings.team_card.image_radius`
- About image block `image_fit` and `image_radius`

## Fallback and invalid config behavior

- Invalid menu alignment falls back to `center`.
- Invalid logo paths are rejected; only `header/*.(jpg|jpeg|png|webp|svg)` paths are accepted.
- Invalid search route keys fall back to defaults or disable rendering if no route resolves.
- Invalid theme display modes fall back to `text_icon`.
- Disabled or missing form menu items are skipped server-side.
- Non-HTTPS external URL menu items remain rejected by existing validation.

## Security rules

- No raw Blade paths, PHP classes, JavaScript, unsafe HTML, CSS classes, or arbitrary iframe content are accepted.
- External menu URLs must be HTTPS.
- Logo/image paths are storage-managed and extension constrained.
- Markdown/RichEditor output remains sanitized through app-owned renderers.
- Public visibility rules remain unchanged.

## Sample JSON payloads

```json
{
  "enabled": true,
  "items_alignment": "center",
  "logo": {
    "light_path": "header/logo.svg",
    "dark_path": "header/logo-dark.webp",
    "alt_text": "PodText",
    "display_mode": "image_text",
    "size": "medium"
  },
  "search": {
    "enabled": true,
    "placeholder": "Search episodes",
    "route_key": "search",
    "query_param": "q"
  },
  "theme_selector": {
    "enabled": true,
    "mode": "light_dark_system",
    "display_mode": "text_icon"
  },
  "items": []
}
```

## Blueprint deviations

- Full footer manager was not implemented by design; it is documented for Step 9F/10F.
- Full Step 10 contributor/top-transcriber redesign was not implemented.
- Nested/dropdown menu builder remains deferred.
- The public header default logo fallback preserves `public/images/podtext-logo.jpg`; configured SVG uploads are allowed only as safe image paths.

## Impact on later prompts

Step 10 Contributors and Top Transcribers UX should build on the repaired contributor directory and keep full top-transcriber redesign in its own prompt. It can reuse the `ContentItemBrowser` grid/control patterns if contributor-specific item grids need similar semantic controls, but should not merge full top-transcriber work into this follow-up.

Step 11 Seeders/Demo Data/Assets/Cleanup should seed only stable Step 9R schema, including the podcast episode grid settings, plus any later Step 10/Step 9F schema that has landed.

Step 2 / Reserved Transcription Publication Policy remains deferred/reserved and was not implemented.

Prompt 13 Dashboard Metrics has not started and should wait for the explicit dashboard prompt or the public-front readiness decision.

## Open issues / follow-up decisions

- Decide whether Step 9F/10F Footer + Rich Section Builder foundation runs immediately after Step 10.
- Decide whether nested/dropdown public menu editing is still wanted after the header/search/logo repairs.
- Complete any broader contributor-page UX refinements in Step 10, not in Step 9R.

## Tests and quality gate summary

Focused tests passed:

- `php artisan test --filter=PublicPodcastsGroupsUxTest`
- `php artisan test --filter=PublicStep9RMenuHeaderUxFixesTest`
- `php artisan test --filter=PublicMenuHeaderUxFixesTest`

Final gate results:

- Step 9R podcast episode grid follow-up baseline: `php artisan test --filter=PublicPodcastsGroupsUxTest` passed, 10 tests / 59 assertions.
- Step 9R podcast episode grid follow-up focused result: `php artisan test --filter=PublicPodcastsGroupsUxTest` passed, 13 tests / 99 assertions.
- `php artisan test --filter=PublicStep9RMenuHeaderUxFixesTest`: passed, 8 tests / 85 assertions.
- `php artisan test --filter=PublicMenuHeaderUxFixesTest`: passed, 7 tests / 70 assertions.
- Public regression filters for podcasts/groups, About, forms, latest/search, homepage search, display sections, card templates, contributors, and item page/media/parser passed.
- `php artisan test`: passed, 210 tests / 1730 assertions.
- `vendor/bin/pint --dirty --format agent`: passed.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed with 0 issues.
- `npm run build`: passed.
