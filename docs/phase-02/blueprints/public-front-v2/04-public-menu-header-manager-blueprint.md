# Public Menu and Header Manager Blueprint

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: public menu/header manager.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Manage the public header/menu from validated JSON settings, including internal routes, external URLs, public form modal/slide-over entries, and a light/dark/system theme selector.

## Dependencies

- JSON settings architecture.
- Public forms definitions if form menu items are implemented in the same stage.
- Existing `PublicPanelProvider`.
- Docs: https://filamentphp.com/docs/5.x/actions/modals, https://filamentphp.com/docs/5.x/navigation/custom-pages.

## Primary User/Admin Flows

- Admin opens public header settings.
- Admin reorders menu items and toggles visibility.
- Admin selects item type and fills safe target fields.
- Public header renders visible items.
- User clicks a route item, external URL, or public form action.
- Theme selector updates local display preference.

## Filament Primitive Mapping

- Settings Page: public content/site settings page.
- Field: `Filament\Forms\Components\Repeater`, Validation: list, Config: reorderable menu items.
- Field: `Filament\Forms\Components\Select`, Validation: registry keys, Config: item type, target route, form key, display mode.
- Field: `Filament\Forms\Components\TextInput`, Validation: required label, nullable HTTPS URL.
- Field: `Filament\Forms\Components\Toggle`, Validation: boolean, Config: visible/open new tab/theme selector enabled.
- Action: `Filament\Actions\Action`, Location: public header/form trigger, Behavior: opens modal or slide-over for selected form.

## JSON Settings/Configuration Shape

```json
{
  "header": {
    "items": [
      {"type": "internal_route", "label": "Home", "target": "home", "visible": true, "sort": 10},
      {"type": "public_form", "label": "Request transcription", "form_key": "request-transcription", "display_mode": "modal", "visible": true}
    ],
    "theme_selector": {"enabled": true, "position": "end"}
  },
  "route_labels": {
    "contributors": {"label": "Transcribers"},
    "groups": {"path": "groups", "label": "Podcasts"}
  }
}
```

## Models/Migrations

Do not create `PublicMenu` or `PublicMenuItem`.

## Casts/Enums/Support Classes

- `PublicMenuItemType` enum: internal route, external URL, public form, dropdown deferred.
- `PublicRouteTarget` enum/registry.
- `PublicMenuConfigReader`.
- `PublicMenuRenderer`.

## Relationships

No relationships. Form menu items reference settings form keys.

## Filament Resources/Pages

No Resource. Settings Page only.

## Form Schemas

- Label TextInput: required, max length.
- Icon Select: allowed Heroicon keys only.
- Type Select: required.
- Internal target Select: visible for internal route.
- URL TextInput: visible for external URL, HTTPS validation.
- Form key Select: visible for public form, options from form definitions.
- Display mode Select: modal or slide_over.

## Tables/Actions

No table. Public form actions are runtime actions, not admin tables.

## Public Pages/Livewire/Blade

Add app-owned public header Blade component/layout hook. Do not re-enable Filament public navigation. Keep route generation in PHP registry.

## Settings

Default top menu: home, groups/podcasts, about, request transcription, volunteer transcriber, theme selector.

## Seeders

Production-safe defaults may seed menu JSON. Keep demo-specific labels out.

## Tests

- Default menu renders.
- Unknown item type skipped.
- Disabled item hidden.
- External URL sanitized/rejected.
- Public form item cannot open disabled/missing form.
- Theme selector renders when enabled.

## Security

No arbitrary route names, classes, or Blade paths. URLs must be safe HTTP(S). Public form submit validates enabled state.

## State/Configuration Transitions

- Admin saves menu JSON.
- Reader normalizes order and removes invalid items.
- Public click dispatches route/external/form action.

## Out Of Scope

- Nested dropdowns unless user approves.
- Backward-compatible route redirects unless user approves.
- Package-based menu builder.

## Quality Gate

Implementation later runs public page tests, FilaCheck for Filament changes, Pint, and build.

## Final-Report Checklist

- State default menu keys.
- State route target registry.
- State public form trigger behavior.
- Confirm no menu models/tables.
