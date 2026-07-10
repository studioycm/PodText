# Importer Workbench WB1 Handoff

## Scope

WB1 opens the Importer Workbench track only. It adds encrypted importer connections,
Google Drive and Spotify connector boundaries, a custom Importer Settings admin page,
Google OAuth storage, and the transcript-format probe command. WB2 studio/run/dataset
work has not started, and the Public Front v2 main queue still resumes at P2 when Yoni
chooses.

## What Changed

- Added `import_connections` with provider/auth/status enums, encrypted credentials,
  JSON settings, factory states, and provider/auth validation.
- Added app-owned connector boundaries under `app/Support/Importer`: Google Drive
  client factory/adapter, Spotify client-credentials adapter, throttle hook,
  connection tester, and transcript-format probe analyzer/writer.
- Added Google OAuth redirect/callback routes using Socialite with Drive/Sheets scopes
  and offline access. Refresh tokens are stored encrypted on the connection.
- Added the custom Filament `ImporterSettings` page under the Hebrew nav group `ייבוא`,
  with table create/edit/delete/test actions and progressive provider/auth fields.
- Added `php artisan importer:probe-formats {connection} {--ids=} {--file=} {--limit=20}`.
  Raw probe samples go to untracked storage; structural findings are tracked at
  `docs/research/importer/01-transcript-format-probe.md`.
- Added WB1 research and implementation plan docs; amended the ledger with WB1-WB7 rows
  and backfilled S1d as `5b3593c`.

## Dependency Notes

Approved WB1 dependencies were installed:

- `google/apiclient`
- `laravel/socialite`

Composer also resolved required transitive packages and patch-level dependency updates
while installing those packages. No additional first-party feature package was added.

## Requirement Classification

- Implemented: `import_connections`, encrypted credential cast, factory, Google
  service-account/OAuth boundaries, Spotify client-credentials boundary, custom
  Importer Settings page, nav group/order, OAuth callback storage, probe command,
  findings skeleton, English/Hebrew translations, focused Pest tests, ledger/current
  state/sequence docs.
- Already existed: native Filament import/export resources; they remain untouched.
- Deferred by blueprint: WB2 run/step/dataset schema and studio pages; WB3 resolvers;
  WB4 fetch/profile transformer; WB5 apply/journal/rollback; WB6 audit/delta; WB7
  Spotify source step and media fetch.
- Not applicable: public rendering changes and Filament ImportAction/Importer classes.
- Blocked: real Google/Spotify credential entry and live connection testing require
  Yoni's local Google Cloud / Spotify setup.

## Verification

- `php -l` on dirty/new PHP files
- `php artisan migrate --no-interaction`
- `php artisan test tests/Feature/ImporterWorkbenchConnectionsTest.php`
- `php artisan test tests/Feature/AdminPhase02ResourcesTest.php --filter="orders every registered admin navigation resource and page through the central map"`
- `vendor/bin/pint --dirty --format agent`
- `php artisan test`
- `vendor/bin/pint --test`
- `vendor/bin/filacheck`
- `npm run build`
- `git diff --check`

## Google Cloud Setup Instructions

1. Create a Google Cloud project or use an existing PodText-owned project.
2. Enable APIs:
   - Google Drive API
   - Google Sheets API
3. Service account path:
   - Create a service account for the importer.
   - Generate a JSON key locally.
   - In PodText admin, open `ייבוא` -> Importer Settings.
   - Create a Google Drive connection with auth type `Service account`.
   - Paste the JSON key into the service-account field.
   - Share the production spreadsheet and the images/documents folder with the service
     account email.
   - Add the target spreadsheet ID and folder ID to the defaults section.
4. OAuth path:
   - Configure the OAuth consent screen.
   - Create an OAuth Web application client.
   - Add the callback URL for this app environment:
     `/admin/importer/google/callback`.
   - Set local env values for `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and
     `GOOGLE_REDIRECT_URI`; do not commit them.
   - Create a Google Drive connection with auth type `OAuth`, save it, then use the
     table action `Connect Google OAuth`.
   - Consent with an account that has access to the sheet/folder. The refresh token is
     stored encrypted on the connection.

## Commit hash

WB1 commit message: `feat: add importer connections foundation`.

The final WB1 commit hash is reported after the local commit because this handoff is
part of that commit.

## Local Front Check Report

1. Open the admin panel and confirm the new `ייבוא` navigation group appears after
   Settings/Backups and opens Importer Settings.
2. Create a service-account Google Drive connection using the real shared sheet.
3. Press `Test connection` and confirm the tab list includes `פרקים שעלו לאוויר`.
4. Confirm the raw `import_connections.credentials` database value does not show the
   JSON private key or token in readable plaintext.
5. Run `php artisan importer:probe-formats {connection_id} --file=probe-sample.json`
   with Yoni's 20-doc stratified list.
6. Open `docs/research/importer/01-transcript-format-probe.md` and confirm document
   structure signals and candidate format profiles were written.
7. Check Hebrew RTL labels in the importer page.
8. Check light and dark admin themes for readable table/actions/form sections.

## Current Git Status

Pending final gate and local commit at handoff creation time.
