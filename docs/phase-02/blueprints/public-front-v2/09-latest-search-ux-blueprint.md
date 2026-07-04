# Latest and Search UX Blueprint

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: latest/search UX refinements.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Make latest a settings-backed looper and improve search filter/card UX without regressing Prompt 11R custom Livewire/Blade listings.

## Dependencies

- Public display sections/loopers.
- Card template builder.
- Existing `ContentItemSearch`.
- Existing public card component.
- Docs: https://livewire.laravel.com/docs/4.x/url, https://livewire.laravel.com/docs/4.x/pagination, https://filamentphp.com/docs/5.x/actions/modals.

## Primary User/Admin Flows

- Admin configures latest heading, total query size, page size, pagination mode, and card template.
- Guest sees latest section with heading, search, top next/previous, and bottom load-more.
- Guest opens search page.
- Guest opens filter drawer/slide-over, toggles categories/tags, clears filters, and sees active count.

## Filament Primitive Mapping

- Settings Page fields and/or HomepageSection config.
- Field: `Filament\Forms\Components\TextInput`, Validation: total limit minimum 50; page size integer 4-25.
- Field: `Filament\Forms\Components\Select`, Validation: pagination/card template keys.
- Action: `Filament\Actions\Action`, Location: search page if using Filament action modal/slide-over; otherwise custom Livewire drawer.

## JSON Settings/Configuration Shape

```json
{
  "latest": {
    "heading": "",
    "total_limit": 50,
    "page_size": 6,
    "pagination_mode": "next_previous_load_more",
    "card_template": "latest_square",
    "card_options": {
      "title_lines": 3,
      "description_visible": true,
      "description_lines": 2,
      "podcast_display": "image_name",
      "podcast_title_separator": " - "
    }
  },
  "search": {
    "filter_panel_mode": "drawer",
    "category_filter_style": "toggle_buttons",
    "tag_filter_style": "chips"
  }
}
```

## Models/Migrations

No model. Use settings and homepage section JSON.

## Casts/Enums/Support Classes

- `LatestLooperConfig`.
- `SearchFilterPanelMode` enum.
- Card template support from card blueprint.

## Relationships

Use existing public item query eager loads.

## Filament Resources/Pages

No Resource changes beyond settings/section config.

## Form Schemas

- Total query size TextInput: numeric, min 50.
- Page size TextInput: numeric, min 4, max 25.
- Card template Select.
- Podcast display Select: image_name, text, concatenate_before_title.
- Description Toggle and numeric line count.

## Tables/Actions

No public Filament Table. Optional public filter Action only if it works cleanly with Livewire state.

## Public Pages/Livewire/Blade

- Latest is a looper with no heavy filters.
- Search filters open by action/button.
- Search and sort stay visible.
- Categories render as multi-select toggles.
- Tags render as multi-select chips/buttons.
- Active filter count badge and clear-all button.
- Card layout uses deterministic grid/fixed image constraints and `min-w-0`.

## Settings

Latest/search defaults in JSON settings; section-specific overrides on `HomepageSection`.

## Seeders

Default latest settings can be production-safe.

## Tests

- Latest min total limit enforced.
- Page size clamped 4-25.
- Search active filter count correct.
- URL state preserved.
- Card layout emits safe deterministic markup.
- Prompt 11R no-Filament-table public listing regression.

## Security

Public queries use `PublicContentItemQueries`. No raw SQL. Search input is escaped and parameterized.

## State/Configuration Transitions

- Latest next/previous changes current page.
- Load more increases visible page count.
- Filter drawer updates URL-backed Livewire state.
- Clear all resets filters and page.

## Out Of Scope

- Infinite scroll default.
- Transcript body full-text search.
- Analytics/top viewed.

## Quality Gate

Implementation later runs Livewire browser-like feature tests and full quality gate.

## Final-Report Checklist

- State latest settings implemented.
- State filter panel mode.
- State card layout safeguards.
- State public table regression test.
