# Seeders and Demo Data Blueprint

Using Filament Blueprint, produce an implementation plan for a Filament v5 application feature: seeders and demo-data strategy.

The plan should:
- Describe the primary user flows end to end.
- Map each domain/configuration concept and flow to concrete Filament primitives such as Settings Pages, Resources, Pages, Relation Managers, Actions, Builder blocks, Repeaters, FileUpload, RichEditor, and Livewire components.
- Identify configuration/state transitions and the actions that trigger them.
- Identify public Livewire/Blade flows and admin Filament flows.
- Identify tests, security rules, and out-of-scope boundaries.

## Goal

Separate production-safe settings/default seeders from optional demo content and document Forge-safe commands.

## Dependencies

- Existing `DatabaseSeeder`.
- Existing `DemoHebrewContentSeeder`.
- JSON settings architecture.
- Docs: https://laravel.com/docs/13.x/seeding, https://laravel.com/docs/13.x/database-testing.

## Primary User/Admin Flows

- Developer deploys production app and seeds only required settings/default structures.
- Developer optionally runs demo seeders in local/staging.
- Developer can clean demo data by stable prefixes/keys.
- Tests seed only required classes.

## Filament Primitive Mapping

No Filament primitives required except settings defaults that later render in Settings Pages.

## JSON Settings/Configuration Shape

Seed production-safe default JSON for:

- card template families
- menu/header defaults
- about page disabled/minimal defaults
- public forms definitions if approved
- route labels
- display defaults
- transcription policy default

## Models/Migrations

No new model. Use existing models/settings.

## Casts/Enums/Support Classes

Seeder classes only:

- `PublicFrontSettingsSeeder`
- optional split demo seeders such as `DemoHebrewTaxonomySeeder`, `DemoHebrewContentSeeder`, `DemoHebrewPublicFrontSeeder`

## Relationships

Respect existing relationships and public visibility constraints.

## Filament Resources/Pages

No Resource changes.

## Form Schemas

No forms.

## Tables/Actions

No tables/actions.

## Public Pages/Livewire/Blade

Defaults must allow public pages to render without demo content.

## Settings

Use settings defaults and/or production-safe seeder. Do not rely on demo content for settings structures.

## Seeders

Recommended structure:

- `DatabaseSeeder`: production-safe user/defaults only, no demo content in production.
- `PublicFrontSettingsSeeder`: default JSON structures.
- `DemoHebrewContentSeeder`: optional demo content.
- Optional cleanup documentation or command for demo reference keys.

Forge-safe examples to document in implementation:

```bash
php artisan db:seed --class=PublicFrontSettingsSeeder --force
php artisan db:seed --class=DemoHebrewContentSeeder
```

Do not run demo seeder with `--force` in production unless explicitly approved.

## Tests

- Production settings seeder idempotent.
- Demo seeder idempotent.
- Cleanup strategy removes demo-prefixed records.
- Tests can call specific seeders.

## Security

Never seed secrets, real credentials, private URLs, or personal data. Demo submissions, if any, must be fake and clearly marked.

## State/Configuration Transitions

- Fresh install: settings defaults created.
- Optional demo seed: demo records added.
- Cleanup: demo records removed by stable reference keys/prefixes.

## Out Of Scope

- Running seeders.
- Data migration from existing real content.
- Prompt 13 dashboard changes.

## Quality Gate

Implementation later runs seeder idempotence tests and full quality gate.

## Final-Report Checklist

- State seeders created/changed.
- State production-safe commands.
- State demo commands and cleanup.
- State whether `DatabaseSeeder` calls demo seeders.
