# Public Front v2 Step 1 JSON Settings Handoff

## Purpose

This handoff is for ChatGPT/Yoni review before generating Public Front v2 Step 3+ prompts. It describes the JSON Settings Architecture actually implemented in Step 1 and the prompt wording changes future steps should make.

## What was implemented

- Added JSON-first public-front array settings to `App\Settings\PublicContentSettings`.
- Added a reversible Spatie settings migration for the new array defaults.
- Added `App\Support\PublicFront` classes for registry defaults, config validation, normalized reads, and safe invalid-config reporting.
- Added two small enums for stable semantic public-front keys.
- Added a safe public-front section to the existing Filament public content settings page.
- Added focused Pest coverage for defaults, merges, invalid reports, unsafe values, settings-page save behavior, old card setting compatibility, and settings-only model exclusions.

No public rendering changes, card template rendering, loopers, menu/header rendering, forms, About page, podcasts page, contributors rewrite, dashboard metrics, homepage section JSON columns, or transcription publication policy were implemented.

## Final namespaces and classes

- `App\Enums\PublicFrontConfigBlockType`
- `App\Enums\PublicFrontLayoutVariant`
- `App\Support\PublicFront\PublicFrontConfigRegistry`
- `App\Support\PublicFront\PublicFrontConfigReader`
- `App\Support\PublicFront\PublicFrontConfigValidator`
- `App\Support\PublicFront\PublicFrontConfigResult`
- `App\Support\PublicFront\PublicFrontInvalidConfig`

Changed existing classes:

- `App\Settings\PublicContentSettings`
- `App\Filament\Pages\PublicContentSettings`

## Final public API for future prompts

Use `PublicFrontConfigReader` at runtime:

```php
$result = app(PublicFrontConfigReader::class)->read();
$config = $result->config();
$displayDefaults = $result->group('display_defaults');
$invalidConfig = $result->invalidConfigArray();
```

Use `PublicFrontConfigValidator` when normalizing arrays before persistence or in tests:

```php
$result = app(PublicFrontConfigValidator::class)->validate($rawConfig);
$safeConfig = $result->config();
```

Use `PublicFrontConfigRegistry` for defaults and semantic options:

```php
PublicFrontConfigRegistry::defaults();
PublicFrontConfigRegistry::settingsKeys();
PublicFrontConfigRegistry::routeKeys();
PublicFrontConfigRegistry::layouts();
PublicFrontConfigRegistry::densities();
PublicFrontConfigRegistry::imageSizes();
PublicFrontConfigRegistry::titleSizes();
```

`PublicFrontConfigResult` exposes:

- `config(): array`
- `group(string $key): array`
- `invalidConfig(): array`
- `invalidConfigArray(): array`
- `hasInvalidConfig(): bool`

`PublicFrontInvalidConfig` exposes safe report fields:

- `path`
- `reason`
- `valuePreview`
- `toArray()`

## Settings groups and keys

New Spatie settings group keys under `public_content`:

- `card_templates`
- `menu_config`
- `about_page`
- `public_forms`
- `route_labels`
- `display_defaults`

No `transcription_policy` key was added. That is an intentional deviation because Step 2 / transcription publication policy is deferred/reserved.

## JSON structure conventions

Current defaults:

```php
[
    'card_templates' => [],
    'menu_config' => [
        'enabled' => false,
        'items' => [],
    ],
    'about_page' => [
        'enabled' => false,
        'blocks' => [],
        'team_profiles' => [],
    ],
    'public_forms' => [],
    'route_labels' => [],
    'display_defaults' => [
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'medium',
        'title_size' => 'base',
        'page_size' => 12,
    ],
]
```

Public-front JSON must store semantic values only. Future prompts should extend `PublicFrontConfigRegistry` and `PublicFrontConfigValidator` when they add new schema keys.

## Defaults and fallback behavior

- Missing settings rows fall back to code defaults from `PublicFrontConfigRegistry::defaults()`.
- Nested stored arrays merge with defaults through `PublicFrontConfigValidator`.
- Unknown top-level settings groups are ignored and reported.
- Unknown nested keys are ignored and reported.
- Invalid finite values fall back to their group defaults.
- If `PublicContentSettings` cannot be resolved, `PublicFrontConfigReader::read()` returns defaults with a `settings_unavailable` invalid-config report.

