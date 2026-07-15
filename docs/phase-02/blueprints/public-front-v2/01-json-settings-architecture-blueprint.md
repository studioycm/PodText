# JSON Settings Architecture Blueprint

> **Historical blueprint notice — 2026-07-16:** This records the shipped
> architecture. ARCH1 in `docs/research/settings-performance/07-sp3d-pre-research.md`
> supersedes its settings-only boundary for Card Templates and Public Forms.

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: JSON settings/configuration architecture.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Establish the conventions and first support classes that make public-front v2 configuration JSON-first, typed, safe, portable, and testable.

## Dependencies

- Prompt 12 complete.
- Existing `App\Settings\PublicContentSettings`.
- Existing `App\Filament\Pages\PublicContentSettings`.
- Existing `App\Models\HomepageSection`.
- Existing `App\Support\PublicContent\PublicContentCardOptions`.
- Docs: https://filamentphp.com/docs/5.x/forms/builder, https://filamentphp.com/docs/5.x/forms/repeater, https://laravel.com/docs/13.x/validation, https://github.com/spatie/laravel-settings.

## Primary User/Admin Flows

- Admin opens public content settings.
- Admin edits structured configuration arrays through Builder/Repeater fields.
- Admin saves settings.
- Typed readers validate and normalize config on read.
- Public Livewire/Blade components receive normalized config and render known variants only.
- Invalid config falls back to code defaults and can be surfaced as admin warnings later.

## Filament Primitive Mapping

- Settings Page: update `App\Filament\Pages\PublicContentSettings`.
- Field: `Filament\Forms\Components\Builder`, Docs: https://filamentphp.com/docs/5.x/forms/builder, Validation: array/list with whitelisted block keys, Config: named blocks for heterogeneous settings.
- Field: `Filament\Forms\Components\Repeater`, Docs: https://filamentphp.com/docs/5.x/forms/repeater, Validation: array/list, Config: homogeneous repeated settings.
- Field: `Filament\Forms\Components\Select`, Docs: https://filamentphp.com/docs/5.x/forms/select, Validation: in registry keys, Config: options from registries.
- Field: `Filament\Forms\Components\Toggle`, Docs: https://filamentphp.com/docs/5.x/forms/toggle, Validation: boolean, Config: used for visibility/enabled flags.

## JSON Settings/Configuration Shape

Use settings arrays for site-level public-front config:

```php
public array $card_templates = [];
public array $menu_config = [];
public array $about_content_blocks = [];
public array $team_profiles = [];
public array $public_forms = [];
public array $route_labels = [];
public array $display_defaults = [];
public array $transcription_policy = [];
```

Use existing `homepage_sections` plus future JSON columns only for per-section rendering configuration:

```php
source_config
selection_config
display_config
pagination_config
```

## Models/Migrations

- Do not create settings-only models.
- Optional future migration: add nullable JSON/text config columns to `homepage_sections`.
- Any new model/table must be an explicit user decision, except `PublicFormSubmission` in the forms blueprint.

## Casts/Enums/Support Classes

- Add support classes under `App\Support\PublicFront`.
- `PublicFrontConfigRegistry`: allowed block types, field types, source entities, attributes, icons, layout variants, defaults.
- `PublicFrontConfigReader`: merges defaults with stored settings.
- `PublicFrontConfigValidator`: validates arrays before save and for runtime fallback.
- Enums for finite keys where stable; store enum values as strings.

## Relationships

No new relationships for the foundation.

## Filament Resources/Pages

Update only existing settings pages. If the settings page becomes too large, split into additional Filament Settings Pages rather than models.

## Form Schemas

Every JSON editor section must use:

- Whitelisted Select options.
- Helper text for technical keys.
- Visibility toggles.
- No freeform class/CSS/SQL/PHP/Blade inputs.

## Tables/Actions

No tables are required. Add optional preview Actions only after the first reader/renderer is stable.

## Public Pages/Livewire/Blade

Public components must never read raw settings arrays directly. They should consume normalized config value objects or arrays returned by typed readers.

## Settings

Defaults belong in the settings class and reader. Use code defaults so the public site renders safely when no settings rows exist.

## Seeders

Production-safe seeders may initialize default JSON structures. Demo content is out of scope.

## Tests

- Defaults exist without settings rows.
- Unknown block/source/layout keys fall back safely.
- Raw class/CSS/SQL/PHP/Blade strings are rejected.
- Public renderers ignore invalid config.
- Settings page smoke test confirms Builder/Repeater components exist.

## Security

No raw CSS/classes, raw SQL, arbitrary PHP classes, arbitrary Blade paths, or unsafe HTML. Admin-authored config is still treated as untrusted at render time.

## State/Configuration Transitions

- Save settings: draft admin form state becomes persisted settings payload.
- Read settings: payload becomes normalized runtime config.
- Invalid persisted config: runtime reader returns defaults and optional warning metadata.

## Out Of Scope

- Card template implementation.
- Menu/header implementation.
- Form submissions.
- Prompt 13 dashboard metrics.

## Quality Gate

Implementation later should run `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/filacheck`, and `npm run build` if app code changes.

## Final-Report Checklist

- State JSON-first classes created.
- State settings fields added.
- State invalid-config fallback behavior.
- State tests added.
- State no settings-only models were created.
