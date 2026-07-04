# Blueprint Result: Transcription Publication Policy

Source blueprint: `docs/phase-02/blueprints/public-front-v2/07-transcription-publication-policy-blueprint.md`

Generated with Laravel Boost context and Filament Blueprint planning docs.

## Commands

```bash
php artisan make:enum TranscriptionPublicSelectionPolicy --no-interaction
php artisan make:test TranscriptionPublicationPolicySettingsTest --pest --no-interaction
php artisan make:test TranscriptionPublicationPolicyAdminTest --pest --no-interaction
php artisan make:test TranscriptionPublicationPolicyPublicPageTest --pest --no-interaction
```

No model/migration command for the setting.

## Models

Update: `App\Settings\PublicContentSettings`

- Ensure `public array $transcription_policy = [];`

Update: `App\Models\Transcription`

- Add a focused policy validation helper only if it belongs on the model; prefer support class for cross-surface checks.
- Do not hide policy behavior in casts/accessors/observers.

Update: `App\Models\ContentItem`

- Existing `featured_transcription_id` remains the public chooser.

Rejected:

- No settings model/table.
- No partial unique index in v1 unless user approves a DB-specific cleanup/enforcement plan.

## Resources And Pages

Settings Page:

- Update `App\Filament\Pages\PublicContentSettings`.

Transcription Resource:

- Resource: `App\Filament\Resources\Transcriptions\TranscriptionResource`
- Form: `App\Filament\Resources\Transcriptions\Schemas\TranscriptionForm`
- Table: `App\Filament\Resources\Transcriptions\Tables\TranscriptionsTable`
- Docs: https://filamentphp.com/docs/5.x/resources

Relation Manager:

- `App\Filament\Resources\ContentItems\RelationManagers\TranscriptionsRelationManager`

Field: `Filament\Forms\Components\Toggle`

- Docs: https://filamentphp.com/docs/5.x/forms/toggle
- Validation: `boolean`
- Config: `transcription_policy.allow_multiple_published_transcriptions_per_item`, default requires user decision.

Field: `Filament\Forms\Components\Select`

- Docs: https://filamentphp.com/docs/5.x/forms/select
- Validation: `required|string|in:featured_then_latest`
- Config: public selection mode.

Field: existing status field in transcription form

- Validation: if setting disallows multiples and state changes to published, reject when another published sibling exists.
- Config: keep current enum options.

Action: `Filament\Actions\Action`

- Docs: https://filamentphp.com/docs/5.x/actions
- Location: Transcriptions relation manager row.
- Visibility: authenticated admin; only when transcription belongs to owner item.
- Authorization: admin can update ContentItem and Transcription.
- Behavior:
  1. Set owner `ContentItem.featured_transcription_id` to selected transcription.
  2. If optional replace flow is approved, unpublish siblings explicitly.
  3. Notify success.

## Support Classes

Create:

- `App\Support\PublicFront\Transcriptions\TranscriptionPublicationPolicyReader`
- `App\Support\PublicFront\Transcriptions\TranscriptionPublicationPolicyValidator`

Enum:

- `App\Enums\TranscriptionPublicSelectionPolicy`

## Authorization

- Settings: authenticated admin only.
- Transcription update/make featured: authenticated admin only.
- Public item viewer: guests, but only public-safe item/transcription records.

## Widgets

None in this step. Dashboard warnings belong to Prompt 13 or a later approved prompt.

## Public Livewire And Blade

Update:

- `App\Livewire\Public\ContentItemTranscriptViewer`
- `resources/views/livewire/public/content-item-transcript-viewer.blade.php`

Behavior:

- If multiple published allowed: keep Prompt 12 tab behavior.
- If disallowed: render only featured/effective transcription.
- Missing/invalid featured transcription falls back to latest published transcription only if it is public-safe.

## Imports

Update any Transcription importer:

- If policy disallows multiples, fail import row when it would create a second published transcription for the same item.
- Failure should be explicit and should not mutate existing rows.

## Tests

- setting true preserves multiple published tabs.
- setting false blocks second published transcription in resource form.
- setting false blocks second published transcription in relation manager.
- importer fails row safely when policy violated.
- make featured action updates content item.
- public page renders one/multiple tabs according to policy.

## Security

Policy does not replace public visibility rules. Public query must still require published group, published item, and published effective transcription.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Out Of Scope

- Per-group policy.
- Automatic cleanup.
- DB uniqueness constraint.

## Final Report Checklist

- State chosen default.
- State enforcement surfaces.
- State public tab behavior.
- State import behavior.
