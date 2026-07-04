# Public Front v2 Research: JSON Settings Architecture

## Purpose

Define a project-wide JSON-first configuration architecture for the next public-front work after Prompt 12. This is planning only.

## Topic Scope

This covers Spatie Settings JSON payloads, JSON columns on existing configuration models, typed config readers, validation, safe rendering, defaults, tests, and the boundary where a model/table becomes justified.

## Exact Search Terms Used

- Boost: "Spatie Laravel Settings array property JSON payload settings migration defaults"
- Boost: "Filament SettingsPage nested array form Builder Repeater settings"
- Boost: "Laravel validation nested array allowed keys list required_array_keys"
- Boost: "Laravel Eloquent array JSON casts encrypted array object settings"
- FilamentExamples MCP: "Spatie settings page JSON array Filament Builder settings page"
- FilamentExamples MCP: "Filament settings page Repeater array settings Spatie"
- FilamentExamples MCP: "Filament Builder blocks JSON content settings page"
- External: "site:laraveldaily.com Filament Repeater Builder RichEditor FileUpload"

## Boost Docs Used

- `application_info`: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, Tailwind 4.3.2, SQLite.
- `database_schema`: `settings` uses `group`, `name`, and `payload`; `homepage_sections` has typed section fields but no JSON config columns.
- Laravel validation docs: nested arrays, allowed keys, `required_array_keys`, `list`, and JSON validation.
- Laravel casts docs: array/JSON casts are appropriate for JSON config columns.
- Filament Builder/Repetater docs: both produce arrays suitable for JSON storage.

## FilamentExamples MCP Examples Found

- `v4/full-projects/hotel-management-bookings/app/Filament/Hotel/Pages/MyHotel.php`: custom Page with `InteractsWithSchemas`, `statePath('data')`, FileUpload, TextInput, Checkbox, and explicit save action.
- `v4/full-projects/clusters-with-profile-settings`: profile/settings style custom page forms.
- `v4/full-projects/schedule-for-doctors/app/Filament/Tables/Columns/ScheduleDetailsColumn.php`: enum-backed/typed display and custom embedded rendering.

MCP access was search-snippet only. No separate fetch/read/source tool was exposed.

## Actual Files, Classes, and Snippets Observed

- Local: `app/Settings/PublicContentSettings.php` already stores public UI settings through Spatie Settings scalar properties.
- Local: `app/Filament/Pages/PublicContentSettings.php` is the current Filament `SettingsPage`.
- Local: `app/Models/HomepageSection.php` is the existing homepage configuration model.
- Local: `app/Support/PublicContent/PublicContentCardOptions.php` safely maps semantic option values to known Tailwind classes and integer ranges.
- Local schema: `settings.payload` is text managed by Spatie Settings; `homepage_sections` has `type`, relationship FKs, limit, sort, and visibility.

## GitHub/Source Files Inspected

- `https://github.com/LaravelDaily/Filament-Menu-Builder-Demo`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/config/filament-menu-builder.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/database/migrations/2026_02_16_061744_create_menus_table.php`
- `https://raw.githubusercontent.com/LaravelDaily/Filament-Menu-Builder-Demo/main/database/migrations/2026_02_16_061745_create_menu_items_table.php`
- `https://github.com/studioycm/FilamentExamples` returned 404 through the public GitHub API, so private/source access was unavailable.

## Pattern To Copy

- Use a small typed reader/support class per configuration area.
- Merge persisted JSON with code defaults before rendering.
- Reject unknown block types, field types, source entities, attributes, icons, and layout variants.
- Map semantic JSON values to known PHP enum values and known Blade/Tailwind variants, as `PublicContentCardOptions` already does.

## Pattern To Avoid

- Do not create settings-only models/tables by default.
- Do not store raw Tailwind classes, raw CSS, raw SQL, arbitrary PHP class names, arbitrary Blade paths, or unsanitized HTML in JSON.
- Do not copy the LaravelDaily menu package model-backed approach for PodText settings.

## PodText Adaptation Notes

Use Spatie Settings arrays for site-level public configuration: card templates, menu/header, about page blocks, team profiles, form definitions, route labels, public display defaults, and transcription publication policy. Use JSON columns on `homepage_sections` only when the data belongs to a section instance.

## JSON-First Settings Recommendation

Create a durable convention:

- `PublicContentSettings` may gain array properties such as `card_templates`, `menu_config`, `about_content_blocks`, `team_profiles`, `public_forms`, `route_labels`, `display_defaults`, and `transcription_policy`.
- `HomepageSection` may gain JSON columns such as `source_config`, `display_config`, `selection_config`, and `pagination_config`.
- Every JSON payload has a registry-backed reader that normalizes defaults and returns safe value objects.
- Public rendering consumes normalized config only, never raw settings arrays directly.

## Model/Table Considered

Rejected for settings-only concepts: `CardTemplate`, `PublicMenu`, `PublicMenuItem`, `AboutPage`, `AboutPageBlock`, `TeamProfile`, `PublicFormDefinition`, `PublicDisplaySection`, and `PublicLooper`.

Accepted only as an explicit exception: `PublicFormSubmission`, because submitted forms are transactional records, not settings.

## Recommended Model/Schema Options

No schema is required for the architecture foundation except optional JSON columns on `homepage_sections`. If those columns are added later, use nullable JSON/text columns cast to arrays and do not remove existing typed fields.

## Recommended Filament Patterns

- Extend the existing `App\Filament\Pages\PublicContentSettings` SettingsPage.
- Use `Filament\Forms\Components\Builder` for heterogeneous block arrays.
- Use `Filament\Forms\Components\Repeater` for repeated homogeneous arrays.
- Use `Filament\Forms\Components\Select`, `TextInput`, `Toggle`, `MarkdownEditor`, `RichEditor`, and `FileUpload` only through whitelisted schemas.
- Use helper text for technical keys and public-facing routing labels.

## Public Livewire/Blade Implications

Public components should receive normalized config objects. Existing public Livewire state remains URL-backed where practical. Blade components should select known variants rather than interpolate class names from JSON.

## Tests

- Settings defaults return expected arrays when no settings rows exist.
- Invalid block/type/source keys fall back safely.
- Unknown attributes do not render.
- Raw CSS/classes/SQL/PHP/Blade paths are rejected.
- Public rendering still hides draft/unpublished records.
- JSON config readers are covered with focused unit tests.

## Security Notes

Settings JSON is not trusted just because it is admin-authored. Validate on save and normalize on read. Sanitize rich content, escape submission payloads, validate external URLs, and only render allowlisted media/embed routes.

## Open Questions

- Should invalid JSON config silently fall back, surface admin warnings, or both?
- Should settings arrays be versioned with a `schema_version` key from v1?
- Should homepage section JSON columns be added in the first implementation step or delayed until the looper work?
