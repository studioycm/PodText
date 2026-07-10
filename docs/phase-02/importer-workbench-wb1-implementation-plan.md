# Importer Workbench WB1 Implementation Plan

## Scope

Implement only WB1 from `docs/phase-02/importer-workbench-plan.md`:

- `import_connections` schema/model/factory.
- Google Drive and Spotify connector boundaries under `app/Support/Importer`.
- Socialite Google OAuth redirect/callback routes that store refresh credentials on a connection.
- Custom Filament admin Importer Settings page in nav group "ייבוא".
- `importer:probe-formats` command and initial findings document.
- Ledger WB-track section, sequence pointer, current-state update, and WB1 handoff.

## Out of scope

- Import runs, steps, datasets, studio builder pages, resolvers, transcript fetching at scale, apply/journal, rollback, media fetching, public path changes.
- Filament ImportAction/Importer classes.
- Real credentials in tracked files/logs.
- Any new dependency beyond `google/apiclient` and `laravel/socialite`.

## Implementation Steps

1. Backfill S1d docs state from "pending local commit" to `5b3593c`.
2. Add dependencies with Composer.
3. Add enums and model:
   - `ImportConnectionProvider`
   - `ImportConnectionAuthType`
   - `ImportConnectionStatus`
   - `ImportConnection` with `encrypted:array` credentials and normalized settings.
4. Add migration and factory for `import_connections`.
5. Add support services:
   - connector contracts/client factories;
   - `GoogleDriveConnector`;
   - `SpotifyConnector`;
   - `ConnectionTester`;
   - transcript probe analyzer/writer.
6. Add OAuth routes/controller using Socialite Google with Drive/Sheets scopes and offline access.
7. Add `ImporterSettings` Filament page:
   - table with create/edit/delete/test actions;
   - progressive provider/auth fields;
   - defaults collapsed section;
   - translated labels/helpers in English and Hebrew.
8. Add `importer:probe-formats` command.
9. Add focused Pest tests for model encryption/normalization, connectors with fakes, admin page behavior, nav order, and probe command.
10. Update ledger, sequence, current state, findings skeleton, and handoff.
11. Run sequential gates:
    - `php artisan migrate --no-interaction`
    - focused tests
    - `vendor/bin/pint --dirty --format agent`
    - `php artisan test`
    - `vendor/bin/pint --test`
    - `vendor/bin/filacheck`
    - `npm run build`
    - `git diff --check`
12. Commit only if all gates pass: `feat: add importer connections foundation`.

## Verification Focus

- Raw database credentials value must not contain plaintext fake secret material.
- Connection test action must be wired and fakeable.
- Provider/auth fields must stay hidden until prerequisites are selected.
- Navigation map completeness test must include the new importer page.
- Probe command must be resumable by skipping existing sample files and must generate the findings doc skeleton without network in tests.
