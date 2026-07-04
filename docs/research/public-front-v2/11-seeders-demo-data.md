# Public Front v2 Research: Seeders and Demo Data

## Purpose

Plan production-safe default settings seeders and optional demo-data cleanup/splitting for public-front v2.

## Topic Scope

Laravel seeders, factories, `DatabaseSeeder::call()`, `WithoutModelEvents`, demo prefixing, production safety, cleanup, and Forge-safe commands.

## Exact Search Terms Used

- Boost: "Laravel seeders DatabaseSeeder call WithoutModelEvents production force"
- Boost: "Laravel database testing seeders Pest seed method RefreshDatabase"
- FilamentExamples MCP: "Laravel Filament demo seeder factories database seeding"
- FilamentExamples MCP: "WithoutModelEvents seeder Filament"

## Boost Docs Used

- Laravel seeding docs: `make:seeder`, `DatabaseSeeder::call()`, production `--force`, and `WithoutModelEvents`.
- Laravel database testing docs: `$this->seed()` and seeding specific classes in tests.

## FilamentExamples MCP Examples Found

- Hotel booking examples include basic demo records and factories.
- Search did not expose a sophisticated demo cleanup pattern.

## Actual Files, Classes, and Snippets Observed

- Local: `database/seeders/DatabaseSeeder.php` seeds baseline content currently.
- Local: `database/seeders/DemoHebrewContentSeeder.php` creates demo authors, categories, tags, groups, items, transcriptions, media, homepage section, and is idempotent.
- Current demo data uses portable reference keys but not every visible slug/title is demo-prefixed.

## GitHub/Source Files Inspected

- LaravelDaily appointment booking article shows simple demo seeding of admin user and tracks.

## Pattern To Copy

- Split seeders by purpose.
- Use `DatabaseSeeder::call()` for explicit composition.
- Use `WithoutModelEvents` only where appropriate and documented.
- Keep production-safe defaults separate from demo content.

## Pattern To Avoid

- Do not automatically run demo seeders in production.
- Do not mix demo content with required settings defaults.
- Do not make cleanup depend on fragile human-readable titles only.

## PodText Adaptation Notes

New JSON settings defaults should be production-safe. Demo content should be optional and easy to remove by reference key/prefix.

## JSON-First Settings Recommendation

Seed only default JSON structures that must exist for first render, such as default card templates, default menu items, default public forms disabled/enabled states, and route labels. Prefer settings migrations/defaults where Spatie Settings supports them.

## Model/Table Considered

No new model. Seeder strategy applies to existing models and settings.

## Recommended Model/Schema Options

No schema. If `PublicFormSubmission` is later added, demo submissions should be optional and clearly demo-prefixed.

## Recommended Filament Patterns

No Resource changes. Add admin dashboard metrics later only if Prompt 13 needs to report demo/default state.

## Public Livewire/Blade Implications

Demo defaults should render a realistic public front but must not be required for production operation.

## Tests

- Demo seeder is idempotent.
- Production-safe settings seeder creates default JSON structures without demo content.
- Cleanup command/strategy removes demo records by stable keys.
- Tests can seed only the relevant seeder classes.

## Security Notes

Never seed real secrets, real emails/passwords, private media URLs, or API keys. Public form demo submissions should not include personal data.

## Open Questions

- Should `DatabaseSeeder` call demo content by default in local only, or never call it automatically?
- Should a demo cleanup Artisan command be introduced or keep cleanup as documented SQL/Eloquent commands?
- Which default forms should ship enabled?
