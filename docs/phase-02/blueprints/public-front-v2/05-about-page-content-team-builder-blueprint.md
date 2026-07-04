# About Page Content and Team Builder Blueprint

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: About page content builder and team-profile settings.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Create a public About page ("מי אנחנו") driven by JSON settings for content blocks and team profiles.

## Dependencies

- JSON settings architecture.
- Existing safe Markdown renderer.
- Public menu/header manager for navigation entry.
- Docs: https://filamentphp.com/docs/5.x/forms/builder, https://filamentphp.com/docs/5.x/forms/rich-editor, https://filamentphp.com/docs/5.x/forms/markdown-editor, https://filamentphp.com/docs/5.x/forms/file-upload.

## Primary User/Admin Flows

- Admin edits About page title and content blocks.
- Admin adds visible team profiles with image, title, name, description, order.
- Admin saves settings.
- Guest opens About page from menu.
- Page renders safe content blocks and visible ordered team profiles.

## Filament Primitive Mapping

- Public Page: new Filament public Page for About.
- Settings Page: existing or dedicated public-front settings page.
- Field: `Filament\Forms\Components\Builder`, Validation: allowed block types, Config: content blocks.
- Field: `Filament\Forms\Components\Repeater`, Validation: list, Config: team profiles.
- Field: `Filament\Forms\Components\RichEditor`, Validation: nullable array/string depending on mode, Config: `json()` if RichEditor is approved.
- Field: `Filament\Forms\Components\MarkdownEditor`, Validation: nullable string, Config: safe Markdown blocks.
- Field: `Filament\Forms\Components\FileUpload`, Validation: image MIME/max size, Config: `image()`, `directory('team')`, `disk('public')`, `visibility('public')`, accepted file types, max size.

## JSON Settings/Configuration Shape

```json
{
  "about_page": {
    "enabled": true,
    "title": "מי אנחנו",
    "content_blocks": [
      {"type": "markdown", "content": "...", "visible": true}
    ],
    "team": {
      "enabled": true,
      "profiles": [
        {"image": "team/name.jpg", "title": "", "name": "", "description": "", "sort": 10, "visible": true}
      ]
    }
  }
}
```

## Models/Migrations

Do not create `AboutPage`, `AboutPageBlock`, or `TeamProfile`.

## Casts/Enums/Support Classes

- `AboutBlockType` enum.
- `AboutPageConfigReader`.
- `AboutBlockRenderer`.
- `TeamProfileConfig`.

## Relationships

No relationships. Future linking to `Author` is out of scope.

## Filament Resources/Pages

- New public page: `App\Filament\Public\Pages\AboutPage`, route likely `/about`.
- No admin Resource.

## Form Schemas

- Title TextInput: required.
- Enabled Toggle.
- Content Builder blocks: Markdown, RichTextJson if approved, Image, Callout, TeamSection.
- Team Repeater fields: image upload, title, name, description, sort order, visible toggle.

## Tables/Actions

No table. Optional preview Action can render About page blocks in a modal.

## Public Pages/Livewire/Blade

Public page uses Blade block components. Markdown uses safe renderer. RichEditor JSON uses Filament rich content renderer and sanitizer if enabled.

## Settings

All content and profiles live in settings JSON.

## Seeders

Production-safe default: disabled or minimal About page block. Demo team profiles only in demo seeder.

## Tests

- Guest can view enabled page.
- Disabled page returns 404 or hides menu entry based on user decision.
- Hidden profile not rendered.
- XSS in Markdown/rich content sanitized.
- FileUpload admin schema has accepted types/max size/disk/visibility.

## Security

No raw HTML block. Rich content must be sanitized. Uploads are images only and stored in controlled directory.

## State/Configuration Transitions

- Admin saves content block JSON.
- Reader normalizes block order/visibility.
- Public page renders normalized blocks.

## Out Of Scope

- Full CMS pages.
- Team/multi-tenancy.
- User/author account linking.

## Quality Gate

Implementation later runs public page tests and full quality gate.

## Final-Report Checklist

- State block types.
- State Markdown/RichEditor decision.
- State upload config.
- Confirm no About/team models.
