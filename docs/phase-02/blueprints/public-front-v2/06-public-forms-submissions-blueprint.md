# Public Forms and Submissions Blueprint

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: configurable public forms and submissions.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Store public form definitions in settings JSON and, if approved, store actual public submissions as transactional records.

## Dependencies

- JSON settings architecture.
- Public menu/header manager for form triggers.
- Docs: https://filamentphp.com/docs/5.x/actions/modals, https://filamentphp.com/docs/5.x/forms/builder, https://laravel.com/docs/13.x/validation.

## Primary User/Admin Flows

- Admin defines a public form in settings.
- Admin adds field blocks and enables the form.
- Admin attaches the form to a menu item with modal or slide-over display.
- Guest opens the form and submits.
- Runtime registry validates payload.
- If submission storage is approved, a `PublicFormSubmission` record is created.
- Admin reviews submissions in a Resource and changes status.

## Filament Primitive Mapping

- Settings Page: form definitions.
- Field: `Filament\Forms\Components\Builder`, Validation: field block list, Config: text/email/phone/textarea/select/checkbox/toggle/url.
- Field: `Filament\Forms\Components\Select`, Validation: registry keys, Config: field type/options/display mode.
- Action: `Filament\Actions\Action`, Location: public header/menu, Visibility: enabled form only, Authorization: guest allowed, Behavior: modal/slide-over form submit.
- Resource: optional `PublicFormSubmissionResource`, Location: `App\Filament\Resources\PublicFormSubmissions`, Docs: https://filamentphp.com/docs/5.x/resources.

## JSON Settings/Configuration Shape

```json
{
  "public_forms": {
    "definitions": [
      {
        "key": "request-transcription",
        "name": "Request transcription",
        "heading": "",
        "description": "",
        "submit_label": "",
        "success_message": "",
        "display_mode_default": "modal",
        "enabled": true,
        "fields": []
      }
    ]
  }
}
```

## Models/Migrations

Do not create `PublicFormDefinition`.

Allowed exception if storage is approved:

`PublicFormSubmission`

- `form_key` string indexed.
- `form_name_snapshot` string nullable.
- `payload` JSON/text.
- `status` string default `new`.
- `submitted_at` datetime indexed.
- `source_url` text nullable.
- `metadata` JSON/text nullable.
- timestamps.

## Casts/Enums/Support Classes

- `PublicFormFieldType` enum.
- `PublicFormSubmissionStatus` enum.
- `PublicFormDefinitionRegistry`.
- `PublicFormSchemaFactory`.
- `PublicFormPayloadValidator`.
- `PublicFormSubmissionPresenter`.

## Relationships

No relationships in v1.

## Filament Resources/Pages

If submissions are stored:

- Resource table for submissions.
- Create is disabled.
- Edit may be status-only or view+status.
- Delete/archive based on policy.

## Form Schemas

Definition fields:

- Key TextInput: required, alpha_dash, unique within settings array.
- Heading/Text/Description.
- Submit label.
- Enabled Toggle.
- Field Builder blocks.

Runtime fields:

- Generated only from registry.
- No arbitrary validation rule strings.
- File field deferred.

## Tables/Actions

Submission table:

- Column: form key/name, status badge, submitted at day-first, source URL/domain, payload summary.
- Action: mark reviewed.
- Action: archive.
- Bulk action: archive selected.

## Public Pages/Livewire/Blade

Use a class-based Livewire component or Filament Action for public modal/slide-over. Disabled forms cannot submit, even if stale markup exists.

## Settings

Definitions live in settings JSON.

## Seeders

Production-safe defaults may include disabled starter form definitions.

## Tests

- Definition registry validates supported fields.
- Enabled form submits.
- Disabled form rejects.
- Required/email/url/select validations work.
- Submission stored with payload JSON and status `new`.
- Admin resource escapes payload.

## Security

Whitelist fields and validation. Escape payload values. Add rate limiting/honeypot before production. File uploads deferred unless explicitly approved.

## State/Configuration Transitions

- Admin saves definition JSON.
- Public opens form: definition becomes runtime schema.
- Submit: payload validated and stored.
- Admin review: status transitions `new -> reviewed -> archived`.

## Out Of Scope

- File uploads.
- Email notifications unless user approves.
- Conditional fields beyond simple visibility.
- Public account-bound submissions.

## Quality Gate

Implementation later runs full gate and public guest submission tests.

## Final-Report Checklist

- State form fields supported.
- State whether submission model was implemented.
- Justify submission exception.
- State rate-limit/honeypot decision.
- Confirm no `PublicFormDefinition` model.
