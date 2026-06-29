# Bootstrap Slice 0 Handoff Report

## Implemented scope

Bootstrap Slice 0 implements the intended content loop:

1. Admin creates or imports authors, content groups, and content items.
2. Admin publishes content groups and content items.
3. Guest visitors browse published groups and read published item transcripts.

Internal domain names remain `Author`, `ContentGroup`, and `ContentItem`. No `Podcast` or `Episode` models, tables, or Filament resources were added.

## Routes and panels

- Public panel: `https://podtext.test`
- Admin panel: `https://podtext.test/admin`
- Public group detail: `/groups/{contentGroupSlug}`
- Public item detail: `/items/{contentGroupSlug}/{contentItemSlug}`
- Admin resources: `/admin/authors`, `/admin/content-groups`, `/admin/content-items`

## Setup commands

```bash
composer install
npm install
php artisan migrate:fresh --seed
npm run build
```

The local `.env` used for final verification had `APP_URL=https://podtext.test`, `APP_LOCALE=he`, `DB_CONNECTION=sqlite`, and `QUEUE_CONNECTION=database`.

## Administrator setup

The demo seeder creates public demo content but does not seed an administrator account. Create an administrator locally with:

```bash
php artisan make:filament-user
```

## Queue worker

Native Filament imports and exports use the `imports-exports` queue:

```bash
php artisan queue:work database --queue=imports-exports,default --tries=3 --timeout=120
```

For one-off verification, add `--stop-when-empty`.

## Import order and formats

Import CSV files in this order when starting from an empty content database:

1. `authors.csv`
2. `content-groups.csv`
3. `content-items.csv`

Example files are in `resources/import-examples/`.

Stable relationship fields:

- Authors: `reference_key`
- Content groups: `reference_key`
- Content items: `content_group_reference_key`
- Content item authors: `author_reference_keys`, separated by `|`

Content item `media_url` must be HTTPS. `embed_url` is optional, must be HTTPS, must not be HTML, and must use an approved host.

## Verification results

- `php artisan migrate:fresh --seed`: passed.
- `php artisan test`: passed, 59 tests and 384 assertions.
- `vendor/bin/pint --test`: passed.
- `npm run build`: passed.
- Native queued import/export verification: passed with `php artisan queue:work database --queue=imports-exports,default --stop-when-empty --tries=3 --timeout=120`.

Queued verification processed:

- Author import: 1 processed, 1 successful.
- Group import: 2 processed, 1 successful, 1 expected failed row for invalid status.
- Item import: 1 processed, 1 successful, relationship resolved by group and author reference keys.
- Author export: 2 processed, 2 successful.
- Group export: 2 processed, 2 successful.
- Item export: 3 processed, 3 successful.
- Remaining queued jobs: 0.
- Failed jobs: 0.

## Prompt 05 fixes

- Required HTTPS validation for content item `media_url` in the Admin form and importer.
- Added regression coverage for rejected non-HTTPS media URLs.
- Raised Pest browser assertion timeout to 30 seconds because the full Pest browser suite exceeded the default 5 second browser assertion timeout on this Windows/Chrome environment.
- Corrected the README queue-worker command to include `imports-exports`.

## Known limitations

- The final queue verification dispatched native Filament import/export jobs directly. It verified queued processing, failed rows, exports, and relationships, but did not click the Admin action modals in a browser.
- Admin notification appearance for completed imports/exports was not manually verified.
- XLSX downloads were not manually verified in the final run.
- Owner download success for generated export and failed-row files was not manually verified in the final run; tests verify unauthorized users are forbidden.
- The application was not launched through a documented long-running local command during Prompt 05; route URLs were resolved through Laravel Boost.

## Deferred features

Deferred features remain outside Bootstrap Slice 0: roles/permissions beyond secure admin access, approval workflows, provider drivers, transcript studio, comments, analytics, activity/audit logs, categories, tags, and synchronized media playback.

## Acceptance items not verified

The unchecked items in `docs/acceptance-checklist.md` remain not verified:

- The application starts from documented commands.
- Administrator sign in and sign out through the browser.
- Example CSV downloads from Admin actions.
- XLSX export downloads.
- Failed-row CSV owner download.
- Explicit transcript selection in the export UI.
- Admin database notification appearance for import/export completion.
- Owner-only generated file download success.
