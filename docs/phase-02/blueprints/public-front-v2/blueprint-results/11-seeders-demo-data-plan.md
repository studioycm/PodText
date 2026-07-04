# Blueprint Result: Seeders And Demo Data

Source blueprint: `docs/phase-02/blueprints/public-front-v2/11-seeders-demo-data-blueprint.md`

Generated with Laravel Boost context and Filament Blueprint planning docs.

## Commands

```bash
php artisan make:seeder PublicFrontSettingsSeeder --no-interaction
php artisan make:seeder DemoHebrewPublicFrontSeeder --no-interaction
php artisan make:test PublicFrontSettingsSeederTest --pest --no-interaction
php artisan make:test DemoHebrewContentSeederIdempotenceTest --pest --no-interaction
```

Optional only if user approves a cleanup command:

```bash
php artisan make:command DemoContentCleanupCommand --no-interaction
```

## Models

Use existing:

- `App\Settings\PublicContentSettings`
- `App\Models\Author`
- `App\Models\Category`
- `App\Models\ContentGroup`
- `App\Models\ContentItem`
- `App\Models\ContentTag`
- `App\Models\HomepageSection`
- `App\Models\Transcription`

No new model.

## Resources And Pages

No Filament Resource/Page changes.

## Seeders

Create: `Database\Seeders\PublicFrontSettingsSeeder`

Purpose: production-safe defaults only.

Seeds:

- JSON card template defaults.
- JSON menu/header defaults.
- About page disabled/minimal defaults.
- Public forms default definitions only if approved, preferably disabled.
- Route labels.
- Display defaults.
- Transcription policy default.

Update: `Database\Seeders\DatabaseSeeder`

- Do not automatically call demo seeders in production.
- If local demo call is retained or added, guard by environment and document clearly.

Update or split: `Database\Seeders\DemoHebrewContentSeeder`

- Keep idempotent behavior.
- Ensure demo reference keys/slugs/titles are consistently identifiable.
- Optionally split public-front display defaults into `DemoHebrewPublicFrontSeeder`.

Optional: `Database\Seeders\DemoHebrewPublicFrontSeeder`

- Demo-only homepage/menu/about/forms/card settings.
- Do not use real personal data.

Forge-safe commands to document:

```bash
php artisan db:seed --class=PublicFrontSettingsSeeder --force
php artisan db:seed --class=DemoHebrewContentSeeder
```

Do not recommend demo seeder `--force` in production.

## Authorization

Seeder execution is CLI/deploy responsibility. No runtime authorization changes.

## Widgets

None.

## Public Livewire And Blade

Production defaults must let public pages render without demo content.

## Tests

- `PublicFrontSettingsSeeder` is idempotent.
- Production-safe defaults do not create demo content.
- Demo seeder is idempotent.
- Demo records can be identified by reference keys/prefixes.
- Tests can seed specific classes without relying on full `DatabaseSeeder`.

## Security

- Do not seed secrets, API keys, license data, passwords beyond controlled local admin fixtures, private URLs, or real personal submissions.
- Demo public form submissions, if ever added, must be fake and clearly marked.

## Quality Gate

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

## Out Of Scope

- Running seeders.
- Migrating real content.
- Prompt 13 dashboard widgets.

## Final Report Checklist

- State seeders created/updated.
- State whether `DatabaseSeeder` calls demo seeders.
- State production and demo commands.
- State cleanup strategy.
