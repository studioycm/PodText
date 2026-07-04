# Podcasts and Groups UX Blueprint

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: podcasts/groups page and group-page refinements.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Improve public `ContentGroup` browse and show pages while keeping internal group/item architecture.

## Dependencies

- Card template builder.
- Existing `ContentGroup` and `ContentItem` models.
- Existing public group routes/pages.
- Docs: https://livewire.laravel.com/docs/4.x/url, https://filamentphp.com/docs/5.x/navigation/custom-pages.

## Primary User/Admin Flows

- Admin configures public group labels and display options.
- Guest opens groups/podcasts page.
- Guest filters by category toggle buttons and searches by name/topic.
- Guest opens group page.
- Guest sees episode/item list with description and configured row-card layout.

## Filament Primitive Mapping

- Settings Page fields for labels and display.
- Field: `Filament\Forms\Components\TextInput`, Validation: labels/path if approved, Config: display copy.
- Field: `Filament\Forms\Components\Select`, Validation: card template/layout/image position keys.
- Field: `Filament\Forms\Components\Toggle`, Validation: booleans for description/image visibility.

## JSON Settings/Configuration Shape

```json
{
  "groups_page": {
    "route_path": "groups",
    "label_singular": "Podcast",
    "label_plural": "Podcasts",
    "category_filter_style": "toggle_buttons",
    "card_template": "group_card"
  },
  "group_page": {
    "item_row_template": "group_episode_row",
    "description_visible": true,
    "description_lines": 3,
    "image_size": "medium",
    "image_position": "start"
  }
}
```

## Models/Migrations

No model. Do not create `Podcast` or `Episode`.

## Casts/Enums/Support Classes

- `PublicGroupDisplayConfig`.
- `GroupPageLayout` enum.
- `PublicGroupQuery`.

## Relationships

Use `ContentGroup` categories and `ContentItem` group relationship. Include group category inheritance and descendant categories.

## Filament Resources/Pages

No admin Resource changes unless helper text for labels is needed. Public pages may add or adjust `BrowseContentGroups` and `ShowContentGroup`.

## Form Schemas

- Label fields with translation-key-aware display if feasible.
- Route path setting is a user decision; if mutable, validate slug segment.
- Card template Select from registry.
- Row options Select/TextInput/Toggle.

## Tables/Actions

No tables/actions.

## Public Pages/Livewire/Blade

- Groups page with category toggle buttons, search, image cards, name, and public episode count.
- Group page item rows show description and follow row-card settings.
- URLs should use existing `/groups` path unless user approves route changes/redirects.

## Settings

Labels and layout options live in JSON settings.

## Seeders

Demo groups should clearly remain demo data. Production defaults may set display labels.

## Tests

- Groups page hides unpublished groups.
- Episode counts count public items only.
- Category toggle includes descendants/inheritance.
- Search matches group name/topic safely.
- Group page descriptions respect line/visibility settings.

## Security

No draft/unpublished content leaks. Descriptions rendered safely.

## State/Configuration Transitions

- Search/category toggles update URL-backed state.
- Label changes affect display only unless route path changes are approved.

## Out Of Scope

- New `Podcast`/`Episode` internal models.
- Route backward compatibility without user decision.
- Audio/player redesign.

## Quality Gate

Implementation later runs public group page tests and full quality gate.

## Final-Report Checklist

- State route/path decision.
- State public labels.
- State group card/row template use.
- Confirm no internal model rename.
