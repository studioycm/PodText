# Blueprint Result: Public Forms And Submissions

Source blueprint: `docs/phase-02/blueprints/public-front-v2/06-public-forms-submissions-blueprint.md`

Generated with Laravel Boost context and Filament Blueprint planning docs.

## Commands

If submission persistence is approved:

```bash
php artisan make:model PublicFormSubmission -m --no-interaction
php artisan make:filament-resource PublicFormSubmission --no-interaction
php artisan make:enum PublicFormFieldType --no-interaction
php artisan make:enum PublicFormSubmissionStatus --no-interaction
php artisan make:livewire Public/PublicFormModal --no-interaction
php artisan make:test PublicFormsSettingsTest --pest --no-interaction
php artisan make:test PublicFormSubmissionTest --pest --no-interaction
php artisan make:test PublicFormSubmissionResourceTest --pest --no-interaction
```

If submission persistence is not approved, skip model/resource/migration commands and only implement settings definitions plus disabled public form UI.

## Models

Update: `App\Settings\PublicContentSettings`

- Ensure `public array $public_forms = [];`

Rejected model:

- `PublicFormDefinition`

Conditional model: `App\Models\PublicFormSubmission`

- Justification: submissions are transactional user-generated records requiring status, review, timestamps, and queryability.
- Fillable: `form_key`, `form_name_snapshot`, `payload`, `status`, `submitted_at`, `source_url`, `metadata`.
- Casts:
  - `payload` => array
  - `metadata` => array
  - `submitted_at` => datetime
  - `status` => `PublicFormSubmissionStatus`

Migration:

- `id`
- `form_key` string indexed
- `form_name_snapshot` string nullable
- `payload` JSON/text
- `status` string default `new`, indexed
- `submitted_at` timestamp indexed
- `source_url` text nullable
- `metadata` JSON/text nullable
- timestamps

## Resources And Pages

Settings Page:

- Update `App\Filament\Pages\PublicContentSettings`.

Submission Resource if persistence approved:

- Resource: `App\Filament\Resources\PublicFormSubmissions\PublicFormSubmissionResource`
- Docs: https://filamentphp.com/docs/5.x/resources
- Disable create.
- Edit page allows status update and safe payload review.

Field: `Filament\Forms\Components\Builder`

- Docs: https://filamentphp.com/docs/5.x/forms/builder
- Validation: `nullable|array`
- Config: form field blocks: text, email, phone, textarea, select, checkbox, toggle, url.

Field: `Filament\Forms\Components\TextInput`

- Docs: https://filamentphp.com/docs/5.x/forms/text-input
- Validation:
  - form key: `required|string|alpha_dash|max:80`
  - field key: `required|string|alpha_dash|max:80`
  - labels: `required|string|max:120`
  - URLs: use safe URL validation.

Field: `Filament\Forms\Components\Textarea`

- Docs: https://filamentphp.com/docs/5.x/forms/textarea
- Validation: `nullable|string|max:1000`
- Config: descriptions and success messages.

Field: `Filament\Forms\Components\Select`

- Docs: https://filamentphp.com/docs/5.x/forms/select
- Validation: `required|string|in:<registry values>`
- Config: field type, display mode, select options.

Field: `Filament\Forms\Components\Toggle`

- Docs: https://filamentphp.com/docs/5.x/forms/toggle
- Validation: `boolean`
- Config: enabled, required, default booleans.

Action: `Filament\Actions\Action`

- Docs: https://filamentphp.com/docs/5.x/actions/modals
- Location: public menu/header trigger.
- Visibility: guests when form enabled.
- Authorization: guest allowed with rate limiting/honeypot if enabled.
- Behavior:
  1. Resolve form by key from settings.
  2. Generate runtime schema from registry.
  3. Validate payload.
  4. Store submission if persistence approved.
  5. Show success message.

Columns for Resource table:

- Column: `Filament\Tables\Columns\TextColumn`, Docs: https://filamentphp.com/docs/5.x/tables/columns/text, Config: `form_key`, searchable.
- Column: `Filament\Tables\Columns\TextColumn`, Config: `status`, `->badge()`.
- Column: `Filament\Tables\Columns\TextColumn`, Config: `submitted_at`, `->dateTime('d/m/Y H:i')`, sortable.
- Column: `Filament\Tables\Columns\TextColumn`, Config: safe payload summary, limit length.

Actions:

- `Filament\Actions\Action` mark reviewed.
- `Filament\Actions\Action` archive.
- `Filament\Actions\BulkAction` archive selected.

## Support Classes

Create:

- `App\Support\PublicFront\Forms\PublicFormDefinitionRegistry`
- `App\Support\PublicFront\Forms\PublicFormSchemaFactory`
- `App\Support\PublicFront\Forms\PublicFormPayloadValidator`
- `App\Support\PublicFront\Forms\PublicFormSubmissionPresenter`

Enums:

- `App\Enums\PublicFormFieldType`
- `App\Enums\PublicFormSubmissionStatus`

## Authorization

- Settings editing: authenticated admin only.
- Submission resource: authenticated admin only.
- Public submission: guest allowed only for enabled form definitions.

## Widgets

None in this step.

## Public Livewire And Blade

Create public form modal/slide-over component if Filament Action integration is not enough:

- `App\Livewire\Public\PublicFormModal`
- `resources/views/livewire/public/public-form-modal.blade.php`

Disabled forms must fail server-side, not only hide in UI.

## Tests

- settings definition accepts valid field blocks.
- unknown field type rejected or ignored safely.
- enabled form submits and stores payload.
- disabled form cannot submit.
- required/email/url/select validation works.
- admin resource escapes payload.
- status transitions: new, reviewed, archived.

## Security

- No arbitrary validation class/rule names.
- No file uploads in v1.
- Escape payload in admin.
- Add rate limiting/honeypot before production enabling.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Out Of Scope

- File uploads.
- Email notifications unless separately approved.
- Conditional fields.

## Final Report Checklist

- State whether `PublicFormSubmission` was implemented.
- Justify submission exception if implemented.
- State supported field types.
- Confirm no `PublicFormDefinition` model.
