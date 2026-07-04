# Transcription Publication Policy Blueprint

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: transcription publication policy setting.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Add a settings-backed policy controlling whether a content item may have more than one published transcription.

## Dependencies

- JSON settings architecture.
- Existing `ContentItem.featured_transcription_id`.
- Existing `Transcription` status/published rules.
- Existing public item transcript viewer.
- Docs: https://laravel.com/docs/13.x/validation, https://filamentphp.com/docs/5.x/resources, https://filamentphp.com/docs/5.x/actions/import.

## Primary User/Admin Flows

- Admin toggles "allow multiple published transcriptions per item".
- Admin creates/edits/imports a transcription.
- If policy disallows multiples, publishing a second sibling is blocked or handled by an explicit action.
- Admin selects featured transcription as public one.
- Public item page shows one or multiple tabs depending on policy.

## Filament Primitive Mapping

- Settings Page: policy toggle.
- Field: `Filament\Forms\Components\Toggle`, Validation: boolean, Config: default true unless user chooses false.
- Resource: update existing Transcription Resource and ContentItem transcriptions relation manager.
- Field: `Filament\Forms\Components\Select`, Validation: same content item for featured, Config: choose featured transcription.
- Action: `Filament\Actions\Action`, Location: relation manager row, Behavior: make featured / optionally unpublish others if user approves.

## JSON Settings/Configuration Shape

```json
{
  "transcription_policy": {
    "allow_multiple_published_transcriptions_per_item": true,
    "public_selection": "featured_then_latest"
  }
}
```

## Models/Migrations

Do not create a model. Do not add a database constraint in v1 unless the user accepts cross-database limitations and data cleanup.

## Casts/Enums/Support Classes

- `TranscriptionPublicationPolicyReader`.
- `TranscriptionPublicationPolicy`.
- Optional enum for `public_selection`.

## Relationships

Use existing `ContentItem` -> `Transcription` and featured transcription relationship.

## Filament Resources/Pages

Update existing Transcription Resource form validation and ContentItem relation manager create/edit actions.

## Form Schemas

- Status field validation must consult policy when setting status to published.
- Published at field remains day-first/Asia Jerusalem where currently expected.
- Featured transcription selection must restrict to same item.

## Tables/Actions

- Row Action: make featured.
- Optional Action: publish and unpublish other published transcriptions, only if user chooses auto-replace behavior.

## Public Pages/Livewire/Blade

If policy allows multiples, existing tabs remain. If disallowed, public viewer uses featured transcription only and avoids rendering tab list unless useful.

## Settings

Default needs user decision:

- `true`: preserves current Prompt 12 behavior.
- `false`: simpler production behavior but needs cleanup and stricter imports.

## Seeders

No demo changes unless sample data intentionally demonstrates multiple transcriptions.

## Tests

- True setting allows current multiple published transcriptions.
- False setting blocks second published transcription.
- Featured transcription is public-selected.
- Import row fails safely when policy would be violated.
- Public page tab behavior matches policy.

## Security

Editorial policy only. Public visibility constraints remain unchanged and mandatory.

## State/Configuration Transitions

- Setting true -> false: existing invalid records need report/cleanup before enforcement.
- Transcription draft -> published: validation checks sibling published state.
- Make featured: updates content item featured FK.

## Out Of Scope

- Per-group policy.
- Database partial unique indexes.
- Automatic cleanup without user approval.

## Quality Gate

Implementation later runs resource/relation manager/import tests and full quality gate.

## Final-Report Checklist

- State chosen default.
- State enforcement points.
- State public tab behavior.
- State import behavior.