## Validation and sanitization behavior

The validator rejects or ignores:

- unknown top-level keys;
- unknown nested keys;
- non-array group payloads;
- non-list list payloads;
- unsafe HTML and iframe/script strings;
- `javascript:` URLs;
- non-HTTPS external URLs;
- raw Tailwind/CSS-looking strings;
- raw CSS declarations;
- SQL-looking strings;
- arbitrary PHP class names / `::class` strings;
- Blade path-looking strings;
- invalid semantic keys.

The validator currently allows only controlled semantic values for:

- layouts: `cards`, `rows`;
- densities: `compact`, `comfortable`;
- image sizes: `hidden`, `small`, `medium`, `large`;
- title sizes: `sm`, `base`, `lg`;
- known route keys from `PublicFrontConfigRegistry::routeKeys()`.

## Invalid config reporting behavior

Invalid config is reported as `PublicFrontInvalidConfig` value objects. Reports include a path, reason, and truncated safe preview. Runtime readers do not throw for invalid JSON configuration; they return normalized defaults plus report metadata.

Admin warning UI was not implemented. Future admin prompts may surface `invalidConfigArray()` safely.

## Existing settings/components changed

`PublicContentSettings` now includes six public-front array properties.

`PublicContentSettings` Filament page now:

- fills missing array defaults through `mutateFormDataBeforeFill()`;
- normalizes public-front arrays through `mutateFormDataBeforeSave()`;
- exposes semantic controls for:
  - `menu_config.enabled`;
  - `display_defaults.layout`;
  - `display_defaults.density`;
  - `display_defaults.image_size`;
  - `display_defaults.title_size`;
  - `display_defaults.page_size`;
  - `route_labels`.

`PublicContentCardOptions` was not changed. Existing Prompt 11 card settings still work.

## Sample JSON payloads

Safe sample:

```json
{
  "menu_config": {
    "enabled": true,
    "items": []
  },
  "display_defaults": {
    "layout": "rows",
    "density": "compact",
    "image_size": "large",
    "title_size": "lg",
    "page_size": 16
  },
  "route_labels": [
    {
      "route_key": "podcasts",
      "label": "Podcasts"
    }
  ]
}
```

Unsafe sample behavior:

```json
{
  "display_defaults": {
    "layout": "p-4 text-red-500"
  },
  "route_labels": [
    {
      "route_key": "search",
      "label": "<script>alert(1)</script>"
    }
  ]
}
```

The layout falls back to `cards`, the unsafe route label is dropped, and invalid-config reports include `display_defaults.layout` and `route_labels.0.label`.

## Sample PHP usage

Future public rendering code should read normalized values:

```php
use App\Support\PublicFront\PublicFrontConfigReader;

$display = app(PublicFrontConfigReader::class)->group('display_defaults');

$layout = $display['layout'] ?? 'cards';
$pageSize = $display['page_size'] ?? 12;
```

Future admin save logic should normalize through the validator:

```php
use App\Support\PublicFront\PublicFrontConfigValidator;

$result = app(PublicFrontConfigValidator::class)->validate($data);
$safeConfig = $result->config();
```

## Blueprint deviations

- Did not add `transcription_policy` because the corrected execution plan defers Step 2 / transcription publication policy.
- Did not add `homepage_sections` JSON columns; they remain deferred until Step 4 / Public Display Sections and Loopers.
- Did not expose Builder blocks for card templates/about/forms yet. Step 1 added only minimal semantic settings controls to avoid implementing later feature UIs early.
- Added `PublicFrontConfigResult` in addition to the requested invalid-config value object so future prompts can consume normalized config and reports together.

## Impact on later prompts

Step 3 Card Template Builder can use the architecture as planned. It should extend `card_templates` schema in `PublicFrontConfigValidator`, add admin Builder/Repeater UI after the registry keys exist, and render only through `PublicFrontConfigReader`.

Step 4 Public Display Sections and Loopers can use the architecture as planned. It should add the deferred `homepage_sections` JSON columns and should add section-level readers/validators instead of overloading `display_defaults`.

