# Public Display Sections and Loopers Blueprint

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: public display sections and generalized loopers.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Extend existing homepage sections into JSON-configured loopers/query displays for items, categories, authors/transcribers, groups, manual selections, and latest/top variants.

## Dependencies

- JSON settings architecture.
- Card template builder foundation.
- Existing `HomepageSection`, `HomepageSectionType`, `HomepageSectionsResource`.
- Existing `PublicContentItemQueries`.
- Docs: https://filamentphp.com/docs/5.x/tables, https://filamentphp.com/docs/5.x/actions, https://livewire.laravel.com/docs/4.x/pagination.

## Primary User/Admin Flows

- Admin creates or edits a homepage section.
- Admin chooses source type: latest, manual items, category, tag, group, authors, groups, categories, top transcribers.
- Admin configures selection, sort, display, pagination/load-more, and card template.
- Admin optionally selects/deselects filtered results using a table/modal.
- Public homepage renders each visible ordered section through a looper component.

## Filament Primitive Mapping

- Resource: update `App\Filament\Resources\HomepageSections\HomepageSectionResource`, Location: existing resource, Docs: https://filamentphp.com/docs/5.x/resources.
- Field: `Filament\Forms\Components\Select`, Validation: registry source/sort/layout keys, Config: source type and card template.
- Field: `Filament\Forms\Components\Builder`, Validation: array, Config: source/display/pagination blocks if heterogeneous.
- Field: `Filament\Forms\Components\Repeater`, Validation: list, Config: manual include/exclude rows.
- Action: `Filament\Actions\Action`, Location: section edit page, Visibility: admin, Authorization: admin, Behavior: open selection modal/table.
- Bulk Action: `Filament\Actions\BulkAction`, Location: selection table, Behavior: select/deselect visible or filtered result keys.

## JSON Settings/Configuration Shape

On `homepage_sections`:

```json
{
  "source_config": {"type": "latest_items", "filters": {}, "sort": "published_desc"},
  "selection_config": {"include": [], "exclude": []},
  "display_config": {"heading": "", "body": "", "card_template": "latest_square", "layout": "grid"},
  "pagination_config": {"mode": "load_more", "page_size": 6, "total_limit": 50, "link_to_full_page": true}
}
```

## Models/Migrations

- Add nullable JSON/text columns to existing `homepage_sections` only when implementing this step.
- Do not create `PublicDisplaySection` or `PublicLooper`.

## Casts/Enums/Support Classes

- Extend `HomepageSection` casts for JSON columns.
- `PublicLooperSourceType` enum.
- `PublicLooperSort` enum.
- `PublicLooperRegistry`.
- `PublicLooperQueryResolver`.
- `PublicLooperDisplayConfig`.

## Relationships

Use existing relationships: category, tag, contentGroup. Manual selections resolve by portable keys or IDs after user decision.

## Filament Resources/Pages

Update HomepageSection form and table. Add filters/columns for source type and visibility if useful.

## Form Schemas

- Source Type Select: options from registry, required.
- Limit/TextInput: retain existing and map into pagination defaults.
- Pagination mode Select: numbered, next_previous, load_more; infinite_scroll deferred.
- Page size TextInput: numeric, 4-25 where applicable.
- Total limit TextInput: numeric, minimum 50 for latest.
- Card template Select: options from card registry.

## Tables/Actions

Selection table should support filtering and actions:

- Select all visible.
- Deselect all visible.
- Select filtered results.
- Deselect filtered results.

Selections must store portable identifiers or explicit key arrays, not SQL.

## Public Pages/Livewire/Blade

Create reusable class-based Livewire looper components. Homepage uses visible ordered sections and normalized config. Search/category/tag pages may reuse query resolvers later.

## Settings

Global display defaults live in settings JSON; section overrides live on `HomepageSection`.

## Seeders

Update production-safe defaults for existing latest/top-transcriber sections only if needed. Demo sections remain optional.

## Tests

- Existing homepage sections keep current behavior.
- Latest looper respects total limit/page size.
- Manual include/exclude respects public visibility.
- Category/tag/group loopers use descendants/typed enabled tags.
- Select/deselect actions store expected config.
- Invalid source config falls back safely.

## Security

No raw SQL, query strings, model class names, or arbitrary scopes in JSON. All public queries use `PublicContentItemQueries`.

## State/Configuration Transitions

- Section created: default config generated by source type.
- Source type changes: incompatible config resets or is normalized.
- Selection action: resolved keys added/removed from JSON.
- Public render: normalized config becomes query + view model.

## Out Of Scope

- Non-homepage generic page builder.
- Infinite scroll as default.
- Analytics/top viewed unless actual metrics exist.

## Quality Gate

Implementation later runs tests, Pint, FilaCheck, and build.

## Final-Report Checklist

- State JSON columns added.
- State supported source types.
- State selection storage format.
- State tests proving public visibility constraints.
- Confirm no generic section/looper model.
