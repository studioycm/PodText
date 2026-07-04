# Blueprint Result: Contributors And Transcribers UX

Source blueprint: `docs/phase-02/blueprints/public-front-v2/08-contributors-transcribers-ux-blueprint.md`

Generated with Laravel Boost context and Filament Blueprint planning docs.

## Commands

```bash
php artisan make:enum ContributorDirectoryLayout --no-interaction
php artisan make:test PublicContributorDirectoryUxTest --pest --no-interaction
php artisan make:test TopTranscribersSectionUxTest --pest --no-interaction
```

No model/resource/migration commands.

## Models

Use existing:

- `App\Models\Author`
- `App\Models\Transcription`
- `App\Models\ContentItem`
- `App\Models\ContentGroup`

Update settings:

- `App\Settings\PublicContentSettings`: add contributor JSON settings only if JSON architecture is in place.

Rejected:

- No `Transcriber` model.
- No denormalized counter table in v1.

## Resources And Pages

Settings Page:

- Update `App\Filament\Pages\PublicContentSettings`.

Field: `Filament\Forms\Components\TextInput`

- Docs: https://filamentphp.com/docs/5.x/forms/text-input
- Validation:
  - public label: `nullable|string|max:80`
  - preview latest count: `required|integer|min:1|max:15`
  - top section default count: `required|integer|min:1|max:15`
- Config: `->numeric()->integer()` for counts.

Field: `Filament\Forms\Components\Select`

- Docs: https://filamentphp.com/docs/5.x/forms/select
- Validation: `required|string|in:quarter,third`
- Config: directory layout choices.

Field: `Filament\Forms\Components\CheckboxList`

- Docs: https://filamentphp.com/docs/5.x/forms/checkbox-list
- Validation: `array|in:5,10,15`
- Config: top section page-size choices with 5, 10, 15.

## Support Classes

Update:

- `App\Support\PublicContent\PublicContributorDiscovery`

Create if needed:

- `App\Support\PublicFront\Contributors\ContributorDisplayConfigReader`

Enum:

- `App\Enums\ContributorDirectoryLayout`

Counting rules:

- Count every published transcription where the parent item is public and parent group is public.
- If the same author has two published transcriptions on one item, count two transcriptions.
- Preview item list should group by item and list both transcription names without duplicating item cards.

## Authorization

- Settings editing: authenticated admin only.
- Public contributor pages: guests, public-safe records only.

## Widgets

None.

## Public Livewire And Blade

Update:

- `App\Livewire\Public\ContributorDirectory`
- `resources/views/livewire/public/contributor-directory.blade.php`
- `resources/views/components/public/contributor-card.blade.php` or create compact variant component.
- top transcriber section in `resources/views/livewire/public/content-item-search.blade.php`

Layout:

- Desktop: compact list on right at about 25%; preview on left/main at about 75%.
- Mobile: stacked with compact list above preview or an accessible tab/selector.
- Compact card: name and number badge only; whole card click selects; no "go to page" action.
- Preview: name, transcription count, full page link, latest related items.

## Tests

- count excludes unpublished parent items/groups.
- count includes duplicate transcriptions on the same item.
- preview groups duplicate item transcriptions under one item card.
- selected contributor state is URL-backed.
- compact card has no full page action.
- full preview link exists.
- homepage top transcribers supports page sizes 5/10/15.

## Security

- Do not expose unpublished titles, transcription names, counts, or links.
- Bios continue to use safe Markdown rendering.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Out Of Scope

- Contributor account workflows.
- New model.
- Cached counters.

## Final Report Checklist

- State count query behavior.
- State duplicate transcription grouping.
- State responsive layout.
- Confirm no new contributor model.
