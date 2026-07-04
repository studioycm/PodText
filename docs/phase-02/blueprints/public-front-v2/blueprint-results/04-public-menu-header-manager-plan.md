# Blueprint Result: Public Menu And Header Manager

Source blueprint: `docs/phase-02/blueprints/public-front-v2/04-public-menu-header-manager-blueprint.md`

Generated with Laravel Boost context and Filament Blueprint planning docs.

## Commands

```bash
php artisan make:enum PublicMenuItemType --no-interaction
php artisan make:enum PublicRouteTarget --no-interaction
php artisan make:livewire Public/PublicHeader --no-interaction
php artisan make:test PublicMenuHeaderSettingsTest --pest --no-interaction
php artisan make:test PublicHeaderRenderTest --pest --no-interaction
```

No menu model/resource/migration commands.

## Models

Update: `App\Settings\PublicContentSettings`

- Ensure `public array $menu_config = [];`
- Ensure `public array $route_labels = [];`

Rejected models:

- `PublicMenu`
- `PublicMenuItem`

## Resources And Pages

Update: `App\Filament\Pages\PublicContentSettings`

Field: `Filament\Forms\Components\Repeater`

- Docs: https://filamentphp.com/docs/5.x/forms/repeater
- Validation: `nullable|array`
- Config:
  - `Repeater::make('menu_config.header.items')`
  - `->reorderable()`
  - `->cloneable()`
  - `->collapsed()`

Field: `Filament\Forms\Components\TextInput`

- Docs: https://filamentphp.com/docs/5.x/forms/text-input
- Validation:
  - label: `required|string|max:80`
  - external URL: `nullable|url|starts_with:https://`
  - route path if ever enabled: `nullable|string|alpha_dash|max:80`
- Config: helper text for route/path consequences.

Field: `Filament\Forms\Components\Select`

- Docs: https://filamentphp.com/docs/5.x/forms/select
- Validation: `required|string|in:<registry keys>`
- Config:
  - item type from `PublicMenuItemType`
  - route target from `PublicRouteTarget`
  - form key from public form definitions
  - display mode: `modal`, `slide_over`
  - icon from allowed Heroicon keys.

Field: `Filament\Forms\Components\Toggle`

- Docs: https://filamentphp.com/docs/5.x/forms/toggle
- Validation: `boolean`
- Config: visible, open new tab, theme selector enabled.

Reactive fields:

- Imports: `Filament\Schemas\Components\Utilities\Get`, `Filament\Schemas\Components\Utilities\Set`
- Use `->live()` on item type.
- Reset incompatible target fields when item type changes.

Action: `Filament\Actions\Action`

- Docs: https://filamentphp.com/docs/5.x/actions/modals
- Location: public header form item trigger.
- Visibility: guest if selected form exists and is enabled.
- Authorization: guest allowed, with runtime form enabled check.
- Behavior:
  1. Resolve form definition by key.
  2. Build safe runtime schema from registry.
  3. Open modal or slide-over.
  4. Submit through public form handler if forms step is implemented.

## Support Classes

Create:

- `App\Support\PublicFront\Menu\PublicMenuConfigReader`
- `App\Support\PublicFront\Menu\PublicMenuRenderer`
- `App\Support\PublicFront\Menu\PublicRouteRegistry`
- `App\Support\PublicFront\Menu\PublicUrlSanitizer`

Enums:

- `App\Enums\PublicMenuItemType`
- `App\Enums\PublicRouteTarget`

Allowed item types:

- internal route
- external URL
- public form
- dropdown deferred

## Authorization

- Admin settings editing: authenticated admin only.
- Public menu viewing: guests.
- Public form item submission: guests only if form definition is enabled.

## Widgets

None.

## Public Livewire And Blade

Create:

- `App\Livewire\Public\PublicHeader`
- `resources/views/livewire/public/public-header.blade.php`
- optional Blade component `resources/views/components/public/header.blade.php`

Update public panel layout/theme integration without enabling Filament navigation:

- `App\Providers\Filament\PublicPanelProvider` remains `->navigation(false)`.

Default header items:

- home
- groups/podcasts
- about
- request transcription public form
- volunteer transcriber public form
- theme selector

## Tests

- default menu renders for guests.
- invisible menu item hidden.
- unknown item type skipped.
- external non-HTTPS and `javascript:` URLs rejected.
- missing route target skipped.
- missing/disabled form item skipped or disabled.
- public panel navigation remains disabled.

## Security

- Internal routes are registry keys only.
- External URLs are sanitized HTTPS only.
- No raw classes, arbitrary route names, Blade paths, or scripts in JSON.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Out Of Scope

- Menu package adoption.
- Nested dropdowns in v1.
- Route path changes/redirect strategy unless user approves.

## Final Report Checklist

- State default menu items.
- State route registry targets.
- State public form trigger behavior.
- Confirm no menu models/tables.
