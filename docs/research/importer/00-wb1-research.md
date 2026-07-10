# Importer Workbench WB1 Research

## Local preflight

- `git status --short --branch`: clean `main...origin/main` before work.
- `git log --oneline --decorate -20`: confirms `5b3593c feat: add import result report and maintenance hardening`, `ada29fb feat: add settings import locks and add-only mode`, and `f694c49 feat: add settings backup versions and restore`.
- `php artisan migrate:status`: all existing migrations are applied locally.
- Gate note applied: S1 is split across S1a/S1b with S1c/S1d follow-ups; the ledger records the Importer Workbench gate as open.

## Binding docs read before code

- `prompts/pre-13-prompts/importer-workbench-wb1-codex-prompt.md`
- `docs/phase-02/importer-workbench-plan.md`
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-step10r-next-implementation-sequence.md`
- `docs/phase-02/current-project-state.md`
- `.ai/guidelines/import-export.md`
- `.ai/guidelines/tooling-quality.md`
- `.ai/guidelines/settings-dashboard.md`
- `.ai/guidelines/media-embeds.md`
- `.ai/guidelines/transcriptions.md`

## Laravel Boost

- `application_info`: Laravel 13.18.0, Filament 5.6.7, Livewire 4.3.3, Pest 4.7.4, PHP 8.4, MySQL local database.
- `database_schema` summary: no existing `import_connections` table; current settings/import/export tables remain native Filament import/export baseline.
- `search_docs` results used:
  - Laravel encrypted casts: `encrypted:array` is appropriate and requires a `TEXT` or larger column because encrypted payload length is not predictable.
  - Laravel Artisan signatures/options and storage APIs apply to `importer:probe-formats`.
  - Filament 5 navigation grouping supports class-based pages/resources with navigation label/group/sort and enum icons.
  - Filament 5 custom pages can use schema/table traits, action schemas, modal content, and notifications.
  - Filament 5 FileUpload docs flag schema-restricted uploads; WB1 avoids storing credential uploads in tracked files and keeps service-account JSON as text for the initial foundation.

## FilamentExamples MCP

Access level: `search_examples` only. No source/read/fetch/details tool was exposed.

### First pass

- Queries: custom settings page manage records, custom page table records/actions, progressive disclosure provider/auth fields, FileUpload JSON credentials, navigation group custom page sort.
- Relevant example: `v4/full-projects/hotel-management-bookings/app/Filament/Hotel/Pages/MyHotel.php`
  - Pattern to copy: Filament `Page` with a schema-backed form, save action, and notification.
  - Pattern to avoid: direct unvalidated attribute writes; WB1 normalizes credentials/settings in app support code and model validation helpers.
  - PodText adaptation: importer settings page will manage many `ImportConnection` records, so the page should combine a table with create/edit/test actions rather than a single settings form.
- Relevant example: `v4/full-projects/hotel-management-bookings/app/Filament/Booking/Pages/FindHotel.php`
  - Pattern to copy: custom page combining form state and a table, with table record actions.
  - Pattern to avoid: external API calls directly in UI closures; WB1 routes connection tests through `ConnectionTester` and connector services that can be faked in tests.
  - PodText adaptation: table actions create/edit/delete/test importer connections and reset the table after mutations.

### Refined pass

- Queries: `Select live visible` conditional fields, page header action schema, table record action schema/fill form, FileUpload constraints, Textarea JSON settings.
- Relevant example: `v4/full-projects/schedule-for-doctors/app/Filament/Pages/ManageDoctorSchedule.php`
  - Pattern to copy: header actions with `schema()`, record actions with `fillForm()`, action methods that send notifications and reset the table.
  - Pattern to avoid: mixing provider-specific behavior directly into the page; WB1 keeps Google/Spotify behavior in `app/Support/Importer`.
  - PodText adaptation: provider and auth-type fields use `live()` + `visible()` chains; defaults stay in a collapsed section; test action renders a finite proof summary without exposing secrets.
- Relevant example: `v4/full-projects/schedule-for-doctors/app/Filament/Resources/Doctors/Schemas/DoctorForm.php`
  - Pattern to copy: explicit `FileUpload` disk/directory constraints where uploads are used.
  - Pattern to avoid: unconstrained uploads; WB1 keeps credential input text-only, and any later file field must copy PodText's accepted type/size/disk/visibility pattern.

## Implementation notes

- Do not use `Filament\Actions\ImportAction`, `Filament\Actions\Imports\Importer`, or custom CSV controllers. WB1 is a custom importer workbench foundation only.
- Credentials must not be logged or written to tracked docs. Test credentials are fake payloads only.
- Google/Spotify connector tests should bind fake client factories and avoid network.
- The probe command writes raw samples under `storage/app/importer/probe/` and only writes structural findings to tracked docs.
