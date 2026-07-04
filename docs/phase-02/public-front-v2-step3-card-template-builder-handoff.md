# Public Front v2 Step 3 Card Template Builder Handoff

## Purpose

This handoff records the Card Template Builder foundation implemented in Public Front v2 Step 3. It is intended for ChatGPT/Yoni review before Step 4 Public Display Sections and Loopers starts.

Step 3 builds on the final Step 1 JSON Settings Architecture. It does not introduce a parallel settings system, settings-only models, looper queries, latest/search redesign, public forms, About/team content, podcast UX, public menu/header work, seeders, dashboard metrics, or the deferred transcription publication policy.

## What was implemented

- Extended `public_content.card_templates` from a placeholder list into a normalized JSON-first card template schema.
- Added safe registry access for card families, part types, sources, attributes, layouts, icon keys, text-size keys, URL targets, and default templates.
- Added a small card-template support layer under `App\Support\PublicFront\Cards`.
- Added default templates for `content_item`, `content_group`, and `contributor`.
- Added a Filament Repeater + Builder admin editing section to the existing `App\Filament\Pages\PublicContentSettings` page.
- Added a compatibility rendering path that resolves templates and exposes safe `data-card-template-*` attributes while preserving current card Blade output.
- Added focused Pest coverage for normalization, invalid config handling, settings-page save behavior, public rendering compatibility, and settings-only model exclusions.

## Final namespaces and classes

New support namespace:

- `App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry`
- `App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver`
- `App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer`
- `App\Support\PublicFront\Cards\PublicFrontCardTemplate`
- `App\Support\PublicFront\Cards\PublicFrontCardPart`

Changed existing classes:

- `App\Support\PublicFront\PublicFrontConfigRegistry`
- `App\Support\PublicFront\PublicFrontConfigValidator`
- `App\Filament\Pages\PublicContentSettings`
- `App\Livewire\Public\ContentItemSearch`
- `App\Livewire\Public\ContributorDirectory`
- `App\Livewire\Public\ContributorContentItems`
- `App\Livewire\Public\ContentGroupBrowser`

Changed public Blade components:

- `resources/views/components/public/content-item-card.blade.php`
- `resources/views/components/public/content-item-grid.blade.php`
- `resources/views/components/public/content-group-card.blade.php`
- `resources/views/components/public/contributor-card.blade.php`

## Final public API for future prompts

Runtime config reads still use the Step 1 API:

```php
$result = app(PublicFrontConfigReader::class)->read();
$config = $result->config();
$cardTemplates = $result->group('card_templates');
$invalidConfig = $result->invalidConfigArray();
```

Validation/normalization still uses:

```php
$result = app(PublicFrontConfigValidator::class)->validate($rawConfig);
$safeConfig = $result->config();
```

Template resolution for public rendering and future section configs:

```php
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;

$template = app(PublicFrontCardTemplateResolver::class)->resolve(
    family: 'content_item',
    key: $sectionDisplayConfig['template_key'] ?? null,
    overrides: $sectionDisplayConfig['template_overrides'] ?? [],
);
```

Compatibility renderer attributes:

```php
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer;

$attributes = app(PublicFrontCardTemplateRenderer::class)
    ->compatibilityAttributes($template);
```

Registry discovery:

```php
PublicFrontConfigRegistry::cardFamilies();
PublicFrontConfigRegistry::cardPartTypes();
PublicFrontConfigRegistry::cardSources();
PublicFrontConfigRegistry::cardAttributes();
PublicFrontConfigRegistry::cardAttributeOptions('content_item');
PublicFrontConfigRegistry::defaultCardTemplates();
```

## Card template JSON schema

The final storage shape is a flat list under `public_content.card_templates`, not the older nested `families` wrapper from the initial research sketch.

Template fields:

```json
{
  "key": "compact_episode",
  "label": "Compact episode card",
  "family": "content_item",
  "layout": "cards",
  "density": "comfortable",
  "image_size": "medium",
  "title_size": "base",
  "parts": []
}
```

Supported families:

- `content_item`
- `content_group`
- `contributor`

Supported template semantic values:

- `layout`: `cards`, `rows`
- `density`: `compact`, `comfortable`
- `image_size`: `hidden`, `small`, `medium`, `large`
- `title_size`: `sm`, `base`, `lg`

Supported part types:

- `image`
- `title`
- `description`
- `metadata_row`
- `entity_attribute`
- `group_identity`
- `transcriber_line`
- `date_read_time`
- `taxonomy`
- `custom_text`
- `action_link`
- `divider`
- `spacer`

Supported part fields:

- `type`
- `source`
- `attribute`
- `label`
- `label_position`
- `icon`
- `icon_position`
- `layout`
- `visible`
- `order`
- `line_clamp`
- `font_size`
- `url_target`
- `text`

Admin Builder input may arrive as:

```json
{
  "type": "title",
  "data": {
    "source": "content_item",
    "attribute": "title"
  }
}
```