Step 5 Latest and Search UX can use `display_defaults` for semantic layout/page-size defaults, but must keep URL-backed Livewire state and Prompt 11R card behavior until Step 3/4 rendering changes exist.

Step 6 Public Forms and Submissions can use `public_forms` for form definitions. It must extend the validator with field schemas before enabling public submission rendering.

Step 7 About Page Content and Team Builder can use `about_page.blocks` and `about_page.team_profiles`. It must add safe Markdown/RichEditor rendering and image constraints in that prompt.

Step 8 Podcasts and Groups UX can use `display_defaults` and `route_labels`. It may add a dedicated podcasts/groups config group only if the prompt updates `PublicContentSettings`, the settings migration strategy, registry, validator, and tests.

Step 9 Public Menu and Header can use `menu_config` and `route_labels`. It must skip or disable missing route/form targets server-side.

Step 10 Contributors and Top Transcribers UX can use `display_defaults` as a baseline. If contributor-specific configuration is needed, add a dedicated schema extension through the same registry/validator pattern.

Step 11 Seeders, Demo Data, Assets, and Cleanup can seed the six public-front array settings through Spatie settings rows. It should not bypass `PublicFrontConfigRegistry::defaults()`.

Step 2 / Reserved Transcription Publication Policy did not get a settings key. If promoted later, add its own isolated prompt, schema, validation, and regression tests.

Prompt 13 Dashboard Metrics can inspect public-front settings only as editorial/config metrics. It should not assume transcription-policy conflict metrics exist.

## Prompt-by-prompt adaptation notes

Step 3 prompt should explicitly say: extend `card_templates` in `PublicFrontConfigRegistry` and `PublicFrontConfigValidator`; do not create a `CardTemplate` model.

Step 4 prompt should explicitly say: add `homepage_sections` JSON columns in Step 4, not Step 1; add section-level validation that composes with `PublicFrontConfigReader`.

Step 5 prompt should explicitly say: consume `display_defaults.page_size`, `layout`, and card semantic values through `PublicFrontConfigReader`.

Step 6 prompt should explicitly say: define `public_forms` schema before using it for public submission validation; do not add file uploads or notifications in v1.

Step 7 prompt should explicitly say: extend `about_page` schema for block/profile types and keep rendering sanitized.

Step 8 prompt should explicitly say: `/podcasts` route labels may come from `route_labels`, but internal code stays `ContentGroup`.

Step 9 prompt should explicitly say: use `menu_config.enabled`, future `menu_config.items`, and `route_labels`; skip or disable unresolved targets.

Step 10 prompt should explicitly say: contributor/top-transcriber defaults should be added through registry/validator if new config is needed.

Step 11 prompt should explicitly say: seed from `PublicFrontConfigRegistry::defaults()` and preserve `public/images/podtext-logo.jpg`.

Step 2 / Reserved prompt should explicitly say: no setting exists yet; add one only in the isolated policy prompt if promoted.

Prompt 13 prompt should explicitly say: Public Front v2 settings exist, but dashboard metrics should use only real schema-backed/editorial states.

## Open issues / follow-up decisions

- No admin warning surface for invalid persisted JSON exists yet.
- No public rendering consumes the new config yet.
- No Builder UI exists yet for card templates, about blocks, or public forms.
- Future prompts that add new settings groups must add properties, migrations/defaults, registry entries, validation, tests, and handoff notes.
- Step 2 transcription publication policy remains deferred/reserved.

## Tests and quality gate summary

Focused checks already run:

- `php artisan test --filter=PublicFrontJsonSettingsArchitectureTest`: passed.
- `php artisan test tests/Feature/TaxonomyTagsPinningSettingsTest.php --filter="loads public content settings defaults"`: passed.
- `php artisan test tests/Feature/AdminPhase02ResourcesTest.php --filter="saves public content settings through the settings page"`: passed.
- `php artisan test tests/Feature/PublicHomepageSearchTest.php --filter="uses safe defaults when old settings rows are missing"`: passed.
- `vendor/bin/pint --dirty --format agent`: passed.

Final full quality gate passed before commit:

- `php artisan test`: passed, 133 tests and 1050 assertions.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/filacheck`: passed with 0 issues.
- `npm run build`: passed.
