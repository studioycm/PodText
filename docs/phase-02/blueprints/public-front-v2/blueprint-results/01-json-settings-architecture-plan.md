# Blueprint Result: JSON Settings Architecture

> **Historical result notice — 2026-07-16:** ARCH1 supersedes the settings-only
> forward boundary for Card Templates/Public Forms. See
> `docs/research/settings-performance/07-sp3d-pre-research.md`.

Source blueprint: `docs/phase-02/blueprints/public-front-v2/01-json-settings-architecture-blueprint.md`

Generated with Laravel Boost context and the installed Filament Blueprint planning docs. No Filament Blueprint Artisan command is exposed in this checkout; use this file as the implementation plan.

## Commands

Run during implementation, not during planning:

```bash
php artisan make:settings-migration add_public_front_json_settings --no-interaction
php artisan make:enum PublicFrontConfigBlockType --no-interaction
php artisan make:enum PublicFrontLayoutVariant --no-interaction
php artisan make:test PublicFrontJsonSettingsTest --pest --no-interaction
php artisan make:test PublicFrontConfigReaderTest --pest --no-interaction
```

Do not create settings-only model scaffold commands.

## Models

Update: `App\Settings\PublicContentSettings`

- Add public array properties:
  - `card_templates`
  - `menu_config`
  - `about_page`
  - `public_forms`
  - `route_labels`
  - `display_defaults`
  - `transcription_policy`
- Validation/default source: code defaults in support readers, not ad hoc controller defaults.
- Storage: existing Spatie Settings `settings.payload`.

Optional later model update: `App\Models\HomepageSection`

- Only when loopers are implemented, add JSON/text config columns to `homepage_sections`.
- Do not add those columns in this foundation step unless needed by the active implementation prompt.

## Resources And Pages

Update existing Settings Page:

- Page: `App\Filament\Pages\PublicContentSettings`
- Docs: https://filamentphp.com/docs/5.x/navigation/custom-pages
- Keep existing scalar settings sections.
- Add a new section named public front configuration only after support readers exist.

Field: `Filament\Forms\Components\Builder`

- Docs: https://filamentphp.com/docs/5.x/forms/builder
- Validation: `nullable|array`
- Config: use only named blocks from registry; use `->blockPreviews()` only when matching preview Blade exists.

Field: `Filament\Forms\Components\Repeater`

- Docs: https://filamentphp.com/docs/5.x/forms/repeater
- Validation: `nullable|array`
- Config: `->reorderable()`, `->cloneable()` for repeated settings where duplicate/edit ergonomics are useful.

Field: `Filament\Forms\Components\Select`

- Docs: https://filamentphp.com/docs/5.x/forms/select
- Validation: `required|string|in:<registry keys>`
- Config: `->options(fn () => PublicFrontConfigRegistry::...())`, `->native(false)` where existing UI style prefers enhanced selects.

Field: `Filament\Forms\Components\TextInput`

- Docs: https://filamentphp.com/docs/5.x/forms/text-input
- Validation: `nullable|string|max:255` or numeric ranges as specified by the child blueprint.
- Config: must include helper text for technical keys.

Field: `Filament\Forms\Components\Toggle`

- Docs: https://filamentphp.com/docs/5.x/forms/toggle
- Validation: `boolean`
- Config: visibility/enabled flags only.

## Support Classes

Create:

- `App\Support\PublicFront\PublicFrontConfigRegistry`
- `App\Support\PublicFront\PublicFrontConfigReader`
- `App\Support\PublicFront\PublicFrontConfigValidator`
- `App\Support\PublicFront\PublicFrontInvalidConfig`

Enums:

- `App\Enums\PublicFrontConfigBlockType`
- `App\Enums\PublicFrontLayoutVariant`

Responsibilities:

- Registry returns allowed block types, field types, entity sources, attributes, icons, layout variants, route targets, and defaults.
- Reader merges persisted JSON with defaults and removes invalid values.
- Validator is used by SettingsPage save logic and unit tests.
- Invalid config must never break public rendering.

## Authorization

- Settings Page access: authenticated admin panel users only, following existing admin panel access rules.
- Public readers/renderers: no authorization side effects; they only normalize config.

## Widgets

None.

## Public Livewire And Blade

- Public Livewire components must consume reader output, not raw settings arrays.
- Blade components must receive normalized semantic values.
- Do not interpolate raw JSON values into class attributes, Blade include paths, SQL fragments, or PHP class names.

## Security

Reject or ignore:

- raw Tailwind/CSS classes
- raw CSS
- raw SQL
- arbitrary PHP class names
- arbitrary Blade paths
- raw iframe/embed HTML
- unsafe HTML

Admin-authored JSON is still treated as untrusted at render time.

## Tests

Feature tests:

- settings page loads for admin.
- default public-front settings render when settings rows are missing.

Unit tests:

- reader returns defaults for empty arrays.
- invalid block type is removed.
- invalid layout/source/icon/attribute falls back.
- raw class/CSS/SQL/PHP/Blade-looking values are rejected or ignored.

Regression tests:

- existing public homepage/search tests still pass with old scalar settings only.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Out Of Scope

- Card rendering implementation.
- Menu/header rendering implementation.
- Public form submissions.
- Prompt 13 dashboard metrics.

## Final Report Checklist

- List array settings added.
- List support classes and enums.
- State invalid-config behavior.
- Confirm no settings-only models were created.
- State Boost and Filament Blueprint docs were used.
