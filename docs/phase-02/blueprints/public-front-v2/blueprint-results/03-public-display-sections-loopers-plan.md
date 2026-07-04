# Blueprint Result: Public Display Sections And Loopers

Source blueprint: `docs/phase-02/blueprints/public-front-v2/03-public-display-sections-loopers-blueprint.md`

Generated with Laravel Boost context and Filament Blueprint planning docs. This plan assumes JSON settings and card template foundations are already implemented.

## Commands

```bash
php artisan make:migration add_json_config_to_homepage_sections_table --table=homepage_sections --no-interaction
php artisan make:enum PublicLooperSourceType --no-interaction
php artisan make:enum PublicLooperSort --no-interaction
php artisan make:livewire Public/PublicContentLooper --no-interaction
php artisan make:test PublicDisplaySectionsLoopersTest --pest --no-interaction
php artisan make:test HomepageSectionConfigTest --pest --no-interaction
```

No `PublicDisplaySection` or `PublicLooper` model commands.

## Models

Update: `App\Models\HomepageSection`

Add fillable/casts only after migration:

- `source_config`: array, nullable JSON/text column.
- `selection_config`: array, nullable JSON/text column.
- `display_config`: array, nullable JSON/text column.
- `pagination_config`: array, nullable JSON/text column.

Migration fields:

- `source_config` JSON nullable after `is_visible`.
- `selection_config` JSON nullable.
- `display_config` JSON nullable.
- `pagination_config` JSON nullable.

Rollback: drop the four columns.

Do not remove existing fields: `type`, `category_id`, `tag_id`, `content_group_id`, `limit`, `sort_order`, `is_visible`.

## Resources And Pages

Update Resource:

- Resource: `App\Filament\Resources\HomepageSections\HomepageSectionResource`
- Form: `App\Filament\Resources\HomepageSections\Schemas\HomepageSectionForm`
- Table: `App\Filament\Resources\HomepageSections\Tables\HomepageSectionsTable`
- Docs: https://filamentphp.com/docs/5.x/resources

Field: `Filament\Forms\Components\Select`

- Docs: https://filamentphp.com/docs/5.x/forms/select
- Validation: `required|string|in:<registry source types>`
- Config:
  - source type
  - sort mode
  - card template
  - layout
  - pagination mode

Field: `Filament\Forms\Components\TextInput`

- Docs: https://filamentphp.com/docs/5.x/forms/text-input
- Validation:
  - page size: `required|integer|min:4|max:25`
  - total limit: latest requires `required|integer|min:50`
  - heading/body max lengths as configured.
- Config: `->numeric()->integer()->minValue(...)->maxValue(...)`.

Field: `Filament\Forms\Components\Repeater`

- Docs: https://filamentphp.com/docs/5.x/forms/repeater
- Validation: `nullable|array`
- Config: manual include/exclude rows with entity type and portable key.

Action: `Filament\Actions\Action`

- Docs: https://filamentphp.com/docs/5.x/actions/modals
- Location: HomepageSection edit page or custom header action.
- Visibility: admin only when source type supports manual selection.
- Authorization: authenticated admin.
- Behavior:
  1. Open modal with selectable records.
  2. Apply public-safe filters.
  3. Add/remove selected portable keys into `selection_config`.
  4. Preserve existing explicit selections.

Bulk Action: `Filament\Actions\BulkAction`

- Docs: https://filamentphp.com/docs/5.x/actions
- Location: selection table modal.
- Visibility: admin only.
- Authorization: authenticated admin.
- Behavior: select all visible, deselect all visible, select filtered, deselect filtered.

## Support Classes

Create:

- `App\Support\PublicFront\Loopers\PublicLooperRegistry`
- `App\Support\PublicFront\Loopers\PublicLooperQueryResolver`
- `App\Support\PublicFront\Loopers\PublicLooperConfigReader`
- `App\Support\PublicFront\Loopers\PublicLooperDisplayConfig`

Enums:

- `App\Enums\PublicLooperSourceType`
- `App\Enums\PublicLooperSort`

Source types:

- latest items
- category items
- tag items
- content group items
- manual items
- authors/transcribers
- groups/podcasts
- categories
- top transcribers

## Authorization

- Admins edit section config.
- Guests only see normalized visible sections and public-safe records.

## Widgets

None.

## Public Livewire And Blade

Create:

- `App\Livewire\Public\PublicContentLooper`
- View: `resources/views/livewire/public/public-content-looper.blade.php`

Update:

- `App\Livewire\Public\ContentItemSearch` to delegate homepage sections to looper resolver/component.

Rules:

- Every item query uses `App\Support\PublicContent\PublicContentItemQueries`.
- Manual selections are filtered again at render time.
- No public Filament Table.

## Tests

- migration adds and rolls back JSON config columns.
- existing homepage sections render unchanged with empty JSON config.
- latest source clamps total limit to at least 50.
- page size clamps or validates 4-25.
- category/tag/group sources use public visibility constraints.
- manual include/exclude cannot expose draft/unpublished items.
- invalid source config falls back safely.

## Security

- No raw SQL, arbitrary model classes, scope names, or query strings in JSON.
- Store portable keys or explicit IDs only after server-side resolution.
- Public renderer rechecks visibility.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Out Of Scope

- Generic page builder outside homepage sections.
- Infinite scroll as default.
- Top viewed/analytics sources without real metrics.

## Final Report Checklist

- State JSON columns added.
- State source types implemented.
- State selection storage format.
- Confirm no `PublicDisplaySection` or `PublicLooper` model.
