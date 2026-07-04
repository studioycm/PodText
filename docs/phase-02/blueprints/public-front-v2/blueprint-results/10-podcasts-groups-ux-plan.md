# Blueprint Result: Podcasts And Groups UX

Source blueprint: `docs/phase-02/blueprints/public-front-v2/10-podcasts-groups-ux-blueprint.md`

Generated with Laravel Boost context and Filament Blueprint planning docs.

## Commands

```bash
php artisan make:enum PublicGroupPageLayout --no-interaction
php artisan make:test PublicGroupsPageUxTest --pest --no-interaction
php artisan make:test PublicGroupShowPageUxTest --pest --no-interaction
```

No `Podcast` or `Episode` commands.

## Models

Use existing:

- `App\Models\ContentGroup`
- `App\Models\ContentItem`
- `App\Models\Category`

Settings update:

- `App\Settings\PublicContentSettings`: group page labels/layout config in JSON settings.

Rejected:

- `Podcast` model.
- `Episode` model.

## Resources And Pages

Public pages:

- `App\Filament\Public\Pages\BrowseContentGroups`
- `App\Filament\Public\Pages\ShowContentGroup`
- Docs: https://filamentphp.com/docs/5.x/navigation/custom-pages

Settings page:

- `App\Filament\Pages\PublicContentSettings`

Field: `Filament\Forms\Components\TextInput`

- Docs: https://filamentphp.com/docs/5.x/forms/text-input
- Validation:
  - singular label: `required|string|max:80`
  - plural label: `required|string|max:80`
  - route path if approved: `nullable|string|alpha_dash|max:80`
- Config: helper text warning that route path changes need redirect decision.

Field: `Filament\Forms\Components\Select`

- Docs: https://filamentphp.com/docs/5.x/forms/select
- Validation: `required|string|in:<registry values>`
- Config:
  - category filter style
  - group card template
  - item row template
  - image size
  - image position
  - layout variant

Field: `Filament\Forms\Components\Toggle`

- Docs: https://filamentphp.com/docs/5.x/forms/toggle
- Validation: `boolean`
- Config: description visibility, image visibility.

Field: `Filament\Forms\Components\TextInput`

- Validation for description lines: `required|integer|min:0|max:6`
- Config: `->numeric()->integer()->minValue(0)->maxValue(6)`.

## Support Classes

Create:

- `App\Support\PublicFront\Groups\PublicGroupDisplayConfigReader`
- `App\Support\PublicFront\Groups\PublicGroupQuery`

Enum:

- `App\Enums\PublicGroupPageLayout`

## Authorization

- Settings editing: authenticated admin only.
- Public group pages: guests, public groups/items only.

## Widgets

None.

## Public Livewire And Blade

Update/create:

- Browse groups Livewire component if not currently separate.
- `resources/views/filament/public/pages/show-content-group.blade.php`
- group card Blade component under `resources/views/components/public/`.

Groups page:

- Search by group title and description/topic.
- Category toggle buttons.
- Cards include image, name, public episode count.
- Link to group page.

Group page:

- Item rows include description.
- Row-card settings control description lines, font sizes through semantic template, image size, image position, layout.
- Reuse card template renderer where implemented.

## Tests

- groups page hides draft groups.
- episode count counts public items only.
- category toggle includes descendant/group-inherited categories.
- search by group name/topic.
- group page hides draft items.
- description settings affect rendered markup.
- internal model names remain `ContentGroup` and `ContentItem`.

## Security

- Do not expose draft/unpublished group or item records.
- Use safe Markdown rendering for descriptions.
- Route path changes require explicit redirect strategy before implementation.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Out Of Scope

- Podcast/Episode model rename.
- Route backward compatibility without user approval.
- Audio player redesign.

## Final Report Checklist

- State path/label decision.
- State group card fields.
- State group page row settings.
- Confirm no model rename.
