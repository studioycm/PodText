# Public Front v2 Implementation Sequence Blueprint

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: Public Front v2 implementation sequence before Prompt 13.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Define the recommended sequence for implementation prompts after Prompt 12 and before resuming Prompt 13 dashboard metrics.

## Dependencies

- User approval of this research/blueprint pack.
- Resolution of open questions in `docs/phase-02/public-front-v2-open-questions.md`.
- Current Prompt 12 code and docs remain source of truth.

## Recommended Order

1. JSON settings architecture and renderer/validator conventions.
2. Card template builder foundation.
3. Public display sections / loopers.
4. Latest/search UX repair.
5. Public menu/header manager.
6. Configurable public forms/submissions.
7. About page content/team builder.
8. Podcasts/group page refinements.
9. Transcriber/top-transcriber refinements.
10. Seeder cleanup.
11. Transcription publication policy setting.
12. Prompt 13 dashboard metrics only after user approves.

## Why This Order

- JSON settings and registries must exist before storing large public-front configuration.
- Card templates are needed by latest, groups, loopers, and contributor previews.
- Loopers provide the source/display system for Latest.
- Search/latest fixes are high user-facing value and exercise card/layout assumptions.
- Menu/forms/about can then reuse settings and public action patterns.
- Group and contributor refinements reuse templates and source resolvers.
- Seeder cleanup should happen after final default JSON shapes are known.
- Transcription policy should wait until dashboard metrics can surface data conflicts or the user chooses a default.

## Filament Primitive Mapping

Each implementation prompt should include only the relevant primitives:

- Settings Pages for JSON config.
- HomepageSection Resource for loopers.
- Public Filament Pages for About/groups if needed.
- Actions for form modal/slide-over and admin selection workflows.
- Builder/Repeater for JSON arrays.
- Optional `PublicFormSubmissionResource` only for actual submissions.

## JSON Settings/Configuration Shape

All implementation prompts must reuse the architecture blueprint naming:

- site-level arrays in settings
- section-level JSON on `HomepageSection`
- normalized readers before rendering

## Models/Migrations

Default no new settings models. The only pre-approved exception candidate is `PublicFormSubmission`, and it still requires user confirmation that submission persistence is in scope.

## Casts/Enums/Support Classes

Create small focused registries/readers per domain. Avoid broad `ContentService` style classes.

## Relationships

Keep internal domain names:

- `ContentGroup`
- `ContentItem`
- `Author`
- `Transcription`

## Filament Resources/Pages

Use Filament 5 APIs only. Existing public listings remain custom Livewire/Blade, not Filament Tables.

## Form Schemas

Every JSON editing form uses whitelisted values, helper text for technical keys, and no arbitrary code/class/view/style fields.

## Tables/Actions

Use tables/actions only for admin selection or real transactional records. Do not create admin tables for settings-only JSON unless there is a model-backed exception.

## Public Pages/Livewire/Blade

Preserve:

- Prompt 11R custom Livewire state and Blade card grids/rows.
- Prompt 11B `Author` contributor discovery.
- Prompt 12 public item page, safe media component, parse-only transcript viewer.

## Settings

Use code defaults so pages render safely without settings rows. Use production-safe seeders only when defaults must be persisted for admin editing.

## Seeders

Defer final seeder split until default JSON structures stabilize.

## Tests

Each implementation prompt adds focused Pest tests for:

- settings defaults and invalid config fallback
- admin settings save behavior
- public rendering and draft hiding
- Livewire URL state
- security/sanitization
- import/form submission edge cases where relevant

## Security

Never store/render raw CSS, classes, SQL, arbitrary PHP, arbitrary Blade paths, iframe HTML, or unsafe rich HTML. Public forms need rate limiting/honeypot before production.

## State/Configuration Transitions

Every prompt final report should classify meaningful blueprint requirements as implemented, already existed, deferred, not applicable, or blocked.

## Out Of Scope

- Starting Prompt 13 without user approval.
- Installing packages.
- Replacing the public panel architecture.
- Creating Podcast/Episode models.

## Quality Gate

For future implementation prompts run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

Docs-only follow-up prompts may use `git diff --check` and `git status --short`.

## Final-Report Checklist

- State which sequence step was implemented.
- State user decisions applied.
- State Boost and FilamentExamples usage.
- State tests and quality gate.
- State current git status.