The validator stores normalized plain JSON:

```json
{
  "type": "title",
  "source": "content_item",
  "attribute": "title",
  "visible": true,
  "order": 10,
  "layout": "inline"
}
```

## Default templates

Default keys:

- `default_content_item`
- `default_content_group`
- `default_contributor`

`default_content_item` includes semantic parts for image, group identity, title, description, transcriber line, transcription date/read-time foundation, duration metadata, categories, and tags.

`default_content_group` includes image, type label, title, description, public item count, and action-link foundation.

`default_contributor` includes contributor name, public transcription count, public content item count, bio, and action-link foundation.

The defaults are code defaults in `PublicFrontCardTemplateRegistry`. They are not persisted into settings unless a future seeder prompt chooses to seed them.

## Registry and validator changes

`PublicFrontConfigRegistry` now exposes card-template registry helpers.

`PublicFrontConfigValidator` now:

- validates template keys/slugs;
- supports `key` and `slug` input aliases, stored as `key`;
- supports `layout` and `layout_variant` input aliases, stored as `layout`;
- validates families through the card template registry;
- validates part type, source, source-specific attribute, label/icon positions, layout, font size, URL target, visibility, order, line clamp, and custom text;
- accepts Filament Builder `type` + `data` block payloads on save;
- stores normalized parts as plain semantic JSON;
- reports invalid config with safe `PublicFrontInvalidConfig` entries;
- skips invalid templates or invalid parts instead of throwing during public rendering.

## Admin settings UI changes

The existing `App\Filament\Pages\PublicContentSettings` page now has a `Public card templates` section.

Admin controls:

- Repeater for templates.
- Template key, label, family, layout, density, image size, and title size fields.
- Builder for heterogeneous ordered parts.
- Builder blocks for every supported part type.
- Safe Select options for sources, source attributes, labels, icons, layouts, line clamps, font sizes, and URL targets.

There is no side-by-side live preview in Step 3. Preview remains deferred because the prompt allowed deferral and current scope is the safe JSON foundation plus compatibility rendering.

## Rendering integration

Current public rendering is preserved.

Resolved templates are passed into:

- homepage/search content item grids;
- top-transcriber contributor cards;
- contributor directory cards;
- contributor preview/content item grids;
- content group cards.

Current card components expose safe compatibility attributes:

- `data-card-template-family`
- `data-card-template-key`
- `data-card-template-layout`
- `data-card-template-parts`

The visible card layout still uses the existing Blade output and `PublicContentCardOptions`. Step 3 does not visually redesign cards and does not interpret every part into new HTML.

## Fallback and invalid config behavior

- Empty `card_templates` setting resolves code defaults by family.
- Missing template key falls back to the family default key.
- Unknown family passed to the resolver falls back to `content_item`.
- Invalid template family removes that template and reports `unknown_semantic_value`.
- Invalid part type/source/attribute removes that part and reports the invalid path.
- Unsafe optional strings are removed; if the required content for a part becomes unsafe, that part is skipped.
- Public rendering does not throw when settings contain invalid templates or invalid parts.

## Security rules

Card template JSON may not store or render:

- raw CSS;
- raw Tailwind classes;
- raw SQL;
- arbitrary PHP class names;
- arbitrary Blade paths;
- unsafe HTML;
- iframe HTML;
- JavaScript URLs.

Allowed values are registry-defined semantic keys. `custom_text` is plain escaped text only at this foundation stage. `action_link` stores semantic source/attribute/target keys only; arbitrary external URLs are not part of Step 3 card template parts.

## Sample JSON payloads

Content item template:

```json
{
  "card_templates": [
    {
      "key": "compact_episode",
      "label": "Compact episode card",
      "family": "content_item",
      "layout": "rows",
      "density": "compact",
      "image_size": "small",
      "title_size": "lg",
      "parts": [
        {
          "type": "title",
          "source": "content_item",
          "attribute": "title",
          "visible": true,
          "order": 10,
          "layout": "inline",
          "url_target": "self"
        },
        {
          "type": "taxonomy",
          "source": "tags",
          "attribute": "links",
          "visible": true,
          "order": 20,
          "layout": "chips"
        }
      ]
    }
  ]
}
```

Contributor template:

```json
{
  "key": "compact_contributor",
  "label": "Compact contributor",
  "family": "contributor",
  "layout": "cards",
  "density": "compact",
  "image_size": "hidden",
  "title_size": "base",
  "parts": [
    {
      "type": "title",
      "source": "author",
      "attribute": "name",
      "order": 10,
      "visible": true
    }
  ]
}
```

## Sample PHP usage

Resolve the default content item template:

```php
$template = app(PublicFrontCardTemplateResolver::class)->resolve('content_item');
```

Resolve a section-selected template with safe inline overrides:

```php
$template = app(PublicFrontCardTemplateResolver::class)->resolve(
    family: 'content_item',
    key: $displayConfig['template_key'] ?? null,
    overrides: [
        'layout' => $displayConfig['layout'] ?? null,
        'density' => $displayConfig['density'] ?? null,
        'image_size' => $displayConfig['image_size'] ?? null,
        'title_size' => $displayConfig['title_size'] ?? null,
    ],
);
```

Render compatibility attributes in Blade:

```blade
@php($templateAttributes = app(\App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer::class)->compatibilityAttributes($cardTemplate))

<article
    data-card-template-family="{{ $templateAttributes['data-card-template-family'] }}"
    data-card-template-key="{{ $templateAttributes['data-card-template-key'] }}"
></article>
```

## Blueprint deviations

- Used the final Step 1 flat `card_templates` list instead of the older nested `families` JSON sketch.
- Did not create `PublicCardFamily`, `PublicCardPartType`, or `PublicCardSourceEntity` enums; the project already had a registry-first Step 1 pattern, so finite values live in the card template registry and validator.
- Did not add a dedicated settings page or Resource. The existing `PublicContentSettings` page was extended.
- Did not add a live preview action/modal. Preview is deferred to a later UX refinement.
- Did not replace all visible card rendering with part-by-part generated HTML. Step 3 intentionally keeps the current Blade card output and adds a compatibility template resolution path.

## Impact on later prompts

Step 4 Public Display Sections and Loopers:

- Add the deferred `homepage_sections` JSON columns.
- Store section-level template keys and semantic overrides in section display config.
- Use `PublicFrontCardTemplateResolver::resolve($family, $key, $overrides)`.
- Do not duplicate template schema in looper config.

Step 5 Latest and Search UX:

- Can use `data-card-template-*` attributes immediately.
- If visual card redesign is required, extend `PublicFrontCardTemplateRenderer` to render parts into controlled Blade fragments.
- Keep URL-backed Livewire state and Prompt 11R public listing behavior.

Step 6 Public Forms and Submissions:

- No direct dependency except continuing the Step 1 registry/validator pattern for `public_forms`.
- Do not store form display HTML/CSS in card template JSON.

Step 7 About Page Content and Team Builder:

- Reuse the Builder + validator adapter pattern, especially plain normalized JSON storage versus Filament Builder `type`/`data` editing state.
- Safe Markdown/RichEditor rendering remains a separate concern.

Step 8 Podcasts and Groups UX:

- Use `content_group` family templates for podcast/group cards.
- Internal code remains `ContentGroup`; no `Podcast` or `Episode` model was added.

Step 9 Public Menu and Header:

- No direct card-template dependency.
- Continue using Step 1 `menu_config` and `route_labels`; do not add menu item models.

Step 10 Contributors and Top Transcribers UX:

- Use `contributor` family templates.
- Contributor cards currently resolve a template and expose compatibility attributes; visual part rendering can be added later if needed.

Step 11 Seeders, Demo Data, Assets, and Cleanup:

- Seed from `PublicFrontCardTemplateRegistry::defaultTemplates()` or `PublicFrontConfigRegistry::defaultCardTemplates()`.
- Preserve `public/images/podtext-logo.jpg`.
- Do not seed unsafe CSS/class/view strings.

Prompt 13 Dashboard Metrics:

- Can inspect `card_templates` as editorial/config metrics only.
- Do not assume transcription-policy metrics exist.
- Prompt 13 has not started.

## Open issues / follow-up decisions

- No admin invalid-config warning UI exists yet.
- No live card preview exists yet.
- Visual rendering of individual template parts is intentionally deferred.
- Step 4 must decide how homepage section display config references template keys and inline semantic overrides.
- Step 5 or later UX prompts should decide when the compatibility renderer becomes a full visual renderer.

## Tests and quality gate summary

Focused checks:

- `php artisan test --filter=PublicFrontCardTemplateBuilderTest`: passed, 12 tests, 120 assertions.
- `php artisan test --filter=PublicFrontJsonSettingsArchitectureTest`: passed, 6 tests, 41 assertions.
- `php artisan test --filter=PublicHomepageSearchTest`: passed, 12 tests, 88 assertions.
- `php artisan test --filter=PublicContributorDiscoveryTest`: passed, 5 tests, 36 assertions.
- `php artisan test --filter=PublicItemPageMediaParserTest`: passed, 9 tests, 85 assertions.
- `vendor/bin/pint --dirty --format agent`: passed after formatting one test import.

Final quality gate:

- `php artisan test --filter=PublicFrontCardTemplateBuilderTest`: passed, 12 tests, 120 assertions.
- `php artisan test --filter=PublicHomepageSearchTest`: passed, 12 tests, 88 assertions.
- `php artisan test --filter=PublicContributorDiscoveryTest`: passed, 5 tests, 36 assertions.
- `php artisan test --filter=PublicItemPageMediaParserTest`: passed, 9 tests, 85 assertions.
- `php artisan test`: passed, 145 tests, 1170 assertions.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed with 0 issues.
- `npm run build`: passed.
