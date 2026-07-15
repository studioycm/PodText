# Card Template Builder Blueprint

> **Historical blueprint notice — 2026-07-16:** This remains evidence for the
> shipped Builder/renderer. Future Template storage follows ARCH1 versioned
> Resources; do not execute this settings-storage design again.

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: card template builder.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Provide reusable JSON-defined public card templates for content items, groups, contributors, and later categories/tags.

## Dependencies

- JSON settings architecture blueprint.
- Existing public card component and `PublicContentCardOptions`.
- Existing public visibility query support.
- Docs: https://filamentphp.com/docs/5.x/forms/builder, https://filamentphp.com/docs/5.x/forms/repeater, https://filamentphp.com/docs/5.x/forms/select.

## Primary User/Admin Flows

- Admin opens card template settings.
- Admin creates/duplicates a template definition inside a card family.
- Admin adds ordered parts such as image, transcriber, date, read time, group, title, description, categories, tags, custom text, and action/link.
- Admin previews with a representative public item.
- Admin selects a template from homepage section, latest, search, group, or contributor settings.
- Public pages render cards through the template renderer.

## Filament Primitive Mapping

- Settings Page: existing `App\Filament\Pages\PublicContentSettings` or a dedicated public-front settings page.
- Field: `Filament\Forms\Components\Builder`, Docs: https://filamentphp.com/docs/5.x/forms/builder, Validation: list of part blocks, Config: `blockPreviews()` for compact part previews.
- Field: `Filament\Forms\Components\Repeater`, Docs: https://filamentphp.com/docs/5.x/forms/repeater, Validation: list, Config: template list with cloneable/reorderable items.
- Field: `Filament\Forms\Components\Select`, Validation: registry keys, Config: source entity, source attribute, icon, layout, font-size preset.
- Action: `Filament\Actions\Action`, Docs: https://filamentphp.com/docs/5.x/actions/modals, Location: settings page, Visibility: admin only, Authorization: authenticated admin, Behavior: open preview modal or refresh inline preview.

## JSON Settings/Configuration Shape

```json
{
  "families": {
    "content_item": {
      "default_template": "latest_square",
      "templates": [
        {
          "key": "latest_square",
          "label": "Latest square card",
          "layout_variant": "stacked_square",
          "parts": [
            {
              "type": "image",
              "source_entity": "content_item",
              "source_attribute": "image",
              "order": 10,
              "visibility": true
            }
          ]
        }
      ]
    }
  }
}
```

Allowed source entities: `content_item`, `content_group`, `transcription`, `author`, `categories`, `tags`, `custom`.

## Models/Migrations

Do not create `CardTemplate`. Templates are settings JSON. Reconsider a model only if the user approves version history, approvals, or high-volume template ownership.

## Casts/Enums/Support Classes

- `PublicCardFamily` enum.
- `PublicCardPartType` enum.
- `PublicCardSourceEntity` enum.
- `PublicCardTemplateRegistry`.
- `PublicCardTemplateReader`.
- `PublicCardTemplateRenderer`.

## Relationships

Use already eager-loaded relationships from public item queries. Do not add relationships just for rendering.

## Filament Resources/Pages

No Resource. Settings Page only.

## Form Schemas

- Template key: `TextInput`, required, alpha_dash, unique within family.
- Label: `TextInput`, required.
- Family: `Select`, required, registry-backed.
- Parts: `Builder`, required min 1.
- Part layout fields: `Select` with semantic options only.
- Custom text: `TextInput` or `MarkdownEditor` only if safe renderer is used.

## Tables/Actions

No table. Optional preview Action:

- Location: settings page.
- Behavior: render sample public item with selected template.
- Authorization: admin only.

## Public Pages/Livewire/Blade

Replace direct card-option branching gradually with renderer calls. Keep current Blade component as the baseline default until renderer reaches parity.

## Settings

Default content item template must reproduce current card behavior. Later steps can add latest and group-specific defaults.

## Seeders

Production-safe default settings seeder may create the initial content item template family.

## Tests

- Default template renders current content card fields.
- Unknown part skipped.
- Invalid source attribute skipped.
- Public search/home/group views render selected template.
- Layout classes are semantic and safe.
- No N+1 regressions in card rendering.

## Security

No raw classes, CSS, Blade paths, SQL, or arbitrary icons. URLs are route keys or sanitized HTTPS URLs. Custom content is escaped or rendered through safe Markdown.

## State/Configuration Transitions

- Admin creates template: JSON definition added.
- Admin selects template in another setting: key reference stored.
- Renderer resolves key: missing key falls back to family default.

## Out Of Scope

- Full visual drag-and-drop design studio.
- Per-user templates.
- Arbitrary HTML card slots.

## Quality Gate

Implementation later runs full app quality gate and public rendering tests.

## Final-Report Checklist

- List template families added.
- List supported part types.
- State preview behavior.
- State tests for invalid config and public rendering.
- Confirm no `CardTemplate` model/table.
