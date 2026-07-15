# Blueprint Result: Card Template Builder

> **Historical result notice — 2026-07-16:** Keep as shipped evidence only.
> Future Card Templates follow ARCH1 versioned Resources and revision-owned JSON.

Source blueprint: `docs/phase-02/blueprints/public-front-v2/02-card-template-builder-blueprint.md`

Generated with Laravel Boost context and Filament Blueprint planning docs. This plan assumes the JSON settings architecture result has already been implemented.

## Commands

```bash
php artisan make:enum PublicCardFamily --no-interaction
php artisan make:enum PublicCardPartType --no-interaction
php artisan make:enum PublicCardSourceEntity --no-interaction
php artisan make:test PublicCardTemplateReaderTest --pest --no-interaction
php artisan make:test PublicCardTemplateRendererTest --pest --no-interaction
php artisan make:test PublicCardTemplateSettingsTest --pest --no-interaction
```

No `CardTemplate` model or resource command.

## Models

Update: `App\Settings\PublicContentSettings`

- Ensure `public array $card_templates = [];` exists.
- No new model/table.

Rejected model:

- `CardTemplate`: rejected because templates are low-volume site configuration and must be portable JSON.

## Resources And Pages

Update: `App\Filament\Pages\PublicContentSettings`

Add card template settings section after core display settings.

Field: `Filament\Forms\Components\Repeater`

- Docs: https://filamentphp.com/docs/5.x/forms/repeater
- Validation: `nullable|array`
- Config:
  - `Repeater::make('card_templates.families.content_item.templates')`
  - `->reorderable()`
  - `->cloneable()`
  - `->collapsed()`
  - schema includes template key, label, layout variant, and parts Builder.

Field: `Filament\Forms\Components\Builder`

- Docs: https://filamentphp.com/docs/5.x/forms/builder
- Validation: `required|array|min:1`
- Config:
  - `Builder::make('parts')`
  - `->blockPreviews()`
  - blocks for `image`, `meta`, `title`, `description`, `taxonomy`, `group_identity`, `transcriber`, `action_link`, and `custom_text`.

Field: `Filament\Forms\Components\TextInput`

- Docs: https://filamentphp.com/docs/5.x/forms/text-input
- Validation:
  - template key: `required|string|alpha_dash|max:80`
  - label: `required|string|max:120`
  - custom label: `nullable|string|max:80`
- Config: helper text on every technical key.

Field: `Filament\Forms\Components\Select`

- Docs: https://filamentphp.com/docs/5.x/forms/select
- Validation: `required|string|in:<registry values>`
- Config:
  - source entity options from `PublicCardTemplateRegistry`
  - source attribute options depend on selected source entity.

Reactive fields:

- Imports: `Filament\Schemas\Components\Utilities\Get`, `Filament\Schemas\Components\Utilities\Set`
- Use `->live()` on source entity Select.
- Reset source attribute when source entity changes.

Field: `Filament\Forms\Components\Toggle`

- Docs: https://filamentphp.com/docs/5.x/forms/toggle
- Validation: `boolean`
- Config: part visibility and description visibility.

Action: `Filament\Actions\Action`

- Docs: https://filamentphp.com/docs/5.x/actions/modals
- Location: settings page form action.
- Visibility: admin only.
- Authorization: authenticated admin settings access.
- Behavior:
  1. Resolve selected template config from current form state.
  2. Load one sample public `ContentItem` through `PublicContentItemQueries`.
  3. Render preview with the public renderer.
  4. Show modal content.

## Support Classes

Create:

- `App\Support\PublicFront\Cards\PublicCardTemplateRegistry`
- `App\Support\PublicFront\Cards\PublicCardTemplateReader`
- `App\Support\PublicFront\Cards\PublicCardTemplateRenderer`
- `App\Support\PublicFront\Cards\PublicCardTemplate`
- `App\Support\PublicFront\Cards\PublicCardPart`

Enums:

- `App\Enums\PublicCardFamily`
- `App\Enums\PublicCardPartType`
- `App\Enums\PublicCardSourceEntity`

Renderer rules:

- Map semantic layout variants to known Blade partials/classes.
- Never accept raw class names or Blade paths from JSON.
- Missing part/source/attribute renders nothing.
- Default content item card must match current public card behavior closely enough for existing tests.

## Authorization

- Admin settings only for editing.
- Public rendering remains guest-readable but must apply public visibility queries.

## Widgets

None.

## Public Livewire And Blade

Update gradually:

- `resources/views/components/public/content-item-card.blade.php`
- `app/Livewire/Public/ContentItemSearch.php`

Keep existing component as fallback. Renderer should receive already eager-loaded `ContentItem` records and avoid queries inside Blade loops.

## Tests

- default template renders content item title, group, description, authors, categories/tags according to config.
- invalid part type is skipped.
- invalid source attribute is skipped.
- missing template key falls back to family default.
- raw classes and Blade paths in JSON are ignored.
- public homepage/search still render `ContentItem` cards, not `Transcription` cards.
- query-count regression for rendering a grid of cards.

## Security

- Icons from allowed Heroicon registry only.
- Action links are route target keys or sanitized HTTPS URLs.
- Custom text is escaped or safe Markdown only.
- No raw Tailwind/CSS/HTML/Blade/PHP.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Out Of Scope

- Visual drag-and-drop studio.
- Per-user templates.
- Template version history.
- Category/tag card families unless a later prompt needs them.

## Final Report Checklist

- List created card families.
- List supported part types and source entities.
- State preview behavior.
- Confirm no `CardTemplate` model/table.
