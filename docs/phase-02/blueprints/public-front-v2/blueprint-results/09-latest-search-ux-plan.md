# Blueprint Result: Latest And Search UX

Source blueprint: `docs/phase-02/blueprints/public-front-v2/09-latest-search-ux-blueprint.md`

Generated with Laravel Boost context and Filament Blueprint planning docs.

## Commands

```bash
php artisan make:enum SearchFilterPanelMode --no-interaction
php artisan make:enum LatestPaginationMode --no-interaction
php artisan make:livewire Public/LatestContentLooper --no-interaction
php artisan make:test LatestSearchUxTest --pest --no-interaction
php artisan make:test SearchFilterDrawerTest --pest --no-interaction
```

No model/resource/migration command unless the looper JSON columns are not yet implemented.

## Models

Use existing:

- `App\Models\ContentItem`
- `App\Models\HomepageSection`

Settings update:

- `App\Settings\PublicContentSettings`: add `display_defaults` and/or latest/search config arrays through JSON architecture.

## Resources And Pages

Settings Page:

- Update `App\Filament\Pages\PublicContentSettings`.

Homepage section form:

- If looper step is implemented, configure latest per section through `App\Filament\Resources\HomepageSections\Schemas\HomepageSectionForm`.

Field: `Filament\Forms\Components\TextInput`

- Docs: https://filamentphp.com/docs/5.x/forms/text-input
- Validation:
  - latest total limit: `required|integer|min:50`
  - latest page size: `required|integer|min:4|max:25`
  - title lines: `required|integer|min:1|max:5`
  - description lines: `required|integer|min:0|max:4`
- Config: numeric/integer min/max.

Field: `Filament\Forms\Components\Select`

- Docs: https://filamentphp.com/docs/5.x/forms/select
- Validation: `required|string|in:<registry values>`
- Config:
  - pagination mode
  - card template
  - podcast display mode
  - filter panel mode

Field: `Filament\Forms\Components\Toggle`

- Docs: https://filamentphp.com/docs/5.x/forms/toggle
- Validation: `boolean`
- Config: description visibility and filter visibility toggles.

Action: `Filament\Actions\Action`

- Docs: https://filamentphp.com/docs/5.x/actions/modals
- Location: public search filter trigger only if using Filament modal/slide-over.
- Visibility: guests.
- Authorization: guest.
- Behavior: open filter form, validate values, apply to Livewire URL-backed state.

## Support Classes

Create:

- `App\Support\PublicFront\Latest\LatestLooperConfigReader`
- `App\Support\PublicFront\Search\SearchFilterConfigReader`

Enums:

- `App\Enums\LatestPaginationMode`
- `App\Enums\SearchFilterPanelMode`

## Authorization

- Settings editing: authenticated admin only.
- Public latest/search: guests with public-safe queries.

## Widgets

None.

## Public Livewire And Blade

Update:

- `App\Livewire\Public\ContentItemSearch`
- `resources/views/livewire/public/content-item-search.blade.php`
- `resources/views/components/public/content-item-card.blade.php`

Create optional:

- `App\Livewire\Public\LatestContentLooper`
- `resources/views/livewire/public/latest-content-looper.blade.php`

Latest behavior:

- Heading row.
- Lightweight search input.
- Next/previous controls on top row.
- Load more at bottom.
- No heavy filters.
- Total query size at least 50.
- Page size from 4 to 25.

Search behavior:

- Search and sort visible.
- Filters behind action/button.
- Drawer or slide-over.
- Category toggle buttons.
- Tag chips/buttons.
- Active filter count badge.
- Clear all.
- URL state preserved.

Card layout:

- full square cropped image for latest template.
- fixed image track or stacked layout.
- `min-w-0` on text containers.
- line clamps from semantic settings.
- no raw classes from JSON.

## Tests

- latest total limit min 50.
- latest page size validates 4-25.
- next/previous and load more update state.
- filter count updates as filters change.
- clear all resets URL-backed filters.
- no public Filament Table markup regression.
- card layout uses safe deterministic class mappings.

## Security

- Search input is parameterized and escaped.
- Query uses `PublicContentItemQueries`.
- No transcript body default search.
- No raw SQL in config.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Out Of Scope

- Infinite scroll default.
- Analytics/top viewed.
- Transcript body full-text search.

## Final Report Checklist

- State latest settings.
- State filter panel implementation.
- State card layout safeguards.
- State Prompt 11R regression coverage.
